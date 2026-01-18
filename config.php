<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DB_HOST', 'MySQL-8.0');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'fitness-club');
define('JWT_SECRET', 'fitness_club_secret_key_2024_change_this');

function connectionDB()
{
  $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  if (!$conn)
    die('Database error');
  mysqli_set_charset($conn, 'utf8mb4');
  return $conn;
}

spl_autoload_register(function ($class_name) {
  require_once __DIR__ . '/classes/' . $class_name . '.php';
});
?>