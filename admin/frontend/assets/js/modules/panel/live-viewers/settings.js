function setLiveViewersModeUi(mode, storageReady = true) {
  const normalized = mode === "fake" ? "fake" : "real";
  $("#liveViewersMode").val(normalized);
  const modeLabel =
    normalized === "fake" ? "Fake (marketing demo)" : "Real (active sessions)";
  $("#liveViewersModeBtn").text(modeLabel);
  $("#liveViewersModeMenu .dropdown-item").removeClass("active");
  $("#liveViewersModeMenu .dropdown-item").each(function () {
    if (($(this).data("mode") || "").toString() === normalized) {
      $(this).addClass("active");
    }
  });
  $("#liveViewerFakeConfig").toggleClass("d-none", normalized !== "fake");
  const statusLabel = storageReady
    ? `Mode: ${normalized}`
    : `Mode: ${normalized} | DB setup pending`;
  $("#liveViewersModeBadge").text(statusLabel);
}

function renderLiveViewersTopProducts(products, storageReady = true) {
  const tbody = $("#liveViewersTopProducts");
  if (!tbody.length) {
    return;
  }

  tbody.empty();

  if (!Array.isArray(products) || products.length === 0) {
    const message = storageReady
      ? "No live viewer data yet."
      : "Viewer storage table missing. Run SQL migration.";
    tbody.append(`
      <tr>
        <td class="ps-3 py-3 text-secondary" colspan="2">${message}</td>
      </tr>
    `);
    return;
  }

  products.forEach((item) => {
    const productName = (item?.name || "Product").toString();
    const safeProductName = escapeHtml(productName);
    const viewers = Math.max(0, parseInt(item?.viewers, 10) || 0);
    tbody.append(`
      <tr class="border-bottom border-secondary">
        <td class="ps-3 py-3 text-light fw-semibold">${safeProductName}</td>
        <td class="pe-3 py-3 text-end text-orange fw-semibold">${viewers}</td>
      </tr>
    `);
  });
}

function renderLiveViewersPayload(payload) {
  const settings = payload?.settings || {};
  const stats = payload?.stats || {};
  const storageReady = payload?.storage_ready !== false;

  const mode = settings?.mode === "fake" ? "fake" : "real";
  const fakeMin = Math.max(1, parseInt(settings?.fake_min, 10) || 120);
  const fakeMax = Math.max(1, parseInt(settings?.fake_max, 10) || 165);
  const windowSeconds = Math.max(
    30,
    Math.min(3600, parseInt(settings?.window_seconds, 10) || 180),
  );

  setLiveViewersModeUi(mode, storageReady);
  $("#liveViewersFakeMin").val(fakeMin);
  $("#liveViewersFakeMax").val(fakeMax);
  $("#liveViewersWindow").val(windowSeconds);

  $("#liveViewersActiveNow").text(
    Math.max(0, parseInt(stats?.active_now, 10) || 0),
  );
  $("#liveViewersTrackedProducts").text(
    Math.max(0, parseInt(stats?.tracked_products, 10) || 0),
  );

  renderLiveViewersTopProducts(stats?.top_products || [], storageReady);
}

async function loadLiveViewersAnalytics(silent = false) {
  if (!$("#liveViewersMode").length) {
    return false;
  }

  try {
    const response = await fetch(`${ADMIN_VIEWERS_API}?action=get`, {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store",
    });

    const result = await response.json();
    if (!response.ok || !result?.ok) {
      throw new Error(result?.message || "Unable to load live viewers data.");
    }

    renderLiveViewersPayload(result?.payload || {});
    return true;
  } catch (error) {
    if (!silent) {
      showNotification(
        error?.message || "Unable to load live viewers data.",
        "danger",
      );
    }
    return false;
  }
}

async function saveLiveViewersSettings() {
  const mode = $("#liveViewersMode").val() === "fake" ? "fake" : "real";
  let fakeMin = parseInt($("#liveViewersFakeMin").val(), 10) || 120;
  let fakeMax = parseInt($("#liveViewersFakeMax").val(), 10) || 165;
  const windowSeconds = parseInt($("#liveViewersWindow").val(), 10) || 180;

  fakeMin = Math.max(1, Math.min(5000, fakeMin));
  fakeMax = Math.max(1, Math.min(5000, fakeMax));
  const safeWindow = Math.max(30, Math.min(3600, windowSeconds));

  if (fakeMax < fakeMin) {
    const swap = fakeMin;
    fakeMin = fakeMax;
    fakeMax = swap;
  }

  try {
    const result = await adminPostJson(ADMIN_VIEWERS_API, {
      action: "save",
      mode,
      fake_min: fakeMin,
      fake_max: fakeMax,
      window_seconds: safeWindow,
    });

    renderLiveViewersPayload(result?.payload || {});
    showNotification(result?.message || "Viewer settings updated.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to save viewer settings.",
      "danger",
    );
  }
}

function initLiveViewersAnalytics() {
  if (!$("#liveViewersMode").length) {
    return;
  }

  setLiveViewersModeUi($("#liveViewersMode").val());

  $("#liveViewersModeMenu")
    .off("click", ".dropdown-item")
    .on("click", ".dropdown-item", function (event) {
      event.preventDefault();
      setLiveViewersModeUi(($(this).data("mode") || "").toString());
    });

  $("#saveLiveViewersBtn").off("click").on("click", saveLiveViewersSettings);

  $("#refreshLiveViewersBtn")
    .off("click")
    .on("click", function () {
      loadLiveViewersAnalytics(false);
    });

  loadLiveViewersAnalytics(true);
}

