// =========================
// Wishlist
// =========================
const COMPARE_KEY = "commerza_compare";
const WISHLIST_API_URL = "backend/wishlist_api.php";

const wishlistState = {
  initialized: false,
  loggedIn: false,
  ids: new Set(),
  count: 0,
  csrfToken: null,
};

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

  try {
    const response = await fetch(`${WISHLIST_API_URL}?action=status`, {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store",
    });

    const data = await response.json();
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
}

function redirectToLoginForWishlist() {
  const current = getWishlistRedirectTarget();
  window.location.href = `login.php?redirect=${encodeURIComponent(current)}`;
}