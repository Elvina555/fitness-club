<?php
ob_start();

try {
  require_once '../config.php'; // подключаем конфиг

  $input = json_decode(file_get_contents('php://input'), true);

  if (empty($input)) {
    $input = $_POST;
  }

  if (empty($input['email'])) {
    throw new Exception("Email обязателен");
  }

  if (empty($input['password'])) {
    throw new Exception("Пароль обязателен");
  }

  $user = new User();
  $result = $user->login($input['email'], $input['password']); // логиним юзера через функцию класса User 
  $response = [
    'success' => true,
    'message' => 'Авторизация успешна',
    'data' => $result
  ];

  // обработка ошибок
} catch (Exception $e) {
  $response = [
    'success' => false,
    'message' => $e->getMessage()
  ];
  http_response_code(401);
}

ob_end_clean();

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>