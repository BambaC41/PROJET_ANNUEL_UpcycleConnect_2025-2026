<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/local_db.php';

// Fonction pour traduire les actions en français lisible
function traduireActionAudit($action) {
    $traductions = [
        'TOGGLE_PRESTATION' => '🔁 Activation/Désactivation d\'offre',
        'CREATE_PRESTATION' => '➕ Création d\'offre',
        'UPDATE_PRESTATION' => '✏️ Modification d\'offre',
        'DELETE_PRESTATION' => '🗑️ Suppression d\'offre',
        'VALIDATION_DEPOT' => '✅ Validation de dépôt conteneur',
        'REFUS_DEPOT' => '❌ Rejet de dépôt conteneur',
        'VALIDE_EVENT' => '✅ Validation d\'événement',
        'EN_ATTENTE_EVENT' => '⏳ Mise en attente d\'événement',
        'UPDATE_EVENT' => '✏️ Modification d\'événement',
        'CHANGE_ROLE' => '🔄 Changement de rôle utilisateur',
        'BAN_USER' => '🚫 Bannissement d\'utilisateur',
        'UNBAN_USER' => '🔓 Désactivation de bannissement',
        'APPROVE_PRO' => '✅ Approbation compte professionnel',
        'HIDE_POST' => '🙈 Masquage de message forum',
        'RESTORE_POST' => '👁️ Restauration de message forum',
        'HIDE_TOPIC' => '🙈 Masquage de sujet forum',
        'RESTORE_TOPIC' => '👁️ Restauration de sujet forum',
        'LOCK_TOPIC' => '🔒 Verrouillage de sujet forum',
        'UNLOCK_TOPIC' => '🔓 Déverrouillage de sujet forum',
        'handle_report' => '⚠️ Traitement de signalement',
        'update_category' => '📂 Modification de catégorie forum',
        'delete_category' => '🗑️ Suppression de catégorie forum',
        'create_category' => '➕ Création de catégorie forum',
    ];
    
    // Rechercher une correspondance partielle
    foreach ($traductions as $key => $label) {
        if (stripos($action, $key) !== false) {
            return $label;
        }
    }
    
    return $action ?: 'Action inconnue';
}

$rows = db_safe_exec(fn(PDO $pdo) => $pdo->query('SELECT a.*, u.email FROM audit_log a JOIN utilisateur u ON u.id_user=a.id_user ORDER BY a.id_audit DESC LIMIT 300')->fetchAll(), []);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Journal d'audit</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin.css">
    <?php include 'includes/onesignal_head.php'; ?>
    <style>
        .audit-table {
            width: 100%;
            border-collapse: collapse;
        }
        .audit-table th, .audit-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .audit-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        .audit-table tr:hover {
            background: #fafbfc;
        }
        .action-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .action-success { background: #e8f5e9; color: #2e7d32; }
        .action-warning { background: #fff3e0; color: #ef6c00; }
        .action-danger { background: #fee2e2; color: #dc2626; }
        .action-info { background: #e3f2fd; color: #1565c0; }
        .stats-bar {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 16px 24px;
            border: 1px solid #e5e7eb;
        }
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #2e7d32;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
        }
        .filter-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-btn {
            padding: 6px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 13px;
        }
        .filter-btn.active {
            background: #2e7d32;
            color: white;
        }
        .filter-btn:not(.active) {
            background: #f0f0f0;
            color: #333;
        }
        .search-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            width: 250px;
        }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="pro-shell page-shell">
    <?php include 'includes/flash_toast.php'; ?>
    
    <section class="pro-card">
        <h1>📋 Journal d'audit</h1>
        <p class="muted">Suivi de toutes les actions effectuées sur la plateforme</p>
        
        <?php
        $totalActions = count($rows);
        $today = date('Y-m-d');
        $todayCount = count(array_filter($rows, fn($r) => substr($r['created_at'] ?? '', 0, 10) === $today));
        ?>
        <div class="stats-bar">
            <div class="stat-card">
                <div class="stat-number"><?= $totalActions ?></div>
                <div class="stat-label">Actions totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $todayCount ?></div>
                <div class="stat-label">Aujourd'hui</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count(array_unique(array_column($rows, 'email')))?></div>
                <div class="stat-label">Utilisateurs actifs</div>
            </div>
        </div>
        
        <!-- Filtres rapides -->
        <div class="filter-bar">
            <button class="filter-btn active" onclick="filterTable('all')">📋 Toutes</button>
            <button class="filter-btn" onclick="filterTable('prestation')">🛍️ Offres</button>
            <button class="filter-btn" onclick="filterTable('event')">📅 Événements</button>
            <button class="filter-btn" onclick="filterTable('depot')">📦 Dépôts</button>
            <button class="filter-btn" onclick="filterTable('user')">👥 Utilisateurs</button>
            <button class="filter-btn" onclick="filterTable('forum')">💬 Forum</button>
            <input type="text" id="searchInput" class="search-input" placeholder="🔍 Rechercher..." onkeyup="searchTable()">
        </div>
        
        <div class="table-responsive">
            <table class="audit-table" id="auditTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Utilisateur</th>
                        <th>Action</th>
                        <th>Cible</th>
                        <th>Détails</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): 
                    $action = $r['action'] ?? '';
                    $actionFr = traduireActionAudit($action);
                    $actionClass = 'action-info';
                    if (strpos($action, 'VALIDATION') !== false || strpos($action, 'APPROVE') !== false || strpos($action, 'UNLOCK') !== false || strpos($action, 'RESTORE') !== false) {
                        $actionClass = 'action-success';
                    } elseif (strpos($action, 'BAN') !== false || strpos($action, 'DELETE') !== false || strpos($action, 'REJET') !== false || strpos($action, 'HIDE') !== false || strpos($action, 'LOCK') !== false) {
                        $actionClass = 'action-danger';
                    } elseif (strpos($action, 'PENDING') !== false || strpos($action, 'TOGGLE') !== false) {
                        $actionClass = 'action-warning';
                    }
                    $cibleType = $r['cible_type'] ?? '';
                    $cibleLabel = '';
                    switch($cibleType) {
                        case 'prestation': $cibleLabel = '🛍️ Offre'; break;
                        case 'event': $cibleLabel = '📅 Événement'; break;
                        case 'demande_depot': $cibleLabel = '📦 Dépôt conteneur'; break;
                        case 'utilisateur': $cibleLabel = '👤 Utilisateur'; break;
                        case 'topic': $cibleLabel = '💬 Sujet forum'; break;
                        case 'post': $cibleLabel = '💬 Message forum'; break;
                        case 'category': $cibleLabel = '📂 Catégorie'; break;
                        case 'report': $cibleLabel = '⚠️ Signalement'; break;
                        default: $cibleLabel = $cibleType ?: '—';
                    }
                ?>
                    <tr data-type="<?= e($cibleType) ?>">
                        <td><?= e(formatDateFr($r['created_at'] ?? '')) ?></td>
                        <td><strong><?= e($r['email'] ?? 'Inconnu') ?></strong></td>
                        <td><span class="action-badge <?= $actionClass ?>"><?= e($actionFr) ?></span></td>
                        <td><?= e($cibleLabel . ' #' . ($r['cible_id'] ?? '')) ?></td>
                        <td><?= e(mb_substr($r['details'] ?? '', 0, 100)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($rows)): ?>
            <div class="empty-state" style="text-align:center; padding:60px;">📭 Aucune action enregistrée</div>
        <?php endif; ?>
    </section>
</main>

<script>
function filterTable(type) {
    const rows = document.querySelectorAll('#auditTable tbody tr');
    const btns = document.querySelectorAll('.filter-btn');
    btns.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    rows.forEach(row => {
        if (type === 'all') {
            row.style.display = '';
        } else {
            const rowType = row.getAttribute('data-type') || '';
            if (rowType === type) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

function searchTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const rows = document.querySelectorAll('#auditTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>
<?php  ?>
</body>
</html>