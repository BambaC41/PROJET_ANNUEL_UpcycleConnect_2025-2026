<?php
require_once 'includes/particulier_bootstrap.php';
require_once 'includes/functions/events.php';
require_once 'includes/functions/inscriptions.php';
require_once 'includes/functions/paiements.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/functions/documents.php';
require_once 'includes/notifications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_event_id'])) {
    $sessionId = (int)$_POST['register_event_id'];
    $existing = api_get_my_inscriptions()['data'] ?? [];
    foreach ($existing as $ex) {
        if ((int)($ex['id_session'] ?? 0) === $sessionId) {
            $_SESSION['flash_toast'] = ['type' => 'warning', 'message' => 'Vous êtes déjà inscrit à cette session.'];
            header('Location: particulier_catalogue.php');
            exit;
        }
    }

    $res = api_register_event($sessionId);
    $status = (int)($res['status'] ?? 0);
    $error = strtolower((string)($res['error'] ?? ''));
    if ($status === 200 || $status === 201) {
        $_SESSION['flash_toast'] = ['type' => 'success', 'message' => 'Inscription enregistrée.'];
        $insId = isset($res['data']['id_inscription']) ? (int)$res['data']['id_inscription'] : null;
        $event = callAPI('GET', '/events/' . $sessionId, $_SESSION['token'])['data'] ?? [];
        $eventTitle = !empty($event['prestation_titre']) ? (string)$event['prestation_titre'] : 'événement';
        $price = (float)($event['prestation_prix'] ?? 0);
        notif_create((int)$_SESSION['user_id'], 'inscription', 'Inscription confirmée', 'Inscription à « ' . $eventTitle . ' ». ' . ($price > 0 ? 'Paiement requis.' : 'Session gratuite confirmée.'));
        if ($price <= 0 && $insId) {
            $html = '<h1>Attestation d\'inscription</h1><p>Session : ' . htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8') . '</p><p>Montant : gratuit</p>';
            document_create_html((int)$_SESSION['user_id'], 'attestation_inscription', 'Attestation inscription', $html, null, null, $insId, [
                'Attestation d\'inscription — UpcycleConnect',
                'Session : ' . $eventTitle,
                'Montant : gratuit',
            ]);
        }
    } elseif (str_contains($error, 'already registered')) {
        $_SESSION['flash_toast'] = ['type' => 'warning', 'message' => 'Vous êtes déjà inscrit à cette session.'];
    } elseif (str_contains($error, 'event full')) {
        $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Session complète.'];
    } else {
        $_SESSION['flash_toast'] = ['type' => 'error', 'message' => 'Inscription impossible. ' . ($res['error'] ?? '')];
    }
    header('Location: particulier_catalogue.php');
    exit;
}

$q = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$events = callAPI('GET', '/events', null)['data'] ?? [];

$cards = [];
foreach ($events as $e) {
    if (($e['statut'] ?? '') !== 'valide') {
        continue;
    }
    $sessionId = (int)($e['id_session'] ?? 0);
    $places = (int)($e['places_restantes'] ?? 0);
    $cards[] = [
        'id_session' => $sessionId,
        'title' => $e['prestation_titre'] ?? 'Session',
        'desc' => ($e['lieu'] ?? '') . ' — ' . formatDateFr($e['date_debut'] ?? '') . ' — Places : ' . $places,
        'price' => (float)($e['prestation_prix'] ?? 0),
        'places' => $places,
    ];
}

$myIns = api_get_my_inscriptions()['data'] ?? [];
$insBySession = [];
foreach ($myIns as $row) {
    $sid = (int)($row['id_session'] ?? 0);
    if ($sid > 0) {
        $insBySession[$sid] = $row;
    }
}

$payments = api_get_my_paiements()['data'] ?? [];
$paidInscriptionIds = [];
foreach ($payments as $p) {
    $st = strtolower((string)($p['statut'] ?? $p['status'] ?? ''));
    if ($st === 'paid' && !empty($p['id_inscription'])) {
        $paidInscriptionIds[(int)$p['id_inscription']] = true;
    }
}

$filtered = array_values(array_filter($cards, function ($c) use ($q) {
    $okQ = ($q === '') || str_contains(mb_strtolower((string)$c['title']), $q) || str_contains(mb_strtolower((string)$c['desc']), $q);
    return $okQ;
}));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue particulier - UpcycleConnect</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <style>
        .payment-btn-group { display: flex; gap: 10px; margin-top: 12px; flex-wrap: wrap; }
        .btn-pay { background: #4caf50; color: white; padding: 8px 20px; border-radius: 30px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block; transition: background 0.2s; }
        .btn-pay:hover { background: #2e7d32; }
        .btn-inscrire { background: #2196f3; color: white; padding: 8px 20px; border-radius: 30px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block; transition: background 0.2s; border: none; cursor: pointer; }
        .btn-inscrire:hover { background: #1976d2; }
        .btn-disabled { background: #ccc; color: #666; padding: 8px 20px; border-radius: 30px; font-weight: 600; font-size: 14px; display: inline-block; cursor: not-allowed; }
    </style>
    <?php include 'includes/onesignal_head.php'; ?>
</head>
<body class="pro-page">
<?php include 'includes/particulier_nav.php'; ?>
<main class="pro-shell page-shell">
<section class="pro-card page-card">
    <h1 class="page-header">📅 Événements disponibles</h1>
    <form method="GET" class="row-actions page-actions">
        <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Recherche…">
        <button class="btn-outline" type="submit">Filtrer</button>
    </form>
    <div class="pro-grid">
        <?php foreach ($filtered as $c): ?>
            <article class="pro-card">
                <h2><?= e($c['title']) ?></h2>
                <p><?= e($c['desc']) ?></p>
                <p class="row-actions" style="flex-wrap:wrap;align-items:center;">
                    <?php $pr = (float)($c['price'] ?? 0); ?>
                    <?php if ($pr > 0): ?>
                        <span class="status-badge status-info">💰 Payant — <?= e(formatPriceEur($pr)) ?></span>
                    <?php else: ?>
                        <span class="status-badge status-ok">🎟️ Gratuit</span>
                    <?php endif; ?>
                </p>
                
                <?php
                $sid = (int)($c['id_session'] ?? 0);
                $placesLeft = (int)($c['places'] ?? 0);
                $insRow = $insBySession[$sid] ?? null;
                $iid = (int)($insRow['id_inscription'] ?? 0);
                $isPaid = $iid > 0 && !empty($paidInscriptionIds[$iid]);
                $isConfirmed = ($insRow['statut'] ?? '') === 'confirmee';
                ?>
                
                <?php if ($placesLeft <= 0): ?>
                    <span class="status-badge status-muted" style="display:block; margin-top:8px;">❌ Complet</span>
                    <span class="btn-disabled">Complet</span>
                    
                <?php elseif ($insRow && $isPaid): ?>
                    <span class="status-badge status-ok" style="display:block; margin-top:8px;">✅ Inscrit et payé</span>
                    <span class="btn-disabled">✅ Déjà inscrit</span>
                    
                <?php elseif ($insRow && $isConfirmed && !$isPaid && $pr > 0): ?>
                    <span class="status-badge status-warn" style="display:block; margin-top:8px;">⏳ Paiement requis</span>
                    <div class="payment-btn-group">
                        <a class="btn-pay" href="paiement_stripe.php?amount=<?= $pr * 100 ?>&item=Inscription+<?= urlencode($c['title']) ?>&inscription_id=<?= $iid ?>">
                            💳 Payer maintenant (<?= number_format($pr, 2) ?>€)
                        </a>
                    </div>
                    
                <?php elseif ($insRow && $pr <= 0): ?>
                    <span class="status-badge status-ok" style="display:block; margin-top:8px;">✅ Inscription gratuite confirmée</span>
                    <span class="btn-disabled">✅ Confirmé</span>
                    
                <?php elseif (!$insRow): ?>
                    <form method="POST" style="margin-top:8px;">
                        <input type="hidden" name="register_event_id" value="<?= $sid ?>">
                        <button class="btn-inscrire" type="submit">📝 S'inscrire</button>
                    </form>
                    
                <?php else: ?>
                    <span class="status-badge status-ok" style="display:block; margin-top:8px;">✅ Inscription enregistrée</span>
                    <span class="btn-disabled">✅ Déjà inscrit</span>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if (empty($filtered)): ?>
            <div class="pro-card" style="text-align:center; grid-column:1/-1;">
                <p>Aucun événement disponible pour le moment.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
</main>
<?php include 'includes/flash_toast.php'; ?>
<?php  ?>
</body>
</html>