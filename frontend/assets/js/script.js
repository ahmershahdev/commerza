(function () {
  function detectAppBaseUrl() {
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
