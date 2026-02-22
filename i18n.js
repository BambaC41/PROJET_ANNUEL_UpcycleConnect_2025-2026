// i18n.js — traductions via fichiers JSON externes (ajout langues sans modifier le code)
const I18N_KEY = "uc_lang";
const DEFAULT_LANG = "fr";

async function loadTranslations(lang) {
  const res = await fetch(`i18n/${lang}.json`, { cache: "no-store" });
  if (!res.ok) throw new Error("Fichier de langue introuvable");
  return res.json();
}

function applyTranslations(dict) {
  // Tout élément avec data-i18n="clé"
  document.querySelectorAll("[data-i18n]").forEach((el) => {
    const key = el.getAttribute("data-i18n");
    if (dict[key]) el.textContent = dict[key];
  });

  // Placeholder : data-i18n-placeholder="clé"
  document.querySelectorAll("[data-i18n-placeholder]").forEach((el) => {
    const key = el.getAttribute("data-i18n-placeholder");
    if (dict[key]) el.setAttribute("placeholder", dict[key]);
  });
}

async function setLanguage(lang) {
  localStorage.setItem(I18N_KEY, lang);
  try {
    const dict = await loadTranslations(lang);
    applyTranslations(dict);
  } catch (e) {
    // fallback FR si erreur
    const dict = await loadTranslations(DEFAULT_LANG);
    applyTranslations(dict);
  }
}

(async function initI18n() {
  const select = document.getElementById("langSelect");
  const saved = localStorage.getItem(I18N_KEY) || DEFAULT_LANG;

  if (select) {
    select.value = saved;
    select.addEventListener("change", () => setLanguage(select.value));
  }

  await setLanguage(saved);
})();
