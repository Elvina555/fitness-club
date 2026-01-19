<?php
require_once 'config.php';

class LoginTestSimple
{

  public static function runAllTests()
  {
    echo "\n\n";
    echo "========================================\n";
    echo "   ТЕСТИРОВАНИЕ СИСТЕМЫ АВТОРИЗАЦИИ   \n";
    echo "========================================\n\n";

    $tests = [];

    // тест 1 проверка файлов
    echo "1. Проверка файлов системы...\n";
    $files = [
      'login.html',
      'api/login.php',
      'classes/User.php',
      'classes/JWT.php'
    ];

    foreach ($files as $file) {
      echo "   - $file: ";
      if (file_exists($file)) {
        echo "OK\n";
      } else {
        echo "НЕ НАЙДЕН\n";
      }
    }

    // тест 2 проверка HTML формы
    echo "\n2. Проверка формы логина...\n";
    $html = file_get_contents('login.html');
    $checks = [
      'Форма логина' => strpos($html, 'loginForm') !== false,
      'Поле email' => strpos($html, 'type="email"') !== false,
      'Поле password' => strpos($html, 'type="password"') !== false,
      'Кнопка отправки' => strpos($html, 'type="submit"') !== false
    ];

    foreach ($checks as $name => $result) {
      echo "   - $name: " . ($result ? "OK" : "ОШИБКА") . "\n";
    }

    // тест 3 проверка API
    echo "\n3. Проверка API логина...\n";
    $api = file_get_contents('api/login.php');
    $apiChecks = [
      'JSON обработка' => strpos($api, 'json_decode') !== false,
      'Класс User' => strpos($api, 'new User()') !== false,
      'Ответ JSON' => strpos($api, 'json_encode') !== false
    ];

    foreach ($apiChecks as $name => $result) {
      echo "   - $name: " . ($result ? "OK" : "ОШИБКА") . "\n";
    }

    // тест 4 проверка редиректа
    echo "\n4. Проверка перенаправлений...\n";
    $redirects = [
      'Клиент' => strpos($html, "role === 'client'") !== false,
      'Тренер' => strpos($html, "role === 'trainer'") !== false,
      'Админ' => strpos($html, "role === 'admin'") !== false
    ];

    foreach ($redirects as $role => $result) {
      echo "   - Роль $role: " . ($result ? "OK" : "ОШИБКА") . "\n";
    }

    echo "\n----------------------------------------\n";
    echo "ПРОВЕРКА СИСТЕМЫ ЗАВЕРШЕНА\n";
    echo "========================================\n\n";
  }
}

// запуск
LoginTestSimple::runAllTests();
?>