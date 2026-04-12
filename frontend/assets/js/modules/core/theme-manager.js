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
  const TOGGLE_LOCK_MS = 3000;
  const ROOT = document.documentElement;

  let transitionTimer = 0;
  let toggleLockTimer = 0;
  let isToggleLocked = false;
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

      [data-theme-toggle] {
        position: relative;
        overflow: hidden;
        isolation: isolate;
      }

      [data-theme-toggle]::before {
        content: "";
        position: absolute;
        inset: 0;
        border-radius: inherit;
        padding: 2px;
        background: conic-gradient(
          from 0deg,
          rgba(147, 197, 253, 0.08),
          rgba(96, 165, 250, 0.86),
          rgba(30, 58, 138, 0.92),
          rgba(147, 197, 253, 0.08)
        );
        -webkit-mask:
          linear-gradient(#000 0 0) content-box,
          linear-gradient(#000 0 0);
        -webkit-mask-composite: xor;
        mask-composite: exclude;
        opacity: 0;
        pointer-events: none;
        z-index: 2;
      }

      [data-theme-toggle]::after {
        content: "";
        position: absolute;
        left: -42%;
        top: 0;
        width: 42%;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(
          100deg,
          rgba(255, 255, 255, 0),
          rgba(191, 219, 254, 0.84),
          rgba(255, 255, 255, 0)
        );
        opacity: 0;
        pointer-events: none;
        z-index: 1;
      }

      [data-theme-toggle][data-theme-locked="1"] {
        cursor: wait !important;
        pointer-events: none !important;
      }

      [data-theme-toggle][data-theme-locked="1"]::before {
        opacity: 1;
        animation: commerzaThemeLockRing 1.04s linear infinite;
      }

      [data-theme-toggle][data-theme-locked="1"]::after {
        opacity: 0.96;
        animation: commerzaThemeLockFlare 1.16s ease-in-out infinite;
      }

      @keyframes commerzaThemeLockRing {
        to {
          transform: rotate(1turn);
        }
      }

      @keyframes commerzaThemeLockFlare {
        0% {
          transform: translateX(0%);
        }

        100% {
          transform: translateX(340%);
        }
      }

      .commerza-theme-curtain {
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 2147483550;
        opacity: 0;
        transform: translateY(-12%) scale(1.02);
        background:
          radial-gradient(
            circle at var(--commerza-curtain-x, 50%) var(--commerza-curtain-y, 50%),
            var(--commerza-curtain-focus, rgba(147, 197, 253, 0.4)),
            transparent 56%
          ),
          linear-gradient(
            180deg,
            var(--commerza-curtain-tone, rgba(246, 250, 255, 0.92)),
            transparent 70%
          );
      }

      .commerza-theme-curtain.is-active {
        animation: commerzaThemeCurtain 760ms cubic-bezier(0.22, 1, 0.36, 1)
          forwards;
      }

      @keyframes commerzaThemeCurtain {
        0% {
          opacity: 0;
          transform: translateY(-12%) scale(1.02);
        }

        38% {
          opacity: 0.94;
          transform: translateY(0%) scale(1);
        }

        100% {
          opacity: 0;
          transform: translateY(14%) scale(1.01);
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
    }, 640);
  }

  function setToggleLockedState(locked) {
    isToggleLocked = locked;

    document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
      button.dataset.themeLocked = locked ? "1" : "0";
      button.setAttribute("aria-busy", locked ? "true" : "false");
      if (button.tagName === "BUTTON") {
        button.disabled = locked;
      }
    });
  }

  function startToggleCooldown() {
    setToggleLockedState(true);
    window.clearTimeout(toggleLockTimer);
    toggleLockTimer = window.setTimeout(() => {
      setToggleLockedState(false);
    }, TOGGLE_LOCK_MS);
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

  function runThemeCurtain(nextTheme, origin) {
    if (!document.body) {
      return;
    }

    const curtain = document.createElement("span");
    curtain.className = "commerza-theme-curtain";
    curtain.style.setProperty("--commerza-curtain-x", `${origin.x}px`);
    curtain.style.setProperty("--commerza-curtain-y", `${origin.y}px`);
    curtain.style.setProperty(
      "--commerza-curtain-tone",
      nextTheme === LIGHT_THEME
        ? "rgba(246, 250, 255, 0.9)"
        : "rgba(8, 13, 24, 0.9)",
    );
    curtain.style.setProperty(
      "--commerza-curtain-focus",
      nextTheme === LIGHT_THEME
        ? "rgba(147, 197, 253, 0.5)"
        : "rgba(30, 58, 138, 0.42)",
    );

    document.body.appendChild(curtain);
    window.requestAnimationFrame(() => {
      curtain.classList.add("is-active");
    });

    curtain.addEventListener(
      "animationend",
      () => {
        curtain.remove();
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
    if (isToggleLocked) {
      button.dataset.themeLocked = "1";
      button.setAttribute("aria-busy", "true");
      if (button.tagName === "BUTTON") {
        button.disabled = true;
      }
    }

    button.addEventListener("click", (event) => {
      event.preventDefault();

      if (isToggleLocked) {
        return;
      }

      const nextTheme = activeTheme === DARK_THEME ? LIGHT_THEME : DARK_THEME;
      const origin = resolveOrigin(button, event);
      startToggleCooldown();
      runThemeCurtain(nextTheme, origin);
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
