<?php
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');
ob_start();

$response = [
  'success' => false,
  'message' => 'Неизвестная ошибка',
  'data' => null
];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Только POST метод разрешен');
  }

  $rawInput = file_get_contents('php://input');

  if (empty($rawInput)) {
    throw new Exception('Нет данных в запросе');
  }

  $input = json_decode($rawInput, true);

  if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception('Некорректный JSON: ' . json_last_error_msg());
  }

  $required = ['email', 'password', 'first_name', 'last_name'];

  foreach ($required as $field) {
    if (empty($input[$field])) {
      throw new Exception("Поле '$field' обязательно");
    }
  }

  if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    throw new Exception('Некорректный email');
  }

  if (strlen($input['password']) < 6) {
    throw new Exception('Пароль должен быть не менее 6 символов');
  }

  if ($input['password'] !== $input['password_confirm']) {
    throw new Exception('Пароли не совпадают');
  }

  $user = new User();
  $result = $user->register([
    'email' => trim($input['email']),
    'password' => $input['password'],
    'first_name' => trim($input['first_name']),
    'last_name' => trim($input['last_name']),
    'phone' => isset($input['phone']) ? trim($input['phone']) : null,
    'role' => 'client'
  ]);

  $response = [
    'success' => true,
    'message' => 'Регистрация успешна',
    'data' => $result
  ];

} catch (Exception $e) {
  $response = [
    'success' => false,
    'message' => $e->getMessage()
  ];
  http_response_code(400);
  error_log('Register error: ' . $e->getMessage());
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>