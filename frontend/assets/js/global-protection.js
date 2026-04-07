(function () {
  if (window.__commerzaMediaProtectionEnabled) return;
  window.__commerzaMediaProtectionEnabled = true;

  function eventTargetElement(event) {
    const target = event ? event.target : null;
    if (!target) {
      return null;
    }

    if (target.nodeType === Node.ELEMENT_NODE) {
      return target;
    }

    if (
      target.parentElement &&
      target.parentElement.nodeType === Node.ELEMENT_NODE
    ) {
      return target.parentElement;
    }

    return null;
  }

  function isProtectedMediaTarget(event) {
    const element = eventTargetElement(event);
    if (!element || typeof element.closest !== "function") {
      return false;
    }

    return !!element.closest("img, video");
  }

  document.addEventListener("contextmenu", (event) => {
    if (isProtectedMediaTarget(event)) {
      event.preventDefault();
    }
  });

  document.addEventListener("dragstart", (event) => {
    if (isProtectedMediaTarget(event)) {
      event.preventDefault();
    }
  });

  document.addEventListener("selectstart", (event) => {
    if (isProtectedMediaTarget(event)) {
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
