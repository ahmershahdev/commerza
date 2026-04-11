(function () {
  const printBtn = document.getElementById("printInvoiceBtn");
  if (!printBtn) {
    return;
  }

  printBtn.addEventListener("click", function () {
    window.print();
  });
})();
