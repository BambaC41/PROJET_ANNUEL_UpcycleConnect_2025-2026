function openModal(id) {
  document.getElementById(id).classList.add("open");
}

function closeModal(id) {
  document.getElementById(id).classList.remove("open");
}

function openCreateModal() {
  document.getElementById("modalTitle").innerText = "Ajouter un événement";
  document.getElementById("modal_action").value = "create";
  document.getElementById("modal_id").value = "";
  document.getElementById("modal_id_prestation").value = "";
  document.getElementById("modal_date_debut").value = "";
  document.getElementById("modal_date_fin").value = "";
  document.getElementById("modal_lieu").value = "";
  document.getElementById("modal_capacite").value = "";
  document.getElementById("modal_statut").value = "actif";
  document.getElementById("modal_id_validateur").value = "1";
  openModal("eventModal");
}

function openEditModal(btn) {
  document.getElementById("modalTitle").innerText = "Modifier l'événement";
  document.getElementById("modal_action").value = "update";
  document.getElementById("modal_id").value = btn.dataset.id;
  document.getElementById("modal_id_prestation").value =
    btn.dataset.id_prestation;
  document.getElementById("modal_date_debut").value = btn.dataset.date_debut;
  document.getElementById("modal_date_fin").value = btn.dataset.date_fin;
  document.getElementById("modal_lieu").value = btn.dataset.lieu;
  document.getElementById("modal_capacite").value = btn.dataset.capacite;
  document.getElementById("modal_statut").value = btn.dataset.statut;
  document.getElementById("modal_id_validateur").value =
    btn.dataset.id_validateur;
  openModal("eventModal");
}
