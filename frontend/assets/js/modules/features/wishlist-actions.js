function updateWishlistBadge() {
  const count = wishlistState.count;
  $("#wishlist-count").text(count);
  $("#wishlist-count-mobile").text(count);
}

async function parseWishlistActionResponse(response) {
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

function isInWishlist(id, name) {
  const parsedId = parseInt(id, 10);
  if (Number.isInteger(parsedId) && parsedId > 0) {
    return wishlistState.ids.has(String(parsedId));
  }
  return false;
}

async function toggleWishlist(item, options = {}) {
  const silent = options.silent === true;
  const forceRemove = options.forceRemove === true;
  const retryCount = Number(options.retryCount || 0);

  if (!wishlistState.initialized) {
    await initWishlistState();
  }

  if (!wishlistState.loggedIn) {
    if (!silent) {
      showNotif("Please login to use wishlist.", "warning");
    }
    redirectToLoginForWishlist();
    return { ok: false, added: false };
  }

  const productId = parseInt(item?.id, 10);
  const productName = (item?.name || "").toString().trim();
  const productCode = (item?.productCode || item?.code || "").toString().trim();

  if (!Number.isInteger(productId) || productId <= 0) {
    if (!silent) {
      showNotif("Unable to update wishlist for this product.", "warning");
    }
    return { ok: false, added: false };
  }

  if (productName === "" || productCode === "") {
    if (!silent) {
      showNotif(
        "Product verification data is missing. Refresh and try again.",
        "warning",
      );
    }
    return { ok: false, added: false };
  }

  if (forceRemove && !wishlistState.ids.has(String(productId))) {
    return { ok: true, added: false };
  }

  try {
    const payload = new URLSearchParams();
    payload.set("action", "toggle");
    payload.set("product_id", String(productId));
    payload.set("product_name", productName);
    payload.set("product_code", productCode);
    payload.set("csrf_token", wishlistState.csrfToken || "");

    const response = await fetch(WISHLIST_API_URL, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
      },
      body: payload.toString(),
    });

    const data = await parseWishlistActionResponse(response);
    const fallbackError = !response.ok
      ? `Wishlist service error (${response.status}).`
      : "Invalid response from wishlist service.";

    if (data?.csrf_token) {
      wishlistState.csrfToken = data.csrf_token;
    }

    if (response.status === 401 || data?.logged_in === false) {
      if (!silent) {
        showNotif("Please login to use wishlist.", "warning");
      }
      redirectToLoginForWishlist();
      return { ok: false, added: false };
    }

    if (!response.ok || !data?.ok) {
      if (response.status === 403 && data?.csrf_token && retryCount < 1) {
        return toggleWishlist(item, {
          ...options,
          retryCount: retryCount + 1,
        });
      }

      if (!silent) {
        showNotif(data?.message || fallbackError, "warning");
      }
      return { ok: false, added: false };
    }

    setServerWishlistState(data);
    updateWishlistBadge();

    if (!silent) {
      showNotif(
        data.added ? "Added to wishlist!" : "Removed from wishlist.",
        data.added ? "success" : "warning",
      );
    }

    return { ok: true, added: !!data.added };
  } catch (error) {
    if (!silent) {
      const isOffline =
        typeof navigator !== "undefined" && navigator.onLine === false;
      showNotif(
        isOffline
          ? "No internet connection. Reconnect and try again."
          : "Unable to connect wishlist service. Refresh and try again.",
        "warning",
      );
    }
    return { ok: false, added: false };
  }
}

function updateWishlistButtons() {
  $(".wishlist-btn").each(function () {
    const btn = $(this);
    const id = btn.data("productId");
    const name = btn.data("productName");
    const active = isInWishlist(id, name);
    btn.toggleClass("active", active);
    btn
      .find("i")
      .toggleClass("bi-heart-fill", active)
      .toggleClass("bi-heart", !active);
    if (btn.closest(".product-detail-card").length) {
      btn.text(active ? "In Wishlist" : "Add to Wishlist");
    }
  });
}

function renderWishlistPage() {
  const container = $("#wishlist-container");
  if (!container.length) return;
}
