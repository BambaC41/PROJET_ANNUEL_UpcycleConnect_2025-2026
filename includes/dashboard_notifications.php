<?php
declare(strict_types=1);

/** @var int $dash_uid */
if (!isset($dash_uid) || $dash_uid <= 0) {
    return;
}
require_once __DIR__ . '/notifications.php';
$dash_recent = notif_list($dash_uid, 5, 0);
$dash_wrap_class = $dash_wrap_class ?? 'pro-card page-card dashboard-notif-preview';
?>
<section class="<?= htmlspecialchars($dash_wrap_class, ENT_QUOTES, 'UTF-8') ?>">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
        <h2 style="margin:0;font-size:1.15rem;">Dernières notifications</h2>
        <a class="btn-outline" href="notifications.php">Voir tout</a>
    </div>
    <?php if ($dash_recent === []): ?>
        <p class="muted">Aucune notification récente.</p>
    <?php else: ?>
        <ul class="dash-notif-list" style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px;">
            <?php foreach ($dash_recent as $n): ?>
                <li style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;background:#fafafa;">
                    <strong><?= vc_escape($n['titre'] ?? '') ?></strong>
                    <span class="status-badge <?= !empty($n['is_read']) ? 'status-muted' : 'status-warn' ?>" style="margin-left:8px;font-size:11px;">
                        <?= !empty($n['is_read']) ? 'Lue' : 'Non lue' ?>
                    </span>
                    <div class="muted" style="font-size:13px;margin-top:4px;"><?= vc_escape(formatDateFr($n['created_at'] ?? '')) ?></div>
                    <div style="font-size:14px;margin-top:6px;"><?= vc_escape($n['contenu'] ?? '') ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
