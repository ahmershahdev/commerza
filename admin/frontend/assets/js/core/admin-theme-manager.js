(function () {
  if (window.__commerzaAdminThemeManagerLoaded) {
    return;
  }
  window.__commerzaAdminThemeManagerLoaded = true;

  const STORAGE_KEY = "commerza_theme";
  const DARK_THEME = "dark";
  const LIGHT_THEME = "light";
  const RUNTIME_STYLE_ID = "commerza-admin-theme-runtime-style";
  const TOGGLE_LOCK_MS = 3000;

  let toggleLockTimer = 0;
  let transitionTimer = 0;
  let isToggleLocked = false;

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

  function ensureRuntimeStyles() {
    if (document.getElementById(RUNTIME_STYLE_ID)) {
      return;
    }

    const style = document.createElement("style");
    style.id = RUNTIME_STYLE_ID;
    style.textContent = `
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
        animation: commerzaAdminThemeLockRing 1.04s linear infinite;
      }

      [data-theme-toggle][data-theme-locked="1"]::after {
        opacity: 0.96;
        animation: commerzaAdminThemeLockFlare 1.16s ease-in-out infinite;
      }

      @keyframes commerzaAdminThemeLockRing {
        to {
          transform: rotate(1turn);
        }
      }

      @keyframes commerzaAdminThemeLockFlare {
        0% {
          transform: translateX(0%);
        }

        100% {
          transform: translateX(340%);
        }
      }

      .commerza-admin-theme-curtain {
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 2147483550;
        opacity: 0;
        transform: translateY(-12%) scale(1.02);
        background:
          radial-gradient(
            circle at var(--commerza-admin-curtain-x, 50%) var(--commerza-admin-curtain-y, 50%),
            var(--commerza-admin-curtain-focus, rgba(147, 197, 253, 0.4)),
            transparent 56%
          ),
          linear-gradient(
            180deg,
            var(--commerza-admin-curtain-tone, rgba(246, 250, 255, 0.92)),
            transparent 70%
          );
      }

      .commerza-admin-theme-curtain.is-active {
        animation: commerzaAdminThemeCurtain 760ms cubic-bezier(0.22, 1, 0.36, 1)
          forwards;
      }

      @keyframes commerzaAdminThemeCurtain {
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

      html.commerza-admin-theme-animating *,
      html.commerza-admin-theme-animating *::before,
      html.commerza-admin-theme-animating *::after {
        transition: background-color 0.26s ease, color 0.24s ease,
          border-color 0.26s ease, box-shadow 0.26s ease !important;
      }
    `;

    document.head.appendChild(style);
  }

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

  function toggleVisualTransition() {
    document.documentElement.classList.add("commerza-admin-theme-animating");
    window.clearTimeout(transitionTimer);
    transitionTimer = window.setTimeout(() => {
      document.documentElement.classList.remove(
        "commerza-admin-theme-animating",
      );
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

  function runThemeCurtain(nextTheme, origin) {
    if (!document.body) {
      return;
    }

    const curtain = document.createElement("span");
    curtain.className = "commerza-admin-theme-curtain";
    curtain.style.setProperty("--commerza-admin-curtain-x", `${origin.x}px`);
    curtain.style.setProperty("--commerza-admin-curtain-y", `${origin.y}px`);
    curtain.style.setProperty(
      "--commerza-admin-curtain-tone",
      nextTheme === LIGHT_THEME
        ? "rgba(246, 250, 255, 0.9)"
        : "rgba(8, 13, 24, 0.9)",
    );
    curtain.style.setProperty(
      "--commerza-admin-curtain-focus",
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

    if (
      source === "pointer" &&
      event &&
      Number.isFinite(event.clientX) &&
      Number.isFinite(event.clientY)
    ) {
      return {
        x: Math.max(event.clientX, 0),
        y: Math.max(event.clientY, 0),
      };
    }

    return { x: 0, y: 0 };
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

  function applyTheme(theme, persist, animate = true) {
    activeTheme = normalizeTheme(theme);
    applyRootTheme(activeTheme);
    applyBodyTheme(activeTheme);
    renderAllToggles();

    document.dispatchEvent(
      new CustomEvent("commerza:admin-theme-change", {
        detail: {
          theme: activeTheme,
        },
      }),
    );

    if (persist) {
      writeStoredTheme(activeTheme);
    }

    if (animate) {
      toggleVisualTransition();
    }
  }

  function bindToggle(button) {
    if (!button || button.dataset.adminThemeBound === "1") {
      return;
    }

    button.dataset.adminThemeBound = "1";
    renderToggleState(button);
    if (isToggleLocked) {
      button.dataset.themeLocked = "1";
      button.setAttribute("aria-busy", "true");
      if (button.tagName === "BUTTON") {
        button.disabled = true;
      }
    }

    button.addEventListener("click", function (event) {
      event.preventDefault();

      if (isToggleLocked) {
        return;
      }

      const nextTheme = activeTheme === DARK_THEME ? LIGHT_THEME : DARK_THEME;
      const origin = resolveOrigin(button, event);
      startToggleCooldown();
      runThemeCurtain(nextTheme, origin);
      applyTheme(nextTheme, true, true);
    });
  }

  function bindAllToggles() {
    document
      .querySelectorAll("[data-theme-toggle]")
      .forEach((button) => bindToggle(button));
  }

  function initialize() {
    ensureRuntimeStyles();
    applyTheme(activeTheme, false, false);
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
