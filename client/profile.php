<?php
// классы
require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';

// токен
if (!isset($_COOKIE['fitness_token'])) {
  header('Location: ../login.html');
  exit;
}

$token = $_COOKIE['fitness_token'];

require_once '../classes/JWT.php';
JWT::init(JWT_SECRET);

try {
  $decoded = JWT::decode($token);
  $user_id = $decoded['user_id'];

  $db = new Database();
  $user = $db->fetchOne(
    "SELECT id, email, first_name, last_name, phone, created_at 
         FROM users WHERE id = ?",
    [$user_id]
  );

  if (!$user) {
    header('Location: ../login.html');
    exit;
  }

} catch (Exception $e) {
  header('Location: ../login.html');
  exit;
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Профиль - Фитнес Клуб</title>
  <link rel="stylesheet" href="../assets/css/common/UI.css">
  <link rel="stylesheet" href="../assets/css/client/profile.css">
</head>

<body>
  <div class="app-container">
    <nav class="navbar">
      <div class="navbar-brand">
        <h2>FitClub</h2>
      </div>
      <!-- навигиация -->
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
        <h2>Мой Профиль</h2>
        <div id="alerts"></div>
        <div class="dashboard-grid">
          <div class="dashboard-card">
            <h3>Информация профиля</h3>
            <div class="main-info-container">
              <!-- вывод инфы в профиль благодаря инфы из токена -->
              <div class="form-group">
                <label for="firstName">Имя:</label>
                <input style="color: var(--white); font-size: 16px;" type="text" id="firstName" class="form-control"
                  value="<?php echo htmlspecialchars($user['first_name']); ?>">
              </div>
              <div class="form-group">
                <label for="lastName">Фамилия:</label>
                <input style="color: var(--white); font-size: 16px;" type="text" id="lastName" class="form-control"
                  value="<?php echo htmlspecialchars($user['last_name']); ?>">
              </div>
              <div class="form-group">
                <label for="email">Email:</label>
                <input style="color: var(--white); font-size: 16px;" type="email" id="email" class="form-control"
                  value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
              </div>
              <div class="form-group">
                <label for="phone">Телефон:</label>
                <input style="color: var(--white); font-size: 16px;" type="tel" id="phone" class="form-control"
                  value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
              </div>
            </div>
            <div>
              <button style="margin-top: 16px;" class="btn" onclick="updateProfile()">Сохранить
                изменения</button>
            </div>
          </div>
        </div>

        <!-- инфа о безопасности аккаунта -->
        <div class="dashboard-grid">
          <div class="dashboard-card">
            <h3>Безопасность</h3>
            <div>
              <p><strong>Дата создания аккаунта:</strong> <?php echo date('d.m.Y', strtotime($user['created_at'])); ?>
              </p>
            </div>
            <div>
              <button class="btn btn-danger" onclick="deleteAccount()">Удалить аккаунт</button>
            </div>
          </div>
        </div>

        <!-- текущая на данный момент информация -->
        <div class="dashboard-grid">
          <div class="dashboard-card">
            <h3>Текущий статус</h3>
            <div>
              <?php
              $activeSubscription = $db->fetchOne(
                "SELECT * FROM subscriptions WHERE client_id = ? AND status = 'active' 
                 ORDER BY end_date DESC LIMIT 1",
                [$user['id']]
              );

              if ($activeSubscription): ?>
                <div class="info-box">
                  <p><strong>Активный абонемент:</strong> <?php echo htmlspecialchars($activeSubscription['type']); ?></p>
                  <p><strong>Посещений осталось:</strong> <?php echo $activeSubscription['visits_left']; ?> из
                    <?php echo $activeSubscription['visits_total']; ?>
                  </p>
                  <p><strong>Действует до:</strong>
                    <?php echo date('d.m.Y', strtotime($activeSubscription['end_date'])); ?></p>
                  <?php
                  $daysLeft = ceil((strtotime($activeSubscription['end_date']) - time()) / (24 * 60 * 60));
                  echo '<p><strong>Дней осталось:</strong> ' . max(0, $daysLeft) . '</p>';
                  ?>
                  <a href="subscription.php" class="btn" style="color: var(--dark-blue)">Управлять абонементом</a>
                </div>
              <?php else: ?>
                <div class="info-box">
                  <p><strong>У вас нет активного абонемента.</strong></p>
                  <a href="subscription.php" class="btn" style="color: var(--dark-blue)">Выбрать абонемент</a>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="dashboard-card">
            <h3>Мои записи</h3>
            <div>
              <?php
              $activeBookings = $db->fetchOne(
                "SELECT COUNT(*) as count FROM bookings WHERE client_id = ? AND status = 'confirmed'",
                [$user['id']]
              );
              ?>
              <p class="card-number"><?php echo $activeBookings['count']; ?></p>
              <p>активных записей</p>
              <a href="bookings.php" class="btn" style="color: var(--dark-blue)">Перейти к записям</a>
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
    // обновляем профиль
    async function updateProfile() {
      const firstName = document.getElementById('firstName').value;
      const lastName = document.getElementById('lastName').value;
      const phone = document.getElementById('phone').value;

      if (!firstName.trim() || !lastName.trim()) {
        alert('Заполните имя и фамилию');
        return;
      }

      // фетчим запрос на обновление профиля 
      try {
        const response = await fetch('../api/client/client-profile.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'action=update&first_name=' + encodeURIComponent(firstName) +
            '&last_name=' + encodeURIComponent(lastName) +
            '&phone=' + encodeURIComponent(phone)
        });

        const text = await response.text();

        try {
          const data = JSON.parse(text);
          if (data.success) {
            alert('Профиль успешно обновлен!');
            location.reload();
          } else {
            alert('Ошибка: ' + data.error);
          }
        } catch (e) {
          alert('Ошибка сервера');
        }

      } catch (error) {
        alert('Ошибка сети');
      }
    }

    // функция удаления профиля
    async function deleteAccount() {
      if (!confirm('Вы действительно хотите удалить свой аккаунт? Это действие необратимо.')) {
        return;
      }

      const password = prompt('Для подтверждения введите ваш пароль:');
      if (!password) {
        return;
      }

      // фетчим если удаляем но к телу запроса добавляем что хотим удалять
      try {
        const response = await fetch('../api/client/client-profile.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'action=delete&password=' + encodeURIComponent(password)
        });

        const text = await response.text();

        try {
          const data = JSON.parse(text);
          if (data.success) {
            alert('Аккаунт успешно удален');
            document.cookie = "fitness_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
            window.location.href = '../login.html';
          } else {
            alert('Ошибка: ' + data.error);
          }
        } catch (e) {
          alert('Ошибка сервера');
        }
      } catch (error) {
        alert('Ошибка удаления аккаунта');
      }
    }
  </script>
</body>

</html>