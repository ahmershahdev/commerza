function loadProductsFromJSON() {
  return fetch(`${ADMIN_PRODUCTS_SYNC_API}?action=get-products`, {
    method: "GET",
    credentials: "same-origin",
  })
    .then(async (response) => {
      const responseText = await response.text();
      let result = null;

      try {
        result = responseText ? JSON.parse(responseText) : null;
      } catch (error) {
        throw new Error(
          "Invalid server response while loading products. Please re-login and refresh the admin page.",
        );
      }

      if (!response.ok) {
        throw new Error(
          result?.message || "Could not load products from server.",
        );
      }

      return result;
    })
    .then((result) => {
      if (!result?.ok || !result?.payload) {
        throw new Error(
          result?.message || "Could not load products from server.",
        );
      }
      hydrateProductsData(result.payload);
    })
    .catch((error) => {
      console.error("Error loading products from backend:", error);
      showNotification(
        error?.message || "Error loading products. Please refresh the page.",
        "danger",
      );
    });
}

function hydrateProductsData(data) {
  allSections = data.sections || [];
  productsData = [];
  allSections.forEach((section) => {
    (section.products || []).forEach((product) => {
      productsData.push({
        ...product,
        category: section.category,
        subcategory: section.subcategory,
        sectionName: section.sectionName,
        sectionId: section.sectionId,
        page: section.page,
      });
    });
  });
  nextId = productsData.length
    ? Math.max(...productsData.map((p) => p.id)) + 1
    : 1;
  nextSectionId = allSections.length ? allSections.length + 1 : 1;
  if (Array.isArray(data?.trash?.items)) {
    hydrateProductTrashItems(data.trash.items);
  }
  syncProductWorkspaceSummary();
  renderSectionDropdowns();
  renderSectionsTable();
  renderProductsTable();
  renderReviewProductSuggestions();
  calculateDashboardMetrics();
  updateNotifications();
}

function syncProductWorkspaceSummary() {
  $("#productWorkspaceProducts").text(
    Array.isArray(productsData) ? productsData.length : 0,
  );
  $("#productWorkspaceSections").text(
    Array.isArray(allSections) ? allSections.length : 0,
  );
  $("#productWorkspaceTrash").text(
    Array.isArray(productTrashItems) ? productTrashItems.length : 0,
  );
}

function syncProductTrashMeta(items) {
  const safeItems = Array.isArray(items) ? items : [];
  let expiringSoon = 0;
  let expired = 0;

  safeItems.forEach((item) => {
    const seconds = Math.max(0, parseInt(item?.expiresInSeconds, 10) || 0);
    if (seconds <= 0) {
      expired += 1;
      return;
    }

    if (seconds <= 86400) {
      expiringSoon += 1;
    }
  });

  $("#productTrashTotalBadge").text(`Total: ${safeItems.length}`);
  $("#productTrashExpiringBadge").text(`Expiring < 24h: ${expiringSoon}`);
  $("#productTrashExpiredBadge").text(`Expired: ${expired}`);
}

function hydrateProductTrashItems(items) {
  productTrashItems = Array.isArray(items) ? items : [];
  syncProductWorkspaceSummary();
  renderProductTrashTable();
}

function formatTrashCountdown(seconds) {
  let remaining = Math.max(0, parseInt(seconds, 10) || 0);
  const days = Math.floor(remaining / 86400);
  remaining -= days * 86400;
  const hours = Math.floor(remaining / 3600);
  remaining -= hours * 3600;
  const minutes = Math.floor(remaining / 60);

  if (days > 0) {
    return `${days}d ${hours}h`;
  }
  if (hours > 0) {
    return `${hours}h ${minutes}m`;
  }
  return `${minutes}m`;
}

function renderProductTrashTable() {
  const tbody = $("#productTrashTable tbody");
  if (!tbody.length) {
    return;
  }

  tbody.empty();
  const items = Array.isArray(productTrashItems) ? productTrashItems : [];
  $("#productTrashCount").text(items.length);
  syncProductTrashMeta(items);

  if (!items.length) {
    tbody.append(
      '<tr><td colspan="5" class="text-center py-4 text-secondary">Trash is empty.</td></tr>',
    );
    return;
  }

  items.forEach((item) => {
    const name = escapeHtml((item?.name || "Product").toString());
    const code = escapeHtml((item?.productCode || "").toString());
    const section = escapeHtml((item?.sectionName || "Section").toString());
    const image = escapeHtml((item?.image || "").toString());
    const deletedAt = escapeHtml(formatDateTime(item?.deletedAt || ""));
    const purgeAfter = escapeHtml(formatDateTime(item?.purgeAfter || ""));
    const expiresInSeconds = Math.max(
      0,
      parseInt(item?.expiresInSeconds, 10) || 0,
    );
    const countdown = formatTrashCountdown(expiresInSeconds);
    const trashId = Number(item?.id || 0);
    const isExpired = expiresInSeconds <= 0;
    const isExpiringSoon = !isExpired && expiresInSeconds <= 86400;
    const statusLabel = isExpired
      ? "Expired"
      : isExpiringSoon
        ? "Expiring Soon"
        : "Safe Window";
    const statusClass = isExpired
      ? "is-expired"
      : isExpiringSoon
        ? "is-warning"
        : "is-safe";

    const imageCell = image
      ? `<img src="../../${image}" alt="${name}" class="rounded" width="44" height="44" style="object-fit:cover;">`
      : '<span class="badge bg-secondary">No media</span>';

    tbody.append(`
      <tr class="border-bottom border-secondary">
        <td class="ps-4 py-3 text-light">
          <div class="d-flex align-items-center gap-2">
            ${imageCell}
            <div>
              <div class="fw-semibold">${name}</div>
              <div class="text-secondary small">Code: ${code || "-"}</div>
            </div>
          </div>
        </td>
        <td class="py-3 text-secondary small">${section}</td>
        <td class="py-3 text-secondary small">${deletedAt}</td>
        <td class="py-3 text-secondary small">
          <div class="trash-countdown-time text-warning fw-semibold">${countdown}</div>
          <div class="trash-status-pill ${statusClass}">${statusLabel}</div>
          <div>${purgeAfter}</div>
        </td>
        <td class="pe-4 py-3">
          <div class="d-flex gap-1 flex-wrap">
            <button class="btn btn-sm btn-outline-success product-trash-restore-btn" type="button" title="Restore this item to catalog" onclick="restoreTrashProductById(${trashId})">Restore</button>
            <button class="btn btn-sm btn-outline-danger product-trash-delete-btn" type="button" title="Permanently delete this trash item" onclick="deleteTrashItemById(${trashId})">Delete</button>
          </div>
        </td>
      </tr>
    `);
  });
}

async function loadProductTrashData(silent = false) {
  if (!$("#productTrashTable").length) {
    return false;
  }

  try {
    const response = await fetch(
      `${ADMIN_PRODUCTS_SYNC_API}?action=get-trash`,
      {
        method: "GET",
        credentials: "same-origin",
        cache: "no-store",
      },
    );

    const result = await response.json();
    if (!response.ok || !result?.ok) {
      throw new Error(result?.message || "Unable to load product trash.");
    }

    hydrateProductTrashItems(result?.payload?.items || []);
    return true;
  } catch (error) {
    if (!silent) {
      showNotification(
        error?.message || "Unable to load product trash.",
        "danger",
      );
    }
    return false;
  }
}

async function restoreTrashProductById(trashId) {
  const safeId = parseInt(trashId, 10) || 0;
  if (safeId <= 0) {
    showNotification("Invalid trash item.", "warning");
    return;
  }

  const confirmed = await showCustomConfirmDialog(
    "Restore this product from trash?",
    "Restore Product",
  );
  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_PRODUCTS_SYNC_API, {
      action: "restore-trash-product",
      id: safeId,
    });

    if (result?.payload?.products) {
      hydrateProductsData(result.payload.products);
    }
    hydrateProductTrashItems(result?.payload?.trash?.items || []);
    showNotification(
      result?.message || "Product restored from trash.",
      "success",
    );
  } catch (error) {
    showNotification(error?.message || "Unable to restore product.", "danger");
  }
}

async function deleteTrashItemById(trashId) {
  const safeId = parseInt(trashId, 10) || 0;
  if (safeId <= 0) {
    showNotification("Invalid trash item.", "warning");
    return;
  }

  const confirmed = await showCustomConfirmDialog(
    "Permanently delete this trash item? This cannot be undone.",
    "Permanent Delete",
  );
  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_PRODUCTS_SYNC_API, {
      action: "delete-trash-item",
      id: safeId,
    });

    hydrateProductTrashItems(result?.payload?.trash?.items || []);
    showNotification(
      result?.message || "Trash item permanently deleted.",
      "success",
    );
  } catch (error) {
    showNotification(
      error?.message || "Unable to delete trash item.",
      "danger",
    );
  }
}

async function emptyProductTrash(mode = "all") {
  const safeMode = mode === "expired" ? "expired" : "all";
  const confirmed = await showCustomConfirmDialog(
    safeMode === "expired"
      ? "Delete only expired trash items now?"
      : "Permanently delete every trashed product now?",
    safeMode === "expired" ? "Empty Expired Trash" : "Empty All Trash",
  );
  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_PRODUCTS_SYNC_API, {
      action: "empty-trash",
      mode: safeMode,
    });

    hydrateProductTrashItems(result?.payload?.trash?.items || []);
    showNotification(
      result?.message || "Trash cleaned successfully.",
      "success",
    );
  } catch (error) {
    showNotification(error?.message || "Unable to empty trash.", "danger");
  }
}

function buildProductsPayload() {
  return {
    meta: {
      total: productsData.length,
      currency: "PKR",
      lastUpdated: new Date().toISOString().split("T")[0],
    },
    sections: allSections.map((section) => ({
      ...section,
      products: productsData.filter((p) => p.sectionId === section.sectionId),
    })),
  };
}

function saveProductsToJSON() {
  const dataToSave = buildProductsPayload();

  return adminPostJson(ADMIN_PRODUCTS_SYNC_API, {
    action: "save-products",
    sections: dataToSave.sections,
  })
    .then((result) => {
      if (result?.payload) {
        hydrateProductsData(result.payload);
      }
      return result;
    })
    .catch((error) => {
      console.error("Error syncing products to backend:", error);
      showNotification(
        error?.message || "Could not sync products to backend.",
        "danger",
      );
      return null;
    });
}
