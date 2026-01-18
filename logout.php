<?php
setcookie('fitness_token', '', [
  'expires' => time() - 3600,
  'path' => '/',
  'secure' => false,
  'httponly' => true,
  'samesite' => 'Lax'
]);

session_start();
session_destroy();

header('Location: login.html');
exit;
?>