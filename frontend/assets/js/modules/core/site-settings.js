function getSiteSettings() {
  if (
    window.CommerzaSiteSettings &&
    typeof window.CommerzaSiteSettings === "object"
  ) {
    return window.CommerzaSiteSettings;
  }

  const inlineData = document.getElementById("commerzaSiteSettingsData");
  if (!inlineData) {
    return null;
  }

  try {
    const parsed = JSON.parse(inlineData.textContent || "{}");
    return parsed && typeof parsed === "object" ? parsed : null;
  } catch (error) {
    return null;
  }
}

function escapeHtml(value) {
  return (value || "")
    .toString()
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function sanitizeRelativeAssetPath(value) {
  const raw = (value || "").toString().trim();
  if (!raw) return "";
  if (raw.includes("..") || raw.includes("\\")) return "";
  if (!/^[a-zA-Z0-9/_\-.]+$/.test(raw)) return "";
  return raw;
}

function sanitizeHttpUrl(value) {
  const raw = (value || "").toString().trim();
  if (!raw) return "";

  if (!/^https?:\/\//i.test(raw)) {
    return "";
  }

  try {
    const parsed = new URL(raw);
    if (parsed.protocol === "http:" || parsed.protocol === "https:") {
      return parsed.href;
    }
  } catch (error) {
    return "";
  }

  return "";
}

function sanitizeMediaSource(value) {
  const asHttp = sanitizeHttpUrl(value);
  if (asHttp) {
    return asHttp;
  }

  return sanitizeRelativeAssetPath(value);
}

function sanitizeLinkUrl(value) {
  const asHttp = sanitizeHttpUrl(value);
  if (asHttp) {
    return asHttp;
  }

  const raw = (value || "").toString().trim();
  if (!raw) {
    return "";
  }

  if (raw.includes("..") || raw.includes("\\")) {
    return "";
  }

  if (/^javascript:/i.test(raw)) {
    return "";
  }

  if (!/^[a-zA-Z0-9/_\-.?#=&%]+$/.test(raw)) {
    return "";
  }

  return raw;
}

function sanitizeIconClass(value) {
  const raw = (value || "").toString().trim();
  if (/^bi\s+bi-[a-z0-9-]+$/i.test(raw)) {
    return raw;
  }

  return "bi bi-link-45deg";
}

function applyBrandSettings(brand) {
  if (!brand) return;
  const name = (brand.name || "").trim();
  const logo = sanitizeMediaSource(brand.logo || "");
  const favicon = sanitizeMediaSource(brand.favicon || "");

  if (name) {
    updateMetaForBrand(name);
    replaceBrandTextNodes(document.body, name);
  }

  if (name) {
    document.querySelectorAll(".brand-text").forEach((node) => {
      node.textContent = name;
    });
  }

  if (logo) {
    document
      .querySelectorAll(".navbar-logo, .offcanvas-logo")
      .forEach((img) => {
        img.src = logo;
        if (name) {
          img.alt = `${name} Logo`;
        }
      });
  } else if (name) {
    document
      .querySelectorAll(".navbar-logo, .offcanvas-logo")
      .forEach((img) => {
        if (!img.alt || img.alt.toLowerCase().includes("commerza")) {
          img.alt = `${name} Logo`;
        }
      });
  }

  if (favicon) {
    const links = document.querySelectorAll(
      'link[rel="icon"], link[rel="shortcut icon"]',
    );
    if (links.length) {
      links.forEach((link) => {
        link.href = favicon;
      });
    } else if (document.head) {
      const link = document.createElement("link");
      link.rel = "icon";
      link.href = favicon;
      document.head.appendChild(link);
    }
  }
}

function replaceStandaloneBrandToken(value, brandName) {
  if (!value || !brandName) {
    return (value || "").toString();
  }

  return value
    .toString()
    .replace(
      /(^|[^A-Za-z0-9._%+-])(Commerza)(?=$|[^A-Za-z0-9._%+-])/gi,
      function (_, prefix) {
        return `${prefix}${brandName}`;
      },
    );
}

function updateMetaForBrand(brandName) {
  if (!brandName) return;
  const replaceBrand = (value) =>
    replaceStandaloneBrandToken(value || "", brandName);

  if (document.title) {
    document.title = replaceBrand(document.title);
  }

  const selectors = [
    'meta[name="description"]',
    'meta[property="og:title"]',
    'meta[property="og:description"]',
    'meta[name="twitter:title"]',
    'meta[name="twitter:description"]',
  ];

  document.querySelectorAll(selectors.join(",")).forEach((meta) => {
    const content = meta.getAttribute("content") || "";
    if (!content) return;
    meta.setAttribute("content", replaceBrand(content));
  });
}

function replaceBrandTextNodes(root, brandName) {
  if (!root || !brandName) return;
  const skipTags = new Set([
    "SCRIPT",
    "STYLE",
    "NOSCRIPT",
    "TEXTAREA",
    "INPUT",
  ]);
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
  let node = walker.nextNode();

  while (node) {
    const parent = node.parentElement;
    if (!parent || !skipTags.has(parent.tagName)) {
      const currentValue = (node.nodeValue || "").toString();
      if (/commerza/i.test(currentValue)) {
        const replacedValue = replaceStandaloneBrandToken(
          currentValue,
          brandName,
        );
        if (replacedValue !== currentValue) {
          node.nodeValue = replacedValue;
        }
      }
    }
    node = walker.nextNode();
  }
}

function applyContactSettings(contact) {
  if (!contact) return;
  const email = (contact.email || "").toString().trim();
  const phone = (contact.phone || "").toString().trim();
  const address = contact.address;

  if (address) {
    const addressEl = document.getElementById("contactAddress");
    if (addressEl) {
      addressEl.textContent = address;
    }
  }

  if (email) {
    const emailEl = document.getElementById("contactEmail");
    if (emailEl) {
      emailEl.textContent = email;
      if (emailEl.tagName === "A") {
        emailEl.setAttribute("href", `mailto:${email}`);
      }
    }
  }

  if (phone) {
    const phoneEl = document.getElementById("contactPhone");
    if (phoneEl) {
      phoneEl.textContent = phone;
    }
  }

  if (email) {
    document.querySelectorAll("p.footer-text").forEach((node) => {
      const text = node.textContent.trim();
      if (!text) return;
      if (text.toLowerCase().includes("email")) {
        node.textContent = `Email: ${email}`;
      } else if (text.includes("@")) {
        node.textContent = email;
      }
    });

    document.querySelectorAll('a[href^="mailto:"]').forEach((anchor) => {
      const href = (anchor.getAttribute("href") || "").trim();
      const match = href.match(/^mailto:([^?]*)(\?.*)?$/i);
      if (!match) {
        return;
      }

      const queryString = match[2] || "";
      anchor.setAttribute("href", `mailto:${email}${queryString}`);

      const text = (anchor.textContent || "").trim();
      if (text.includes("@")) {
        anchor.textContent = email;
      }
    });
  }

  if (phone) {
    document.querySelectorAll("p.footer-text").forEach((node) => {
      const text = node.textContent.trim();
      if (!text) return;
      if (text.toLowerCase().includes("phone")) {
        node.textContent = `Phone: ${phone}`;
      } else if (/\+?\d[\d\s-]{7,}/.test(text)) {
        node.textContent = phone;
      }
    });
  }
}

function applySocialSettings(socialLinks) {
  if (!Array.isArray(socialLinks) || socialLinks.length === 0) return;
  const getSocialLabel = (link) => {
    const explicit = (link?.label || link?.name || "").trim();
    if (explicit) return explicit;
    const url = (link?.url || "").toLowerCase();
    if (url.includes("facebook")) return "Facebook";
    if (url.includes("instagram")) return "Instagram";
    if (url.includes("x.com") || url.includes("twitter")) return "X";
    if (url.includes("linkedin")) return "LinkedIn";
    if (url.includes("youtube")) return "YouTube";
    return "Social link";
  };

  const html = socialLinks
    .map((link) => {
      const safeUrl = sanitizeLinkUrl(link?.url || "");
      if (!safeUrl) {
        return "";
      }

      const safeLabel = escapeHtml(getSocialLabel(link));
      const safeIcon = sanitizeIconClass(link?.icon || "");

      return `
        <a href="${escapeHtml(safeUrl)}" target="_blank" rel="noopener" aria-label="Follow on ${safeLabel}">
            <i class="${escapeHtml(safeIcon)}"></i>
        </a>
      `;
    })
    .filter(Boolean)
    .join("");

  document.querySelectorAll(".social-links").forEach((container) => {
    container.innerHTML = html;
  });
}

function applySliderSettings(sliderImages) {
  if (!Array.isArray(sliderImages) || sliderImages.length === 0) return;
  const carousel = document.querySelector("#carouselExampleIndicators");
  if (!carousel) return;

  const indicators = carousel.querySelector(".carousel-indicators");
  const inner = carousel.querySelector(".carousel-inner");
  if (!indicators || !inner) return;

  indicators.innerHTML = sliderImages
    .map(
      (slide, index) => `
        <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="${index}" class="${index === 0 ? "active" : ""}" aria-current="${index === 0 ? "true" : "false"}" aria-label="Slide ${index + 1}"></button>
    `,
    )
    .join("");

  inner.innerHTML = sliderImages
    .map((slide, index) => {
      const safeImage = sanitizeMediaSource(slide?.image || "");
      if (!safeImage) {
        return "";
      }

      const safeLabelText = escapeHtml(slide?.label || "");
      const safeTextBody = escapeHtml(slide?.text || "");
      const safeHeading = escapeHtml(slide?.heading || "");
      const safeAlt = escapeHtml(slide?.alt || "carousel image");
      const safeButtonText = escapeHtml(slide?.buttonText || "");
      const safeButtonLink = sanitizeLinkUrl(slide?.buttonLink || "");

      const label = slide.label
        ? `<span class="carousel-label">${safeLabelText}</span>`
        : "";
      const text = slide.text
        ? `<p class="carousel-text">${safeTextBody}</p>`
        : "";
      const button =
        safeButtonText && safeButtonLink
          ? `<a href="${escapeHtml(safeButtonLink)}" class="btn carousel-btn">${safeButtonText}</a>`
          : "";

      return `
            <div class="carousel-item ${index === 0 ? "active" : ""}">
                <img src="${escapeHtml(safeImage)}" class="d-block w-100 c-img" loading="lazy" alt="${safeAlt}">
                <div class="carousel-overlay">
                    <div class="carousel-content">
                        ${label}
                        <h2 class="carousel-heading">${safeHeading}</h2>
                        ${text}
                        ${button}
                    </div>
                </div>
            </div>
        `;
    })
    .filter(Boolean)
    .join("");
}

function applyTickerSettings(ticker) {
  const container = document.querySelector(".ticker-container");
  if (!container) return;
  const scroll = container.querySelector(".ticker-scroll");
  if (!scroll) return;

  if (!ticker) return;
  if (ticker.enabled === false) {
    container.style.display = "none";
    return;
  }

  container.style.display = "";
  const messages = Array.isArray(ticker.messages)
    ? ticker.messages.map((message) => (message || "").trim()).filter(Boolean)
    : [];

  if (messages.length === 0) return;
  const repeated = messages.concat(messages);
  scroll.innerHTML = repeated
    .map((message) => `<span>${escapeHtml(message)}</span>`)
    .join("");
}

function normalizeCollectorEntry(entry, brandName) {
  const source = entry && typeof entry === "object" ? entry : {};
  const name = (source.name || "").toString().trim();
  const tagline = (source.tagline || "").toString().trim();
  const quote = (source.quote || "").toString().trim();

  if (!name || !quote) {
    return null;
  }

  return {
    name: replaceStandaloneBrandToken(name, brandName),
    tagline: replaceStandaloneBrandToken(tagline || "Collector", brandName),
    quote: replaceStandaloneBrandToken(quote, brandName),
  };
}

function applyCollectorsSpeakSettings(collectorsSpeak, brandName) {
  const track = document.getElementById("collectorsSpeakTrack");
  const marquee = document.getElementById("collectorsSpeakMarquee");
  if (!track) return;

  const list = Array.isArray(collectorsSpeak)
    ? collectorsSpeak
        .map((entry) => normalizeCollectorEntry(entry, brandName))
        .filter(Boolean)
    : [];

  if (list.length === 0) {
    track.innerHTML = "";
    if (marquee) {
      marquee.style.display = "none";
    }
    return;
  }

  if (marquee) {
    marquee.style.display = "";
  }

  const repeated = list.concat(list);
  track.innerHTML = repeated
    .map(
      (item) => `
        <div class="testimonial-card">
          <p class="testimonial-text">"${escapeHtml(item.quote)}"</p>
          <div class="testimonial-meta">
            <span class="meta-name">${escapeHtml(item.name)}</span>
            <span class="meta-role">${escapeHtml(item.tagline || "Collector")}</span>
          </div>
        </div>
      `,
    )
    .join("");
}

function applySiteSettings() {
  const settings = getSiteSettings();
  if (!settings) return;
  const brandName = ((settings.brand || {}).name || "").toString().trim();
  applyBrandSettings(settings.brand);
  applyContactSettings(settings.contact);
  applySocialSettings(settings.socialLinks);
  applySliderSettings(settings.sliderImages);
  applyTickerSettings(settings.ticker);
  applyCollectorsSpeakSettings(settings.collectorsSpeak, brandName);
}
