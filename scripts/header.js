function toggleHeaderDropdown(e, element) {
  e.preventDefault();
  let parent = element.closest(".dropdown");

  document.querySelectorAll(".dropdown").forEach((d) => {
    if (d !== parent) d.classList.remove("active");
  });
  parent.classList.toggle("active");
}


window.addEventListener("click", function (e) {
  if (!e.target.closest(".dropdown"))
    document
      .querySelectorAll(".dropdown")
      .forEach((d) => d.classList.remove("active"));
});
