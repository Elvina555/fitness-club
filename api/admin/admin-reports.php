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

$action = $_GET['action'] ?? '';

try {
  if ($action === 'stats') {
    getStatistics($db);
  } else {
    echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
  }
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  exit;
}

function getStatistics($db)
{
  $dailyDate = $_GET['daily'] ?? date('Y-m-d');
  $weeklyDate = $_GET['weekly'] ?? date('Y-m-d');
  $monthlyDate = $_GET['monthly'] ?? date('Y-m');

  $response = [
    'success' => true,
    'daily' => getDailyStats($db, $dailyDate),
    'weekly' => getWeeklyStats($db, $weeklyDate),
    'monthly' => getMonthlyStats($db, $monthlyDate)
  ];

  echo json_encode($response, JSON_NUMERIC_CHECK);
}

function getDailyStats($db, $date)
{
  $workouts = $db->fetchOne(
    "SELECT COUNT(*) as count FROM workouts 
     WHERE workout_date = ? 
     AND status IN ('scheduled', 'completed')",
    [$date]
  );

  $visitors = $db->fetchOne(
    "SELECT COUNT(DISTINCT b.client_id) as count 
     FROM bookings b
     JOIN workouts w ON b.workout_id = w.id
     WHERE w.workout_date = ? 
     AND b.status IN ('confirmed', 'attended')",
    [$date]
  );

  $income = $db->fetchOne(
    "SELECT COALESCE(SUM(
            CASE type 
                WHEN 'month' THEN 3000 
                WHEN '3months' THEN 8000 
                WHEN '6months' THEN 15000 
                WHEN 'year' THEN 25000 
                ELSE 500 
            END
        ), 0) as income
     FROM subscriptions
     WHERE DATE(created_at) = ? AND status = 'active'",
    [$date]
  );

  return [
    'workouts' => (int) ($workouts['count'] ?? 0),
    'visitors' => (int) ($visitors['count'] ?? 0),
    'income' => (int) ($income['income'] ?? 0)
  ];
}

function getWeeklyStats($db, $startDate)
{
  $start = date('Y-m-d', strtotime('monday this week', strtotime($startDate)));
  $end = date('Y-m-d', strtotime('sunday this week', strtotime($startDate)));

  $workouts = $db->fetchOne(
    "SELECT COUNT(*) as count FROM workouts 
     WHERE workout_date BETWEEN ? AND ? 
     AND status IN ('scheduled', 'completed')",
    [$start, $end]
  );

  $visitors = $db->fetchOne(
    "SELECT COUNT(DISTINCT b.client_id) as count 
     FROM bookings b
     JOIN workouts w ON b.workout_id = w.id
     WHERE w.workout_date BETWEEN ? AND ? 
     AND b.status IN ('confirmed', 'attended')",
    [$start, $end]
  );

  $income = $db->fetchOne(
    "SELECT COALESCE(SUM(
            CASE type 
                WHEN 'month' THEN 3000 
                WHEN '3months' THEN 8000 
                WHEN '6months' THEN 15000 
                WHEN 'year' THEN 25000 
                ELSE 500 
            END
        ), 0) as income
     FROM subscriptions
     WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'active'",
    [$start, $end]
  );

  return [
    'workouts' => (int) ($workouts['count'] ?? 0),
    'visitors' => (int) ($visitors['count'] ?? 0),
    'income' => (int) ($income['income'] ?? 0)
  ];
}

function getMonthlyStats($db, $month)
{
  $start = date('Y-m-01', strtotime($month));
  $end = date('Y-m-t', strtotime($month));

  $workouts = $db->fetchOne(
    "SELECT COUNT(*) as count FROM workouts 
     WHERE workout_date BETWEEN ? AND ? 
     AND status IN ('scheduled', 'completed')",
    [$start, $end]
  );

  $visitors = $db->fetchOne(
    "SELECT COUNT(DISTINCT b.client_id) as count 
     FROM bookings b
     JOIN workouts w ON b.workout_id = w.id
     WHERE w.workout_date BETWEEN ? AND ? 
     AND b.status IN ('confirmed', 'attended')",
    [$start, $end]
  );

  $income = $db->fetchOne(
    "SELECT COALESCE(SUM(
            CASE type 
                WHEN 'month' THEN 3000 
                WHEN '3months' THEN 8000 
                WHEN '6months' THEN 15000 
                WHEN 'year' THEN 25000 
                ELSE 500 
            END
        ), 0) as income
     FROM subscriptions
     WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'active'",
    [$start, $end]
  );

  return [
    'workouts' => (int) ($workouts['count'] ?? 0),
    'visitors' => (int) ($visitors['count'] ?? 0),
    'income' => (int) ($income['income'] ?? 0)
  ];
}
?>