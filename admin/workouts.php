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

// получаем параметры фильтрации
$status = $_GET['status'] ?? 'scheduled';
$search = $_GET['search'] ?? '';
$trainer_id = $_GET['trainer_id'] ?? '';

// получаем список тренеров для фильтра
$trainers = $db->fetchAll("SELECT id, first_name, last_name FROM users WHERE role = 'trainer' AND active = 1 ORDER BY first_name");

// SQL запрос для тренировок
$sql = "SELECT w.*, 
               u.first_name as trainer_first_name, 
               u.last_name as trainer_last_name,
               COUNT(b.id) as booked_count
        FROM workouts w
        LEFT JOIN users u ON w.trainer_id = u.id
        LEFT JOIN bookings b ON w.id = b.workout_id AND b.status IN ('created', 'confirmed')
        WHERE 1=1";

$params = [];
$types = '';

if ($status !== 'all') {
    $sql .= " AND w.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($search)) {
    $sql .= " AND (w.title LIKE ? OR w.description LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, array_fill(0, 4, $searchTerm));
    $types .= str_repeat('s', 4);
}

if (!empty($trainer_id)) {
    $sql .= " AND w.trainer_id = ?";
    $params[] = $trainer_id;
    $types .= 's';
}

$sql .= " GROUP BY w.id ORDER BY w.workout_date DESC, w.start_time DESC";

$workouts = $db->fetchAll($sql, $params);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление тренировками - Админ панель</title>
    <link rel="stylesheet" href="../assets/css/common/UI.css">
    <link rel="stylesheet" href="../assets/css/admin/workouts.css">
</head>
<body>
    <div class="app-container">
        <nav class="navbar">
            <div class="navbar-brand">
                <h2>FitClub Admin</h2>
            </div>
            <!-- навигация -->
            <ul class="navbar-menu">
        <li><a href="index.php" >Главная</a></li>
        <li><a href="users.php">Пользователи</a></li>
        <li><a href="workouts.php" class="active">Тренировки</a></li>
        <li><a href="reports.php">Отчеты</a></li>
        <li><a href="../logout.php">Выход</a></li>
            </ul>
        </nav>

        <main class="main-content">
            <div class="container">
                <div class="workouts-container">
                    <div class="workouts-header">
                        <h2>Управление тренировками</h2>

                        <div class="filters">
                            

                            <div class="filter-tabs">

                            <form method="GET" action="workouts.php" class="search-box">
                                <input type="text" name="search" placeholder="Поиск по названию, тренеру..."
                                    value="<?php echo htmlspecialchars($search); ?>" onchange="this.form.submit()">
                                <input type="hidden" name="status" value="<?php echo $status; ?>">
                                <input type="hidden" name="trainer_id" value="<?php echo $trainer_id; ?>">
                            </form>

                                <a href="workouts.php?status=all" class="filter-tab <?php echo $status === 'all' ? 'active' : ''; ?>">
                                    Все
                                </a>
                                <a href="workouts.php?status=scheduled" class="filter-tab <?php echo $status === 'scheduled' ? 'active' : ''; ?>">
                                    Запланированы
                                </a>
                                <a href="workouts.php?status=cancelled" class="filter-tab <?php echo $status === 'cancelled' ? 'active' : ''; ?>">
                                    Отменены
                                </a>
                                <a href="workouts.php?status=completed" class="filter-tab <?php echo $status === 'completed' ? 'active' : ''; ?>">
                                    Проведены
                                </a>

                                <select name="trainer_id" class="filter-select" onchange="this.form.submit()" form="filterForm">
                                <option value="">Все тренеры</option>
                                <!-- выбор тренера -->
                                <?php foreach ($trainers as $trainer): ?>
                                    <option value="<?php echo $trainer['id']; ?>" <?php echo $trainer_id == $trainer['id'] ? 'selected' : ''; ?>>
                                          <?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button class="btn btn-add" onclick="openAddWorkoutModal()">
                                <span>+</span> Добавить тренировку
                            </button>

                            </div>

                            
                        </div>
                    </div>

                    <!-- вывод таблицы с инфой -->
                    <div class="workouts-table-container">
                        <?php if (empty($workouts)): ?>
                            <div class="empty-state">
                                <h3>Тренировки не найдены</h3>
                                <p>Попробуйте изменить параметры поиска или добавьте новую тренировку</p>
                            </div>
                        <?php else: ?>
                            <table class="workouts-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Название</th>
                                        <th>Тренер</th>
                                        <th>Дата и время</th>
                                        <th>Участники</th>
                                        <th>Статус</th>
                                        <th>Дата создания</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($workouts as $workout): ?>
                                        <tr>
                                            <td><?php echo $workout['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($workout['title']); ?></strong><br>
                                                <small><?php echo htmlspecialchars(mb_strimwidth($workout['description'] ?? '', 0, 50, '...')); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($workout['trainer_first_name'] . ' ' . $workout['trainer_last_name']); ?>
                                            </td>
                                            <td>
                                                <?php echo date('d.m.Y', strtotime($workout['workout_date'])); ?><br>
                                                <?php echo date('H:i', strtotime($workout['start_time'])) . ' - ' . date('H:i', strtotime($workout['end_time'])); ?>
                                            </td>
                                            <td>
                                                <div class="participants">
                                                    <span><?php echo $workout['booked_count']; ?>/<?php echo $workout['max_participants']; ?></span>
                                                    <div class="participants-bar">
                                                        <div class="participants-fill" style="width: <?php echo min(100, ($workout['booked_count'] / $workout['max_participants']) * 100); ?>%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $workout['status']; ?>">
                                                    <?php
                                                    echo match ($workout['status']) {
                                                        'scheduled' => 'Запланирована',
                                                        'cancelled' => 'Отменена',
                                                        'completed' => 'Проведена',
                                                        default => $workout['status']
                                                    };
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('d.m.Y H:i', strtotime($workout['created_at'])); ?>
                                            </td>
                                            <td>
                                                <div class="actions">
                                                    <?php if ($workout['status'] === 'scheduled'): ?>
                                                        <button class="btn btn-edit" onclick="openEditWorkoutModal(<?php echo htmlspecialchars(json_encode($workout, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)); ?>)">
                                                            Редактировать
                                                        </button>
                                                        <button class="btn btn-cancel" onclick="cancelWorkout(<?php echo $workout['id']; ?>, '<?php echo htmlspecialchars(addslashes($workout['title'])); ?>')">
                                                            Отменить
                                                        </button>
                                                        <button class="btn btn-complete" onclick="completeWorkout(<?php echo $workout['id']; ?>)">
                                                            Завершить
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-delete" onclick="deleteWorkout(<?php echo $workout['id']; ?>, '<?php echo htmlspecialchars(addslashes($workout['title'])); ?>')">
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

    <!-- модальное окно добавления тренировки -->
    <div id="addWorkoutModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Добавить новую тренировку</h3>
                <span class="close" onclick="closeAddWorkoutModal()">&times;</span>
            </div>
            <form id="addWorkoutForm">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label for="add_title">Название тренировки *</label>
                    <input type="text" id="add_title" name="title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="add_description">Описание</label>
                    <textarea id="add_description" name="description" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="add_trainer_id">Тренер *</label>
                        <select id="add_trainer_id" name="trainer_id" class="form-control" required>
                            <option value="">Выберите тренера</option>
                            <?php foreach ($trainers as $trainer): ?>
                                <option value="<?php echo $trainer['id']; ?>">
                                    <?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="add_max_participants">Макс. участников *</label>
                        <input type="number" id="add_max_participants" name="max_participants" class="form-control" min="1" max="50" value="10" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="add_workout_date">Дата тренировки *</label>
                        <input type="date" id="add_workout_date" name="workout_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="add_start_time">Время начала *</label>
                        <input type="time" id="add_start_time" name="start_time" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="add_end_time">Время окончания *</label>
                        <input type="time" id="add_end_time" name="end_time" class="form-control" required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-cancel-modal" onclick="closeAddWorkoutModal()">Отмена</button>
                    <button type="submit" class="btn btn-save">Добавить</button>
                </div>
            </form>
        </div>
    </div>

    <!-- модальное окно редактирования тренировки -->
    <div id="editWorkoutModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Редактировать тренировку</h3>
                <span class="close" onclick="closeEditWorkoutModal()">&times;</span>
            </div>
            <form id="editWorkoutForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_id" name="id">

                <div class="form-group">
                    <label for="edit_title">Название тренировки *</label>
                    <input type="text" id="edit_title" name="title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="edit_description">Описание</label>
                    <textarea id="edit_description" name="description" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_trainer_id">Тренер *</label>
                        <select id="edit_trainer_id" name="trainer_id" class="form-control" required>
                            <option value="">Выберите тренера</option>
                            <?php foreach ($trainers as $trainer): ?>
                                <option value="<?php echo $trainer['id']; ?>">
                                    <?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_max_participants">Макс. участников *</label>
                        <input type="number" id="edit_max_participants" name="max_participants" class="form-control" min="1" max="50" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_workout_date">Дата тренировки *</label>
                        <input type="date" id="edit_workout_date" name="workout_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_start_time">Время начала *</label>
                        <input type="time" id="edit_start_time" name="start_time" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_end_time">Время окончания *</label>
                        <input type="time" id="edit_end_time" name="end_time" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="edit_status">Статус</label>
                    <select id="edit_status" name="status" class="form-control">
                        <option value="scheduled">Запланирована</option>
                        <option value="cancelled">Отменена</option>
                        <option value="completed">Проведена</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-cancel-modal" onclick="closeEditWorkoutModal()">Отмена</button>
                    <button type="submit" class="btn btn-save">Сохранить</button>
                </div>
            </form>
        </div>
    </div>

    <form id="filterForm" method="GET" action="workouts.php" style="display: none;">
        <input type="hidden" name="status" value="<?php echo $status; ?>">
        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
    </form>

    <script>
        // устанавливаем минимальную дату на сегодня для формы добавления
        document.getElementById('add_workout_date').min = new Date().toISOString().split('T')[0];
        
        // функции для работы с модалками
        function openAddWorkoutModal() {
            document.getElementById('addWorkoutModal').style.display = 'block';
        }

        function closeAddWorkoutModal() {
            document.getElementById('addWorkoutModal').style.display = 'none';
            document.getElementById('addWorkoutForm').reset();
        }

        function openEditWorkoutModal(workout) {
            document.getElementById('editWorkoutModal').style.display = 'block';
            
            // заполняем форму данными тренировки
            document.getElementById('edit_id').value = workout.id;
            document.getElementById('edit_title').value = workout.title;
            document.getElementById('edit_description').value = workout.description || '';
            document.getElementById('edit_trainer_id').value = workout.trainer_id;
            document.getElementById('edit_max_participants').value = workout.max_participants;
            document.getElementById('edit_workout_date').value = workout.workout_date;
            document.getElementById('edit_start_time').value = workout.start_time;
            document.getElementById('edit_end_time').value = workout.end_time;
            document.getElementById('edit_status').value = workout.status;
        }

        function closeEditWorkoutModal() {
            document.getElementById('editWorkoutModal').style.display = 'none';
            document.getElementById('editWorkoutForm').reset();
        }

        // отмена тренировки
        function cancelWorkout(workoutId, workoutTitle) {
            if (confirm(`Вы уверены, что хотите отменить тренировку "${workoutTitle}"? Все записавшиеся клиенты получат уведомления.`)) {
                const formData = new FormData();
                formData.append('action', 'cancel');
                formData.append('workout_id', workoutId);

                fetch('../api/admin/admin-workouts.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Тренировка отменена');
                        window.location.reload();
                    } else {
                        alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
                    }
                })
                .catch(error => {
                    alert('Ошибка сети: ' + error.message);
                });
            }
        }

        // завершение тренировки
        function completeWorkout(workoutId) {
            if (confirm('Вы уверены, что хотите отметить тренировку как проведенную?')) {
                const formData = new FormData();
                formData.append('action', 'complete');
                formData.append('workout_id', workoutId);

                fetch('../api/admin/admin-workouts.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Тренировка отмечена как проведенная');
                        window.location.reload();
                    } else {
                        alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
                    }
                })
                .catch(error => {
                    alert('Ошибка сети: ' + error.message);
                });
            }
        }

        // удаление тренировки
        function deleteWorkout(workoutId, workoutTitle) {
            if (confirm(`Вы уверены, что хотите удалить тренировку "${workoutTitle}"? Это действие нельзя отменить.`)) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('workout_id', workoutId);

                fetch('../api/admin/admin-workouts.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Тренировка удалена');
                        window.location.reload();
                    } else {
                        alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
                    }
                })
                .catch(error => {
                    alert('Ошибка сети: ' + error.message);
                });
            }
        }

        // обработчик формы добавления
        document.getElementById('addWorkoutForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // проверка времени
            const startTime = document.getElementById('add_start_time').value;
            const endTime = document.getElementById('add_end_time').value;
            
            if (startTime >= endTime) {
                alert('Время окончания должно быть позже времени начала');
                return;
            }

            const formData = new FormData(this);

            fetch('../api/admin/admin-workouts.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Тренировка успешно добавлена');
                    closeAddWorkoutModal();
                    window.location.reload();
                } else {
                    alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
                }
            })
            .catch(error => {
                alert('Ошибка сети: ' + error.message);
            });
        });

        // обработчик формы редактирования
        document.getElementById('editWorkoutForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // проверка времени
            const startTime = document.getElementById('edit_start_time').value;
            const endTime = document.getElementById('edit_end_time').value;
            
            if (startTime >= endTime) {
                alert('Время окончания должно быть позже времени начала');
                return;
            }

            const formData = new FormData(this);

            fetch('../api/admin/admin-workouts.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Тренировка успешно обновлена');
                    closeEditWorkoutModal();
                    window.location.reload();
                } else {
                    alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
                }
            })
            .catch(error => {
                alert('Ошибка сети: ' + error.message);
            });
        });

        // закрытие модальных окон при клике вне их
        window.onclick = function(event) {
            const addModal = document.getElementById('addWorkoutModal');
            const editModal = document.getElementById('editWorkoutModal');

            if (event.target === addModal) {
                closeAddWorkoutModal();
            }
            if (event.target === editModal) {
                closeEditWorkoutModal();
            }
        };
    </script>
</body>
</html>