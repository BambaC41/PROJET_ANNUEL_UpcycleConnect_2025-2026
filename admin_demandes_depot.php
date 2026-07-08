<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/functions/documents.php';
require_once 'includes/functions/conteneur.php';
require_once 'includes/functions/qr.php';
require_once 'includes/notifications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_demande_id'])) {
    $demandeId = (int)$_POST['validate_demande_id'];
    
    $result = api_validate_demande_depot($demandeId);
    
    if (($result['status'] ?? 0) === 200) {
        $barcodeValue = $result['data']['barcode_value'] ?? '';
        
        db_safe_exec(function (PDO $pdo) use ($demandeId, $barcodeValue) {
            $stmt = $pdo->prepare('SELECT d.id_user, c.code AS conteneur_code, ca.code AS code_acces, d.statut, o.titre
                                   FROM demande_depot d
                                   LEFT JOIN conteneur c ON c.id_conteneur = d.id_conteneur
                                   LEFT JOIN code_acces ca ON ca.id_demande = d.id_demande
                                   LEFT JOIN objet o ON o.id_objet = d.id_objet
                                   WHERE d.id_demande = ?');
            $stmt->execute([$demandeId]);
            $row = $stmt->fetch();
            if (!$row) return;
            
            notif_create((int)$row['id_user'], 'depot', 'Demande de depot validee', 'Votre demande de depot a ete validee. Code barre EAN-13 disponible.');
            
            $html = '<h1>UpcycleConnect - Fiche de depot</h1>'
                . '<p><strong>Demande:</strong> #' . $demandeId . '</p>'
                . '<p><strong>Objet:</strong> ' . htmlspecialchars((string)($row['titre'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><strong>Conteneur:</strong> ' . htmlspecialchars((string)($row['conteneur_code'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><strong>Code acces:</strong> ' . htmlspecialchars((string)($row['code_acces'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><strong>Code barre EAN-13:</strong> ' . htmlspecialchars($barcodeValue, ENT_QUOTES, 'UTF-8') . '</p>';
            
            document_create_html((int)$row['id_user'], 'fiche_depot', 'Fiche depot #' . $demandeId, $html, null, $demandeId, null, []);
            
            $audit = $pdo->prepare('INSERT INTO audit_log (id_user, action, cible_type, cible_id, details, created_at) VALUES (?, "VALIDATION_DEPOT", "demande_depot", ?, "Validation demande depot - Code EAN-13: ' . $barcodeValue . '", NOW())');
            $audit->execute([(int)$_SESSION['user_id'], $demandeId]);
        });
        
        $_SESSION['flash_toast'] = ['type' => 'success', 'message' => 'Demande depot validee. Code barre EAN-13 genere: ' . $barcodeValue];
    } else {
        $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Erreur lors de la validation: ' . ($result['error'] ?? 'Inconnue')];
    }
    header('Location: admin_demandes_depot.php');
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
    $_SESSION['flash_toast'] = ['type' => 'warning', 'message' => 'Demande depot rejetee.'];
    header('Location: admin_demandes_depot.php');
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

function demande_badge_class(string $st): string {
    return match ($st) {
        'validee', 'deposee' => 'status-ok',
        'en_attente' => 'status-warn',
        'rejetee' => 'status-danger',
        'retiree' => 'status-muted',
        default => 'status-info',
    };
}

function displayEAN13Barcode($code) {
    if (empty($code) || strlen($code) != 13) {
        return '<span class="muted">Code invalide</span>';
    }
    
    $formatted = '';
    for ($i = 0; $i < strlen($code); $i++) {
        if ($i > 0 && $i % 4 == 0) {
            $formatted .= ' ';
        }
        $formatted .= $code[$i];
    }
    
    $canvasId = 'barcode_' . md5($code . time() . rand(1000, 9999));
    
    $html = '
    <div style="background:white; padding:4px; border-radius:4px; display:inline-block;">
        <canvas id="' . $canvasId . '" width="150" height="40" style="background:white; border:1px solid #eee;"></canvas>
        <div style="font-family:\'Courier New\', monospace; font-size:9px; font-weight:bold; text-align:center; margin-top:3px;">' . htmlspecialchars($formatted) . '</div>
    </div>
    <script>
    (function() {
        var canvas = document.getElementById("' . $canvasId . '");
        if (!canvas) return;
        var ctx = canvas.getContext("2d");
        var code = "' . $code . '";
        var width = canvas.width;
        var height = canvas.height;
        
        var patterns = {
            "0": "0001101", "1": "0011001", "2": "0010011", "3": "0111101",
            "4": "0100011", "5": "0110001", "6": "0101111", "7": "0111011",
            "8": "0110111", "9": "0001011"
        };
        
        ctx.fillStyle = "white";
        ctx.fillRect(0, 0, width, height);
        
        var fullPattern = "";
        for (var i = 0; i < code.length; i++) {
            fullPattern += patterns[code[i]] || "0000000";
        }
        
        var barWidth = width / fullPattern.length;
        var x = 0;
        for (var i = 0; i < fullPattern.length; i++) {
            ctx.fillStyle = fullPattern[i] === "1" ? "black" : "white";
            ctx.fillRect(x, 0, barWidth, height);
            x += barWidth;
        }
        
        ctx.fillStyle = "black";
        ctx.fillRect(0, 0, barWidth * 2, height);
        ctx.fillRect(width - barWidth * 2, 0, barWidth * 2, height);
        ctx.fillRect(width/2 - barWidth, 0, barWidth * 2, height);
    })();
    </script>';
    
    return $html;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Demandes de depot</title>
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
    
    <div class="nav-links">
        <a href="admin_conteneurs.php" class="nav-link">📦 Conteneurs</a>
        <a href="admin_demandes_depot.php" class="nav-link active">📋 Demandes de depot</a>
    </div>
    
    <section class="pro-card">
        <h1>📋 Demandes de depot</h1>
        
        <form method="GET" class="row-actions" style="flex-wrap: wrap; gap: 8px;">
            <select class="input" name="d_statut" style="width: auto;">
                <option value="all" <?= $fStatut === 'all' ? 'selected' : '' ?>>Tous statuts</option>
                <option value="en_attente" <?= $fStatut === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                <option value="validee" <?= $fStatut === 'validee' ? 'selected' : '' ?>>Validee</option>
                <option value="deposee" <?= $fStatut === 'deposee' ? 'selected' : '' ?>>Deposee</option>
                <option value="rejetee" <?= $fStatut === 'rejetee' ? 'selected' : '' ?>>Rejetee</option>
                <option value="retiree" <?= $fStatut === 'retiree' ? 'selected' : '' ?>>Retiree</option>
            </select>
            <select class="input" name="d_conteneur" style="width: auto;">
                <option value="0">Tous conteneurs</option>
                <?php foreach ($conteneurs as $c): ?>
                    <option value="<?= (int)($c['id_conteneur'] ?? 0) ?>" <?= $fCont === (int)($c['id_conteneur'] ?? 0) ? 'selected' : '' ?>><?= e($c['code'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <input class="input" type="search" name="d_q" value="<?= e($_GET['d_q'] ?? '') ?>" placeholder="Objet, user, conteneur…" style="width: 200px;">
            <select class="input" name="d_sort" style="width: auto;">
                <option value="date" <?= $dSort === 'date' ? 'selected' : '' ?>>Tri : date</option>
                <option value="statut" <?= $dSort === 'statut' ? 'selected' : '' ?>>Tri : statut</option>
                <option value="objet" <?= $dSort === 'objet' ? 'selected' : '' ?>>Tri : objet</option>
            </select>
            <button class="btn-outline" type="submit">Filtrer</button>
            <a class="btn-outline" href="admin_demandes_depot.php">Reset</a>
        </form>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Objet</th>
                        <th>Utilisateur</th>
                        <th>Conteneur</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Code EAN-13</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($demandesFiltered as $d): ?>
                    <?php $codesRes = api_get_demande_codes((int)($d['id_demande'] ?? 0)); ?>
                    <?php $codes = (($codesRes['status'] ?? 0) === 200) ? ($codesRes['data'] ?? null) : null; ?>
                    <?php $st = (string)($d['statut'] ?? ''); ?>
                    <?php $barcodeValue = is_array($codes) ? ($codes['barcode_value'] ?? '') : ''; ?>
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
                        <td style="min-width: 180px;">
                            <?php if ($barcodeValue && strlen($barcodeValue) == 13): ?>
                                <?= displayEAN13Barcode($barcodeValue) ?>
                            <?php elseif ($st === 'validee'): ?>
                                <span class="muted">⏳ Generation...</span>
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
                                <a class="btn-outline" href="document_download.php?id=<?= $docId ?>">📄 PDF</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($demandesFiltered)): ?>
                    <tr><td colspan="8" style="text-align:center;">Aucune demande de depot trouvee.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php  ?>
</body>
</html>
