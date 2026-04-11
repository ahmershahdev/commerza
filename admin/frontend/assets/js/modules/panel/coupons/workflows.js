function formatDateTime(value) {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "-";
  return `${date.toISOString().slice(0, 10)} ${date.toTimeString().slice(0, 5)}`;
}

function setAdminDropdownSelection(targetId, value, label = "") {
  const input = $(`#${targetId}`);
  const button = $(`#${targetId}Btn`);
  const menu = $(`#${targetId}Menu`);

  if (!input.length) {
    return;
  }

  const normalizedValue = (value ?? "").toString();
  input.val(normalizedValue);

  if (button.length && label.toString().trim() !== "") {
    button.text(label.toString());
  }

  if (!menu.length) {
    return;
  }

  let hasActive = false;
  menu.find(".admin-dropdown-item").each(function () {
    const item = $(this);
    const itemValue = (item.data("value") ?? "").toString();
    const isActive = itemValue === normalizedValue;
    item.toggleClass("active", isActive);
    if (isActive) {
      hasActive = true;
    }
  });

  if (!hasActive) {
    menu.find(".admin-dropdown-item").removeClass("active");
  }
}

function couponDiscountTypeLabel(value) {
  return (value || "").toString() === "percent" ? "Percent %" : "Fixed PKR";
}

function reviewVisibilityLabel(value) {
  const normalized = (value || "all").toString();
  if (normalized === "visible") return "Visible";
  if (normalized === "hidden") return "Hidden";
  return "All Reviews";
}

function fakeReviewVisibilityLabel(value) {
  return (value || "1").toString() === "0" ? "Hidden" : "Visible";
}

function parseAdminProductIdInput(value) {
  const raw = (value || "").toString().trim();
  if (raw === "") {
    return 0;
  }

  const direct = parseInt(raw, 10);
  if (Number.isInteger(direct) && direct > 0) {
    return direct;
  }

  const leadingMatch = raw.match(/^(\d+)/);
  if (leadingMatch && leadingMatch[1]) {
    const parsed = parseInt(leadingMatch[1], 10);
    if (Number.isInteger(parsed) && parsed > 0) {
      return parsed;
    }
  }

  return 0;
}

function reviewProductOptions(limit = 300) {
  if (!Array.isArray(productsData) || productsData.length === 0) {
    return [];
  }

  const deduped = new Map();
  productsData.forEach((item) => {
    const id = parseInt(item?.id, 10) || 0;
    if (id <= 0 || deduped.has(id)) {
      return;
    }

    const name = (item?.name || "").toString().trim();
    deduped.set(id, {
      id,
      name: name === "" ? `Product ${id}` : name,
    });
  });

  return Array.from(deduped.values())
    .sort((left, right) => {
      const leftName = (left.name || "").toLowerCase();
      const rightName = (right.name || "").toLowerCase();
      const cmp = leftName.localeCompare(rightName);
      if (cmp !== 0) {
        return cmp;
      }
      return left.id - right.id;
    })
    .slice(0, Math.max(0, limit));
}

function renderReviewProductSuggestions() {
  const datalist = document.getElementById("reviewProductSuggestions");
  if (!datalist) {
    return;
  }

  const options = reviewProductOptions(400);
  datalist.innerHTML = "";

  options.forEach((option) => {
    const node = document.createElement("option");
    node.value = `${option.id} - ${option.name}`;
    datalist.appendChild(node);
  });
}

function resetReviewQuickAddForm() {
  $("#reviewAddUserId").val("");
  $("#reviewAddProductId").val("");
  $("#reviewAddOrderId").val("");
  setAdminDropdownSelection("reviewAddRating", "5", "5 - Excellent");
  setAdminDropdownSelection("reviewAddVisible", "1", "Visible");
  $("#reviewAddText").val("");
  $("#reviewAddNote").val("");
}

function focusReviewQuickForm() {
  const card = document.getElementById("reviewQuickAddCard");
  if (card) {
    card.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  window.setTimeout(() => {
    const input = document.getElementById("reviewAddUserId");
    if (input) {
      input.focus();
    }
  }, 120);
}

function collectReviewQuickAddPayload() {
  const userId = parseInt($("#reviewAddUserId").val(), 10) || 0;
  const productId = parseAdminProductIdInput($("#reviewAddProductId").val());
  const orderRaw = ($("#reviewAddOrderId").val() || "").toString().trim();
  const rating = parseInt($("#reviewAddRating").val(), 10) || 0;
  const isVisible =
    ($("#reviewAddVisible").val() || "1").toString() === "0" ? 0 : 1;
  const reviewText = ($("#reviewAddText").val() || "").toString().trim();
  const adminNote = ($("#reviewAddNote").val() || "").toString().trim();

  if (userId <= 0) {
    throw new Error("Enter a valid User ID.");
  }

  if (productId <= 0) {
    throw new Error("Enter a valid Product ID.");
  }

  let orderId = 0;
  if (orderRaw !== "") {
    orderId = parseInt(orderRaw, 10) || 0;
    if (orderId <= 0) {
      throw new Error("Order ID must be a positive number.");
    }
  }

  if (rating < 1 || rating > 5) {
    throw new Error("Rating must be between 1 and 5.");
  }

  if (reviewText.length < 10 || reviewText.length > 500) {
    throw new Error("Review text must be 10 to 500 characters.");
  }

  if (adminNote.length > 500) {
    throw new Error("Admin note can be up to 500 characters.");
  }

  return {
    action: "add-review",
    user_id: userId,
    product_id: productId,
    order_id: orderId,
    rating,
    review_text: reviewText,
    is_visible: isVisible,
    admin_note: adminNote,
  };
}

async function submitReviewQuickAddForm() {
  try {
    const payload = collectReviewQuickAddPayload();
    const result = await adminPostJson(ADMIN_REVIEWS_API, payload);

    adminReviews = Array.isArray(result?.payload?.reviews)
      ? result.payload.reviews
      : adminReviews;
    renderReviewsStats(result?.payload?.stats || {});
    renderReviewsTable();
    resetReviewQuickAddForm();
    showNotification(result?.message || "Review added.", "success");
  } catch (error) {
    showNotification(error?.message || "Unable to add review.", "danger");
  }
}

function securitySeverityLabel(value) {
  const normalized = (value || "").toString();
  if (normalized === "info") return "Info";
  if (normalized === "warning") return "Warning";
  if (normalized === "critical") return "Critical";
  return "All";
}

function securityActorTypeLabel(value) {
  const normalized = (value || "").toString();
  if (normalized === "user") return "User";
  if (normalized === "admin") return "Admin";
  return "All";
}

const COUPON_SAMPLE_CART_TOTAL = 5000;
const COUPON_PRESETS = {
  flash10: {
    seed: "FLASH",
    title: "Flash Deal 10%",
    description: "24-hour conversion campaign",
    discountType: "percent",
    discountValue: 10,
    minOrder: 2000,
    maxDiscount: 900,
    usageLimit: 200,
    perUserLimit: 1,
    expiresInHours: 24,
  },
  welcome250: {
    seed: "WELCOME",
    title: "Welcome PKR 250",
    description: "First-order welcome incentive",
    discountType: "fixed",
    discountValue: 250,
    minOrder: 1500,
    maxDiscount: null,
    usageLimit: 1000,
    perUserLimit: 1,
    expiresInHours: 168,
  },
  vip15: {
    seed: "VIP",
    title: "VIP 15% Capped",
    description: "High-intent returning customer offer",
    discountType: "percent",
    discountValue: 15,
    minOrder: 5000,
    maxDiscount: 1500,
    usageLimit: 300,
    perUserLimit: 2,
    expiresInHours: 120,
  },
};

function couponNormalizeCodeInput(value) {
  const cleaned = (value || "")
    .toString()
    .trim()
    .toUpperCase()
    .replace(/[^A-Z0-9_-]+/g, "");
  return cleaned.slice(0, 50);
}

function couponBuildCode(seed = "") {
  const normalizedSeed = couponNormalizeCodeInput(
    (seed || "").toString().replace(/\s+/g, "_").replace(/_+/g, "_"),
  );

  const datePart = new Date().toISOString().slice(5, 10).replace("-", "");
  const randomPart = Math.floor(100 + Math.random() * 900);

  if (normalizedSeed.length >= 3) {
    return `${normalizedSeed.slice(0, 18)}${datePart}`;
  }

  return `DEAL${datePart}${randomPart}`;
}

function couponCurrentFormState() {
  const discountType =
    ($("#couponDiscountType").val() || "fixed").toString() === "percent"
      ? "percent"
      : "fixed";

  const discountValue = Math.max(
    0,
    parseFloat($("#couponDiscountValue").val()) || 0,
  );
  const minOrder = Math.max(0, parseFloat($("#couponMinOrder").val()) || 0);
  const maxDiscountRaw = parseFloat($("#couponMaxDiscount").val());
  const maxDiscount =
    discountType === "percent" && Number.isFinite(maxDiscountRaw)
      ? Math.max(0, maxDiscountRaw)
      : 0;

  return {
    code: couponNormalizeCodeInput($("#couponCode").val()),
    title: ($("#couponTitle").val() || "").toString().trim(),
    discountType,
    discountValue,
    minOrder,
    maxDiscount,
    usageLimit: Math.max(0, parseInt($("#couponUsageLimit").val(), 10) || 0),
    perUserLimit: Math.max(
      0,
      parseInt($("#couponPerUserLimit").val(), 10) || 0,
    ),
    expiresAt: ($("#couponExpiresAt").val() || "").toString().trim(),
    isActive: $("#couponIsActive").is(":checked"),
  };
}

function couponSimulationLabel(state) {
  const sampleSubtotal = COUPON_SAMPLE_CART_TOTAL;
  let discount = 0;

  if (sampleSubtotal >= state.minOrder && state.discountValue > 0) {
    if (state.discountType === "percent") {
      discount = sampleSubtotal * (state.discountValue / 100);
      if (state.maxDiscount > 0) {
        discount = Math.min(discount, state.maxDiscount);
      }
    } else {
      discount = state.discountValue;
    }
  }

  discount = Math.max(0, Math.min(discount, sampleSubtotal));
  return `Sample: On ${formatPkr(sampleSubtotal)} cart, discount is ${formatPkr(discount)}.`;
}

function updateCouponPreview() {
  if (!$("#couponPreviewCode").length) {
    return;
  }

  const state = couponCurrentFormState();
  const codeLabel = state.code || "NO-CODE";
  const typeLabel =
    state.discountType === "percent"
      ? "Percent discount"
      : "Fixed PKR discount";
  const valueLabel =
    state.discountType === "percent"
      ? `${state.discountValue.toFixed(2)}%`
      : formatPkr(state.discountValue);
  const limitsLabel = [
    state.usageLimit > 0 ? `Total ${state.usageLimit}` : "Total unlimited",
    state.perUserLimit > 0
      ? `Per-user ${state.perUserLimit}`
      : "Per-user unlimited",
  ].join(" | ");

  let expiryLabel = "No expiry";
  if (state.expiresAt) {
    const parsed = new Date(state.expiresAt);
    if (!Number.isNaN(parsed.getTime())) {
      expiryLabel = formatDateTime(parsed.toISOString());
    }
  }

  $("#couponPreviewCode").text(codeLabel);
  $("#couponPreviewType").text(typeLabel);
  $("#couponPreviewValue").text(valueLabel);
  $("#couponPreviewMinOrder").text(formatPkr(state.minOrder));
  $("#couponPreviewLimits").text(limitsLabel);
  $("#couponPreviewExpiry").text(expiryLabel);
  $("#couponPreviewStatus").text(state.isActive ? "Active" : "Inactive");
  $("#couponPreviewSimulation").text(couponSimulationLabel(state));
}

function applyCouponPreset(presetKey) {
  const preset = COUPON_PRESETS[(presetKey || "").toString()];
  if (!preset) {
    return;
  }

  const generatedCode = couponBuildCode(preset.seed);
  const expiresAt = new Date(Date.now() + preset.expiresInHours * 3600000);
  const localValue = new Date(
    expiresAt.getTime() - expiresAt.getTimezoneOffset() * 60000,
  )
    .toISOString()
    .slice(0, 16);

  $("#couponCodeSeed").val(preset.seed);
  $("#couponCode").val(generatedCode);
  $("#couponTitle").val(preset.title);
  $("#couponDescription").val(preset.description);
  setAdminDropdownSelection(
    "couponDiscountType",
    preset.discountType,
    couponDiscountTypeLabel(preset.discountType),
  );
  $("#couponDiscountType").trigger("change");
  $("#couponDiscountValue").val(preset.discountValue);
  $("#couponMinOrder").val(preset.minOrder);
  $("#couponMaxDiscount").val(preset.maxDiscount || "");
  $("#couponUsageLimit").val(preset.usageLimit);
  $("#couponPerUserLimit").val(preset.perUserLimit);
  $("#couponExpiresAt").val(localValue);
  $("#couponIsActive").prop("checked", true);

  updateCouponPreview();
  showNotification("Coupon preset applied. Review and save.", "success");
}

function generateCouponCodeFromSeed() {
  const seed =
    ($("#couponCodeSeed").val() || $("#couponTitle").val() || "")
      .toString()
      .trim() || "DEAL";
  const generated = couponBuildCode(seed);
  $("#couponCode").val(generated);
  updateCouponPreview();
}

async function copyCouponCodeToClipboard() {
  const code = couponNormalizeCodeInput($("#couponCode").val());
  if (!code) {
    showNotification("Generate or type a coupon code first.", "warning");
    return;
  }

  try {
    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(code);
      showNotification("Coupon code copied.", "success");
      return;
    }
  } catch (error) {
    // Fallback to legacy copy flow below.
  }

  const tempInput = document.createElement("input");
  tempInput.value = code;
  document.body.appendChild(tempInput);
  tempInput.select();
  document.execCommand("copy");
  document.body.removeChild(tempInput);
  showNotification("Coupon code copied.", "success");
}

function prefillCouponEmailTemplateFromForm() {
  const code = couponNormalizeCodeInput($("#couponCode").val()) || "{{code}}";
  const discountType =
    ($("#couponDiscountType").val() || "fixed").toString() === "percent"
      ? "percent"
      : "fixed";
  const discountValue = Math.max(
    0,
    parseFloat($("#couponDiscountValue").val()) || 0,
  );
  const minOrder = Math.max(0, parseFloat($("#couponMinOrder").val()) || 0);
  const expiresRaw = ($("#couponExpiresAt").val() || "").toString().trim();
  const title =
    ($("#couponTitle").val() || "").toString().trim() ||
    "Commerza exclusive offer";

  const discountLabel =
    discountType === "percent"
      ? `${discountValue.toFixed(2)}% OFF`
      : `${formatPkr(discountValue)} OFF`;

  let expiryLine = "Expires: No expiry";
  if (expiresRaw) {
    const parsed = new Date(expiresRaw);
    if (!Number.isNaN(parsed.getTime())) {
      expiryLine = `Expires: ${formatDateTime(parsed.toISOString())}`;
    }
  }

  const subject = `${title} - ${code}`;

  const message = [
    "Hi there,",
    "",
    `Use coupon code ${code} to get ${discountLabel}.`,
    `Minimum order: ${formatPkr(minOrder)}`,
    expiryLine,
    "",
    "You can also keep placeholders: {{code}}, {{discount}}, {{min_order}}, {{expires_at}}",
    "",
    "Thanks for shopping with Commerza.",
  ].join("\n");

  $("#couponEmailSubject").val(subject);
  $("#couponEmailMessage").val(message);
  showNotification("Email subject and template prefilled.", "success");
}

function couponFilteredCollection() {
  const query = couponSearchQuery.trim().toLowerCase();
  if (!query) {
    return adminCoupons;
  }

  return adminCoupons.filter((coupon) => {
    const haystack = [
      coupon.code || "",
      coupon.title || "",
      coupon.description || "",
      coupon.discountLabel || "",
    ]
      .join(" ")
      .toLowerCase();

    return haystack.includes(query);
  });
}

function resetCouponForm() {
  $("#couponId").val("");
  $("#couponCode").val("");
  $("#couponCodeSeed").val("");
  $("#couponTitle").val("");
  $("#couponDescription").val("");
  setAdminDropdownSelection("couponDiscountType", "fixed", "Fixed PKR");
  $("#couponDiscountValue").val("");
  $("#couponMinOrder").val("0");
  $("#couponMaxDiscount").val("").prop("disabled", true);
  $("#couponUsageLimit").val("");
  $("#couponPerUserLimit").val("");
  $("#couponExpiresAt").val("");
  $("#couponIsActive").prop("checked", true);
  $("#saveCouponBtn").html('<i class="bi bi-save2 me-1"></i>Save Coupon');
  updateCouponPreview();
}

function populateCouponEmailSelect() {
  const input = $("#couponEmailCouponId");
  const button = $("#couponEmailCouponIdBtn");
  const menu = $("#couponEmailCouponIdMenu");
  if (!input.length || !button.length || !menu.length) return;

  const selectedValue = (input.val() || "").toString();
  menu.empty();

  if (!adminCoupons.length) {
    input.val("");
    button.text("No coupons available");
    menu.append(
      '<li><span class="dropdown-item text-secondary">No coupons available</span></li>',
    );
    return;
  }

  menu.append(
    '<li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="couponEmailCouponId" data-value="" data-label="Select a coupon">Select a coupon</a></li>',
  );
  menu.append('<li><hr class="dropdown-divider border-secondary-subtle"></li>');

  adminCoupons.forEach((coupon) => {
    const code = escapeHtml(coupon.code || "");
    const label = escapeHtml(coupon.discountLabel || "Offer");
    const optionLabel = `${code} - ${label}`;
    menu.append(
      `<li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="couponEmailCouponId" data-value="${Number(coupon.id || 0)}" data-label="${optionLabel}">${optionLabel}</a></li>`,
    );
  });

  const matchingCoupon = adminCoupons.find(
    (coupon) => String(Number(coupon.id || 0)) === selectedValue,
  );

  if (matchingCoupon) {
    const matchLabel = `${(matchingCoupon.code || "").toString()} - ${(matchingCoupon.discountLabel || "Offer").toString()}`;
    setAdminDropdownSelection(
      "couponEmailCouponId",
      String(Number(matchingCoupon.id || 0)),
      matchLabel,
    );
    return;
  }

  setAdminDropdownSelection("couponEmailCouponId", "", "Select a coupon");
}

function renderCouponsTable() {
  const tbody = $("#couponsTable tbody");
  if (!tbody.length) return;

  tbody.empty();

  const filteredCoupons = couponFilteredCollection();

  const total = adminCoupons.length;
  const active = adminCoupons.filter(
    (coupon) => coupon.isActive && !coupon.isExpired,
  ).length;
  const used = adminCoupons.reduce(
    (sum, coupon) => sum + Math.max(0, parseInt(coupon.usedCount, 10) || 0),
    0,
  );

  $("#couponStatsTotal").text(total);
  $("#couponStatsActive").text(active);
  $("#couponStatsUsed").text(used);
  $("#couponStatsShowing").text(filteredCoupons.length);

  if (!adminCoupons.length) {
    tbody.append(
      '<tr><td colspan="6" class="text-center py-4 text-secondary">No coupons created yet.</td></tr>',
    );
    return;
  }

  if (!filteredCoupons.length) {
    tbody.append(
      '<tr><td colspan="6" class="text-center py-4 text-secondary">No coupons match your search.</td></tr>',
    );
    return;
  }

  filteredCoupons.forEach((coupon) => {
    const usageLimit = parseInt(coupon.usageLimit, 10) || 0;
    const perUserLimit = parseInt(coupon.perUserLimit, 10) || 0;
    const minOrder = Number(coupon.minOrder || 0);
    const maxDiscount = Number(coupon.maxDiscount || 0);
    const usedCount = parseInt(coupon.usedCount, 10) || 0;
    const isActive = !!coupon.isActive;
    const isExpired = !!coupon.isExpired;

    const statusText = !isActive
      ? "Inactive"
      : isExpired
        ? "Expired"
        : "Active";
    const statusClass = !isActive
      ? "bg-secondary"
      : isExpired
        ? "bg-danger"
        : "bg-success";

    const limits = [
      `Min: ${formatPkr(minOrder)}`,
      usageLimit > 0 ? `Limit: ${usageLimit}` : "Limit: Unlimited",
      perUserLimit > 0 ? `Per user: ${perUserLimit}` : "Per user: Unlimited",
      coupon.discountType === "percent" && maxDiscount > 0
        ? `Max discount: ${formatPkr(maxDiscount)}`
        : null,
    ]
      .filter(Boolean)
      .join("<br>");

    const expiryLabel = coupon.expiresAt
      ? formatDateTime(coupon.expiresAt)
      : "No expiry";

    tbody.append(`
      <tr class="border-bottom border-secondary">
        <td class="ps-4 py-3">
          <div class="text-light fw-semibold">${escapeHtml(coupon.code || "")}</div>
          <div class="text-secondary small">${escapeHtml(coupon.title || "Untitled coupon")}</div>
        </td>
        <td class="py-3 text-light">
          <div>${escapeHtml(coupon.discountLabel || "")}</div>
          <div class="text-secondary small">Used ${usedCount} time(s)</div>
        </td>
        <td class="py-3 text-secondary small">${limits}</td>
        <td class="py-3 text-secondary small">${escapeHtml(expiryLabel)}</td>
        <td class="py-3"><span class="badge ${statusClass}">${statusText}</span></td>
        <td class="pe-4 py-3">
          <div class="d-flex flex-wrap gap-1">
            <button class="btn btn-sm btn-outline-orange" onclick="editCouponById(${Number(coupon.id || 0)})"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm ${isActive ? "btn-outline-secondary" : "btn-outline-success"}" onclick="toggleCouponStatus(${Number(coupon.id || 0)}, ${isActive ? 0 : 1})">${isActive ? "Disable" : "Enable"}</button>
            <button class="btn btn-sm btn-outline-danger" onclick="deleteCouponById(${Number(coupon.id || 0)})"><i class="bi bi-trash"></i></button>
          </div>
        </td>
      </tr>
    `);
  });
}

function editCouponById(couponId) {
  const coupon = adminCoupons.find(
    (item) => Number(item.id) === Number(couponId),
  );
  if (!coupon) {
    showNotification("Coupon not found.", "warning");
    return;
  }

  $("#couponId").val(coupon.id || "");
  $("#couponCode").val(coupon.code || "");
  $("#couponTitle").val(coupon.title || "");
  $("#couponDescription").val(coupon.description || "");
  setAdminDropdownSelection(
    "couponDiscountType",
    coupon.discountType || "fixed",
    couponDiscountTypeLabel(coupon.discountType || "fixed"),
  );
  $("#couponDiscountValue").val(coupon.discountValue || "");
  $("#couponMinOrder").val(coupon.minOrder || 0);
  $("#couponMaxDiscount")
    .val(coupon.maxDiscount || "")
    .prop("disabled", (coupon.discountType || "fixed") !== "percent");
  $("#couponUsageLimit").val(coupon.usageLimit || "");
  $("#couponPerUserLimit").val(coupon.perUserLimit || "");
  $("#couponIsActive").prop("checked", !!coupon.isActive);

  if (coupon.expiresAt) {
    const parsed = new Date(coupon.expiresAt);
    if (!Number.isNaN(parsed.getTime())) {
      const localValue = new Date(
        parsed.getTime() - parsed.getTimezoneOffset() * 60000,
      )
        .toISOString()
        .slice(0, 16);
      $("#couponExpiresAt").val(localValue);
    }
  } else {
    $("#couponExpiresAt").val("");
  }

  $("#couponCodeSeed").val(coupon.title || coupon.code || "");
  $("#saveCouponBtn").html(
    '<i class="bi bi-pencil-square me-1"></i>Update Coupon',
  );
  updateCouponPreview();

  const codeInput = document.getElementById("couponCode");
  if (codeInput) {
    codeInput.scrollIntoView({ behavior: "smooth", block: "center" });
    window.setTimeout(() => codeInput.focus(), 120);
  }
}

async function loadCouponsData(silent = false) {
  if (!$("#couponsSection").length) {
    return false;
  }

  try {
    const response = await fetch(`${ADMIN_COUPONS_API}?action=list`, {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store",
    });

    const result = await response.json();
    if (!response.ok || !result?.ok) {
      throw new Error(result?.message || "Unable to load coupons.");
    }

    adminCoupons = Array.isArray(result?.payload?.coupons)
      ? result.payload.coupons
      : [];

    renderCouponsTable();
    populateCouponEmailSelect();
    return true;
  } catch (error) {
    if (!silent) {
      showNotification(error?.message || "Unable to load coupons.", "danger");
    }
    return false;
  }
}

async function saveCouponFromForm() {
  const id = parseInt($("#couponId").val(), 10) || 0;
  const code = couponNormalizeCodeInput($("#couponCode").val());
  const discountType = ($("#couponDiscountType").val() || "fixed").toString();
  const discountValue = parseFloat($("#couponDiscountValue").val()) || 0;

  if (!code || code.length < 3) {
    showNotification("Coupon code must be at least 3 characters.", "danger");
    return;
  }

  if (discountValue <= 0) {
    showNotification("Discount value must be greater than zero.", "danger");
    return;
  }

  const payload = {
    action: "save-coupon",
    id,
    code,
    title: ($("#couponTitle").val() || "").toString().trim(),
    description: ($("#couponDescription").val() || "").toString().trim(),
    discount_type: discountType,
    discount_value: discountValue,
    min_order: parseFloat($("#couponMinOrder").val()) || 0,
    max_discount:
      discountType === "percent"
        ? parseFloat($("#couponMaxDiscount").val()) || null
        : null,
    usage_limit: parseInt($("#couponUsageLimit").val(), 10) || null,
    per_user_limit: parseInt($("#couponPerUserLimit").val(), 10) || null,
    expires_at: ($("#couponExpiresAt").val() || "").toString().trim(),
    is_active: $("#couponIsActive").is(":checked") ? 1 : 0,
  };

  try {
    const result = await adminPostJson(ADMIN_COUPONS_API, payload);
    adminCoupons = Array.isArray(result?.payload?.coupons)
      ? result.payload.coupons
      : [];
    renderCouponsTable();
    populateCouponEmailSelect();
    resetCouponForm();
    updateCouponPreview();
    showNotification(
      result?.message || "Coupon saved successfully.",
      "success",
    );
  } catch (error) {
    showNotification(error?.message || "Unable to save coupon.", "danger");
  }
}

async function toggleCouponStatus(couponId, isActive) {
  const coupon = adminCoupons.find(
    (item) => Number(item.id) === Number(couponId),
  );
  if (!coupon) {
    showNotification("Coupon not found.", "warning");
    return;
  }

  const payload = {
    action: "save-coupon",
    id: coupon.id,
    code: coupon.code,
    title: coupon.title || "",
    description: coupon.description || "",
    discount_type: coupon.discountType || "fixed",
    discount_value: Number(coupon.discountValue || 0),
    min_order: Number(coupon.minOrder || 0),
    max_discount:
      coupon.discountType === "percent"
        ? Number(coupon.maxDiscount || 0) || null
        : null,
    usage_limit: parseInt(coupon.usageLimit, 10) || null,
    per_user_limit: parseInt(coupon.perUserLimit, 10) || null,
    expires_at: coupon.expiresAt || "",
    is_active: isActive ? 1 : 0,
  };

  try {
    const result = await adminPostJson(ADMIN_COUPONS_API, payload);
    adminCoupons = Array.isArray(result?.payload?.coupons)
      ? result.payload.coupons
      : [];
    renderCouponsTable();
    populateCouponEmailSelect();
    showNotification(result?.message || "Coupon status updated.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to update coupon status.",
      "danger",
    );
  }
}

async function deleteCouponById(couponId) {
  const coupon = adminCoupons.find(
    (item) => Number(item.id) === Number(couponId),
  );
  if (!coupon) {
    showNotification("Coupon not found.", "warning");
    return;
  }

  const confirmed = await showCustomConfirmDialog(
    `Delete coupon ${coupon.code}?`,
    "Delete Coupon",
  );
  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_COUPONS_API, {
      action: "delete-coupon",
      id: couponId,
    });
    adminCoupons = Array.isArray(result?.payload?.coupons)
      ? result.payload.coupons
      : [];
    renderCouponsTable();
    populateCouponEmailSelect();
    showNotification(result?.message || "Coupon deleted.", "success");
  } catch (error) {
    showNotification(error?.message || "Unable to delete coupon.", "danger");
  }
}

async function sendCouponEmail() {
  const couponId = parseInt($("#couponEmailCouponId").val(), 10) || 0;
  const recipientsRaw = ($("#couponEmailRecipients").val() || "").toString();
  const subject = ($("#couponEmailSubject").val() || "").toString().trim();
  const message = ($("#couponEmailMessage").val() || "").toString().trim();

  if (couponId <= 0) {
    showNotification("Select a coupon before sending email.", "danger");
    return;
  }

  if (!recipientsRaw.trim()) {
    showNotification("Add at least one recipient email.", "danger");
    return;
  }

  const recipients = recipientsRaw
    .split(/[\s,;]+/)
    .map((item) => item.trim())
    .filter(Boolean);

  if (!recipients.length) {
    showNotification("Add valid recipient email addresses.", "danger");
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_COUPONS_API, {
      action: "send-coupon-email",
      coupon_id: couponId,
      recipients,
      subject,
      message,
    });

    adminCoupons = Array.isArray(result?.payload?.coupons)
      ? result.payload.coupons
      : adminCoupons;
    renderCouponsTable();
    showNotification(result?.message || "Coupon email sent.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to send coupon email.",
      "danger",
    );
  }
}

function initCouponsSection() {
  if (!$("#couponsSection").length) {
    return;
  }

  $("#couponDiscountType")
    .off("change")
    .on("change", function () {
      const isPercent = $(this).val() === "percent";
      $("#couponMaxDiscount").prop("disabled", !isPercent);
      if (!isPercent) {
        $("#couponMaxDiscount").val("");
      }

      updateCouponPreview();
    })
    .trigger("change");

  const previewFields = [
    "#couponCode",
    "#couponTitle",
    "#couponDescription",
    "#couponDiscountValue",
    "#couponMinOrder",
    "#couponMaxDiscount",
    "#couponUsageLimit",
    "#couponPerUserLimit",
    "#couponExpiresAt",
    "#couponIsActive",
  ].join(",");

  $(previewFields)
    .off("input.couponPreview change.couponPreview")
    .on("input.couponPreview change.couponPreview", updateCouponPreview);

  $("#couponPresetQuickActions")
    .off("click.couponPreset", ".coupon-preset-btn")
    .on("click.couponPreset", ".coupon-preset-btn", function () {
      const presetKey = ($(this).data("couponPreset") || "").toString().trim();
      applyCouponPreset(presetKey);
    });

  $("#couponGenerateCodeBtn")
    .off("click")
    .on("click", generateCouponCodeFromSeed);
  $("#couponCopyCodeBtn").off("click").on("click", copyCouponCodeToClipboard);
  $("#couponPrefillEmailBtn")
    .off("click")
    .on("click", prefillCouponEmailTemplateFromForm);

  $("#couponTableSearch")
    .off("input")
    .on("input", function () {
      couponSearchQuery = ($(this).val() || "").toString().trim().toLowerCase();
      renderCouponsTable();
    });

  $("#saveCouponBtn").off("click").on("click", saveCouponFromForm);
  $("#resetCouponBtn").off("click").on("click", resetCouponForm);
  $("#refreshCouponsBtn")
    .off("click")
    .on("click", () => loadCouponsData(false));
  $("#sendCouponEmailBtn").off("click").on("click", sendCouponEmail);

  couponSearchQuery = ($("#couponTableSearch").val() || "")
    .toString()
    .trim()
    .toLowerCase();
  resetCouponForm();
  updateCouponPreview();
}

