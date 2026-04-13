function showNotification(message, type) {
  const alertClass =
    type === "success"
      ? "alert-success"
      : type === "danger"
        ? "alert-danger"
        : "alert-warning";

  const safeMessage = escapeHtml((message || "").toString());

  $("body").append(`
        <div class="alert ${alertClass} alert-dismissible fade show admin-floating-alert" role="alert">
            <span class="admin-floating-alert-message">${safeMessage}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `);

  setTimeout(() => {
    $(".admin-floating-alert")
      .first()
      .fadeOut("slow", function () {
        $(this).remove();
      });
  }, 3000);
}

function showAdminDialog(options = {}) {
  const modalEl = document.getElementById("adminDialogModal");
  if (!modalEl || typeof bootstrap === "undefined") {
    if (options.type === "prompt") {
      return Promise.resolve(null);
    }
    return Promise.resolve(false);
  }

  const titleEl = document.getElementById("adminDialogTitle");
  const messageEl = document.getElementById("adminDialogMessage");
  const inputWrap = document.getElementById("adminDialogInputWrap");
  const inputEl = document.getElementById("adminDialogInput");
  const cancelBtn = document.getElementById("adminDialogCancelBtn");
  const okBtn = document.getElementById("adminDialogOkBtn");

  if (
    !titleEl ||
    !messageEl ||
    !inputWrap ||
    !inputEl ||
    !cancelBtn ||
    !okBtn
  ) {
    if (options.type === "prompt") {
      return Promise.resolve(null);
    }
    return Promise.resolve(false);
  }

  const dialogType = options.type || "confirm";
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl, {
    backdrop: "static",
    keyboard: false,
  });

  titleEl.textContent = (options.title || "Confirm Action").toString();
  messageEl.textContent = (options.message || "Are you sure?").toString();
  okBtn.textContent = (options.confirmText || "Continue").toString();
  cancelBtn.textContent = (options.cancelText || "Cancel").toString();

  if (dialogType === "prompt") {
    inputWrap.classList.remove("d-none");
    inputEl.value = (options.defaultValue || "").toString();
  } else {
    inputWrap.classList.add("d-none");
    inputEl.value = "";
  }

  if (dialogType === "alert") {
    cancelBtn.classList.add("d-none");
  } else {
    cancelBtn.classList.remove("d-none");
  }

  return new Promise((resolve) => {
    let settled = false;

    const cleanup = () => {
      okBtn.removeEventListener("click", onConfirm);
      cancelBtn.removeEventListener("click", onCancel);
      modalEl.removeEventListener("hidden.bs.modal", onHidden);
    };

    const finish = (value) => {
      if (settled) return;
      settled = true;
      cleanup();
      resolve(value);
    };

    const onConfirm = () => {
      const value = dialogType === "prompt" ? inputEl.value : true;
      finish(value);
      modal.hide();
    };

    const onCancel = () => {
      if (dialogType === "prompt") {
        finish(null);
      } else {
        finish(false);
      }
      modal.hide();
    };

    const onHidden = () => {
      if (settled) return;
      if (dialogType === "prompt") {
        finish(null);
      } else {
        finish(false);
      }
    };

    okBtn.addEventListener("click", onConfirm);
    cancelBtn.addEventListener("click", onCancel);
    modalEl.addEventListener("hidden.bs.modal", onHidden);

    modal.show();
    if (dialogType === "prompt") {
      setTimeout(() => inputEl.focus(), 100);
    }
  });
}

function showCustomConfirmDialog(message, title = "Confirm Action") {
  return showAdminDialog({
    type: "confirm",
    title,
    message,
    confirmText: "Yes, Continue",
    cancelText: "Cancel",
  });
}

function showCustomPromptDialog(
  message,
  defaultValue = "",
  title = "Add Note",
) {
  return showAdminDialog({
    type: "prompt",
    title,
    message,
    defaultValue,
    confirmText: "Save",
    cancelText: "Skip",
  });
}

function showStatusNotification(orderId, oldStatus, newStatus) {
  const safeOrderId = escapeHtml((orderId || "").toString());
  const safeOldStatus = escapeHtml((oldStatus || "").toString());
  const safeNewStatus = escapeHtml((newStatus || "").toString());

  const notif = $(
    `<div class="alert alert-info alert-dismissible fade show admin-status-alert" role="alert"><div class="admin-status-alert-body"><i class="bi bi-arrow-left-right admin-status-alert-icon"></i><h5 class="admin-status-alert-title">Order Status Updated!</h5><p class="admin-status-alert-order">Order: <strong>${safeOrderId}</strong></p><p class="admin-status-alert-transition"><span class="admin-status-pill admin-status-pill-old">${safeOldStatus}</span><i class="bi bi-arrow-right admin-status-arrow"></i><span class="admin-status-pill admin-status-pill-new">${safeNewStatus}</span></p></div><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`,
  );
  $("body").append(notif);
  setTimeout(() => notif.remove(), 4000);
}

function openNotificationTarget(tabId) {
  const id = (tabId || "").toString().trim();
  if (!id) return;

  const tab = document.getElementById(id);
  if (tab && typeof tab.click === "function") {
    tab.click();
  }
}

function notificationSignature(notification) {
  const text = (notification?.text || "").toString().trim().toLowerCase();
  const icon = (notification?.icon || "").toString().trim().toLowerCase();
  const type = (notification?.type || "").toString().trim().toLowerCase();
  const targetTab = (notification?.targetTab || "")
    .toString()
    .trim()
    .toLowerCase();
  return `${type}|${icon}|${targetTab}|${text}`;
}

let lastRenderedNotificationSignatures = [];

function clearAdminNotifications(minutes = 45) {
  void minutes;

  if (!Array.isArray(lastRenderedNotificationSignatures)) {
    lastRenderedNotificationSignatures = [];
  }

  lastRenderedNotificationSignatures.forEach((signature) => {
    const safeSignature = (signature || "").toString().trim();
    if (safeSignature !== "") {
      dismissedNotificationSignatures.add(safeSignature);
    }
  });

  saveDismissedNotificationSignatures();
  updateNotifications();
}

function resumeAdminNotifications() {
  notificationsPausedUntil = 0;
  dismissedNotificationSignatures = new Set();
  saveDismissedNotificationSignatures();
  updateNotifications();
}

window.openNotificationTarget = openNotificationTarget;
window.clearAdminNotifications = clearAdminNotifications;
window.resumeAdminNotifications = resumeAdminNotifications;

function updateNotifications() {
  const list = $("#notificationList");
  const count = $("#notificationCount");
  if (!list.length || !count.length) return;

  const notifications = [];
  const orders = getAdminOrdersData();

  const recentOrders = orders.filter((order) =>
    isWithinDays(order.orderDate, NOTIFICATION_RULES.recentOrderDays),
  );
  if (recentOrders.length > 0) {
    const latestOrder = [...recentOrders].sort(
      (a, b) => new Date(b.orderDate) - new Date(a.orderDate),
    )[0];
    if (latestOrder && latestOrder.orderId) {
      notifications.push({
        text: `New order ${latestOrder.orderId} (last ${NOTIFICATION_RULES.recentOrderDays} days)`,
        icon: "bi-receipt",
        type: "info",
        targetTab: "orders-tab",
      });
    }
  }

  const pendingOrders = orders.filter((order) => {
    const status = (order.status || "").toLowerCase();
    return (
      isWithinDays(order.orderDate, NOTIFICATION_RULES.pendingOrderDays) &&
      (status === "pending" || status === "processing")
    );
  });
  if (pendingOrders.length > 0) {
    notifications.push({
      text: `${pendingOrders.length} order(s) waiting for action (last ${NOTIFICATION_RULES.pendingOrderDays} days)`,
      icon: "bi-clock-history",
      type: "warning",
      targetTab: "orders-tab",
    });
  }

  const newCustomers = getAdminCustomersData().filter((customer) =>
    isWithinDays(customer.registeredAt, NOTIFICATION_RULES.newCustomerDays),
  );
  if (newCustomers.length > 0) {
    notifications.push({
      text: `New customers: ${newCustomers.length} (last ${NOTIFICATION_RULES.newCustomerDays} days)`,
      icon: "bi-person-check",
      type: "success",
      targetTab: "customers-tab",
    });
  }

  const newProducts = productsData.filter((product) =>
    isWithinDays(product.createdAt, NOTIFICATION_RULES.newProductDays),
  );
  if (newProducts.length > 0) {
    notifications.push({
      text: `New products added: ${newProducts.length} (last ${NOTIFICATION_RULES.newProductDays} days)`,
      icon: "bi-bag-plus",
      type: "info",
      targetTab: "products-tab",
    });
  }

  const lowStock = productsData.filter(
    (product) => Number(product.stock) <= NOTIFICATION_RULES.lowStockThreshold,
  );
  if (lowStock.length > 0) {
    notifications.push({
      text: `${lowStock.length} product(s) low stock (check today)`,
      icon: "bi-exclamation-triangle",
      type: "danger",
      targetTab: "products-tab",
    });
  }

  const pendingRefunds = (
    Array.isArray(adminRefunds) ? adminRefunds : []
  ).filter(
    (refund) => (refund?.status || "").toString().toLowerCase() === "pending",
  );
  if (pendingRefunds.length > 0) {
    notifications.push({
      text: `${pendingRefunds.length} refund request(s) waiting review`,
      icon: "bi-cash-coin",
      type: "info",
      targetTab: "orders-tab",
    });
  }

  const latestNotifications = notifications.slice(0, 3);
  const latestSignatures = latestNotifications.map((notification) =>
    notificationSignature(notification),
  );

  lastRenderedNotificationSignatures = latestSignatures;

  let dismissedPruned = false;
  Array.from(dismissedNotificationSignatures).forEach((signature) => {
    if (!latestSignatures.includes(signature)) {
      dismissedNotificationSignatures.delete(signature);
      dismissedPruned = true;
    }
  });

  if (dismissedPruned) {
    saveDismissedNotificationSignatures();
  }

  const visibleNotifications = latestNotifications.filter((notification) => {
    const signature = notificationSignature(notification);
    return !dismissedNotificationSignatures.has(signature);
  });

  list.empty();
  list.append(`
    <li class="dropdown-header d-flex align-items-center justify-content-between gap-2">
      <h6 class="text-secondary mb-0">Latest 3 Notifications</h6>
      <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="clearAdminNotifications(); return false;">Clear</button>
    </li>
  `);
  list.append('<li><hr class="dropdown-divider border-secondary"></li>');

  if (latestNotifications.length === 0) {
    count.text("0");
    count.addClass("d-none");
    list.append(
      '<li><span class="dropdown-item text-secondary">No new notifications</span></li>',
    );
    return;
  }

  if (visibleNotifications.length === 0) {
    count.text("0");
    count.addClass("d-none");
    list.append(
      '<li><span class="dropdown-item text-secondary">Notifications cleared. New activity will appear automatically.</span></li>',
    );
    list.append(
      '<li><a class="dropdown-item text-light" href="#" onclick="resumeAdminNotifications(); return false;"><i class="bi bi-arrow-repeat me-2"></i>Reset cleared state</a></li>',
    );
    return;
  }

  count.text(String(visibleNotifications.length));
  count.removeClass("d-none");

  visibleNotifications.forEach((notif, index) => {
    const targetTab = (notif?.targetTab || "").toString();
    const clickHandler = targetTab
      ? `openNotificationTarget('${targetTab}'); return false;`
      : "return false;";
    const safeText = escapeHtml(notif?.text || "");

    list.append(`
            <li><a class="dropdown-item text-light d-flex align-items-center gap-2" href="#" onclick="${clickHandler}">
                <i class="bi ${notif.icon} text-${notif.type}"></i>
                <span>${safeText}</span>
            </a></li>
        `);
    if (index < visibleNotifications.length - 1) {
      list.append('<li><hr class="dropdown-divider border-secondary"></li>');
    }
  });
}

