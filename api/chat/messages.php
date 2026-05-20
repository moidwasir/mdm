<?php
/**
 * Messages API — list and send
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Id');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$userId = (int)($_SERVER['HTTP_X_USER_ID'] ?? $_GET['user_id'] ?? 0);
if (!$userId) jsonResponse(['success' => false, 'message' => 'User ID required'], 401);

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ---- GET: Fetch messages ----
if ($method === 'GET') {
    $convId  = (int)($_GET['conversation_id'] ?? 0);
    $before  = (int)($_GET['before_id'] ?? PHP_INT_MAX);
    $limit   = min((int)($_GET['limit'] ?? MESSAGES_PER_PAGE), 100);

    if (!$convId) jsonResponse(['success' => false, 'message' => 'conversation_id required'], 400);

    // Verify membership
    $mem = $db->prepare("SELECT id FROM conversation_members WHERE conversation_id = ? AND user_id = ?");
    $mem->execute([$convId, $userId]);
    if (!$mem->fetch()) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);

    $stmt = $db->prepare("
        SELECT m.id, m.conversation_id, m.sender_id, m.content, m.message_type, m.media_url, m.reply_to_id, m.status, m.created_at,
               u.display_name as sender_name, u.avatar as sender_avatar,
               rm.content as reply_content
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        LEFT JOIN messages rm ON rm.id = m.reply_to_id
        WHERE m.conversation_id = ? AND m.id < ? AND m.is_deleted = 0
        ORDER BY m.id DESC
        LIMIT ?
    ");
    $stmt->execute([$convId, $before, $limit]);
    $messages = array_reverse($stmt->fetchAll());

    // Mark as delivered
    $db->prepare("UPDATE messages SET status = 'delivered' WHERE conversation_id = ? AND sender_id != ? AND status = 'sent'")->execute([$convId, $userId]);

    // Update last read
    if (!empty($messages)) {
        $lastId = end($messages)['id'];
        $db->prepare("UPDATE conversation_members SET last_read_message_id = ? WHERE conversation_id = ? AND user_id = ?")->execute([$lastId, $convId, $userId]);
    }

    jsonResponse(['success' => true, 'messages' => $messages, 'has_more' => count($messages) === $limit]);
}

// ---- POST: Send message ----
if ($method === 'POST') {
    $input  = json_decode(file_get_contents('php://input'), true);
    $convId = (int)($input['conversation_id'] ?? 0);
    $content= trim($input['content'] ?? '');
    $type   = $input['message_type'] ?? 'text';
    $replyTo= (int)($input['reply_to_id'] ?? 0) ?: null;
    $mediaUrl = $input['media_url'] ?? null;

    if (!$convId) jsonResponse(['success' => false, 'message' => 'conversation_id required'], 400);
    if ($type === 'text' && !$content) jsonResponse(['success' => false, 'message' => 'Message content required'], 400);
    if (mb_strlen($content) > MAX_MESSAGE_LENGTH) jsonResponse(['success' => false, 'message' => 'Message too long'], 400);

    // Verify membership
    $mem = $db->prepare("SELECT id FROM conversation_members WHERE conversation_id = ? AND user_id = ?");
    $mem->execute([$convId, $userId]);
    if (!$mem->fetch()) jsonResponse(['success' => false, 'message' => 'Access denied'], 403);

    $stmt = $db->prepare("INSERT INTO messages (conversation_id, sender_id, content, message_type, media_url, reply_to_id) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$convId, $userId, $content, $type, $mediaUrl, $replyTo]);
    $msgId = (int)$db->lastInsertId();

    // Update conversation timestamp
    $db->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$convId]);

    // Fetch the full message to return
    $msg = $db->prepare("SELECT m.*, u.display_name as sender_name, u.avatar as sender_avatar FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.id = ?");
    $msg->execute([$msgId]);
    $message = $msg->fetch();

    jsonResponse(['success' => true, 'message' => $message]);
}

jsonResponse(['success' => false, 'message' => 'Bad request'], 400);
