<?php
require_once '../../classes/Database.php';
require_once '../../classes/User.php';
require_once '../../classes/JWT.php';

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

if ($currentUser['role'] !== 'client') {
  http_response_code(403);
  echo json_encode(['error' => 'Доступ запрещен']);
  exit;
}

$db = new Database();

try {
  if ($method === 'GET') {
    $subscriptions = $db->fetchAll(
      "SELECT * FROM subscriptions
         WHERE client_id = ?
         ORDER BY created_at DESC",
      [$currentUser['id']]
    );

    $result = [];
    foreach ($subscriptions as $sub) {
      $sub['days_left'] = ceil((strtotime($sub['end_date']) - time()) / (24 * 60 * 60));
      $result[] = $sub;
    }

    // Активный абонемент - только со статусом 'active'
    $active = $db->fetchOne(
      "SELECT * FROM subscriptions
         WHERE client_id = ? AND status = 'active'
         LIMIT 1",
      [$currentUser['id']]
    );

    echo json_encode([
      'success' => true,
      'subscriptions' => $result,
      'active_subscription' => $active
    ]);
    exit;
  } elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    // Проверяем валидность типа абонемента
    $validTypes = ['month', '3months', '6months', 'year'];
    if (empty($data['type']) || !in_array($data['type'], $validTypes)) {
      http_response_code(400);
      echo json_encode(['error' => 'Недействительный тип абонемента']);
      exit;
    }

    // Начинаем транзакцию для атомарности
    $db->getConnection()->begin_transaction();

    try {
      // 1. Находим все активные абонементы пользователя
      $activeSubscriptions = $db->fetchAll(
        "SELECT * FROM subscriptions 
             WHERE client_id = ? AND status = 'active'",
        [$currentUser['id']]
      );

      // 2. Обновляем статус всех активных абонементов на 'expired'
      if (!empty($activeSubscriptions)) {
        $db->executeQuery(
          "UPDATE subscriptions 
                 SET status = 'expired' 
                 WHERE client_id = ? AND status = 'active'",
          [$currentUser['id']]
        );
      }

      // 3. Создаем новый абонемент
      $subscriptionParams = [
        'month' => ['days' => 30, 'visits' => 4, 'price' => 99, 'name' => 'На месяц'],
        '3months' => ['days' => 90, 'visits' => 12, 'price' => 249, 'name' => 'На 3 месяца'],
        '6months' => ['days' => 180, 'visits' => 24, 'price' => 449, 'name' => 'На 6 месяцев'],
        'year' => ['days' => 365, 'visits' => 52, 'price' => 799, 'name' => 'На год'],
      ];

      $params = $subscriptionParams[$data['type']];
      $startDate = date('Y-m-d');
      $endDate = date('Y-m-d', strtotime("+{$params['days']} days"));

      $subscription_id = $db->insert('subscriptions', [
        'client_id' => $currentUser['id'],
        'type' => $data['type'],
        'visits_total' => $params['visits'],
        'visits_left' => $params['visits'],
        'start_date' => $startDate,
        'end_date' => $endDate,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
      ]);

      // Фиксируем транзакцию
      $db->getConnection()->commit();

      echo json_encode([
        'success' => true,
        'subscription_id' => $subscription_id,
        'type' => $data['type'],
        'subscription_name' => $params['name'],
        'start_date' => $startDate,
        'end_date' => $endDate,
        'visits_total' => $params['visits'],
        'message' => 'Абонемент успешно активирован!'
      ]);
      exit;

    } catch (Exception $e) {
      // Откатываем транзакцию в случае ошибки
      $db->getConnection()->rollback();
      throw $e;
    }
  } elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['subscription_id']) || empty($data['status'])) {
      http_response_code(400);
      echo json_encode(['error' => 'Не указаны обязательные параметры']);
      exit;
    }

    $subscription = $db->fetchOne(
      "SELECT * FROM subscriptions WHERE id = ? AND client_id = ?",
      [$data['subscription_id'], $currentUser['id']]
    );

    if (!$subscription) {
      http_response_code(404);
      echo json_encode(['error' => 'Абонемент не найден']);
      exit;
    }

    $db->update('subscriptions', ['status' => $data['status']], 'id = ?', [$data['subscription_id']]);

    echo json_encode([
      'success' => true,
      'message' => 'Абонемент обновлен'
    ]);
    exit;
  } elseif ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['subscription_id'])) {
      http_response_code(400);
      echo json_encode(['error' => 'Не указан subscription_id']);
      exit;
    }

    $subscription = $db->fetchOne(
      "SELECT * FROM subscriptions WHERE id = ? AND client_id = ?",
      [$data['subscription_id'], $currentUser['id']]
    );

    if (!$subscription) {
      http_response_code(404);
      echo json_encode(['error' => 'Абонемент не найден']);
      exit;
    }

    $db->update('subscriptions', ['status' => 'cancelled'], 'id = ?', [$data['subscription_id']]);

    echo json_encode([
      'success' => true,
      'message' => 'Абонемент отменен'
    ]);
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
