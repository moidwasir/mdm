<?php
$pageTitle = 'Chat Monitor';
require_once __DIR__ . '/../includes/auth-check.php';
$db = getDB();

// Handle AJAX request to fetch conversation messages
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_messages') {
    $convId = (int)($_GET['conversation_id'] ?? 0);
    $stmt = $db->prepare("
        SELECT m.content, m.created_at, m.sender_id, u.display_name as sender_name, u.avatar
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE m.conversation_id = ? AND m.is_deleted = 0
        ORDER BY m.id ASC
    ");
    $stmt->execute([$convId]);
    jsonResponse(['success' => true, 'messages' => $stmt->fetchAll()]);
}

$conversations = $db->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as msg_count, 
           (SELECT COUNT(*) FROM conversation_members WHERE conversation_id = c.id) as member_count 
    FROM conversations c 
    WHERE c.is_active = 1 
    ORDER BY c.updated_at DESC 
    LIMIT 20
")->fetchAll();

$totalMessages = $db->query("SELECT COUNT(*) FROM messages")->fetchColumn();
$activeConvos = $db->query("SELECT COUNT(*) FROM conversations WHERE is_active = 1")->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-comments"></i> Chat Monitor</h1>
    <p>Monitor active conversations and messages in real-time</p>
</div>

<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-comments"></i></div>
        <div class="stat-value"><?= $activeConvos ?></div>
        <div class="stat-label">Active Conversations</div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon"><i class="fas fa-message"></i></div>
        <div class="stat-value"><?= $totalMessages ?></div>
        <div class="stat-label">Total Messages</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px;">
    <!-- Conversations Table -->
    <div class="card">
        <div class="card-header"><h3 class="card-title">Active Chat Channels</h3></div>
        <?php if (empty($conversations)): ?>
            <div class="empty-state">
                <i class="fas fa-comments"></i>
                <h3>No active chats</h3>
                <p>Chat conversations will appear here once users start messaging.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Conversation Name</th>
                            <th>Type</th>
                            <th>Members</th>
                            <th>Messages</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($conversations as $c): ?>
                        <tr class="conv-row" id="conv-<?= $c['id'] ?>">
                            <td style="font-weight: 500;"><?= sanitize($c['name'] ?: 'Direct Message') ?></td>
                            <td>
                                <span class="badge <?= $c['type'] === 'group' ? 'badge-info' : 'badge-secondary' ?>">
                                    <?= ucfirst($c['type']) ?>
                                </span>
                            </td>
                            <td style="color: var(--text-secondary);"><?= $c['member_count'] ?></td>
                            <td style="color: var(--text-secondary);"><?= $c['msg_count'] ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="viewChat(<?= $c['id'] ?>, '<?= sanitize($c['name'] ?: 'Direct Message') ?>')">
                                    <i class="fas fa-eye"></i> Live View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Live Preview Console -->
    <div class="card" id="chat-preview-card" style="display: flex; flex-direction: column; height: 500px;">
        <div class="card-header" style="background: rgba(99, 102, 241, 0.05);">
            <h3 class="card-title" id="chat-preview-title"><i class="fas fa-desktop"></i> Select a Conversation</h3>
        </div>
        <div class="card-body" id="chat-messages-container" style="flex: 1; overflow-y: auto; padding: 16px; background: rgba(0, 0, 0, 0.1); border-radius: 8px; margin: 16px; display: flex; flex-direction: column; gap: 12px;">
            <div class="empty-state" style="margin: auto;">
                <i class="fas fa-binoculars" style="font-size: 3rem; color: var(--text-muted);"></i>
                <p style="margin-top: 12px; color: var(--text-secondary);">Click "Live View" on any chat channel to monitor messages real-time</p>
            </div>
        </div>
    </div>
</div>

<script>
let activeConvInterval = null;
let currentConvId = null;

function viewChat(convId, name) {
    currentConvId = convId;
    document.getElementById('chat-preview-title').innerHTML = `<i class="fas fa-comments text-primary"></i> Live View: ${name}`;
    
    // Clear previous polling interval
    if (activeConvInterval) clearInterval(activeConvInterval);
    
    // Highlight active row
    document.querySelectorAll('.conv-row').forEach(row => row.classList.remove('active-row'));
    const selectedRow = document.getElementById(`conv-${convId}`);
    if (selectedRow) selectedRow.classList.add('active-row');
    
    // Initial fetch
    fetchMessages(convId);
    
    // Poll every 3 seconds for new live messages
    activeConvInterval = setInterval(() => fetchMessages(convId), 3000);
}

function fetchMessages(convId) {
    fetch(`chat-monitor.php?ajax_action=get_messages&conversation_id=${convId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderMessages(data.messages);
            }
        });
}

function renderMessages(messages) {
    const container = document.getElementById('chat-messages-container');
    if (messages.length === 0) {
        container.innerHTML = `<div class="empty-state" style="margin:auto;"><p>No messages in this chat yet</p></div>`;
        return;
    }
    
    let html = '';
    messages.forEach(msg => {
        const time = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        html += `
            <div style="background: rgba(255,255,255,0.03); padding: 10px 14px; border-radius: 12px; border-left: 3px solid var(--primary-color);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <strong style="color: var(--primary-color); font-size: 0.9rem;">${msg.sender_name}</strong>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">${time}</span>
                </div>
                <div style="color: var(--text-primary); font-size: 0.9rem; word-break: break-all;">${msg.content}</div>
            </div>
        `;
    });
    
    // Keep scroll at bottom if user is not looking at history
    const isAtBottom = container.scrollHeight - container.clientHeight <= container.scrollTop + 50;
    container.innerHTML = html;
    if (isAtBottom) {
        container.scrollTop = container.scrollHeight;
    }
}
</script>

<style>
.active-row {
    background: rgba(99, 102, 241, 0.08) !important;
    border-left: 4px solid var(--primary-color);
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
