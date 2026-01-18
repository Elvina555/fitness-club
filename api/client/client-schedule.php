<?php
require_once '../../classes/Database.php';
require_once '../../classes/User.php';

header('Content-Type: application/json; charset=UTF-8');

$usr = new User();
$method = $_SERVER['REQUEST_METHOD'];

try {
  $currentUser = $usr->getCurrentUser();
} catch (Exception $e) {
  http_response_code(401);
  echo json_encode(['error' => 'Не авторизирован']);
  exit;
}

// для надежности
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

// получаем тренирровки из бд
try {
  if ($method === 'GET') {
    $date = $_GET['date'] ?? null;
    $trainer_id = $_GET['trainer_id'] ?? null;
    $title = $_GET['title'] ?? null;

    $sql = "SELECT w.*, 
                       u.first_name as trainer_first_name, 
                       u.last_name as trainer_last_name,
                       u.specialization as trainer_specialization,
                       COUNT(CASE WHEN b.status IN ('confirmed', 'attended') THEN 1 END) as booked_by_client
                FROM workouts w
                JOIN users u ON w.trainer_id = u.id
                LEFT JOIN bookings b ON w.id = b.workout_id AND b.client_id = ? AND b.status IN ('confirmed', 'attended')
                WHERE w.status = 'scheduled' 
                  AND w.workout_date >= CURDATE()";

    $params = [$currentUser['id']];

    if ($date) {
      $sql .= " AND w.workout_date = ?";
      $params[] = $date;
    }

    if ($trainer_id && is_numeric($trainer_id)) {
      $sql .= " AND w.trainer_id = ?";
      $params[] = (int) $trainer_id;
    }

    if ($title) {
      $sql .= " AND w.title LIKE ?";
      $params[] = "%" . $title . "%";
    }

    $sql .= " GROUP BY w.id ORDER BY w.workout_date ASC, w.start_time ASC";

    $workouts = $db->fetchAll($sql, $params);

    // форматирование под фронт
    $formattedWorkouts = [];
    foreach ($workouts as $workout) {
      $spotsLeft = $workout['max_participants'] - $workout['current_participants'];
      $isFull = $spotsLeft <= 0;
      $isBooked = $workout['booked_by_client'] > 0;

      $formattedWorkouts[] = [
        'id' => $workout['id'],
        'title' => $workout['title'],
        'description' => $workout['description'],
        'workout_date' => $workout['workout_date'],
        'start_time' => $workout['start_time'],
        'end_time' => $workout['end_time'],
        'max_participants' => $workout['max_participants'],
        'current_participants' => $workout['current_participants'],
        'spots_left' => $spotsLeft,
        'is_full' => $isFull,
        'is_booked' => $isBooked,
        'status' => $workout['status'],
        'trainer' => [
          'id' => $workout['trainer_id'],
          'first_name' => $workout['trainer_first_name'],
          'last_name' => $workout['trainer_last_name'],
          'specialization' => $workout['trainer_specialization']
        ]
      ];
    }

    //  список тренеров для фильтра
    $trainers = $db->fetchAll(
      "SELECT id, first_name, last_name, specialization 
             FROM users 
             WHERE role = 'trainer' AND active = 1 
             ORDER BY first_name ASC"
    );

    echo json_encode([
      'success' => true,
      'workouts' => $formattedWorkouts,
      'trainers' => $trainers,
      'total' => count($formattedWorkouts)
    ]);
    exit;

  } else {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не разрешен']);
    exit;
  }

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
  exit;
}
?>