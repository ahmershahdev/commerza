(function () {
  if (window.CommerzaAuth) return;

  const USERS_KEY = "commerza_users";
  const SESSION_KEY = "commerza_session";
  const RESET_KEY = "commerza_reset_requests";

  function normalizeEmail(email) {
    return (email || "").trim().toLowerCase();
  }

  function getUsers() {
    const stored = localStorage.getItem(USERS_KEY);
    if (!stored) return [];
    try {
      return JSON.parse(stored);
    } catch (error) {
      return [];
    }
  }

  function saveUsers(users) {
    localStorage.setItem(USERS_KEY, JSON.stringify(users));
  }

  function findUserByEmail(email) {
    const normalized = normalizeEmail(email);
    return getUsers().find((user) => user.email === normalized) || null;
  }

  function registerUser(data) {
    const name = (data?.name || "").trim();
    const email = normalizeEmail(data?.email);
    const password = data?.password || "";
    const phone = (data?.phone || "").trim();

    if (!name || !email || !password || !phone) {
      return { ok: false, error: "All fields are required." };
    }

    const users = getUsers();
    if (users.some((user) => user.email === email)) {
      return { ok: false, error: "Email already registered." };
    }

    const newUser = {
      id: Date.now(),
      name,
      email,
      password,
      phone,
      createdAt: new Date().toISOString(),
    };

    users.push(newUser);
    saveUsers(users);
    return { ok: true, user: newUser };
  }

  function loginUser(email, password) {
    const user = findUserByEmail(email);
    if (!user || user.password !== password) {
      return { ok: false, error: "Invalid email or password." };
    }

    localStorage.setItem(
      SESSION_KEY,
      JSON.stringify({
        email: user.email,
        loggedInAt: new Date().toISOString(),
      }),
    );
    return { ok: true, user };
  }

  function logoutUser() {
    localStorage.removeItem(SESSION_KEY);
  }

  function getCurrentUser() {
    const session = localStorage.getItem(SESSION_KEY);
    if (!session) return null;
    try {
      const parsed = JSON.parse(session);
      return findUserByEmail(parsed.email);
    } catch (error) {
      return null;
    }
  }

  function updateUser(email, updates) {
    const users = getUsers();
    const normalized = normalizeEmail(email);
    const index = users.findIndex((user) => user.email === normalized);
    if (index === -1) return { ok: false, error: "User not found." };

    const nextEmail = normalizeEmail(updates.email || users[index].email);
    if (
      nextEmail !== normalized &&
      users.some((user) => user.email === nextEmail)
    ) {
      return { ok: false, error: "Email already in use." };
    }

    users[index] = {
      ...users[index],
      ...updates,
      email: nextEmail,
    };
    saveUsers(users);

    const session = localStorage.getItem(SESSION_KEY);
    if (session) {
      try {
        const parsed = JSON.parse(session);
        parsed.email = users[index].email;
        localStorage.setItem(SESSION_KEY, JSON.stringify(parsed));
      } catch (error) {
        // ignore
      }
    }

    return { ok: true, user: users[index] };
  }

  function updatePassword(email, currentPassword, newPassword) {
    const users = getUsers();
    const normalized = normalizeEmail(email);
    const index = users.findIndex((user) => user.email === normalized);
    if (index === -1) return { ok: false, error: "User not found." };
    if (users[index].password !== currentPassword) {
      return { ok: false, error: "Current password is incorrect." };
    }
    users[index].password = newPassword;
    saveUsers(users);
    return { ok: true };
  }

  function requestPasswordReset(email) {
    const user = findUserByEmail(email);
    if (!user) return { ok: false, error: "No account found for this email." };

    const resets = JSON.parse(localStorage.getItem(RESET_KEY) || "[]");
    resets.push({ email: user.email, requestedAt: new Date().toISOString() });
    localStorage.setItem(RESET_KEY, JSON.stringify(resets));
    return { ok: true };
  }

  window.CommerzaAuth = {
    registerUser,
    loginUser,
    logoutUser,
    getCurrentUser,
    updateUser,
    updatePassword,
    requestPasswordReset,
  };
})();
