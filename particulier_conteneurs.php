<?php
require_once 'includes/particulier_bootstrap.php';
require_once 'includes/functions/qr.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/notifications.php';

// États prédéfinis pour les objets
$etatsDisponibles = [
    'comme_neuf' => '🟢 Comme neuf',
    'tres_bon' => '🟢 Très bon état',
    'bon' => '🟡 Bon état',
    'correct' => '🟠 État correct',
    'a_renover' => '🔴 À rénover',
    'pieces' => '⚫ Pour pièces'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_demande'])) {
    $description = trim((string)($_POST['description'] ?? ''));
    $etat = trim((string)($_POST['etat'] ?? ''));
    
    // Limitation description 300 caractères
    if (mb_strlen($description) > 300) {
        $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'La description ne peut pas dépasser 300 caractères.'];
        header('Location: particulier_conteneurs.php');
        exit;
    }
    
    // Validation de l'état
    if (!array_key_exists($etat, $etatsDisponibles)) {
        $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Veuillez sélectionner un état valide.'];
        header('Location: particulier_conteneurs.php');
        exit;
    }
    
    $payload = [
        'id_conteneur' => (int)($_POST['id_conteneur'] ?? 0),
        'titre' => trim((string)($_POST['titre'] ?? '')),
        'description' => $description,
        'etat' => $etat,
        'type_materiau' => trim((string)($_POST['type_materiau'] ?? '')),
        'poids' => (float)($_POST['poids'] ?? 0),
    ];
    
    $res = api_create_demande_depot($payload);
    $ok = (($res['status'] ?? 0) === 201);
    $_SESSION['flash_toast'] = $ok
        ? ['type' => 'success', 'message' => 'Demande de dépôt créée.']
        : ['type' => 'error', 'message' => 'Création demande impossible.'];
    if ($ok) {
        notif_create((int)$_SESSION['user_id'], 'depot', 'Demande de dépôt', 'Votre demande est en attente de validation par l\'équipe.');
        notif_notify_roles([1], 'depot', 'Nouvelle demande dépôt', 'Une nouvelle demande de dépôt conteneur a été créée.');
    }
    header('Location: particulier_conteneurs.php');
    exit;
}

$status = trim((string)($_GET['status'] ?? 'all'));
$demandes = api_get_my_demandes_depot()['data'] ?? [];
$conteneursRes = api_get_conteneurs();
$conteneurs = (($conteneursRes['status'] ?? 0) === 200) ? ($conteneursRes['data'] ?? []) : [];
$filtered = array_values(array_filter($demandes, fn($d) => $status === 'all' || (($d['statut'] ?? '') === $status)));

function particulier_depot_statut_label(string $st): string
{
    return match ($st) {
        'en_attente' => 'En attente de validation',
        'validee' => 'Validée',
        'deposee' => 'Déposée',
        'rejetee' => 'Rejetée',
        'retiree' => 'Objet récupéré',
        default => $st,
    };
}

function particulier_depot_badge(string $st): string
{
    return match ($st) {
        'validee', 'deposee' => 'status-ok',
        'en_attente' => 'status-warn',
        'rejetee' => 'status-danger',
        'retiree' => 'status-muted',
        default => 'status-info',
    };
}

function getEtatDisplay(string $etat, array $etats): string
{
    return $etats[$etat] ?? '❓ Inconnu';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conteneurs particulier</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <?php include 'includes/onesignal_head.php'; ?>
    <style>
        .char-counter {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
            text-align: right;
        }
        .char-counter.warning {
            color: #f44336;
        }
        .etat-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .etat-comme_neuf { background: #4caf50; color: white; }
        .etat-tres_bon { background: #8bc34a; color: white; }
        .etat-bon { background: #ffc107; color: #333; }
        .etat-correct { background: #ff9800; color: white; }
        .etat-a_renover { background: #f44336; color: white; }
        .etat-pieces { background: #9e9e9e; color: white; }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/particulier_nav.php'; ?>
<main class="pro-shell page-shell">
<section class="pro-card page-card">
<h1 class="page-header">🗳️ Dépôts conteneur</h1>

<?php if (!empty($_SESSION['flash_toast'])): ?>
    <div class="<?= $_SESSION['flash_toast']['type'] === 'error' ? 'error-box' : 'success-box' ?>">
        <?= e($_SESSION['flash_toast']['message']) ?>
    </div>
    <?php unset($_SESSION['flash_toast']); ?>
<?php endif; ?>

<h2 style="font-size:18px;">Demander un dépôt</h2>
<form method="POST" class="row-actions" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
    <input type="hidden" name="create_demande" value="1">
    
    <label>Titre de l'objet *</label>
    <input class="input" name="titre" placeholder="Objet (titre)" required maxlength="150">
    
    <label>Matériau</label>
    <input class="input" name="type_materiau" placeholder="Matériau" maxlength="100">
    
    <label>État de l'objet *</label>
    <select class="input" name="etat" required>
        <option value="">-- Sélectionnez un état --</option>
        <?php foreach ($etatsDisponibles as $value => $label): ?>
            <option value="<?= e($value) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    
    <label>Poids approximatif (kg)</label>
    <input class="input" type="number" step="0.01" name="poids" placeholder="Poids approximatif">
    
    <label>Conteneur *</label>
    <select class="input" name="id_conteneur" required>
        <option value="">-- Sélectionnez un conteneur --</option>
        <?php foreach ($conteneurs as $c): ?>
            <option value="<?= (int)$c['id_conteneur'] ?>"><?= e($c['code'] ?? '') ?> - <?= e($c['adresse'] ?? '') ?></option>
        <?php endforeach; ?>
    </select>
    
    <label style="grid-column:1/-1;">Description (max 300 caractères)</label>
    <textarea class="input" name="description" placeholder="Description" style="grid-column:1/-1;min-height:90px;" maxlength="300" oninput="updateCharCount(this)"></textarea>
    <div class="char-counter" id="descCounter" style="grid-column:1/-1;">0 / 300 caractères</div>
    
    <button class="btn-primary" type="submit" style="grid-column:1/-1;">📦 Envoyer la demande</button>
</form>

<form method="GET" class="row-actions page-actions">
    <select class="input" name="status">
        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tous</option>
        <option value="en_attente" <?= $status === 'en_attente' ? 'selected' : '' ?>>En attente</option>
        <option value="validee" <?= $status === 'validee' ? 'selected' : '' ?>>Validée</option>
        <option value="deposee" <?= $status === 'deposee' ? 'selected' : '' ?>>Déposée</option>
        <option value="rejetee" <?= $status === 'rejetee' ? 'selected' : '' ?>>Rejetée</option>
        <option value="retiree" <?= $status === 'retiree' ? 'selected' : '' ?>>Récupérée</option>
    </select>
    <button class="btn-outline" type="submit">Filtrer</button>
</form>

<div class="table-wrap">
<table class="table">
<thead>
    <tr>
        <th>Objet</th>
        <th>État</th>
        <th>Conteneur</th>
        <th>Statut</th>
        <th>Code d'accès</th>
        <th>QR</th>
        <th>Document</th>
    </tr>
</thead>
<tbody>
<?php foreach ($filtered as $d): ?>
<?php 
$codesRes = api_get_demande_codes((int)($d['id_demande'] ?? 0)); 
$codes = (($codesRes['status'] ?? 0) === 200) ? ($codesRes['data'] ?? null) : null; 
$docId = (int)db_safe_exec(function (PDO $pdo) use ($d) {
    $s = $pdo->prepare('SELECT id_document FROM document_genere WHERE id_demande=? ORDER BY id_document DESC LIMIT 1');
    $s->execute([(int)$d['id_demande']]);
    return (int)$s->fetchColumn();
}, 0);
$st = (string)($d['statut'] ?? '');
$payloadQr = '';
if (is_array($codes)) {
    $payloadQr = trim((string)($codes['barcode_value'] ?: ($codes['code_acces'] ?? '')));
}
$showQr = $payloadQr !== '' && in_array($st, ['validee', 'deposee'], true);
$etatDisplay = getEtatDisplay($d['etat'] ?? '', $etatsDisponibles);
$etatClass = match($d['etat'] ?? '') {
    'comme_neuf' => 'etat-comme_neuf',
    'tres_bon' => 'etat-tres_bon',
    'bon' => 'etat-bon',
    'correct' => 'etat-correct',
    'a_renover' => 'etat-a_renover',
    'pieces' => 'etat-pieces',
    default => ''
};
?>
<tr>
    <td><strong><?= e($d['titre'] ?? '') ?></strong><br><small class="muted"><?= e(mb_substr($d['description'] ?? '', 0, 100)) ?>...</small></td>
    <td><span class="etat-badge <?= e($etatClass) ?>"><?= e($etatDisplay) ?></span></td>
    <td><?= e($d['code_conteneur'] ?? '') ?></td>
    <td><span class="status-badge <?= e(particulier_depot_badge($st)) ?>"><?= e(particulier_depot_statut_label($st)) ?></span></td>
    <td><?= e(is_array($codes) ? ($codes['code_acces'] ?? '-') : '-') ?></td>
    <td>
    <?php if ($showQr): ?>
        <div style="width:120px;"><?= qr_svg_string($payloadQr) ?></div>
        <p style="margin:6px 0 4px;"><code><?= e($payloadQr) ?></code></p>
        <button class="btn-outline" type="button" data-copy="<?= e($payloadQr) ?>" onclick="navigator.clipboard.writeText(this.getAttribute('data-copy'))">Copier</button>
    <?php elseif ($st === 'en_attente'): ?>
        <span class="muted">En attente</span>
    <?php elseif ($st === 'rejetee'): ?>
        <span class="status-badge status-danger">Rejetée</span>
    <?php elseif ($st === 'retiree'): ?>
        <span class="status-badge status-muted">Récupéré</span>
    <?php else: ?>
        <span class="muted">—</span>
    <?php endif; ?>
    </td>
    <td>
    <?php if ($docId > 0): ?>
        <a class="btn-outline" href="document_download.php?id=<?= $docId ?>">📄 PDF</a>
    <?php else: ?>—<?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($filtered)): ?>
<tr><td colspan="7" style="text-align:center;">Aucune demande de dépôt</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</section>
</main>
<script>
function updateCharCount(textarea) {
    let len = textarea.value.length;
    let counter = document.getElementById('descCounter');
    if (counter) {
        counter.textContent = len + ' / 300 caractères';
        if (len >= 300) {
            counter.classList.add('warning');
        } else {
            counter.classList.remove('warning');
        }
    }
}
// Initialiser le compteur
const textarea = document.querySelector('textarea[name="description"]');
if (textarea) {
    updateCharCount(textarea);
    textarea.addEventListener('input', function() { updateCharCount(this); });
}
</script>
<?php include 'includes/flash_toast.php'; ?>
<?php  ?>
</body>
</html>