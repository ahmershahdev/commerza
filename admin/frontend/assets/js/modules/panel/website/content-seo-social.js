function collectTickerComposerMessages() {
  return ($("#tickerMessages").val() || "")
    .toString()
    .split("\n")
    .map((line) => line.trim())
    .filter(Boolean)
    .slice(0, 20);
}

function renderTickerComposerPreview(messages) {
  const list = Array.isArray(messages)
    ? messages
        .map((line) => (line || "").toString().trim())
        .filter(Boolean)
        .slice(0, 20)
    : [];

  const shell = $("#tickerPreviewList");
  const meta = $("#tickerComposerMeta");

  if (meta.length) {
    const totalChars = list.reduce((sum, line) => sum + line.length, 0);
    meta.text(`${list.length} message(s) | ${totalChars} total characters`);
  }

  if (!shell.length) {
    return;
  }

  if (!list.length) {
    shell.html(
      '<span class="ticker-preview-pill">Add a message to preview</span>',
    );
    return;
  }

  shell.html(
    list
      .map(
        (line) =>
          `<span class="ticker-preview-pill">${escapeHtml(line)}</span>`,
      )
      .join(""),
  );
}

function normalizeCollectorsSpeakEntry(entry) {
  const source = entry && typeof entry === "object" ? entry : {};
  const name = (source.name || "").toString().trim();
  const tagline = (source.tagline || "").toString().trim();
  const quote = (source.quote || "").toString().trim();

  if (!name || !quote) {
    return null;
  }

  return {
    name: name.slice(0, 80),
    tagline: tagline.slice(0, 120),
    quote: quote.slice(0, 500),
  };
}

function normalizeCollectorsSpeakList(list) {
  if (!Array.isArray(list)) {
    return [];
  }

  const normalized = [];
  list.forEach((entry) => {
    const item = normalizeCollectorsSpeakEntry(entry);
    if (!item) {
      return;
    }
    normalized.push(item);
  });

  return normalized.slice(0, 20);
}

function collectorsSpeakTextFromEntries(entries) {
  const normalized = normalizeCollectorsSpeakList(entries);
  if (!normalized.length) {
    return "";
  }

  return normalized
    .map(
      (entry) =>
        `${entry.name} | ${entry.tagline || "Collector"} | ${entry.quote}`,
    )
    .join("\n");
}

function collectCollectorsSpeakEntries() {
  const lines = ($("#collectorsSpeakInput").val() || "")
    .toString()
    .split("\n")
    .map((line) => line.trim())
    .filter(Boolean)
    .slice(0, 20);

  const parsed = lines
    .map((line) => {
      const parts = line.split("|").map((part) => part.trim());
      if (parts.length < 3) {
        return null;
      }

      const name = parts[0] || "";
      const tagline = parts[1] || "Collector";
      const quote = parts.slice(2).join(" | ").trim();
      return normalizeCollectorsSpeakEntry({
        name,
        tagline,
        quote,
      });
    })
    .filter(Boolean);

  return normalizeCollectorsSpeakList(parsed);
}

function renderCollectorsSpeakPreview(entries) {
  const normalized = normalizeCollectorsSpeakList(entries);
  const shell = $("#collectorsSpeakPreview");
  if (!shell.length) {
    return;
  }

  if (!normalized.length) {
    shell.html(
      '<div class="text-secondary small">Preview will appear here as you type.</div>',
    );
    return;
  }

  shell.html(
    normalized
      .map(
        (entry) => `
        <article class="collectors-preview-item">
          <strong>${escapeHtml(entry.name)}</strong>
          <span>${escapeHtml(entry.tagline || "Collector")}</span>
          <p>"${escapeHtml(entry.quote)}"</p>
        </article>
      `,
      )
      .join(""),
  );
}

function getDefaultStorybookPages() {
  return [
    {
      subtitle: "Design Language",
      title: "Built For Modern Legacy",
      body_primary:
        "Every release begins with silhouette drafts, dial proportion studies, and wrist-balance tests so the final watch feels premium in real daily wear.",
      body_secondary:
        "Prototype variants are reviewed under indoor, outdoor, and low-light scenes before any design moves to production.",
      footnote: "Chapter note: precision starts before production.",
    },
    {
      subtitle: "Material and Movement",
      title: "Casework, Crystal, and Caliber Harmony",
      body_primary:
        "Brushed and polished surfaces are tuned together to create visual depth while preserving a clean edge profile and comfortable wrist feel.",
      body_secondary:
        "Lume balance, dial contrast, and movement stability are stress-tested so readability and reliability stay sharp over time.",
      footnote: "Every layer must earn its place.",
    },
    {
      subtitle: "Wrist Presence",
      title: "Designed To Transition Across Moments",
      body_primary:
        "A Commerza watch is styled to move from office hours to evening plans without looking out of place or overdesigned.",
      body_secondary:
        "The goal is repeat wear value: strong identity from distance and refined detailing when seen up close.",
      footnote: "Form and confidence in one profile.",
    },
    {
      subtitle: "Service and Trust",
      title: "Refined Through Real Customer Signals",
      body_primary:
        "Feedback on strap comfort, dial clarity, and case finishing is reviewed each cycle and translated into practical product updates.",
      body_secondary:
        "Packaging quality, dispatch handling, and support response are treated as part of the product, not an afterthought.",
      footnote: "Experience matters beyond the watch itself.",
    },
    {
      subtitle: "Final Note",
      title: "The Next Chapter Starts On Your Wrist",
      body_primary:
        "From first sketch to final shipment, the same premium standard shapes each release in the catalog.",
      body_secondary:
        "Explore references built for precision, comfort, and long-term style confidence in everyday life.",
      footnote: "End of lookbook.",
    },
  ];
}

function normalizeStorybookPageEntry(entry, fallback = {}) {
  const source = entry && typeof entry === "object" ? entry : {};
  const defaults = fallback && typeof fallback === "object" ? fallback : {};

  const subtitle = (source.subtitle || "").toString().trim().slice(0, 120);
  const title = (source.title || "").toString().trim().slice(0, 150);
  const bodyPrimary = (source.body_primary || "")
    .toString()
    .trim()
    .slice(0, 700);
  const bodySecondary = (source.body_secondary || "")
    .toString()
    .trim()
    .slice(0, 700);
  const footnote = (source.footnote || "").toString().trim().slice(0, 180);

  return {
    subtitle: subtitle || (defaults.subtitle || "").toString(),
    title: title || (defaults.title || "").toString(),
    body_primary: bodyPrimary || (defaults.body_primary || "").toString(),
    body_secondary: bodySecondary || (defaults.body_secondary || "").toString(),
    footnote: footnote || (defaults.footnote || "").toString(),
  };
}

function normalizeStorybookPages(list) {
  const defaults = getDefaultStorybookPages();
  if (!Array.isArray(list)) {
    return defaults.map((page) => ({ ...page }));
  }

  return defaults.map((fallbackPage, index) =>
    normalizeStorybookPageEntry(list[index], fallbackPage),
  );
}

function renderStorybookEditor(pages) {
  const normalized = normalizeStorybookPages(pages);

  normalized.forEach((page, index) => {
    const pageNumber = index + 1;
    $(`#storybookPage${pageNumber}Subtitle`).val(page.subtitle || "");
    $(`#storybookPage${pageNumber}Title`).val(page.title || "");
    $(`#storybookPage${pageNumber}BodyPrimary`).val(page.body_primary || "");
    $(`#storybookPage${pageNumber}BodySecondary`).val(
      page.body_secondary || "",
    );
    $(`#storybookPage${pageNumber}Footnote`).val(page.footnote || "");
  });
}

function collectStorybookPagesFromEditor() {
  const pages = [];

  for (let pageNumber = 1; pageNumber <= 5; pageNumber += 1) {
    pages.push({
      subtitle: ($(`#storybookPage${pageNumber}Subtitle`).val() || "")
        .toString()
        .trim(),
      title: ($(`#storybookPage${pageNumber}Title`).val() || "")
        .toString()
        .trim(),
      body_primary: ($(`#storybookPage${pageNumber}BodyPrimary`).val() || "")
        .toString()
        .trim(),
      body_secondary: (
        $(`#storybookPage${pageNumber}BodySecondary`).val() || ""
      )
        .toString()
        .trim(),
      footnote: ($(`#storybookPage${pageNumber}Footnote`).val() || "")
        .toString()
        .trim(),
    });
  }

  return normalizeStorybookPages(pages);
}

function buildDefaultSiteSettings() {
  return {
    brand: {
      name: "COMMERZA",
      logo: "frontend/assets/images/logo/commerza-logo.webp",
      favicon: "frontend/assets/images/favicon/commerza-watches-icon.ico",
    },
    contact: {
      address: "Barrage Colony, HYD, PK",
      email: "commerza.ahmer@gmail.com",
      phone: "+92 314 8396293",
    },
    ticker: {
      enabled: true,
      messages: [
        "Private drop unlocked: signature chronographs now shipping nationwide",
        "Members perk: free premium case with selected limited editions",
        "New arrival: skeleton gold steel collection is now in stock",
        "Private drop unlocked: signature chronographs now shipping nationwide",
        "Members perk: free premium case with selected limited editions",
        "New arrival: skeleton gold steel collection is now in stock",
      ],
    },
    collectorsSpeak: [
      {
        name: "A. Khan",
        tagline: "Lahore",
        quote:
          "The Skeleton Gold Steel feels premium in every detail. The movement is smooth and the dial steals attention.",
      },
      {
        name: "S. Malik",
        tagline: "Karachi",
        quote:
          "I've worn the Black Gold Dial daily. It keeps time accurately and looks incredible under low light.",
      },
      {
        name: "R. Ahmed",
        tagline: "Islamabad",
        quote:
          "Fast shipping and stellar packaging. The leather strap quality is beyond what I expected.",
      },
      {
        name: "M. Hassan",
        tagline: "Rawalpindi",
        quote:
          "The automatic movement is mesmerizing. I can watch it for hours through the exhibition case back.",
      },
      {
        name: "F. Ali",
        tagline: "Multan",
        quote:
          "Excellent build quality and attention to detail. The weight feels perfect on the wrist.",
      },
      {
        name: "Z. Iqbal",
        tagline: "Faisalabad",
        quote:
          "Customer service is outstanding. They helped me choose the perfect watch for my collection.",
      },
      {
        name: "N. Raza",
        tagline: "Peshawar",
        quote:
          "The luminous hands are perfect for night visibility. Absolutely love the craftsmanship.",
      },
      {
        name: "H. Shah",
        tagline: "Quetta",
        quote:
          "Premium materials and flawless finishing. This watch rivals luxury brands at triple the price.",
      },
    ],
    socialLinks: [
      {
        id: 1,
        label: "Facebook",
        url: "https://www.facebook.com/commerza.ahmer",
        icon: "bi bi-facebook",
      },
      {
        id: 2,
        label: "X",
        url: "https://x.com/commerza_ahmer",
        icon: "bi bi-twitter",
      },
      {
        id: 3,
        label: "Instagram",
        url: "https://www.instagram.com/commerza.ahmer",
        icon: "bi bi-instagram",
      },
    ],
    sliderImages: [
      {
        id: 1,
        image: "frontend/assets/images/slider/watch-banner-chronograph.webp",
        alt: "luxury chronograph watch banner premium collection",
        label: "Premium Collection",
        heading: "Chronograph Precision",
        text: "Engineered movements with dual finish cases",
        buttonText: "Explore Now",
        buttonLink: "shop-category-a.php",
      },
      {
        id: 2,
        image: "frontend/assets/images/slider/watch-banner-collection.webp",
        alt: "complete watch collection showcase all styles",
        label: "Complete Series",
        heading: "Every Style, One Place",
        text: "From minimalist to bold statement pieces",
        buttonText: "View Collection",
        buttonLink: "shop-category-b.php",
      },
      {
        id: 3,
        image: "frontend/assets/images/slider/watch-banner-premium.webp",
        alt: "premium watches exclusive luxury timepieces",
        label: "Exclusive Launch",
        heading: "Limited Editions",
        text: "Hand assembled luxury with skeleton dials",
        buttonText: "Shop Limited",
        buttonLink: "shop-category-b.php",
      },
    ],
    featuredVideos: {
      home: "frontend/assets/videos/slider/steel_watch_1.mp4",
      categoryA:
        "frontend/assets/videos/products/smart/automatic_watches_carousel.mp4",
    },
    storybook: {
      pages: getDefaultStorybookPages(),
    },
    pageMeta: [],
  };
}

function loadSiteSettings() {
  const defaults = buildDefaultSiteSettings();
  const stored = sessionStorage.getItem(SITE_SETTINGS_KEY);
  if (!stored) return defaults;

  try {
    const parsed = JSON.parse(stored);
    return {
      ...defaults,
      ...parsed,
      brand: { ...defaults.brand, ...(parsed.brand || {}) },
      contact: { ...defaults.contact, ...(parsed.contact || {}) },
      ticker: {
        ...defaults.ticker,
        ...(parsed.ticker || {}),
        messages:
          Array.isArray(parsed.ticker?.messages) &&
          parsed.ticker.messages.length
            ? parsed.ticker.messages
            : defaults.ticker.messages,
      },
      collectorsSpeak: normalizeCollectorsSpeakList(
        Array.isArray(parsed.collectorsSpeak)
          ? parsed.collectorsSpeak
          : defaults.collectorsSpeak,
      ),
      socialLinks: Array.isArray(parsed.socialLinks)
        ? parsed.socialLinks
        : defaults.socialLinks,
      sliderImages: Array.isArray(parsed.sliderImages)
        ? parsed.sliderImages
        : defaults.sliderImages,
      featuredVideos: {
        ...defaults.featuredVideos,
        ...(parsed.featuredVideos || {}),
      },
      storybook: {
        pages: normalizeStorybookPages(parsed.storybook?.pages),
      },
      pageMeta: normalizeSeoPageMetaList(
        Array.isArray(parsed.pageMeta) ? parsed.pageMeta : defaults.pageMeta,
      ),
    };
  } catch (error) {
    console.warn("Invalid site settings, using defaults");
    return defaults;
  }
}

function saveSiteSettings() {
  sessionStorage.setItem(SITE_SETTINGS_KEY, JSON.stringify(siteSettings));
}

function applyWebsiteSettingsPayload(payload) {
  const defaults = buildDefaultSiteSettings();
  const source = payload && typeof payload === "object" ? payload : {};

  siteSettings = {
    ...defaults,
    ...source,
    brand: { ...defaults.brand, ...(source.brand || {}) },
    contact: { ...defaults.contact, ...(source.contact || {}) },
    ticker: {
      ...defaults.ticker,
      ...(source.ticker || {}),
      messages:
        Array.isArray(source.ticker?.messages) && source.ticker.messages.length
          ? source.ticker.messages
          : defaults.ticker.messages,
    },
    collectorsSpeak: normalizeCollectorsSpeakList(
      Array.isArray(source.collectorsSpeak)
        ? source.collectorsSpeak
        : defaults.collectorsSpeak,
    ),
    socialLinks: Array.isArray(source.socialLinks)
      ? source.socialLinks
      : defaults.socialLinks,
    sliderImages: Array.isArray(source.sliderImages)
      ? source.sliderImages
      : defaults.sliderImages,
    featuredVideos: {
      ...defaults.featuredVideos,
      ...(source.featuredVideos || {}),
    },
    storybook: {
      pages: normalizeStorybookPages(source.storybook?.pages),
    },
    pageMeta: normalizeSeoPageMetaList(
      Array.isArray(source.pageMeta) ? source.pageMeta : defaults.pageMeta,
    ),
  };

  nextSocialId =
    Math.max(0, ...siteSettings.socialLinks.map((link) => link.id || 0)) + 1;
  nextSliderId =
    Math.max(0, ...siteSettings.sliderImages.map((item) => item.id || 0)) + 1;

  $("#siteAddress").val(siteSettings.contact.address || "");
  $("#siteEmail").val(siteSettings.contact.email || "");
  $("#sitePhone").val(siteSettings.contact.phone || "");
  $("#siteName").val(siteSettings.brand?.name || "");
  $("#siteLogo").val(siteSettings.brand?.logo || "");
  $("#siteFavicon").val(siteSettings.brand?.favicon || "");

  $("#tickerEnabled").prop("checked", siteSettings.ticker?.enabled !== false);
  $("#tickerMessages").val((siteSettings.ticker?.messages || []).join("\n"));
  $("#collectorsSpeakInput").val(
    collectorsSpeakTextFromEntries(siteSettings.collectorsSpeak || []),
  );
  $("#homeFeatureVideo").val(siteSettings.featuredVideos?.home || "");
  $("#categoryAFeatureVideo").val(siteSettings.featuredVideos?.categoryA || "");
  renderStorybookEditor(siteSettings.storybook?.pages || []);

  renderTickerComposerPreview(siteSettings.ticker?.messages || []);
  renderCollectorsSpeakPreview(siteSettings.collectorsSpeak || []);

  renderSeoPageOptions();
  renderSeoMetaTable();
  refreshSeoMetaEditor();
  renderSocialLinksTable();
  renderSliderTable();
  saveSiteSettings();

  if (typeof window.applyAdminBranding === "function") {
    window.applyAdminBranding();
  }
}

async function loadWebsiteSettingsFromApi(silent = false) {
  try {
    const response = await fetch(`${ADMIN_WEBSITE_API}?action=get`, {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store",
    });

    const result = await response.json();
    if (!response.ok || !result?.ok) {
      throw new Error(result?.message || "Unable to load website settings.");
    }

    applyWebsiteSettingsPayload(result?.payload || null);
    return true;
  } catch (error) {
    if (!silent) {
      showNotification(
        error?.message || "Unable to load website settings.",
        "danger",
      );
    }
    return false;
  }
}

function initWebsiteSettings() {
  if (!$("#websiteSection").length) return;

  applyWebsiteSettingsPayload(loadSiteSettings());
  loadWebsiteSettingsFromApi(true);
}

function resetTickerForm() {
  const defaults = buildDefaultSiteSettings();
  $("#tickerEnabled").prop("checked", defaults.ticker.enabled);
  $("#tickerMessages").val(defaults.ticker.messages.join("\n"));
  renderTickerComposerPreview(defaults.ticker.messages);
}

function resetCollectorsSpeakForm() {
  const defaults = buildDefaultSiteSettings();
  const entries = normalizeCollectorsSpeakList(defaults.collectorsSpeak || []);
  $("#collectorsSpeakInput").val(collectorsSpeakTextFromEntries(entries));
  renderCollectorsSpeakPreview(entries);
}

function resetStorybookForm() {
  const defaults = buildDefaultSiteSettings();
  renderStorybookEditor(defaults.storybook?.pages || []);
}

function normalizeSeoPageMetaEntry(entry) {
  const source = entry && typeof entry === "object" ? entry : {};
  return {
    page: (source.page || "").toString().trim().toLowerCase(),
    meta_title: (source.meta_title || "").toString().trim(),
    meta_description: (source.meta_description || "").toString().trim(),
    canonical_url: (source.canonical_url || "").toString().trim(),
    og_title: (source.og_title || "").toString().trim(),
    og_description: (source.og_description || "").toString().trim(),
    og_image: (source.og_image || "").toString().trim(),
    json_ld: (source.json_ld || "").toString().trim(),
    updated_at: (source.updated_at || "").toString().trim(),
  };
}

function normalizeSeoPageMetaList(list) {
  if (!Array.isArray(list)) {
    return [];
  }

  const unique = [];
  const seen = new Set();
  list.forEach((entry) => {
    const normalized = normalizeSeoPageMetaEntry(entry);
    if (!normalized.page || seen.has(normalized.page)) {
      return;
    }

    seen.add(normalized.page);
    unique.push(normalized);
  });

  return unique;
}

function getSeoPageLabel(pageKey) {
  const key = (pageKey || "").toString().trim().toLowerCase();
  const match = ADMIN_PAGES.find(
    (page) => (page.id || "").toString().toLowerCase() === key,
  );

  return match ? match.label : key;
}

function renderSeoPageOptions() {
  const input = $("#seoPageSelect");
  const menu = $("#seoPageSelectMenu");

  if (!input.length || !menu.length) {
    return;
  }

  const currentValue = (input.val() || "").toString().trim().toLowerCase();

  menu.empty();
  const optionsMarkup = ADMIN_PAGES.map((page) => {
    const pageId = (page.id || "").toString().trim();
    if (!pageId) {
      return "";
    }

    const label = (page.label || pageId).toString();
    const optionLabel = `${label} (${pageId})`;
    return `<li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="seoPageSelect" data-value="${escapeHtml(
      pageId,
    )}" data-label="${escapeHtml(optionLabel)}">${escapeHtml(label)} <small class="text-secondary d-block">${escapeHtml(pageId)}</small></a></li>`;
  })
    .filter(Boolean)
    .join("");

  menu.html(optionsMarkup);

  const fallback = (ADMIN_PAGES[0]?.id || "").toString();
  const nextValue = currentValue || fallback;
  if (nextValue || fallback) {
    setSeoPageSelection(nextValue || fallback);
  }
}

function setSeoPageSelection(pageKey) {
  const normalized = (pageKey || "").toString().trim().toLowerCase();
  if (!normalized) {
    return;
  }

  const label = getSeoPageLabel(normalized);
  setAdminDropdownSelection(
    "seoPageSelect",
    normalized,
    `${label} (${normalized})`,
  );
}

function getSelectedSeoPage() {
  const inputValue = ($("#seoPageSelect").val() || "").toString().trim();
  if (inputValue) {
    return inputValue.toLowerCase();
  }

  return ((ADMIN_PAGES[0] && ADMIN_PAGES[0].id) || "").toString().toLowerCase();
}

function setSeoFormValues(entry) {
  const source = normalizeSeoPageMetaEntry(entry);
  $("#seoMetaTitleInput").val(source.meta_title || "");
  $("#seoMetaDescriptionInput").val(source.meta_description || "");
  $("#seoCanonicalInput").val(source.canonical_url || "");
  $("#seoOgTitleInput").val(source.og_title || "");
  $("#seoOgDescriptionInput").val(source.og_description || "");
  $("#seoOgImageInput").val(source.og_image || "");

  let jsonLd = source.json_ld || "";
  if (jsonLd) {
    try {
      jsonLd = JSON.stringify(JSON.parse(jsonLd), null, 2);
    } catch (error) {
      jsonLd = source.json_ld || "";
    }
  }

  $("#seoJsonLdInput").val(jsonLd);
}

function refreshSeoMetaEditor() {
  if (!$("#seoPageSelect").length) {
    return;
  }

  const page = getSelectedSeoPage();
  const entries = normalizeSeoPageMetaList(siteSettings?.pageMeta || []);
  const currentEntry = entries.find((entry) => entry.page === page) || null;

  setSeoFormValues(currentEntry || {});

  const pageLabel = getSeoPageLabel(page);
  const message = currentEntry
    ? `Editing SEO metadata for ${pageLabel}. Last update: ${formatDateTime(currentEntry.updated_at || "") || "recent"}.`
    : `No saved SEO metadata for ${pageLabel}. Fill details and click Save SEO Meta.`;

  $("#seoMetaPreview").text(message);
}

function renderSeoMetaTable() {
  const tbody = $("#seoMetaTable tbody");
  if (!tbody.length) {
    return;
  }

  const rows = normalizeSeoPageMetaList(siteSettings?.pageMeta || []).sort(
    (a, b) => a.page.localeCompare(b.page),
  );

  tbody.empty();
  if (!rows.length) {
    tbody.append(
      '<tr><td colspan="3" class="text-center py-4 text-secondary">No page metadata configured.</td></tr>',
    );
    return;
  }

  rows.forEach((entry) => {
    const page = escapeHtml(entry.page || "");
    const pageLabel = escapeHtml(getSeoPageLabel(entry.page || ""));
    const metaTitle = escapeHtml(entry.meta_title || "-");
    tbody.append(`
      <tr class="border-bottom border-secondary">
        <td class="ps-4 py-3 text-light">
          <div class="fw-semibold">${pageLabel}</div>
          <small class="text-secondary">${page}</small>
        </td>
        <td class="py-3 text-secondary small">${metaTitle}</td>
        <td class="pe-4 py-3">
          <button class="btn btn-sm btn-outline-orange me-1 seo-meta-edit-btn" data-seo-page="${page}"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-outline-danger seo-meta-delete-btn" data-seo-page="${page}"><i class="bi bi-trash"></i></button>
        </td>
      </tr>
    `);
  });
}

function getSeoMetaPayloadFromForm() {
  return {
    page: getSelectedSeoPage(),
    meta_title: ($("#seoMetaTitleInput").val() || "").toString().trim(),
    meta_description: ($("#seoMetaDescriptionInput").val() || "")
      .toString()
      .trim(),
    canonical_url: ($("#seoCanonicalInput").val() || "").toString().trim(),
    og_title: ($("#seoOgTitleInput").val() || "").toString().trim(),
    og_description: ($("#seoOgDescriptionInput").val() || "").toString().trim(),
    og_image: ($("#seoOgImageInput").val() || "").toString().trim(),
    json_ld: ($("#seoJsonLdInput").val() || "").toString().trim(),
  };
}

async function saveSeoMetaFromForm() {
  const payload = getSeoMetaPayloadFromForm();
  if (!payload.page) {
    showNotification("Select a page first.", "warning");
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_WEBSITE_API, {
      action: "save-page-meta",
      ...payload,
    });

    applyWebsiteSettingsPayload(result?.payload || null);
    setSeoPageSelection(payload.page);
    refreshSeoMetaEditor();
    showNotification(result?.message || "SEO metadata saved.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to save SEO metadata.",
      "danger",
    );
  }
}

async function deleteSeoMetaForPage(pageKey = "") {
  const page = (pageKey || getSelectedSeoPage())
    .toString()
    .trim()
    .toLowerCase();
  if (!page) {
    showNotification("Select a page first.", "warning");
    return;
  }

  const pageLabel = getSeoPageLabel(page);
  const confirmed = await showCustomConfirmDialog(
    `Delete SEO metadata for ${pageLabel}?`,
    "Delete SEO Meta",
  );

  if (!confirmed) {
    return;
  }

  try {
    const result = await adminPostJson(ADMIN_WEBSITE_API, {
      action: "delete-page-meta",
      page,
    });

    applyWebsiteSettingsPayload(result?.payload || null);
    setSeoPageSelection(page);
    refreshSeoMetaEditor();
    showNotification(result?.message || "SEO metadata deleted.", "success");
  } catch (error) {
    showNotification(
      error?.message || "Unable to delete SEO metadata.",
      "danger",
    );
  }
}

function resetSeoMetaForm() {
  refreshSeoMetaEditor();
}

function renderSocialIconPreview(iconValue) {
  const icon = (iconValue || "").toString().trim();
  if (!icon) {
    return '<span class="text-secondary small">-</span>';
  }

  if (/^bi\s+bi-[a-z0-9-]+$/i.test(icon)) {
    return `<i class="${escapeHtml(icon)} text-orange"></i>`;
  }

  const previewPath = resolveAdminImagePath(icon);
  return `<img src="${escapeHtml(previewPath)}" alt="Icon" style="width: 22px; height: 22px; object-fit: contain;" onerror="this.style.display='none'; this.nextElementSibling && (this.nextElementSibling.style.display='inline');"><span class="text-secondary small" style="display:none;">Invalid</span>`;
}

function renderSocialLinksTable() {
  const tbody = $("#socialLinksTable tbody");
  if (tbody.length === 0) return;
  tbody.empty();

  if (!siteSettings.socialLinks || siteSettings.socialLinks.length === 0) {
    tbody.append(
      '<tr><td colspan="4" class="text-center py-4 text-secondary">No social links added</td></tr>',
    );
    return;
  }

  siteSettings.socialLinks.forEach((link) => {
    const safeLabel = escapeHtml(link.label || "");
    const safeUrl = escapeHtml(link.url || "");
    const iconPreview = renderSocialIconPreview(link.icon || "");
    tbody.append(`
            <tr class="border-bottom border-secondary">
          <td class="ps-4 py-3 text-light fw-semibold">${safeLabel}</td>
          <td class="py-3 text-secondary small">${safeUrl}</td>
          <td class="py-3">${iconPreview}</td>
                <td class="pe-4 py-3">
                    <button class="btn btn-sm btn-outline-orange me-1" onclick="editSocialLink(${link.id})"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteSocialLink(${link.id})"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
        `);
  });
}

function editSocialLink(id) {
  const link = siteSettings.socialLinks.find((item) => item.id === id);
  if (!link) return;
  $("#socialId").val(link.id);
  $("#socialLabel").val(link.label);
  $("#socialUrl").val(link.url);
  $("#socialIcon").val(link.icon);
  $("#saveSocialBtn").html('<i class="bi bi-save2 me-1"></i>Update Social');
}

async function deleteSocialLink(id) {
  const confirmed = await showCustomConfirmDialog(
    "Delete this social link?",
    "Delete Social Link",
  );
  if (!confirmed) return;

  adminPostJson(ADMIN_WEBSITE_API, {
    action: "delete-social",
    id,
  })
    .then((result) => {
      applyWebsiteSettingsPayload(result?.payload || null);
      resetSocialForm();
      showNotification(result?.message || "Social link deleted!", "success");
    })
    .catch((error) => {
      showNotification(
        error?.message || "Unable to delete social link.",
        "danger",
      );
    });
}

function resetSocialForm() {
  $("#socialId").val("");
  $("#socialLabel").val("");
  $("#socialUrl").val("");
  $("#socialIcon").val("");
  $("#saveSocialBtn").html('<i class="bi bi-plus-circle me-1"></i>Add Social');
}

function resolveAdminImagePath(path) {
  if (!path) return "";
  if (path.startsWith("http")) return path;
  const cleaned = path.replace(/^\.\//, "");
  return `../../${cleaned}`;
}

function renderSliderTable() {
  const tbody = $("#sliderTable tbody");
  if (tbody.length === 0) return;
  tbody.empty();

  if (!siteSettings.sliderImages || siteSettings.sliderImages.length === 0) {
    tbody.append(
      '<tr><td colspan="4" class="text-center py-4 text-secondary">No slides added</td></tr>',
    );
    return;
  }

  siteSettings.sliderImages.forEach((item) => {
    const preview = resolveAdminImagePath(item.image);
    const safeHeading = escapeHtml(item.heading || "Untitled");
    const safeAlt = escapeHtml(item.alt || "Slide");
    const safeButtonText = escapeHtml(item.buttonText || "CTA");
    const safeButtonLink = escapeHtml(item.buttonLink || "#");
    const videoBadge = item.video
      ? '<span class="badge bg-secondary ms-2">Video</span>'
      : "";
    tbody.append(`
            <tr class="border-bottom border-secondary">
                <td class="ps-4 py-3"><img src="${preview}" alt="${safeAlt}" style="width: 90px; height: 50px; object-fit: cover; border-radius: 6px;" onerror="this.src='assets/images/products/placeholder.webp'"></td>
                <td class="py-3 text-light fw-semibold">${safeHeading}${videoBadge}</td>
                <td class="py-3 text-secondary small">${safeButtonText} -> ${safeButtonLink}</td>
                <td class="pe-4 py-3">
                    <button class="btn btn-sm btn-outline-orange me-1" onclick="editSlider(${item.id})"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteSlider(${item.id})"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
        `);
  });
}

function editSlider(id) {
  const item = siteSettings.sliderImages.find((slide) => slide.id === id);
  if (!item) return;
  $("#sliderId").val(item.id);
  $("#sliderImage").val(item.image);
  $("#sliderAlt").val(item.alt || "");
  $("#sliderLabel").val(item.label || "");
  $("#sliderHeading").val(item.heading || "");
  $("#sliderText").val(item.text || "");
  $("#sliderButtonText").val(item.buttonText || "");
  $("#sliderButtonLink").val(item.buttonLink || "");
  $("#sliderVideo").val(item.video || "");
  $("#saveSliderBtn").html('<i class="bi bi-save2 me-1"></i>Update Slide');
}

async function deleteSlider(id) {
  const confirmed = await showCustomConfirmDialog(
    "Delete this slide?",
    "Delete Slide",
  );
  if (!confirmed) return;

  adminPostJson(ADMIN_WEBSITE_API, {
    action: "delete-slider",
    id,
  })
    .then((result) => {
      applyWebsiteSettingsPayload(result?.payload || null);
      resetSliderForm();
      showNotification(result?.message || "Slide deleted!", "success");
    })
    .catch((error) => {
      showNotification(error?.message || "Unable to delete slide.", "danger");
    });
}

function resetSliderForm() {
  $("#sliderId").val("");
  $("#sliderImage").val("");
  $("#sliderAlt").val("");
  $("#sliderLabel").val("");
  $("#sliderHeading").val("");
  $("#sliderText").val("");
  $("#sliderButtonText").val("");
  $("#sliderButtonLink").val("");
  $("#sliderVideo").val("");
  $("#saveSliderBtn").html('<i class="bi bi-plus-circle me-1"></i>Add Slide');
}

function getSuppressedEmails() {
  const list = readJsonStorage(EMAIL_SUPPRESSED_KEY, []);
  if (!Array.isArray(list)) return new Set();
  return new Set(
    list.map((email) => normalizeEmailValue(email)).filter(Boolean),
  );
}

function saveSuppressedEmails(set) {
  sessionStorage.setItem(EMAIL_SUPPRESSED_KEY, JSON.stringify(Array.from(set)));
}
