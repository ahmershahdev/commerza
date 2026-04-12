// Catalog data, filters, search, and suggestion handlers.

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
  const rawQuery = (query || "").toString().trim();
  const isFilterState = rawQuery.toLowerCase() === "filters";
  const safeQuery = escapeHtml(rawQuery);

  const title = isFilterState
    ? "No products match these filters"
    : "No products found";
  const detail = isFilterState
    ? "Try switching Section or Movement back to All, or choose Featured sort."
    : `No direct matches for "${safeQuery}" in this collection.`;
  const guidance = isFilterState
    ? "Reset filters to instantly restore full collections."
    : "Use fewer keywords or press Enter from the search bar for deeper search. Try keywords like smart, quartz, chronograph, or automatic.";

  container.html(`
            <div class="search-empty-stage text-center py-5">
                <span class="search-empty-cursor-ball" aria-hidden="true"></span>
                <p class="search-empty-eyebrow">Catalog Intelligence</p>
                <h3 class="search-empty-title">${title}</h3>
                <p class="search-empty-detail">${detail}</p>
                <small class="search-empty-guidance">${guidance}</small>
            </div>
        `);
}

function getAllProducts(data) {
  return data.sections.flatMap((section) => section.products || []);
}

function uniqueProducts(products) {
  const seen = new Set();
  return products.filter((product) => {
    const id = Number(product?.id || 0);
    const key =
      id > 0
        ? `id:${id}`
        : `${product?.name || ""}-${product?.salePrice || product?.price || 0}-${product?.image || ""}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

function getSearchIndex(data) {
  if (searchIndexCache.source === data && searchIndexCache.index.length > 0) {
    return searchIndexCache.index;
  }

  const rows = [];
  const seen = new Set();

  (data?.sections || []).forEach((section) => {
    const sectionId = (section?.sectionId || "").toString().trim();
    (section?.products || []).forEach((product) => {
      const id = Number(product?.id || 0);
      const name = (product?.name || "").toString().trim();
      if (!name) {
        return;
      }

      const dedupeKey = id > 0 ? `id:${id}` : `${name}-${sectionId}`;
      if (seen.has(dedupeKey)) {
        return;
      }
      seen.add(dedupeKey);

      const description = (product?.description || "").toString().trim();
      const productCode = (product?.code || product?.productCode || "")
        .toString()
        .trim();

      rows.push({
        sectionId,
        product: {
          ...product,
          __sectionId: sectionId,
        },
        searchBlob: `${name} ${description} ${productCode}`.toLowerCase(),
      });
    });
  });

  searchIndexCache = {
    source: data,
    index: rows,
  };

  return rows;
}

function applySearchResults(query, data) {
  const normalized = query.toLowerCase().trim();
  const activeTargets = getActiveSearchTargets();

  if (activeTargets.length === 0) return;

  const isIndexSearch =
    activeTargets.length === 1 &&
    activeTargets[0].containerId === "featured-products-container";
  const indexed = getSearchIndex(data);
  const matchedRows = indexed.filter((entry) =>
    entry.searchBlob.includes(normalized),
  );

  activeTargets.forEach((target) => {
    let matched = [];
    if (isIndexSearch) {
      matched = matchedRows.map((entry) => entry.product);
    } else {
      const allowedSections = new Set(target.sectionIds || []);
      matched = matchedRows
        .filter((entry) => allowedSections.has(entry.sectionId))
        .map((entry) => entry.product);
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

function resolveCatalogApiUrl(path) {
  const rawBasePath = (window.CommerzaAppBasePath || "/")
    .toString()
    .replace(/\\/g, "/");
  const normalizedBasePath = rawBasePath.endsWith("/")
    ? rawBasePath
    : `${rawBasePath}/`;
  const normalizedPath = (path || "").toString().replace(/^\/+/, "");
  return `${normalizedBasePath}${normalizedPath}`;
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
    pageFilterCache = new Map();
    searchIndexCache = {
      source: null,
      index: [],
    };
    suggestionIndexCache = {
      source: null,
      index: [],
    };
    suggestionResultCache = new Map();
  }

  if (productsCache) {
    return $.Deferred().resolve(productsCache).promise();
  }

  return $.ajax({
    url: resolveCatalogApiUrl("backend/api/products_api.php"),
    method: "GET",
    dataType: "json",
    cache: true,
  }).then((data) => {
    const normalized = {
      sections: Array.isArray(data?.sections) ? data.sections : [],
    };
    productsCache = normalized;
    pageFilterCache = new Map();
    searchIndexCache = {
      source: null,
      index: [],
    };
    suggestionIndexCache = {
      source: null,
      index: [],
    };
    suggestionResultCache = new Map();
    return normalized;
  });
}

function getCurrentPageKey() {
  const rawPath = window.location.pathname.replace(/\\/g, "/");
  const rawBasePath = (window.CommerzaAppBasePath || "/")
    .toString()
    .replace(/\\/g, "/");
  const normalizedBasePath = `/${rawBasePath
    .replace(/^\/+|\/+$/g, "")
    .toLowerCase()}/`;

  let relativePath = rawPath;
  if (
    normalizedBasePath !== "//" &&
    rawPath.toLowerCase().startsWith(normalizedBasePath)
  ) {
    relativePath = `/${rawPath.slice(normalizedBasePath.length)}`;
  }

  const normalizedPath = relativePath.replace(/\/+/g, "/");
  const segments = normalizedPath.split("/").filter(Boolean);
  if (segments.length === 0) {
    return "index.php";
  }

  const firstSegment = (segments[0] || "").toLowerCase();
  if (["product", "products", "prodcuts"].includes(firstSegment)) {
    return "products.php";
  }

  if (firstSegment === "account") {
    return "account.php";
  }

  const file = segments[segments.length - 1] || "";
  return normalizePageFileName(file) || "index.php";
}

function normalizePageFileName(pageName) {
  const value = (pageName || "").toString().trim().split("?")[0].split("#")[0];
  if (!value) return "";
  const file = value.split("/").filter(Boolean).pop();
  if (!file) return "";

  const lower = file.toLowerCase();

  if (lower.endsWith(".php")) {
    return lower;
  }

  if (lower.endsWith(".html")) {
    return `${lower.slice(0, -5)}.php`;
  }

  if (lower === "home" || lower === "index") {
    return "index.php";
  }

  if (lower === "product" || lower === "products" || lower === "prodcuts") {
    return "products.php";
  }

  if (lower === "account") {
    return "account.php";
  }

  if (lower.includes(".")) {
    return lower;
  }

  return `${lower}.php`;
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
  return sections.flatMap((section) => {
    const sectionId = (section?.sectionId || "").toString().trim();
    const sectionName = (section?.sectionName || "").toString().trim();
    return (section.products || []).map((product) => ({
      ...product,
      __sectionId: sectionId,
      __sectionName: sectionName,
    }));
  });
}

function normalizeMovementType(product) {
  const raw = (product?.movement || "quartz").toString().trim().toLowerCase();
  if (raw === "auto" || raw === "automatic" || raw.startsWith("auto")) {
    return "auto";
  }
  if (raw === "smart" || raw.startsWith("smart") || raw === "digital") {
    return "smart";
  }
  return "quartz";
}

function buildMovementOptions(products) {
  const movementSet = new Set();
  products.forEach((product) => {
    movementSet.add(normalizeMovementType(product));
  });
  const order = ["auto", "smart", "quartz"];
  return order.filter((item) => movementSet.has(item));
}

function formatMovementLabel(value) {
  if (value === "auto") return "Automatic";
  if (value === "smart") return "Smart";
  return "Quartz";
}

function getPageFilterData(data, pageKey) {
  const normalizedPageKey = normalizePageFileName(pageKey);
  const cached = pageFilterCache.get(normalizedPageKey);
  if (cached && cached.source === data) {
    return cached;
  }

  const sections = getSectionsForPage(data, normalizedPageKey);
  const products = uniqueProducts(getProductsForSections(sections));
  const sectionBuckets = new Map();
  const movementBuckets = new Map();

  products.forEach((product) => {
    const sectionId = (product?.__sectionId || "").toString().trim();
    const movement = normalizeMovementType(product);

    if (sectionId !== "") {
      if (!sectionBuckets.has(sectionId)) {
        sectionBuckets.set(sectionId, []);
      }
      sectionBuckets.get(sectionId).push(product);
    }

    if (!movementBuckets.has(movement)) {
      movementBuckets.set(movement, []);
    }
    movementBuckets.get(movement).push(product);
  });

  const built = {
    source: data,
    pageKey: normalizedPageKey,
    sections,
    products,
    sectionBuckets,
    movementBuckets,
    sortedBuckets: new Map(),
  };

  pageFilterCache.set(normalizedPageKey, built);
  return built;
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

  const pageData = getPageFilterData(data, getCurrentPageKey());
  const sections = pageData.sections;
  const products = pageData.products;

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
  const parsed = Number(price);
  return Number.isFinite(parsed) ? parsed : 0;
}

function sortProducts(products, sortValue, cacheMap = null, cacheKey = "") {
  if (
    !Array.isArray(products) ||
    products.length <= 1 ||
    sortValue === "default"
  ) {
    return Array.isArray(products) ? [...products] : [];
  }

  if (cacheMap && cacheKey && cacheMap.has(cacheKey)) {
    return [...cacheMap.get(cacheKey)];
  }

  const copy = products.slice();
  if (sortValue === "price-asc") {
    copy.sort((a, b) => getPriceValue(a) - getPriceValue(b));
  } else if (sortValue === "price-desc") {
    copy.sort((a, b) => getPriceValue(b) - getPriceValue(a));
  } else if (sortValue === "name-asc") {
    copy.sort((a, b) =>
      (a.name || "").localeCompare(b.name || "", undefined, {
        sensitivity: "base",
        numeric: true,
      }),
    );
  }

  if (cacheMap && cacheKey) {
    cacheMap.set(cacheKey, copy.slice());
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
    const pageData = getPageFilterData(data, getCurrentPageKey());

    let products =
      sectionValue === "all"
        ? pageData.products
        : pageData.sectionBuckets.get(sectionValue) || [];

    if (movementValue !== "all") {
      if (sectionValue === "all") {
        products = pageData.movementBuckets.get(movementValue) || [];
      } else {
        products = products.filter(
          (product) => normalizeMovementType(product) === movementValue,
        );
      }
    }

    const shouldCacheSort = sectionValue === "all" && movementValue === "all";
    const sortCacheKey = shouldCacheSort ? `all|${sortValue}` : "";
    products = sortProducts(
      products,
      sortValue,
      shouldCacheSort ? pageData.sortedBuckets : null,
      sortCacheKey,
    );

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

function suggestionSearchIndex(data) {
  if (
    suggestionIndexCache.source === data &&
    suggestionIndexCache.index.length > 0
  ) {
    return suggestionIndexCache.index;
  }

  const allProducts = uniqueProducts(getAllProducts(data));
  const indexed = allProducts
    .filter((product) => {
      const id = Number(product?.id || 0);
      const name = (product?.name || "").toString().trim();
      return id > 0 && name !== "";
    })
    .map((product) => {
      const name = (product?.name || "").toString().trim();
      const description = (product?.description || "").toString().trim();
      const productCode = (product?.code || product?.productCode || "")
        .toString()
        .trim();

      return {
        id: Number(product.id || 0),
        name,
        nameLower: name.toLowerCase(),
        image: (product?.image || "").toString().trim(),
        salePrice: Number(product?.salePrice || product?.price || 0) || 0,
        productCode,
        searchBlob: `${name} ${description} ${productCode}`.toLowerCase(),
      };
    });

  suggestionIndexCache = {
    source: data,
    index: indexed,
  };
  suggestionResultCache = new Map();

  return indexed;
}

function getLocalSuggestions(query, data, limit = 8) {
  const normalized = (query || "").toString().trim().toLowerCase();
  if (normalized.length < 2) {
    return [];
  }

  const safeLimit = Math.max(1, limit);
  const cacheKey = `${normalized}|${safeLimit}`;
  if (suggestionResultCache.has(cacheKey)) {
    return suggestionResultCache.get(cacheKey).slice(0, safeLimit);
  }

  const indexed = suggestionSearchIndex(data);
  const queryTokens = normalized.split(/\s+/).filter(Boolean);
  const ranked = [];

  indexed.forEach((item) => {
    const includesWhole = item.searchBlob.includes(normalized);
    const includesTokens = queryTokens.every((token) =>
      item.searchBlob.includes(token),
    );

    if (!includesWhole && !includesTokens) {
      return;
    }

    let score = 0;

    if (item.nameLower.startsWith(normalized)) {
      score += 180;
    } else if (item.nameLower.includes(normalized)) {
      score += 95;
    }

    queryTokens.forEach((token) => {
      if (item.nameLower.startsWith(token)) {
        score += 34;
        return;
      }

      if (item.nameLower.includes(token)) {
        score += 16;
        return;
      }

      if (item.searchBlob.includes(token)) {
        score += 6;
      }
    });

    const codeLower = item.productCode.toLowerCase();
    if (codeLower.startsWith(normalized)) {
      score += 74;
    } else if (codeLower.includes(normalized)) {
      score += 22;
    }

    ranked.push({
      item,
      score,
    });
  });

  ranked.sort((a, b) => {
    if (b.score !== a.score) {
      return b.score - a.score;
    }

    return a.item.name.localeCompare(b.item.name, undefined, {
      sensitivity: "base",
      numeric: true,
    });
  });

  const result = ranked.slice(0, safeLimit).map((entry) => entry.item);

  if (suggestionResultCache.size >= 140) {
    const oldestKey = suggestionResultCache.keys().next().value;
    if (oldestKey) {
      suggestionResultCache.delete(oldestKey);
    }
  }
  suggestionResultCache.set(cacheKey, result);

  return result;
}

function fetchSearchSuggestions(query, limit = 6) {
  return $.ajax({
    url: resolveCatalogApiUrl("backend/api/products_api.php"),
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

function renderSuggestions(input, products, query = "") {
  const form = input.closest(".search-form");
  let list = form.find(".search-suggestions");
  if (list.length === 0) {
    list = $('<div class="search-suggestions"></div>');
    form.append(list);
  }

  const normalizedQuery = (query || "").toString().trim();
  if (products.length === 0) {
    if (normalizedQuery.length >= 2) {
      list.html(`
          <div class="suggestion-empty-state suggestion-empty-simple">
            <p class="mb-1">No products found</p>
            <small>No direct matches for "${escapeHtml(normalizedQuery)}". Press Enter for deeper search.</small>
          </div>
        `);
      list.addClass("show");
    } else {
      list.removeClass("show").empty();
    }
    return;
  }

  list.html(
    products
      .map(
        (p) => `
            <button type="button" class="suggestion-item" data-name="${encodeURIComponent(p.name || "")}">
                <span class="suggestion-name-wrap">
                  <span class="suggestion-name">${escapeHtml(p.name || "")}</span>
                  <span class="suggestion-meta">Quick suggestion</span>
                </span>
                <span class="suggestion-price">${Number(p.salePrice || p.price || p.sale_price || 0).toLocaleString()} PKR</span>
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
    fetchProductsData()
      .done((data) => {
        if (input.val().trim() !== querySnapshot) {
          return;
        }

        const localMatches = getLocalSuggestions(querySnapshot, data, 8);
        if (localMatches.length > 0) {
          renderSuggestions(input, localMatches, querySnapshot);
          return;
        }

        activeSuggestRequest = fetchSearchSuggestions(querySnapshot, 8)
          .done((remoteSuggestions) => {
            if (input.val().trim() !== querySnapshot) {
              return;
            }

            renderSuggestions(input, remoteSuggestions, querySnapshot);
          })
          .fail((xhr, status) => {
            if (status !== "abort") {
              renderSuggestions(input, [], querySnapshot);
            }
          })
          .always(() => {
            activeSuggestRequest = null;
          });
      })
      .fail(() => {
        renderSuggestions(input, [], querySnapshot);
      });
  }, 120);
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

$(document).on("mousemove", ".search-empty-stage", function (e) {
  if (
    window.matchMedia &&
    !window.matchMedia("(hover: hover) and (pointer: fine)").matches
  ) {
    return;
  }

  const rect = this.getBoundingClientRect();
  if (rect.width <= 0 || rect.height <= 0) {
    return;
  }

  const x = Math.max(0, Math.min(rect.width, e.clientX - rect.left));
  const y = Math.max(0, Math.min(rect.height, e.clientY - rect.top));

  this.style.setProperty("--search-empty-orb-x", `${x.toFixed(1)}px`);
  this.style.setProperty("--search-empty-orb-y", `${y.toFixed(1)}px`);
  this.classList.add("is-pointer-active");
});

$(document).on("mouseleave", ".search-empty-stage", function () {
  this.classList.remove("is-pointer-active");
  this.style.removeProperty("--search-empty-orb-x");
  this.style.removeProperty("--search-empty-orb-y");
});

$(document).on("click", function (e) {
  if (!$(e.target).closest(".search-form").length) {
    $(".search-suggestions").removeClass("show").empty();
  }
});
