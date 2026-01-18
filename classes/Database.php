<?php

class Database
{
  private $connection;

  public function __construct()
  {
    require_once dirname(__DIR__) . '/config.php';

    // Инициализируем JWT если класс существует
    if (class_exists('JWT')) {
      JWT::init(JWT_SECRET);
    }

    $this->connection = connectionDB();
  }

  public function getConnection()
  {
    return $this->connection;
  }

  // Безопасное выполнение запроса
  public function executeQuery($sql, $params = [])
  {
    $stmt = $this->connection->prepare($sql);

    if (!$stmt) {
      throw new Exception("Ошибка подготовки запроса: " . $this->connection->error);
    }

    if (!empty($params)) {
      $types = '';
      foreach ($params as $param) {
        if (is_int($param)) {
          $types .= 'i';
        } elseif (is_float($param)) {
          $types .= 'd';
        } else {
          $types .= 's';
        }
      }

      $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
      throw new Exception("Ошибка выполнения запроса: " . $stmt->error);
    }

    return $stmt;
  }

  // Получение одной записи
  public function fetchOne($sql, $params = [])
  {
    $stmt = $this->executeQuery($sql, $params);
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      return null;
    }

    return $result->fetch_assoc();
  }

  // Получение всех записей
  public function fetchAll($sql, $params = [])
  {
    $stmt = $this->executeQuery($sql, $params);
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
      $rows[] = $row;
    }

    return $rows;
  }

  // Вставка записи
  public function insert($table, $data)
  {
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    $values = array_values($data);

    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    $stmt = $this->executeQuery($sql, $values);

    return $this->connection->insert_id;
  }

  // Обновление записи
  public function update($table, $data, $where, $whereParams = [])
  {
    $setParts = [];
    $values = [];

    foreach ($data as $column => $value) {
      $setParts[] = "$column = ?";
      $values[] = $value;
    }

    $setClause = implode(', ', $setParts);
    $values = array_merge($values, $whereParams);

    $sql = "UPDATE $table SET $setClause WHERE $where";
    $stmt = $this->executeQuery($sql, $values);

    return $stmt->affected_rows;
  }

  // Удаление записи
  public function delete($table, $where, $params = [])
  {
    $sql = "DELETE FROM $table WHERE $where";
    $stmt = $this->executeQuery($sql, $params);

    return $stmt->affected_rows;
  }

  public function beginTransaction()
  {
    $this->connection->begin_transaction();
  }

  public function commit()
  {
    $this->connection->commit();
  }

  public function rollback()
  {
    $this->connection->rollback();
  }
}
?>