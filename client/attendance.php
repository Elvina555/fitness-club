<?php
// подклчаем классы и конфиг
require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';

$user = new User();
$currentUser = $user->getCurrentUser();

if (!$currentUser || $currentUser['role'] !== 'client') {
  header('Location: ../login.html');
  exit;
}

$db = new Database();
?>
<!DOCTYPE html>
<html lang="ru">

<!-- мета файлы и стили -->

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Мое посещение - Фитнес Клуб</title>
  <link rel="stylesheet" href="../assets/css/common/UI.css">
  <link rel="stylesheet" href="../assets/css/client/attendance.css">
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

    <!-- статистика обновляемая через код -->
    <main class="main-content">
      <div class="app-container">
        <h2>Мое посещение тренировок</h2>
        <div class="dashboard-cards" id="statsDashboard">
          <div class="stat-card">
            <div class="stat-number">0</div>
            <div class="stat-label">Всего записей</div>
          </div>
          <div class="stat-card">
            <div class="stat-number">0%</div>
            <div class="stat-label">Посещено</div>
          </div>
          <div class="stat-card">
            <div class="stat-number">0%</div>
            <div class="stat-label">Пропущено</div>
          </div>
          <div class="stat-card">
            <div class="stat-number">0%</div>
            <div class="stat-label">Отменено</div>
          </div>
        </div>

        <div class="filter-buttons">
          <button class="filter-btn active" onclick="filterAttendance('all')">Все</button>
          <button class="filter-btn" onclick="filterAttendance('attended')">Посещенные</button>
          <button class="filter-btn" onclick="filterAttendance('missed')">Пропущенные</button>
          <button class="filter-btn" onclick="filterAttendance('confirmed')">Предстоящие</button>
          <button class="filter-btn" onclick="filterAttendance('cancelled')">Отмененные</button>
        </div>

        <div class="attendance-table">
          <table id="attendanceTable">
            <thead>
              <tr>
                <th>Дата</th>
                <th>Тренировка</th>
                <th>Тренер</th>
                <th>Время</th>
                <th>Статус записи</th>
                <th>Статус посещения</th>
                <th>Заметки тренера</th>
              </tr>
            </thead>
            <tbody id="attendanceBody">
              <tr>
                <td colspan="7" class="empty-state">Загрузка данных...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </main>

    <footer class="footer">
      <p>&copy; 2025 Фитнес Клуб. Все права защищены.</p>
    </footer>
  </div>

  <script>
    // фетчим посещения через апишку
    async function loadAttendanceData() {
      try {
        const res = await fetch('../api/client/client-attendance.php');

        if (!res.ok) {
          if (res.status === 401 || res.status === 403) {
            window.location.href = '../login.html';
            return;
          }
          throw new Error('Ошибка сети: ' + res.status);
        }

        const data = await res.json();

        if (!data.success) {
          console.error('Ошибка данных:', data.error);
          return;
        }

        // обновляем статистику дашборда
        updateDashboard(data.stats);

        updateAttendanceTable(data.bookings);

      } catch (error) {
        console.error('Ошибка загрузки данных:', error);
        document.getElementById('attendanceBody').innerHTML =
          '<tr><td colspan="7" class="empty-state">Ошибка загрузки данных</td></tr>';
      }
    }

    // функция обновления дашборда
    function updateDashboard(stats) {
      const cards = document.querySelectorAll('.stat-card');

      if (cards.length >= 4) {
        cards[0].querySelector('.stat-number').textContent = stats.total;
        cards[1].querySelector('.stat-number').textContent = stats.attended_percent + '%';
        cards[2].querySelector('.stat-number').textContent = stats.missed_percent + '%';
        cards[3].querySelector('.stat-number').textContent = stats.cancelled_percent + '%';
      }
    }

    // обновляем таблицу посещений
    function updateAttendanceTable(bookings) {
      const tbody = document.getElementById('attendanceBody');

      if (!bookings || bookings.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="7" class="empty-state">
              У вас пока нет записей на тренировки
            </td>
          </tr>
        `;
        return;
      }

      // присваиваем статусы для посещений
      tbody.innerHTML = bookings.map(booking => {
        const date = new Date(booking.workout_date);
        const formattedDate = date.toLocaleDateString('ru-RU');

        let statusClass = '';
        let statusText = '';
        switch (booking.booking_status) {
          case 'attended':
            statusClass = 'status-attended';
            statusText = 'Посещена';
            break;
          case 'missed':
            statusClass = 'status-missed';
            statusText = 'Пропущена';
            break;
          case 'confirmed':
            statusClass = 'status-confirmed';
            statusText = 'Подтверждена';
            break;
          case 'cancelled':
            statusClass = 'status-cancelled';
            statusText = 'Отменена';
            break;
          default:
            statusClass = 'status-upcoming';
            statusText = 'Создана';
        }

        // отмечаем посеещения
        let attendanceClass = '';
        let attendanceText = '';
        if (booking.attendance_status === 'attended') {
          attendanceClass = 'status-attended';
          attendanceText = 'Присутствовал';
        } else if (booking.attendance_status === 'missed') {
          attendanceClass = 'status-missed';
          attendanceText = 'Отсутствовал';
        } else {
          attendanceClass = 'status-upcoming';
          attendanceText = booking.workout_date_passed ? 'Не отмечено' : 'Ожидается';
        }

        // время
        const startTime = booking.start_time.substring(0, 5);
        const endTime = booking.end_time.substring(0, 5);

        let notesHtml = '';
        if (booking.trainer_notes) {
          notesHtml = `
            <div class="notes-section">
              <div class="notes-label">Заметки тренера:</div>
              <div>${booking.trainer_notes}</div>
            </div>
          `;
        } else if (booking.attendance_status) {
          notesHtml = '<em>Нет заметок</em>';
        }

        // выводим данные в таблицу
        return `
          <tr class="attendance-row" data-status="${booking.booking_status}">
            <td>${formattedDate}</td>
            <td><strong>${booking.workout_title}</strong></td>
            <td>${booking.trainer_name}</td>
            <td>${startTime} - ${endTime}</td>
            <td><span class="status-badge ${statusClass}">${statusText}</span></td>
            <td><span class="status-badge ${attendanceClass}">${attendanceText}</span></td>
            <td>${notesHtml}</td>
          </tr>
        `;
      }).join('');
    }

    // фильтруем данные 
    function filterAttendance(status) {
      document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
      });
      event.target.classList.add('active');

      const rows = document.querySelectorAll('.attendance-row');
      rows.forEach(row => {
        if (status === 'all') {
          row.style.display = '';
        } else {
          if (row.getAttribute('data-status') === status) {
            row.style.display = '';
          } else {
            row.style.display = 'none';
          }
        }
      });

      // если результатов нет
      const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
      if (visibleRows.length === 0 && rows.length > 0) {
        document.getElementById('attendanceBody').innerHTML += `
          <tr id="noResultsRow">
            <td colspan="7" class="empty-state">
              Нет записей с выбранным статусом
            </td>
          </tr>
        `;
      } else {
        const noResultsRow = document.getElementById('noResultsRow');
        if (noResultsRow) {
          noResultsRow.remove();
        }
      }
    }

    document.addEventListener('DOMContentLoaded', loadAttendanceData);
  </script>
</body>

</html>