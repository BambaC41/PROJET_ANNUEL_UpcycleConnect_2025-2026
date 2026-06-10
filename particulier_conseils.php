<?php
require_once 'includes/particulier_bootstrap.php';
$query = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$conseils = api_get_conseils()['data'] ?? [];

function conseilImageByCategory(string $cat): string {
    $c = mb_strtolower($cat);
    if (str_contains($c, 'bois')) return 'https://images.unsplash.com/photo-1519710164239-da123dc03ef4?auto=format&fit=crop&w=1200&q=80';
    if (str_contains($c, 'velo')) return 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?auto=format&fit=crop&w=1200&q=80';
    if (str_contains($c, 'textile') || str_contains($c, 'vetement')) return 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=1200&q=80';
    if (str_contains($c, 'guide')) return 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1200&q=80';
    if (str_contains($c, 'news')) return 'https://images.unsplash.com/photo-1495020689067-958852a7765e?auto=format&fit=crop&w=1200&q=80';
    return 'https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?auto=format&fit=crop&w=1200&q=80';
}

$filtered = array_values(array_filter($conseils, function($c) use ($query) {
    if ($query === '') return true;
    return str_contains(mb_strtolower((string)($c['titre'] ?? '')), $query) || str_contains(mb_strtolower((string)($c['contenu'] ?? '')), $query) || str_contains(mb_strtolower((string)($c['categorie'] ?? '')), $query);
}));
?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Conseils particulier</title>
<link rel="stylesheet" href="styles/style.css"><link rel="stylesheet" href="styles/pro.css"></head>
<body class="pro-page"><?php include 'includes/particulier_nav.php'; ?><main class="pro-shell page-shell">
<section class="pro-card"><h1>💡 Espace conseils</h1>
<form method="GET" class="row-actions"><input class="input" type="search" name="q" value="<?= e($query) ?>" placeholder="Filtrer par mot-clé..."><button class="btn-outline">Filtrer</button></form>
<div class="pro-grid">
<?php foreach ($filtered as $c): ?><?php $img = !empty($c['image_url']) ? $c['image_url'] : conseilImageByCategory((string)($c['categorie'] ?? '')); ?>
<article class="pro-card"><img src="<?= e($img) ?>" alt="<?= e($c['titre'] ?? 'Conseil') ?>" style="width:100%;height:180px;object-fit:cover;border-radius:10px;margin-bottom:8px;"><h2><?= e($c['titre'] ?? '') ?></h2><p><strong><?= e($c['categorie'] ?? 'Conseil') ?></strong></p><p><?= e(mb_strimwidth((string)($c['contenu'] ?? ''), 0, 220, '...')) ?></p></article>
<?php endforeach; ?>
</div></section></main></body></html>
