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
const ADMIN_PRODUCTS_SYNC_API = "../backend/api/catalog/products_sync_api.php";
const ADMIN_SECURITY_API = "../backend/api/security/security_api.php";
const ADMIN_ORDERS_API = "../backend/api/commerce/orders_api.php";
const ADMIN_VIEWERS_API = "../backend/api/analytics/viewers_api.php";
const ADMIN_WEBSITE_API = "../backend/api/content/website_api.php";
const ADMIN_MEDIA_API = "../backend/api/content/media_api.php";
const ADMIN_COUPONS_API = "../backend/api/marketing/coupons_api.php";
const ADMIN_REVIEWS_API = "../backend/api/marketing/reviews_api.php";
const ADMIN_PERMISSION_SET = admin_build_permission_set(
  ADMIN_RUNTIME?.admin?.permissions || ADMIN_RUNTIME?.permissions || [],
);
const ADMIN_HIDDEN_TAB_SET = new Set(
  (Array.isArray(ADMIN_RUNTIME?.admin?.hiddenTabs)
    ? ADMIN_RUNTIME.admin.hiddenTabs
    : Array.isArray(ADMIN_RUNTIME?.hiddenTabs)
      ? ADMIN_RUNTIME.hiddenTabs
      : []
  )
    .map((tabId) => (tabId || "").toString().trim().toLowerCase())
    .filter((tabId) => tabId !== ""),
);
const ADMIN_TAB_CATALOG = Array.isArray(ADMIN_RUNTIME?.tabCatalog)
  ? ADMIN_RUNTIME.tabCatalog
  : [];
let adminOrders = [];
let adminCustomers = [];
let adminMetrics = null;
let adminRefunds = [];
let adminBlacklist = [];
let adminBlacklistNoticeVisible = true;
let adminShippingConfig = {
  flatFee: 1000,
  freeShippingOver: 500,
};
let adminCoupons = [];
let couponSearchQuery = "";
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

function admin_normalize_permission(permission) {
  const normalized = (permission || "").toString().trim().toLowerCase();
  if (normalized === "") {
    return "";
  }

  if (normalized === "*") {
    return "*";
  }

  return /^[a-z0-9_]+\.[a-z0-9_*]+$/.test(normalized) ? normalized : "";
}

function admin_build_permission_set(permissions) {
  const set = new Set();
  const list = Array.isArray(permissions) ? permissions : [];

  list.forEach((permission) => {
    const normalized = admin_normalize_permission(permission);
    if (normalized !== "") {
      set.add(normalized);
    }
  });

  return set;
}

function admin_has_permission(permission) {
  const normalized = admin_normalize_permission(permission);
  if (normalized === "") {
    return true;
  }

  if (ADMIN_PERMISSION_SET.has("*")) {
    return true;
  }

  if (ADMIN_PERMISSION_SET.has(normalized)) {
    return true;
  }

  const segments = normalized.split(".");
  if (segments.length !== 2) {
    return false;
  }

  const [prefix, scope] = segments;
  if (prefix === "" || scope === "") {
    return false;
  }

  if (ADMIN_PERMISSION_SET.has(`${prefix}.*`)) {
    return true;
  }

  if (scope === "view" && ADMIN_PERMISSION_SET.has(`${prefix}.manage`)) {
    return true;
  }

  return false;
}

function admin_has_any_permission(permissions) {
  const list = Array.isArray(permissions) ? permissions : [];
  if (!list.length) {
    return true;
  }

  return list.some((permission) => admin_has_permission(permission));
}

function admin_tab_metadata(tabId) {
  const normalizedTabId = (tabId || "").toString().trim().toLowerCase();
  if (normalizedTabId === "") {
    return null;
  }

  return (
    ADMIN_TAB_CATALOG.find(
      (entry) =>
        (entry?.id || "").toString().trim().toLowerCase() === normalizedTabId,
    ) || null
  );
}

function admin_can_access_tab(tabId) {
  const normalizedTabId = (tabId || "").toString().trim().toLowerCase();
  if (normalizedTabId === "") {
    return false;
  }

  if (ADMIN_HIDDEN_TAB_SET.has(normalizedTabId)) {
    return false;
  }

  const metadata = admin_tab_metadata(normalizedTabId);
  if (!metadata) {
    return true;
  }

  return admin_has_any_permission(metadata.permissions || []);
}

function admin_can_use_orders_summary_api() {
  return admin_has_any_permission([
    "orders.manage",
    "customers.manage",
    "analytics.view",
    "dashboard.view",
  ]);
}

function admin_parse_boolean(value, fallback = true) {
  if (value === null || value === undefined) {
    return fallback;
  }

  if (typeof value === "boolean") {
    return value;
  }

  if (typeof value === "number") {
    return value !== 0;
  }

  const normalized = value.toString().trim().toLowerCase();
  if (normalized === "") {
    return fallback;
  }

  if (["1", "true", "yes", "on", "visible", "show"].includes(normalized)) {
    return true;
  }

  if (["0", "false", "no", "off", "hidden", "hide"].includes(normalized)) {
    return false;
  }

  return fallback;
}

window.commerzaAdminHasPermission = admin_has_permission;
window.commerzaAdminHasAnyPermission = admin_has_any_permission;
window.commerzaAdminCanAccessTab = admin_can_access_tab;

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
const DASHBOARD_WINDOW_DAYS = 30;
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
    intro:
      "Use the campaign studio to launch cleaner offers with fewer support issues.",
    steps: [
      "Pick a preset and generate a readable code from your campaign seed.",
      "Validate min order, max discount, and usage limits in the live preview.",
      "Prefill email copy, test with small recipients, then launch at scale.",
    ],
    tip: "Keep code length short and set per-user limits for high-traffic events.",
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
