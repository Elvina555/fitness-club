<?php
require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$user = new User();
$currentUser = $user->getCurrentUser();

if (!$currentUser) {
  http_response_code(401);
  echo json_encode(['error' => 'Не авторизирован']);
  exit;
}

if ($currentUser['role'] !== 'client') {
  http_response_code(403);
  echo json_encode(['error' => 'Доступ запрещен']);
  exit;
}

$db = new Database();

try {
  if ($method === 'GET') {
    $reviews = $db->fetchAll(
      "SELECT r.id, r.rating, r.comment, r.moderation_status, r.moderation_comment,
                    r.created_at, r.workout_id,
                    u.id as trainer_id, u.first_name as trainer_first_name,
                    u.last_name as trainer_last_name,
                    w.title as workout_title, w.workout_date
             FROM reviews r
             JOIN users u ON r.trainer_id = u.id
             JOIN workouts w ON r.workout_id = w.id
             WHERE r.client_id = ?
             ORDER BY r.created_at DESC",
      [$currentUser['id']]
    );

    echo json_encode([
      'success' => true,
      'reviews' => $reviews
    ]);

  } elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (
      empty($data['trainer_id']) || empty($data['workout_id']) ||
      !isset($data['rating']) || empty($data['comment'])
    ) {
      http_response_code(400);
      echo json_encode(['error' => 'Не указаны обязательные параметры']);
      exit;
    }
    $booking = $db->fetchOne(
      "SELECT b.* FROM bookings b
             WHERE b.client_id = ? AND b.workout_id = ? AND b.status IN ('attended', 'confirmed')
             LIMIT 1",
      [$currentUser['id'], $data['workout_id']]
    );

    if (!$booking) {
      http_response_code(400);
      echo json_encode(['error' => 'Вы не посетили эту тренировку']);
      exit;
    }

    $existing = $db->fetchOne(
      "SELECT id FROM reviews 
             WHERE client_id = ? AND trainer_id = ? AND workout_id = ?",
      [$currentUser['id'], $data['trainer_id'], $data['workout_id']]
    );

    if ($existing) {
      http_response_code(400);
      echo json_encode(['error' => 'Вы уже оставили отзыв о этой тренировке']);
      exit;
    }

    $rating = (int) $data['rating'];
    if ($rating < 1 || $rating > 5) {
      http_response_code(400);
      echo json_encode(['error' => 'Рейтинг должен быть от 1 до 5']);
      exit;
    }

    // создаем отзыв
    $review_id = $db->insert('reviews', [
      'client_id' => $currentUser['id'],
      'trainer_id' => (int) $data['trainer_id'],
      'workout_id' => (int) $data['workout_id'],
      'rating' => $rating,
      'comment' => trim($data['comment']),
      'moderation_status' => 'pending'
    ]);

    echo json_encode([
      'success' => true,
      'review_id' => $review_id,
      'message' => 'Отзыв создан и отправлен на модерацию'
    ]);

  } elseif ($method === 'PUT') {
    // обнова отзыва
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['review_id'])) {
      http_response_code(400);
      echo json_encode(['error' => 'Не указан review_id']);
      exit;
    }

    // чек право на изменение
    $review = $db->fetchOne(
      "SELECT * FROM reviews WHERE id = ? AND client_id = ?",
      [$data['review_id'], $currentUser['id']]
    );

    if (!$review) {
      http_response_code(404);
      echo json_encode(['error' => 'Отзыв не найден']);
      exit;
    }

    if ($review['moderation_status'] !== 'pending') {
      http_response_code(400);
      echo json_encode(['error' => 'Можно редактировать только отзывы на модерации']);
      exit;
    }

    $updateData = [];

    if (isset($data['rating'])) {
      $rating = (int) $data['rating'];
      if ($rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(['error' => 'Рейтинг должен быть от 1 до 5']);
        exit;
      }
      $updateData['rating'] = $rating;
    }

    if (isset($data['comment'])) {
      $updateData['comment'] = trim($data['comment']);
    }

    if (empty($updateData)) {
      http_response_code(400);
      echo json_encode(['error' => 'Нет данных для обновления']);
      exit;
    }

    // обрнова отзыва
    $db->update('reviews', $updateData, 'id = ?', [$data['review_id']]);

    echo json_encode([
      'success' => true,
      'message' => 'Отзыв обновлен'
    ]);

  } elseif ($method === 'DELETE') {
    // удалить отзыв
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['review_id'])) {
      http_response_code(400);
      echo json_encode(['error' => 'Не указан review_id']);
      exit;
    }

    // чек право на удаление
    $review = $db->fetchOne(
      "SELECT * FROM reviews WHERE id = ? AND client_id = ?",
      [$data['review_id'], $currentUser['id']]
    );

    if (!$review) {
      http_response_code(404);
      echo json_encode(['error' => 'Отзыв не найден']);
      exit;
    }

    // можно удалять только отзывы на модерации -- запомнить
    if ($review['moderation_status'] !== 'pending') {
      http_response_code(400);
      echo json_encode(['error' => 'Можно удалять только отзывы на модерации']);
      exit;
    }

    // удаление отзыва
    $db->delete('reviews', 'id = ?', [$data['review_id']]);

    echo json_encode([
      'success' => true,
      'message' => 'Отзыв удален'
    ]);
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
?>