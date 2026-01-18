<?php
// классы подключаем
require_once '../config.php';
require_once '../classes/User.php';

$userModel = new User();
$currentUser = $userModel->getCurrentUser();

// проверка роли
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
  <title>Мои уведомления - Фитнес Клуб</title>
  <link rel="stylesheet" href="../assets/css/client/notifications.css">
  <link rel="stylesheet" href="../assets/css/common/UI.css">
  <style>

  </style>
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
        <div class="page-header">
          <h2>Мои уведомления</h2>
          <div class="header-actions">
            <button id="markAllRead" class="btn">Отметить все как прочитанные</button>
            <button id="deleteAllRead" class="btn">Удалить прочитанные</button>
          </div>
        </div>

        <div id="alerts"></div>
        <!-- показывается ток во время загрузки -->
        <div id="notifications-loading" class="info-box">
          <p>Загрузка уведомлений...</p>
        </div>
        <!-- показывается ток если все пусто -->
        <div id="notifications-empty" class="empty-state" style="display:none;">
          <h3>Нет уведомлений</h3>
          <p>Здесь будут появляться важные уведомления о ваших тренировках и абонементах</p>
        </div>

        <div id="notifications-container" style="display:none;"></div>
      </div>
    </main>

    <footer class="footer">
      <p>&copy; 2025 Фитнес Клуб. Все права защищены.</p>
    </footer>
  </div>

  <script>

    document.addEventListener('DOMContentLoaded', () => {
      const loadingEl = document.getElementById('notifications-loading');
      const emptyEl = document.getElementById('notifications-empty');
      const containerEl = document.getElementById('notifications-container');
      const alertsEl = document.getElementById('alerts');

      function showAlert(message, type = 'info') {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.textContent = message;
        alertsEl.appendChild(alert);
        setTimeout(() => alert.remove(), 5000);
      }

      // форматируем дату
      function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('ru-RU', {
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        });
      }

      // получаем тип уведомления
      function getTypeClass(type) {
        const types = {
          'booking_confirmed': 'type-booking',
          'reminder': 'type-reminder',
          'subscription_expiry': 'type-subscription',
          'review_status': 'type-review',
          'schedule_change': 'type-booking',
        };
        return types[type] || 'type-booking';
      }

      // переводим тип в читаемую надпись
      function getTypeLabel(type) {
        const labels = {
          'booking_confirmed': 'Бронирование',
          'reminder': 'Напоминание',
          'subscription_expiry': 'Абонемент',
          'review_status': 'Отзыв',
          'schedule_change': 'Расписание'
        };
        return labels[type] || 'Уведомление';
      }

      // фетчим уведомления из бд через апишку
      async function loadNotifications() {
        try {
          const response = await fetch('../api/client/client-notifications.php');

          if (!response.ok) {
            throw new Error('Ошибка загрузки уведомлений');
          }

          const data = await response.json();

          loadingEl.style.display = 'none';

          if (!data.success || !data.notifications || data.notifications.length === 0) {
            emptyEl.style.display = 'block';
            containerEl.style.display = 'none';
            return;
          }

          emptyEl.style.display = 'none';
          containerEl.style.display = 'block';

          // выводим уведомления
          containerEl.innerHTML = data.notifications.map(notification => `
                        <div class="notification-card ${notification.is_read ? 'read' : 'unread'}" data-id="${notification.id}">
                            <div class="notification-header">
                                <div>
                                    <span class="notification-type ${getTypeClass(notification.type)}">
                                        ${getTypeLabel(notification.type)}
                                    </span>
                                    <h4 class="notification-title">${notification.title}</h4>
                                </div>
                                <div class="notification-date">${formatDate(notification.created_at)}</div>
                            </div>
                            <p class="notification-message">${notification.message}</p>
                            <div class="notification-actions">
                                ${!notification.is_read ? `
                                    <button class="btn" onclick="markAsRead(${notification.id})">
                                        Отметить как прочитанное
                                    </button>
                                ` : ''}
                                <button class="btn" onclick="deleteNotification(${notification.id})">
                                    Удалить
                                </button>
                            </div>
                        </div>
                    `).join('');

        } catch (error) {
          console.error('Ошибка:', error);
          loadingEl.style.display = 'none';
          showAlert('Ошибка загрузки уведомлений', 'danger');
        }
      }

      // фетчим если отмечаем прочитанным
      window.markAsRead = async function (notificationId) {
        try {
          const response = await fetch('../api/client/client-notifications.php', {
            method: 'PUT',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              notification_id: notificationId,
              mark_read: true
            })
          });

          const data = await response.json();

          if (data.success) {
            const card = document.querySelector(`.notification-card[data-id="${notificationId}"]`);
            if (card) {
              card.classList.remove('unread');
              card.classList.add('read');
              card.querySelector('.notification-actions').innerHTML = `
                                <button class="btn" onclick="deleteNotification(${notificationId})">
                                    Удалить
                                </button>
                            `;
            }
            showAlert('Уведомление отмечено как прочитанное', 'success');
          } else {
            throw new Error(data.error || 'Ошибка');
          }
        } catch (error) {
          console.error('Ошибка:', error);
          showAlert('Не удалось обновить уведомление', 'danger');
        }
      };

      // фетчим если удаляем
      window.deleteNotification = async function (notificationId) {
        if (!confirm('Удалить это уведомление?')) return;

        try {
          const response = await fetch('../api/client/client-notifications.php', {
            method: 'DELETE',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              notification_id: notificationId
            })
          });

          const data = await response.json();

          if (data.success) {
            const card = document.querySelector(`.notification-card[data-id="${notificationId}"]`);
            if (card) {
              card.remove();
            }
            showAlert('Уведомление удалено', 'success');

            // чек есть ли  уведомления
            const cards = document.querySelectorAll('.notification-card');
            if (cards.length === 0) {
              containerEl.style.display = 'none';
              emptyEl.style.display = 'block';
            }
          } else {
            throw new Error(data.error || 'Ошибка');
          }
        } catch (error) {
          console.error('Ошибка:', error);
          showAlert('Не удалось удалить уведомление', 'danger');
        }
      };

      // фетчим если "отметить все как прочитанные"
      document.getElementById('markAllRead').addEventListener('click', async () => {
        try {
          const response = await fetch('../api/client/client-notifications.php', {
            method: 'PUT',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              mark_all_read: true
            })
          });

          const data = await response.json();

          if (data.success) {
            const cards = document.querySelectorAll('.notification-card.unread');
            cards.forEach(card => {
              card.classList.remove('unread');
              card.classList.add('read');
              const notificationId = card.dataset.id;
              card.querySelector('.notification-actions').innerHTML = `
                                <button class="btn" onclick="deleteNotification(${notificationId})">
                                    Удалить
                                </button>
                            `;
            });
            showAlert('Все уведомления отмечены как прочитанные', 'success');
          } else {
            throw new Error(data.error || 'Ошибка');
          }
        } catch (error) {
          console.error('Ошибка:', error);
          showAlert('Не удалось обновить уведомления', 'danger');
        }
      });

      // фетчим если  "удалить прочитанные"
      document.getElementById('deleteAllRead').addEventListener('click', async () => {
        if (!confirm('Удалить все прочитанные уведомления?')) return;

        try {
          const response = await fetch('../api/client/client-notifications.php', {
            method: 'DELETE',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              delete_all_read: true
            })
          });

          const data = await response.json();

          if (data.success) {
            const cards = document.querySelectorAll('.notification-card.read');
            cards.forEach(card => card.remove());
            showAlert('Все прочитанные уведомления удалены', 'success');

            // чек есть ли уведомления
            const remainingCards = document.querySelectorAll('.notification-card');
            if (remainingCards.length === 0) {
              containerEl.style.display = 'none';
              emptyEl.style.display = 'block';
            }
          } else {
            throw new Error(data.error || 'Ошибка');
          }
        } catch (error) {
          console.error('Ошибка:', error);
          showAlert('Не удалось удалить уведомления', 'danger');
        }
      });
      loadNotifications();
    });
  </script>
</body>

</html>