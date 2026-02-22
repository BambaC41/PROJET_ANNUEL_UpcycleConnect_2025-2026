// auth.js - Auth "mock" via localStorage

const AUTH_KEY = "uc_auth_user";      // utilisateur connecté
const USERS_KEY = "uc_users_db";      // "base" utilisateurs

function getUsers() {
  try {
    return JSON.parse(localStorage.getItem(USERS_KEY)) || [];
  } catch {
    return [];
  }
}

function setUsers(users) {
  localStorage.setItem(USERS_KEY, JSON.stringify(users));
}

function setAuthUser(user) {
  localStorage.setItem(AUTH_KEY, JSON.stringify(user));
}

function getAuthUser() {
  try {
    return JSON.parse(localStorage.getItem(AUTH_KEY));
  } catch {
    return null;
  }
}

function clearAuthUser() {
  localStorage.removeItem(AUTH_KEY);
}

function normalizeEmail(email) {
  return (email || "").trim().toLowerCase();
}

function validatePassword(pw) {
  // simple pour démo (min 8, 1 chiffre)
  return typeof pw === "string" && pw.length >= 8 && /\d/.test(pw);
}

// ====== UI header (index + autres pages) ======
(function initHeaderAuthUI() {
  const authButtons = document.getElementById("authButtons");
  const userBox = document.getElementById("userBox");
  const userHello = document.getElementById("userHello");
  const logoutBtn = document.getElementById("logoutBtn");

  // Si les éléments n'existent pas sur la page, on ignore
  if (!authButtons || !userBox || !userHello || !logoutBtn) return;

  const user = getAuthUser();
  if (user) {
    authButtons.classList.add("hidden");
    userBox.classList.remove("hidden");
    userHello.textContent = `Bonjour, ${user.firstName}`;
  } else {
    userBox.classList.add("hidden");
    authButtons.classList.remove("hidden");
  }

  logoutBtn.addEventListener("click", () => {
    clearAuthUser();
    window.location.href = "index.html";
  });
})();

// ====== Register ======
function registerUser({ firstName, lastName, email, password, role }) {
  const users = getUsers();
  const em = normalizeEmail(email);

  if (!firstName || !lastName || !em || !password) {
    throw new Error("Merci de remplir tous les champs.");
  }
  if (!validatePassword(password)) {
    throw new Error("Mot de passe trop faible (min 8 caractères + 1 chiffre).");
  }
  if (users.some(u => u.email === em)) {
    throw new Error("Un compte existe déjà avec cet email.");
  }

  const newUser = {
    id: crypto?.randomUUID ? crypto.randomUUID() : String(Date.now()),
    firstName: firstName.trim(),
    lastName: lastName.trim(),
    email: em,
    // ⚠️ démo uniquement — en vrai: hash côté serveur
    password: password,
    role: role || "particulier",
    createdAt: new Date().toISOString()
  };

  users.push(newUser);
  setUsers(users);

  // auto-login après inscription
  setAuthUser({
    id: newUser.id,
    firstName: newUser.firstName,
    lastName: newUser.lastName,
    email: newUser.email,
    role: newUser.role
  });

  return newUser;
}

// ====== Login ======
function loginUser({ email, password }) {
  const users = getUsers();
  const em = normalizeEmail(email);

  const found = users.find(u => u.email === em && u.password === password);
  if (!found) throw new Error("Email ou mot de passe incorrect.");

  setAuthUser({
    id: found.id,
    firstName: found.firstName,
    lastName: found.lastName,
    email: found.email,
    role: found.role
  });

  return found;
}

// Helpers pour pages login/register
window.UC_AUTH = {
  registerUser,
  loginUser,
  getAuthUser
};
