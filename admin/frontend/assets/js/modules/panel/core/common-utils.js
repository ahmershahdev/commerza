function normalizeEmailValue(email) {
  return (email || "").toString().trim().toLowerCase();
}

function escapeHtml(value) {
  return (value || "")
    .toString()
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/\"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function sanitizeAdminMediaUrl(value) {
  const raw = (value || "").toString().trim();
  if (!raw) {
    return "";
  }

  if (!/^(https?:\/\/|\.\.\/\.\.\/|\/|frontend\/assets\/)/i.test(raw)) {
    return "";
  }

  return raw.replace(/[\u0000-\u001F\u007F]/g, "");
}

function formatPkr(value) {
  return `PKR ${Number(value || 0).toLocaleString()}`;
}

function isCodPaymentMethod(paymentMethod) {
  const normalized = (paymentMethod || "").toString().toLowerCase().trim();
  if (!normalized) return false;
  return (
    normalized === "cod" ||
    normalized.includes("cash on delivery") ||
    /\bcod\b/i.test(normalized)
  );
}

function isStripePaymentMethod(paymentMethod) {
  const normalized = (paymentMethod || "").toString().toLowerCase().trim();
  if (!normalized) return false;
  return normalized === "stripe" || normalized.includes("stripe");
}

function normalizePaymentMethodLabel(paymentMethodRaw) {
  const raw = (paymentMethodRaw || "").toString().trim();
  if (!raw) {
    return "";
  }

  if (isCodPaymentMethod(raw)) {
    return "COD";
  }

  if (isStripePaymentMethod(raw)) {
    return "Stripe";
  }

  return "Legacy Payment";
}

function resolveOrderPaymentBadge(paymentStatusRaw, paymentMethodRaw) {
  const paymentStatus = (paymentStatusRaw || "").toString().toLowerCase();
  const paymentMethod = (paymentMethodRaw || "").toString().trim();
  const paymentMethodLabel = normalizePaymentMethodLabel(paymentMethod);
  const isCodMethod = isCodPaymentMethod(paymentMethod);

  if (paymentStatus === "refunded") {
    return { label: "Refunded", className: "bg-secondary" };
  }

  if (paymentStatus === "partially_refunded") {
    return { label: "Refund Pending", className: "bg-warning text-dark" };
  }

  if (paymentStatus === "paid") {
    if (paymentMethodLabel !== "") {
      return { label: paymentMethodLabel, className: "bg-success" };
    }

    return { label: "Paid", className: "bg-success" };
  }

  if (paymentMethodLabel !== "") {
    if (isCodMethod) {
      return { label: "COD", className: "bg-info text-dark" };
    }

    return {
      label: paymentMethodLabel,
      className: "bg-dark border border-info text-info",
    };
  }

  if (paymentStatus === "unpaid" && isCodMethod) {
    return { label: "COD", className: "bg-info text-dark" };
  }

  if (paymentStatus === "unpaid") {
    return { label: "Unpaid", className: "bg-dark border border-secondary" };
  }

  return {
    label: paymentMethod || "N/A",
    className: "bg-dark border border-secondary",
  };
}

function readJsonStorage(key, fallback) {
  const stored = sessionStorage.getItem(key);
  if (!stored) return fallback;
  try {
    return JSON.parse(stored);
  } catch (error) {
    return fallback;
  }
}

function loadDismissedNotificationSignatures() {
  try {
    const raw = localStorage.getItem(ADMIN_NOTIFICATIONS_DISMISSED_KEY);
    if (!raw) {
      dismissedNotificationSignatures = new Set();
      return;
    }

    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) {
      dismissedNotificationSignatures = new Set();
      return;
    }

    dismissedNotificationSignatures = new Set(
      parsed
        .map((item) => (item || "").toString().trim())
        .filter((item) => item !== ""),
    );
  } catch (_error) {
    dismissedNotificationSignatures = new Set();
  }
}

function saveDismissedNotificationSignatures() {
  try {
    localStorage.setItem(
      ADMIN_NOTIFICATIONS_DISMISSED_KEY,
      JSON.stringify(Array.from(dismissedNotificationSignatures)),
    );
  } catch (_error) {
    // Ignore persistence failures in private browsing modes.
  }
}

function getAdminOrdersData() {
  if (Array.isArray(adminOrders) && adminOrders.length) {
    return adminOrders;
  }
  return readJsonStorage(ORDERS_KEY, []);
}

function getAdminCustomersData() {
  if (Array.isArray(adminCustomers) && adminCustomers.length) {
    return adminCustomers;
  }
  return [];
}

function setAdminOrdersPayload(payload) {
  adminOrders = Array.isArray(payload?.orders) ? payload.orders : [];
  adminCustomers = Array.isArray(payload?.customers) ? payload.customers : [];
  adminMetrics =
    payload?.metrics && typeof payload.metrics === "object"
      ? payload.metrics
      : null;
  adminRefunds = Array.isArray(payload?.refunds) ? payload.refunds : [];
  adminBlacklist = Array.isArray(payload?.blacklist) ? payload.blacklist : [];
  adminBlacklistNoticeVisible = admin_parse_boolean(
    payload?.blacklistNoticeVisible,
    true,
  );

  const shipping =
    payload?.shippingConfig && typeof payload.shippingConfig === "object"
      ? payload.shippingConfig
      : {};

  adminShippingConfig = {
    flatFee: Math.max(0, Number(shipping?.flatFee ?? 1000) || 0),
    freeShippingOver: Math.max(
      0,
      Number(shipping?.freeShippingOver ?? 500) || 0,
    ),
  };

  sessionStorage.setItem(ORDERS_KEY, JSON.stringify(adminOrders));
  syncBlacklistNoticeToggleUi();
}

function applyOrderStatusPatch(payload) {
  const patchedOrder = payload?.order;
  if (patchedOrder && patchedOrder.orderId) {
    const orderId = (patchedOrder.orderId || "").toString();
    const index = adminOrders.findIndex(
      (item) => (item?.orderId || "").toString() === orderId,
    );

    if (index >= 0) {
      adminOrders[index] = {
        ...adminOrders[index],
        status: patchedOrder.status || adminOrders[index].status,
        paymentStatus:
          patchedOrder.paymentStatus || adminOrders[index].paymentStatus,
      };
    }
  }

  if (payload?.metrics && typeof payload.metrics === "object") {
    adminMetrics = payload.metrics;
  }

  sessionStorage.setItem(ORDERS_KEY, JSON.stringify(adminOrders));
}

async function loadAdminOrdersData(silent = false) {
  if (!admin_can_use_orders_summary_api()) {
    return false;
  }

  try {
    const response = await fetch(`${ADMIN_ORDERS_API}?action=summary`, {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store",
    });

    const result = await response.json();

    if (!response.ok || !result?.ok) {
      throw new Error(result?.message || "Unable to load orders from server.");
    }

    setAdminOrdersPayload(result?.payload || {});
    displayRecentOrders();
    displayAllOrders();
    displayAllCustomers();
    calculateDashboardMetrics();
    renderAnalyticsSection();
    renderRefundRequests();
    renderBlacklistTable();
    renderShippingConfigCard();
    updateNotifications();

    if ($("#emailSection").length) {
      emailDirectory = buildEmailDirectory();
      renderEmailRecipients();
    }

    return true;
  } catch (error) {
    if (!silent) {
      showNotification(
        error?.message || "Unable to sync orders and customers.",
        "danger",
      );
    }
    return false;
  }
}

async function adminPostJson(url, payload) {
  const action = (payload?.action || "post").toString().trim().toLowerCase();
  const requestId = `admin-${action}-${Date.now().toString(36)}-${Math.random().toString(16).slice(2, 14)}`;

  const response = await fetch(url, {
    method: "POST",
    credentials: "same-origin",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": ADMIN_CSRF_TOKEN,
      "X-Request-ID": requestId,
    },
    body: JSON.stringify(payload),
  });

  let result = null;
  try {
    result = await response.json();
  } catch (error) {
    result = null;
  }

  if (!response.ok || !result?.ok) {
    const message = result?.message || "Request failed.";
    throw new Error(message);
  }

  return result;
}

function formatUploadSize(bytes) {
  const safeBytes = Math.max(0, Number.parseInt(bytes, 10) || 0);
  if (safeBytes >= 1024 * 1024) {
    return `${(safeBytes / (1024 * 1024)).toFixed(2)} MB`;
  }
  return `${(safeBytes / 1024).toFixed(1)} KB`;
}

function uploadFileLabel(name, maxLength = 36) {
  const safeName = (name || "file").toString();
  if (safeName.length <= maxLength) {
    return safeName;
  }
  return `${safeName.slice(0, maxLength - 3)}...`;
}
