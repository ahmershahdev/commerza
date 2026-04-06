$(document).ready(function () {
  const upBtn = $("#backToTop");
  let cart = [];

  const CART_API_URL = "backend/cart_api.php";
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

  applySiteSettings();
  ensureLegalNavLinks();

  const carouselElement = document.getElementById("carouselExampleIndicators");
  const playPauseBtn = document.getElementById("carouselPlayPause");

  if (
    carouselElement &&
    playPauseBtn &&
    window.bootstrap &&
    typeof window.bootstrap.Carousel === "function"
  ) {
    const carousel = new window.bootstrap.Carousel(carouselElement, {
      interval: 3500,
      pause: false,
    });

    let isPlaying = true;

    playPauseBtn.addEventListener("click", function () {
      isPlaying = !isPlaying;

      if (isPlaying) {
        carousel.cycle();
        playPauseBtn.innerHTML = '<i class="bi bi-pause-fill"></i>';
      } else {
        carousel.pause();
        playPauseBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
      }
    });
  }

  initAccountPage();

  initCartState().finally(() => {
    updateCartBadge();
    if ($("#cart-items-container").length > 0) {
      displayCartItems();
    }
  });

  initWishlistState().finally(() => {
    updateWishlistBadge();
    updateWishlistButtons();
    renderWishlistPage();
  });
  renderComparePage();

  function getTotalCartQty() {
    return cart.reduce((total, item) => total + item.quantity, 0);
  }

  async function initCartState(forceRefresh = false) {
    if (cartState.initialized && !forceRefresh) {
      return cart;
    }

    try {
      const response = await fetch(`${CART_API_URL}?action=status`, {
        method: "GET",
        credentials: "same-origin",
        cache: "no-store",
      });

      const data = await response.json();
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
    return cart;
  }

  async function postCartAction(action, payload = {}, retryCount = 0) {
    if (!cartState.initialized) {
      await initCartState();
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

      const data = await response.json();

      if (!response.ok || !data?.ok) {
        if (data?.csrf_token) {
          cartState.csrfToken = data.csrf_token;
        }

        if (response.status === 403 && data?.csrf_token && retryCount < 1) {
          return postCartAction(action, payload, retryCount + 1);
        }

        return {
          ok: false,
          message: data?.message || "Unable to update cart.",
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
      return {
        ok: false,
        message: "Unable to connect cart service.",
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

  function ensureLegalNavLinks() {
    const legalLinks = [
      { href: "terms-of-service.php", label: "Terms" },
      { href: "privacy-policy.php", label: "Privacy" },
    ];
    const currentPage = getCurrentPageKey();

    const appendLegalLinks = (menu) => {
      if (!menu || menu.length === 0) {
        return;
      }

      legalLinks.forEach((link) => {
        if (menu.find(`a[href="${link.href}"]`).length > 0) {
          return;
        }

        const isCurrent = currentPage === link.href;
        menu.append(`
          <li class="nav-item">
            <a class="nav-link"${isCurrent ? ' aria-current="page"' : ""} href="${link.href}">${link.label}</a>
          </li>
        `);
      });
    };

    $("header .collapse.navbar-collapse .navbar-nav.me-auto").each(function () {
      appendLegalLinks($(this));
    });

    $("header .offcanvas .offcanvas-body .navbar-nav").each(function () {
      appendLegalLinks($(this));
    });
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
      if (isPlaceholderAnchor) {
        showNotif("Unable to add this product right now.", "warning");
      }
      return;
    }

    await initCartState();

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
        1200,
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
          1200,
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
      (entry) =>
        String(entry.id) !== String(item.id) && entry.name !== item.name,
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

    cartActionLocks.add(itemId);
    setCartItemActionState(itemId, true);

    try {
      if (action === "plus") {
        shouldCooldown = true;

        if (getTotalCartQty() >= 10) {
          triggerAlert();
          return;
        }

        const result = await postCartAction("set_qty", {
          product_id: itemId,
          quantity: currentQty + 1,
        });

        if (!result.ok) {
          showNotif(result.message, "warning");
          return;
        }
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
      }

      updateCartBadge();
      displayCartItems(itemId);
    } finally {
      cartActionLocks.delete(itemId);

      if (shouldCooldown) {
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

  function startCartQtyCooldown(productId) {
    const safeProductId = parseInt(productId, 10);
    if (!Number.isInteger(safeProductId) || safeProductId <= 0) {
      return;
    }

    const expiresAt = Date.now() + CART_QTY_COOLDOWN_MS;
    cartQtyCooldownUntil.set(safeProductId, expiresAt);

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
        Number(item.salePrice) > 0
          ? Number(item.salePrice)
          : Number(item.price);
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
        Number(item.salePrice) > 0
          ? Number(item.salePrice)
          : Number(item.price);
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

    const fallbackTotal = Math.max(
      0,
      safeSubtotal + safeShipping - safeDiscount,
    );
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
  let activeSuggestRequest = null;
  let suggestDebounceTimer = null;
  let reviewsCsrfToken = "";
  let liveViewerIntervalId = null;

  function clearLiveViewerPolling() {
    if (liveViewerIntervalId) {
      clearInterval(liveViewerIntervalId);
      liveViewerIntervalId = null;
    }
  }

  function updateLiveViewerBadge(count) {
    const label = $(".live-viewers [data-live-viewers-text]").first();
    if (!label.length) {
      return;
    }

    const normalized =
      Number.isFinite(count) && count >= 0 ? Math.round(count) : 0;
    label.text(`${normalized} people viewing now`);
  }

  async function sendLiveViewerHeartbeat(productId) {
    const csrfToken = (window.CommerzaCsrfToken || reviewsCsrfToken || "")
      .toString()
      .trim();

    if (!csrfToken) {
      return;
    }

    const body = new URLSearchParams({
      action: "heartbeat",
      product_id: String(productId),
      csrf_token: csrfToken,
    });

    const response = await fetch("backend/viewers_api.php", {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
      },
      body: body.toString(),
    });

    if (!response.ok) {
      throw new Error("Unable to send live viewer heartbeat.");
    }
  }

  async function fetchLiveViewerCount(productId) {
    const params = new URLSearchParams({
      action: "count",
      product_id: String(productId),
    });

    const response = await fetch(
      `backend/viewers_api.php?${params.toString()}`,
      {
        method: "GET",
        credentials: "same-origin",
        cache: "no-store",
      },
    );

    const payload = await response.json();
    if (!response.ok || !payload?.ok) {
      throw new Error(payload?.message || "Unable to fetch live viewers.");
    }

    const safeWindowSeconds = Number(payload?.window_seconds);

    return {
      count: Number(payload?.display_count) || 0,
      windowSeconds:
        Number.isFinite(safeWindowSeconds) && safeWindowSeconds >= 30
          ? safeWindowSeconds
          : 45,
    };
  }

  function startLiveViewerPolling(productId) {
    clearLiveViewerPolling();

    const normalizedProductId = parseInt(productId, 10);
    if (!Number.isInteger(normalizedProductId) || normalizedProductId <= 0) {
      updateLiveViewerBadge(0);
      return;
    }

    const syncCount = async (heartbeat) => {
      try {
        if (heartbeat) {
          await sendLiveViewerHeartbeat(normalizedProductId);
        }

        const result = await fetchLiveViewerCount(normalizedProductId);
        updateLiveViewerBadge(result.count);

        if (!liveViewerIntervalId) {
          const intervalMs = Math.max(result.windowSeconds * 1000, 30000);
          liveViewerIntervalId = setInterval(() => {
            syncCount(true);
          }, intervalMs);
        }
      } catch (error) {
        updateLiveViewerBadge(0);
      }
    };

    syncCount(true);
  }

  $(window).on("beforeunload", clearLiveViewerPolling);

  initProductDetailPage();
  initNewsletterModal();
  initNewsletterInlineForm();
  initOrderTrackingPage();
  initProductFilters();

  function getActiveSearchTargets() {
    return searchConfig.filter((cfg) => $(`#${cfg.containerId}`).length > 0);
  }

  function resetProductSections() {
    const activeTargets = getActiveSearchTargets();
    activeTargets.forEach((target) => {
      target.sectionIds.forEach((sectionId) => {
        loadProductsBySection(sectionId, target.containerId);
      });
    });
  }

  function renderEmptyResult(containerId, query) {
    const container = $(`#${containerId}`);
    if (container.length === 0) return;
    container.html(`
            <div class="text-center py-5">
                <i class="bi bi-search" style="font-size: 3rem; color: #ff6600;"></i>
                <h3 class="text-white mt-3">No results found</h3>
                <p class="text-secondary">We couldn't find any matches for "${query}".</p>
            </div>
        `);
  }

  function getAllProducts(data) {
    return data.sections.flatMap((section) => section.products || []);
  }

  function uniqueProducts(products) {
    const seen = new Set();
    return products.filter((product) => {
      const key = `${product.name}-${product.salePrice}`;
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    });
  }

  function applySearchResults(query, data) {
    const normalized = query.toLowerCase();
    const activeTargets = getActiveSearchTargets();

    if (activeTargets.length === 0) return;

    const isIndexSearch =
      activeTargets.length === 1 &&
      activeTargets[0].containerId === "featured-products-container";
    const allProducts = uniqueProducts(getAllProducts(data));

    activeTargets.forEach((target) => {
      let matched = [];
      if (isIndexSearch) {
        matched = allProducts.filter((p) => {
          const haystack = `${p.name} ${p.description}`.toLowerCase();
          return haystack.includes(normalized);
        });
      } else {
        target.sectionIds.forEach((sectionId) => {
          const section = data.sections.find((s) => s.sectionId === sectionId);
          if (!section) return;
          const filtered = section.products.filter((p) => {
            const haystack = `${p.name} ${p.description}`.toLowerCase();
            return haystack.includes(normalized);
          });
          matched = matched.concat(filtered);
        });
      }

      if (matched.length === 0) {
        renderEmptyResult(target.containerId, query);
      } else {
        const uniqueMatched = uniqueProducts(matched);
        renderProducts(uniqueMatched, target.containerId);
        if (uniqueMatched.length > 0) {
          scrollToProduct(uniqueMatched[0].name);
        }
      }
    });
  }

  function normalizeName(name) {
    return (name || "").toLowerCase().trim();
  }

  function scrollToProduct(name) {
    const targetName = normalizeName(name);
    const card = $(`.product-card[data-product-name="${targetName}"]`).first();
    if (card.length === 0) return;
    const offsetTop = card.offset().top - 100;
    window.scrollTo({ top: offsetTop, behavior: "smooth" });
    card.addClass("product-focus");
    setTimeout(() => card.removeClass("product-focus"), 1500);
  }

  function fetchProductsData(forceRefresh = false) {
    if (forceRefresh) {
      productsCache = null;
    }

    if (productsCache) {
      return $.Deferred().resolve(productsCache).promise();
    }

    return $.ajax({
      url: "backend/products_api.php",
      method: "GET",
      dataType: "json",
      cache: true,
    }).then((data) => {
      const normalized = {
        sections: Array.isArray(data?.sections) ? data.sections : [],
      };
      productsCache = normalized;
      return normalized;
    });
  }

  function getCurrentPageKey() {
    const path = window.location.pathname.replace(/\\/g, "/");
    const file = path.split("/").pop();
    return file || "index.php";
  }

  function normalizePageFileName(pageName) {
    const value = (pageName || "").toString().trim();
    if (!value) return "";
    const file = value.split("/").pop();
    if (!file) return "";
    if (file.endsWith(".html")) {
      return `${file.slice(0, -5)}.php`;
    }
    return file;
  }

  function getSectionsForPage(data, pageKey) {
    if (!data?.sections) return [];
    const currentPage = normalizePageFileName(pageKey);
    return data.sections.filter((section) => {
      const sectionPage = normalizePageFileName(section.page || "");
      return sectionPage === currentPage;
    });
  }

  function getProductsForSections(sections) {
    return sections.flatMap((section) => section.products || []);
  }

  function buildMovementOptions(products) {
    const movementSet = new Set();
    products.forEach((product) => {
      if (product.movement) {
        movementSet.add(product.movement);
      } else {
        movementSet.add("quartz");
      }
    });
    const order = ["auto", "smart", "quartz"];
    return order.filter((item) => movementSet.has(item));
  }

  function formatMovementLabel(value) {
    if (value === "auto") return "Automatic";
    if (value === "smart") return "Smart";
    return "Quartz";
  }

  function populateFilterOptions(data) {
    const sectionInput = $("#filter-section");
    const movementInput = $("#filter-movement");
    const sectionMenu = $("#filter-section-menu");
    const movementMenu = $("#filter-movement-menu");
    if (
      !sectionInput.length ||
      !movementInput.length ||
      !sectionMenu.length ||
      !movementMenu.length
    )
      return;

    const pageKey = getCurrentPageKey();
    const sections = getSectionsForPage(data, pageKey);
    const products = getProductsForSections(sections);

    const selectedSection = sectionInput.val();
    const selectedMovement = movementInput.val();

    sectionMenu.empty();
    sectionMenu.append(
      '<li><a class="dropdown-item filter-dropdown-item" href="#" data-target="filter-section" data-value="all" data-label="All Sections">All Sections</a></li>',
    );
    sectionMenu.append('<li><hr class="dropdown-divider"></li>');
    sections.forEach((section) => {
      sectionMenu.append(
        `<li><a class="dropdown-item filter-dropdown-item" href="#" data-target="filter-section" data-value="${section.sectionId}" data-label="${section.sectionName}">${section.sectionName}</a></li>`,
      );
    });

    movementMenu.empty();
    movementMenu.append(
      '<li><a class="dropdown-item filter-dropdown-item" href="#" data-target="filter-movement" data-value="all" data-label="All Movements">All Movements</a></li>',
    );
    movementMenu.append('<li><hr class="dropdown-divider"></li>');
    buildMovementOptions(products).forEach((movement) => {
      const label = formatMovementLabel(movement);
      movementMenu.append(
        `<li><a class="dropdown-item filter-dropdown-item" href="#" data-target="filter-movement" data-value="${movement}" data-label="${label}">${label}</a></li>`,
      );
    });

    if (selectedSection) {
      const sectionLabel =
        selectedSection === "all"
          ? "All Sections"
          : sections.find((section) => section.sectionId === selectedSection)
              ?.sectionName || "All Sections";
      $("#filter-section-btn").text(sectionLabel);
    }
    if (selectedMovement) {
      const movementLabel =
        selectedMovement === "all"
          ? "All Movements"
          : formatMovementLabel(selectedMovement);
      $("#filter-movement-btn").text(movementLabel);
    }
  }

  function getPriceValue(product) {
    const price = product.salePrice ?? product.price;
    return price != null ? parseInt(price, 10) : 0;
  }

  function sortProducts(products, sortValue) {
    const copy = [...products];
    if (sortValue === "price-asc") {
      copy.sort((a, b) => getPriceValue(a) - getPriceValue(b));
    } else if (sortValue === "price-desc") {
      copy.sort((a, b) => getPriceValue(b) - getPriceValue(a));
    } else if (sortValue === "name-asc") {
      copy.sort((a, b) => (a.name || "").localeCompare(b.name || ""));
    }
    return copy;
  }

  function toggleFilteredView(isFiltered) {
    const wrapper = $("#filtered-products-wrapper");
    if (!wrapper.length) return;
    wrapper.toggleClass("d-none", !isFiltered);
    $(".product-section-block").toggleClass("d-none", isFiltered);
  }

  function applyProductFilters() {
    const sectionValue = $("#filter-section").val() || "all";
    const movementValue = $("#filter-movement").val() || "all";
    const sortValue = $("#filter-sort").val() || "default";

    const isFiltered =
      sectionValue !== "all" ||
      movementValue !== "all" ||
      sortValue !== "default";
    if (!isFiltered) {
      toggleFilteredView(false);
      resetProductSections();
      return;
    }

    fetchProductsData().done((data) => {
      const pageKey = getCurrentPageKey();
      let sections = getSectionsForPage(data, pageKey);
      if (sectionValue !== "all") {
        sections = sections.filter(
          (section) => section.sectionId === sectionValue,
        );
      }
      let products = getProductsForSections(sections);
      if (movementValue !== "all") {
        products = products.filter(
          (product) => (product.movement || "quartz") === movementValue,
        );
      }
      products = sortProducts(products, sortValue);

      toggleFilteredView(true);
      if (products.length === 0) {
        renderEmptyResult("filtered-products-container", "filters");
        $("#filtered-count").text("0 items");
        return;
      }
      renderProducts(products, "filtered-products-container");
      $("#filtered-count").text(
        `${products.length} item${products.length === 1 ? "" : "s"}`,
      );
    });
  }

  function initProductFilters() {
    if (!$("#filter-section").length) return;
    fetchProductsData().done((data) => {
      populateFilterOptions(data);
    });

    $(document).on("click", ".filter-dropdown-item", function (e) {
      e.preventDefault();
      const item = $(this);
      const target = item.data("target");
      const value = item.data("value");
      const label = item.data("label");
      if (!target) return;
      $(`#${target}`).val(value);
      $(`#${target}-btn`).text(label);
      applyProductFilters();
    });
    $("#apply-filter").on("click", function (e) {
      e.preventDefault();
      applyProductFilters();
    });
    $("#reset-filter").on("click", function (e) {
      e.preventDefault();
      $("#filter-section").val("all");
      $("#filter-movement").val("all");
      $("#filter-sort").val("default");
      $("#filter-section-btn").text("All Sections");
      $("#filter-movement-btn").text("All Movements");
      $("#filter-sort-btn").text("Featured");
      toggleFilteredView(false);
      resetProductSections();
    });
  }

  function normalizeProductSlug(value) {
    return (value || "")
      .toString()
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/-+/g, "-")
      .replace(/^-+|-+$/g, "");
  }

  function normalizeProductName(value) {
    return (value || "").toString().trim().replace(/\s+/g, " ").toLowerCase();
  }

  function normalizeProductCode(value) {
    return (value || "").toString().trim().replace(/\s+/g, "").toUpperCase();
  }

  function resolveProductSlug(product) {
    const explicitSlug = normalizeProductSlug(product?.slug || "");
    if (explicitSlug) {
      return explicitSlug;
    }

    const fromName = normalizeProductSlug(product?.name || "");
    return fromName || "product";
  }

  function sanitizeProductBasePath(rawPath) {
    const raw = (rawPath || "").toString().trim().replace(/\\/g, "/");
    if (!raw) {
      return "";
    }

    const segments = raw.split("/").filter(Boolean);
    const isSafeSegment = (segment) => /^[a-z0-9_-]+$/i.test(segment || "");
    const projectSegment = segments.find((segment) =>
      /^commerza$/i.test(segment),
    );

    if (projectSegment && isSafeSegment(projectSegment)) {
      return `/${projectSegment}/`;
    }

    for (let index = 0; index < segments.length; index += 1) {
      const segment = (segments[index] || "").toString().trim();
      if (isSafeSegment(segment)) {
        return `/${segment}/`;
      }
    }

    return raw.includes(":") ? "/" : "";
  }

  function getProductAppBasePath() {
    const globalBase = sanitizeProductBasePath(
      window.CommerzaAppBasePath || "",
    );
    if (globalBase) {
      return globalBase;
    }

    const pathname = window.location.pathname.replace(/\\/g, "/");
    const lowerPathname = pathname.toLowerCase();
    const markers = ["/products.php", "/prodcuts/", "/products/", "/product/"];

    for (const marker of markers) {
      const markerIndex = lowerPathname.indexOf(marker);
      if (markerIndex >= 0) {
        return (
          sanitizeProductBasePath(pathname.slice(0, markerIndex + 1)) || "/"
        );
      }
    }

    const lastSlashIndex = pathname.lastIndexOf("/");
    if (lastSlashIndex >= 0) {
      return (
        sanitizeProductBasePath(pathname.slice(0, lastSlashIndex + 1)) || "/"
      );
    }

    return sanitizeProductBasePath(pathname) || "/";
  }

  function getProductDetailPath(slug) {
    const normalizedSlug = normalizeProductSlug(slug);
    if (!normalizedSlug) {
      return "products.php";
    }

    return `products/${encodeURIComponent(normalizedSlug)}`;
  }

  function getProductDetailAbsoluteUrl(slug) {
    const basePath = getProductAppBasePath();
    const detailPath = getProductDetailPath(slug);

    return `${window.location.origin}${basePath}${detailPath}`;
  }

  function extractProductSlugFromPath() {
    const pathname = window.location.pathname.replace(/\\/g, "/");
    const match =
      pathname.match(/\/prodcuts\/([^/?#]+)/i) ||
      pathname.match(/\/products\/([^/?#]+)/i) ||
      pathname.match(/\/product\/([^/?#]+)/i);

    if (!match || !match[1]) {
      return "";
    }

    try {
      return normalizeProductSlug(decodeURIComponent(match[1]));
    } catch (error) {
      return normalizeProductSlug(match[1]);
    }
  }

  function getProductRequestParams() {
    const urlParams = new URLSearchParams(window.location.search);
    const preload =
      window.CommerzaProductRequest &&
      typeof window.CommerzaProductRequest === "object"
        ? window.CommerzaProductRequest
        : {};

    const slug = normalizeProductSlug(
      preload.slug || urlParams.get("slug") || extractProductSlugFromPath(),
    );
    const id =
      preload.id != null && preload.id !== ""
        ? String(preload.id)
        : urlParams.get("id");
    const name =
      preload.name != null && preload.name !== ""
        ? String(preload.name)
        : urlParams.get("name");
    const code =
      preload.code != null && preload.code !== ""
        ? String(preload.code)
        : urlParams.get("code");

    return {
      slug,
      id,
      name,
      code,
    };
  }

  function updateProductMeta(product) {
    if (!product) return;
    const title = `${product.name} | Commerza`;
    const description =
      product.description ||
      "Discover premium Commerza watches and accessories.";
    const canonicalUrl = getProductDetailAbsoluteUrl(
      resolveProductSlug(product),
      product?.id,
    );
    const normalizedImage = sanitizeClientAssetUrl(product.image);
    const imageUrl = (() => {
      if (!normalizedImage) {
        return "";
      }

      if (normalizedImage.startsWith("http")) {
        return normalizedImage;
      }

      if (normalizedImage.startsWith("/")) {
        return `${window.location.origin}${normalizedImage}`;
      }

      return `${window.location.origin}${getProductAppBasePath()}${normalizedImage}`;
    })();

    document.title = title;
    $('meta[name="description"]').attr("content", description);
    $('meta[property="og:title"]').attr("content", title);
    $('meta[property="og:description"]').attr("content", description);
    $('meta[property="og:url"]').attr("content", canonicalUrl);
    if (imageUrl) $('meta[property="og:image"]').attr("content", imageUrl);
    $('link[rel="canonical"]').attr("href", canonicalUrl);

    const currentUrl = `${window.location.origin}${window.location.pathname}${window.location.search}`;
    if (
      typeof window.history.replaceState === "function" &&
      currentUrl !== canonicalUrl
    ) {
      window.history.replaceState({}, "", canonicalUrl);
    }
  }

  function findProduct(data, params) {
    if (!data?.sections) return null;
    const slug = normalizeProductSlug(params?.slug || "");
    const id = (params?.id || "").toString().trim();
    const name = normalizeProductName(params?.name || "");
    const code = normalizeProductCode(params?.code || "");

    if (slug === "" && id === "" && name === "" && code === "") {
      return null;
    }

    const pickByLowestId = (candidates) => {
      if (!Array.isArray(candidates) || candidates.length === 0) {
        return null;
      }

      return candidates.slice().sort((a, b) => {
        const aId = Number.parseInt(a?.id, 10);
        const bId = Number.parseInt(b?.id, 10);
        const safeA = Number.isInteger(aId) ? aId : Number.MAX_SAFE_INTEGER;
        const safeB = Number.isInteger(bId) ? bId : Number.MAX_SAFE_INTEGER;
        return safeA - safeB;
      })[0];
    };

    const candidates = [];
    for (const section of data.sections) {
      const products = section.products || [];
      for (const product of products) {
        candidates.push({
          ...product,
          sectionName: section.sectionName,
          sectionId: section.sectionId,
        });
      }
    }

    if (id !== "") {
      const idMatch = candidates.find(
        (product) => (product.id ?? "").toString().trim() === id,
      );
      if (idMatch) {
        return idMatch;
      }
    }

    const hasStrictFilters = slug !== "" || code !== "" || name !== "";

    if (hasStrictFilters) {
      const strictMatches = candidates.filter((product) => {
        if (slug !== "" && resolveProductSlug(product) !== slug) {
          return false;
        }

        if (
          code !== "" &&
          normalizeProductCode(product.productCode || "") !== code
        ) {
          return false;
        }

        if (name !== "" && normalizeProductName(product.name || "") !== name) {
          return false;
        }

        return true;
      });

      const strictWinner = pickByLowestId(strictMatches);
      if (strictWinner) {
        return strictWinner;
      }
    }

    if (slug !== "") {
      const slugWinner = pickByLowestId(
        candidates.filter((product) => resolveProductSlug(product) === slug),
      );
      if (slugWinner) {
        return slugWinner;
      }
    }

    if (code !== "") {
      const codeWinner = pickByLowestId(
        candidates.filter(
          (product) => normalizeProductCode(product.productCode || "") === code,
        ),
      );
      if (codeWinner) {
        return codeWinner;
      }
    }

    if (name !== "") {
      return pickByLowestId(
        candidates.filter(
          (product) => normalizeProductName(product.name || "") === name,
        ),
      );
    }

    return null;
  }

  function renderProductDetail(product) {
    const container = $("#product-detail-container");
    if (!container.length) return;

    if (!product) {
      clearLiveViewerPolling();
      container.html(`
                <div class="text-center py-5">
                    <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: #ff6600;"></i>
                    <h3 class="text-white mt-3">Product not found</h3>
                    <p class="text-secondary">We couldn’t locate that product. Please return to the shop.</p>
                    <a href="index.php" class="btn product-btn-buy mt-3">Back to Home</a>
                </div>
            `);
      return;
    }

    const originalPrice =
      product.price != null ? parseInt(product.price).toLocaleString() : "";
    const salePrice =
      product.salePrice != null
        ? parseInt(product.salePrice).toLocaleString()
        : "";
    const movementLabel =
      product.movement === "auto"
        ? "Automatic"
        : product.movement === "smart"
          ? "Smart"
          : "Quartz";
    const stockText =
      product.stock != null ? `${product.stock} in stock` : "Stock available";
    const movementClass =
      product.movement === "smart"
        ? "movement-smart"
        : product.movement === "auto"
          ? "movement-auto"
          : "movement-quartz";
    const numericProductId = Number.parseInt(product.id, 10);
    const safeProductId = Number.isInteger(numericProductId)
      ? String(numericProductId)
      : "";
    const safeName = escapeHtml(product.name || "Product");
    const safeDescription = escapeHtml(product.description || "");
    const safeSectionName = escapeHtml(product.sectionName || "Commerza");
    const safeMovementLabel = escapeHtml(movementLabel);
    const safeStockText = escapeHtml(stockText);
    const safeImage = escapeHtml(sanitizeClientAssetUrl(product.image));
    const safePriceValue = Number.isFinite(Number(product.price))
      ? String(Number(product.price))
      : "0";
    const safeSalePriceValue = Number.isFinite(Number(product.salePrice))
      ? String(Number(product.salePrice))
      : safePriceValue;
    const safeStockValue = Number.isFinite(Number(product.stock))
      ? String(Number(product.stock))
      : "";
    const stockCount = Number.isFinite(Number(product.stock))
      ? Number(product.stock)
      : 0;
    const defaultDispatchText =
      stockCount > 0 ? "Dispatch in 24-48 hours" : "Pre-order availability";
    const dispatchText =
      (product.dispatchInfo || "").toString().trim() || defaultDispatchText;
    const productCode =
      (product.productCode || "").toString().trim() ||
      (safeProductId ? `CMRZ-${safeProductId.padStart(5, "0")}` : "CMRZ-NA");
    const warrantyText =
      (product.warrantyInfo || "").toString().trim() ||
      "12-month seller warranty";
    const safeMovementValue = escapeHtml((product.movement || "").toString());
    const safeDispatchText = escapeHtml(dispatchText);
    const safeProductCode = escapeHtml(productCode);
    const safeWarrantyText = escapeHtml(warrantyText);
    const wishlistActive = isInWishlist(product.id, product.name);
    const compareActive = isInCompare(product.id, product.name);
    const compareIcon = compareActive ? "bi-check2-circle" : "bi-sliders";

    container.html(`
            <div class="product-detail-card">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-5">
                        <div class="product-media">
                      <img src="${safeImage}" class="p-image" alt="${safeName}">
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="product-badge-row">
                      <span class="movement-badge ${movementClass}">${safeMovementLabel}</span>
                      <span class="stock-pill">${safeStockText}</span>
                          <span class="live-viewers">
                                <span class="live-dot" aria-hidden="true"></span>
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            <span data-live-viewers-text>Loading live viewers...</span>
                            </span>
                        </div>
                    <h1 class="product-name">${safeName}</h1>
                    <p class="product-desc">${safeDescription}</p>
                        <div class="price-stack">
                            ${originalPrice ? `<span class="price-original">${originalPrice} PKR</span>` : ""}
                            ${salePrice ? `<span class="price-sale">${salePrice} PKR</span>` : ""}
                        </div>
                        <div class="spec-grid">
                            <div class="spec-card">
                                <span class="spec-label">Movement</span>
                        <span class="spec-value">${safeMovementLabel}</span>
                            </div>
                            <div class="spec-card">
                                <span class="spec-label">Collection</span>
                        <span class="spec-value">${safeSectionName}</span>
                            </div>
                            <div class="spec-card">
                                <span class="spec-label">Availability</span>
                        <span class="spec-value">${safeStockText}</span>
                            </div>
                        </div>
                        <div class="detail-highlights">
                          <div class="detail-highlight-item">
                            <i class="bi bi-upc-scan"></i>
                            <div class="detail-highlight-copy">
                              <span class="detail-highlight-title">Product Code</span>
                              <span class="detail-highlight-value">${safeProductCode}</span>
                            </div>
                          </div>
                          <div class="detail-highlight-item">
                            <i class="bi bi-lightning-charge"></i>
                            <div class="detail-highlight-copy">
                              <span class="detail-highlight-title">Dispatch</span>
                              <span class="detail-highlight-value">${safeDispatchText}</span>
                            </div>
                          </div>
                          <div class="detail-highlight-item">
                            <i class="bi bi-shield-check"></i>
                            <div class="detail-highlight-copy">
                              <span class="detail-highlight-title">Warranty</span>
                              <span class="detail-highlight-value">${safeWarrantyText}</span>
                            </div>
                          </div>
                          <div class="detail-highlight-item">
                            <i class="bi bi-stars"></i>
                            <div class="detail-highlight-copy">
                              <span class="detail-highlight-title">Craft Focus</span>
                              <span class="detail-highlight-value">Precision finish and comfort fit</span>
                            </div>
                          </div>
                        </div>
                        <div class="product-assurance-list" aria-label="shopping assurances">
                          <span class="assurance-chip"><i class="bi bi-lock"></i> Secure checkout</span>
                          <span class="assurance-chip"><i class="bi bi-arrow-repeat"></i> Easy support flow</span>
                          <span class="assurance-chip"><i class="bi bi-truck"></i> Reliable delivery updates</span>
                        </div>
                        <div class="product-actions">
                        <a href="#" class="btn product-btn-buy product-btn-cart" data-product-id="${safeProductId}" data-product-name="${safeName}" data-product-code="${safeProductCode}" data-product-image="${safeImage}" data-product-price="${safePriceValue}" data-product-sale-price="${safeSalePriceValue}">Buy Now</a>
                        <a href="#" class="btn product-btn-cart" data-product-id="${safeProductId}" data-product-name="${safeName}" data-product-code="${safeProductCode}" data-product-image="${safeImage}" data-product-price="${safePriceValue}" data-product-sale-price="${safeSalePriceValue}">Add to Cart</a>
                        <button class="btn product-btn-buy wishlist-btn ${wishlistActive ? "active" : ""}" data-product-id="${safeProductId}" data-product-name="${safeName}" data-product-code="${safeProductCode}" data-product-image="${safeImage}" data-product-price="${safePriceValue}" data-product-sale-price="${safeSalePriceValue}" type="button">
                                ${wishlistActive ? "In Wishlist" : "Add to Wishlist"}
                            </button>
                        <button class="btn product-btn-buy compare-btn" data-product-id="${safeProductId}" data-product-name="${safeName}" data-product-code="${safeProductCode}" data-product-image="${safeImage}" data-product-price="${safePriceValue}" data-product-sale-price="${safeSalePriceValue}" data-product-stock="${safeStockValue}" data-product-movement="${safeMovementValue}" type="button">
                                <i class="bi ${compareIcon}"></i> Compare
                            </button>
                            <a href="compare.php" class="btn product-btn-cart compare-link">View Compare</a>
                        </div>
                    </div>
                </div>
            </div>
        `);

    updateProductMeta(product);
    updateWishlistButtons();
    updateCompareButtons();
    startLiveViewerPolling(product.id);
  }

  function renderShareButtons(product) {
    const container = $("#product-share-buttons");
    if (!container.length || !product) return;

    const shareName = (product.name || "this product")
      .toString()
      .slice(0, 120)
      .replace(/\s+/g, " ")
      .trim();
    const url = getProductDetailAbsoluteUrl(
      resolveProductSlug(product),
      product?.id,
    );
    const shareText = `Check out ${shareName} on Commerza.`;
    const text = encodeURIComponent(shareText);
    const encodedUrl = encodeURIComponent(url);
    const canUseNativeShare =
      typeof navigator !== "undefined" && typeof navigator.share === "function";

    container.html(`
            <a class="btn share-btn share-btn-facebook" href="https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}" target="_blank" rel="noopener" aria-label="Share on Facebook">
              <i class="bi bi-facebook"></i>
              <span>Facebook</span>
            </a>
            <a class="btn share-btn share-btn-x" href="https://twitter.com/intent/tweet?url=${encodedUrl}&text=${text}" target="_blank" rel="noopener" aria-label="Share on X">
              <i class="bi bi-twitter-x"></i>
              <span>X</span>
            </a>
            <a class="btn share-btn share-btn-whatsapp" href="https://wa.me/?text=${text}%20${encodedUrl}" target="_blank" rel="noopener" aria-label="Share on WhatsApp">
              <i class="bi bi-whatsapp"></i>
              <span>WhatsApp</span>
            </a>
            <button type="button" class="btn share-btn share-btn-copy" id="copyProductLink" aria-label="Copy product link">
              <i class="bi bi-link-45deg"></i>
              <span>Copy Link</span>
            </button>
            ${
              canUseNativeShare
                ? `<button type="button" class="btn share-btn share-btn-native" id="nativeShareProduct" aria-label="Open native share sheet">
                   <i class="bi bi-share"></i>
                   <span>More</span>
                 </button>`
                : ""
            }
        `);

    const copyBtn = $("#copyProductLink");
    copyBtn.off("click").on("click", function () {
      const clipboardWrite =
        navigator.clipboard &&
        typeof navigator.clipboard.writeText === "function"
          ? navigator.clipboard.writeText(url)
          : Promise.reject(new Error("Clipboard API unavailable"));

      clipboardWrite
        .then(() => {
          const button = $(this);
          button.addClass("is-copied");
          button.find("span").text("Copied");
          showNotif("Product link copied.", "success");
          window.setTimeout(() => {
            button.removeClass("is-copied");
            button.find("span").text("Copy Link");
          }, 1400);
        })
        .catch(() => {
          showNotif("Unable to copy link.", "warning");
        });
    });

    if (canUseNativeShare) {
      $("#nativeShareProduct")
        .off("click")
        .on("click", async function () {
          try {
            await navigator.share({
              title: `${shareName} | Commerza`,
              text: shareText,
              url,
            });
          } catch (error) {
            if (error?.name === "AbortError") {
              return;
            }

            showNotif("Unable to open share panel.", "warning");
          }
        });
    }
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

  function formatReviewDate(value) {
    if (!value) return "";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return "";
    }
    return date.toLocaleDateString();
  }

  function renderReviewImagesMarkup(images) {
    const list = Array.isArray(images) ? images : [];
    if (!list.length) {
      return "";
    }

    const html = list
      .slice(0, 2)
      .map((image) => {
        const path = sanitizeClientAssetUrl((image?.path || "").toString());
        if (!path) {
          return "";
        }

        return `
          <a href="${encodeURI(path)}" target="_blank" rel="noopener" class="d-inline-block me-2 mb-2">
            <img src="${encodeURI(path)}" alt="Review image" style="width: 68px; height: 68px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255, 102, 0, 0.4);">
          </a>
        `;
      })
      .join("");

    return html ? `<div class="mt-2">${html}</div>` : "";
  }

  const REVIEW_MAX_UPLOAD_FILES = 2;
  const REVIEW_MAX_UPLOAD_BYTES = 6 * 1024 * 1024;
  const REVIEW_WEBP_TARGET_KB = 260;
  const REVIEW_WEBP_MAX_DIMENSION = 1800;
  const REVIEW_WEBP_QUALITY_STEPS = [
    0.86, 0.8, 0.74, 0.68, 0.62, 0.56, 0.5, 0.44,
  ];

  let reviewSelectedFiles = [];

  function reviewFormatSizeKb(bytes) {
    const numeric = Number(bytes);
    if (!Number.isFinite(numeric) || numeric <= 0) {
      return "0 KB";
    }

    return `${Math.max(1, Math.round(numeric / 1024))} KB`;
  }

  function reviewBaseName(fileName) {
    const stem = (fileName || "review-image")
      .toString()
      .replace(/\.[^.]+$/, "")
      .replace(/[^a-z0-9-_]+/gi, "-")
      .replace(/^-+|-+$/g, "");

    return stem || "review-image";
  }

  function reviewCanvasToBlob(canvas, type, quality) {
    return new Promise((resolve) => {
      if (!canvas || typeof canvas.toBlob !== "function") {
        resolve(null);
        return;
      }

      canvas.toBlob(
        (blob) => {
          resolve(blob || null);
        },
        type,
        quality,
      );
    });
  }

  function reviewLoadImageFromFile(file) {
    return new Promise((resolve, reject) => {
      if (!(file instanceof File)) {
        reject(new Error("Invalid review image file."));
        return;
      }

      const objectUrl = URL.createObjectURL(file);
      const image = new Image();
      image.decoding = "async";
      image.onload = () => {
        URL.revokeObjectURL(objectUrl);
        resolve(image);
      };
      image.onerror = () => {
        URL.revokeObjectURL(objectUrl);
        reject(new Error("Unable to parse selected image."));
      };
      image.src = objectUrl;
    });
  }

  async function reviewCompressFileToWebp(file) {
    const mime = (file?.type || "").toString().toLowerCase();
    const size = Number(file?.size) || 0;

    if (
      !(
        mime === "image/png" ||
        mime === "image/jpeg" ||
        mime === "image/webp" ||
        mime === "image/gif"
      )
    ) {
      throw new Error("Only JPG, PNG, WEBP, and GIF images are allowed.");
    }

    if (size <= 0 || size >= REVIEW_MAX_UPLOAD_BYTES) {
      throw new Error("Each image must be less than 6 MB.");
    }

    const image = await reviewLoadImageFromFile(file);
    const sourceWidth = Number(image.naturalWidth || image.width || 0);
    const sourceHeight = Number(image.naturalHeight || image.height || 0);

    if (!sourceWidth || !sourceHeight) {
      return file;
    }

    const longestSide = Math.max(sourceWidth, sourceHeight);
    const scale =
      longestSide > REVIEW_WEBP_MAX_DIMENSION
        ? REVIEW_WEBP_MAX_DIMENSION / longestSide
        : 1;

    const outputWidth = Math.max(1, Math.round(sourceWidth * scale));
    const outputHeight = Math.max(1, Math.round(sourceHeight * scale));

    const canvas = document.createElement("canvas");
    canvas.width = outputWidth;
    canvas.height = outputHeight;

    const context = canvas.getContext("2d");
    if (!context) {
      return file;
    }

    context.drawImage(image, 0, 0, outputWidth, outputHeight);

    const targetBytes = REVIEW_WEBP_TARGET_KB * 1024;
    let bestBlob = null;

    for (const quality of REVIEW_WEBP_QUALITY_STEPS) {
      const blob = await reviewCanvasToBlob(canvas, "image/webp", quality);
      if (!blob) {
        continue;
      }

      if (!bestBlob || blob.size < bestBlob.size) {
        bestBlob = blob;
      }

      if (blob.size <= targetBytes) {
        bestBlob = blob;
        break;
      }
    }

    if (!bestBlob) {
      return file;
    }

    if (mime === "image/webp" && bestBlob.size >= size) {
      return file;
    }

    return new File([bestBlob], `${reviewBaseName(file.name)}.webp`, {
      type: "image/webp",
      lastModified: Date.now(),
    });
  }

  function reviewRenderSelectedFiles() {
    const selection = $("#reviewFileSelection");
    if (!selection.length) {
      return;
    }

    if (!reviewSelectedFiles.length) {
      selection.text("No images selected yet.");
      return;
    }

    const markup = reviewSelectedFiles
      .map((file, index) => {
        const safeName = escapeHtml(
          (file?.name || `review-image-${index + 1}.webp`).toString(),
        );
        const sizeLabel = reviewFormatSizeKb(file?.size || 0);

        return `
          <span class="badge rounded-pill text-bg-dark border border-warning-subtle me-2 mb-2">
            <span>${safeName} (${sizeLabel})</span>
            <button type="button" class="btn btn-link btn-sm text-warning p-0 ms-2 review-remove-file-btn" data-file-index="${index}" aria-label="Remove ${safeName}">
              <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
          </span>
        `;
      })
      .join("");

    selection.html(markup);
  }

  function reviewSyncInputFiles() {
    const input = document.getElementById("reviewImages");
    if (!input) {
      return;
    }

    if (typeof DataTransfer === "undefined") {
      return;
    }

    try {
      const transfer = new DataTransfer();
      reviewSelectedFiles.forEach((file) => {
        transfer.items.add(file);
      });

      input.files = transfer.files;
    } catch (_error) {
      // Some older engines may block FileList mutation; keeping state fallback is safe.
    }
  }

  function reviewClearSelectedFiles() {
    reviewSelectedFiles = [];

    const input = document.getElementById("reviewImages");
    if (input) {
      input.value = "";
    }

    reviewRenderSelectedFiles();
  }

  async function reviewHandleFileSelectionChange(event) {
    const input = event?.target;
    const files = input?.files ? Array.from(input.files) : [];

    if (!files.length) {
      reviewClearSelectedFiles();
      return;
    }

    if (files.length > REVIEW_MAX_UPLOAD_FILES) {
      showNotif("You can upload up to 2 images only.", "warning");
    }

    const normalizedFiles = files.slice(0, REVIEW_MAX_UPLOAD_FILES);
    $("#reviewFileSelection").text("Optimizing selected images...");

    const compressedFiles = [];
    let originalBytes = 0;

    for (const file of normalizedFiles) {
      originalBytes += Number(file?.size) || 0;
      const compressed = await reviewCompressFileToWebp(file);
      compressedFiles.push(compressed instanceof File ? compressed : file);
    }

    reviewSelectedFiles = compressedFiles;
    reviewSyncInputFiles();
    reviewRenderSelectedFiles();

    const optimizedBytes = reviewSelectedFiles.reduce(
      (total, file) => total + (Number(file?.size) || 0),
      0,
    );

    if (originalBytes > 0) {
      const savingsRatio = Math.max(0, 1 - optimizedBytes / originalBytes);
      const savingsPercent = Math.round(savingsRatio * 100);
      const optimizedLabel = reviewFormatSizeKb(optimizedBytes);

      if (savingsPercent > 0) {
        showNotif(
          `Images converted to WebP (${savingsPercent}% smaller, ${optimizedLabel} total).`,
          "success",
        );
      } else {
        showNotif(
          `Images converted to WebP (${optimizedLabel} total).`,
          "success",
        );
      }
    }
  }

  function reviewInitializeUploader() {
    const input = document.getElementById("reviewImages");
    if (!input) {
      return;
    }

    if (input.getAttribute("data-review-uploader-ready") !== "1") {
      input.addEventListener("change", (event) => {
        reviewHandleFileSelectionChange(event).catch((error) => {
          showNotif(
            error?.message || "Unable to optimize selected images.",
            "warning",
          );
          reviewClearSelectedFiles();
        });
      });

      input.setAttribute("data-review-uploader-ready", "1");
    }

    $(document)
      .off("click.reviewRemoveSelectedImage")
      .on(
        "click.reviewRemoveSelectedImage",
        "#reviewFileSelection .review-remove-file-btn",
        function (event) {
          event.preventDefault();

          const index = parseInt($(this).attr("data-file-index"), 10);
          if (
            !Number.isInteger(index) ||
            index < 0 ||
            index >= reviewSelectedFiles.length
          ) {
            return;
          }

          reviewSelectedFiles.splice(index, 1);
          reviewSyncInputFiles();
          reviewRenderSelectedFiles();
        },
      );

    reviewRenderSelectedFiles();
  }

  const REVIEW_RATING_LABELS = {
    1: "1 star - Poor",
    2: "2 stars - Fair",
    3: "3 stars - Good",
    4: "4 stars - Very good",
    5: "5 stars - Excellent",
  };

  function normalizeReviewRatingSelection(value) {
    const parsed = parseInt(value, 10);
    if (Number.isInteger(parsed) && parsed >= 1 && parsed <= 5) {
      return parsed;
    }

    return 0;
  }

  function reviewRatingLabel(value) {
    const rating = normalizeReviewRatingSelection(value);
    if (rating === 0) {
      return "Select a rating to continue";
    }

    return REVIEW_RATING_LABELS[rating] || REVIEW_RATING_LABELS[5];
  }

  function setReviewRatingSelection(value) {
    const rating = normalizeReviewRatingSelection(value);
    const ratingInput = $("#reviewRating");
    const starsContainer = $("#reviewStarsInput");

    ratingInput.val(rating > 0 ? String(rating) : "");

    if (starsContainer.length) {
      starsContainer.find(".review-star-btn").each(function () {
        const star = $(this);
        const starRating = parseInt(star.data("rating"), 10) || 0;
        const isActive = rating > 0 && starRating <= rating;
        star.toggleClass("active", isActive);
        star.attr("aria-pressed", isActive ? "true" : "false");
      });

      starsContainer.attr("data-rating", rating > 0 ? String(rating) : "0");
    }

    const label = $("#reviewRatingLabel");
    if (label.length) {
      label.text(reviewRatingLabel(rating));
    }

    return rating;
  }

  function getReviewRatingSelection() {
    const inputRating = normalizeReviewRatingSelection(
      $("#reviewRating").val(),
    );
    if (inputRating > 0) {
      return inputRating;
    }

    const lastActive = $("#reviewStarsInput .review-star-btn.active").last();
    const fallbackRating = normalizeReviewRatingSelection(
      lastActive.data("rating"),
    );
    if (fallbackRating > 0) {
      return fallbackRating;
    }

    return 0;
  }

  function setupReviewStarInput(isReadOnly = false) {
    const starsContainer = $("#reviewStarsInput");
    if (!starsContainer.length) {
      return;
    }

    starsContainer
      .find(".review-star-btn")
      .prop("disabled", !!isReadOnly)
      .off("click")
      .off("keydown")
      .on("click", function (event) {
        event.preventDefault();
        if (isReadOnly) {
          return;
        }

        const rating = normalizeReviewRatingSelection($(this).data("rating"));
        setReviewRatingSelection(rating);
      })
      .on("keydown", function (event) {
        if (isReadOnly) {
          return;
        }

        const current = normalizeReviewRatingSelection($(this).data("rating"));
        if (event.key === "ArrowRight" || event.key === "ArrowUp") {
          event.preventDefault();
          const next = Math.min(5, current + 1);
          setReviewRatingSelection(next);
          starsContainer
            .find(`.review-star-btn[data-rating="${next}"]`)
            .trigger("focus");
          return;
        }

        if (event.key === "ArrowLeft" || event.key === "ArrowDown") {
          event.preventDefault();
          const prev = Math.max(1, current - 1);
          setReviewRatingSelection(prev);
          starsContainer
            .find(`.review-star-btn[data-rating="${prev}"]`)
            .trigger("focus");
          return;
        }

        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          setReviewRatingSelection(current);
        }
      });

    setReviewRatingSelection($("#reviewRating").val());
  }

  // Fallback delegated binding so stars remain selectable even if a late render
  // occurs before direct bindings are applied.
  $(document)
    .off("click.reviewStarsFallback")
    .on(
      "click.reviewStarsFallback",
      "#reviewStarsInput .review-star-btn",
      function (event) {
        event.preventDefault();
        const rating = normalizeReviewRatingSelection($(this).data("rating"));
        if (rating > 0) {
          setReviewRatingSelection(rating);
        }
      },
    );

  function renderReviewFormState(eligibility, productId) {
    const form = $("#productReviewForm");
    const message = $("#reviewEligibilityMessage");
    const ratingInput = $("#reviewRating");
    const textInput = $("#reviewText");
    const imageInput = $("#reviewImages");
    const removeExistingWrap = $("#reviewRemoveExistingWrap");
    const removeExistingInput = $("#reviewRemoveExistingImages");
    const submitBtn = $("#reviewSubmitBtn");
    const hiddenProductInput = $("#reviewProductId");

    if (!form.length) {
      return;
    }

    hiddenProductInput.val(String(productId || ""));

    const existingReview = eligibility?.existing_review || null;
    const existingImages = Array.isArray(existingReview?.images)
      ? existingReview.images
      : [];
    const canReview = !!eligibility?.can_review || !!existingReview;
    const statusMessage = (eligibility?.message || "").toString().trim();

    reviewInitializeUploader();

    if (existingReview) {
      setReviewRatingSelection(existingReview.rating || 5);
      if (textInput.length && !textInput.val()) {
        textInput.val((existingReview.text || "").toString());
      }
    } else if (!(ratingInput.val() || "").toString().trim()) {
      setReviewRatingSelection(0);
    }

    ratingInput.prop("disabled", false);
    textInput.prop("disabled", !canReview);
    imageInput.prop("disabled", !canReview);
    removeExistingInput.prop("checked", false);
    removeExistingInput.prop("disabled", !(canReview && existingImages.length));
    removeExistingWrap.toggleClass(
      "d-none",
      !(canReview && existingImages.length),
    );
    submitBtn.prop("disabled", !canReview);
    $("#reviewStarsInput").toggleClass("is-readonly", false);
    setupReviewStarInput(false);

    if (!canReview) {
      reviewClearSelectedFiles();
    }

    submitBtn.text(existingReview ? "Update Review" : "Submit Review");

    if (message.length) {
      message
        .removeClass("text-secondary text-warning text-success")
        .addClass(canReview ? "text-success" : "text-warning")
        .text(
          statusMessage ||
            (canReview
              ? "You can submit a verified review for this product."
              : "Login and purchase this product with a delivered order to review."),
        );
    }
  }

  function bindProductReviewSubmit(product) {
    const form = $("#productReviewForm");
    if (!form.length) {
      return;
    }

    form.off("submit").on("submit", async function (event) {
      event.preventDefault();

      const productId = parseInt(product?.id, 10);
      if (!Number.isInteger(productId) || productId <= 0) {
        showNotif("Invalid product context for review.", "warning");
        return;
      }

      const rating = getReviewRatingSelection();
      const text = ($("#reviewText").val() || "").toString().trim();
      const selectedFiles = reviewSelectedFiles.slice(
        0,
        REVIEW_MAX_UPLOAD_FILES,
      );
      const removeExistingImages = $("#reviewRemoveExistingImages").is(
        ":checked",
      );

      if (rating < 1 || rating > 5) {
        showNotif("Select a rating between 1 and 5.", "warning");
        return;
      }

      if (text.length < 10 || text.length > 500) {
        showNotif(
          "Review text must be between 10 and 500 characters.",
          "warning",
        );
        return;
      }

      if (selectedFiles.length > REVIEW_MAX_UPLOAD_FILES) {
        showNotif("You can upload up to 2 images only.", "warning");
        return;
      }

      const maxBytes = REVIEW_MAX_UPLOAD_BYTES;
      for (const file of selectedFiles) {
        const mime = (file?.type || "").toString().toLowerCase();
        if (
          !(
            mime === "image/png" ||
            mime === "image/jpeg" ||
            mime === "image/webp" ||
            mime === "image/gif"
          )
        ) {
          showNotif(
            "Only JPG, PNG, WEBP, and GIF images are allowed.",
            "warning",
          );
          return;
        }

        if ((Number(file?.size) || 0) >= maxBytes) {
          showNotif("Each image must be less than 6 MB.", "warning");
          return;
        }
      }

      const submitBtn = $("#reviewSubmitBtn");
      const originalText = submitBtn.text();
      submitBtn.prop("disabled", true).text("Submitting...");

      try {
        const body = new FormData();
        body.set("action", "submit");
        body.set("product_id", String(productId));
        body.set("rating", String(rating));
        body.set("review_text", text);
        body.set("csrf_token", reviewsCsrfToken || "");

        if (removeExistingImages) {
          body.set("remove_existing_images", "1");
        }

        selectedFiles.slice(0, REVIEW_MAX_UPLOAD_FILES).forEach((file) => {
          body.append("review_images[]", file);
        });

        const response = await fetch("backend/reviews_api.php", {
          method: "POST",
          credentials: "same-origin",
          body,
        });

        const result = await response.json();
        if (result?.csrf_token) {
          reviewsCsrfToken = String(result.csrf_token);
        }

        if (!response.ok || !result?.ok) {
          throw new Error(result?.message || "Unable to submit review.");
        }

        showNotif(
          result?.message || "Review submitted successfully.",
          "success",
        );

        const existing = result?.payload?.eligibility?.existing_review;
        if (existing && Number.isInteger(Number(existing.rating))) {
          setReviewRatingSelection(existing.rating);
        }

        $("#reviewText").val("");
        $("#reviewRemoveExistingImages").prop("checked", false);
        reviewClearSelectedFiles();
        renderReviewsMarquee(product);
      } catch (error) {
        showNotif(error?.message || "Unable to submit review.", "warning");
      } finally {
        submitBtn.prop("disabled", false).text(originalText);
      }
    });
  }

  function renderReviewsMarquee(product) {
    const track = $("#reviews-track");
    const summary = $("#reviewsSummaryText");
    if (!track.length) return;

    const productId = parseInt(product?.id, 10);
    if (!Number.isInteger(productId) || productId <= 0) {
      track.css("animation", "none");
      track.html(
        '<div class="review-card"><p class="mb-2">Reviews are not available for this product.</p></div>',
      );
      renderReviewFormState(
        {
          can_review: false,
          message: "Login with an eligible account to post a review.",
        },
        0,
      );
      return;
    }

    track.css("animation", "none");
    track.html(
      '<div class="review-card"><p class="mb-0">Loading customer reviews...</p></div>',
    );

    fetch(`backend/reviews_api.php?action=list&product_id=${productId}`, {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store",
    })
      .then((response) =>
        response.json().then((result) => ({
          ok: response.ok,
          result,
        })),
      )
      .then(({ ok, result }) => {
        if (result?.csrf_token) {
          reviewsCsrfToken = String(result.csrf_token);
        }

        if (!ok || !result?.ok) {
          throw new Error(result?.message || "Unable to load reviews.");
        }

        const payload = result?.payload || {};
        const reviews = Array.isArray(payload.reviews) ? payload.reviews : [];
        const count = Number(payload?.summary?.count || 0);
        const average = Number(payload?.summary?.average || 0);

        if (summary.length) {
          if (count > 0) {
            summary.text(
              `${average.toFixed(1)} / 5 (${count} review${count === 1 ? "" : "s"})`,
            );
          } else {
            summary.text(
              "No reviews yet. Be the first to share your feedback.",
            );
          }
        }

        if (!reviews.length) {
          track.css("animation", "none");
          track.html(`
            <div class="review-card">
              <div class="text-warning mb-2">☆☆☆☆☆</div>
              <p class="mb-2">No customer reviews yet. You can be the first reviewer.</p>
              <div class="text-secondary small">Commerza Community</div>
            </div>
          `);
        } else {
          const cardHtml = reviews
            .map((review) => {
              const rating = Math.max(
                1,
                Math.min(5, Number(review.rating) || 0),
              );
              const stars = "★".repeat(rating) + "☆".repeat(5 - rating);
              const safeName = escapeHtml(review.name || "Customer");
              const safeText = escapeHtml(review.text || "");
              const imagesMarkup = renderReviewImagesMarkup(
                review.images || [],
              );
              const dateLabel = formatReviewDate(
                review.updated_at || review.created_at,
              );

              return `
                <div class="review-card">
                  <div class="review-stars-line mb-2">
                    <span class="review-stars">${stars}</span>
                    <span class="review-score">${rating}/5</span>
                  </div>
                  <p class="mb-2">${safeText}</p>
                  ${imagesMarkup}
                  <div class="text-secondary small">${safeName}${dateLabel ? ` · ${dateLabel}` : ""}</div>
                </div>
              `;
            })
            .join("");

          const shouldMarquee = reviews.length >= 3;
          track.css(
            "animation",
            shouldMarquee ? "review-marquee 24s linear infinite" : "none",
          );
          track.html(shouldMarquee ? cardHtml + cardHtml : cardHtml);
        }

        renderReviewFormState(payload.eligibility || null, productId);
        bindProductReviewSubmit(product);
      })
      .catch((error) => {
        track.css("animation", "none");
        track.html(`
          <div class="review-card">
            <div class="text-warning mb-2">☆☆☆☆☆</div>
            <p class="mb-2">${escapeHtml(error?.message || "Unable to load reviews right now.")}</p>
            <div class="text-secondary small">Commerza</div>
          </div>
        `);
        renderReviewFormState(
          {
            can_review: false,
            message: "Reviews are temporarily unavailable.",
          },
          productId,
        );
      });
  }

  function renderRelatedProducts(data, currentProduct) {
    const container = $("#related-products-container");
    if (!container.length || !data?.sections) return;

    const allProducts = uniqueProducts(getAllProducts(data));
    const filtered = allProducts.filter(
      (p) =>
        String(p.id) !== String(currentProduct?.id) &&
        p.name !== currentProduct?.name,
    );

    const shuffled = filtered.sort(() => 0.5 - Math.random());
    const selected = shuffled.slice(0, 4);

    container.empty();
    if (selected.length === 0) {
      container.html(
        '<p class="text-secondary">No related products found.</p>',
      );
      return;
    }
    selected.forEach((product) => {
      container.append(createProductCard(product));
    });
  }

  function initProductDetailPage() {
    const container = $("#product-detail-container");
    if (!container.length) return;

    const productRequest = getProductRequestParams();

    const hasProductIdentity =
      (productRequest.slug || "").toString().trim() !== "" ||
      (productRequest.id || "").toString().trim() !== "" ||
      (productRequest.name || "").toString().trim() !== "" ||
      (productRequest.code || "").toString().trim() !== "";

    if (!hasProductIdentity) {
      renderProductDetail(null);
      renderShareButtons(null);
      renderReviewsMarquee(null);
      return;
    }

    fetchProductsData()
      .done((data) => {
        const product = findProduct(data, productRequest);

        renderProductDetail(product);
        renderShareButtons(product);
        renderReviewsMarquee(product);
        renderRelatedProducts(data, product);
      })
      .fail(() => {
        renderProductDetail(null);
      });
  }

  function handleSearch(query) {
    const trimmed = query.trim();
    if (trimmed.length === 0) {
      resetProductSections();
      return;
    }

    fetchProductsData()
      .done((data) => {
        applySearchResults(trimmed, data);
      })
      .fail(() => {
        showNotif("Search failed. Please try again.", "warning");
      });
  }

  function clearSearchSuggestions(form) {
    form.find(".search-suggestions").removeClass("show").empty();
  }

  function fetchSearchSuggestions(query, limit = 6) {
    return $.ajax({
      url: "backend/products_api.php",
      method: "GET",
      dataType: "json",
      cache: true,
      data: {
        action: "suggest",
        q: query,
        limit,
      },
    }).then((data) => {
      if (!data?.ok || !Array.isArray(data?.suggestions)) {
        return [];
      }

      return data.suggestions;
    });
  }

  $(document).on("submit", ".search-form", function (e) {
    e.preventDefault();
    const query = $(this).find('input[type="search"]').val() || "";
    handleSearch(query);
    const offcanvas = $(".offcanvas.show");
    if (
      offcanvas.length &&
      window.bootstrap &&
      typeof window.bootstrap.Offcanvas?.getInstance === "function"
    ) {
      window.bootstrap.Offcanvas.getInstance(offcanvas[0])?.hide();
    }
  });

  function renderSuggestions(input, products) {
    const form = input.closest(".search-form");
    let list = form.find(".search-suggestions");
    if (list.length === 0) {
      list = $('<div class="search-suggestions"></div>');
      form.append(list);
    }
    if (products.length === 0) {
      list.removeClass("show").empty();
      return;
    }
    list.html(
      products
        .map(
          (p) => `
            <button type="button" class="suggestion-item" data-name="${encodeURIComponent(p.name || "")}">
                <span class="suggestion-name">${escapeHtml(p.name || "")}</span>
                <span class="suggestion-price">${Number(p.salePrice || 0).toLocaleString()} PKR</span>
            </button>
        `,
        )
        .join(""),
    );
    list.addClass("show");
  }

  $(document).on("input", '.search-form input[type="search"]', function () {
    const input = $(this);
    const form = input.closest(".search-form");
    const value = input.val().trim();

    if (suggestDebounceTimer) {
      clearTimeout(suggestDebounceTimer);
      suggestDebounceTimer = null;
    }

    if (
      activeSuggestRequest &&
      typeof activeSuggestRequest.abort === "function"
    ) {
      activeSuggestRequest.abort();
      activeSuggestRequest = null;
    }

    if (value.length === 0) {
      resetProductSections();
      clearSearchSuggestions(form);
      return;
    }

    if (value.length < 2) {
      clearSearchSuggestions(form);
      return;
    }

    const querySnapshot = value;
    suggestDebounceTimer = setTimeout(() => {
      activeSuggestRequest = fetchSearchSuggestions(querySnapshot, 6)
        .done((suggestions) => {
          if (input.val().trim() !== querySnapshot) {
            return;
          }

          renderSuggestions(input, suggestions);
        })
        .fail((xhr, status) => {
          if (status !== "abort") {
            clearSearchSuggestions(form);
          }
        })
        .always(() => {
          activeSuggestRequest = null;
        });
    }, 180);
  });

  $(document).on("click", ".suggestion-item", function (e) {
    e.preventDefault();
    e.stopPropagation();
    const encodedName = ($(this).attr("data-name") || "").toString();
    let name = "";
    try {
      name = decodeURIComponent(encodedName || "");
    } catch {
      name = encodedName;
    }
    const form = $(this).closest(".search-form");
    form.find('input[type="search"]').val(name);
    clearSearchSuggestions(form);
    handleSearch(name);
  });

  $(document).on("click", function (e) {
    if (!$(e.target).closest(".search-form").length) {
      $(".search-suggestions").removeClass("show").empty();
    }
  });

  $(window).on("scroll", function () {
    if ($(this).scrollTop() > 300) {
      upBtn.addClass("show");
    } else {
      upBtn.removeClass("show");
    }
  });

  upBtn.on("click", function (e) {
    e.preventDefault();
    window.scrollTo({ top: 0, behavior: "smooth" });
  });

  $("#completeCheckoutBtn").on("click", function () {
    if (window.CommerzaUseServerCheckout) {
      return;
    }

    showNotif(
      "Server checkout is required for order placement in this build.",
      "warning",
    );
  });
});
