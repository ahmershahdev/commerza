function renderReviewsStats(stats = {}) {
  $("#reviewStatTotal").text(parseInt(stats.total, 10) || 0);
  $("#reviewStatVisible").text(parseInt(stats.visible, 10) || 0);
  $("#reviewStatHidden").text(parseInt(stats.hidden, 10) || 0);
  $("#reviewStatAverage").text(Number(stats.averageRating || 0).toFixed(2));
  $("#reviewStatLocked").text(parseInt(stats.locked || 0, 10) || 0);
}

function renderAdminReviewImages(images) {
  const list = Array.isArray(images) ? images : [];
  if (!list.length) {
    return "";
  }

  const thumbs = list
    .slice(0, 2)
    .map((image) => {
      const path = (image?.path || "").toString().trim();
      if (!path) {
        return "";
      }

      const resolved = resolveAdminImagePath(path);
      const safePath = sanitizeAdminMediaUrl(resolved || `../../${path}`);
      if (!safePath) {
        return "";
      }

      return `<a href="${escapeHtml(safePath)}" target="_blank" rel="noopener" class="d-inline-block me-1 mt-2"><img src="${escapeHtml(safePath)}" alt="Review image" style="width:34px;height:34px;object-fit:cover;border-radius:6px;border:1px solid rgba(255,255,255,0.2);" onerror="this.style.display='none';"></a>`;
    })
    .join("");

  return thumbs
    ? `<div class="small text-info">Images: ${list.length}</div><div>${thumbs}</div>`
    : "";
}

function renderReviewsTable() {
  const tbody = $("#reviewsTable tbody");
  if (!tbody.length) return;

  tbody.empty();

  if (!adminReviews.length) {
    tbody.append(
      '<tr><td colspan="7" class="text-center py-4 text-secondary">No reviews found.</td></tr>',
    );
    return;
  }

  adminReviews.forEach((review) => {
    const statusBadge = review.isVisible
      ? '<span class="badge bg-success">Visible</span>'
      : '<span class="badge bg-secondary">Hidden</span>';
    const lockBadge = review.isLocked
      ? '<span class="badge bg-info text-dark">Locked</span>'
      : '<span class="badge bg-dark border border-secondary">Unlocked</span>';
    const rating = Math.max(1, Math.min(5, parseInt(review.rating, 10) || 0));
    const stars = `${"★".repeat(rating)}${"☆".repeat(5 - rating)}`;
    const text = (review.reviewText || "").toString();
    const clipped = text.length > 120 ? `${text.slice(0, 120)}...` : text;
    const imageMarkup = renderAdminReviewImages(review.images || []);
    const lockMeta = review.isLocked
      ? `<div class="text-info small">${escapeHtml(
          review.lockedAt
            ? `Locked: ${formatDateTime(review.lockedAt)}`
            : "Locked",
        )}</div>`
      : "";
    const reviewId = Number(review.id || 0);

    tbody.append(`
      <tr class="border-bottom border-secondary">
        <td class="ps-4 py-3 text-light fw-semibold">${escapeHtml(review.productName || "Product")}</td>
        <td class="py-3 text-light">
          <div>${escapeHtml(review.userName || "Customer")}</div>
          <div class="text-secondary small">${escapeHtml(review.userEmail || "")}</div>
        </td>
        <td class="py-3 text-warning">${stars}</td>
        <td class="py-3 text-secondary small" title="${escapeHtml(text)}">${escapeHtml(clipped)}${imageMarkup}</td>
        <td class="py-3">
          <div class="d-flex flex-column gap-1">${statusBadge}${lockBadge}</div>
        </td>
        <td class="py-3 text-secondary small">${escapeHtml(formatDateTime(review.updatedAt))}${lockMeta}</td>
        <td class="pe-4 py-3">
          <div class="d-flex flex-wrap gap-1">
            <button class="btn btn-sm btn-outline-orange" onclick="editReviewById(${reviewId})" ${review.isLocked ? "disabled" : ""}>Edit</button>
            <button class="btn btn-sm ${review.isVisible ? "btn-outline-secondary" : "btn-outline-success"}" onclick="toggleReviewVisibilityById(${reviewId}, ${review.isVisible ? 0 : 1})">${review.isVisible ? "Hide" : "Show"}</button>
            <button class="btn btn-sm ${review.isLocked ? "btn-outline-warning" : "btn-outline-info"}" onclick="setReviewLockById(${reviewId}, ${review.isLocked ? 0 : 1})">${review.isLocked ? "Unlock" : "Lock"}</button>
            <button class="btn btn-sm btn-outline-danger" onclick="deleteReviewById(${reviewId})"><i class="bi bi-trash"></i></button>
          </div>
        </td>
      </tr>
    `);
  });
}

async function loadReviewsData(silent = false) {
  if (!$("#reviewsSection").length) {
    return false;
  }

  const visibility = ($("#reviewVisibilityFilter").val() || "all").toString();

  try {
    const response = await fetch(
      `${ADMIN_REVIEWS_API}?action=list&visibility=${encodeURIComponent(visibility)}`,
      {
        method: "GET",
        credentials: "same-origin",
        cache: "no-store",
      },
    );

    const result = await response.json();
    if (!response.ok || !result?.ok) {
      throw new Error(result?.message || "Unable to load reviews.");
    }

    adminReviews = Array.isArray(result?.payload?.reviews)
      ? result.payload.reviews
      : [];
    renderReviewsStats(result?.payload?.stats || {});
    renderReviewsTable();
    return true;
  } catch (error) {
    if (!silent) {
      showNotification(error?.message || "Unable to load reviews.", "danger");
    }
    return false;
  }
}

async function toggleReviewVisibilityById(reviewId, isVisible) {
  try {
    const result = await adminPostJson(ADMIN_REVIEWS_API, {
      action: "set-visibility",
      id: reviewId,
      is_visible: isVisible ? 1 : 0,
    });

    adminReviews = Array.isArray(result?.payload?.reviews)
      ? result.payload.reviews
      : adminReviews;
    renderReviewsStats(result?.payload?.stats || {});
    renderReviewsTable();
    showNotification(
      result?.message || "Review visibility updated.",
      "success",
    );
  } catch (error) {
    showNotification(
      error?.message || "Unable to update review visibility.",
      "danger",
    );
  }
}

async function setReviewLockById(reviewId, isLocked) {
  const safeReviewId = parseInt(reviewId, 10) || 0;
  if (safeReviewId <= 0) {
    showNotification("Invalid review id.", "warning");
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_REVIEWS_API, {
      action: "set-lock",
      id: safeReviewId,
      is_locked: isLocked ? 1 : 0,
    });

    adminReviews = Array.isArray(result?.payload?.reviews)
      ? result.payload.reviews
      : adminReviews;
    renderReviewsStats(result?.payload?.stats || {});
    renderReviewsTable();
    showNotification(
      result?.message || "Review lock state updated.",
      "success",
    );
  } catch (error) {
    showNotification(
      error?.message || "Unable to update review lock state.",
      "danger",
    );
  }
}

async function editReviewById(reviewId) {
  const review = adminReviews.find(
    (item) => Number(item.id) === Number(reviewId),
  );
  if (!review) {
    showNotification("Review not found.", "warning");
    return;
  }

  if (review.isLocked) {
    showNotification("Unlock this review before editing it.", "warning");
    return;
  }

  const ratingInput = await showCustomPromptDialog(
    "Update rating (1-5):",
    String(review.rating || 5),
    "Edit Review Rating",
  );
  if (ratingInput === null) {
    return;
  }

  const parsedRating = parseInt((ratingInput || "").toString().trim(), 10);
  if (!Number.isInteger(parsedRating) || parsedRating < 1 || parsedRating > 5) {
    showNotification("Rating must be between 1 and 5.", "danger");
    return;
  }

  const textInput = await showCustomPromptDialog(
    "Update review text:",
    review.reviewText || "",
    "Edit Review Text",
  );
  if (textInput === null) {
    return;
  }

  const reviewText = (textInput || "").toString().trim();
  if (reviewText.length < 10 || reviewText.length > 500) {
    showNotification("Review text must be 10 to 500 characters.", "danger");
    return;
  }

  const noteInput = await showCustomPromptDialog(
    "Optional admin note:",
    review.adminNote || "",
    "Admin Note",
  );

  const adminNote = noteInput === null ? review.adminNote || "" : noteInput;

  try {
    const result = await adminPostJson(ADMIN_REVIEWS_API, {
      action: "update-review",
      id: reviewId,
      rating: parsedRating,
      review_text: reviewText,
      admin_note: adminNote,
    });

    adminReviews = Array.isArray(result?.payload?.reviews)
      ? result.payload.reviews
      : adminReviews;
    renderReviewsStats(result?.payload?.stats || {});
    renderReviewsTable();
    showNotification(result?.message || "Review updated.", "success");
  } catch (error) {
    showNotification(error?.message || "Unable to update review.", "danger");
  }
}

async function deleteReviewById(reviewId) {
  const review = adminReviews.find(
    (item) => Number(item.id) === Number(reviewId),
  );
  if (!review) {
    showNotification("Review not found.", "warning");
    return;
  }

  const confirmed = await showCustomConfirmDialog(
    "Delete this review permanently?",
    "Delete Review",
  );
  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_REVIEWS_API, {
      action: "delete-review",
      id: reviewId,
    });

    adminReviews = Array.isArray(result?.payload?.reviews)
      ? result.payload.reviews
      : adminReviews;
    renderReviewsStats(result?.payload?.stats || {});
    renderReviewsTable();
    showNotification(result?.message || "Review deleted.", "success");
  } catch (error) {
    showNotification(error?.message || "Unable to delete review.", "danger");
  }
}

function addReviewByAdmin() {
  focusReviewQuickForm();
}

async function addFakeBulkReviewsByAdmin(forcedCount = null) {
  const inputProductId = parseAdminProductIdInput(
    $("#fakeReviewProductId").val(),
  );
  let productId = inputProductId;

  if (productId <= 0) {
    const productIdPrompt = await showCustomPromptDialog(
      "Product ID for fake review generation:",
      "",
      "Fake Review Product",
    );

    if (productIdPrompt === null) {
      return;
    }

    productId = parseAdminProductIdInput(productIdPrompt);
  }

  if (productId <= 0) {
    showNotification("Enter a valid Product ID.", "warning");
    return;
  }

  const configuredCount = parseInt($("#fakeReviewCount").val(), 10) || 1;
  const count = Math.max(
    1,
    Math.min(
      100,
      Number.isInteger(forcedCount) ? forcedCount : configuredCount,
    ),
  );
  const ratingMin = Math.max(
    1,
    Math.min(5, parseInt($("#fakeReviewRatingMin").val(), 10) || 3),
  );
  const ratingMax = Math.max(
    1,
    Math.min(5, parseInt($("#fakeReviewRatingMax").val(), 10) || 5),
  );
  const isVisible =
    parseInt($("#fakeReviewVisibility").val(), 10) === 0 ? 0 : 1;

  try {
    const result = await adminPostJson(ADMIN_REVIEWS_API, {
      action: "add-fake-bulk-reviews",
      product_id: productId,
      count,
      rating_min: Math.min(ratingMin, ratingMax),
      rating_max: Math.max(ratingMin, ratingMax),
      is_visible: isVisible,
    });

    adminReviews = Array.isArray(result?.payload?.reviews)
      ? result.payload.reviews
      : adminReviews;
    renderReviewsStats(result?.payload?.stats || {});
    renderReviewsTable();
    showNotification(result?.message || "Fake reviews generated.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to generate fake reviews.",
      "danger",
    );
  }
}

function initReviewsSection() {
  if (!$("#reviewsSection").length) {
    return;
  }

  renderReviewProductSuggestions();
  resetReviewQuickAddForm();

  setAdminDropdownSelection(
    "reviewVisibilityFilter",
    $("#reviewVisibilityFilter").val() || "all",
    reviewVisibilityLabel($("#reviewVisibilityFilter").val() || "all"),
  );
  setAdminDropdownSelection(
    "fakeReviewVisibility",
    $("#fakeReviewVisibility").val() || "1",
    fakeReviewVisibilityLabel($("#fakeReviewVisibility").val() || "1"),
  );

  $("#reviewVisibilityFilter")
    .off("change")
    .on("change", function () {
      loadReviewsData(false);
    });

  $("#refreshReviewsBtn")
    .off("click")
    .on("click", function () {
      loadReviewsData(false);
    });

  $("#addReviewBtn")
    .off("click")
    .on("click", function () {
      addReviewByAdmin();
    });

  $("#submitAddReviewBtn")
    .off("click")
    .on("click", function () {
      submitReviewQuickAddForm();
    });

  $("#clearAddReviewBtn")
    .off("click")
    .on("click", function () {
      resetReviewQuickAddForm();
    });

  $("#addFakeReviewBtn")
    .off("click")
    .on("click", function () {
      addFakeBulkReviewsByAdmin(1);
    });

  $("#addFakeBulkReviewsBtn")
    .off("click")
    .on("click", function () {
      addFakeBulkReviewsByAdmin();
    });

  $("#addSingleFakeReviewBtn")
    .off("click")
    .on("click", function () {
      addFakeBulkReviewsByAdmin(1);
    });
}

function securitySeverityBadgeClass(severity) {
  const normalized = (severity || "").toString().toLowerCase();
  if (normalized === "critical") {
    return "security-severity-pill security-severity-critical";
  }
  if (normalized === "warning") {
    return "security-severity-pill security-severity-warning";
  }
  return "security-severity-pill security-severity-info";
}

function securityEventRowClass(severity) {
  const normalized = (severity || "").toString().toLowerCase();
  if (normalized === "critical") return "security-event-critical";
  if (normalized === "warning") return "security-event-warning";
  return "security-event-info";
}

function humanizeSecurityText(value) {
  const raw = (value || "").toString().trim();
  if (raw === "") {
    return "Unknown";
  }

  return raw
    .replace(/[._-]+/g, " ")
    .replace(/\s+/g, " ")
    .trim()
    .replace(/\b\w/g, (ch) => ch.toUpperCase());
}

function securityEventTypeLabel(eventType) {
  const normalized = (eventType || "").toString().trim().toLowerCase();
  if (normalized === "login_failed") {
    return "Login Failed";
  }
  if (normalized === "login_success") {
    return "Login Successful";
  }
  if (normalized === "suspicious_login_ip_change") {
    return "Suspicious Login (IP Changed)";
  }
  if (normalized === "rate_limit_block") {
    return "Rate Limit Blocked";
  }

  return humanizeSecurityText(eventType);
}

function securityActorDisplayLabel(actorType) {
  const normalized = (actorType || "").toString().trim().toLowerCase();
  if (normalized === "admin") {
    return "Admin";
  }
  if (normalized === "user") {
    return "Customer";
  }
  return "System";
}

function securityIdentifierDisplay(actorIdentifier) {
  const value = (actorIdentifier || "").toString().trim();
  const lowered = value.toLowerCase();

  if (value === "" || value === "-" || lowered === "anonymous") {
    return {
      label: "Account",
      value: "Unknown account",
    };
  }

  if (value.includes("@")) {
    return {
      label: "Account",
      value,
    };
  }

  if (/^\d{11,15}$/.test(value)) {
    return {
      label: "Phone",
      value,
    };
  }

  return {
    label: "Identifier",
    value,
  };
}

function securityIpDisplay(ipAddress) {
  const ip = (ipAddress || "").toString().trim();
  if (ip === "" || ip === "-" || ip === "0.0.0.0") {
    return {
      label: "Location",
      value: "IP unavailable",
    };
  }

  if (ip === "127.0.0.1" || ip === "::1") {
    return {
      label: "Location",
      value: "Localhost",
    };
  }

  return {
    label: "IP Address",
    value: ip,
  };
}

function securityDetailValueText(value) {
  if (value === null || typeof value === "undefined") {
    return "-";
  }

  if (typeof value === "boolean") {
    return value ? "Yes" : "No";
  }

  if (typeof value === "number") {
    return Number.isFinite(value) ? value.toString() : "-";
  }

  if (Array.isArray(value)) {
    const preview = value
      .slice(0, 3)
      .map((entry) => securityDetailValueText(entry))
      .filter((entry) => entry !== "-")
      .join(", ");
    if (preview !== "") {
      return preview;
    }
    return value.length ? "Items available" : "-";
  }

  if (typeof value === "object") {
    return "Additional data available";
  }

  const text = value.toString().trim();
  if (text === "") {
    return "-";
  }

  return humanizeSecurityText(text);
}

function securityEventDetailsSummary(eventItem) {
  const details = eventItem?.details;
  if (!details || typeof details !== "object") {
    const preview = (eventItem?.details_preview || "").toString().trim();
    return preview !== "" && preview !== "-"
      ? preview
      : "No additional details";
  }

  const currentIp = securityDetailValueText(details.current_ip);
  const previousIp = securityDetailValueText(details.previous_ip);
  if (currentIp !== "-" && previousIp !== "-") {
    return `IP changed from ${previousIp} to ${currentIp}`;
  }

  const summaryParts = [];
  const entries = Object.entries(details);
  for (let index = 0; index < entries.length; index += 1) {
    const [key, rawValue] = entries[index];
    const valueText = securityDetailValueText(rawValue);
    if (valueText === "-") {
      continue;
    }

    const normalizedKey = (key || "").toString().trim().toLowerCase();
    if (normalizedKey === "retry_after_seconds") {
      summaryParts.push(`Retry after ${valueText} seconds`);
      continue;
    }

    if (normalizedKey === "blacklist_id") {
      summaryParts.push(`Blacklist entry #${valueText}`);
      continue;
    }

    if (normalizedKey === "affected_rows") {
      summaryParts.push(`Affected records: ${valueText}`);
      continue;
    }

    const label = humanizeSecurityText(key);
    summaryParts.push(`${label}: ${valueText}`);

    if (summaryParts.length >= 3) {
      break;
    }
  }

  if (!summaryParts.length) {
    return "No additional details";
  }

  return summaryParts.join(" | ");
}

function renderSecurityEventsTable() {
  const tbody = $("#securityEventsTable tbody");
  if (!tbody.length) {
    return;
  }

  tbody.empty();

  const events = Array.isArray(securityEventsState.events)
    ? securityEventsState.events
    : [];

  if (!events.length) {
    tbody.append(
      '<tr><td colspan="6" class="text-center py-4 text-secondary">No security events found for these filters.</td></tr>',
    );
    return;
  }

  events.forEach((eventItem) => {
    const eventType = (eventItem?.event_type || "unknown_event").toString();
    const eventTypeLabel = securityEventTypeLabel(eventType);
    const severity = (eventItem?.severity || "info").toString();
    const severityLabel = securitySeverityLabel(severity);
    const actorType = (eventItem?.actor_type || "-").toString();
    const actorIdentifier = (eventItem?.actor_identifier || "-").toString();
    const ipAddress = (eventItem?.ip_address || "-").toString();
    const actorLabel = securityActorDisplayLabel(actorType);
    const identifierDisplay = securityIdentifierDisplay(actorIdentifier);
    const ipDisplay = securityIpDisplay(ipAddress);
    const detailsPreview = securityEventDetailsSummary(eventItem);
    const createdAt = formatDateTime(eventItem?.created_at || "");

    const detailsTitle =
      eventItem?.details && typeof eventItem.details === "object"
        ? escapeHtml(JSON.stringify(eventItem.details))
        : "";

    tbody.append(`
      <tr class="border-bottom border-secondary security-event-row ${securityEventRowClass(severity)}">
        <td class="ps-4 py-3 text-secondary small">${escapeHtml(createdAt)}</td>
        <td class="py-3 text-light fw-semibold"><div class="security-event-label">${escapeHtml(eventTypeLabel)}</div><div class="security-event-code">${escapeHtml(eventType)}</div></td>
        <td class="py-3"><span class="badge ${securitySeverityBadgeClass(severity)} rounded-pill">${escapeHtml(severityLabel)}</span></td>
        <td class="py-3 text-secondary small">${escapeHtml(actorLabel)}</td>
        <td class="py-3 text-secondary small"><div class="security-event-identifier-label">${escapeHtml(identifierDisplay.label)}</div><div class="security-event-identifier">${escapeHtml(identifierDisplay.value)}</div><div class="security-event-ip-label">${escapeHtml(ipDisplay.label)}</div><div class="security-event-ip">${escapeHtml(ipDisplay.value)}</div></td>
        <td class="pe-4 py-3 text-secondary small security-event-details" title="${detailsTitle}">${escapeHtml(detailsPreview)}</td>
      </tr>
    `);
  });
}

function renderSecurityEventsMeta() {
  const meta = $("#securityEventsMeta");
  if (!meta.length) {
    return;
  }

  const page = Math.max(1, parseInt(securityEventsState.page, 10) || 1);
  const totalPages = Math.max(
    1,
    parseInt(securityEventsState.totalPages, 10) || 1,
  );
  const total = Math.max(0, parseInt(securityEventsState.total, 10) || 0);

  meta.text(
    `Page ${page} of ${totalPages} · ${total.toLocaleString()} event(s)`,
  );

  $("#securityEventsPrevBtn").prop("disabled", page <= 1);
  $("#securityEventsNextBtn").prop("disabled", page >= totalPages);
}

function securityEventsFilterPayload() {
  return {
    eventType: ($("#securityEventTypeFilter").val() || "").toString().trim(),
    severity: ($("#securitySeverityFilter").val() || "").toString().trim(),
    actorType: ($("#securityActorTypeFilter").val() || "").toString().trim(),
    search: ($("#securityEventSearchFilter").val() || "").toString().trim(),
    from: ($("#securityFromFilter").val() || "").toString().trim(),
    to: ($("#securityToFilter").val() || "").toString().trim(),
  };
}

async function loadSecurityEvents(silent = false) {
  if (!$("#securityEventsSection").length) {
    return false;
  }

  const filters = securityEventsState.filters || {};
  const page = Math.max(1, parseInt(securityEventsState.page, 10) || 1);
  const perPage = Math.max(5, parseInt(securityEventsState.perPage, 10) || 25);

  const params = new URLSearchParams();
  params.set("action", "list-events");
  params.set("page", String(page));
  params.set("per_page", String(Math.min(perPage, 100)));

  if (filters.eventType) params.set("event_type", filters.eventType);
  if (filters.severity) params.set("severity", filters.severity);
  if (filters.actorType) params.set("actor_type", filters.actorType);
  if (filters.search) params.set("search", filters.search);
  if (filters.from) params.set("from", filters.from);
  if (filters.to) params.set("to", filters.to);

  try {
    const response = await fetch(`${ADMIN_SECURITY_API}?${params.toString()}`, {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store",
    });

    const result = await response.json();
    if (!response.ok || !result?.ok) {
      throw new Error(result?.message || "Unable to load security events.");
    }

    const payload = result?.payload || {};
    const pagination = payload?.pagination || {};

    securityEventsState.events = Array.isArray(payload?.events)
      ? payload.events
      : [];
    securityEventsState.page = Math.max(
      1,
      parseInt(pagination?.page, 10) || securityEventsState.page || 1,
    );
    securityEventsState.perPage = Math.max(
      5,
      parseInt(pagination?.per_page, 10) || securityEventsState.perPage || 25,
    );
    securityEventsState.total = Math.max(
      0,
      parseInt(pagination?.total, 10) || 0,
    );
    securityEventsState.totalPages = Math.max(
      1,
      parseInt(pagination?.total_pages, 10) || 1,
    );

    renderSecurityEventsTable();
    renderSecurityEventsMeta();
    return true;
  } catch (error) {
    if (!silent) {
      showNotification(
        error?.message || "Unable to load security events.",
        "danger",
      );
    }
    return false;
  }
}

function applySecurityEventsFilters(resetPage = true) {
  securityEventsState.filters = securityEventsFilterPayload();
  if (resetPage) {
    securityEventsState.page = 1;
  }
  loadSecurityEvents(false);
}

function clearSecurityEventsFilters() {
  $("#securityEventTypeFilter").val("");
  setAdminDropdownSelection("securitySeverityFilter", "", "All");
  setAdminDropdownSelection("securityActorTypeFilter", "", "All");
  $("#securityEventSearchFilter").val("");
  $("#securityFromFilter").val("");
  $("#securityToFilter").val("");

  securityEventsState.filters = {
    eventType: "",
    severity: "",
    actorType: "",
    search: "",
    from: "",
    to: "",
  };
  securityEventsState.page = 1;
  loadSecurityEvents(false);
}

