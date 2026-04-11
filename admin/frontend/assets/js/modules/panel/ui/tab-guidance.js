function injectAdminTabPlaybooks() {
  Object.entries(ADMIN_TAB_PLAYBOOKS).forEach(([sectionId, config]) => {
    const pane = document.getElementById(sectionId);
    if (!pane) {
      return;
    }

    const existing = pane.querySelector(
      `.tab-playbook[data-for-tab="${sectionId}"]`,
    );
    if (existing) {
      return;
    }

    const rawSteps = Array.isArray(config?.steps) ? config.steps : [];
    const stepItems = rawSteps
      .map((step) => escapeHtml((step || "").toString().trim()))
      .filter((step) => step !== "")
      .map((step) => `<li>${step}</li>`)
      .join("");

    if (stepItems === "") {
      return;
    }

    const title = escapeHtml((config?.title || "Quick Steps").toString());
    const intro = escapeHtml((config?.intro || "").toString());
    const tip = escapeHtml((config?.tip || "").toString());

    const block = document.createElement("section");
    block.className = "tab-playbook mb-4";
    block.dataset.forTab = sectionId;
    block.innerHTML = `
      <span class="step-chip">Simple Guide</span>
      <h2 class="tab-playbook-title">${title}</h2>
      <p class="tab-playbook-intro">${intro}</p>
      <ol class="tab-playbook-list">${stepItems}</ol>
      <p class="tab-playbook-note mb-0"><i class="bi bi-lightbulb me-2"></i>${tip}</p>
    `;

    const helperBanner = pane.querySelector(".helper-banner");
    if (helperBanner) {
      helperBanner.insertAdjacentElement("afterend", block);
      return;
    }

    pane.insertAdjacentElement("afterbegin", block);
  });
}

function applyAdminTabVisibility() {
  const navTabs = Array.from(
    document.querySelectorAll('#sidebarNav [data-bs-toggle="pill"]'),
  );

  if (!navTabs.length) {
    return;
  }

  const visibleTabs = [];

  navTabs.forEach((tabButton) => {
    const tabId = (tabButton.id || "").toString().trim();
    const targetSelector = (tabButton.getAttribute("data-bs-target") || "")
      .toString()
      .trim();
    const targetPane = targetSelector
      ? document.querySelector(targetSelector)
      : null;
    const allowed = admin_can_access_tab(tabId);

    if (!allowed) {
      tabButton.classList.remove("active");
      tabButton.classList.add("d-none");
      tabButton.setAttribute("aria-hidden", "true");
      tabButton.setAttribute("aria-selected", "false");

      if (targetPane) {
        targetPane.classList.remove("active", "show");
        targetPane.classList.add("d-none");
      }

      return;
    }

    tabButton.classList.remove("d-none");
    tabButton.removeAttribute("aria-hidden");

    if (targetPane) {
      targetPane.classList.remove("d-none");
    }

    visibleTabs.push(tabButton);
  });

  if (!visibleTabs.length) {
    return;
  }

  const activeVisible =
    document.querySelector(
      '#sidebarNav [data-bs-toggle="pill"].active:not(.d-none)',
    ) || null;

  if (activeVisible) {
    return;
  }

  const firstVisible = visibleTabs[0];
  if (!firstVisible) {
    return;
  }

  if (
    window.bootstrap?.Tab &&
    typeof window.bootstrap.Tab.getOrCreateInstance === "function"
  ) {
    const tabInstance = window.bootstrap.Tab.getOrCreateInstance(firstVisible);
    tabInstance.show();
    return;
  }

  if (typeof firstVisible.click === "function") {
    firstVisible.click();
  }
}

