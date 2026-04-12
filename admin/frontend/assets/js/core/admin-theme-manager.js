(function () {
  if (window.__commerzaAdminThemeManagerLoaded) {
    return;
  }
  window.__commerzaAdminThemeManagerLoaded = true;

  const STORAGE_KEY = "commerza_theme";
  const DARK_THEME = "dark";
  const LIGHT_THEME = "light";

  function normalizeTheme(value) {
    return value === LIGHT_THEME ? LIGHT_THEME : DARK_THEME;
  }

  function readStoredTheme() {
    try {
      return normalizeTheme(localStorage.getItem(STORAGE_KEY));
    } catch (_error) {
      return DARK_THEME;
    }
  }

  function writeStoredTheme(theme) {
    try {
      localStorage.setItem(STORAGE_KEY, theme);
    } catch (_error) {
      // Ignore storage failures.
    }
  }

  let activeTheme = normalizeTheme(
    document.documentElement.getAttribute("data-commerza-theme") ||
      readStoredTheme(),
  );

  function applyRootTheme(theme) {
    document.documentElement.setAttribute("data-commerza-theme", theme);
    document.documentElement.setAttribute("data-bs-theme", theme);
  }

  function applyBodyTheme(theme) {
    if (!document.body) {
      return;
    }

    document.body.classList.add("dark-theme");
    document.body.classList.toggle("light-theme", theme === LIGHT_THEME);
  }

  function renderToggleState(button) {
    if (!button) {
      return;
    }

    const icon = button.querySelector("[data-theme-icon]");
    const label = button.querySelector("[data-theme-label]");
    const isLight = activeTheme === LIGHT_THEME;

    if (icon) {
      icon.className = "bi " + (isLight ? "bi-sun-fill" : "bi-moon-stars-fill");
    }

    if (label) {
      label.textContent = isLight ? "Light Mode" : "Dark Mode";
    }

    button.setAttribute(
      "aria-label",
      isLight ? "Switch to dark mode" : "Switch to light mode",
    );
    button.setAttribute(
      "title",
      isLight ? "Switch to dark mode" : "Switch to light mode",
    );
    button.dataset.themeCurrent = activeTheme;
  }

  function renderAllToggles() {
    document
      .querySelectorAll("[data-theme-toggle]")
      .forEach((button) => renderToggleState(button));
  }

  function applyTheme(theme, persist) {
    activeTheme = normalizeTheme(theme);
    applyRootTheme(activeTheme);
    applyBodyTheme(activeTheme);
    renderAllToggles();

    if (persist) {
      writeStoredTheme(activeTheme);
    }
  }

  function bindToggle(button) {
    if (!button || button.dataset.adminThemeBound === "1") {
      return;
    }

    button.dataset.adminThemeBound = "1";
    renderToggleState(button);

    button.addEventListener("click", function (event) {
      event.preventDefault();
      const nextTheme = activeTheme === DARK_THEME ? LIGHT_THEME : DARK_THEME;
      applyTheme(nextTheme, true);
    });
  }

  function bindAllToggles() {
    document
      .querySelectorAll("[data-theme-toggle]")
      .forEach((button) => bindToggle(button));
  }

  function initialize() {
    applyTheme(activeTheme, false);
    bindAllToggles();
  }

  applyRootTheme(activeTheme);

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initialize, { once: true });
  } else {
    initialize();
  }

  window.addEventListener("storage", function (event) {
    if (event.key !== STORAGE_KEY) {
      return;
    }

    applyTheme(normalizeTheme(event.newValue), false);
  });
})();
