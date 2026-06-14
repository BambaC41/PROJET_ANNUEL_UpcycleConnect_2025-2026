<?php
require_once 'includes/admin_bootstrap.php';
require_once 'includes/functions/local_db.php';
require_once 'includes/ui_helpers.php';

// Récupérer les données Stripe
$stripeData = api_get_stripe_balance();
$totalRevenue = 0;
$transactionsCount = 0;
$stripeTransactions = [];

if (($stripeData['status'] ?? 0) === 200) {
    if (isset($stripeData['data']['data'])) {
        $stripeBalance = $stripeData['data']['data'];
        $totalRevenue = $stripeBalance['total_revenue'] ?? $stripeBalance['balance_pending'] ?? 0;
        $transactionsCount = $stripeBalance['transaction_count'] ?? 0;
        $stripeTransactions = $stripeBalance['transactions'] ?? [];
    } elseif (isset($stripeData['data']['balance_pending'])) {
        $totalRevenue = $stripeData['data']['balance_pending'];
        $transactionsCount = $stripeData['data']['transaction_count'] ?? 0;
        $stripeTransactions = $stripeData['data']['transactions'] ?? [];
    }
}

// Revenus mensuels depuis la BDD
$monthlyRevenue = (array)db_safe_exec(function (PDO $pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(paid_at, '%Y-%m') as month,
            SUM(montant) as total,
            COUNT(*) as count
        FROM paiement 
        WHERE statut = 'paid' AND paid_at IS NOT NULL
        GROUP BY DATE_FORMAT(paid_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}, []);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Finance Stripe</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <link rel="stylesheet" href="styles/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?php include 'includes/onesignal_head.php'; ?>
    <style>
        .finance-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            border: 1px solid #e5e7eb;
            text-align: center;
        }
        .stat-card.stripe { border-left: 4px solid #6772e5; }
        .stat-icon { font-size: 40px; margin-bottom: 12px; }
        .stat-amount { font-size: 36px; font-weight: 700; margin: 8px 0; color: #6772e5; }
        .stat-label { color: #666; font-size: 14px; }
        .stat-sub { font-size: 12px; color: #999; margin-top: 6px; }
        
        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid #e5e7eb;
        }
        .chart-container h3 {
            margin: 0 0 16px 0;
            font-size: 18px;
        }
        
        .payment-table {
            width: 100%;
            border-collapse: collapse;
        }
        .payment-table th, .payment-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .payment-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        .payment-row {
            cursor: pointer;
            transition: background 0.2s;
        }
        .payment-row:hover {
            background: #f0f7ff;
        }
        .status-paid {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            font-size: 12px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            color: #999;
        }
        .refresh-btn {
            background: #2196f3;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 30px;
            cursor: pointer;
        }
        
        .modal-transaction {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        .modal-transaction.active { display: flex; }
        .modal-transaction-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            padding: 0;
        }
        .modal-header {
            background: #6772e5;
            color: white;
            padding: 16px 20px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: white;
        }
        .modal-body { padding: 20px; }
        .detail-row {
            display: flex;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }
        .detail-label { width: 100px; font-weight: 600; color: #555; }
        .detail-value { flex: 1; color: #333; word-break: break-all; }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/header.php'; ?>
<div class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="admin-content">
        <section class="admin-section">
            <?php include 'includes/flash_toast.php'; ?>
            
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <h1>💰 Finance & Paiements Stripe</h1>
                <button class="refresh-btn" onclick="location.reload()">🔄 Actualiser</button>
            </div>
            
            <div class="finance-stats">
                <div class="stat-card stripe">
                    <div class="stat-icon">💳</div>
                    <div class="stat-amount"><?= number_format($totalRevenue, 2, ',', ' ') ?> €</div>
                    <div class="stat-label">Total des transactions Stripe</div>
                    <div class="stat-sub"><?= $transactionsCount ?> paiement(s) effectué(s)</div>
                </div>
            </div>
            
            <?php if (!empty($monthlyRevenue)): ?>
            <div class="chart-container">
                <h3>📈 Évolution des revenus mensuels</h3>
                <canvas id="revenueChart" height="80" style="max-height: 250px;"></canvas>
            </div>
            <?php endif; ?>
            
            <h2 style="margin: 30px 0 20px 0;">📋 Historique des transactions Stripe</h2>
            
            <?php if (empty($stripeTransactions)): ?>
                <div class="empty-state">
                    <div style="font-size: 48px; margin-bottom: 16px;">📭</div>
                    <h3>Aucune transaction Stripe</h3>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="payment-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Montant</th>
                                <th>Statut</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stripeTransactions as $index => $tx): ?>
                                <tr class="payment-row" onclick="showTransactionDetails(<?= $index ?>)">
                                    <td><small><?= e(substr($tx['id'] ?? '', 0, 16)) ?>...</small></td>
                                    <td><strong><?= number_format($tx['amount'] ?? 0, 2, ',', ' ') ?> €</strong></td>
                                    <td><span class="status-paid">✅ <?= e($tx['status'] ?? 'succeeded') ?></span></td>
                                    <td><?= e($tx['created'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<!-- Modal détails transaction -->
<div id="transactionModal" class="modal-transaction" onclick="closeTransactionModal()">
    <div class="modal-transaction-content" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3>💳 Détails de la transaction</h3>
            <button class="modal-close" onclick="closeTransactionModal()">&times;</button>
        </div>
        <div class="modal-body" id="transactionModalBody"></div>
    </div>
</div>

<script>
const transactionsData = <?= json_encode($stripeTransactions) ?>;

function showTransactionDetails(index) {
    const tx = transactionsData[index];
    if (!tx) return;
    
    const modalBody = document.getElementById('transactionModalBody');
    modalBody.innerHTML = `
        <div class="detail-row">
            <div class="detail-label">🆔 ID :</div>
            <div class="detail-value">${tx.id}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">💰 Montant :</div>
            <div class="detail-value">${tx.amount.toFixed(2)} €</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">📌 Statut :</div>
            <div class="detail-value">✅ ${tx.status}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">📅 Date :</div>
            <div class="detail-value">${tx.created}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">🔧 Type :</div>
            <div class="detail-value">${tx.type || 'charge'}</div>
        </div>
    `;
    document.getElementById('transactionModal').classList.add('active');
}

function closeTransactionModal() {
    document.getElementById('transactionModal').classList.remove('active');
}

window.onclick = function(e) {
    if (e.target.classList.contains('modal-transaction')) {
        closeTransactionModal();
    }
}

<?php if (!empty($monthlyRevenue)): ?>
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueData = <?= json_encode(array_reverse($monthlyRevenue)) ?>;
new Chart(revenueCtx, {
    type: 'bar',
    data: {
        labels: revenueData.map(item => item.month),
        datasets: [{
            label: 'Chiffre d\'affaires (€)',
            data: revenueData.map(item => parseFloat(item.total)),
            backgroundColor: 'rgba(103, 114, 229, 0.6)',
            borderColor: '#6772e5',
            borderWidth: 2,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, ticks: { callback: function(value) { return value + ' €'; } } } }
    }
});
<?php endif; ?>
</script>
<?php  ?>
</body>
</html>