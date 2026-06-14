<?php
require_once 'includes/particulier_bootstrap.php';

$flash = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$query = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$conseils = api_get_conseils()['data'] ?? [];

function conseilImageByCategory(string $cat): string {
    $c = mb_strtolower($cat);
    if (str_contains($c, 'bois')) return 'https://images.unsplash.com/photo-1519710164239-da123dc03ef4?auto=format&fit=crop&w=800&q=80';
    if (str_contains($c, 'velo')) return 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?auto=format&fit=crop&w=800&q=80';
    if (str_contains($c, 'textile') || str_contains($c, 'vetement')) return 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=800&q=80';
    if (str_contains($c, 'guide')) return 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=800&q=80';
    if (str_contains($c, 'news')) return 'https://images.unsplash.com/photo-1495020689067-958852a7765e?auto=format&fit=crop&w=800&q=80';
    return 'https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?auto=format&fit=crop&w=800&q=80';
}

// Limitation du contenu à 300 caractères pour l'affichage
function getShortContent($content, $max = 300) {
    if (empty($content)) return '';
    if (mb_strlen($content) <= $max) return $content;
    return mb_substr($content, 0, $max) . '...';
}

$filtered = array_values(array_filter($conseils, function($c) use ($query) {
    if ($query === '') return true;
    return str_contains(mb_strtolower((string)($c['titre'] ?? '')), $query) 
        || str_contains(mb_strtolower((string)($c['contenu'] ?? '')), $query) 
        || str_contains(mb_strtolower((string)($c['categorie'] ?? '')), $query);
}));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conseils particulier - UpcycleConnect</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/pro.css">
    <?php include 'includes/onesignal_head.php'; ?>
    <style>
        /* ============================================
           STYLES CONSEILS
        ============================================ */
        .conseils-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin-top: 20px;
        }
        
        .conseil-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        
        .conseil-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        
        .conseil-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: #f0f2f5;
        }
        
        .conseil-content {
            padding: 20px;
        }
        
        .conseil-category {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #4caf50;
            background: #e8f5e9;
            padding: 4px 10px;
            border-radius: 20px;
            margin-bottom: 12px;
        }
        
        .conseil-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0 0 12px 0;
            line-height: 1.4;
        }
        
        .conseil-preview {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
            margin-bottom: 16px;
        }
        
        /* ============================================
           MODAL STYLES
        ============================================ */
        .modal-conseil {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .modal-conseil.active {
            display: flex;
        }
        .modal-conseil-content {
            background: white;
            border-radius: 20px;
            max-width: 650px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            padding: 0;
            position: relative;
            cursor: default;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }
        .modal-conseil-close {
            position: absolute;
            top: 12px;
            right: 16px;
            cursor: pointer;
            font-size: 28px;
            color: white;
            z-index: 20;
            background: rgba(0,0,0,0.5);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .modal-conseil-close:hover {
            background: rgba(0,0,0,0.8);
        }
        .modal-conseil-image {
            width: 100%;
            max-height: 300px;
            object-fit: cover;
        }
        .modal-conseil-body {
            padding: 24px;
        }
        .modal-conseil-body h2 {
            margin: 0 0 16px 0;
            font-size: 24px;
            color: #1a1a2e;
        }
        .modal-conseil-category {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            color: #4caf50;
            background: #e8f5e9;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 16px;
        }
        .modal-conseil-content-text {
            font-size: 15px;
            line-height: 1.6;
            color: #333;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #f8f9fa;
            border-radius: 16px;
            color: #666;
        }
        .empty-state p {
            margin: 8px 0;
        }
        
        @media (max-width: 768px) {
            .conseils-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="pro-page">
<?php include 'includes/particulier_nav.php'; ?>
<main class="pro-shell page-shell">
    <section class="pro-card">
        <h1>💡 Espace conseils</h1>
        
        <?php if ($flash !== ''): ?>
            <div class="<?= $flashType === 'error' ? 'error-box' : 'success-box' ?>">
                <?= e($flash) ?>
            </div>
        <?php endif; ?>
        
        <form method="GET" class="row-actions">
            <input class="input" type="search" name="q" value="<?= e($query) ?>" placeholder="Filtrer par mot-clé..." style="flex:1;">
            <button class="btn-outline" type="submit">Filtrer</button>
        </form>
        
        <?php if (empty($filtered)): ?>
            <div class="empty-state">
                <p>📭 Aucun conseil trouvé pour votre recherche.</p>
                <p>Essayez d'autres mots-clés ou consultez tous les conseils.</p>
            </div>
        <?php else: ?>
            <div class="conseils-grid">
                <?php foreach ($filtered as $c): 
                    $img = !empty($c['image_url']) ? $c['image_url'] : conseilImageByCategory((string)($c['categorie'] ?? ''));
                    $shortContent = getShortContent($c['contenu'] ?? '', 300);
                ?>
                    <div class="conseil-card" onclick='showConseilModal(<?= json_encode([
                        'titre' => $c['titre'] ?? '',
                        'contenu' => $c['contenu'] ?? '',
                        'categorie' => $c['categorie'] ?? 'Conseil',
                        'image_url' => $img,
                        'date' => formatDateFr($c['created_at'] ?? '')
                    ], JSON_HEX_TAG) ?>)'>
                        <img class="conseil-image" src="<?= e($img) ?>" alt="<?= e($c['titre'] ?? 'Conseil') ?>">
                        <div class="conseil-content">
                            <span class="conseil-category">📖 <?= e($c['categorie'] ?? 'Conseil') ?></span>
                            <h3 class="conseil-title"><?= e($c['titre'] ?? '') ?></h3>
                            <p class="conseil-preview"><?= e($shortContent) ?></p>
                            <span style="color:#4caf50; font-size:13px; font-weight:500;">Lire la suite →</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- MODAL CONSEIL -->
<div id="conseilModal" class="modal-conseil" onclick="closeConseilModal()">
    <div class="modal-conseil-content" onclick="event.stopPropagation()">
        <span class="modal-conseil-close" onclick="closeConseilModal()">&times;</span>
        <img id="modalConseilImg" class="modal-conseil-image" src="" alt="">
        <div class="modal-conseil-body">
            <span id="modalConseilCategory" class="modal-conseil-category"></span>
            <h2 id="modalConseilTitre"></h2>
            <div id="modalConseilContenu" class="modal-conseil-content-text"></div>
            <p class="muted" style="margin-top:20px; font-size:12px;">📅 Publié le <span id="modalConseilDate"></span></p>
        </div>
    </div>
</div>

<script>
function showConseilModal(conseil) {
    document.getElementById('modalConseilTitre').textContent = conseil.titre;
    document.getElementById('modalConseilContenu').innerHTML = conseil.contenu.replace(/\n/g, '<br>');
    document.getElementById('modalConseilCategory').innerHTML = '📖 ' + conseil.categorie;
    document.getElementById('modalConseilDate').textContent = conseil.date;
    
    const img = document.getElementById('modalConseilImg');
    if (conseil.image_url && conseil.image_url !== '') {
        img.src = conseil.image_url;
        img.style.display = 'block';
    } else {
        img.style.display = 'none';
    }
    
    document.getElementById('conseilModal').classList.add('active');
}

function closeConseilModal() {
    document.getElementById('conseilModal').classList.remove('active');
}

// Fermer avec Echap
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && document.getElementById('conseilModal').classList.contains('active')) {
        closeConseilModal();
    }
});
</script>

<?php include 'includes/flash_toast.php'; ?>
<?php  ?>
</body>
</html>