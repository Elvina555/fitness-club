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
    case 'approve':
      approveReview($db, $currentUser['id']);
      break;
    case 'reject':
      rejectReview($db, $currentUser['id']);
      break;
    case 'edit':
      editReview($db, $currentUser['id']);
      break;
    case 'delete':
      deleteReview($db);
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

function approveReview($db, $adminId)
{
  if (empty($_POST['review_id'])) {
    throw new Exception("ID отзыва не указан");
  }
  $reviewId = intval($_POST['review_id']);
  $review = $db->fetchOne("SELECT * FROM reviews WHERE id = ?", [$reviewId]);
  if (!$review) {
    throw new Exception("Отзыв не найден");
  }

  $updateData = [
    'moderation_status' => 'approved',
    'moderated_by' => $adminId,
    'moderated_at' => date('Y-m-d H:i:s'),
    'moderation_comment' => null
  ];

  $affected = $db->update('reviews', $updateData, 'id = ?', [$reviewId]);

  if ($affected > 0) {
    createNotification(
      $db,
      $review['client_id'],
      'review_status',
      'Отзыв одобрен',
      'Ваш отзыв на тренировку был одобрен и опубликован. Спасибо за обратную связь!',
      $reviewId
    );

    echo json_encode([
      'success' => true,
      'message' => 'Отзыв успешно одобрен'
    ]);
  } else {
    throw new Exception("Ошибка при обновлении отзыва");
  }
}

function rejectReview($db, $adminId)
{
  if (empty($_POST['review_id'])) {
    throw new Exception("ID отзыва не указан");
  }

  if (empty($_POST['moderation_comment'])) {
    throw new Exception("Укажите причину отклонения");
  }

  $reviewId = intval($_POST['review_id']);
  $moderationComment = trim($_POST['moderation_comment']);

  $review = $db->fetchOne("SELECT * FROM reviews WHERE id = ?", [$reviewId]);
  if (!$review) {
    throw new Exception("Отзыв не найден");
  }

  $updateData = [
    'moderation_status' => 'rejected',
    'moderated_by' => $adminId,
    'moderated_at' => date('Y-m-d H:i:s'),
    'moderation_comment' => $moderationComment
  ];

  $affected = $db->update('reviews', $updateData, 'id = ?', [$reviewId]);

  if ($affected > 0) {
    createNotification(
      $db,
      $review['client_id'],
      'review_status',
      'Отзыв отклонен',
      'Ваш отзыв на тренировку был отклонен. Причина: ' . $moderationComment,
      $reviewId
    );

    echo json_encode([
      'success' => true,
      'message' => 'Отзыв успешно отклонен'
    ]);
  } else {
    throw new Exception("Ошибка при обновлении отзыва");
  }
}

function editReview($db, $adminId)
{
  if (empty($_POST['review_id'])) {
    throw new Exception("ID отзыва не указан");
  }

  $required = ['rating', 'comment', 'moderation_status'];
  foreach ($required as $field) {
    if (empty($_POST[$field])) {
      throw new Exception("Поле '$field' обязательно для заполнения");
    }
  }

  $reviewId = intval($_POST['review_id']);
  $rating = intval($_POST['rating']);
  $comment = trim($_POST['comment']);
  $moderationStatus = $_POST['moderation_status'];
  $moderationComment = !empty($_POST['moderation_comment']) ? trim($_POST['moderation_comment']) : null;

  if ($rating < 1 || $rating > 5) {
    throw new Exception("Рейтинг должен быть от 1 до 5");
  }

  $review = $db->fetchOne("SELECT * FROM reviews WHERE id = ?", [$reviewId]);
  if (!$review) {
    throw new Exception("Отзыв не найден");
  }

  $updateData = [
    'rating' => $rating,
    'comment' => $comment,
    'moderation_status' => $moderationStatus,
    'moderation_comment' => $moderationComment
  ];
  if ($review['moderation_status'] !== $moderationStatus) {
    $updateData['moderated_by'] = $adminId;
    $updateData['moderated_at'] = date('Y-m-d H:i:s');
  }

  $affected = $db->update('reviews', $updateData, 'id = ?', [$reviewId]);

  if ($affected > 0) {
    if ($review['moderation_status'] !== $moderationStatus) {
      $notificationType = ($moderationStatus === 'approved') ?
        'Отзыв одобрен' : 'Отзыв отклонен';
      $notificationMessage = ($moderationStatus === 'approved') ?
        'Ваш отзыв на тренировку был одобрен и опубликован.' :
        'Ваш отзыв на тренировку был отклонен.' .
        ($moderationComment ? ' Причина: ' . $moderationComment : '');

      createNotification(
        $db,
        $review['client_id'],
        'review_status',
        $notificationType,
        $notificationMessage,
        $reviewId
      );
    }

    echo json_encode([
      'success' => true,
      'message' => 'Отзыв успешно обновлен'
    ]);
  } else {
    throw new Exception("Ошибка при обновлении отзыва");
  }
}

function deleteReview($db)
{
  if (empty($_POST['review_id'])) {
    throw new Exception("ID отзыва не указан");
  }

  $reviewId = intval($_POST['review_id']);
  $review = $db->fetchOne("SELECT id FROM reviews WHERE id = ?", [$reviewId]);
  if (!$review) {
    throw new Exception("Отзыв не найден");
  }

  $affected = $db->delete('reviews', 'id = ?', [$reviewId]);

  if ($affected > 0) {
    echo json_encode([
      'success' => true,
      'message' => 'Отзыв успешно удален'
    ]);
  } else {
    throw new Exception("Ошибка при удалении отзыва");
  }
}

function createNotification($db, $userId, $type, $title, $message, $relatedId = null)
{
  $notificationData = [
    'user_id' => $userId,
    'type' => $type,
    'title' => $title,
    'message' => $message,
    'is_read' => 0,
    'related_id' => $relatedId,
    'created_at' => date('Y-m-d H:i:s')
  ];

  $db->insert('notifications', $notificationData);
}
?>