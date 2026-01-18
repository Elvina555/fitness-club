<?php
require_once '../../classes/Database.php';
require_once '../../classes/User.php';

header('Content-Type: application/json; charset=UTF-8');

$usr = new User();
$method = $_SERVER['REQUEST_METHOD'];

try {
  $currentUser = $usr->getCurrentUser();
} catch (Exception $e) {
  http_response_code(401);
  echo json_encode(['error' => 'Не авторизирован']);
  exit;
}

if (!$currentUser) {
  http_response_code(401);
  echo json_encode(['error' => 'Не авторизирован']);
  exit;
}

if ($currentUser['role'] !== 'client') {
  http_response_code(403);
  echo json_encode(['error' => 'Доступ запрещен']);
  exit;
}

$db = new Database();

// получаем уведомления
try {
  if ($method === 'GET') {
    $notifications = $db->fetchAll(
      "SELECT * FROM notifications
             WHERE user_id = ?
             ORDER BY is_read ASC, created_at DESC
             LIMIT 100",
      [$currentUser['id']]
    );

    echo json_encode([
      'success' => true,
      'notifications' => $notifications
    ]);
    exit;
    // в случае если мы хотим все уведомления отмеченными сделать 
  } elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['mark_all_read']) && $data['mark_all_read'] === true) {
      $db->update(
        'notifications',
        ['is_read' => 1],
        'user_id = ? AND is_read = 0',
        [$currentUser['id']]
      );

      echo json_encode([
        'success' => true,
        'message' => 'Все уведомления отмечены как прочитанные'
      ]);
      exit;
      // если хотим отметить ток одно
    } elseif (!empty($data['notification_id']) && isset($data['mark_read'])) {
      $notification = $db->fetchOne(
        "SELECT * FROM notifications 
                 WHERE id = ? AND user_id = ?",
        [$data['notification_id'], $currentUser['id']]
      );

      if (!$notification) {
        http_response_code(404);
        echo json_encode(['error' => 'Уведомление не найдено']);
        exit;
      }

      $db->update(
        'notifications',
        ['is_read' => 1],
        'id = ?',
        [$data['notification_id']]
      );

      echo json_encode([
        'success' => true,
        'message' => 'Уведомление отмечено как прочитанное'
      ]);
      exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Неверные параметры запроса']);
    exit;
    // если хотим удалить уведомление
  } elseif ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['delete_all_read']) && $data['delete_all_read'] === true) {
      $db->delete(
        'notifications',
        'user_id = ? AND is_read = 1',
        [$currentUser['id']]
      );

      echo json_encode([
        'success' => true,
        'message' => 'Все прочитанные уведомления удалены'
      ]);
      exit;
      // если уведомление пусто
    } elseif (!empty($data['notification_id'])) {
      $notification = $db->fetchOne(
        "SELECT * FROM notifications 
                 WHERE id = ? AND user_id = ?",
        [$data['notification_id'], $currentUser['id']]
      );

      if (!$notification) {
        http_response_code(404);
        echo json_encode(['error' => 'Уведомление не найдено']);
        exit;
      }

      $db->delete('notifications', 'id = ?', [$data['notification_id']]);

      echo json_encode([
        'success' => true,
        'message' => 'Уведомление удалено'
      ]);
      exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Неверные параметры запроса']);
    exit;

  } else {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не разрешен']);
    exit;
  }

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
  exit;
}