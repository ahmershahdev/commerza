function initOrderTrackingPage() {
  const form = $("#orderTrackingForm");
  if (!form.length) return;

  form.on("submit", function () {
    const orderInput = $("#orderIdInput");
    const emailInput = $("#orderEmailInput");

    let orderId = (orderInput.val() || "").toString().trim().toUpperCase();
    if (orderId && !orderId.startsWith("#")) {
      orderId = `#${orderId}`;
    }
    orderInput.val(orderId);

    emailInput.val((emailInput.val() || "").toString().trim().toLowerCase());
  });
}