<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/forum_local.php';
require_once 'includes/notifications.php';
require_once 'includes/ui_helpers.php';

$userId = (int)$_SESSION['user_id'];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$filter = trim((string)($_GET['filter'] ?? 'all'));
$roleFilter = trim((string)($_GET['role'] ?? 'all'));
$q = trim((string)($_GET['q'] ?? ''));

// Actions de modération
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postId = (int)($_POST['post_id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? 'Modération administrateur'));
    
    if ($action === 'toggle_hide' && $postId > 0) {
        $currentState = (int)($_POST['current_hide'] ?? 0);
        $newState = $currentState ? 'restore' : 'hide';
        $ok = forum_moderate_post($postId, $userId, $newState, $reason);
        
        if ($ok && !$currentState) {
            $authorId = (int)db_safe_exec(function (PDO $pdo) use ($postId) {
                $st = $pdo->prepare('SELECT author_id FROM forum_posts WHERE id_post = ?');
                $st->execute([$postId]);
                return (int)$st->fetchColumn();
            }, 0);
            if ($authorId > 0) {
                notif_create($authorId, 'forum_moderation', '📝 Votre message a été masqué', 
                    "Un modérateur a masqué votre message pour la raison suivante : " . $reason . "\n\nSi vous avez des questions, contactez l'équipe de modération.");
            }
        }
        
        $_SESSION['flash_toast'] = $ok 
            ? ['type' => 'success', 'message' => $currentState ? 'Message restauré.' : 'Message masqué.']
            : ['type' => 'error', 'message' => 'Action impossible.'];
    }
    
    elseif ($action === 'delete' && $postId > 0) {
        $ok = (bool)db_safe_exec(function (PDO $pdo) use ($postId) {
            $st = $pdo->prepare('DELETE FROM forum_posts WHERE id_post = ?');
            return $st->execute([$postId]);
        }, false);
        $_SESSION['flash_toast'] = $ok 
            ? ['type' => 'success', 'message' => 'Message supprimé définitivement.']
            : ['type' => 'error', 'message' => 'Suppression impossible.'];
    }
    
    elseif ($action === 'ban_user' && $postId > 0) {
        $authorId = (int)db_safe_exec(function (PDO $pdo) use ($postId) {
            $st = $pdo->prepare('SELECT author_id FROM forum_posts WHERE id_post = ?');
            $st->execute([$postId]);
            return (int)$st->fetchColumn();
        }, 0);
        if ($authorId > 0) {
            $duration = $_POST['ban_duration'] ?? '7';
            $banUntil = date('Y-m-d H:i:s', strtotime('+' . $duration . ' days'));
            $banReason = trim((string)($_POST['ban_reason'] ?? $reason));
            $ok = (bool)db_safe_exec(function (PDO $pdo) use ($authorId, $banUntil, $banReason, $userId) {
                $st = $pdo->prepare('INSERT INTO forum_bans (user_id, reason, banned_until, banned_by) VALUES (?, ?, ?, ?)');
                return $st->execute([$authorId, $banReason, $banUntil, $userId]);
            }, false);
            $_SESSION['flash_toast'] = $ok 
                ? ['type' => 'success', 'message' => 'Utilisateur banni jusqu\'au ' . date('d/m/Y', strtotime($banUntil))]
                : ['type' => 'error', 'message' => 'Bannissement impossible.'];
        }
    }
    
    elseif ($action === 'warn_user' && $postId > 0) {
        $authorId = (int)db_safe_exec(function (PDO $pdo) use ($postId) {
            $st = $pdo->prepare('SELECT author_id FROM forum_posts WHERE id_post = ?');
            $st->execute([$postId]);
            return (int)$st->fetchColumn();
        }, 0);
        if ($authorId > 0) {
            $warnReason = trim((string)($_POST['warn_reason'] ?? $reason));
            $ok = (bool)db_safe_exec(function (PDO $pdo) use ($authorId, $warnReason, $userId) {
                $st = $pdo->prepare('INSERT INTO user_warnings (user_id, warning_type, message, issued_by) VALUES (?, "forum", ?, ?)');
                return $st->execute([$authorId, $warnReason, $userId]);
            }, false);
            if ($ok) {
                notif_create($authorId, 'forum_warning', '⚠️ Avertissement reçu', 
                    "Vous avez reçu un avertissement de la part d'un modérateur.\n\nRaison : " . $warnReason . "\n\nVeuillez respecter les règles du forum.");
            }
            $_SESSION['flash_toast'] = $ok 
                ? ['type' => 'success', 'message' => 'Avertissement envoyé.']
                : ['type' => 'error', 'message' => 'Impossible d\'envoyer l\'avertissement.'];
        }
    }
    
    header('Location: admin_forum_moderation.php?page=' . $page . '&filter=' . $filter . '&role=' . $roleFilter . ($q ? '&q=' . urlencode($q) : ''));
    exit;
}

// Récupération des messages avec filtre par rôle
$messages = (array)db_safe_exec(function (PDO $pdo) use ($page, $perPage, $filter, $roleFilter, $q) {
    $sql = "SELECT p.id_post, p.content, p.created_at, p.is_hidden, p.hidden_reason, p.hidden_by, p.hidden_at,
            u.id_user, u.pseudo, u.email, u.id_role,
            t.id_topic, t.title as topic_title, t.is_locked, t.is_hidden as topic_hidden,
            c.name as category_name,
            m.pseudo as hidden_by_pseudo,
            r.libelle as role_name
            FROM forum_posts p
            JOIN utilisateur u ON u.id_user = p.author_id
            JOIN role r ON r.id_role = u.id_role
            JOIN forum_topics t ON t.id_topic = p.topic_id
            JOIN forum_categories c ON c.id_category = t.category_id
            LEFT JOIN utilisateur m ON m.id_user = p.hidden_by";
    
    $where = [];
    $params = [];
    
    if ($filter === 'hidden') {
        $where[] = "p.is_hidden = 1";
    } elseif ($filter === 'visible') {
        $where[] = "p.is_hidden = 0";
    }
    
    if ($roleFilter !== 'all') {
        $where[] = "u.id_role = ?";
        $params[] = (int)$roleFilter;
    }
    
    if ($q !== '') {
        $where[] = "(p.content LIKE ? OR u.pseudo LIKE ? OR t.title LIKE ?)";
        $like = "%$q%";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = ($page - 1) * $perPage;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}, []);

// Récupération des suggestions pour l'autocomplete
$suggestions = [];
if (strlen($q) > 1) {
    $suggestions = (array)db_safe_exec(function (PDO $pdo) use ($q) {
        $sql = "SELECT DISTINCT u.pseudo 
                FROM forum_posts p
                JOIN utilisateur u ON u.id_user = p.author_id
                WHERE u.pseudo LIKE ?
                LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(["%$q%"]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }, []);
}

// Comptage pour les statistiques
$stats = (array)db_safe_exec(function (PDO $pdo) {
    $stats = [];
    $st = $pdo->query("SELECT COUNT(*) FROM forum_posts WHERE is_hidden = 1");
    $stats['hidden'] = (int)$st->fetchColumn();
    $st = $pdo->query("SELECT COUNT(*) FROM forum_posts WHERE is_hidden = 0");
    $stats['visible'] = (int)$st->fetchColumn();
    $st = $pdo->query("SELECT COUNT(*) FROM forum_reports WHERE status = 'pending'");
    $stats['reports'] = (int)$st->fetchColumn();
    return $stats;
}, ['hidden' => 0, 'visible' => 0, 'reports' => 0]);

// Récupération des rôles pour le filtre
$roles = (array)db_safe_exec(function (PDO $pdo) {
    return $pdo->query("SELECT id_role, libelle FROM role ORDER BY id_role")->fetchAll(PDO::FETCH_ASSOC);
}, []);

$totalMessages = $filter === 'hidden' ? $stats['hidden'] : ($filter === 'visible' ? $stats['visible'] : $stats['hidden'] + $stats['visible']);
$totalPages = max(1, (int)ceil($totalMessages / $perPage));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Modération centralisée du forum</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin.css">
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="pro-shell page-shell">
    <?php include 'includes/flash_toast.php'; ?>
    
    <section class="pro-card">
        <h1>🛡️ Modération centralisée du forum</h1>
        <p class="muted">
            <a href="admin_forum.php">← Supervision forum</a> ·
            <a href="admin_forum_reports.php">⚠️ Signalements (<?= $stats['reports'] ?>)</a> ·
            <a href="admin_forum_categories.php">📂 Catégories</a> ·
            <a href="forum.php" target="_blank">🌐 Voir le forum</a>
        </p>
        
        <!-- Statistiques -->
        <div class="stats-bar">
            <div class="stat-badge">
                <div class="stat-number"><?= $stats['visible'] ?></div>
                <div>Messages visibles</div>
            </div>
            <div class="stat-badge">
                <div class="stat-number" style="color:#f44336;"><?= $stats['hidden'] ?></div>
                <div>Messages masqués</div>
            </div>
            <div class="stat-badge">
                <div class="stat-number" style="color:#ff9800;"><?= $stats['reports'] ?></div>
                <div>Signalements en attente</div>
            </div>
        </div>
        
        <!-- Filtres -->
        <div class="filter-bar">
            <a href="?filter=all&role=<?= e($roleFilter) ?><?= $q ? '&q=' . urlencode($q) : '' ?>" 
               class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">📋 Tous</a>
            <a href="?filter=visible&role=<?= e($roleFilter) ?><?= $q ? '&q=' . urlencode($q) : '' ?>" 
               class="filter-btn <?= $filter === 'visible' ? 'active' : '' ?>">✅ Visibles</a>
            <a href="?filter=hidden&role=<?= e($roleFilter) ?><?= $q ? '&q=' . urlencode($q) : '' ?>" 
               class="filter-btn <?= $filter === 'hidden' ? 'active' : '' ?>">🙈 Masqués</a>
        </div>
        
        <!-- Filtre par rôle et recherche avec autocomplete -->
        <form method="GET" style="margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <input type="hidden" name="filter" value="<?= e($filter) ?>">
            <select name="role" class="role-filter-select" onchange="this.form.submit()">
                <option value="all">👥 Tous les rôles</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= (int)$role['id_role'] ?>" <?= $roleFilter == $role['id_role'] ? 'selected' : '' ?>>
                        <?php 
                            switch($role['libelle']) {
                                case 'Administrateur': echo '👑 ' . e($role['libelle']); break;
                                case 'Professionnel': echo '🏢 ' . e($role['libelle']); break;
                                case 'Salarie animateur formateur': echo '🎓 ' . e($role['libelle']); break;
                                default: echo '👤 ' . e($role['libelle']);
                            }
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <div class="search-wrapper">
                <div class="search-container" style="flex:1;">
                    <input class="input" type="search" name="q" id="searchInput" 
                           value="<?= e($q) ?>" placeholder="Rechercher par auteur, message ou sujet..." 
                           style="width: 100%; padding: 10px;" autocomplete="off">
                    <div id="suggestions" class="suggestions-box"></div>
                </div>
                <button class="btn-primary" type="submit">🔍 Rechercher</button>
                <?php if ($q): ?>
                    <a class="btn-outline" href="?filter=<?= e($filter) ?>&role=<?= e($roleFilter) ?>">✖️ Effacer</a>
                <?php endif; ?>
            </div>
        </form>
        
        <?php if (empty($messages)): ?>
            <div class="empty-state" style="text-align:center; padding: 60px;">
                📭 Aucun message trouvé.
            </div>
        <?php else: ?>
            <?php foreach ($messages as $msg): 
                $roleClass = '';
                switch($msg['id_role'] ?? 2) {
                    case 1: $roleClass = 'role-admin'; $roleLabel = 'Administrateur'; break;
                    case 3: $roleClass = 'role-pro'; $roleLabel = 'Professionnel'; break;
                    case 4: $roleClass = 'role-salarie'; $roleLabel = 'Salarié'; break;
                    default: $roleClass = 'role-user'; $roleLabel = 'Particulier';
                }
            ?>
                <div class="message-card <?= !empty($msg['is_hidden']) ? 'hidden' : '' ?>" id="post-<?= (int)$msg['id_post'] ?>">
                    <div class="message-header">
                        <div>
                            <span class="message-author">
                                👤 <?= e($msg['pseudo'] ?? 'Utilisateur') ?>
                                <span class="role-badge <?= $roleClass ?>"><?= $roleLabel ?></span>
                            </span>
                            <?php if (!empty($msg['is_hidden'])): ?>
                                <span class="badge-hidden">🙈 MASQUÉ</span>
                            <?php else: ?>
                                <span class="badge-visible">✅ VISIBLE</span>
                            <?php endif; ?>
                        </div>
                        <div class="message-date">
                            📅 <?= formatDateFr($msg['created_at'] ?? '') ?>
                        </div>
                    </div>
                    
                    <div class="message-topic">
                        💬 Sujet: 
                        <a href="forum_topic.php?id=<?= (int)$msg['id_topic'] ?>" target="_blank">
                            <?= e(mb_substr($msg['topic_title'] ?? '', 0, 100)) ?>
                        </a>
                        <?php if (!empty($msg['topic_hidden'])): ?>
                            <span class="topic-locked">(sujet masqué)</span>
                        <?php endif; ?>
                        <?php if (!empty($msg['is_locked'])): ?>
                            <span class="topic-locked">🔒 verrouillé</span>
                        <?php endif; ?>
                        <br>
                        <small>📂 <?= e($msg['category_name'] ?? '') ?></small>
                    </div>
                    
                    <div class="message-content">
                        <?= nl2br(e(mb_substr($msg['content'] ?? '', 0, 1000))) ?>
                        <?php if (mb_strlen($msg['content'] ?? '') > 1000): ?>
                            <span class="muted">...</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($msg['is_hidden']) && !empty($msg['hidden_reason'])): ?>
                        <div class="mod-info">
                            🔒 Masqué par <?= e($msg['hidden_by_pseudo'] ?? 'modérateur') ?> 
                            le <?= formatDateFr($msg['hidden_at'] ?? '') ?>
                            <br>📝 Raison: <?= e($msg['hidden_reason']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="message-actions">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_hide">
                            <input type="hidden" name="post_id" value="<?= (int)$msg['id_post'] ?>">
                            <input type="hidden" name="current_hide" value="<?= (int)($msg['is_hidden'] ?? 0) ?>">
                            <?php if (empty($msg['is_hidden'])): ?>
                                <input type="text" name="reason" placeholder="Raison du masquage" class="input" style="width: 200px; display: inline-block; margin-right: 8px;" required>
                                <button class="btn-warning btn-sm" type="submit">🙈 Masquer</button>
                            <?php else: ?>
                                <button class="btn-outline btn-sm" type="submit">👁️ Restaurer</button>
                            <?php endif; ?>
                        </form>
                        
                        <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ Supprimer définitivement ce message ?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="post_id" value="<?= (int)$msg['id_post'] ?>">
                            <button class="btn-danger btn-sm" type="submit">🗑️ Supprimer</button>
                        </form>
                        
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="warn_user">
                            <input type="hidden" name="post_id" value="<?= (int)$msg['id_post'] ?>">
                            <input type="text" name="warn_reason" placeholder="Motif avertissement" class="input" style="width: 180px; display: inline-block; margin-right: 8px;">
                            <button class="btn-outline btn-sm" type="submit">⚠️ Avertir</button>
                        </form>
                        
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="ban_user">
                            <input type="hidden" name="post_id" value="<?= (int)$msg['id_post'] ?>">
                            <select name="ban_duration" class="input" style="width: 100px; display: inline-block; margin-right: 8px;">
                                <option value="1">1 jour</option>
                                <option value="7" selected>7 jours</option>
                                <option value="30">30 jours</option>
                                <option value="365">1 an</option>
                            </select>
                            <button class="btn-danger btn-sm" type="submit">🚫 Bannir</button>
                        </form>
                        
                        <a href="forum_topic.php?id=<?= (int)$msg['id_topic'] ?>" target="_blank" class="btn-outline btn-sm">🔗 Voir le sujet</a>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if ($totalPages > 1): ?>
                <nav style="display: flex; gap: 12px; justify-content: center; margin-top: 30px;">
                    <?php if ($page > 1): ?>
                        <a class="btn-outline" href="?page=<?= $page - 1 ?>&filter=<?= e($filter) ?>&role=<?= e($roleFilter) ?><?= $q ? '&q=' . urlencode($q) : '' ?>">← Précédent</a>
                    <?php endif; ?>
                    <span class="muted">Page <?= $page ?> / <?= $totalPages ?> (<?= $totalMessages ?> messages)</span>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn-outline" href="?page=<?= $page + 1 ?>&filter=<?= e($filter) ?>&role=<?= e($roleFilter) ?><?= $q ? '&q=' . urlencode($q) : '' ?>">Suivant →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<script>
// Autocomplete / suggestions en temps réel
const searchInput = document.getElementById('searchInput');
const suggestionsBox = document.getElementById('suggestions');
let debounceTimer;

const suggestions = <?= json_encode($suggestions); ?>;

function showSuggestions() {
    const query = searchInput.value.trim().toLowerCase();
    if (query.length < 2) {
        suggestionsBox.classList.remove('show');
        return;
    }
    
    // Filtrer les suggestions en fonction de ce qui est tapé
    const filtered = suggestions.filter(s => s.toLowerCase().includes(query));
    
    if (filtered.length > 0) {
        suggestionsBox.innerHTML = filtered.map(s => 
            `<div class="suggestion-item" onclick="selectSuggestion('${s.replace(/'/g, "\\'")}')">🔍 ${s}</div>`
        ).join('');
        suggestionsBox.classList.add('show');
    } else {
        suggestionsBox.classList.remove('show');
    }
}

function selectSuggestion(value) {
    searchInput.value = value;
    suggestionsBox.classList.remove('show');
    // Soumettre automatiquement le formulaire
    searchInput.closest('form').submit();
}

searchInput.addEventListener('input', function() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(showSuggestions, 300);
});

// Cacher les suggestions quand on clique ailleurs
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
        suggestionsBox.classList.remove('show');
    }
});
</script>
<?php  ?>
</body>
</html>