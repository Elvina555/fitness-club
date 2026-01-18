<?php
// классы
require_once '../config.php';
require_once '../classes/User.php';

// инфа о юзере
$userModel = new User();
$currentUser = $userModel->getCurrentUser();

// токен
if (!$currentUser || $currentUser['role'] !== 'client') {
  header('Location: ../login.html');
  exit;
}

$trainer_id = isset($_GET['trainer_id']) ? (int) $_GET['trainer_id'] : null;
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Расписание - Фитнес Клуб</title>
  <link rel="stylesheet" href="../assets/css/common/UI.css">
  <link rel="stylesheet" href="../assets/css/client/schedule.css">
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

    <!-- основной контент -->
    <main class="main-content">
      <div class="app-container">
        <h2>Расписание тренировок</h2>
        <div id="alerts"></div>
        <div class="filters">
          <h3 style="margin-top: 0;">Фильтры</h3>
          <div class="filter-row">
            <div class="filter-group">
              <label for="filterDate">Дата</label>
              <input type="date" id="filterDate" class="form-control">
            </div>
            <div class="filter-group">
              <label for="filterTrainer">Тренер</label>
              <select id="filterTrainer" class="form-control">
                <option value="">Все тренеры</option>
              </select>
            </div>
            <div class="filter-group">
              <label for="filterTitle">Название</label>
              <input type="text" id="filterTitle" class="form-control">
            </div>
          </div>
          <div style="display: flex; gap: 1rem;">
            <button id="applyFilters" class="btn">Применить фильтры</button>
            <button id="resetFilters" class="btn">Сбросить фильтры</button>
          </div>
        </div>
        <div id="loading" class="loading">
          <p>Загрузка расписания...</p>
        </div>
        <div id="emptyState" class="info-box" style="display: none; text-align: center;">
          <p>Тренировки не найдены. Попробуйте изменить параметры фильтрации.</p>
        </div>
        <div id="workoutsContainer" class="workouts-grid" style="display: none;">
        </div>

        <!-- модальное окно записи -->
        <div id="bookingModal" class="modal">
          <div class="modal-content">
            <div class="modal-header">
              <button class="close-modal" onclick="closeBookingModal()">&times;</button>
              <h2 id="modalWorkoutTitle"></h2>
            </div>
            <div id="modalWorkoutDetails" style="margin-bottom: 1.5rem;"></div>
            <div class="modal-footer">
              <button class="submit-btn" onclick="closeBookingModal()">Отмена</button>
              <button id="confirmBooking" class="submit-btn" style="width:45%;">Записаться</button>
            </div>
          </div>
        </div>

        <!-- модальное окно ошибки -->
        <div id="errorModal" class="error-modal">
          <div class="error-modal-content">
            <div class="error-modal-header">
              <h3>Ошибка</h3>
              <button class="close-error" onclick="closeErrorModal()">&times;</button>
            </div>
            <div class="error-modal-body" id="errorModalMessage">
              Произошла ошибка при выполнении операции.
            </div>
            <div class="error-modal-footer">
              <button class="submit-btn" onclick="closeErrorModal()">Понятно</button>
            </div>
          </div>
        </div>

        <!-- модальное окно успеха -->
        <div id="successModal" class="success-modal">
          <div class="success-modal-content">
            <div class="success-modal-header">
              <h3>Успешно!</h3>
              <button class="close-success" onclick="closeSuccessModal()">&times;</button>
            </div>
            <div class="success-modal-body">
              <div class="success-icon">✓</div>
              <p id="successModalMessage">Операция выполнена успешно!</p>
            </div>
            <div class="success-modal-footer">
              <button class="submit-btn" onclick="stayOnPage()">Закрыть</button>
            </div>
          </div>
        </div>

        <!-- модальное окно для покупки абонемента -->
        <div id="subscriptionModal" class="subscription-modal">
          <div class="subscription-modal-content">
            <div class="subscription-modal-header">
              <h3>Требуется абонемент</h3>
              <button class="close-subscription" onclick="closeSubscriptionModal()">&times;</button>
            </div>
            <div class="subscription-modal-body" id="subscriptionModalMessage">
              Для записи на тренировку необходим активный абонемент.
            </div>
            <div class="subscription-modal-footer">
              <button class="submit-btn" onclick="goToSubscriptionPage()">Купить абонемент</button>
              <button class="submit-btn" onclick="closeSubscriptionModal()">Отмена</button>
            </div>
          </div>
        </div>
      </div>
    </main>

    <footer class="footer">
      <p>&copy; 2025 Фитнес Клуб. Все права защищены.</p>
    </footer>
  </div>

  <script>
    let currentWorkoutId = null;
    let trainersList = [];

    // фильтрация
    document.addEventListener('DOMContentLoaded', () => {
      loadSchedule();
      loadTrainers();
      document.getElementById('applyFilters').addEventListener('click', applyFilters);
      document.getElementById('resetFilters').addEventListener('click', resetFilters);
      document.getElementById('filterTitle').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') applyFilters();
      });
    });

    // функция загрузки расписания
    function loadSchedule(filters = {}) {
      const loadingEl = document.getElementById('loading');
      const containerEl = document.getElementById('workoutsContainer');
      const emptyEl = document.getElementById('emptyState');

      loadingEl.style.display = 'block';
      containerEl.style.display = 'none';
      emptyEl.style.display = 'none';

      const params = new URLSearchParams();
      if (filters.date) params.append('date', filters.date);
      if (filters.trainer_id) params.append('trainer_id', filters.trainer_id);
      if (filters.title) params.append('title', filters.title);

      // фетчим расписания для пользователя
      fetch(`../api/client/client-schedule.php?${params.toString()}`)
        .then(async response => {
          const data = await response.json();

          if (!response.ok || !data.success) {
            throw new Error(data.error || 'Ошибка загрузки расписания');
          }

          loadingEl.style.display = 'none';

          if (!data.workouts || data.workouts.length === 0) {
            emptyEl.style.display = 'block';
            return;
          }

          containerEl.style.display = 'grid';
          renderWorkouts(data.workouts);

        })
        .catch(error => {
          console.error('Ошибка:', error);
          loadingEl.style.display = 'none';
          showErrorModal('Ошибка загрузки расписания', error.message);
        });
    }

    // фетчим тренеров из бд
    function loadTrainers() {
      fetch('../api/client/client-trainers.php')
        .then(async response => {
          const data = await response.json();

          if (data.success && data.trainers) {
            trainersList = data.trainers;
            const select = document.getElementById('filterTrainer');
            data.trainers.forEach(trainer => {
              const option = document.createElement('option');
              option.value = trainer.id;
              option.textContent = `${trainer.first_name} ${trainer.last_name} - ${trainer.specialization || 'Тренер'}`;
              select.appendChild(option);
            });
            <?php if ($trainer_id): ?>
              document.getElementById('filterTrainer').value = <?php echo $trainer_id; ?>;
              applyFilters();
            <?php endif; ?>
          }
        })
        .catch(error => console.error('Ошибка загрузки тренеров:', error));
    }

    // функция рендера тренировок
    function renderWorkouts(workouts) {
      const container = document.getElementById('workoutsContainer');

      container.innerHTML = workouts.map(workout => {
        const date = new Date(workout.workout_date);
        const formattedDate = date.toLocaleDateString('ru-RU', {
          weekday: 'long',
          day: 'numeric',
          month: 'long'
        });

        let spotsClass = 'spots-available';
        if (workout.is_full) {
          spotsClass = 'spots-full';
        } else if (workout.spots_left <= 3) {
          spotsClass = 'spots-few';
        }

        const spotsText = workout.is_full ? 'Мест нет' : `Осталось мест: ${workout.spots_left}`;
        const isDisabled = workout.is_booked || workout.is_full;

        return `
                    <div class="workout-card" data-id="${workout.id}">
                        <h2>${workout.title}</h2>
                        <p><strong>Дата:</strong> ${formattedDate}</p>
                        <p><strong>Время:</strong> ${workout.start_time} - ${workout.end_time}</p>
                        <p><strong>Тренер:</strong> ${workout.trainer.first_name} ${workout.trainer.last_name}</p>
                        <p><strong>Специализация:</strong> ${workout.trainer.specialization || 'Не указана'}</p>
                        <p><strong>Участников:</strong> ${workout.current_participants}/${workout.max_participants}</p>
                        <p class="${spotsClass}">${spotsText}</p>
                        ${workout.description ? `<p style="font-size: 0.9rem; color: #666;">${workout.description.substring(0, 100)}...</p>` : ''}
                        <button class="btn book-btn" 
                                onclick="openBookingModal(${workout.id})"
                                ${isDisabled ? 'disabled' : ''}>
                            ${workout.is_booked ? 'Вы уже записаны' : workout.is_full ? 'Мест нет' : 'Записаться'}
                        </button>
                    </div>
                `;
      }).join('');
    }

    // функция фильтров
    function applyFilters() {
      const filters = {
        date: document.getElementById('filterDate').value,
        trainer_id: document.getElementById('filterTrainer').value,
        title: document.getElementById('filterTitle').value.trim()
      };

      loadSchedule(filters);
    }

    // функция сброса фильтров
    function resetFilters() {
      document.getElementById('filterDate').value = '';
      document.getElementById('filterTrainer').value = '';
      document.getElementById('filterTitle').value = '';
      loadSchedule();
    }

    // функция открытия модалки
    function openBookingModal(workoutId) {
      currentWorkoutId = workoutId;
      fetch(`../api/client/client-schedule.php`)
        .then(async response => {
          const data = await response.json();

          if (data.success && data.workouts) {
            const workout = data.workouts.find(w => w.id == workoutId);
            if (workout) {
              const date = new Date(workout.workout_date);
              const formattedDate = date.toLocaleDateString('ru-RU', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
              });

              document.getElementById('modalWorkoutTitle').textContent = workout.title;

              const details = `
                                <p><strong>Дата и время:</strong> ${formattedDate}, ${workout.start_time} - ${workout.end_time}</p>
                                <p><strong>Тренер:</strong> ${workout.trainer.first_name} ${workout.trainer.last_name}</p>
                                <p><strong>Специализация:</strong> ${workout.trainer.specialization || 'Не указана'}</p>
                                <p><strong>Мест свободно:</strong> ${workout.spots_left} из ${workout.max_participants}</p>
                                ${workout.description ? `<p><strong>Описание:</strong> ${workout.description}</p>` : ''}
                            `;

              document.getElementById('modalWorkoutDetails').innerHTML = details;
              document.getElementById('bookingModal').classList.add('active');
            }
          }
        })
        .catch(error => {
          console.error('Ошибка:', error);
          showErrorModal('Ошибка загрузки информации', 'Не удалось загрузить информацию о тренировке');
        });
    }

    // функция закрытия модалки
    function closeBookingModal() {
      document.getElementById('bookingModal').classList.remove('active');
      currentWorkoutId = null;
    }

    // обработчик нажатия кнопки "записаться"
    document.getElementById('confirmBooking').addEventListener('click', () => {
      if (!currentWorkoutId) return;

      const confirmBtn = document.getElementById('confirmBooking');
      confirmBtn.classList.add('loading-btn');
      confirmBtn.disabled = true;

      // проверка наличия активного абонемента
      fetch('../api/client/client-subscriptions.php')
        .then(async response => {
          const data = await response.json();

          if (!data.success) {
            throw new Error('Не удалось получить информацию об абонементах');
          }

          if (!data.active_subscription) {
            // показ модалки для покупки абонемента
            showSubscriptionModal('Для записи на тренировку необходим активный абонемент.');
            resetBookingButton(confirmBtn);
            return;
          }

          if (data.active_subscription.visits_left <= 0) {
            showSubscriptionModal('На вашем абонементе не осталось посещений. Пожалуйста, продлите абонемент.');
            resetBookingButton(confirmBtn);
            return;
          }

          // если абонемент есть запись на тренировку
          return fetch('../api/client/client-bookings.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              workout_id: currentWorkoutId,
              subscription_id: data.active_subscription.id
            })
          });
        })
        .then(async response => {
          if (!response) return; // если response undefined (была ошибка с абонементом)

          const data = await response.json();

          if (!response.ok || !data.success) {
            throw new Error(data.error || 'Ошибка при записи на тренировку');
          }

          // если успешная запись
          closeBookingModal();
          showSuccessModal('Вы успешно записались на тренировку!');

        })
        .catch(error => {
          console.error('Ошибка:', error);
          showErrorModal('Ошибка записи', error.message);
          resetBookingButton(confirmBtn);
        });
    });

    // сброс состояния кнопки
    function resetBookingButton(btn) {
      btn.classList.remove('loading-btn');
      btn.disabled = false;
      btn.textContent = 'Записаться';
    }

    // функции для работы с модальными окнами

    function showErrorModal(title, message) {
      document.getElementById('errorModalMessage').textContent = message;
      document.getElementById('errorModal').classList.add('active');
    }

    function closeErrorModal() {
      document.getElementById('errorModal').classList.remove('active');
    }

    function showSuccessModal(title, message) {
      const fullMessage = message ? `${title}<br>${message}` : title;
      document.getElementById('successModalMessage').innerHTML = fullMessage;
      document.getElementById('successModal').classList.add('active');
    }

    function closeSuccessModal() {
      document.getElementById('successModal').classList.remove('active');
    }

    function stayOnPage() {
      closeSuccessModal();
      // обновление страницы чтобы показать новое состояние
      loadSchedule();
    }

    function showSubscriptionModal(message) {
      document.getElementById('subscriptionModalMessage').textContent = message;
      document.getElementById('subscriptionModal').classList.add('active');
    }

    function closeSubscriptionModal() {
      document.getElementById('subscriptionModal').classList.remove('active');
    }

    function goToSubscriptionPage() {
      closeSubscriptionModal();
      closeBookingModal();
      window.location.href = 'subscription.php';
    }

    // функция для старых алертов
    function showAlert(message, type = 'info') {
      const alertsEl = document.getElementById('alerts');
      const alert = document.createElement('div');
      alert.className = `alert alert-${type}`;
      alert.textContent = message;
      alertsEl.appendChild(alert);
      setTimeout(() => {
        alert.remove();
      }, 5000);
    }
  </script>
</body>

</html>