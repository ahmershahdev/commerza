$(document).ready(function () {
  const adminIdentity = ADMIN_RUNTIME.admin || {};
  const sidebarEmailEl = document.getElementById("adminSidebarEmail");

  function normalizeAdminEmail(value) {
    return (value || "").toString().trim().toLowerCase();
  }

  function enforceSidebarEmailLowercase() {
    if (!sidebarEmailEl) {
      return;
    }

    const normalized = normalizeAdminEmail(sidebarEmailEl.textContent || "");
    if (normalized === "") {
      return;
    }

    if ((sidebarEmailEl.textContent || "") !== normalized) {
      sidebarEmailEl.textContent = normalized;
    }

    sidebarEmailEl.setAttribute("title", normalized);
  }

  function syncAdminIdentityUi(nextIdentity = {}) {
    if (!window.CommerzaAdminRuntime) {
      window.CommerzaAdminRuntime = {};
    }

    if (!window.CommerzaAdminRuntime.admin) {
      window.CommerzaAdminRuntime.admin = {};
    }

    if (nextIdentity?.name) {
      const safeName = nextIdentity.name.toString().trim();
      if (safeName !== "") {
        window.CommerzaAdminRuntime.admin.name = safeName;
        $("#adminSidebarName").text(safeName);
      }
    }

    const normalizedEmail = normalizeAdminEmail(nextIdentity?.email || "");
    if (normalizedEmail !== "") {
      adminIdentity.email = normalizedEmail;
      if (typeof ADMIN_RUNTIME === "object" && ADMIN_RUNTIME?.admin) {
        ADMIN_RUNTIME.admin.email = normalizedEmail;
      }
      window.CommerzaAdminRuntime.admin.email = normalizedEmail;
      $("#adminSidebarEmail")
        .text(normalizedEmail)
        .attr("title", normalizedEmail);
      $("#securityPasswordEmail, #securityKeyEmail").val(normalizedEmail);
    }

    enforceSidebarEmailLowercase();
  }

  syncAdminIdentityUi({
    ...adminIdentity,
    email: normalizeAdminEmail(
      adminIdentity?.email || sidebarEmailEl?.textContent || "",
    ),
  });

  if (sidebarEmailEl && window.MutationObserver) {
    const sidebarEmailObserver = new MutationObserver(() => {
      enforceSidebarEmailLowercase();
    });

    sidebarEmailObserver.observe(sidebarEmailEl, {
      characterData: true,
      childList: true,
      subtree: true,
    });
  }

  const canProductsManage = admin_has_permission("products.manage");
  const canProductTrashManage = admin_has_any_permission([
    "product_trash.manage",
    "products.manage",
  ]);
  const canOrdersSummaryData = admin_can_use_orders_summary_api();
  const canCouponsManage = admin_has_permission("coupons.manage");
  const canReviewsManage = admin_has_permission("reviews.manage");
  const canSecurityManage = admin_has_permission("security.manage");
  const canWebsiteManage = admin_has_permission("website.manage");
  const canViewersManage = admin_has_permission("viewers.manage");
  const canEmailManage = admin_has_permission("email.manage");

  document.addEventListener("commerza:admin-theme-change", () => {
    if (!canOrdersSummaryData) {
      return;
    }

    if (!document.getElementById("analyticsProfitLossChart")) {
      return;
    }

    renderAnalyticsSection();
  });

  const sidebar = document.getElementById("sidebarMenu");
  if (sidebar) {
    sidebar.addEventListener("shown.bs.collapse", () => {
      document.body.classList.add("sidebar-open");
    });
    sidebar.addEventListener("hidden.bs.collapse", () => {
      document.body.classList.remove("sidebar-open");
    });
  }

  injectAdminTabPlaybooks();
  applyAdminTabVisibility();

  $(document)
    .off("click.adminDropdownItem")
    .on("click.adminDropdownItem", ".admin-dropdown-item", function (event) {
      event.preventDefault();

      const targetId = ($(this).data("target") || "").toString().trim();
      if (!targetId) {
        return;
      }

      const selectedValue = ($(this).data("value") ?? "").toString();
      const selectedLabel = ($(this).data("label") || $(this).text() || "")
        .toString()
        .trim();

      setAdminDropdownSelection(targetId, selectedValue, selectedLabel);
      $(`#${targetId}`).trigger("change");
    });

  function refreshTabPaneByTabId(tabId) {
    const normalizedTabId = (tabId || "").toString().trim().toLowerCase();
    if (normalizedTabId !== "" && !admin_can_access_tab(normalizedTabId)) {
      return;
    }

    switch ((tabId || "").toString()) {
      case "product-trash-tab":
        if (canProductTrashManage) {
          loadProductTrashData(true);
        }
        break;
      case "products-tab":
        if (canProductsManage) {
          loadProductsFromJSON();
        }
        break;
      case "orders-tab":
        if (canOrdersSummaryData) {
          displayAllOrders();
          renderRefundRequests();
        }
        break;
      case "customers-tab":
        if (canOrdersSummaryData) {
          displayAllCustomers();
          renderBlacklistTable();
          syncBlacklistNoticeToggleUi();
        }
        break;
      case "sub-admins-tab":
        if (admin_has_permission("sub_admins.manage")) {
          window.refreshSubAdminsPanel?.();
        }
        break;
      case "analytics-tab":
        if (canOrdersSummaryData) {
          renderAnalyticsSection();
        }
        break;
      case "coupons-tab":
        if (canCouponsManage) {
          renderCouponsTable();
        }
        break;
      case "reviews-tab":
        if (canReviewsManage) {
          renderReviewsTable();
        }
        break;
      case "security-events-tab":
        if (canSecurityManage) {
          renderSecurityEventsTable();
        }
        break;
      case "website-tab":
        if (canWebsiteManage) {
          renderSocialLinksTable();
          renderSliderTable();
        }
        break;
      default:
        break;
    }
  }

  function focusProductTrashCard() {
    const card = document.getElementById("productTrashCard");
    if (!card) {
      return;
    }

    card.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function syncActiveTabUi(tabEl) {
    const pageTitle = document.getElementById("pageTitle");
    const breadcrumbCurrent = document.getElementById("adminBreadcrumbCurrent");
    const tabText =
      tabEl?.querySelector("span:last-child")?.textContent?.trim() ||
      tabEl?.textContent?.trim() ||
      "Dashboard";
    const normalizedTitle = tabText.replace(/\s+/g, " ");

    if (pageTitle) {
      pageTitle.textContent = normalizedTitle;
    }

    if (breadcrumbCurrent) {
      breadcrumbCurrent.textContent = normalizedTitle;
    }

    if (window.innerWidth < 768 && sidebar) {
      const collapse = bootstrap.Collapse.getOrCreateInstance(sidebar, {
        toggle: false,
      });
      collapse.hide();
    }
  }

  $(document)
    .off("shown.bs.tab", '#sidebarNav [data-bs-toggle="pill"]')
    .on("shown.bs.tab", '#sidebarNav [data-bs-toggle="pill"]', function () {
      const activeTabId = (this.id || "").toString();
      syncActiveTabUi(this);
      refreshTabPaneByTabId(activeTabId);

      if (activeTabId === "product-trash-tab") {
        window.setTimeout(focusProductTrashCard, 180);
      }
    });

  const initialVisibleTab =
    document.querySelector(
      '#sidebarNav [data-bs-toggle="pill"].active:not(.d-none)',
    ) ||
    document.querySelector('#sidebarNav [data-bs-toggle="pill"]:not(.d-none)');
  syncActiveTabUi(initialVisibleTab);

  function applyButtonCooldown(selector, duration = 1200) {
    $(document).on("click", selector, function () {
      const btn = $(this);
      if (btn.prop("disabled")) return;
      btn.prop("disabled", true);
      setTimeout(() => btn.prop("disabled", false), duration);
    });
  }

  applyButtonCooldown("#saveProductBtn");
  applyButtonCooldown("#saveSectionBtn");
  applyButtonCooldown("#resetSectionBtn");
  applyButtonCooldown("#bulkProductsImportBtn");
  applyButtonCooldown("#downloadSampleProductsCsvBtn");
  applyButtonCooldown("#saveContactBtn");
  applyButtonCooldown("#saveSocialBtn");
  applyButtonCooldown("#resetSocialBtn");
  applyButtonCooldown("#saveTickerBtn");
  applyButtonCooldown("#resetTickerBtn");
  applyButtonCooldown("#saveStorybookBtn");
  applyButtonCooldown("#resetStorybookBtn");
  applyButtonCooldown("#saveCollectorsSpeakBtn");
  applyButtonCooldown("#resetCollectorsSpeakBtn");
  applyButtonCooldown("#saveFeaturedVideosBtn");
  applyButtonCooldown("#saveSliderBtn");
  applyButtonCooldown("#resetSliderBtn");
  applyButtonCooldown("#saveAdminEmailBtn");
  applyButtonCooldown("#saveAdminPasswordBtn");
  applyButtonCooldown("#saveAdminResetKeyBtn");
  applyButtonCooldown("#saveLiveViewersBtn");
  applyButtonCooldown("#bulkDeleteOrdersBtn");
  applyButtonCooldown("#bulkDeleteCustomersBtn");
  applyButtonCooldown("#saveCouponBtn");
  applyButtonCooldown("#resetCouponBtn");
  applyButtonCooldown("#sendCouponEmailBtn");
  applyButtonCooldown("#refreshCouponsBtn");
  applyButtonCooldown("#refreshReviewsBtn");
  applyButtonCooldown("#submitAddReviewBtn");
  applyButtonCooldown("#addFakeReviewBtn");
  applyButtonCooldown("#addSingleFakeReviewBtn");
  applyButtonCooldown("#addFakeBulkReviewsBtn");
  applyButtonCooldown("#securityEventsApplyBtn");
  applyButtonCooldown("#securityEventsClearBtn");
  applyButtonCooldown("#securityEventsRefreshBtn");

  bindUploadControl("#uploadSiteLogoBtn", "#siteLogoFile", "logo", (path) => {
    $("#siteLogo").val(path);
  });
  bindUploadControl(
    "#uploadSiteFaviconBtn",
    "#siteFaviconFile",
    "favicon",
    (path) => {
      $("#siteFavicon").val(path);
    },
  );
  bindUploadControl(
    "#uploadSocialIconBtn",
    "#socialIconFile",
    "social-icon",
    (path) => {
      $("#socialIcon").val(path);
    },
  );
  bindUploadControl(
    "#uploadSliderImageBtn",
    "#sliderImageFile",
    "slider-image",
    (path) => {
      $("#sliderImage").val(path);
    },
  );
  bindUploadControl(
    "#uploadSliderVideoBtn",
    "#sliderVideoFile",
    "slider-video",
    (path) => {
      $("#sliderVideo").val(path);
    },
  );
  bindUploadControl(
    "#uploadHomeFeatureVideoBtn",
    "#homeFeatureVideoFile",
    "slider-video",
    (path) => {
      $("#homeFeatureVideo").val(path);
    },
  );
  bindUploadControl(
    "#uploadCategoryAFeatureVideoBtn",
    "#categoryAFeatureVideoFile",
    "product-video",
    (path) => {
      $("#categoryAFeatureVideo").val(path);
    },
  );
  bindUploadControl(
    "#uploadProductImageBtn",
    "#productImageFile",
    "product-image",
    (path) => {
      $("#productImage").val(path);
    },
  );
  bindUploadControl(
    "#uploadProductVideoBtn",
    "#productVideoFile",
    "product-video",
    (path) => {
      $("#productVideo").val(path);
    },
  );

  $("#ordersSelectAll")
    .off("change")
    .on("change", function () {
      const checked = this.checked;
      $(".order-select-row").prop("checked", checked);
    });

  $(document)
    .off("change", ".order-select-row")
    .on("change", ".order-select-row", function () {
      const total = $(".order-select-row").length;
      const selected = $(".order-select-row:checked").length;
      $("#ordersSelectAll").prop("checked", total > 0 && total === selected);
    });

  $("#customersSelectAll")
    .off("change")
    .on("change", function () {
      const checked = this.checked;
      $(".customer-select-row").prop("checked", checked);
    });

  $(document)
    .off("change", ".customer-select-row")
    .on("change", ".customer-select-row", function () {
      const total = $(".customer-select-row:not(:disabled)").length;
      const selected = $(".customer-select-row:checked").length;
      $("#customersSelectAll").prop("checked", total > 0 && total === selected);
    });

  $("#customersSearchInput")
    .off("input change")
    .on("input change", function () {
      displayAllCustomers();
    });

  $("#bulkProductsImportBtn")
    .off("click")
    .on("click", importProductsFromFileInput);

  $("#downloadSampleProductsCsvBtn")
    .off("click")
    .on("click", downloadSampleProductsCSV);

  $("#refreshProductTrashBtn")
    .off("click")
    .on("click", function () {
      loadProductTrashData(false);
    });

  $("#emptyExpiredProductTrashBtn")
    .off("click")
    .on("click", function () {
      emptyProductTrash("expired");
    });

  $("#emptyAllProductTrashBtn")
    .off("click")
    .on("click", function () {
      emptyProductTrash("all");
    });

  $("#bulkDeleteOrdersBtn").off("click").on("click", bulkDeleteOrders);
  $("#bulkDeleteCustomersBtn").off("click").on("click", bulkDeleteCustomers);
  $("#saveShippingConfigBtn").off("click").on("click", saveShippingConfig);
  $("#addBlacklistBtn").off("click").on("click", addBlacklistFromForm);
  $("#whitelistContactBtn").off("click").on("click", whitelistContactFromForm);
  $("#saveBlacklistNoticeToggleBtn")
    .off("click")
    .on("click", saveBlacklistNoticeVisibility);
  $("#saveSeoMetaBtn").off("click").on("click", saveSeoMetaFromForm);
  $("#resetSeoMetaBtn").off("click").on("click", resetSeoMetaForm);
  $("#deleteSeoMetaBtn")
    .off("click")
    .on("click", function () {
      deleteSeoMetaForPage("");
    });
  $("#seoPageSelect").off("change").on("change", refreshSeoMetaEditor);
  syncBlacklistNoticeToggleUi();

  $(document)
    .off("click", ".delete-customer-btn")
    .on("click", ".delete-customer-btn", function () {
      const customerId = parseInt($(this).data("customerId"), 10) || 0;
      const customerName = ($(this).data("customerName") || "Customer")
        .toString()
        .trim();
      if (customerId <= 0) {
        showNotification("Customer id is invalid.", "warning");
        return;
      }
      deleteSingleCustomer(customerId, customerName || "Customer");
    });

  $(document)
    .off("click", ".blacklist-customer-btn")
    .on("click", ".blacklist-customer-btn", function () {
      const customerId = parseInt($(this).data("customerId"), 10) || 0;
      const customerName = ($(this).data("customerName") || "Customer")
        .toString()
        .trim();
      const customerEmail = ($(this).data("customerEmail") || "")
        .toString()
        .trim();
      const customerPhone = ($(this).data("customerPhone") || "")
        .toString()
        .trim();

      blacklistCustomerByIdentity(
        customerId,
        customerName,
        customerEmail,
        customerPhone,
        false,
      );
    });

  $(document)
    .off("click", ".blacklist-delete-customer-btn")
    .on("click", ".blacklist-delete-customer-btn", function () {
      const customerId = parseInt($(this).data("customerId"), 10) || 0;
      const customerName = ($(this).data("customerName") || "Customer")
        .toString()
        .trim();
      const customerEmail = ($(this).data("customerEmail") || "")
        .toString()
        .trim();
      const customerPhone = ($(this).data("customerPhone") || "")
        .toString()
        .trim();

      blacklistCustomerByIdentity(
        customerId,
        customerName,
        customerEmail,
        customerPhone,
        true,
      );
    });

  $(document)
    .off("click", ".unblacklist-customer-btn")
    .on("click", ".unblacklist-customer-btn", function () {
      const customerEmail = ($(this).data("customerEmail") || "")
        .toString()
        .trim();
      const customerPhone = ($(this).data("customerPhone") || "")
        .toString()
        .trim();

      removeBlacklistByContact(customerEmail, customerPhone);
    });

  $(document)
    .off("click", ".remove-blacklist-btn")
    .on("click", ".remove-blacklist-btn", function () {
      const blacklistId = parseInt($(this).data("blacklistId"), 10) || 0;
      removeBlacklistById(blacklistId);
    });

  $(document)
    .off("click", ".seo-meta-edit-btn")
    .on("click", ".seo-meta-edit-btn", function () {
      const page = ($(this).data("seoPage") || "").toString().trim();
      if (!page) {
        return;
      }

      setSeoPageSelection(page.toLowerCase());
      refreshSeoMetaEditor();
    });

  $(document)
    .off("click", ".seo-meta-delete-btn")
    .on("click", ".seo-meta-delete-btn", function () {
      const page = ($(this).data("seoPage") || "").toString().trim();
      if (!page) {
        return;
      }

      deleteSeoMetaForPage(page);
    });

  $(document)
    .off("click", ".refund-status-btn")
    .on("click", ".refund-status-btn", function () {
      const refundId = parseInt($(this).data("refund-id"), 10) || 0;
      const status = ($(this).data("status") || "pending").toString();
      if (refundId <= 0) return;
      updateRefundStatus(refundId, status);
    });

  $(document).on("click", ".password-toggle", function () {
    const target = $(this).data("target");
    const input = $(target);
    if (!input.length) return;
    input.attr("type", input.attr("type") === "password" ? "text" : "password");
    $(this).toggleClass("bi-eye bi-eye-slash");
  });

  loadDismissedNotificationSignatures();

  const initialProductsLoad = canProductsManage
    ? loadProductsFromJSON()
    : Promise.resolve(false);
  const initialTrashLoad = canProductTrashManage
    ? loadProductTrashData(true)
    : Promise.resolve(false);
  const initialOrdersLoad = canOrdersSummaryData
    ? loadAdminOrdersData(true)
    : Promise.resolve(false);
  const initialCouponsLoad = canCouponsManage
    ? loadCouponsData(true)
    : Promise.resolve(false);
  const initialReviewsLoad = canReviewsManage
    ? loadReviewsData(true)
    : Promise.resolve(false);
  const initialSecurityEventsLoad = canSecurityManage
    ? loadSecurityEvents(true)
    : Promise.resolve(false);

  if (canCouponsManage) {
    initCouponsSection();
  }
  if (canReviewsManage) {
    initReviewsSection();
  }
  if (canSecurityManage) {
    initSecurityEventsSection();
  }
  if (canWebsiteManage) {
    initWebsiteSettings();
  }
  if (canViewersManage) {
    initLiveViewersAnalytics();
  }

  Promise.allSettled([
    Promise.resolve(initialProductsLoad),
    Promise.resolve(initialTrashLoad),
    Promise.resolve(initialOrdersLoad),
    Promise.resolve(initialCouponsLoad),
    Promise.resolve(initialReviewsLoad),
    Promise.resolve(initialSecurityEventsLoad),
  ]).finally(() => {
    if (canEmailManage) {
      initEmailCenter();
    }

    calculateDashboardMetrics();

    if (canOrdersSummaryData) {
      displayRecentOrders();
      displayAllOrders();
      displayAllCustomers();
      renderBlacklistTable();
      renderShippingConfigCard();
      renderAnalyticsSection();
      renderRefundRequests();
      syncBlacklistNoticeToggleUi();
      updateNotifications();
    }
  });

  $("#saveProductBtn")
    .off("click")
    .on("click", function () {
      if (!$("#productForm")[0].checkValidity()) {
        showNotification("Please fill in all required fields", "danger");
        return;
      }

      const productId = $("#productId").val();
      const sectionId = $("#productSection").val();
      const section = allSections.find((s) => s.sectionId === sectionId);
      const existingProduct = productId
        ? productsData.find((p) => p.id === parseInt(productId))
        : null;

      if (!section) {
        showNotification("Please select a valid section", "danger");
        return;
      }

      const resolvedProductId = productId ? parseInt(productId, 10) : nextId++;
      const stockValue = parseInt($("#productStock").val(), 10) || 0;
      const dispatchFallback =
        stockValue > 0 ? "Dispatch in 24-48 hours" : "Pre-order availability";
      const productCode = normalizeProductCodeInput(
        $("#productCode").val(),
        resolvedProductId,
      );

      const duplicateCode = productsData.find(
        (item) =>
          (item?.productCode || "").toString().toUpperCase() === productCode &&
          parseInt(item?.id, 10) !== resolvedProductId,
      );

      if (duplicateCode) {
        showNotification(
          "Product code already exists. Use a unique code.",
          "danger",
        );
        return;
      }

      const productData = {
        id: resolvedProductId,
        name: $("#productName").val(),
        price: parseFloat($("#productPrice").val()) || 0,
        salePrice: parseFloat($("#productSalePrice").val()) || 0,
        stock: stockValue,
        image: $("#productImage").val().trim(),
        video: $("#productVideo").val().trim(),
        productCode,
        warrantyInfo: normalizeProductMetaInput(
          $("#productWarrantyInfo").val(),
          "12-month seller warranty",
          120,
        ),
        dispatchInfo: normalizeProductMetaInput(
          $("#productDispatchInfo").val(),
          dispatchFallback,
          120,
        ),
        description: $("#productDescription").val(),
        movement: $("#productMovement").val(),
        category: section ? section.category : "Uncategorized",
        subcategory: section ? section.subcategory : "General",
        sectionName: section ? section.sectionName : "General",
        sectionId: sectionId,
        page: section ? section.page : "index.php",
        createdAt: existingProduct?.createdAt || new Date().toISOString(),
      };

      if (productData.price <= 0) {
        showNotification("Price must be greater than 0.", "danger");
        return;
      }

      if (productData.salePrice <= 0) {
        productData.salePrice = productData.price;
      }

      if (productId) {
        const index = productsData.findIndex(
          (p) => p.id === parseInt(productId),
        );
        if (index > -1) {
          productsData[index] = productData;
          showNotification("Product updated!", "success");
        }
      } else {
        productsData.push(productData);
        showNotification("Product added!", "success");
      }

      saveProductsToJSON();
      renderProductsTable();
      calculateDashboardMetrics();
      updateNotifications();
      $("#productForm")[0].reset();
      $("#productId").val("");
      $("#productSectionBtn").text("Select Section");
      $("#productSection").val("");
      $("#productMovementBtn").text("Quartz");
      $("#productMovement").val("quartz");
      $("#productCode").val("");
      $("#productWarrantyInfo").val("12-month seller warranty");
      $("#productDispatchInfo").val("Dispatch in 24-48 hours");
      bootstrap.Modal.getInstance(
        document.getElementById("productModal"),
      ).hide();
    });

  $(document)
    .off("click", "#addNewProductBtn")
    .on("click", "#addNewProductBtn", function () {
      $("#productForm")[0].reset();
      $("#productId").val("");
      $("#productSectionBtn").text("Select Section");
      $("#productSection").val("");
      $("#productMovementBtn").text("Quartz");
      $("#productMovement").val("quartz");
      $("#productCode").val("");
      $("#productWarrantyInfo").val("12-month seller warranty");
      $("#productDispatchInfo").val("Dispatch in 24-48 hours");
      $("#productModalLabel").text("Add New Product");
      new bootstrap.Modal(document.getElementById("productModal")).show();
    });

  $("#saveSectionBtn")
    .off("click")
    .on("click", function () {
      const formId = $("#sectionFormId").val();
      const sectionName = $("#sectionName").val().trim();
      const rawId = $("#sectionId").val().trim();
      const page = $("#sectionPage").val().trim() || "index.php";
      const category = $("#sectionCategory").val().trim() || "Uncategorized";
      const subcategory = $("#sectionSubcategory").val().trim() || "General";

      if (!sectionName) {
        showNotification("Section name is required", "danger");
        return;
      }

      const baseId = rawId || slugifySection(sectionName);
      if (!baseId) {
        showNotification("Section ID is required", "danger");
        return;
      }

      if (formId) {
        if (
          formId !== baseId &&
          allSections.some((section) => section.sectionId === baseId)
        ) {
          showNotification("Section ID already exists", "danger");
          return;
        }
        const section = allSections.find((item) => item.sectionId === formId);
        if (section) {
          section.sectionName = sectionName;
          section.sectionId = baseId;
          section.page = page;
          section.category = category;
          section.subcategory = subcategory;
          productsData = productsData.map((product) => {
            if (product.sectionId !== formId) return product;
            return {
              ...product,
              sectionId: baseId,
              sectionName: sectionName,
              category: category,
              subcategory: subcategory,
              page: page,
            };
          });
          showNotification("Section updated!", "success");
        }
      } else {
        const uniqueId = ensureUniqueSectionId(baseId);
        allSections.push({
          sectionName,
          sectionId: uniqueId,
          page,
          category,
          subcategory,
          products: [],
        });
        showNotification("Section added!", "success");
      }

      saveProductsToJSON();
      renderSectionDropdowns();
      renderSectionsTable();
      renderProductsTable();
      updateNotifications();
      resetSectionForm();
    });

  $("#resetSectionBtn")
    .off("click")
    .on("click", function () {
      resetSectionForm();
    });

  $("#saveContactBtn")
    .off("click")
    .on("click", function () {
      const address = $("#siteAddress").val().trim();
      const email = $("#siteEmail").val().trim();
      const phone = $("#sitePhone").val().trim();

      if (!address || !email || !phone) {
        showNotification("Please enter address, email and phone", "danger");
        return;
      }

      adminPostJson(ADMIN_WEBSITE_API, {
        action: "save-contact",
        address,
        email,
        phone,
      })
        .then((result) => {
          applyWebsiteSettingsPayload(result?.payload || null);
          showNotification(
            result?.message || "Contact details updated!",
            "success",
          );
        })
        .catch((error) => {
          showNotification(
            error?.message || "Unable to update contact details.",
            "danger",
          );
        });
    });

  $("#saveBrandBtn")
    .off("click")
    .on("click", function () {
      const name = $("#siteName").val().trim();
      const logo = $("#siteLogo").val().trim();
      const favicon = $("#siteFavicon").val().trim();

      if (!name || !logo || !favicon) {
        showNotification(
          "Please enter website name, logo, and favicon",
          "danger",
        );
        return;
      }

      adminPostJson(ADMIN_WEBSITE_API, {
        action: "save-brand",
        name,
        logo,
        favicon,
      })
        .then((result) => {
          applyWebsiteSettingsPayload(result?.payload || null);
          showNotification(result?.message || "Branding updated!", "success");
          if (typeof window.applyAdminBranding === "function") {
            window.applyAdminBranding();
          }
        })
        .catch((error) => {
          showNotification(
            error?.message || "Unable to update branding.",
            "danger",
          );
        });
    });

  $("#saveSocialBtn")
    .off("click")
    .on("click", function () {
      const id = $("#socialId").val();
      const label = $("#socialLabel").val().trim();
      const url = $("#socialUrl").val().trim();
      const icon = $("#socialIcon").val().trim();

      if (!label || !url) {
        showNotification("Please fill in label and URL", "danger");
        return;
      }

      adminPostJson(ADMIN_WEBSITE_API, {
        action: "save-social",
        id: id ? parseInt(id, 10) : 0,
        label,
        url,
        icon,
      })
        .then((result) => {
          applyWebsiteSettingsPayload(result?.payload || null);
          resetSocialForm();
          showNotification(result?.message || "Social link saved!", "success");
        })
        .catch((error) => {
          showNotification(
            error?.message || "Unable to save social link.",
            "danger",
          );
        });
    });

  $("#resetSocialBtn").on("click", function () {
    resetSocialForm();
  });

  $("#saveStorybookBtn")
    .off("click")
    .on("click", function () {
      const pages = collectStorybookPagesFromEditor();

      adminPostJson(ADMIN_WEBSITE_API, {
        action: "save-storybook",
        pages,
      })
        .then((result) => {
          applyWebsiteSettingsPayload(result?.payload || null);
          showNotification(
            result?.message || "Homepage storybook saved!",
            "success",
          );
        })
        .catch((error) => {
          showNotification(
            error?.message || "Unable to save storybook.",
            "danger",
          );
        });
    });

  $("#resetStorybookBtn")
    .off("click")
    .on("click", function () {
      resetStorybookForm();
    });

  $("#saveTickerBtn")
    .off("click")
    .on("click", function () {
      const enabled = $("#tickerEnabled").is(":checked");
      const messages = collectTickerComposerMessages();

      if (messages.length === 0) {
        showNotification("Please add at least one ticker message", "danger");
        return;
      }

      adminPostJson(ADMIN_WEBSITE_API, {
        action: "save-ticker",
        enabled,
        messages,
      })
        .then((result) => {
          applyWebsiteSettingsPayload(result?.payload || null);
          showNotification(result?.message || "Ticker updated!", "success");
        })
        .catch((error) => {
          showNotification(
            error?.message || "Unable to save ticker.",
            "danger",
          );
        });
    });

  $("#resetTickerBtn")
    .off("click")
    .on("click", function () {
      resetTickerForm();
    });

  $("#saveCollectorsSpeakBtn")
    .off("click")
    .on("click", function () {
      const entries = collectCollectorsSpeakEntries();
      if (!entries.length) {
        showNotification(
          "Add at least one collector entry before saving.",
          "danger",
        );
        return;
      }

      adminPostJson(ADMIN_WEBSITE_API, {
        action: "save-collectors-speak",
        entries,
      })
        .then((result) => {
          applyWebsiteSettingsPayload(result?.payload || null);
          showNotification(
            result?.message || "Collectors Speak saved!",
            "success",
          );
        })
        .catch((error) => {
          showNotification(
            error?.message || "Unable to save Collectors Speak.",
            "danger",
          );
        });
    });

  $("#resetCollectorsSpeakBtn")
    .off("click")
    .on("click", function () {
      resetCollectorsSpeakForm();
    });

  $("#tickerMessages")
    .off("input")
    .on("input", function () {
      renderTickerComposerPreview(collectTickerComposerMessages());
    });

  $("#collectorsSpeakInput")
    .off("input")
    .on("input", function () {
      renderCollectorsSpeakPreview(collectCollectorsSpeakEntries());
    });

  $("#saveFeaturedVideosBtn")
    .off("click")
    .on("click", function () {
      const homeVideo = $("#homeFeatureVideo").val().trim();
      const categoryAVideo = $("#categoryAFeatureVideo").val().trim();

      if (!homeVideo || !categoryAVideo) {
        showNotification(
          "Please add both featured video paths before saving.",
          "danger",
        );
        return;
      }

      adminPostJson(ADMIN_WEBSITE_API, {
        action: "save-feature-videos",
        home_video: homeVideo,
        category_a_video: categoryAVideo,
      })
        .then((result) => {
          applyWebsiteSettingsPayload(result?.payload || null);
          showNotification(
            result?.message || "Featured videos updated!",
            "success",
          );
        })
        .catch((error) => {
          showNotification(
            error?.message || "Unable to save featured videos.",
            "danger",
          );
        });
    });

  $("#saveSliderBtn")
    .off("click")
    .on("click", function () {
      const id = $("#sliderId").val();
      const image = $("#sliderImage").val().trim();
      const alt = $("#sliderAlt").val().trim();
      const label = $("#sliderLabel").val().trim();
      const heading = $("#sliderHeading").val().trim();
      const text = $("#sliderText").val().trim();
      const buttonText = $("#sliderButtonText").val().trim();
      const buttonLink = $("#sliderButtonLink").val().trim();
      const video = $("#sliderVideo").val().trim();

      if (!image || !heading) {
        showNotification("Please add image and heading", "danger");
        return;
      }

      adminPostJson(ADMIN_WEBSITE_API, {
        action: "save-slider",
        id: id ? parseInt(id, 10) : 0,
        image,
        alt,
        label,
        heading,
        text,
        buttonText,
        buttonLink,
        video,
      })
        .then((result) => {
          applyWebsiteSettingsPayload(result?.payload || null);
          resetSliderForm();
          showNotification(result?.message || "Slide saved!", "success");
        })
        .catch((error) => {
          showNotification(error?.message || "Unable to save slide.", "danger");
        });
    });

  $("#resetSliderBtn").on("click", function () {
    resetSliderForm();
  });

  $("#saveAdminEmailBtn").on("click", function () {
    const currentPassword = (
      $("#securityEmailPassword").val() || ""
    ).toString();
    const resetKey = ($("#securityEmailResetKey").val() || "")
      .toString()
      .trim();
    const newEmail = ($("#securityEmailNew").val() || "")
      .toString()
      .trim()
      .toLowerCase();
    const confirmEmail = ($("#securityEmailConfirm").val() || "")
      .toString()
      .trim()
      .toLowerCase();

    if (!currentPassword || !resetKey) {
      showNotification("Password and reset key are required", "danger");
      return;
    }

    if (
      !newEmail ||
      !confirmEmail ||
      newEmail !== confirmEmail ||
      !newEmail.includes("@")
    ) {
      showNotification("Enter a valid matching email", "danger");
      return;
    }

    adminPostJson(ADMIN_SECURITY_API, {
      action: "update-email",
      currentPassword,
      resetKey,
      newEmail,
      confirmEmail,
    })
      .then((result) => {
        syncAdminIdentityUi({
          email: result?.email || newEmail,
        });
        $(
          "#securityEmailPassword, #securityEmailResetKey, #securityEmailNew, #securityEmailConfirm",
        ).val("");
        showNotification(result?.message || "Admin email updated!", "success");
      })
      .catch((error) => {
        showNotification(
          error?.message || "Could not update admin email.",
          "danger",
        );
      });
  });

  $("#saveAdminPasswordBtn").on("click", function () {
    const currentEmail = ($("#securityPasswordEmail").val() || "")
      .toString()
      .trim()
      .toLowerCase();
    const resetKey = ($("#securityPasswordResetKey").val() || "")
      .toString()
      .trim();
    const newPassword = ($("#securityPasswordNew").val() || "").toString();
    const confirmPassword = (
      $("#securityPasswordConfirm").val() || ""
    ).toString();

    if (!currentEmail || !resetKey) {
      showNotification("Email and reset key are required", "danger");
      return;
    }

    if (!newPassword || newPassword !== confirmPassword) {
      showNotification("Passwords do not match", "danger");
      return;
    }

    adminPostJson(ADMIN_SECURITY_API, {
      action: "update-password",
      currentEmail,
      resetKey,
      newPassword,
      confirmPassword,
    })
      .then((result) => {
        $(
          "#securityPasswordResetKey, #securityPasswordNew, #securityPasswordConfirm",
        ).val("");
        showNotification(
          result?.message || "Admin password updated!",
          "success",
        );
      })
      .catch((error) => {
        showNotification(
          error?.message || "Could not update admin password.",
          "danger",
        );
      });
  });

  $("#saveAdminResetKeyBtn").on("click", function () {
    const currentEmail = ($("#securityKeyEmail").val() || "")
      .toString()
      .trim()
      .toLowerCase();
    const currentPassword = ($("#securityKeyPassword").val() || "").toString();
    const newKey = ($("#securityKeyNew").val() || "").toString().trim();
    const confirmKey = ($("#securityKeyConfirm").val() || "").toString().trim();

    if (!currentEmail || !currentPassword) {
      showNotification("Email and password are required", "danger");
      return;
    }

    if (!newKey || newKey !== confirmKey) {
      showNotification("Reset keys do not match", "danger");
      return;
    }

    adminPostJson(ADMIN_SECURITY_API, {
      action: "update-reset-key",
      currentEmail,
      currentPassword,
      newKey,
      confirmKey,
    })
      .then((result) => {
        $("#securityKeyPassword, #securityKeyNew, #securityKeyConfirm").val("");
        showNotification(result?.message || "Reset key updated!", "success");
      })
      .catch((error) => {
        showNotification(
          error?.message || "Could not update reset key.",
          "danger",
        );
      });
  });
});
