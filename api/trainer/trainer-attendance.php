<?php
require_once '../../config.php';
header('Content-Type: application/json; charset=utf-8');
ob_start();

$response = ['success' => false, 'message' => 'Неизвестная ошибка'];

try {
  $userModel = new User();
  $db = new Database();

  $currentUser = $userModel->getCurrentUser();
  if (!$currentUser || $currentUser['role'] !== 'trainer') {
    throw new Exception('Доступ запрещен');
  }

  $trainerId = $currentUser['id'];

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $workoutId = $_GET['workout_id'] ?? null;

    if ($workoutId) {
      $workout = $db->fetchOne(
        "SELECT w.*, 
                        (SELECT COUNT(*) FROM bookings b WHERE b.workout_id = w.id AND b.status IN ('confirmed', 'attended')) as total_participants,
                        (SELECT COUNT(*) FROM bookings b2 WHERE b2.workout_id = w.id AND b2.status = 'attended') as attended_count
                 FROM workouts w
                 WHERE w.id = ? AND w.trainer_id = ?",
        [$workoutId, $trainerId]
      );

      if (!$workout) {
        throw new Exception('Тренировка не найдена или доступ запрещен');
      }

      $participants = $db->fetchAll(
        "SELECT b.id as booking_id, b.status as booking_status,
          u.id as client_id, u.first_name, u.last_name, u.email, u.phone,
          s.type as subscription_type, s.visits_left,
          a.attended, a.notes, a.marked_at
   FROM bookings b
   JOIN users u ON b.client_id = u.id
   LEFT JOIN subscriptions s ON b.subscription_id = s.id
   LEFT JOIN attendance a ON b.id = a.booking_id
   WHERE b.workout_id = ? AND b.status IN ('confirmed', 'attended', 'missed')
   ORDER BY u.last_name, u.first_name",
        [$workoutId]
      );

      $response = [
        'success' => true,
        'workout' => $workout,
        'participants' => $participants
      ];
    } else {
      $workouts = $db->fetchAll(
        "SELECT w.*, 
                        COUNT(b.id) as total_participants,
                        (SELECT COUNT(*) FROM bookings b2 WHERE b2.workout_id = w.id AND b2.status = 'attended') as attended_count
                 FROM workouts w
                 LEFT JOIN bookings b ON w.id = b.workout_id AND b.status IN ('confirmed', 'attended', 'missed')
                 WHERE w.trainer_id = ? 
                 AND (w.status = 'completed' OR 
                     (w.status = 'scheduled' AND 
                      (w.workout_date = CURDATE() OR 
                       (w.workout_date < CURDATE() AND w.status != 'cancelled'))))
                 GROUP BY w.id
                 ORDER BY w.workout_date DESC, w.start_time DESC",
        [$trainerId]
      );
      $response = [
        'success' => true,
        'workouts' => $workouts
      ];
    }

  } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input)) {
      $input = $_POST;
    }

    $action = $input['action'] ?? '';
    if ($action === 'mark_attendance') {
      $required = ['booking_id', 'attended'];
      foreach ($required as $field) {
        if (!isset($input[$field])) {
          throw new Exception("Поле '$field' обязательно");
        }
      }

      $bookingId = (int) $input['booking_id'];
      $attended = (int) $input['attended'];
      $notes = isset($input['notes']) ? trim($input['notes']) : null;
      $booking = $db->fetchOne(
        "SELECT b.*, w.trainer_id, w.id as workout_id
                 FROM bookings b
                 JOIN workouts w ON b.workout_id = w.id
                 WHERE b.id = ? AND w.trainer_id = ?",
        [$bookingId, $trainerId]
      );

      if (!$booking) {
        throw new Exception('Бронирование не найдено или доступ запрещен');
      }

      $existingAttendance = $db->fetchOne(
        "SELECT id, notes FROM attendance WHERE booking_id = ?",
        [$bookingId]
      );

      $attendanceData = [
        'attended' => $attended,
        'marked_by' => $trainerId,
        'marked_at' => date('Y-m-d H:i:s')
      ];

      if ($notes !== null) {
        $attendanceData['notes'] = $notes;
      }

      if ($existingAttendance) {
        $db->update(
          'attendance',
          $attendanceData,
          'booking_id = ?',
          [$bookingId]
        );
      } else {
        $attendanceData['booking_id'] = $bookingId;
        $db->insert('attendance', $attendanceData);
      }

      $bookingStatus = $attended ? 'attended' : 'missed';
      $db->update(
        'bookings',
        ['status' => $bookingStatus, 'updated_at' => date('Y-m-d H:i:s')],
        'id = ?',
        [$bookingId]
      );

      $workout = $db->fetchOne(
        "SELECT status FROM workouts WHERE id = ?",
        [$booking['workout_id']]
      );

      if ($workout['status'] === 'scheduled') {
        $allMarked = $db->fetchOne(
          "SELECT COUNT(*) as total,
                            SUM(CASE WHEN b.status IN ('attended', 'missed') THEN 1 ELSE 0 END) as marked
                     FROM bookings b
                     WHERE b.workout_id = ? AND b.status IN ('confirmed', 'attended', 'missed')",
          [$booking['workout_id']]
        );

        if ($allMarked && $allMarked['total'] > 0 && $allMarked['marked'] == $allMarked['total']) {
          $db->update(
            'workouts',
            ['status' => 'completed', 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$booking['workout_id']]
          );
        }
      }

      $response = [
        'success' => true,
        'message' => $attended ? 'Посещение отмечено' : 'Отсутствие отмечено'
      ];

    } elseif ($action === 'save_notes') {
      if (empty($input['booking_id'])) {
        throw new Exception('ID бронирования обязательно');
      }

      $bookingId = (int) $input['booking_id'];
      $notes = isset($input['notes']) ? trim($input['notes']) : null;
      $booking = $db->fetchOne(
        "SELECT b.*, w.trainer_id
                 FROM bookings b
                 JOIN workouts w ON b.workout_id = w.id
                 WHERE b.id = ? AND w.trainer_id = ?",
        [$bookingId, $trainerId]
      );

      if (!$booking) {
        throw new Exception('Бронирование не найдено или доступ запрещен');
      }
      $existingAttendance = $db->fetchOne(
        "SELECT id FROM attendance WHERE booking_id = ?",
        [$bookingId]
      );

      if ($existingAttendance) {
        $db->update(
          'attendance',
          [
            'notes' => $notes,
            'marked_by' => $trainerId,
            'marked_at' => date('Y-m-d H:i:s')
          ],
          'booking_id = ?',
          [$bookingId]
        );
      } else {
        $db->insert('attendance', [
          'booking_id' => $bookingId,
          'attended' => null,
          'notes' => $notes,
          'marked_by' => $trainerId,
          'marked_at' => date('Y-m-d H:i:s')
        ]);
      }

      $response = [
        'success' => true,
        'message' => 'Заметка сохранена'
      ];

    } else {
      throw new Exception('Неизвестное действие');
    }
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