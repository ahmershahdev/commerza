document.addEventListener("DOMContentLoaded", function () {
  if (typeof window.commerzaOnReady !== "function") {
    return;
  }

  window.commerzaOnReady(function () {
    loadProductsBySection(
      "automatic-vault",
      "automatic-vault-products-container",
    );
    loadProductsBySection(
      "smart-evolution",
      "smart-evolution-products-container",
    );
  });
});
