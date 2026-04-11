function buildDefaultProductCode(productId) {
  const numericId = Math.max(0, parseInt(productId, 10) || 0);
  if (numericId > 0) {
    return `CMRZ-${String(numericId).padStart(5, "0")}`;
  }
  return "CMRZ-NEW";
}

function normalizeProductCodeInput(rawValue, fallbackId) {
  const normalized = (rawValue || "")
    .toString()
    .toUpperCase()
    .trim()
    .replace(/[^A-Z0-9-]+/g, "-")
    .replace(/-+/g, "-")
    .replace(/^-+|-+$/g, "");

  if (normalized) {
    return normalized.slice(0, 40);
  }

  return buildDefaultProductCode(fallbackId).slice(0, 40);
}

function normalizeProductMetaInput(rawValue, fallbackValue, maxLength = 120) {
  const cleaned = (rawValue || "").toString().trim();
  const value = cleaned || fallbackValue;
  return value.slice(0, maxLength);
}

function renderProductsTable() {
  const filterSection = window.currentSectionFilter || "";
  const tbody = $("#productsTable tbody");
  tbody.empty();

  let filteredProducts = productsData;
  if (filterSection) {
    filteredProducts = productsData.filter(
      (p) => p.sectionId === filterSection,
    );
  }

  $("#productCount").text(filteredProducts.length);

  if (filteredProducts.length === 0) {
    tbody.append(
      '<tr><td colspan="6" class="text-center py-4 text-secondary">No products found</td></tr>',
    );
    return;
  }

  filteredProducts.forEach((product) => {
    const numericPrice = Number(product.price) || 0;
    const numericSalePrice = Number(product.salePrice) || 0;
    const effectiveSale =
      numericSalePrice > 0 ? numericSalePrice : numericPrice;
    const productCode = escapeHtml(
      (product.productCode || buildDefaultProductCode(product.id)).toString(),
    );
    const warrantyInfo = escapeHtml(
      (product.warrantyInfo || "12-month seller warranty").toString(),
    );
    const dispatchInfo = escapeHtml(
      (
        product.dispatchInfo ||
        (Number(product.stock) > 0
          ? "Dispatch in 24-48 hours"
          : "Pre-order availability")
      ).toString(),
    );
    const safeName = escapeHtml(
      (product.name || "Untitled Product").toString(),
    );
    const safeSectionName = escapeHtml(
      (product.sectionName || "Section").toString(),
    );
    const safeCategory = escapeHtml(
      (product.category || "Uncategorized").toString(),
    );
    const safeImage = escapeHtml((product.image || "").toString());

    const stock =
      product.stock > 10
        ? `<span class="badge bg-success rounded-pill">In Stock (${product.stock})</span>`
        : `<span class="badge bg-warning text-dark rounded-pill">Low (${product.stock})</span>`;
    tbody.append(`
            <tr class="border-bottom border-secondary">
                <td class="ps-4 py-3"><img src="../../${safeImage}" alt="${safeName}" class="rounded" width="50" height="50" style="object-fit: cover; cursor: pointer;" onerror="this.src='assets/images/products/placeholder.webp'"></td>
                <td class="py-3 text-light fw-semibold" style="max-width: 260px;">
                  <div>${safeName}</div>
                  <div class="text-secondary small mt-1">Code: <span class="text-orange">${productCode}</span></div>
                  <div class="text-secondary small">Warranty: ${warrantyInfo}</div>
                  <div class="text-secondary small">Dispatch: ${dispatchInfo}</div>
                </td>
                <td class="py-3 text-secondary small"><span class="d-block text-warning">${safeSectionName}</span><span class="text-secondary">${safeCategory}</span></td>
                <td class="py-3 text-light fw-semibold"><span class="text-secondary" style="text-decoration: line-through; font-size: 0.9rem;">${formatPkr(numericPrice)}</span><span class="ms-2 text-orange">${formatPkr(effectiveSale)}</span></td>
                <td class="py-3">${stock}</td>
                <td class="pe-4 py-3"><button class="btn btn-sm btn-outline-orange me-1" onclick="editProduct(${product.id})" title="Edit"><i class="bi bi-pencil"></i></button><button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(${product.id})" title="Delete"><i class="bi bi-trash"></i></button></td>
            </tr>
        `);
  });
}

function editProduct(id) {
  const product = productsData.find((p) => p.id === id);
  if (product) {
    $("#productId").val(product.id);
    $("#productName").val(product.name);
    $("#productPrice").val(product.price);
    $("#productSalePrice").val(product.salePrice);
    $("#productStock").val(product.stock);

    const sectionName =
      allSections.find((s) => s.sectionId === product.sectionId)?.sectionName ||
      "Select Section";
    $("#productSectionBtn").text(sectionName);
    $("#productSection").val(product.sectionId);

    const movementType = product.movement || "quartz";
    const movementDisplay =
      movementType === "quartz"
        ? "Quartz"
        : movementType === "auto"
          ? "Automatic"
          : "Smart";
    $("#productMovementBtn").text(movementDisplay);
    $("#productMovement").val(movementType);

    $("#productImage").val(product.image);
    $("#productVideo").val(product.video || "");
    $("#productCode").val(
      product.productCode || buildDefaultProductCode(product.id),
    );
    $("#productWarrantyInfo").val(
      product.warrantyInfo || "12-month seller warranty",
    );
    $("#productDispatchInfo").val(
      product.dispatchInfo ||
        (Number(product.stock) > 0
          ? "Dispatch in 24-48 hours"
          : "Pre-order availability"),
    );
    $("#productDescription").val(product.description);
    $("#productModalLabel").text("Edit Product");
    new bootstrap.Modal(document.getElementById("productModal")).show();
  }
}

async function deleteProduct(id) {
  const confirmed = await showCustomConfirmDialog(
    "Move this product to trash? It will auto-delete after 7 days.",
    "Move To Trash",
  );
  if (!confirmed) {
    return;
  }

  const index = productsData.findIndex((p) => p.id === id);
  if (index > -1) {
    const name = productsData[index].name;
    productsData.splice(index, 1);
    saveProductsToJSON();
    renderProductsTable();
    calculateDashboardMetrics();
    updateNotifications();
    showNotification(
      `"${name}" moved to trash (7-day restore window).`,
      "success",
    );
  }
}

function filterBySection(sectionId, sectionName) {
  $("#sectionFilterBtn").text(sectionName);
  window.currentSectionFilter = sectionId;
  renderProductsTable();
}

function selectProductSection(sectionId, sectionName) {
  $("#productSectionBtn").text(sectionName);
  $("#productSection").val(sectionId);
}

function renderSectionDropdowns() {
  const filterMenu = $("#sectionFilterMenu");
  const productMenu = $("#productSectionMenu");

  if (filterMenu.length) {
    filterMenu.empty();
    filterMenu.append(
      '<li><a class="dropdown-item text-light" href="#" onclick="filterBySection(\'\', \'All Sections\'); return false;">All Sections</a></li>',
    );
    filterMenu.append(
      '<li><hr class="dropdown-divider border-secondary"></li>',
    );
    allSections.forEach((section) => {
      filterMenu.append(
        `<li><a class="dropdown-item text-light" href="#" onclick="filterBySection('${section.sectionId}', '${section.sectionName}'); return false;">${section.sectionName}</a></li>`,
      );
    });
  }

  if (productMenu.length) {
    productMenu.empty();
    productMenu.append(
      '<li><a class="dropdown-item text-light" href="#" onclick="selectProductSection(\'\', \'Select Section\'); return false;">Select Section</a></li>',
    );
    productMenu.append(
      '<li><hr class="dropdown-divider border-secondary"></li>',
    );
    allSections.forEach((section) => {
      productMenu.append(
        `<li><a class="dropdown-item text-light" href="#" onclick="selectProductSection('${section.sectionId}', '${section.sectionName}'); return false;">${section.sectionName}</a></li>`,
      );
    });
  }
}

function renderSectionsTable() {
  const tbody = $("#sectionsTable tbody");
  if (!tbody.length) return;

  tbody.empty();

  if (!allSections.length) {
    tbody.append(
      '<tr><td colspan="4" class="text-center py-4 text-secondary">No sections created</td></tr>',
    );
    return;
  }

  allSections.forEach((section) => {
    tbody.append(`
            <tr class="border-bottom border-secondary">
                <td class="ps-4 py-3 text-light fw-semibold">${section.sectionName}<div class="text-secondary small">${section.sectionId}</div></td>
                <td class="py-3 text-secondary small">${section.page || "N/A"}</td>
                <td class="py-3 text-secondary small">${section.category || "Uncategorized"}<div class="text-secondary">${section.subcategory || ""}</div></td>
                <td class="pe-4 py-3">
                    <button class="btn btn-sm btn-outline-orange me-1" onclick="editSection('${section.sectionId}')"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteSection('${section.sectionId}')"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
        `);
  });
}

function slugifySection(name) {
  return (name || "")
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/(^-|-$)/g, "");
}

function ensureUniqueSectionId(baseId) {
  let id = baseId || `section-${nextSectionId}`;
  let counter = 2;
  while (allSections.some((section) => section.sectionId === id)) {
    id = `${baseId}-${counter++}`;
  }
  return id;
}

function editSection(sectionId) {
  const section = allSections.find((item) => item.sectionId === sectionId);
  if (!section) return;
  $("#sectionFormId").val(section.sectionId);
  $("#sectionName").val(section.sectionName || "");
  $("#sectionId").val(section.sectionId || "");
  $("#sectionPage").val(section.page || "");
  $("#sectionCategory").val(section.category || "");
  $("#sectionSubcategory").val(section.subcategory || "");
  $("#saveSectionBtn").html('<i class="bi bi-save2 me-1"></i>Update Section');
}

async function deleteSection(sectionId) {
  const confirmed = await showCustomConfirmDialog(
    "Move this section and all its products to trash?",
    "Move Section To Trash",
  );
  if (!confirmed) return;

  allSections = allSections.filter(
    (section) => section.sectionId !== sectionId,
  );
  productsData = productsData.filter(
    (product) => product.sectionId !== sectionId,
  );
  if (window.currentSectionFilter === sectionId) {
    window.currentSectionFilter = "";
    $("#sectionFilterBtn").text("All Sections");
  }
  saveProductsToJSON();
  renderSectionDropdowns();
  renderSectionsTable();
  renderProductsTable();
  calculateDashboardMetrics();
  updateNotifications();
  resetSectionForm();
  showNotification("Section deleted!", "success");
}

function resetSectionForm() {
  $("#sectionFormId").val("");
  $("#sectionName").val("");
  $("#sectionId").val("");
  $("#sectionPage").val("");
  $("#sectionCategory").val("");
  $("#sectionSubcategory").val("");
  $("#saveSectionBtn").html(
    '<i class="bi bi-plus-circle me-1"></i>Add Section',
  );
}

function selectProductMovement(movementId, movementName) {
  $("#productMovementBtn").text(movementName);
  $("#productMovement").val(movementId);
}

