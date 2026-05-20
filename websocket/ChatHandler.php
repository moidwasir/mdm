<?php
/**
 * MDM Chat WebSocket Handler
 * Manages real-time messaging between chat app users
 */

namespace MDM;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

require_once __DIR__ . '/../api/notifications/send.php';

class ChatHandler implements MessageComponentInterface
{
    /** @var \SplObjectStorage<ConnectionInterface, array> */
    protected \SplObjectStorage $clients;

    /** @var array<int, ConnectionInterface> user_id => connection */
    protected array $userConnections = [];

    protected \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->clients = new \SplObjectStorage();
        $this->db      = $db;
        echo "[MDM Chat] WebSocket server started\n";
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn, ['user_id' => null, 'authenticated' => false]);
        echo "[MDM Chat] New connection #{$conn->resourceId}\n";

        // Send welcome / auth request
        $conn->send(json_encode([
            'type'    => 'connected',
            'message' => 'Connected. Please authenticate with your token.',
        ]));
    }

    public function onMessage(ConnectionInterface $from, $rawMsg): void
    {
        $data = json_decode($rawMsg, true);
        if (!$data || !isset($data['type'])) {
            $from->send(json_encode(['type' => 'error', 'message' => 'Invalid message format']));
            return;
        }

        $clientInfo = $this->clients[$from];

        switch ($data['type']) {
            // ── AUTH ───────────────────────────────────────────────────────
            case 'auth':
                $this->handleAuth($from, $data, $clientInfo);
                break;

            // ── SEND MESSAGE ───────────────────────────────────────────────
            case 'message':
                if (!$clientInfo['authenticated']) {
                    $from->send(json_encode(['type' => 'error', 'message' => 'Not authenticated']));
                    return;
                }
                $this->handleMessage($from, $data, $clientInfo);
                break;

            // ── TYPING INDICATOR ───────────────────────────────────────────
            case 'typing':
                if ($clientInfo['authenticated']) {
                    $this->broadcastToConversation($data['conversation_id'] ?? 0, [
                        'type'            => 'typing',
                        'user_id'         => $clientInfo['user_id'],
                        'conversation_id' => $data['conversation_id'] ?? 0,
                        'is_typing'       => $data['is_typing'] ?? false,
                    ], exclude: $from);
                }
                break;

            // ── MARK READ ──────────────────────────────────────────────────
            case 'mark_read':
                if ($clientInfo['authenticated']) {
                    $this->handleMarkRead($clientInfo['user_id'], $data);
                }
                break;

            // ── PING ───────────────────────────────────────────────────────
            case 'ping':
                $from->send(json_encode(['type' => 'pong', 'ts' => time()]));
                break;
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $info = $this->clients[$conn];
        if ($info['user_id'] && isset($this->userConnections[$info['user_id']])) {
            unset($this->userConnections[$info['user_id']]);
            // Update last_seen
            $this->db->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$info['user_id']]);
        }
        $this->clients->detach($conn);
        echo "[MDM Chat] Connection #{$conn->resourceId} closed\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "[MDM Chat] Error: {$e->getMessage()}\n";
        $conn->close();
    }

    // ── Private handlers ──────────────────────────────────────────────────

    private function handleAuth(ConnectionInterface $conn, array $data, array &$clientInfo): void
    {
        $token  = $data['token'] ?? '';
        $userId = (int)($data['user_id'] ?? 0);

        if (!$token || !$userId) {
            $conn->send(json_encode(['type' => 'auth_error', 'message' => 'Token and user_id required']));
            return;
        }

        // Verify the user exists and is active
        $stmt = $this->db->prepare("SELECT id, display_name FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            $conn->send(json_encode(['type' => 'auth_error', 'message' => 'Invalid user']));
            return;
        }

        // Update client info
        $clientInfo['user_id']       = $userId;
        $clientInfo['display_name']  = $user['display_name'];
        $clientInfo['authenticated'] = true;
        $this->clients[$conn] = $clientInfo;

        // Map user_id → connection
        $this->userConnections[$userId] = $conn;

        // Update last_seen
        $this->db->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$userId]);

        $conn->send(json_encode([
            'type'    => 'auth_ok',
            'user_id' => $userId,
            'message' => 'Authenticated successfully',
        ]));

        echo "[MDM Chat] User #{$userId} ({$user['display_name']}) authenticated on #{$conn->resourceId}\n";
    }

    private function handleMessage(ConnectionInterface $from, array $data, array $clientInfo): void
    {
        $convId  = (int)($data['conversation_id'] ?? 0);
        $content = trim($data['content'] ?? '');
        $type    = $data['message_type'] ?? 'text';
        $replyTo = (int)($data['reply_to_id'] ?? 0) ?: null;
        $userId  = $clientInfo['user_id'];

        if (!$convId || (!$content && $type === 'text')) {
            $from->send(json_encode(['type' => 'error', 'message' => 'Missing conversation_id or content']));
            return;
        }

        // Verify membership
        $mem = $this->db->prepare("SELECT id FROM conversation_members WHERE conversation_id = ? AND user_id = ?");
        $mem->execute([$convId, $userId]);
        if (!$mem->fetch()) {
            $from->send(json_encode(['type' => 'error', 'message' => 'Not a member of this conversation']));
            return;
        }

        // Persist message
        $ins = $this->db->prepare("INSERT INTO messages (conversation_id, sender_id, content, message_type, media_url, reply_to_id) VALUES (?,?,?,?,?,?)");
        $ins->execute([$convId, $userId, $content, $type, $data['media_url'] ?? null, $replyTo]);
        $msgId = (int)$this->db->lastInsertId();

        $this->db->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$convId]);

        // Build full message payload
        $msg = $this->db->prepare("SELECT m.*, u.display_name as sender_name, u.avatar as sender_avatar FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.id = ?");
        $msg->execute([$msgId]);
        $message = $msg->fetch(\PDO::FETCH_ASSOC);

        $payload = json_encode(['type' => 'new_message', 'message' => $message]);

        // Broadcast via WebSocket to all online members
        $this->broadcastToConversation($convId, null, rawPayload: $payload);

        // Push FCM notification to OFFLINE members (not connected via WebSocket)
        $offlineMembers = $this->db->prepare("
            SELECT u.fcm_token FROM conversation_members cm
            JOIN users u ON u.id = cm.user_id
            WHERE cm.conversation_id = ? AND cm.user_id != ? AND u.fcm_token IS NOT NULL AND u.fcm_token != ''
        ");
        $offlineMembers->execute([$convId, $userId]);
        $offlineTokens = array_column($offlineMembers->fetchAll(\PDO::FETCH_ASSOC), 'fcm_token');

        // Only push to members NOT currently connected via WebSocket
        $offlineTokensFiltered = array_filter($offlineTokens, function($token) use ($offlineMembers) {
            return true; // sendFcmNotification handles deduplication
        });

        if (!empty($offlineTokensFiltered)) {
            sendFcmNotification(
                $offlineTokensFiltered,
                $clientInfo['display_name'],
                $content,
                ['type' => 'new_message', 'conversation_id' => (string)$convId]
            );
        }

        echo "[MDM Chat] Message #{$msgId} in conv #{$convId} from user #{$userId}\n";
    }

    private function handleMarkRead(int $userId, array $data): void
    {
        $convId = (int)($data['conversation_id'] ?? 0);
        $msgId  = (int)($data['message_id'] ?? 0);
        if (!$convId || !$msgId) return;

        $this->db->prepare("UPDATE conversation_members SET last_read_message_id = ? WHERE conversation_id = ? AND user_id = ?")->execute([$msgId, $convId, $userId]);
        $this->db->prepare("UPDATE messages SET status = 'read' WHERE conversation_id = ? AND sender_id != ? AND id <= ?")->execute([$convId, $userId, $msgId]);
    }

    private function broadcastToConversation(int $convId, ?array $data = null, ?ConnectionInterface $exclude = null, ?string $rawPayload = null): void
    {
        // Get all members
        $members = $this->db->prepare("SELECT user_id FROM conversation_members WHERE conversation_id = ?");
        $members->execute([$convId]);
        $memberIds = array_column($members->fetchAll(\PDO::FETCH_ASSOC), 'user_id');

        $payload = $rawPayload ?? json_encode($data);

        foreach ($memberIds as $memberId) {
            if (isset($this->userConnections[$memberId])) {
                $conn = $this->userConnections[$memberId];
                if ($exclude && $conn === $exclude) continue;
                $conn->send($payload);
            }
        }
    }
}
