(function () {
  if (window.__commerzaMediaProtectionEnabled) return;
  window.__commerzaMediaProtectionEnabled = true;

  document.addEventListener("contextmenu", (event) => {
    if (event.target.closest("img, video")) {
      event.preventDefault();
    }
  });

  document.addEventListener("dragstart", (event) => {
    if (event.target.closest("img, video")) {
      event.preventDefault();
    }
  });

  document.addEventListener("selectstart", (event) => {
    if (!event.target.closest("input, textarea, [contenteditable='true']")) {
      event.preventDefault();
    }
  });

  window.addEventListener(
    "wheel",
    (event) => {
      if (event.ctrlKey) {
        event.preventDefault();
      }
    },
    { passive: false },
  );

  window.addEventListener("keydown", (event) => {
    const blockedKeys = ["+", "=", "-", "_", "0"];
    if (event.ctrlKey && blockedKeys.includes(event.key)) {
      event.preventDefault();
    }
  });

  ["gesturestart", "gesturechange", "gestureend"].forEach((gestureName) => {
    window.addEventListener(
      gestureName,
      (event) => {
        event.preventDefault();
      },
      { passive: false },
    );
  });
})();
