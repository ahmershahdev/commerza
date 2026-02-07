(function () {
  window.CommerzaAdminConfig = {
    adminEmailDefault: 'commerza.ahmer@gmail.com',
    adminPasswordDefault: 'Commerza@2026',
    resetKey: 'COMMERZA-RESET-2026'
  };

  const SITE_SETTINGS_KEY = 'commerza_site_settings';

  function getSiteSettings() {
    const stored = localStorage.getItem(SITE_SETTINGS_KEY);
    if (!stored) return null;
    try {
      return JSON.parse(stored);
    } catch (error) {
      console.warn('Invalid site settings found');
      return null;
    }
  }

  function updateMetaForBrand(brandName) {
    if (!brandName) return;
    const replaceBrand = (value) => value.replace(/\bCommerza\b/gi, brandName);

    if (document.title) {
      document.title = replaceBrand(document.title);
    }

    const selectors = [
      'meta[name="description"]',
      'meta[property="og:title"]',
      'meta[property="og:description"]',
      'meta[name="twitter:title"]',
      'meta[name="twitter:description"]'
    ];

    document.querySelectorAll(selectors.join(','))
      .forEach(meta => {
        const content = meta.getAttribute('content') || '';
        if (!content) return;
        meta.setAttribute('content', replaceBrand(content));
      });
  }

  function replaceBrandTextNodes(root, brandName) {
    if (!root || !brandName) return;
    const skipTags = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEXTAREA', 'INPUT']);
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
    let node = walker.nextNode();

    while (node) {
      const parent = node.parentElement;
      if (!parent || !skipTags.has(parent.tagName)) {
        if (/\bCommerza\b/i.test(node.nodeValue)) {
          node.nodeValue = node.nodeValue.replace(/\bCommerza\b/gi, brandName);
        }
      }
      node = walker.nextNode();
    }
  }

  function applyAdminBranding() {
    const settings = getSiteSettings();
    const brand = settings?.brand;
    if (!brand) return;

    const name = (brand.name || '').trim();
    const logo = (brand.logo || '').trim();
    const favicon = (brand.favicon || '').trim();

    const resolveAdminAssetPath = (value) => {
      if (!value) return value;
      if (/^(https?:|data:|blob:)/i.test(value)) return value;
      if (value.startsWith('/')) return value;
      if (value.startsWith('frontend/')) return `../../${value}`;
      return value;
    };

    if (name) {
      updateMetaForBrand(name);
      replaceBrandTextNodes(document.body, name);
      document.querySelectorAll('.brand-text').forEach(node => {
        node.textContent = name;
      });
    }

    if (logo) {
      const resolvedLogo = resolveAdminAssetPath(logo);
      document.querySelectorAll('.navbar-logo, .offcanvas-logo').forEach(img => {
        img.src = resolvedLogo;
        if (name) {
          img.alt = `${name} Logo`;
        }
      });
    } else if (name) {
      document.querySelectorAll('.navbar-logo, .offcanvas-logo').forEach(img => {
        if (!img.alt || img.alt.toLowerCase().includes('commerza')) {
          img.alt = `${name} Logo`;
        }
      });
    }

    if (favicon) {
      const resolvedFavicon = resolveAdminAssetPath(favicon);
      const links = document.querySelectorAll('link[rel="icon"], link[rel="shortcut icon"]');
      if (links.length) {
        links.forEach(link => {
          link.href = resolvedFavicon;
        });
      } else if (document.head) {
        const link = document.createElement('link');
        link.rel = 'icon';
        link.href = resolvedFavicon;
        document.head.appendChild(link);
      }
    }
  }

  window.applyAdminBranding = applyAdminBranding;

  document.addEventListener('DOMContentLoaded', () => {
    applyAdminBranding();
  });
})();
