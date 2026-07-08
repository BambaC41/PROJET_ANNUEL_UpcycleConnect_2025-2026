<?php
require_once 'includes/particulier_bootstrap.php';

$id_annonce = (int)($_GET['id'] ?? 0);
if ($id_annonce <= 0) {
    header('Location: particulier_annonces.php');
    exit;
}


$response = api_get_annonce($id_annonce);
$annonce = $response['data'] ?? null;

if (!$annonce || ($annonce['id_user'] ?? 0) != ($_SESSION['user_id'] ?? 0)) {
    $_SESSION['flash_message'] = 'Vous ne pouvez pas supprimer cette annonce.';
    $_SESSION['flash_type'] = 'error';
    header('Location: particulier_annonces.php');
    exit;
}


if (($annonce['statut'] ?? '') === 'validee') {
    $_SESSION['flash_message'] = '❌ Une annonce validée ne peut pas être supprimée.';
    $_SESSION['flash_type'] = 'error';
    header('Location: particulier_annonces.php');
    exit;
}

$res = api_delete_annonce($id_annonce);

if (($res['status'] ?? 0) === 200) {
    $_SESSION['flash_message'] = 'Annonce supprimée avec succès.';
    $_SESSION['flash_type'] = 'success';
} else {
    $_SESSION['flash_message'] = '❌ Erreur lors de la suppression : ' . ($res['error'] ?? '');
    $_SESSION['flash_type'] = 'error';
}

header('Location: particulier_annonces.php');
exit;
?>
