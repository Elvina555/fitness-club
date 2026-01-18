<?php
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Database.php';

$userModel = new User();
$db = new Database();

$currentUser = $userModel->getCurrentUser();
if (!$currentUser || $currentUser['role'] !== 'trainer') {
  header('Location: /login.html');
  exit;
}

$trainerId = $currentUser['id'];

if (isset($_GET['token'])) {
  setcookie('fitness_token', $_GET['token'], time() + 60 * 60 * 24 * 7, '/');
}

// загрузка профиля тренера
$profile = $db->fetchOne(
  "SELECT id, email, role, first_name, last_name, middle_name, 
            phone, avatar_url, description, specialization, 
            created_at, updated_at, active
     FROM users 
     WHERE id = ?",
  [$trainerId]
);

// стата
$stats = $db->fetchOne(
  "SELECT 
        (SELECT COUNT(*) FROM workouts WHERE trainer_id = ? AND status = 'completed') as completed_workouts,
        (SELECT COUNT(*) FROM workouts WHERE trainer_id = ? AND status = 'scheduled' 
         AND (workout_date > CURDATE() OR (workout_date = CURDATE() AND end_time > CURTIME()))) as upcoming_workouts,
        (SELECT COUNT(DISTINCT b.client_id) 
         FROM bookings b 
         JOIN workouts w ON b.workout_id = w.id 
         WHERE w.trainer_id = ? AND b.status IN ('confirmed', 'attended')) as unique_clients,
        (SELECT AVG(r.rating) 
         FROM reviews r 
         JOIN workouts w ON r.workout_id = w.id 
         WHERE w.trainer_id = ? AND r.moderation_status = 'approved') as avg_rating,
        (SELECT COUNT(*) FROM reviews r 
         JOIN workouts w ON r.workout_id = w.id 
         WHERE w.trainer_id = ? AND r.moderation_status = 'approved') as total_reviews",
  [$trainerId, $trainerId, $trainerId, $trainerId, $trainerId]
);
?>
<!DOCTYPE html>
<html lang="ru">

<!-- мета и стили -->

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Профиль - Тренер</title>
  <link rel="stylesheet" href="../assets/css/common/UI.css">
  <link rel="stylesheet" href="../assets/css/trainer/profile.css">
</head>

<body>
  <div class="app-container">
    <nav class="navbar">
      <div class="navbar-brand">
        <h2>FitClub Trainer</h2>
      </div>
      <!-- навигация -->
      <ul class="navbar-menu">
        <li><a href="index.php">Главная</a></li>
        <li><a href="schedule.php">Расписание</a></li>
        <li><a href="attedance.php">Посещаемость</a></li>
        <li><a href="profile.php" class="active">Профиль</a></li>
        <li><a href="../logout.php">Выход</a></li>
      </ul>
    </nav>

    <main class="main-content">
      <div class="container">
        <div class="trainer-container">
          <div class="page-header">
            <h2><i class="fas fa-user-cog"></i> Мой профиль</h2>
            <p>Управляйте вашей личной информацией и настройками аккаунта</p>
          </div>

          <div class="alert" id="messageAlert"></div>

          <div class="profile-content">
            <div class="profile-sidebar">
              <div class="profile-card">
                <div class="profile-avatar">
                  <div class="avatar-container">
                    <!-- щагрузка аватара -->
                    <?php if ($profile['avatar_url']): ?>
                      <img src="<?php echo htmlspecialchars($profile['avatar_url']); ?>" alt="Аватар" class="avatar-img"
                        id="avatarImage">
                    <?php else:
                      $initials = mb_substr($profile['first_name'], 0, 1, 'UTF-8') .
                        mb_substr($profile['last_name'], 0, 1, 'UTF-8');
                      ?>
                      <div class="avatar-placeholder" id="avatarPlaceholder">
                        <?php echo strtoupper($initials); ?>
                      </div>
                    <?php endif; ?>
                    <button class="avatar-upload-btn" onclick="openAvatarUpload()" title="Изменить аватар">
                      <i class="fas fa-camera"></i>
                    </button>
                  </div>
                  <input type="file" id="avatarInput" accept="image/*" style="display: none;"
                    onchange="uploadAvatar(event)">
                  <div class="profile-name">
                    <h3 id="profileName">
                      <?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?>
                    </h3>
                    <?php if ($profile['middle_name']): ?>
                      <p style="color: var(--color-text-secondary); margin: 0;">
                        <?php echo htmlspecialchars($profile['middle_name']); ?>
                      </p>
                    <?php endif; ?>
                  </div>
                  <div class="profile-role">
                    <span class="badge"
                      style="background: var(--color-primary); color: white; padding: 3px 8px; border-radius: 12px;">
                      Тренер
                    </span>
                  </div>
                </div>

                <!-- статистика тренера -->
                <div class="stats-grid">
                  <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['completed_workouts'] ?? 0; ?></div>
                    <div class="stat-label">Проведено тренировок</div>
                  </div>
                  <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['upcoming_workouts'] ?? 0; ?></div>
                    <div class="stat-label">Предстоящих</div>
                  </div>
                  <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['unique_clients'] ?? 0; ?></div>
                    <div class="stat-label">Уникальных клиентов</div>
                  </div>
                  <div class="stat-item">
                    <div class="stat-value">
                      <?php
                      $avgRating = $stats['avg_rating'] ?? 0;
                      echo number_format($avgRating, 1);
                      ?>
                    </div>
                    <div class="stat-label">
                      <div class="rating-stars">
                        <?php
                        $fullStars = floor($avgRating);
                        $hasHalfStar = $avgRating - $fullStars >= 0.5;

                        for ($i = 1; $i <= 5; $i++):
                          if ($i <= $fullStars): ?>
                            <i class="fas fa-star star"></i>
                          <?php elseif ($i == $fullStars + 1 && $hasHalfStar): ?>
                            <i class="fas fa-star-half-alt star"></i>
                          <?php else: ?>
                            <i class="far fa-star star star-empty"></i>
                          <?php endif;
                        endfor; ?>
                      </div>
                    </div>
                  </div>
                </div>

                <div style="margin-top: 20px; text-align: center;">
                  <p class="account-info">
                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($profile['email']); ?>
                  </p>
                  <p class="account-info">
                    <i class="fas fa-calendar-alt"></i> В клубе с
                    <?php echo date('d.m.Y', strtotime($profile['created_at'])); ?>
                  </p>
                  <?php if ($profile['specialization']): ?>
                    <p class="account-info">
                      <i class="fas fa-dumbbell"></i> <?php echo htmlspecialchars($profile['specialization']); ?>
                    </p>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div class="profile-main">

              <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('personal')">
                  <i class="fas fa-user-edit"></i> Личные данные
                </button>
              </div>

              <!-- вывод основной инфы -->
              <div id="personalTab" class="tab-content active">
                <div class="profile-card">
                  <h3 class="section-title">Основная информация</h3>
                  <form id="personalForm" onsubmit="updateProfile(event)">
                    <div class="form-row">
                      <div class="form-group">
                        <label for="first_name">Имя <span class="label-hint">*</span></label>
                        <input type="text" id="first_name" name="first_name"
                          value="<?php echo htmlspecialchars($profile['first_name']); ?>" required maxlength="50">
                      </div>
                      <div class="form-group">
                        <label for="last_name">Фамилия <span class="label-hint">*</span></label>
                        <input type="text" id="last_name" name="last_name"
                          value="<?php echo htmlspecialchars($profile['last_name']); ?>" required maxlength="50">
                      </div>
                    </div>

                    <div class="form-group">
                      <label for="middle_name">Отчество</label>
                      <input type="text" id="middle_name" name="middle_name"
                        value="<?php echo htmlspecialchars($profile['middle_name'] ?? ''); ?>" maxlength="50">
                    </div>

                    <div class="form-group">
                      <label for="phone">Телефон</label>
                      <input type="tel" id="phone" name="phone"
                        value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" maxlength="20"
                        placeholder="+7 (999) 123-45-67">
                      <small class="label-hint">Формат: +7 (999) 123-45-67</small>
                    </div>

                    <div class="form-group">
                      <label for="specialization">Специализация</label>
                      <input type="text" id="specialization" name="specialization"
                        value="<?php echo htmlspecialchars($profile['specialization'] ?? ''); ?>" maxlength="100"
                        placeholder="Например: Йога, Пилатес, Стретчинг">
                      <small class="label-hint">Укажите ваши основные направления</small>
                    </div>

                    <div class="form-group">
                      <label for="description">Обо мне</label>
                      <textarea id="description" name="description" maxlength="1000"
                        placeholder="Расскажите о своем опыте, достижениях, подходе к тренировкам..."><?php echo htmlspecialchars($profile['description'] ?? ''); ?></textarea>
                      <small class="label-hint">Эта информация будет видна клиентам</small>
                    </div>

                    <div class="form-actions">
                      <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Сохранить изменения
                      </button>
                      <button type="button" class="btn btn-secondary" onclick="resetPersonalForm()">
                        <i class="fas fa-undo"></i> Отмена
                      </button>
                    </div>
                  </form>
                </div>
              </div>

              <div
                style="margin-top: 30px; padding: 20px; background: var(--color-secondary); border-radius: var(--radius-base);">
                <h4 style="margin-top: 0; color: var(--color-text);">Дополнительная информация</h4>
                <p style="color: var(--color-text-secondary); margin-bottom: 10px;">
                  <i class="fas fa-info-circle"></i> Для изменения email или роли обратитесь к администратору.
                </p>
                <p style="color: var(--color-text-secondary); margin-bottom: 10px;">
                  <i class="fas fa-shield-alt"></i> Ваши данные защищены и используются только для работы системы.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
  </div>
  </div>
  </main>
  </div>

  <form id="avatarUploadForm" class="avatar-upload-form" enctype="multipart/form-data">
    <input type="file" name="avatar" accept="image/*" required>
  </form>

  <script>
    function showMessage(message, type = 'success') {
      const alert = document.getElementById('messageAlert');
      alert.textContent = message;
      alert.className = `alert alert-${type}`;
      alert.style.display = 'block';
      alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setTimeout(() => {
        alert.style.display = 'none';
      }, 5000);
    }

    function openAvatarUpload() {
      document.getElementById('avatarInput').click();
    }

    // загруузка аватара
    async function uploadAvatar(event) {
      const file = event.target.files[0];
      if (!file) return;

      // чек размера файла (5MB)
      if (file.size > 5 * 1024 * 1024) {
        showMessage('Файл слишком большой. Максимальный размер: 5MB', 'error');
        return;
      }

      // чек типа файла
      const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
      if (!allowedTypes.includes(file.type)) {
        showMessage('Допустимые форматы: JPEG, PNG, GIF, WebP', 'error');
        return;
      }

      const formData = new FormData();
      formData.append('action', 'upload_avatar');
      formData.append('avatar', file);

      try {
        const response = await fetch('../api/trainer/trainer-profile.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          // обнова аватара
          const avatarImage = document.getElementById('avatarImage');
          const avatarPlaceholder = document.getElementById('avatarPlaceholder');

          if (avatarImage) {
            avatarImage.src = result.avatar_url + '?t=' + new Date().getTime();
          } else if (avatarPlaceholder) {
            const newImage = document.createElement('img');
            newImage.id = 'avatarImage';
            newImage.className = 'avatar-img';
            newImage.src = result.avatar_url + '?t=' + new Date().getTime();
            newImage.alt = 'Аватар';

            avatarPlaceholder.parentNode.replaceChild(newImage, avatarPlaceholder);
          }

          showMessage('Аватар успешно обновлен', 'success');
        } else {
          showMessage(result.message, 'error');
        }
      } catch (error) {
        showMessage('Ошибка загрузки файла', 'error');
        console.error('Ошибка:', error);
      }
      event.target.value = '';
    }

    // обнова профиля
    async function updateProfile(event) {
      event.preventDefault();

      const form = event.target;
      const formData = new FormData(form);
      formData.append('action', 'update_profile');

      // фетчим данные через апи если обновили
      try {
        const response = await fetch('../api/trainer/trainer-profile.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          // обнова имени 
          const profileName = document.getElementById('profileName');
          if (profileName && result.profile) {
            profileName.textContent = result.profile.first_name + ' ' + result.profile.last_name;
          }

          // обнова специализации
          const specializationElement = document.querySelector('.account-info:nth-child(3)');
          if (specializationElement && result.profile.specialization) {
            specializationElement.innerHTML = `<i class="fas fa-dumbbell"></i> ${result.profile.specialization}`;
          } else if (!result.profile.specialization && specializationElement) {
            specializationElement.remove();
          }

          showMessage(result.message, 'success');

          // обнова даты последней обновы
          if (result.profile && result.profile.updated_at) {
            const updateDate = new Date(result.profile.updated_at);
            const dateString = updateDate.toLocaleDateString('ru-RU') + ' ' +
              updateDate.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });

            const updateField = document.querySelector('input[value*="Последнее обновление"]');
            if (updateField) {
              updateField.value = dateString;
            }
          }
        } else {
          showMessage(result.message, 'error');
        }
      } catch (error) {
        showMessage('Ошибка сети. Попробуйте позже.', 'error');
        console.error('Ошибка:', error);
      }
    }

    // сброс личных данных
    function resetPersonalForm() {
      if (confirm('Отменить изменения и вернуть исходные значения?')) {
        document.getElementById('personalForm').reset();
      }
    }

    // инициализация при загрузке 
    document.addEventListener('DOMContentLoaded', function () {
      const maxLengthFields = {
        'first_name': 50,
        'last_name': 50,
        'middle_name': 50,
        'phone': 20,
        'specialization': 100
      };

      Object.keys(maxLengthFields).forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
          field.maxLength = maxLengthFields[fieldId];
        }
      });

      // счетчики символов для текстовых полей
      const textFields = ['description'];
      textFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
          const counter = document.createElement('small');
          counter.className = 'label-hint';
          counter.style.display = 'block';
          counter.style.textAlign = 'right';
          counter.style.marginTop = '5px';
          counter.textContent = `${field.value.length}/1000`;

          field.parentNode.appendChild(counter);

          field.addEventListener('input', function () {
            counter.textContent = `${this.value.length}/1000`;
          });
        }
      });
    });
  </script>
</body>

</html>