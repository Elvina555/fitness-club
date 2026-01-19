<?php
require_once 'config.php';

class JWTTestSimple
{

  public static function runAllTests()
  {
    echo "\n\n";
    echo "========================================\n";
    echo "        ТЕСТИРОВАНИЕ JWT КЛАССА        \n";
    echo "========================================\n\n";

    $secret = JWT_SECRET;
    $tests = [];

    // тест 1 инициализация
    echo "1. Проверка инициализации секрета... ";
    try {
      // используем Reflection без deprecated метода
      $reflection = new ReflectionClass('JWT');
      $property = $reflection->getProperty('secret');
      $property->setAccessible(true);

      // сохраняем оригинальное значение
      $originalSecret = $property->getValue();
      // устанавливаем null
      $property->setValue(null, null);

      try {
        JWT::encode(['test' => 1]);
        echo "ОШИБКА\n";
        $tests[] = false;
      } catch (Exception $e) {
        if (strpos($e->getMessage(), 'JWT secret not initialized') !== false) {
          echo "OK\n";
          $tests[] = true;
        } else {
          echo "ОШИБКА: " . $e->getMessage() . "\n";
          $tests[] = false;
        }
      }

      // восстанавливаем секрет
      JWT::init($originalSecret);

    } catch (Exception $e) {
      echo "ОШИБКА: " . $e->getMessage() . "\n";
      $tests[] = false;
    }

    // тест 2 кодирование/декодирование
    echo "2. Кодирование и декодирование... ";
    try {
      JWT::init($secret);
      $payload = ['user_id' => 123, 'email' => 'test@example.com'];
      $token = JWT::encode($payload);
      $decoded = JWT::decode($token);

      if ($decoded['user_id'] == 123 && $decoded['email'] == 'test@example.com') {
        echo "OK\n";
        $tests[] = true;
      } else {
        echo "ОШИБКА\n";
        $tests[] = false;
      }
    } catch (Exception $e) {
      echo "ОШИБКА: " . $e->getMessage() . "\n";
      $tests[] = false;
    }

    // тест 3 проверка подписи
    echo "3. Проверка подписи токена... ";
    try {
      $token = JWT::encode(['test' => 1]);
      $parts = explode('.', $token);
      $modified = $parts[0] . '.' . $parts[1] . '.' . substr($parts[2], 0, -1) . 'X';

      JWT::decode($modified);
      echo "ОШИБКА\n";
      $tests[] = false;
    } catch (Exception $e) {
      if (strpos($e->getMessage(), 'Неверная подпись токена') !== false) {
        echo "OK\n";
        $tests[] = true;
      } else {
        echo "ОШИБКА: " . $e->getMessage() . "\n";
        $tests[] = false;
      }
    }

    // тест 4 формат токена
    echo "4. Проверка формата токена... ";
    try {
      JWT::decode('invalid.token');
      echo "ОШИБКА\n";
      $tests[] = false;
    } catch (Exception $e) {
      if (strpos($e->getMessage(), 'Некорректный формат токена') !== false) {
        echo "OK\n";
        $tests[] = true;
      } else {
        echo "ОШИБКА: " . $e->getMessage() . "\n";
        $tests[] = false;
      }
    }

    // тест 5 срок действия
    echo "5. Проверка срока действия... ";
    try {
      // создаем просроченный токен
      $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
      $payload = json_encode(['exp' => time() - 3600]);

      $header64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
      $payload64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
      $signature = hash_hmac('sha256', $header64 . "." . $payload64, $secret, true);
      $signature64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

      $expiredToken = $header64 . "." . $payload64 . "." . $signature64;

      JWT::decode($expiredToken);
      echo "ОШИБКА\n";
      $tests[] = false;
    } catch (Exception $e) {
      if (strpos($e->getMessage(), 'Срок действия токена истек') !== false) {
        echo "OK\n";
        $tests[] = true;
      } else {
        echo "ОШИБКА: " . $e->getMessage() . "\n";
        $tests[] = false;
      }
    }

    // итоги
    echo "\n----------------------------------------\n";
    echo "ИТОГИ:\n";
    $passed = array_sum($tests);
    $total = count($tests);
    echo "Пройдено: $passed из $total\n";

    if ($passed == $total) {
      echo "СТАТУС: ВСЕ ТЕСТЫ ПРОЙДЕНЫ \n";
    } else {
      echo "СТАТУС: ЕСТЬ ОШИБКИ \n";
    }
    echo "========================================\n\n";
  }
}

// запуск
JWTTestSimple::runAllTests();
?>