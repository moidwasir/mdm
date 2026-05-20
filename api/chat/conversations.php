<?php
/**
 * Conversations API — list, create, get
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
if (!$userId) jsonResponse(['success' => false, 'message' => 'User ID required (X-User-Id header)'], 401);

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ---- GET: List conversations for user ----
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        $stmt = $db->prepare("
            SELECT c.id, c.type, c.name, c.avatar, c.updated_at,
                   cm.role as my_role,
                   (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.id > COALESCE(cm.last_read_message_id, 0)) as unread_count,
                   (SELECT content FROM messages m2 WHERE m2.conversation_id = c.id ORDER BY m2.created_at DESC LIMIT 1) as last_message,
                   (SELECT created_at FROM messages m3 WHERE m3.conversation_id = c.id ORDER BY m3.created_at DESC LIMIT 1) as last_message_at
            FROM conversations c
            JOIN conversation_members cm ON cm.conversation_id = c.id AND cm.user_id = ?
            WHERE c.is_active = 1
            ORDER BY COALESCE(last_message_at, c.created_at) DESC
        ");
        $stmt->execute([$userId]);
        jsonResponse(['success' => true, 'conversations' => $stmt->fetchAll()]);
    }

    if ($action === 'get') {
        $id   = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT c.*, cm.role as my_role FROM conversations c JOIN conversation_members cm ON cm.conversation_id = c.id AND cm.user_id = ? WHERE c.id = ?");
        $stmt->execute([$userId, $id]);
        $conv = $stmt->fetch();
        if (!$conv) jsonResponse(['success' => false, 'message' => 'Conversation not found'], 404);

        // members
        $mstmt = $db->prepare("SELECT u.id, u.username, u.display_name, u.avatar, u.last_seen, cm.role FROM conversation_members cm JOIN users u ON u.id = cm.user_id WHERE cm.conversation_id = ?");
        $mstmt->execute([$id]);
        $conv['members'] = $mstmt->fetchAll();
        jsonResponse(['success' => true, 'conversation' => $conv]);
    }
}

// ---- POST: Create conversation ----
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $type  = $input['type'] ?? 'direct'; // direct | group

    if ($type === 'direct') {
        $targetId = (int)($input['target_user_id'] ?? 0);
        if (!$targetId || $targetId === $userId) jsonResponse(['success' => false, 'message' => 'Invalid target user'], 400);

        // Check existing direct conversation
        $check = $db->prepare("
            SELECT c.id FROM conversations c
            JOIN conversation_members cm1 ON cm1.conversation_id = c.id AND cm1.user_id = ?
            JOIN conversation_members cm2 ON cm2.conversation_id = c.id AND cm2.user_id = ?
            WHERE c.type = 'direct'
            LIMIT 1
        ");
        $check->execute([$userId, $targetId]);
        $existing = $check->fetch();
        if ($existing) jsonResponse(['success' => true, 'conversation_id' => $existing['id'], 'created' => false]);

        $db->prepare("INSERT INTO conversations (type, created_by) VALUES ('direct', ?)")->execute([$userId]);
        $convId = (int)$db->lastInsertId();
        $db->prepare("INSERT INTO conversation_members (conversation_id, user_id) VALUES (?, ?), (?, ?)")->execute([$convId, $userId, $convId, $targetId]);
        jsonResponse(['success' => true, 'conversation_id' => $convId, 'created' => true]);
    }

    if ($type === 'group') {
        $name    = trim($input['name'] ?? '');
        $members = $input['members'] ?? [];
        if (!$name) jsonResponse(['success' => false, 'message' => 'Group name required'], 400);

        $db->prepare("INSERT INTO conversations (type, name, created_by) VALUES ('group', ?, ?)")->execute([$name, $userId]);
        $convId = (int)$db->lastInsertId();

        $allMembers = array_unique(array_merge([$userId], array_map('intval', $members)));
        $placeholders = implode(',', array_fill(0, count($allMembers), '(?,?)'));
        $values = [];
        foreach ($allMembers as $mid) { $values[] = $convId; $values[] = $mid; }
        $db->prepare("INSERT INTO conversation_members (conversation_id, user_id) VALUES $placeholders")->execute($values);

        // Set creator as admin
        $db->prepare("UPDATE conversation_members SET role = 'admin' WHERE conversation_id = ? AND user_id = ?")->execute([$convId, $userId]);

        jsonResponse(['success' => true, 'conversation_id' => $convId, 'created' => true]);
    }
}

jsonResponse(['success' => false, 'message' => 'Bad request'], 400);
