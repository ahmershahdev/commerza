// Navigation and layout helpers for the page-ready stage.

function ensureLegalNavLinks() {
  const legalLinks = [
    { href: "terms-of-service.php", label: "Terms" },
    { href: "privacy-policy.php", label: "Privacy" },
  ];
  const currentPage = getCurrentPageKey();

  const appendLegalLinks = (menu) => {
    if (!menu || menu.length === 0) {
      return;
    }

    legalLinks.forEach((link) => {
      if (menu.find(`a[href="${link.href}"]`).length > 0) {
        return;
      }

      const isCurrent = currentPage === link.href;
      menu.append(`
          <li class="nav-item">
            <a class="nav-link"${isCurrent ? ' aria-current="page"' : ""} href="${link.href}">${link.label}</a>
          </li>
        `);
    });
  };

  $("header .collapse.navbar-collapse .navbar-nav.me-auto").each(function () {
    appendLegalLinks($(this));
  });

  $("header .offcanvas .offcanvas-body .navbar-nav").each(function () {
    appendLegalLinks($(this));
  });
}

function ensureDesktopMegaDropdown() {
  if (
    window.matchMedia &&
    (!window.matchMedia("(min-width: 1200px)").matches ||
      !window.matchMedia("(hover: hover) and (pointer: fine)").matches)
  ) {
    return;
  }

  const currentPage = getCurrentPageKey();
  const quickLinks = [
    {
      href: "order-tracking.php",
      icon: "bi-truck",
      label: "Order Tracking",
      desc: "Track order status",
    },
    {
      href: "compare.php",
      icon: "bi-columns-gap",
      label: "Compare Products",
      desc: "See watches side by side",
    },
    {
      href: "wishlist.php",
      icon: "bi-heart",
      label: "Wishlist",
      desc: "Saved favorites",
    },
    {
      href: "cart.php",
      icon: "bi-cart3",
      label: "Cart",
      desc: "Review your cart",
    },
    {
      href: "products.php",
      icon: "bi-grid-3x3-gap",
      label: "All Products",
      desc: "Browse full catalog",
    },
    {
      href: "faq.php",
      icon: "bi-question-circle",
      label: "FAQ",
      desc: "Get quick answers",
    },
    {
      href: "shipping.php",
      icon: "bi-box-seam",
      label: "Shipping",
      desc: "Delivery information",
    },
    {
      href: "returns.php",
      icon: "bi-arrow-counterclockwise",
      label: "Returns",
      desc: "Return policy",
    },
    {
      href: "contact.php",
      icon: "bi-chat-dots",
      label: "Contact",
      desc: "Reach support",
    },
  ];
  const quickAccessTags = ["smart", "quartz", "chronograph", "automatic"];

  const navMenus = $(
    "header nav.navbar .collapse.navbar-collapse ul.navbar-nav",
  ).filter(function () {
    const menu = $(this);
    if (!menu.length || menu.find(".commerza-mega-nav").length) {
      return false;
    }

    const navLinks = menu.find("a.nav-link");
    return navLinks.length >= 3 && menu.closest(".offcanvas").length === 0;
  });

  navMenus.each(function (menuIndex) {
    const menu = $(this);
    const dropdownId = `commerzaMegaMenu${menuIndex + 1}`;
    const cardMarkup = quickLinks
      .map((link) => {
        const isCurrent = currentPage === link.href;
        return `
            <a class="commerza-mega-link${isCurrent ? " is-current" : ""}" href="${link.href}"${isCurrent ? ' aria-current="page"' : ""}>
              <span class="commerza-mega-icon"><i class="bi ${link.icon}"></i></span>
              <span class="commerza-mega-copy">
                <strong>${link.label}</strong>
                <small>${link.desc}</small>
              </span>
            </a>
          `;
      })
      .join("");
    const quickTagMarkup = quickAccessTags
      .map((tag) => `<span class="commerza-mega-tag">${escapeHtml(tag)}</span>`)
      .join("");

    menu.append(`
        <li class="nav-item dropdown commerza-mega-nav" data-commerza-quick-access="1">
          <a class="nav-link dropdown-toggle" href="#" id="${dropdownId}" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Quick Access
          </a>
          <div class="dropdown-menu commerza-mega-dropdown" aria-labelledby="${dropdownId}">
            <div class="commerza-mega-header">
              <p class="commerza-mega-eyebrow">Quick Navigation</p>
              <h6 class="commerza-mega-title">Premium Shortcuts</h6>
              <div class="commerza-mega-tags">${quickTagMarkup}</div>
            </div>
            <div class="commerza-mega-grid">
              ${cardMarkup}
            </div>
          </div>
        </li>
      `);
  });
}
