<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/functions/documents.php';
require_once 'includes/functions/conteneur.php';
require_once 'includes/functions/qr.php';
require_once 'includes/notifications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_conteneur'])) {
    $payload = [
        'code' => trim((string)($_POST['code'] ?? '')),
        'adresse' => trim((string)($_POST['adresse'] ?? '')),
        'statut' => trim((string)($_POST['statut'] ?? 'actif')),
        'date_installation' => trim((string)($_POST['date_installation'] ?? '')),
        'derniere_maintenance' => trim((string)($_POST['derniere_maintenance'] ?? '')),
    ];
    $res = api_create_conteneur($payload);
    $pdoOk = false;
    if (($res['status'] ?? 0) !== 201) {
        $pdoOk = (bool)db_safe_exec(function (PDO $pdo) use ($payload): bool {
            $stmt = $pdo->prepare('INSERT INTO conteneur (code, adresse, statut, date_installation, derniere_maintenance) VALUES (?, ?, ?, NULLIF(?, ""), NULLIF(?, ""))');
            return $stmt->execute([
                $payload['code'],
                $payload['adresse'],
                $payload['statut'],
                $payload['date_installation'],
                $payload['derniere_maintenance'],
            ]);
        }, false);
    }
    $_SESSION['flash_toast'] = ((($res['status'] ?? 0) === 201) || $pdoOk)
        ? ['type' => 'success', 'message' => 'Conteneur créé.']
        : ['type' => 'error', 'message' => 'Création conteneur impossible.'];
    header('Location: admin_conteneurs.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_conteneur_id'])) {
    $cid = (int)$_POST['update_conteneur_id'];
    $payload = [
        'code' => trim((string)($_POST['edit_code'] ?? '')),
        'adresse' => trim((string)($_POST['edit_adresse'] ?? '')),
        'statut' => trim((string)($_POST['edit_statut'] ?? 'actif')),
        'date_installation' => trim((string)($_POST['edit_date_installation'] ?? '')),
        'derniere_maintenance' => trim((string)($_POST['edit_derniere_maintenance'] ?? '')),
    ];
    $res = api_update_conteneur($cid, $payload);
    $pdoOk = false;
    if (($res['status'] ?? 0) !== 200) {
        $pdoOk = (bool)db_safe_exec(function (PDO $pdo) use ($cid, $payload): bool {
            $stmt = $pdo->prepare('UPDATE conteneur SET code = ?, adresse = ?, statut = ?, date_installation = NULLIF(?, ""), derniere_maintenance = NULLIF(?, "") WHERE id_conteneur = ?');
            return $stmt->execute([
                $payload['code'],
                $payload['adresse'],
                $payload['statut'],
                $payload['date_installation'],
                $payload['derniere_maintenance'],
                $cid,
            ]);
        }, false);
    }
    $_SESSION['flash_toast'] = ((($res['status'] ?? 0) === 200) || $pdoOk)
        ? ['type' => 'success', 'message' => 'Conteneur mis à jour.']
        : ['type' => 'error', 'message' => 'Mise à jour impossible.'];
    header('Location: admin_conteneurs.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_conteneur_id'])) {
    $cid = (int)$_POST['delete_conteneur_id'];
    $linked = (int)db_safe_exec(function (PDO $pdo) use ($cid) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM demande_depot WHERE id_conteneur = ?');
        $stmt->execute([$cid]);
        return (int)$stmt->fetchColumn();
    }, 0);
    if ($linked > 0) {
        db_safe_exec(function (PDO $pdo) use ($cid): void {
            $stmt = $pdo->prepare('UPDATE conteneur SET statut = "inactif" WHERE id_conteneur = ?');
            $stmt->execute([$cid]);
        }, null);
        $_SESSION['flash_toast'] = ['type' => 'warning', 'message' => 'Conteneur désactivé (demandes liées).'];
    } else {
        $res = api_delete_conteneur_admin($cid);
        $pdoOk = false;
        if (($res['status'] ?? 0) !== 200) {
            $pdoOk = (bool)db_safe_exec(function (PDO $pdo) use ($cid): bool {
                $stmt = $pdo->prepare('DELETE FROM conteneur WHERE id_conteneur = ?');
                return $stmt->execute([$cid]);
            }, false);
        }
        $_SESSION['flash_toast'] = ((($res['status'] ?? 0) === 200) || $pdoOk)
            ? ['type' => 'success', 'message' => 'Conteneur supprimé.']
            : ['type' => 'error', 'message' => 'Suppression impossible.'];
    }
    header('Location: admin_conteneurs.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_demande_id'])) {
    $demandeId = (int)$_POST['validate_demande_id'];
    api_validate_demande_depot($demandeId);
    db_safe_exec(function (PDO $pdo) use ($demandeId) {
        $stmt = $pdo->prepare('SELECT d.id_user, c.code AS conteneur_code, ca.code AS code_acces, cb.barcode_value, d.statut, o.titre
                               FROM demande_depot d
                               LEFT JOIN conteneur c ON c.id_conteneur = d.id_conteneur
                               LEFT JOIN code_acces ca ON ca.id_demande = d.id_demande
                               LEFT JOIN code_barre cb ON cb.id_demande = d.id_demande
                               LEFT JOIN objet o ON o.id_objet = d.id_objet
                               WHERE d.id_demande = ?');
        $stmt->execute([$demandeId]);
        $row = $stmt->fetch();
        if (!$row) return;
        notif_create((int)$row['id_user'], 'depot', 'Demande de depot validee', 'Votre demande de depot a ete validee. Code acces disponible.');
        $payloadQr = trim((string)($row['barcode_value'] ?: ($row['code_acces'] ?? '')));
        $html = '<h1>UpcycleConnect - Fiche de depot</h1>'
            . '<p><strong>Demande:</strong> #' . $demandeId . '</p>'
            . '<p><strong>Objet:</strong> ' . htmlspecialchars((string)($row['titre'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Conteneur:</strong> ' . htmlspecialchars((string)($row['conteneur_code'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Code acces:</strong> ' . htmlspecialchars((string)($row['code_acces'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>QR payload:</strong> ' . htmlspecialchars($payloadQr, ENT_QUOTES, 'UTF-8') . '</p>';
        document_create_html((int)$row['id_user'], 'fiche_depot', 'Fiche depot #' . $demandeId, $html, null, $demandeId, null, []);
        $audit = $pdo->prepare('INSERT INTO audit_log (id_user, action, cible_type, cible_id, details, created_at) VALUES (?, "VALIDATION_DEPOT", "demande_depot", ?, "Validation demande depot", NOW())');
        $audit->execute([(int)$_SESSION['user_id'], $demandeId]);
    });
    $_SESSION['flash_toast'] = ['type' => 'success', 'message' => 'Demande dépôt validée.'];
    header('Location: admin_conteneurs.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_demande_id'])) {
    $demandeId = (int)$_POST['reject_demande_id'];
    db_safe_exec(function (PDO $pdo) use ($demandeId) {
        $stmt = $pdo->prepare('UPDATE demande_depot SET statut = "rejetee" WHERE id_demande = ?');
        $stmt->execute([$demandeId]);
        $owner = $pdo->prepare('SELECT id_user FROM demande_depot WHERE id_demande = ?');
        $owner->execute([$demandeId]);
        $userId = (int)$owner->fetchColumn();
        if ($userId > 0) {
            notif_create($userId, 'depot', 'Demande de depot rejetee', 'Votre demande de depot a ete rejetee.');
        }
        $audit = $pdo->prepare('INSERT INTO audit_log (id_user, action, cible_type, cible_id, details, created_at) VALUES (?, "REFUS_DEPOT", "demande_depot", ?, "Refus demande depot", NOW())');
        $audit->execute([(int)$_SESSION['user_id'], $demandeId]);
    });
    $_SESSION['flash_toast'] = ['type' => 'warning', 'message' => 'Demande dépôt rejetée.'];
    header('Location: admin_conteneurs.php');
    exit();
}

$fetched = api_get_all_demandes_depot();
$demandesDepot = (($fetched['status'] ?? 0) === 200 && is_array($fetched['data'] ?? null)) ? $fetched['data'] : [];
if ($demandesDepot === []) {
    $demandesDepot = (array)db_safe_exec(function (PDO $pdo) {
        $sql = 'SELECT d.id_demande, d.statut, d.requested_at, d.id_user, d.id_conteneur, o.titre, c.code AS code_conteneur
                FROM demande_depot d
                JOIN objet o ON o.id_objet = d.id_objet
                JOIN conteneur c ON c.id_conteneur = d.id_conteneur
                ORDER BY d.requested_at DESC';
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }, []);
}

$conteneursRes = api_get_conteneurs();
$conteneurs = (($conteneursRes['status'] ?? 0) === 200 && is_array($conteneursRes['data'] ?? null)) ? $conteneursRes['data'] : [];
if ($conteneurs === []) {
    $conteneurs = (array)db_safe_exec(fn(PDO $pdo) => $pdo->query('SELECT * FROM conteneur ORDER BY id_conteneur DESC')->fetchAll(PDO::FETCH_ASSOC), []);
}

$fStatut = trim((string)($_GET['d_statut'] ?? 'all'));
$fCont = (int)($_GET['d_conteneur'] ?? 0);
$fQ = mb_strtolower(trim((string)($_GET['d_q'] ?? '')));
$dSort = trim((string)($_GET['d_sort'] ?? 'date'));

$demandesFiltered = array_values(array_filter($demandesDepot, static function ($d) use ($fStatut, $fCont, $fQ) {
    if ($fStatut !== 'all' && (($d['statut'] ?? '') !== $fStatut)) return false;
    if ($fCont > 0 && (int)($d['id_conteneur'] ?? 0) !== $fCont) return false;
    $needle = mb_strtolower((string)($d['titre'] ?? '') . ' ' . (string)($d['code_conteneur'] ?? '') . ' ' . (string)($d['id_user'] ?? ''));
    if ($fQ !== '' && !str_contains($needle, $fQ)) return false;
    return true;
}));

usort($demandesFiltered, static function ($a, $b) use ($dSort) {
    if ($dSort === 'statut') return strcmp((string)($a['statut'] ?? ''), (string)($b['statut'] ?? ''));
    if ($dSort === 'objet') return strcmp((string)($a['titre'] ?? ''), (string)($b['titre'] ?? ''));
    return strcmp((string)($b['requested_at'] ?? ''), (string)($a['requested_at'] ?? ''));
});

$cSort = trim((string)($_GET['c_sort'] ?? 'code'));
usort($conteneurs, static function ($a, $b) use ($cSort) {
    if ($cSort === 'statut') return strcmp((string)($a['statut'] ?? ''), (string)($b['statut'] ?? ''));
    return strcmp((string)($a['code'] ?? ''), (string)($b['code'] ?? ''));
});

$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    foreach ($conteneurs as $c) {
        if ((int)($c['id_conteneur'] ?? 0) === $editId) { $editRow = $c; break; }
    }
}

function demande_badge_class(string $st): string {
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
    <title>Admin - Conteneurs et dépôts</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin.css">
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<main class="pro-shell page-shell">
    <?php include 'includes/flash_toast.php'; ?>
    
    <section class="pro-card">
        <h1>📦 Conteneurs et dépôts</h1>

        <?php if ($editRow !== null): ?>
            <h2>✏️ Modifier conteneur #<?= e((string)$editId) ?></h2>
            <form method="POST" class="form-grid" style="max-width:720px;">
                <input type="hidden" name="update_conteneur_id" value="<?= e((string)$editId) ?>">
                <input class="input" name="edit_code" value="<?= e((string)($editRow['code'] ?? '')) ?>" required>
                <input class="input" name="edit_adresse" value="<?= e((string)($editRow['adresse'] ?? '')) ?>" required>
                <select class="input" name="edit_statut">
                    <?php foreach (['actif', 'inactif', 'maintenance'] as $st): ?>
                        <option value="<?= e($st) ?>" <?= (($editRow['statut'] ?? '') === $st) ? 'selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="input" type="date" name="edit_date_installation" value="<?= e(substr((string)($editRow['date_installation'] ?? ''), 0, 10)) ?>">
                <input class="input" type="date" name="edit_derniere_maintenance" value="<?= e(substr((string)($editRow['derniere_maintenance'] ?? ''), 0, 10)) ?>">
                <div class="row-actions">
                    <button class="btn-primary" type="submit">💾 Enregistrer</button>
                    <a class="btn-outline" href="admin_conteneurs.php">❌ Annuler</a>
                </div>
            </form>
        <?php endif; ?>

        <h2>➕ Créer un conteneur</h2>
        <form method="POST" class="form-grid">
            <input type="hidden" name="create_conteneur" value="1">
            <input class="input" name="code" placeholder="Code conteneur" required>
            <input class="input" name="adresse" placeholder="Adresse" required>
            <select class="input" name="statut">
                <option value="actif">✅ actif</option>
                <option value="inactif">❌ inactif</option>
                <option value="maintenance">🔧 maintenance</option>
            </select>
            <input class="input" type="date" name="date_installation">
            <input class="input" type="date" name="derniere_maintenance">
            <button class="btn-primary" type="submit">➕ Créer</button>
        </form>
    </section>

    <section class="pro-card">
        <h2>🗑️ Gestion des conteneurs</h2>
        <form method="GET" class="row-actions">
            <select class="input" name="c_sort">
                <option value="code" <?= $cSort === 'code' ? 'selected' : '' ?>>Tri : code</option>
                <option value="statut" <?= $cSort === 'statut' ? 'selected' : '' ?>>Tri : statut</option>
            </select>
            <button class="btn-outline" type="submit">Appliquer tri</button>
        </form>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>ID</th><th>Code</th><th>Adresse</th><th>Statut</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($conteneurs as $c): ?>
                    <tr>
                        <td><?= e($c['id_conteneur'] ?? '') ?></td>
                        <td><strong><?= e($c['code'] ?? '') ?></strong></td>
                        <td><?= e($c['adresse'] ?? '') ?></td>
                        <td><span class="status-badge status-info"><?= e($c['statut'] ?? '') ?></span></td>
                        <td class="row-actions">
                            <a class="btn-outline" href="admin_conteneurs.php?edit=<?= (int)($c['id_conteneur'] ?? 0) ?>">✏️ Modifier</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Confirmer suppression / désactivation ?');">
                                <input type="hidden" name="delete_conteneur_id" value="<?= e($c['id_conteneur'] ?? 0) ?>">
                                <button class="btn-danger" type="submit">🗑️ Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="pro-card">
        <h2>📋 Demandes de dépôt</h2>
        <form method="GET" class="row-actions">
            <select class="input" name="d_statut">
                <option value="all" <?= $fStatut === 'all' ? 'selected' : '' ?>>Tous statuts</option>
                <option value="en_attente" <?= $fStatut === 'en_attente' ? 'selected' : '' ?>>en_attente</option>
                <option value="validee" <?= $fStatut === 'validee' ? 'selected' : '' ?>>validee</option>
                <option value="deposee" <?= $fStatut === 'deposee' ? 'selected' : '' ?>>deposee</option>
                <option value="rejetee" <?= $fStatut === 'rejetee' ? 'selected' : '' ?>>rejetee</option>
            </select>
            <select class="input" name="d_conteneur">
                <option value="0">Tous conteneurs</option>
                <?php foreach ($conteneurs as $c): ?>
                    <option value="<?= (int)($c['id_conteneur'] ?? 0) ?>" <?= $fCont === (int)($c['id_conteneur'] ?? 0) ? 'selected' : '' ?>><?= e($c['code'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <input class="input" type="search" name="d_q" value="<?= e($_GET['d_q'] ?? '') ?>" placeholder="Objet, user, conteneur…">
            <select class="input" name="d_sort">
                <option value="date" <?= $dSort === 'date' ? 'selected' : '' ?>>Tri : date</option>
                <option value="statut" <?= $dSort === 'statut' ? 'selected' : '' ?>>Tri : statut</option>
                <option value="objet" <?= $dSort === 'objet' ? 'selected' : '' ?>>Tri : objet</option>
            </select>
            <button class="btn-outline" type="submit">Filtrer</button>
        </form>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Objet</th><th>Utilisateur</th><th>Conteneur</th><th>Date</th><th>Statut</th><th>QR</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($demandesFiltered as $d): ?>
                    <?php $codesRes = api_get_demande_codes((int)($d['id_demande'] ?? 0)); ?>
                    <?php $codes = (($codesRes['status'] ?? 0) === 200) ? ($codesRes['data'] ?? null) : null; ?>
                    <?php $st = (string)($d['statut'] ?? ''); ?>
                    <?php $payloadQr = is_array($codes) ? trim((string)($codes['barcode_value'] ?: ($codes['code_acces'] ?? ''))) : ''; ?>
                    <?php $showQr = $payloadQr !== '' && in_array($st, ['validee', 'deposee'], true); ?>
                    <?php $docId = (int)db_safe_exec(function (PDO $pdo) use ($d) {
                        $s = $pdo->prepare('SELECT id_document FROM document_genere WHERE id_demande=? ORDER BY id_document DESC LIMIT 1');
                        $s->execute([(int)$d['id_demande']]);
                        return (int)$s->fetchColumn();
                    }, 0); ?>
                    <tr>
                        <td><?= e($d['id_demande'] ?? '') ?></td>
                        <td><strong><?= e($d['titre'] ?? '') ?></strong></td>
                        <td><?= e((string)($d['id_user'] ?? '')) ?></td>
                        <td><?= e($d['code_conteneur'] ?? '') ?></td>
                        <td><?= e(formatDateFr($d['requested_at'] ?? '')) ?></td>
                        <td><span class="status-badge <?= e(demande_badge_class($st)) ?>"><?= e($st) ?></span></td>
                        <td style="min-width:140px;">
                            <?php if ($showQr): ?>
                                <div style="width:120px;"><?= qr_svg_string($payloadQr) ?></div>
                                <code style="font-size:11px;"><?= e($payloadQr) ?></code>
                            <?php elseif ($st === 'en_attente'): ?>
                                <span class="muted">⏳ En attente</span>
                            <?php elseif ($st === 'rejetee'): ?>
                                <span class="status-badge status-danger">❌ Rejetée</span>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="row-actions">
                            <?php if ($st !== 'validee' && $st !== 'rejetee' && $st !== 'retiree'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="validate_demande_id" value="<?= e($d['id_demande'] ?? 0) ?>">
                                    <button class="btn-success" type="submit">✅ Valider</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="reject_demande_id" value="<?= e($d['id_demande'] ?? 0) ?>">
                                    <button class="btn-danger" type="submit">❌ Rejeter</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($docId > 0): ?>
                                <a class="btn-outline" href="document_download.php?id=<?= $docId ?>">📄 PDF fiche</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>