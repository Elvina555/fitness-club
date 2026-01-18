<?php
require_once '../../classes/User.php';
require_once '../../classes/Database.php';

header('Content-Type: application/json');

$userModel = new User();
$db = new Database();

$currentUser = $userModel->getCurrentUser();
if (!$currentUser || $currentUser['role'] !== 'admin') {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Недостаточно прав']);
  exit;
}
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
  switch ($action) {
    case 'add':
      addWorkout($db);
      break;
    case 'edit':
      editWorkout($db);
      break;
    case 'cancel':
      cancelWorkout($db);
      break;
    case 'complete':
      completeWorkout($db);
      break;
    case 'delete':
      deleteWorkout($db);
      break;
    default:
      http_response_code(400);
      echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
      exit;
  }
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  exit;
}
function addWorkout($db)
{
  $required = ['title', 'trainer_id', 'workout_date', 'start_time', 'end_time', 'max_participants'];
  foreach ($required as $field) {
    if (empty($_POST[$field])) {
      throw new Exception("Поле '$field' обязательно для заполнения");
    }
  }

  $trainer = $db->fetchOne("SELECT id FROM users WHERE id = ? AND role = 'trainer'", [$_POST['trainer_id']]);
  if (!$trainer) {
    throw new Exception("Тренер не найден");
  }

  $workoutDate = $_POST['workout_date'];
  $currentDate = date('Y-m-d');
  if ($workoutDate < $currentDate) {
    throw new Exception("Нельзя создать тренировку в прошлом");
  }

  $startTime = $_POST['start_time'];
  $endTime = $_POST['end_time'];
  if ($startTime >= $endTime) {
    throw new Exception("Время окончания должно быть позже времени начала");
  }

  $conflictingWorkout = $db->fetchOne(
    "SELECT id, title FROM workouts 
         WHERE trainer_id = ? 
         AND workout_date = ? 
         AND status = 'scheduled'
         AND (
             (start_time <= ? AND end_time > ?) OR
             (start_time < ? AND end_time >= ?) OR
             (start_time >= ? AND end_time <= ?)
         )",
    [
      $_POST['trainer_id'],
      $workoutDate,
      $startTime,
      $startTime,
      $endTime,
      $endTime,
      $startTime,
      $endTime
    ]
  );

  if ($conflictingWorkout) {
    throw new Exception("Тренер уже занят в это время. Конфликтующая тренировка: " . $conflictingWorkout['title']);
  }

  $workoutData = [
    'title' => trim($_POST['title']),
    'description' => !empty($_POST['description']) ? trim($_POST['description']) : null,
    'trainer_id' => $_POST['trainer_id'],
    'workout_date' => $workoutDate,
    'start_time' => $startTime,
    'end_time' => $endTime,
    'max_participants' => intval($_POST['max_participants']),
    'current_participants' => 0,
    'status' => 'scheduled'
  ];

  $workoutId = $db->insert('workouts', $workoutData);

  sendNotificationToTrainer(
    $db,
    $_POST['trainer_id'],
    $workoutId,
    'Новая тренировка добавлена',
    "Вам назначена новая тренировка: '{$workoutData['title']}' на " . date('d.m.Y', strtotime($workoutDate)) . " в " . date('H:i', strtotime($startTime))
  );

  echo json_encode([
    'success' => true,
    'message' => 'Тренировка успешно добавлена',
    'workout_id' => $workoutId
  ]);
}

function editWorkout($db)
{
  if (empty($_POST['id'])) {
    throw new Exception("ID тренировки не указан");
  }

  $required = ['title', 'trainer_id', 'workout_date', 'start_time', 'end_time', 'max_participants', 'status'];
  foreach ($required as $field) {
    if (empty($_POST[$field])) {
      throw new Exception("Поле '$field' обязательно для заполнения");
    }
  }

  $workoutId = intval($_POST['id']);

  $oldWorkout = $db->fetchOne("SELECT * FROM workouts WHERE id = ?", [$workoutId]);
  if (!$oldWorkout) {
    throw new Exception("Тренировка не найдена");
  }

  $trainer = $db->fetchOne("SELECT id FROM users WHERE id = ? AND role = 'trainer'", [$_POST['trainer_id']]);
  if (!$trainer) {
    throw new Exception("Тренер не найден");
  }

  $startTime = $_POST['start_time'];
  $endTime = $_POST['end_time'];
  if ($startTime >= $endTime) {
    throw new Exception("Время окончания должно быть позже времени начала");
  }

  $workoutDate = $_POST['workout_date'];
  if (
    $oldWorkout['trainer_id'] != $_POST['trainer_id'] ||
    $oldWorkout['workout_date'] != $workoutDate ||
    $oldWorkout['start_time'] != $startTime ||
    $oldWorkout['end_time'] != $endTime
  ) {

    $conflictingWorkout = $db->fetchOne(
      "SELECT id, title FROM workouts 
             WHERE trainer_id = ? 
             AND workout_date = ? 
             AND status = 'scheduled'
             AND id != ?
             AND (
                 (start_time <= ? AND end_time > ?) OR
                 (start_time < ? AND end_time >= ?) OR
                 (start_time >= ? AND end_time <= ?)
             )",
      [
        $_POST['trainer_id'],
        $workoutDate,
        $workoutId,
        $startTime,
        $startTime,
        $endTime,
        $endTime,
        $startTime,
        $endTime
      ]
    );

    if ($conflictingWorkout) {
      throw new Exception("Тренер уже занят в это время. Конфликтующая тренировка: " . $conflictingWorkout['title']);
    }
  }

  $maxParticipants = intval($_POST['max_participants']);
  if ($maxParticipants < $oldWorkout['current_participants']) {
    throw new Exception("Максимальное количество участников не может быть меньше уже записавшихся (" . $oldWorkout['current_participants'] . ")");
  }

  $updateData = [
    'title' => trim($_POST['title']),
    'description' => !empty($_POST['description']) ? trim($_POST['description']) : null,
    'trainer_id' => $_POST['trainer_id'],
    'workout_date' => $workoutDate,
    'start_time' => $startTime,
    'end_time' => $endTime,
    'max_participants' => $maxParticipants,
    'status' => $_POST['status']
  ];

  $affected = $db->update('workouts', $updateData, 'id = ?', [$workoutId]);

  if ($affected > 0) {
    $changes = [];
    if ($oldWorkout['title'] != $updateData['title'])
      $changes[] = 'название';
    if (
      $oldWorkout['workout_date'] != $updateData['workout_date'] ||
      $oldWorkout['start_time'] != $updateData['start_time'] ||
      $oldWorkout['end_time'] != $updateData['end_time']
    ) {
      $changes[] = 'время проведения';
    }
    if ($oldWorkout['trainer_id'] != $updateData['trainer_id'])
      $changes[] = 'тренера';

    if (!empty($changes)) {
      $changeText = implode(', ', $changes);
      $message = "В тренировке '{$updateData['title']}' изменились: $changeText";
      sendWorkoutChangeNotifications($db, $workoutId, $message);

      if ($oldWorkout['trainer_id'] != $updateData['trainer_id']) {
        sendNotificationToTrainer(
          $db,
          $oldWorkout['trainer_id'],
          $workoutId,
          'Тренировка переназначена',
          "Тренировка '{$updateData['title']}' от " . date('d.m.Y', strtotime($workoutDate)) . " переназначена другому тренеру."
        );

        sendNotificationToTrainer(
          $db,
          $updateData['trainer_id'],
          $workoutId,
          'Новая тренировка назначена',
          "Вам назначена тренировка: '{$updateData['title']}' на " . date('d.m.Y', strtotime($workoutDate)) . " в " . date('H:i', strtotime($startTime))
        );
      } elseif ($oldWorkout['trainer_id'] == $updateData['trainer_id']) {
        sendNotificationToTrainer(
          $db,
          $updateData['trainer_id'],
          $workoutId,
          'Изменения в тренировке',
          "В тренировке '{$updateData['title']}' от " . date('d.m.Y', strtotime($workoutDate)) . " произошли изменения: $changeText"
        );
      }
    }

    if ($oldWorkout['status'] != $updateData['status']) {
      $statusMessages = [
        'scheduled' => 'Тренировка запланирована',
        'cancelled' => 'Тренировка отменена',
        'completed' => 'Тренировка проведена'
      ];

      $statusMessage = $statusMessages[$updateData['status']] ?? 'Статус тренировки изменен';

      sendWorkoutChangeNotifications($db, $workoutId, $statusMessage . ": '{$updateData['title']}'");
      sendNotificationToTrainer(
        $db,
        $updateData['trainer_id'],
        $workoutId,
        'Изменение статуса тренировки',
        "Тренировка '{$updateData['title']}' от " . date('d.m.Y', strtotime($workoutDate)) . ": $statusMessage"
      );
    }

    echo json_encode([
      'success' => true,
      'message' => 'Тренировка успешно обновлена'
    ]);
  } else {
    throw new Exception("Ошибка при обновлении тренировки");
  }
}

function cancelWorkout($db)
{
  if (empty($_POST['workout_id'])) {
    throw new Exception("ID тренировки не указан");
  }

  $workoutId = intval($_POST['workout_id']);

  $workout = $db->fetchOne("SELECT * FROM workouts WHERE id = ?", [$workoutId]);
  if (!$workout) {
    throw new Exception("Тренировка не найдена");
  }

  if ($workout['status'] === 'cancelled') {
    throw new Exception("Тренировка уже отменена");
  }

  $workoutTitle = $workout['title'];
  $workoutDate = date('d.m.Y', strtotime($workout['workout_date']));
  $startTime = date('H:i', strtotime($workout['start_time']));
  $trainerId = $workout['trainer_id'];

  $affected = $db->update('workouts', ['status' => 'cancelled'], 'id = ?', [$workoutId]);

  if ($affected > 0) {
    $db->executeQuery(
      "UPDATE bookings SET status = 'cancelled' WHERE workout_id = ? AND status IN ('created', 'confirmed')",
      [$workoutId]
    );

    $bookings = $db->fetchAll(
      "SELECT b.subscription_id, s.visits_total, s.visits_left
     FROM bookings b
     JOIN subscriptions s ON b.subscription_id = s.id
     WHERE b.workout_id = ? 
     AND b.status = 'cancelled' 
     AND b.subscription_id IS NOT NULL",
      [$workoutId]
    );

    foreach ($bookings as $booking) {
      if ($booking['visits_left'] < $booking['visits_total']) {
        $newVisitsLeft = $booking['visits_left'] + 1;
        $db->executeQuery(
          "UPDATE subscriptions SET visits_left = ? WHERE id = ?",
          [$newVisitsLeft, $booking['subscription_id']]
        );
      }
    }

    $message = "Тренировка '{$workoutTitle}' ($workoutDate в $startTime) отменена. Посещение возвращено на ваш абонемент.";

    $clients = $db->fetchAll(
      "SELECT DISTINCT b.client_id 
             FROM bookings b
             WHERE b.workout_id = ? AND b.status IN ('created', 'confirmed', 'cancelled')",
      [$workoutId]
    );

    $title = "Изменения в тренировке '{$workoutTitle}' ($workoutDate в $startTime)";

    foreach ($clients as $client) {
      $notificationData = [
        'user_id' => $client['client_id'],
        'type' => 'schedule_change',
        'title' => $title,
        'message' => $message,
        'is_read' => 0,
        'related_id' => $workoutId,
        'created_at' => date('Y-m-d H:i:s')
      ];
      $db->insert('notifications', $notificationData);
    }

    $trainerNotificationData = [
      'user_id' => $trainerId,
      'type' => 'schedule_change',
      'title' => 'Тренировка отменена',
      'message' => "Тренировка '{$workoutTitle}' от $workoutDate в $startTime отменена.",
      'is_read' => 0,
      'related_id' => $workoutId,
      'created_at' => date('Y-m-d H:i:s')
    ];
    $db->insert('notifications', $trainerNotificationData);

    echo json_encode([
      'success' => true,
      'message' => 'Тренировка отменена'
    ]);
  } else {
    throw new Exception("Ошибка при отмене тренировки");
  }
}

function completeWorkout($db)
{
  if (empty($_POST['workout_id'])) {
    throw new Exception("ID тренировки не указан");
  }

  $workoutId = intval($_POST['workout_id']);
  $workout = $db->fetchOne("SELECT * FROM workouts WHERE id = ?", [$workoutId]);

  if (!$workout) {
    throw new Exception("Тренировка не найдена");
  }

  if ($workout['status'] === 'completed') {
    throw new Exception("Тренировка уже отмечена как проведенная");
  }

  $affected = $db->update('workouts', ['status' => 'completed'], 'id = ?', [$workoutId]);

  if ($affected > 0) {
    $workoutDate = date('d.m.Y', strtotime($workout['workout_date']));
    $startTime = date('H:i', strtotime($workout['start_time']));

    $message = "Тренировка '{$workout['title']}' ($workoutDate в $startTime) проведена. Спасибо за участие!";
    sendWorkoutChangeNotifications($db, $workoutId, $message);

    sendNotificationToTrainer(
      $db,
      $workout['trainer_id'],
      $workoutId,
      'Тренировка проведена',
      "Тренировка '{$workout['title']}' от $workoutDate в $startTime отмечена как проведенная."
    );

    echo json_encode([
      'success' => true,
      'message' => 'Тренировка отмечена как проведенная'
    ]);
  } else {
    throw new Exception("Ошибка при обновлении статуса тренировки");
  }
}

function deleteWorkout($db)
{
  if (empty($_POST['workout_id'])) {
    throw new Exception("ID тренировки не указан");
  }

  $workoutId = intval($_POST['workout_id']);

  $workout = $db->fetchOne("SELECT * FROM workouts WHERE id = ?", [$workoutId]);
  if (!$workout) {
    throw new Exception("Тренировка не найдена");
  }

  $activeBookings = $db->fetchOne(
    "SELECT COUNT(*) as count FROM bookings WHERE workout_id = ? AND status IN ('created', 'confirmed')",
    [$workoutId]
  );

  if ($activeBookings['count'] > 0) {
    throw new Exception("Невозможно удалить тренировку с активными бронированиями. Сначала отмените тренировку.");
  }

  $workoutDate = date('d.m.Y', strtotime($workout['workout_date']));
  $startTime = date('H:i', strtotime($workout['start_time']));

  $dbConnection = $db->getConnection();
  $dbConnection->begin_transaction();

  try {
    $clients = $db->fetchAll(
      "SELECT DISTINCT client_id FROM bookings WHERE workout_id = ?",
      [$workoutId]
    );

    $db->executeQuery(
      "DELETE FROM attendance WHERE booking_id IN (SELECT id FROM bookings WHERE workout_id = ?)",
      [$workoutId]
    );

    $db->executeQuery("DELETE FROM reviews WHERE workout_id = ?", [$workoutId]);

    $db->executeQuery("DELETE FROM bookings WHERE workout_id = ?", [$workoutId]);

    $affected = $db->delete('workouts', 'id = ?', [$workoutId]);

    $dbConnection->commit();

    if ($affected > 0) {
      foreach ($clients as $client) {
        $notificationData = [
          'user_id' => $client['client_id'],
          'type' => 'schedule_change',
          'title' => 'Тренировка удалена',
          'message' => "Тренировка '{$workout['title']}' ($workoutDate в $startTime) была удалена из расписания.",
          'is_read' => 0,
          'related_id' => null,
          'created_at' => date('Y-m-d H:i:s')
        ];
        $db->insert('notifications', $notificationData);
      }

      sendNotificationToTrainer(
        $db,
        $workout['trainer_id'],
        null,
        'Тренировка удалена',
        "Тренировка '{$workout['title']}' от $workoutDate в $startTime удалена из расписания."
      );

      echo json_encode([
        'success' => true,
        'message' => 'Тренировка удалена'
      ]);
    } else {
      throw new Exception("Ошибка при удалении тренировки");
    }
  } catch (Exception $e) {
    $dbConnection->rollback();
    throw new Exception("Ошибка при удалении связанных данных: " . $e->getMessage());
  }
}

function sendWorkoutChangeNotifications($db, $workoutId, $message)
{
  $clients = $db->fetchAll(
    "SELECT DISTINCT b.client_id, u.email, u.first_name 
         FROM bookings b
         JOIN users u ON b.client_id = u.id
         WHERE b.workout_id = ? AND b.status IN ('created', 'confirmed')",
    [$workoutId]
  );

  $workout = $db->fetchOne(
    "SELECT title, workout_date, start_time FROM workouts WHERE id = ?",
    [$workoutId]
  );

  if ($workout) {
    $workoutDate = date('d.m.Y', strtotime($workout['workout_date']));
    $startTime = date('H:i', strtotime($workout['start_time']));
    $title = "Изменения в тренировке '{$workout['title']}' ($workoutDate в $startTime)";

    foreach ($clients as $client) {
      $notificationData = [
        'user_id' => $client['client_id'],
        'type' => 'schedule_change',
        'title' => $title,
        'message' => $message,
        'is_read' => 0,
        'related_id' => $workoutId,
        'created_at' => date('Y-m-d H:i:s')
      ];

      $db->insert('notifications', $notificationData);
    }
  }
}

function sendNotificationToTrainer($db, $trainerId, $workoutId, $title, $message)
{
  $notificationData = [
    'user_id' => $trainerId,
    'type' => 'schedule_change',
    'title' => $title,
    'message' => $message,
    'is_read' => 0,
    'related_id' => $workoutId,
    'created_at' => date('Y-m-d H:i:s')
  ];

  $db->insert('notifications', $notificationData);
}
?>