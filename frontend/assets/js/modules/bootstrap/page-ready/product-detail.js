// Product detail rendering and URL identity helpers.

  function normalizeProductIdentityName(value) {
    return (value || "").toString().trim().replace(/\s+/g, " ").toLowerCase();
  }

  function normalizeProductIdentityCode(value) {
    return (value || "").toString().trim().replace(/\s+/g, "").toUpperCase();
  }

  function normalizeProductIdentitySlug(value) {
    return (value || "")
      .toString()
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/-+/g, "-")
      .replace(/^-+|-+$/g, "");
  }

  function resolveProductIdentitySlug(product) {
    const explicitSlug = normalizeProductIdentitySlug(product?.slug || "");
    if (explicitSlug) {
      return explicitSlug;
    }

    const fromName = normalizeProductIdentitySlug(product?.name || "");
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

  function buildProductDetailPathFromSlug(slug) {
    const normalizedSlug = normalizeProductIdentitySlug(slug);
    if (!normalizedSlug) {
      return "products.php";
    }

    return `products/${encodeURIComponent(normalizedSlug)}`;
  }

  function buildProductDetailAbsoluteUrl(product) {
    const basePath = getProductAppBasePath();
    const detailPath = buildProductDetailPathFromSlug(
      resolveProductIdentitySlug(product),
    );

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
      return normalizeProductIdentitySlug(decodeURIComponent(match[1]));
    } catch (error) {
      return normalizeProductIdentitySlug(match[1]);
    }
  }

  function getProductRequestParams() {
    const urlParams = new URLSearchParams(window.location.search);
    const preload =
      window.CommerzaProductRequest &&
      typeof window.CommerzaProductRequest === "object"
        ? window.CommerzaProductRequest
        : {};

    const slug = normalizeProductIdentitySlug(
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
    const canonicalUrl = buildProductDetailAbsoluteUrl(product);
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
    const id = (params?.id || "").toString().trim();
    const normalizedName = normalizeProductIdentityName(params?.name || "");
    const normalizedCode = normalizeProductIdentityCode(params?.code || "");
    const normalizedSlug = normalizeProductIdentitySlug(params?.slug || "");

    if (
      id === "" &&
      normalizedName === "" &&
      normalizedCode === "" &&
      normalizedSlug === ""
    ) {
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

    const hasStrictFilters =
      normalizedSlug !== "" || normalizedCode !== "" || normalizedName !== "";

    if (hasStrictFilters) {
      const strictMatches = candidates.filter((product) => {
        if (
          normalizedSlug !== "" &&
          normalizeProductIdentitySlug(product.slug || product.name || "") !==
            normalizedSlug
        ) {
          return false;
        }

        if (
          normalizedCode !== "" &&
          normalizeProductIdentityCode(product.productCode || "") !==
            normalizedCode
        ) {
          return false;
        }

        if (
          normalizedName !== "" &&
          normalizeProductIdentityName(product.name || "") !== normalizedName
        ) {
          return false;
        }

        return true;
      });

      const strictWinner = pickByLowestId(strictMatches);
      if (strictWinner) {
        return strictWinner;
      }
    }

    if (normalizedSlug !== "") {
      const slugWinner = pickByLowestId(
        candidates.filter(
          (product) =>
            normalizeProductIdentitySlug(product.slug || product.name || "") ===
            normalizedSlug,
        ),
      );
      if (slugWinner) {
        return slugWinner;
      }
    }

    if (normalizedCode !== "") {
      const codeWinner = pickByLowestId(
        candidates.filter(
          (product) =>
            normalizeProductIdentityCode(product.productCode || "") ===
            normalizedCode,
        ),
      );
      if (codeWinner) {
        return codeWinner;
      }
    }

    if (normalizedName !== "") {
      return pickByLowestId(
        candidates.filter(
          (product) =>
            normalizeProductIdentityName(product.name || "") === normalizedName,
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
    const url = buildProductDetailAbsoluteUrl(product);
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
    const selected = shuffled.slice(0, 6);

    container.empty();
    if (selected.length === 0) {
      container.html(
        '<p class="text-secondary">No related products found.</p>',
      );
      return;
    }
    selected.forEach((product) => {
      const card = $(createProductCard(product));
      const gridColumn = card.first();
      if (gridColumn.length) {
        gridColumn.removeClass("col-lg-3 col-lg-4").addClass("col-lg-4");
      }
      container.append(card);
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

