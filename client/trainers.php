<?php
require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';

$user = new User();
$currentUser = $user->getCurrentUser();

if (!$currentUser || $currentUser['role'] !== 'client') {
  header('Location: ../login.html');
  exit;
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Тренеры - Фитнес Клуб</title>
  <link rel="stylesheet" href="../assets/css/common/UI.css">
  <link rel="stylesheet" href="../assets/css/client/trainers.css">

</head>

<body>
  <div class="app-container">
    <nav class="navbar">
      <div class="navbar-brand">
        <h2>FitClub</h2>
      </div>
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
        <h2>Наши Тренеры</h2>

        <div id="alerts"></div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;"
          id="trainersContainer">
          <p class="text-center" style="grid-column: 1 / -1;">Загрузка...</p>
        </div>
      </div>


      <div id="trainerModal" class="modal">
        <div class="modal-content">
          <div class="modal-header">
            <h3 id="modalTrainerName"></h3>
          </div>
          <div class="modal-body" id="modalContent">

          </div>
          <div class="modal-footer">
            <button class="submit-btn" onclick="closeModal()">Закрыть</button>
            <a href="schedule.php" id="scheduleLink" class="submit-btn">Посмотреть расписание</a>
          </div>
        </div>
      </div>
    </main>

    <footer class="footer">
      <p>&copy; 2025 Фитнес Клуб. Все права защищены.</p>
    </footer>
  </div>

  <script>
    async function loadTrainers() {
      try {
        const res = await fetch('../api/client/client-trainers.php');

        // проверка статуса ответа
        if (!res.ok) {
          if (res.status === 401 || res.status === 403) {
            window.location.href = '../login.html';
            return;
          }
          throw new Error('Ошибка сети: ' + res.status);
        }

        const data = await res.json();
        console.log('Данные тренеров:', data); // потом убрать

        if (!data.success) {
          showAlert('Ошибка загрузки тренеров', 'danger');
          return;
        }

        const container = document.getElementById('trainersContainer');
        if (!data.trainers || data.trainers.length === 0) {
          container.innerHTML = '<p class="text-center" style="grid-column: 1 / -1;">Тренеры не найдены</p>';
          return;
        }

        container.innerHTML = data.trainers.map(trainer => {
          // парс рейтинга в число
          const avgRating = parseFloat(trainer.avg_rating) || 0;
          const reviewCount = parseInt(trainer.review_count) || 0;

          // инициалы для заглушки
          const firstLetter = trainer.first_name ? trainer.first_name.charAt(0).toUpperCase() : 'Т';
          const lastLetter = trainer.last_name ? trainer.last_name.charAt(0).toUpperCase() : 'Р';
          const initials = firstLetter + lastLetter;

          return `
                <div class="workout-card" onclick="openTrainerModal(${trainer.id})">
                    <div class="trainer-header">
                        ${trainer.avatar_url ?
              `<img src="../${trainer.avatar_url}" alt="${trainer.first_name} ${trainer.last_name}" class="trainer-avatar">` :
              `<div class="avatar-placeholder">${initials}</div>`
            }
                        <div>
                            <h4 style="margin: 0;">${trainer.first_name} ${trainer.last_name}</h4>
                            <p style="margin: 0; color: #666; font-size: 0.9rem;">${trainer.specialization || 'Тренер'}</p>
                        </div>
                    </div>
                    <p><strong>Рейтинг:</strong> ${avgRating.toFixed(1)} </p>
                    <p><strong>Отзывов:</strong> ${reviewCount}</p>
                    ${trainer.description ? `<p style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">${trainer.description.substring(0, 100)}...</p>` : ''}
                    <button class="btn btn-primary btn-block" style="margin-top: 1rem;">Подробнее</button>
                </div>
                `;
        }).join('');
      } catch (error) {
        console.error('Ошибка:', error);
        showAlert('Ошибка загрузки данных: ' + error.message, 'danger');
      }
    }

    async function openTrainerModal(trainerId) {
      try {
        const res = await fetch(`../api/client/client-trainers.php?trainer_id=${trainerId}`);

        if (!res.ok) {
          if (res.status === 401 || res.status === 403) {
            window.location.href = '../login.html';
            return;
          }
          throw new Error('Ошибка сети: ' + res.status);
        }

        const data = await res.json();
        console.log('Данные тренера (детально):', data); // потом убрать

        if (!data.success) {
          showAlert('Ошибка загрузки информации', 'danger');
          return;
        }

        // проверка структуры ответа
        if (!data.trainer) {
          showAlert('Данные тренера не найдены', 'danger');
          return;
        }

        const trainer = data.trainer;
        const avgRating = parseFloat(trainer.avg_rating) || 0;
        const reviewCount = parseInt(trainer.review_count) || 0;

        // генерируем инициалы для заглушки
        const firstLetter = trainer.first_name ? trainer.first_name.charAt(0).toUpperCase() : 'Т';
        const lastLetter = trainer.last_name ? trainer.last_name.charAt(0).toUpperCase() : 'Р';
        const initials = firstLetter + lastLetter;

        document.getElementById('modalTrainerName').textContent =
          `${trainer.first_name} ${trainer.last_name}`;
        document.getElementById('scheduleLink').href =
          `schedule.php?trainer_id=${trainer.id}`;

        const reviews = data.reviews || [];
        const workouts = data.upcoming_workouts || [];
        //динамический вывод тренеров
        let content = `
            <div class="modal-trainer-info">
                ${trainer.avatar_url ?
            `<img src="../${trainer.avatar_url}" alt="${trainer.first_name} ${trainer.last_name}" class="modal-avatar">` :
            `<div class="modal-avatar-placeholder">${initials}</div>`
          }
                <div style="flex: 1;">
                    <p><strong>Специализация:</strong> ${trainer.specialization || 'Не указана'}</p>
                    <p><strong>Рейтинг:</strong> ${avgRating.toFixed(1)}  (${reviewCount} отзывов)</p>
                    ${trainer.phone ? `<p><strong>Телефон:</strong> ${trainer.phone}</p>` : ''}
                    ${trainer.email ? `<p><strong>Email:</strong> ${trainer.email}</p>` : ''}
                </div>
            </div>
        `;

        if (trainer.description) {
          content += `
                <div style="margin-bottom: 1.5rem;">
                    <h4>О тренере:</h4>
                    <p style="background: var(--light-green);color: var(--white); padding: 1rem; border-radius: 4px; line-height: 1.6;">${trainer.description}</p>
                </div>
            `;
        }

        if (workouts.length > 0) {
          content += `
                <div style="margin-bottom: 1.5rem;">
                    <h4>Предстоящие тренировки:</h4>
                    <ul style="list-style: none; padding: 0;">
                        ${workouts.map(w => {
            const date = new Date(w.workout_date);
            const formattedDate = date.toLocaleDateString('ru-RU');
            const startTime = w.start_time.substring(0, 5);
            const endTime = w.end_time.substring(0, 5);

            return `
                            <li style="margin-bottom: 0.5rem; padding: 0.75rem; background: var(--light-green); border-radius: 4px; color: var(--white);">
                                • <strong>${w.title}</strong><br>
                                <small>${formattedDate} ${startTime}-${endTime}</small><br>
                                <small>Мест: ${w.current_participants || 0}/${w.max_participants || 0}</small>
                            </li>
                          `;
          }).join('')}
                    </ul>
                </div>
            `;
        } else {
          content += `
                <div style="margin-bottom: 1.5rem;">
                    <p><em>Нет предстоящих тренировок</em></p>
                </div>
            `;
        }

        if (reviews.length > 0) {
          content += `
                <div>
                    <h4>Последние отзывы:</h4>
                    ${reviews.slice(0, 3).map(r => {
            const rating = parseFloat(r.rating) || 0;
            const comment = r.comment || 'Без комментария';
            const createdAt = r.created_at ? new Date(r.created_at).toLocaleDateString('ru-RU') : '';

            return `
                        <div style="background: var(--light-green); padding: 0.75rem; margin-bottom: 0.75rem; border-radius: 4px;">
                            <p style="margin: 0; color: var(--white)">
                                <strong style="color: var(--orange);"> ${rating.toFixed(1)}/5</strong> - ${r.first_name} ${r.last_name}
                                ${createdAt ? `<small style="color: var(--white); margin-left: 1rem;">${createdAt}</small>` : ''}
                            </p>
                            <p style="margin: 0.25rem 0 0 0; color: var(--white); font-size: 0.9rem;">"${comment.substring(0, 150)}${comment.length > 150 ? '...' : ''}"</p>
                        </div>
                        `;
          }).join('')}
                </div>
            `;
        } else {
          content += `
                <div>
                    <h4>Отзывы:</h4>
                    <p><em>Пока нет отзывов</em></p>
                </div>
            `;
        }

        document.getElementById('modalContent').innerHTML = content;
        document.getElementById('trainerModal').classList.add('active');
      } catch (error) {
        console.error('Ошибка:', error);
        showAlert('Ошибка загрузки информации: ' + error.message, 'danger');
      }
    }
    // закрытие модалки
    function closeModal() {
      document.getElementById('trainerModal').classList.remove('active');
    }

    function showAlert(message, type = 'info') {
      const alerts = document.getElementById('alerts');
      const alert = document.createElement('div');
      alert.className = `alert alert-${type}`;
      alert.textContent = message;
      alerts.appendChild(alert);
      setTimeout(() => alert.remove(), 5000);
    }

    // загружка тренеров загрузке страницы
    loadTrainers();

    // хакрытие модалки по клику вне модалки
    document.getElementById('trainerModal').addEventListener('click', function (event) {
      if (event.target === this) {
        this.classList.remove('active');
      }
    });

    // закрытие модалки
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeModal();
      }
    });
  </script>
</body>

</html>