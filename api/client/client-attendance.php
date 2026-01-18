<?php
require_once '../../config.php';
require_once '../../classes/Database.php';
require_once '../../classes/User.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$user = new User();
$currentUser = $user->getCurrentUser();

if (!$currentUser) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Не авторизован']);
  exit;
}

if ($currentUser['role'] !== 'client') {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => 'Доступ запрещен']);
  exit;
}

$db = new Database();

try {
  if ($method === 'GET') {
    $client_id = $currentUser['id'];
    $bookings = $db->fetchAll(
      "SELECT 
          b.id as booking_id,
          b.status as booking_status,
          b.created_at as booking_date,
          w.id as workout_id,
          w.title as workout_title,
          w.description as workout_description,
          w.workout_date,
          w.start_time,
          w.end_time,
          w.status as workout_status,
          u.id as trainer_id,
          CONCAT(u.first_name, ' ', u.last_name) as trainer_name,
          u.specialization as trainer_specialization,
          a.attended as attendance_status,
          a.notes as trainer_notes,
          a.marked_at as attendance_date,
          CASE 
            WHEN w.workout_date < CURDATE() THEN 1
            ELSE 0
          END as workout_date_passed
       FROM bookings b
       JOIN workouts w ON b.workout_id = w.id
       JOIN users u ON w.trainer_id = u.id
       LEFT JOIN attendance a ON b.id = a.booking_id
       WHERE b.client_id = ?
       ORDER BY w.workout_date DESC, w.start_time DESC",
      [$client_id]
    );

    $total = count($bookings);
    $attended = 0;
    $missed = 0;
    $cancelled = 0;
    $confirmed = 0;

    foreach ($bookings as $booking) {
      switch ($booking['booking_status']) {
        case 'attended':
          $attended++;
          break;
        case 'missed':
          $missed++;
          break;
        case 'cancelled':
          $cancelled++;
          break;
        case 'confirmed':
          $confirmed++;
          break;
      }
    }
    $response = [
      'success' => true,
      'stats' => [
        'total' => $total,
        'attended' => $attended,
        'missed' => $missed,
        'cancelled' => $cancelled,
        'confirmed' => $confirmed,
        'attended_percent' => $total > 0 ? round(($attended / $total) * 100) : 0,
        'missed_percent' => $total > 0 ? round(($missed / $total) * 100) : 0,
        'cancelled_percent' => $total > 0 ? round(($cancelled / $total) * 100) : 0,
        'confirmed_percent' => $total > 0 ? round(($confirmed / $total) * 100) : 0
      ],
      'bookings' => $bookings
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

  } else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Метод не разрешен']);
  }

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $e->getMessage() + "ошибка джейсон"]);
}
?>