<?php
// includes/sidebar.php - Version clean sans AlterNext
?>
<aside class="admin-sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="assets/logo.png" alt="UpcycleConnect" style="height: 35px;">
            <span class="sidebar-title">UpcycleConnect Admin</span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <div class="sidebar-group">
            <div class="sidebar-group-label">Général</div>
            <a href="admin.php">🏠 Dashboard</a>
            <a href="admin_users.php">👥 Utilisateurs</a>
            <a href="admin_catalog.php">🛍️ Catalogue</a>
            <a href="admin_events.php">📅 Événements</a>
            <a href="admin_annonces.php">📦 Annonces</a>
        </div>
        
        <div class="sidebar-group">
            <div class="sidebar-group-label">Gestion</div>
            <a href="admin_conteneurs.php">🗳️ Conteneurs</a>
            <a href="admin_finance.php">💰 Finance</a>
            <a href="admin_documents.php">📄 Documents</a>
            <a href="admin_audit.php">🧾 Audit</a>
        </div>
        
        <div class="sidebar-group">
            <div class="sidebar-group-label">Forum</div>
            <a href="admin_forum.php">💬 Vue générale</a>
            <a href="admin_forum_reports.php">⚠️ Signalements</a>
            <a href="admin_forum_categories.php">📂 Catégories</a>
        </div>
        
        <div class="sidebar-group">
            <div class="sidebar-group-label">Compte</div>
            <a href="admin_profile.php">👤 Mon profil</a>
            <a href="logout.php" class="sidebar-logout">🔓 Déconnexion</a>
        </div>
    </nav>
</aside>

<style>
/* Styles sidebar - version propre */
.admin-sidebar {
    background: white;
    border-radius: 18px;
    padding: 20px 0;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
    position: sticky;
    top: 24px;
}

.sidebar-header {
    padding: 0 20px 20px;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 20px;
}

.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.sidebar-title {
    font-weight: 700;
    font-size: 16px;
    color: #2e7d32;
}

.sidebar-group {
    margin-bottom: 20px;
}

.sidebar-group-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #94a3b8;
    padding: 0 20px;
    margin-bottom: 8px;
    padding-top: 8px;
    border-top: 1px solid #e5e7eb;
}

.sidebar-group:first-child .sidebar-group-label {
    border-top: none;
    padding-top: 0;
}

.admin-sidebar a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    color: #334155;
    text-decoration: none;
    transition: all 0.2s;
    font-size: 14px;
    border-radius: 10px;
    margin: 0 8px;
}

.admin-sidebar a:hover {
    background: rgba(22, 163, 74, 0.08);
    color: #2e7d32;
}

.sidebar-logout {
    color: #dc2626 !important;
    margin-top: 10px;
}

.sidebar-logout:hover {
    background: rgba(220, 38, 38, 0.08) !important;
    color: #dc2626 !important;
}

/* Layout admin */
.admin-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 28px;
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px 28px 36px;
}

.admin-content {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 28px;
}

@media (max-width: 980px) {
    .admin-layout {
        grid-template-columns: 1fr;
        padding: 20px;
    }
    .admin-sidebar {
        position: relative;
        top: auto;
    }
}
</style>