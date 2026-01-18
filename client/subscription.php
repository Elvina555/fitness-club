<?php
// класс
require_once __DIR__ . '/../classes/User.php';

// инфа о юзере
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
  <title>Мои абонементы</title>
  <link rel="stylesheet" href="../assets/css/common/UI.css">
  <link rel="stylesheet" href="../assets/css/client/subscription.css">
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
      <div class="app-container">
        <h2>Мои абонементы</h2>

        <div id="subscriptions-loading" class="info-box">
          <p>Загрузка...</p>
        </div>

        <div id="subscriptions-error" class="alert alert-danger" style="display:none;">
          Ошибка загрузки данных. Попробуйте обновить страницу.
        </div>

        <section id="active-subscription-section" class="recent-section mt-20" style="display:none;">
          <h3>Активный абонемент</h3>
          <div class="info-box">
            <div class="subscription-card">
              <p><strong>Тип:</strong> <span id="active-type"></span></p>
              <p><strong>Посещений осталось:</strong>
                <span id="active-visits-left"></span>
                из
                <span id="active-visits-total"></span>
              </p>
              <p><strong>Действует до:</strong> <span id="active-end-date"></span></p>
              <p><strong>Дней осталось:</strong> <span id="active-days-left"></span></p>
            </div>
          </div>
        </section>

        <section id="no-subscriptions-section" class="recent-section" style="display:none;">
          <div class="info-box" style="padding: 16px;">
            <p><strong>У вас нет активных абонементов.</strong></p>
            <p>Вы можете приобрести новый абонемент ниже.</p>
          </div>
        </section>

        <section class="recommendations mt-20">
          <h2>Доступные абонементы для покупки</h2>
          <div class="workouts-grid">
            <div class="workout-card">
              <h3 class="type-sub">Абонемент на месяц</h3>
              <p class="text-sub">4 посещения, действует 30 дней.</p>
              <button class="btn " data-sub-type="month">Купить за 3000</button>
              </button>
            </div>
            <div class="workout-card">
              <h3 class="type-sub">Абонемент на 3 месяца</h3>
              <p class="text-sub">12 посещений, действует 90 дней.</p>
              <button class="btn " data-sub-type="3months">
                Купить за 8000
              </button>
            </div>
            <div class="workout-card">
              <h3 class="type-sub">Абонемент на 6 месяцев</h3>
              <p class="text-sub">24 посещения, действует 180 дней.</p>
              <button class="btn" data-sub-type="6months">
                Купить за 15000
              </button>
            </div>
            <div class="workout-card">
              <h3 class="type-sub">Абонемент на год</h3>
              <p class="text-sub">52 посещения, действует 365 дней.</p>
              <button class="btn " data-sub-type="year">
                Купить за 25000
              </button>
            </div>
          </div>
        </section>
      </div>
    </main>

    <footer class="footer">
      <p>&copy; 2025 Фитнес Клуб. Все права защищены.</p>
    </footer>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const loadingEl = document.getElementById('subscriptions-loading');
      const errorEl = document.getElementById('subscriptions-error');
      const activeSection = document.getElementById('active-subscription-section');
      const noSubsSection = document.getElementById('no-subscriptions-section');

      function hideLoading() {
        if (loadingEl) {
          loadingEl.style.display = 'none';
        }
      }

      function showError(message) {
        hideLoading();
        if (errorEl) {
          errorEl.style.display = 'block';
          errorEl.textContent = message || 'Ошибка загрузки данных. Попробуйте обновить страницу.';
        }
      }

      // фоматирование даты
      function formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        if (Number.isNaN(d.getTime())) return dateStr;
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        return `${day}.${month}.${year}`;
      }

      // фетчим инфу об абонементе
      fetch('../api/client/client-subscriptions.php', {
        method: 'GET',
        headers: {
          'Accept': 'application/json'
        }
      })
        .then(async response => {
          const text = await response.text();
          let data;

          try {
            data = JSON.parse(text);
          } catch (e) {
            throw new Error('Некорректный ответ сервера');
          }

          if (!response.ok || !data.success) {
            throw new Error(data.error || 'Ошибка загрузки данных');
          }

          hideLoading();
          errorEl.style.display = 'none';

          const active = data.active_subscription;
          const subs = Array.isArray(data.subscriptions) ? data.subscriptions : [];

          if (active) {
            activeSection.style.display = 'block';
            noSubsSection.style.display = 'none';

            document.getElementById('active-type').textContent = active.type || '';
            document.getElementById('active-visits-left').textContent = active.visits_left ?? '';
            document.getElementById('active-visits-total').textContent = active.visits_total ?? '';
            document.getElementById('active-end-date').textContent = formatDate(active.end_date);

            const daysLeft = Math.ceil(
              (new Date(active.end_date).getTime() - Date.now()) / (24 * 60 * 60 * 1000)
            );
            document.getElementById('active-days-left').textContent = Math.max(0, daysLeft);
          } else {
            activeSection.style.display = 'none';
            noSubsSection.style.display = 'block';
          }

          // если subs пустой это норм
        })
        .catch(err => {
          showError(err.message);
        });

      // обработка покупки абонементов
      document.querySelectorAll('button[data-sub-type]').forEach(btn => {
        btn.addEventListener('click', () => {
          const type = btn.getAttribute('data-sub-type');
          btn.disabled = true;

          fetch('../api/client/client-subscriptions.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json'
            },
            body: JSON.stringify({ type })
          })
            .then(async response => {
              const text = await response.text();
              let data;
              try {
                data = JSON.parse(text);
              } catch (e) {
                throw new Error('Некорректный ответ сервера при покупке');
              }

              if (!response.ok || !data.success) {
                throw new Error(data.error || 'Не удалось купить абонемент');
              }

              alert(data.message || 'Абонемент успешно активирован!');
              window.location.reload();
            })
            .catch(err => {
              alert(err.message);
            })
            .finally(() => {
              btn.disabled = false;
            });
        });
      });
    });
  </script>
</body>

</html>