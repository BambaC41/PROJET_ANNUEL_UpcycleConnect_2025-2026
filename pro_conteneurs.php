<?php
require_once 'includes/pro_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/notifications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_demande_id'])) {
    $demandeId = (int)$_POST['collect_demande_id'];
    $inputCode = trim((string)($_POST['barcode_value'] ?? ''));
    $inputCode = preg_replace('/\s+/', '', $inputCode);
    
    $done = (bool)db_safe_exec(function (PDO $pdo) use ($demandeId, $inputCode) {
        $stmt = $pdo->prepare('
            SELECT d.id_demande, d.id_user, d.statut, cb.id_code_barre, cb.barcode_value 
            FROM demande_depot d 
            LEFT JOIN code_barre cb ON cb.id_demande = d.id_demande 
            WHERE d.id_demande = ?
        ');
        $stmt->execute([$demandeId]);
        $demande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$demande || ($demande['statut'] ?? '') !== 'validee') {
            return false;
        }
        
        $storedCode = preg_replace('/\s+/', '', $demande['barcode_value'] ?? '');
        if (!empty($storedCode) && $inputCode !== $storedCode) {
            return false;
        }
        
        $update = $pdo->prepare('UPDATE demande_depot SET statut = "retiree", deposited_at = NOW() WHERE id_demande = ?');
        $update->execute([$demandeId]);
        
        if (!empty($demande['id_code_barre'])) {
            $retrait = $pdo->prepare('INSERT INTO retrait (collected_at, notes, id_user, id_code_barre) VALUES (NOW(), ?, ?, ?)');
            $retrait->execute(['Recuperation professionnel', (int)$_SESSION['user_id'], (int)$demande['id_code_barre']]);
        }
        
        notif_create((int)$demande['id_user'], 'retrait', 'Objet recupere', 'Votre objet depose a ete recupere par un professionnel.');
        
        return true;
    }, false);
    
    $_SESSION['flash_toast'] = $done
        ? ['type' => 'success', 'message' => '✅ Recuperation enregistree avec succes !']
        : ['type' => 'error', 'message' => '❌ Code barre incorrect ou demande non validee'];
    
    header('Location: pro_conteneurs.php');
    exit;
}

$status = trim((string)($_GET['status'] ?? 'available'));

// Requête avec GROUP BY pour éviter les doublons
$demandes = db_safe_exec(function (PDO $pdo) {
    $sql = '
        SELECT DISTINCT 
            d.id_demande, 
            d.statut, 
            o.titre, 
            c.code AS code_conteneur, 
            cb.barcode_value
        FROM demande_depot d
        JOIN objet o ON o.id_objet = d.id_objet
        JOIN conteneur c ON c.id_conteneur = d.id_conteneur
        LEFT JOIN code_barre cb ON cb.id_demande = d.id_demande
        GROUP BY d.id_demande, d.statut, o.titre, c.code, cb.barcode_value
        ORDER BY d.id_demande DESC
    ';
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}, []);

// Filtrer selon le choix
if ($status === 'available') {
    // Uniquement les objets disponibles (validee uniquement)
    $filtered = array_values(array_filter($demandes, fn($d) => ($d['statut'] ?? '') === 'validee'));
} elseif ($status === 'history') {
    // Historique des recuperations (retiree seulement)
    $filtered = array_values(array_filter($demandes, fn($d) => ($d['statut'] ?? '') === 'retiree'));
} else {
    // Tous les statuts
    $filtered = $demandes;
}

function pro_depot_badge(string $st): string {
    return match ($st) {
        'validee', 'deposee' => 'status-ok',
        'en_attente' => 'status-warn',
        'rejetee' => 'status-danger',
        'retiree' => 'status-muted',
        default => 'status-info',
    };
}

function displayEAN13Preview($code) {
    if (empty($code) || strlen($code) != 13) {
        return '<span class="muted">—</span>';
    }
    
    $formatted = '';
    for ($i = 0; $i < strlen($code); $i++) {
        if ($i > 0 && $i % 4 == 0) $formatted .= ' ';
        $formatted .= $code[$i];
    }
    
    $canvasId = 'barcode_' . md5($code . rand(1000, 9999) . time());
    
    return '
    <div style="background:white; padding:4px; border-radius:4px; display:inline-block;">
        <canvas id="' . $canvasId . '" width="150" height="40" style="background:white;"></canvas>
        <div style="font-family:monospace; font-size:9px; font-weight:bold; text-align:center;">' . htmlspecialchars($formatted) . '</div>
    </div>
    <script>
    (function() {
        var canvas = document.getElementById("' . $canvasId . '");
        if (!canvas) return;
        var ctx = canvas.getContext("2d");
        var code = "' . $code . '";
        var width = canvas.width;
        var height = canvas.height;
        var patterns = {"0":"0001101","1":"0011001","2":"0010011","3":"0111101","4":"0100011","5":"0110001","6":"0101111","7":"0111011","8":"0110111","9":"0001011"};
        ctx.fillStyle = "white";
        ctx.fillRect(0, 0, width, height);
        var fullPattern = "";
        for (var i = 0; i < code.length; i++) fullPattern += patterns[code[i]] || "0000000";
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
    <?php include 'includes/onesignal_head.php'; ?>
    <style>
        .status-ok { background: #e8f5e9; color: #2e7d32; }
        .status-warn { background: #fff3e0; color: #ef6c00; }
        .status-danger { background: #fee2e2; color: #dc2626; }
        .status-muted { background: #f5f5f5; color: #757575; }
        .status-info { background: #e3f2fd; color: #1976d2; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; display: inline-block; }
        .error-box { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .success-box { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .table td { vertical-align: middle; }
        .view-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .view-btn {
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            background: #f0f0f0;
            color: #333;
            transition: all 0.2s;
        }
        .view-btn:hover {
            background: #e0e0e0;
        }
        .view-btn.active {
            background: #4caf50;
            color: white;
        }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/pro_nav.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card page-card">
        <h1 class="page-header">🗑️ Recuperation objets conteneurs</h1>
        
        <?php if (isset($_SESSION['flash_toast'])): ?>
            <div class="<?= $_SESSION['flash_toast']['type'] === 'success' ? 'success-box' : 'error-box' ?>">
                <?= htmlspecialchars($_SESSION['flash_toast']['message']) ?>
            </div>
            <?php unset($_SESSION['flash_toast']); ?>
        <?php endif; ?>
        
        <!-- Sélecteur d'affichage -->
        <div class="view-toggle">
            <a href="?status=available" class="view-btn <?= $status === 'available' ? 'active' : '' ?>">📦 Objets disponibles</a>
            <a href="?status=history" class="view-btn <?= $status === 'history' ? 'active' : '' ?>">📜 Historique</a>
            <a href="pro_conteneurs_history.php" class="view-btn">📊 Historique complet</a>
            <a href="?status=all" class="view-btn <?= $status === 'all' ? 'active' : '' ?>">📋 Tous les statuts</a>
        </div>
        
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Objet</th><th>Conteneur</th><th>Statut</th><th>Code-barre</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php if (empty($filtered)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding: 40px;">
                            <?php if ($status === 'available'): ?>
                                🎉 Aucun objet disponible pour le moment. Revenez plus tard !
                            <?php elseif ($status === 'history'): ?>
                                📭 Aucun objet recupere pour le moment.
                            <?php else: ?>
                                📭 Aucune demande trouvee.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
                
                <?php foreach ($filtered as $d): ?>
                    <?php 
                    $st = (string)($d['statut'] ?? '');
                    $barcode = $d['barcode_value'] ?? '';
                    ?>
                    <tr>
                        <td><?= (int)($d['id_demande'] ?? 0) ?></td>
                        <td><strong><?= htmlspecialchars($d['titre'] ?? '') ?></strong></td>
                        <td><?= htmlspecialchars($d['code_conteneur'] ?? '') ?></td>
                        <td><span class="status-badge <?= pro_depot_badge($st) ?>"><?= htmlspecialchars($st) ?></span></td>
                        <td style="min-width: 180px;">
                            <?php if ($barcode && strlen($barcode) == 13 && ($st === 'validee' || $st === 'retiree')): ?>
                                <?= displayEAN13Preview($barcode) ?>
                            <?php elseif ($st === 'validee'): ?>
                                <span class="muted">⏳ Generation en cours...</span>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($st === 'validee' && $barcode): ?>
                                <form method="POST" onsubmit="return confirm('Confirmer la recuperation de cet objet ?');">
                                    <input type="hidden" name="collect_demande_id" value="<?= (int)($d['id_demande'] ?? 0) ?>">
                                    <input class="input" name="barcode_value" placeholder="Code barre" value="" style="width:160px; margin-bottom:5px; font-family:monospace; text-align:center;">
                                    <button class="btn-primary" type="submit" style="width:100%;">✅ Confirmer</button>
                                </form>
                            <?php elseif ($st === 'validee'): ?>
                                <span class="muted" style="color:orange;">⏳ En attente du code</span>
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
<?php  ?>
</body>
</html>