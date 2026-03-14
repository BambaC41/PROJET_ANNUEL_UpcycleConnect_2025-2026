<?php
session_start();
if (!isset($_SESSION['token'])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/functions/events.php';
require_once 'includes/functions/prestations.php'; // Pour récupérer la liste des prestations

$msg_success = "";
$msg_error = "";

// --- ACTIONS (Create, Update, Delete) ---

// 1. Suppression
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $res = api_delete_event($_SESSION['token'], $_GET['id']);
    if ($res['status'] === 200 || $res['status'] === 204) {
        header("Location: events.php?msg=deleted");
        exit();
    } else {
        $msg_error = "Erreur lors de la suppression de l'événement.";
    }
}

// 2. Création et Modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // On formate la date renvoyée par le formulaire HTML (YYYY-MM-DDTHH:MM) vers le format API (YYYY-MM-DD HH:MM:SS)
    $date_debut = date('Y-m-d H:i:s', strtotime($_POST['date_debut']));
    $date_fin = date('Y-m-d H:i:s', strtotime($_POST['date_fin']));

    $payload = [
        'date_debut' => $date_debut,
        'date_fin' => $date_fin,
        'lieu' => $_POST['lieu'] ?? '',
        'capacite_max' => (int)($_POST['capacite_max'] ?? 0),
        'statut' => $_POST['statut'] ?? 'actif',
        'id_prestation' => (int)($_POST['id_prestation'] ?? 0),
        'id_validateur' => (int)($_POST['id_validateur'] ?? 1)
    ];

    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $res = api_create_event($_SESSION['token'], $payload);
        if ($res['status'] === 200 || $res['status'] === 201) {
            header("Location: events.php?msg=created");
            exit();
        } else {
            $msg_error = "Erreur lors de la création de l'événement.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = $_POST['id'];
        $res = api_update_event($_SESSION['token'], $id, $payload);
        if ($res['status'] === 200 || $res['status'] === 204) {
            header("Location: events.php?msg=updated");
            exit();
        } else {
            $msg_error = "Erreur lors de la modification de l'événement.";
        }
    }
}

// --- RECUPERATION DONNEES ---
$events = api_get_events($_SESSION['token']);
$prestations = api_get_prestations($_SESSION['token']);

// On mappe les prestations pour retrouver facilement le titre à partir de l'ID_prestation
$prestationsMap = [];
foreach ($prestations as $p) {
    if (isset($p['id'])) {
        $prestationsMap[$p['id']] = $p['titre'] ?? 'Prestation inconnue';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<?php include 'includes/head.php'; ?>
<body>
<main class="admin-layout">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <section class="admin-content">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>Gestion des Événements</h1>
            <button type="button" class="btn-primary" onclick="openCreateModal()">Ajouter un événement</button>
        </div>

        <!-- Feedback Messages -->
        <?php if ($msg_error): ?><p class="error" style="text-align: center;"><?= htmlspecialchars($msg_error) ?></p><?php endif; ?>
        <?php if (isset($_GET['msg'])): ?>
            <?php if($_GET['msg'] == 'created'): ?><p class="pill pill-green" style="display:block; text-align:center;">Événement créé avec succès.</p><?php endif; ?>
            <?php if($_GET['msg'] == 'updated'): ?><p class="pill pill-green" style="display:block; text-align:center;">Événement mis à jour.</p><?php endif; ?>
            <?php if($_GET['msg'] == 'deleted'): ?><p class="pill pill-green" style="display:block; text-align:center;">Événement supprimé.</p><?php endif; ?>
        <?php endif; ?>

        <!-- Table -->
        <section class="admin-section">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>Prestation liée</th>
                        <th>Lieu</th>
                        <th>Dates</th>
                        <th>Capacité</th>
                        <th>Statut</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:20px;">Aucun événement trouvé.</td></tr>
                    <?php else: ?>
                        <?php foreach ($events as $e): ?>
                            <tr>
                                <td><span class="muted small">#<?= htmlspecialchars($e['id'] ?? '') ?></span></td>
                                <td><strong><?= htmlspecialchars($prestationsMap[$e['id_prestation'] ?? ''] ?? 'Inconnu') ?></strong></td>
                                <td><?= htmlspecialchars($e['lieu'] ?? '') ?></td>
                                <td>
                                    <span class="small"><?= date('d/m/Y H:i', strtotime($e['date_debut'] ?? 'now')) ?></span><br>
                                    <span class="muted small">au <?= date('d/m/Y H:i', strtotime($e['date_fin'] ?? 'now')) ?></span>
                                </td>
                                <td><?= htmlspecialchars($e['capacite_max'] ?? '0') ?> pers.</td>
                                <td>
                                    <span class="pill <?= ($e['statut'] == 'actif') ? 'pill-green' : 'pill-gray' ?>">
                                        <?= htmlspecialchars(ucfirst($e['statut'] ?? 'attente')) ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <button class="btn-outline" 
                                        onclick="openEditModal(this)"
                                        data-id="<?= htmlspecialchars($e['id'] ?? '') ?>"
                                        data-date_debut="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($e['date_debut'] ?? 'now'))) ?>"
                                        data-date_fin="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($e['date_fin'] ?? 'now'))) ?>"
                                        data-lieu="<?= htmlspecialchars($e['lieu'] ?? '') ?>"
                                        data-capacite="<?= htmlspecialchars($e['capacite_max'] ?? '0') ?>"
                                        data-statut="<?= htmlspecialchars($e['statut'] ?? 'actif') ?>"
                                        data-id_prestation="<?= htmlspecialchars($e['id_prestation'] ?? '') ?>"
                                        data-id_validateur="<?= htmlspecialchars($e['id_validateur'] ?? '1') ?>"
                                    >Éditer</button>
                                    <a href="events.php?action=delete&id=<?= urlencode($e['id'] ?? '') ?>" class="btn-outline" style="border-color:#dc2626; color:#dc2626; margin-left: 5px;" onclick="return confirm('Confirmer la suppression de cet événement ?')">Supprimer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </section>
</main>

<!-- MODAL CREATE / EDIT -->
<div id="eventModal" class="modal-overlay">
    <div class="modal-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;" id="modalTitle">Événement</h3>
            <button onclick="closeModal('eventModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <form method="POST" class="form-block">
            <input type="hidden" name="action" id="modal_action" value="create">
            <input type="hidden" name="id" id="modal_id">
            
            <div class="form-group"><label>Prestation liée</label><select name="id_prestation" id="modal_id_prestation" class="input" required><option value="">Sélectionner une prestation</option><?php foreach ($prestationsMap as $pId => $pTitre): ?><option value="<?= htmlspecialchars($pId) ?>"><?= htmlspecialchars($pTitre) ?></option><?php endforeach; ?></select></div>
            <div class="grid-2"><div class="form-group"><label>Date de début</label><input type="datetime-local" name="date_debut" id="modal_date_debut" class="input" required></div><div class="form-group"><label>Date de fin</label><input type="datetime-local" name="date_fin" id="modal_date_fin" class="input" required></div></div>
            <div class="grid-2"><div class="form-group"><label>Lieu</label><input type="text" name="lieu" id="modal_lieu" class="input" required></div><div class="form-group"><label>Capacité Max</label><input type="number" name="capacite_max" id="modal_capacite" class="input" required></div></div>
            <div class="grid-2"><div class="form-group"><label>Statut</label><select name="statut" id="modal_statut" class="input"><option value="actif">Actif</option><option value="en attente">En attente</option><option value="inactif">Inactif</option><option value="annule">Annulé</option></select></div><div class="form-group"><label>ID Validateur</label><input type="number" name="id_validateur" id="modal_id_validateur" class="input" value="1"></div></div>
            
            <div style="margin-top:20px; text-align:right;">
                <button type="button" class="btn-secondary" onclick="closeModal('eventModal')">Annuler</button>
                <button type="submit" class="btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script src="scripts/events.js" defer></script>
<?php include 'includes/footer.php'; ?>
</body>
</html>