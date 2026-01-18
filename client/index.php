<?php
// проверка на то чтоб был токен
if (isset($_GET['token'])) {
  setcookie('fitness_token', $_GET['token'], time() + 60 * 60 * 24 * 7, '/');
}

// подключение классов
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Database.php';

$userModel = new User();
$db = new Database();

// если роль не клиент, то выкидываем с этой части системы на страницу входа
$currentUser = $userModel->getCurrentUser();
if (!$currentUser || $currentUser['role'] !== 'client') {
  header('Location: /login.html');
  exit;
}

// данные для дашборда
$db = new Database();

$activeBookings = $db->fetchOne(
  "SELECT COUNT(*) as count FROM bookings WHERE client_id = ? AND status = 'confirmed'",
  [$currentUser['id']]
);

$activeSubscriptions = $db->fetchOne(
  "SELECT COUNT(*) as count FROM subscriptions WHERE client_id = ? AND status = 'active'",
  [$currentUser['id']]
);

$upcomingWorkouts = $db->fetchOne(
  "SELECT COUNT(*) as count FROM bookings b 
     JOIN workouts w ON b.workout_id = w.id 
     WHERE b.client_id = ? AND w.workout_date >= CURDATE()",
  [$currentUser['id']]
);

$activeSubscription = $db->fetchOne(
  "SELECT * FROM subscriptions WHERE client_id = ? AND status = 'active' 
     ORDER BY end_date DESC LIMIT 1",
  [$currentUser['id']]
);

// рекомендуемые тренировки
$recommendedWorkouts = $db->fetchAll(
  "SELECT w.*, 
            u.first_name as trainer_first_name, u.last_name as trainer_last_name,
            COALESCE(AVG(r.rating), 0) as trainer_rating
     FROM workouts w
     JOIN users u ON w.trainer_id = u.id
     LEFT JOIN reviews r ON u.id = r.trainer_id AND r.moderation_status = 'approved'
     WHERE w.status = 'scheduled' AND w.workout_date >= CURDATE()
     GROUP BY w.id
     ORDER BY w.workout_date ASC, w.start_time ASC
     LIMIT 3"
);

?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="/assets/css/client/index.css">
  <link rel="stylesheet" href="../assets/css/common/UI.css">
  <title>Фитнес Клуб - Личный Кабинет</title>
</head>

<body>
  <div class="app-container">
    <nav class="navbar">
      <div class="navbar-brand">
        <h2>FitClub</h2>
      </div>
      <!-- навигация -->
      <ul class="navbar-menu">
        <li><a href="index.php">Главная</a></li>
        <li><a href="schedule.php">Расписание</a></li>
        <li><a href="subscription.php">Абонементы</a></li>
        <li><a href="notifications.php">Уведомления</a></li>
        <li><a href="attendance.php">Посещения</a></li>
        <li><a href="../logout.php">Выход</a></li>
      </ul>
    </nav>

    <main class="main-content">
      <div class="container">
        <h1 class="welcome-text">Добро пожаловать, <?php echo htmlspecialchars($currentUser['first_name']); ?>!</h1>

        <div class="dashboard-grid">
          <div class="dashboard-card">
            <h3>Мои записи</h3>
            <p class="card-number" style="color: black;"><?php echo $activeBookings['count']; ?></p>
          </div>

          <div class="dashboard-card">
            <h3>Кол-во абонементов</h3>
            <p class="card-number" style="color: black;"><?php echo $activeSubscriptions['count']; ?></p>
            <a href="subscription.php" class="submit-btn">Перейти</a>
          </div>

          <div class="dashboard-card">
            <h3>Будущие тренировки</h3>
            <p class="card-number" style="color: black;"><?php echo $upcomingWorkouts['count']; ?></p>
            <a href="schedule.php" class="submit-btn">Перейти</a>
          </div>

          <div class="dashboard-card">
            <h3>Мой профиль</h3>
            <p style="color: black;"><?php echo htmlspecialchars($currentUser['email']); ?></p>
            <a href="profile.php" class="submit-btn">Редактировать</a>
          </div>
        </div>

        <!-- в зависимости от абонемента (есть\отсутсвует) выводим разную инфу -->
        <?php if ($activeSubscription): ?>
          <section class="recent-section mt-20">
            <h3>Информация об абонементе</h3>
            <div class="info-box">
              <div class="subscription-card">
                <p><strong>Тип:</strong> <?php echo htmlspecialchars($activeSubscription['type']); ?></p>
                <p><strong>Посещений осталось:</strong> <?php echo $activeSubscription['visits_left']; ?> из
                  <?php echo $activeSubscription['visits_total']; ?>
                </p>
                <p><strong>Действует до:</strong> <?php echo date('d.m.Y', strtotime($activeSubscription['end_date'])); ?>
                </p>
                <p><strong>Дней осталось:</strong> <?php
                $daysLeft = ceil((strtotime($activeSubscription['end_date']) - time()) / (24 * 60 * 60));
                echo max(0, $daysLeft);
                ?></p>
              </div>
            </div>
          </section>
        <?php else: ?>
          <section class="recent-section">
            <div class="info-box" style="background: var(--white); border-left-color: var(--orange);">
              <p><strong>У вас нет активного абонемента.</strong><br>
                <a href="subscription.php">Выбрать абонемент</a>
              </p>
            </div>
          </section>
        <?php endif; ?>

        <section class="recommendations">
          <!-- аналогичный вывод инфы по тренировкам -->
          <h2>Рекомендуемые тренировки</h2>
          <?php if (count($recommendedWorkouts) > 0): ?>
            <div class="workouts-grid">
              <?php foreach ($recommendedWorkouts as $workout): ?>
                <div class="workout-card">
                  <h3 class="workout-name"><?php echo htmlspecialchars($workout['title']); ?></h3>
                  <p class="workout-trainer"><strong>Тренер:</strong>
                    <?php echo htmlspecialchars($workout['trainer_first_name'] . ' ' . $workout['trainer_last_name']); ?>
                  </p>
                  <p class="workout-date"><strong>Дата:</strong>
                    <?php echo date('d.m.Y', strtotime($workout['workout_date'])); ?></p>
                  <p class="workout-time"><strong>Время:</strong> <?php echo substr($workout['start_time'], 0, 5); ?> -
                    <?php echo substr($workout['end_time'], 0, 5); ?>
                  </p>
                  <p class="workout-participiants"><strong>Мест свободно:</strong>
                    <?php echo $workout['max_participants'] - $workout['current_participants']; ?>/<?php echo $workout['max_participants']; ?>
                  </p>
                  <p class="workout-rating"><strong>Рейтинг тренера:</strong>
                    <?php echo number_format($workout['trainer_rating'], 1); ?> </p>
                  <a href="schedule.php?date=<?php echo $workout['workout_date']; ?>" class="submit-btn"
                    style="width: 85%; ">Записаться</a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted">Нет доступных тренировок</p>
          <?php endif; ?>
        </section>
      </div>
    </main>
    <footer class="footer">
      <p>&copy; 2025 Фитнес Клуб. Все права защищены.</p>
    </footer>
  </div>
</body>

</html>