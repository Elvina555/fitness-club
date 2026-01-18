<?php
session_start();

require_once '../../config.php';
require_once '../../classes/Database.php';
require_once '../../classes/JWT.php';
require_once '../../classes/User.php';

header('Content-Type: application/json');

$userModel = new User();
$method = $_SERVER['REQUEST_METHOD'];

// проверяем авторизацию
$currentUser = $userModel->getCurrentUser();
if (!$currentUser || $currentUser['role'] !== 'client') {
  http_response_code(401);
  echo json_encode(['error' => 'Не авторизован']);
  exit;
}

$db = new Database();

try {
  if ($method === 'GET') {
    // получить все записи клиента
    $bookings = $db->fetchAll(
      "SELECT b.id, b.status, b.created_at, b.updated_at,
                    w.id as workout_id, w.title, w.description, w.workout_date,
                    w.start_time, w.end_time, w.max_participants, w.current_participants,
                    u.id as trainer_id, u.first_name as trainer_first_name,
                    u.last_name as trainer_last_name, u.specialization,
                    s.id as subscription_id, s.type as subscription_type,
                    s.visits_left, s.end_date
             FROM bookings b
             JOIN workouts w ON b.workout_id = w.id
             JOIN users u ON w.trainer_id = u.id
             LEFT JOIN subscriptions s ON b.subscription_id = s.id
             WHERE b.client_id = ?
             ORDER BY w.workout_date ASC, w.start_time ASC",
      [$currentUser['id']]
    );

    echo json_encode([
      'success' => true,
      'bookings' => $bookings
    ]);

  } elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['workout_id']) || empty($data['subscription_id'])) {
      http_response_code(400);
      echo json_encode(['error' => 'Не указаны обязательные параметры']);
      exit;
    }

    $workout_id = (int) $data['workout_id'];
    $subscription_id = (int) $data['subscription_id'];

    // проверка на активные записи
    $existing = $db->fetchOne(
      "SELECT id FROM bookings WHERE client_id = ? AND workout_id = ? AND status IN ('confirmed', 'attended')",
      [$currentUser['id'], $workout_id]
    );

    if ($existing) {
      http_response_code(400);
      echo json_encode(['error' => 'Вы уже записаны на эту тренировку']);
      exit;
    }

    $subscription = $db->fetchOne(
      "SELECT * FROM subscriptions WHERE id = ? AND client_id = ? AND status = 'active'",
      [$subscription_id, $currentUser['id']]
    );

    if (!$subscription) {
      http_response_code(400);
      echo json_encode(['error' => 'Абонемент недействителен']);
      exit;
    }

    if ($subscription['visits_left'] <= 0) {
      http_response_code(400);
      echo json_encode(['error' => 'На абонементе нет оставшихся посещений']);
      exit;
    }

    $workout = $db->fetchOne(
      "SELECT * FROM workouts WHERE id = ? AND status = 'scheduled'",
      [$workout_id]
    );

    if (!$workout) {
      http_response_code(400);
      echo json_encode(['error' => 'Тренировка недоступна']);
      exit;
    }

    if ($workout['current_participants'] >= $workout['max_participants']) {
      http_response_code(400);
      echo json_encode(['error' => 'Нет свободных мест']);
      exit;
    }

    $booking_id = $db->insert('bookings', [
      'client_id' => $currentUser['id'],
      'workout_id' => $workout_id,
      'subscription_id' => $subscription_id,
      'status' => 'confirmed'
    ]);

    if (!$booking_id) {
      throw new Exception('Не удалось создать запись');
    }

    // увеличиваем колво участников
    $db->update(
      'workouts',
      ['current_participants' => $workout['current_participants'] + 1],
      'id = ?',
      [$workout_id]
    );

    // уменьшаем колво оставшихся посещений
    $db->update(
      'subscriptions',
      ['visits_left' => $subscription['visits_left'] - 1],
      'id = ?',
      [$subscription_id]
    );

    $db->insert('notifications', [
      'user_id' => $currentUser['id'],
      'title' => 'Запись на тренировку',
      'message' => 'Вы записаны на тренировку: "' . $workout['title'] . '" (' .
        $workout['workout_date'] . ' в ' . substr($workout['start_time'], 0, 5) . ')',
      'type' => 'booking_confirmed',
      'is_read' => 0,
      'created_at' => date('Y-m-d H:i:s')
    ]);

    echo json_encode([
      'success' => true,
      'booking_id' => $booking_id,
      'message' => 'Вы успешно записались на тренировку'
    ]);

  } elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['booking_id'])) {
      http_response_code(400);
      echo json_encode(['error' => 'Не указан booking_id']);
      exit;
    }

    $booking_id = (int) $data['booking_id'];

    // получение инфы о бронировании
    $booking = $db->fetchOne(
      "SELECT b.*, w.title, w.current_participants, s.visits_left, s.id as subscription_id, w.id as workout_id
             FROM bookings b
             JOIN workouts w ON b.workout_id = w.id
             JOIN subscriptions s ON b.subscription_id = s.id
             WHERE b.id = ? AND b.client_id = ? AND b.status = 'confirmed'",
      [$booking_id, $currentUser['id']]
    );

    if (!$booking) {
      http_response_code(404);
      echo json_encode(['error' => 'Запись не найдена или уже отменена']);
      exit;
    }

    // обнова статуса бронирования на отмененный
    $result = $db->update('bookings', ['status' => 'cancelled'], 'id = ?', [$booking_id]);

    if (!$result) {
      throw new Exception('Не удалось отменить запись');
    }

    // уменьшение кол-ва участников в тренировке
    $newParticipantsCount = max(0, $booking['current_participants'] - 1);
    $db->update(
      'workouts',
      ['current_participants' => $newParticipantsCount],
      'id = ?',
      [$booking['workout_id']]
    );

    // возвращаем посещение в абонемент
    $newVisitsLeft = $booking['visits_left'] + 1;
    $db->update(
      'subscriptions',
      ['visits_left' => $newVisitsLeft],
      'id = ?',
      [$booking['subscription_id']]
    );

    $db->insert('notifications', [
      'user_id' => $currentUser['id'],
      'title' => 'Отмена записи',
      'message' => 'Вы отменили запись на тренировку "' . $booking['title'] . '"',
      'type' => 'booking_cancelled',
      'is_read' => 0,
      'created_at' => date('Y-m-d H:i:s')
    ]);

    echo json_encode([
      'success' => true,
      'message' => 'Запись успешно отменена'
    ]);
  } else {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не разрешен']);
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
?>