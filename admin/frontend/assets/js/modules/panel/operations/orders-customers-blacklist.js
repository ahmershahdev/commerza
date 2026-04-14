function normalizeCustomerSearchText(value) {
  return (value || "").toString().trim().toLowerCase();
}

function customerMatchesSearch(customer, query) {
  const searchValue = normalizeCustomerSearchText(query);
  if (!searchValue) {
    return true;
  }

  const haystack = [
    customer?.name,
    customer?.username,
    customer?.email,
    customer?.phone,
  ]
    .map((entry) => normalizeCustomerSearchText(entry))
    .filter(Boolean);

  return haystack.some((entry) => entry.includes(searchValue));
}

function updateCustomerSearchSuggestions(customers, query) {
  const datalist = document.getElementById("customersSearchSuggestions");
  if (!datalist) {
    return;
  }

  datalist.innerHTML = "";
  const searchValue = normalizeCustomerSearchText(query);
  if (!searchValue) {
    return;
  }

  const suggestionSet = new Set();

  customers.forEach((customer) => {
    const options = [
      (customer?.name || "").toString().trim(),
      (customer?.username || "").toString().trim(),
      (customer?.email || "").toString().trim(),
    ].filter(Boolean);

    options.forEach((value) => {
      const normalized = normalizeCustomerSearchText(value);
      if (!normalized.includes(searchValue)) {
        return;
      }

      if (suggestionSet.size >= 14 || suggestionSet.has(value)) {
        return;
      }

      suggestionSet.add(value);
      const option = document.createElement("option");
      option.value = value;
      datalist.appendChild(option);
    });
  });
}

function displayAllCustomers() {
  const tbody = document.querySelector("#customersTable tbody");
  if (!tbody) return;

  tbody.innerHTML = "";

  const customers = getAdminCustomersData();
  const searchInput = document.getElementById("customersSearchInput");
  const searchQuery = searchInput ? searchInput.value : "";
  const filteredCustomers = customers.filter((customer) =>
    customerMatchesSearch(customer, searchQuery),
  );

  updateCustomerSearchSuggestions(customers, searchQuery);

  if (filteredCustomers.length === 0) {
    const noMatch = normalizeCustomerSearchText(searchQuery) !== "";
    tbody.innerHTML = noMatch
      ? '<tr><td colspan="9" class="text-center py-4 text-secondary">No customers match this search.</td></tr>'
      : '<tr><td colspan="9" class="text-center py-4 text-secondary">No customers found</td></tr>';
    $("#customersSelectAll").prop("checked", false);
    return;
  }

  filteredCustomers.forEach((customer) => {
    const customerId = Number(customer.id || customer.userId || 0);
    const canDelete = customerId > 0;
    const isBlacklisted = !!customer?.isBlacklisted;
    const blacklistReason = (customer?.blacklistReason || "").toString().trim();
    const customerName = escapeHtml(customer.name || "Customer");
    const customerUsernameRaw = (customer.username || "").toString().trim();
    const customerUsername = customerUsernameRaw
      ? `@${escapeHtml(customerUsernameRaw)}`
      : '<span class="text-secondary small">Private / Not set</span>';
    const customerEmailRaw = (customer.email || "").toString().trim();
    const customerPhoneRaw = (customer.phone || "").toString().trim();
    const customerEmail = escapeHtml(customerEmailRaw || "N/A");
    const customerPhone = escapeHtml(customerPhoneRaw || "N/A");
    const ordersCount = Math.max(0, parseInt(customer.orderCount, 10) || 0);
    const totalSpent = Number(customer.totalSpent || 0);
    const blacklistButton = isBlacklisted
      ? `<button class="btn btn-sm btn-outline-success unblacklist-customer-btn" data-customer-email="${escapeHtml(customerEmailRaw)}" data-customer-phone="${escapeHtml(customerPhoneRaw)}"><i class="bi bi-shield-check me-1"></i>Unblacklist</button>`
      : `<button class="btn btn-sm btn-outline-warning blacklist-customer-btn" data-customer-id="${customerId}" data-customer-name="${customerName}" data-customer-email="${escapeHtml(customerEmailRaw)}" data-customer-phone="${escapeHtml(customerPhoneRaw)}"><i class="bi bi-slash-circle me-1"></i>Blacklist</button>`;
    const blacklistDeleteButton = canDelete
      ? `<button class="btn btn-sm btn-outline-danger blacklist-delete-customer-btn" data-customer-id="${customerId}" data-customer-name="${customerName}" data-customer-email="${escapeHtml(customerEmailRaw)}" data-customer-phone="${escapeHtml(customerPhoneRaw)}"><i class="bi bi-person-fill-x me-1"></i>Blacklist + Delete</button>`
      : "";
    const deleteButton = canDelete
      ? `<button class="btn btn-sm btn-outline-danger delete-customer-btn" data-customer-id="${customerId}" data-customer-name="${customerName}"><i class="bi bi-person-x me-1"></i>Delete Profile</button>`
      : '<span class="text-secondary small">Guest checkout</span>';
    const blacklistMeta = isBlacklisted
      ? `<div class="small text-warning mt-1">Blacklisted${blacklistReason ? `: ${escapeHtml(blacklistReason)}` : ""}</div>`
      : "";

    const row = document.createElement("tr");
    row.className = "border-bottom border-secondary";
    row.innerHTML = `
            <td class="ps-4 py-3">
              <input type="checkbox" class="form-check-input customer-select-row" value="${customerId}" ${canDelete ? "" : "disabled"}>
            </td>
            <td class="ps-4 py-3">
              <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(customer.name || "Customer")}&background=1d4ed8&color=fff" alt="Customer" class="rounded-circle" width="40" height="40">
            </td>
            <td class="py-3 text-light fw-semibold">${customerName}</td>
            <td class="py-3 text-secondary">${customerUsername}</td>
            <td class="py-3 text-secondary">${customerEmail}${blacklistMeta}</td>
            <td class="py-3 text-secondary">${customerPhone}</td>
            <td class="py-3 text-light">${ordersCount}</td>
            <td class="py-3 text-light fw-semibold">${formatPkr(totalSpent)}</td>
            <td class="pe-4 py-3 d-flex flex-wrap gap-1">${blacklistButton}${blacklistDeleteButton}${deleteButton}</td>
        `;
    tbody.appendChild(row);
  });

  $("#customersSelectAll").prop("checked", false);
}

function renderShippingConfigCard() {
  const flatFee = Math.max(0, Number(adminShippingConfig?.flatFee) || 0);
  const freeOver = Math.max(
    0,
    Number(adminShippingConfig?.freeShippingOver) || 0,
  );

  if ($("#shippingFlatFeeInput").length) {
    $("#shippingFlatFeeInput").val(flatFee.toFixed(2));
  }

  if ($("#freeShippingOverInput").length) {
    $("#freeShippingOverInput").val(freeOver.toFixed(2));
  }

  const preview = $("#shippingRulesPreview");
  if (!preview.length) {
    return;
  }

  if (flatFee <= 0) {
    preview.text("Shipping is currently free for all orders.");
    return;
  }

  if (freeOver > 0) {
    preview.text(
      `Orders below ${formatPkr(freeOver)} pay ${formatPkr(flatFee)} shipping. Orders at or above threshold get free shipping.`,
    );
    return;
  }

  preview.text(`All orders pay flat shipping: ${formatPkr(flatFee)}.`);
}

function syncBlacklistNoticeToggleUi() {
  const toggle = $("#blacklistUserNoticeToggle");
  if (!toggle.length) {
    return;
  }

  const canManageBlacklist = admin_has_any_permission([
    "customers.manage",
    "orders.manage",
  ]);
  const saveButton = $("#saveBlacklistNoticeToggleBtn");

  toggle.prop("checked", !!adminBlacklistNoticeVisible);
  toggle.prop("disabled", !canManageBlacklist);
  saveButton.prop("disabled", !canManageBlacklist);
}

async function saveBlacklistNoticeVisibility() {
  const toggle = $("#blacklistUserNoticeToggle");
  if (!toggle.length) {
    return;
  }

  const visibleToUser = toggle.is(":checked") ? 1 : 0;

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "save-blacklist-notice-visibility",
      visible_to_user: visibleToUser,
    });

    applyOrdersSummaryPayload(result?.payload || {});
    showNotification(
      result?.message || "Blacklist notice visibility updated.",
      "success",
    );
  } catch (error) {
    syncBlacklistNoticeToggleUi();
    showNotification(
      error?.message || "Unable to update blacklist notice visibility.",
      "danger",
    );
  }
}

function renderBlacklistTable() {
  const tbody = $("#blacklistTable tbody");
  if (!tbody.length) {
    return;
  }

  const entries = Array.isArray(adminBlacklist) ? adminBlacklist : [];
  tbody.empty();

  if (!entries.length) {
    tbody.append(
      '<tr><td colspan="5" class="text-center py-4 text-secondary">No blacklisted contacts yet.</td></tr>',
    );
    return;
  }

  const riskBadgeForReason = (reasonValue) => {
    const reasonText = (reasonValue || "").toString().trim().toLowerCase();
    if (/fraud|chargeback|stolen|scam|abuse/i.test(reasonText)) {
      return { label: "Critical", badgeClass: "bg-danger" };
    }

    if (/spam|bot|fake|duplicate|suspicious/i.test(reasonText)) {
      return { label: "High", badgeClass: "bg-warning text-dark" };
    }

    return { label: "Watchlist", badgeClass: "bg-secondary" };
  };

  entries.forEach((entry) => {
    const id = Number(entry?.id || 0);
    const email = escapeHtml((entry?.email || "-").toString().trim() || "-");
    const phone = escapeHtml((entry?.phone || "-").toString().trim() || "-");
    const reasonRaw = (entry?.reason || "").toString().trim();
    const reason = escapeHtml(reasonRaw || "-");
    const risk = riskBadgeForReason(reasonRaw);
    const riskLabel = escapeHtml(risk.label);
    const riskBadgeClass = escapeHtml(risk.badgeClass);
    const createdAt = escapeHtml(formatDateTime(entry?.createdAt || ""));

    tbody.append(`
      <tr class="border-bottom border-secondary">
        <td class="ps-4 py-3 text-light">${email}</td>
        <td class="py-3 text-secondary">${phone}</td>
        <td class="py-3 text-secondary small">
          <span class="badge ${riskBadgeClass} text-uppercase">${riskLabel}</span>
          <div class="small mt-1">${reason}</div>
        </td>
        <td class="py-3 text-secondary small">${createdAt}</td>
        <td class="pe-4 py-3">
          <button class="btn btn-sm btn-outline-success remove-blacklist-btn" data-blacklist-id="${id}">
            <i class="bi bi-shield-check me-1"></i>Unblacklist
          </button>
        </td>
      </tr>
    `);
  });
}

function applyOrdersSummaryPayload(payload) {
  setAdminOrdersPayload(payload || {});
  displayRecentOrders();
  displayAllOrders();
  displayAllCustomers();
  calculateDashboardMetrics();
  renderAnalyticsSection();
  renderRefundRequests();
  renderBlacklistTable();
  renderShippingConfigCard();
  updateNotifications();
  $("#customersSelectAll").prop("checked", false);
}

async function saveShippingConfig() {
  const flatFee = Math.max(
    0,
    Number($("#shippingFlatFeeInput").val() || 0) || 0,
  );
  const freeShippingOver = Math.max(
    0,
    Number($("#freeShippingOverInput").val() || 0) || 0,
  );

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "save-shipping-settings",
      flat_fee: Number(flatFee.toFixed(2)),
      free_shipping_over: Number(freeShippingOver.toFixed(2)),
    });

    applyOrdersSummaryPayload(result?.payload || {});
    showNotification(result?.message || "Shipping rules updated.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to update shipping rules.",
      "danger",
    );
  }
}

function findBlacklistEntryByContact(email, phone) {
  const normalizedEmail = (email || "").toString().trim().toLowerCase();
  const normalizedPhone = (phone || "").toString().trim();

  const entries = Array.isArray(adminBlacklist) ? adminBlacklist : [];
  return (
    entries.find((entry) => {
      const entryEmail = (entry?.email || "").toString().trim().toLowerCase();
      const entryPhone = (entry?.phone || "").toString().trim();

      if (normalizedEmail !== "" && entryEmail === normalizedEmail) {
        return true;
      }

      if (normalizedPhone !== "" && entryPhone === normalizedPhone) {
        return true;
      }

      return false;
    }) || null
  );
}

async function createBlacklistEntry(payload, successMessage) {
  const result = await adminPostJson(ADMIN_ORDERS_API, {
    action: "add-blacklist",
    ...payload,
  });

  applyOrdersSummaryPayload(result?.payload || {});
  showNotification(result?.message || successMessage, "success");
}

async function addBlacklistFromForm() {
  const email = (($("#blacklistEmailInput").val() || "") + "").trim();
  const phone = (($("#blacklistPhoneInput").val() || "") + "").trim();
  const reason = (($("#blacklistReasonInput").val() || "") + "").trim();

  if (email === "" && phone === "") {
    showNotification("Enter an email or phone number.", "warning");
    return;
  }

  try {
    await createBlacklistEntry(
      {
        email,
        phone,
        reason,
      },
      "Contact blacklisted.",
    );

    $("#blacklistEmailInput").val("");
    $("#blacklistPhoneInput").val("");
    $("#blacklistReasonInput").val("");
  } catch (error) {
    showNotification(
      error?.message || "Unable to blacklist contact.",
      "danger",
    );
  }
}

async function whitelistContactFromForm() {
  const email = (($("#whitelistEmailInput").val() || "") + "").trim();
  const phone = (($("#whitelistPhoneInput").val() || "") + "").trim();

  if (email === "" && phone === "") {
    showNotification("Enter an email or phone number to whitelist.", "warning");
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "remove-blacklist-contact",
      email,
      phone,
    });

    applyOrdersSummaryPayload(result?.payload || {});
    showNotification(result?.message || "Contact whitelisted.", "success");
    $("#whitelistEmailInput").val("");
    $("#whitelistPhoneInput").val("");
  } catch (error) {
    const localMatch = findBlacklistEntryByContact(email, phone);
    if (localMatch && Number(localMatch.id || 0) > 0) {
      await removeBlacklistById(localMatch.id);
      $("#whitelistEmailInput").val("");
      $("#whitelistPhoneInput").val("");
      return;
    }

    showNotification(
      error?.message || "Unable to whitelist contact.",
      "danger",
    );
  }
}

async function blacklistCustomerByIdentity(
  customerId,
  customerName,
  email,
  phone,
  deleteAfter = false,
) {
  const safeCustomerId = Number(customerId || 0) || 0;
  const safeName = (customerName || "Customer").toString().trim() || "Customer";
  const safeEmail = (email || "").toString().trim();
  const safePhone = (phone || "").toString().trim();

  if (safeCustomerId <= 0 && safeEmail === "" && safePhone === "") {
    showNotification("Customer contact data is missing.", "warning");
    return;
  }

  const confirmText = deleteAfter
    ? `Blacklist and delete ${safeName}?`
    : `Blacklist ${safeName}?`;
  const confirmed = await showCustomConfirmDialog(
    confirmText,
    deleteAfter ? "Blacklist + Delete" : "Blacklist Customer",
  );
  if (!confirmed) {
    return;
  }

  const reason = await showCustomPromptDialog(
    "Optional reason for blacklist entry:",
    "",
    "Blacklist Reason",
  );

  try {
    await createBlacklistEntry(
      {
        customer_id: safeCustomerId,
        email: safeEmail,
        phone: safePhone,
        reason: (reason || "").toString().trim(),
      },
      "Customer blacklisted.",
    );

    if (!deleteAfter || safeCustomerId <= 0) {
      return;
    }

    const deleteResult = await adminPostJson(ADMIN_ORDERS_API, {
      action: "delete-customers",
      customer_ids: [safeCustomerId],
    });

    applyOrdersSummaryPayload(deleteResult?.payload || {});
    showNotification(
      deleteResult?.message || "Customer blacklisted and deleted.",
      "success",
    );
  } catch (error) {
    showNotification(
      error?.message || "Unable to process blacklist action.",
      "danger",
    );
  }
}

async function removeBlacklistById(blacklistId) {
  const id = Number(blacklistId || 0) || 0;
  if (id <= 0) {
    showNotification("Invalid blacklist id.", "warning");
    return;
  }

  const confirmed = await showCustomConfirmDialog(
    "Remove this blacklist entry?",
    "Unblacklist Contact",
  );
  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "remove-blacklist",
      blacklist_id: id,
    });

    applyOrdersSummaryPayload(result?.payload || {});
    showNotification(result?.message || "Blacklist entry removed.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to remove blacklist entry.",
      "danger",
    );
  }
}

async function removeBlacklistByContact(email, phone) {
  const entry = findBlacklistEntryByContact(email, phone);
  if (!entry) {
    showNotification(
      "No active blacklist entry found for this customer.",
      "warning",
    );
    return;
  }

  await removeBlacklistById(entry.id);
}

function deleteOrder(orderId) {
  showCustomConfirmDialog(
    "Are you sure you want to cancel this order?",
    "Cancel Order",
  ).then((confirmed) => {
    if (confirmed) {
      updateOrderStatus(orderId, "Cancelled");
    }
  });
}

function decodeSelectedOrderNumber(value) {
  const raw = (value || "").toString().trim();
  if (!raw) return "";

  try {
    return decodeURIComponent(raw);
  } catch (error) {
    return raw;
  }
}

function selectedOrderNumbers() {
  return $(".order-select-row:checked")
    .map(function () {
      return decodeSelectedOrderNumber($(this).val());
    })
    .get()
    .filter(Boolean);
}

function selectedCustomerIds() {
  return $(".customer-select-row:checked")
    .map(function () {
      return parseInt($(this).val(), 10) || 0;
    })
    .get()
    .filter((id) => id > 0);
}

async function deleteSingleCustomer(customerId, customerName = "Customer") {
  const safeCustomerId = parseInt(customerId, 10) || 0;
  if (safeCustomerId <= 0) {
    showNotification("Invalid customer id.", "warning");
    return;
  }

  const confirmed = await showCustomConfirmDialog(
    `Delete profile for ${customerName}? This removes the account and linked records.`,
    "Delete Customer Profile",
  );
  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "delete-customers",
      customer_ids: [safeCustomerId],
    });

    setAdminOrdersPayload(result?.payload || {});
    displayRecentOrders();
    displayAllOrders();
    displayAllCustomers();
    calculateDashboardMetrics();
    renderAnalyticsSection();
    renderRefundRequests();
    updateNotifications();
    $("#customersSelectAll").prop("checked", false);

    showNotification(result?.message || "Customer profile deleted.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to delete customer profile.",
      "danger",
    );
  }
}

async function bulkDeleteOrders() {
  const orderNumbers = selectedOrderNumbers();
  if (!orderNumbers.length) {
    showNotification("Select at least one order to delete.", "warning");
    return;
  }

  const confirmed = await showCustomConfirmDialog(
    `Delete ${orderNumbers.length} selected order(s)?`,
    "Delete Selected Orders",
  );
  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "delete-orders",
      order_numbers: orderNumbers,
    });

    setAdminOrdersPayload(result?.payload || {});
    displayRecentOrders();
    displayAllOrders();
    displayAllCustomers();
    calculateDashboardMetrics();
    renderAnalyticsSection();
    renderRefundRequests();
    updateNotifications();
    $("#ordersSelectAll").prop("checked", false);

    showNotification(result?.message || "Selected orders deleted.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to delete selected orders.",
      "danger",
    );
  }
}

async function bulkDeleteCustomers() {
  const customerIds = selectedCustomerIds();
  if (!customerIds.length) {
    showNotification("Select at least one customer to delete.", "warning");
    return;
  }

  const confirmed = await showCustomConfirmDialog(
    `Delete ${customerIds.length} selected customer(s)?`,
    "Delete Selected Customers",
  );
  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "delete-customers",
      customer_ids: customerIds,
    });

    setAdminOrdersPayload(result?.payload || {});
    displayRecentOrders();
    displayAllOrders();
    displayAllCustomers();
    calculateDashboardMetrics();
    renderAnalyticsSection();
    renderRefundRequests();
    updateNotifications();
    $("#customersSelectAll").prop("checked", false);

    showNotification(
      result?.message || "Selected customers deleted.",
      "success",
    );
  } catch (error) {
    showNotification(
      error?.message || "Unable to delete selected customers.",
      "danger",
    );
  }
}

async function updateOrderStatus(orderId, newStatus) {
  const normalizedOrderId = (orderId || "").toString();
  if (!normalizedOrderId) {
    showNotification("Invalid order id.", "danger");
    return;
  }

  if (ORDER_STATUS_LOCKS.has(normalizedOrderId)) {
    showNotification(
      "A status update is already in progress for this order.",
      "warning",
    );
    return;
  }

  const orders = getAdminOrdersData();
  const targetOrder = orders.find(
    (order) => (order.orderId || "").toString() === normalizedOrderId,
  );

  if (!targetOrder) {
    showNotification("Order not found.", "danger");
    return;
  }

  const oldStatus = targetOrder.status;
  if (oldStatus === newStatus) {
    showNotification("Order is already in the selected status.", "warning");
    return;
  }

  ORDER_STATUS_LOCKS.add(normalizedOrderId);
  displayAllOrders();

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "update-status",
      order_number: normalizedOrderId,
      status: newStatus,
      refresh_mode: "minimal",
    });

    const payload = result?.payload || {};
    if (Array.isArray(payload?.orders)) {
      setAdminOrdersPayload(payload);
    } else {
      applyOrderStatusPatch(payload);
    }

    displayRecentOrders();
    calculateDashboardMetrics();
    renderAnalyticsSection();
    updateNotifications();

    showStatusNotification(normalizedOrderId, oldStatus, newStatus);
  } catch (error) {
    showNotification(
      error?.message || "Unable to update order status.",
      "danger",
    );
  } finally {
    ORDER_STATUS_LOCKS.delete(normalizedOrderId);
    displayAllOrders();
  }
}

function logisticsEtaInputValue(rawValue) {
  const raw = (rawValue || "").toString().trim();
  if (!raw) {
    return "";
  }

  const normalized = raw.replace(" ", "T");
  const directMatch = normalized.match(/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})/);
  if (directMatch && directMatch[1]) {
    return directMatch[1];
  }

  const parsed = new Date(raw);
  if (Number.isNaN(parsed.getTime())) {
    return "";
  }

  const local = new Date(parsed.getTime() - parsed.getTimezoneOffset() * 60000);
  return local.toISOString().slice(0, 16);
}

function openOrderLogisticsModal(targetOrder) {
  const modalEl = document.getElementById("orderLogisticsModal");
  const orderInput = document.getElementById("orderLogisticsOrderId");
  const etaInput = document.getElementById("orderLogisticsEtaInput");
  const noteInput = document.getElementById("orderLogisticsNoteInput");
  const saveBtn = document.getElementById("orderLogisticsSaveBtn");
  const clearBtn = document.getElementById("orderLogisticsEtaClearBtn");

  if (
    !modalEl ||
    !orderInput ||
    !etaInput ||
    !noteInput ||
    !saveBtn ||
    !clearBtn ||
    typeof bootstrap === "undefined"
  ) {
    showNotification("Logistics editor is unavailable right now.", "danger");
    return Promise.resolve(null);
  }

  const currentOrderId = (targetOrder?.orderId || "").toString();
  orderInput.value = currentOrderId;
  etaInput.value = logisticsEtaInputValue(targetOrder?.deliveryEstimate || "");
  noteInput.value = (targetOrder?.adminNote || "").toString();

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl, {
    backdrop: "static",
    keyboard: false,
  });

  return new Promise((resolve) => {
    let settled = false;

    const cleanup = () => {
      saveBtn.removeEventListener("click", onSave);
      clearBtn.removeEventListener("click", onClear);
      modalEl.removeEventListener("hidden.bs.modal", onHidden);
    };

    const finish = (value) => {
      if (settled) {
        return;
      }

      settled = true;
      cleanup();
      resolve(value);
    };

    const onSave = () => {
      const deliveryEstimate = (etaInput.value || "").toString().trim();
      if (
        deliveryEstimate !== "" &&
        !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(deliveryEstimate)
      ) {
        showNotification(
          "Invalid ETA format. Use the date and time field.",
          "warning",
        );
        etaInput.focus();
        return;
      }

      finish({
        deliveryEstimate,
        adminNote: (noteInput.value || "").toString().trim(),
      });
      modal.hide();
    };

    const onClear = () => {
      etaInput.value = "";
      etaInput.focus();
    };

    const onHidden = () => {
      if (!settled) {
        finish(null);
      }
    };

    saveBtn.addEventListener("click", onSave);
    clearBtn.addEventListener("click", onClear);
    modalEl.addEventListener("hidden.bs.modal", onHidden);

    modal.show();
    setTimeout(() => {
      etaInput.focus();
    }, 80);
  });
}

async function updateOrderLogistics(orderId) {
  const orders = getAdminOrdersData();
  const targetOrder = orders.find((order) => order.orderId === orderId);

  if (!targetOrder) {
    showNotification("Order not found.", "danger");
    return;
  }

  const modalResult = await openOrderLogisticsModal(targetOrder);
  if (!modalResult) {
    return;
  }

  const deliveryEstimate = (modalResult.deliveryEstimate || "").toString();
  const adminNote = (modalResult.adminNote || "").toString().trim();

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "update-logistics",
      order_number: orderId,
      delivery_estimate: deliveryEstimate,
      admin_note: adminNote,
    });

    setAdminOrdersPayload(result?.payload || {});
    displayRecentOrders();
    displayAllOrders();
    displayAllCustomers();
    calculateDashboardMetrics();
    renderAnalyticsSection();
    renderRefundRequests();
    updateNotifications();

    if ($("#emailSection").length) {
      emailDirectory = buildEmailDirectory();
      renderEmailRecipients();
    }

    showNotification(result?.message || "Order logistics updated.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to update order logistics.",
      "danger",
    );
  }
}

function toggleOrderDetails(elementId) {
  const element = document.getElementById(elementId);
  if (element) {
    element.style.display =
      element.style.display === "none" ? "table-row" : "none";
  }
}
