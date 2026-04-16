(function () {
  const form = document.getElementById("orderTrackingForm");
  const resultContainer = document.getElementById("orderTrackingResult");
  const submitBtn = document.getElementById("orderTrackingSubmitBtn");

  if (
    !form ||
    !resultContainer ||
    !submitBtn ||
    typeof window.fetch !== "function"
  ) {
    return;
  }

  const statusBadgeClass = {
    delivered: "success",
    cancelled: "danger",
    refunded: "danger",
    shipped: "primary",
    processing: "info",
    confirmed: "info",
  };

  const escapeHtml = (value) =>
    String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");

  const statusClass = (value) => {
    const key = String(value || "")
      .trim()
      .toLowerCase();
    return statusBadgeClass[key] || "warning";
  };

  const toPkr = (value) => {
    const amount = Number(value || 0);
    return Number.isFinite(amount) ? amount.toLocaleString() + " PKR" : "0 PKR";
  };

  const sanitizeImageSrc = (value) => {
    const raw = String(value || "").trim();
    if (!raw) {
      return "";
    }

    const normalized = raw.replace(/[\u0000-\u001F\u007F]/g, "");
    if (!normalized) {
      return "";
    }

    if (/^(?:javascript|vbscript):/i.test(normalized)) {
      return "";
    }

    if (/^data:/i.test(normalized)) {
      return /^data:image\/(?:png|jpe?g|webp|gif);base64,[a-z0-9+/=\s]+$/i.test(
        normalized,
      )
        ? normalized
        : "";
    }

    if (/^(?:https?:)?\/\//i.test(normalized)) {
      return normalized;
    }

    if (
      normalized.startsWith("/") ||
      normalized.startsWith("./") ||
      normalized.startsWith("../")
    ) {
      return normalized;
    }

    return /^[a-z0-9][a-z0-9._/-]*$/i.test(normalized) ? normalized : "";
  };

  const renderAlert = (message, tone) => {
    const klass = tone === "danger" ? "danger" : "warning";
    resultContainer.innerHTML = `<div class="alert alert-${klass}" role="alert">${escapeHtml(message)}</div>`;
  };

  const renderResult = (payload) => {
    const order = payload?.order || {};
    const items = Array.isArray(payload?.items) ? payload.items : [];

    const itemsMarkup = items.length
      ? items
          .map((item) => {
            const imageSrc = sanitizeImageSrc(item.product_img);
            const hasImage = imageSrc !== "";
            return `
                  <div class="d-flex align-items-center gap-3 mb-2 p-2 rounded tracking-line-item">
                    ${hasImage ? `<img src="${escapeHtml(imageSrc)}" alt="${escapeHtml(item.product_name)}" class="tracking-line-thumb">` : ""}
                    <div class="flex-grow-1">
                      <p class="mb-0 fw-semibold tracking-detail-value">${escapeHtml(item.product_name)}</p>
                      <small class="tracking-line-meta">Qty: ${Number(item.quantity || 0)} | Unit: ${toPkr(item.unit_price)}</small>
                    </div>
                    <p class="mb-0 fw-semibold tracking-detail-value">${toPkr(item.line_total)}</p>
                  </div>
                `;
          })
          .join("")
      : '<p class="mb-0 tracking-detail-meta">No line items found for this order.</p>';

    resultContainer.innerHTML = `
          <div class="card product-card mb-4">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                  <h3 class="product-name mb-1">${escapeHtml(order.order_number)}</h3>
                  <p class="product-desc mb-0">Placed on ${escapeHtml(order.created_label)}</p>
                </div>
                <span class="badge rounded-pill bg-${statusClass(order.status)}">${escapeHtml(order.status)}</span>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <div class="p-3 rounded tracking-detail-card">
                    <p class="mb-1 tracking-detail-meta">Customer</p>
                    <p class="mb-1 fw-semibold tracking-detail-value">${escapeHtml(order.customer_name)}</p>
                    <p class="mb-1 tracking-detail-meta">${escapeHtml(order.customer_email)}</p>
                    <p class="mb-0 tracking-detail-meta">${escapeHtml(order.customer_phone)}</p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 rounded tracking-detail-card">
                    <p class="mb-1 tracking-detail-meta">Shipping Address</p>
                    <p class="mb-0 tracking-detail-value">${escapeHtml(order.address).replace(/\n/g, "<br>")}</p>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <p class="mb-2 tracking-detail-meta">Items</p>
                ${itemsMarkup}
              </div>

              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top border-secondary-subtle tracking-summary-row">
                <p class="mb-0 tracking-payment-meta">Payment: ${escapeHtml(order.payment_method)} (${escapeHtml(order.payment_status)})</p>
                <p class="mb-0 fw-bold tracking-total-value">Total: ${toPkr(order.grand_total)}</p>
              </div>
            </div>
          </div>
        `;
  };

  form.addEventListener("submit", async function (event) {
    event.preventDefault();

    const formData = new FormData(form);
    const defaultText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = "Tracking...";

    try {
      const response = await fetch("backend/api/order_tracking_api.php", {
        method: "POST",
        credentials: "same-origin",
        body: formData,
        cache: "no-store",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      });

      let data = {};
      try {
        data = await response.json();
      } catch (_jsonError) {
        data = {};
      }

      if (data && typeof data.csrf_token === "string") {
        const csrfInput = form.querySelector('input[name="csrf_token"]');
        if (csrfInput) {
          csrfInput.value = data.csrf_token;
        }
      }

      if (!response.ok || !data.ok) {
        renderAlert(
          data.message || "Unable to track your order right now.",
          response.status === 429 ? "warning" : "danger",
        );
        return;
      }

      renderResult(data.payload || {});
    } catch (_error) {
      renderAlert(
        "Unable to track your order right now. Please try again.",
        "danger",
      );
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = defaultText;
    }
  });
})();
