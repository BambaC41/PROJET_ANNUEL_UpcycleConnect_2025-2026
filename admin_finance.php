<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/ui_helpers.php';

$statusF = trim((string)($_GET['status'] ?? 'all'));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'], $_POST['new_status'])) {
    $pid = (int)$_POST['payment_id'];
    $st = trim((string)$_POST['new_status']);
    $rows = (int)db_safe_exec(static function (PDO $pdo) use ($pid, $st): int {
        $u = $pdo->prepare("UPDATE paiement SET status = ?, statut = ?, paid_at = IF(? = 'paid', NOW(), paid_at) WHERE id_paiement = ?");
        $u->execute([$st, $st, $st, $pid]);
        return $u->rowCount();
    }, 0);
    $_SESSION['flash_toast'] = $rows > 0
        ? ['type' => 'success', 'message' => 'Paiement #' . $pid . ' → ' . $st]
        : ['type' => 'error', 'message' => 'Mise à jour impossible.'];
    if ($rows > 0) {
        db_safe_exec(static function (PDO $pdo) use ($pid, $st): void {
            $pdo->prepare('INSERT INTO audit_log (id_user, action, cible_type, cible_id, details, created_at) VALUES (?, ?, "paiement", ?, ?, NOW())')
                ->execute([(int)($_SESSION['user_id'] ?? 0), 'PAYMENT_STATUS', $pid, $st]);
        }, null);
    }
    header('Location: admin_finance.php');
    exit;
}

$rows = (array)db_safe_exec(static function (PDO $pdo) use ($statusF, $dateFrom, $dateTo): array {
    $sql = "SELECT p.id_paiement, p.montant, p.amount, p.devise, p.currency, COALESCE(p.status, p.statut) AS pay_status,
            p.paid_at, p.provider, p.payment_ref, p.id_inscription, p.user_id,
            u.email, u.pseudo, u.id_role, pr.titre AS prestation_titre
        FROM paiement p
        LEFT JOIN utilisateur u ON u.id_user = COALESCE(p.user_id, (SELECT i.id_user FROM inscription i WHERE i.id_inscription = p.id_inscription LIMIT 1))
        LEFT JOIN inscription i ON i.id_inscription = p.id_inscription
        LEFT JOIN session s ON s.id_session = i.id_session
        LEFT JOIN prestation pr ON pr.id_prestation = s.id_prestation
        WHERE 1=1";
    $params = [];
    if ($statusF !== 'all') {
        $sql .= " AND (p.status = ? OR p.statut = ?)";
        $params[] = $statusF;
        $params[] = $statusF;
    }
    if ($dateFrom !== '') {
        $sql .= ' AND DATE(p.paid_at) >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $sql .= ' AND DATE(p.paid_at) <= ?';
        $params[] = $dateTo;
    }
    $sql .= ' ORDER BY p.id_paiement DESC LIMIT 200';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}, []);

$sum = static function (array $list, callable $fn): float {
    return array_reduce($list, static fn($c, $p) => $c + ($fn($p) ? (float)($p['montant'] ?? $p['amount'] ?? 0) : 0), 0.0);
};
$paid = $sum($rows, static fn($p) => in_array((string)($p['pay_status'] ?? ''), ['paid', 'paye'], true));
$pending = $sum($rows, static fn($p) => in_array((string)($p['pay_status'] ?? ''), ['pending', 'en_attente'], true));
$failed = $sum($rows, static fn($p) => in_array((string)($p['pay_status'] ?? ''), ['failed', 'echec'], true));
$total = $paid + $pending + $failed;

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=paiements_export.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id', 'email', 'montant', 'statut', 'prestation', 'date']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['id_paiement'], $r['email'], $r['montant'] ?? $r['amount'], $r['pay_status'], $r['prestation_titre'], $r['paid_at']]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<?php include 'includes/head.php'; ?>
<body class="admin-page">
<?php include 'includes/header.php'; ?>
<main class="admin-layout">
<?php include 'includes/sidebar.php'; ?>
<section class="admin-content">
<?php include 'includes/flash_toast.php'; ?>
<section class="admin-section">
    <h1>Finance et paiements</h1>
    <div class="admin-kpi-grid">
        <div class="admin-card"><h3>CA payé</h3><p><?= e(number_format($paid, 2, ',', ' ')) ?> €</p></div>
        <div class="admin-card"><h3>En attente</h3><p><?= e(number_format($pending, 2, ',', ' ')) ?> €</p></div>
        <div class="admin-card"><h3>Échoués</h3><p><?= e(number_format($failed, 2, ',', ' ')) ?> €</p></div>
        <div class="admin-card"><h3>Total lignes</h3><p><?= count($rows) ?></p></div>
        <div class="admin-card"><h3>TVA estimée (20%)</h3><p><?= e(number_format($paid * 0.2, 2, ',', ' ')) ?> €</p></div>
    </div>
    <form method="GET" class="row-actions">
        <select class="input" name="status"><option value="all">Tous statuts</option><option value="paid">paid</option><option value="pending">pending</option><option value="failed">failed</option></select>
        <input class="input" type="date" name="date_from" value="<?= e($dateFrom) ?>">
        <input class="input" type="date" name="date_to" value="<?= e($dateTo) ?>">
        <button class="btn-outline" type="submit">Filtrer</button>
        <a class="btn-outline" href="admin_finance.php?export=csv">Export CSV</a>
    </form>
    <?php if (empty($rows)): render_empty_state('Aucun paiement', 'Les paiements démo apparaîtront ici après inscription ou facturation pro.'); else: ?>
    <div class="table-responsive">
    <table class="admin-table">
        <thead><tr><th>ID</th><th>Payeur</th><th>Rôle</th><th>Prestation</th><th>Montant</th><th>Statut</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $p):
            $amt = (float)($p['montant'] ?? $p['amount'] ?? 0);
            $roleLabels = [1 => 'Admin', 2 => 'Particulier', 3 => 'Pro', 4 => 'Salarié'];
        ?>
            <tr>
                <td><?= (int)$p['id_paiement'] ?></td>
                <td><?= e($p['email'] ?? $p['pseudo'] ?? '') ?></td>
                <td><?= e($roleLabels[(int)($p['id_role'] ?? 0)] ?? '') ?></td>
                <td><?= e($p['prestation_titre'] ?? '—') ?></td>
                <td><?= e(number_format($amt, 2, ',', ' ')) ?> €</td>
                <td><?= e($p['pay_status'] ?? '') ?></td>
                <td><?= e(formatDateFr($p['paid_at'] ?? '')) ?></td>
                <td class="actions-compact">
                    <a class="btn-outline" href="document_download.php?type=paiement&id=<?= (int)$p['id_paiement'] ?>">Facture</a>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="payment_id" value="<?= (int)$p['id_paiement'] ?>">
                        <select class="input" name="new_status"><option value="paid">paid</option><option value="pending">pending</option><option value="failed">failed</option></select>
                        <button class="btn-outline" type="submit">Appliquer</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>
</section>
</main>
</body>
</html>
