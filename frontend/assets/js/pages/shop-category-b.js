document.addEventListener("DOMContentLoaded", function () {
  if (typeof window.commerzaOnReady !== "function") {
    return;
  }

  window.commerzaOnReady(function () {
    loadProductsBySection(
      "signature-collection",
      "signature-collection-products-container",
    );
    loadProductsBySection(
      "sports-sales-division",
      "sports-division-products-container",
    );
  });
});
