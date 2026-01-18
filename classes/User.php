<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/JWT.php';

class User
{
  private $db;

  public function __construct()
  {
    $this->db = new Database();
  }

  public function getByEmail($email)
  {
    return $this->db->fetchOne(
      "SELECT id, email, role, first_name, last_name, phone, avatar_url, description, specialization, created_at
         FROM users
         WHERE email = ?",
      [$email]
    );
  }

  public function getCurrentUser()
  {
    $token = null;

    if (isset($_GET['token'])) {
      $token = $_GET['token'];
    }

    if (!$token && isset($_COOKIE['fitness_token'])) {
      $token = $_COOKIE['fitness_token'];
    }

    if (!$token) {
      $headers = getallheaders();
      if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        if (strpos($authHeader, 'Bearer ') === 0) {
          $token = substr($authHeader, 7);
        }
      }
    }

    if (!$token) {
      return null;
    }

    try {
      $decoded = JWT::decode($token);
      if (!$decoded || !isset($decoded['user_id'])) {
        return null;
      }

      return $this->getById($decoded['user_id']);
    } catch (Exception $e) {
      error_log("Invalid token: " . $e->getMessage());
      return null;
    }
  }
  public function register($data)
  {
    $required = ['email', 'password', 'first_name', 'last_name'];
    foreach ($required as $field) {
      if (empty($data[$field])) {
        throw new Exception("Поле '$field' обязательно для заполнения");
      }
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
      throw new Exception("Некорректный email адрес");
    }
    $existing = $this->db->fetchOne("SELECT id FROM users WHERE email = ?", [$data['email']]);
    if ($existing) {
      throw new Exception("Пользователь с таким email уже существует");
    }
    $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);
    $role = $data['role'] ?? 'client';
    $allowed_roles = ['client', 'trainer', 'admin'];
    if (!in_array($role, $allowed_roles)) {
      $role = 'client';
    }
    $userData = [
      'email' => trim($data['email']),
      'password_hash' => $password_hash,
      'role' => $role,
      'first_name' => trim($data['first_name']),
      'last_name' => trim($data['last_name']),
      'phone' => isset($data['phone']) ? trim($data['phone']) : null
    ];
    $user_id = $this->db->insert('users', $userData);
    $token = $this->generateToken($user_id, $data['email'], $role);
    $user = $this->getById($user_id);
    return [
      'user' => $user,
      'token' => $token
    ];
  }

  // Авторизация пользователя
  public function login($email, $password)
  {
    // Находим пользователя по email
    $user = $this->db->fetchOne(
      "SELECT * FROM users WHERE email = ?",
      [$email]
    );

    if (!$user) {
      throw new Exception("Пользователь не найден");
    }

    // Проверяем пароль
    if (!password_verify($password, $user['password_hash'])) {
      throw new Exception("Неверный пароль");
    }

    // Проверяем активность
    if (isset($user['active']) && !$user['active']) {
      throw new Exception("Пользователь заблокирован");
    }

    // Генерируем новый токен
    $token = $this->generateToken($user['id'], $user['email'], $user['role']);

    return [
      'user' => $this->getById($user['id']),
      'token' => $token
    ];
  }

  // Генерация JWT токена
  private function generateToken($user_id, $email, $role)
  {
    return JWT::encode([
      'user_id' => $user_id,
      'email' => $email,
      'role' => $role
    ]);
  }

  // Получение пользователя по ID (без пароля)
  public function getById($id)
  {
    $user = $this->db->fetchOne(
      "SELECT id, email, role, first_name, last_name, 
                    phone, avatar_url, description, 
                    specialization, created_at 
             FROM users 
             WHERE id = ?",
      [$id]
    );

    if (!$user) {
      throw new Exception("Пользователь не найден");
    }

    return $user;
  }
}
?>