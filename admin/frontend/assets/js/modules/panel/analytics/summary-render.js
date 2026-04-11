function renderAnalyticsSection() {
  const revenue = Number(adminMetrics?.totalRevenue || 0);
  const refundLoss = Number(adminMetrics?.refundLoss || 0);
  const netRevenue = Number(adminMetrics?.netRevenue ?? revenue - refundLoss);
  const orderCount = Math.max(0, parseInt(adminMetrics?.totalOrders, 10) || 0);
  const avgOrderValue = Number(adminMetrics?.avgOrderValue || 0);
  const returningRate = Number(adminMetrics?.returningCustomerRate || 0);
  const orders = getAdminOrdersData();
  const openOrders = orders.filter((order) => {
    const status = (order?.status || "").toString().toLowerCase();
    return ["pending", "confirmed", "processing"].includes(status);
  }).length;
  const lowStockCount = productsData.filter(
    (product) => Number(product.stock) <= NOTIFICATION_RULES.lowStockThreshold,
  ).length;
  const pendingRefundCount = (
    Array.isArray(adminRefunds) ? adminRefunds : []
  ).filter(
    (refund) => (refund?.status || "").toString().toLowerCase() === "pending",
  ).length;
  const acceptedRefundCount = (
    Array.isArray(adminRefunds) ? adminRefunds : []
  ).filter(
    (refund) => (refund?.status || "").toString().toLowerCase() === "accepted",
  ).length;
  const rejectedRefundCount = (
    Array.isArray(adminRefunds) ? adminRefunds : []
  ).filter(
    (refund) => (refund?.status || "").toString().toLowerCase() === "rejected",
  ).length;

  const pendingRefunds = Number(
    adminMetrics?.pendingRefunds ?? pendingRefundCount,
  );
  const acceptedRefunds = Number(
    adminMetrics?.acceptedRefunds ?? acceptedRefundCount,
  );
  const rejectedRefunds = Number(
    adminMetrics?.rejectedRefunds ?? rejectedRefundCount,
  );

  $("#analyticsRevenueValue").text(formatPkr(revenue));
  $("#analyticsOrdersValue").text(orderCount.toLocaleString());
  $("#analyticsAovValue").text(formatPkr(avgOrderValue));
  $("#analyticsReturningValue").text(`${returningRate.toFixed(1)}%`);

  $("#analyticsRevenueHint").html(
    '<i class="bi bi-calendar-week"></i> Last 30 days',
  );
  $("#analyticsOrdersHint").html(
    '<i class="bi bi-calendar-week"></i> Last 30 days',
  );
  $("#analyticsAovHint").html(
    '<i class="bi bi-calculator"></i> Sales divided by orders',
  );
  $("#analyticsReturningHint").html(
    '<i class="bi bi-people"></i> Customers who came back',
  );

  $("#storeHealthOpenOrders").text(openOrders.toLocaleString());
  $("#storeHealthLowStock").text(lowStockCount.toLocaleString());
  $("#storeHealthPendingRefunds").text(pendingRefunds.toLocaleString());

  const dashboardRefundValue = document.getElementById(
    "dashboardRefundSummaryValue",
  );
  if (dashboardRefundValue) {
    dashboardRefundValue.textContent = `${pendingRefunds} / ${acceptedRefunds} / ${rejectedRefunds}`;
  }

  const dashboardRefundInfo = document.getElementById(
    "dashboardRefundSummaryInfo",
  );
  if (dashboardRefundInfo) {
    dashboardRefundInfo.textContent = "Pending / Accepted / Rejected";
  }

  const actionItems = [];
  if (openOrders > 0) {
    actionItems.push(
      `Process ${openOrders} open order(s) to keep delivery speed high.`,
    );
  }
  if (lowStockCount > 0) {
    actionItems.push(
      `Restock ${lowStockCount} low-stock product(s) before they run out.`,
    );
  }
  if (pendingRefunds > 0) {
    actionItems.push(
      `Review ${pendingRefunds} pending refund request(s) today.`,
    );
  }
  if (refundLoss > 0) {
    actionItems.push(
      `Refund loss in the last 30 days is ${formatPkr(refundLoss)}. Net progress is ${formatPkr(netRevenue)}.`,
    );
  }
  if (orderCount === 0) {
    actionItems.push(
      "No recent orders yet. Push one offer through Email Center.",
    );
  }
  if (returningRate < 20 && orderCount > 0) {
    actionItems.push(
      "Repeat customers are low. Send a follow-up offer to past buyers.",
    );
  }
  if (!actionItems.length) {
    actionItems.push(
      "Everything looks healthy. Keep monitoring stock and orders daily.",
    );
  }

  const actionList = $("#analyticsActionList");
  if (actionList.length) {
    actionList.empty();
    actionItems.slice(0, 5).forEach((item) => {
      actionList.append(
        `<li class="list-group-item bg-transparent border-secondary text-light">${escapeHtml(item)}</li>`,
      );
    });
  }

  const weekly = Array.isArray(adminMetrics?.weeklyPerformance)
    ? adminMetrics.weeklyPerformance
    : [];
  const topProducts = Array.isArray(adminMetrics?.topProducts)
    ? adminMetrics.topProducts
    : [];

  const weeklyWrap = $("#weeklyPerformanceRows");
  if (weeklyWrap.length) {
    weeklyWrap.empty();

    if (!weekly.length) {
      weeklyWrap.append(
        '<div class="text-secondary small">No weekly performance data yet.</div>',
      );
    } else {
      const maxRevenue = Math.max(
        ...weekly.map((item) => Number(item?.revenue || 0)),
        0,
      );

      weekly.forEach((item, idx) => {
        const label = escapeHtml(item?.label || "Day");
        const revenueValue = Number(item?.revenue || 0);
        const ordersValue = Math.max(0, parseInt(item?.orders, 10) || 0);
        const width = maxRevenue > 0 ? (revenueValue / maxRevenue) * 100 : 0;
        const barClass =
          idx % 4 === 0
            ? "bg-orange"
            : idx % 4 === 1
              ? "bg-info"
              : idx % 4 === 2
                ? "bg-success"
                : "bg-warning";

        weeklyWrap.append(`
          <div class="mb-3">
            <div class="d-flex justify-content-between mb-2">
              <span class="text-light">${label}</span>
              <span class="text-secondary">${formatPkr(revenueValue)} · ${ordersValue} order${ordersValue === 1 ? "" : "s"}</span>
            </div>
            <div class="progress" style="height: 6px;">
              <div class="progress-bar ${barClass}" style="width: ${Math.max(4, Math.min(100, width)).toFixed(1)}%"></div>
            </div>
          </div>
        `);
      });
    }
  }

  const topProductsWrap = $("#topProductsList");
  if (topProductsWrap.length) {
    topProductsWrap.empty();

    if (!topProducts.length) {
      topProductsWrap.append(
        '<div class="text-secondary small">No top-product data yet.</div>',
      );
    } else {
      topProducts.forEach((item) => {
        const name = escapeHtml(item?.name || "Product");
        const orders = Math.max(0, parseInt(item?.orders, 10) || 0);
        const revenueTotal = Number(item?.revenue || 0);
        topProductsWrap.append(`
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <p class="text-light mb-1 fw-semibold">${name}</p>
              <p class="text-secondary small mb-0">${orders} units sold</p>
            </div>
            <span class="text-orange fw-semibold">${formatPkr(revenueTotal)}</span>
          </div>
        `);
      });
    }
  }

  renderAnalyticsProfitLossChart(weekly);
}

