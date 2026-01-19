<?php
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Database.php';

$userModel = new User();
$db = new Database();

$currentUser = $userModel->getCurrentUser();
if (!$currentUser || $currentUser['role'] !== 'trainer') {
  header('Location: /login.html');
  exit;
}

$trainerId = $currentUser['id'];

// текущее время проверки не прошла ли тренировка
$currentDateTime = date('Y-m-d H:i:s');

// ВООБЩЕ ближайшие 3 тренировки которые ЕЩЁ НЕ ПРОШЛИ
$upcomingWorkouts = $db->fetchAll(
  "SELECT w.*, 
          COUNT(b.id) as bookings_count,
          (SELECT COUNT(*) FROM bookings b2 WHERE b2.workout_id = w.id AND b2.status = 'attended') as attended_count
   FROM workouts w
   LEFT JOIN bookings b ON w.id = b.workout_id AND b.status IN ('confirmed', 'attended')
   WHERE w.trainer_id = ? 
   AND w.status = 'scheduled'
   AND w.workout_date >= CURDATE()
   AND (w.workout_date > CURDATE() OR w.start_time >= CURTIME())
   GROUP BY w.id
   ORDER BY w.workout_date ASC, w.start_time ASC
   LIMIT 3",
  [$trainerId]
);

// всего ПРОВЕДЕННЫХ тренировок (токльо status completed)
$completedWorkouts = $db->fetchOne(
  "SELECT COUNT(*) as count
     FROM workouts 
     WHERE trainer_id = ? 
     AND status = 'completed'",
  [$trainerId]
);

// предстоящие трени (только status scheduled)
$upcomingWorkoutsCount = $db->fetchOne(
  "SELECT COUNT(*) as count
     FROM workouts 
     WHERE trainer_id = ? 
     AND status = 'scheduled'
     AND (workout_date > CURDATE() OR (workout_date = CURDATE() AND end_time > CURTIME()))",
  [$trainerId]
);

// уникальные клиентты
$uniqueClients = $db->fetchOne(
  "SELECT COUNT(DISTINCT b.client_id) as count
     FROM bookings b
     JOIN workouts w ON b.workout_id = w.id
     WHERE w.trainer_id = ? 
     AND b.status IN ('confirmed', 'attended')",
  [$trainerId]
);

// средний рейтинг
$avgRating = $db->fetchOne(
  "SELECT AVG(r.rating) as avg_rating
     FROM reviews r
     JOIN workouts w ON r.workout_id = w.id
     WHERE w.trainer_id = ? 
     AND r.moderation_status = 'approved'",
  [$trainerId]
);

// общее количество всех тренировок (для информации)
$allWorkoutsCount = $db->fetchOne(
  "SELECT COUNT(*) as count 
     FROM workouts 
     WHERE trainer_id = ?",
  [$trainerId]
);

// количество ВСЕХ предстоящих тренировок (scheduled и не прошедших)
$allUpcomingCount = $db->fetchOne(
  "SELECT COUNT(*) as count 
     FROM workouts 
     WHERE trainer_id = ? 
     AND status = 'scheduled'
     AND (workout_date > CURDATE() OR (workout_date = CURDATE() AND end_time > CURTIME()))",
  [$trainerId]
);

// потом убрать
$pastWorkoutsCount = $db->fetchOne(
  "SELECT COUNT(*) as count 
     FROM workouts 
     WHERE trainer_id = ? 
     AND (workout_date < CURDATE() OR (workout_date = CURDATE() AND end_time <= CURTIME()))",
  [$trainerId]
);

if (isset($_GET['token'])) {
  setcookie('fitness_token', $_GET['token'], time() + 60 * 60 * 24 * 7, '/');
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Главная - Тренер</title>
  <link rel="stylesheet" href="../assets/css/common/UI.css">
  <link rel="stylesheet" href="../assets/css/trainer/index.css">
  <style>

  </style>
</head>

<body>
  <div class="app-container">
    <nav class="navbar">
      <div class="navbar-brand">
        <h2>FitClub Trainer</h2>
      </div>
      <ul class="navbar-menu">
        <li><a href="index.php" class="active">Главная</a></li>
        <li><a href="schedule.php">Расписание</a></li>
        <li><a href="attedance.php">Посещаемость</a></li>
        <li><a href="profile.php">Профиль</a></li>
        <li><a href="../logout.php">Выход</a></li>
      </ul>
    </nav>

    <main class="main-content">
      <div class="container">
        <div class="trainer-dashboard">
          <div class="welcome-section">
            <h2>Добро пожаловать,
              <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>!
            </h2>
            <p>Ваша панель тренера. Здесь вы можете управлять тренировками, отслеживать посещаемость и просматривать
              статистику.</p>
            <?php if ($currentUser['specialization']): ?>
              <p><strong>Специализация:</strong> <?php echo htmlspecialchars($currentUser['specialization']); ?></p>
            <?php endif; ?>
          </div>

          <div class="dashboard-header">
            <h2>Ваша статистика</h2>
            <div class="quick-actions">
              <a href="schedule.php" class="btn-secondary">Расписание</a>
              <a href="attedance.php" class="btn-secondary">Посещаемость</a>
            </div>
          </div>

          <div class="stats-grid">
            <div class="stat-card">
              <div class="stat-value"><?php echo $completedWorkouts['count'] ?? 0; ?></div>
              <div class="stat-label">Всего проведенных тренировок</div>
            </div>
            <div class="stat-card">
              <div class="stat-value"><?php echo $upcomingWorkoutsCount['count'] ?? 0; ?></div>
              <div class="stat-label">Предстоящих тренировок</div>
            </div>
            <div class="stat-card">
              <div class="stat-value"><?php echo $uniqueClients['count'] ?? 0; ?></div>
              <div class="stat-label">Уникальных клиентов</div>
            </div>
            <div class="stat-card">
              <div class="stat-value"><?php echo number_format($avgRating['avg_rating'] ?? 0, 1); ?></div>
              <div class="stat-label">Средний рейтинг</div>
            </div>
          </div>
        </div>

        <!-- ближайшие трени (которые ЕЩЁ НЕ ПРОШЛИ) -->
        <!-- ближайшие трени (которые ЕЩЁ НЕ ПРОШЛИ) -->
        <div class="upcoming-workouts">
          <h3>
            Ваши ближайшие тренировки
            <span>(предстоящих: <?php echo $allUpcomingCount['count'] ?? 0; ?>)</span>
            <?php if (($allUpcomingCount['count'] ?? 0) > 3): ?>
              <a href="schedule.php" class="view-all-link">
                Показать все (<?php echo ($allUpcomingCount['count'] ?? 0) - 3; ?> еще) →
              </a>
            <?php endif; ?>
          </h3>

          <?php if (!empty($upcomingWorkouts)): ?>
            <div class="workouts-grid">
              <?php foreach ($upcomingWorkouts as $index => $workout):
                $occupancy = $workout['max_participants'] > 0
                  ? round(($workout['bookings_count'] / $workout['max_participants']) * 100, 1)
                  : 0;
                $isToday = $workout['workout_date'] == date('Y-m-d');
                $isTomorrow = $workout['workout_date'] == date('Y-m-d', strtotime('+1 day'));
                $currentTime = time();
                $workoutStartTime = strtotime($workout['workout_date'] . ' ' . $workout['start_time']);
                $workoutEndTime = strtotime($workout['workout_date'] . ' ' . $workout['end_time']);
                $isInProgress = $currentTime >= $workoutStartTime && $currentTime <= $workoutEndTime;

                if ($isToday) {
                  $priorityClass = 'priority-high';
                  $hoursUntil = round(($workoutStartTime - $currentTime) / 3600, 1);
                  if ($isInProgress) {
                    $priorityText = "Сейчас";
                  } else {
                    $priorityText = "Через " . max(0, $hoursUntil) . " ч";
                  }
                } elseif ($isTomorrow) {
                  $priorityClass = 'priority-medium';
                  $priorityText = "Завтра";
                } else {
                  $priorityClass = 'priority-low';
                  $daysUntil = floor(($workoutStartTime - $currentTime) / (60 * 60 * 24));
                  $priorityText = "$daysUntil дн";
                }

                $start = strtotime($workout['start_time']);
                $end = strtotime($workout['end_time']);
                $duration = round(($end - $start) / 3600, 1);
                ?>
                <div class="workout-card">
                  <div class="workout-header">
                    <div
                      style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                      <div>
                        <span class="workout-number"
                          style="background: var(--light-green); color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                          #<?php echo $index + 1; ?>
                        </span>
                        <span class="workout-priority <?php echo $priorityClass; ?>" style="margin-left: 8px;">
                          <?php echo $priorityText; ?>
                        </span>
                        <?php if ($isInProgress): ?>
                          <span class="workout-status status-in-progress">ИДЕТ СЕЙЧАС</span>
                        <?php else: ?>
                          <span class="workout-status status-scheduled">Запланирована</span>
                        <?php endif; ?>
                      </div>
                    </div>

                    <h4 class="workout-title" style="margin: 10px 0; font-size: 20px;">
                      <?php echo htmlspecialchars($workout['title']); ?>
                    </h4>

                    <div class="workout-date">
                      <?php if ($isToday): ?>
                        <span><strong>Сегодня</strong>, <?php echo date('H:i', strtotime($workout['start_time'])); ?></span>
                      <?php elseif ($isTomorrow): ?>
                        <span><strong>Завтра</strong>, <?php echo date('H:i', strtotime($workout['start_time'])); ?></span>
                      <?php else: ?>
                        <span><?php echo date('d.m.Y', strtotime($workout['workout_date'])) . ', ' . date('H:i', strtotime($workout['start_time'])); ?></span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="workout-body">
                    <div class="workout-details">
                      <div class="detail-item">
                        <span class="detail-label">Время:</span>
                        <span class="detail-value">
                          <?php echo date('H:i', strtotime($workout['start_time'])) . ' - ' . date('H:i', strtotime($workout['end_time'])); ?>
                        </span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-label">Участники:</span>
                        <span class="detail-value">
                          <strong><?php echo $workout['bookings_count']; ?></strong>/<?php echo $workout['max_participants']; ?>
                          <?php if ($workout['attended_count'] > 0): ?>
                            <span style="color: #17a2b8; margin-left: 5px;">
                              (<?php echo $workout['attended_count']; ?> посетило)
                            </span>
                          <?php endif; ?>
                        </span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-label">Длительность:</span>
                        <span class="detail-value"><?php echo $duration; ?> ч</span>
                      </div>
                    </div>

                    <div class="progress-container">
                      <div class="progress-label">
                        <span>Загруженность</span>
                        <span><strong><?php echo $occupancy; ?>%</strong></span>
                      </div>
                      <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo min($occupancy, 100); ?>%"></div>
                      </div>
                    </div>

                    <?php if ($workout['description']): ?>
                      <div
                        style="color: var(--color-text-secondary); font-size: 14px; margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 8px; border-left: 3px solid var(--green);">
                        <?php echo htmlspecialchars($workout['description']); ?>
                      </div>
                    <?php endif; ?>
                  </div>

                  <div class="workout-actions">
                    <?php if ($isToday): ?>
                      <?php if ($isInProgress): ?>
                        <a href="attedance.php?workout_id=<?php echo $workout['id']; ?>" class="submit-btn"
                          style="background: #28a745;">
                          Отметить посещение
                        </a>
                      <?php else: ?>
                        <a href="attedance.php?workout_id=<?php echo $workout['id']; ?>" class="submit-btn">
                          Посещаемость
                        </a>
                      <?php endif; ?>
                    <?php else: ?>
                      <a href="attedance.php?workout_id=<?php echo $workout['id']; ?>" class="submit-btn">
                        👥 Участники (<?php echo $workout['bookings_count']; ?>)
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="empty-state no-upcoming">
              <h3>Нет предстоящих тренировок</h3>
              <p>У вас нет запланированных тренировок на ближайшее время.</p>
              <a href="schedule.php" class="submit-btn"
                style="margin-top: 20px; display: inline-block; width: auto; padding: 12px 24px;">
                + Создать тренировку
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>
  </div>
  </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      // обнова времени тренировок в реальном времени
      function updateWorkoutTimes() {
        const now = new Date();
        const today = now.toISOString().split('T')[0];
        const tomorrow = new Date(now);
        tomorrow.setDate(tomorrow.getDate() + 1);
        const tomorrowStr = tomorrow.toISOString().split('T')[0];

        document.querySelectorAll('.workout-date').forEach(function (element) {
          const dateElement = element.querySelector('span:nth-child(2)');
          if (dateElement) {
            const text = dateElement.innerHTML;

            if (text.includes('<strong>Сегодня</strong>') || text.includes('<strong>Завтра</strong>')) {
              return;
            }
            const parts = text.split(',');
            if (parts.length === 2) {
              const dateStr = parts[0].trim();
              const timeStr = parts[1].trim();
              const [day, month, year] = dateStr.split('.');
              const workoutDate = `${year}-${month}-${day}`;
              if (workoutDate === today) {
                dateElement.innerHTML = `<strong>Сегодня</strong>, ${timeStr}`;
                const priorityElement = element.closest('.workout-header').querySelector('.workout-priority');
                if (priorityElement) {
                  priorityElement.className = 'workout-priority priority-high';
                }
              } else if (workoutDate === tomorrowStr) {
                dateElement.innerHTML = `<strong>Завтра</strong>, ${timeStr}`;
                const priorityElement = element.closest('.workout-header').querySelector('.workout-priority');
                if (priorityElement) {
                  priorityElement.className = 'workout-priority priority-medium';
                  priorityElement.textContent = "Завтра";
                }
              }
            }
          }
        });
      }
      updateWorkoutTimes();
      setInterval(updateWorkoutTimes, 60000);

      //  идет ли сейчас треня
      function checkInProgressWorkouts() {
        const now = new Date();
        const today = now.toISOString().split('T')[0];
        const currentTime = now.getHours() * 60 + now.getMinutes();

        document.querySelectorAll('.workout-card').forEach(function (card) {
          const dateElement = card.querySelector('.workout-date span:nth-child(2)');
          if (dateElement && dateElement.innerHTML.includes('<strong>Сегодня</strong>')) {
            const timeMatch = dateElement.innerHTML.match(/\d{2}:\d{2}/);
            if (timeMatch) {
              const [hours, minutes] = timeMatch[0].split(':').map(Number);
              const workoutTime = hours * 60 + minutes;
              const duration = 60;

              if (workoutTime <= currentTime && currentTime <= workoutTime + duration) {

                const statusElement = card.querySelector('.workout-status');
                if (statusElement) {
                  statusElement.className = 'workout-status status-in-progress';
                  statusElement.textContent = 'ИДЕТ СЕЙЧАС';
                }
                const priorityElement = card.querySelector('.workout-priority');
                if (priorityElement) {
                  priorityElement.textContent = 'Идет сейчас';
                }
                const actionBtn = card.querySelector('.btn-primary');
                if (actionBtn) {
                  actionBtn.innerHTML = '<span></span> Идет сейчас - отмечать!';
                  actionBtn.style.background = '#28a745';
                }
                const timeDetail = card.querySelector('.detail-item:nth-child(1) .detail-value');
                if (timeDetail && !timeDetail.innerHTML.includes('Идет сейчас')) {
                  timeDetail.innerHTML += ' <span style="color: #28a745; margin-left: 10px;">Идет сейчас</span>';
                }
              }
            }
          }
        });
      }

      // проверка прогресса тренировки
      checkInProgressWorkouts();
      setInterval(checkInProgressWorkouts, 30000);

      const workoutCards = document.querySelectorAll('.workout-card');
      workoutCards.forEach(card => {
        card.addEventListener('mouseenter', function () {
          this.style.transform = 'translateY(-5px)';
        });

        card.addEventListener('mouseleave', function () {
          this.style.transform = 'translateY(0)';
        });
      });

      const todayWorkouts = document.querySelectorAll('.workout-date');
      let hasTodayWorkout = false;
      let todayWorkoutTitle = '';

      todayWorkouts.forEach(function (element) {
        if (element.innerHTML.includes('<strong>Сегодня</strong>')) {
          hasTodayWorkout = true;
          todayWorkoutTitle = element.closest('.workout-card').querySelector('.workout-title').textContent;
        }
      });

      if (hasTodayWorkout) {
        setTimeout(function () {
          alert(` Напоминание: у вас сегодня тренировка "${todayWorkoutTitle}"!`);
        }, 1500);
      }
    });
  </script>
</body>

</html>