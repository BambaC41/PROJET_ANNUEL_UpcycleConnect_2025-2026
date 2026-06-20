<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/chat_local.php';
require_once 'includes/notifications.php';

$userId = (int)$_SESSION['user_id'];
$conversationId = (int)($_GET['conv'] ?? 0);
$targetUser = (int)($_GET['user'] ?? 0);

if ($targetUser > 0 && $targetUser != $userId) {
    $convId = chat_get_or_create_conversation($userId, $targetUser);
    if ($convId) {
        header('Location: admin_chat.php?conv=' . $convId);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $content = trim((string)($_POST['content'] ?? ''));
    $filePath = trim((string)($_POST['file_path'] ?? ''));
    $fileName = trim((string)($_POST['file_name'] ?? ''));
    if ($targetUserId > 0 && ($content !== '' || $filePath !== '')) {
        $result = chat_send_message($userId, $targetUserId, $content, $filePath, $fileName);
        if (isset($result['conversation_id'])) {
            notif_create($targetUserId, 'chat', ' Nouveau message', 
                'Vous avez reçu un nouveau message de ' . ($_SESSION['pseudo'] ?? 'un utilisateur'));
            header('Location: admin_chat.php?conv=' . $result['conversation_id']);
            exit;
        }
    }
}

$conversations = chat_get_conversations($userId);
$unreadCount = chat_get_unread_count($userId);

$messages = [];
if ($conversationId > 0) {
    $messages = chat_get_messages($conversationId);
    chat_mark_as_read($conversationId, $userId);
}

function getOtherUser($conv, $userId) {
    if ($conv['user1_id'] == $userId) {
        return [
            'id' => $conv['user2_id'], 
            'pseudo' => $conv['user2_pseudo'] ?? 'Utilisateur', 
            'photo' => $conv['user2_photo'] ?? '',
            'role' => $conv['user2_role'] ?? 2
        ];
    }
    return [
        'id' => $conv['user1_id'], 
        'pseudo' => $conv['user1_pseudo'] ?? 'Utilisateur', 
        'photo' => $conv['user1_photo'] ?? '',
        'role' => $conv['user1_role'] ?? 2
    ];
}

function getRoleLabel($roleId) {
    switch($roleId) {
        case 1: return 'Admin';
        case 3: return 'Pro';
        case 4: return 'Staff';
        default: return '';
    }
}

function getRoleColor($roleId) {
    switch($roleId) {
        case 1: return '#f44336';
        case 3: return '#ff9800';
        case 4: return '#2196f3';
        default: return '';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Administration</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin.css">
    <?php include 'includes/onesignal_head.php'; ?>
    <style>
        * { box-sizing: border-box; }
        .chat-layout {
            display: flex;
            gap: 0;
            min-height: 600px;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            background: white;
        }
        .chat-sidebar {
            width: 340px;
            min-width: 340px;
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #e5e7eb;
            max-height: 700px;
        }
        .chat-sidebar-header {
            padding: 16px 20px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .chat-sidebar-header h3 {
            margin: 0;
            font-size: 16px;
            color: #1a1a2e;
        }
        .chat-unread-badge-header {
            background: #f44336;
            color: white;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 700;
        }
        .chat-search {
            padding: 12px 16px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
        }
        .chat-search input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.2s;
            background: #f5f7fb;
        }
        .chat-search input:focus {
            outline: none;
            border-color: #4caf50;
            background: white;
        }
        .chat-conversations {
            flex: 1;
            overflow-y: auto;
            background: white;
        }
        .chat-conv-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.15s;
            text-decoration: none;
            color: inherit;
        }
        .chat-conv-item:hover {
            background: #f0f7ff;
        }
        .chat-conv-item.active {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
        }
        .chat-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #4caf50;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            margin-right: 12px;
            flex-shrink: 0;
            overflow: hidden;
        }
        .chat-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .chat-conv-info {
            flex: 1;
            min-width: 0;
        }
        .chat-conv-name {
            font-weight: 600;
            font-size: 14px;
            color: #1a1a2e;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .chat-conv-name .role-tag {
            font-size: 9px;
            padding: 1px 6px;
            border-radius: 4px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .chat-conv-preview {
            font-size: 12px;
            color: #999;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 2px;
        }
        .chat-conv-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            flex-shrink: 0;
            margin-left: 8px;
        }
        .chat-conv-time {
            font-size: 10px;
            color: #bbb;
        }
        .chat-unread-badge {
            background: #f44336;
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            margin-top: 4px;
            padding: 0 6px;
        }
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #fafbfc;
            max-height: 700px;
        }
        .chat-main-header {
            padding: 12px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
        }
        .chat-main-header .chat-avatar {
            width: 36px;
            height: 36px;
            font-size: 14px;
        }
        .chat-main-header .user-info {
            flex: 1;
        }
        .chat-main-header .user-info .name {
            font-weight: 600;
            font-size: 15px;
            color: #1a1a2e;
        }
        .chat-main-header .user-info .status {
            font-size: 11px;
            color: #999;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #fafbfc;
        }
        .chat-message {
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
        }
        .chat-message.sent {
            align-items: flex-end;
        }
        .chat-message.received {
            align-items: flex-start;
        }
        .chat-message .message-wrapper {
            max-width: 75%;
        }
        .chat-bubble {
            padding: 10px 16px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.5;
            word-wrap: break-word;
            position: relative;
        }
        .chat-message.sent .chat-bubble {
            background: #4caf50;
            color: white;
            border-bottom-right-radius: 4px;
        }
        .chat-message.received .chat-bubble {
            background: white;
            color: #333;
            border: 1px solid #e5e7eb;
            border-bottom-left-radius: 4px;
        }
        .chat-message .message-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
            padding: 0 8px;
            font-size: 10px;
            color: #999;
        }
        .chat-message .file-attachment {
            display: inline-block;
            padding: 6px 12px;
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            margin-top: 6px;
            font-size: 12px;
        }
        .chat-message .file-attachment a {
            color: inherit;
            text-decoration: underline;
        }
        .chat-message.received .file-attachment {
            background: #f0f0f0;
            color: #333;
        }
        .delete-msg-btn {
            background: none;
            border: none;
            color: rgba(255,255,255,0.6);
            font-size: 14px;
            cursor: pointer;
            float: right;
            margin-left: 8px;
            transition: color 0.2s;
        }
        .delete-msg-btn:hover {
            color: #ff4444;
        }
        .chat-message.received .delete-msg-btn {
            color: rgba(0,0,0,0.3);
        }
        .chat-message.received .delete-msg-btn:hover {
            color: #ff4444;
        }
        .chat-input-area {
            padding: 12px 20px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 10px;
            align-items: flex-end;
            background: white;
        }
        .chat-input-area textarea {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 10px;
            resize: none;
            font-size: 14px;
            min-height: 42px;
            max-height: 100px;
            font-family: inherit;
            background: #f5f7fb;
            transition: border-color 0.2s;
        }
        .chat-input-area textarea:focus {
            outline: none;
            border-color: #4caf50;
            background: white;
        }
        .chat-input-area .btn-file {
            background: none;
            border: none;
            font-size: 22px;
            cursor: pointer;
            padding: 6px 8px;
            color: #666;
            transition: color 0.2s;
        }
        .chat-input-area .btn-file:hover {
            color: #4caf50;
        }
        .chat-input-area .btn-send {
            background: #4caf50;
            border: none;
            color: white;
            padding: 10px 18px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 18px;
            transition: background 0.2s;
        }
        .chat-input-area .btn-send:hover {
            background: #2e7d32;
        }
        .chat-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1;
            color: #999;
            padding: 40px;
        }
        .chat-empty .icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        .search-results {
            border-top: 1px solid #e5e7eb;
            max-height: 250px;
            overflow-y: auto;
            background: white;
        }
        .search-result-item {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            cursor: pointer;
            transition: background 0.15s;
            text-decoration: none;
            color: inherit;
            border-bottom: 1px solid #f5f5f5;
        }
        .search-result-item:hover {
            background: #f0f7ff;
        }
        .search-result-item .chat-avatar {
            width: 36px;
            height: 36px;
            font-size: 14px;
        }
        .search-result-item .result-info {
            flex: 1;
        }
        .search-result-item .result-info .name {
            font-weight: 600;
            font-size: 14px;
        }
        .search-result-item .result-info .email {
            font-size: 11px;
            color: #999;
        }
        .search-result-item .result-info .role-tag {
            font-size: 9px;
            padding: 1px 6px;
            border-radius: 4px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 6px;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        .empty-state .icon {
            font-size: 32px;
            margin-bottom: 12px;
        }
        @media (max-width: 768px) {
            .chat-layout {
                flex-direction: column;
                border-radius: 0;
            }
            .chat-sidebar {
                width: 100%;
                min-width: auto;
                max-height: 300px;
                border-right: none;
                border-bottom: 1px solid #e5e7eb;
            }
            .chat-main {
                max-height: 500px;
            }
        }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="admin-shell">
    <div class="pro-card" style="padding:0;overflow:hidden; max-width:1400px; margin:0 auto;">
        <div class="chat-layout">
            <div class="chat-sidebar">
                <div class="chat-sidebar-header">
                    <h3> Conversations</h3>
                    <?php if ($unreadCount > 0): ?>
                        <span class="chat-unread-badge-header"><?= $unreadCount ?> non lu(s)</span>
                    <?php endif; ?>
                </div>
                <div class="chat-search">
                    <input type="text" id="searchInput" placeholder="Rechercher un utilisateur..." autocomplete="off">
                </div>
                <div id="searchResults" class="search-results" style="display:none;"></div>
                <div class="chat-conversations" id="conversationsList">
                    <?php if (empty($conversations)): ?>
                        <div class="empty-state">
                            <div class="icon">💬</div>
                            <p>Aucune conversation</p>
                            <p style="font-size:12px;">Recherchez un utilisateur pour commencer à chatter</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): 
                            $other = getOtherUser($conv, $userId);
                            $isActive = $conversationId == $conv['id_conversation'];
                            $roleLabel = getRoleLabel($other['role'] ?? 2);
                            $roleColor = getRoleColor($other['role'] ?? 2);
                        ?>
                            <a href="admin_chat.php?conv=<?= (int)$conv['id_conversation'] ?>" class="chat-conv-item <?= $isActive ? 'active' : '' ?>">
                                <div class="chat-avatar">
                                    <?php if (!empty($other['photo'])): ?>
                                        <img src="<?= e(vc_media_url($other['photo'])) ?>" alt="">
                                    <?php else: ?>
                                        <?= e(mb_substr($other['pseudo'] ?? 'U', 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="chat-conv-info">
                                    <div class="chat-conv-name">
                                        <?= e($other['pseudo'] ?? 'Utilisateur') ?>
                                        <?php if ($roleLabel): ?>
                                            <span class="role-tag" style="background:<?= $roleColor ?>;color:white;"><?= $roleLabel ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="chat-conv-preview">
                                        <?= e(mb_substr($conv['last_message'] ?? '', 0, 40)) ?: 'Nouvelle conversation' ?>
                                    </div>
                                </div>
                                <div class="chat-conv-right">
                                    <?php if (!empty($conv['last_message_at'])): ?>
                                        <span class="chat-conv-time"><?= date('H:i', strtotime($conv['last_message_at'])) ?></span>
                                    <?php endif; ?>
                                    <?php if ((int)($conv['unread_count'] ?? 0) > 0): ?>
                                        <span class="chat-unread-badge"><?= (int)$conv['unread_count'] ?></span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="chat-main">
                <?php if ($conversationId > 0): 
                    $currentConv = null;
                    foreach ($conversations as $c) {
                        if ($c['id_conversation'] == $conversationId) {
                            $currentConv = $c;
                            break;
                        }
                    }
                    $other = $currentConv ? getOtherUser($currentConv, $userId) : null;
                    $roleLabel = getRoleLabel($other['role'] ?? 2);
                ?>
                    <div class="chat-main-header">
                        <div class="chat-avatar">
                            <?php if ($other && !empty($other['photo'])): ?>
                                <img src="<?= e(vc_media_url($other['photo'])) ?>" alt="">
                            <?php elseif ($other): ?>
                                <?= e(mb_substr($other['pseudo'] ?? 'U', 0, 1)) ?>
                            <?php else: ?>
                                U
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <div class="name">
                                <?= e($other['pseudo'] ?? 'Utilisateur') ?>
                                <?php if ($roleLabel): ?>
                                    <span class="role-tag" style="background:<?= getRoleColor($other['role'] ?? 2) ?>;color:white;font-size:9px;padding:1px 8px;border-radius:4px;"><?= $roleLabel ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="status">En ligne</div>
                        </div>
                    </div>
                    <div class="chat-messages" id="chatMessages">
                        <?php if (empty($messages)): ?>
                            <div class="chat-empty">
                                <div class="icon">💬</div>
                                <p>Aucun message</p>
                                <p style="font-size:13px;">Envoyez un premier message à <?= e($other['pseudo'] ?? '') ?> !</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): 
                                $isSent = (int)$msg['sender_id'] === $userId;
                                $senderPseudo = $isSent ? 'Vous' : ($msg['sender_pseudo'] ?? '');
                            ?>
                                <div class="chat-message <?= $isSent ? 'sent' : 'received' ?>" data-message-id="<?= (int)$msg['id_message'] ?>">
                                    <div class="message-wrapper">
                                        <div class="chat-bubble">
                                            <?= nl2br(e($msg['content'] ?? '')) ?>
                                            <?php if (!empty($msg['file_path'])): ?>
                                                <div class="file-attachment">
                                                    📎 <a href="<?= e($msg['file_path']) ?>" target="_blank"><?= e($msg['file_name'] ?? 'Fichier') ?></a>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($isSent): ?>
                                                <button class="delete-msg-btn" data-message-id="<?= (int)$msg['id_message'] ?>">✕</button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="message-meta">
                                            <span><?= $senderPseudo ?></span>
                                            <span>•</span>
                                            <span><?= date('H:i', strtotime($msg['created_at'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="chat-input-area">
                        <form method="POST" id="chatForm" style="display:flex;gap:10px;width:100%;align-items:flex-end;">
                            <input type="hidden" name="send_message" value="1">
                            <input type="hidden" name="user_id" value="<?= $other ? (int)$other['id'] : 0 ?>">
                            <input type="hidden" name="file_path" id="filePath">
                            <input type="hidden" name="file_name" id="fileName">
                            
                            <button type="button" class="btn-file" onclick="document.getElementById('fileInput').click()">📎</button>
                            <input type="file" id="fileInput" style="display:none" onchange="uploadFile(this)">
                            
                            <textarea name="content" id="messageInput" rows="1" placeholder="Écrire un message..." onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();document.getElementById('chatForm').submit();}"></textarea>
                            <button type="submit" class="btn-send">➤</button>
                        </form>
                    </div>
                    
                <?php else: ?>
                    <div class="chat-empty">
                        <div class="icon">💬</div>
                        <p>Sélectionnez une conversation</p>
                        <p style="font-size:13px;">ou recherchez un utilisateur pour démarrer une discussion</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
let searchTimeout;

document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const query = this.value.trim();
    
    if (query.length === 0) {
        document.getElementById('searchResults').style.display = 'none';
        document.getElementById('conversationsList').style.display = 'block';
        return;
    }
    
    searchTimeout = setTimeout(function() {
        fetch('chat_search_ajax.php?q=' + encodeURIComponent(query))
            .then(res => {
                if (!res.ok) throw new Error('Erreur réseau');
                return res.json();
            })
            .then(data => {
                const resultsDiv = document.getElementById('searchResults');
                const convList = document.getElementById('conversationsList');
                
                if (!data || data.length === 0) {
                    resultsDiv.innerHTML = '<div class="empty-state" style="padding:20px;"><p>Aucun utilisateur trouvé</p></div>';
                    resultsDiv.style.display = 'block';
                    convList.style.display = 'none';
                    return;
                }
                
                let html = '';
                data.forEach(user => {
                    const initial = (user.pseudo || 'U').substring(0, 1).toUpperCase();
                    const photoUrl = user.photo_url || '';
                    const roleLabel = user.role_label || '';
                    const roleColor = user.role_color || '';
                    html += `
                        <a href="admin_chat.php?user=${user.id_user}" class="search-result-item">
                            <div class="chat-avatar">
                                ${photoUrl ? `<img src="${photoUrl}" alt="">` : initial}
                            </div>
                            <div class="result-info">
                                <div class="name">
                                    ${user.pseudo || 'Utilisateur'}
                                    ${roleLabel ? `<span class="role-tag" style="background:${roleColor};color:white;">${roleLabel}</span>` : ''}
                                </div>
                                <div class="email">${user.email || ''}</div>
                            </div>
                            <span style="color:#4caf50;font-size:18px;">+</span>
                        </a>
                    `;
                });
                resultsDiv.innerHTML = html;
                resultsDiv.style.display = 'block';
                convList.style.display = 'none';
            })
            .catch(err => {
                console.error(err);
                document.getElementById('searchResults').innerHTML = '<div class="empty-state" style="padding:20px;"><p>Erreur de recherche</p></div>';
                document.getElementById('searchResults').style.display = 'block';
            });
    }, 300);
});

document.getElementById('searchInput').addEventListener('blur', function() {
    setTimeout(() => {
        if (!document.activeElement || !document.activeElement.closest('.search-results')) {
            document.getElementById('searchResults').style.display = 'none';
            document.getElementById('conversationsList').style.display = 'block';
        }
    }, 200);
});

function uploadFile(input) {
    const file = input.files[0];
    if (!file) return;
    
    const formData = new FormData();
    formData.append('file', file);
    
    fetch('upload_chat.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.file_path) {
            document.getElementById('filePath').value = data.file_path;
            document.getElementById('fileName').value = data.file_name;
            document.getElementById('chatForm').submit();
        } else {
            alert('Erreur lors de l\'upload du fichier');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Erreur lors de l\'upload');
    });
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('delete-msg-btn')) {
        const messageId = e.target.dataset.messageId;
        if (confirm('Supprimer ce message ?')) {
            fetch('particulier_chat_delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Authorization': 'Bearer <?= e($_SESSION['token'] ?? '') ?>'
                },
                body: 'message_id=' + messageId
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const msgElement = document.querySelector(`.chat-message[data-message-id="${messageId}"]`);
                    if (msgElement) {
                        msgElement.style.display = 'none';
                    }
                } else {
                    alert('Erreur lors de la suppression');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Erreur lors de la suppression');
            });
        }
    }
});

const msgContainer = document.getElementById('chatMessages');
if (msgContainer) {
    msgContainer.scrollTop = msgContainer.scrollHeight;
}

document.getElementById('messageInput')?.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 100) + 'px';
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('searchResults').style.display = 'none';
        document.getElementById('conversationsList').style.display = 'block';
        document.getElementById('searchInput').value = '';
    }
});
</script>

<?php include 'includes/flash_toast.php'; ?>
</body>
</html>