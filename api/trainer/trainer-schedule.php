<?php

require_once '../../config.php';
header('Content-Type: application/json; charset=utf-8');
ob_start();

$response = ['success' => false, 'message' => 'Неизвестная ошибка'];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Только POST метод разрешен');
  }

  $userModel = new User();
  $db = new Database();

  $currentUser = $userModel->getCurrentUser();
  if (!$currentUser || $currentUser['role'] !== 'trainer') {
    throw new Exception('Доступ запрещен');
  }

  $trainerId = $currentUser['id'];
  $action = $_POST['action'] ?? '';

  switch ($action) {
    case 'get_workouts':
      $workouts = $db->fetchAll(
        "SELECT w.*, 
                        COUNT(b.id) as bookings_count,
                        (SELECT COUNT(*) FROM bookings b2 WHERE b2.workout_id = w.id AND b2.status = 'attended') as attended_count
                 FROM workouts w
                 LEFT JOIN bookings b ON w.id = b.workout_id AND b.status IN ('confirmed', 'attended')
                 WHERE w.trainer_id = ?
                 GROUP BY w.id
                 ORDER BY 
                    CASE 
                        WHEN w.workout_date > CURDATE() THEN 1
                        WHEN w.workout_date = CURDATE() AND w.end_time > CURTIME() THEN 2
                        ELSE 3
                    END,
                    w.workout_date ASC,
                    w.start_time ASC",
        [$trainerId]
      );

      $response = [
        'success' => true,
        'data' => $workouts,
        'message' => 'Тренировки получены'
      ];
      break;

    case 'create_workout':
      $required = ['title', 'workout_date', 'start_time', 'end_time', 'max_participants'];
      foreach ($required as $field) {
        if (empty($_POST[$field])) {
          throw new Exception("Поле '$field' обязательно");
        }
      }

      $workoutData = [
        'trainer_id' => $trainerId,
        'title' => trim($_POST['title']),
        'description' => $_POST['description'] ?? null,
        'workout_date' => $_POST['workout_date'],
        'start_time' => $_POST['start_time'],
        'end_time' => $_POST['end_time'],
        'max_participants' => (int) $_POST['max_participants'],
        'status' => 'scheduled',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
      ];

      $conflictCheck = $db->fetchOne(
        "SELECT id FROM workouts 
                 WHERE trainer_id = ? 
                 AND workout_date = ?
                 AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))",
        [
          $trainerId,
          $workoutData['workout_date'],
          $workoutData['start_time'],
          $workoutData['start_time'],
          $workoutData['end_time'],
          $workoutData['end_time']
        ]
      );

      if ($conflictCheck) {
        throw new Exception('Время тренировки пересекается с другой вашей тренировкой');
      }

      $workoutId = $db->insert('workouts', $workoutData);
      $notification = [
        'user_id' => $trainerId,
        'type' => 'schedule_change',
        'title' => 'Новая тренировка добавлена',
        'message' => "Вам назначена новая тренировка: '{$workoutData['title']}' на {$workoutData['workout_date']} в " . substr($workoutData['start_time'], 0, 5),
        'is_read' => 0,
        'related_id' => $workoutId,
        'created_at' => date('Y-m-d H:i:s')
      ];
      $db->insert('notifications', $notification);

      $response = [
        'success' => true,
        'message' => 'Тренировка успешно создана',
        'workout_id' => $workoutId
      ];
      break;

    case 'update_workout':
      if (empty($_POST['workout_id'])) {
        throw new Exception('ID тренировки обязателен');
      }
      $workoutId = (int) $_POST['workout_id'];
      $workout = $db->fetchOne(
        "SELECT id FROM workouts WHERE id = ? AND trainer_id = ?",
        [$workoutId, $trainerId]
      );

      if (!$workout) {
        throw new Exception('Тренировка не найдена или доступ запрещен');
      }

      $updateData = [];
      if (!empty($_POST['title']))
        $updateData['title'] = trim($_POST['title']);
      if (isset($_POST['description']))
        $updateData['description'] = $_POST['description'];
      if (!empty($_POST['workout_date']))
        $updateData['workout_date'] = $_POST['workout_date'];
      if (!empty($_POST['start_time']))
        $updateData['start_time'] = $_POST['start_time'];
      if (!empty($_POST['end_time']))
        $updateData['end_time'] = $_POST['end_time'];
      if (!empty($_POST['max_participants']))
        $updateData['max_participants'] = (int) $_POST['max_participants'];
      if (!empty($_POST['status']))
        $updateData['status'] = $_POST['status'];

      if (empty($updateData)) {
        throw new Exception('Нет данных для обновления');
      }
      $updateData['updated_at'] = date('Y-m-d H:i:s');
      if (isset($updateData['workout_date']) || isset($updateData['start_time']) || isset($updateData['end_time'])) {
        $currentWorkout = $db->fetchOne("SELECT workout_date, start_time, end_time FROM workouts WHERE id = ?", [$workoutId]);

        $checkDate = $updateData['workout_date'] ?? $currentWorkout['workout_date'];
        $checkStart = $updateData['start_time'] ?? $currentWorkout['start_time'];
        $checkEnd = $updateData['end_time'] ?? $currentWorkout['end_time'];

        $conflictCheck = $db->fetchOne(
          "SELECT id FROM workouts 
                     WHERE trainer_id = ? 
                     AND id != ?
                     AND workout_date = ?
                     AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))",
          [
            $trainerId,
            $workoutId,
            $checkDate,
            $checkStart,
            $checkStart,
            $checkEnd,
            $checkEnd
          ]
        );

        if ($conflictCheck) {
          throw new Exception('Время тренировки пересекается с другой вашей тренировкой');
        }
      }

      $affected = $db->update('workouts', $updateData, 'id = ?', [$workoutId]);
      if ($affected > 0) {
        $bookings = $db->fetchAll(
          "SELECT b.client_id, u.email, u.first_name, w.title, w.workout_date, w.start_time 
                     FROM bookings b 
                     JOIN users u ON b.client_id = u.id 
                     JOIN workouts w ON b.workout_id = w.id 
                     WHERE b.workout_id = ? AND b.status = 'confirmed'",
          [$workoutId]
        );

        foreach ($bookings as $booking) {
          $notification = [
            'user_id' => $booking['client_id'],
            'type' => 'schedule_change',
            'title' => "Изменения в тренировке '{$booking['title']}'",
            'message' => "В тренировке '{$booking['title']}' от {$booking['workout_date']} произошли изменения",
            'is_read' => 0,
            'related_id' => $workoutId,
            'created_at' => date('Y-m-d H:i:s')
          ];
          $db->insert('notifications', $notification);
        }
      }

      $response = [
        'success' => true,
        'message' => 'Тренировка успешно обновлена',
        'affected' => $affected
      ];
      break;

    case 'cancel_workout':
      if (empty($_POST['workout_id'])) {
        throw new Exception('ID тренировки обязателен');
      }

      $workoutId = (int) $_POST['workout_id'];

      $workout = $db->fetchOne(
        "SELECT id, title, workout_date, start_time FROM workouts WHERE id = ? AND trainer_id = ?",
        [$workoutId, $trainerId]
      );

      if (!$workout) {
        throw new Exception('Тренировка не найдена или доступ запрещен');
      }

      $affected = $db->update(
        'workouts',
        ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')],
        'id = ?',
        [$workoutId]
      );

      $confirmedBookings = $db->fetchAll(
        "SELECT id, client_id, subscription_id FROM bookings 
                 WHERE workout_id = ? AND status = 'confirmed'",
        [$workoutId]
      );

      foreach ($confirmedBookings as $booking) {
        $db->update(
          'bookings',
          ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')],
          'id = ?',
          [$booking['id']]
        );

        if ($booking['subscription_id']) {
          $db->executeQuery(
            "UPDATE subscriptions 
                         SET visits_left = LEAST(visits_total, visits_left + 1)
                         WHERE id = ?",
            [$booking['subscription_id']]
          );
        }

        $notification = [
          'user_id' => $booking['client_id'],
          'type' => 'schedule_change',
          'title' => 'Тренировка отменена',
          'message' => "Тренировка '{$workout['title']}' от {$workout['workout_date']} в " . substr($workout['start_time'], 0, 5) . " отменена.",
          'is_read' => 0,
          'related_id' => $workoutId,
          'created_at' => date('Y-m-d H:i:s')
        ];
        $db->insert('notifications', $notification);
      }

      $response = [
        'success' => true,
        'message' => 'Тренировка отменена',
        'affected' => $affected
      ];
      break;

    default:
      throw new Exception('Неизвестное действие');
  }

} catch (Exception $e) {
  $response = [
    'success' => false,
    'message' => $e->getMessage()
  ];
  http_response_code(400);
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>