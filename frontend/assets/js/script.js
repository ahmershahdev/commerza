(function () {
  function detectAppBaseUrl() {
    const baseElement = document.querySelector("base[href]");
    if (baseElement) {
      const baseHref = (baseElement.getAttribute("href") || "")
        .toString()
        .trim();
      if (baseHref) {
        try {
          const parsedBase = new URL(baseHref, window.location.href);
          const normalizedPath = parsedBase.pathname.replace(/\\/g, "/");
          const withTrailingSlash = normalizedPath.endsWith("/")
            ? normalizedPath
            : `${normalizedPath}/`;
          return `${parsedBase.origin}${withTrailingSlash}`;
        } catch (error) {
          // Ignore invalid base href and continue with script detection.
        }
      }
    }

    const marker = "/frontend/assets/js/script.js";
    const candidateScripts = [];

    if (document.currentScript) {
      candidateScripts.push(document.currentScript);
    }

    const scripts = document.getElementsByTagName("script");
    for (let index = 0; index < scripts.length; index += 1) {
      candidateScripts.push(scripts[index]);
    }

    for (let index = candidateScripts.length - 1; index >= 0; index -= 1) {
      const script = candidateScripts[index];
      if (!script) {
        continue;
      }

      const src = (script.getAttribute("src") || script.src || "").toString();
      if (!src) {
        continue;
      }

      let parsed = null;
      try {
        parsed = new URL(src, window.location.href);
      } catch (error) {
        parsed = null;
      }

      if (!parsed) {
        continue;
      }

      const normalizedPath = parsed.pathname.replace(/\\/g, "/");
      const markerIndex = normalizedPath.toLowerCase().lastIndexOf(marker);
      if (markerIndex >= 0) {
        return `${parsed.origin}${normalizedPath.slice(0, markerIndex + 1)}`;
      }
    }

    const pathname = window.location.pathname.replace(/\\/g, "/");
    const segments = pathname.split("/").filter(Boolean);
    const isSafeSegment = (value) => /^[a-z0-9_-]+$/i.test(value || "");
    const projectSegment = segments.find((segment) =>
      /^commerza$/i.test(segment),
    );

    if (projectSegment && isSafeSegment(projectSegment)) {
      return `${window.location.origin}/${projectSegment}/`;
    }

    if (segments.length > 0 && isSafeSegment(segments[0])) {
      return `${window.location.origin}/${segments[0]}/`;
    }

    return `${window.location.origin}/`;
  }

  const appBaseUrl = detectAppBaseUrl();

  try {
    const parsedAppBase = new URL(appBaseUrl, window.location.href);
    const normalizedPath = parsedAppBase.pathname.replace(/\\/g, "/");
    const withTrailingSlash = normalizedPath.endsWith("/")
      ? normalizedPath
      : `${normalizedPath}/`;
    window.CommerzaAppBasePath = withTrailingSlash;
    window.CommerzaAppBaseUrl = `${parsedAppBase.origin}${withTrailingSlash}`;
  } catch (error) {
    window.CommerzaAppBasePath = "/";
    window.CommerzaAppBaseUrl = appBaseUrl;
  }

  function resolveAppUrl(path) {
    const rawPath = (path || "").toString().trim();
    if (/^(https?:)?\/\//i.test(rawPath)) {
      return rawPath;
    }

    return `${appBaseUrl}${rawPath.replace(/^\/+/, "")}`;
  }

  function normalizeCommerzaHref(href) {
    const rawHref = (href || "").toString().trim();
    if (!rawHref) {
      return "";
    }

    if (rawHref === "#" || rawHref.startsWith("#")) {
      return rawHref;
    }

    let parsed = null;
    try {
      parsed = new URL(rawHref, window.location.href);
    } catch (error) {
      parsed = null;
    }

    if (!parsed) {
      return rawHref;
    }

    const normalizedPath = parsed.pathname.replace(/\\/g, "/");
    const malformedPrefix = /^\/C:\/xampp\/htdocs\/commerza\//i;
    if (!malformedPrefix.test(normalizedPath)) {
      return rawHref;
    }

    const fixedPath = normalizedPath.replace(malformedPrefix, "/commerza/");
    parsed.pathname = fixedPath;
    return parsed.toString();
  }

  function closestFromEventTarget(target, selector) {
    if (!selector || typeof Element === "undefined") {
      return null;
    }

    if (!(target instanceof Element)) {
      return null;
    }

    if (typeof target.closest !== "function") {
      return null;
    }

    return target.closest(selector);
  }

  function installMalformedLinkGuard() {
    if (document.documentElement.dataset.commerzaLinkGuardInstalled === "1") {
      return;
    }

    document.documentElement.dataset.commerzaLinkGuardInstalled = "1";

    document.addEventListener(
      "click",
      function (event) {
        const anchor = closestFromEventTarget(event.target, "a[href]");

        if (!anchor) {
          return;
        }

        const currentHref = (anchor.getAttribute("href") || "").toString();
        if (!currentHref) {
          return;
        }

        const normalizedHref = normalizeCommerzaHref(currentHref);
        if (!normalizedHref || normalizedHref === currentHref) {
          return;
        }

        anchor.setAttribute("href", normalizedHref);
      },
      true,
    );
  }

  function installCartAnchorGuard() {
    if (
      document.documentElement.dataset.commerzaCartAnchorGuardInstalled === "1"
    ) {
      return;
    }

    document.documentElement.dataset.commerzaCartAnchorGuardInstalled = "1";

    document.addEventListener(
      "click",
      function (event) {
        const anchor = closestFromEventTarget(
          event.target,
          "a.product-btn-cart, a.product-btn-buy",
        );

        if (!anchor) {
          return;
        }

        const href = (anchor.getAttribute("href") || "").toString().trim();
        if (
          href === "" ||
          href === "#" ||
          href.startsWith("#") ||
          href.endsWith("#")
        ) {
          event.preventDefault();
        }
      },
      true,
    );
  }

  function installGlobalProductCardTracking() {
    if (
      document.documentElement.dataset.commerzaCardTrackingInstalled === "1"
    ) {
      return;
    }

    if (
      window.matchMedia &&
      !window.matchMedia("(hover: hover) and (pointer: fine)").matches
    ) {
      return;
    }

    const setCardMotion = function (card, pointerX, pointerY) {
      if (!card || !card.getBoundingClientRect) {
        return;
      }

      const rect = card.getBoundingClientRect();
      if (rect.width <= 0 || rect.height <= 0) {
        return;
      }

      const relativeX = (pointerX - rect.left) / rect.width;
      const relativeY = (pointerY - rect.top) / rect.height;
      const rotateY = (relativeX - 0.5) * 10;
      const rotateX = (0.5 - relativeY) * 8;
      const shiftX = (relativeX - 0.5) * 8;
      const shiftY = (relativeY - 0.5) * 2;
      const pointerX = Math.min(100, Math.max(0, relativeX * 100));
      const pointerY = Math.min(100, Math.max(0, relativeY * 100));

      card.style.setProperty("--pc-rotate-x", `${rotateX.toFixed(2)}deg`);
      card.style.setProperty("--pc-rotate-y", `${rotateY.toFixed(2)}deg`);
      card.style.setProperty("--pc-shift-x", `${shiftX.toFixed(2)}px`);
      card.style.setProperty("--pc-shift-y", `${shiftY.toFixed(2)}px`);
      card.style.setProperty("--pc-pointer-x", `${pointerX.toFixed(2)}%`);
      card.style.setProperty("--pc-pointer-y", `${pointerY.toFixed(2)}%`);
    };

    const resetCardMotion = function (card) {
      if (!card || !card.style) {
        return;
      }

      card.style.setProperty("--pc-rotate-x", "0deg");
      card.style.setProperty("--pc-rotate-y", "0deg");
      card.style.setProperty("--pc-shift-x", "0px");
      card.style.setProperty("--pc-shift-y", "0px");
      card.style.setProperty("--pc-pointer-x", "50%");
      card.style.setProperty("--pc-pointer-y", "50%");
    };

    document.documentElement.dataset.commerzaCardTrackingInstalled = "1";

    document.addEventListener("mousemove", function (event) {
      const card = closestFromEventTarget(event.target, ".product-card");
      if (!card) {
        return;
      }

      setCardMotion(card, event.clientX, event.clientY);
    });

    document.addEventListener("mouseout", function (event) {
      const card = closestFromEventTarget(event.target, ".product-card");
      if (!card) {
        return;
      }

      const nextTarget = event.relatedTarget;
      if (nextTarget && card.contains(nextTarget)) {
        return;
      }

      resetCardMotion(card);
    });

    window.addEventListener("blur", function () {
      const cards = document.querySelectorAll(".product-card");
      cards.forEach(function (card) {
        resetCardMotion(card);
      });
    });
  }

  installMalformedLinkGuard();
  installCartAnchorGuard();
  installGlobalProductCardTracking();

  const moduleCandidates = [
    [
      "frontend/assets/js/modules/core/site-settings.js",
      "frontend/assets/js/modules/01-settings.js",
    ],
    [
      "frontend/assets/js/modules/core/notifications.js",
      "frontend/assets/js/modules/02-notifications.js",
    ],
    [
      "frontend/assets/js/modules/features/account.js",
      "frontend/assets/js/modules/03-account.js",
    ],
    [
      "frontend/assets/js/modules/features/products.js",
      "frontend/assets/js/modules/05-products.js",
    ],
    [
      "frontend/assets/js/modules/features/newsletter.js",
      "frontend/assets/js/modules/06-newsletter.js",
    ],
    [
      "frontend/assets/js/modules/features/order-tracking.js",
      "frontend/assets/js/modules/07-order-tracking.js",
    ],
    [
      "frontend/assets/js/modules/features/wishlist-state.js",
      "frontend/assets/js/modules/08-wishlist-state.js",
    ],
    [
      "frontend/assets/js/modules/features/compare-core.js",
      "frontend/assets/js/modules/09-compare-core.js",
    ],
    [
      "frontend/assets/js/modules/features/wishlist-actions.js",
      "frontend/assets/js/modules/10-wishlist-actions.js",
    ],
    [
      "frontend/assets/js/modules/features/compare-render.js",
      "frontend/assets/js/modules/11-compare-render.js",
    ],
    [
      "frontend/assets/js/modules/04-document-ready.js",
      "frontend/assets/js/modules/bootstrap/document-ready.js",
    ],
  ];

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      const script = document.createElement("script");
      const resolvedSrc = resolveAppUrl(src);
      script.src = resolvedSrc;
      script.async = false;
      script.onload = function () {
        resolve();
      };
      script.onerror = function () {
        reject(new Error("Unable to load script module: " + resolvedSrc));
      };
      document.head.appendChild(script);
    });
  }

  function loadFromCandidates(candidates) {
    const options = Array.isArray(candidates) ? candidates : [candidates];

    function tryAt(index) {
      if (index >= options.length) {
        return Promise.reject(
          new Error("Unable to load any script module: " + options.join(", ")),
        );
      }

      return loadScript(options[index]).catch(function () {
        return tryAt(index + 1);
      });
    }

    return tryAt(0);
  }

  window.CommerzaModulesReady = moduleCandidates.reduce(function (
    chain,
    candidates,
  ) {
    return chain.then(function () {
      return loadFromCandidates(candidates);
    });
  }, Promise.resolve());

  window.commerzaOnReady = function (callback) {
    const run = function () {
      window.CommerzaModulesReady.then(function () {
        callback();
      }).catch(function (error) {
        console.error(error);
      });
    };

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", run, { once: true });
      return;
    }

    run();
  };

  window.CommerzaModulesReady.catch(function (error) {
    console.error(error);
  });
})();
