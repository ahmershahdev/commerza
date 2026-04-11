(function () {
  const script = document.currentScript;
  if (!script) {
    return;
  }

  const csrfToken = (script.getAttribute("data-csrf-token") || "")
    .toString()
    .trim();
  window.CommerzaCsrfToken = csrfToken;
})();
