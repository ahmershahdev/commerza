let productsData = [];
let allSections = [];
let nextId = 41;
let nextSectionId = 1;
const SITE_SETTINGS_KEY = "commerza_site_settings";
let siteSettings = null;
let nextSocialId = 1;
let nextSliderId = 1;
const NEWSLETTER_SUBSCRIBERS_KEY = "commerza_newsletter_subscribers";
const NEWSLETTER_EMAIL_KEY = "commerza_newsletter_email";
const USERS_KEY = "commerza_users";
const ORDERS_KEY = "commerza_orders";
const EMAIL_TEMPLATES_KEY = "commerza_email_templates";
const EMAIL_OUTBOX_KEY = "commerza_email_outbox";
const EMAIL_MANUAL_RECIPIENTS_KEY = "commerza_email_manual_recipients";
const EMAIL_SUPPRESSED_KEY = "commerza_email_suppressed";
const VIEWERS_MODE_KEY = "commerza_viewers_mode";
const PAGE_META_KEY = "commerza_page_meta";
const PAGE_CONTENT_KEY = "commerza_page_content";
const ADMIN_RUNTIME = window.CommerzaAdminRuntime || {};
const ADMIN_CSRF_TOKEN = ADMIN_RUNTIME.csrfToken || "";
const ADMIN_PRODUCTS_SYNC_API = "../backend/products_sync_api.php";
const ADMIN_SECURITY_API = "../backend/security_api.php";
const ADMIN_ORDERS_API = "../backend/orders_api.php";
const ADMIN_VIEWERS_API = "../backend/viewers_api.php";
const ADMIN_WEBSITE_API = "../backend/website_api.php";
const ADMIN_MEDIA_API = "../backend/media_api.php";
const ADMIN_COUPONS_API = "../backend/coupons_api.php";
const ADMIN_REVIEWS_API = "../backend/reviews_api.php";
let adminOrders = [];
let adminCustomers = [];
let adminMetrics = null;
let adminRefunds = [];
let adminBlacklist = [];
let adminShippingConfig = {
  flatFee: 1000,
  freeShippingOver: 500,
};
let adminCoupons = [];
let adminReviews = [];
let productTrashItems = [];
let notificationsPausedUntil = 0;
const ADMIN_NOTIFICATIONS_DISMISSED_KEY =
  "commerza_admin_notifications_dismissed";
let dismissedNotificationSignatures = new Set();
const ORDER_STATUS_LOCKS = new Set();
let analyticsProfitLossChart = null;
let securityEventsState = {
  events: [],
  page: 1,
  perPage: 25,
  total: 0,
  totalPages: 0,
  filters: {
    eventType: "",
    severity: "",
    actorType: "",
    search: "",
    from: "",
    to: "",
  },
};

(function () {
  if (window.__commerzaMediaProtectionEnabled) return;
  window.__commerzaMediaProtectionEnabled = true;

  const isMediaTarget = (target) => {
    if (!target || typeof Element === "undefined") {
      return false;
    }

    return target instanceof Element && !!target.closest("img, video");
  };

  document.addEventListener("contextmenu", (event) => {
    if (isMediaTarget(event.target)) {
      event.preventDefault();
    }
  });

  document.addEventListener("dragstart", (event) => {
    if (isMediaTarget(event.target)) {
      event.preventDefault();
    }
  });

  document.addEventListener("selectstart", (event) => {
    if (isMediaTarget(event.target)) {
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

const DEFAULT_EMAIL_TEMPLATES = [
  {
    id: 1,
    name: "Welcome to Commerza Circle",
    subject: "Welcome to the Commerza Circle",
    body: "Hi there,\n\nThanks for joining the Commerza Circle. You will get early access to launches, exclusive offers, and collector stories.\n\n- The Commerza Team",
  },
  {
    id: 2,
    name: "New Arrivals Drop",
    subject: "New arrivals just landed",
    body: "Hello,\n\nOur latest watches are live now. Explore the newest drops and find your next statement piece.\n\nShop now: https://commerza.com\n\n- The Commerza Team",
  },
  {
    id: 3,
    name: "Limited Time Offer",
    subject: "Limited-time offer inside",
    body: "Hi,\n\nFor a limited time, enjoy exclusive pricing on selected collections. The offer ends soon, so do not miss out.\n\n- The Commerza Team",
  },
  {
    id: 4,
    name: "Back in Stock Alert",
    subject: "Back in stock: your favorites",
    body: "Hello,\n\nGood news! Popular watches are back in stock. Quantities are limited, so grab yours soon.\n\n- The Commerza Team",
  },
  {
    id: 5,
    name: "Order Update",
    subject: "Your Commerza order update",
    body: "Hi,\n\nWe wanted to share a quick update about your order. If you have any questions, reply to this email and our team will help.\n\n- The Commerza Team",
  },
  {
    id: 6,
    name: "Shipping Delay Notice",
    subject: "Shipping update from Commerza",
    body: "Hello,\n\nWe are experiencing a short shipping delay due to high demand. Your order is still on the way, and we will share tracking soon.\n\n- The Commerza Team",
  },
  {
    id: 7,
    name: "VIP Early Access",
    subject: "VIP early access is live",
    body: "Hi,\n\nAs a Commerza subscriber, you get early access to our newest collection. Take a first look before the public launch.\n\n- The Commerza Team",
  },
  {
    id: 8,
    name: "Holiday Gift Guide",
    subject: "Holiday gift picks from Commerza",
    body: "Hello,\n\nNeed a gift that stands out? Our holiday guide highlights the best watches for every style and budget.\n\n- The Commerza Team",
  },
  {
    id: 9,
    name: "Feedback Request",
    subject: "We would love your feedback",
    body: "Hi,\n\nYour feedback helps us improve. If you have a moment, let us know what you love and what we can do better.\n\n- The Commerza Team",
  },
  {
    id: 10,
    name: "Monthly Newsletter",
    subject: "Your Commerza monthly roundup",
    body: "Hello,\n\nHere is your monthly roundup with new releases, staff picks, and limited offers.\n\n- The Commerza Team",
  },
  {
    id: 11,
    name: "Support Reply",
    subject: "Re: Support request",
    body: "Hi,\n\nThanks for reaching out to Commerza support. We are looking into this and will update you shortly.\n\nIf you can share your order ID and any extra details, we can help faster.\n\n- Commerza Support",
  },
];
const EMAIL_SOURCE_BADGES = {
  Newsletter: "bg-info text-dark",
  Account: "bg-warning text-dark",
  Order: "bg-success",
  Manual: "bg-secondary",
};
let emailDirectory = [];
let emailSelected = new Set();
let emailFiltered = [];
let emailTemplates = [];
const ADMIN_PAGES = [
  { id: "index.php", label: "Home" },
  { id: "products.php", label: "Products" },
  { id: "shop-category-a.php", label: "Shop Category A" },
  { id: "shop-category-b.php", label: "Shop Category B" },
  { id: "about.php", label: "About" },
  { id: "contact.php", label: "Contact" },
  { id: "faq.php", label: "FAQ" },
  { id: "returns.php", label: "Returns" },
  { id: "shipping.php", label: "Shipping" },
  { id: "warranty.php", label: "Warranty" },
  { id: "login.php", label: "Login" },
  { id: "signup.php", label: "Signup" },
  { id: "cart.php", label: "Cart" },
  { id: "wishlist.php", label: "Wishlist" },
  { id: "order-tracking.php", label: "Order Tracking" },
  { id: "compare.php", label: "Compare" },
  { id: "account.php", label: "Account" },
];
// Notification timing rules keep the bell focused on recent actions.
const NOTIFICATION_RULES = {
  recentOrderDays: 7,
  pendingOrderDays: 14,
  newCustomerDays: 14,
  newProductDays: 7,
  lowStockThreshold: 5,
};
const ADMIN_TAB_PLAYBOOKS = {
  dashboardSection: {
    title: "Daily Admin Routine",
    intro: "Run this checklist to stay ahead of orders and stock issues.",
    steps: [
      "Check the notification bell and open any urgent item first.",
      "Review pending and processing orders, then update their statuses.",
      "Verify low-stock products and restock or pause listings if needed.",
    ],
    tip: "Repeat this flow at opening time and before closing the day.",
  },
  productsSection: {
    title: "Products Workflow",
    intro: "Use this order every time to avoid broken listings.",
    steps: [
      "Create or confirm the section where the product belongs.",
      "Add product details: name, image, pricing, stock, and product code.",
      "Save and quickly verify the product card on the website.",
    ],
    tip: "If pricing changes, update sale price and stock together.",
  },
  productTrashSection: {
    title: "Product Trash Workflow",
    intro: "Use trash as a safety net before permanent deletion.",
    steps: [
      "Open Product Trash and review item name, section, and delete date.",
      "Use Restore for mistaken deletions so products return to the catalog.",
      "Use Empty Expired or Delete only when recovery is no longer needed.",
    ],
    tip: "Permanent delete cannot be undone, so restore first when unsure.",
  },
  ordersSection: {
    title: "Order Handling Steps",
    intro: "Keep order updates consistent so customers are never confused.",
    steps: [
      "Start with oldest pending orders and verify payment status.",
      "Mark processing only after packing is confirmed.",
      "Mark delivered only when shipment confirmation is received.",
    ],
    tip: "Always add notes before deleting or changing critical order records.",
  },
  customersSection: {
    title: "Customer Records Checklist",
    intro: "Use this tab for quick contact and profile verification.",
    steps: [
      "Search for the customer and confirm email and phone details.",
      "Use recent order activity to validate profile accuracy.",
      "Flag suspicious or duplicate records for review before deletion.",
    ],
    tip: "Delete customer records only when you are sure they are duplicates.",
  },
  couponsSection: {
    title: "Coupon Campaign Steps",
    intro: "Build safe offers that customers can redeem without confusion.",
    steps: [
      "Create a simple code with clear discount type and value.",
      "Set expiry date, usage limit, and per-user limit before saving.",
      "Send test email copy first, then launch to customer lists.",
    ],
    tip: "Short codes like SAVE10 are easier for users and support teams.",
  },
  reviewsSection: {
    title: "Review Moderation Steps",
    intro:
      "Use quick-add and moderation controls without guessing IDs or flow.",
    steps: [
      "Use Quick Add Review to submit manual reviews in one form.",
      "Use Quick Fake x1 for one seed review, or Bulk for larger test sets.",
      "Moderate with Hide/Show or Lock/Unlock, then refresh to verify updates.",
    ],
    tip: "Prefer Hide + Lock for suspicious content so audit history stays visible.",
  },
  analyticsSection: {
    title: "Analytics Reading Guide",
    intro: "Focus on decisions, not just numbers.",
    steps: [
      "Review sales and order totals for the active period.",
      "Compare top-performing categories with low-performing ones.",
      "Choose one action for today: restock, promote, or adjust pricing.",
    ],
    tip: "Small daily improvements are better than delayed big changes.",
  },
  emailSection: {
    title: "Email Campaign Steps",
    intro: "Use this sequence to avoid mistakes in customer emails.",
    steps: [
      "Choose recipients carefully and remove invalid addresses.",
      "Preview subject and message with the selected template.",
      "Send to a small test group before full dispatch.",
    ],
    tip: "Keep message text short and include one clear call to action.",
  },
  websiteSection: {
    title: "Website Content Steps",
    intro: "Update visuals and links in a safe order.",
    steps: [
      "Upload media assets first and confirm file paths.",
      "Update text blocks, social links, and sliders in small batches.",
      "Save changes and review the corresponding public page immediately.",
    ],
    tip: "One section at a time prevents accidental cross-page edits.",
  },
  securityEventsSection: {
    title: "Security Event Review",
    intro: "Treat this tab like a triage board.",
    steps: [
      "Filter by severity and inspect critical events first.",
      "Check actor, IP, and timestamp to confirm suspicious behavior.",
      "Escalate repeated abuse patterns to blocking or policy updates.",
    ],
    tip: "Export serious incidents for weekly audits and team review.",
  },
  homepageSection: {
    title: "Homepage Publishing Steps",
    intro: "Keep homepage updates clear and consistent for visitors.",
    steps: [
      "Update headline and highlighted message with current campaigns.",
      "Verify hero media, sliders, and featured products are aligned.",
      "Preview on desktop and mobile before final save.",
    ],
    tip: "Avoid changing too many homepage elements at once.",
  },
};

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

function resolveOrderPaymentBadge(paymentStatusRaw, paymentMethodRaw) {
  const paymentStatus = (paymentStatusRaw || "").toString().toLowerCase();
  const paymentMethod = (paymentMethodRaw || "").toString().trim();
  const codUnpaid =
    paymentStatus === "unpaid" && isCodPaymentMethod(paymentMethod);

  if (paymentStatus === "refunded") {
    return { label: "Refunded", className: "bg-secondary" };
  }

  if (paymentStatus === "partially_refunded") {
    return { label: "Refund Pending", className: "bg-warning text-dark" };
  }

  if (paymentStatus === "paid") {
    return { label: "Paid", className: "bg-success" };
  }

  if (codUnpaid) {
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

function ensureUploadQueueUi(fileInput) {
  if (!fileInput) {
    return null;
  }

  const key = (fileInput.id || fileInput.name || "media-upload").toString();
  let shell = document.querySelector(`[data-upload-status-for="${key}"]`);
  if (!shell) {
    shell = document.createElement("div");
    shell.className = "upload-queue-status d-none";
    shell.setAttribute("data-upload-status-for", key);
    shell.innerHTML = `
      <div class="upload-queue-meta">
        <strong data-upload-heading>Upload Queue</strong>
        <span data-upload-summary>Waiting...</span>
      </div>
      <div class="progress" style="height:6px;">
        <div class="progress-bar bg-warning" role="progressbar" data-upload-progress style="width:0%">0%</div>
      </div>
      <ul class="upload-queue-list" data-upload-list></ul>
    `;

    const anchor = fileInput.closest(".d-flex") || fileInput.parentElement;
    if (anchor?.parentNode) {
      anchor.parentNode.insertBefore(shell, anchor.nextSibling);
    } else {
      fileInput.insertAdjacentElement("afterend", shell);
    }
  }

  return shell;
}

function initUploadQueueUi(shell, files) {
  if (!shell) {
    return;
  }

  const list = shell.querySelector("[data-upload-list]");
  const heading = shell.querySelector("[data-upload-heading]");
  const summary = shell.querySelector("[data-upload-summary]");
  const progress = shell.querySelector("[data-upload-progress]");

  if (heading) {
    heading.textContent = `Upload Queue (${files.length})`;
  }
  if (summary) {
    summary.textContent = "0 completed";
  }
  if (progress) {
    progress.style.width = "0%";
    progress.textContent = "0%";
  }

  if (list) {
    list.innerHTML = files
      .map(
        (file, index) => `
          <li class="upload-queue-item" data-upload-item="${index}">
            <div>
              <div class="upload-file-line">${uploadFileLabel(file?.name)}</div>
              <div class="upload-note-line" data-upload-note>Queued...</div>
              <div class="upload-path-line d-none" data-upload-path></div>
            </div>
            <span class="upload-stage-badge" data-upload-stage>queued</span>
          </li>
        `,
      )
      .join("");
  }

  shell.classList.remove("d-none");
}

function updateUploadQueueTotals(shell, completed, total, success, failed) {
  if (!shell) {
    return;
  }

  const summary = shell.querySelector("[data-upload-summary]");
  const progress = shell.querySelector("[data-upload-progress]");
  const pct =
    total > 0 ? Math.min(100, Math.round((completed / total) * 100)) : 0;

  if (summary) {
    summary.textContent = `${completed}/${total} done | ${success} success | ${failed} failed`;
  }
  if (progress) {
    progress.style.width = `${pct}%`;
    progress.textContent = `${pct}%`;
    progress.classList.toggle("bg-danger", failed > 0 && completed === total);
    progress.classList.toggle(
      "bg-success",
      failed === 0 && completed === total,
    );
    progress.classList.toggle("bg-warning", completed < total);
  }
}

function updateUploadQueueItem(
  shell,
  index,
  stage,
  note,
  path = "",
  isError = false,
) {
  if (!shell) {
    return;
  }

  const row = shell.querySelector(`[data-upload-item="${index}"]`);
  if (!row) {
    return;
  }

  const noteEl = row.querySelector("[data-upload-note]");
  const stageEl = row.querySelector("[data-upload-stage]");
  const pathEl = row.querySelector("[data-upload-path]");

  if (noteEl) {
    noteEl.textContent = (note || "").toString();
  }
  if (stageEl) {
    stageEl.textContent = (stage || "queued").toString();
  }

  if (pathEl) {
    const safePath = (path || "").toString().trim();
    if (safePath) {
      pathEl.textContent = safePath;
      pathEl.classList.remove("d-none");
    } else {
      pathEl.textContent = "";
      pathEl.classList.add("d-none");
    }
  }

  row.classList.toggle("is-error", Boolean(isError));
  row.classList.toggle("is-done", !isError && stage === "done");
}

function uploadAdminMedia(target, file, callbacks = {}) {
  if (!target || !file) {
    return Promise.reject(new Error("Select a valid file before uploading."));
  }

  const onUploadProgress =
    typeof callbacks.onUploadProgress === "function"
      ? callbacks.onUploadProgress
      : () => {};
  const onStage =
    typeof callbacks.onStage === "function" ? callbacks.onStage : () => {};

  const formData = new FormData();
  formData.append("target", target);
  formData.append("file", file);

  const scope = (target || "upload").toString().trim().toLowerCase();
  const requestId = `admin-${scope}-${Date.now().toString(36)}-${Math.random().toString(16).slice(2, 14)}`;

  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", ADMIN_MEDIA_API, true);
    xhr.withCredentials = true;
    xhr.setRequestHeader("X-CSRF-Token", ADMIN_CSRF_TOKEN);
    xhr.setRequestHeader("X-Request-ID", requestId);

    let stageSent = false;
    const moveToServerStage = () => {
      if (stageSent) {
        return;
      }
      stageSent = true;
      onStage("parsing");
    };

    xhr.upload.addEventListener("progress", (event) => {
      if (!event.lengthComputable) {
        onUploadProgress(0);
        return;
      }
      const pct = Math.min(100, Math.round((event.loaded / event.total) * 100));
      onUploadProgress(pct);
      if (pct >= 100) {
        moveToServerStage();
      }
    });

    xhr.upload.addEventListener("load", () => {
      onUploadProgress(100);
      moveToServerStage();
    });

    xhr.onerror = () => {
      const offline =
        typeof navigator !== "undefined" && navigator.onLine === false;
      reject(
        new Error(
          offline
            ? "Network is offline. Reconnect internet and retry upload."
            : "Network error while uploading file.",
        ),
      );
    };

    xhr.onload = () => {
      let result = null;
      try {
        result = JSON.parse(xhr.responseText || "{}");
      } catch (error) {
        result = null;
      }

      if (xhr.status < 200 || xhr.status >= 300 || !result?.ok) {
        reject(new Error(result?.message || "Upload failed."));
        return;
      }

      const path = (result?.payload?.path || "").toString();
      if (!path) {
        reject(new Error("Uploaded file path is missing."));
        return;
      }

      resolve({
        ...result,
        payload: {
          ...(result?.payload || {}),
          path,
        },
      });
    };

    onStage("uploading");
    xhr.send(formData);
  });
}

async function runConcurrentUploads(files, concurrency, worker) {
  const safeConcurrency = Math.max(1, Math.min(concurrency, files.length || 1));
  const results = new Array(files.length);
  let pointer = 0;

  async function runner() {
    while (pointer < files.length) {
      const current = pointer;
      pointer += 1;

      try {
        results[current] = await worker(files[current], current);
      } catch (error) {
        results[current] = { ok: false, error };
      }
    }
  }

  const workers = Array.from({ length: safeConcurrency }, () => runner());
  await Promise.all(workers);

  return results;
}

function bindUploadControl(buttonSelector, inputSelector, target, onComplete) {
  $(buttonSelector)
    .off("click")
    .on("click", async function () {
      const fileInput = document.querySelector(inputSelector);
      const files = Array.from(fileInput?.files || []).filter(
        (file) => file && typeof file.name === "string",
      );

      if (!files.length) {
        showNotification("Please choose one or more files first.", "warning");
        return;
      }

      const btn = $(this);
      const originalHtml = btn.html();
      btn
        .prop("disabled", true)
        .html('<span class="spinner-border spinner-border-sm"></span>');

      const shell = ensureUploadQueueUi(fileInput);
      initUploadQueueUi(shell, files);

      const parallelism = 1;
      let completed = 0;
      let success = 0;
      let failed = 0;
      const uploadedPaths = [];

      updateUploadQueueTotals(shell, completed, files.length, success, failed);

      try {
        await runConcurrentUploads(files, parallelism, async (file, index) => {
          updateUploadQueueItem(
            shell,
            index,
            "uploading",
            `Uploading ${formatUploadSize(file.size)}...`,
          );

          try {
            const result = await uploadAdminMedia(target, file, {
              onUploadProgress: (pct) => {
                if (pct >= 100) {
                  return;
                }
                updateUploadQueueItem(
                  shell,
                  index,
                  "uploading",
                  `Uploading... ${pct}%`,
                );
              },
              onStage: (stage) => {
                if (stage === "parsing") {
                  updateUploadQueueItem(
                    shell,
                    index,
                    "parsing",
                    "Server is parsing/compressing...",
                  );
                }
              },
            });

            const item = Array.isArray(result?.payload?.items)
              ? result.payload.items.find((entry) => entry?.status === "ok")
              : null;
            const outputPath = (result?.payload?.path || "").toString();
            const outputSize = Number.parseFloat(item?.size_kb || 0) || 0;
            const inputSize =
              Number.parseFloat(item?.original_size_kb || 0) || 0;
            const parser = (item?.parser || "validated").toString();

            const note =
              inputSize > 0 && outputSize > 0
                ? `${parser} | ${inputSize.toFixed(1)} KB -> ${outputSize.toFixed(1)} KB`
                : `${parser} | ${formatUploadSize(file.size)}`;

            updateUploadQueueItem(shell, index, "done", note, outputPath);

            success += 1;
            if (outputPath) {
              uploadedPaths.push(outputPath);
            }

            return { ok: true, result };
          } catch (error) {
            failed += 1;
            updateUploadQueueItem(
              shell,
              index,
              "failed",
              error?.message || "Upload failed.",
              "",
              true,
            );
            return { ok: false, error };
          } finally {
            completed += 1;
            updateUploadQueueTotals(
              shell,
              completed,
              files.length,
              success,
              failed,
            );
          }
        });

        if (uploadedPaths.length && typeof onComplete === "function") {
          onComplete(uploadedPaths[0], uploadedPaths);
        }

        if (fileInput) {
          fileInput.value = "";
        }

        if (success > 0) {
          const msg =
            files.length > 1
              ? `Processed ${success}/${files.length} file(s).`
              : "File uploaded.";
          showNotification(msg, failed > 0 ? "warning" : "success");
        } else {
          showNotification("Unable to upload selected files.", "danger");
        }
      } catch (error) {
        showNotification(error?.message || "Unable to upload file.", "danger");
      } finally {
        btn.prop("disabled", false).html(originalHtml);
      }
    });
}

function setLiveViewersModeUi(mode, storageReady = true) {
  const normalized = mode === "fake" ? "fake" : "real";
  $("#liveViewersMode").val(normalized);
  const modeLabel =
    normalized === "fake" ? "Fake (marketing demo)" : "Real (active sessions)";
  $("#liveViewersModeBtn").text(modeLabel);
  $("#liveViewersModeMenu .dropdown-item").removeClass("active");
  $("#liveViewersModeMenu .dropdown-item").each(function () {
    if (($(this).data("mode") || "").toString() === normalized) {
      $(this).addClass("active");
    }
  });
  $("#liveViewerFakeConfig").toggleClass("d-none", normalized !== "fake");
  const statusLabel = storageReady
    ? `Mode: ${normalized}`
    : `Mode: ${normalized} | DB setup pending`;
  $("#liveViewersModeBadge").text(statusLabel);
}

function renderLiveViewersTopProducts(products, storageReady = true) {
  const tbody = $("#liveViewersTopProducts");
  if (!tbody.length) {
    return;
  }

  tbody.empty();

  if (!Array.isArray(products) || products.length === 0) {
    const message = storageReady
      ? "No live viewer data yet."
      : "Viewer storage table missing. Run SQL migration.";
    tbody.append(`
      <tr>
        <td class="ps-3 py-3 text-secondary" colspan="2">${message}</td>
      </tr>
    `);
    return;
  }

  products.forEach((item) => {
    const productName = (item?.name || "Product").toString();
    const safeProductName = escapeHtml(productName);
    const viewers = Math.max(0, parseInt(item?.viewers, 10) || 0);
    tbody.append(`
      <tr class="border-bottom border-secondary">
        <td class="ps-3 py-3 text-light fw-semibold">${safeProductName}</td>
        <td class="pe-3 py-3 text-end text-orange fw-semibold">${viewers}</td>
      </tr>
    `);
  });
}

function renderLiveViewersPayload(payload) {
  const settings = payload?.settings || {};
  const stats = payload?.stats || {};
  const storageReady = payload?.storage_ready !== false;

  const mode = settings?.mode === "fake" ? "fake" : "real";
  const fakeMin = Math.max(1, parseInt(settings?.fake_min, 10) || 120);
  const fakeMax = Math.max(1, parseInt(settings?.fake_max, 10) || 165);
  const windowSeconds = Math.max(
    30,
    Math.min(3600, parseInt(settings?.window_seconds, 10) || 180),
  );

  setLiveViewersModeUi(mode, storageReady);
  $("#liveViewersFakeMin").val(fakeMin);
  $("#liveViewersFakeMax").val(fakeMax);
  $("#liveViewersWindow").val(windowSeconds);

  $("#liveViewersActiveNow").text(
    Math.max(0, parseInt(stats?.active_now, 10) || 0),
  );
  $("#liveViewersTrackedProducts").text(
    Math.max(0, parseInt(stats?.tracked_products, 10) || 0),
  );

  renderLiveViewersTopProducts(stats?.top_products || [], storageReady);
}

async function loadLiveViewersAnalytics(silent = false) {
  if (!$("#liveViewersMode").length) {
    return false;
  }

  try {
    const response = await fetch(`${ADMIN_VIEWERS_API}?action=get`, {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store",
    });

    const result = await response.json();
    if (!response.ok || !result?.ok) {
      throw new Error(result?.message || "Unable to load live viewers data.");
    }

    renderLiveViewersPayload(result?.payload || {});
    return true;
  } catch (error) {
    if (!silent) {
      showNotification(
        error?.message || "Unable to load live viewers data.",
        "danger",
      );
    }
    return false;
  }
}

async function saveLiveViewersSettings() {
  const mode = $("#liveViewersMode").val() === "fake" ? "fake" : "real";
  let fakeMin = parseInt($("#liveViewersFakeMin").val(), 10) || 120;
  let fakeMax = parseInt($("#liveViewersFakeMax").val(), 10) || 165;
  const windowSeconds = parseInt($("#liveViewersWindow").val(), 10) || 180;

  fakeMin = Math.max(1, Math.min(5000, fakeMin));
  fakeMax = Math.max(1, Math.min(5000, fakeMax));
  const safeWindow = Math.max(30, Math.min(3600, windowSeconds));

  if (fakeMax < fakeMin) {
    const swap = fakeMin;
    fakeMin = fakeMax;
    fakeMax = swap;
  }

  try {
    const result = await adminPostJson(ADMIN_VIEWERS_API, {
      action: "save",
      mode,
      fake_min: fakeMin,
      fake_max: fakeMax,
      window_seconds: safeWindow,
    });

    renderLiveViewersPayload(result?.payload || {});
    showNotification(result?.message || "Viewer settings updated.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to save viewer settings.",
      "danger",
    );
  }
}

function initLiveViewersAnalytics() {
  if (!$("#liveViewersMode").length) {
    return;
  }

  setLiveViewersModeUi($("#liveViewersMode").val());

  $("#liveViewersModeMenu")
    .off("click", ".dropdown-item")
    .on("click", ".dropdown-item", function (event) {
      event.preventDefault();
      setLiveViewersModeUi(($(this).data("mode") || "").toString());
    });

  $("#saveLiveViewersBtn").off("click").on("click", saveLiveViewersSettings);

  $("#refreshLiveViewersBtn")
    .off("click")
    .on("click", function () {
      loadLiveViewersAnalytics(false);
    });

  loadLiveViewersAnalytics(true);
}

function formatShortDate(value) {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "-";
  return date.toISOString().split("T")[0];
}

function updateEmailPreview() {
  const subject = ($("#emailSubjectInput").val() || "").trim();
  const body = ($("#emailBodyInput").val() || "").trim();
  const preview = $("#emailPreview");
  if (!preview.length) return;
  const attachmentInput = document.getElementById("emailAttachmentInput");
  const files = attachmentInput?.files
    ? Array.from(attachmentInput.files).map((file) => file.name)
    : [];
  const attachmentLine = files.length
    ? `Attachments: ${files.join(", ")}`
    : "Attachments: None";
  const title = subject ? `Subject: ${subject}` : "Subject: (No subject)";
  preview.text(`${title}\n${attachmentLine}\n\n${body}`.trim());
}

function getNewsletterSubscribers() {
  const rawList = readJsonStorage(NEWSLETTER_SUBSCRIBERS_KEY, []);
  const list = Array.isArray(rawList) ? rawList : [];
  const legacyEmail = normalizeEmailValue(
    sessionStorage.getItem(NEWSLETTER_EMAIL_KEY),
  );

  if (
    legacyEmail &&
    !list.some(
      (item) => normalizeEmailValue(item.email || item) === legacyEmail,
    )
  ) {
    list.push({
      email: legacyEmail,
      sources: ["modal"],
      subscribedAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    });
  }

  return list
    .map((item) => {
      if (typeof item === "string") {
        return {
          email: normalizeEmailValue(item),
          sources: ["modal"],
          subscribedAt: new Date().toISOString(),
          updatedAt: new Date().toISOString(),
        };
      }
      return {
        email: normalizeEmailValue(item.email),
        sources: Array.isArray(item.sources)
          ? item.sources
          : [item.source || "modal"],
        subscribedAt: item.subscribedAt,
        updatedAt: item.updatedAt || item.subscribedAt,
      };
    })
    .filter((entry) => entry.email);
}

function getManualRecipients() {
  const raw = readJsonStorage(EMAIL_MANUAL_RECIPIENTS_KEY, []);
  if (!Array.isArray(raw)) return [];
  return raw
    .map((item) => {
      if (typeof item === "string") {
        return {
          email: normalizeEmailValue(item),
          addedAt: new Date().toISOString(),
        };
      }
      return {
        email: normalizeEmailValue(item.email),
        addedAt: item.addedAt || new Date().toISOString(),
      };
    })
    .filter((item) => item.email);
}

function saveManualRecipients(list) {
  sessionStorage.setItem(EMAIL_MANUAL_RECIPIENTS_KEY, JSON.stringify(list));
}

function buildEmailDirectory() {
  const directory = new Map();
  const suppressed = getSuppressedEmails();

  const addEntry = (email, source, meta = {}) => {
    const normalized = normalizeEmailValue(email);
    if (!normalized) return;
    if (suppressed.has(normalized)) return;
    const existing = directory.get(normalized) || {
      email: normalized,
      name: meta.name || "",
      sources: new Set(),
      firstSeen: meta.firstSeen || meta.lastSeen || new Date().toISOString(),
      lastSeen: meta.lastSeen || meta.firstSeen || new Date().toISOString(),
    };
    existing.sources.add(source);
    if (meta.name && !existing.name) {
      existing.name = meta.name;
    }
    if (
      meta.firstSeen &&
      (!existing.firstSeen ||
        new Date(meta.firstSeen) < new Date(existing.firstSeen))
    ) {
      existing.firstSeen = meta.firstSeen;
    }
    if (
      meta.lastSeen &&
      new Date(meta.lastSeen) > new Date(existing.lastSeen)
    ) {
      existing.lastSeen = meta.lastSeen;
    }
    directory.set(normalized, existing);
  };

  getNewsletterSubscribers().forEach((sub) => {
    const lastSeen = sub.updatedAt || sub.subscribedAt;
    addEntry(sub.email, "Newsletter", {
      lastSeen,
      firstSeen: sub.subscribedAt,
    });
  });

  const users = getAdminCustomersData();
  if (Array.isArray(users)) {
    users.forEach((user) => {
      addEntry(user.email, "Account", {
        name: user.name,
        lastSeen: user.registeredAt,
        firstSeen: user.registeredAt,
      });
    });
  }

  const orders = getAdminOrdersData();
  if (Array.isArray(orders)) {
    orders.forEach((order) => {
      addEntry(order.email, "Order", {
        name: order.customerName,
        lastSeen: order.orderDate,
        firstSeen: order.orderDate,
      });
    });
  }

  getManualRecipients().forEach((item) => {
    addEntry(item.email, "Manual", {
      lastSeen: item.addedAt,
      firstSeen: item.addedAt,
    });
  });

  return Array.from(directory.values())
    .map((entry) => ({
      ...entry,
      sources: Array.from(entry.sources),
    }))
    .sort((a, b) => new Date(b.lastSeen) - new Date(a.lastSeen));
}

function getEmailTemplates() {
  const stored = readJsonStorage(EMAIL_TEMPLATES_KEY, []);
  if (Array.isArray(stored) && stored.length) {
    return stored;
  }
  return DEFAULT_EMAIL_TEMPLATES.map((template) => ({ ...template }));
}

function saveEmailTemplates(list) {
  sessionStorage.setItem(EMAIL_TEMPLATES_KEY, JSON.stringify(list));
}

function renderTemplateSelect() {
  const menu = $("#emailTemplateMenu");
  if (!menu.length) return;
  menu.empty();
  menu.append(
    '<li><a class="dropdown-item text-light" href="#" data-template-id="">Custom</a></li>',
  );
  menu.append('<li><hr class="dropdown-divider border-secondary"></li>');
  emailTemplates.forEach((template) => {
    menu.append(
      `<li><a class="dropdown-item text-light" href="#" data-template-id="${template.id}">${template.name}</a></li>`,
    );
  });
}

function renderEmailRecipients() {
  const tbody = $("#emailRecipientsTable tbody");
  if (!tbody.length) return;
  const filter = ($("#emailSourceFilter").val() || "all").toLowerCase();
  const query = ($("#emailSearchInput").val() || "").trim().toLowerCase();

  emailFiltered = emailDirectory.filter((entry) => {
    const inNewsletter = entry.sources.includes("Newsletter");
    const inCustomers =
      entry.sources.includes("Order") || entry.sources.includes("Account");
    if (filter === "newsletter" && !inNewsletter) return false;
    if (filter === "customers" && !inCustomers) return false;
    if (query) {
      const name = (entry.name || "").toLowerCase();
      return entry.email.includes(query) || name.includes(query);
    }
    return true;
  });

  $("#emailRecipientCount").text(emailDirectory.length);

  tbody.empty();
  if (!emailFiltered.length) {
    tbody.append(
      '<tr><td colspan="5" class="text-center py-4 text-secondary">No recipients found</td></tr>',
    );
    updateSelectedCount();
    return;
  }

  emailFiltered.forEach((entry) => {
    const isChecked = emailSelected.has(entry.email);
    const badges = entry.sources
      .map((source) => {
        const badgeClass = EMAIL_SOURCE_BADGES[source] || "bg-secondary";
        return `<span class="badge ${badgeClass} me-1">${source}</span>`;
      })
      .join("");
    tbody.append(`
            <tr class="border-bottom border-secondary">
                <td class="ps-4 py-3">
                    <input type="checkbox" class="form-check-input email-recipient-check" data-email="${entry.email}" ${isChecked ? "checked" : ""}>
                </td>
                <td class="py-3 text-light fw-semibold">${entry.email}</td>
                <td class="py-3">${badges}</td>
                <td class="py-3 text-secondary small">${formatShortDate(entry.lastSeen)}</td>
                <td class="pe-4 py-3">
                    <button class="btn btn-sm btn-outline-danger email-remove-recipient" data-email="${entry.email}" title="Remove">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `);
  });

  updateSelectedCount();
}

function updateSelectedCount() {
  $("#emailSelectedCount").text(emailSelected.size);
}

function addManualRecipient(email) {
  const normalized = normalizeEmailValue(email);
  if (!normalized || !normalized.includes("@")) return false;
  const suppressed = getSuppressedEmails();
  if (suppressed.has(normalized)) {
    suppressed.delete(normalized);
    saveSuppressedEmails(suppressed);
  }
  const existing = getManualRecipients();
  if (!existing.some((item) => item.email === normalized)) {
    existing.push({ email: normalized, addedAt: new Date().toISOString() });
    saveManualRecipients(existing);
  }
  if (!emailDirectory.some((entry) => entry.email === normalized)) {
    emailDirectory.push({
      email: normalized,
      name: "",
      sources: ["Manual"],
      firstSeen: new Date().toISOString(),
      lastSeen: new Date().toISOString(),
    });
    emailDirectory = emailDirectory.sort(
      (a, b) => new Date(b.lastSeen) - new Date(a.lastSeen),
    );
  }
  emailSelected.add(normalized);
  return true;
}

function applyTemplateToComposer(templateId) {
  const template = emailTemplates.find(
    (item) => String(item.id) === String(templateId),
  );
  if (!template) return;
  $("#emailTemplateId").val(template.id);
  $("#emailTemplateBtn").text(template.name || "Custom");
  $("#emailTemplateName").val(template.name || "");
  $("#emailSubjectInput").val(template.subject || "");
  $("#emailBodyInput").val(template.body || "");
  updateEmailPreview();
}

function saveTemplateFromComposer() {
  const name = ($("#emailTemplateName").val() || "").trim();
  const subject = ($("#emailSubjectInput").val() || "").trim();
  const body = ($("#emailBodyInput").val() || "").trim();
  if (!name || (!subject && !body)) {
    showNotification("Add a template name and content before saving", "danger");
    return;
  }

  const selectedId = $("#emailTemplateId").val();
  if (selectedId) {
    const index = emailTemplates.findIndex(
      (item) => String(item.id) === String(selectedId),
    );
    if (index !== -1) {
      emailTemplates[index] = { ...emailTemplates[index], name, subject, body };
      saveEmailTemplates(emailTemplates);
      renderTemplateSelect();
      $("#emailTemplateId").val(selectedId);
      $("#emailTemplateBtn").text(name || "Custom");
      showNotification("Template updated!", "success");
      return;
    }
  }

  const nextId = Math.max(0, ...emailTemplates.map((item) => item.id || 0)) + 1;
  emailTemplates.push({ id: nextId, name, subject, body });
  saveEmailTemplates(emailTemplates);
  renderTemplateSelect();
  $("#emailTemplateId").val(String(nextId));
  $("#emailTemplateBtn").text(name || "Custom");
  showNotification("Template saved!", "success");
}

function resetComposerTemplate() {
  $("#emailTemplateId").val("");
  $("#emailTemplateBtn").text("Custom");
  $("#emailTemplateName").val("");
  $("#emailSubjectInput").val("");
  $("#emailBodyInput").val("");
  updateEmailPreview();
}

function removeEmailRecipient(email) {
  const normalized = normalizeEmailValue(email);
  if (!normalized) return;

  const suppressed = getSuppressedEmails();
  suppressed.add(normalized);
  saveSuppressedEmails(suppressed);

  const manual = getManualRecipients().filter(
    (item) => item.email !== normalized,
  );
  saveManualRecipients(manual);

  const newsletter = readJsonStorage(NEWSLETTER_SUBSCRIBERS_KEY, []);
  if (Array.isArray(newsletter)) {
    const filtered = newsletter.filter(
      (item) => normalizeEmailValue(item.email || item) !== normalized,
    );
    sessionStorage.setItem(
      NEWSLETTER_SUBSCRIBERS_KEY,
      JSON.stringify(filtered),
    );
  }

  emailDirectory = emailDirectory.filter((entry) => entry.email !== normalized);
  emailSelected.delete(normalized);
}

function sendEmailFromComposer() {
  const recipients = Array.from(emailSelected);
  const subject = ($("#emailSubjectInput").val() || "").trim();
  const body = ($("#emailBodyInput").val() || "").trim();
  const attachmentInput = document.getElementById("emailAttachmentInput");
  const hasFiles = attachmentInput?.files && attachmentInput.files.length > 0;

  if (!recipients.length) {
    showNotification("Select at least one recipient", "danger");
    return;
  }
  if (!subject && !body) {
    showNotification("Add a subject or message before sending", "danger");
    return;
  }

  if (hasFiles) {
    showNotification(
      "Attachments must be added in your email client after it opens.",
      "warning",
    );
  }

  const mailto = `mailto:?bcc=${encodeURIComponent(recipients.join(","))}&subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
  if (mailto.length > 1900) {
    showNotification(
      "Too many recipients for a mailto link. Copy emails instead.",
      "warning",
    );
    return;
  }

  const outbox = readJsonStorage(EMAIL_OUTBOX_KEY, []);
  if (Array.isArray(outbox)) {
    outbox.unshift({
      subject,
      body,
      recipients,
      sentAt: new Date().toISOString(),
    });
    sessionStorage.setItem(
      EMAIL_OUTBOX_KEY,
      JSON.stringify(outbox.slice(0, 50)),
    );
  }

  window.location.href = mailto;
}

function initEmailCenter() {
  if (!$("#emailSection").length) return;
  emailDirectory = buildEmailDirectory();
  emailTemplates = getEmailTemplates();
  renderTemplateSelect();
  renderEmailRecipients();
  updateEmailPreview();

  $(document).on("click", "#emailSourceMenu .dropdown-item", function (event) {
    event.preventDefault();
    const source = $(this).data("source") || "all";
    $("#emailSourceFilter").val(source);
    $("#emailSourceBtn").text($(this).text().trim());
    renderEmailRecipients();
  });
  $("#emailSearchInput").on("input", renderEmailRecipients);

  $(document).on("change", ".email-recipient-check", function () {
    const email = $(this).data("email");
    if (!email) return;
    if (this.checked) {
      emailSelected.add(email);
    } else {
      emailSelected.delete(email);
    }
    updateSelectedCount();
  });

  $("#emailSelectAllBtn").on("click", function () {
    emailFiltered.forEach((entry) => emailSelected.add(entry.email));
    renderEmailRecipients();
  });

  $("#emailClearBtn").on("click", function () {
    emailSelected.clear();
    renderEmailRecipients();
  });

  $("#emailCopyBtn").on("click", function () {
    const list = Array.from(emailSelected);
    if (!list.length) {
      showNotification("Select recipients to copy", "warning");
      return;
    }
    const text = list.join(", ");
    navigator.clipboard
      ?.writeText(text)
      .then(() => {
        showNotification("Emails copied to clipboard", "success");
      })
      .catch(() => {
        showNotification("Unable to copy emails", "danger");
      });
  });

  $("#emailAddRecipientBtn").on("click", function () {
    const input = $("#emailAddRecipientInput");
    const value = input.val();
    if (!addManualRecipient(value)) {
      showNotification("Enter a valid email address", "danger");
      return;
    }
    input.val("");
    renderEmailRecipients();
    showNotification("Recipient added", "success");
  });

  $(document).on("click", ".email-remove-recipient", function () {
    const email = $(this).data("email");
    if (!email) return;
    removeEmailRecipient(email);
    renderEmailRecipients();
    showNotification("Recipient removed", "success");
  });

  $(document).on(
    "click",
    "#emailTemplateMenu .dropdown-item",
    function (event) {
      event.preventDefault();
      const templateId = $(this).data("template-id");
      if (!templateId) {
        $("#emailTemplateId").val("");
        $("#emailTemplateBtn").text("Custom");
        $("#emailTemplateName").val("");
        updateEmailPreview();
        return;
      }
      applyTemplateToComposer(templateId);
    },
  );

  $("#emailSubjectInput, #emailBodyInput, #emailAttachmentInput").on(
    "input change",
    updateEmailPreview,
  );

  $("#emailSaveTemplateBtn").on("click", saveTemplateFromComposer);
  $("#emailNewTemplateBtn").on("click", resetComposerTemplate);
  $("#emailSendBtn").on("click", sendEmailFromComposer);
}

function formatDateTime(value) {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "-";
  return `${date.toISOString().slice(0, 10)} ${date.toTimeString().slice(0, 5)}`;
}

function setAdminDropdownSelection(targetId, value, label = "") {
  const input = $(`#${targetId}`);
  const button = $(`#${targetId}Btn`);
  const menu = $(`#${targetId}Menu`);

  if (!input.length) {
    return;
  }

  const normalizedValue = (value ?? "").toString();
  input.val(normalizedValue);

  if (button.length && label.toString().trim() !== "") {
    button.text(label.toString());
  }

  if (!menu.length) {
    return;
  }

  let hasActive = false;
  menu.find(".admin-dropdown-item").each(function () {
    const item = $(this);
    const itemValue = (item.data("value") ?? "").toString();
    const isActive = itemValue === normalizedValue;
    item.toggleClass("active", isActive);
    if (isActive) {
      hasActive = true;
    }
  });

  if (!hasActive) {
    menu.find(".admin-dropdown-item").removeClass("active");
  }
}

function couponDiscountTypeLabel(value) {
  return (value || "").toString() === "percent" ? "Percent %" : "Fixed PKR";
}

function reviewVisibilityLabel(value) {
  const normalized = (value || "all").toString();
  if (normalized === "visible") return "Visible";
  if (normalized === "hidden") return "Hidden";
  return "All Reviews";
}

function fakeReviewVisibilityLabel(value) {
  return (value || "1").toString() === "0" ? "Hidden" : "Visible";
}

function parseAdminProductIdInput(value) {
  const raw = (value || "").toString().trim();
  if (raw === "") {
    return 0;
  }

  const direct = parseInt(raw, 10);
  if (Number.isInteger(direct) && direct > 0) {
    return direct;
  }

  const leadingMatch = raw.match(/^(\d+)/);
  if (leadingMatch && leadingMatch[1]) {
    const parsed = parseInt(leadingMatch[1], 10);
    if (Number.isInteger(parsed) && parsed > 0) {
      return parsed;
    }
  }

  return 0;
}

function reviewProductOptions(limit = 300) {
  if (!Array.isArray(productsData) || productsData.length === 0) {
    return [];
  }

  const deduped = new Map();
  productsData.forEach((item) => {
    const id = parseInt(item?.id, 10) || 0;
    if (id <= 0 || deduped.has(id)) {
      return;
    }

    const name = (item?.name || "").toString().trim();
    deduped.set(id, {
      id,
      name: name === "" ? `Product ${id}` : name,
    });
  });

  return Array.from(deduped.values())
    .sort((left, right) => {
      const leftName = (left.name || "").toLowerCase();
      const rightName = (right.name || "").toLowerCase();
      const cmp = leftName.localeCompare(rightName);
      if (cmp !== 0) {
        return cmp;
      }
      return left.id - right.id;
    })
    .slice(0, Math.max(0, limit));
}

function renderReviewProductSuggestions() {
  const datalist = document.getElementById("reviewProductSuggestions");
  if (!datalist) {
    return;
  }

  const options = reviewProductOptions(400);
  datalist.innerHTML = "";

  options.forEach((option) => {
    const node = document.createElement("option");
    node.value = `${option.id} - ${option.name}`;
    datalist.appendChild(node);
  });
}

function resetReviewQuickAddForm() {
  $("#reviewAddUserId").val("");
  $("#reviewAddProductId").val("");
  $("#reviewAddOrderId").val("");
  $("#reviewAddRating").val("5");
  $("#reviewAddVisible").val("1");
  $("#reviewAddText").val("");
  $("#reviewAddNote").val("");
}

function focusReviewQuickForm() {
  const card = document.getElementById("reviewQuickAddCard");
  if (card) {
    card.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  window.setTimeout(() => {
    const input = document.getElementById("reviewAddUserId");
    if (input) {
      input.focus();
    }
  }, 120);
}

function collectReviewQuickAddPayload() {
  const userId = parseInt($("#reviewAddUserId").val(), 10) || 0;
  const productId = parseAdminProductIdInput($("#reviewAddProductId").val());
  const orderRaw = ($("#reviewAddOrderId").val() || "").toString().trim();
  const rating = parseInt($("#reviewAddRating").val(), 10) || 0;
  const isVisible =
    ($("#reviewAddVisible").val() || "1").toString() === "0" ? 0 : 1;
  const reviewText = ($("#reviewAddText").val() || "").toString().trim();
  const adminNote = ($("#reviewAddNote").val() || "").toString().trim();

  if (userId <= 0) {
    throw new Error("Enter a valid User ID.");
  }

  if (productId <= 0) {
    throw new Error("Enter a valid Product ID.");
  }

  let orderId = 0;
  if (orderRaw !== "") {
    orderId = parseInt(orderRaw, 10) || 0;
    if (orderId <= 0) {
      throw new Error("Order ID must be a positive number.");
    }
  }

  if (rating < 1 || rating > 5) {
    throw new Error("Rating must be between 1 and 5.");
  }

  if (reviewText.length < 10 || reviewText.length > 500) {
    throw new Error("Review text must be 10 to 500 characters.");
  }

  if (adminNote.length > 500) {
    throw new Error("Admin note can be up to 500 characters.");
  }

  return {
    action: "add-review",
    user_id: userId,
    product_id: productId,
    order_id: orderId,
    rating,
    review_text: reviewText,
    is_visible: isVisible,
    admin_note: adminNote,
  };
}

async function submitReviewQuickAddForm() {
  try {
    const payload = collectReviewQuickAddPayload();
    const result = await adminPostJson(ADMIN_REVIEWS_API, payload);

    adminReviews = Array.isArray(result?.payload?.reviews)
      ? result.payload.reviews
      : adminReviews;
    renderReviewsStats(result?.payload?.stats || {});
    renderReviewsTable();
    resetReviewQuickAddForm();
    showNotification(result?.message || "Review added.", "success");
  } catch (error) {
    showNotification(error?.message || "Unable to add review.", "danger");
  }
}

function securitySeverityLabel(value) {
  const normalized = (value || "").toString();
  if (normalized === "info") return "Info";
  if (normalized === "warning") return "Warning";
  if (normalized === "critical") return "Critical";
  return "All";
}

function securityActorTypeLabel(value) {
  const normalized = (value || "").toString();
  if (normalized === "user") return "User";
  if (normalized === "admin") return "Admin";
  return "All";
}

function resetCouponForm() {
  $("#couponId").val("");
  $("#couponCode").val("");
  $("#couponTitle").val("");
  $("#couponDescription").val("");
  setAdminDropdownSelection("couponDiscountType", "fixed", "Fixed PKR");
  $("#couponDiscountValue").val("");
  $("#couponMinOrder").val("0");
  $("#couponMaxDiscount").val("").prop("disabled", true);
  $("#couponUsageLimit").val("");
  $("#couponPerUserLimit").val("");
  $("#couponExpiresAt").val("");
  $("#couponIsActive").prop("checked", true);
}

function populateCouponEmailSelect() {
  const input = $("#couponEmailCouponId");
  const button = $("#couponEmailCouponIdBtn");
  const menu = $("#couponEmailCouponIdMenu");
  if (!input.length || !button.length || !menu.length) return;

  const selectedValue = (input.val() || "").toString();
  menu.empty();

  if (!adminCoupons.length) {
    input.val("");
    button.text("No coupons available");
    menu.append(
      '<li><span class="dropdown-item text-secondary">No coupons available</span></li>',
    );
    return;
  }

  menu.append(
    '<li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="couponEmailCouponId" data-value="" data-label="Select a coupon">Select a coupon</a></li>',
  );
  menu.append('<li><hr class="dropdown-divider border-secondary-subtle"></li>');

  adminCoupons.forEach((coupon) => {
    const code = escapeHtml(coupon.code || "");
    const label = escapeHtml(coupon.discountLabel || "Offer");
    const optionLabel = `${code} - ${label}`;
    menu.append(
      `<li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="couponEmailCouponId" data-value="${Number(coupon.id || 0)}" data-label="${optionLabel}">${optionLabel}</a></li>`,
    );
  });

  const matchingCoupon = adminCoupons.find(
    (coupon) => String(Number(coupon.id || 0)) === selectedValue,
  );

  if (matchingCoupon) {
    const matchLabel = `${(matchingCoupon.code || "").toString()} - ${(matchingCoupon.discountLabel || "Offer").toString()}`;
    setAdminDropdownSelection(
      "couponEmailCouponId",
      String(Number(matchingCoupon.id || 0)),
      matchLabel,
    );
    return;
  }

  setAdminDropdownSelection("couponEmailCouponId", "", "Select a coupon");
}

function renderCouponsTable() {
  const tbody = $("#couponsTable tbody");
  if (!tbody.length) return;

  tbody.empty();

  const total = adminCoupons.length;
  const active = adminCoupons.filter(
    (coupon) => coupon.isActive && !coupon.isExpired,
  ).length;
  const used = adminCoupons.reduce(
    (sum, coupon) => sum + Math.max(0, parseInt(coupon.usedCount, 10) || 0),
    0,
  );

  $("#couponStatsTotal").text(total);
  $("#couponStatsActive").text(active);
  $("#couponStatsUsed").text(used);

  if (!adminCoupons.length) {
    tbody.append(
      '<tr><td colspan="6" class="text-center py-4 text-secondary">No coupons created yet.</td></tr>',
    );
    return;
  }

  adminCoupons.forEach((coupon) => {
    const usageLimit = parseInt(coupon.usageLimit, 10) || 0;
    const perUserLimit = parseInt(coupon.perUserLimit, 10) || 0;
    const minOrder = Number(coupon.minOrder || 0);
    const maxDiscount = Number(coupon.maxDiscount || 0);
    const usedCount = parseInt(coupon.usedCount, 10) || 0;
    const isActive = !!coupon.isActive;
    const isExpired = !!coupon.isExpired;

    const statusText = !isActive
      ? "Inactive"
      : isExpired
        ? "Expired"
        : "Active";
    const statusClass = !isActive
      ? "bg-secondary"
      : isExpired
        ? "bg-danger"
        : "bg-success";

    const limits = [
      `Min: ${formatPkr(minOrder)}`,
      usageLimit > 0 ? `Limit: ${usageLimit}` : "Limit: Unlimited",
      perUserLimit > 0 ? `Per user: ${perUserLimit}` : "Per user: Unlimited",
      coupon.discountType === "percent" && maxDiscount > 0
        ? `Max discount: ${formatPkr(maxDiscount)}`
        : null,
    ]
      .filter(Boolean)
      .join("<br>");

    const expiryLabel = coupon.expiresAt
      ? formatDateTime(coupon.expiresAt)
      : "No expiry";

    tbody.append(`
      <tr class="border-bottom border-secondary">
        <td class="ps-4 py-3">
          <div class="text-light fw-semibold">${escapeHtml(coupon.code || "")}</div>
          <div class="text-secondary small">${escapeHtml(coupon.title || "Untitled coupon")}</div>
        </td>
        <td class="py-3 text-light">
          <div>${escapeHtml(coupon.discountLabel || "")}</div>
          <div class="text-secondary small">Used ${usedCount} time(s)</div>
        </td>
        <td class="py-3 text-secondary small">${limits}</td>
        <td class="py-3 text-secondary small">${escapeHtml(expiryLabel)}</td>
        <td class="py-3"><span class="badge ${statusClass}">${statusText}</span></td>
        <td class="pe-4 py-3">
          <div class="d-flex flex-wrap gap-1">
            <button class="btn btn-sm btn-outline-orange" onclick="editCouponById(${Number(coupon.id || 0)})"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm ${isActive ? "btn-outline-secondary" : "btn-outline-success"}" onclick="toggleCouponStatus(${Number(coupon.id || 0)}, ${isActive ? 0 : 1})">${isActive ? "Disable" : "Enable"}</button>
            <button class="btn btn-sm btn-outline-danger" onclick="deleteCouponById(${Number(coupon.id || 0)})"><i class="bi bi-trash"></i></button>
          </div>
        </td>
      </tr>
    `);
  });
}

function editCouponById(couponId) {
  const coupon = adminCoupons.find(
    (item) => Number(item.id) === Number(couponId),
  );
  if (!coupon) {
    showNotification("Coupon not found.", "warning");
    return;
  }

  $("#couponId").val(coupon.id || "");
  $("#couponCode").val(coupon.code || "");
  $("#couponTitle").val(coupon.title || "");
  $("#couponDescription").val(coupon.description || "");
  setAdminDropdownSelection(
    "couponDiscountType",
    coupon.discountType || "fixed",
    couponDiscountTypeLabel(coupon.discountType || "fixed"),
  );
  $("#couponDiscountValue").val(coupon.discountValue || "");
  $("#couponMinOrder").val(coupon.minOrder || 0);
  $("#couponMaxDiscount")
    .val(coupon.maxDiscount || "")
    .prop("disabled", (coupon.discountType || "fixed") !== "percent");
  $("#couponUsageLimit").val(coupon.usageLimit || "");
  $("#couponPerUserLimit").val(coupon.perUserLimit || "");
  $("#couponIsActive").prop("checked", !!coupon.isActive);

  if (coupon.expiresAt) {
    const parsed = new Date(coupon.expiresAt);
    if (!Number.isNaN(parsed.getTime())) {
      const localValue = new Date(
        parsed.getTime() - parsed.getTimezoneOffset() * 60000,
      )
        .toISOString()
        .slice(0, 16);
      $("#couponExpiresAt").val(localValue);
    }
  } else {
    $("#couponExpiresAt").val("");
  }
}

async function loadCouponsData(silent = false) {
  if (!$("#couponsSection").length) {
    return false;
  }

  try {
    const response = await fetch(`${ADMIN_COUPONS_API}?action=list`, {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store",
    });

    const result = await response.json();
    if (!response.ok || !result?.ok) {
      throw new Error(result?.message || "Unable to load coupons.");
    }

    adminCoupons = Array.isArray(result?.payload?.coupons)
      ? result.payload.coupons
      : [];

    renderCouponsTable();
    populateCouponEmailSelect();
    return true;
  } catch (error) {
    if (!silent) {
      showNotification(error?.message || "Unable to load coupons.", "danger");
    }
    return false;
  }
}

async function saveCouponFromForm() {
  const id = parseInt($("#couponId").val(), 10) || 0;
  const code = ($("#couponCode").val() || "").toString().trim().toUpperCase();
  const discountType = ($("#couponDiscountType").val() || "fixed").toString();
  const discountValue = parseFloat($("#couponDiscountValue").val()) || 0;

  if (!code || code.length < 3) {
    showNotification("Coupon code must be at least 3 characters.", "danger");
    return;
  }

  if (discountValue <= 0) {
    showNotification("Discount value must be greater than zero.", "danger");
    return;
  }

  const payload = {
    action: "save-coupon",
    id,
    code,
    title: ($("#couponTitle").val() || "").toString().trim(),
    description: ($("#couponDescription").val() || "").toString().trim(),
    discount_type: discountType,
    discount_value: discountValue,
    min_order: parseFloat($("#couponMinOrder").val()) || 0,
    max_discount:
      discountType === "percent"
        ? parseFloat($("#couponMaxDiscount").val()) || null
        : null,
    usage_limit: parseInt($("#couponUsageLimit").val(), 10) || null,
    per_user_limit: parseInt($("#couponPerUserLimit").val(), 10) || null,
    expires_at: ($("#couponExpiresAt").val() || "").toString().trim(),
    is_active: $("#couponIsActive").is(":checked") ? 1 : 0,
  };

  try {
    const result = await adminPostJson(ADMIN_COUPONS_API, payload);
    adminCoupons = Array.isArray(result?.payload?.coupons)
      ? result.payload.coupons
      : [];
    renderCouponsTable();
    populateCouponEmailSelect();
    resetCouponForm();
    showNotification(
      result?.message || "Coupon saved successfully.",
      "success",
    );
  } catch (error) {
    showNotification(error?.message || "Unable to save coupon.", "danger");
  }
}

async function toggleCouponStatus(couponId, isActive) {
  const coupon = adminCoupons.find(
    (item) => Number(item.id) === Number(couponId),
  );
  if (!coupon) {
    showNotification("Coupon not found.", "warning");
    return;
  }

  const payload = {
    action: "save-coupon",
    id: coupon.id,
    code: coupon.code,
    title: coupon.title || "",
    description: coupon.description || "",
    discount_type: coupon.discountType || "fixed",
    discount_value: Number(coupon.discountValue || 0),
    min_order: Number(coupon.minOrder || 0),
    max_discount:
      coupon.discountType === "percent"
        ? Number(coupon.maxDiscount || 0) || null
        : null,
    usage_limit: parseInt(coupon.usageLimit, 10) || null,
    per_user_limit: parseInt(coupon.perUserLimit, 10) || null,
    expires_at: coupon.expiresAt || "",
    is_active: isActive ? 1 : 0,
  };

  try {
    const result = await adminPostJson(ADMIN_COUPONS_API, payload);
    adminCoupons = Array.isArray(result?.payload?.coupons)
      ? result.payload.coupons
      : [];
    renderCouponsTable();
    populateCouponEmailSelect();
    showNotification(result?.message || "Coupon status updated.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to update coupon status.",
      "danger",
    );
  }
}

async function deleteCouponById(couponId) {
  const coupon = adminCoupons.find(
    (item) => Number(item.id) === Number(couponId),
  );
  if (!coupon) {
    showNotification("Coupon not found.", "warning");
    return;
  }

  const confirmed = await showCustomConfirmDialog(
    `Delete coupon ${coupon.code}?`,
    "Delete Coupon",
  );
  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_COUPONS_API, {
      action: "delete-coupon",
      id: couponId,
    });
    adminCoupons = Array.isArray(result?.payload?.coupons)
      ? result.payload.coupons
      : [];
    renderCouponsTable();
    populateCouponEmailSelect();
    showNotification(result?.message || "Coupon deleted.", "success");
  } catch (error) {
    showNotification(error?.message || "Unable to delete coupon.", "danger");
  }
}

async function sendCouponEmail() {
  const couponId = parseInt($("#couponEmailCouponId").val(), 10) || 0;
  const recipientsRaw = ($("#couponEmailRecipients").val() || "").toString();
  const subject = ($("#couponEmailSubject").val() || "").toString().trim();
  const message = ($("#couponEmailMessage").val() || "").toString().trim();

  if (couponId <= 0) {
    showNotification("Select a coupon before sending email.", "danger");
    return;
  }

  if (!recipientsRaw.trim()) {
    showNotification("Add at least one recipient email.", "danger");
    return;
  }

  const recipients = recipientsRaw
    .split(/[\s,;]+/)
    .map((item) => item.trim())
    .filter(Boolean);

  if (!recipients.length) {
    showNotification("Add valid recipient email addresses.", "danger");
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_COUPONS_API, {
      action: "send-coupon-email",
      coupon_id: couponId,
      recipients,
      subject,
      message,
    });

    adminCoupons = Array.isArray(result?.payload?.coupons)
      ? result.payload.coupons
      : adminCoupons;
    renderCouponsTable();
    showNotification(result?.message || "Coupon email sent.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to send coupon email.",
      "danger",
    );
  }
}

function initCouponsSection() {
  if (!$("#couponsSection").length) {
    return;
  }

  $("#couponDiscountType")
    .off("change")
    .on("change", function () {
      const isPercent = $(this).val() === "percent";
      $("#couponMaxDiscount").prop("disabled", !isPercent);
      if (!isPercent) {
        $("#couponMaxDiscount").val("");
      }
    })
    .trigger("change");

  $("#saveCouponBtn").off("click").on("click", saveCouponFromForm);
  $("#resetCouponBtn").off("click").on("click", resetCouponForm);
  $("#refreshCouponsBtn")
    .off("click")
    .on("click", () => loadCouponsData(false));
  $("#sendCouponEmailBtn").off("click").on("click", sendCouponEmail);

  resetCouponForm();
}

function renderReviewsStats(stats = {}) {
  $("#reviewStatTotal").text(parseInt(stats.total, 10) || 0);
  $("#reviewStatVisible").text(parseInt(stats.visible, 10) || 0);
  $("#reviewStatHidden").text(parseInt(stats.hidden, 10) || 0);
  $("#reviewStatAverage").text(Number(stats.averageRating || 0).toFixed(2));
  $("#reviewStatLocked").text(parseInt(stats.locked || 0, 10) || 0);
}

function renderAdminReviewImages(images) {
  const list = Array.isArray(images) ? images : [];
  if (!list.length) {
    return "";
  }

  const thumbs = list
    .slice(0, 2)
    .map((image) => {
      const path = (image?.path || "").toString().trim();
      if (!path) {
        return "";
      }

      const resolved = resolveAdminImagePath(path);
      const safePath = sanitizeAdminMediaUrl(resolved || `../../${path}`);
      if (!safePath) {
        return "";
      }

      return `<a href="${escapeHtml(safePath)}" target="_blank" rel="noopener" class="d-inline-block me-1 mt-2"><img src="${escapeHtml(safePath)}" alt="Review image" style="width:34px;height:34px;object-fit:cover;border-radius:6px;border:1px solid rgba(255,255,255,0.2);" onerror="this.style.display='none';"></a>`;
    })
    .join("");

  return thumbs
    ? `<div class="small text-info">Images: ${list.length}</div><div>${thumbs}</div>`
    : "";
}

function renderReviewsTable() {
  const tbody = $("#reviewsTable tbody");
  if (!tbody.length) return;

  tbody.empty();

  if (!adminReviews.length) {
    tbody.append(
      '<tr><td colspan="7" class="text-center py-4 text-secondary">No reviews found.</td></tr>',
    );
    return;
  }

  adminReviews.forEach((review) => {
    const statusBadge = review.isVisible
      ? '<span class="badge bg-success">Visible</span>'
      : '<span class="badge bg-secondary">Hidden</span>';
    const lockBadge = review.isLocked
      ? '<span class="badge bg-info text-dark">Locked</span>'
      : '<span class="badge bg-dark border border-secondary">Unlocked</span>';
    const rating = Math.max(1, Math.min(5, parseInt(review.rating, 10) || 0));
    const stars = `${"★".repeat(rating)}${"☆".repeat(5 - rating)}`;
    const text = (review.reviewText || "").toString();
    const clipped = text.length > 120 ? `${text.slice(0, 120)}...` : text;
    const imageMarkup = renderAdminReviewImages(review.images || []);
    const lockMeta = review.isLocked
      ? `<div class="text-info small">${escapeHtml(
          review.lockedAt
            ? `Locked: ${formatDateTime(review.lockedAt)}`
            : "Locked",
        )}</div>`
      : "";
    const reviewId = Number(review.id || 0);

    tbody.append(`
      <tr class="border-bottom border-secondary">
        <td class="ps-4 py-3 text-light fw-semibold">${escapeHtml(review.productName || "Product")}</td>
        <td class="py-3 text-light">
          <div>${escapeHtml(review.userName || "Customer")}</div>
          <div class="text-secondary small">${escapeHtml(review.userEmail || "")}</div>
        </td>
        <td class="py-3 text-warning">${stars}</td>
        <td class="py-3 text-secondary small" title="${escapeHtml(text)}">${escapeHtml(clipped)}${imageMarkup}</td>
        <td class="py-3">
          <div class="d-flex flex-column gap-1">${statusBadge}${lockBadge}</div>
        </td>
        <td class="py-3 text-secondary small">${escapeHtml(formatDateTime(review.updatedAt))}${lockMeta}</td>
        <td class="pe-4 py-3">
          <div class="d-flex flex-wrap gap-1">
            <button class="btn btn-sm btn-outline-orange" onclick="editReviewById(${reviewId})" ${review.isLocked ? "disabled" : ""}>Edit</button>
            <button class="btn btn-sm ${review.isVisible ? "btn-outline-secondary" : "btn-outline-success"}" onclick="toggleReviewVisibilityById(${reviewId}, ${review.isVisible ? 0 : 1})">${review.isVisible ? "Hide" : "Show"}</button>
            <button class="btn btn-sm ${review.isLocked ? "btn-outline-warning" : "btn-outline-info"}" onclick="setReviewLockById(${reviewId}, ${review.isLocked ? 0 : 1})">${review.isLocked ? "Unlock" : "Lock"}</button>
            <button class="btn btn-sm btn-outline-danger" onclick="deleteReviewById(${reviewId})"><i class="bi bi-trash"></i></button>
          </div>
        </td>
      </tr>
    `);
  });
}

async function loadReviewsData(silent = false) {
  if (!$("#reviewsSection").length) {
    return false;
  }

  const visibility = ($("#reviewVisibilityFilter").val() || "all").toString();

  try {
    const response = await fetch(
      `${ADMIN_REVIEWS_API}?action=list&visibility=${encodeURIComponent(visibility)}`,
      {
        method: "GET",
        credentials: "same-origin",
        cache: "no-store",
      },
    );

    const result = await response.json();
    if (!response.ok || !result?.ok) {
      throw new Error(result?.message || "Unable to load reviews.");
    }

    adminReviews = Array.isArray(result?.payload?.reviews)
      ? result.payload.reviews
      : [];
    renderReviewsStats(result?.payload?.stats || {});
    renderReviewsTable();
    return true;
  } catch (error) {
    if (!silent) {
      showNotification(error?.message || "Unable to load reviews.", "danger");
    }
    return false;
  }
}

async function toggleReviewVisibilityById(reviewId, isVisible) {
  try {
    const result = await adminPostJson(ADMIN_REVIEWS_API, {
      action: "set-visibility",
      id: reviewId,
      is_visible: isVisible ? 1 : 0,
    });

    adminReviews = Array.isArray(result?.payload?.reviews)
      ? result.payload.reviews
      : adminReviews;
    renderReviewsStats(result?.payload?.stats || {});
    renderReviewsTable();
    showNotification(
      result?.message || "Review visibility updated.",
      "success",
    );
  } catch (error) {
    showNotification(
      error?.message || "Unable to update review visibility.",
      "danger",
    );
  }
}

async function setReviewLockById(reviewId, isLocked) {
  const safeReviewId = parseInt(reviewId, 10) || 0;
  if (safeReviewId <= 0) {
    showNotification("Invalid review id.", "warning");
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_REVIEWS_API, {
      action: "set-lock",
      id: safeReviewId,
      is_locked: isLocked ? 1 : 0,
    });

    adminReviews = Array.isArray(result?.payload?.reviews)
      ? result.payload.reviews
      : adminReviews;
    renderReviewsStats(result?.payload?.stats || {});
    renderReviewsTable();
    showNotification(
      result?.message || "Review lock state updated.",
      "success",
    );
  } catch (error) {
    showNotification(
      error?.message || "Unable to update review lock state.",
      "danger",
    );
  }
}

async function editReviewById(reviewId) {
  const review = adminReviews.find(
    (item) => Number(item.id) === Number(reviewId),
  );
  if (!review) {
    showNotification("Review not found.", "warning");
    return;
  }

  if (review.isLocked) {
    showNotification("Unlock this review before editing it.", "warning");
    return;
  }

  const ratingInput = await showCustomPromptDialog(
    "Update rating (1-5):",
    String(review.rating || 5),
    "Edit Review Rating",
  );
  if (ratingInput === null) {
    return;
  }

  const parsedRating = parseInt((ratingInput || "").toString().trim(), 10);
  if (!Number.isInteger(parsedRating) || parsedRating < 1 || parsedRating > 5) {
    showNotification("Rating must be between 1 and 5.", "danger");
    return;
  }

  const textInput = await showCustomPromptDialog(
    "Update review text:",
    review.reviewText || "",
    "Edit Review Text",
  );
  if (textInput === null) {
    return;
  }

  const reviewText = (textInput || "").toString().trim();
  if (reviewText.length < 10 || reviewText.length > 500) {
    showNotification("Review text must be 10 to 500 characters.", "danger");
    return;
  }

  const noteInput = await showCustomPromptDialog(
    "Optional admin note:",
    review.adminNote || "",
    "Admin Note",
  );

  const adminNote = noteInput === null ? review.adminNote || "" : noteInput;

  try {
    const result = await adminPostJson(ADMIN_REVIEWS_API, {
      action: "update-review",
      id: reviewId,
      rating: parsedRating,
      review_text: reviewText,
      admin_note: adminNote,
    });

    adminReviews = Array.isArray(result?.payload?.reviews)
      ? result.payload.reviews
      : adminReviews;
    renderReviewsStats(result?.payload?.stats || {});
    renderReviewsTable();
    showNotification(result?.message || "Review updated.", "success");
  } catch (error) {
    showNotification(error?.message || "Unable to update review.", "danger");
  }
}

async function deleteReviewById(reviewId) {
  const review = adminReviews.find(
    (item) => Number(item.id) === Number(reviewId),
  );
  if (!review) {
    showNotification("Review not found.", "warning");
    return;
  }

  const confirmed = await showCustomConfirmDialog(
    "Delete this review permanently?",
    "Delete Review",
  );
  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_REVIEWS_API, {
      action: "delete-review",
      id: reviewId,
    });

    adminReviews = Array.isArray(result?.payload?.reviews)
      ? result.payload.reviews
      : adminReviews;
    renderReviewsStats(result?.payload?.stats || {});
    renderReviewsTable();
    showNotification(result?.message || "Review deleted.", "success");
  } catch (error) {
    showNotification(error?.message || "Unable to delete review.", "danger");
  }
}

function addReviewByAdmin() {
  focusReviewQuickForm();
}

async function addFakeBulkReviewsByAdmin(forcedCount = null) {
  const inputProductId = parseAdminProductIdInput(
    $("#fakeReviewProductId").val(),
  );
  let productId = inputProductId;

  if (productId <= 0) {
    const productIdPrompt = await showCustomPromptDialog(
      "Product ID for fake review generation:",
      "",
      "Fake Review Product",
    );

    if (productIdPrompt === null) {
      return;
    }

    productId = parseAdminProductIdInput(productIdPrompt);
  }

  if (productId <= 0) {
    showNotification("Enter a valid Product ID.", "warning");
    return;
  }

  const configuredCount = parseInt($("#fakeReviewCount").val(), 10) || 1;
  const count = Math.max(
    1,
    Math.min(
      100,
      Number.isInteger(forcedCount) ? forcedCount : configuredCount,
    ),
  );
  const ratingMin = Math.max(
    1,
    Math.min(5, parseInt($("#fakeReviewRatingMin").val(), 10) || 3),
  );
  const ratingMax = Math.max(
    1,
    Math.min(5, parseInt($("#fakeReviewRatingMax").val(), 10) || 5),
  );
  const isVisible =
    parseInt($("#fakeReviewVisibility").val(), 10) === 0 ? 0 : 1;

  try {
    const result = await adminPostJson(ADMIN_REVIEWS_API, {
      action: "add-fake-bulk-reviews",
      product_id: productId,
      count,
      rating_min: Math.min(ratingMin, ratingMax),
      rating_max: Math.max(ratingMin, ratingMax),
      is_visible: isVisible,
    });

    adminReviews = Array.isArray(result?.payload?.reviews)
      ? result.payload.reviews
      : adminReviews;
    renderReviewsStats(result?.payload?.stats || {});
    renderReviewsTable();
    showNotification(result?.message || "Fake reviews generated.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to generate fake reviews.",
      "danger",
    );
  }
}

function initReviewsSection() {
  if (!$("#reviewsSection").length) {
    return;
  }

  renderReviewProductSuggestions();
  resetReviewQuickAddForm();

  setAdminDropdownSelection(
    "reviewVisibilityFilter",
    $("#reviewVisibilityFilter").val() || "all",
    reviewVisibilityLabel($("#reviewVisibilityFilter").val() || "all"),
  );
  setAdminDropdownSelection(
    "fakeReviewVisibility",
    $("#fakeReviewVisibility").val() || "1",
    fakeReviewVisibilityLabel($("#fakeReviewVisibility").val() || "1"),
  );

  $("#reviewVisibilityFilter")
    .off("change")
    .on("change", function () {
      loadReviewsData(false);
    });

  $("#refreshReviewsBtn")
    .off("click")
    .on("click", function () {
      loadReviewsData(false);
    });

  $("#addReviewBtn")
    .off("click")
    .on("click", function () {
      addReviewByAdmin();
    });

  $("#submitAddReviewBtn")
    .off("click")
    .on("click", function () {
      submitReviewQuickAddForm();
    });

  $("#clearAddReviewBtn")
    .off("click")
    .on("click", function () {
      resetReviewQuickAddForm();
    });

  $("#addFakeReviewBtn")
    .off("click")
    .on("click", function () {
      addFakeBulkReviewsByAdmin(1);
    });

  $("#addFakeBulkReviewsBtn")
    .off("click")
    .on("click", function () {
      addFakeBulkReviewsByAdmin();
    });

  $("#addSingleFakeReviewBtn")
    .off("click")
    .on("click", function () {
      addFakeBulkReviewsByAdmin(1);
    });
}

function securitySeverityBadgeClass(severity) {
  const normalized = (severity || "").toString().toLowerCase();
  if (normalized === "critical") {
    return "security-severity-pill security-severity-critical";
  }
  if (normalized === "warning") {
    return "security-severity-pill security-severity-warning";
  }
  return "security-severity-pill security-severity-info";
}

function securityEventRowClass(severity) {
  const normalized = (severity || "").toString().toLowerCase();
  if (normalized === "critical") return "security-event-critical";
  if (normalized === "warning") return "security-event-warning";
  return "security-event-info";
}

function humanizeSecurityText(value) {
  const raw = (value || "").toString().trim();
  if (raw === "") {
    return "Unknown";
  }

  return raw
    .replace(/[._-]+/g, " ")
    .replace(/\s+/g, " ")
    .trim()
    .replace(/\b\w/g, (ch) => ch.toUpperCase());
}

function securityEventTypeLabel(eventType) {
  const normalized = (eventType || "").toString().trim().toLowerCase();
  if (normalized === "login_failed") {
    return "Login Failed";
  }
  if (normalized === "login_success") {
    return "Login Successful";
  }
  if (normalized === "suspicious_login_ip_change") {
    return "Suspicious Login (IP Changed)";
  }
  if (normalized === "rate_limit_block") {
    return "Rate Limit Blocked";
  }

  return humanizeSecurityText(eventType);
}

function securityActorDisplayLabel(actorType) {
  const normalized = (actorType || "").toString().trim().toLowerCase();
  if (normalized === "admin") {
    return "Admin";
  }
  if (normalized === "user") {
    return "Customer";
  }
  return "System";
}

function securityIdentifierDisplay(actorIdentifier) {
  const value = (actorIdentifier || "").toString().trim();
  const lowered = value.toLowerCase();

  if (value === "" || value === "-" || lowered === "anonymous") {
    return {
      label: "Account",
      value: "Unknown account",
    };
  }

  if (value.includes("@")) {
    return {
      label: "Account",
      value,
    };
  }

  if (/^\d{11,15}$/.test(value)) {
    return {
      label: "Phone",
      value,
    };
  }

  return {
    label: "Identifier",
    value,
  };
}

function securityIpDisplay(ipAddress) {
  const ip = (ipAddress || "").toString().trim();
  if (ip === "" || ip === "-" || ip === "0.0.0.0") {
    return {
      label: "Location",
      value: "IP unavailable",
    };
  }

  if (ip === "127.0.0.1" || ip === "::1") {
    return {
      label: "Location",
      value: "Localhost",
    };
  }

  return {
    label: "IP Address",
    value: ip,
  };
}

function securityDetailValueText(value) {
  if (value === null || typeof value === "undefined") {
    return "-";
  }

  if (typeof value === "boolean") {
    return value ? "Yes" : "No";
  }

  if (typeof value === "number") {
    return Number.isFinite(value) ? value.toString() : "-";
  }

  if (Array.isArray(value)) {
    const preview = value
      .slice(0, 3)
      .map((entry) => securityDetailValueText(entry))
      .filter((entry) => entry !== "-")
      .join(", ");
    if (preview !== "") {
      return preview;
    }
    return value.length ? "Items available" : "-";
  }

  if (typeof value === "object") {
    return "Additional data available";
  }

  const text = value.toString().trim();
  if (text === "") {
    return "-";
  }

  return humanizeSecurityText(text);
}

function securityEventDetailsSummary(eventItem) {
  const details = eventItem?.details;
  if (!details || typeof details !== "object") {
    const preview = (eventItem?.details_preview || "").toString().trim();
    return preview !== "" && preview !== "-"
      ? preview
      : "No additional details";
  }

  const currentIp = securityDetailValueText(details.current_ip);
  const previousIp = securityDetailValueText(details.previous_ip);
  if (currentIp !== "-" && previousIp !== "-") {
    return `IP changed from ${previousIp} to ${currentIp}`;
  }

  const summaryParts = [];
  const entries = Object.entries(details);
  for (let index = 0; index < entries.length; index += 1) {
    const [key, rawValue] = entries[index];
    const valueText = securityDetailValueText(rawValue);
    if (valueText === "-") {
      continue;
    }

    const normalizedKey = (key || "").toString().trim().toLowerCase();
    if (normalizedKey === "retry_after_seconds") {
      summaryParts.push(`Retry after ${valueText} seconds`);
      continue;
    }

    if (normalizedKey === "blacklist_id") {
      summaryParts.push(`Blacklist entry #${valueText}`);
      continue;
    }

    if (normalizedKey === "affected_rows") {
      summaryParts.push(`Affected records: ${valueText}`);
      continue;
    }

    const label = humanizeSecurityText(key);
    summaryParts.push(`${label}: ${valueText}`);

    if (summaryParts.length >= 3) {
      break;
    }
  }

  if (!summaryParts.length) {
    return "No additional details";
  }

  return summaryParts.join(" | ");
}

function renderSecurityEventsTable() {
  const tbody = $("#securityEventsTable tbody");
  if (!tbody.length) {
    return;
  }

  tbody.empty();

  const events = Array.isArray(securityEventsState.events)
    ? securityEventsState.events
    : [];

  if (!events.length) {
    tbody.append(
      '<tr><td colspan="6" class="text-center py-4 text-secondary">No security events found for these filters.</td></tr>',
    );
    return;
  }

  events.forEach((eventItem) => {
    const eventType = (eventItem?.event_type || "unknown_event").toString();
    const eventTypeLabel = securityEventTypeLabel(eventType);
    const severity = (eventItem?.severity || "info").toString();
    const severityLabel = securitySeverityLabel(severity);
    const actorType = (eventItem?.actor_type || "-").toString();
    const actorIdentifier = (eventItem?.actor_identifier || "-").toString();
    const ipAddress = (eventItem?.ip_address || "-").toString();
    const actorLabel = securityActorDisplayLabel(actorType);
    const identifierDisplay = securityIdentifierDisplay(actorIdentifier);
    const ipDisplay = securityIpDisplay(ipAddress);
    const detailsPreview = securityEventDetailsSummary(eventItem);
    const createdAt = formatDateTime(eventItem?.created_at || "");

    const detailsTitle =
      eventItem?.details && typeof eventItem.details === "object"
        ? escapeHtml(JSON.stringify(eventItem.details))
        : "";

    tbody.append(`
      <tr class="border-bottom border-secondary security-event-row ${securityEventRowClass(severity)}">
        <td class="ps-4 py-3 text-secondary small">${escapeHtml(createdAt)}</td>
        <td class="py-3 text-light fw-semibold"><div class="security-event-label">${escapeHtml(eventTypeLabel)}</div><div class="security-event-code">${escapeHtml(eventType)}</div></td>
        <td class="py-3"><span class="badge ${securitySeverityBadgeClass(severity)} rounded-pill">${escapeHtml(severityLabel)}</span></td>
        <td class="py-3 text-secondary small">${escapeHtml(actorLabel)}</td>
        <td class="py-3 text-secondary small"><div class="security-event-identifier-label">${escapeHtml(identifierDisplay.label)}</div><div class="security-event-identifier">${escapeHtml(identifierDisplay.value)}</div><div class="security-event-ip-label">${escapeHtml(ipDisplay.label)}</div><div class="security-event-ip">${escapeHtml(ipDisplay.value)}</div></td>
        <td class="pe-4 py-3 text-secondary small security-event-details" title="${detailsTitle}">${escapeHtml(detailsPreview)}</td>
      </tr>
    `);
  });
}

function renderSecurityEventsMeta() {
  const meta = $("#securityEventsMeta");
  if (!meta.length) {
    return;
  }

  const page = Math.max(1, parseInt(securityEventsState.page, 10) || 1);
  const totalPages = Math.max(
    1,
    parseInt(securityEventsState.totalPages, 10) || 1,
  );
  const total = Math.max(0, parseInt(securityEventsState.total, 10) || 0);

  meta.text(
    `Page ${page} of ${totalPages} · ${total.toLocaleString()} event(s)`,
  );

  $("#securityEventsPrevBtn").prop("disabled", page <= 1);
  $("#securityEventsNextBtn").prop("disabled", page >= totalPages);
}

function securityEventsFilterPayload() {
  return {
    eventType: ($("#securityEventTypeFilter").val() || "").toString().trim(),
    severity: ($("#securitySeverityFilter").val() || "").toString().trim(),
    actorType: ($("#securityActorTypeFilter").val() || "").toString().trim(),
    search: ($("#securityEventSearchFilter").val() || "").toString().trim(),
    from: ($("#securityFromFilter").val() || "").toString().trim(),
    to: ($("#securityToFilter").val() || "").toString().trim(),
  };
}

async function loadSecurityEvents(silent = false) {
  if (!$("#securityEventsSection").length) {
    return false;
  }

  const filters = securityEventsState.filters || {};
  const page = Math.max(1, parseInt(securityEventsState.page, 10) || 1);
  const perPage = Math.max(5, parseInt(securityEventsState.perPage, 10) || 25);

  const params = new URLSearchParams();
  params.set("action", "list-events");
  params.set("page", String(page));
  params.set("per_page", String(Math.min(perPage, 100)));

  if (filters.eventType) params.set("event_type", filters.eventType);
  if (filters.severity) params.set("severity", filters.severity);
  if (filters.actorType) params.set("actor_type", filters.actorType);
  if (filters.search) params.set("search", filters.search);
  if (filters.from) params.set("from", filters.from);
  if (filters.to) params.set("to", filters.to);

  try {
    const response = await fetch(`${ADMIN_SECURITY_API}?${params.toString()}`, {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store",
    });

    const result = await response.json();
    if (!response.ok || !result?.ok) {
      throw new Error(result?.message || "Unable to load security events.");
    }

    const payload = result?.payload || {};
    const pagination = payload?.pagination || {};

    securityEventsState.events = Array.isArray(payload?.events)
      ? payload.events
      : [];
    securityEventsState.page = Math.max(
      1,
      parseInt(pagination?.page, 10) || securityEventsState.page || 1,
    );
    securityEventsState.perPage = Math.max(
      5,
      parseInt(pagination?.per_page, 10) || securityEventsState.perPage || 25,
    );
    securityEventsState.total = Math.max(
      0,
      parseInt(pagination?.total, 10) || 0,
    );
    securityEventsState.totalPages = Math.max(
      1,
      parseInt(pagination?.total_pages, 10) || 1,
    );

    renderSecurityEventsTable();
    renderSecurityEventsMeta();
    return true;
  } catch (error) {
    if (!silent) {
      showNotification(
        error?.message || "Unable to load security events.",
        "danger",
      );
    }
    return false;
  }
}

function applySecurityEventsFilters(resetPage = true) {
  securityEventsState.filters = securityEventsFilterPayload();
  if (resetPage) {
    securityEventsState.page = 1;
  }
  loadSecurityEvents(false);
}

function clearSecurityEventsFilters() {
  $("#securityEventTypeFilter").val("");
  setAdminDropdownSelection("securitySeverityFilter", "", "All");
  setAdminDropdownSelection("securityActorTypeFilter", "", "All");
  $("#securityEventSearchFilter").val("");
  $("#securityFromFilter").val("");
  $("#securityToFilter").val("");

  securityEventsState.filters = {
    eventType: "",
    severity: "",
    actorType: "",
    search: "",
    from: "",
    to: "",
  };
  securityEventsState.page = 1;
  loadSecurityEvents(false);
}

function initSecurityEventsSection() {
  if (!$("#securityEventsSection").length) {
    return;
  }

  setAdminDropdownSelection(
    "securitySeverityFilter",
    $("#securitySeverityFilter").val() || "",
    securitySeverityLabel($("#securitySeverityFilter").val() || ""),
  );
  setAdminDropdownSelection(
    "securityActorTypeFilter",
    $("#securityActorTypeFilter").val() || "",
    securityActorTypeLabel($("#securityActorTypeFilter").val() || ""),
  );

  $("#securityEventsApplyBtn")
    .off("click")
    .on("click", function () {
      applySecurityEventsFilters(true);
    });

  $("#securityEventsClearBtn")
    .off("click")
    .on("click", function () {
      clearSecurityEventsFilters();
    });

  $("#securityEventsRefreshBtn")
    .off("click")
    .on("click", function () {
      loadSecurityEvents(false);
    });

  $("#securityEventsPrevBtn")
    .off("click")
    .on("click", function () {
      if (securityEventsState.page <= 1) {
        return;
      }
      securityEventsState.page -= 1;
      loadSecurityEvents(false);
    });

  $("#securityEventsNextBtn")
    .off("click")
    .on("click", function () {
      if (securityEventsState.page >= securityEventsState.totalPages) {
        return;
      }
      securityEventsState.page += 1;
      loadSecurityEvents(false);
    });
}

function isWithinDays(dateString, days) {
  if (!dateString) return false;
  const date = new Date(dateString);
  if (Number.isNaN(date.getTime())) return false;
  const now = new Date();
  const diffMs = now - date;
  const diffDays = diffMs / (1000 * 60 * 60 * 24);
  return diffDays <= days;
}

function get30DaysAgo() {
  const date = new Date();
  date.setDate(date.getDate() - 30);
  return date.toISOString().split("T")[0];
}

function calculateDashboardMetrics() {
  const thirtyDaysAgo = get30DaysAgo();
  const orders = getAdminOrdersData();

  const fallbackRevenue = orders
    .filter(
      (order) =>
        order.orderDate >= thirtyDaysAgo &&
        (order.status || "").toLowerCase() === "delivered",
    )
    .reduce((sum, order) => sum + Number(order.total || 0), 0);

  const fallbackOrders = orders.filter(
    (order) => order.orderDate >= thirtyDaysAgo,
  ).length;

  const fallbackPendingFulfillment = orders.filter((order) => {
    if (order.orderDate < thirtyDaysAgo) {
      return false;
    }

    const status = (order.status || "").toLowerCase();
    return ["pending", "confirmed", "processing"].includes(status);
  }).length;

  const fallbackCustomerSet = new Set();
  orders
    .filter((order) => order.orderDate >= thirtyDaysAgo)
    .forEach((order) => {
      if (order.customerName) {
        fallbackCustomerSet.add(order.customerName);
      }
    });

  const fallbackCustomerOrderCounts = new Map();
  orders
    .filter((order) => order.orderDate >= thirtyDaysAgo)
    .forEach((order) => {
      const key =
        String(order.customerEmail || order.customerName || "")
          .trim()
          .toLowerCase() || "";

      if (key === "") {
        return;
      }

      fallbackCustomerOrderCounts.set(
        key,
        (fallbackCustomerOrderCounts.get(key) || 0) + 1,
      );
    });

  const fallbackReturningCustomers = Array.from(
    fallbackCustomerOrderCounts.values(),
  ).filter((count) => count > 1).length;

  const fallbackReturningRate =
    fallbackCustomerOrderCounts.size > 0
      ? (fallbackReturningCustomers / fallbackCustomerOrderCounts.size) * 100
      : 0;

  let totalProducts = 0;
  if (allSections && allSections.length > 0) {
    allSections.forEach((section) => {
      if (section.products && Array.isArray(section.products)) {
        totalProducts += section.products.length;
      }
    });
  }

  const revenue = Number(adminMetrics?.totalRevenue ?? fallbackRevenue);
  const totalOrdersCount = Number(adminMetrics?.totalOrders ?? fallbackOrders);
  const totalCustomers = Number(
    adminMetrics?.totalCustomers ?? fallbackCustomerSet.size,
  );
  const productsCount = Number(adminMetrics?.totalProducts ?? totalProducts);
  const avgOrderValue = Number(
    adminMetrics?.avgOrderValue ??
      (totalOrdersCount > 0 ? revenue / totalOrdersCount : 0),
  );
  const returningCustomerRate = Number(
    adminMetrics?.returningCustomerRate ?? fallbackReturningRate,
  );
  const pendingFulfillmentCount = Number(
    adminMetrics?.pendingFulfillment ?? fallbackPendingFulfillment,
  );

  const totalRevenueNode = document.getElementById("totalRevenueValue");
  if (totalRevenueNode) {
    totalRevenueNode.textContent = "PKR " + revenue.toLocaleString();
  }

  const totalOrdersNode = document.getElementById("totalOrdersValue");
  if (totalOrdersNode) {
    totalOrdersNode.textContent = totalOrdersCount.toLocaleString();
  }

  const totalCustomersNode = document.getElementById("totalCustomersValue");
  if (totalCustomersNode) {
    totalCustomersNode.textContent = totalCustomers.toLocaleString();
  }

  const totalProductsNode = document.getElementById("totalProductsValue");
  if (totalProductsNode) {
    totalProductsNode.textContent = productsCount.toLocaleString();
  }

  const avgOrderValueNode = document.getElementById("avgOrderValueValue");
  if (avgOrderValueNode) {
    avgOrderValueNode.textContent =
      "PKR " +
      avgOrderValue.toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
      });
  }

  const returningCustomerRateNode = document.getElementById(
    "returningCustomerRateValue",
  );
  if (returningCustomerRateNode) {
    returningCustomerRateNode.textContent = `${returningCustomerRate.toFixed(1)}%`;
  }

  const pendingFulfillmentNode = document.getElementById(
    "pendingFulfillmentValue",
  );
  if (pendingFulfillmentNode) {
    pendingFulfillmentNode.textContent =
      pendingFulfillmentCount.toLocaleString();
  }

  const returningCustomerRateInfoNode = document.getElementById(
    "returningCustomerRateInfo",
  );
  if (returningCustomerRateInfoNode && fallbackCustomerOrderCounts.size > 0) {
    returningCustomerRateInfoNode.textContent =
      fallbackReturningCustomers +
      " repeat buyers out of " +
      fallbackCustomerOrderCounts.size +
      " active customers";
  }
}

function loadProductsFromJSON() {
  return fetch(`${ADMIN_PRODUCTS_SYNC_API}?action=get-products`, {
    method: "GET",
    credentials: "same-origin",
  })
    .then(async (response) => {
      const responseText = await response.text();
      let result = null;

      try {
        result = responseText ? JSON.parse(responseText) : null;
      } catch (error) {
        throw new Error(
          "Invalid server response while loading products. Please re-login and refresh the admin page.",
        );
      }

      if (!response.ok) {
        throw new Error(
          result?.message || "Could not load products from server.",
        );
      }

      return result;
    })
    .then((result) => {
      if (!result?.ok || !result?.payload) {
        throw new Error(
          result?.message || "Could not load products from server.",
        );
      }
      hydrateProductsData(result.payload);
    })
    .catch((error) => {
      console.error("Error loading products from backend:", error);
      showNotification(
        error?.message || "Error loading products. Please refresh the page.",
        "danger",
      );
    });
}

function hydrateProductsData(data) {
  allSections = data.sections || [];
  productsData = [];
  allSections.forEach((section) => {
    (section.products || []).forEach((product) => {
      productsData.push({
        ...product,
        category: section.category,
        subcategory: section.subcategory,
        sectionName: section.sectionName,
        sectionId: section.sectionId,
        page: section.page,
      });
    });
  });
  nextId = productsData.length
    ? Math.max(...productsData.map((p) => p.id)) + 1
    : 1;
  nextSectionId = allSections.length ? allSections.length + 1 : 1;
  if (Array.isArray(data?.trash?.items)) {
    hydrateProductTrashItems(data.trash.items);
  }
  syncProductWorkspaceSummary();
  renderSectionDropdowns();
  renderSectionsTable();
  renderProductsTable();
  renderReviewProductSuggestions();
  calculateDashboardMetrics();
  updateNotifications();
}

function syncProductWorkspaceSummary() {
  $("#productWorkspaceProducts").text(
    Array.isArray(productsData) ? productsData.length : 0,
  );
  $("#productWorkspaceSections").text(
    Array.isArray(allSections) ? allSections.length : 0,
  );
  $("#productWorkspaceTrash").text(
    Array.isArray(productTrashItems) ? productTrashItems.length : 0,
  );
}

function syncProductTrashMeta(items) {
  const safeItems = Array.isArray(items) ? items : [];
  let expiringSoon = 0;
  let expired = 0;

  safeItems.forEach((item) => {
    const seconds = Math.max(0, parseInt(item?.expiresInSeconds, 10) || 0);
    if (seconds <= 0) {
      expired += 1;
      return;
    }

    if (seconds <= 86400) {
      expiringSoon += 1;
    }
  });

  $("#productTrashTotalBadge").text(`Total: ${safeItems.length}`);
  $("#productTrashExpiringBadge").text(`Expiring < 24h: ${expiringSoon}`);
  $("#productTrashExpiredBadge").text(`Expired: ${expired}`);
}

function hydrateProductTrashItems(items) {
  productTrashItems = Array.isArray(items) ? items : [];
  syncProductWorkspaceSummary();
  renderProductTrashTable();
}

function formatTrashCountdown(seconds) {
  let remaining = Math.max(0, parseInt(seconds, 10) || 0);
  const days = Math.floor(remaining / 86400);
  remaining -= days * 86400;
  const hours = Math.floor(remaining / 3600);
  remaining -= hours * 3600;
  const minutes = Math.floor(remaining / 60);

  if (days > 0) {
    return `${days}d ${hours}h`;
  }
  if (hours > 0) {
    return `${hours}h ${minutes}m`;
  }
  return `${minutes}m`;
}

function renderProductTrashTable() {
  const tbody = $("#productTrashTable tbody");
  if (!tbody.length) {
    return;
  }

  tbody.empty();
  const items = Array.isArray(productTrashItems) ? productTrashItems : [];
  $("#productTrashCount").text(items.length);
  syncProductTrashMeta(items);

  if (!items.length) {
    tbody.append(
      '<tr><td colspan="5" class="text-center py-4 text-secondary">Trash is empty.</td></tr>',
    );
    return;
  }

  items.forEach((item) => {
    const name = escapeHtml((item?.name || "Product").toString());
    const code = escapeHtml((item?.productCode || "").toString());
    const section = escapeHtml((item?.sectionName || "Section").toString());
    const image = escapeHtml((item?.image || "").toString());
    const deletedAt = escapeHtml(formatDateTime(item?.deletedAt || ""));
    const purgeAfter = escapeHtml(formatDateTime(item?.purgeAfter || ""));
    const expiresInSeconds = Math.max(
      0,
      parseInt(item?.expiresInSeconds, 10) || 0,
    );
    const countdown = formatTrashCountdown(expiresInSeconds);
    const trashId = Number(item?.id || 0);
    const isExpired = expiresInSeconds <= 0;
    const isExpiringSoon = !isExpired && expiresInSeconds <= 86400;
    const statusLabel = isExpired
      ? "Expired"
      : isExpiringSoon
        ? "Expiring Soon"
        : "Safe Window";
    const statusClass = isExpired
      ? "is-expired"
      : isExpiringSoon
        ? "is-warning"
        : "is-safe";

    const imageCell = image
      ? `<img src="../../${image}" alt="${name}" class="rounded" width="44" height="44" style="object-fit:cover;">`
      : '<span class="badge bg-secondary">No media</span>';

    tbody.append(`
      <tr class="border-bottom border-secondary">
        <td class="ps-4 py-3 text-light">
          <div class="d-flex align-items-center gap-2">
            ${imageCell}
            <div>
              <div class="fw-semibold">${name}</div>
              <div class="text-secondary small">Code: ${code || "-"}</div>
            </div>
          </div>
        </td>
        <td class="py-3 text-secondary small">${section}</td>
        <td class="py-3 text-secondary small">${deletedAt}</td>
        <td class="py-3 text-secondary small">
          <div class="trash-countdown-time text-warning fw-semibold">${countdown}</div>
          <div class="trash-status-pill ${statusClass}">${statusLabel}</div>
          <div>${purgeAfter}</div>
        </td>
        <td class="pe-4 py-3">
          <div class="d-flex gap-1 flex-wrap">
            <button class="btn btn-sm btn-outline-success" type="button" title="Restore this item to catalog" onclick="restoreTrashProductById(${trashId})">Restore</button>
            <button class="btn btn-sm btn-outline-danger" type="button" title="Permanently delete this trash item" onclick="deleteTrashItemById(${trashId})">Delete</button>
          </div>
        </td>
      </tr>
    `);
  });
}

async function loadProductTrashData(silent = false) {
  if (!$("#productTrashTable").length) {
    return false;
  }

  try {
    const response = await fetch(
      `${ADMIN_PRODUCTS_SYNC_API}?action=get-trash`,
      {
        method: "GET",
        credentials: "same-origin",
        cache: "no-store",
      },
    );

    const result = await response.json();
    if (!response.ok || !result?.ok) {
      throw new Error(result?.message || "Unable to load product trash.");
    }

    hydrateProductTrashItems(result?.payload?.items || []);
    return true;
  } catch (error) {
    if (!silent) {
      showNotification(
        error?.message || "Unable to load product trash.",
        "danger",
      );
    }
    return false;
  }
}

async function restoreTrashProductById(trashId) {
  const safeId = parseInt(trashId, 10) || 0;
  if (safeId <= 0) {
    showNotification("Invalid trash item.", "warning");
    return;
  }

  const confirmed = await showCustomConfirmDialog(
    "Restore this product from trash?",
    "Restore Product",
  );
  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_PRODUCTS_SYNC_API, {
      action: "restore-trash-product",
      id: safeId,
    });

    if (result?.payload?.products) {
      hydrateProductsData(result.payload.products);
    }
    hydrateProductTrashItems(result?.payload?.trash?.items || []);
    showNotification(
      result?.message || "Product restored from trash.",
      "success",
    );
  } catch (error) {
    showNotification(error?.message || "Unable to restore product.", "danger");
  }
}

async function deleteTrashItemById(trashId) {
  const safeId = parseInt(trashId, 10) || 0;
  if (safeId <= 0) {
    showNotification("Invalid trash item.", "warning");
    return;
  }

  const confirmed = await showCustomConfirmDialog(
    "Permanently delete this trash item? This cannot be undone.",
    "Permanent Delete",
  );
  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_PRODUCTS_SYNC_API, {
      action: "delete-trash-item",
      id: safeId,
    });

    hydrateProductTrashItems(result?.payload?.trash?.items || []);
    showNotification(
      result?.message || "Trash item permanently deleted.",
      "success",
    );
  } catch (error) {
    showNotification(
      error?.message || "Unable to delete trash item.",
      "danger",
    );
  }
}

async function emptyProductTrash(mode = "all") {
  const safeMode = mode === "expired" ? "expired" : "all";
  const confirmed = await showCustomConfirmDialog(
    safeMode === "expired"
      ? "Delete only expired trash items now?"
      : "Permanently delete every trashed product now?",
    safeMode === "expired" ? "Empty Expired Trash" : "Empty All Trash",
  );
  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_PRODUCTS_SYNC_API, {
      action: "empty-trash",
      mode: safeMode,
    });

    hydrateProductTrashItems(result?.payload?.trash?.items || []);
    showNotification(
      result?.message || "Trash cleaned successfully.",
      "success",
    );
  } catch (error) {
    showNotification(error?.message || "Unable to empty trash.", "danger");
  }
}

function buildProductsPayload() {
  return {
    meta: {
      total: productsData.length,
      currency: "PKR",
      lastUpdated: new Date().toISOString().split("T")[0],
    },
    sections: allSections.map((section) => ({
      ...section,
      products: productsData.filter((p) => p.sectionId === section.sectionId),
    })),
  };
}

function saveProductsToJSON() {
  const dataToSave = buildProductsPayload();

  return adminPostJson(ADMIN_PRODUCTS_SYNC_API, {
    action: "save-products",
    sections: dataToSave.sections,
  })
    .then((result) => {
      if (result?.payload) {
        hydrateProductsData(result.payload);
      }
      return result;
    })
    .catch((error) => {
      console.error("Error syncing products to backend:", error);
      showNotification(
        error?.message || "Could not sync products to backend.",
        "danger",
      );
      return null;
    });
}

function buildDefaultProductCode(productId) {
  const numericId = Math.max(0, parseInt(productId, 10) || 0);
  if (numericId > 0) {
    return `CMRZ-${String(numericId).padStart(5, "0")}`;
  }
  return "CMRZ-NEW";
}

function normalizeProductCodeInput(rawValue, fallbackId) {
  const normalized = (rawValue || "")
    .toString()
    .toUpperCase()
    .trim()
    .replace(/[^A-Z0-9-]+/g, "-")
    .replace(/-+/g, "-")
    .replace(/^-+|-+$/g, "");

  if (normalized) {
    return normalized.slice(0, 40);
  }

  return buildDefaultProductCode(fallbackId).slice(0, 40);
}

function normalizeProductMetaInput(rawValue, fallbackValue, maxLength = 120) {
  const cleaned = (rawValue || "").toString().trim();
  const value = cleaned || fallbackValue;
  return value.slice(0, maxLength);
}

function renderProductsTable() {
  const filterSection = window.currentSectionFilter || "";
  const tbody = $("#productsTable tbody");
  tbody.empty();

  let filteredProducts = productsData;
  if (filterSection) {
    filteredProducts = productsData.filter(
      (p) => p.sectionId === filterSection,
    );
  }

  $("#productCount").text(filteredProducts.length);

  if (filteredProducts.length === 0) {
    tbody.append(
      '<tr><td colspan="6" class="text-center py-4 text-secondary">No products found</td></tr>',
    );
    return;
  }

  filteredProducts.forEach((product) => {
    const numericPrice = Number(product.price) || 0;
    const numericSalePrice = Number(product.salePrice) || 0;
    const effectiveSale =
      numericSalePrice > 0 ? numericSalePrice : numericPrice;
    const productCode = escapeHtml(
      (product.productCode || buildDefaultProductCode(product.id)).toString(),
    );
    const warrantyInfo = escapeHtml(
      (product.warrantyInfo || "12-month seller warranty").toString(),
    );
    const dispatchInfo = escapeHtml(
      (
        product.dispatchInfo ||
        (Number(product.stock) > 0
          ? "Dispatch in 24-48 hours"
          : "Pre-order availability")
      ).toString(),
    );
    const safeName = escapeHtml(
      (product.name || "Untitled Product").toString(),
    );
    const safeSectionName = escapeHtml(
      (product.sectionName || "Section").toString(),
    );
    const safeCategory = escapeHtml(
      (product.category || "Uncategorized").toString(),
    );
    const safeImage = escapeHtml((product.image || "").toString());

    const stock =
      product.stock > 10
        ? `<span class="badge bg-success rounded-pill">In Stock (${product.stock})</span>`
        : `<span class="badge bg-warning text-dark rounded-pill">Low (${product.stock})</span>`;
    tbody.append(`
            <tr class="border-bottom border-secondary">
                <td class="ps-4 py-3"><img src="../../${safeImage}" alt="${safeName}" class="rounded" width="50" height="50" style="object-fit: cover; cursor: pointer;" onerror="this.src='assets/images/products/placeholder.webp'"></td>
                <td class="py-3 text-light fw-semibold" style="max-width: 260px;">
                  <div>${safeName}</div>
                  <div class="text-secondary small mt-1">Code: <span class="text-orange">${productCode}</span></div>
                  <div class="text-secondary small">Warranty: ${warrantyInfo}</div>
                  <div class="text-secondary small">Dispatch: ${dispatchInfo}</div>
                </td>
                <td class="py-3 text-secondary small"><span class="d-block text-warning">${safeSectionName}</span><span class="text-secondary">${safeCategory}</span></td>
                <td class="py-3 text-light fw-semibold"><span class="text-secondary" style="text-decoration: line-through; font-size: 0.9rem;">${formatPkr(numericPrice)}</span><span class="ms-2 text-orange">${formatPkr(effectiveSale)}</span></td>
                <td class="py-3">${stock}</td>
                <td class="pe-4 py-3"><button class="btn btn-sm btn-outline-orange me-1" onclick="editProduct(${product.id})" title="Edit"><i class="bi bi-pencil"></i></button><button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(${product.id})" title="Delete"><i class="bi bi-trash"></i></button></td>
            </tr>
        `);
  });
}

function editProduct(id) {
  const product = productsData.find((p) => p.id === id);
  if (product) {
    $("#productId").val(product.id);
    $("#productName").val(product.name);
    $("#productPrice").val(product.price);
    $("#productSalePrice").val(product.salePrice);
    $("#productStock").val(product.stock);

    const sectionName =
      allSections.find((s) => s.sectionId === product.sectionId)?.sectionName ||
      "Select Section";
    $("#productSectionBtn").text(sectionName);
    $("#productSection").val(product.sectionId);

    const movementType = product.movement || "quartz";
    const movementDisplay =
      movementType === "quartz"
        ? "Quartz"
        : movementType === "auto"
          ? "Automatic"
          : "Smart";
    $("#productMovementBtn").text(movementDisplay);
    $("#productMovement").val(movementType);

    $("#productImage").val(product.image);
    $("#productVideo").val(product.video || "");
    $("#productCode").val(
      product.productCode || buildDefaultProductCode(product.id),
    );
    $("#productWarrantyInfo").val(
      product.warrantyInfo || "12-month seller warranty",
    );
    $("#productDispatchInfo").val(
      product.dispatchInfo ||
        (Number(product.stock) > 0
          ? "Dispatch in 24-48 hours"
          : "Pre-order availability"),
    );
    $("#productDescription").val(product.description);
    $("#productModalLabel").text("Edit Product");
    new bootstrap.Modal(document.getElementById("productModal")).show();
  }
}

async function deleteProduct(id) {
  const confirmed = await showCustomConfirmDialog(
    "Move this product to trash? It will auto-delete after 7 days.",
    "Move To Trash",
  );
  if (!confirmed) {
    return;
  }

  const index = productsData.findIndex((p) => p.id === id);
  if (index > -1) {
    const name = productsData[index].name;
    productsData.splice(index, 1);
    saveProductsToJSON();
    renderProductsTable();
    calculateDashboardMetrics();
    updateNotifications();
    showNotification(
      `"${name}" moved to trash (7-day restore window).`,
      "success",
    );
  }
}

function filterBySection(sectionId, sectionName) {
  $("#sectionFilterBtn").text(sectionName);
  window.currentSectionFilter = sectionId;
  renderProductsTable();
}

function selectProductSection(sectionId, sectionName) {
  $("#productSectionBtn").text(sectionName);
  $("#productSection").val(sectionId);
}

function renderSectionDropdowns() {
  const filterMenu = $("#sectionFilterMenu");
  const productMenu = $("#productSectionMenu");

  if (filterMenu.length) {
    filterMenu.empty();
    filterMenu.append(
      '<li><a class="dropdown-item text-light" href="#" onclick="filterBySection(\'\', \'All Sections\'); return false;">All Sections</a></li>',
    );
    filterMenu.append(
      '<li><hr class="dropdown-divider border-secondary"></li>',
    );
    allSections.forEach((section) => {
      filterMenu.append(
        `<li><a class="dropdown-item text-light" href="#" onclick="filterBySection('${section.sectionId}', '${section.sectionName}'); return false;">${section.sectionName}</a></li>`,
      );
    });
  }

  if (productMenu.length) {
    productMenu.empty();
    productMenu.append(
      '<li><a class="dropdown-item text-light" href="#" onclick="selectProductSection(\'\', \'Select Section\'); return false;">Select Section</a></li>',
    );
    productMenu.append(
      '<li><hr class="dropdown-divider border-secondary"></li>',
    );
    allSections.forEach((section) => {
      productMenu.append(
        `<li><a class="dropdown-item text-light" href="#" onclick="selectProductSection('${section.sectionId}', '${section.sectionName}'); return false;">${section.sectionName}</a></li>`,
      );
    });
  }
}

function renderSectionsTable() {
  const tbody = $("#sectionsTable tbody");
  if (!tbody.length) return;

  tbody.empty();

  if (!allSections.length) {
    tbody.append(
      '<tr><td colspan="4" class="text-center py-4 text-secondary">No sections created</td></tr>',
    );
    return;
  }

  allSections.forEach((section) => {
    tbody.append(`
            <tr class="border-bottom border-secondary">
                <td class="ps-4 py-3 text-light fw-semibold">${section.sectionName}<div class="text-secondary small">${section.sectionId}</div></td>
                <td class="py-3 text-secondary small">${section.page || "N/A"}</td>
                <td class="py-3 text-secondary small">${section.category || "Uncategorized"}<div class="text-secondary">${section.subcategory || ""}</div></td>
                <td class="pe-4 py-3">
                    <button class="btn btn-sm btn-outline-orange me-1" onclick="editSection('${section.sectionId}')"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteSection('${section.sectionId}')"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
        `);
  });
}

function slugifySection(name) {
  return (name || "")
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/(^-|-$)/g, "");
}

function ensureUniqueSectionId(baseId) {
  let id = baseId || `section-${nextSectionId}`;
  let counter = 2;
  while (allSections.some((section) => section.sectionId === id)) {
    id = `${baseId}-${counter++}`;
  }
  return id;
}

function editSection(sectionId) {
  const section = allSections.find((item) => item.sectionId === sectionId);
  if (!section) return;
  $("#sectionFormId").val(section.sectionId);
  $("#sectionName").val(section.sectionName || "");
  $("#sectionId").val(section.sectionId || "");
  $("#sectionPage").val(section.page || "");
  $("#sectionCategory").val(section.category || "");
  $("#sectionSubcategory").val(section.subcategory || "");
  $("#saveSectionBtn").html('<i class="bi bi-save2 me-1"></i>Update Section');
}

async function deleteSection(sectionId) {
  const confirmed = await showCustomConfirmDialog(
    "Move this section and all its products to trash?",
    "Move Section To Trash",
  );
  if (!confirmed) return;

  allSections = allSections.filter(
    (section) => section.sectionId !== sectionId,
  );
  productsData = productsData.filter(
    (product) => product.sectionId !== sectionId,
  );
  if (window.currentSectionFilter === sectionId) {
    window.currentSectionFilter = "";
    $("#sectionFilterBtn").text("All Sections");
  }
  saveProductsToJSON();
  renderSectionDropdowns();
  renderSectionsTable();
  renderProductsTable();
  calculateDashboardMetrics();
  updateNotifications();
  resetSectionForm();
  showNotification("Section deleted!", "success");
}

function resetSectionForm() {
  $("#sectionFormId").val("");
  $("#sectionName").val("");
  $("#sectionId").val("");
  $("#sectionPage").val("");
  $("#sectionCategory").val("");
  $("#sectionSubcategory").val("");
  $("#saveSectionBtn").html(
    '<i class="bi bi-plus-circle me-1"></i>Add Section',
  );
}

function selectProductMovement(movementId, movementName) {
  $("#productMovementBtn").text(movementName);
  $("#productMovement").val(movementId);
}

function exportProductsData() {
  if (productsData.length === 0) {
    showNotification("No products to export", "warning");
    return;
  }
  const dataToExport = {
    meta: {
      total: productsData.length,
      currency: "PKR",
      exportedDate: new Date().toISOString().split("T")[0],
      exportedTime: new Date().toLocaleTimeString(),
    },
    sections: allSections.map((section) => ({
      ...section,
      products: productsData.filter((p) => p.sectionId === section.sectionId),
    })),
  };
  const blob = new Blob([JSON.stringify(dataToExport, null, 2)], {
    type: "application/json",
  });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = `products-export-${new Date().toISOString().split("T")[0]}.json`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  showNotification("Products exported!", "success");
}

function exportProductsAsCSV() {
  if (productsData.length === 0) {
    showNotification("No products to export", "warning");
    return;
  }
  const headers = [
    "ID",
    "Name",
    "Product Code",
    "Section",
    "Category",
    "Price",
    "Sale Price",
    "Stock",
    "Movement",
    "Warranty",
    "Dispatch",
    "Description",
  ];
  const rows = productsData.map((p) => [
    p.id,
    `"${p.name}"`,
    `"${p.productCode || ""}"`,
    p.sectionName,
    p.category,
    p.price,
    p.salePrice,
    p.stock,
    p.movement,
    `"${p.warrantyInfo || ""}"`,
    `"${p.dispatchInfo || ""}"`,
    `"${p.description}"`,
  ]);
  const csvContent = [headers.join(","), ...rows.map((r) => r.join(","))].join(
    "\n",
  );
  const blob = new Blob([csvContent], { type: "text/csv" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = `products-export-${new Date().toISOString().split("T")[0]}.csv`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  showNotification("CSV exported!", "success");
}

function downloadSampleProductsCSV() {
  const headers = [
    "section_id",
    "section_name",
    "page",
    "category",
    "subcategory",
    "name",
    "description",
    "image",
    "video",
    "product_code",
    "warranty_info",
    "dispatch_info",
    "price",
    "sale_price",
    "stock",
    "movement",
  ];

  const rows = [
    [
      "featured-collection",
      "Featured Collection",
      "index.php",
      "Premium Watches",
      "Luxury",
      "Aurora Black Steel",
      "Elegant black steel watch with premium finish.",
      "frontend/assets/images/products/featured/aurora-black-steel.webp",
      "",
      "CMRZ-00041",
      "12-month seller warranty",
      "Dispatch in 24-48 hours",
      "12900",
      "11499",
      "17",
      "quartz",
    ],
    [
      "sports-division",
      "Sports & Sales Division",
      "shop-category-b.php",
      "Sports Watches",
      "Performance",
      "Runner Tactical Pro",
      "Durable sports watch built for active daily use.",
      "frontend/assets/images/products/sports/runner-tactical-pro.webp",
      "",
      "CMRZ-00042",
      "18-month seller warranty",
      "Dispatch in 24-48 hours",
      "9900",
      "8500",
      "22",
      "smart",
    ],
  ];

  const csv = [headers, ...rows]
    .map((row) =>
      row
        .map((value) => `"${String(value || "").replace(/"/g, '""')}"`)
        .join(","),
    )
    .join("\n");

  const blob = new Blob([csv], { type: "text/csv" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = "commerza-products-sample.csv";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  showNotification("Sample CSV downloaded.", "success");
}

function parseCsvRecords(csvText) {
  const records = [];
  let row = [];
  let value = "";
  let inQuotes = false;

  for (let i = 0; i < csvText.length; i += 1) {
    const char = csvText[i];
    const next = csvText[i + 1];

    if (char === '"') {
      if (inQuotes && next === '"') {
        value += '"';
        i += 1;
      } else {
        inQuotes = !inQuotes;
      }
      continue;
    }

    if (char === "," && !inQuotes) {
      row.push(value);
      value = "";
      continue;
    }

    if ((char === "\n" || char === "\r") && !inQuotes) {
      if (char === "\r" && next === "\n") {
        i += 1;
      }

      row.push(value);
      if (row.some((cell) => String(cell || "").trim() !== "")) {
        records.push(row);
      }
      row = [];
      value = "";
      continue;
    }

    value += char;
  }

  row.push(value);
  if (row.some((cell) => String(cell || "").trim() !== "")) {
    records.push(row);
  }

  return records;
}

function normalizeImportKey(value) {
  return (value || "")
    .toString()
    .toLowerCase()
    .replace(/[^a-z0-9]/g, "");
}

function parseCsvObjects(csvText) {
  const records = parseCsvRecords(csvText || "");
  if (records.length < 2) {
    return [];
  }

  const headers = records[0].map((header) => normalizeImportKey(header));
  const rows = [];

  for (let i = 1; i < records.length; i += 1) {
    const record = records[i];
    const row = {};
    headers.forEach((header, index) => {
      if (!header) {
        return;
      }

      row[header] = (record[index] || "").toString().trim();
    });

    rows.push(row);
  }

  return rows;
}

function readImportField(row, aliases) {
  for (const alias of aliases) {
    const key = normalizeImportKey(alias);
    const value = (row?.[key] || "").toString().trim();
    if (value !== "") {
      return value;
    }
  }

  return "";
}

function normalizeImportMovement(value) {
  const normalized = (value || "").toString().trim().toLowerCase();
  if (normalized === "auto" || normalized === "automatic") {
    return "auto";
  }
  if (normalized === "smart" || normalized === "digital") {
    return "smart";
  }
  return "quartz";
}

function buildCatalogFromCsvRows(rows) {
  const sectionMap = new Map();
  const usedSectionIds = new Set();
  const products = [];
  let productIdSeed = 1;

  rows.forEach((row) => {
    const productName = readImportField(row, ["name", "product_name"]);
    if (!productName) {
      return;
    }

    const rawSectionName = readImportField(row, ["section_name", "section"]);
    const rawSectionId = readImportField(row, ["section_id", "sectionid"]);
    const sectionName = rawSectionName || "Imported Section";
    const sectionIdBase = slugifySection(
      rawSectionId || sectionName || "imported",
    );
    let sectionId = sectionIdBase || "imported";
    let suffix = 2;

    while (!sectionMap.has(sectionId) && usedSectionIds.has(sectionId)) {
      sectionId = `${sectionIdBase || "imported"}-${suffix++}`;
    }

    if (!sectionMap.has(sectionId)) {
      sectionMap.set(sectionId, {
        sectionId,
        sectionName,
        page: readImportField(row, ["page"]) || "index.php",
        category: readImportField(row, ["category"]) || "Imported Products",
        subcategory: readImportField(row, ["subcategory"]),
      });
      usedSectionIds.add(sectionId);
    }

    const stock = Math.max(
      0,
      parseInt(readImportField(row, ["stock"]), 10) || 0,
    );
    const productId = productIdSeed++;
    const basePrice = Number.parseFloat(readImportField(row, ["price"])) || 0;
    const salePriceRaw = Number.parseFloat(
      readImportField(row, ["sale_price", "saleprice"]),
    );
    const salePrice = Number.isFinite(salePriceRaw) ? salePriceRaw : basePrice;

    products.push({
      id: productId,
      sectionId,
      sectionName: sectionMap.get(sectionId)?.sectionName || sectionName,
      page: sectionMap.get(sectionId)?.page || "index.php",
      category: sectionMap.get(sectionId)?.category || "Imported Products",
      subcategory: sectionMap.get(sectionId)?.subcategory || "",
      name: productName,
      description: readImportField(row, ["description"]) || "Imported product",
      image:
        readImportField(row, ["image", "image_url"]) ||
        "frontend/assets/images/products/placeholder.webp",
      video: readImportField(row, ["video", "video_url"]),
      productCode: normalizeProductCodeInput(
        readImportField(row, ["product_code", "productcode", "code"]),
        productId,
      ),
      warrantyInfo: normalizeProductMetaInput(
        readImportField(row, ["warranty_info", "warranty"]),
        "12-month seller warranty",
        120,
      ),
      dispatchInfo: normalizeProductMetaInput(
        readImportField(row, ["dispatch_info", "dispatch"]),
        stock > 0 ? "Dispatch in 24-48 hours" : "Pre-order availability",
        120,
      ),
      price: Math.max(0, basePrice),
      salePrice: Math.max(0, salePrice),
      stock,
      movement: normalizeImportMovement(readImportField(row, ["movement"])),
      createdAt: new Date().toISOString().slice(0, 10),
    });
  });

  if (!products.length) {
    throw new Error("The uploaded CSV has no valid product rows.");
  }

  return {
    sections: Array.from(sectionMap.values()),
    products,
  };
}

function buildCatalogFromJsonPayload(rawData) {
  const inputSections = Array.isArray(rawData?.sections)
    ? rawData.sections
    : Array.isArray(rawData)
      ? rawData
      : [];

  if (!inputSections.length) {
    throw new Error("JSON must contain a sections array with products.");
  }

  const sectionMap = new Map();
  const usedSectionIds = new Set();
  const products = [];
  let productIdSeed = 1;
  const usedProductIds = new Set();

  inputSections.forEach((section) => {
    const sectionName = (
      section?.sectionName ||
      section?.name ||
      "Imported Section"
    )
      .toString()
      .trim();
    const sectionIdBase = slugifySection(
      (section?.sectionId || section?.id || sectionName || "imported")
        .toString()
        .trim(),
    );

    let sectionId = sectionIdBase || "imported";
    let suffix = 2;
    while (!sectionMap.has(sectionId) && usedSectionIds.has(sectionId)) {
      sectionId = `${sectionIdBase || "imported"}-${suffix++}`;
    }

    sectionMap.set(sectionId, {
      sectionId,
      sectionName: sectionName || "Imported Section",
      page: (section?.page || "index.php").toString().trim() || "index.php",
      category: (section?.category || "Imported Products").toString().trim(),
      subcategory: (section?.subcategory || "").toString().trim(),
    });
    usedSectionIds.add(sectionId);

    const sectionProducts = Array.isArray(section?.products)
      ? section.products
      : [];

    sectionProducts.forEach((product) => {
      const name = (product?.name || "").toString().trim();
      if (!name) {
        return;
      }

      const providedId = parseInt(product?.id, 10);
      let productId =
        Number.isInteger(providedId) && providedId > 0
          ? providedId
          : productIdSeed;

      while (usedProductIds.has(productId)) {
        productId += 1;
      }

      usedProductIds.add(productId);
      productIdSeed = Math.max(productIdSeed + 1, productId + 1);

      const stock = Math.max(0, parseInt(product?.stock, 10) || 0);
      const rawPrice = Number.parseFloat(product?.price);
      const basePrice = Number.isFinite(rawPrice) ? Math.max(0, rawPrice) : 0;
      const rawSale = Number.parseFloat(product?.salePrice);
      const salePrice = Number.isFinite(rawSale)
        ? Math.max(0, rawSale)
        : basePrice;

      products.push({
        id: productId,
        sectionId,
        sectionName:
          sectionMap.get(sectionId)?.sectionName || "Imported Section",
        page: sectionMap.get(sectionId)?.page || "index.php",
        category: sectionMap.get(sectionId)?.category || "Imported Products",
        subcategory: sectionMap.get(sectionId)?.subcategory || "",
        name,
        description: (product?.description || "Imported product").toString(),
        image:
          (product?.image || "").toString().trim() ||
          "frontend/assets/images/products/placeholder.webp",
        video: (product?.video || "").toString().trim(),
        productCode: normalizeProductCodeInput(
          (product?.productCode || product?.product_code || "").toString(),
          productId,
        ),
        warrantyInfo: normalizeProductMetaInput(
          (product?.warrantyInfo || product?.warranty_info || "").toString(),
          "12-month seller warranty",
          120,
        ),
        dispatchInfo: normalizeProductMetaInput(
          (product?.dispatchInfo || product?.dispatch_info || "").toString(),
          stock > 0 ? "Dispatch in 24-48 hours" : "Pre-order availability",
          120,
        ),
        price: basePrice,
        salePrice,
        stock,
        movement: normalizeImportMovement(product?.movement || "quartz"),
        createdAt: new Date().toISOString().slice(0, 10),
      });
    });
  });

  if (!products.length) {
    throw new Error("JSON import has no valid products.");
  }

  return {
    sections: Array.from(sectionMap.values()),
    products,
  };
}

async function applyImportedCatalogData(catalog, onProgress = null) {
  const importedSections = Array.isArray(catalog?.sections)
    ? catalog.sections
    : [];
  const importedProducts = Array.isArray(catalog?.products)
    ? catalog.products
    : [];

  if (!importedSections.length || !importedProducts.length) {
    throw new Error("Imported file has no usable sections/products.");
  }

  const totalSteps = importedSections.length + importedProducts.length;
  let importProgressStep = 0;
  const reportProgress = (label) => {
    if (typeof onProgress !== "function") {
      return;
    }

    importProgressStep += 1;
    onProgress({
      step: importProgressStep,
      total: Math.max(totalSteps, 1),
      label: (label || "Processing").toString(),
    });
  };

  allSections = [];
  const sectionMap = new Map();
  importedSections.forEach((section, index) => {
    const normalized = {
      sectionName: (section?.sectionName || `Section ${index + 1}`).toString(),
      sectionId: (section?.sectionId || `section-${index + 1}`).toString(),
      page: (section?.page || "index.php").toString(),
      category: (section?.category || "Uncategorized").toString(),
      subcategory: (section?.subcategory || "General").toString(),
      products: [],
    };

    allSections.push(normalized);
    sectionMap.set(normalized.sectionId, normalized);
    reportProgress(`Section: ${normalized.sectionName}`);
  });

  productsData = [];
  const usedProductIds = new Set();
  let productIdSeed = 1;

  for (let index = 0; index < importedProducts.length; index += 1) {
    const product = importedProducts[index] || {};
    const preferredSectionId = (product?.sectionId || "").toString();
    const section =
      sectionMap.get(preferredSectionId) ||
      allSections.find((entry) => entry.sectionId === preferredSectionId) ||
      allSections[0];

    let productId = parseInt(product?.id, 10);
    if (!Number.isInteger(productId) || productId <= 0) {
      productId = productIdSeed;
    }

    while (usedProductIds.has(productId)) {
      productId += 1;
    }

    usedProductIds.add(productId);
    productIdSeed = Math.max(productIdSeed + 1, productId + 1);

    productsData.push({
      ...product,
      id: productId,
      sectionId: section?.sectionId || preferredSectionId,
      sectionName: section?.sectionName || product?.sectionName || "Section",
      page: section?.page || product?.page || "index.php",
      category: section?.category || product?.category || "Uncategorized",
      subcategory: section?.subcategory || product?.subcategory || "General",
    });

    reportProgress(
      `Product: ${(product?.name || `Item ${index + 1}`).toString()}`,
    );

    if (index % 20 === 0) {
      // Yield so the UI can refresh progress text for large imports.
      // eslint-disable-next-line no-await-in-loop
      await new Promise((resolve) => setTimeout(resolve, 0));
    }
  }

  nextId = productsData.length
    ? Math.max(...productsData.map((p) => parseInt(p?.id, 10) || 0)) + 1
    : 1;
  nextSectionId = allSections.length + 1;
  window.currentSectionFilter = "";

  renderSectionDropdowns();
  renderSectionsTable();
  renderProductsTable();
  calculateDashboardMetrics();
  updateNotifications();
}

async function importProductsFromFileInput() {
  const input = document.getElementById("bulkProductsFile");
  if (!input || !input.files || input.files.length === 0) {
    showNotification("Please choose a CSV or JSON file first.", "warning");
    return;
  }

  const file = input.files[0];
  const confirmed = await showCustomConfirmDialog(
    "Import will replace current product sections and products. Continue?",
    "Confirm Bulk Import",
  );
  if (!confirmed) {
    return;
  }

  const importBtn = $("#bulkProductsImportBtn");
  const progressEl = $("#bulkImportProgress");
  const originalBtnHtml = importBtn.html();
  const setImportProgress = (message, toneClass = "text-secondary") => {
    if (!progressEl.length) {
      return;
    }
    progressEl
      .removeClass(
        "text-secondary text-warning text-success text-danger text-info",
      )
      .addClass(toneClass)
      .text((message || "").toString());
  };

  importBtn
    .prop("disabled", true)
    .html(
      '<span class="spinner-border spinner-border-sm"></span> Importing...',
    );
  setImportProgress("Reading import file...", "text-warning");

  try {
    const text = await new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve((reader.result || "").toString());
      reader.onerror = () => reject(new Error("Unable to read uploaded file."));
      reader.readAsText(file);
    });

    const fileName = (file?.name || "").toLowerCase();
    const isJson = fileName.endsWith(".json") || file.type.includes("json");
    let catalog = null;

    if (isJson) {
      setImportProgress("Parsing JSON payload...", "text-info");
      const parsed = JSON.parse(text || "{}");
      catalog = buildCatalogFromJsonPayload(parsed);
    } else {
      setImportProgress("Parsing CSV rows one-by-one...", "text-info");
      const rows = parseCsvObjects(text || "");
      catalog = buildCatalogFromCsvRows(rows);
    }

    await applyImportedCatalogData(catalog, ({ step, total, label }) => {
      setImportProgress(`Processing ${step}/${total} | ${label}`, "text-info");
    });

    setImportProgress("Syncing imported catalog to server...", "text-warning");
    const syncResult = await saveProductsToJSON();
    if (!syncResult?.ok) {
      throw new Error("Imported rows were prepared but server sync failed.");
    }

    input.value = "";
    const importedCount = Array.isArray(catalog?.products)
      ? catalog.products.length
      : 0;
    setImportProgress(
      `Import complete. ${importedCount} product(s) processed one-by-one.`,
      "text-success",
    );
    showNotification("Bulk upload completed successfully.", "success");
  } catch (error) {
    setImportProgress(
      error?.message || "Import failed. Check your file and retry.",
      "text-danger",
    );
    showNotification(
      error?.message || "Unable to import products file.",
      "danger",
    );
  } finally {
    importBtn.prop("disabled", false).html(originalBtnHtml);
  }
}

function showNotification(message, type) {
  const alertClass =
    type === "success"
      ? "alert-success"
      : type === "danger"
        ? "alert-danger"
        : "alert-warning";
  $("body").append(`
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert" style="position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `);
  setTimeout(
    () =>
      $(".alert").fadeOut("slow", function () {
        $(this).remove();
      }),
    3000,
  );
}

function showAdminDialog(options = {}) {
  const modalEl = document.getElementById("adminDialogModal");
  if (!modalEl || typeof bootstrap === "undefined") {
    if (options.type === "prompt") {
      return Promise.resolve(null);
    }
    return Promise.resolve(false);
  }

  const titleEl = document.getElementById("adminDialogTitle");
  const messageEl = document.getElementById("adminDialogMessage");
  const inputWrap = document.getElementById("adminDialogInputWrap");
  const inputEl = document.getElementById("adminDialogInput");
  const cancelBtn = document.getElementById("adminDialogCancelBtn");
  const okBtn = document.getElementById("adminDialogOkBtn");

  if (
    !titleEl ||
    !messageEl ||
    !inputWrap ||
    !inputEl ||
    !cancelBtn ||
    !okBtn
  ) {
    if (options.type === "prompt") {
      return Promise.resolve(null);
    }
    return Promise.resolve(false);
  }

  const dialogType = options.type || "confirm";
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl, {
    backdrop: "static",
    keyboard: false,
  });

  titleEl.textContent = (options.title || "Confirm Action").toString();
  messageEl.textContent = (options.message || "Are you sure?").toString();
  okBtn.textContent = (options.confirmText || "Continue").toString();
  cancelBtn.textContent = (options.cancelText || "Cancel").toString();

  if (dialogType === "prompt") {
    inputWrap.classList.remove("d-none");
    inputEl.value = (options.defaultValue || "").toString();
  } else {
    inputWrap.classList.add("d-none");
    inputEl.value = "";
  }

  if (dialogType === "alert") {
    cancelBtn.classList.add("d-none");
  } else {
    cancelBtn.classList.remove("d-none");
  }

  return new Promise((resolve) => {
    let settled = false;

    const cleanup = () => {
      okBtn.removeEventListener("click", onConfirm);
      cancelBtn.removeEventListener("click", onCancel);
      modalEl.removeEventListener("hidden.bs.modal", onHidden);
    };

    const finish = (value) => {
      if (settled) return;
      settled = true;
      cleanup();
      resolve(value);
    };

    const onConfirm = () => {
      const value = dialogType === "prompt" ? inputEl.value : true;
      finish(value);
      modal.hide();
    };

    const onCancel = () => {
      if (dialogType === "prompt") {
        finish(null);
      } else {
        finish(false);
      }
      modal.hide();
    };

    const onHidden = () => {
      if (settled) return;
      if (dialogType === "prompt") {
        finish(null);
      } else {
        finish(false);
      }
    };

    okBtn.addEventListener("click", onConfirm);
    cancelBtn.addEventListener("click", onCancel);
    modalEl.addEventListener("hidden.bs.modal", onHidden);

    modal.show();
    if (dialogType === "prompt") {
      setTimeout(() => inputEl.focus(), 100);
    }
  });
}

function showCustomConfirmDialog(message, title = "Confirm Action") {
  return showAdminDialog({
    type: "confirm",
    title,
    message,
    confirmText: "Yes, Continue",
    cancelText: "Cancel",
  });
}

function showCustomPromptDialog(
  message,
  defaultValue = "",
  title = "Add Note",
) {
  return showAdminDialog({
    type: "prompt",
    title,
    message,
    defaultValue,
    confirmText: "Save",
    cancelText: "Skip",
  });
}

function showStatusNotification(orderId, oldStatus, newStatus) {
  const notif = $(
    `<div class="alert alert-info alert-dismissible fade show" role="alert" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999; min-width: 380px; background-color: #1a3a4a; border: 2px solid #0d6efd; border-radius: 8px; padding: 30px; box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);"><div style="color: #fff; text-align: center;"><i class="bi bi-arrow-left-right" style="font-size: 2rem; color: #0d6efd; display: block; margin-bottom: 10px;"></i><h5 style="margin: 10px 0; color: #0d6efd;">Order Status Updated!</h5><p style="margin: 5px 0; font-size: 0.95rem;">Order: <strong>${orderId}</strong></p><p style="margin: 10px 0; font-size: 0.9rem; color: #b0b0b0;"><span style="background-color: #332a1a; padding: 4px 8px; border-radius: 4px;">${oldStatus}</span><i class="bi bi-arrow-right" style="margin: 0 8px; color: #0d6efd;"></i><span style="background-color: #1a332a; padding: 4px 8px; border-radius: 4px;">${newStatus}</span></p></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button></div>`,
  );
  $("body").append(notif);
  setTimeout(() => notif.remove(), 4000);
}

function openNotificationTarget(tabId) {
  const id = (tabId || "").toString().trim();
  if (!id) return;

  const tab = document.getElementById(id);
  if (tab && typeof tab.click === "function") {
    tab.click();
  }
}

function notificationSignature(notification) {
  const text = (notification?.text || "").toString().trim().toLowerCase();
  const icon = (notification?.icon || "").toString().trim().toLowerCase();
  const type = (notification?.type || "").toString().trim().toLowerCase();
  const targetTab = (notification?.targetTab || "")
    .toString()
    .trim()
    .toLowerCase();
  return `${type}|${icon}|${targetTab}|${text}`;
}

let lastRenderedNotificationSignatures = [];

function clearAdminNotifications(minutes = 45) {
  void minutes;

  if (!Array.isArray(lastRenderedNotificationSignatures)) {
    lastRenderedNotificationSignatures = [];
  }

  lastRenderedNotificationSignatures.forEach((signature) => {
    const safeSignature = (signature || "").toString().trim();
    if (safeSignature !== "") {
      dismissedNotificationSignatures.add(safeSignature);
    }
  });

  saveDismissedNotificationSignatures();
  updateNotifications();
}

function resumeAdminNotifications() {
  notificationsPausedUntil = 0;
  dismissedNotificationSignatures = new Set();
  saveDismissedNotificationSignatures();
  updateNotifications();
}

window.openNotificationTarget = openNotificationTarget;
window.clearAdminNotifications = clearAdminNotifications;
window.resumeAdminNotifications = resumeAdminNotifications;

function updateNotifications() {
  const list = $("#notificationList");
  const count = $("#notificationCount");
  if (!list.length || !count.length) return;

  const notifications = [];
  const orders = getAdminOrdersData();

  const recentOrders = orders.filter((order) =>
    isWithinDays(order.orderDate, NOTIFICATION_RULES.recentOrderDays),
  );
  if (recentOrders.length > 0) {
    const latestOrder = [...recentOrders].sort(
      (a, b) => new Date(b.orderDate) - new Date(a.orderDate),
    )[0];
    if (latestOrder && latestOrder.orderId) {
      notifications.push({
        text: `New order ${latestOrder.orderId} (last ${NOTIFICATION_RULES.recentOrderDays} days)`,
        icon: "bi-receipt",
        type: "info",
        targetTab: "orders-tab",
      });
    }
  }

  const pendingOrders = orders.filter((order) => {
    const status = (order.status || "").toLowerCase();
    return (
      isWithinDays(order.orderDate, NOTIFICATION_RULES.pendingOrderDays) &&
      (status === "pending" || status === "processing")
    );
  });
  if (pendingOrders.length > 0) {
    notifications.push({
      text: `${pendingOrders.length} order(s) waiting for action (last ${NOTIFICATION_RULES.pendingOrderDays} days)`,
      icon: "bi-clock-history",
      type: "warning",
      targetTab: "orders-tab",
    });
  }

  const newCustomers = getAdminCustomersData().filter((customer) =>
    isWithinDays(customer.registeredAt, NOTIFICATION_RULES.newCustomerDays),
  );
  if (newCustomers.length > 0) {
    notifications.push({
      text: `New customers: ${newCustomers.length} (last ${NOTIFICATION_RULES.newCustomerDays} days)`,
      icon: "bi-person-check",
      type: "success",
      targetTab: "customers-tab",
    });
  }

  const newProducts = productsData.filter((product) =>
    isWithinDays(product.createdAt, NOTIFICATION_RULES.newProductDays),
  );
  if (newProducts.length > 0) {
    notifications.push({
      text: `New products added: ${newProducts.length} (last ${NOTIFICATION_RULES.newProductDays} days)`,
      icon: "bi-bag-plus",
      type: "info",
      targetTab: "products-tab",
    });
  }

  const lowStock = productsData.filter(
    (product) => Number(product.stock) <= NOTIFICATION_RULES.lowStockThreshold,
  );
  if (lowStock.length > 0) {
    notifications.push({
      text: `${lowStock.length} product(s) low stock (check today)`,
      icon: "bi-exclamation-triangle",
      type: "danger",
      targetTab: "products-tab",
    });
  }

  const pendingRefunds = (
    Array.isArray(adminRefunds) ? adminRefunds : []
  ).filter(
    (refund) => (refund?.status || "").toString().toLowerCase() === "pending",
  );
  if (pendingRefunds.length > 0) {
    notifications.push({
      text: `${pendingRefunds.length} refund request(s) waiting review`,
      icon: "bi-cash-coin",
      type: "info",
      targetTab: "orders-tab",
    });
  }

  const latestNotifications = notifications.slice(0, 3);
  const latestSignatures = latestNotifications.map((notification) =>
    notificationSignature(notification),
  );

  lastRenderedNotificationSignatures = latestSignatures;

  let dismissedPruned = false;
  Array.from(dismissedNotificationSignatures).forEach((signature) => {
    if (!latestSignatures.includes(signature)) {
      dismissedNotificationSignatures.delete(signature);
      dismissedPruned = true;
    }
  });

  if (dismissedPruned) {
    saveDismissedNotificationSignatures();
  }

  const visibleNotifications = latestNotifications.filter((notification) => {
    const signature = notificationSignature(notification);
    return !dismissedNotificationSignatures.has(signature);
  });

  list.empty();
  list.append(`
    <li class="dropdown-header d-flex align-items-center justify-content-between gap-2">
      <h6 class="text-secondary mb-0">Latest 3 Notifications</h6>
      <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="clearAdminNotifications(); return false;">Clear</button>
    </li>
  `);
  list.append('<li><hr class="dropdown-divider border-secondary"></li>');

  if (latestNotifications.length === 0) {
    count.text("0");
    count.addClass("d-none");
    list.append(
      '<li><span class="dropdown-item text-secondary">No new notifications</span></li>',
    );
    return;
  }

  if (visibleNotifications.length === 0) {
    count.text("0");
    count.addClass("d-none");
    list.append(
      '<li><span class="dropdown-item text-secondary">Notifications cleared. New activity will appear automatically.</span></li>',
    );
    list.append(
      '<li><a class="dropdown-item text-light" href="#" onclick="resumeAdminNotifications(); return false;"><i class="bi bi-arrow-repeat me-2"></i>Reset cleared state</a></li>',
    );
    return;
  }

  count.text(String(visibleNotifications.length));
  count.removeClass("d-none");

  visibleNotifications.forEach((notif, index) => {
    const targetTab = (notif?.targetTab || "").toString();
    const clickHandler = targetTab
      ? `openNotificationTarget('${targetTab}'); return false;`
      : "return false;";
    const safeText = escapeHtml(notif?.text || "");

    list.append(`
            <li><a class="dropdown-item text-light d-flex align-items-center gap-2" href="#" onclick="${clickHandler}">
                <i class="bi ${notif.icon} text-${notif.type}"></i>
                <span>${safeText}</span>
            </a></li>
        `);
    if (index < visibleNotifications.length - 1) {
      list.append('<li><hr class="dropdown-divider border-secondary"></li>');
    }
  });
}

function injectAdminTabPlaybooks() {
  Object.entries(ADMIN_TAB_PLAYBOOKS).forEach(([sectionId, config]) => {
    const pane = document.getElementById(sectionId);
    if (!pane) {
      return;
    }

    const existing = pane.querySelector(
      `.tab-playbook[data-for-tab="${sectionId}"]`,
    );
    if (existing) {
      return;
    }

    const rawSteps = Array.isArray(config?.steps) ? config.steps : [];
    const stepItems = rawSteps
      .map((step) => escapeHtml((step || "").toString().trim()))
      .filter((step) => step !== "")
      .map((step) => `<li>${step}</li>`)
      .join("");

    if (stepItems === "") {
      return;
    }

    const title = escapeHtml((config?.title || "Quick Steps").toString());
    const intro = escapeHtml((config?.intro || "").toString());
    const tip = escapeHtml((config?.tip || "").toString());

    const block = document.createElement("section");
    block.className = "tab-playbook mb-4";
    block.dataset.forTab = sectionId;
    block.innerHTML = `
      <span class="step-chip">Simple Guide</span>
      <h2 class="tab-playbook-title">${title}</h2>
      <p class="tab-playbook-intro">${intro}</p>
      <ol class="tab-playbook-list">${stepItems}</ol>
      <p class="tab-playbook-note mb-0"><i class="bi bi-lightbulb me-2"></i>${tip}</p>
    `;

    const helperBanner = pane.querySelector(".helper-banner");
    if (helperBanner) {
      helperBanner.insertAdjacentElement("afterend", block);
      return;
    }

    pane.insertAdjacentElement("afterbegin", block);
  });
}

$(document).ready(function () {
  const adminIdentity = ADMIN_RUNTIME.admin || {};

  function normalizeAdminEmail(value) {
    return (value || "").toString().trim().toLowerCase();
  }

  function syncAdminIdentityUi(nextIdentity = {}) {
    if (!window.CommerzaAdminRuntime) {
      window.CommerzaAdminRuntime = {};
    }

    if (!window.CommerzaAdminRuntime.admin) {
      window.CommerzaAdminRuntime.admin = {};
    }

    if (nextIdentity?.name) {
      const safeName = nextIdentity.name.toString().trim();
      if (safeName !== "") {
        window.CommerzaAdminRuntime.admin.name = safeName;
        $("#adminSidebarName").text(safeName);
      }
    }

    const normalizedEmail = normalizeAdminEmail(nextIdentity?.email || "");
    if (normalizedEmail !== "") {
      window.CommerzaAdminRuntime.admin.email = normalizedEmail;
      $("#adminSidebarEmail").text(normalizedEmail);
      $("#securityPasswordEmail, #securityKeyEmail").val(normalizedEmail);
    }
  }

  syncAdminIdentityUi(adminIdentity);

  const sidebar = document.getElementById("sidebarMenu");
  if (sidebar) {
    sidebar.addEventListener("shown.bs.collapse", () => {
      document.body.classList.add("sidebar-open");
    });
    sidebar.addEventListener("hidden.bs.collapse", () => {
      document.body.classList.remove("sidebar-open");
    });
  }

  injectAdminTabPlaybooks();

  $(document)
    .off("click.adminDropdownItem")
    .on("click.adminDropdownItem", ".admin-dropdown-item", function (event) {
      event.preventDefault();

      const targetId = ($(this).data("target") || "").toString().trim();
      if (!targetId) {
        return;
      }

      const selectedValue = ($(this).data("value") ?? "").toString();
      const selectedLabel = ($(this).data("label") || $(this).text() || "")
        .toString()
        .trim();

      setAdminDropdownSelection(targetId, selectedValue, selectedLabel);
      $(`#${targetId}`).trigger("change");
    });

  function refreshTabPaneByTabId(tabId) {
    switch ((tabId || "").toString()) {
      case "product-trash-tab":
        loadProductTrashData(true);
        break;
      case "products-tab":
        loadProductsFromJSON();
        break;
      case "orders-tab":
        displayAllOrders();
        renderRefundRequests();
        break;
      case "customers-tab":
        displayAllCustomers();
        break;
      case "analytics-tab":
        renderAnalyticsSection();
        break;
      case "coupons-tab":
        renderCouponsTable();
        break;
      case "reviews-tab":
        renderReviewsTable();
        break;
      case "security-events-tab":
        renderSecurityEventsTable();
        break;
      case "website-tab":
        renderSocialLinksTable();
        renderSliderTable();
        break;
      default:
        break;
    }
  }

  function focusProductTrashCard() {
    const card = document.getElementById("productTrashCard");
    if (!card) {
      return;
    }

    card.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function syncActiveTabUi(tabEl) {
    const pageTitle = document.getElementById("pageTitle");
    const breadcrumbCurrent = document.getElementById("adminBreadcrumbCurrent");
    const tabText =
      tabEl?.querySelector("span:last-child")?.textContent?.trim() ||
      tabEl?.textContent?.trim() ||
      "Dashboard";
    const normalizedTitle = tabText.replace(/\s+/g, " ");

    if (pageTitle) {
      pageTitle.textContent = normalizedTitle;
    }

    if (breadcrumbCurrent) {
      breadcrumbCurrent.textContent = normalizedTitle;
    }

    if (window.innerWidth < 768 && sidebar) {
      const collapse = bootstrap.Collapse.getOrCreateInstance(sidebar, {
        toggle: false,
      });
      collapse.hide();
    }
  }

  $(document)
    .off("shown.bs.tab", '#sidebarNav [data-bs-toggle="pill"]')
    .on("shown.bs.tab", '#sidebarNav [data-bs-toggle="pill"]', function () {
      const activeTabId = (this.id || "").toString();
      syncActiveTabUi(this);
      refreshTabPaneByTabId(activeTabId);

      if (activeTabId === "product-trash-tab") {
        window.setTimeout(focusProductTrashCard, 180);
      }
    });

  syncActiveTabUi(
    document.querySelector('#sidebarNav [data-bs-toggle="pill"].active'),
  );

  function applyButtonCooldown(selector, duration = 1200) {
    $(document).on("click", selector, function () {
      const btn = $(this);
      if (btn.prop("disabled")) return;
      btn.prop("disabled", true);
      setTimeout(() => btn.prop("disabled", false), duration);
    });
  }

  applyButtonCooldown("#saveProductBtn");
  applyButtonCooldown("#saveSectionBtn");
  applyButtonCooldown("#resetSectionBtn");
  applyButtonCooldown("#bulkProductsImportBtn");
  applyButtonCooldown("#downloadSampleProductsCsvBtn");
  applyButtonCooldown("#saveContactBtn");
  applyButtonCooldown("#saveSocialBtn");
  applyButtonCooldown("#resetSocialBtn");
  applyButtonCooldown("#saveTickerBtn");
  applyButtonCooldown("#resetTickerBtn");
  applyButtonCooldown("#saveFeaturedVideosBtn");
  applyButtonCooldown("#saveSliderBtn");
  applyButtonCooldown("#resetSliderBtn");
  applyButtonCooldown("#saveAdminEmailBtn");
  applyButtonCooldown("#saveAdminPasswordBtn");
  applyButtonCooldown("#saveAdminResetKeyBtn");
  applyButtonCooldown("#saveLiveViewersBtn");
  applyButtonCooldown("#bulkDeleteOrdersBtn");
  applyButtonCooldown("#bulkDeleteCustomersBtn");
  applyButtonCooldown("#saveCouponBtn");
  applyButtonCooldown("#resetCouponBtn");
  applyButtonCooldown("#sendCouponEmailBtn");
  applyButtonCooldown("#refreshCouponsBtn");
  applyButtonCooldown("#refreshReviewsBtn");
  applyButtonCooldown("#submitAddReviewBtn");
  applyButtonCooldown("#addFakeReviewBtn");
  applyButtonCooldown("#addSingleFakeReviewBtn");
  applyButtonCooldown("#addFakeBulkReviewsBtn");
  applyButtonCooldown("#securityEventsApplyBtn");
  applyButtonCooldown("#securityEventsClearBtn");
  applyButtonCooldown("#securityEventsRefreshBtn");

  bindUploadControl("#uploadSiteLogoBtn", "#siteLogoFile", "logo", (path) => {
    $("#siteLogo").val(path);
  });
  bindUploadControl(
    "#uploadSiteFaviconBtn",
    "#siteFaviconFile",
    "favicon",
    (path) => {
      $("#siteFavicon").val(path);
    },
  );
  bindUploadControl(
    "#uploadSocialIconBtn",
    "#socialIconFile",
    "social-icon",
    (path) => {
      $("#socialIcon").val(path);
    },
  );
  bindUploadControl(
    "#uploadSliderImageBtn",
    "#sliderImageFile",
    "slider-image",
    (path) => {
      $("#sliderImage").val(path);
    },
  );
  bindUploadControl(
    "#uploadSliderVideoBtn",
    "#sliderVideoFile",
    "slider-video",
    (path) => {
      $("#sliderVideo").val(path);
    },
  );
  bindUploadControl(
    "#uploadHomeFeatureVideoBtn",
    "#homeFeatureVideoFile",
    "slider-video",
    (path) => {
      $("#homeFeatureVideo").val(path);
    },
  );
  bindUploadControl(
    "#uploadCategoryAFeatureVideoBtn",
    "#categoryAFeatureVideoFile",
    "product-video",
    (path) => {
      $("#categoryAFeatureVideo").val(path);
    },
  );
  bindUploadControl(
    "#uploadProductImageBtn",
    "#productImageFile",
    "product-image",
    (path) => {
      $("#productImage").val(path);
    },
  );
  bindUploadControl(
    "#uploadProductVideoBtn",
    "#productVideoFile",
    "product-video",
    (path) => {
      $("#productVideo").val(path);
    },
  );

  $("#ordersSelectAll")
    .off("change")
    .on("change", function () {
      const checked = this.checked;
      $(".order-select-row").prop("checked", checked);
    });

  $(document)
    .off("change", ".order-select-row")
    .on("change", ".order-select-row", function () {
      const total = $(".order-select-row").length;
      const selected = $(".order-select-row:checked").length;
      $("#ordersSelectAll").prop("checked", total > 0 && total === selected);
    });

  $("#customersSelectAll")
    .off("change")
    .on("change", function () {
      const checked = this.checked;
      $(".customer-select-row").prop("checked", checked);
    });

  $(document)
    .off("change", ".customer-select-row")
    .on("change", ".customer-select-row", function () {
      const total = $(".customer-select-row:not(:disabled)").length;
      const selected = $(".customer-select-row:checked").length;
      $("#customersSelectAll").prop("checked", total > 0 && total === selected);
    });

  $("#customersSearchInput")
    .off("input change")
    .on("input change", function () {
      displayAllCustomers();
    });

  $("#bulkProductsImportBtn")
    .off("click")
    .on("click", importProductsFromFileInput);

  $("#downloadSampleProductsCsvBtn")
    .off("click")
    .on("click", downloadSampleProductsCSV);

  $("#refreshProductTrashBtn")
    .off("click")
    .on("click", function () {
      loadProductTrashData(false);
    });

  $("#emptyExpiredProductTrashBtn")
    .off("click")
    .on("click", function () {
      emptyProductTrash("expired");
    });

  $("#emptyAllProductTrashBtn")
    .off("click")
    .on("click", function () {
      emptyProductTrash("all");
    });

  $("#bulkDeleteOrdersBtn").off("click").on("click", bulkDeleteOrders);
  $("#bulkDeleteCustomersBtn").off("click").on("click", bulkDeleteCustomers);
  $("#saveShippingConfigBtn").off("click").on("click", saveShippingConfig);
  $("#addBlacklistBtn").off("click").on("click", addBlacklistFromForm);
  $("#whitelistContactBtn").off("click").on("click", whitelistContactFromForm);
  $("#saveSeoMetaBtn").off("click").on("click", saveSeoMetaFromForm);
  $("#resetSeoMetaBtn").off("click").on("click", resetSeoMetaForm);
  $("#deleteSeoMetaBtn")
    .off("click")
    .on("click", function () {
      deleteSeoMetaForPage("");
    });
  $("#seoPageSelect").off("change").on("change", refreshSeoMetaEditor);

  $(document)
    .off("click", ".delete-customer-btn")
    .on("click", ".delete-customer-btn", function () {
      const customerId = parseInt($(this).data("customerId"), 10) || 0;
      const customerName = ($(this).data("customerName") || "Customer")
        .toString()
        .trim();
      if (customerId <= 0) {
        showNotification("Customer id is invalid.", "warning");
        return;
      }
      deleteSingleCustomer(customerId, customerName || "Customer");
    });

  $(document)
    .off("click", ".blacklist-customer-btn")
    .on("click", ".blacklist-customer-btn", function () {
      const customerId = parseInt($(this).data("customerId"), 10) || 0;
      const customerName = ($(this).data("customerName") || "Customer")
        .toString()
        .trim();
      const customerEmail = ($(this).data("customerEmail") || "")
        .toString()
        .trim();
      const customerPhone = ($(this).data("customerPhone") || "")
        .toString()
        .trim();

      blacklistCustomerByIdentity(
        customerId,
        customerName,
        customerEmail,
        customerPhone,
        false,
      );
    });

  $(document)
    .off("click", ".blacklist-delete-customer-btn")
    .on("click", ".blacklist-delete-customer-btn", function () {
      const customerId = parseInt($(this).data("customerId"), 10) || 0;
      const customerName = ($(this).data("customerName") || "Customer")
        .toString()
        .trim();
      const customerEmail = ($(this).data("customerEmail") || "")
        .toString()
        .trim();
      const customerPhone = ($(this).data("customerPhone") || "")
        .toString()
        .trim();

      blacklistCustomerByIdentity(
        customerId,
        customerName,
        customerEmail,
        customerPhone,
        true,
      );
    });

  $(document)
    .off("click", ".unblacklist-customer-btn")
    .on("click", ".unblacklist-customer-btn", function () {
      const customerEmail = ($(this).data("customerEmail") || "")
        .toString()
        .trim();
      const customerPhone = ($(this).data("customerPhone") || "")
        .toString()
        .trim();

      removeBlacklistByContact(customerEmail, customerPhone);
    });

  $(document)
    .off("click", ".remove-blacklist-btn")
    .on("click", ".remove-blacklist-btn", function () {
      const blacklistId = parseInt($(this).data("blacklistId"), 10) || 0;
      removeBlacklistById(blacklistId);
    });

  $(document)
    .off("click", ".seo-meta-edit-btn")
    .on("click", ".seo-meta-edit-btn", function () {
      const page = ($(this).data("seoPage") || "").toString().trim();
      if (!page) {
        return;
      }

      $("#seoPageSelect").val(page.toLowerCase());
      refreshSeoMetaEditor();
    });

  $(document)
    .off("click", ".seo-meta-delete-btn")
    .on("click", ".seo-meta-delete-btn", function () {
      const page = ($(this).data("seoPage") || "").toString().trim();
      if (!page) {
        return;
      }

      deleteSeoMetaForPage(page);
    });

  $(document)
    .off("click", ".refund-status-btn")
    .on("click", ".refund-status-btn", function () {
      const refundId = parseInt($(this).data("refund-id"), 10) || 0;
      const status = ($(this).data("status") || "pending").toString();
      if (refundId <= 0) return;
      updateRefundStatus(refundId, status);
    });

  $(document).on("click", ".password-toggle", function () {
    const target = $(this).data("target");
    const input = $(target);
    if (!input.length) return;
    input.attr("type", input.attr("type") === "password" ? "text" : "password");
    $(this).toggleClass("bi-eye bi-eye-slash");
  });

  loadDismissedNotificationSignatures();

  const initialProductsLoad = loadProductsFromJSON();
  const initialTrashLoad = loadProductTrashData(true);
  const initialOrdersLoad = loadAdminOrdersData(true);
  const initialCouponsLoad = loadCouponsData(true);
  const initialReviewsLoad = loadReviewsData(true);
  const initialSecurityEventsLoad = loadSecurityEvents(true);

  initCouponsSection();
  initReviewsSection();
  initSecurityEventsSection();
  initWebsiteSettings();
  initLiveViewersAnalytics();

  Promise.allSettled([
    Promise.resolve(initialProductsLoad),
    Promise.resolve(initialTrashLoad),
    Promise.resolve(initialOrdersLoad),
    Promise.resolve(initialCouponsLoad),
    Promise.resolve(initialReviewsLoad),
    Promise.resolve(initialSecurityEventsLoad),
  ]).finally(() => {
    initEmailCenter();
    calculateDashboardMetrics();
    displayRecentOrders();
    displayAllOrders();
    displayAllCustomers();
    renderBlacklistTable();
    renderShippingConfigCard();
    renderAnalyticsSection();
    renderRefundRequests();
    updateNotifications();
  });

  $("#saveProductBtn")
    .off("click")
    .on("click", function () {
      if (!$("#productForm")[0].checkValidity()) {
        showNotification("Please fill in all required fields", "danger");
        return;
      }

      const productId = $("#productId").val();
      const sectionId = $("#productSection").val();
      const section = allSections.find((s) => s.sectionId === sectionId);
      const existingProduct = productId
        ? productsData.find((p) => p.id === parseInt(productId))
        : null;

      if (!section) {
        showNotification("Please select a valid section", "danger");
        return;
      }

      const resolvedProductId = productId ? parseInt(productId, 10) : nextId++;
      const stockValue = parseInt($("#productStock").val(), 10) || 0;
      const dispatchFallback =
        stockValue > 0 ? "Dispatch in 24-48 hours" : "Pre-order availability";
      const productCode = normalizeProductCodeInput(
        $("#productCode").val(),
        resolvedProductId,
      );

      const duplicateCode = productsData.find(
        (item) =>
          (item?.productCode || "").toString().toUpperCase() === productCode &&
          parseInt(item?.id, 10) !== resolvedProductId,
      );

      if (duplicateCode) {
        showNotification(
          "Product code already exists. Use a unique code.",
          "danger",
        );
        return;
      }

      const productData = {
        id: resolvedProductId,
        name: $("#productName").val(),
        price: parseFloat($("#productPrice").val()) || 0,
        salePrice: parseFloat($("#productSalePrice").val()) || 0,
        stock: stockValue,
        image: $("#productImage").val().trim(),
        video: $("#productVideo").val().trim(),
        productCode,
        warrantyInfo: normalizeProductMetaInput(
          $("#productWarrantyInfo").val(),
          "12-month seller warranty",
          120,
        ),
        dispatchInfo: normalizeProductMetaInput(
          $("#productDispatchInfo").val(),
          dispatchFallback,
          120,
        ),
        description: $("#productDescription").val(),
        movement: $("#productMovement").val(),
        category: section ? section.category : "Uncategorized",
        subcategory: section ? section.subcategory : "General",
        sectionName: section ? section.sectionName : "General",
        sectionId: sectionId,
        page: section ? section.page : "index.php",
        createdAt: existingProduct?.createdAt || new Date().toISOString(),
      };

      if (productData.price <= 0) {
        showNotification("Price must be greater than 0.", "danger");
        return;
      }

      if (productData.salePrice <= 0) {
        productData.salePrice = productData.price;
      }

      if (productId) {
        const index = productsData.findIndex(
          (p) => p.id === parseInt(productId),
        );
        if (index > -1) {
          productsData[index] = productData;
          showNotification("Product updated!", "success");
        }
      } else {
        productsData.push(productData);
        showNotification("Product added!", "success");
      }

      saveProductsToJSON();
      renderProductsTable();
      calculateDashboardMetrics();
      updateNotifications();
      $("#productForm")[0].reset();
      $("#productId").val("");
      $("#productSectionBtn").text("Select Section");
      $("#productSection").val("");
      $("#productMovementBtn").text("Quartz");
      $("#productMovement").val("quartz");
      $("#productCode").val("");
      $("#productWarrantyInfo").val("12-month seller warranty");
      $("#productDispatchInfo").val("Dispatch in 24-48 hours");
      bootstrap.Modal.getInstance(
        document.getElementById("productModal"),
      ).hide();
    });

  $(document)
    .off("click", "#addNewProductBtn")
    .on("click", "#addNewProductBtn", function () {
      $("#productForm")[0].reset();
      $("#productId").val("");
      $("#productSectionBtn").text("Select Section");
      $("#productSection").val("");
      $("#productMovementBtn").text("Quartz");
      $("#productMovement").val("quartz");
      $("#productCode").val("");
      $("#productWarrantyInfo").val("12-month seller warranty");
      $("#productDispatchInfo").val("Dispatch in 24-48 hours");
      $("#productModalLabel").text("Add New Product");
      new bootstrap.Modal(document.getElementById("productModal")).show();
    });

  $("#saveSectionBtn")
    .off("click")
    .on("click", function () {
      const formId = $("#sectionFormId").val();
      const sectionName = $("#sectionName").val().trim();
      const rawId = $("#sectionId").val().trim();
      const page = $("#sectionPage").val().trim() || "index.php";
      const category = $("#sectionCategory").val().trim() || "Uncategorized";
      const subcategory = $("#sectionSubcategory").val().trim() || "General";

      if (!sectionName) {
        showNotification("Section name is required", "danger");
        return;
      }

      const baseId = rawId || slugifySection(sectionName);
      if (!baseId) {
        showNotification("Section ID is required", "danger");
        return;
      }

      if (formId) {
        if (
          formId !== baseId &&
          allSections.some((section) => section.sectionId === baseId)
        ) {
          showNotification("Section ID already exists", "danger");
          return;
        }
        const section = allSections.find((item) => item.sectionId === formId);
        if (section) {
          section.sectionName = sectionName;
          section.sectionId = baseId;
          section.page = page;
          section.category = category;
          section.subcategory = subcategory;
          productsData = productsData.map((product) => {
            if (product.sectionId !== formId) return product;
            return {
              ...product,
              sectionId: baseId,
              sectionName: sectionName,
              category: category,
              subcategory: subcategory,
              page: page,
            };
          });
          showNotification("Section updated!", "success");
        }
      } else {
        const uniqueId = ensureUniqueSectionId(baseId);
        allSections.push({
          sectionName,
          sectionId: uniqueId,
          page,
          category,
          subcategory,
          products: [],
        });
        showNotification("Section added!", "success");
      }

      saveProductsToJSON();
      renderSectionDropdowns();
      renderSectionsTable();
      renderProductsTable();
      updateNotifications();
      resetSectionForm();
    });

  $("#resetSectionBtn")
    .off("click")
    .on("click", function () {
      resetSectionForm();
    });

  $("#saveContactBtn")
    .off("click")
    .on("click", function () {
      const address = $("#siteAddress").val().trim();
      const email = $("#siteEmail").val().trim();
      const phone = $("#sitePhone").val().trim();

      if (!address || !email || !phone) {
        showNotification("Please enter address, email and phone", "danger");
        return;
      }

      adminPostJson(ADMIN_WEBSITE_API, {
        action: "save-contact",
        address,
        email,
        phone,
      })
        .then((result) => {
          applyWebsiteSettingsPayload(result?.payload || null);
          showNotification(
            result?.message || "Contact details updated!",
            "success",
          );
        })
        .catch((error) => {
          showNotification(
            error?.message || "Unable to update contact details.",
            "danger",
          );
        });
    });

  $("#saveBrandBtn")
    .off("click")
    .on("click", function () {
      const name = $("#siteName").val().trim();
      const logo = $("#siteLogo").val().trim();
      const favicon = $("#siteFavicon").val().trim();

      if (!name || !logo || !favicon) {
        showNotification(
          "Please enter website name, logo, and favicon",
          "danger",
        );
        return;
      }

      adminPostJson(ADMIN_WEBSITE_API, {
        action: "save-brand",
        name,
        logo,
        favicon,
      })
        .then((result) => {
          applyWebsiteSettingsPayload(result?.payload || null);
          showNotification(result?.message || "Branding updated!", "success");
          if (typeof window.applyAdminBranding === "function") {
            window.applyAdminBranding();
          }
        })
        .catch((error) => {
          showNotification(
            error?.message || "Unable to update branding.",
            "danger",
          );
        });
    });

  $("#saveSocialBtn")
    .off("click")
    .on("click", function () {
      const id = $("#socialId").val();
      const label = $("#socialLabel").val().trim();
      const url = $("#socialUrl").val().trim();
      const icon = $("#socialIcon").val().trim();

      if (!label || !url) {
        showNotification("Please fill in label and URL", "danger");
        return;
      }

      adminPostJson(ADMIN_WEBSITE_API, {
        action: "save-social",
        id: id ? parseInt(id, 10) : 0,
        label,
        url,
        icon,
      })
        .then((result) => {
          applyWebsiteSettingsPayload(result?.payload || null);
          resetSocialForm();
          showNotification(result?.message || "Social link saved!", "success");
        })
        .catch((error) => {
          showNotification(
            error?.message || "Unable to save social link.",
            "danger",
          );
        });
    });

  $("#resetSocialBtn").on("click", function () {
    resetSocialForm();
  });

  $("#saveTickerBtn")
    .off("click")
    .on("click", function () {
      const enabled = $("#tickerEnabled").is(":checked");
      const messages = $("#tickerMessages")
        .val()
        .split("\n")
        .map((line) => line.trim())
        .filter(Boolean);

      if (messages.length === 0) {
        showNotification("Please add at least one ticker message", "danger");
        return;
      }

      adminPostJson(ADMIN_WEBSITE_API, {
        action: "save-ticker",
        enabled,
        messages,
      })
        .then((result) => {
          applyWebsiteSettingsPayload(result?.payload || null);
          showNotification(result?.message || "Ticker updated!", "success");
        })
        .catch((error) => {
          showNotification(
            error?.message || "Unable to save ticker.",
            "danger",
          );
        });
    });

  $("#resetTickerBtn").on("click", function () {
    resetTickerForm();
  });

  $("#saveFeaturedVideosBtn")
    .off("click")
    .on("click", function () {
      const homeVideo = $("#homeFeatureVideo").val().trim();
      const categoryAVideo = $("#categoryAFeatureVideo").val().trim();

      if (!homeVideo || !categoryAVideo) {
        showNotification(
          "Please add both featured video paths before saving.",
          "danger",
        );
        return;
      }

      adminPostJson(ADMIN_WEBSITE_API, {
        action: "save-feature-videos",
        home_video: homeVideo,
        category_a_video: categoryAVideo,
      })
        .then((result) => {
          applyWebsiteSettingsPayload(result?.payload || null);
          showNotification(
            result?.message || "Featured videos updated!",
            "success",
          );
        })
        .catch((error) => {
          showNotification(
            error?.message || "Unable to save featured videos.",
            "danger",
          );
        });
    });

  $("#saveSliderBtn")
    .off("click")
    .on("click", function () {
      const id = $("#sliderId").val();
      const image = $("#sliderImage").val().trim();
      const alt = $("#sliderAlt").val().trim();
      const label = $("#sliderLabel").val().trim();
      const heading = $("#sliderHeading").val().trim();
      const text = $("#sliderText").val().trim();
      const buttonText = $("#sliderButtonText").val().trim();
      const buttonLink = $("#sliderButtonLink").val().trim();
      const video = $("#sliderVideo").val().trim();

      if (!image || !heading) {
        showNotification("Please add image and heading", "danger");
        return;
      }

      adminPostJson(ADMIN_WEBSITE_API, {
        action: "save-slider",
        id: id ? parseInt(id, 10) : 0,
        image,
        alt,
        label,
        heading,
        text,
        buttonText,
        buttonLink,
        video,
      })
        .then((result) => {
          applyWebsiteSettingsPayload(result?.payload || null);
          resetSliderForm();
          showNotification(result?.message || "Slide saved!", "success");
        })
        .catch((error) => {
          showNotification(error?.message || "Unable to save slide.", "danger");
        });
    });

  $("#resetSliderBtn").on("click", function () {
    resetSliderForm();
  });

  $("#saveAdminEmailBtn").on("click", function () {
    const currentPassword = (
      $("#securityEmailPassword").val() || ""
    ).toString();
    const resetKey = ($("#securityEmailResetKey").val() || "")
      .toString()
      .trim();
    const newEmail = ($("#securityEmailNew").val() || "")
      .toString()
      .trim()
      .toLowerCase();
    const confirmEmail = ($("#securityEmailConfirm").val() || "")
      .toString()
      .trim()
      .toLowerCase();

    if (!currentPassword || !resetKey) {
      showNotification("Password and reset key are required", "danger");
      return;
    }

    if (
      !newEmail ||
      !confirmEmail ||
      newEmail !== confirmEmail ||
      !newEmail.includes("@")
    ) {
      showNotification("Enter a valid matching email", "danger");
      return;
    }

    adminPostJson(ADMIN_SECURITY_API, {
      action: "update-email",
      currentPassword,
      resetKey,
      newEmail,
      confirmEmail,
    })
      .then((result) => {
        syncAdminIdentityUi({
          email: result?.email || newEmail,
        });
        $(
          "#securityEmailPassword, #securityEmailResetKey, #securityEmailNew, #securityEmailConfirm",
        ).val("");
        showNotification(result?.message || "Admin email updated!", "success");
      })
      .catch((error) => {
        showNotification(
          error?.message || "Could not update admin email.",
          "danger",
        );
      });
  });

  $("#saveAdminPasswordBtn").on("click", function () {
    const currentEmail = ($("#securityPasswordEmail").val() || "")
      .toString()
      .trim()
      .toLowerCase();
    const resetKey = ($("#securityPasswordResetKey").val() || "")
      .toString()
      .trim();
    const newPassword = ($("#securityPasswordNew").val() || "").toString();
    const confirmPassword = (
      $("#securityPasswordConfirm").val() || ""
    ).toString();

    if (!currentEmail || !resetKey) {
      showNotification("Email and reset key are required", "danger");
      return;
    }

    if (!newPassword || newPassword !== confirmPassword) {
      showNotification("Passwords do not match", "danger");
      return;
    }

    adminPostJson(ADMIN_SECURITY_API, {
      action: "update-password",
      currentEmail,
      resetKey,
      newPassword,
      confirmPassword,
    })
      .then((result) => {
        $(
          "#securityPasswordResetKey, #securityPasswordNew, #securityPasswordConfirm",
        ).val("");
        showNotification(
          result?.message || "Admin password updated!",
          "success",
        );
      })
      .catch((error) => {
        showNotification(
          error?.message || "Could not update admin password.",
          "danger",
        );
      });
  });

  $("#saveAdminResetKeyBtn").on("click", function () {
    const currentEmail = ($("#securityKeyEmail").val() || "")
      .toString()
      .trim()
      .toLowerCase();
    const currentPassword = ($("#securityKeyPassword").val() || "").toString();
    const newKey = ($("#securityKeyNew").val() || "").toString().trim();
    const confirmKey = ($("#securityKeyConfirm").val() || "").toString().trim();

    if (!currentEmail || !currentPassword) {
      showNotification("Email and password are required", "danger");
      return;
    }

    if (!newKey || newKey !== confirmKey) {
      showNotification("Reset keys do not match", "danger");
      return;
    }

    adminPostJson(ADMIN_SECURITY_API, {
      action: "update-reset-key",
      currentEmail,
      currentPassword,
      newKey,
      confirmKey,
    })
      .then((result) => {
        $("#securityKeyPassword, #securityKeyNew, #securityKeyConfirm").val("");
        showNotification(result?.message || "Reset key updated!", "success");
      })
      .catch((error) => {
        showNotification(
          error?.message || "Could not update reset key.",
          "danger",
        );
      });
  });
});

function renderAnalyticsSection() {
  const revenue = Number(adminMetrics?.totalRevenue || 0);
  const refundLoss = Number(adminMetrics?.refundLoss || 0);
  const netRevenue = Number(adminMetrics?.netRevenue ?? revenue - refundLoss);
  const orderCount = Math.max(0, parseInt(adminMetrics?.totalOrders, 10) || 0);
  const avgOrderValue = Number(adminMetrics?.avgOrderValue || 0);
  const returningRate = Number(adminMetrics?.returningCustomerRate || 0);
  const orders = getAdminOrdersData();
  const openOrders = orders.filter((order) => {
    const status = (order?.status || "").toString().toLowerCase();
    return ["pending", "confirmed", "processing"].includes(status);
  }).length;
  const lowStockCount = productsData.filter(
    (product) => Number(product.stock) <= NOTIFICATION_RULES.lowStockThreshold,
  ).length;
  const pendingRefundCount = (
    Array.isArray(adminRefunds) ? adminRefunds : []
  ).filter(
    (refund) => (refund?.status || "").toString().toLowerCase() === "pending",
  ).length;
  const acceptedRefundCount = (
    Array.isArray(adminRefunds) ? adminRefunds : []
  ).filter(
    (refund) => (refund?.status || "").toString().toLowerCase() === "accepted",
  ).length;
  const rejectedRefundCount = (
    Array.isArray(adminRefunds) ? adminRefunds : []
  ).filter(
    (refund) => (refund?.status || "").toString().toLowerCase() === "rejected",
  ).length;

  const pendingRefunds = Number(
    adminMetrics?.pendingRefunds ?? pendingRefundCount,
  );
  const acceptedRefunds = Number(
    adminMetrics?.acceptedRefunds ?? acceptedRefundCount,
  );
  const rejectedRefunds = Number(
    adminMetrics?.rejectedRefunds ?? rejectedRefundCount,
  );

  $("#analyticsRevenueValue").text(formatPkr(revenue));
  $("#analyticsOrdersValue").text(orderCount.toLocaleString());
  $("#analyticsAovValue").text(formatPkr(avgOrderValue));
  $("#analyticsReturningValue").text(`${returningRate.toFixed(1)}%`);

  $("#analyticsRevenueHint").html(
    '<i class="bi bi-calendar-week"></i> Last 30 days',
  );
  $("#analyticsOrdersHint").html(
    '<i class="bi bi-calendar-week"></i> Last 30 days',
  );
  $("#analyticsAovHint").html(
    '<i class="bi bi-calculator"></i> Sales divided by orders',
  );
  $("#analyticsReturningHint").html(
    '<i class="bi bi-people"></i> Customers who came back',
  );

  $("#storeHealthOpenOrders").text(openOrders.toLocaleString());
  $("#storeHealthLowStock").text(lowStockCount.toLocaleString());
  $("#storeHealthPendingRefunds").text(pendingRefunds.toLocaleString());

  const dashboardRefundValue = document.getElementById(
    "dashboardRefundSummaryValue",
  );
  if (dashboardRefundValue) {
    dashboardRefundValue.textContent = `${pendingRefunds} / ${acceptedRefunds} / ${rejectedRefunds}`;
  }

  const dashboardRefundInfo = document.getElementById(
    "dashboardRefundSummaryInfo",
  );
  if (dashboardRefundInfo) {
    dashboardRefundInfo.textContent = "Pending / Accepted / Rejected";
  }

  const actionItems = [];
  if (openOrders > 0) {
    actionItems.push(
      `Process ${openOrders} open order(s) to keep delivery speed high.`,
    );
  }
  if (lowStockCount > 0) {
    actionItems.push(
      `Restock ${lowStockCount} low-stock product(s) before they run out.`,
    );
  }
  if (pendingRefunds > 0) {
    actionItems.push(
      `Review ${pendingRefunds} pending refund request(s) today.`,
    );
  }
  if (refundLoss > 0) {
    actionItems.push(
      `Refund loss in the last 30 days is ${formatPkr(refundLoss)}. Net progress is ${formatPkr(netRevenue)}.`,
    );
  }
  if (orderCount === 0) {
    actionItems.push(
      "No recent orders yet. Push one offer through Email Center.",
    );
  }
  if (returningRate < 20 && orderCount > 0) {
    actionItems.push(
      "Repeat customers are low. Send a follow-up offer to past buyers.",
    );
  }
  if (!actionItems.length) {
    actionItems.push(
      "Everything looks healthy. Keep monitoring stock and orders daily.",
    );
  }

  const actionList = $("#analyticsActionList");
  if (actionList.length) {
    actionList.empty();
    actionItems.slice(0, 5).forEach((item) => {
      actionList.append(
        `<li class="list-group-item bg-transparent border-secondary text-light">${escapeHtml(item)}</li>`,
      );
    });
  }

  const weekly = Array.isArray(adminMetrics?.weeklyPerformance)
    ? adminMetrics.weeklyPerformance
    : [];
  const topProducts = Array.isArray(adminMetrics?.topProducts)
    ? adminMetrics.topProducts
    : [];

  const weeklyWrap = $("#weeklyPerformanceRows");
  if (weeklyWrap.length) {
    weeklyWrap.empty();

    if (!weekly.length) {
      weeklyWrap.append(
        '<div class="text-secondary small">No weekly performance data yet.</div>',
      );
    } else {
      const maxRevenue = Math.max(
        ...weekly.map((item) => Number(item?.revenue || 0)),
        0,
      );

      weekly.forEach((item, idx) => {
        const label = escapeHtml(item?.label || "Day");
        const revenueValue = Number(item?.revenue || 0);
        const ordersValue = Math.max(0, parseInt(item?.orders, 10) || 0);
        const width = maxRevenue > 0 ? (revenueValue / maxRevenue) * 100 : 0;
        const barClass =
          idx % 4 === 0
            ? "bg-orange"
            : idx % 4 === 1
              ? "bg-info"
              : idx % 4 === 2
                ? "bg-success"
                : "bg-warning";

        weeklyWrap.append(`
          <div class="mb-3">
            <div class="d-flex justify-content-between mb-2">
              <span class="text-light">${label}</span>
              <span class="text-secondary">${formatPkr(revenueValue)} · ${ordersValue} order${ordersValue === 1 ? "" : "s"}</span>
            </div>
            <div class="progress" style="height: 6px;">
              <div class="progress-bar ${barClass}" style="width: ${Math.max(4, Math.min(100, width)).toFixed(1)}%"></div>
            </div>
          </div>
        `);
      });
    }
  }

  const topProductsWrap = $("#topProductsList");
  if (topProductsWrap.length) {
    topProductsWrap.empty();

    if (!topProducts.length) {
      topProductsWrap.append(
        '<div class="text-secondary small">No top-product data yet.</div>',
      );
    } else {
      topProducts.forEach((item) => {
        const name = escapeHtml(item?.name || "Product");
        const orders = Math.max(0, parseInt(item?.orders, 10) || 0);
        const revenueTotal = Number(item?.revenue || 0);
        topProductsWrap.append(`
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <p class="text-light mb-1 fw-semibold">${name}</p>
              <p class="text-secondary small mb-0">${orders} units sold</p>
            </div>
            <span class="text-orange fw-semibold">${formatPkr(revenueTotal)}</span>
          </div>
        `);
      });
    }
  }

  renderAnalyticsProfitLossChart(weekly);
}

function renderAnalyticsProfitLossChart(weekly) {
  const canvas = document.getElementById("analyticsProfitLossChart");
  if (!canvas) {
    return;
  }

  if (typeof window.Chart === "undefined") {
    if (analyticsProfitLossChart) {
      analyticsProfitLossChart.destroy();
      analyticsProfitLossChart = null;
    }
    return;
  }

  const fallbackWeekly = Array.from({ length: 7 }, (_, index) => {
    const date = new Date();
    date.setDate(date.getDate() - (6 - index));
    return {
      label: date.toLocaleDateString("en-US", { weekday: "short" }),
      revenue: 0,
      loss: 0,
      net: 0,
    };
  });

  const series =
    Array.isArray(weekly) && weekly.length ? weekly : fallbackWeekly;
  const labels = series.map((row) => (row?.label || "Day").toString());
  const revenueData = series.map((row) => Number(row?.revenue || 0));
  const lossData = series.map((row) => Number(row?.loss || 0));
  const netData = series.map((row) => Number(row?.net || 0));

  if (analyticsProfitLossChart) {
    analyticsProfitLossChart.destroy();
    analyticsProfitLossChart = null;
  }

  analyticsProfitLossChart = new window.Chart(canvas.getContext("2d"), {
    type: "bar",
    data: {
      labels,
      datasets: [
        {
          label: "Revenue",
          data: revenueData,
          backgroundColor: "rgba(255, 122, 26, 0.72)",
          borderColor: "rgba(255, 166, 64, 0.95)",
          borderWidth: 1,
          borderRadius: 8,
          borderSkipped: false,
        },
        {
          label: "Loss",
          data: lossData,
          backgroundColor: "rgba(220, 53, 69, 0.72)",
          borderColor: "rgba(255, 99, 132, 0.95)",
          borderWidth: 1,
          borderRadius: 8,
          borderSkipped: false,
        },
        {
          type: "line",
          label: "Net Progress",
          data: netData,
          borderColor: "rgba(40, 167, 69, 1)",
          backgroundColor: "rgba(40, 167, 69, 0.25)",
          borderWidth: 2,
          tension: 0.35,
          pointRadius: 3,
          pointHoverRadius: 5,
          fill: false,
          yAxisID: "y",
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: "index",
        intersect: false,
      },
      plugins: {
        legend: {
          labels: {
            color: "#d9d9d9",
            boxWidth: 12,
            boxHeight: 12,
          },
        },
        tooltip: {
          callbacks: {
            label: (context) => {
              const value = Number(context?.raw || 0);
              return `${context.dataset.label}: ${formatPkr(value)}`;
            },
          },
        },
      },
      scales: {
        x: {
          ticks: {
            color: "#bcbcbc",
          },
          grid: {
            color: "rgba(255,255,255,0.05)",
          },
        },
        y: {
          beginAtZero: true,
          ticks: {
            color: "#bcbcbc",
            callback: (value) => formatPkr(Number(value || 0)),
          },
          grid: {
            color: "rgba(255,255,255,0.06)",
          },
        },
      },
    },
  });
}

function refundBadgeClass(status) {
  const normalized = (status || "").toLowerCase();
  if (normalized === "accepted") return "bg-success";
  if (normalized === "rejected") return "bg-danger";
  return "bg-warning text-dark";
}

function renderRefundRequests() {
  const tbody = document.querySelector("#refundTable tbody");
  if (!tbody) return;

  tbody.innerHTML = "";

  if (!Array.isArray(adminRefunds) || adminRefunds.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="8" class="text-center py-4 text-secondary">No refund requests yet.</td></tr>';
    return;
  }

  adminRefunds.forEach((refund) => {
    const orderNumber = escapeHtml(
      refund.orderNumber || `#${refund.orderId || "-"}`,
    );
    const customerName = escapeHtml(refund.customerName || "Customer");
    const requestedAt = escapeHtml(formatShortDate(refund.requestedAt));
    const reason = escapeHtml(refund.reason || "No reason provided");
    const evidencePath = (refund.evidencePath || "").toString().trim();
    const evidenceName = escapeHtml(
      refund.evidenceName || "View uploaded file",
    );
    const evidenceUrl = evidencePath
      ? evidencePath.startsWith("http")
        ? evidencePath
        : `../../${encodeURI(evidencePath)}`
      : "";
    const status = (refund.status || "pending").toLowerCase();
    const statusLabel =
      status.charAt(0).toUpperCase() + status.slice(1).toLowerCase();
    const paymentBadge = resolveOrderPaymentBadge(
      refund.orderPaymentStatus || "unpaid",
      refund.orderPaymentMethod || "",
    );

    const row = document.createElement("tr");
    row.className = "border-bottom border-secondary";
    row.innerHTML = `
      <td class="ps-4 py-3 text-light fw-semibold">${orderNumber}</td>
      <td class="py-3 text-light">${customerName}</td>
      <td class="py-3 text-secondary small">${requestedAt}</td>
      <td class="py-3"><span class="badge ${refundBadgeClass(status)} rounded-pill">${escapeHtml(statusLabel)}</span></td>
      <td class="py-3"><span class="badge ${paymentBadge.className} rounded-pill">${escapeHtml(paymentBadge.label)}</span></td>
      <td class="py-3 text-secondary small">${reason}</td>
      <td class="py-3 text-secondary small">${evidenceUrl ? `<a href="${evidenceUrl}" target="_blank" rel="noopener" class="text-warning text-decoration-underline">${evidenceName}</a>` : "No file"}</td>
      <td class="pe-4 py-3">
        <div class="d-flex flex-wrap gap-1">
          <button class="btn btn-sm btn-outline-warning refund-status-btn" data-refund-id="${Number(refund.id || 0)}" data-status="pending">Pending</button>
          <button class="btn btn-sm btn-outline-success refund-status-btn" data-refund-id="${Number(refund.id || 0)}" data-status="accepted">Accept</button>
          <button class="btn btn-sm btn-outline-danger refund-status-btn" data-refund-id="${Number(refund.id || 0)}" data-status="rejected">Reject</button>
        </div>
      </td>
    `;
    tbody.appendChild(row);
  });
}

async function updateRefundStatus(refundId, status) {
  const normalizedStatus = (status || "pending").toLowerCase();
  if (!["pending", "accepted", "rejected"].includes(normalizedStatus)) {
    showNotification("Invalid refund status.", "danger");
    return;
  }

  const notePrompt =
    normalizedStatus === "pending"
      ? "Optional note for this refund request:"
      : `Optional note for ${normalizedStatus} decision:`;
  const adminNote = await showCustomPromptDialog(
    notePrompt,
    "",
    "Refund Decision Note",
  );
  if (adminNote === null) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "update-refund-status",
      refund_id: Number(refundId || 0),
      status: normalizedStatus,
      admin_note: adminNote.trim(),
    });

    setAdminOrdersPayload(result?.payload || {});
    displayRecentOrders();
    displayAllOrders();
    displayAllCustomers();
    calculateDashboardMetrics();
    renderAnalyticsSection();
    renderRefundRequests();
    updateNotifications();

    showNotification(result?.message || "Refund request updated.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to update refund request.",
      "danger",
    );
  }
}

function displayRecentOrders() {
  let orders = getAdminOrdersData();
  orders = orders
    .sort((a, b) => new Date(b.orderDate) - new Date(a.orderDate))
    .slice(0, 5);

  let tbody = document.querySelector("#dashboardSection .table tbody");
  if (!tbody) return;

  tbody.innerHTML = "";

  if (orders.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="5" class="text-center py-4 text-secondary">No orders yet</td></tr>';
    return;
  }

  orders.forEach((order, idx) => {
    const items = Array.isArray(order.items) ? order.items : [];
    const customerName = (order.customerName || "Customer").toString();
    const shortName = customerName.split(" ")[0] || customerName;
    const safeOrderId = escapeHtml(order.orderId || "");
    const safeShortName = escapeHtml(shortName);
    const safeOrderDate = escapeHtml(order.orderDate || "");
    const safeStatus = escapeHtml(order.status || "Pending");
    const totalAmount = Number(order.total || 0);
    const statusColor =
      order.status === "Pending"
        ? "bg-warning text-dark"
        : order.status === "Shipped"
          ? "bg-info text-dark"
          : order.status === "Cancelled"
            ? "bg-danger"
            : "bg-success";
    const row = document.createElement("tr");
    row.className = "border-bottom border-secondary";
    row.innerHTML = `
        <td class="ps-4 py-3 fw-semibold text-light">${safeOrderId}</td>
      <td class="py-3 text-light">${safeShortName}</td>
        <td class="py-3 text-secondary small">${safeOrderDate}</td>
        <td class="py-3 text-light fw-semibold">PKR ${totalAmount.toLocaleString()}</td>
            <td class="pe-4 py-3">
          <span class="badge ${statusColor} rounded-pill px-3 py-2">${safeStatus}</span>
            </td>
        `;
    row.style.cursor = "pointer";
    row.onclick = () => toggleOrderDetails("recentOrderDetails-" + idx);
    tbody.appendChild(row);
    const detailsRow = document.createElement("tr");
    detailsRow.id = "recentOrderDetails-" + idx;
    detailsRow.style.display = "none";
    detailsRow.className = "bg-dark";
    detailsRow.innerHTML = `
            <td colspan="5" class="py-3 px-4">
                <div style="background-color: #2a2a2a; padding: 15px; border-radius: 6px;">
            <h6 class="text-orange mb-3 fw-bold">Products in Order</h6>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        ${items
                          .map((item, i) => {
                            const price =
                              typeof item.price === "string"
                                ? parseInt(item.price.replace(/\D/g, ""))
                                : item.price;
                            const qty = parseInt(item.quantity) || 0;
                            const lineTotal = price * qty;
                            const rawImgSrc = item.image
                              ? item.image.startsWith("http")
                                ? item.image
                                : "../../" + item.image
                              : "https://via.placeholder.com/50?text=No+Image";
                            const imgSrc = escapeHtml(
                              sanitizeAdminMediaUrl(rawImgSrc) ||
                                "https://via.placeholder.com/50?text=No+Image",
                            );
                            const safeItemName = escapeHtml(
                              item.name || "Item",
                            );
                            return `
                            <div style="background-color: #1a1a1a; padding: 12px; border-radius: 4px; border: 1px solid #444; display: flex; gap: 12px;">
                                <img src="${imgSrc}" alt="${safeItemName}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; background-color: #333;" onerror="this.src='https://via.placeholder.com/50?text=No+Image';">
                                <div style="flex: 1;">
                                    <p class="text-light fw-semibold mb-1" style="font-size: 0.95rem;">${safeItemName}</p>
                                  <p class="text-secondary mb-0" style="font-size: 0.85rem;">Price: <strong class="text-orange">PKR ${price.toLocaleString()}</strong> x <strong>${qty}</strong> = <strong class="text-orange">PKR ${lineTotal.toLocaleString()}</strong></p>
                                </div>
                            </div>
                        `;
                          })
                          .join("")}
                    </div>
                </div>
            </td>
        `;
    tbody.appendChild(detailsRow);
  });

  $("#ordersSelectAll").prop("checked", false);
}

function displayAllOrders() {
  let orders = getAdminOrdersData();
  orders = orders.sort((a, b) => new Date(b.orderDate) - new Date(a.orderDate));

  const tbody = document.querySelector("#ordersTable tbody");
  if (!tbody) return;

  tbody.innerHTML = "";

  const statusClass = (status) => {
    const normalized = (status || "").toLowerCase();
    if (normalized === "pending") return "bg-warning text-dark";
    if (normalized === "processing" || normalized === "confirmed") {
      return "bg-info text-dark";
    }
    if (normalized === "shipped") return "bg-primary";
    if (normalized === "cancelled") return "bg-danger";
    if (normalized === "refunded") return "bg-secondary";
    return "bg-success";
  };

  if (orders.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="8" class="text-center py-4 text-secondary">No orders found</td></tr>';
    return;
  }

  orders.forEach((order, idx) => {
    const items = Array.isArray(order.items) ? order.items : [];
    const totalAmount = Number(order.total || 0);
    const rawOrderId = (order.orderId || "-").toString();
    const safeOrderId = escapeHtml(rawOrderId);
    const encodedOrderId = encodeURIComponent(rawOrderId);
    const safeCustomerName = escapeHtml(order.customerName || "Customer");
    const safeDate = escapeHtml(order.orderDate || "-");
    const safeEmail = escapeHtml(order.email || "N/A");
    const safePhone = escapeHtml(order.phone || "N/A");
    const safeAddress = escapeHtml(order.address || "N/A");
    const statusValue = (order.status || "Pending").toString();
    const paymentBadge = resolveOrderPaymentBadge(
      order.paymentStatus || "",
      order.paymentMethod || "",
    );
    const safeUserNote = escapeHtml((order.userNote || "").toString());
    const safeAdminNote = escapeHtml((order.adminNote || "").toString());
    const safeDeliveryEstimate = escapeHtml(
      (order.deliveryEstimate || "").toString(),
    );
    const isStatusUpdating = ORDER_STATUS_LOCKS.has(rawOrderId);
    const statusButtonDisabled = isStatusUpdating ? "disabled" : "";
    const invoiceUrl = `../../invoice.php?order=${encodedOrderId}`;

    const row = document.createElement("tr");
    row.className = "border-bottom border-secondary";
    row.innerHTML = `
            <td class="ps-4 py-3" onclick="event.stopPropagation();">
          <input type="checkbox" class="form-check-input order-select-row" value="${encodedOrderId}">
            </td>
            <td class="ps-4 py-3 fw-semibold text-light">${safeOrderId}</td>
            <td class="py-3 text-light">${safeCustomerName}</td>
            <td class="py-3 text-secondary small">${safeDate}</td>
            <td class="py-3 text-light fw-semibold">${formatPkr(totalAmount)}</td>
            <td class="py-3"><span class="badge ${paymentBadge.className} rounded-pill">${escapeHtml(paymentBadge.label)}</span></td>
            <td class="py-3"><span class="badge ${statusClass(statusValue)} rounded-pill">${escapeHtml(statusValue)}</span></td>
            <td class="pe-4 py-3">
              <button class="btn btn-sm btn-outline-danger" onclick="deleteOrder(decodeURIComponent('${encodedOrderId}')); event.stopPropagation();"><i class="bi bi-trash"></i></button>
            </td>
        `;
    row.style.cursor = "pointer";
    row.onclick = () => toggleOrderDetails("orderDetails-" + idx);
    tbody.appendChild(row);

    const detailsRow = document.createElement("tr");
    detailsRow.id = "orderDetails-" + idx;
    detailsRow.style.display = "none";
    detailsRow.className = "bg-dark";
    detailsRow.innerHTML = `
            <td colspan="8" class="py-3 px-4">
                <div style="background-color: #2a2a2a; padding: 20px; border-radius: 6px;">
                    <div class="row mb-3">
                        <div class="col-md-6">
                      <h6 class="text-orange mb-3 fw-bold">Customer Details</h6>
                            <p class="text-secondary mb-1"><strong class="text-light">Name:</strong> ${safeCustomerName}</p>
                            <p class="text-secondary mb-1"><strong class="text-light">Email:</strong> ${safeEmail}</p>
                            <p class="text-secondary mb-1"><strong class="text-light">Phone:</strong> ${safePhone}</p>
                            <p class="text-secondary"><strong class="text-light">Address:</strong> ${safeAddress}</p>
                        </div>
                        <div class="col-md-6">
                      <h6 class="text-orange mb-3 fw-bold">Order Summary</h6>
                            <p class="text-secondary mb-1"><strong class="text-light">Subtotal:</strong> ${formatPkr(order.subtotal || 0)}</p>
                            <p class="text-secondary mb-1"><strong class="text-light">Shipping:</strong> ${formatPkr(order.shipping || 0)}</p>
                            <p class="text-secondary mb-1"><strong class="text-light">Discount:</strong> ${Number(order.discount || 0) > 0 ? `- ${formatPkr(order.discount || 0)}` : formatPkr(0)}${order.couponCode ? ` <span class="badge bg-dark border border-secondary ms-1">${escapeHtml(order.couponCode)}</span>` : ""}</p>
                            <p class="text-secondary mb-1"><strong class="text-light">Delivery Estimate:</strong> ${safeDeliveryEstimate || "Not set"}</p>
                            <p class="text-secondary mb-1"><strong class="text-light">Customer Note:</strong> ${safeUserNote || "-"}</p>
                            <p class="text-secondary mb-1"><strong class="text-light">Admin Note:</strong> ${safeAdminNote || "-"}</p>
                            <p class="text-orange fw-bold"><strong>Total:</strong> ${formatPkr(order.total || 0)}</p>
                            <div style="margin-top: 15px;">
                        <h6 class="text-orange mb-2 fw-bold">Change Status</h6>
                                ${isStatusUpdating ? '<p class="text-warning small mb-2"><i class="bi bi-arrow-repeat"></i> Status update in progress...</p>' : ""}
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                  <button class="btn btn-sm btn-warning text-dark fw-semibold" ${statusButtonDisabled} onclick="updateOrderStatus(decodeURIComponent('${encodedOrderId}'), 'Pending'); event.stopPropagation();"><i class="bi bi-hourglass"></i> Pending</button>
                                  <button class="btn btn-sm btn-info text-dark fw-semibold" ${statusButtonDisabled} onclick="updateOrderStatus(decodeURIComponent('${encodedOrderId}'), 'Shipped'); event.stopPropagation();"><i class="bi bi-truck"></i> Shipped</button>
                                  <button class="btn btn-sm btn-success fw-semibold" ${statusButtonDisabled} onclick="updateOrderStatus(decodeURIComponent('${encodedOrderId}'), 'Delivered'); event.stopPropagation();"><i class="bi bi-check-circle"></i> Delivered</button>
                                  <button class="btn btn-sm btn-danger fw-semibold" ${statusButtonDisabled} onclick="updateOrderStatus(decodeURIComponent('${encodedOrderId}'), 'Cancelled'); event.stopPropagation();"><i class="bi bi-x-circle"></i> Cancel</button>
                                  <button class="btn btn-sm btn-outline-orange fw-semibold" ${statusButtonDisabled} onclick="updateOrderLogistics(decodeURIComponent('${encodedOrderId}')); event.stopPropagation();"><i class="bi bi-pencil-square"></i> Logistics</button>
                                  <a class="btn btn-sm btn-outline-light fw-semibold" href="${invoiceUrl}" target="_blank" rel="noopener" onclick="event.stopPropagation();"><i class="bi bi-file-earmark-pdf"></i> Invoice</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr style="border-color: #444;">
                      <h6 class="text-orange mb-3 fw-bold">Products in Order</h6>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        ${items
                          .map((item) => {
                            const price =
                              typeof item.price === "string"
                                ? parseInt(item.price.replace(/\D/g, ""))
                                : Number(item.price || 0);
                            const qty = parseInt(item.quantity, 10) || 0;
                            const lineTotal = price * qty;
                            const rawImgSrc = item.image
                              ? item.image.startsWith("http")
                                ? item.image
                                : "../../" + item.image
                              : "https://via.placeholder.com/60?text=No+Image";
                            const imgSrc = escapeHtml(
                              sanitizeAdminMediaUrl(rawImgSrc) ||
                                "https://via.placeholder.com/60?text=No+Image",
                            );
                            return `
                              <div style="background-color: #1a1a1a; padding: 12px; border-radius: 4px; border: 1px solid #444; display: flex; gap: 12px;">
                                <img src="${imgSrc}" alt="${escapeHtml(item.name || "Item")}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; background-color: #333;" onerror="this.src='https://via.placeholder.com/60?text=No+Image';">
                                <div style="flex: 1;">
                                  <p class="text-light fw-semibold mb-1" style="font-size: 0.95rem;">${escapeHtml(item.name || "Item")}</p>
                                  <p class="text-secondary mb-0" style="font-size: 0.9rem;"><strong>Unit Price:</strong> ${formatPkr(price)} | <strong>Quantity:</strong> ${qty} | <strong class="text-orange">Total: ${formatPkr(lineTotal)}</strong></p>
                                </div>
                              </div>
                            `;
                          })
                          .join("")}
                    </div>
                </div>
            </td>
        `;
    tbody.appendChild(detailsRow);
  });
}

function normalizeCustomerSearchText(value) {
  return (value || "").toString().trim().toLowerCase();
}

function customerMatchesSearch(customer, query) {
  const searchValue = normalizeCustomerSearchText(query);
  if (!searchValue) {
    return true;
  }

  const haystack = [
    customer?.name,
    customer?.username,
    customer?.email,
    customer?.phone,
  ]
    .map((entry) => normalizeCustomerSearchText(entry))
    .filter(Boolean);

  return haystack.some((entry) => entry.includes(searchValue));
}

function updateCustomerSearchSuggestions(customers, query) {
  const datalist = document.getElementById("customersSearchSuggestions");
  if (!datalist) {
    return;
  }

  datalist.innerHTML = "";
  const searchValue = normalizeCustomerSearchText(query);
  if (!searchValue) {
    return;
  }

  const suggestionSet = new Set();

  customers.forEach((customer) => {
    const options = [
      (customer?.name || "").toString().trim(),
      (customer?.username || "").toString().trim(),
      (customer?.email || "").toString().trim(),
    ].filter(Boolean);

    options.forEach((value) => {
      const normalized = normalizeCustomerSearchText(value);
      if (!normalized.includes(searchValue)) {
        return;
      }

      if (suggestionSet.size >= 14 || suggestionSet.has(value)) {
        return;
      }

      suggestionSet.add(value);
      const option = document.createElement("option");
      option.value = value;
      datalist.appendChild(option);
    });
  });
}

function displayAllCustomers() {
  const tbody = document.querySelector("#customersTable tbody");
  if (!tbody) return;

  tbody.innerHTML = "";

  const customers = getAdminCustomersData();
  const searchInput = document.getElementById("customersSearchInput");
  const searchQuery = searchInput ? searchInput.value : "";
  const filteredCustomers = customers.filter((customer) =>
    customerMatchesSearch(customer, searchQuery),
  );

  updateCustomerSearchSuggestions(customers, searchQuery);

  if (filteredCustomers.length === 0) {
    const noMatch = normalizeCustomerSearchText(searchQuery) !== "";
    tbody.innerHTML = noMatch
      ? '<tr><td colspan="9" class="text-center py-4 text-secondary">No customers match this search.</td></tr>'
      : '<tr><td colspan="9" class="text-center py-4 text-secondary">No customers found</td></tr>';
    $("#customersSelectAll").prop("checked", false);
    return;
  }

  filteredCustomers.forEach((customer) => {
    const customerId = Number(customer.id || customer.userId || 0);
    const canDelete = customerId > 0;
    const isBlacklisted = !!customer?.isBlacklisted;
    const blacklistReason = (customer?.blacklistReason || "").toString().trim();
    const customerName = escapeHtml(customer.name || "Customer");
    const customerUsernameRaw = (customer.username || "").toString().trim();
    const customerUsername = customerUsernameRaw
      ? `@${escapeHtml(customerUsernameRaw)}`
      : '<span class="text-secondary small">Private / Not set</span>';
    const customerEmailRaw = (customer.email || "").toString().trim();
    const customerPhoneRaw = (customer.phone || "").toString().trim();
    const customerEmail = escapeHtml(customerEmailRaw || "N/A");
    const customerPhone = escapeHtml(customerPhoneRaw || "N/A");
    const ordersCount = Math.max(0, parseInt(customer.orderCount, 10) || 0);
    const totalSpent = Number(customer.totalSpent || 0);
    const blacklistButton = isBlacklisted
      ? `<button class="btn btn-sm btn-outline-success unblacklist-customer-btn" data-customer-email="${escapeHtml(customerEmailRaw)}" data-customer-phone="${escapeHtml(customerPhoneRaw)}"><i class="bi bi-shield-check me-1"></i>Unblacklist</button>`
      : `<button class="btn btn-sm btn-outline-warning blacklist-customer-btn" data-customer-id="${customerId}" data-customer-name="${customerName}" data-customer-email="${escapeHtml(customerEmailRaw)}" data-customer-phone="${escapeHtml(customerPhoneRaw)}"><i class="bi bi-slash-circle me-1"></i>Blacklist</button>`;
    const blacklistDeleteButton = canDelete
      ? `<button class="btn btn-sm btn-outline-danger blacklist-delete-customer-btn" data-customer-id="${customerId}" data-customer-name="${customerName}" data-customer-email="${escapeHtml(customerEmailRaw)}" data-customer-phone="${escapeHtml(customerPhoneRaw)}"><i class="bi bi-person-fill-x me-1"></i>Blacklist + Delete</button>`
      : "";
    const deleteButton = canDelete
      ? `<button class="btn btn-sm btn-outline-danger delete-customer-btn" data-customer-id="${customerId}" data-customer-name="${customerName}"><i class="bi bi-person-x me-1"></i>Delete Profile</button>`
      : '<span class="text-secondary small">Guest checkout</span>';
    const blacklistMeta = isBlacklisted
      ? `<div class="small text-warning mt-1">Blacklisted${blacklistReason ? `: ${escapeHtml(blacklistReason)}` : ""}</div>`
      : "";

    const row = document.createElement("tr");
    row.className = "border-bottom border-secondary";
    row.innerHTML = `
            <td class="ps-4 py-3">
              <input type="checkbox" class="form-check-input customer-select-row" value="${customerId}" ${canDelete ? "" : "disabled"}>
            </td>
            <td class="ps-4 py-3">
                <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(customer.name || "Customer")}&background=ff6600&color=000" alt="Customer" class="rounded-circle" width="40" height="40">
            </td>
            <td class="py-3 text-light fw-semibold">${customerName}</td>
            <td class="py-3 text-secondary">${customerUsername}</td>
            <td class="py-3 text-secondary">${customerEmail}${blacklistMeta}</td>
            <td class="py-3 text-secondary">${customerPhone}</td>
            <td class="py-3 text-light">${ordersCount}</td>
            <td class="py-3 text-light fw-semibold">${formatPkr(totalSpent)}</td>
            <td class="pe-4 py-3 d-flex flex-wrap gap-1">${blacklistButton}${blacklistDeleteButton}${deleteButton}</td>
        `;
    tbody.appendChild(row);
  });

  $("#customersSelectAll").prop("checked", false);
}

function renderShippingConfigCard() {
  const flatFee = Math.max(0, Number(adminShippingConfig?.flatFee) || 0);
  const freeOver = Math.max(
    0,
    Number(adminShippingConfig?.freeShippingOver) || 0,
  );

  if ($("#shippingFlatFeeInput").length) {
    $("#shippingFlatFeeInput").val(flatFee.toFixed(2));
  }

  if ($("#freeShippingOverInput").length) {
    $("#freeShippingOverInput").val(freeOver.toFixed(2));
  }

  const preview = $("#shippingRulesPreview");
  if (!preview.length) {
    return;
  }

  if (flatFee <= 0) {
    preview.text("Shipping is currently free for all orders.");
    return;
  }

  if (freeOver > 0) {
    preview.text(
      `Orders below ${formatPkr(freeOver)} pay ${formatPkr(flatFee)} shipping. Orders at or above threshold get free shipping.`,
    );
    return;
  }

  preview.text(`All orders pay flat shipping: ${formatPkr(flatFee)}.`);
}

function renderBlacklistTable() {
  const tbody = $("#blacklistTable tbody");
  if (!tbody.length) {
    return;
  }

  const entries = Array.isArray(adminBlacklist) ? adminBlacklist : [];
  tbody.empty();

  if (!entries.length) {
    tbody.append(
      '<tr><td colspan="5" class="text-center py-4 text-secondary">No blacklisted contacts yet.</td></tr>',
    );
    return;
  }

  const riskBadgeForReason = (reasonValue) => {
    const reasonText = (reasonValue || "").toString().trim().toLowerCase();
    if (/fraud|chargeback|stolen|scam|abuse/i.test(reasonText)) {
      return { label: "Critical", badgeClass: "bg-danger" };
    }

    if (/spam|bot|fake|duplicate|suspicious/i.test(reasonText)) {
      return { label: "High", badgeClass: "bg-warning text-dark" };
    }

    return { label: "Watchlist", badgeClass: "bg-secondary" };
  };

  entries.forEach((entry) => {
    const id = Number(entry?.id || 0);
    const email = escapeHtml((entry?.email || "-").toString().trim() || "-");
    const phone = escapeHtml((entry?.phone || "-").toString().trim() || "-");
    const reasonRaw = (entry?.reason || "").toString().trim();
    const reason = escapeHtml(reasonRaw || "-");
    const risk = riskBadgeForReason(reasonRaw);
    const riskLabel = escapeHtml(risk.label);
    const riskBadgeClass = escapeHtml(risk.badgeClass);
    const createdAt = escapeHtml(formatDateTime(entry?.createdAt || ""));

    tbody.append(`
      <tr class="border-bottom border-secondary">
        <td class="ps-4 py-3 text-light">${email}</td>
        <td class="py-3 text-secondary">${phone}</td>
        <td class="py-3 text-secondary small">
          <span class="badge ${riskBadgeClass} text-uppercase">${riskLabel}</span>
          <div class="small mt-1">${reason}</div>
        </td>
        <td class="py-3 text-secondary small">${createdAt}</td>
        <td class="pe-4 py-3">
          <button class="btn btn-sm btn-outline-success remove-blacklist-btn" data-blacklist-id="${id}">
            <i class="bi bi-shield-check me-1"></i>Unblacklist
          </button>
        </td>
      </tr>
    `);
  });
}

function applyOrdersSummaryPayload(payload) {
  setAdminOrdersPayload(payload || {});
  displayRecentOrders();
  displayAllOrders();
  displayAllCustomers();
  calculateDashboardMetrics();
  renderAnalyticsSection();
  renderRefundRequests();
  renderBlacklistTable();
  renderShippingConfigCard();
  updateNotifications();
  $("#customersSelectAll").prop("checked", false);
}

async function saveShippingConfig() {
  const flatFee = Math.max(
    0,
    Number($("#shippingFlatFeeInput").val() || 0) || 0,
  );
  const freeShippingOver = Math.max(
    0,
    Number($("#freeShippingOverInput").val() || 0) || 0,
  );

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "save-shipping-settings",
      flat_fee: Number(flatFee.toFixed(2)),
      free_shipping_over: Number(freeShippingOver.toFixed(2)),
    });

    applyOrdersSummaryPayload(result?.payload || {});
    showNotification(result?.message || "Shipping rules updated.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to update shipping rules.",
      "danger",
    );
  }
}

function findBlacklistEntryByContact(email, phone) {
  const normalizedEmail = (email || "").toString().trim().toLowerCase();
  const normalizedPhone = (phone || "").toString().trim();

  const entries = Array.isArray(adminBlacklist) ? adminBlacklist : [];
  return (
    entries.find((entry) => {
      const entryEmail = (entry?.email || "").toString().trim().toLowerCase();
      const entryPhone = (entry?.phone || "").toString().trim();

      if (normalizedEmail !== "" && entryEmail === normalizedEmail) {
        return true;
      }

      if (normalizedPhone !== "" && entryPhone === normalizedPhone) {
        return true;
      }

      return false;
    }) || null
  );
}

async function createBlacklistEntry(payload, successMessage) {
  const result = await adminPostJson(ADMIN_ORDERS_API, {
    action: "add-blacklist",
    ...payload,
  });

  applyOrdersSummaryPayload(result?.payload || {});
  showNotification(result?.message || successMessage, "success");
}

async function addBlacklistFromForm() {
  const email = (($("#blacklistEmailInput").val() || "") + "").trim();
  const phone = (($("#blacklistPhoneInput").val() || "") + "").trim();
  const reason = (($("#blacklistReasonInput").val() || "") + "").trim();

  if (email === "" && phone === "") {
    showNotification("Enter an email or phone number.", "warning");
    return;
  }

  try {
    await createBlacklistEntry(
      {
        email,
        phone,
        reason,
      },
      "Contact blacklisted.",
    );

    $("#blacklistEmailInput").val("");
    $("#blacklistPhoneInput").val("");
    $("#blacklistReasonInput").val("");
  } catch (error) {
    showNotification(
      error?.message || "Unable to blacklist contact.",
      "danger",
    );
  }
}

async function whitelistContactFromForm() {
  const email = (($("#whitelistEmailInput").val() || "") + "").trim();
  const phone = (($("#whitelistPhoneInput").val() || "") + "").trim();

  if (email === "" && phone === "") {
    showNotification("Enter an email or phone number to whitelist.", "warning");
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "remove-blacklist-contact",
      email,
      phone,
    });

    applyOrdersSummaryPayload(result?.payload || {});
    showNotification(result?.message || "Contact whitelisted.", "success");
    $("#whitelistEmailInput").val("");
    $("#whitelistPhoneInput").val("");
  } catch (error) {
    const localMatch = findBlacklistEntryByContact(email, phone);
    if (localMatch && Number(localMatch.id || 0) > 0) {
      await removeBlacklistById(localMatch.id);
      $("#whitelistEmailInput").val("");
      $("#whitelistPhoneInput").val("");
      return;
    }

    showNotification(
      error?.message || "Unable to whitelist contact.",
      "danger",
    );
  }
}

async function blacklistCustomerByIdentity(
  customerId,
  customerName,
  email,
  phone,
  deleteAfter = false,
) {
  const safeCustomerId = Number(customerId || 0) || 0;
  const safeName = (customerName || "Customer").toString().trim() || "Customer";
  const safeEmail = (email || "").toString().trim();
  const safePhone = (phone || "").toString().trim();

  if (safeCustomerId <= 0 && safeEmail === "" && safePhone === "") {
    showNotification("Customer contact data is missing.", "warning");
    return;
  }

  const confirmText = deleteAfter
    ? `Blacklist and delete ${safeName}?`
    : `Blacklist ${safeName}?`;
  const confirmed = await showCustomConfirmDialog(
    confirmText,
    deleteAfter ? "Blacklist + Delete" : "Blacklist Customer",
  );
  if (!confirmed) {
    return;
  }

  const reason = await showCustomPromptDialog(
    "Optional reason for blacklist entry:",
    "",
    "Blacklist Reason",
  );

  try {
    await createBlacklistEntry(
      {
        customer_id: safeCustomerId,
        email: safeEmail,
        phone: safePhone,
        reason: (reason || "").toString().trim(),
      },
      "Customer blacklisted.",
    );

    if (!deleteAfter || safeCustomerId <= 0) {
      return;
    }

    const deleteResult = await adminPostJson(ADMIN_ORDERS_API, {
      action: "delete-customers",
      customer_ids: [safeCustomerId],
    });

    applyOrdersSummaryPayload(deleteResult?.payload || {});
    showNotification(
      deleteResult?.message || "Customer blacklisted and deleted.",
      "success",
    );
  } catch (error) {
    showNotification(
      error?.message || "Unable to process blacklist action.",
      "danger",
    );
  }
}

async function removeBlacklistById(blacklistId) {
  const id = Number(blacklistId || 0) || 0;
  if (id <= 0) {
    showNotification("Invalid blacklist id.", "warning");
    return;
  }

  const confirmed = await showCustomConfirmDialog(
    "Remove this blacklist entry?",
    "Unblacklist Contact",
  );
  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "remove-blacklist",
      blacklist_id: id,
    });

    applyOrdersSummaryPayload(result?.payload || {});
    showNotification(result?.message || "Blacklist entry removed.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to remove blacklist entry.",
      "danger",
    );
  }
}

async function removeBlacklistByContact(email, phone) {
  const entry = findBlacklistEntryByContact(email, phone);
  if (!entry) {
    showNotification(
      "No active blacklist entry found for this customer.",
      "warning",
    );
    return;
  }

  await removeBlacklistById(entry.id);
}

function deleteOrder(orderId) {
  showCustomConfirmDialog(
    "Are you sure you want to cancel this order?",
    "Cancel Order",
  ).then((confirmed) => {
    if (confirmed) {
      updateOrderStatus(orderId, "Cancelled");
    }
  });
}

function decodeSelectedOrderNumber(value) {
  const raw = (value || "").toString().trim();
  if (!raw) return "";

  try {
    return decodeURIComponent(raw);
  } catch (error) {
    return raw;
  }
}

function selectedOrderNumbers() {
  return $(".order-select-row:checked")
    .map(function () {
      return decodeSelectedOrderNumber($(this).val());
    })
    .get()
    .filter(Boolean);
}

function selectedCustomerIds() {
  return $(".customer-select-row:checked")
    .map(function () {
      return parseInt($(this).val(), 10) || 0;
    })
    .get()
    .filter((id) => id > 0);
}

async function deleteSingleCustomer(customerId, customerName = "Customer") {
  const safeCustomerId = parseInt(customerId, 10) || 0;
  if (safeCustomerId <= 0) {
    showNotification("Invalid customer id.", "warning");
    return;
  }

  const confirmed = await showCustomConfirmDialog(
    `Delete profile for ${customerName}? This removes the account and linked records.`,
    "Delete Customer Profile",
  );
  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "delete-customers",
      customer_ids: [safeCustomerId],
    });

    setAdminOrdersPayload(result?.payload || {});
    displayRecentOrders();
    displayAllOrders();
    displayAllCustomers();
    calculateDashboardMetrics();
    renderAnalyticsSection();
    renderRefundRequests();
    updateNotifications();
    $("#customersSelectAll").prop("checked", false);

    showNotification(result?.message || "Customer profile deleted.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to delete customer profile.",
      "danger",
    );
  }
}

async function bulkDeleteOrders() {
  const orderNumbers = selectedOrderNumbers();
  if (!orderNumbers.length) {
    showNotification("Select at least one order to delete.", "warning");
    return;
  }

  const confirmed = await showCustomConfirmDialog(
    `Delete ${orderNumbers.length} selected order(s)?`,
    "Delete Selected Orders",
  );
  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "delete-orders",
      order_numbers: orderNumbers,
    });

    setAdminOrdersPayload(result?.payload || {});
    displayRecentOrders();
    displayAllOrders();
    displayAllCustomers();
    calculateDashboardMetrics();
    renderAnalyticsSection();
    renderRefundRequests();
    updateNotifications();
    $("#ordersSelectAll").prop("checked", false);

    showNotification(result?.message || "Selected orders deleted.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to delete selected orders.",
      "danger",
    );
  }
}

async function bulkDeleteCustomers() {
  const customerIds = selectedCustomerIds();
  if (!customerIds.length) {
    showNotification("Select at least one customer to delete.", "warning");
    return;
  }

  const confirmed = await showCustomConfirmDialog(
    `Delete ${customerIds.length} selected customer(s)?`,
    "Delete Selected Customers",
  );
  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "delete-customers",
      customer_ids: customerIds,
    });

    setAdminOrdersPayload(result?.payload || {});
    displayRecentOrders();
    displayAllOrders();
    displayAllCustomers();
    calculateDashboardMetrics();
    renderAnalyticsSection();
    renderRefundRequests();
    updateNotifications();
    $("#customersSelectAll").prop("checked", false);

    showNotification(
      result?.message || "Selected customers deleted.",
      "success",
    );
  } catch (error) {
    showNotification(
      error?.message || "Unable to delete selected customers.",
      "danger",
    );
  }
}

async function updateOrderStatus(orderId, newStatus) {
  const normalizedOrderId = (orderId || "").toString();
  if (!normalizedOrderId) {
    showNotification("Invalid order id.", "danger");
    return;
  }

  if (ORDER_STATUS_LOCKS.has(normalizedOrderId)) {
    showNotification(
      "A status update is already in progress for this order.",
      "warning",
    );
    return;
  }

  const orders = getAdminOrdersData();
  const targetOrder = orders.find(
    (order) => (order.orderId || "").toString() === normalizedOrderId,
  );

  if (!targetOrder) {
    showNotification("Order not found.", "danger");
    return;
  }

  const oldStatus = targetOrder.status;
  if (oldStatus === newStatus) {
    showNotification("Order is already in the selected status.", "warning");
    return;
  }

  ORDER_STATUS_LOCKS.add(normalizedOrderId);
  displayAllOrders();

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "update-status",
      order_number: normalizedOrderId,
      status: newStatus,
      refresh_mode: "minimal",
    });

    const payload = result?.payload || {};
    if (Array.isArray(payload?.orders)) {
      setAdminOrdersPayload(payload);
    } else {
      applyOrderStatusPatch(payload);
    }

    displayRecentOrders();
    calculateDashboardMetrics();
    renderAnalyticsSection();
    updateNotifications();

    showStatusNotification(normalizedOrderId, oldStatus, newStatus);
  } catch (error) {
    showNotification(
      error?.message || "Unable to update order status.",
      "danger",
    );
  } finally {
    ORDER_STATUS_LOCKS.delete(normalizedOrderId);
    displayAllOrders();
  }
}

async function updateOrderLogistics(orderId) {
  const orders = getAdminOrdersData();
  const targetOrder = orders.find((order) => order.orderId === orderId);

  if (!targetOrder) {
    showNotification("Order not found.", "danger");
    return;
  }

  const estimateDefault = (targetOrder.deliveryEstimate || "")
    .toString()
    .replace(" ", "T")
    .slice(0, 16);

  const deliveryEstimate = await showCustomPromptDialog(
    "Set delivery estimate (YYYY-MM-DD or YYYY-MM-DDTHH:MM). Leave empty to clear.",
    estimateDefault,
    "Update Delivery Estimate",
  );

  if (deliveryEstimate === null) {
    return;
  }

  const adminNote = await showCustomPromptDialog(
    "Add or update an internal admin note for this order.",
    (targetOrder.adminNote || "").toString(),
    "Update Admin Note",
  );

  if (adminNote === null) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "update-logistics",
      order_number: orderId,
      delivery_estimate: (deliveryEstimate || "").toString().trim(),
      admin_note: (adminNote || "").toString().trim(),
    });

    setAdminOrdersPayload(result?.payload || {});
    displayRecentOrders();
    displayAllOrders();
    displayAllCustomers();
    calculateDashboardMetrics();
    renderAnalyticsSection();
    renderRefundRequests();
    updateNotifications();

    if ($("#emailSection").length) {
      emailDirectory = buildEmailDirectory();
      renderEmailRecipients();
    }

    showNotification(result?.message || "Order logistics updated.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to update order logistics.",
      "danger",
    );
  }
}

function toggleOrderDetails(elementId) {
  const element = document.getElementById(elementId);
  if (element) {
    element.style.display =
      element.style.display === "none" ? "table-row" : "none";
  }
}
function buildDefaultSiteSettings() {
  return {
    brand: {
      name: "COMMERZA",
      logo: "frontend/assets/images/logo/commerza-logo.webp",
      favicon: "frontend/assets/images/favicon/commerza-watches-icon.ico",
    },
    contact: {
      address: "Barrage Colony, HYD, PK",
      email: "commerza.ahmer@gmail.com",
      phone: "+92 314 8396293",
    },
    ticker: {
      enabled: true,
      messages: [
        "SALE IS LIVE: PREMIUM AUTOMATIC WATCHES UP TO 20% OFF",
        "COLLECTION UPDATE: NEW SKELETON SERIES NOW AVAILABLE",
        "FREE SHIPPING: NATIONWIDE DELIVERY ON ALL PREMIUM ORDERS",
      ],
    },
    socialLinks: [
      {
        id: 1,
        label: "Facebook",
        url: "https://www.facebook.com/commerza.ahmer",
        icon: "bi bi-facebook",
      },
      {
        id: 2,
        label: "X",
        url: "https://x.com/commerza_ahmer",
        icon: "bi bi-twitter",
      },
      {
        id: 3,
        label: "Instagram",
        url: "https://www.instagram.com/commerza.ahmer",
        icon: "bi bi-instagram",
      },
    ],
    sliderImages: [
      {
        id: 1,
        image: "frontend/assets/images/slider/watch-banner-chronograph.webp",
        alt: "luxury chronograph watch banner premium collection",
        label: "Premium Collection",
        heading: "Chronograph Precision",
        text: "Engineered movements with dual finish cases",
        buttonText: "Explore Now",
        buttonLink: "shop-category-a.php",
      },
      {
        id: 2,
        image: "frontend/assets/images/slider/watch-banner-collection.webp",
        alt: "complete watch collection showcase all styles",
        label: "Complete Series",
        heading: "Every Style, One Place",
        text: "From minimalist to bold statement pieces",
        buttonText: "View Collection",
        buttonLink: "shop-category-b.php",
      },
      {
        id: 3,
        image: "frontend/assets/images/slider/watch-banner-premium.webp",
        alt: "premium watches exclusive luxury timepieces",
        label: "Exclusive Launch",
        heading: "Limited Editions",
        text: "Hand assembled luxury with skeleton dials",
        buttonText: "Shop Limited",
        buttonLink: "shop-category-b.php",
      },
    ],
    featuredVideos: {
      home: "frontend/assets/videos/slider/steel_watch_1.mp4",
      categoryA:
        "frontend/assets/videos/products/smart/automatic_watches_carousel.mp4",
    },
    pageMeta: [],
  };
}

function loadSiteSettings() {
  const defaults = buildDefaultSiteSettings();
  const stored = sessionStorage.getItem(SITE_SETTINGS_KEY);
  if (!stored) return defaults;

  try {
    const parsed = JSON.parse(stored);
    return {
      ...defaults,
      ...parsed,
      brand: { ...defaults.brand, ...(parsed.brand || {}) },
      contact: { ...defaults.contact, ...(parsed.contact || {}) },
      ticker: {
        ...defaults.ticker,
        ...(parsed.ticker || {}),
        messages:
          Array.isArray(parsed.ticker?.messages) &&
          parsed.ticker.messages.length
            ? parsed.ticker.messages
            : defaults.ticker.messages,
      },
      socialLinks: Array.isArray(parsed.socialLinks)
        ? parsed.socialLinks
        : defaults.socialLinks,
      sliderImages: Array.isArray(parsed.sliderImages)
        ? parsed.sliderImages
        : defaults.sliderImages,
      featuredVideos: {
        ...defaults.featuredVideos,
        ...(parsed.featuredVideos || {}),
      },
      pageMeta: normalizeSeoPageMetaList(
        Array.isArray(parsed.pageMeta) ? parsed.pageMeta : defaults.pageMeta,
      ),
    };
  } catch (error) {
    console.warn("Invalid site settings, using defaults");
    return defaults;
  }
}

function saveSiteSettings() {
  sessionStorage.setItem(SITE_SETTINGS_KEY, JSON.stringify(siteSettings));
}

function applyWebsiteSettingsPayload(payload) {
  const defaults = buildDefaultSiteSettings();
  const source = payload && typeof payload === "object" ? payload : {};

  siteSettings = {
    ...defaults,
    ...source,
    brand: { ...defaults.brand, ...(source.brand || {}) },
    contact: { ...defaults.contact, ...(source.contact || {}) },
    ticker: {
      ...defaults.ticker,
      ...(source.ticker || {}),
      messages:
        Array.isArray(source.ticker?.messages) && source.ticker.messages.length
          ? source.ticker.messages
          : defaults.ticker.messages,
    },
    socialLinks: Array.isArray(source.socialLinks)
      ? source.socialLinks
      : defaults.socialLinks,
    sliderImages: Array.isArray(source.sliderImages)
      ? source.sliderImages
      : defaults.sliderImages,
    featuredVideos: {
      ...defaults.featuredVideos,
      ...(source.featuredVideos || {}),
    },
    pageMeta: normalizeSeoPageMetaList(
      Array.isArray(source.pageMeta) ? source.pageMeta : defaults.pageMeta,
    ),
  };

  nextSocialId =
    Math.max(0, ...siteSettings.socialLinks.map((link) => link.id || 0)) + 1;
  nextSliderId =
    Math.max(0, ...siteSettings.sliderImages.map((item) => item.id || 0)) + 1;

  $("#siteAddress").val(siteSettings.contact.address || "");
  $("#siteEmail").val(siteSettings.contact.email || "");
  $("#sitePhone").val(siteSettings.contact.phone || "");
  $("#siteName").val(siteSettings.brand?.name || "");
  $("#siteLogo").val(siteSettings.brand?.logo || "");
  $("#siteFavicon").val(siteSettings.brand?.favicon || "");

  $("#tickerEnabled").prop("checked", siteSettings.ticker?.enabled !== false);
  $("#tickerMessages").val((siteSettings.ticker?.messages || []).join("\n"));
  $("#homeFeatureVideo").val(siteSettings.featuredVideos?.home || "");
  $("#categoryAFeatureVideo").val(siteSettings.featuredVideos?.categoryA || "");

  renderSeoPageOptions();
  renderSeoMetaTable();
  refreshSeoMetaEditor();
  renderSocialLinksTable();
  renderSliderTable();
  saveSiteSettings();

  if (typeof window.applyAdminBranding === "function") {
    window.applyAdminBranding();
  }
}

async function loadWebsiteSettingsFromApi(silent = false) {
  try {
    const response = await fetch(`${ADMIN_WEBSITE_API}?action=get`, {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store",
    });

    const result = await response.json();
    if (!response.ok || !result?.ok) {
      throw new Error(result?.message || "Unable to load website settings.");
    }

    applyWebsiteSettingsPayload(result?.payload || null);
    return true;
  } catch (error) {
    if (!silent) {
      showNotification(
        error?.message || "Unable to load website settings.",
        "danger",
      );
    }
    return false;
  }
}

function initWebsiteSettings() {
  if (!$("#websiteSection").length) return;

  applyWebsiteSettingsPayload(loadSiteSettings());
  loadWebsiteSettingsFromApi(true);
}

function resetTickerForm() {
  const defaults = buildDefaultSiteSettings();
  $("#tickerEnabled").prop("checked", defaults.ticker.enabled);
  $("#tickerMessages").val(defaults.ticker.messages.join("\n"));
}

function normalizeSeoPageMetaEntry(entry) {
  const source = entry && typeof entry === "object" ? entry : {};
  return {
    page: (source.page || "").toString().trim().toLowerCase(),
    meta_title: (source.meta_title || "").toString().trim(),
    meta_description: (source.meta_description || "").toString().trim(),
    canonical_url: (source.canonical_url || "").toString().trim(),
    og_title: (source.og_title || "").toString().trim(),
    og_description: (source.og_description || "").toString().trim(),
    og_image: (source.og_image || "").toString().trim(),
    json_ld: (source.json_ld || "").toString().trim(),
    updated_at: (source.updated_at || "").toString().trim(),
  };
}

function normalizeSeoPageMetaList(list) {
  if (!Array.isArray(list)) {
    return [];
  }

  const unique = [];
  const seen = new Set();
  list.forEach((entry) => {
    const normalized = normalizeSeoPageMetaEntry(entry);
    if (!normalized.page || seen.has(normalized.page)) {
      return;
    }

    seen.add(normalized.page);
    unique.push(normalized);
  });

  return unique;
}

function getSeoPageLabel(pageKey) {
  const key = (pageKey || "").toString().trim().toLowerCase();
  const match = ADMIN_PAGES.find(
    (page) => (page.id || "").toString().toLowerCase() === key,
  );

  return match ? match.label : key;
}

function renderSeoPageOptions() {
  const select = $("#seoPageSelect");
  if (!select.length) {
    return;
  }

  const currentValue = (select.val() || "").toString().trim().toLowerCase();
  select.empty();

  ADMIN_PAGES.forEach((page) => {
    const pageId = (page.id || "").toString().trim();
    if (!pageId) {
      return;
    }

    const label = (page.label || pageId).toString();
    select.append(
      `<option value="${escapeHtml(pageId)}">${escapeHtml(label)} (${escapeHtml(pageId)})</option>`,
    );
  });

  const fallback = (ADMIN_PAGES[0]?.id || "").toString();
  const nextValue = currentValue || fallback;
  if (nextValue) {
    select.val(nextValue);
  }

  if (!select.val() && fallback) {
    select.val(fallback);
  }
}

function getSelectedSeoPage() {
  const selectValue = ($("#seoPageSelect").val() || "").toString().trim();
  if (selectValue) {
    return selectValue.toLowerCase();
  }

  return ((ADMIN_PAGES[0] && ADMIN_PAGES[0].id) || "").toString().toLowerCase();
}

function setSeoFormValues(entry) {
  const source = normalizeSeoPageMetaEntry(entry);
  $("#seoMetaTitleInput").val(source.meta_title || "");
  $("#seoMetaDescriptionInput").val(source.meta_description || "");
  $("#seoCanonicalInput").val(source.canonical_url || "");
  $("#seoOgTitleInput").val(source.og_title || "");
  $("#seoOgDescriptionInput").val(source.og_description || "");
  $("#seoOgImageInput").val(source.og_image || "");

  let jsonLd = source.json_ld || "";
  if (jsonLd) {
    try {
      jsonLd = JSON.stringify(JSON.parse(jsonLd), null, 2);
    } catch (error) {
      jsonLd = source.json_ld || "";
    }
  }

  $("#seoJsonLdInput").val(jsonLd);
}

function refreshSeoMetaEditor() {
  if (!$("#seoPageSelect").length) {
    return;
  }

  const page = getSelectedSeoPage();
  const entries = normalizeSeoPageMetaList(siteSettings?.pageMeta || []);
  const currentEntry = entries.find((entry) => entry.page === page) || null;

  setSeoFormValues(currentEntry || {});

  const pageLabel = getSeoPageLabel(page);
  const message = currentEntry
    ? `Editing SEO metadata for ${pageLabel}. Last update: ${formatDateTime(currentEntry.updated_at || "") || "recent"}.`
    : `No saved SEO metadata for ${pageLabel}. Fill details and click Save SEO Meta.`;

  $("#seoMetaPreview").text(message);
}

function renderSeoMetaTable() {
  const tbody = $("#seoMetaTable tbody");
  if (!tbody.length) {
    return;
  }

  const rows = normalizeSeoPageMetaList(siteSettings?.pageMeta || []).sort(
    (a, b) => a.page.localeCompare(b.page),
  );

  tbody.empty();
  if (!rows.length) {
    tbody.append(
      '<tr><td colspan="3" class="text-center py-4 text-secondary">No page metadata configured.</td></tr>',
    );
    return;
  }

  rows.forEach((entry) => {
    const page = escapeHtml(entry.page || "");
    const pageLabel = escapeHtml(getSeoPageLabel(entry.page || ""));
    const metaTitle = escapeHtml(entry.meta_title || "-");
    tbody.append(`
      <tr class="border-bottom border-secondary">
        <td class="ps-4 py-3 text-light">
          <div class="fw-semibold">${pageLabel}</div>
          <small class="text-secondary">${page}</small>
        </td>
        <td class="py-3 text-secondary small">${metaTitle}</td>
        <td class="pe-4 py-3">
          <button class="btn btn-sm btn-outline-orange me-1 seo-meta-edit-btn" data-seo-page="${page}"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-outline-danger seo-meta-delete-btn" data-seo-page="${page}"><i class="bi bi-trash"></i></button>
        </td>
      </tr>
    `);
  });
}

function getSeoMetaPayloadFromForm() {
  return {
    page: getSelectedSeoPage(),
    meta_title: ($("#seoMetaTitleInput").val() || "").toString().trim(),
    meta_description: ($("#seoMetaDescriptionInput").val() || "")
      .toString()
      .trim(),
    canonical_url: ($("#seoCanonicalInput").val() || "").toString().trim(),
    og_title: ($("#seoOgTitleInput").val() || "").toString().trim(),
    og_description: ($("#seoOgDescriptionInput").val() || "").toString().trim(),
    og_image: ($("#seoOgImageInput").val() || "").toString().trim(),
    json_ld: ($("#seoJsonLdInput").val() || "").toString().trim(),
  };
}

async function saveSeoMetaFromForm() {
  const payload = getSeoMetaPayloadFromForm();
  if (!payload.page) {
    showNotification("Select a page first.", "warning");
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_WEBSITE_API, {
      action: "save-page-meta",
      ...payload,
    });

    applyWebsiteSettingsPayload(result?.payload || null);
    $("#seoPageSelect").val(payload.page);
    refreshSeoMetaEditor();
    showNotification(result?.message || "SEO metadata saved.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to save SEO metadata.",
      "danger",
    );
  }
}

async function deleteSeoMetaForPage(pageKey = "") {
  const page = (pageKey || getSelectedSeoPage())
    .toString()
    .trim()
    .toLowerCase();
  if (!page) {
    showNotification("Select a page first.", "warning");
    return;
  }

  const pageLabel = getSeoPageLabel(page);
  const confirmed = await showCustomConfirmDialog(
    `Delete SEO metadata for ${pageLabel}?`,
    "Delete SEO Meta",
  );

  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_WEBSITE_API, {
      action: "delete-page-meta",
      page,
    });

    applyWebsiteSettingsPayload(result?.payload || null);
    $("#seoPageSelect").val(page);
    refreshSeoMetaEditor();
    showNotification(result?.message || "SEO metadata deleted.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to delete SEO metadata.",
      "danger",
    );
  }
}

function resetSeoMetaForm() {
  refreshSeoMetaEditor();
}

function renderSocialIconPreview(iconValue) {
  const icon = (iconValue || "").toString().trim();
  if (!icon) {
    return '<span class="text-secondary small">-</span>';
  }

  if (/^bi\s+bi-[a-z0-9-]+$/i.test(icon)) {
    return `<i class="${escapeHtml(icon)} text-orange"></i>`;
  }

  const previewPath = resolveAdminImagePath(icon);
  return `<img src="${escapeHtml(previewPath)}" alt="Icon" style="width: 22px; height: 22px; object-fit: contain;" onerror="this.style.display='none'; this.nextElementSibling && (this.nextElementSibling.style.display='inline');"><span class="text-secondary small" style="display:none;">Invalid</span>`;
}

function renderSocialLinksTable() {
  const tbody = $("#socialLinksTable tbody");
  if (tbody.length === 0) return;
  tbody.empty();

  if (!siteSettings.socialLinks || siteSettings.socialLinks.length === 0) {
    tbody.append(
      '<tr><td colspan="4" class="text-center py-4 text-secondary">No social links added</td></tr>',
    );
    return;
  }

  siteSettings.socialLinks.forEach((link) => {
    const safeLabel = escapeHtml(link.label || "");
    const safeUrl = escapeHtml(link.url || "");
    const iconPreview = renderSocialIconPreview(link.icon || "");
    tbody.append(`
            <tr class="border-bottom border-secondary">
          <td class="ps-4 py-3 text-light fw-semibold">${safeLabel}</td>
          <td class="py-3 text-secondary small">${safeUrl}</td>
          <td class="py-3">${iconPreview}</td>
                <td class="pe-4 py-3">
                    <button class="btn btn-sm btn-outline-orange me-1" onclick="editSocialLink(${link.id})"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteSocialLink(${link.id})"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
        `);
  });
}

function editSocialLink(id) {
  const link = siteSettings.socialLinks.find((item) => item.id === id);
  if (!link) return;
  $("#socialId").val(link.id);
  $("#socialLabel").val(link.label);
  $("#socialUrl").val(link.url);
  $("#socialIcon").val(link.icon);
  $("#saveSocialBtn").html('<i class="bi bi-save2 me-1"></i>Update Social');
}

async function deleteSocialLink(id) {
  const confirmed = await showCustomConfirmDialog(
    "Delete this social link?",
    "Delete Social Link",
  );
  if (!confirmed) return;

  adminPostJson(ADMIN_WEBSITE_API, {
    action: "delete-social",
    id,
  })
    .then((result) => {
      applyWebsiteSettingsPayload(result?.payload || null);
      resetSocialForm();
      showNotification(result?.message || "Social link deleted!", "success");
    })
    .catch((error) => {
      showNotification(
        error?.message || "Unable to delete social link.",
        "danger",
      );
    });
}

function resetSocialForm() {
  $("#socialId").val("");
  $("#socialLabel").val("");
  $("#socialUrl").val("");
  $("#socialIcon").val("");
  $("#saveSocialBtn").html('<i class="bi bi-plus-circle me-1"></i>Add Social');
}

function resolveAdminImagePath(path) {
  if (!path) return "";
  if (path.startsWith("http")) return path;
  const cleaned = path.replace(/^\.\//, "");
  return `../../${cleaned}`;
}

function renderSliderTable() {
  const tbody = $("#sliderTable tbody");
  if (tbody.length === 0) return;
  tbody.empty();

  if (!siteSettings.sliderImages || siteSettings.sliderImages.length === 0) {
    tbody.append(
      '<tr><td colspan="4" class="text-center py-4 text-secondary">No slides added</td></tr>',
    );
    return;
  }

  siteSettings.sliderImages.forEach((item) => {
    const preview = resolveAdminImagePath(item.image);
    const safeHeading = escapeHtml(item.heading || "Untitled");
    const safeAlt = escapeHtml(item.alt || "Slide");
    const safeButtonText = escapeHtml(item.buttonText || "CTA");
    const safeButtonLink = escapeHtml(item.buttonLink || "#");
    const videoBadge = item.video
      ? '<span class="badge bg-secondary ms-2">Video</span>'
      : "";
    tbody.append(`
            <tr class="border-bottom border-secondary">
                <td class="ps-4 py-3"><img src="${preview}" alt="${safeAlt}" style="width: 90px; height: 50px; object-fit: cover; border-radius: 6px;" onerror="this.src='assets/images/products/placeholder.webp'"></td>
                <td class="py-3 text-light fw-semibold">${safeHeading}${videoBadge}</td>
                <td class="py-3 text-secondary small">${safeButtonText} -> ${safeButtonLink}</td>
                <td class="pe-4 py-3">
                    <button class="btn btn-sm btn-outline-orange me-1" onclick="editSlider(${item.id})"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteSlider(${item.id})"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
        `);
  });
}

function editSlider(id) {
  const item = siteSettings.sliderImages.find((slide) => slide.id === id);
  if (!item) return;
  $("#sliderId").val(item.id);
  $("#sliderImage").val(item.image);
  $("#sliderAlt").val(item.alt || "");
  $("#sliderLabel").val(item.label || "");
  $("#sliderHeading").val(item.heading || "");
  $("#sliderText").val(item.text || "");
  $("#sliderButtonText").val(item.buttonText || "");
  $("#sliderButtonLink").val(item.buttonLink || "");
  $("#sliderVideo").val(item.video || "");
  $("#saveSliderBtn").html('<i class="bi bi-save2 me-1"></i>Update Slide');
}

async function deleteSlider(id) {
  const confirmed = await showCustomConfirmDialog(
    "Delete this slide?",
    "Delete Slide",
  );
  if (!confirmed) return;

  adminPostJson(ADMIN_WEBSITE_API, {
    action: "delete-slider",
    id,
  })
    .then((result) => {
      applyWebsiteSettingsPayload(result?.payload || null);
      resetSliderForm();
      showNotification(result?.message || "Slide deleted!", "success");
    })
    .catch((error) => {
      showNotification(error?.message || "Unable to delete slide.", "danger");
    });
}

function resetSliderForm() {
  $("#sliderId").val("");
  $("#sliderImage").val("");
  $("#sliderAlt").val("");
  $("#sliderLabel").val("");
  $("#sliderHeading").val("");
  $("#sliderText").val("");
  $("#sliderButtonText").val("");
  $("#sliderButtonLink").val("");
  $("#sliderVideo").val("");
  $("#saveSliderBtn").html('<i class="bi bi-plus-circle me-1"></i>Add Slide');
}

function getSuppressedEmails() {
  const list = readJsonStorage(EMAIL_SUPPRESSED_KEY, []);
  if (!Array.isArray(list)) return new Set();
  return new Set(
    list.map((email) => normalizeEmailValue(email)).filter(Boolean),
  );
}

function saveSuppressedEmails(set) {
  sessionStorage.setItem(EMAIL_SUPPRESSED_KEY, JSON.stringify(Array.from(set)));
}
