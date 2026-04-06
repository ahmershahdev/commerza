let commerzaProductsPayloadPromise = null;

function productsEscapeHtml(value) {
  return (value || "")
    .toString()
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/\"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function productsSanitizeAssetUrl(value) {
  const raw = (value || "").toString().trim();
  if (!raw) {
    return "";
  }

  if (!/^(https?:\/\/|\/|frontend\/assets\/)/i.test(raw)) {
    return "";
  }

  return raw.replace(/[\u0000-\u001F\u007F]/g, "");
}

function fetchProductsPayload() {
  if (commerzaProductsPayloadPromise) {
    return commerzaProductsPayloadPromise;
  }

  commerzaProductsPayloadPromise = fetch("backend/products_api.php", {
    method: "GET",
    credentials: "same-origin",
    cache: "no-store",
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error("Unable to load products.");
      }
      return response.json();
    })
    .then((data) => {
      if (!data?.ok || !Array.isArray(data.sections)) {
        throw new Error("Invalid products payload.");
      }
      return data;
    })
    .catch((error) => {
      commerzaProductsPayloadPromise = null;
      throw error;
    });

  return commerzaProductsPayloadPromise;
}

function loadProductsBySection(sectionId, containerId) {
  fetchProductsPayload()
    .then((data) => {
      const section = data.sections.find((s) => s.sectionId === sectionId);
      if (!section) {
        renderProducts([], containerId);
        return;
      }
      renderProducts(section.products || [], containerId);
    })
    .catch(() => {
      const container = $(`#${containerId}`);
      if (container.length === 0) return;
      container.html(`
            <div class="text-center py-5">
                <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: #ff6600;"></i>
                <h3 class="text-white mt-3">Unable to load products</h3>
                <p class="text-secondary">Please refresh the page.</p>
            </div>
        `);
    });
}

function formatProductPrice(value) {
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) {
    return "0";
  }
  return Math.round(numeric).toLocaleString();
}

function normalizeProductCardSlug(value) {
  return (value || "")
    .toString()
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/-+/g, "-")
    .replace(/^-+|-+$/g, "");
}

function resolveProductCardSlug(product) {
  const explicitSlug = normalizeProductCardSlug(product?.slug || "");
  if (explicitSlug) {
    return explicitSlug;
  }

  const fromName = normalizeProductCardSlug(product?.name || "");
  return fromName || "product";
}

function resolveProductCardBasePath() {
  const normalizeBase = (value) => {
    const raw = (value || "").toString().trim().replace(/\\/g, "/");
    if (!raw) {
      return "";
    }

    const normalized = `/${raw.replace(/^\/+|\/+$/g, "")}/`;
    return normalized === "//" ? "/" : normalized;
  };

  const globalBase = normalizeBase(window.CommerzaAppBasePath || "");
  if (globalBase) {
    return globalBase;
  }

  const marker = "/frontend/assets/js/script.js";
  const scripts = document.getElementsByTagName("script");
  for (let index = scripts.length - 1; index >= 0; index -= 1) {
    const src = (scripts[index].getAttribute("src") || scripts[index].src || "")
      .toString()
      .trim();
    if (!src) {
      continue;
    }

    let parsed = null;
    try {
      parsed = new URL(src, window.location.href);
    } catch (error) {
      parsed = null;
    }

    if (!parsed) {
      continue;
    }

    const pathname = parsed.pathname.replace(/\\/g, "/");
    const markerIndex = pathname.toLowerCase().lastIndexOf(marker);
    if (markerIndex >= 0) {
      return normalizeBase(pathname.slice(0, markerIndex + 1)) || "/";
    }
  }

  return "/";
}

function buildProductDetailPath(product) {
  const slug = resolveProductCardSlug(product);
  if (!slug) {
    return "products.php";
  }

  const numericProductId = Number.parseInt(product?.id, 10);
  const idQuery =
    Number.isInteger(numericProductId) && numericProductId > 0
      ? `?id=${encodeURIComponent(String(numericProductId))}`
      : "";

  return `${resolveProductCardBasePath()}products/${encodeURIComponent(slug)}${idQuery}`;
}

function buildProductRatingMarkup(product) {
  const ratingCount = Math.max(0, parseInt(product?.ratingCount, 10) || 0);
  const rawAverage = Number(product?.ratingAverage);
  const ratingAverage =
    ratingCount > 0 && Number.isFinite(rawAverage)
      ? Math.max(0, Math.min(5, rawAverage))
      : 0;
  const ratingPercent = (ratingAverage / 5) * 100;
  const ratingStateClass = ratingCount > 0 ? "has-reviews" : "no-reviews";
  const ratingLabel =
    ratingCount > 0
      ? `${ratingAverage.toFixed(1)} out of 5 stars from ${ratingCount} reviews`
      : "No reviews yet";
  const reviewWord = ratingCount === 1 ? "review" : "reviews";
  const ratingScore = ratingCount > 0 ? ratingAverage.toFixed(1) : "New";
  const ratingMeta =
    ratingCount > 0 ? `${ratingCount} ${reviewWord}` : "Be first to review";

  return `
    <div class="product-card-rating ${ratingStateClass}" aria-label="${productsEscapeHtml(ratingLabel)}">
      <span class="rating-stars" aria-hidden="true">
        <span class="rating-stars-base">★★★★★</span>
        <span class="rating-stars-fill" style="width:${ratingPercent.toFixed(2)}%">★★★★★</span>
      </span>
      <span class="rating-score">${productsEscapeHtml(ratingScore)}</span>
      <span class="rating-meta">${productsEscapeHtml(ratingMeta)}</span>
    </div>
  `;
}

function setProductCardMotion(card, pointerX, pointerY) {
  const rect = card.getBoundingClientRect();
  if (rect.width <= 0 || rect.height <= 0) {
    return;
  }

  const relativeX = (pointerX - rect.left) / rect.width;
  const relativeY = (pointerY - rect.top) / rect.height;
  const rotateY = (relativeX - 0.5) * 10;
  const rotateX = (0.5 - relativeY) * 8;
  const shiftX = (relativeX - 0.5) * 8;
  const shiftY = (relativeY - 0.5) * 2;

  card.style.setProperty("--pc-rotate-x", `${rotateX.toFixed(2)}deg`);
  card.style.setProperty("--pc-rotate-y", `${rotateY.toFixed(2)}deg`);
  card.style.setProperty("--pc-shift-x", `${shiftX.toFixed(2)}px`);
  card.style.setProperty("--pc-shift-y", `${shiftY.toFixed(2)}px`);
}

function resetProductCardMotion(card) {
  card.style.setProperty("--pc-rotate-x", "0deg");
  card.style.setProperty("--pc-rotate-y", "0deg");
  card.style.setProperty("--pc-shift-x", "0px");
  card.style.setProperty("--pc-shift-y", "0px");
}

function bindProductCardMotion(container) {
  const host = container?.get?.(0) || null;
  if (!host || host.dataset.productMotionBound === "1") {
    return;
  }

  host.dataset.productMotionBound = "1";

  if (
    window.matchMedia &&
    !window.matchMedia("(hover: hover) and (pointer: fine)").matches
  ) {
    return;
  }

  host.addEventListener("mousemove", (event) => {
    const card = event.target.closest(".product-card");
    if (!card || !host.contains(card)) {
      return;
    }

    setProductCardMotion(card, event.clientX, event.clientY);
  });

  host.addEventListener("mouseout", (event) => {
    const card = event.target.closest(".product-card");
    if (!card || !host.contains(card)) {
      return;
    }

    const nextTarget = event.relatedTarget;
    if (nextTarget && card.contains(nextTarget)) {
      return;
    }

    resetProductCardMotion(card);
  });

  host.addEventListener("mouseleave", () => {
    host.querySelectorAll(".product-card").forEach((card) => {
      resetProductCardMotion(card);
    });
  });
}

function renderProducts(products, containerId) {
  const container = $(`#${containerId}`);
  if (container.length === 0) return;

  container.empty();

  const productsPerRow = 4;
  const remainder = products.length % productsPerRow;
  const shouldCenterLastRow = remainder > 0 && remainder <= 3;
  const splitIndex = shouldCenterLastRow
    ? products.length - remainder
    : products.length;

  const firstBatch = products.slice(0, splitIndex);
  firstBatch.forEach((product) => {
    container.append(createProductCard(product));
  });

  if (shouldCenterLastRow) {
    const lastBatch = products.slice(splitIndex);
    lastBatch.forEach((product) => {
      const productCardHtml = createProductCard(product);
      const wrappedCard = $(productCardHtml);
      wrappedCard.addClass("last-row-product");
      container.append(wrappedCard);
    });
  }

  bindProductCardMotion(container);
}

function createProductCard(product) {
  const originalPrice = formatProductPrice(product.price);
  const effectiveSalePrice =
    Number(product.salePrice) > 0 ? product.salePrice : product.price;
  const salePrice = formatProductPrice(effectiveSalePrice);
  const numericProductId = Number.parseInt(product.id, 10);
  const safeProductId = Number.isInteger(numericProductId)
    ? String(numericProductId)
    : "";
  const safeName = productsEscapeHtml(product.name || "");
  const safeNameLower = productsEscapeHtml(
    (product.name || "").toLowerCase().trim(),
  );
  const safeDescription = productsEscapeHtml(product.description || "");
  const safeImage = productsEscapeHtml(productsSanitizeAssetUrl(product.image));
  const resolvedProductCode =
    (product.productCode || "").toString().trim() ||
    (safeProductId !== ""
      ? `CMRZ-${safeProductId.padStart(5, "0")}`
      : "CMRZ-NA");
  const safeProductCode = productsEscapeHtml(resolvedProductCode);
  const safePriceValue = Number.isFinite(Number(product.price))
    ? String(Number(product.price))
    : "0";
  const safeSalePriceValue = Number.isFinite(Number(product.salePrice))
    ? String(Number(product.salePrice))
    : safePriceValue;
  const movementType =
    product.movement === "auto"
      ? "auto"
      : product.movement === "smart"
        ? "smart"
        : "quartz";
  const saleBadge =
    product.movement !== "smart"
      ? '<span class="sale-badge">PREMIUM SALE</span>'
      : "";
  const detailUrl = productsEscapeHtml(buildProductDetailPath(product));
  const wishlistActive = isInWishlist(product.id, product.name);
  const wishlistIcon = wishlistActive ? "bi-heart-fill" : "bi-heart";
  const ratingMarkup = buildProductRatingMarkup(product);

  return `
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4 d-flex">
        <div class="card product-card" data-price="${safeSalePriceValue}" data-movement="${movementType}" data-product-id="${safeProductId}" data-product-name="${safeNameLower}">
            <div class="image-container">
          <button class="wishlist-btn ${wishlistActive ? "active" : ""}" data-product-id="${safeProductId}" data-product-name="${safeName}" data-product-code="${safeProductCode}" data-product-image="${safeImage}" data-product-price="${safePriceValue}" data-product-sale-price="${safeSalePriceValue}" type="button" aria-label="Toggle wishlist">
                        <i class="bi ${wishlistIcon}"></i>
                    </button>
                    <a href="${detailUrl}" style="text-decoration: none; color: inherit;">
              <img src="${safeImage}"
            class="card-img-top p-image product-image" loading="lazy" alt="${safeName}">
                    </a>
                    ${saleBadge}
                </div>
                <div class="card-body">
                    <h3 class="card-title product-name">
              <a href="${detailUrl}" style="text-decoration: none; color: inherit;">${safeName}</a>
                    </h3>
              ${ratingMarkup}
            <p class="card-text product-desc">${safeDescription}</p>
                    <div class="mb-3">
                        <span class="original-price"
                            style="text-decoration: line-through; color: #b0b0b0;">${originalPrice} PKR</span>
                        <span class="sale-price"
                            style="color: #ff6600; font-weight: bold; margin-left: 5px;">${salePrice} PKR</span>
                    </div>
                    <div class="d-flex gap-2">
                  <a href="#" class="btn product-btn-buy product-btn-cart flex-fill text-center justify-content-center align-items-center" data-product-id="${safeProductId}" data-product-name="${safeName}" data-product-code="${safeProductCode}" data-product-image="${safeImage}" data-product-price="${safePriceValue}" data-product-sale-price="${safeSalePriceValue}">Buy Now</a>
                  <a href="#" class="btn product-btn-cart flex-fill text-center justify-content-center align-items-center" data-product-id="${safeProductId}" data-product-name="${safeName}" data-product-code="${safeProductCode}" data-product-image="${safeImage}" data-product-price="${safePriceValue}" data-product-sale-price="${safeSalePriceValue}">Add to Cart</a>
                    </div>
                </div>
            </div>
        </div>
    `;
}
