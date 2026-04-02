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

  applySiteSettings();
  ensureLegalNavLinks();

  const carouselElement = document.getElementById("carouselExampleIndicators");
  const playPauseBtn = document.getElementById("carouselPlayPause");

  if (carouselElement && playPauseBtn) {
    const carousel = new bootstrap.Carousel(carouselElement, {
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
    const dataName = btn.data("productName");
    const dataPrice = btn.data("productSalePrice") || btn.data("productPrice");
    const dataImage = btn.data("productImage");

    let name = dataName;
    let price = dataPrice;
    let image = dataImage;

    if (!name || !price || !image) {
      const card = btn.closest(".product-card");
      const detailCard = btn.closest(".product-detail-card");
      const scope = card.length ? card : detailCard;

      if (!name) {
        name = scope.find(".product-name").first().text().trim();
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
      name: name || "",
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
    const isDirectContactLink =
      href.startsWith("mailto:") || href.startsWith("tel:");
    const hasProductContext =
      !!btn.data("productName") ||
      !!btn.data("productId") ||
      btn.closest(".product-detail-card").length > 0 ||
      btn.closest(".product-card[data-product-name]").length > 0;
    const isNavigationLink =
      href !== "" &&
      href !== "#" &&
      !href.startsWith("javascript:") &&
      href.indexOf("compare.php") === -1;
    if (
      $(this).hasClass("change-qty") ||
      $(this).hasClass("compare-remove-btn") ||
      $(this).hasClass("wishlist-btn") ||
      $(this).hasClass("compare-link") ||
      (href && href.indexOf("compare.php") !== -1) ||
      isDirectContactLink ||
      isNavigationLink ||
      !hasProductContext
    )
      return;
    e.preventDefault();

    await initCartState();

    if (getTotalCartQty() >= 10) {
      triggerAlert();
      return;
    }

    const productId = parseInt(btn.data("productId"), 10);
    if (!Number.isInteger(productId) || productId <= 0) {
      showNotif("Unable to add this product. Invalid product id.", "warning");
      return;
    }

    const img = getProductImageElement(btn);
    const cartIcon = $("#cart-icon");

    const addResult = await postCartAction("add", {
      product_id: productId,
      quantity: 1,
    });

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

    if (action === "plus") {
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
    displayCartItems();
  });

  $(document).on("click", ".remove-item", async function () {
    const index = $(this).data("index");
    const item = cart[index];

    if (!item || !Number.isInteger(parseInt(item.id, 10))) {
      showNotif("Unable to remove this cart item.", "warning");
      return;
    }

    const result = await postCartAction("remove", {
      product_id: parseInt(item.id, 10),
    });

    if (!result.ok) {
      showNotif(result.message, "warning");
      return;
    }

    updateCartBadge();
    displayCartItems();
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

  function displayCartItems() {
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
      const effectivePrice =
        Number(item.salePrice) > 0
          ? Number(item.salePrice)
          : Number(item.price);
      const displayPrice =
        Number.isFinite(effectivePrice) && effectivePrice > 0
          ? `${effectivePrice.toLocaleString()} PKR`
          : "N/A";

      container.append(`
                <div class="card product-card mb-3">
                  <div class="card-body d-flex align-items-center gap-3">
                    <img src="${item.image}" class="cart-img me-3" alt="${item.name}" />
                    <div class="flex-grow-1 text-center">
                      <h3 class="product-name mb-1">${item.name}</h3>
                      <p class="product-desc mb-2 cart-item-price">${displayPrice}</p>
                      <div class="d-flex align-items-center justify-content-center gap-3 mx-auto" style="max-width: 150px;">
                        <button class="btn btn-sm product-btn-cart change-qty" data-index="${index}" data-action="minus">−</button>
                        <span class="text-white fw-bold">${item.quantity}</span>
                        <button class="btn btn-sm product-btn-cart change-qty" data-index="${index}" data-action="plus">+</button>
                      </div>
                    </div>
                    <button class="btn btn-sm btn-danger remove-item ms-3 align-self-start" data-index="${index}" aria-label="Remove ${item.name} from cart">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>
            `);
    });
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

  async function fetchLiveViewerCount(productId, heartbeat) {
    const params = new URLSearchParams({
      action: "count",
      product_id: String(productId),
      heartbeat: heartbeat ? "1" : "0",
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
        const result = await fetchLiveViewerCount(
          normalizedProductId,
          heartbeat,
        );
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
      cache: false,
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

  function updateProductMeta(product) {
    if (!product) return;
    const title = `${product.name} | Commerza`;
    const description =
      product.description ||
      "Discover premium Commerza watches and accessories.";
    const canonicalUrl = `${window.location.origin}/products.php?${product.id != null ? `id=${product.id}` : `name=${encodeURIComponent(product.name)}`}`;
    const imageUrl = product.image?.startsWith("http")
      ? product.image
      : `${window.location.origin}/${product.image}`;

    document.title = title;
    $('meta[name="description"]').attr("content", description);
    $('meta[property="og:title"]').attr("content", title);
    $('meta[property="og:description"]').attr("content", description);
    $('meta[property="og:url"]').attr("content", canonicalUrl);
    if (imageUrl) $('meta[property="og:image"]').attr("content", imageUrl);
    $('link[rel="canonical"]').attr("href", canonicalUrl);
  }

  function findProduct(data, params) {
    if (!data?.sections) return null;
    const id = params?.id;
    const name = params?.name;
    for (const section of data.sections) {
      const products = section.products || [];
      for (const product of products) {
        if (id != null && String(product.id) === String(id)) {
          return {
            ...product,
            sectionName: section.sectionName,
            sectionId: section.sectionId,
          };
        }
        if (
          name &&
          (product.name || "").toLowerCase().trim() ===
            name.toLowerCase().trim()
        ) {
          return {
            ...product,
            sectionName: section.sectionName,
            sectionId: section.sectionId,
          };
        }
      }
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
    const wishlistActive = isInWishlist(product.id, product.name);
    const wishlistIcon = wishlistActive ? "bi-heart-fill" : "bi-heart";
    const compareActive = isInCompare(product.id, product.name);
    const compareIcon = compareActive ? "bi-check2-circle" : "bi-sliders";

    container.html(`
            <div class="product-detail-card">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-5">
                        <div class="product-media">
                            <img src="${product.image}" class="p-image" alt="${product.name}">
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="product-badge-row">
                            <span class="movement-badge ${movementClass}">${movementLabel}</span>
                            <span class="stock-pill">${stockText}</span>
                          <span class="live-viewers">
                                <span class="live-dot" aria-hidden="true"></span>
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            <span data-live-viewers-text>Loading live viewers...</span>
                            </span>
                        </div>
                        <h1 class="product-name">${product.name}</h1>
                        <p class="product-desc">${product.description || ""}</p>
                        <div class="price-stack">
                            ${originalPrice ? `<span class="price-original">${originalPrice} PKR</span>` : ""}
                            ${salePrice ? `<span class="price-sale">${salePrice} PKR</span>` : ""}
                        </div>
                        <div class="spec-grid">
                            <div class="spec-card">
                                <span class="spec-label">Movement</span>
                                <span class="spec-value">${movementLabel}</span>
                            </div>
                            <div class="spec-card">
                                <span class="spec-label">Collection</span>
                                <span class="spec-value">${product.sectionName || "Commerza"}</span>
                            </div>
                            <div class="spec-card">
                                <span class="spec-label">Availability</span>
                                <span class="spec-value">${stockText}</span>
                            </div>
                        </div>
                        <div class="product-actions">
                            <a href="#" class="btn product-btn-buy product-btn-cart" data-product-id="${product.id ?? ""}" data-product-name="${product.name}" data-product-image="${product.image}" data-product-price="${product.price}" data-product-sale-price="${product.salePrice}">Buy Now</a>
                            <a href="#" class="btn product-btn-cart" data-product-id="${product.id ?? ""}" data-product-name="${product.name}" data-product-image="${product.image}" data-product-price="${product.price}" data-product-sale-price="${product.salePrice}">Add to Cart</a>
                            <button class="btn product-btn-buy wishlist-btn ${wishlistActive ? "active" : ""}" data-product-id="${product.id ?? ""}" data-product-name="${product.name}" data-product-image="${product.image}" data-product-price="${product.price}" data-product-sale-price="${product.salePrice}" type="button">
                                ${wishlistActive ? "In Wishlist" : "Add to Wishlist"}
                            </button>
                            <button class="btn product-btn-buy compare-btn" data-product-id="${product.id ?? ""}" data-product-name="${product.name}" data-product-image="${product.image}" data-product-price="${product.price}" data-product-sale-price="${product.salePrice}" data-product-stock="${product.stock ?? ""}" data-product-movement="${product.movement ?? ""}" type="button">
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

    const url = `${window.location.origin}/products.php?${product.id != null ? `id=${product.id}` : `name=${encodeURIComponent(product.name)}`}`;
    const text = encodeURIComponent(`Check out ${product.name} on Commerza.`);
    container.html(`
            <a class="btn" href="https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}" target="_blank" rel="noopener">Facebook</a>
            <a class="btn" href="https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${text}" target="_blank" rel="noopener">X</a>
            <a class="btn" href="https://wa.me/?text=${text}%20${encodeURIComponent(url)}" target="_blank" rel="noopener">WhatsApp</a>
            <button type="button" class="btn" id="copyProductLink">Copy Link</button>
        `);

    $("#copyProductLink")
      .off("click")
      .on("click", function () {
        navigator.clipboard
          ?.writeText(url)
          .then(() => {
            showNotif("Product link copied.", "success");
          })
          .catch(() => {
            showNotif("Unable to copy link.", "warning");
          });
      });
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

  function formatReviewDate(value) {
    if (!value) return "";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return "";
    }
    return date.toLocaleDateString();
  }

  function renderReviewFormState(eligibility, productId) {
    const form = $("#productReviewForm");
    const message = $("#reviewEligibilityMessage");
    const ratingInput = $("#reviewRating");
    const textInput = $("#reviewText");
    const submitBtn = $("#reviewSubmitBtn");
    const hiddenProductInput = $("#reviewProductId");

    if (!form.length) {
      return;
    }

    hiddenProductInput.val(String(productId || ""));

    const canReview = !!eligibility?.can_review;
    const existingReview = eligibility?.existing_review || null;
    const statusMessage = (eligibility?.message || "").toString().trim();

    if (existingReview) {
      if (ratingInput.length) {
        ratingInput.val(String(existingReview.rating || "5"));
      }
      if (textInput.length && !textInput.val()) {
        textInput.val((existingReview.text || "").toString());
      }
    }

    ratingInput.prop("disabled", !canReview);
    textInput.prop("disabled", !canReview);
    submitBtn.prop("disabled", !canReview);

    submitBtn.text(existingReview ? "Update Review" : "Submit Review");

    if (message.length) {
      message
        .removeClass("text-secondary text-warning text-success")
        .addClass(canReview ? "text-success" : "text-warning")
        .text(
          statusMessage ||
            (canReview
              ? "You can submit a verified review for this product."
              : "Login and complete an eligible delivered order to review."),
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

      const rating = parseInt($("#reviewRating").val(), 10) || 0;
      const text = ($("#reviewText").val() || "").toString().trim();

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

      const submitBtn = $("#reviewSubmitBtn");
      const originalText = submitBtn.text();
      submitBtn.prop("disabled", true).text("Submitting...");

      try {
        const body = new URLSearchParams();
        body.set("action", "submit");
        body.set("product_id", String(productId));
        body.set("rating", String(rating));
        body.set("review_text", text);
        body.set("csrf_token", reviewsCsrfToken || "");

        const response = await fetch("backend/reviews_api.php", {
          method: "POST",
          credentials: "same-origin",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
          },
          body: body.toString(),
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
        $("#reviewText").val("");
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
              const dateLabel = formatReviewDate(
                review.updated_at || review.created_at,
              );

              return `
                <div class="review-card">
                  <div class="text-warning mb-2">${stars}</div>
                  <p class="mb-2">${safeText}</p>
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

    const params = new URLSearchParams(window.location.search);
    const productId = params.get("id");
    const productName = params.get("name");

    fetchProductsData()
      .done((data) => {
        const product = findProduct(data, { id: productId, name: productName });
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

  $(document).on("submit", ".search-form", function (e) {
    e.preventDefault();
    const query = $(this).find('input[type="search"]').val() || "";
    handleSearch(query);
    const offcanvas = $(".offcanvas.show");
    if (offcanvas.length) {
      bootstrap.Offcanvas.getInstance(offcanvas[0])?.hide();
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
            <button type="button" class="suggestion-item" data-name="${p.name}">
                <span class="suggestion-name">${p.name}</span>
                <span class="suggestion-price">${parseInt(p.salePrice).toLocaleString()} PKR</span>
            </button>
        `,
        )
        .join(""),
    );
    list.addClass("show");
  }

  function buildSuggestions(query) {
    const trimmed = query.trim();
    if (trimmed.length < 2) return [];
    if (!productsCache) return [];
    const normalized = trimmed.toLowerCase();
    const allProducts = uniqueProducts(getAllProducts(productsCache));
    return allProducts
      .filter((p) => {
        const haystack = `${p.name} ${p.description}`.toLowerCase();
        return haystack.includes(normalized);
      })
      .slice(0, 6);
  }

  $(document).on("input", '.search-form input[type="search"]', function () {
    const input = $(this);
    const value = input.val().trim();
    if (value.length === 0) {
      resetProductSections();
      input
        .closest(".search-form")
        .find(".search-suggestions")
        .removeClass("show")
        .empty();
      return;
    }
    fetchProductsData().done(() => {
      const suggestions = buildSuggestions(value);
      renderSuggestions(input, suggestions);
    });
  });

  $(document).on("click", ".suggestion-item", function (e) {
    e.preventDefault();
    e.stopPropagation();
    const name = $(this).data("name");
    const form = $(this).closest(".search-form");
    form.find('input[type="search"]').val(name);
    form.find(".search-suggestions").removeClass("show").empty();
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
