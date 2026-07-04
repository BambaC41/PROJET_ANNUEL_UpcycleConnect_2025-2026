<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/employee_bootstrap.php';
require_once __DIR__ . '/includes/functions/local_db.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/ui_helpers.php';

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_conseil'])) {
    $titre = trim((string)($_POST['titre'] ?? ''));
    $contenu = trim((string)($_POST['contenu'] ?? ''));
    $categorie = trim((string)($_POST['categorie'] ?? ''));
    $imageUrl = '';
    
    $errors = [];
    if (empty($titre)) $errors[] = "Le titre est requis.";
    if (empty($contenu)) $errors[] = "Le contenu est requis.";
    if (empty($categorie)) $errors[] = "La catégorie est requise.";
    if (mb_strlen($contenu) > 5000) $errors[] = "Le contenu ne peut pas dépasser 5000 caractères.";
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/conseils/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        $size = (int)($_FILES['image']['size'] ?? 0);
        
        if (in_array($mimeType, $allowedTypes) && $size > 0 && $size <= 5 * 1024 * 1024) {
            $ext = match($mimeType) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg'
            };
            $filename = 'c_' . $userId . '_' . date('YmdHis') . '_' . rand(1000, 9999) . '.' . $ext;
            $destination = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $imageUrl = 'uploads/conseils/' . $filename;
            }
        }
    } elseif (!empty($_POST['image_url'])) {
        $imageUrl = trim((string)($_POST['image_url'] ?? ''));
    }
    
    if (empty($errors)) {
        $payload = [
            'titre' => $titre,
            'contenu' => $contenu,
            'categorie' => $categorie,
            'image_url' => $imageUrl,
            'is_active' => false,
        ];
        
        $res = api_create_conseil($payload);
        
        if (($res['status'] ?? 0) === 201) {
            $nid = (int)($res['data']['id_conseil'] ?? 0);
            if ($nid > 0) {
                db_safe_exec(function (PDO $pdo) use ($nid, $userId) {
                    $st = $pdo->prepare('UPDATE conseil SET id_auteur = ? WHERE id_conseil = ? AND (id_auteur IS NULL OR id_auteur = ?)');
                    $st->execute([$userId, $nid, $userId]);
                });
            }
            notif_create($userId, 'conseil', 'Conseil créé', 'Votre conseil est en brouillon, en attente de validation.');
            notif_notify_roles([1], 'conseil', 'Conseil à valider', 'Un salarié a soumis un conseil en brouillon.');
            toast_redirect('salarie_conseils.php', 'success', '✅ Conseil créé en brouillon.');
        }
        
        $localId = (int)db_safe_exec(function (PDO $pdo) use ($titre, $contenu, $categorie, $imageUrl, $userId) {
            $st = $pdo->prepare('INSERT INTO conseil (titre, contenu, categorie, image_url, is_active, id_auteur) VALUES (?, ?, ?, ?, 0, ?)');
            $st->execute([$titre, $contenu, $categorie, $imageUrl, $userId]);
            return (int)$pdo->lastInsertId();
        }, 0);
        
        if ($localId > 0) {
            notif_create($userId, 'conseil', 'Conseil créé (local)', 'Conseil enregistré en brouillon.');
            notif_notify_roles([1], 'conseil', 'Conseil à valider', 'Un salarié a créé un conseil en brouillon.');
            toast_redirect('salarie_conseils.php', 'success', '✅ Conseil créé en brouillon.');
        }
        
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        toast_redirect('salarie_conseils.php', 'error', '❌ Création impossible.');
    } else {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header('Location: salarie_conseils.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_conseil'])) {
    $id = (int)$_POST['conseil_id'];
    $titre = trim((string)($_POST['titre'] ?? ''));
    $contenu = trim((string)($_POST['contenu'] ?? ''));
    $categorie = trim((string)($_POST['categorie'] ?? ''));
    $imageUrl = trim((string)($_POST['image_url'] ?? ''));
    
    $isStillDraft = (bool)db_safe_exec(function (PDO $pdo) use ($id, $userId) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM conseil WHERE id_conseil = ? AND id_auteur = ? AND is_active = 0');
        $st->execute([$id, $userId]);
        return $st->fetchColumn() > 0;
    }, false);
    
    if (!$isStillDraft) {
        toast_redirect('salarie_conseils.php', 'error', '❌ Ce conseil a déjà été publié, vous ne pouvez plus le modifier.');
    }
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/conseils/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        $size = (int)($_FILES['image']['size'] ?? 0);
        
        if (in_array($mimeType, $allowedTypes) && $size > 0 && $size <= 5 * 1024 * 1024) {
            $ext = match($mimeType) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg'
            };
            $filename = 'c_' . $userId . '_' . date('YmdHis') . '_' . rand(1000, 9999) . '.' . $ext;
            $destination = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $imageUrl = 'uploads/conseils/' . $filename;
            }
        }
    }
    
    if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
        $oldImage = '';
        db_safe_exec(function (PDO $pdo) use ($id, &$oldImage) {
            $st = $pdo->prepare('SELECT image_url FROM conseil WHERE id_conseil = ?');
            $st->execute([$id]);
            $oldImage = (string)$st->fetchColumn();
            return true;
        }, false);
        if (!empty($oldImage) && file_exists(__DIR__ . '/' . $oldImage)) {
            unlink(__DIR__ . '/' . $oldImage);
        }
        $imageUrl = '';
    }
    
    $payload = [
        'titre' => $titre,
        'contenu' => $contenu,
        'categorie' => $categorie,
        'image_url' => $imageUrl,
        'is_active' => false,
    ];
    
    $res = api_update_conseil($id, $payload);
    if (($res['status'] ?? 0) === 200) {
        toast_redirect('salarie_conseils.php', 'success', '✅ Conseil mis à jour.');
    }
    
    $ok = (bool)db_safe_exec(function (PDO $pdo) use ($id, $userId, $payload) {
        $st = $pdo->prepare('UPDATE conseil SET titre = ?, contenu = ?, categorie = ?, image_url = ?, is_active = 0 WHERE id_conseil = ? AND id_auteur = ?');
        return $st->execute([$payload['titre'], $payload['contenu'], $payload['categorie'], $payload['image_url'], $id, $userId]);
    }, false);
    
    if ($ok) {
        toast_redirect('salarie_conseils.php', 'success', '✅ Conseil mis à jour.');
    }
    toast_redirect('salarie_conseils.php', 'error', '❌ Mise à jour impossible.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_conseil'])) {
    $id = (int)$_POST['conseil_id'];
    
    $isStillDraft = (bool)db_safe_exec(function (PDO $pdo) use ($id, $userId) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM conseil WHERE id_conseil = ? AND id_auteur = ? AND is_active = 0');
        $st->execute([$id, $userId]);
        return $st->fetchColumn() > 0;
    }, false);
    
    if (!$isStillDraft) {
        toast_redirect('salarie_conseils.php', 'error', '❌ Ce conseil a déjà été publié, vous ne pouvez pas le supprimer.');
    }
    
    $imageToDelete = '';
    db_safe_exec(function (PDO $pdo) use ($id, &$imageToDelete) {
        $st = $pdo->prepare('SELECT image_url FROM conseil WHERE id_conseil = ?');
        $st->execute([$id]);
        $imageToDelete = (string)$st->fetchColumn();
        return true;
    }, false);
    
    $delRes = api_delete_conseil($id);
    if (($delRes['status'] ?? 0) === 200) {
        if (!empty($imageToDelete) && file_exists(__DIR__ . '/' . $imageToDelete)) {
            unlink(__DIR__ . '/' . $imageToDelete);
        }
        toast_redirect('salarie_conseils.php', 'success', '✅ Conseil supprimé.');
    }
    
    $ok = (bool)db_safe_exec(function (PDO $pdo) use ($id, $userId) {
        $st = $pdo->prepare('DELETE FROM conseil WHERE id_conseil = ? AND id_auteur = ? AND is_active = 0');
        return $st->execute([$id, $userId]);
    }, false);
    
    if ($ok) {
        if (!empty($imageToDelete) && file_exists(__DIR__ . '/' . $imageToDelete)) {
            unlink(__DIR__ . '/' . $imageToDelete);
        }
        toast_redirect('salarie_conseils.php', 'success', '✅ Conseil supprimé.');
    }
    toast_redirect('salarie_conseils.php', 'error', '❌ Suppression impossible.');
}

$apiMine = [];
$resMine = api_get_my_conseils();
if (($resMine['status'] ?? 0) === 200) {
    $apiMine = is_array($resMine['data']) ? $resMine['data'] : [];
}
$mine = salarie_conseils_merge_local($apiMine, $userId);

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? 'all'));

$formErrors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);

$filtered = [];
foreach ($mine as $c) {
    $isActive = !empty($c['is_active']);
    if ($status === 'active' && !$isActive) continue;
    if ($status === 'draft' && $isActive) continue;
    $hay = strtolower(($c['titre'] ?? '') . ' ' . ($c['categorie'] ?? '') . ' ' . ($c['contenu'] ?? ''));
    if ($q !== '' && !str_contains($hay, strtolower($q))) continue;
    $filtered[] = $c;
}

$stats = [
    'total' => count($mine),
    'draft' => count(array_filter($mine, fn($c) => empty($c['is_active']))),
    'published' => count(array_filter($mine, fn($c) => !empty($c['is_active']))),
];

function getShortContent($content, $max = 150) {
    if (empty($content)) return '';
    if (mb_strlen($content) <= $max) return $content;
    return mb_substr($content, 0, $max) . '...';
}

function conseilImage($c) {
    if (!empty($c['image_url'])) {
        return '/upcycle/' . $c['image_url'];
    }
    $cat = mb_strtolower($c['categorie'] ?? '');
    if (str_contains($cat, 'bois')) {
        return 'https://images.unsplash.com/photo-1519710164239-da123dc03ef4?auto=format&fit=crop&w=800&q=80';
    }
    if (str_contains($cat, 'velo')) {
        return 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?auto=format&fit=crop&w=800&q=80';
    }
    if (str_contains($cat, 'textile')) {
        return 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=800&q=80';
    }
    return 'https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?auto=format&fit=crop&w=800&q=80';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes conseils - Espace Salarié</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin_global.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include __DIR__ . '/includes/employee_nav.php'; ?>
<?php include __DIR__ . '/includes/flash_toast.php'; ?>

<main class="conseils-page">
    
    <div class="page-header">
        <div>
            <h1>📝 Mes conseils & news</h1>
            <p>Rédigez des astuces et tutoriels pour la communauté</p>
        </div>
        <button class="btn-create" onclick="openCreateModal()">+ Nouveau conseil</button>
    </div>
    
    <div class="stats-row">
        <div class="stat-card"><div class="stat-number"><?= $stats['total'] ?></div><div class="stat-label">Total</div></div>
        <div class="stat-card"><div class="stat-number" style="color:#ff9800;"><?= $stats['draft'] ?></div><div class="stat-label">Brouillons</div></div>
        <div class="stat-card"><div class="stat-number" style="color:#4caf50;"><?= $stats['published'] ?></div><div class="stat-label">Publiés</div></div>
    </div>
    
    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; width: 100%;">
            <input type="text" name="q" placeholder="🔍 Rechercher..." value="<?= e($q) ?>" style="flex: 2; min-width: 150px;">
            <select name="status">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tous</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Brouillons</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Publiés</option>
            </select>
            <button type="submit" style="background:#f0f0f0; border:none; padding:8px 16px; border-radius:30px; cursor:pointer;">Filtrer</button>
            <a href="salarie_conseils.php" style="background:#f0f0f0; color:#333; padding:8px 16px; border-radius:30px; text-decoration:none;">Reset</a>
        </form>
    </div>
    
    <?php if (empty($filtered)): ?>
        <div class="empty-state">
            <div style="font-size: 48px; margin-bottom: 16px;">📭</div>
            <h3>Aucun conseil trouvé</h3>
            <p>Créez votre premier conseil en cliquant sur le bouton ci-dessus.</p>
        </div>
    <?php else: ?>
        <div class="conseils-grid">
            <?php foreach ($filtered as $c): 
                $isActive = !empty($c['is_active']);
                $badgeClass = $isActive ? 'badge-published' : 'badge-draft';
                $badgeText = $isActive ? '✅ Publié' : '🕒 Brouillon';
                $imageUrl = conseilImage($c);
                $shortContent = getShortContent($c['contenu'] ?? '', 150);
            ?>
                <div class="conseil-card" onclick='openConseilModal(<?= htmlspecialchars(json_encode([
                    'id' => $c['id_conseil'],
                    'titre' => $c['titre'] ?? '',
                    'contenu' => $c['contenu'] ?? '',
                    'categorie' => $c['categorie'] ?? 'Non catégorisé',
                    'image_url' => $imageUrl,
                    'date' => formatDateFr($c['created_at'] ?? ''),
                    'is_active' => $isActive,
                ]), JSON_HEX_TAG) ?>)'>
                    <img class="conseil-image" src="<?= e($imageUrl) ?>" alt="<?= e($c['titre'] ?? 'Conseil') ?>">
                    <span class="conseil-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                    <div class="conseil-content">
                        <span class="conseil-category">📖 <?= e($c['categorie'] ?? 'Non catégorisé') ?></span>
                        <h3 class="conseil-title"><?= e(mb_substr($c['titre'] ?? '', 0, 60)) ?></h3>
                        <p class="conseil-preview"><?= e($shortContent) ?></p>
                        <span class="read-more">Lire la suite →</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<div id="createModal" class="modal-conseil" onclick="closeCreateModal()">
    <div class="modal-conseil-content" onclick="event.stopPropagation()">
        <span class="modal-conseil-close" onclick="closeCreateModal()">&times;</span>
        <div class="modal-conseil-image-container" style="background: linear-gradient(135deg, #2e7d32, #4caf50); min-height:150px;">
            <div style="text-align:center; padding:40px 20px;">
                <div style="font-size:48px;">✏️</div>
                <h2 style="margin:16px 0 0 0; color:white;">Créer un conseil</h2>
                <p style="margin:8px 0 0 0; color:rgba(255,255,255,0.9);">Partagez vos astuces et tutoriels</p>
            </div>
        </div>
        <div class="modal-conseil-body">
            <?php if (!empty($formErrors)): ?>
                <div class="error-message">
                    <?php foreach ($formErrors as $err): ?>
                        <div>❌ <?= e($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="create_conseil" value="1">
                <div class="form-group">
                    <label>Titre *</label>
                    <input type="text" name="titre" class="input" required value="<?= e($formData['titre'] ?? '') ?>" placeholder="Titre de votre conseil">
                </div>
                <div class="form-group">
                    <label>Catégorie *</label>
                    <select name="categorie" class="input" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach (conseil_categories_list() as $cat): ?>
                            <option value="<?= e($cat) ?>" <?= (($formData['categorie'] ?? '') === $cat) ? 'selected' : '' ?>><?= e($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Image (optionnel)</label>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="input">
                    <small class="muted">Formats : JPG, PNG, WEBP. Max 5 Mo.</small>
                </div>
                <div class="form-group">
                    <label>Contenu *</label>
                    <textarea name="contenu" class="input" required rows="8" placeholder="Rédigez votre conseil ici..."><?= e($formData['contenu'] ?? '') ?></textarea>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-modal btn-modal-primary">📤 Publier (brouillon)</button>
                    <button type="button" class="btn-modal btn-modal-secondary" onclick="closeCreateModal()">Annuler</button>
                </div>
                <small class="muted" style="display:block; margin-top:12px;">⚠️ Le conseil sera créé en <strong>brouillon</strong> et devra être validé par un administrateur.</small>
            </form>
        </div>
    </div>
</div>

<div id="conseilModal" class="modal-conseil" onclick="closeConseilModal()">
    <div class="modal-conseil-content" onclick="event.stopPropagation()">
        <span class="modal-conseil-close" onclick="closeConseilModal()">&times;</span>
        <div class="modal-conseil-image-container">
            <img id="modalConseilImg" class="modal-conseil-img" src="" alt="">
        </div>
        <div class="modal-conseil-body">
            <h2 id="modalConseilTitre"></h2>
            <div class="modal-info-row">
                <div class="modal-info-label">📂 Catégorie :</div>
                <div class="modal-info-value" id="modalConseilCategory"></div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label">📅 Publié le :</div>
                <div class="modal-info-value" id="modalConseilDate"></div>
            </div>
            <div class="modal-info-row">
                <div class="modal-info-label">📌 Statut :</div>
                <div class="modal-info-value" id="modalConseilStatut"></div>
            </div>
            <div><div class="modal-info-label" style="width:auto;margin-bottom:8px;">📝 Contenu :</div>
            <div class="modal-conseil-content-text" id="modalConseilContenu"></div></div>
            <div class="modal-actions" id="modalActions"></div>
        </div>
        <div id="editMode" style="display:none; padding:24px; border-top:1px solid #eee;">
            <form method="POST" enctype="multipart/form-data" id="editConseilForm">
                <input type="hidden" name="update_conseil" value="1">
                <input type="hidden" name="conseil_id" id="edit_conseil_id">
                <div class="form-group">
                    <label>Titre</label>
                    <input type="text" name="titre" id="edit_titre" class="input" required>
                </div>
                <div class="form-group">
                    <label>Catégorie</label>
                    <select name="categorie" id="edit_categorie" class="input" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach (conseil_categories_list() as $cat): ?>
                            <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>URL de l'image</label>
                    <input type="text" name="image_url" id="edit_image_url" class="input" placeholder="https://... ou /uploads/conseils/...">
                </div>
                <div class="form-group">
                    <label>Nouvelle image</label>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="input">
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="delete_image" value="1"> Supprimer l'image actuelle</label>
                </div>
                <div class="form-group">
                    <label>Contenu</label>
                    <textarea name="contenu" id="edit_contenu" class="input" required rows="8"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-modal btn-modal-primary">💾 Enregistrer</button>
                    <button type="button" class="btn-modal btn-modal-secondary" onclick="toggleEditMode()">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentConseilData = null;

function openCreateModal() {
    document.getElementById('createModal').classList.add('active');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.remove('active');
}

function openConseilModal(conseil) {
    currentConseilData = conseil;
    
    document.getElementById('modalConseilTitre').textContent = conseil.titre;
    document.getElementById('modalConseilContenu').innerHTML = conseil.contenu.replace(/\n/g, '<br>');
    document.getElementById('modalConseilCategory').textContent = conseil.categorie;
    document.getElementById('modalConseilDate').textContent = conseil.date;
    
    let statutHtml = '';
    if (conseil.is_active) {
        statutHtml = '<span style="background:#e8f5e9; color:#2e7d32; padding:4px 12px; border-radius:30px;">✅ Publié</span>';
    } else {
        statutHtml = '<span style="background:#fff3e0; color:#ef6c00; padding:4px 12px; border-radius:30px;">🕒 Brouillon</span>';
    }
    document.getElementById('modalConseilStatut').innerHTML = statutHtml;
    
    const img = document.getElementById('modalConseilImg');
    if (conseil.image_url && conseil.image_url !== '') {
        img.src = conseil.image_url;
        img.style.display = 'block';
    } else {
        img.style.display = 'none';
    }
    
    document.getElementById('edit_conseil_id').value = conseil.id;
    document.getElementById('edit_titre').value = conseil.titre;
    document.getElementById('edit_categorie').value = conseil.categorie;
    document.getElementById('edit_image_url').value = (conseil.image_url && !conseil.image_url.includes('unsplash')) ? conseil.image_url : '';
    document.getElementById('edit_contenu').value = conseil.contenu;
    
    const actionsDiv = document.getElementById('modalActions');
    actionsDiv.innerHTML = '';
    
    if (!conseil.is_active) {
        const editBtn = document.createElement('button');
        editBtn.className = 'btn-modal btn-modal-primary';
        editBtn.innerHTML = '✏️ Modifier';
        editBtn.onclick = () => toggleEditMode();
        actionsDiv.appendChild(editBtn);
        
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'btn-modal btn-modal-danger';
        deleteBtn.innerHTML = '🗑️ Supprimer';
        deleteBtn.onclick = () => confirmDelete();
        actionsDiv.appendChild(deleteBtn);
    }
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'btn-modal btn-modal-secondary';
    closeBtn.innerHTML = 'Fermer';
    closeBtn.onclick = () => closeConseilModal();
    actionsDiv.appendChild(closeBtn);
    
    document.getElementById('editMode').style.display = 'none';
    document.getElementById('conseilModal').classList.add('active');
}

function closeConseilModal() {
    document.getElementById('conseilModal').classList.remove('active');
    currentConseilData = null;
}

function toggleEditMode() {
    if (currentConseilData && currentConseilData.is_active) {
        alert('⚠️ Ce conseil est déjà publié et ne peut plus être modifié.');
        return;
    }
    const editDiv = document.getElementById('editMode');
    if (editDiv.style.display === 'none') {
        editDiv.style.display = 'block';
    } else {
        editDiv.style.display = 'none';
    }
}

function confirmDelete() {
    if (currentConseilData && currentConseilData.is_active) {
        alert('⚠️ Ce conseil est publié et ne peut pas être supprimé.');
        return;
    }
    if (confirm('Êtes-vous sûr de vouloir supprimer définitivement ce conseil ? Cette action est irréversible.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        var input1 = document.createElement('input');
        input1.type = 'hidden';
        input1.name = 'delete_conseil';
        input1.value = '1';
        var input2 = document.createElement('input');
        input2.type = 'hidden';
        input2.name = 'conseil_id';
        input2.value = currentConseilData.id;
        form.appendChild(input1);
        form.appendChild(input2);
        document.body.appendChild(form);
        form.submit();
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeConseilModal();
        closeCreateModal();
    }
});
</script>

<?php include __DIR__ . '/includes/flash_toast.php'; ?>
<?php  ?>
</body>
</html>