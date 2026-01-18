<?php
// классы
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Database.php';
$userModel = new User();
$db = new Database();

// проверка роли
$currentUser = $userModel->getCurrentUser();
if (!$currentUser || $currentUser['role'] !== 'trainer') {
  header('Location: /login.html');
  exit;
}

$trainerId = $currentUser['id'];

// проверка токена
if (isset($_GET['token'])) {
  setcookie('fitness_token', $_GET['token'], time() + 60 * 60 * 24 * 7, '/');
}

// трени
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
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Расписание - Тренер</title>
  <link rel="stylesheet" href="../assets/css/trainer/schedule.css">
  <link rel="stylesheet" href="../assets/css/common/UI.css">
  <style>

  </style>
</head>

<body>
  <div class="app-container">
    <nav class="navbar">
      <div class="navbar-brand">
        <h2>FitClub Trainer</h2>
      </div>
      <!-- навигация -->
      <ul class="navbar-menu">
        <li><a href="index.php">Главная</a></li>
        <li><a href="schedule.php" class="active">Расписание</a></li>
        <li><a href="attedance.php">Посещаемость</a></li>
        <li><a href="profile.php">Профиль</a></li>
        <li><a href="../logout.php">Выход</a></li>
      </ul>
    </nav>

    <main class="main-content">
      <div class="container">
        <div class="trainer-container">
          <div class="page-header">
            <div>
              <h2>Ваше расписание тренировок</h2>
              <p style="opacity: 0.9; margin-top: 5px;">Управляйте своими тренировками, создавайте новые и отслеживайте
                записавшихся</p>
            </div>
            <button class="btn" onclick="openCreateModal()">
              Новая тренировка
            </button>
          </div>

          <!-- алерт успеха/ошибки -->
          <div class="alert" id="messageAlert"></div>
          <!-- тренировки -->
          <div class="workouts-table-container" id="workoutsTable">
            <?php if (empty($workouts)): ?>
              <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>Нет тренировок</h3>
                <p>У вас еще нет запланированных тренировок. Создайте первую!</p>
                <button class="btn btn-primary" onclick="openCreateModal()" style="margin-top: 20px;">
                  <i class="fas fa-plus"></i> Создать тренировку
                </button>
              </div>
            <?php else: ?>
              <table class="workouts-table">
                <thead>
                  <tr>
                    <th>Название</th>
                    <th>Дата и время</th>
                    <th>Статус</th>
                    <th>Участники</th>
                    <th>Действия</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($workouts as $workout):
                    $isToday = $workout['workout_date'] == date('Y-m-d');
                    $isTomorrow = $workout['workout_date'] == date('Y-m-d', strtotime('+1 day'));
                    $isPast = strtotime($workout['workout_date'] . ' ' . $workout['end_time']) < time();
                    $isInProgress = $isToday &&
                      time() >= strtotime($workout['start_time']) &&
                      time() <= strtotime($workout['end_time']);
                    ?>
                    <tr>
                      <td>
                        <strong><?php echo htmlspecialchars($workout['title']); ?></strong>
                        <?php if ($isToday): ?>
                          <span class="date-badge badge-today">Сегодня</span>
                        <?php elseif ($isTomorrow): ?>
                          <span class="date-badge badge-tomorrow">Завтра</span>
                        <?php elseif ($isPast): ?>
                          <span class="date-badge badge-past">Прошла</span>
                        <?php else: ?>
                          <span class="date-badge badge-future">Будущая</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php echo date('d.m.Y', strtotime($workout['workout_date'])); ?><br>
                        <?php echo date('H:i', strtotime($workout['start_time'])) . ' - ' . date('H:i', strtotime($workout['end_time'])); ?>
                      </td>
                      <td>
                        <?php if ($isInProgress): ?>
                          <span class="workout-status status-in-progress">Идет сейчас</span>
                        <?php else: ?>
                          <span class="workout-status status-<?php echo $workout['status']; ?>">
                            <?php
                            $statusText = [
                              'scheduled' => 'Запланирована',
                              'completed' => 'Проведена',
                              'cancelled' => 'Отменена'
                            ];
                            echo $statusText[$workout['status']] ?? $workout['status'];
                            ?>
                          </span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php echo $workout['bookings_count']; ?> записей<br>
                        <small><?php echo $workout['attended_count']; ?> посетило</small>
                      </td>
                      <td class="actions-cell">
                        <?php if ($workout['status'] == 'scheduled' && !$isPast): ?>
                          <?php if ($isToday): ?>
                            <a href="attedance.php?workout_id=<?php echo $workout['id']; ?>" class="btn btn-success">
                              <i class="fas fa-check"></i> Отметить
                            </a>
                          <?php endif; ?>
                          <button class="btn btn-warning"
                            onclick="openEditModal(<?php echo htmlspecialchars(json_encode($workout)); ?>)">
                            <i class="fas fa-edit"></i> Редактировать
                          </button>
                          <button class="btn btn-danger"
                            onclick="cancelWorkout(<?php echo $workout['id']; ?>, '<?php echo htmlspecialchars($workout['title']); ?>')">
                            <i class="fas fa-times"></i> Отменить
                          </button>
                        <?php elseif ($workout['status'] == 'completed'): ?>
                          <a href="attedance.php?workout_id=<?php echo $workout['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-users"></i> Посетители
                          </a>
                        <?php elseif ($workout['status'] == 'cancelled'): ?>
                          <span class="btn btn-secondary" style="cursor: default;">
                            <i class="fas fa-ban"></i> —
                          </span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- модальное окно создания тренировки -->
  <div class="modal" id="createModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fas fa-plus"></i> Создать новую тренировку</h3>
        <button class="close-modal" onclick="closeCreateModal()">&times;</button>
      </div>
      <div class="modal-body">
        <form id="createWorkoutForm" onsubmit="createWorkout(event)">
          <div class="form-group">
            <label for="title">Название тренировки *</label>
            <input type="text" id="title" name="title" required maxlength="100" placeholder="Например: Утренняя йога">
          </div>

          <div class="form-group">
            <label for="description">Описание</label>
            <textarea id="description" name="description" rows="3"
              placeholder="Краткое описание тренировки..."></textarea>
          </div>

          <div class="form-group">
            <label for="workout_date">Дата тренировки *</label>
            <input type="date" id="workout_date" name="workout_date" required min="<?php echo date('Y-m-d'); ?>">
          </div>

          <div class="form-group">
            <label for="start_time">Время начала *</label>
            <input type="time" id="start_time" name="start_time" required>
          </div>

          <div class="form-group">
            <label for="end_time">Время окончания *</label>
            <input type="time" id="end_time" name="end_time" required>
          </div>

          <div class="form-group">
            <label for="max_participants">Макс. участников *</label>
            <input type="number" id="max_participants" name="max_participants" required min="1" max="50" value="10">
          </div>

          <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button type="submit" class="btn btn-primary" style="flex: 1;">
              <i class="fas fa-save"></i> Создать тренировку
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeCreateModal()" style="flex: 1;">
              <i class="fas fa-times"></i> Отмена
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- модальное окно редактирования тренировки -->
  <div class="modal" id="editModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fas fa-edit"></i> Редактировать тренировку</h3>
        <button class="close-modal" onclick="closeEditModal()">&times;</button>
      </div>
      <div class="modal-body">
        <form id="editWorkoutForm" onsubmit="updateWorkout(event)">
          <input type="hidden" id="edit_workout_id" name="workout_id">

          <div class="form-group">
            <label for="edit_title">Название тренировки *</label>
            <input type="text" id="edit_title" name="title" required maxlength="100">
          </div>

          <div class="form-group">
            <label for="edit_description">Описание</label>
            <textarea id="edit_description" name="description" rows="3"></textarea>
          </div>

          <div class="form-group">
            <label for="edit_workout_date">Дата тренировки *</label>
            <input type="date" id="edit_workout_date" name="workout_date" required>
          </div>

          <div class="form-group">
            <label for="edit_start_time">Время начала *</label>
            <input type="time" id="edit_start_time" name="start_time" required>
          </div>

          <div class="form-group">
            <label for="edit_end_time">Время окончания *</label>
            <input type="time" id="edit_end_time" name="end_time" required>
          </div>

          <div class="form-group">
            <label for="edit_max_participants">Макс. участников *</label>
            <input type="number" id="edit_max_participants" name="max_participants" required min="1" max="50">
          </div>

          <div class="form-group">
            <label for="edit_status">Статус</label>
            <select id="edit_status" name="status">
              <option value="scheduled">Запланирована</option>
              <option value="completed">Проведена</option>
              <option value="cancelled">Отменена</option>
            </select>
          </div>

          <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button type="submit" class="btn btn-primary" style="flex: 1;">
              <i class="fas fa-save"></i> Сохранить изменения
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="flex: 1;">
              <i class="fas fa-times"></i> Отмена
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // функция откртия модалки создания трени
    function openCreateModal() {
      document.getElementById('createModal').style.display = 'flex';
      document.getElementById('workout_date').min = new Date().toISOString().split('T')[0]; // минимальная дата это сегодня
    }

    // закрытия модалки создания трени
    function closeCreateModal() {
      document.getElementById('createModal').style.display = 'none';
      document.getElementById('createWorkoutForm').reset();
    }

    // функция открытия модалки редактирования
    function openEditModal(workout) {
      document.getElementById('editModal').style.display = 'flex';
      document.getElementById('edit_workout_id').value = workout.id;
      document.getElementById('edit_title').value = workout.title;
      document.getElementById('edit_description').value = workout.description || '';
      document.getElementById('edit_workout_date').value = workout.workout_date;
      document.getElementById('edit_start_time').value = workout.start_time.substring(0, 5);
      document.getElementById('edit_end_time').value = workout.end_time.substring(0, 5);
      document.getElementById('edit_max_participants').value = workout.max_participants;
      document.getElementById('edit_status').value = workout.status;
    }

    // функция закрытия модалки
    function closeEditModal() {
      document.getElementById('editModal').style.display = 'none';
    }

    // закрытие модалки при клике не на неё
    window.onclick = function (event) {
      const createModal = document.getElementById('createModal');
      const editModal = document.getElementById('editModal');

      if (event.target == createModal) {
        closeCreateModal();
      }
      if (event.target == editModal) {
        closeEditModal();
      }
    }

    // показ алерта
    function showMessage(message, type = 'success') {
      const alert = document.getElementById('messageAlert');
      alert.textContent = message;
      alert.className = `alert alert-${type}`;
      alert.style.display = 'block';

      setTimeout(() => {
        alert.style.display = 'none';
      }, 5000);
    }

    // создание тренировки
    async function createWorkout(event) {
      event.preventDefault();

      const form = event.target;
      const formData = new FormData(form);
      formData.append('action', 'create_workout');

      // фетчим через апи если создаем
      try {
        const response = await fetch('../api/trainer/trainer-schedule.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          showMessage('Тренировка успешно создана!', 'success');
          closeCreateModal();
          setTimeout(() => window.location.reload(), 1000);
        } else {
          showMessage(result.message, 'error');
        }
      } catch (error) {
        showMessage('Ошибка сети. Попробуйте позже.', 'error');
        console.error('Ошибка:', error);
      }
    }

    // обнова тренировки
    async function updateWorkout(event) {
      event.preventDefault();

      const form = event.target;
      const formData = new FormData(form);
      formData.append('action', 'update_workout');

      // фетчим если обновляем
      try {
        const response = await fetch('../api/trainer/trainer-schedule.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          showMessage('Тренировка успешно обновлена!', 'success');
          closeEditModal();
          setTimeout(() => window.location.reload(), 1000);
        } else {
          showMessage(result.message, 'error');
        }
      } catch (error) {
        showMessage('Ошибка сети. Попробуйте позже.', 'error');
        console.error('Ошибка:', error);
      }
    }

    // отмена тренировки
    async function cancelWorkout(workoutId, workoutTitle) {
      if (!confirm(`Вы уверены, что хотите отменить тренировку "${workoutTitle}"? Все записавшиеся клиенты получат уведомление.`)) {
        return;
      }

      try {
        const formData = new FormData();
        formData.append('action', 'cancel_workout');
        formData.append('workout_id', workoutId);

        // фетчим если отменяем
        const response = await fetch('../api/trainer/trainer-schedule.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          showMessage('Тренировка отменена. Клиенты уведомлены.', 'success');
          setTimeout(() => window.location.reload(), 1000);
        } else {
          showMessage(result.message, 'error');
        }
      } catch (error) {
        showMessage('Ошибка сети. Попробуйте позже.', 'error');
        console.error('Ошибка:', error);
      }
    }

    // установить текущее время по умолчанию
    document.addEventListener('DOMContentLoaded', function () {
      const now = new Date();
      const startTime = new Date(now.getTime() + 60 * 60 * 1000); // +1 час от текущего
      const endTime = new Date(startTime.getTime() + 60 * 60 * 1000); // +1 час от старта

      document.getElementById('start_time').value =
        startTime.getHours().toString().padStart(2, '0') + ':' +
        startTime.getMinutes().toString().padStart(2, '0');

      document.getElementById('end_time').value =
        endTime.getHours().toString().padStart(2, '0') + ':' +
        endTime.getMinutes().toString().padStart(2, '0');
    });
  </script>
</body>

</html>