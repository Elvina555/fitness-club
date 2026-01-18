<?php
require_once 'config.php'; // подключаем конфиг

// делаем проверку на наличие токена
if (isset($_GET['token'])) {
  $token = $_GET['token'];
  setcookie('fitness_token', $token, time() + 86400 * 30, '/');
  header('Location: index.php');
  exit;
}

$currentUser = null;
$userInitials = '';
$userName = '';
$userRole = '';


//  есть ли токен
if (isset($_COOKIE['fitness_token'])) {
  try {
    $decoded = JWT::decode($_COOKIE['fitness_token']); // попытка расшифровать JWT
    $user = new User();
    $currentUser = $user->getById($decoded['user_id']); //получение данных пользователя из БД и создаётся объект User и по user_id из токена вытаскивается пользователь из базы
    if ($currentUser) {
      $firstLetter = mb_substr($currentUser['first_name'], 0, 1, 'UTF-8');
      $lastLetter = mb_substr($currentUser['last_name'], 0, 1, 'UTF-8');
      $userInitials = strtoupper($firstLetter . $lastLetter);
      $userName = htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); // это все формирование инициалов и имени


      // переводим значения из бд в текст
      $roles = [
        'admin' => 'Администратор',
        'trainer' => 'Тренер',
        'client' => 'Клиент'
      ];
      $userRole = isset($roles[$currentUser['role']]) ? $roles[$currentUser['role']] : $currentUser['role'];
      // если в бд была какая-то роль который нет в списке выше то выведет её название
    }
  } catch (Exception $e) {
    setcookie('fitness_token', '', time() - 3600, '/');
    // обрабатываем ошибку токена если случилась
  }
}

?>
<!DOCTYPE html>
<html lang="ru">

<!-- подключение мета файлов и стилей css -->

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/common/main_index.css">
  <link rel="stylesheet" href="../assets/css/common/UI.css">
  <title>Фитнес-клуб - Главная</title>
</head>

<body>
  <!-- верхнеее меню -->
  <div class="container">
    <header>
      <a href="main_index.php" class="logo">Фитнес-клуб</a>

      <div class="user-info">
        <a href="login.html" class="submit-btn">Вход</a>
        <a href="register.html" class="submit-btn">Регистрация</a>
      </div>
    </header>
    <!-- основная часть -->
    <?php if (!$currentUser): ?>
      <div class="hero">

        <h1>Добро пожаловать в Фитнес-клуб!</h1>
        <p>Записывайтесь на тренировки, выбирайте тренеров, отслеживайте прогресс и достигайте своих целей вместе с нами!
        </p>
        <div>
          <a href="register.html" class="submit-btn">Начать
            бесплатно</a>
          <a href="login.html" class="submit-btn">Уже есть аккаунт</a>
        </div>
      </div>
      <!-- карточки с достоинствами клуба -->
      <div class="features">
        <div class="feature-card">
          <h3>Онлайн-запись</h3>
          <p>Выбирайте удобное время и записывайтесь на тренировки в один клик</p>
        </div>

        <div class="feature-card">
          <h3>Лучшие тренеры</h3>
          <p>Профессиональные инструкторы с отзывами и рейтингами</p>
        </div>

        <div class="feature-card">
          <h3>Отслеживание прогресса</h3>
          <p>Ведите историю тренировок и следите за своими успехами</p>
        </div>
      </div>
    <?php endif; ?>

    <footer>
      <p>© 2024 Фитнес-клуб. Все права защищены.</p>
      <p>Телефон: +7 (999) 123-45-67 | Email: info@fitness-club.ru</p>
    </footer>
  </div>

  <!-- скорее всего убрать -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const token = localStorage.getItem('fitness_token');
      const user = localStorage.getItem('fitness_user');

      if (token && user && !document.cookie.includes('fitness_token')) {
        document.cookie = `fitness_token=${token}; path=/; max-age=${60 * 60 * 24 * 30}`;
        const urlParams = new URLSearchParams(window.location.search);

        if (urlParams.has('just_logged_in')) {
          window.location.href = 'main_index.php';
        }
      }
    });
  </script>
</body>

</html>