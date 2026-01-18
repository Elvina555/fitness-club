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

// Получаем параметры фильтрации
$status = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';

// Формируем SQL запрос
$sql = "SELECT r.*, 
               c.first_name as client_first_name, 
               c.last_name as client_last_name,
               t.first_name as trainer_first_name,
               t.last_name as trainer_last_name,
               w.title as workout_title,
               w.workout_date,
               u.first_name as moderator_first_name,
               u.last_name as moderator_last_name
        FROM reviews r
        LEFT JOIN users c ON r.client_id = c.id
        LEFT JOIN users t ON r.trainer_id = t.id
        LEFT JOIN users u ON r.moderated_by = u.id
        LEFT JOIN workouts w ON r.workout_id = w.id
        WHERE 1=1";

$params = [];
$types = '';

if ($status !== 'all') {
  $sql .= " AND r.moderation_status = ?";
  $params[] = $status;
  $types .= 's';
}

if (!empty($search)) {
  $sql .= " AND (r.comment LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR t.first_name LIKE ? OR t.last_name LIKE ?)";
  $searchTerm = "%{$search}%";
  $params = array_merge($params, array_fill(0, 5, $searchTerm));
  $types .= str_repeat('s', 5);
}

$sql .= " ORDER BY r.created_at DESC";

$reviews = $db->fetchAll($sql, $params);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Модерация отзывов - Админ панель</title>
  <link rel="stylesheet" href="../assets/css/common/UI.css">
  <link rel="stylesheet" href="../assets/css/admin/reviews.css">
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
        <li><a href="reports.php">Отчеты</a></li>
        <li><a href="../logout.php">Выход</a></li>
      </ul>
    </nav>

    <main class="main-content">
      <div class="container">
        <div class="reviews-container">
          <div class="reviews-header">
            <h2>Модерация отзывов</h2>

            <div class="filters">


              <div class="filter-tabs">

                <form method="GET" action="reviews.php" class="search-box">
                  <input type="text" name="search" style="width: 80%;"
                    placeholder="Поиск по тексту, клиенту, тренеру..." value="<?php echo htmlspecialchars($search); ?>"
                    onchange="this.form.submit()">
                  <?php if ($status !== 'all'): ?>
                    <input type="hidden" name="status" value="<?php echo $status; ?>">
                  <?php endif; ?>
                </form>

                <a href="reviews.php?status=all" class="filter-tab <?php echo $status === 'all' ? 'active' : ''; ?>">
                  Все
                </a>
                <a href="reviews.php?status=pending"
                  class="filter-tab <?php echo $status === 'pending' ? 'active' : ''; ?>">
                  Ожидают
                </a>
                <a href="reviews.php?status=approved"
                  class="filter-tab <?php echo $status === 'approved' ? 'active' : ''; ?>">
                  Одобрены
                </a>
                <a href="reviews.php?status=rejected"
                  class="filter-tab <?php echo $status === 'rejected' ? 'active' : ''; ?>">
                  Отклонены
                </a>
              </div>
            </div>
          </div>

          <div class="reviews-table-container">
            <?php if (empty($reviews)): ?>
              <div class="empty-state">
                <div class="empty-state-icon">📝</div>
                <h3>Отзывы не найдены</h3>
                <p>Попробуйте изменить параметры поиска</p>
              </div>
            <?php else: ?>
              <table class="reviews-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Клиент</th>
                    <th>Тренер</th>
                    <th>Тренировка</th>
                    <th>Рейтинг</th>
                    <th>Отзыв</th>
                    <th>Статус</th>
                    <th>Дата</th>
                    <th>Действия</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- перебор всех отзывов, в зависимости от значений в бд свой определенный вывод -->
                  <?php foreach ($reviews as $review): ?>
                    <tr>
                      <td><?php echo $review['id']; ?></td>
                      <td>
                        <?php echo htmlspecialchars($review['client_first_name'] . ' ' . $review['client_last_name']); ?>
                      </td>
                      <td>
                        <?php echo htmlspecialchars($review['trainer_first_name'] . ' ' . $review['trainer_last_name']); ?>
                      </td>
                      <td>
                        <?php echo htmlspecialchars($review['workout_title']); ?><br>
                        <small><?php echo date('d.m.Y', strtotime($review['workout_date'])); ?></small>
                      </td>
                      <td>
                        <div class="rating">
                          <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="rating-star <?php echo $i > $review['rating'] ? 'rating-empty' : ''; ?>">
                              <?php echo $i <= $review['rating'] ? '★' : '☆'; ?>
                            </span>
                          <?php endfor; ?>
                        </div>
                      </td>
                      <td>
                        <div class="review-comment" title="<?php echo htmlspecialchars($review['comment']); ?>">
                          <?php echo htmlspecialchars(mb_strimwidth($review['comment'], 0, 50, '...')); ?>
                        </div>
                      </td>
                      <td>
                        <span class="status-badge status-<?php echo $review['moderation_status']; ?>">
                          <?php
                          echo match ($review['moderation_status']) {
                            'pending' => 'На модерации',
                            'approved' => 'Одобрен',
                            'rejected' => 'Отклонен',
                            default => $review['moderation_status']
                          };
                          ?>
                        </span>
                      </td>
                      <td>
                        <?php echo date('d.m.Y H:i', strtotime($review['created_at'])); ?>
                      </td>
                      <td>
                        <div class="actions">
                          <?php if ($review['moderation_status'] === 'pending'): ?>
                            <button class="btn btn-approve" onclick="approveReview(<?php echo $review['id']; ?>)">
                              Одобрить
                            </button>
                            <button class="btn btn-reject" onclick="showRejectModal(<?php echo $review['id']; ?>)">
                              Отклонить
                            </button>
                          <?php else: ?>
                            <button class="btn btn-edit"
                              onclick="showEditModal(<?php echo htmlspecialchars(json_encode($review, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)); ?>)">
                              Изменить
                            </button>
                            <button class="btn btn-delete" onclick="deleteReview(<?php echo $review['id']; ?>)">
                              Удалить
                            </button>
                          <?php endif; ?>
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

  <!-- модальное окно отклонения отзыва -->
  <div id="rejectModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Отклонение отзыва</h3>
        <span class="close" onclick="closeRejectModal()">&times;</span>
      </div>
      <form id="rejectForm">
        <input type="hidden" id="reject_review_id" name="review_id">
        <input type="hidden" name="action" value="reject">

        <div class="form-group">
          <label for="reject_reason">Причина отклонения *</label>
          <textarea id="reject_reason" name="moderation_comment" class="form-control" rows="4" required
            placeholder="Укажите причину отклонения отзыва (будет видна только администраторам)"></textarea>
        </div>

        <div class="form-actions">
          <button type="button" class="btn btn-cancel" onclick="closeRejectModal()">Отмена</button>
          <button type="submit" class="btn btn-save">Отклонить отзыв</button>
        </div>
      </form>
    </div>
  </div>

  <!-- модальное окно редактирования отзыва -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Редактирование отзыва</h3>
        <span class="close" onclick="closeEditModal()">&times;</span>
      </div>
      <form id="editForm">
        <input type="hidden" id="edit_review_id" name="review_id">
        <input type="hidden" name="action" value="edit">

        <div class="form-group">
          <label for="edit_rating">Рейтинг *</label>
          <select id="edit_rating" name="rating" class="form-control" required>
            <option value="1">1 ★</option>
            <option value="2">2 ★★</option>
            <option value="3">3 ★★★</option>
            <option value="4">4 ★★★★</option>
            <option value="5">5 ★★★★★</option>
          </select>
        </div>

        <div class="form-group">
          <label for="edit_comment">Отзыв *</label>
          <textarea id="edit_comment" name="comment" class="form-control" rows="4" required></textarea>
        </div>

        <div class="form-group">
          <label for="edit_status">Статус модерации</label>
          <select id="edit_status" name="moderation_status" class="form-control">
            <option value="pending">На модерации</option>
            <option value="approved">Одобрен</option>
            <option value="rejected">Отклонен</option>
          </select>
        </div>

        <div class="form-group">
          <label for="edit_moderation_comment">Комментарий модератора</label>
          <textarea id="edit_moderation_comment" name="moderation_comment" class="form-control" rows="3"
            placeholder="Причина отклонения (только для администраторов)"></textarea>
        </div>

        <div class="form-actions">
          <button type="button" class="btn btn-cancel" onclick="closeEditModal()">Отмена</button>
          <button type="submit" class="btn btn-save">Сохранить</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // функция для работы с моадльными окнами
    function showRejectModal(reviewId) {
      document.getElementById('reject_review_id').value = reviewId;
      document.getElementById('rejectModal').style.display = 'block';
    }

    function closeRejectModal() {
      document.getElementById('rejectModal').style.display = 'none';
      document.getElementById('rejectForm').reset();
    }

    function showEditModal(review) {
      document.getElementById('edit_review_id').value = review.id;
      document.getElementById('edit_rating').value = review.rating;
      document.getElementById('edit_comment').value = review.comment;
      document.getElementById('edit_status').value = review.moderation_status;
      document.getElementById('edit_moderation_comment').value = review.moderation_comment || '';
      document.getElementById('editModal').style.display = 'block';
    }

    function closeEditModal() {
      document.getElementById('editModal').style.display = 'none';
      document.getElementById('editForm').reset();
    }

    // одобрение отзыва
    function approveReview(reviewId) {
      if (confirm('Вы уверены, что хотите одобрить этот отзыв?')) {
        const formData = new FormData();
        formData.append('action', 'approve');
        formData.append('review_id', reviewId);

        fetch('../api/admin/admin-reviews.php', {
          method: 'POST',
          body: formData
        })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              alert('Отзыв успешно одобрен');
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

    // удаление отзыва
    function deleteReview(reviewId) {
      if (confirm('Вы уверены, что хотите удалить этот отзыв?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('review_id', reviewId);

        fetch('../api/admin/admin-reviews.php', {
          method: 'POST',
          body: formData
        })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              alert('Отзыв успешно удален');
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

    // обработчик формы отклонения
    document.getElementById('rejectForm').addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData(this);

      fetch('../api/admin/admin-reviews.php', {
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('Отзыв отклонен');
            closeRejectModal();
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
    document.getElementById('editForm').addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData(this);

      fetch('../api/admin/admin-reviews.php', {
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('Отзыв обновлен');
            closeEditModal();
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
    window.onclick = function (event) {
      const rejectModal = document.getElementById('rejectModal');
      const editModal = document.getElementById('editModal');

      if (event.target === rejectModal) {
        closeRejectModal();
      }
      if (event.target === editModal) {
        closeEditModal();
      }
    };
  </script>
</body>

</html>