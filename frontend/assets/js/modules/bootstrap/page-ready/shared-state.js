// Shared state and utility helpers for the page-ready stage.

let upBtn = null;
let cart = [];

function resolveCommerzaRuntimeBase() {
  const appBase = (
    window.CommerzaAppBaseUrl ||
    window.CommerzaAppBasePath ||
    ""
  )
    .toString()
    .trim();

  if (appBase) {
    return appBase;
  }

  const pathname = (window.location.pathname || "").replace(/\\/g, "/");
  const segments = pathname.split("/").filter(Boolean);
  const isSafeSegment = (value) => /^[a-z0-9_-]+$/i.test(value || "");
  const projectSegment = segments.find((segment) =>
    /^commerza$/i.test(segment),
  );

  if (projectSegment && isSafeSegment(projectSegment)) {
    return `${window.location.origin}/${projectSegment}/`;
  }

  if (segments.length > 0 && isSafeSegment(segments[0])) {
    return `${window.location.origin}/${segments[0]}/`;
  }

  return `${window.location.origin}/`;
}

function resolveCartApiUrl(path) {
  const normalizedPath = (path || "").toString().replace(/^\/+/, "");
  const appBase = resolveCommerzaRuntimeBase();

  try {
    return new URL(normalizedPath, appBase).toString();
  } catch (error) {
    return normalizedPath;
  }
}

async function parseJsonResponse(response) {
  const rawText = await response.text();
  if (!rawText) {
    return null;
  }

  const cleaned = rawText
    .toString()
    .replace(/^\uFEFF/, "")
    .trim();
  if (!cleaned) {
    return null;
  }

  try {
    return JSON.parse(cleaned);
  } catch (error) {
    const objectStart = cleaned.indexOf("{");
    const objectEnd = cleaned.lastIndexOf("}");
    if (objectStart >= 0 && objectEnd > objectStart) {
      try {
        return JSON.parse(cleaned.slice(objectStart, objectEnd + 1));
      } catch (secondaryError) {
        return null;
      }
    }

    return null;
  }
}

const CART_API_URL = resolveCartApiUrl("backend/api/cart_api.php");
const cartState = {
  initialized: false,
  csrfToken: null,
  pricing: null,
  coupon: null,
  couponNotice: "",
};
const cartActionLocks = new Set();
const cartQtyCooldownUntil = new Map();
const CART_QTY_COOLDOWN_MS = 3000;
const CART_WISHLIST_SYNC_TTL_MS = 15000;
let cartWishlistSyncInFlight = null;
let lastCartWishlistSyncAt = 0;
let cartInitInFlight = null;

const searchConfig = [
  {
    containerId: "featured-products-container",
    sectionIds: ["featured-collection"],
  },
  {
    containerId: "automatic-vault-products-container",
    sectionIds: ["automatic-vault"],
  },
  {
    containerId: "smart-evolution-products-container",
    sectionIds: ["smart-evolution"],
  },
  {
    containerId: "signature-collection-products-container",
    sectionIds: ["signature-collection"],
  },
  {
    containerId: "sports-division-products-container",
    sectionIds: ["sports-sales-division"],
  },
];

let productsCache = null;
let pageFilterCache = new Map();
let searchIndexCache = {
  source: null,
  index: [],
};
let suggestionIndexCache = {
  source: null,
  index: [],
};
let suggestionResultCache = new Map();
let activeSuggestRequest = null;
let suggestDebounceTimer = null;
let reviewsCsrfToken = "";
let liveViewerIntervalId = null;

function escapeHtml(value) {
  return (value || "")
    .toString()
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/\"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function sanitizeClientAssetUrl(value) {
  const raw = (value || "").toString().trim();
  if (!raw) {
    return "";
  }

  if (!/^(https?:\/\/|\/|frontend\/assets\/)/i.test(raw)) {
    return "";
  }

  return raw.replace(/[\u0000-\u001F\u007F]/g, "");
}
