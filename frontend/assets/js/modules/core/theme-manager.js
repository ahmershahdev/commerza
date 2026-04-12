(function () {
  if (window.__commerzaThemeManagerLoaded) {
    return;
  }
  window.__commerzaThemeManagerLoaded = true;

  const STORAGE_KEY = "commerza_theme";
  const DARK_THEME = "dark";
  const LIGHT_THEME = "light";
  const RUNTIME_STYLE_ID = "commerza-theme-runtime-style";
  const FLOATING_TOGGLE_ID = "commerza-theme-floating-toggle";
  const ROOT = document.documentElement;

  let transitionTimer = 0;
  let activeTheme = sanitizeTheme(
    ROOT.getAttribute("data-commerza-theme") || readStoredTheme(),
  );

  function sanitizeTheme(theme) {
    return theme === LIGHT_THEME ? LIGHT_THEME : DARK_THEME;
  }

  function readStoredTheme() {
    try {
      return sanitizeTheme(localStorage.getItem(STORAGE_KEY));
    } catch (_error) {
      return DARK_THEME;
    }
  }

  function writeStoredTheme(theme) {
    try {
      localStorage.setItem(STORAGE_KEY, theme);
    } catch (_error) {
      // Ignore storage failures (private mode, blocked storage, etc.)
    }
  }

  function ensureRuntimeStyles() {
    if (document.getElementById(RUNTIME_STYLE_ID)) {
      return;
    }

    const style = document.createElement("style");
    style.id = RUNTIME_STYLE_ID;
    style.textContent = `
      #${FLOATING_TOGGLE_ID} {
        position: fixed;
        left: 1rem;
        bottom: 1rem;
        z-index: 2147483600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid rgba(255, 255, 255, 0.24);
        border-radius: 999px;
        background: linear-gradient(135deg, rgba(9, 15, 28, 0.96), rgba(20, 34, 63, 0.94));
        color: #f3f7ff;
        font-size: 0.9rem;
        font-weight: 600;
        line-height: 1;
        box-shadow: 0 14px 36px rgba(5, 8, 14, 0.3);
        padding: 0.7rem 0.95rem;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
      }

      #${FLOATING_TOGGLE_ID}:hover {
        transform: translateY(-1px);
        box-shadow: 0 18px 44px rgba(5, 8, 14, 0.36);
      }

      #${FLOATING_TOGGLE_ID}:focus-visible,
      [data-theme-toggle]:focus-visible {
        outline: 2px solid rgba(86, 136, 255, 0.88);
        outline-offset: 2px;
      }

      #${FLOATING_TOGGLE_ID}[data-theme-current="light"] {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(225, 238, 255, 0.94));
        color: #0e234a;
        border-color: rgba(13, 42, 92, 0.28);
      }

      #${FLOATING_TOGGLE_ID} .bi {
        font-size: 1rem;
      }

      #${FLOATING_TOGGLE_ID} [data-theme-icon],
      [data-theme-toggle] [data-theme-icon] {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        min-width: 1rem;
        opacity: 1 !important;
        visibility: visible !important;
        color: currentColor !important;
      }

      .commerza-theme-toggle-label {
        letter-spacing: 0.01em;
      }

      .commerza-theme-wipe {
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 2147483550;
        background: var(--commerza-wipe-color, rgba(244, 248, 255, 0.92));
        clip-path: circle(0 at var(--commerza-wipe-x, 0px) var(--commerza-wipe-y, 0px));
      }

      .commerza-theme-wipe.is-active {
        animation: commerzaThemeWipe 560ms cubic-bezier(0.22, 1, 0.36, 1) forwards;
      }

      @keyframes commerzaThemeWipe {
        0% {
          clip-path: circle(0 at var(--commerza-wipe-x, 0px) var(--commerza-wipe-y, 0px));
          opacity: 0.98;
        }

        100% {
          clip-path: circle(165vmax at var(--commerza-wipe-x, 0px) var(--commerza-wipe-y, 0px));
          opacity: 0;
        }
      }

      html.commerza-theme-animating *,
      html.commerza-theme-animating *::before,
      html.commerza-theme-animating *::after {
        transition: background-color 0.24s ease, color 0.22s ease, border-color 0.24s ease, box-shadow 0.24s ease !important;
      }

      body.dark-theme.light-theme {
        color-scheme: light;
      }

      button.offcanvas-action-btn-theme {
        width: 100%;
        border: 0;
        background: transparent;
        text-align: left;
      }

      @media (max-width: 767.98px) {
        #${FLOATING_TOGGLE_ID} {
          left: 0.75rem;
          bottom: 0.75rem;
          padding: 0.68rem 0.78rem;
          border-radius: 14px;
        }

        #${FLOATING_TOGGLE_ID} .commerza-theme-toggle-label {
          display: none;
        }
      }
    `;

    document.head.appendChild(style);
  }

  function setRootTheme(theme) {
    ROOT.setAttribute("data-commerza-theme", theme);
    ROOT.setAttribute("data-bs-theme", theme);
  }

  function setBodyTheme(theme) {
    if (!document.body) {
      return;
    }

    document.body.classList.add("dark-theme");
    document.body.classList.toggle("light-theme", theme === LIGHT_THEME);
  }

  function updateBootstrapTone(theme) {
    const isLight = theme === LIGHT_THEME;

    document.querySelectorAll(".navbar").forEach((navbar) => {
      navbar.classList.toggle("navbar-dark", !isLight);
      navbar.classList.toggle("navbar-light", isLight);
    });

    document
      .querySelectorAll(
        ".dropdown-menu.account-quick-menu, .dropdown-menu.admin-header-dropdown",
      )
      .forEach((menu) => {
        menu.classList.toggle("dropdown-menu-dark", !isLight);
      });

    document.querySelectorAll(".btn-close").forEach((button) => {
      button.classList.toggle("btn-close-white", !isLight);
    });
  }

  function toggleVisualTransition() {
    ROOT.classList.add("commerza-theme-animating");
    window.clearTimeout(transitionTimer);
    transitionTimer = window.setTimeout(() => {
      ROOT.classList.remove("commerza-theme-animating");
    }, 420);
  }

  function renderToggleVisualState(button) {
    if (!button) {
      return;
    }

    const icon = button.querySelector("[data-theme-icon]");
    const label = button.querySelector("[data-theme-label]");

    const isLight = activeTheme === LIGHT_THEME;
    const iconClass = isLight ? "bi-sun-fill" : "bi-moon-stars-fill";
    const labelText = isLight ? "Light Mode" : "Dark Mode";
    const nextAction = isLight ? "Switch to dark mode" : "Switch to light mode";

    if (icon) {
      icon.className = "bi " + iconClass;
    }

    if (label) {
      label.textContent = labelText;
    }

    button.dataset.themeCurrent = activeTheme;
    button.setAttribute("title", nextAction);
    button.setAttribute("aria-label", nextAction);
  }

  function updateAllToggleVisualStates() {
    document
      .querySelectorAll("[data-theme-toggle]")
      .forEach((button) => renderToggleVisualState(button));
  }

  function applyTheme(theme, options) {
    const normalized = sanitizeTheme(theme);
    const shouldPersist = !options || options.persist !== false;
    const shouldAnimate = !options || options.animate !== false;

    activeTheme = normalized;
    setRootTheme(normalized);
    setBodyTheme(normalized);
    updateBootstrapTone(normalized);
    updateAllToggleVisualStates();

    if (shouldPersist) {
      writeStoredTheme(normalized);
    }

    if (shouldAnimate) {
      toggleVisualTransition();
    }
  }

  function resolveOrigin(button, event) {
    const source = (button.getAttribute("data-theme-origin") || "")
      .toLowerCase()
      .trim();

    if (source === "center") {
      return {
        x: Math.max(window.innerWidth / 2, 0),
        y: Math.max(window.innerHeight / 2, 0),
      };
    }

    if (source === "pointer") {
      if (
        event &&
        Number.isFinite(event.clientX) &&
        Number.isFinite(event.clientY)
      ) {
        return {
          x: Math.max(event.clientX, 0),
          y: Math.max(event.clientY, 0),
        };
      }

      return {
        x: Math.max(window.innerWidth / 2, 0),
        y: Math.max(window.innerHeight / 2, 0),
      };
    }

    if (source === "left-top") {
      return { x: 0, y: 0 };
    }

    return {
      x: 0,
      y: 0,
    };
  }

  function runThemeWipe(nextTheme, origin) {
    if (!document.body) {
      return;
    }

    const wipe = document.createElement("span");
    wipe.className = "commerza-theme-wipe";
    wipe.style.setProperty("--commerza-wipe-x", `${origin.x}px`);
    wipe.style.setProperty("--commerza-wipe-y", `${origin.y}px`);
    wipe.style.setProperty(
      "--commerza-wipe-color",
      nextTheme === LIGHT_THEME
        ? "rgba(246, 250, 255, 0.94)"
        : "rgba(6, 11, 22, 0.92)",
    );

    document.body.appendChild(wipe);
    window.requestAnimationFrame(() => {
      wipe.classList.add("is-active");
    });

    wipe.addEventListener(
      "animationend",
      () => {
        wipe.remove();
      },
      { once: true },
    );
  }

  function bindToggleButton(button) {
    if (!button || button.dataset.themeToggleBound === "1") {
      return;
    }

    button.dataset.themeToggleBound = "1";
    renderToggleVisualState(button);

    button.addEventListener("click", (event) => {
      event.preventDefault();

      const nextTheme = activeTheme === DARK_THEME ? LIGHT_THEME : DARK_THEME;
      const origin = resolveOrigin(button, event);
      runThemeWipe(nextTheme, origin);
      applyTheme(nextTheme, { persist: true, animate: true });
    });
  }

  function bindAllToggleButtons() {
    document
      .querySelectorAll("[data-theme-toggle]")
      .forEach((button) => bindToggleButton(button));
  }

  function ensureFloatingToggle() {
    if (!document.body || document.getElementById(FLOATING_TOGGLE_ID)) {
      return;
    }

    const button = document.createElement("button");
    button.type = "button";
    button.id = FLOATING_TOGGLE_ID;
    button.setAttribute("data-theme-toggle", "floating");
    button.setAttribute("data-theme-origin", "left-top");
    button.innerHTML =
      '<i class="bi" data-theme-icon aria-hidden="true"></i><span class="commerza-theme-toggle-label" data-theme-label></span>';

    document.body.appendChild(button);
  }

  function initialize() {
    ensureRuntimeStyles();
    setRootTheme(activeTheme);
    setBodyTheme(activeTheme);
    updateBootstrapTone(activeTheme);
    ensureFloatingToggle();
    bindAllToggleButtons();
    updateAllToggleVisualStates();
  }

  window.addEventListener("storage", (event) => {
    if (event.key !== STORAGE_KEY) {
      return;
    }

    const nextTheme = sanitizeTheme(event.newValue);
    applyTheme(nextTheme, { persist: false, animate: false });
  });

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initialize, { once: true });
  } else {
    initialize();
  }
})();
