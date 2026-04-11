function isWithinDays(dateString, days) {
  if (!dateString) return false;
  const date = new Date(dateString);
  if (Number.isNaN(date.getTime())) return false;
  const now = new Date();
  const diffMs = now - date;
  const diffDays = diffMs / (1000 * 60 * 60 * 24);
  return diffDays <= days;
}

function getRollingWindowStart(days) {
  const date = new Date();
  date.setHours(0, 0, 0, 0);
  date.setDate(date.getDate() - Math.max(1, Number(days) || 30));
  return date;
}

function parseDashboardDate(dateString) {
  if (!dateString) {
    return null;
  }

  const parsed = new Date(dateString);
  if (Number.isNaN(parsed.getTime())) {
    return null;
  }

  return parsed;
}

function isOrderWithinDashboardWindow(order, windowStart) {
  const parsedOrderDate = parseDashboardDate(order?.orderDate);
  if (!parsedOrderDate) {
    return false;
  }

  return parsedOrderDate >= windowStart;
}

function calculateDashboardMetrics() {
  const windowStart = getRollingWindowStart(DASHBOARD_WINDOW_DAYS);
  const orders = getAdminOrdersData();
  const ordersInWindow = orders.filter((order) =>
    isOrderWithinDashboardWindow(order, windowStart),
  );
  const deliveredOrdersInWindow = ordersInWindow.filter(
    (order) => (order.status || "").toLowerCase() === "delivered",
  );

  const fallbackRevenue = deliveredOrdersInWindow.reduce(
    (sum, order) => sum + Number(order.total || 0),
    0,
  );

  const fallbackOrders = ordersInWindow.length;

  const fallbackPendingFulfillment = ordersInWindow.filter((order) => {
    const status = (order.status || "").toLowerCase();
    return ["pending", "confirmed", "processing"].includes(status);
  }).length;

  const fallbackCustomerSet = new Set();
  ordersInWindow.forEach((order) => {
    const identifier = String(order.customerEmail || order.customerName || "")
      .trim()
      .toLowerCase();
    if (identifier !== "") {
      fallbackCustomerSet.add(identifier);
    }
  });

  const fallbackCustomerOrderCounts = new Map();
  ordersInWindow.forEach((order) => {
    const key =
      String(order.customerEmail || order.customerName || "")
        .trim()
        .toLowerCase() || "";

    if (key === "") {
      return;
    }

    fallbackCustomerOrderCounts.set(
      key,
      (fallbackCustomerOrderCounts.get(key) || 0) + 1,
    );
  });

  const fallbackReturningCustomers = Array.from(
    fallbackCustomerOrderCounts.values(),
  ).filter((count) => count > 1).length;

  const fallbackReturningRate =
    fallbackCustomerOrderCounts.size > 0
      ? (fallbackReturningCustomers / fallbackCustomerOrderCounts.size) * 100
      : 0;

  let totalProducts = 0;
  if (allSections && allSections.length > 0) {
    allSections.forEach((section) => {
      if (section.products && Array.isArray(section.products)) {
        totalProducts += section.products.length;
      }
    });
  }

  const revenue = Number(adminMetrics?.totalRevenue ?? fallbackRevenue);
  const totalOrdersCount = Number(adminMetrics?.totalOrders ?? fallbackOrders);
  const totalCustomers = Number(
    adminMetrics?.totalCustomers ?? fallbackCustomerSet.size,
  );
  const productsCount = Number(adminMetrics?.totalProducts ?? totalProducts);
  const avgOrderValue = Number(
    adminMetrics?.avgOrderValue ??
      (totalOrdersCount > 0 ? revenue / totalOrdersCount : 0),
  );
  const returningCustomerRate = Number(
    adminMetrics?.returningCustomerRate ?? fallbackReturningRate,
  );
  const pendingFulfillmentCount = Number(
    adminMetrics?.pendingFulfillment ?? fallbackPendingFulfillment,
  );

  const totalRevenueNode = document.getElementById("totalRevenueValue");
  if (totalRevenueNode) {
    totalRevenueNode.textContent = "PKR " + revenue.toLocaleString();
  }

  const windowLabel = `Last ${DASHBOARD_WINDOW_DAYS} Days`;
  const totalRevenueInfoNode = document.getElementById("totalRevenueInfo");
  if (totalRevenueInfoNode) {
    totalRevenueInfoNode.textContent = `Delivered Orders (${windowLabel})`;
  }

  const totalOrdersNode = document.getElementById("totalOrdersValue");
  if (totalOrdersNode) {
    totalOrdersNode.textContent = totalOrdersCount.toLocaleString();
  }

  const totalOrdersInfoNode = document.getElementById("totalOrdersInfo");
  if (totalOrdersInfoNode) {
    totalOrdersInfoNode.textContent = windowLabel;
  }

  const totalCustomersNode = document.getElementById("totalCustomersValue");
  if (totalCustomersNode) {
    totalCustomersNode.textContent = totalCustomers.toLocaleString();
  }

  const totalCustomersInfoNode = document.getElementById("totalCustomersInfo");
  if (totalCustomersInfoNode) {
    totalCustomersInfoNode.textContent = windowLabel;
  }

  const totalProductsNode = document.getElementById("totalProductsValue");
  if (totalProductsNode) {
    totalProductsNode.textContent = productsCount.toLocaleString();
  }

  const avgOrderValueNode = document.getElementById("avgOrderValueValue");
  if (avgOrderValueNode) {
    avgOrderValueNode.textContent =
      "PKR " +
      avgOrderValue.toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
      });
  }

  const avgOrderValueInfoNode = document.getElementById("avgOrderValueInfo");
  if (avgOrderValueInfoNode) {
    avgOrderValueInfoNode.textContent = `Delivered Revenue / ${windowLabel} Orders`;
  }

  const returningCustomerRateNode = document.getElementById(
    "returningCustomerRateValue",
  );
  if (returningCustomerRateNode) {
    returningCustomerRateNode.textContent = `${returningCustomerRate.toFixed(1)}%`;
  }

  const pendingFulfillmentNode = document.getElementById(
    "pendingFulfillmentValue",
  );
  if (pendingFulfillmentNode) {
    pendingFulfillmentNode.textContent =
      pendingFulfillmentCount.toLocaleString();
  }

  const pendingFulfillmentInfoNode = document.getElementById(
    "pendingFulfillmentInfo",
  );
  if (pendingFulfillmentInfoNode) {
    pendingFulfillmentInfoNode.textContent = `Pending + Confirmed + Processing (${windowLabel})`;
  }

  const returningCustomerRateInfoNode = document.getElementById(
    "returningCustomerRateInfo",
  );
  if (returningCustomerRateInfoNode && fallbackCustomerOrderCounts.size > 0) {
    returningCustomerRateInfoNode.textContent =
      fallbackReturningCustomers +
      " repeat buyers out of " +
      fallbackCustomerOrderCounts.size +
      " active customers";
  }
}

