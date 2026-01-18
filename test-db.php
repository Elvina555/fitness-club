<?php
require_once 'config.php';
require_once 'classes/Database.php';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Тест базы данных</title>
  <style>
    body {
      font-family: monospace;
      margin: 0;
      padding: 20px;
      background: #fff;
      color: #000;
      font-size: 14px;
      line-height: 1.4;
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
    }

    h1 {
      margin: 0 0 20px 0;
      font-weight: normal;
      padding-bottom: 10px;
      border-bottom: 1px solid #ccc;
    }

    .result {
      padding: 8px;
      margin: 4px 0;
    }

    .success {
      color: #090;
    }

    .error {
      color: #900;
    }

    .warning {
      color: #950;
    }

    .section {
      margin: 20px 0;
      padding: 15px 0;
      border-top: 1px solid #ccc;
    }

    .section h2 {
      margin: 0 0 15px 0;
      font-weight: normal;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin: 10px 0;
    }

    th {
      text-align: left;
      padding: 6px 8px;
      background: #f5f5f5;
      font-weight: normal;
      border-bottom: 1px solid #ddd;
    }

    td {
      padding: 6px 8px;
      border-bottom: 1px solid #eee;
    }

    .timestamp {
      color: #666;
      font-size: 12px;
      margin-top: 20px;
      padding-top: 10px;
      border-top: 1px solid #ccc;
    }
  </style>
</head>

<body>
  <div class="container">
    <h1>ТЕСТ БАЗЫ ДАННЫХ</h1>

    <?php
    try {
      $db = new Database();
      $conn = $db->getConnection();

      echo '<div class="result success">Подключение к MySQL успешно</div>';
      echo '<div class="result">Хост: ' . DB_HOST . '</div>';
      echo '<div class="result">База: ' . DB_NAME . '</div>';

      $tables = $db->fetchAll("SHOW TABLES");
      $tableNames = [];
      foreach ($tables as $table) {
        $tableNames[] = $table['Tables_in_' . DB_NAME];
      }

      echo '<div class="section">';
      echo '<h2>ТАБЛИЦЫ В БАЗЕ (' . count($tableNames) . ')</h2>';

      if (!empty($tableNames)) {
        echo '<table>';
        echo '<tr><th>Таблица</th><th>Записей</th></tr>';

        foreach ($tableNames as $tableName) {
          try {
            $count = $db->fetchOne("SELECT COUNT(*) as cnt FROM `$tableName`");
            echo '<tr>';
            echo '<td>' . $tableName . '</td>';
            echo '<td>' . ($count['cnt'] ?? 0) . '</td>';
            echo '</tr>';
          } catch (Exception $e) {
            echo '<tr>';
            echo '<td>' . $tableName . '</td>';
            echo '<td class="error">ошибка</td>';
            echo '</tr>';
          }
        }

        echo '</table>';
      } else {
        echo '<div class="result warning">Таблиц не найдено</div>';
      }
      echo '</div>';

      $required = ['users', 'workouts', 'bookings', 'subscriptions', 'reviews'];
      $missing = [];

      foreach ($required as $table) {
        if (!in_array($table, $tableNames)) {
          $missing[] = $table;
        }
      }

      if (empty($missing)) {
        echo '<div class="result success">Все обязательные таблицы существуют</div>';
      } else {
        echo '<div class="result error">Отсутствуют: ' . implode(', ', $missing) . '</div>';
      }

      if (in_array('users', $tableNames)) {
        $usersByRole = $db->fetchAll("SELECT role, COUNT(*) as cnt FROM users GROUP BY role ORDER BY role");

        echo '<div class="section">';
        echo '<h2>ПОЛЬЗОВАТЕЛИ ПО РОЛЯМ</h2>';

        if (!empty($usersByRole)) {
          echo '<table>';
          echo '<tr><th>Роль</th><th>Количество</th></tr>';

          foreach ($usersByRole as $user) {
            echo '<tr>';
            echo '<td>' . $user['role'] . '</td>';
            echo '<td>' . $user['cnt'] . '</td>';
            echo '</tr>';
          }

          echo '</table>';
        } else {
          echo '<div class="result warning">Пользователей не найдено</div>';
        }
        echo '</div>';
      }

      if (in_array('workouts', $tableNames) && in_array('users', $tableNames)) {
        try {
          $activeWorkouts = $db->fetchAll("
                        SELECT w.title, u.first_name, u.last_name, w.workout_date, w.start_time, w.current_participants, w.max_participants 
                        FROM workouts w 
                        JOIN users u ON w.trainer_id = u.id 
                        WHERE w.status = 'scheduled' 
                        AND w.workout_date >= CURDATE() 
                        ORDER BY w.workout_date 
                        LIMIT 10
                    ");

          if (!empty($activeWorkouts)) {
            echo '<div class="section">';
            echo '<h2>АКТИВНЫЕ ТРЕНИРОВКИ</h2>';

            echo '<table>';
            echo '<tr><th>Тренировка</th><th>Тренер</th><th>Дата</th><th>Время</th><th>Участники</th></tr>';

            foreach ($activeWorkouts as $workout) {
              echo '<tr>';
              echo '<td>' . htmlspecialchars($workout['title']) . '</td>';
              echo '<td>' . htmlspecialchars($workout['first_name'] . ' ' . $workout['last_name']) . '</td>';
              echo '<td>' . $workout['workout_date'] . '</td>';
              echo '<td>' . $workout['start_time'] . '</td>';
              echo '<td>' . $workout['current_participants'] . '/' . $workout['max_participants'] . '</td>';
              echo '</tr>';
            }

            echo '</table>';
            echo '</div>';
          }
        } catch (Exception $e) {
          echo '<div class="result warning">Не удалось получить тренировки: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
      }

    } catch (Exception $e) {
      echo '<div class="result error">Ошибка подключения: ' . htmlspecialchars($e->getMessage()) . '</div>';
      echo '<div class="section">';
      echo '<h2>КОНФИГУРАЦИЯ</h2>';
      echo '<div class="result">DB_HOST: ' . DB_HOST . '</div>';
      echo '<div class="result">DB_USER: ' . DB_USER . '</div>';
      echo '<div class="result">DB_NAME: ' . DB_NAME . '</div>';
      echo '<div class="result">DB_PASS: ' . (DB_PASS ? 'установлен' : 'не установлен') . '</div>';
      echo '</div>';
    }
    ?>
  </div>
</body>

</html>