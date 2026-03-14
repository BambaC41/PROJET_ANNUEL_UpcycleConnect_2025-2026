function toggleHeaderDropdown(e, element) {
  e.preventDefault();
  let parent = element.closest(".dropdown");
  // Ferme les autres dropdowns ouverts s'il y en a
  document.querySelectorAll(".dropdown").forEach((d) => {
    if (d !== parent) d.classList.remove("active");
  });
  parent.classList.toggle("active");
}

// Ferme le menu si on clique ailleurs sur la page
window.addEventListener("click", function (e) {
  if (!e.target.closest(".dropdown"))
    document
      .querySelectorAll(".dropdown")
      .forEach((d) => d.classList.remove("active"));
});
