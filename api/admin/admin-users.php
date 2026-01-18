<?php
require_once '../../classes/User.php';
require_once '../../classes/Database.php';

header('Content-Type: application/json');

$userModel = new User();
$db = new Database();

$currentUser = $userModel->getCurrentUser();
if (!$currentUser || $currentUser['role'] !== 'admin') {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Недостаточно прав']);
  exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
  switch ($action) {
    case 'add':
      addUser($db);
      break;
    case 'edit':
      editUser($db);
      break;
    case 'delete':
      deleteUser($db);
      break;
    default:
      http_response_code(400);
      echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
      exit;
  }
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  exit;
}

function addUser($db)
{
  $required = ['email', 'password', 'first_name', 'last_name', 'role'];
  foreach ($required as $field) {
    if (empty($_POST[$field])) {
      throw new Exception("Поле '$field' обязательно для заполнения");
    }
  }

  if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    throw new Exception("Некорректный email адрес");
  }

  $existing = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$_POST['email']]);
  if ($existing) {
    throw new Exception("Пользователь с таким email уже существует");
  }

  $password_hash = password_hash($_POST['password'], PASSWORD_BCRYPT);

  $userData = [
    'email' => trim($_POST['email']),
    'password_hash' => $password_hash,
    'role' => $_POST['role'],
    'first_name' => trim($_POST['first_name']),
    'last_name' => trim($_POST['last_name']),
    'phone' => !empty($_POST['phone']) ? trim($_POST['phone']) : null,
    'middle_name' => !empty($_POST['middle_name']) ? trim($_POST['middle_name']) : null,
    'active' => 1
  ];

  if ($_POST['role'] === 'trainer') {
    $userData['description'] = !empty($_POST['description']) ? trim($_POST['description']) : null;
    $userData['specialization'] = !empty($_POST['specialization']) ? trim($_POST['specialization']) : null;
  }

  $user_id = $db->insert('users', $userData);

  echo json_encode([
    'success' => true,
    'message' => 'Пользователь успешно добавлен',
    'user_id' => $user_id
  ]);
}

function editUser($db)
{
  if (empty($_POST['id'])) {
    throw new Exception("ID пользователя не указан");
  }

  $required = ['email', 'first_name', 'last_name', 'role'];
  foreach ($required as $field) {
    if (empty($_POST[$field])) {
      throw new Exception("Поле '$field' обязательно для заполнения");
    }
  }

  if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    throw new Exception("Некорректный email адрес");
  }

  $existing = $db->fetchOne(
    "SELECT id FROM users WHERE email = ? AND id != ?",
    [$_POST['email'], $_POST['id']]
  );
  if ($existing) {
    throw new Exception("Пользователь с таким email уже существует");
  }

  $currentUserData = $db->fetchOne("SELECT role FROM users WHERE id = ?", [$_POST['id']]);
  if (!$currentUserData) {
    throw new Exception("Пользователь не найден");
  }

  if ($currentUserData['role'] !== $_POST['role']) {
    handleRoleChange($db, $_POST['id'], $currentUserData['role'], $_POST['role']);

    if ($_POST['role'] !== 'trainer') {
      $db->executeQuery(
        "UPDATE users SET description = NULL, specialization = NULL WHERE id = ?",
        [$_POST['id']]
      );
    }
  }

  $userData = [
    'email' => trim($_POST['email']),
    'role' => $_POST['role'],
    'first_name' => trim($_POST['first_name']),
    'last_name' => trim($_POST['last_name']),
    'phone' => !empty($_POST['phone']) ? trim($_POST['phone']) : null,
    'middle_name' => !empty($_POST['middle_name']) ? trim($_POST['middle_name']) : null,
    'active' => !empty($_POST['active']) ? intval($_POST['active']) : 1
  ];

  if (!empty($_POST['password'])) {
    $userData['password_hash'] = password_hash($_POST['password'], PASSWORD_BCRYPT);
  }

  if ($_POST['role'] === 'trainer') {
    $userData['description'] = !empty($_POST['description']) ? trim($_POST['description']) : null;
    $userData['specialization'] = !empty($_POST['specialization']) ? trim($_POST['specialization']) : null;
  }

  $affected = $db->update('users', $userData, 'id = ?', [$_POST['id']]);

  if ($affected > 0) {
    echo json_encode([
      'success' => true,
      'message' => 'Пользователь успешно обновлен'
    ]);
  } else {
    throw new Exception("Пользователь не найден или данные не изменились");
  }
}

function handleRoleChange($db, $userId, $oldRole, $newRole)
{
  $dbConnection = $db->getConnection();
  $dbConnection->begin_transaction();

  try {
    if ($oldRole === 'client' && in_array($newRole, ['trainer', 'admin'])) {
      $clientBookings = $db->fetchAll(
        "SELECT workout_id FROM bookings 
         WHERE client_id = ? 
         AND status IN ('created', 'confirmed')",
        [$userId]
      );

      $db->executeQuery(
        "DELETE FROM attendance WHERE booking_id IN (SELECT id FROM bookings WHERE client_id = ?)",
        [$userId]
      );

      $db->executeQuery(
        "UPDATE bookings SET status = 'cancelled' WHERE client_id = ?",
        [$userId]
      );

      foreach ($clientBookings as $booking) {
        $db->executeQuery(
          "UPDATE workouts 
           SET current_participants = GREATEST(0, current_participants - 1)
           WHERE id = ?",
          [$booking['workout_id']]
        );
      }

      $db->executeQuery(
        "DELETE FROM reviews WHERE client_id = ?",
        [$userId]
      );

      $db->executeQuery(
        "DELETE FROM notifications WHERE user_id = ?",
        [$userId]
      );

      $db->executeQuery(
        "DELETE FROM bookings WHERE client_id = ?",
        [$userId]
      );

      $db->executeQuery(
        "DELETE FROM subscriptions WHERE client_id = ?",
        [$userId]
      );

    } elseif ($oldRole === 'trainer' && in_array($newRole, ['client', 'admin'])) {
      $trainerWorkouts = $db->fetchAll(
        "SELECT id FROM workouts WHERE trainer_id = ?",
        [$userId]
      );

      foreach ($trainerWorkouts as $workout) {
        $db->executeQuery(
          "DELETE FROM attendance WHERE booking_id IN 
           (SELECT id FROM bookings WHERE workout_id = ?)",
          [$workout['id']]
        );

        $db->executeQuery(
          "UPDATE bookings SET status = 'cancelled' WHERE workout_id = ?",
          [$workout['id']]
        );

        $db->executeQuery(
          "DELETE FROM reviews WHERE workout_id = ?",
          [$workout['id']]
        );

        $db->executeQuery(
          "DELETE FROM bookings WHERE workout_id = ?",
          [$workout['id']]
        );
      }

      $db->executeQuery(
        "DELETE FROM workouts WHERE trainer_id = ?",
        [$userId]
      );

      $db->executeQuery(
        "DELETE FROM reviews WHERE trainer_id = ?",
        [$userId]
      );

      $db->executeQuery(
        "DELETE FROM notifications WHERE user_id = ?",
        [$userId]
      );

      $db->executeQuery(
        "UPDATE users SET description = NULL, specialization = NULL WHERE id = ?",
        [$userId]
      );

    } elseif ($oldRole === 'admin' && in_array($newRole, ['client', 'trainer'])) {

      $db->executeQuery(
        "UPDATE reviews SET moderated_by = NULL WHERE moderated_by = ?",
        [$userId]
      );

      $db->executeQuery(
        "UPDATE attendance SET marked_by = NULL WHERE marked_by = ?",
        [$userId]
      );

      $db->executeQuery(
        "DELETE FROM notifications WHERE user_id = ?",
        [$userId]
      );
    }

    $dbConnection->commit();

  } catch (Exception $e) {
    $dbConnection->rollback();
    throw new Exception("Ошибка при обработке смены роли: " . $e->getMessage());
  }
}

function deleteUser($db)
{
  if (empty($_POST['id'])) {
    throw new Exception("ID пользователя не указан");
  }

  $user_id = intval($_POST['id']);
  $user = $db->fetchOne("SELECT id, role FROM users WHERE id = ?", [$user_id]);
  if (!$user) {
    throw new Exception("Пользователь не найден");
  }

  $currentUser = (new User())->getCurrentUser();
  if ($currentUser && $currentUser['id'] == $user_id) {
    throw new Exception("Нельзя удалить самого себя");
  }

  $dbConnection = $db->getConnection();
  $dbConnection->begin_transaction();

  try {
    if ($user['role'] === 'client') {
      $clientBookings = $db->fetchAll(
        "SELECT workout_id FROM bookings 
         WHERE client_id = ? 
         AND status IN ('created', 'confirmed')",
        [$user_id]
      );

      $db->executeQuery(
        "DELETE FROM attendance WHERE booking_id IN (SELECT id FROM bookings WHERE client_id = ?)",
        [$user_id]
      );

      foreach ($clientBookings as $booking) {
        $db->executeQuery(
          "UPDATE workouts 
           SET current_participants = GREATEST(0, current_participants - 1)
           WHERE id = ?",
          [$booking['workout_id']]
        );
      }

      $db->executeQuery(
        "UPDATE bookings SET status = 'cancelled' WHERE client_id = ?",
        [$user_id]
      );

      $db->executeQuery(
        "UPDATE subscriptions SET status = 'expired' WHERE client_id = ?",
        [$user_id]
      );
      $db->executeQuery("DELETE FROM reviews WHERE client_id = ?", [$user_id]);
      $db->executeQuery("DELETE FROM notifications WHERE user_id = ?", [$user_id]);
      $db->executeQuery("DELETE FROM bookings WHERE client_id = ?", [$user_id]);
      $db->executeQuery("DELETE FROM subscriptions WHERE client_id = ?", [$user_id]);

    } elseif ($user['role'] === 'trainer') {
      $trainerWorkouts = $db->fetchAll(
        "SELECT id FROM workouts WHERE trainer_id = ?",
        [$user_id]
      );

      foreach ($trainerWorkouts as $workout) {
        $db->executeQuery(
          "DELETE FROM attendance WHERE booking_id IN 
           (SELECT id FROM bookings WHERE workout_id = ?)",
          [$workout['id']]
        );

        $db->executeQuery(
          "UPDATE bookings SET status = 'cancelled' WHERE workout_id = ?",
          [$workout['id']]
        );

        $db->executeQuery(
          "DELETE FROM reviews WHERE workout_id = ?",
          [$workout['id']]
        );

        $db->executeQuery(
          "DELETE FROM bookings WHERE workout_id = ?",
          [$workout['id']]
        );
      }

      $db->executeQuery(
        "DELETE FROM workouts WHERE trainer_id = ?",
        [$user_id]
      );

      $db->executeQuery("DELETE FROM reviews WHERE trainer_id = ?", [$user_id]);

      $db->executeQuery("DELETE FROM notifications WHERE user_id = ?", [$user_id]);

    } else {
      $db->executeQuery(
        "UPDATE reviews SET moderated_by = NULL WHERE moderated_by = ?",
        [$user_id]
      );
      $db->executeQuery(
        "UPDATE attendance SET marked_by = NULL WHERE marked_by = ?",
        [$user_id]
      );
      $db->executeQuery("DELETE FROM notifications WHERE user_id = ?", [$user_id]);
    }
    $affected = $db->delete('users', 'id = ?', [$user_id]);

    if ($affected > 0) {
      $dbConnection->commit();
      echo json_encode([
        'success' => true,
        'message' => 'Пользователь успешно удален'
      ]);
    } else {
      $dbConnection->rollback();
      throw new Exception("Ошибка при удалении пользователя");
    }

  } catch (Exception $e) {
    $dbConnection->rollback();
    throw new Exception("Ошибка при удалении связанных данных: " . $e->getMessage());
  }
}
?>