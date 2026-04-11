function renderAnalyticsProfitLossChart(weekly) {
  const canvas = document.getElementById("analyticsProfitLossChart");
  if (!canvas) {
    return;
  }

  if (typeof window.Chart === "undefined") {
    if (analyticsProfitLossChart) {
      analyticsProfitLossChart.destroy();
      analyticsProfitLossChart = null;
    }
    return;
  }

  const fallbackWeekly = Array.from({ length: 7 }, (_, index) => {
    const date = new Date();
    date.setDate(date.getDate() - (6 - index));
    return {
      label: date.toLocaleDateString("en-US", { weekday: "short" }),
      revenue: 0,
      loss: 0,
      net: 0,
    };
  });

  const series =
    Array.isArray(weekly) && weekly.length ? weekly : fallbackWeekly;
  const labels = series.map((row) => (row?.label || "Day").toString());
  const revenueData = series.map((row) => Number(row?.revenue || 0));
  const lossData = series.map((row) => Number(row?.loss || 0));
  const netData = series.map((row) => Number(row?.net || 0));

  if (analyticsProfitLossChart) {
    analyticsProfitLossChart.destroy();
    analyticsProfitLossChart = null;
  }

  analyticsProfitLossChart = new window.Chart(canvas.getContext("2d"), {
    type: "bar",
    data: {
      labels,
      datasets: [
        {
          label: "Revenue",
          data: revenueData,
          backgroundColor: "rgba(255, 122, 26, 0.72)",
          borderColor: "rgba(255, 166, 64, 0.95)",
          borderWidth: 1,
          borderRadius: 8,
          borderSkipped: false,
        },
        {
          label: "Loss",
          data: lossData,
          backgroundColor: "rgba(220, 53, 69, 0.72)",
          borderColor: "rgba(255, 99, 132, 0.95)",
          borderWidth: 1,
          borderRadius: 8,
          borderSkipped: false,
        },
        {
          type: "line",
          label: "Net Progress",
          data: netData,
          borderColor: "rgba(40, 167, 69, 1)",
          backgroundColor: "rgba(40, 167, 69, 0.25)",
          borderWidth: 2,
          tension: 0.35,
          pointRadius: 3,
          pointHoverRadius: 5,
          fill: false,
          yAxisID: "y",
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: "index",
        intersect: false,
      },
      plugins: {
        legend: {
          labels: {
            color: "#d9d9d9",
            boxWidth: 12,
            boxHeight: 12,
          },
        },
        tooltip: {
          callbacks: {
            label: (context) => {
              const value = Number(context?.raw || 0);
              return `${context.dataset.label}: ${formatPkr(value)}`;
            },
          },
        },
      },
      scales: {
        x: {
          ticks: {
            color: "#bcbcbc",
          },
          grid: {
            color: "rgba(255,255,255,0.05)",
          },
        },
        y: {
          beginAtZero: true,
          ticks: {
            color: "#bcbcbc",
            callback: (value) => formatPkr(Number(value || 0)),
          },
          grid: {
            color: "rgba(255,255,255,0.06)",
          },
        },
      },
    },
  });
}

function refundBadgeClass(status) {
  const normalized = (status || "").toLowerCase();
  if (normalized === "accepted") return "bg-success";
  if (normalized === "rejected") return "bg-danger";
  return "bg-warning text-dark";
}

function renderRefundRequests() {
  const tbody = document.querySelector("#refundTable tbody");
  if (!tbody) return;

  tbody.innerHTML = "";

  if (!Array.isArray(adminRefunds) || adminRefunds.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="8" class="text-center py-4 text-secondary">No refund requests yet.</td></tr>';
    return;
  }

  adminRefunds.forEach((refund) => {
    const orderNumber = escapeHtml(
      refund.orderNumber || `#${refund.orderId || "-"}`,
    );
    const customerName = escapeHtml(refund.customerName || "Customer");
    const requestedAt = escapeHtml(formatShortDate(refund.requestedAt));
    const reason = escapeHtml(refund.reason || "No reason provided");
    const evidencePath = (refund.evidencePath || "").toString().trim();
    const evidenceName = escapeHtml(
      refund.evidenceName || "View uploaded file",
    );
    const evidenceUrlCandidate = evidencePath
      ? evidencePath.startsWith("http")
        ? evidencePath
        : `../../${encodeURI(evidencePath)}`
      : "";
    const evidenceUrl = sanitizeAdminMediaUrl(evidenceUrlCandidate);
    const safeEvidenceUrl = escapeHtml(evidenceUrl);
    const status = (refund.status || "pending").toLowerCase();
    const statusLabel =
      status.charAt(0).toUpperCase() + status.slice(1).toLowerCase();
    const paymentBadge = resolveOrderPaymentBadge(
      refund.orderPaymentStatus || "unpaid",
      refund.orderPaymentMethod || "",
    );

    const row = document.createElement("tr");
    row.className = "border-bottom border-secondary";
    row.innerHTML = `
      <td class="ps-4 py-3 text-light fw-semibold">${orderNumber}</td>
      <td class="py-3 text-light">${customerName}</td>
      <td class="py-3 text-secondary small">${requestedAt}</td>
      <td class="py-3"><span class="badge ${refundBadgeClass(status)} rounded-pill">${escapeHtml(statusLabel)}</span></td>
      <td class="py-3"><span class="badge ${paymentBadge.className} rounded-pill">${escapeHtml(paymentBadge.label)}</span></td>
      <td class="py-3 text-secondary small">${reason}</td>
      <td class="py-3 text-secondary small">${evidenceUrl ? `<a href="${safeEvidenceUrl}" target="_blank" rel="noopener" class="text-warning text-decoration-underline">${evidenceName}</a>` : "No file"}</td>
      <td class="pe-4 py-3">
        <div class="d-flex flex-wrap gap-1">
          <button class="btn btn-sm btn-outline-warning refund-status-btn" data-refund-id="${Number(refund.id || 0)}" data-status="pending">Pending</button>
          <button class="btn btn-sm btn-outline-success refund-status-btn" data-refund-id="${Number(refund.id || 0)}" data-status="accepted">Accept</button>
          <button class="btn btn-sm btn-outline-danger refund-status-btn" data-refund-id="${Number(refund.id || 0)}" data-status="rejected">Reject</button>
        </div>
      </td>
    `;
    tbody.appendChild(row);
  });
}

async function updateRefundStatus(refundId, status) {
  const normalizedStatus = (status || "pending").toLowerCase();
  if (!["pending", "accepted", "rejected"].includes(normalizedStatus)) {
    showNotification("Invalid refund status.", "danger");
    return;
  }

  const notePrompt =
    normalizedStatus === "pending"
      ? "Optional note for this refund request:"
      : `Optional note for ${normalizedStatus} decision:`;
  const adminNote = await showCustomPromptDialog(
    notePrompt,
    "",
    "Refund Decision Note",
  );
  if (adminNote === null) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_ORDERS_API, {
      action: "update-refund-status",
      refund_id: Number(refundId || 0),
      status: normalizedStatus,
      admin_note: adminNote.trim(),
    });

    setAdminOrdersPayload(result?.payload || {});
    displayRecentOrders();
    displayAllOrders();
    displayAllCustomers();
    calculateDashboardMetrics();
    renderAnalyticsSection();
    renderRefundRequests();
    updateNotifications();

    showNotification(result?.message || "Refund request updated.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to update refund request.",
      "danger",
    );
  }
}

function displayRecentOrders() {
  let orders = getAdminOrdersData();
  orders = orders
    .sort((a, b) => new Date(b.orderDate) - new Date(a.orderDate))
    .slice(0, 5);

  let tbody = document.querySelector("#dashboardSection .table tbody");
  if (!tbody) return;

  tbody.innerHTML = "";

  if (orders.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="5" class="text-center py-4 text-secondary">No orders yet</td></tr>';
    return;
  }

  orders.forEach((order, idx) => {
    const items = Array.isArray(order.items) ? order.items : [];
    const customerName = (order.customerName || "Customer").toString();
    const shortName = customerName.split(" ")[0] || customerName;
    const safeOrderId = escapeHtml(order.orderId || "");
    const safeShortName = escapeHtml(shortName);
    const safeOrderDate = escapeHtml(order.orderDate || "");
    const safeStatus = escapeHtml(order.status || "Pending");
    const totalAmount = Number(order.total || 0);
    const statusColor =
      order.status === "Pending"
        ? "bg-warning text-dark"
        : order.status === "Shipped"
          ? "bg-info text-dark"
          : order.status === "Cancelled"
            ? "bg-danger"
            : "bg-success";
    const row = document.createElement("tr");
    row.className = "border-bottom border-secondary";
    row.innerHTML = `
        <td class="ps-4 py-3 fw-semibold text-light">${safeOrderId}</td>
      <td class="py-3 text-light">${safeShortName}</td>
        <td class="py-3 text-secondary small">${safeOrderDate}</td>
        <td class="py-3 text-light fw-semibold">PKR ${totalAmount.toLocaleString()}</td>
            <td class="pe-4 py-3">
          <span class="badge ${statusColor} rounded-pill px-3 py-2">${safeStatus}</span>
            </td>
        `;
    row.style.cursor = "pointer";
    row.onclick = () => toggleOrderDetails("recentOrderDetails-" + idx);
    tbody.appendChild(row);
    const detailsRow = document.createElement("tr");
    detailsRow.id = "recentOrderDetails-" + idx;
    detailsRow.style.display = "none";
    detailsRow.className = "bg-dark";
    detailsRow.innerHTML = `
            <td colspan="5" class="py-3 px-4">
                <div style="background-color: #2a2a2a; padding: 15px; border-radius: 6px;">
            <h6 class="text-orange mb-3 fw-bold">Products in Order</h6>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        ${items
                          .map((item, i) => {
                            const price =
                              typeof item.price === "string"
                                ? parseInt(item.price.replace(/\D/g, ""))
                                : item.price;
                            const qty = parseInt(item.quantity) || 0;
                            const lineTotal = price * qty;
                            const rawImgSrc = item.image
                              ? item.image.startsWith("http")
                                ? item.image
                                : "../../" + item.image
                              : "https://via.placeholder.com/50?text=No+Image";
                            const imgSrc = escapeHtml(
                              sanitizeAdminMediaUrl(rawImgSrc) ||
                                "https://via.placeholder.com/50?text=No+Image",
                            );
                            const safeItemName = escapeHtml(
                              item.name || "Item",
                            );
                            return `
                            <div style="background-color: #1a1a1a; padding: 12px; border-radius: 4px; border: 1px solid #444; display: flex; gap: 12px;">
                                <img src="${imgSrc}" alt="${safeItemName}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; background-color: #333;" onerror="this.src='https://via.placeholder.com/50?text=No+Image';">
                                <div style="flex: 1;">
                                    <p class="text-light fw-semibold mb-1" style="font-size: 0.95rem;">${safeItemName}</p>
                                  <p class="text-secondary mb-0" style="font-size: 0.85rem;">Price: <strong class="text-orange">PKR ${price.toLocaleString()}</strong> x <strong>${qty}</strong> = <strong class="text-orange">PKR ${lineTotal.toLocaleString()}</strong></p>
                                </div>
                            </div>
                        `;
                          })
                          .join("")}
                    </div>
                </div>
            </td>
        `;
    tbody.appendChild(detailsRow);
  });

  $("#ordersSelectAll").prop("checked", false);
}

function displayAllOrders() {
  let orders = getAdminOrdersData();
  orders = orders.sort((a, b) => new Date(b.orderDate) - new Date(a.orderDate));

  const tbody = document.querySelector("#ordersTable tbody");
  if (!tbody) return;

  tbody.innerHTML = "";

  const statusClass = (status) => {
    const normalized = (status || "").toLowerCase();
    if (normalized === "pending") return "bg-warning text-dark";
    if (normalized === "processing" || normalized === "confirmed") {
      return "bg-info text-dark";
    }
    if (normalized === "shipped") return "bg-primary";
    if (normalized === "cancelled") return "bg-danger";
    if (normalized === "refunded") return "bg-secondary";
    return "bg-success";
  };

  if (orders.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="8" class="text-center py-4 text-secondary">No orders found</td></tr>';
    return;
  }

  orders.forEach((order, idx) => {
    const items = Array.isArray(order.items) ? order.items : [];
    const totalAmount = Number(order.total || 0);
    const rawOrderId = (order.orderId || "-").toString();
    const safeOrderId = escapeHtml(rawOrderId);
    const encodedOrderId = encodeURIComponent(rawOrderId);
    const safeCustomerName = escapeHtml(order.customerName || "Customer");
    const safeDate = escapeHtml(order.orderDate || "-");
    const safeEmail = escapeHtml(order.email || "N/A");
    const safePhone = escapeHtml(order.phone || "N/A");
    const safeAddress = escapeHtml(order.address || "N/A");
    const statusValue = (order.status || "Pending").toString();
    const paymentBadge = resolveOrderPaymentBadge(
      order.paymentStatus || "",
      order.paymentMethod || "",
    );
    const safeUserNote = escapeHtml((order.userNote || "").toString());
    const safeAdminNote = escapeHtml((order.adminNote || "").toString());
    const safeDeliveryEstimate = escapeHtml(
      (order.deliveryEstimate || "").toString(),
    );
    const isStatusUpdating = ORDER_STATUS_LOCKS.has(rawOrderId);
    const statusButtonDisabled = isStatusUpdating ? "disabled" : "";
    const invoiceUrl = `../../invoice.php?order=${encodedOrderId}`;

    const row = document.createElement("tr");
    row.className = "border-bottom border-secondary";
    row.innerHTML = `
            <td class="ps-4 py-3" onclick="event.stopPropagation();">
          <input type="checkbox" class="form-check-input order-select-row" value="${encodedOrderId}">
            </td>
            <td class="ps-4 py-3 fw-semibold text-light">${safeOrderId}</td>
            <td class="py-3 text-light">${safeCustomerName}</td>
            <td class="py-3 text-secondary small">${safeDate}</td>
            <td class="py-3 text-light fw-semibold">${formatPkr(totalAmount)}</td>
            <td class="py-3"><span class="badge ${paymentBadge.className} rounded-pill">${escapeHtml(paymentBadge.label)}</span></td>
            <td class="py-3"><span class="badge ${statusClass(statusValue)} rounded-pill">${escapeHtml(statusValue)}</span></td>
            <td class="pe-4 py-3">
              <button class="btn btn-sm btn-outline-danger" onclick="deleteOrder(decodeURIComponent('${encodedOrderId}')); event.stopPropagation();"><i class="bi bi-trash"></i></button>
            </td>
        `;
    row.style.cursor = "pointer";
    row.onclick = () => toggleOrderDetails("orderDetails-" + idx);
    tbody.appendChild(row);

    const detailsRow = document.createElement("tr");
    detailsRow.id = "orderDetails-" + idx;
    detailsRow.style.display = "none";
    detailsRow.className = "bg-dark";
    detailsRow.innerHTML = `
            <td colspan="8" class="py-3 px-4">
                <div style="background-color: #2a2a2a; padding: 20px; border-radius: 6px;">
                    <div class="row mb-3">
                        <div class="col-md-6">
                      <h6 class="text-orange mb-3 fw-bold">Customer Details</h6>
                            <p class="text-secondary mb-1"><strong class="text-light">Name:</strong> ${safeCustomerName}</p>
                            <p class="text-secondary mb-1"><strong class="text-light">Email:</strong> ${safeEmail}</p>
                            <p class="text-secondary mb-1"><strong class="text-light">Phone:</strong> ${safePhone}</p>
                            <p class="text-secondary"><strong class="text-light">Address:</strong> ${safeAddress}</p>
                        </div>
                        <div class="col-md-6">
                      <h6 class="text-orange mb-3 fw-bold">Order Summary</h6>
                            <p class="text-secondary mb-1"><strong class="text-light">Subtotal:</strong> ${formatPkr(order.subtotal || 0)}</p>
                            <p class="text-secondary mb-1"><strong class="text-light">Shipping:</strong> ${formatPkr(order.shipping || 0)}</p>
                            <p class="text-secondary mb-1"><strong class="text-light">Discount:</strong> ${Number(order.discount || 0) > 0 ? `- ${formatPkr(order.discount || 0)}` : formatPkr(0)}${order.couponCode ? ` <span class="badge bg-dark border border-secondary ms-1">${escapeHtml(order.couponCode)}</span>` : ""}</p>
                            <p class="text-secondary mb-1"><strong class="text-light">Delivery Estimate:</strong> ${safeDeliveryEstimate || "Not set"}</p>
                            <p class="text-secondary mb-1"><strong class="text-light">Customer Note:</strong> ${safeUserNote || "-"}</p>
                            <p class="text-secondary mb-1"><strong class="text-light">Admin Note:</strong> ${safeAdminNote || "-"}</p>
                            <p class="text-orange fw-bold"><strong>Total:</strong> ${formatPkr(order.total || 0)}</p>
                            <div style="margin-top: 15px;">
                        <h6 class="text-orange mb-2 fw-bold">Change Status</h6>
                                ${isStatusUpdating ? '<p class="text-warning small mb-2"><i class="bi bi-arrow-repeat"></i> Status update in progress...</p>' : ""}
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                  <button class="btn btn-sm btn-warning text-dark fw-semibold" ${statusButtonDisabled} onclick="updateOrderStatus(decodeURIComponent('${encodedOrderId}'), 'Pending'); event.stopPropagation();"><i class="bi bi-hourglass"></i> Pending</button>
                                  <button class="btn btn-sm btn-info text-dark fw-semibold" ${statusButtonDisabled} onclick="updateOrderStatus(decodeURIComponent('${encodedOrderId}'), 'Shipped'); event.stopPropagation();"><i class="bi bi-truck"></i> Shipped</button>
                                  <button class="btn btn-sm btn-success fw-semibold" ${statusButtonDisabled} onclick="updateOrderStatus(decodeURIComponent('${encodedOrderId}'), 'Delivered'); event.stopPropagation();"><i class="bi bi-check-circle"></i> Delivered</button>
                                  <button class="btn btn-sm btn-danger fw-semibold" ${statusButtonDisabled} onclick="updateOrderStatus(decodeURIComponent('${encodedOrderId}'), 'Cancelled'); event.stopPropagation();"><i class="bi bi-x-circle"></i> Cancel</button>
                                  <button class="btn btn-sm btn-outline-orange fw-semibold" ${statusButtonDisabled} onclick="updateOrderLogistics(decodeURIComponent('${encodedOrderId}')); event.stopPropagation();"><i class="bi bi-pencil-square"></i> Logistics</button>
                                  <a class="btn btn-sm btn-outline-light fw-semibold" href="${invoiceUrl}" target="_blank" rel="noopener" onclick="event.stopPropagation();"><i class="bi bi-file-earmark-pdf"></i> Invoice</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr style="border-color: #444;">
                      <h6 class="text-orange mb-3 fw-bold">Products in Order</h6>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        ${items
                          .map((item) => {
                            const price =
                              typeof item.price === "string"
                                ? parseInt(item.price.replace(/\D/g, ""))
                                : Number(item.price || 0);
                            const qty = parseInt(item.quantity, 10) || 0;
                            const lineTotal = price * qty;
                            const rawImgSrc = item.image
                              ? item.image.startsWith("http")
                                ? item.image
                                : "../../" + item.image
                              : "https://via.placeholder.com/60?text=No+Image";
                            const imgSrc = escapeHtml(
                              sanitizeAdminMediaUrl(rawImgSrc) ||
                                "https://via.placeholder.com/60?text=No+Image",
                            );
                            return `
                              <div style="background-color: #1a1a1a; padding: 12px; border-radius: 4px; border: 1px solid #444; display: flex; gap: 12px;">
                                <img src="${imgSrc}" alt="${escapeHtml(item.name || "Item")}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; background-color: #333;" onerror="this.src='https://via.placeholder.com/60?text=No+Image';">
                                <div style="flex: 1;">
                                  <p class="text-light fw-semibold mb-1" style="font-size: 0.95rem;">${escapeHtml(item.name || "Item")}</p>
                                  <p class="text-secondary mb-0" style="font-size: 0.9rem;"><strong>Unit Price:</strong> ${formatPkr(price)} | <strong>Quantity:</strong> ${qty} | <strong class="text-orange">Total: ${formatPkr(lineTotal)}</strong></p>
                                </div>
                              </div>
                            `;
                          })
                          .join("")}
                    </div>
                </div>
            </td>
        `;
    tbody.appendChild(detailsRow);
  });
}

