(function () {
  if (window.__commerzaMediaProtectionEnabled) return;
  window.__commerzaMediaProtectionEnabled = true;
  const ELEMENT_NODE = 1;

  function eventTargetElement(event) {
    const target = event ? event.target : null;
    if (!target) {
      return null;
    }

    if (target.nodeType === ELEMENT_NODE) {
      return target;
    }

    if (
      target.parentElement &&
      target.parentElement.nodeType === ELEMENT_NODE
    ) {
      return target.parentElement;
    }

    return null;
  }

  function isProtectedMediaTarget(event) {
    const element = eventTargetElement(event);
    if (!element) {
      return false;
    }

    if (typeof element.closest === "function") {
      return !!element.closest("img, video");
    }

    if (typeof element.matches === "function") {
      return element.matches("img, video");
    }

    return false;
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
