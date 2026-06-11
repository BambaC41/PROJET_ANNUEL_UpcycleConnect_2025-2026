<?php
require_once 'includes/pro_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/notifications.php';
require_once 'includes/functions/qr.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_demande_id'])) {
    $demandeId = (int)$_POST['collect_demande_id'];
    $inputCode = trim((string)($_POST['barcode_value'] ?? ''));
    $done = (bool)db_safe_exec(function (PDO $pdo) use ($demandeId, $inputCode) {
        $stmt = $pdo->prepare('SELECT d.id_demande, d.id_user, d.statut, cb.id_code_barre, cb.barcode_value FROM demande_depot d LEFT JOIN code_barre cb ON cb.id_demande = d.id_demande WHERE d.id_demande = ?');
        $stmt->execute([$demandeId]);
        $d = $stmt->fetch();
        if (!$d || (($d['statut'] ?? '') !== 'validee')) {
            return false;
        }
        if ($inputCode !== '' && !empty($d['barcode_value']) && $inputCode !== $d['barcode_value']) {
            return false;
        }
        $up = $pdo->prepare('UPDATE demande_depot SET statut = "retiree" WHERE id_demande = ?');
        $up->execute([$demandeId]);
        if (!empty($d['id_code_barre'])) {
            $ret = $pdo->prepare('INSERT INTO retrait (collected_at, notes, id_user, id_code_barre) VALUES (NOW(), ?, ?, ?) ON DUPLICATE KEY UPDATE collected_at = NOW()');
            $ret->execute(['Recuperation pro', (int)$_SESSION['user_id'], (int)$d['id_code_barre']]);
        }
        notif_create((int)$d['id_user'], 'retrait', 'Objet récupéré', 'Votre objet déposé a été récupéré par un professionnel.');
        return true;
    }, false);
    $_SESSION['flash_toast'] = $done
        ? ['type' => 'success', 'message' => 'Récupération enregistrée. Le particulier a été notifié.']
        : ['type' => 'error', 'message' => 'Récupération impossible (statut ou code incorrect).'];
    header('Location: pro_conteneurs.php');
    exit;
}

$status = trim((string)($_GET['status'] ?? 'all'));
$demandes = db_safe_exec(function (PDO $pdo) {
    $sql = 'SELECT d.id_demande, d.statut, o.titre, c.code AS code_conteneur
            FROM demande_depot d
            JOIN objet o ON o.id_objet = d.id_objet
            JOIN conteneur c ON c.id_conteneur = d.id_conteneur
            ORDER BY d.id_demande DESC';
    return $pdo->query($sql)->fetchAll();
}, []);
$filtered = array_values(array_filter($demandes, fn($d) => $status === 'all' || (($d['statut'] ?? '') === $status)));

function pro_depot_badge(string $st): string
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
    <title>Conteneurs Pro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <!-- OneSignal Push Notifications -->
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/pro_nav.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card page-card">
        <h1 class="page-header">Récupération objets conteneurs</h1>
        <form method="GET" class="row-actions page-actions">
            <select class="input" name="status">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tous statuts</option>
                <option value="en_attente" <?= $status === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                <option value="validee" <?= $status === 'validee' ? 'selected' : '' ?>>Validée</option>
                <option value="deposee" <?= $status === 'deposee' ? 'selected' : '' ?>>Déposée</option>
                <option value="retiree" <?= $status === 'retiree' ? 'selected' : '' ?>>Retirée</option>
            </select>
            <button class="btn-outline" type="submit">Filtrer</button>
        </form>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Objet</th><th>Conteneur</th><th>Statut</th><th>QR</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($filtered as $d): ?>
                <?php $codesRes = api_get_demande_codes((int)($d['id_demande'] ?? 0)); ?>
                <?php $codes = (($codesRes['status'] ?? 0) === 200) ? ($codesRes['data'] ?? null) : null; ?>
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
                    <td><span class="status-badge <?= e(pro_depot_badge($st)) ?>"><?= e($st) ?></span></td>
                    <td>
                        <?php if ($showQr): ?>
                            <div style="width:100px;"><?= qr_svg_string($payloadQr) ?></div>
                        <?php elseif ($st === 'en_attente'): ?>
                            <span class="muted">En attente validation</span>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($st === 'validee'): ?>
                            <form method="POST" class="row-actions" style="flex-direction:column;align-items:flex-start;">
                                <input type="hidden" name="collect_demande_id" value="<?= (int)($d['id_demande'] ?? 0) ?>">
                                <input class="input" name="barcode_value" placeholder="Code (optionnel)">
                                <button class="btn-primary" type="submit">Confirmer récupération</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
</main>
<?php include __DIR__ . '/includes/flash_toast.php'; ?>
</body>
</html>
