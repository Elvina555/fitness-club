<?php
if (isset($_GET['token'])) {
  setcookie('fitness_token', $_GET['token'], time() + 60 * 60 * 24 * 7, '/');
}
// классы
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Database.php';

$userModel = new User();
$db = new Database();

// проверка роли
$currentUser = $userModel->getCurrentUser();
if (!$currentUser || $currentUser['role'] !== 'admin') {
  header('Location: /login.html');
  exit;
}

// запросы к бд для статистики в дашборде
$stats = [];


$clients = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'client'");
$stats['total_clients'] = $clients['count'];

$trainers = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'trainer'");
$stats['total_trainers'] = $trainers['count'];

$workouts = $db->fetchOne("SELECT COUNT(*) as count FROM workouts 
                          WHERE workout_date >= CURDATE() 
                          AND workout_date <= DATE_ADD(CURDATE(), INTERVAL 1 DAY) 
                          AND status = 'scheduled'");
$stats['upcoming_workouts'] = $workouts['count'];

$bookings = $db->fetchOne("SELECT COUNT(*) as count FROM bookings 
                          WHERE status IN ('created', 'confirmed') 
                          AND workout_id IN (SELECT id FROM workouts WHERE workout_date >= CURDATE())");
$stats['active_bookings'] = $bookings['count'];

$reviews = $db->fetchOne("SELECT COUNT(*) as count FROM reviews WHERE moderation_status = 'pending'");
$stats['pending_reviews'] = $reviews['count'];

$revenue = $db->fetchOne("
    SELECT 
        COALESCE(SUM(
            CASE 
                WHEN s.type = 'month' THEN 3000
                WHEN s.type = '3months' THEN 8000
                WHEN s.type = '6months' THEN 15000
                WHEN s.type = 'year' THEN 25000
                ELSE 0
            END
        ), 0) as revenue
    FROM subscriptions s
    WHERE MONTH(s.created_at) = MONTH(CURDATE()) 
    AND YEAR(s.created_at) = YEAR(CURDATE())
    AND s.status = 'active'
");
$stats['monthly_revenue'] = $revenue['revenue'];

?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Админ панель - Фитнес Клуб</title>
  <link rel="stylesheet" href="../assets/css/common/UI.css">
  <link rel="stylesheet" href="../assets/css/admin/index.css">
</head>

<body>
  <div class="app-container">
    <nav class="navbar">
      <div class="navbar-brand">
        <h2>FitClub Admin</h2>
      </div>
      <!-- навигация -->
      <ul class="navbar-menu">
        <li><a href="index.php" class="active">Главная</a></li>
        <li><a href="users.php">Пользователи</a></li>
        <li><a href="workouts.php">Тренировки</a></li>
        <li><a href="reports.php">Отчеты</a></li>
        <li><a href="../logout.php">Выход</a></li>
      </ul>
    </nav>

    <main class="main-content">
      <div class="container">
        <div class="admin-header">
          <h2 style="color: var(--white)">Добро пожаловать, <?php echo htmlspecialchars($currentUser['first_name']); ?>!
          </h2>
          <p style="color: var(--white)">Панель администратора фитнес-клуба</p>
        </div>

        <div class="admin-stats">
          <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_clients'] ?? 0; ?></div>
            <div class="stat-label">Клиентов</div>
          </div>

          <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_trainers'] ?? 0; ?></div>
            <div class="stat-label">Тренеров</div>
          </div>

          <div class="stat-card">
            <div class="stat-number"><?php echo $stats['upcoming_workouts'] ?? 0; ?></div>
            <div class="stat-label">Ближайших тренировок</div>
          </div>

          <div class="stat-card">
            <div class="stat-number"><?php echo $stats['active_bookings'] ?? 0; ?></div>
            <div class="stat-label">Активных записей</div>
          </div>

          <div class="stat-card">
            <div class="stat-number"><?php echo $stats['pending_reviews'] ?? 0; ?></div>
            <div class="stat-label">Отзывов на модерации</div>
          </div>

          <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['monthly_revenue'] ?? 0, 0, ',', ' '); ?> ₽</div>
            <div class="stat-label">Доход за месяц</div>
          </div>
        </div>

        <!-- быстрый переход к управления пользователями -->
        <div class="admin-actions">
          <div class="action-card">
            <h3>Пользователи</h3>
            <ul class="action-list">
              <li><a href="users.php">Все пользователи</a></li>
              <li><a href="users.php?role=client">Клиенты</a></li>
              <li><a href="users.php?role=trainer">Тренеры</a></li>
              <li><a href="users.php?role=admin">Администраторы</a></li>
            </ul>
          </div>

          <!-- быстрый переход к расписанию -->
          <div class="action-card">
            <h3>Контент</h3>
            <ul class="action-list">
              <li><a href="workouts.php">Расписание тренировок</a></li>
            </ul>
          </div>

          <!-- быстрый переход к отчетам -->
          <div class="action-card">
            <h3>Финансы и отчеты</h3>
            <ul class="action-list">
              <li><a href="reports.php">Отчеты</a></li>
            </ul>
          </div>
        </div>
      </div>
    </main>

    <footer class="footer">
      <p>&copy; 2025 Фитнес Клуб. Все права защищены.</p>
    </footer>
  </div>
</body>

</html>