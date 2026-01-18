<?php
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Database.php';

$userModel = new User();
$db = new Database();

$currentUser = $userModel->getCurrentUser();
if (!$currentUser || $currentUser['role'] !== 'admin') {
  header('Location: /login.html');
  exit;
}

// Определяем выбранную роль
$role = $_GET['role'] ?? 'all';
$search = $_GET['search'] ?? '';

// Формируем SQL запрос с фильтрами
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = '';

if ($role !== 'all') {
  $sql .= " AND role = ?";
  $params[] = $role;
  $types .= 's';
}

if (!empty($search)) {
  $sql .= " AND (email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ?)";
  $searchTerm = "%{$search}%";
  $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
  $types .= str_repeat('s', 4);
}

$sql .= " ORDER BY created_at DESC";

// Получаем пользователей через Database класс
$users = $db->fetchAll($sql, $params);

// Определяем заголовок в зависимости от выбранной роли
$pageTitle = match ($role) {
  'client' => 'Управление клиентами',
  'trainer' => 'Управление тренерами',
  'admin' => 'Управление администраторами',
  default => 'Управление пользователями'
};
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $pageTitle; ?> - Админ панель</title>
  <link rel="stylesheet" href="../assets/css/common/UI.css">
  <link rel="stylesheet" href="../assets/css/admin/users.css">
  <style>

  </style>
</head>

<body>
  <div class="app-container">
    <nav class="navbar">
      <div class="navbar-brand">
        <h2>FitClub Admin</h2>
      </div>
      <ul class="navbar-menu">
        <li><a href="index.php">Главная</a></li>
        <li><a href="users.php" class="active">Пользователи</a></li>
        <li><a href="workouts.php">Тренировки</a></li>
        <li><a href="reports.php">Отчеты</a></li>
        <li><a href="reviews.php">Отзывы</a></li>
        <li><a href="../logout.php">Выход</a></li>
      </ul>
    </nav>

    <main class="main-content">
      <div class="container">
        <div class="users-container">
          <div class="users-header">
            <h2><?php echo $pageTitle; ?></h2>

            <div class="filters">


              <div class="filter-tabs">

                <form method="GET" action="users.php" class="search-box">
                  <input type="text" name="search" placeholder="Поиск по email, имени, фамилии..."
                    value="<?php echo htmlspecialchars($search); ?>" onchange="this.form.submit()">
                  <?php if ($role !== 'all'): ?>
                    <input type="hidden" name="role" value="<?php echo $role; ?>">
                  <?php endif; ?>
                </form>

                <a href="users.php?role=all" class="filter-tab <?php echo $role === 'all' ? 'active' : ''; ?>">
                  Все
                </a>
                <a href="users.php?role=client" class="filter-tab <?php echo $role === 'client' ? 'active' : ''; ?>">
                  Клиенты
                </a>
                <a href="users.php?role=trainer" class="filter-tab <?php echo $role === 'trainer' ? 'active' : ''; ?>">
                  Тренеры
                </a>
                <a href="users.php?role=admin" class="filter-tab <?php echo $role === 'admin' ? 'active' : ''; ?>">
                  Админы
                </a>
                <button class="btn btn-add" onclick="openAddUserModal()">
                  <span>+</span> Добавить пользователя
                </button>
              </div>


            </div>
          </div>

          <div class="users-table-container">
            <?php if (empty($users)): ?>
              <div class="empty-state">
                <h3>Пользователи не найдены</h3>
                <p>Попробуйте изменить параметры поиска или добавьте нового пользователя</p>
              </div>
            <?php else: ?>
              <table class="users-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>ФИО</th>
                    <th>Телефон</th>
                    <th>Роль</th>
                    <th>Статус</th>
                    <th>Дата регистрации</th>
                    <th>Действия</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- перебор юзеров и инфы о них -->
                  <?php foreach ($users as $user): ?>
                    <tr>
                      <td><?php echo $user['id']; ?></td>
                      <td>
                        <strong><?php echo htmlspecialchars($user['email']); ?></strong>
                      </td>
                      <td>
                        <?php
                        echo htmlspecialchars(
                          ($user['last_name'] ?? '') . ' ' .
                          ($user['first_name'] ?? '') . ' ' .
                          ($user['middle_name'] ?? '')
                        );
                        ?>
                      </td>
                      <td><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></td>
                      <td>
                        <span class="role-badge role-<?php echo $user['role']; ?>">
                          <?php
                          echo match ($user['role']) {
                            'client' => 'Клиент',
                            'trainer' => 'Тренер',
                            'admin' => 'Админ',
                            default => $user['role']
                          };
                          ?>
                        </span>
                      </td>
                      <td>
                        <?php if ($user['active']): ?>
                          <span class="active-status">Активен</span>
                        <?php else: ?>
                          <span class="inactive-status">Неактивен</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?>
                      </td>
                      <td>
                        <div class="actions">
                          <button class="btn btn-edit"
                            onclick="openEditUserModal(<?php echo htmlspecialchars(json_encode($user, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)); ?>)">
                            Редактировать
                          </button>
                          <button class="btn btn-delete"
                            onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['email'])); ?>')">
                            Удалить
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- модальное окно добавления пользователя -->
  <div id="addUserModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Добавить нового пользователя</h3>
        <span class="close" onclick="closeAddUserModal()">&times;</span>
      </div>
      <form id="addUserForm">
        <input type="hidden" name="action" value="add">

        <div class="form-group">
          <label for="add_email">Email *</label>
          <input type="email" id="add_email" name="email" class="form-control" required>
        </div>

        <div class="form-group">
          <label for="add_password">Пароль *</label>
          <input type="password" id="add_password" name="password" class="form-control" required>
        </div>

        <div class="form-group">
          <label for="add_role">Роль *</label>
          <select id="add_role" name="role" class="form-control" required onchange="toggleTrainerFields('add')">
            <option value="client">Клиент</option>
            <option value="trainer">Тренер</option>
            <option value="admin">Администратор</option>
          </select>
        </div>

        <div class="form-group">
          <label for="add_first_name">Имя *</label>
          <input type="text" id="add_first_name" name="first_name" class="form-control" required>
        </div>

        <div class="form-group">
          <label for="add_last_name">Фамилия *</label>
          <input type="text" id="add_last_name" name="last_name" class="form-control" required>
        </div>

        <div class="form-group">
          <label for="add_middle_name">Отчество</label>
          <input type="text" id="add_middle_name" name="middle_name" class="form-control">
        </div>

        <div class="form-group">
          <label for="add_phone">Телефон</label>
          <input type="tel" id="add_phone" name="phone" class="form-control">
        </div>

        <div id="addTrainerFields" style="display: none;">
          <div class="form-group">
            <label for="add_description">Описание тренера</label>
            <textarea id="add_description" name="description" class="form-control" rows="3"></textarea>
          </div>

          <div class="form-group">
            <label for="add_specialization">Специализация</label>
            <input type="text" id="add_specialization" name="specialization" class="form-control">
          </div>
        </div>

        <div class="form-actions">
          <button type="button" class="btn btn-cancel" onclick="closeAddUserModal()">Отмена</button>
          <button type="submit" class="btn btn-save">Добавить</button>
        </div>
      </form>
    </div>
  </div>

  <!-- модальное окно редактирования пользователя -->
  <div id="editUserModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Редактировать пользователя</h3>
        <span class="close" onclick="closeEditUserModal()">&times;</span>
      </div>
      <form id="editUserForm">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" id="edit_id" name="id">

        <div class="form-group">
          <label for="edit_email">Email *</label>
          <input type="email" id="edit_email" name="email" class="form-control" required>
        </div>

        <div class="form-group">
          <label for="edit_password">Пароль (оставьте пустым, если не меняется)</label>
          <input type="password" id="edit_password" name="password" class="form-control">
        </div>

        <div class="form-group">
          <label for="edit_role">Роль *</label>
          <select id="edit_role" name="role" class="form-control" required onchange="toggleTrainerFields('edit')">
            <option value="client">Клиент</option>
            <option value="trainer">Тренер</option>
            <option value="admin">Администратор</option>
          </select>
        </div>

        <div class="form-group">
          <label for="edit_first_name">Имя *</label>
          <input type="text" id="edit_first_name" name="first_name" class="form-control" required>
        </div>

        <div class="form-group">
          <label for="edit_last_name">Фамилия *</label>
          <input type="text" id="edit_last_name" name="last_name" class="form-control" required>
        </div>

        <div class="form-group">
          <label for="edit_middle_name">Отчество</label>
          <input type="text" id="edit_middle_name" name="middle_name" class="form-control">
        </div>

        <div class="form-group">
          <label for="edit_phone">Телефон</label>
          <input type="tel" id="edit_phone" name="phone" class="form-control">
        </div>

        <div class="form-group">
          <label for="edit_active">Статус</label>
          <select id="edit_active" name="active" class="form-control">
            <option value="1">Активен</option>
            <option value="0">Неактивен</option>
          </select>
        </div>

        <div id="editTrainerFields" style="display: none;">
          <div class="form-group">
            <label for="edit_description">Описание тренера</label>
            <textarea id="edit_description" name="description" class="form-control" rows="3"></textarea>
          </div>

          <div class="form-group">
            <label for="edit_specialization">Специализация</label>
            <input type="text" id="edit_specialization" name="specialization" class="form-control">
          </div>
        </div>

        <div class="form-actions">
          <button type="button" class="btn btn-cancel" onclick="closeEditUserModal()">Отмена</button>
          <button type="submit" class="btn btn-save">Сохранить</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // функции для работы с модальными окнами
    function openAddUserModal() {
      document.getElementById('addUserModal').style.display = 'block';
    }

    function closeAddUserModal() {
      document.getElementById('addUserModal').style.display = 'none';
      document.getElementById('addUserForm').reset();
      document.getElementById('addTrainerFields').style.display = 'none';
    }

    function openEditUserModal(user) {
      document.getElementById('editUserModal').style.display = 'block';
      document.body.style.overflow = 'hidden';

      window.originalUserRole = user.role;

      // заполняем форму данными пользователя
      document.getElementById('edit_id').value = user.id;
      document.getElementById('edit_email').value = user.email;
      document.getElementById('edit_role').value = user.role;
      document.getElementById('edit_first_name').value = user.first_name;
      document.getElementById('edit_last_name').value = user.last_name;
      document.getElementById('edit_middle_name').value = user.middle_name || '';
      document.getElementById('edit_phone').value = user.phone || '';
      document.getElementById('edit_active').value = user.active ? '1' : '0';

      // для тренеров показываем дополнительные поля
      if (user.role === 'trainer') {
        document.getElementById('editTrainerFields').style.display = 'block';
        document.getElementById('edit_description').value = user.description || '';
        document.getElementById('edit_specialization').value = user.specialization || '';
      } else {
        document.getElementById('editTrainerFields').style.display = 'none';
      }

      // добавляем обработчик смены роли с предупреждениями
      document.getElementById('edit_role').addEventListener('change', function () {
        if (window.originalUserRole !== this.value) {
          let message = '';
          if (window.originalUserRole === 'client' && this.value === 'trainer') {
            message = 'ВНИМАНИЕ! При смене клиента на тренера:\n' +
              '- Все будущие записи клиента будут отменены\n' +
              '- Все абонементы будут удалены\n' +
              '- Все отзывы и история посещений будут удалены\n' +
              '- Пользователь станет "чистым" тренером\n' +
              'Продолжить?';
          } else if (window.originalUserRole === 'client' && this.value === 'admin') {
            message = 'ВНИМАНИЕ! При смене клиента на администратора:\n' +
              '- Все клиентские данные будут удалены\n' +
              '- Все абонементы и записи будут удалены\n' +
              '- Пользователь станет чистым администратором\n' +
              'Продолжить?';
          } else if (window.originalUserRole === 'trainer' && this.value === 'client') {
            message = 'ВНИМАНИЕ! При смене тренера на клиента:\n' +
              '- Все будущие тренировки тренера будут отменены\n' +
              '- Все отзывы о тренере будут удалены\n' +
              '- Специализация и описание будут очищены\n' +
              '- Пользователь станет чистым клиентом\n' +
              'Продолжить?';
          } else if (window.originalUserRole === 'trainer' && this.value === 'admin') {
            message = 'ВНИМАНИЕ! При смене тренера на администратора:\n' +
              '- Все тренировки будут переданы другому тренеру или отменены\n' +
              '- Все отзывы о тренере будут удалены\n' +
              '- Пользователь станет чистым администратором\n' +
              'Продолжить?';
          } else if (window.originalUserRole === 'admin' && this.value === 'client') {
            message = 'ВНИМАНИЕ! При смене администратора на клиента:\n' +
              '- Все админские связи будут очищены\n' +
              '- Пользователь станет чистым клиентом\n' +
              'Продолжить?';
          } else if (window.originalUserRole === 'admin' && this.value === 'trainer') {
            message = 'ВНИМАНИЕ! При смене администратора на тренера:\n' +
              '- Все админские связи будут очищены\n' +
              '- Пользователь станет чистым тренером\n' +
              'Продолжить?';
          }

          if (message && !confirm(message)) {
            this.value = window.originalUserRole;
            return;
          }

          // показываем/скрываем поля тренера в зависимости от выбранной роли
          if (this.value === 'trainer') {
            document.getElementById('editTrainerFields').style.display = 'block';
          } else {
            document.getElementById('editTrainerFields').style.display = 'none';
          }
        }
      });
    }

    function closeEditUserModal() {
      document.getElementById('editUserModal').style.display = 'none';
    }

    // переключение полей тренера
    function toggleTrainerFields(formType) {
      const roleSelect = document.getElementById(formType + '_role');
      const trainerFields = document.getElementById(formType + 'TrainerFields');

      if (roleSelect.value === 'trainer') {
        trainerFields.style.display = 'block';
      } else {
        trainerFields.style.display = 'none';
      }
    }

    // удаление пользователя
    function deleteUser(userId, userEmail) {
      if (confirm(`Вы уверены, что хотите удалить пользователя ${userEmail}?`)) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', userId);

        fetch('../api/admin/admin-users.php', {
          method: 'POST',
          body: formData
        })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              alert('Пользователь успешно удален');
              window.location.reload();
            } else {
              alert('Ошибка при удалении: ' + (data.error || 'Неизвестная ошибка'));
            }
          })
          .catch(error => {
            alert('Ошибка сети: ' + error.message);
          });
      }
    }

    // обработчик формы добавления
    document.getElementById('addUserForm').addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData(this);

      fetch('../api/admin/admin-users.php', {
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('Пользователь успешно добавлен');
            closeAddUserModal();
            window.location.reload();
          } else {
            alert('Ошибка при добавлении: ' + (data.error || 'Неизвестная ошибка'));
          }
        })
        .catch(error => {
          alert('Ошибка сети: ' + error.message);
        });
    });

    // обработчик формы редактирования
    document.getElementById('editUserForm').addEventListener('submit', function (e) {
      e.preventDefault();

      const newRole = document.getElementById('edit_role').value;
      const originalRole = window.originalUserRole;

      if (originalRole !== newRole) {
        let confirmMessage = '';
        if (originalRole === 'client' && newRole === 'trainer') {
          confirmMessage = 'Вы уверены, что хотите сменить клиента на тренера? ' +
            'Это отменит все его будущие записи и удалит всю клиентскую историю.';
        } else if (originalRole === 'client' && newRole === 'admin') {
          confirmMessage = 'Вы уверены, что хотите сменить клиента на администратора? ' +
            'Это удалит всю клиентскую историю.';
        } else if (originalRole === 'trainer' && newRole === 'client') {
          confirmMessage = 'Вы уверены, что хотите сменить тренера на клиента? ' +
            'Это отменит все его будущие тренировки.';
        } else if (originalRole === 'trainer' && newRole === 'admin') {
          confirmMessage = 'Вы уверены, что хотите сменить тренера на администратора? ' +
            'Это отменит все его будущие тренировки и удалит отзывы о нем.';
        } else if (originalRole === 'admin' && newRole === 'client') {
          confirmMessage = 'Вы уверены, что хотите сменить администратора на клиента? ' +
            'Это сбросит все его админские связи.';
        } else if (originalRole === 'admin' && newRole === 'trainer') {
          confirmMessage = 'Вы уверены, что хотите сменить администратора на тренера? ' +
            'Это сбросит все его админские связи.';
        }

        if (confirmMessage && !confirm(confirmMessage)) {
          return;
        }
      }

      const formData = new FormData(this);

      fetch('../api/admin/admin-users.php', {
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('Пользователь успешно обновлен');
            closeEditUserModal();
            window.location.reload();
          } else {
            alert('Ошибка при обновлении: ' + (data.error || 'Неизвестная ошибка'));
          }
        })
        .catch(error => {
          alert('Ошибка сети: ' + error.message);
        });
    });

    // закрытие модальных окон при клике вне их
    window.onclick = function (event) {
      const addModal = document.getElementById('addUserModal');
      const editModal = document.getElementById('editUserModal');

      if (event.target === addModal) {
        closeAddUserModal();
      }
      if (event.target === editModal) {
        closeEditUserModal();
      }
    };
  </script>
</body>

</html>