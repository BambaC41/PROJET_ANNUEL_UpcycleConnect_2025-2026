<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/notifications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_annonce_id'])) {
    $annonceId = (int)$_POST['delete_annonce_id'];
    $selfId = (int)$_SESSION['user_id'];
    
    $annonce = db_safe_exec(function(PDO $pdo) use ($annonceId) {
        $stmt = $pdo->prepare('SELECT * FROM annonce WHERE id_annonce = ?');
        $stmt->execute([$annonceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }, null);
    
    if ($annonce) {
        $statut = $annonce['statut'] ?? '';
        $ownerId = (int)($annonce['id_user'] ?? 0);
        
        if ($statut === 'validee') {
            db_safe_exec(function(PDO $pdo) use ($annonceId, $selfId) {
                $stmt = $pdo->prepare('UPDATE annonce SET statut = "rejetee" WHERE id_annonce = ?');
                $stmt->execute([$annonceId]);
                
                $audit = $pdo->prepare('INSERT INTO audit_log (id_user, action, cible_type, cible_id, details, created_at) VALUES (?, "ADMIN_DELETE_ANNONCE", "annonce", ?, "Suppression administrative - annonce désactivée", NOW())');
                $audit->execute([$selfId, $annonceId]);
                return true;
            }, false);
            
            notif_create($ownerId, 'annonce', 'Annonce supprimée par l\'administration', 'Votre annonce a été supprimée par l\'administration.');
            $_SESSION['flash_toast'] = ['type' => 'warning', 'message' => '⚠️ Annonce validée désactivée.'];
        } else {
            db_safe_exec(function(PDO $pdo) use ($annonceId, $selfId) {
                $stmt = $pdo->prepare('DELETE FROM annonce WHERE id_annonce = ?');
                $stmt->execute([$annonceId]);
                
                $audit = $pdo->prepare('INSERT INTO audit_log (id_user, action, cible_type, cible_id, details, created_at) VALUES (?, "ADMIN_DELETE_ANNONCE", "annonce", ?, "Suppression administrative définitive", NOW())');
                $audit->execute([$selfId, $annonceId]);
                return true;
            }, false);
            
            notif_create($ownerId, 'annonce', 'Annonce supprimée par l\'administration', 'Votre annonce a été supprimée par l\'administration.');
            $_SESSION['flash_toast'] = ['type' => 'success', 'message' => '🗑️ Annonce supprimée définitivement.'];
        }
    }
    
    header('Location: admin_annonces_all.php');
    exit();
}

$query = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$mode = trim((string)($_GET['mode'] ?? 'all'));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

$annonces = (array)db_safe_exec(function(PDO $pdo) use ($query, $mode, $page, $perPage) {
    $sql = "SELECT a.*, u.pseudo, u.email, u.id_user as user_id,
            o.titre as objet_titre, o.photo_url as objet_photo
            FROM annonce a
            LEFT JOIN utilisateur u ON u.id_user = a.id_user
            LEFT JOIN objet o ON o.id_objet = a.id_objet
            WHERE 1=1";
    $params = [];
    
    if ($query !== '') {
        $sql .= " AND (a.titre LIKE ? OR a.description LIKE ? OR u.pseudo LIKE ?)";
        $like = "%$query%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    
    if ($mode !== 'all') {
        $sql .= " AND a.mode = ?";
        $params[] = $mode;
    }
    
    $sql .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = ($page - 1) * $perPage;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}, []);

$total = (int)db_safe_exec(function(PDO $pdo) use ($query, $mode) {
    $sql = "SELECT COUNT(*) FROM annonce a
            LEFT JOIN utilisateur u ON u.id_user = a.id_user
            WHERE 1=1";
    $params = [];
    
    if ($query !== '') {
        $sql .= " AND (a.titre LIKE ? OR a.description LIKE ? OR u.pseudo LIKE ?)";
        $like = "%$query%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    
    if ($mode !== 'all') {
        $sql .= " AND a.mode = ?";
        $params[] = $mode;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}, 0);

$totalPages = max(1, (int)ceil($total / $perPage));

$stats = (array)db_safe_exec(function(PDO $pdo) {
    $stats = [];
    $st = $pdo->query("SELECT COUNT(*) FROM annonce");
    $stats['total'] = (int)$st->fetchColumn();
    $st = $pdo->query("SELECT COUNT(*) FROM annonce WHERE statut = 'validee'");
    $stats['validee'] = (int)$st->fetchColumn();
    $st = $pdo->query("SELECT COUNT(*) FROM annonce WHERE statut = 'en_attente'");
    $stats['en_attente'] = (int)$st->fetchColumn();
    $st = $pdo->query("SELECT COUNT(*) FROM annonce WHERE statut = 'rejetee'");
    $stats['rejetee'] = (int)$st->fetchColumn();
    $st = $pdo->query("SELECT COUNT(*) FROM annonce WHERE statut NOT IN ('validee', 'en_attente', 'rejetee')");
    $stats['autres'] = (int)$st->fetchColumn();
    $st = $pdo->query("SELECT COUNT(*) FROM annonce WHERE mode = 'don'");
    $stats['don'] = (int)$st->fetchColumn();
    $st = $pdo->query("SELECT COUNT(*) FROM annonce WHERE mode = 'vente'");
    $stats['vente'] = (int)$st->fetchColumn();
    return $stats;
}, ['total' => 0, 'validee' => 0, 'en_attente' => 0, 'rejetee' => 0, 'autres' => 0, 'don' => 0, 'vente' => 0]);

function annonce_status_badge($statut) {
    $map = [
        'en_attente' => ['class' => 'status-warn', 'label' => '⏳ En attente'],
        'validee' => ['class' => 'status-ok', 'label' => '✅ Validée'],
        'rejetee' => ['class' => 'status-danger', 'label' => '❌ Rejetée'],
    ];
    
    if (isset($map[$statut])) {
        $info = $map[$statut];
    } else {
        $info = ['class' => 'status-info', 'label' => $statut];
    }
    return '<span class="status-badge ' . $info['class'] . '">' . $info['label'] . '</span>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Toutes les annonces</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>

<main class="pro-shell page-shell">
  
    <section class="pro-card">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
            <h1 style="margin:0;">📦 Toutes les annonces</h1>
            <a href="admin_annonces.php" class="btn-outline" style="padding:8px 16px;font-size:13px;">
                ⬅ Retour aux annonces à valider
            </a>
        </div>
        <p class="text-muted">Cliquez sur une annonce pour voir les détails</p>
        
        <div style="display:flex;gap:16px;margin-top:16px;flex-wrap:wrap;">
            <div style="background:#f8f9fa;border-radius:12px;padding:12px 20px;text-align:center;flex:1;min-width:80px;">
                <div style="font-size:24px;font-weight:700;color:#2e7d32;"><?= (int)$stats['total'] ?></div>
                <div style="font-size:11px;color:#666;">Total</div>
            </div>
            <div style="background:#f8f9fa;border-radius:12px;padding:12px 20px;text-align:center;flex:1;min-width:80px;">
                <div style="font-size:24px;font-weight:700;color:#2e7d32;"><?= (int)$stats['validee'] ?></div>
                <div style="font-size:11px;color:#666;">✅ Validées</div>
            </div>
            <div style="background:#f8f9fa;border-radius:12px;padding:12px 20px;text-align:center;flex:1;min-width:80px;">
                <div style="font-size:24px;font-weight:700;color:#ef6c00;"><?= (int)$stats['en_attente'] ?></div>
                <div style="font-size:11px;color:#666;">⏳ En attente</div>
            </div>
            <div style="background:#f8f9fa;border-radius:12px;padding:12px 20px;text-align:center;flex:1;min-width:80px;">
                <div style="font-size:24px;font-weight:700;color:#dc2626;"><?= (int)$stats['rejetee'] ?></div>
                <div style="font-size:11px;color:#666;">❌ Rejetées</div>
            </div>
            <div style="background:#f8f9fa;border-radius:12px;padding:12px 20px;text-align:center;flex:1;min-width:80px;">
                <div style="font-size:24px;font-weight:700;color:#1565c0;"><?= (int)$stats['autres'] ?></div>
                <div style="font-size:11px;color:#666;">🔀 Autres (vendu...)</div>
            </div>
        </div>
    </section>


    <section class="pro-card">
        <form method="GET" class="row-actions" style="margin-bottom:0;">
            <input class="input" type="search" name="q" placeholder="Rechercher..." value="<?= e($query) ?>" style="flex:1;">
            <select class="input" name="mode" style="width:120px;">
                <option value="all" <?= $mode==='all'?'selected':'' ?>>Tous</option>
                <option value="don" <?= $mode==='don'?'selected':'' ?>>Don</option>
                <option value="vente" <?= $mode==='vente'?'selected':'' ?>>Vente</option>
            </select>
            <button class="btn-outline" type="submit">Filtrer</button>
            <a href="admin_annonces_all.php" class="btn-outline">Réinitialiser</a>
        </form>
    </section>

    <section class="pro-card">
        <h2 style="font-size:18px;margin-top:0;">📋 Liste des annonces</h2>
        
        <?php if (empty($annonces)): ?>
            <div class="empty-state">
                <div class="icon">📭</div>
                <p>Aucune annonce trouvée.</p>
            </div>
        <?php else: ?>
            <div class="pro-grid">
                <?php foreach ($annonces as $a): 
                    $statut = (string)($a['statut'] ?? 'en_attente');
                    
                    $statutLabel = match($statut) {
                        'en_attente' => '⏳ En attente',
                        'validee' => '✅ Validée',
                        'rejetee' => '❌ Rejetée',
                        default => $statut
                    };
                    $modeLabel = ($a['mode'] ?? '') === 'don' ? '🎁 Don' : '💰 Vente';
                    $prix = (float)($a['prix'] ?? 0);

                    $photo = !empty($a['photo_url']) ? vc_media_url($a['photo_url']) : (!empty($a['objet_photo']) ? vc_media_url($a['objet_photo']) : '');
                    $auteur = !empty($a['pseudo']) ? $a['pseudo'] : '#' . ($a['id_user'] ?? 0);
                ?>
                    <div class="pro-card annonce-clickable" 
                         style="margin:0;cursor:pointer;"
                         onclick='showAdminAnnonceModal(<?= htmlspecialchars(json_encode([
                             'id' => (int)($a['id_annonce'] ?? 0),
                             'titre' => $a['titre'] ?? 'Sans titre',
                             'description' => $a['description'] ?? '',
                             'mode' => $a['mode'] ?? 'don',
                             'prix' => $prix,
                             'statut' => $statut,
                             'statut_label' => $statutLabel,
                             'date' => formatDateFr($a['created_at'] ?? ''),
                             'photo_url' => $photo,
                             'auteur' => $auteur,
                             'email' => $a['email'] ?? '',
                             'objet_titre' => $a['objet_titre'] ?? ''
                         ]), JSON_HEX_TAG) ?>)'>
                        
                        <?php if (!empty($photo)): ?>
                            <img src="<?= e($photo) ?>" alt="<?= e($a['titre'] ?? 'Annonce') ?>" style="width:100%;height:160px;object-fit:cover;border-radius:10px;margin-bottom:12px;">
                        <?php else: ?>
                            <div style="width:100%;height:160px;background:#f0f0f0;border-radius:10px;margin-bottom:12px;display:flex;align-items:center;justify-content:center;color:#999;">
                                📷 Pas d'image
                            </div>
                        <?php endif; ?>
                        
                        <h3 style="margin:0 0 8px 0;font-size:18px;"><?= e($a['titre'] ?? 'Sans titre') ?></h3>
                        <p style="margin:0 0 8px 0;color:#666;font-size:13px;">
                            <?= e(mb_strimwidth((string)($a['description'] ?? ''), 0, 100, '...')) ?>
                        </p>
                        <p style="margin:0;">
                            <strong><?= $modeLabel ?></strong> - 
                            <?= ($a['mode'] ?? '') === 'vente' ? e(formatPriceEur($prix)) : 'Gratuit' ?>
                            <br>
                            <span style="font-size:12px;"><?= annonce_status_badge($statut) ?></span>
                            <span style="font-size:12px;color:#999;margin-left:8px;">📅 <?= e(formatDateFr($a['created_at'] ?? '')) ?></span>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?= e(http_build_query(array_filter(['q' => $query, 'mode' => $mode !== 'all' ? $mode : null, 'page' => $page - 1]))) ?>">← Précédent</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="active"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?<?= e(http_build_query(array_filter(['q' => $query, 'mode' => $mode !== 'all' ? $mode : null, 'page' => $i]))) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= e(http_build_query(array_filter(['q' => $query, 'mode' => $mode !== 'all' ? $mode : null, 'page' => $page + 1]))) ?>">Suivant →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div style="margin-top:16px;font-size:12px;color:#999;text-align:center;">
                <?= (int)$total ?> annonce(s) trouvée(s) - Page <?= (int)$page ?> / <?= (int)$totalPages ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<div id="adminAnnonceModal" class="modal-annonce" onclick="closeAdminAnnonceModal()">
    <div class="modal-annonce-content" onclick="event.stopPropagation()">
        <span class="modal-annonce-close" onclick="closeAdminAnnonceModal()">&times;</span>
        <div class="modal-image-container">
            <img id="adminModalAnnonceImg" class="modal-annonce-img" src="" alt="">
        </div>
        <div class="modal-body">
            <h2 id="adminModalAnnonceTitre"></h2>
            <div class="modal-info-row">
                <div class="modal-info-label">👤 Auteur :</div>
                <div class="modal-info-value" id="adminModalAnnonceAuteur"></div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label">💰 Mode :</div>
                <div class="modal-info-value" id="adminModalAnnonceMode"></div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label">💶 Prix :</div>
                <div class="modal-info-value" id="adminModalAnnoncePrix"></div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label">📌 Statut :</div>
                <div class="modal-info-value" id="adminModalAnnonceStatut"></div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label">📅 Date :</div>
                <div class="modal-info-value" id="adminModalAnnonceDate"></div>
            </div>
            <?php if (!empty($a['objet_titre'] ?? '')): ?>
            <div class="modal-info-row">
                <div class="modal-info-label">📦 Objet :</div>
                <div class="modal-info-value" id="adminModalAnnonceObjet"></div>
            </div>
            <?php endif; ?>
            <div>
                <div class="modal-info-label" style="width:auto;margin-bottom:8px;">📝 Description :</div>
                <div class="modal-description-box" id="adminModalAnnonceDesc"></div>
            </div>
            <div class="modal-actions">
                <form method="POST" id="adminDeleteForm" style="display:inline;">
                    <input type="hidden" name="delete_annonce_id" id="adminDeleteAnnonceId">
                    <button type="submit" class="btn-modal btn-modal-danger" id="adminDeleteBtn">
                        🗑️ Supprimer
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/flash_toast.php'; ?>

<script>
let currentAdminAnnonceId = null;
let currentAdminAnnonceStatut = null;

function showAdminAnnonceModal(annonce) {
    currentAdminAnnonceId = annonce.id;
    currentAdminAnnonceStatut = annonce.statut;
    
    document.getElementById('adminModalAnnonceTitre').textContent = annonce.titre || 'Sans titre';
    document.getElementById('adminModalAnnonceAuteur').textContent = annonce.auteur + (annonce.email ? ' (' + annonce.email + ')' : '');
    document.getElementById('adminModalAnnonceMode').textContent = annonce.mode === 'don' ? '🎁 Don' : '💰 Vente';
    document.getElementById('adminModalAnnoncePrix').textContent = annonce.mode === 'vente' ? parseFloat(annonce.prix).toFixed(2) + ' €' : 'Gratuit';
    document.getElementById('adminModalAnnonceStatut').textContent = annonce.statut_label;
    document.getElementById('adminModalAnnonceDate').textContent = annonce.date;
    document.getElementById('adminModalAnnonceDesc').textContent = annonce.description || 'Aucune description';
    
    const objetRow = document.getElementById('adminModalAnnonceObjet');
    if (annonce.objet_titre) {
        objetRow.textContent = annonce.objet_titre;
        objetRow.parentElement.style.display = 'flex';
    } else if (objetRow) {
        objetRow.parentElement.style.display = 'none';
    }
    
    const img = document.getElementById('adminModalAnnonceImg');
    if (annonce.photo_url && annonce.photo_url !== '' && annonce.photo_url !== 'null') {
        img.src = annonce.photo_url;
        img.style.display = 'block';
    } else {
        img.src = '';
        img.style.display = 'block';
    }
    
    const deleteBtn = document.getElementById('adminDeleteBtn');
    const deleteInput = document.getElementById('adminDeleteAnnonceId');
    deleteInput.value = annonce.id;
    
    if (annonce.statut === 'validee') {
        deleteBtn.textContent = '🔒 Désactiver (validée)';
        deleteBtn.className = 'btn-modal btn-modal-warning';
    } else {
        deleteBtn.textContent = '🗑️ Supprimer définitivement';
        deleteBtn.className = 'btn-modal btn-modal-danger';
    }
    
    document.getElementById('adminAnnonceModal').classList.add('active');
}

function closeAdminAnnonceModal() {
    document.getElementById('adminAnnonceModal').classList.remove('active');
    currentAdminAnnonceId = null;
    currentAdminAnnonceStatut = null;
}

document.getElementById('adminDeleteForm').addEventListener('submit', function(e) {
    const id = document.getElementById('adminDeleteAnnonceId').value;
    const statut = currentAdminAnnonceStatut;
    const titre = document.getElementById('adminModalAnnonceTitre').textContent || 'cette annonce';
    
    let message;
    if (statut === 'validee') {
        message = `⚠️ L'annonce "${titre}" est VALIDÉE.\n\nLa désactiver la rendra non visible et enverra une notification au propriétaire.\n\nConfirmez-vous cette action ?`;
    } else {
        message = `⚠️ Êtes-vous sûr de vouloir supprimer définitivement l'annonce "${titre}" ?\n\nCette action est irréversible.`;
    }
    
    if (!confirm(message)) {
        e.preventDefault();
        return false;
    }
    return true;
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAdminAnnonceModal();
    }
});

window.onclick = function(e) {
    const modal = document.getElementById('adminAnnonceModal');
    if (e.target === modal) {
        closeAdminAnnonceModal();
    }
}
</script>

</body>
</html>