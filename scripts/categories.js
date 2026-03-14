function openModal(id) {
  document.getElementById(id).classList.add("open");
}

function closeModal(id) {
  document.getElementById(id).classList.remove("open");
}

function openCreateModal() {
  document.getElementById("modalTitle").innerText = "Ajouter une catégorie";
  document.getElementById("modal_action").value = "create";
  document.getElementById("modal_id").value = "";
  document.getElementById("modal_nom").value = "";
  document.getElementById("modal_desc").value = "";
  openModal("categoryModal");
}

function openEditModal(btn) {
  document.getElementById("modalTitle").innerText = "Modifier la catégorie";
  document.getElementById("modal_action").value = "update";
  document.getElementById("modal_id").value = btn.dataset.id;
  document.getElementById("modal_nom").value = btn.dataset.nom;
  document.getElementById("modal_desc").value = btn.dataset.desc;
  openModal("categoryModal");
}

function openViewModal(e, link) {
  e.preventDefault();
  document.getElementById("view_nom").innerText =
    link.dataset.nom || "Catégorie inconnue";
  document.getElementById("view_id_badge").innerText =
    "ID: " + (link.dataset.id || "N/A");
  document.getElementById("view_desc").innerText =
    link.dataset.desc || "Aucune description renseignée.";
  openModal("viewModal");
}
