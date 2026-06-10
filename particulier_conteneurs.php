<?php
require_once 'includes/particulier_bootstrap.php';
require_once 'includes/functions/qr.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/notifications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_demande'])) {
    $payload = [
        'id_conteneur' => (int)($_POST['id_conteneur'] ?? 0),
        'titre' => trim((string)($_POST['titre'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'etat' => trim((string)($_POST['etat'] ?? '')),
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conteneurs particulier</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
</head>
<body class="pro-page">
<?php include 'includes/particulier_nav.php'; ?>
<main class="pro-shell page-shell">
<section class="pro-card page-card">
<h1 class="page-header">🗳️ Dépôts conteneur</h1>
<h2 style="font-size:18px;">Demander un dépôt</h2>
<form method="POST" class="row-actions" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
<input type="hidden" name="create_demande" value="1">
<input class="input" name="titre" placeholder="Objet (titre)" required>
<input class="input" name="type_materiau" placeholder="Matériau">
<input class="input" name="etat" placeholder="État">
<input class="input" type="number" step="0.01" name="poids" placeholder="Poids approximatif">
<select class="input" name="id_conteneur" required><?php foreach ($conteneurs as $c): ?><option value="<?= (int)$c['id_conteneur'] ?>"><?= e($c['code'] ?? '') ?> - <?= e($c['adresse'] ?? '') ?></option><?php endforeach; ?></select>
<textarea class="input" name="description" placeholder="Description" style="grid-column:1/-1;"></textarea>
<button class="btn-primary" type="submit" style="grid-column:1/-1;">Envoyer la demande</button>
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
<thead><tr><th>Objet</th><th>Conteneur</th><th>Statut</th><th>Code d'accès</th><th>QR</th><th>Document</th></tr></thead>
<tbody>
<?php foreach ($filtered as $d): ?>
<?php $codesRes = api_get_demande_codes((int)($d['id_demande'] ?? 0)); $codes = (($codesRes['status'] ?? 0) === 200) ? ($codesRes['data'] ?? null) : null; ?>
<?php $docId = (int)db_safe_exec(function (PDO $pdo) use ($d) {
    $s = $pdo->prepare('SELECT id_document FROM document_genere WHERE id_demande=? ORDER BY id_document DESC LIMIT 1');
    $s->execute([(int)$d['id_demande']]);
    return (int)$s->fetchColumn();
}, 0); ?>
<?php
$st = (string)($d['statut'] ?? '');
$payloadQr = '';
if (is_array($codes)) {
    $payloadQr = trim((string)($codes['barcode_value'] ?: ($codes['code_acces'] ?? '')));
}
$showQr = $payloadQr !== '' && in_array($st, ['validee', 'deposee'], true);
?>
<tr>
<td><?= e($d['titre'] ?? '') ?></td>
<td><?= e($d['code_conteneur'] ?? '') ?></td>
<td><span class="status-badge <?= e(particulier_depot_badge($st)) ?>"><?= e(particulier_depot_statut_label($st)) ?></span></td>
<td><?= e(is_array($codes) ? ($codes['code_acces'] ?? '-') : '-') ?></td>
<td>
<?php if ($showQr): ?>
<div style="width:120px;"><?= qr_svg_string($payloadQr) ?></div>
<p style="margin:6px 0 4px;"><code><?= e($payloadQr) ?></code></p>
<button class="btn-outline" type="button" data-copy="<?= e($payloadQr) ?>" onclick="navigator.clipboard.writeText(this.getAttribute('data-copy'))">Copier le code</button>
<?php elseif ($st === 'en_attente'): ?>
<span class="muted">Code en attente de validation</span>
<?php elseif ($st === 'rejetee'): ?>
<span class="status-badge status-danger">Demande rejetée</span>
<?php elseif ($st === 'retiree'): ?>
<span class="status-badge status-muted">Objet récupéré</span>
<?php else: ?>
<span class="muted">—</span>
<?php endif; ?>
</td>
<td>
<?php if ($docId > 0): ?>
<a class="btn-outline" href="document_download.php?id=<?= $docId ?>">Télécharger PDF</a>
<a class="btn-outline" href="document_download.php?id=<?= $docId ?>&view=1">Voir</a>
<?php else: ?>—<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
</main>
<?php include 'includes/flash_toast.php'; ?>
</body>
</html>
