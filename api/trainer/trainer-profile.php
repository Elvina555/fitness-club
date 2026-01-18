<?php
require_once '../../config.php';
header('Content-Type: application/json; charset=utf-8');
ob_start();

$response = ['success' => false, 'message' => 'Неизвестная ошибка'];

try {
  $userModel = new User();
  $db = new Database();

  $currentUser = $userModel->getCurrentUser();
  if (!$currentUser || $currentUser['role'] !== 'trainer') {
    throw new Exception('Доступ запрещен');
  }

  $trainerId = $currentUser['id'];

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $profile = $db->fetchOne(
      "SELECT id, email, role, first_name, last_name, middle_name, 
                    phone, avatar_url, description, specialization, 
                    created_at, updated_at, active
             FROM users 
             WHERE id = ?",
      [$trainerId]
    );

    if (!$profile) {
      throw new Exception('Профиль не найден');
    }

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
                 WHERE w.trainer_id = ? AND r.moderation_status = 'approved') as avg_rating",
      [$trainerId, $trainerId, $trainerId, $trainerId]
    );

    $response = [
      'success' => true,
      'profile' => $profile,
      'stats' => $stats
    ];

  } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
      $updateData = [];
      $editableFields = ['first_name', 'last_name', 'middle_name', 'phone', 'description', 'specialization'];

      foreach ($editableFields as $field) {
        if (isset($_POST[$field])) {
          $updateData[$field] = trim($_POST[$field]);

          if (in_array($field, ['first_name', 'last_name']) && empty($updateData[$field])) {
            throw new Exception("Поле '$field' обязательно для заполнения");
          }

          if ($field === 'phone' && !empty($updateData[$field])) {
            if (!preg_match('/^[\d\s\-\+\(\)]+$/', $updateData[$field])) {
              throw new Exception('Некорректный формат телефона');
            }
          }
        }
      }

      if (empty($updateData)) {
        throw new Exception('Нет данных для обновления');
      }
      $updateData['updated_at'] = date('Y-m-d H:i:s');
      $affected = $db->update('users', $updateData, 'id = ?', [$trainerId]);
      if ($affected > 0) {
        $updatedProfile = $db->fetchOne(
          "SELECT id, email, role, first_name, last_name, middle_name, 
                            phone, avatar_url, description, specialization, 
                            created_at, updated_at, active
                     FROM users 
                     WHERE id = ?",
          [$trainerId]
        );

        $response = [
          'success' => true,
          'message' => 'Профиль успешно обновлен',
          'profile' => $updatedProfile
        ];
      } else {
        $response = [
          'success' => true,
          'message' => 'Изменений не внесено',
          'profile' => null
        ];
      }

    } elseif ($action === 'change_password') {
      $required = ['current_password', 'new_password', 'confirm_password'];
      foreach ($required as $field) {
        if (empty($_POST[$field])) {
          throw new Exception("Поле '$field' обязательно");
        }
      }

      $currentPassword = $_POST['current_password'];
      $newPassword = $_POST['new_password'];
      $confirmPassword = $_POST['confirm_password'];

      if ($newPassword !== $confirmPassword) {
        throw new Exception('Новые пароли не совпадают');
      }

      if (strlen($newPassword) < 6) {
        throw new Exception('Новый пароль должен быть не менее 6 символов');
      }

      $user = $db->fetchOne(
        "SELECT password_hash FROM users WHERE id = ?",
        [$trainerId]
      );

      if (!$user) {
        throw new Exception('Пользователь не найден');
      }
      if (!password_verify($currentPassword, $user['password_hash'])) {
        throw new Exception('Текущий пароль неверен');
      }
      $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);

      $affected = $db->update(
        'users',
        [
          'password_hash' => $newPasswordHash,
          'updated_at' => date('Y-m-d H:i:s')
        ],
        'id = ?',
        [$trainerId]
      );

      if ($affected > 0) {
        $response = [
          'success' => true,
          'message' => 'Пароль успешно изменен'
        ];
      } else {
        throw new Exception('Ошибка при изменении пароля');
      }

    } elseif ($action === 'upload_avatar') {
      if (empty($_FILES['avatar'])) {
        throw new Exception('Файл не загружен');
      }

      $avatarFile = $_FILES['avatar'];

      if ($avatarFile['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Ошибка загрузки файла. Код ошибки: ' . $avatarFile['error']);
      }

      $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
      $fileType = mime_content_type($avatarFile['tmp_name']);

      if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Допустимые форматы: JPEG, PNG, GIF, WebP. Ваш файл: ' . $fileType);
      }

      $maxSize = 5 * 1024 * 1024; // 5MB
      if ($avatarFile['size'] > $maxSize) {
        throw new Exception('Максимальный размер файла: 5MB. Ваш файл: ' . round($avatarFile['size'] / 1024 / 1024, 2) . 'MB');
      }

      $fileExtension = strtolower(pathinfo($avatarFile['name'], PATHINFO_EXTENSION));
      $fileName = 'avatar_' . $trainerId . '_' . time() . '.' . $fileExtension;

      // определение абсолютного пути к корню проекта
      $projectRoot = dirname(__DIR__, 2); // выход на 2 уровня вверх от api/trainer/

      // путь для сохранения файла - абсолютный
      $uploadDir = $projectRoot . '/uploads/avatars/';

      // проверяем а потом создаем директорию (если нет)
      if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
          throw new Exception('Не удалось создать директорию для загрузки: ' . $uploadDir);
        }
      }

      // проверка права на запись
      if (!is_writable($uploadDir)) {
        throw new Exception('Нет прав на запись в директорию: ' . $uploadDir);
      }

      $filePath = $uploadDir . $fileName;

      // пробуем сохранить
      if (!move_uploaded_file($avatarFile['tmp_name'], $filePath)) {
        throw new Exception('Ошибка при сохранении файла. Проверьте права доступа.');
      }

      // проверка что файл сохранился
      if (!file_exists($filePath)) {
        throw new Exception('Файл не был сохранен на сервере');
      }

      // URL для базы данных (относительный путь от корня сайта)
      $avatarUrl = '/uploads/avatars/' . $fileName;

      // удаляем старый аватар если существует
      $oldAvatar = $db->fetchOne(
        "SELECT avatar_url FROM users WHERE id = ?",
        [$trainerId]
      );

      if ($oldAvatar && $oldAvatar['avatar_url'] && file_exists($projectRoot . $oldAvatar['avatar_url'])) {
        @unlink($projectRoot . $oldAvatar['avatar_url']);
      }

      $affected = $db->update(
        'users',
        [
          'avatar_url' => $avatarUrl,
          'updated_at' => date('Y-m-d H:i:s')
        ],
        'id = ?',
        [$trainerId]
      );

      if ($affected > 0) {
        $response = [
          'success' => true,
          'message' => 'Аватар успешно загружен',
          'avatar_url' => $avatarUrl
        ];
      } else {
        // если не удалось обновить БД удаляем файл
        @unlink($filePath);
        throw new Exception('Ошибка при обновлении аватара в базе данных');
      }

    } else {
      throw new Exception('Неизвестное действие');
    }
  }

} catch (Exception $e) {
  $response = [
    'success' => false,
    'message' => $e->getMessage()
  ];
  http_response_code(400);
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>