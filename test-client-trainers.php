<?php
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Тест API: Тренеры</title>
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

    .url {
      color: #000;
      margin: 10px 0;
      font-size: 13px;
      word-break: break-all;
    }

    .status {
      padding: 10px;
      margin: 10px 0;
      background: #f5f5f5;
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

    .response {
      margin: 20px 0;
      max-height: 400px;
      overflow: auto;
      background: #f9f9f9;
      padding: 15px;
      font-size: 12px;
    }

    .timestamp {
      color: #666;
      font-size: 12px;
      margin-top: 20px;
      padding-top: 10px;
      border-top: 1px solid #ccc;
    }

    .test-btn {
      background: #f5f5f5;
      color: #000;
      border: 1px solid #ccc;
      padding: 8px 15px;
      font-family: monospace;
      cursor: pointer;
      margin: 10px 0;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin: 10px 0;
    }

    th {
      text-align: left;
      padding: 6px 8px;
      background: #f0f0f0;
      font-weight: normal;
      border-bottom: 1px solid #ddd;
    }

    td {
      padding: 6px 8px;
      border-bottom: 1px solid #eee;
    }
  </style>
</head>

<body>
  <div class="container">
    <h1>ТЕСТ API: CLIENT-TRAINERS</h1>

    <div class="url">URL: api/client/client-trainers.php</div>

    <button class="test-btn" onclick="runTest()">Запустить тест</button>

    <div id="status" class="status"></div>

    <div id="response" class="response" style="display:none;"></div>

  </div>

  <script>

    function runTest() {
      const statusEl = document.getElementById('status');
      const responseEl = document.getElementById('response');

      statusEl.textContent = 'Тестирование...';
      statusEl.className = 'status';
      responseEl.style.display = 'none';

      fetch('api/client/client-trainers.php')
        .then(response => {
          const status = response.status;
          let statusClass = 'error';

          if (status === 200) statusClass = 'success';
          else if (status === 401 || status === 403) statusClass = 'warning';

          statusEl.innerHTML = 'HTTP статус: <span class="' + statusClass + '">' + status + '</span>';
          statusEl.className = 'status ' + statusClass;

          return response.text();
        })
        .then(data => {
          responseEl.style.display = 'block';

          try {
            const jsonData = JSON.parse(data);
            responseEl.innerHTML = formatJSON(jsonData);

            if (jsonData.success) {
              statusEl.innerHTML += ' - Успешный ответ';
              if (jsonData.trainers) {
                statusEl.innerHTML += ' (тренеров: ' + jsonData.trainers.length + ')';
              }
            } else if (jsonData.error) {
              statusEl.innerHTML += ' - ' + jsonData.error;
            }
          } catch (e) {
            responseEl.textContent = data.substring(0, 1000);
          }
        })
        .catch(error => {
          statusEl.textContent = 'Ошибка сети: ' + error.message;
          statusEl.className = 'status error';
        });
    }

    function formatJSON(data) {
      if (!data) return '';

      let html = '';

      if (data.success !== undefined) {
        html += '<div class="' + (data.success ? 'success' : 'error') + '">success: ' + data.success + '</div>';
      }

      if (data.error) {
        html += '<div class="error">error: ' + data.error + '</div>';
      }

      if (data.trainers && Array.isArray(data.trainers)) {
        html += '<div>trainers: ' + data.trainers.length + ' записей</div>';

        let table = '<table>';
        table += '<tr><th>ID</th><th>Имя</th><th>Роль</th><th>Email</th></tr>';

        data.trainers.slice(0, 5).forEach(trainer => {
          table += '<tr>';
          table += '<td>' + (trainer.id || '') + '</td>';
          table += '<td>' + (trainer.first_name || '') + ' ' + (trainer.last_name || '') + '</td>';
          table += '<td>' + (trainer.role || '') + '</td>';
          table += '<td>' + (trainer.email || '') + '</td>';
          table += '</tr>';
        });

        if (data.trainers.length > 5) {
          table += '<tr><td colspan="4">... и еще ' + (data.trainers.length - 5) + ' записей</td></tr>';
        }

        table += '</table>';
        html += table;
      }

      return html;
    }

    setTimeout(runTest, 500);
  </script>
</body>

</html>