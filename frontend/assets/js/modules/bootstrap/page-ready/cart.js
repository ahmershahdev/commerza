// Cart state, actions, and delegated handlers.

async function syncCartAndWishlistState(forceRefresh = false) {
  const now = Date.now();
  const recentlySynced =
    now - lastCartWishlistSyncAt < CART_WISHLIST_SYNC_TTL_MS;

  if (!forceRefresh && recentlySynced) {
    return;
  }

  if (cartWishlistSyncInFlight) {
    return cartWishlistSyncInFlight;
  }

  cartWishlistSyncInFlight = (async () => {
    try {
      await initCartState(true);
    } catch (error) {
      // Keep UI usable even if sync fails temporarily.
    }

    try {
      await initWishlistState();
    } catch (error) {
      // Keep UI usable even if sync fails temporarily.
    }

    updateCartBadge();
    if (typeof updateWishlistBadge === "function") {
      updateWishlistBadge();
    }
    if (typeof updateWishlistButtons === "function") {
      updateWishlistButtons();
    }
    if (typeof renderWishlistPage === "function") {
      renderWishlistPage();
    }

    lastCartWishlistSyncAt = Date.now();
  })();

  try {
    await cartWishlistSyncInFlight;
  } finally {
    cartWishlistSyncInFlight = null;
  }
}

window.addEventListener("pageshow", (event) => {
  const fromBfcache = !!(event && event.persisted);
  syncCartAndWishlistState(fromBfcache);
});

window.addEventListener("focus", () => {
  syncCartAndWishlistState(false);
});

document.addEventListener("visibilitychange", () => {
  if (document.visibilityState === "visible") {
    syncCartAndWishlistState(false);
  }
});

function getTotalCartQty() {
  return cart.reduce((total, item) => total + item.quantity, 0);
}

async function initCartState(forceRefresh = false) {
  if (cartState.initialized && !forceRefresh) {
    return cart;
  }

  if (cartInitInFlight) {
    return cartInitInFlight;
  }

  cartInitInFlight = (async () => {
    try {
      const response = await fetch(`${CART_API_URL}?action=status`, {
        method: "GET",
        credentials: "same-origin",
        cache: "no-store",
      });

      const data = await parseJsonResponse(response);
      if (response.ok && data?.ok) {
        cart = Array.isArray(data.items) ? data.items : [];
        cartState.csrfToken = data?.csrf_token || cartState.csrfToken;
        cartState.pricing =
          data?.pricing && typeof data.pricing === "object"
            ? data.pricing
            : null;
        cartState.coupon =
          data?.coupon && typeof data.coupon === "object" ? data.coupon : null;
        cartState.couponNotice = (data?.coupon_notice || "").toString();
      } else {
        cart = [];
        cartState.pricing = null;
        cartState.coupon = null;
        cartState.couponNotice = "";
      }
    } catch (error) {
      cart = [];
      cartState.pricing = null;
      cartState.coupon = null;
      cartState.couponNotice = "";
    }

    cartState.initialized = true;
    lastCartWishlistSyncAt = Date.now();
    return cart;
  })();

  try {
    return await cartInitInFlight;
  } finally {
    cartInitInFlight = null;
  }
}

async function postCartAction(action, payload = {}, retryCount = 0) {
  if (!cartState.csrfToken) {
    const pageToken = ($('input[name="csrf_token"]').first().val() || "")
      .toString()
      .trim();
    if (pageToken !== "") {
      cartState.csrfToken = pageToken;
    }
  }

  if (!cartState.initialized && !cartInitInFlight) {
    initCartState().catch(() => {
      // Fire-and-forget warmup; request still proceeds and CSRF retry handles refresh.
    });
  }

  const form = new URLSearchParams();
  form.set("action", action);
  form.set("csrf_token", cartState.csrfToken || "");

  Object.entries(payload).forEach(([key, value]) => {
    form.set(key, String(value));
  });

  try {
    const response = await fetch(CART_API_URL, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
      },
      body: form.toString(),
    });

    const data = await parseJsonResponse(response);
    const fallbackError = !response.ok
      ? `Cart service error (${response.status}).`
      : "Invalid response from cart service.";

    if (!response.ok || !data?.ok) {
      if (data?.csrf_token) {
        cartState.csrfToken = data.csrf_token;
      }

      if (response.status === 403 && data?.csrf_token && retryCount < 1) {
        return postCartAction(action, payload, retryCount + 1);
      }

      return {
        ok: false,
        message: data?.message || fallbackError,
      };
    }

    cart = Array.isArray(data.items) ? data.items : [];
    cartState.csrfToken = data?.csrf_token || cartState.csrfToken;
    cartState.initialized = true;
    cartState.pricing =
      data?.pricing && typeof data.pricing === "object" ? data.pricing : null;
    cartState.coupon =
      data?.coupon && typeof data.coupon === "object" ? data.coupon : null;
    cartState.couponNotice = (data?.coupon_notice || "").toString();

    return {
      ok: true,
      message: data?.message || "Cart updated.",
    };
  } catch (error) {
    const isOffline =
      typeof navigator !== "undefined" && navigator.onLine === false;
    return {
      ok: false,
      message: isOffline
        ? "No internet connection. Reconnect and try again."
        : "Unable to connect cart service. Refresh and try again.",
    };
  }
}

function ensureCustomAlert() {
  if (document.getElementById("customAlert")) return;
  const alertHtml = `
            <div id="customAlert" class="alert alert-danger text-center"
                style="display:none; position:fixed; top:20px; right:0; left:0; margin:auto; width:300px; z-index:9998;">
                Cart Full: Your 10-item limit has been reached.
            </div>
        `;
  $("body").append(alertHtml);
}

function triggerAlert() {
  ensureCustomAlert();
  const alertBox = $("#customAlert");
  alertBox.stop(true, true).fadeIn(300).delay(2000).fadeOut(300);
}

function getProductImageElement(btn) {
  let card = btn.closest(".product-card");
  if (card.length) return card.find("img.p-image");
  const detailCard = btn.closest(".product-detail-card");
  if (detailCard.length) return detailCard.find("img.p-image");
  return $("img.p-image").first();
}

function resolveCartItem(btn) {
  const dataId = parseInt(btn.data("productId"), 10);
  const dataName = btn.data("productName");
  const dataCode = (btn.data("productCode") || "").toString().trim();
  const dataPrice = btn.data("productSalePrice") || btn.data("productPrice");
  const dataImage = btn.data("productImage");

  let productId = Number.isInteger(dataId) && dataId > 0 ? dataId : 0;
  let name = dataName;
  let productCode = dataCode;
  let price = dataPrice;
  let image = dataImage;

  if (!productId || !name || !productCode || !price || !image) {
    const card = btn.closest(".product-card");
    const detailCard = btn.closest(".product-detail-card");
    const scope = card.length ? card : detailCard;

    if (!productId) {
      const scopedId = parseInt(scope.data("productId"), 10);
      if (Number.isInteger(scopedId) && scopedId > 0) {
        productId = scopedId;
      }
    }

    if (!name) {
      name = scope.find(".product-name").first().text().trim();
    }

    if (!productCode) {
      productCode = (scope.data("productCode") || "").toString().trim();
    }

    if (!price) {
      price =
        scope.find(".sale-price").first().text().trim() ||
        scope.find(".price-sale").first().text().trim() ||
        scope.find(".price-original").first().text().trim();
    }

    if (!image) {
      image = scope.find("img.p-image").first().attr("src");
    }
  }

  const normalizedPrice = (() => {
    if (price == null) return "";
    if (typeof price === "number") return `${price} PKR`;
    const text = String(price).trim();
    if (!text) return "";
    if (!/\d/.test(text)) return "";
    if (/PKR/i.test(text)) return text;
    return `${text} PKR`;
  })();

  return {
    id: productId,
    name: name || "",
    code: productCode || "",
    price: normalizedPrice,
    image: image || "",
  };
}

$(document).on(
  "click",
  "a.product-btn-buy[href^='mailto:'], a.product-btn-cart[href^='mailto:'], a.product-btn-buy[href^='tel:'], a.product-btn-cart[href^='tel:']",
  function (e) {
    e.stopPropagation();
    window.location.href = $(this).attr("href");
  },
);

$(document).on("click", ".product-btn-cart", async function (e) {
  const btn = $(this);
  const rawHref = (btn.attr("href") || "").trim();
  const href = rawHref.toLowerCase();
  const isPlaceholderAnchor =
    rawHref === "" ||
    rawHref === "#" ||
    rawHref.startsWith("#") ||
    rawHref.endsWith("#");
  const isDirectContactLink =
    href.startsWith("mailto:") || href.startsWith("tel:");
  const hasProductContext =
    !!btn.data("productName") ||
    !!btn.data("productId") ||
    btn.closest(".product-detail-card").length > 0 ||
    btn.closest(".product-card[data-product-name]").length > 0;
  const isNavigationLink =
    href !== "" &&
    !isPlaceholderAnchor &&
    !href.startsWith("javascript:") &&
    href.indexOf("compare.php") === -1;

  if (isPlaceholderAnchor) {
    e.preventDefault();
  }

  if (
    $(this).hasClass("change-qty") ||
    $(this).hasClass("compare-remove-btn") ||
    $(this).hasClass("wishlist-btn") ||
    $(this).hasClass("compare-link") ||
    (href && href.indexOf("compare.php") !== -1) ||
    isDirectContactLink ||
    isNavigationLink
  )
    return;

  if (!hasProductContext) {
    return;
  }

  if (!cartState.initialized && !cartInitInFlight) {
    initCartState().catch(() => {
      // Keep first add responsive while cart state warms up in background.
    });
  }

  if (getTotalCartQty() >= 10) {
    triggerAlert();
    return;
  }

  const cartItem = resolveCartItem(btn);
  const productId = parseInt(cartItem.id || btn.data("productId"), 10);
  const productName = (cartItem.name || btn.data("productName") || "")
    .toString()
    .trim();
  const productCode = (cartItem.code || btn.data("productCode") || "")
    .toString()
    .trim();
  if (!Number.isInteger(productId) || productId <= 0) {
    showNotif("Unable to add this product. Invalid product id.", "warning");
    return;
  }

  const img = getProductImageElement(btn);
  const cartIcon = $("#cart-icon");

  const addPayload = {
    product_id: productId,
    quantity: 1,
  };

  if (productName !== "") {
    addPayload.product_name = productName;
  }

  if (productCode !== "") {
    addPayload.product_code = productCode;
  }

  const addResult = await postCartAction("add", addPayload);

  if (!addResult.ok) {
    showNotif(addResult.message, "warning");
    return;
  }

  updateCartBadge();
  if ($("#cart-items-container").length > 0) {
    displayCartItems();
  }

  let isBuyNow = btn.hasClass("product-btn-buy");
  if (isBuyNow) {
    setTimeout(() => {
      window.location.href = "cart.php";
    }, 600);
  }

  if (img.length && cartIcon.length && !isBuyNow) {
    let imgRect = img[0].getBoundingClientRect();
    let cartRect = cartIcon[0].getBoundingClientRect();
    let flyImg = $("<img />", {
      src: img.attr("src"),
      css: {
        position: "fixed",
        top: imgRect.top + "px",
        left: imgRect.left + "px",
        width: imgRect.width + "px",
        height: imgRect.height + "px",
        borderRadius: "12px",
        zIndex: 999999,
        pointerEvents: "none",
        opacity: 1,
      },
    }).appendTo("body");

    flyImg.animate(
      {
        top: cartRect.top + cartRect.height / 2 - 15 + "px",
        left: cartRect.left + cartRect.width / 2 - 15 + "px",
        width: "30px",
        height: "30px",
        opacity: 0.5,
      },
      520,
      "swing",
      function () {
        $(this).remove();
        cartIcon.addClass("shake-animation");
        setTimeout(() => cartIcon.removeClass("shake-animation"), 400);
      },
    );
  }
});

$(document).on("click", ".wishlist-btn", async function (e) {
  e.preventDefault();
  e.stopPropagation();
  const btn = $(this);
  const item = {
    id: btn.data("productId"),
    name: btn.data("productName"),
    productCode: btn.data("productCode"),
    image: btn.data("productImage"),
    price: btn.data("productPrice"),
    salePrice: btn.data("productSalePrice"),
  };
  const outcome = await toggleWishlist(item);
  if (!outcome.ok) {
    return;
  }

  const added = outcome.added;
  updateWishlistButtons();
  renderWishlistPage();
  if (added) {
    const img = getProductImageElement(btn);
    const wishlistLink = $('.nav-link[href="wishlist.php"]').first();
    const wishlistIcon = wishlistLink.find("i").first();
    if (img.length && wishlistIcon.length) {
      const imgRect = img[0].getBoundingClientRect();
      const wishRect = wishlistIcon[0].getBoundingClientRect();
      const flyImg = $("<img />", {
        src: img.attr("src"),
        css: {
          position: "fixed",
          top: imgRect.top + "px",
          left: imgRect.left + "px",
          width: imgRect.width + "px",
          height: imgRect.height + "px",
          borderRadius: "12px",
          zIndex: 999999,
          pointerEvents: "none",
          opacity: 1,
        },
      }).appendTo("body");

      flyImg.animate(
        {
          top: wishRect.top + wishRect.height / 2 - 15 + "px",
          left: wishRect.left + wishRect.width / 2 - 15 + "px",
          width: "30px",
          height: "30px",
          opacity: 0.5,
        },
        520,
        "swing",
        function () {
          $(this).remove();
          wishlistIcon.addClass("shake-animation");
          setTimeout(() => wishlistIcon.removeClass("shake-animation"), 400);
        },
      );
    }
  }
});

$(document).on("click", ".compare-btn", function (e) {
  e.preventDefault();
  const btn = $(this);
  const item = {
    id: btn.data("productId"),
    name: btn.data("productName"),
    image: btn.data("productImage"),
    price: btn.data("productPrice"),
    salePrice: btn.data("productSalePrice"),
    stock: btn.data("productStock"),
    movement: btn.data("productMovement"),
  };
  toggleCompare(item);
  updateCompareButtons();
  renderComparePage();
});

$(document).on("click", ".compare-remove-btn", function () {
  const btn = $(this);
  const item = {
    id: btn.data("productId"),
    name: btn.data("productName"),
  };
  const list = getCompare().filter(
    (entry) => String(entry.id) !== String(item.id) && entry.name !== item.name,
  );
  saveCompare(list);
  renderComparePage();
  updateCompareButtons();
});

$(document).on("click", ".change-qty", async function () {
  const index = $(this).data("index");
  const action = $(this).data("action");
  const item = cart[index];

  if (!item || !Number.isInteger(parseInt(item.id, 10))) {
    showNotif("Unable to update this cart item.", "warning");
    return;
  }

  const itemId = parseInt(item.id, 10);
  const currentQty = parseInt(item.quantity, 10) || 1;

  if (getCartQtyCooldownMs(itemId) > 0) {
    return;
  }

  if (cartActionLocks.has(itemId)) {
    return;
  }

  let shouldCooldown = false;
  let didMutateQuantity = false;

  cartActionLocks.add(itemId);
  setCartItemActionState(itemId, true);

  try {
    if (action === "plus") {
      if (getTotalCartQty() >= 10) {
        triggerAlert();
        return;
      }

      shouldCooldown = true;
      const result = await postCartAction("set_qty", {
        product_id: itemId,
        quantity: currentQty + 1,
      });

      if (!result.ok) {
        showNotif(result.message, "warning");
        return;
      }

      didMutateQuantity = true;
    } else if (action === "minus") {
      if (currentQty <= 1) {
        return;
      }

      shouldCooldown = true;

      const result = await postCartAction("set_qty", {
        product_id: itemId,
        quantity: currentQty - 1,
      });

      if (!result.ok) {
        showNotif(result.message, "warning");
        return;
      }

      didMutateQuantity = true;
    }

    if (didMutateQuantity) {
      if (shouldCooldown) {
        setCartQtyCooldown(itemId);
      }

      updateCartBadge();
      displayCartItems(itemId);
    }
  } finally {
    cartActionLocks.delete(itemId);

    if (didMutateQuantity && shouldCooldown) {
      startCartQtyCooldown(itemId);
    }

    setCartItemActionState(itemId, false);
  }
});

$(document).on("click", ".remove-item", async function () {
  const index = $(this).data("index");
  const item = cart[index];

  if (!item || !Number.isInteger(parseInt(item.id, 10))) {
    showNotif("Unable to remove this cart item.", "warning");
    return;
  }

  const itemId = parseInt(item.id, 10);
  if (cartActionLocks.has(itemId)) {
    return;
  }

  cartActionLocks.add(itemId);
  setCartItemActionState(itemId, true);

  try {
    const result = await postCartAction("remove", {
      product_id: itemId,
    });

    if (!result.ok) {
      showNotif(result.message, "warning");
      return;
    }

    updateCartBadge();
    displayCartItems();
  } finally {
    cartActionLocks.delete(itemId);
    setCartItemActionState(itemId, false);
  }
});

$(document).on("click", "#applyCouponBtn", async function () {
  const input = $("#couponCodeInput");
  if (!input.length) {
    return;
  }

  const code = (input.val() || "").toString().trim().toUpperCase();
  if (!code) {
    showNotif("Please enter a coupon code.", "warning");
    return;
  }

  const btn = $(this);
  const originalText = btn.text();
  btn.prop("disabled", true).text("Applying...");

  const result = await postCartAction("apply_coupon", { code });

  btn.prop("disabled", false).text(originalText);

  if (!result.ok) {
    cartState.couponNotice = result.message || "Coupon could not be applied.";
    renderCouponState();
    showNotif(result.message || "Coupon could not be applied.", "warning");
    return;
  }

  cartState.couponNotice = "";
  displayCartItems();
  showNotif(result.message || "Coupon applied successfully.", "success");
});

$(document).on("click", "#removeCouponBtn", async function () {
  const btn = $(this);
  btn.prop("disabled", true);

  const result = await postCartAction("remove_coupon");

  btn.prop("disabled", false);

  if (!result.ok) {
    showNotif(result.message || "Unable to remove coupon.", "warning");
    return;
  }

  cartState.couponNotice = "";
  displayCartItems();
  showNotif(result.message || "Coupon removed.", "success");
});

function updateCartBadge() {
  const count = getTotalCartQty();
  $("#cart-count").text(count);
  $("#cart-count-mobile").text(count);
}

function formatPkrAmount(value) {
  const numeric = Number(value);
  const safe = Number.isFinite(numeric) ? Math.max(0, numeric) : 0;
  return `${safe.toLocaleString(undefined, { maximumFractionDigits: 2 })} PKR`;
}

function renderCouponState() {
  const input = $("#couponCodeInput");
  const status = $("#couponStatusText");
  const removeBtn = $("#removeCouponBtn");
  const hiddenCoupon = $("#checkoutCouponCode");

  if (!input.length || !status.length || !removeBtn.length) {
    return;
  }

  const coupon =
    cartState.coupon && typeof cartState.coupon === "object"
      ? cartState.coupon
      : null;

  if (coupon?.code) {
    const discountAmount = Number(coupon.discount || 0);
    input.val(String(coupon.code || ""));
    hiddenCoupon.val(String(coupon.code || ""));
    removeBtn.removeClass("d-none");
    status
      .removeClass("text-secondary text-warning text-danger")
      .addClass("text-success")
      .text(
        `Applied ${coupon.code} · Saved ${formatPkrAmount(discountAmount)}`,
      );
    return;
  }

  hiddenCoupon.val("");
  removeBtn.addClass("d-none");

  const notice = (cartState.couponNotice || "").trim();
  if (notice) {
    status
      .removeClass("text-secondary text-success text-danger")
      .addClass("text-warning")
      .text(notice);
    return;
  }

  status
    .removeClass("text-success text-warning text-danger")
    .addClass("text-secondary")
    .text("No coupon applied.");
}

function getCartQtyCooldownMs(productId) {
  const safeProductId = parseInt(productId, 10);
  if (!Number.isInteger(safeProductId) || safeProductId <= 0) {
    return 0;
  }

  const cooldownUntil = Number(cartQtyCooldownUntil.get(safeProductId) || 0);
  if (!Number.isFinite(cooldownUntil) || cooldownUntil <= 0) {
    return 0;
  }

  const remaining = cooldownUntil - Date.now();
  if (remaining <= 0) {
    cartQtyCooldownUntil.delete(safeProductId);
    return 0;
  }

  return remaining;
}

function setCartQtyCooldown(productId, durationMs = CART_QTY_COOLDOWN_MS) {
  const safeProductId = parseInt(productId, 10);
  if (!Number.isInteger(safeProductId) || safeProductId <= 0) {
    return 0;
  }

  const safeDuration = Math.max(200, parseInt(durationMs, 10) || 0);
  const expiresAt = Date.now() + safeDuration;
  cartQtyCooldownUntil.set(safeProductId, expiresAt);
  return expiresAt;
}

function startCartQtyCooldown(productId) {
  const safeProductId = parseInt(productId, 10);
  if (!Number.isInteger(safeProductId) || safeProductId <= 0) {
    return;
  }

  if (getCartQtyCooldownMs(safeProductId) <= 0) {
    setCartQtyCooldown(safeProductId);
  }

  const refreshState = () => {
    if (getCartQtyCooldownMs(safeProductId) <= 0) {
      cartQtyCooldownUntil.delete(safeProductId);
      setCartItemActionState(safeProductId, false);
      return;
    }

    setCartItemActionState(safeProductId, false);
    window.setTimeout(refreshState, 250);
  };

  refreshState();
}

function setCartItemActionState(productId, isBusy) {
  if (!Number.isInteger(parseInt(productId, 10))) {
    return;
  }

  const safeProductId = parseInt(productId, 10);
  const cooldownRemainingMs = getCartQtyCooldownMs(safeProductId);
  const isCoolingDown = cooldownRemainingMs > 0;
  const cooldownLabel = isCoolingDown
    ? `Ready in ${Math.ceil(cooldownRemainingMs / 1000)}s`
    : "";
  const row = $(`.cart-item-row[data-product-id="${safeProductId}"]`);
  if (!row.length) {
    return;
  }

  row.toggleClass("is-busy", !!isBusy);
  row.toggleClass("is-cooldown", isCoolingDown);
  row.find(".change-qty").prop("disabled", !!isBusy || isCoolingDown);
  row.find(".remove-item").prop("disabled", !!isBusy);
  row.find(".cart-qty-hint").text(cooldownLabel);
}

function displayCartItems(changedProductId = null) {
  let container = $("#cart-items-container");
  container.empty();

  if (cart.length === 0) {
    container.html(`
                <div class="text-center py-5">
                    <i class="bi bi-cart-x" style="font-size: 4rem; color: #ff6600;"></i>
                    <h2 class="text-white mt-3">Your Cart is Empty</h2>
                    <p class="text-secondary">Looks like you haven't added anything to your cart yet.</p>
                    <a href="index.php" class="btn product-btn-buy mt-3 px-4 py-2">Continue Shopping</a>
                </div>
            `);
    updateSummary(0, 0);
    return;
  }

  cart.forEach((item, index) => {
    const safeItemId = parseInt(item.id, 10) || 0;
    const effectivePrice =
      Number(item.salePrice) > 0 ? Number(item.salePrice) : Number(item.price);
    const displayPrice =
      Number.isFinite(effectivePrice) && effectivePrice > 0
        ? `${effectivePrice.toLocaleString()} PKR`
        : "N/A";
    const safeItemName = escapeHtml(item.name || "Product");
    const safeItemImage = escapeHtml(
      sanitizeClientAssetUrl(item.image) ||
        "https://via.placeholder.com/80?text=Image",
    );
    const safeQuantity = Math.max(0, parseInt(item.quantity, 10) || 0);
    const lineTotal =
      Number.isFinite(effectivePrice) && effectivePrice > 0
        ? effectivePrice * safeQuantity
        : 0;
    const isBusy = cartActionLocks.has(safeItemId);
    const cooldownRemainingMs = getCartQtyCooldownMs(safeItemId);
    const isCoolingDown = cooldownRemainingMs > 0;
    const cooldownLabel = isCoolingDown
      ? `Ready in ${Math.ceil(cooldownRemainingMs / 1000)}s`
      : "";
    const disableMinus = isBusy || isCoolingDown || safeQuantity <= 1;
    const disablePlus = isBusy || isCoolingDown;

    container.append(`
                <div class="card product-card mb-3 cart-item-row ${isBusy ? "is-busy" : ""} ${isCoolingDown ? "is-cooldown" : ""}" data-product-id="${safeItemId}">
                  <div class="card-body d-flex align-items-center gap-3">
                    <img src="${safeItemImage}" class="cart-img me-3" alt="${safeItemName}" />
                    <div class="flex-grow-1 text-center">
                      <h3 class="product-name mb-1">${safeItemName}</h3>
                      <p class="product-desc mb-2 cart-item-price">${displayPrice}</p>
                      <p class="small text-secondary mb-2">Price: <strong class="text-white">${formatPkrAmount(lineTotal)}</strong></p>
                      <div class="d-flex align-items-center justify-content-center gap-3 mx-auto cart-qty-control" style="max-width: 170px;">
                        <button class="btn btn-sm change-qty" data-index="${index}" data-action="minus" aria-label="Decrease quantity for ${safeItemName}" ${disableMinus ? "disabled" : ""}>−</button>
                        <span class="text-white fw-bold cart-qty-value">${safeQuantity}</span>
                        <button class="btn btn-sm change-qty" data-index="${index}" data-action="plus" aria-label="Increase quantity for ${safeItemName}" ${disablePlus ? "disabled" : ""}>+</button>
                      </div>
                      <div class="cart-qty-hint text-secondary small mt-1">${cooldownLabel}</div>
                    </div>
                    <button class="btn btn-sm btn-danger remove-item ms-3 align-self-start" data-index="${index}" aria-label="Remove ${safeItemName} from cart" ${isBusy ? "disabled" : ""}>
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>
            `);
  });

  if (Number.isInteger(parseInt(changedProductId, 10))) {
    const safeChangedId = parseInt(changedProductId, 10);
    const changedRow = container.find(
      `.cart-item-row[data-product-id="${safeChangedId}"]`,
    );
    if (changedRow.length) {
      changedRow.addClass("is-updated");
      window.setTimeout(() => {
        changedRow.removeClass("is-updated");
      }, 520);
    }
  }

  calculateTotal();
}

function calculateTotal() {
  let subtotal = 0;
  let totalItems = 0;
  cart.forEach((item) => {
    const effectivePrice =
      Number(item.salePrice) > 0 ? Number(item.salePrice) : Number(item.price);
    if (Number.isFinite(effectivePrice) && effectivePrice > 0) {
      subtotal += effectivePrice * item.quantity;
    }
    totalItems += item.quantity;
  });
  updateSummary(subtotal, totalItems);
}

function updateSummary(subtotal, totalItems) {
  const fallbackSubtotal = Number(subtotal) || 0;
  const pricing =
    cartState.pricing && typeof cartState.pricing === "object"
      ? cartState.pricing
      : null;

  const calculatedSubtotal = Number(pricing?.subtotal);
  const calculatedShipping = Number(pricing?.shipping);
  const calculatedDiscount = Number(pricing?.discount);
  const calculatedTotal = Number(pricing?.total);

  const safeSubtotal = Number.isFinite(calculatedSubtotal)
    ? Math.max(0, calculatedSubtotal)
    : Math.max(0, fallbackSubtotal);
  const safeShipping = Number.isFinite(calculatedShipping)
    ? Math.max(0, calculatedShipping)
    : 0;
  const safeDiscount = Number.isFinite(calculatedDiscount)
    ? Math.max(0, calculatedDiscount)
    : 0;

  const fallbackTotal = Math.max(0, safeSubtotal + safeShipping - safeDiscount);
  const safeTotal = Number.isFinite(calculatedTotal)
    ? Math.max(0, calculatedTotal)
    : fallbackTotal;

  $("#total-items-qty").text(totalItems);
  $("#cart-subtotal").text(formatPkrAmount(safeSubtotal));

  if (safeShipping <= 0) {
    $("#cart-shipping").html(`
            <span style="text-decoration: line-through; color: #b0b0b0;">1000 PKR</span> 
            <span style="color: #28a745; margin-left: 10px; font-weight: bold;">FREE</span>
        `);
  } else {
    $("#cart-shipping").text(formatPkrAmount(safeShipping));
  }

  const discountLabel =
    safeDiscount > 0
      ? `- ${formatPkrAmount(safeDiscount)}`
      : formatPkrAmount(0);

  $("#cart-discount").text(discountLabel);
  $("#cart-total").text(formatPkrAmount(safeTotal));
  $("#cart-total").attr("data-amount", safeTotal.toFixed(2));
  renderCouponState();
}
