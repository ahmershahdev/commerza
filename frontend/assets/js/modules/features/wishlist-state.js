// =========================
// Wishlist
// =========================
const COMPARE_KEY = "commerza_compare";

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

function resolveWishlistApiUrl(path) {
  const normalizedPath = (path || "").toString().replace(/^\/+/, "");
  const appBase = resolveCommerzaRuntimeBase();

  try {
    return new URL(normalizedPath, appBase).toString();
  } catch (error) {
    return normalizedPath;
  }
}

async function parseWishlistResponseJson(response) {
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

const WISHLIST_API_URL = resolveWishlistApiUrl("backend/wishlist_api.php");

const wishlistState = {
  initialized: false,
  loggedIn: false,
  ids: new Set(),
  count: 0,
  csrfToken: null,
};
let wishlistInitInFlight = null;

function getCurrentPageFileName() {
  const path = window.location.pathname.replace(/\\/g, "/");
  const file = path.split("/").pop();
  return file || "index.php";
}

function getWishlistRedirectTarget() {
  const file = getCurrentPageFileName();
  const search = window.location.search || "";
  const hash = window.location.hash || "";
  return `${file}${search}${hash}`;
}

function setServerWishlistState(data) {
  const ids = Array.isArray(data?.ids) ? data.ids : [];
  const normalizedIds = ids
    .map((id) => parseInt(id, 10))
    .filter((id) => Number.isInteger(id) && id > 0)
    .map((id) => String(id));
  wishlistState.loggedIn = !!data?.logged_in;
  wishlistState.ids = new Set(normalizedIds);
  wishlistState.count = Number.isInteger(data?.count)
    ? data.count
    : wishlistState.ids.size;
  wishlistState.csrfToken = data?.csrf_token || wishlistState.csrfToken;
}

async function initWishlistState() {
  if (wishlistState.initialized) {
    return wishlistState;
  }

  if (wishlistInitInFlight) {
    return wishlistInitInFlight;
  }

  wishlistInitInFlight = (async () => {
    try {
      const response = await fetch(`${WISHLIST_API_URL}?action=status`, {
        method: "GET",
        credentials: "same-origin",
        cache: "no-store",
      });

      const data = await parseWishlistResponseJson(response);
      if (response.ok && data?.ok) {
        setServerWishlistState(data);
        wishlistState.initialized = true;
        return wishlistState;
      }
    } catch (error) {
      wishlistState.loggedIn = false;
      wishlistState.ids = new Set();
      wishlistState.count = 0;
    }

    wishlistState.initialized = true;
    return wishlistState;
  })();

  try {
    return await wishlistInitInFlight;
  } finally {
    wishlistInitInFlight = null;
  }
}

function redirectToLoginForWishlist() {
  const current = getWishlistRedirectTarget();
  window.location.href = `login.php?redirect=${encodeURIComponent(current)}`;
}
