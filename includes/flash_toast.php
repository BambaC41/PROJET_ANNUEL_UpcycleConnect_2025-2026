<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$flashType = $flashType ?? 'success';
$hasInlineFlash = isset($flash) && trim((string)$flash) !== '';

if (!$hasInlineFlash) {
    $flash = '';
    if (!empty($_SESSION['flash_toast']) && is_array($_SESSION['flash_toast'])) {
        $ft = $_SESSION['flash_toast'];
        unset($_SESSION['flash_toast']);
        $flash = (string)($ft['message'] ?? '');
        $flashType = (string)($ft['type'] ?? 'success');
    }
    if ($flash === '' && !empty($_SESSION['flash_message'])) {
        $flash = (string)$_SESSION['flash_message'];
        $flashType = (string)($_SESSION['flash_type'] ?? 'success');
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    }
}

if (trim((string)$flash) === '') {
    return;
}

$bg = '#166534';
if ($flashType === 'error') {
    $bg = '#b91c1c';
} elseif ($flashType === 'warning') {
    $bg = '#b45309';
} elseif ($flashType === 'info') {
    $bg = '#1d4ed8';
}
?>
<div id="flashToast" style="position:fixed;right:16px;bottom:16px;z-index:5000;padding:12px 14px;border-radius:10px;color:#fff;background:<?= htmlspecialchars($bg, ENT_QUOTES, 'UTF-8') ?>;box-shadow:0 10px 24px rgba(0,0,0,.18);max-width:min(420px,92vw);">
    <?= htmlspecialchars((string)$flash, ENT_QUOTES, 'UTF-8') ?>
</div>
<script>
setTimeout(() => {
  const t = document.getElementById('flashToast');
  if (t) t.remove();
}, 4200);
</script>