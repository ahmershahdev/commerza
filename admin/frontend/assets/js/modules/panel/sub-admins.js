(function () {
  const runtime = window.CommerzaAdminRuntime || {};
  const SUB_ADMINS_API = "../backend/sub_admins_api.php";

  const state = {
    rows: [],
    roles: [],
    permissions: Array.isArray(runtime.permissionCatalog)
      ? runtime.permissionCatalog
      : [],
    tabs: Array.isArray(runtime.tabCatalog) ? runtime.tabCatalog : [],
    loaded: false,
  };

  function normalizePermission(permission) {
    const normalized = (permission || "").toString().trim().toLowerCase();
    if (normalized === "") {
      return "";
    }

    if (normalized === "*") {
      return "*";
    }

    return /^[a-z0-9_]+\.[a-z0-9_*]+$/.test(normalized) ? normalized : "";
  }

  function hasSubAdminPermission() {
    if (typeof window.commerzaAdminHasPermission === "function") {
      return !!window.commerzaAdminHasPermission("sub_admins.manage");
    }

    const source = Array.isArray(runtime?.admin?.permissions)
      ? runtime.admin.permissions
      : Array.isArray(runtime?.permissions)
        ? runtime.permissions
        : [];

    const set = new Set(
      source
        .map((value) => normalizePermission(value))
        .filter((value) => value !== ""),
    );

    if (
      set.has("*") ||
      set.has("sub_admins.manage") ||
      set.has("sub_admins.*")
    ) {
      return true;
    }

    return false;
  }

  function subAdminTabHidden() {
    const hiddenTabs = Array.isArray(runtime?.admin?.hiddenTabs)
      ? runtime.admin.hiddenTabs
      : Array.isArray(runtime?.hiddenTabs)
        ? runtime.hiddenTabs
        : [];

    return hiddenTabs
      .map((tabId) => (tabId || "").toString().trim().toLowerCase())
      .includes("sub-admins-tab");
  }

  function escapeHtml(value) {
    return (value || "")
      .toString()
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function formatDateTimeLocal(value) {
    if (!value) {
      return "-";
    }

    if (typeof window.formatDateTime === "function") {
      return window.formatDateTime(value);
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return "-";
    }

    return date.toLocaleString();
  }

  function notify(message, type) {
    if (typeof window.showNotification === "function") {
      window.showNotification(message, type || "info");
      return;
    }

    window.alert(message);
  }

  async function askConfirm(message, title) {
    if (typeof window.showCustomConfirmDialog === "function") {
      return !!(await window.showCustomConfirmDialog(
        message,
        title || "Confirm",
      ));
    }

    return window.confirm(message);
  }

  async function askPrompt(message, initialValue, title) {
    if (typeof window.showCustomPromptDialog === "function") {
      return await window.showCustomPromptDialog(
        message,
        initialValue || "",
        title || "Input",
      );
    }

    return window.prompt(message, initialValue || "");
  }

  function fallbackPostJson(payload) {
    const action = (payload?.action || "post").toString().trim().toLowerCase();
    const requestId = `admin-${action}-${Date.now().toString(36)}-${Math.random().toString(16).slice(2, 14)}`;
    return fetch(SUB_ADMINS_API, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": runtime.csrfToken || "",
        "X-Request-ID": requestId,
      },
      body: JSON.stringify(payload || {}),
    }).then(async (response) => {
      const result = await response.json().catch(() => null);
      if (!response.ok || !result?.ok) {
        throw new Error(result?.message || "Request failed.");
      }
      return result;
    });
  }

  function apiPost(payload) {
    if (typeof window.adminPostJson === "function") {
      return window.adminPostJson(SUB_ADMINS_API, payload);
    }

    return fallbackPostJson(payload);
  }

  async function loadList() {
    const response = await fetch(`${SUB_ADMINS_API}?action=list`, {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store",
    });

    const result = await response.json().catch(() => null);
    if (!response.ok || !result?.ok) {
      throw new Error(result?.message || "Unable to load sub admins.");
    }

    return result?.payload || {};
  }

  function activeRoleValue() {
    return ($("#subAdminRole").val() || "operations_manager").toString().trim();
  }

  function findRoleMeta(role) {
    const normalized = (role || "").toString().trim().toLowerCase();
    const list = Array.isArray(state.roles) ? state.roles : [];
    return (
      list.find(
        (entry) => (entry?.key || "").toString().toLowerCase() === normalized,
      ) || null
    );
  }

  function selectedPermissionKeys() {
    const keys = [];
    $("#subAdminPermissionGrid .sub-admin-permission-check:checked").each(
      function () {
        const key = ($(this).val() || "").toString().trim();
        if (key !== "") {
          keys.push(key);
        }
      },
    );

    return Array.from(new Set(keys));
  }

  function selectedHiddenTabs() {
    const tabs = [];
    $("#subAdminHiddenTabsGrid .sub-admin-tab-check:checked").each(function () {
      const tabId = ($(this).val() || "").toString().trim();
      if (tabId !== "") {
        tabs.push(tabId);
      }
    });

    return Array.from(new Set(tabs));
  }

  function normalizedKeyList(values) {
    return Array.from(
      new Set(
        (Array.isArray(values) ? values : [])
          .map((value) => (value || "").toString().trim().toLowerCase())
          .filter((value) => value !== ""),
      ),
    ).sort();
  }

  function roleDefaultPermissionKeys(role) {
    const roleMeta = findRoleMeta(role);
    return normalizedKeyList(roleMeta?.defaultPermissions || []).filter(
      (key) => key !== "*",
    );
  }

  function permissionSetHas(permissionSet, permission) {
    const normalized = (permission || "").toString().trim().toLowerCase();
    if (normalized === "") {
      return true;
    }

    if (permissionSet.has("*") || permissionSet.has(normalized)) {
      return true;
    }

    const segments = normalized.split(".", 2);
    if (segments.length !== 2) {
      return false;
    }

    const prefix = (segments[0] || "").toString().trim();
    const scope = (segments[1] || "").toString().trim();

    if (prefix === "" || scope === "") {
      return false;
    }

    if (permissionSet.has(`${prefix}.*`)) {
      return true;
    }

    if (scope === "view" && permissionSet.has(`${prefix}.manage`)) {
      return true;
    }

    return false;
  }

  function roleMatchesPermissionDefaults(role, permissions) {
    const normalizedRole = (role || "").toString().trim().toLowerCase();
    if (normalizedRole === "custom") {
      return true;
    }

    const expected = roleDefaultPermissionKeys(normalizedRole);
    const selected = normalizedKeyList(permissions || []).filter(
      (key) => key !== "*",
    );

    if (expected.length !== selected.length) {
      return false;
    }

    for (let index = 0; index < expected.length; index += 1) {
      if (expected[index] !== selected[index]) {
        return false;
      }
    }

    return true;
  }

  function tabMetaById(tabId) {
    const normalized = (tabId || "").toString().trim().toLowerCase();
    const tabs = Array.isArray(state.tabs) ? state.tabs : [];

    return (
      tabs.find(
        (tab) => (tab?.id || "").toString().trim().toLowerCase() === normalized,
      ) || null
    );
  }

  function resolveHiddenTabsAgainstPermissions(tabIds, permissionKeys) {
    const hiddenTabs = normalizedKeyList(tabIds || []);
    const permissionSet = new Set(normalizedKeyList(permissionKeys || []));
    const allowed = [];
    const blockedLabels = [];

    hiddenTabs.forEach((tabId) => {
      const meta = tabMetaById(tabId);
      const requirements = normalizedKeyList(meta?.permissions || []);

      const isGranted = requirements.some((permission) =>
        permissionSetHas(permissionSet, permission),
      );

      if (isGranted) {
        blockedLabels.push((meta?.label || tabId).toString());
        return;
      }

      allowed.push(tabId);
    });

    return {
      allowed,
      blockedLabels: Array.from(new Set(blockedLabels)),
    };
  }

  function enforceHiddenTabPermissionConstraints(showNotice = true) {
    const currentHiddenTabs = selectedHiddenTabs();
    if (!currentHiddenTabs.length) {
      return false;
    }

    const resolution = resolveHiddenTabsAgainstPermissions(
      currentHiddenTabs,
      selectedPermissionKeys(),
    );

    if (resolution.allowed.length === currentHiddenTabs.length) {
      return false;
    }

    applyHiddenTabsSelection(resolution.allowed);

    if (showNotice && resolution.blockedLabels.length) {
      notify(
        `Cannot hide tabs that are granted by selected permissions: ${resolution.blockedLabels.join(", ")}.`,
        "warning",
      );
    }

    return true;
  }

  function markRoleCard(role) {
    const normalized = (role || "").toString().trim().toLowerCase();
    $("#subAdminRoleCards .sub-admin-role-card").removeClass("is-active");
    $(
      `#subAdminRoleCards .sub-admin-role-card[data-role="${normalized}"]`,
    ).addClass("is-active");

    const roleMeta = findRoleMeta(normalized);
    const fallbackText =
      "Select a role profile. You can still fine-tune permissions and hidden tabs below.";
    const help = (roleMeta?.description || "").toString().trim();
    $("#subAdminRoleHelp").text(help !== "" ? help : fallbackText);

    $("#subAdminRole").val(normalized || "operations_manager");
  }

  function applyPermissionSelection(keys) {
    const selected = new Set(
      (Array.isArray(keys) ? keys : [])
        .map((key) => (key || "").toString().trim().toLowerCase())
        .filter((key) => key !== ""),
    );

    $("#subAdminPermissionGrid .sub-admin-permission-check").each(function () {
      const key = ($(this).val() || "").toString().trim().toLowerCase();
      $(this).prop("checked", selected.has(key));
    });
  }

  function applyHiddenTabsSelection(tabIds) {
    const selected = new Set(
      (Array.isArray(tabIds) ? tabIds : [])
        .map((id) => (id || "").toString().trim().toLowerCase())
        .filter((id) => id !== ""),
    );

    $("#subAdminHiddenTabsGrid .sub-admin-tab-check").each(function () {
      const tabId = ($(this).val() || "").toString().trim().toLowerCase();
      $(this).prop("checked", selected.has(tabId));
    });
  }

  function applyRoleDefaults(role) {
    const roleMeta = findRoleMeta(role);
    const defaults = Array.isArray(roleMeta?.defaultPermissions)
      ? roleMeta.defaultPermissions
          .map((value) => (value || "").toString().trim().toLowerCase())
          .filter((value) => value !== "" && value !== "*")
      : [];

    applyPermissionSelection(defaults);
    enforceHiddenTabPermissionConstraints(false);
  }

  function normalizeVerificationCode(value) {
    return (value || "").toString().replace(/\D+/g, "").slice(0, 6);
  }

  function syncVerificationSection(entry = null) {
    const wrap = $("#subAdminVerificationWrap");
    if (!wrap.length) {
      return;
    }

    const editId = Number($("#subAdminEditId").val() || 0) || 0;
    const row =
      entry && typeof entry === "object"
        ? entry
        : editId > 0
          ? rowById(editId)
          : null;
    const isPending =
      !!row &&
      (Number(row?.id || 0) || 0) > 0 &&
      !(row?.emailVerified || false);

    wrap.prop("hidden", !isPending);

    const verifyBtn = $("#verifySubAdminCodeBtn");
    const resendBtn = $("#subAdminResendVerifyFromFormBtn");
    const codeInput = $("#subAdminVerificationCode");
    const hint = $("#subAdminVerificationHint");

    if (!isPending) {
      verifyBtn.prop("disabled", true);
      resendBtn.prop("disabled", true);
      codeInput.val("");
      hint.text("Enter the 6-digit code sent to this sub-admin email.");
      return;
    }

    verifyBtn.prop("disabled", false);
    resendBtn.prop("disabled", false);
    const email = (row?.email || "").toString().trim();
    hint.text(
      email !== ""
        ? `Enter the 6-digit code sent to ${email} to verify this sub-admin account.`
        : "Enter the 6-digit code sent to this sub-admin email.",
    );
  }

  function resetForm(forceRoleDefaults = true) {
    const form = document.getElementById("subAdminForm");
    if (form) {
      form.reset();
    }

    $("#subAdminEditId").val("0");
    $("#subAdminFormTitle").text("Create Sub Admin");
    $("#saveSubAdminBtn")
      .html('<i class="bi bi-person-plus me-1"></i>Create Sub Admin')
      .prop("disabled", false);

    const defaultRole = "operations_manager";
    markRoleCard(defaultRole);
    if (forceRoleDefaults) {
      applyRoleDefaults(defaultRole);
    }
    applyHiddenTabsSelection([]);
    $("#subAdminVerificationCode").val("");
    syncVerificationSection(null);
  }

  function roleBadgeClass(role) {
    const key = (role || "").toString().trim().toLowerCase();
    if (key === "operations_manager") {
      return "bg-success";
    }
    if (key === "customer_support") {
      return "bg-info text-dark";
    }
    if (key === "marketing_website") {
      return "bg-warning text-dark";
    }
    if (key === "read_only") {
      return "bg-secondary";
    }
    if (key === "view_only") {
      return "bg-dark border border-secondary";
    }
    return "bg-primary";
  }

  function statusBadge(entry) {
    const status = (entry?.status || "active").toString().toLowerCase();
    if (status === "suspended_until_changed") {
      return '<span class="badge bg-danger">Suspended</span>';
    }

    if (status === "suspended_temporary") {
      const until = escapeHtml(
        formatDateTimeLocal(entry?.suspendedUntil || ""),
      );
      return `<span class="badge bg-warning text-dark">Suspended (temp)</span><div class="small text-secondary mt-1">Until: ${until}</div>`;
    }

    return '<span class="badge bg-success">Active</span>';
  }

  function verificationBadge(entry) {
    const verified = !!entry?.emailVerified;
    if (verified) {
      return '<span class="badge bg-success">Verified</span>';
    }

    return '<span class="badge bg-warning text-dark">Pending</span>';
  }

  function rowActions(entry) {
    const id = Number(entry?.id || 0) || 0;
    if (id <= 0) {
      return "";
    }

    const status = (entry?.status || "active").toString().toLowerCase();
    const actionButtons = [];

    actionButtons.push(
      `<button type="button" class="btn btn-sm btn-outline-orange sub-admin-edit-btn" data-admin-id="${id}"><i class="bi bi-pencil-square me-1"></i>Edit</button>`,
    );

    if (!entry?.emailVerified) {
      actionButtons.push(
        `<button type="button" class="btn btn-sm btn-outline-info sub-admin-resend-btn" data-admin-id="${id}"><i class="bi bi-envelope-arrow-up me-1"></i>Resend Code</button>`,
      );
      actionButtons.push(
        `<button type="button" class="btn btn-sm btn-outline-info sub-admin-open-verify-btn" data-admin-id="${id}"><i class="bi bi-key me-1"></i>Enter Code</button>`,
      );
    }

    if (status === "active") {
      actionButtons.push(
        `<button type="button" class="btn btn-sm btn-outline-warning sub-admin-suspend-temp-btn" data-admin-id="${id}"><i class="bi bi-pause-circle me-1"></i>Suspend 24h</button>`,
      );
      actionButtons.push(
        `<button type="button" class="btn btn-sm btn-outline-danger sub-admin-suspend-until-btn" data-admin-id="${id}"><i class="bi bi-slash-circle me-1"></i>Suspend Until Changed</button>`,
      );
    } else {
      actionButtons.push(
        `<button type="button" class="btn btn-sm btn-outline-success sub-admin-reactivate-btn" data-admin-id="${id}"><i class="bi bi-arrow-clockwise me-1"></i>Activate</button>`,
      );
    }

    actionButtons.push(
      `<button type="button" class="btn btn-sm btn-outline-danger sub-admin-delete-btn" data-admin-id="${id}"><i class="bi bi-trash3 me-1"></i>Delete</button>`,
    );

    return actionButtons.join("");
  }

  function renderRows() {
    const tbody = $("#subAdminsTable tbody");
    if (!tbody.length) {
      return;
    }

    const rows = Array.isArray(state.rows) ? state.rows : [];
    tbody.empty();

    if (!rows.length) {
      tbody.append(
        '<tr><td colspan="6" class="text-center py-4 text-secondary">No sub admin accounts found.</td></tr>',
      );
      return;
    }

    rows.forEach((entry) => {
      const id = Number(entry?.id || 0) || 0;
      const fullName = escapeHtml(entry?.fullName || "Sub Admin");
      const email = escapeHtml(entry?.email || "");
      const phone = escapeHtml(entry?.phone || "-");
      const roleLabel = escapeHtml(entry?.roleLabel || "Custom Access");
      const roleClass = roleBadgeClass(entry?.role || "custom");
      const lastLogin = escapeHtml(
        formatDateTimeLocal(entry?.lastLoginAt || ""),
      );
      const permissionCount = Array.isArray(entry?.permissions)
        ? entry.permissions.length
        : 0;

      tbody.append(`
        <tr class="border-bottom border-secondary" data-sub-admin-id="${id}">
          <td class="ps-4 py-3 text-light">
            <div class="fw-semibold">${fullName}</div>
            <div class="small text-secondary">${email}</div>
            <div class="small text-secondary">Phone: ${phone}</div>
          </td>
          <td class="py-3 text-secondary">
            <span class="badge ${escapeHtml(roleClass)}">${roleLabel}</span>
            <div class="small text-secondary mt-1">${permissionCount} permissions</div>
          </td>
          <td class="py-3 text-secondary">${statusBadge(entry)}</td>
          <td class="py-3 text-secondary">${verificationBadge(entry)}</td>
          <td class="py-3 text-secondary">${lastLogin}</td>
          <td class="pe-4 py-3 d-flex flex-wrap gap-1">${rowActions(entry)}</td>
        </tr>
      `);
    });
  }

  function renderRoleCards() {
    const shell = document.getElementById("subAdminRoleCards");
    if (!shell) {
      return;
    }

    const roleRows = Array.isArray(state.roles) ? state.roles : [];
    if (!roleRows.length) {
      shell.innerHTML =
        '<div class="text-secondary small">Role profiles unavailable right now. Refresh to retry.</div>';
      return;
    }

    shell.innerHTML = roleRows
      .map((role) => {
        const key = escapeHtml(
          (role?.key || "custom").toString().toLowerCase(),
        );
        const label = escapeHtml(role?.label || key);
        const description = escapeHtml(role?.description || "");
        const defaultCount = Array.isArray(role?.defaultPermissions)
          ? role.defaultPermissions.filter((p) => (p || "") !== "*").length
          : 0;

        return `
          <button type="button" class="sub-admin-role-card" data-role="${key}">
            <span class="role-title">${label}</span>
            <span class="role-description">${description}</span>
            <span class="role-meta">${defaultCount} default capabilities</span>
          </button>
        `;
      })
      .join("");

    markRoleCard(activeRoleValue() || "operations_manager");
  }

  function renderPermissionGrid() {
    const shell = document.getElementById("subAdminPermissionGrid");
    if (!shell) {
      return;
    }

    const permissionRows = Array.isArray(state.permissions)
      ? state.permissions
      : [];
    if (!permissionRows.length) {
      shell.innerHTML =
        '<div class="text-secondary small">Permission catalog unavailable right now.</div>';
      return;
    }

    shell.innerHTML = permissionRows
      .map((permission) => {
        const key = escapeHtml(permission?.key || "");
        const label = escapeHtml(permission?.label || key);
        const description = escapeHtml(permission?.description || "");

        return `
          <label class="sub-admin-permission-item">
            <input type="checkbox" class="form-check-input sub-admin-permission-check" value="${key}">
            <span class="permission-content">
              <strong>${label}</strong>
              <small>${description}</small>
            </span>
          </label>
        `;
      })
      .join("");
  }

  function renderHiddenTabsGrid() {
    const shell = document.getElementById("subAdminHiddenTabsGrid");
    if (!shell) {
      return;
    }

    const tabRows = Array.isArray(state.tabs) ? state.tabs : [];
    if (!tabRows.length) {
      shell.innerHTML =
        '<div class="text-secondary small">Tab catalog unavailable right now.</div>';
      return;
    }

    shell.innerHTML = tabRows
      .map((tab) => {
        const id = escapeHtml(tab?.id || "");
        const label = escapeHtml(tab?.label || id);

        return `
          <label class="sub-admin-tab-item">
            <input type="checkbox" class="form-check-input sub-admin-tab-check" value="${id}">
            <span>${label}</span>
          </label>
        `;
      })
      .join("");
  }

  function loadFormFromRow(entry) {
    if (!entry || typeof entry !== "object") {
      return;
    }

    $("#subAdminEditId").val(Number(entry.id || 0) || 0);
    $("#subAdminFullName").val((entry.fullName || "").toString());
    $("#subAdminEmail").val((entry.email || "").toString());
    $("#subAdminPhone").val((entry.phone || "").toString());
    $("#subAdminPassword").val("");

    const role = (entry.role || "custom").toString().toLowerCase();
    markRoleCard(role);
    applyPermissionSelection(entry.permissions || []);
    applyHiddenTabsSelection(entry.hiddenTabs || []);
    enforceHiddenTabPermissionConstraints(false);
    $("#subAdminVerificationCode").val("");
    syncVerificationSection(entry);

    if (!roleMatchesPermissionDefaults(role, entry.permissions || [])) {
      markRoleCard("custom");
    }

    $("#subAdminFormTitle").text("Edit Sub Admin Access");
    $("#saveSubAdminBtn")
      .html('<i class="bi bi-check2-circle me-1"></i>Update Access')
      .prop("disabled", false);

    const formCard = document.getElementById("subAdminForm");
    if (formCard) {
      formCard.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  }

  async function refreshPanel(showSuccess = false) {
    const payload = await loadList();
    state.rows = Array.isArray(payload?.subAdmins) ? payload.subAdmins : [];
    state.roles = Array.isArray(payload?.roles) ? payload.roles : [];
    state.permissions = Array.isArray(payload?.permissions)
      ? payload.permissions
      : state.permissions;
    state.tabs = Array.isArray(payload?.tabs) ? payload.tabs : state.tabs;

    renderRoleCards();
    renderPermissionGrid();
    renderHiddenTabsGrid();
    renderRows();

    if (!state.loaded) {
      resetForm(true);
      state.loaded = true;
    }

    if (showSuccess) {
      notify("Sub-admin panel synced.", "success");
    }
  }

  async function submitForm(event) {
    event.preventDefault();

    const editId = Number($("#subAdminEditId").val() || 0) || 0;
    const fullName = ("" + ($("#subAdminFullName").val() || "")).trim();
    const email = ("" + ($("#subAdminEmail").val() || "")).trim();
    const phone = ("" + ($("#subAdminPhone").val() || "")).trim();
    const password = ("" + ($("#subAdminPassword").val() || "")).trim();
    let role = activeRoleValue() || "operations_manager";
    const permissions = selectedPermissionKeys();

    if (!roleMatchesPermissionDefaults(role, permissions)) {
      role = "custom";
      markRoleCard("custom");
    }

    enforceHiddenTabPermissionConstraints(false);
    const hiddenTabs = selectedHiddenTabs();

    if (fullName.length < 3) {
      notify("Enter a valid full name.", "warning");
      return;
    }

    if (email === "") {
      notify("Enter a valid admin email.", "warning");
      return;
    }

    if (editId <= 0 && password === "") {
      notify("Password is required when creating a sub admin.", "warning");
      return;
    }

    if (role === "custom" && permissions.length === 0) {
      notify("Select at least one permission for custom role.", "warning");
      return;
    }

    const payload = {
      action: editId > 0 ? "update" : "create",
      full_name: fullName,
      email,
      phone,
      password,
      role,
      permissions,
      hidden_tabs: hiddenTabs,
    };

    if (editId > 0) {
      payload.admin_id = editId;
    }

    try {
      $("#saveSubAdminBtn").prop("disabled", true);
      const result = await apiPost(payload);
      const nextPayload = result?.payload || {};
      state.rows = Array.isArray(nextPayload?.subAdmins)
        ? nextPayload.subAdmins
        : [];
      state.roles = Array.isArray(nextPayload?.roles)
        ? nextPayload.roles
        : state.roles;
      state.permissions = Array.isArray(nextPayload?.permissions)
        ? nextPayload.permissions
        : state.permissions;
      state.tabs = Array.isArray(nextPayload?.tabs)
        ? nextPayload.tabs
        : state.tabs;

      renderRoleCards();
      renderPermissionGrid();
      renderHiddenTabsGrid();
      renderRows();

      const successMessage =
        result?.message ||
        (editId > 0 ? "Sub-admin access updated." : "Sub-admin created.");

      if (editId > 0) {
        const refreshed = rowById(editId);
        if (refreshed && !refreshed.emailVerified) {
          loadFormFromRow(refreshed);
          $("#subAdminVerificationCode").trigger("focus");
          notify(
            `${successMessage} Enter the verification code below to complete verification.`,
            "success",
          );
          return;
        }
      }

      resetForm(true);
      notify(successMessage, "success");
    } catch (error) {
      notify(error?.message || "Unable to save sub-admin access.", "danger");
    } finally {
      $("#saveSubAdminBtn").prop("disabled", false);
    }
  }

  function rowById(adminId) {
    const id = Number(adminId || 0) || 0;
    if (id <= 0) {
      return null;
    }

    return (
      (Array.isArray(state.rows) ? state.rows : []).find(
        (entry) => (Number(entry?.id || 0) || 0) === id,
      ) || null
    );
  }

  async function applySuspension(adminId, mode, durationMinutes) {
    const row = rowById(adminId);
    if (!row) {
      notify("Sub-admin account not found.", "warning");
      return;
    }

    let reason = "";
    if (mode === "temporary") {
      reason =
        (await askPrompt(
          `Optional reason for temporarily suspending ${row.fullName || "this sub admin"}:`,
          "",
          "Suspend Sub Admin",
        )) || "";
    } else if (mode === "until_changed") {
      reason =
        (await askPrompt(
          `Reason for suspending ${row.fullName || "this sub admin"} until changed:`,
          "",
          "Suspend Until Changed",
        )) || "";
    }

    try {
      const result = await apiPost({
        action: "set-suspension",
        admin_id: Number(adminId || 0) || 0,
        mode,
        duration_minutes: Number(durationMinutes || 0) || 0,
        reason: (reason || "").toString().trim(),
      });

      const nextPayload = result?.payload || {};
      state.rows = Array.isArray(nextPayload?.subAdmins)
        ? nextPayload.subAdmins
        : [];
      renderRows();
      notify(result?.message || "Sub-admin suspension updated.", "success");
    } catch (error) {
      notify(
        error?.message || "Unable to update sub-admin suspension.",
        "danger",
      );
    }
  }

  async function deleteSubAdmin(adminId) {
    const row = rowById(adminId);
    if (!row) {
      notify("Sub-admin account not found.", "warning");
      return;
    }

    const confirmed = await askConfirm(
      `Delete ${row.fullName || "this sub admin"}? This removes panel access and marks account as deleted.`,
      "Delete Sub Admin",
    );

    if (!confirmed) {
      return;
    }

    try {
      const result = await apiPost({
        action: "delete",
        admin_id: Number(adminId || 0) || 0,
      });
      const nextPayload = result?.payload || {};
      state.rows = Array.isArray(nextPayload?.subAdmins)
        ? nextPayload.subAdmins
        : [];
      renderRows();
      notify(result?.message || "Sub-admin deleted.", "success");

      const currentEditId = Number($("#subAdminEditId").val() || 0) || 0;
      if (currentEditId === Number(adminId || 0)) {
        resetForm(true);
      }
    } catch (error) {
      notify(error?.message || "Unable to delete sub-admin.", "danger");
    }
  }

  async function resendVerification(adminId) {
    const row = rowById(adminId);
    if (!row) {
      notify("Sub-admin account not found.", "warning");
      return;
    }

    try {
      const result = await apiPost({
        action: "resend-verification",
        admin_id: Number(adminId || 0) || 0,
      });
      const nextPayload = result?.payload || {};
      state.rows = Array.isArray(nextPayload?.subAdmins)
        ? nextPayload.subAdmins
        : [];
      renderRows();
      const currentEditId = Number($("#subAdminEditId").val() || 0) || 0;
      if (currentEditId === Number(adminId || 0)) {
        const refreshed = rowById(adminId);
        if (refreshed) {
          loadFormFromRow(refreshed);
          $("#subAdminVerificationCode").trigger("focus");
        } else {
          syncVerificationSection(null);
        }
      }
      notify(result?.message || "Verification code sent.", "success");
    } catch (error) {
      notify(error?.message || "Unable to resend verification code.", "danger");
    }
  }

  async function verifySubAdminCode() {
    const editId = Number($("#subAdminEditId").val() || 0) || 0;
    if (editId <= 0) {
      notify(
        "Select a sub-admin first, then enter the verification code.",
        "warning",
      );
      return;
    }

    const row = rowById(editId);
    if (!row) {
      notify("Sub-admin account not found.", "warning");
      syncVerificationSection(null);
      return;
    }

    if (row.emailVerified) {
      notify("This sub-admin email is already verified.", "info");
      syncVerificationSection(row);
      return;
    }

    const codeInput = $("#subAdminVerificationCode");
    const code = normalizeVerificationCode(codeInput.val());
    codeInput.val(code);

    if (!/^\d{6}$/.test(code)) {
      notify("Enter a valid 6-digit verification code.", "warning");
      codeInput.trigger("focus");
      return;
    }

    const verifyBtn = $("#verifySubAdminCodeBtn");
    const resendBtn = $("#subAdminResendVerifyFromFormBtn");

    try {
      verifyBtn.prop("disabled", true);
      resendBtn.prop("disabled", true);

      const result = await apiPost({
        action: "verify-email",
        admin_id: editId,
        verification_code: code,
      });

      const nextPayload = result?.payload || {};
      state.rows = Array.isArray(nextPayload?.subAdmins)
        ? nextPayload.subAdmins
        : [];
      state.roles = Array.isArray(nextPayload?.roles)
        ? nextPayload.roles
        : state.roles;
      state.permissions = Array.isArray(nextPayload?.permissions)
        ? nextPayload.permissions
        : state.permissions;
      state.tabs = Array.isArray(nextPayload?.tabs)
        ? nextPayload.tabs
        : state.tabs;

      renderRoleCards();
      renderPermissionGrid();
      renderHiddenTabsGrid();
      renderRows();

      const refreshed = rowById(editId);
      if (refreshed) {
        loadFormFromRow(refreshed);
      } else {
        resetForm(true);
      }

      codeInput.val("");
      notify(result?.message || "Sub-admin email verified.", "success");
    } catch (error) {
      notify(error?.message || "Unable to verify code.", "danger");
    } finally {
      verifyBtn.prop("disabled", false);
      resendBtn.prop("disabled", false);
      const freshRow = rowById(editId);
      syncVerificationSection(freshRow);
    }
  }

  function bindEvents() {
    $(document)
      .off("click.subAdminRoleCard", "#subAdminRoleCards .sub-admin-role-card")
      .on(
        "click.subAdminRoleCard",
        "#subAdminRoleCards .sub-admin-role-card",
        function () {
          const role = ($(this).data("role") || "operations_manager")
            .toString()
            .trim()
            .toLowerCase();
          markRoleCard(role);
          applyRoleDefaults(role);
        },
      );

    $(document)
      .off(
        "change.subAdminPermissionEdit",
        "#subAdminPermissionGrid .sub-admin-permission-check",
      )
      .on(
        "change.subAdminPermissionEdit",
        "#subAdminPermissionGrid .sub-admin-permission-check",
        function () {
          if (activeRoleValue() !== "custom") {
            markRoleCard("custom");
          }

          enforceHiddenTabPermissionConstraints(false);
        },
      );

    $(document)
      .off(
        "change.subAdminHiddenTabsEdit",
        "#subAdminHiddenTabsGrid .sub-admin-tab-check",
      )
      .on(
        "change.subAdminHiddenTabsEdit",
        "#subAdminHiddenTabsGrid .sub-admin-tab-check",
        function () {
          if (activeRoleValue() !== "custom") {
            markRoleCard("custom");
          }

          enforceHiddenTabPermissionConstraints(true);
        },
      );

    $("#subAdminForm").off("submit").on("submit", submitForm);

    $("#resetSubAdminBtn")
      .off("click")
      .on("click", function () {
        resetForm(true);
      });

    $("#refreshSubAdminsBtn")
      .off("click")
      .on("click", async function () {
        try {
          await refreshPanel(true);
        } catch (error) {
          notify(
            error?.message || "Unable to refresh sub-admin list.",
            "danger",
          );
        }
      });

    $(document)
      .off("click.subAdminEdit", ".sub-admin-edit-btn")
      .on("click.subAdminEdit", ".sub-admin-edit-btn", function () {
        const adminId = Number($(this).data("adminId") || 0) || 0;
        const row = rowById(adminId);
        if (!row) {
          notify("Sub-admin account not found.", "warning");
          return;
        }
        loadFormFromRow(row);
      });

    $(document)
      .off("click.subAdminOpenVerify", ".sub-admin-open-verify-btn")
      .on(
        "click.subAdminOpenVerify",
        ".sub-admin-open-verify-btn",
        function () {
          const adminId = Number($(this).data("adminId") || 0) || 0;
          const row = rowById(adminId);
          if (!row) {
            notify("Sub-admin account not found.", "warning");
            return;
          }
          loadFormFromRow(row);
          $("#subAdminVerificationCode").trigger("focus");
        },
      );

    $(document)
      .off("click.subAdminResend", ".sub-admin-resend-btn")
      .on("click.subAdminResend", ".sub-admin-resend-btn", function () {
        const adminId = Number($(this).data("adminId") || 0) || 0;
        resendVerification(adminId);
      });

    $("#subAdminResendVerifyFromFormBtn")
      .off("click")
      .on("click", function () {
        const adminId = Number($("#subAdminEditId").val() || 0) || 0;
        resendVerification(adminId);
      });

    $("#verifySubAdminCodeBtn")
      .off("click")
      .on("click", function () {
        verifySubAdminCode();
      });

    $("#subAdminVerificationCode")
      .off("input.subAdminVerifyCode")
      .on("input.subAdminVerifyCode", function () {
        $(this).val(normalizeVerificationCode($(this).val()));
      })
      .off("keydown.subAdminVerifyCode")
      .on("keydown.subAdminVerifyCode", function (event) {
        if (event.key !== "Enter") {
          return;
        }

        event.preventDefault();
        verifySubAdminCode();
      });

    $(document)
      .off("click.subAdminSuspendTemp", ".sub-admin-suspend-temp-btn")
      .on(
        "click.subAdminSuspendTemp",
        ".sub-admin-suspend-temp-btn",
        async function () {
          const adminId = Number($(this).data("adminId") || 0) || 0;
          const confirmed = await askConfirm(
            "Suspend this sub admin for 24 hours?",
            "Temporary Suspension",
          );
          if (!confirmed) {
            return;
          }
          applySuspension(adminId, "temporary", 1440);
        },
      );

    $(document)
      .off("click.subAdminSuspendUntil", ".sub-admin-suspend-until-btn")
      .on(
        "click.subAdminSuspendUntil",
        ".sub-admin-suspend-until-btn",
        async function () {
          const adminId = Number($(this).data("adminId") || 0) || 0;
          const confirmed = await askConfirm(
            "Suspend this sub admin until you reactivate access?",
            "Suspend Until Changed",
          );
          if (!confirmed) {
            return;
          }
          applySuspension(adminId, "until_changed", 0);
        },
      );

    $(document)
      .off("click.subAdminActivate", ".sub-admin-reactivate-btn")
      .on(
        "click.subAdminActivate",
        ".sub-admin-reactivate-btn",
        async function () {
          const adminId = Number($(this).data("adminId") || 0) || 0;
          const confirmed = await askConfirm(
            "Reactivate this sub admin account?",
            "Restore Access",
          );
          if (!confirmed) {
            return;
          }
          applySuspension(adminId, "active", 0);
        },
      );

    $(document)
      .off("click.subAdminDelete", ".sub-admin-delete-btn")
      .on("click.subAdminDelete", ".sub-admin-delete-btn", function () {
        const adminId = Number($(this).data("adminId") || 0) || 0;
        deleteSubAdmin(adminId);
      });
  }

  async function initPanel() {
    if (!document.getElementById("subAdminForm")) {
      return;
    }

    if (!hasSubAdminPermission() || subAdminTabHidden()) {
      return;
    }

    bindEvents();
    try {
      await refreshPanel(false);
    } catch (error) {
      notify(
        error?.message || "Unable to initialize sub-admin panel.",
        "danger",
      );
    }
  }

  window.refreshSubAdminsPanel = async function () {
    if (!document.getElementById("subAdminForm")) {
      return;
    }

    if (!hasSubAdminPermission() || subAdminTabHidden()) {
      return;
    }

    await refreshPanel(false);
  };

  window.renderSubAdminsPanel = function () {
    renderRows();
  };

  $(document).ready(function () {
    initPanel();
  });
})();
