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

if (isset($_GET['token'])) {
  setcookie('fitness_token', $_GET['token'], time() + 60 * 60 * 24 * 7, '/');
}

$selectedWorkoutId = $_GET['workout_id'] ?? null;
$workout = null;
$participants = [];

if ($selectedWorkoutId) {
  // инфа о тренировке
  $workout = $db->fetchOne(
    "SELECT w.* FROM workouts w WHERE w.id = ? AND w.trainer_id = ?",
    [$selectedWorkoutId, $trainerId]
  );

  if ($workout) {
    // участники тренировки
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
      [$selectedWorkoutId]
    );
  }
}

// все тренировки для выпадающего списка
$allWorkouts = $db->fetchAll(
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
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Посещаемость - Тренер</title>
  <link rel="stylesheet" href="../assets/css/common/UI.css">
  <style>
    .trainer-container {
      margin: 0 auto;
    }

    .page-header {
      margin-bottom: 32px;
      padding: 16px;
      background-color: var(--white);
      max-width: 1200px;
      border-radius: 12px;
      border: 2px var(--orange);
    }

    .page-header p {
      opacity: 0.9;
      margin: 0;
    }

    .workout-selector {
      background: var(--white);
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 30px;
      border: 1px solid var(--orange);
    }

    .workout-selector h3 {
      margin-top: 0;
      margin-bottom: 15px;
    }

    .select-container {
      display: flex;
      gap: 10px;
      align-items: center;
    }

    .select-container select {
      flex: 1;
      padding: 10px;
      border: 1px solid var(--orange);
      border-radius: 8px;
      background: var(--light-green);
      color: var(--dark-blue);
      font-size: 16px;
    }

    .workout-info {
      background: var(--white);
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 30px;
      border: 1px solid var(--orange);
      display:
        <?php echo $workout ? 'block' : 'none'; ?>
      ;
    }

    .workout-info h3 {
      margin-top: 0;
      margin-bottom: 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .workout-details {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 20px;
    }

    .detail-item {
      display: flex;
      flex-direction: column;
    }

    .detail-label {
      font-size: 12px;
      color: var(--green);
      margin-bottom: 5px;
    }

    .detail-value {
      font-weight: 500;
      color: var(--dark-blue);
    }

    .participants-table {
      width: 100%;
      background: var(--white);
      border-radius: 12px;
      overflow: hidden;
      border: 1px solid var(--orange);
      margin-bottom: 30px;
      display:
        <?php echo $workout ? 'block' : 'none'; ?>
      ;
    }

    .participants-table th {
      background: var(--green);
      padding: 15px;
      text-align: left;
      font-weight: 500;
      color: var(--white);
      border-bottom: 1px solid var(--orange);
    }

    .participants-table td {
      padding: 15px;
      border-bottom: 1px solid var(--orange);
      vertical-align: middle;
    }

    .participants-table tr:last-child td {
      border-bottom: none;
    }

    .participants-table tr:hover {
      background: var(--light-orange);
    }

    .attendance-status {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 50;
    }

    .status-attended {
      background: var(--light-green);
      color: var(--white);
    }

    .status-missed {
      background: var(--orange);
      color: var(--white);
    }

    .status-pending {
      background: #ffc107;
      color: #212529;
    }

    .attendance-buttons {
      display: flex;
      gap: 8px;
    }

    .notes-container {
      min-width: 250px;
    }

    .notes-textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid var(--orange);
      border-radius: 8px;
      font-size: 12px;
      resize: vertical;
      min-height: 60px;
      margin-bottom: 8px;
      background: var(--light-green);
      color: var(--dark-blue);
    }

    .notes-actions {
      display: flex;
      gap: 8px;
      justify-content: flex-end;
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--light-green);
    }

    .empty-state i {
      font-size: 48px;
      margin-bottom: 20px;
      opacity: 0.5;
    }

    .alert {
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      display: none;
      background: var(--light-green);
      color: var(--white);
      border: 1px solid var(--orange);
    }

    .alert-error {
      background: var(--orange);
      color: var(--white);
    }

    .loading {
      text-align: center;
      padding: 40px;
      color: var(--light-green);
    }

    .stats-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 15px;
      margin-bottom: 20px;
    }

    .stat-card {
      background: var(--orange);
      border-radius: 12px;
      padding: 15px;
      text-align: center;
      border: 1px solid var(--orange);
    }

    .stat-value {
      font-size: 18px;
      font-weight: 600;
      color: var(--white);
      margin-bottom: 5px;
    }

    .stat-label {
      font-size: 12px;
      color: var(--white);
    }

    .last-updated {
      font-size: 12px;
      color: var(--light-green);
      margin-top: 5px;
      font-style: italic;
    }

    .btn-sm {
      padding: 6px 12px;
      font-size: 12px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }

    .btn-primary {
      background: var(--light-green);
      color: var(--white);
    }

    .btn-secondary {
      background: var(--orange);
      color: var(--white);
    }

    .btn-success {
      background: var(--light-green);
      color: var(--white);
      border: none;
      padding: 8px 16px;
      border-radius: 5px;
      cursor: pointer;
    }

    .btn-danger {
      background: var(--orange);
      color: var(--white);
      border: none;
      padding: 8px 16px;
      border-radius: 5px;
      cursor: pointer;
    }
  </style>
</head>

<body>
  <div class="app-container">
    <nav class="navbar">
      <div class="navbar-brand">
        <h2>FitClub Trainer</h2>
      </div>
      <ul class="navbar-menu">
        <li><a href="index.php">Главная</a></li>
        <li><a href="schedule.php">Расписание</a></li>
        <li><a href="attedance.php" class="active">Посещаемость</a></li>
        <li><a href="profile.php">Профиль</a></li>
        <li><a href="../logout.php">Выход</a></li>
      </ul>
    </nav>
    <main class="main-content">
      <div class="container">
        <div class="trainer-container">
          <div class="page-header">
            <div>
              <h2> Учет посещаемости</h2>
              <p>Отмечайте посещение клиентов на тренировках и оставляйте заметки</p>
            </div>
          </div>
          <div class="alert" id="messageAlert"></div>
          <div class="workout-selector">
            <h3>Выберите тренировку</h3>
            <div class="select-container">
              <select id="workoutSelect" style="background-color: var(--white); border-radius: 11px; padding: 16px;"
                onchange="onWorkoutSelect()">
                <option value="">-- Выберите тренировку --</option>
                <!-- перебор всех инфы о тренировках тренера -->
                <?php foreach ($allWorkouts as $workoutOption):
                  $isToday = $workoutOption['workout_date'] == date('Y-m-d');
                  $statusText = [
                    'scheduled' => 'Запланирована',
                    'completed' => 'Проведена',
                    'cancelled' => 'Отменена'
                  ];
                  ?>
                  <option value="<?php echo $workoutOption['id']; ?>" <?php echo ($selectedWorkoutId == $workoutOption['id']) ? 'selected' : ''; ?>>
                    <?php echo date('d.m.Y', strtotime($workoutOption['workout_date'])) . ' ' .
                      date('H:i', strtotime($workoutOption['start_time'])) . ' - ' .
                      date('H:i', strtotime($workoutOption['end_time'])) . ' | ' .
                      htmlspecialchars($workoutOption['title']) . ' | ' .
                      ($statusText[$workoutOption['status']] ?? $workoutOption['status']) .
                      ($isToday ? ' (Сегодня)' : ''); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button class="submit-btn" onclick="refreshWorkouts()">
                Обновить
              </button>
            </div>
          </div>

          <!-- выводим инфу о тренировке -->
          <?php if ($workout): ?>
            <div class="workout-info" id="workoutInfo">
              <h3>
                <?php echo htmlspecialchars($workout['title']); ?>
                <span style="font-size: var(--font-size-sm); font-weight: normal;">
                  <?php
                  $statusText = [
                    'scheduled' => 'Запланирована',
                    'completed' => 'Проведена',
                    'cancelled' => 'Отменена'
                  ];
                  echo $statusText[$workout['status']] ?? $workout['status'];
                  ?>
                </span>
              </h3>

              <div class="stats-cards">
                <div class="stat-card">
                  <div class="stat-value"><?php echo count($participants); ?></div>
                  <div class="stat-label">Всего участников</div>
                </div>
                <div class="stat-card">
                  <div class="stat-value">
                    <?php
                    $attendedCount = array_filter($participants, function ($p) {
                      return $p['attended'] == 1;
                    });
                    echo count($attendedCount);
                    ?>
                  </div>
                  <div class="stat-label">Посетило</div>
                </div>
                <div class="stat-card">
                  <div class="stat-value">
                    <?php
                    $missedCount = array_filter($participants, function ($p) {
                      return $p['attended'] === '0';
                    });
                    echo count($missedCount);
                    ?>
                  </div>
                  <div class="stat-label">Отсутствовало</div>
                </div>
                <div class="stat-card">
                  <div class="stat-value">
                    <?php
                    $pendingCount = array_filter($participants, function ($p) {
                      return $p['attended'] === null;
                    });
                    echo count($pendingCount);
                    ?>
                  </div>
                  <div class="stat-label">Ожидают отметки</div>
                </div>
              </div>

              <!-- выводим детали каждой тренировки -->
              <div class="workout-details">
                <div class="detail-item">
                  <span class="detail-label">Дата:</span>
                  <span class="detail-value"><?php echo date('d.m.Y', strtotime($workout['workout_date'])); ?></span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Время:</span>
                  <span class="detail-value">
                    <?php echo date('H:i', strtotime($workout['start_time'])) . ' - ' . date('H:i', strtotime($workout['end_time'])); ?>
                  </span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Макс. участников:</span>
                  <span class="detail-value"><?php echo $workout['max_participants']; ?></span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Создана:</span>
                  <span class="detail-value"><?php echo date('d.m.Y H:i', strtotime($workout['created_at'])); ?></span>
                </div>
              </div>

              <?php if ($workout['description']): ?>
                <div
                  style="color: var(--dark-blue); margin-top: 15px; padding: 10px; background: var(--light-green); border-radius: 8px;">
                  <?php echo htmlspecialchars($workout['description']); ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- инфа о участниках тренировки -->
            <div class="participants-table" id="participantsTable">
              <table>
                <thead>
                  <tr>
                    <th>Клиент</th>
                    <th>Контакты</th>
                    <th>Абонемент</th>
                    <th>Статус посещения</th>
                    <th class="notes-container">Заметки</th>
                    <th>Действия</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($participants)): ?>
                    <tr>
                      <td colspan="6" style="text-align: center; padding: 40px;">
                        <i class="fas fa-users"
                          style="font-size: 36px; color: var(--light-green); margin-bottom: 15px; display: block;"></i>
                        <p style="color: var(--dark-blue);">Нет записавшихся участников</p>
                      </td>
                    </tr>
                  <?php else: ?>
                    <!-- перебираем инфу каждого посетившего тренировку -->
                    <?php foreach ($participants as $participant):
                      $attended = $participant['attended'];
                      $attendedText = '';
                      $statusClass = '';

                      if ($attended === null) {
                        $attendedText = 'Не отмечено';
                        $statusClass = 'status-pending';
                      } elseif ($attended == 1) {
                        $attendedText = 'Посетил';
                        $statusClass = 'status-attended';
                      } else {
                        $attendedText = 'Отсутствовал';
                        $statusClass = 'status-missed';
                      }

                      $notes = $participant['notes'] ?? '';
                      $markedAt = $participant['marked_at'] ?? '';
                      ?>
                      <tr data-booking-id="<?php echo $participant['booking_id']; ?>">
                        <td>
                          <strong><?php echo htmlspecialchars($participant['last_name'] . ' ' . $participant['first_name']); ?></strong><br>
                          <small style="color: var(--dark-blue);">ID: <?php echo $participant['client_id']; ?></small>
                        </td>
                        <td>
                          <?php echo htmlspecialchars($participant['email']); ?><br>
                          <?php if ($participant['phone']): ?>
                            <small
                              style="color: var(--dark-blue);"><?php echo htmlspecialchars($participant['phone']); ?></small>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if ($participant['subscription_type']):
                            $subscriptionTypes = [
                              'month' => 'Месячный',
                              '3months' => '3 месяца',
                              'year' => 'Годовой'
                            ];
                            ?>
                            <span style="font-size: 12px;">
                              <?php echo $subscriptionTypes[$participant['subscription_type']] ?? $participant['subscription_type']; ?>
                            </span><br>
                            <small style="color: var(--dark-blue);">
                              Осталось: <?php echo $participant['visits_left']; ?> посещ.
                            </small>
                          <?php else: ?>
                            <span style="color: var(--dark-blue); font-size: 12px;">Нет абонемента</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <!-- статус посещения -->
                          <span class="attendance-status <?php echo $statusClass; ?>"
                            id="status-<?php echo $participant['booking_id']; ?>">
                            <?php echo $attendedText; ?>
                          </span><br>
                          <?php if ($markedAt): ?>
                            <small style="color: var(--dark-blue); font-size: 11px;">
                              <?php echo date('d.m.Y H:i', strtotime($markedAt)); ?>
                            </small>
                          <?php endif; ?>
                        </td>
                        <td>
                          <!-- Упрощенное поле для заметок -->
                          <div class="notes-form">
                            <textarea class="notes-textarea" id="notes-textarea-<?php echo $participant['booking_id']; ?>"
                              placeholder="Введите заметку о клиенте..."><?php echo htmlspecialchars($notes); ?></textarea>
                            <div class="notes-actions">
                              <button class="btn-sm btn-primary"
                                onclick="saveNotes(<?php echo $participant['booking_id']; ?>)">
                                Сохранить заметку
                              </button>
                            </div>
                            <?php if ($markedAt && $notes): ?>
                              <div class="last-updated">
                                Обновлено: <?php echo date('H:i', strtotime($markedAt)); ?>
                              </div>
                            <?php endif; ?>
                          </div>
                        </td>
                        <td>
                          <!-- кнопки для отметки -->
                          <div class="attendance-buttons">
                            <?php if ($attended !== 1): ?>
                              <button class="btn-success"
                                onclick="markAttendance(<?php echo $participant['booking_id']; ?>, 1)">
                                Присутствовал
                              </button>
                            <?php endif; ?>
                            <?php if ($attended !== 0): ?>
                              <button class="btn-danger" onclick="markAttendance(<?php echo $participant['booking_id']; ?>, 0)">
                                Отсутствовал
                              </button>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="empty-state">
              <i class="fas fa-clipboard-list"></i>
              <h3>Выберите тренировку</h3>
              <p>Выберите тренировку из списка выше для просмотра и отметки посещаемости</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>

  <script>
    // вывод сообщения
    function showMessage(message, type = 'success') {
      const alert = document.getElementById('messageAlert');
      alert.textContent = message;
      alert.className = type === 'success' ? 'alert' : 'alert alert-error';
      alert.style.display = 'block';
      alert.scrollIntoView({ behavior: 'smooth', block: 'center' });

      setTimeout(() => {
        alert.style.display = 'none';
      }, 5000);
    }

    // выбор тренировки
    function onWorkoutSelect() {
      const select = document.getElementById('workoutSelect');
      const workoutId = select.value;

      if (workoutId) {
        window.location.href = `attedance.php?workout_id=${workoutId}`;
      }
    }

    function refreshWorkouts() {
      window.location.reload();
    }

    // отметка посещения
    async function markAttendance(bookingId, attended) {
      const notesTextarea = document.getElementById(`notes-textarea-${bookingId}`);
      const notes = notesTextarea ? notesTextarea.value.trim() : '';

      try {
        const response = await fetch('../api/trainer/trainer-attendance.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            action: 'mark_attendance',
            booking_id: bookingId,
            attended: attended,
            notes: notes
          })
        });

        const result = await response.json();

        if (result.success) {
          updateAttendanceStatus(bookingId, attended);
          showMessage(result.message, 'success');
        } else {
          showMessage(result.message, 'error');
        }
      } catch (error) {
        showMessage('Ошибка сети. Попробуйте позже.', 'error');
        console.error('Ошибка:', error);
      }
    }

    // обнова статуса посещения
    function updateAttendanceStatus(bookingId, attended) {
      const statusElement = document.getElementById(`status-${bookingId}`);
      const attendedText = attended ? 'Посетил' : 'Отсутствовал';
      const statusClass = attended ? 'status-attended' : 'status-missed';
      statusElement.textContent = attendedText;
      statusElement.className = `attendance-status ${statusClass}`;

      const now = new Date();
      const timeElement = document.createElement('small');
      timeElement.style.cssText = 'color: var(--dark-blue); font-size: 11px; display: block; margin-top: 5px;';
      timeElement.textContent = 'Отмечено: ' + now.toLocaleDateString('ru-RU') + ' ' +
        now.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });

      // удаление старого элемент времени, если есть
      const oldTimeElement = statusElement.nextElementSibling;
      if (oldTimeElement && oldTimeElement.tagName === 'SMALL') {
        oldTimeElement.remove();
      }
      statusElement.parentNode.appendChild(timeElement);
    }

    // сохранение заметок
    async function saveNotes(bookingId) {
      const textarea = document.getElementById(`notes-textarea-${bookingId}`);
      const notes = textarea.value.trim();

      try {
        const response = await fetch('../api/trainer/trainer-attendance.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            action: 'save_notes',
            booking_id: bookingId,
            notes: notes
          })
        });

        const result = await response.json();

        if (result.success) {
          // обновляем время последнего обновления
          const notesForm = textarea.closest('.notes-form');
          const lastUpdated = notesForm.querySelector('.last-updated');
          const now = new Date();

          if (!lastUpdated) {
            const newLastUpdated = document.createElement('div');
            newLastUpdated.className = 'last-updated';
            newLastUpdated.textContent = 'Обновлено: ' + now.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
            notesForm.appendChild(newLastUpdated);
          } else {
            lastUpdated.textContent = 'Обновлено: ' + now.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
          }

          showMessage(result.message, 'success');
        } else {
          showMessage(result.message, 'error');
        }
      } catch (error) {
        showMessage('Ошибка сети. Попробуйте позже.', 'error');
        console.error('Ошибка:', error);
      }
    }

    // авто обновление страницы если тренировка идет сейчас
    function checkCurrentWorkout() {
      const workoutInfo = document.getElementById('workoutInfo');
      if (workoutInfo) {
        // не закончилась ли тренировка
        const now = new Date();
        const workoutDate = '<?php echo $workout ? $workout["workout_date"] : ""; ?>';
        const endTime = '<?php echo $workout ? $workout["end_time"] : ""; ?>';

        if (workoutDate && endTime) {
          const workoutEnd = new Date(`${workoutDate}T${endTime}`);

          // если треня прямо сейчас обновляем страницу 30 секунд
          if (now < workoutEnd && now > new Date(workoutEnd.getTime() - 2 * 60 * 60 * 1000)) {
            setTimeout(() => {
              window.location.reload();
            }, 30000);
          }
        }
      }
    }

    document.addEventListener('DOMContentLoaded', function () {
      checkCurrentWorkout();

      <?php if (!$workout && !empty($allWorkouts)): ?>
        document.getElementById('workoutSelect').focus();
      <?php endif; ?>

      // Сохранение заметок по Ctrl+Enter
      document.addEventListener('keydown', function (event) {
        if (event.ctrlKey && event.key === 'Enter') {
          const activeTextarea = document.activeElement;
          if (activeTextarea && activeTextarea.className.includes('notes-textarea')) {
            const bookingId = activeTextarea.id.replace('notes-textarea-', '');
            event.preventDefault();
            saveNotes(bookingId);
          }
        }
      });
    });
  </script>
</body>

</html>