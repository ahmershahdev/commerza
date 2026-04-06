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

function productsNormalizeSlug(value) {
  return (value || "")
    .toString()
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/-+/g, "-")
    .replace(/^-+|-+$/g, "");
}

function productsResolveSlug(product) {
  const explicitSlug = productsNormalizeSlug(product?.slug);
  if (explicitSlug) {
    return explicitSlug;
  }

  const fromName = productsNormalizeSlug(product?.name || "");
  return fromName || "product";
}

function productsBuildDetailPath(product) {
  const params = new URLSearchParams();
  const numericProductId = Number.parseInt(product?.id, 10);
  const name = (product?.name || "").toString().trim();
  const code = (product?.productCode || "").toString().trim();

  if (Number.isInteger(numericProductId) && numericProductId > 0) {
    params.set("id", String(numericProductId));
  }

  if (name !== "") {
    params.set("name", name);
  }

  if (code !== "") {
    params.set("code", code);
  }

  const query = params.toString();
  return query ? `products.php?${query}` : "products.php";
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
  const safeProductCode = productsEscapeHtml(
    (product.productCode || "").toString().trim(),
  );
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
  const detailUrl = productsEscapeHtml(productsBuildDetailPath(product));
  const wishlistActive = isInWishlist(product.id, product.name);
  const wishlistIcon = wishlistActive ? "bi-heart-fill" : "bi-heart";

  return `
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4 d-flex">
        <div class="card product-card" data-price="${safeSalePriceValue}" data-movement="${movementType}" data-product-id="${safeProductId}" data-product-name="${safeNameLower}">
                <div style="position: relative;">
          <button class="wishlist-btn ${wishlistActive ? "active" : ""}" data-product-id="${safeProductId}" data-product-name="${safeName}" data-product-code="${safeProductCode}" data-product-image="${safeImage}" data-product-price="${safePriceValue}" data-product-sale-price="${safeSalePriceValue}" type="button" aria-label="Toggle wishlist">
                        <i class="bi ${wishlistIcon}"></i>
                    </button>
                    <a href="${detailUrl}" style="text-decoration: none; color: inherit;">
              <img src="${safeImage}"
                class="card-img-top p-image" loading="lazy" alt="${safeName}">
                    </a>
                    ${saleBadge}
                </div>
                <div class="card-body">
                    <h3 class="card-title product-name">
              <a href="${detailUrl}" style="text-decoration: none; color: inherit;">${safeName}</a>
                    </h3>
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
