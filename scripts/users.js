let autoSaveTimeout = null;

function $(sel) {
  return document.querySelector(sel);
}

function openEditModal(btn) {
  const id = btn.dataset.id || "";
  const pseudo = btn.dataset.pseudo || "";
  const prenom = btn.dataset.prenom || "";
  const nom = btn.dataset.nom || "";
  const email = btn.dataset.email || "";
  const role = btn.dataset.role || "";
  const statut = btn.dataset.statut || "";
  const photoProfil = btn.dataset.photo_profil || "";
  const bio = btn.dataset.bio || "";

  $("#modal_id_user").value = id;
  $("#modal_pseudo").value = pseudo;
  $("#modal_prenom").value = prenom;
  $("#modal_nom").value = nom;
  $("#modal_email").value = email;
  $("#modal_role").value = role;
  $("#modal_statut").value = statut;
  $("#modal_photo_profil").value = photoProfil;
  $("#modal_bio").value = bio;

  $("#editModal").classList.add("open");

  const form = $("#editModal form");
  if (form && !form.dataset.autosaveAttached) {
    const handler = () => scheduleAutoSave();
    form.addEventListener("input", handler);
    form.addEventListener("change", handler);
    form.dataset.autosaveAttached = "1";
  }
}

function closeEditModal() {
  $("#editModal").classList.remove("open");
}

function openPasswordModal() {
  $("#passwordModal").classList.add("open");
}

function closePasswordModal() {
  $("#passwordModal").classList.remove("open");
}

function resetPasswordChoice(mode) {
  const userId = $("#modal_id_user")?.value;
  if (!userId) return;
  if (mode === "email") {
    window.location.href =
      "reset_password.php?mode=email&id=" + encodeURIComponent(userId);
  } else if (mode === "form") {
    const container = $("#password-form");
    const hiddenId = $("#password_form_id_user");
    if (hiddenId) hiddenId.value = userId;
    if (container) container.style.display = "block";
  }
}

function hidePasswordForm() {
  const container = $("#password-form");
  if (!container) return;
  container.style.display = "none";
  const pwdInputs = container.querySelectorAll('input[type="password"]');
  pwdInputs.forEach((inp) => (inp.value = ""));
}

function toggleDropdown(btn) {
  document.querySelectorAll(".dropdown").forEach((d) => {
    if (d !== btn.parentElement) d.classList.remove("active");
  });
  btn.parentElement.classList.toggle("active");
}

window.addEventListener("click", function (event) {
  if (!event.target.closest(".dropdown")) {
    document.querySelectorAll(".dropdown").forEach((d) => d.classList.remove("active"));
  }
});

function scheduleAutoSave() {
  if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
  autoSaveTimeout = setTimeout(runAutoSave, 1500);
}

function runAutoSave() {
  const form = $("#editModal form");
  if (!form) return;

  const formData = new FormData(form);

  fetch("users.php", {
    method: "POST",
    body: formData,
    credentials: "same-origin",
  }).catch(() => {
    // silence (pas de message UI)
  });
}

// Expose globally for inline onclick handlers
window.openEditModal = openEditModal;
window.closeEditModal = closeEditModal;
window.openPasswordModal = openPasswordModal;
window.closePasswordModal = closePasswordModal;
window.resetPasswordChoice = resetPasswordChoice;
window.toggleDropdown = toggleDropdown;
window.hidePasswordForm = hidePasswordForm;

