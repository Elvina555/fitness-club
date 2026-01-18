<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Отчеты - Админ панель</title>
  <link rel="stylesheet" href="../assets/css/common/UI.css">
  <link rel="stylesheet" href="../assets/css/admin/reports.css">
</head>

<body>
  <div class="app-container">
    <nav class="navbar">
      <div class="navbar-brand">
        <h2>FitClub Admin</h2>
      </div>
      <ul class="navbar-menu">
        <li><a href="index.php">Главная</a></li>
        <li><a href="users.php">Пользователи</a></li>
        <li><a href="workouts.php">Тренировки</a></li>
        <li><a href="reviews.php">Отзывы</a></li>
        <li><a href="reports.php" class="active">Статистика</a></li>
        <li><a href="../logout.php">Выход</a></li>
      </ul>
    </nav>

    <main class="main-content">
      <div class="container">
        <div class="reports-container">
          <div class="reports-header">
            <h2>Статистика фитнес-клуба</h2>
            <p class="reports-description">Просмотр статистики по дням, неделям и месяцам</p>
          </div>

          <div class="reports-grid">
            <!-- дневная статистика -->
            <div class="report-card" id="dailyReportCard">
              <div class="report-icon">ДЕНЬ</div>
              <h3 class="report-title">Дневная статистика</h3>
              <p class="report-description">
                Статистика за выбранный день: посещаемость,
                доходы, активные тренировки.
              </p>

              <div class="date-picker">
                <label for="dailyDate">Выберите дату:</label>
                <input type="date" id="dailyDate" class="date-input" value="<?php echo date('Y-m-d'); ?>">
              </div>

              <div class="report-stats">
                <div class="stat-item">
                  <span class="stat-value" id="dailyWorkouts">0</span>
                  <span class="stat-label">Тренировок</span>
                </div>
                <div class="stat-item">
                  <span class="stat-value" id="dailyVisitors">0</span>
                  <span class="stat-label">Посетителей</span>
                </div>
                <div class="stat-item">
                  <span class="stat-value" id="dailyIncome">0</span>
                  <span class="stat-label">Доход, ₽</span>
                </div>
              </div>
            </div>

            <!-- недельная статистика -->
            <div class="report-card" id="weeklyReportCard">
              <div class="report-icon">НЕДЕЛЯ</div>
              <h3 class="report-title">Недельная статистика</h3>
              <p class="report-description">
                Статистика за неделю: динамика посещаемости,
                доходы, активность тренеров.
              </p>

              <div class="date-picker">
                <label for="weeklyDate">Начало недели:</label>
                <input type="date" id="weeklyDate" class="date-input" value="<?php
                // понедельник текущей недели
                echo date('Y-m-d', strtotime('monday this week'));
                ?>">
              </div>

              <div class="report-stats">
                <div class="stat-item">
                  <span class="stat-value" id="weeklyWorkouts">0</span>
                  <span class="stat-label">Тренировок</span>
                </div>
                <div class="stat-item">
                  <span class="stat-value" id="weeklyVisitors">0</span>
                  <span class="stat-label">Посетителей</span>
                </div>
                <div class="stat-item">
                  <span class="stat-value" id="weeklyIncome">0</span>
                  <span class="stat-label">Доход, ₽</span>
                </div>
              </div>
            </div>

            <!-- месячная статистика -->
            <div class="report-card" id="monthlyReportCard">
              <div class="report-icon">МЕСЯЦ</div>
              <h3 class="report-title">Месячная статистика</h3>
              <p class="report-description">
                Статистика за месяц: финансовые показатели,
                активность клиентов, общая эффективность.
              </p>

              <div class="date-picker">
                <label for="monthlyDate">Выберите месяц:</label>
                <input type="month" id="monthlyDate" class="date-input" value="<?php echo date('Y-m'); ?>">
              </div>

              <div class="report-stats">
                <div class="stat-item">
                  <span class="stat-value" id="monthlyWorkouts">0</span>
                  <span class="stat-label">Тренировок</span>
                </div>
                <div class="stat-item">
                  <span class="stat-value" id="monthlyVisitors">0</span>
                  <span class="stat-label">Посетителей</span>
                </div>
                <div class="stat-item">
                  <span class="stat-value" id="monthlyIncome">0</span>
                  <span class="stat-label">Доход, ₽</span>
                </div>
              </div>
            </div>
          </div>

          <div class="loading" id="loading" style="display: none;">
            <div class="loading-spinner"></div>
            <p>Загрузка статистики...</p>
          </div>

        </div>
      </div>
    </main>
  </div>

  <script>
    // загрузка статисты при загрузке страницы
    document.addEventListener('DOMContentLoaded', function () {
      updateStats();

      // обнова статы при изменении даты
      document.getElementById('dailyDate').addEventListener('change', updateStats);
      document.getElementById('weeklyDate').addEventListener('change', updateStats);
      document.getElementById('monthlyDate').addEventListener('change', updateStats);
    });

    // функция обновления статы
    function updateStats() {
      const dailyDate = document.getElementById('dailyDate').value;
      const weeklyDate = document.getElementById('weeklyDate').value;
      const monthlyDate = document.getElementById('monthlyDate').value;

      // показываем загрузку
      document.querySelectorAll('.stat-value').forEach(el => el.textContent = '...');
      document.getElementById('loading').style.display = 'block';

      fetch(`../api/admin/admin-reports.php?action=stats&daily=${dailyDate}&weekly=${weeklyDate}&monthly=${monthlyDate}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // дневная стата
            if (data.daily) {
              document.getElementById('dailyWorkouts').textContent = data.daily.workouts || 0;
              document.getElementById('dailyVisitors').textContent = data.daily.visitors || 0;
              document.getElementById('dailyIncome').textContent = formatCurrency(data.daily.income || 0);
            }

            // неделная стата
            if (data.weekly) {
              document.getElementById('weeklyWorkouts').textContent = data.weekly.workouts || 0;
              document.getElementById('weeklyVisitors').textContent = data.weekly.visitors || 0;
              document.getElementById('weeklyIncome').textContent = formatCurrency(data.weekly.income || 0);
            }

            // месячная стата
            if (data.monthly) {
              document.getElementById('monthlyWorkouts').textContent = data.monthly.workouts || 0;
              document.getElementById('monthlyVisitors').textContent = data.monthly.visitors || 0;
              document.getElementById('monthlyIncome').textContent = formatCurrency(data.monthly.income || 0);
            }
          } else {
            console.error('Ошибка загрузки статистики:', data.error);
            showAlert('Ошибка загрузки статистики', 'danger');
          }
        })
        .catch(error => {
          console.error('Ошибка сети:', error);
          showAlert('Ошибка сети при загрузке статистики', 'danger');
        })
        .finally(() => {
          document.getElementById('loading').style.display = 'none';
        });
    }

    // форматирование валюты
    function formatCurrency(amount) {
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      }).format(amount).replace('₽', '₽').trim();
    }

    // показ сообщения об ошибках
    function showAlert(message, type = 'danger') {
      const alertDiv = document.createElement('div');
      alertDiv.className = `alert alert-${type}`;
      alertDiv.textContent = message;
      alertDiv.style.position = 'fixed';
      alertDiv.style.top = '20px';
      alertDiv.style.right = '20px';
      alertDiv.style.zIndex = '1000';
      alertDiv.style.padding = '15px';
      alertDiv.style.borderRadius = '5px';

      document.body.appendChild(alertDiv);

      setTimeout(() => {
        alertDiv.remove();
      }, 5000);
    }
  </script>
</body>

</html>
<?php
// проверка доступа
require_once __DIR__ . '/../classes/User.php';
$userModel = new User();
$currentUser = $userModel->getCurrentUser();

if (!$currentUser || $currentUser['role'] !== 'admin') {
  header('Location: /login.html');
  exit;
}

// установка куки с токеном если есть в GET
if (isset($_GET['token'])) {
  setcookie('fitness_token', $_GET['token'], time() + 60 * 60 * 24 * 7, '/');
}
?>