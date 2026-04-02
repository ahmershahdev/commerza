(function () {
  const moduleFiles = [
    "frontend/assets/js/modules/01-settings.js",
    "frontend/assets/js/modules/02-notifications.js",
    "frontend/assets/js/modules/03-account.js",
    "frontend/assets/js/modules/05-products.js",
    "frontend/assets/js/modules/06-newsletter.js",
    "frontend/assets/js/modules/07-order-tracking.js",
    "frontend/assets/js/modules/08-wishlist-state.js",
    "frontend/assets/js/modules/09-compare-core.js",
    "frontend/assets/js/modules/10-wishlist-actions.js",
    "frontend/assets/js/modules/11-compare-render.js",
    "frontend/assets/js/modules/04-document-ready.js",
  ];

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      const script = document.createElement("script");
      script.src = src;
      script.async = false;
      script.onload = function () {
        resolve();
      };
      script.onerror = function () {
        reject(new Error("Unable to load script module: " + src));
      };
      document.head.appendChild(script);
    });
  }

  window.CommerzaModulesReady = moduleFiles.reduce(function (chain, src) {
    return chain.then(function () {
      return loadScript(src);
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
