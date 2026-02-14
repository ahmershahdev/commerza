const SITE_SETTINGS_KEY = "commerza_site_settings";

(function () {
  if (window.CommerzaAuth) return;

  const USERS_KEY = "commerza_users";
  const SESSION_KEY = "commerza_session";
  const RESET_KEY = "commerza_reset_requests";

  function normalizeEmail(email) {
    return (email || "").trim().toLowerCase();
  }

  function getUsers() {
    const stored = localStorage.getItem(USERS_KEY);
    if (!stored) return [];
    try {
      return JSON.parse(stored);
    } catch (error) {
      return [];
    }
  }

  function saveUsers(users) {
    localStorage.setItem(USERS_KEY, JSON.stringify(users));
  }

  function findUserByEmail(email) {
    const normalized = normalizeEmail(email);
    return getUsers().find((user) => user.email === normalized) || null;
  }

  function registerUser(data) {
    const name = (data?.name || "").trim();
    const email = normalizeEmail(data?.email);
    const password = data?.password || "";
    const phone = (data?.phone || "").trim();

    if (!name || !email || !password || !phone) {
      return { ok: false, error: "All fields are required." };
    }

    const users = getUsers();
    if (users.some((user) => user.email === email)) {
      return { ok: false, error: "Email already registered." };
    }

    const newUser = {
      id: Date.now(),
      name,
      email,
      password,
      phone,
      createdAt: new Date().toISOString(),
    };

    users.push(newUser);
    saveUsers(users);
    return { ok: true, user: newUser };
  }

  function loginUser(email, password) {
    const user = findUserByEmail(email);
    if (!user || user.password !== password) {
      return { ok: false, error: "Invalid email or password." };
    }

    localStorage.setItem(
      SESSION_KEY,
      JSON.stringify({
        email: user.email,
        loggedInAt: new Date().toISOString(),
      }),
    );
    return { ok: true, user };
  }

  function logoutUser() {
    localStorage.removeItem(SESSION_KEY);
  }

  function getCurrentUser() {
    const session = localStorage.getItem(SESSION_KEY);
    if (!session) return null;
    try {
      const parsed = JSON.parse(session);
      return findUserByEmail(parsed.email);
    } catch (error) {
      return null;
    }
  }

  function updateUser(email, updates) {
    const users = getUsers();
    const normalized = normalizeEmail(email);
    const index = users.findIndex((user) => user.email === normalized);
    if (index === -1) return { ok: false, error: "User not found." };

    const nextEmail = normalizeEmail(updates.email || users[index].email);
    if (
      nextEmail !== normalized &&
      users.some((user) => user.email === nextEmail)
    ) {
      return { ok: false, error: "Email already in use." };
    }

    users[index] = {
      ...users[index],
      ...updates,
      email: nextEmail,
    };
    saveUsers(users);

    const session = localStorage.getItem(SESSION_KEY);
    if (session) {
      try {
        const parsed = JSON.parse(session);
        parsed.email = users[index].email;
        localStorage.setItem(SESSION_KEY, JSON.stringify(parsed));
      } catch (error) {
        // ignore
      }
    }

    return { ok: true, user: users[index] };
  }

  function updatePassword(email, currentPassword, newPassword) {
    const users = getUsers();
    const normalized = normalizeEmail(email);
    const index = users.findIndex((user) => user.email === normalized);
    if (index === -1) return { ok: false, error: "User not found." };
    if (users[index].password !== currentPassword) {
      return { ok: false, error: "Current password is incorrect." };
    }
    users[index].password = newPassword;
    saveUsers(users);
    return { ok: true };
  }

  function requestPasswordReset(email) {
    const user = findUserByEmail(email);
    if (!user) return { ok: false, error: "No account found for this email." };

    const resets = JSON.parse(localStorage.getItem(RESET_KEY) || "[]");
    resets.push({ email: user.email, requestedAt: new Date().toISOString() });
    localStorage.setItem(RESET_KEY, JSON.stringify(resets));
    return { ok: true };
  }

  window.CommerzaAuth = {
    registerUser,
    loginUser,
    logoutUser,
    getCurrentUser,
    updateUser,
    updatePassword,
    requestPasswordReset,
  };
})();

function getSiteSettings() {
  const stored = localStorage.getItem(SITE_SETTINGS_KEY);
  if (!stored) return null;
  try {
    return JSON.parse(stored);
  } catch (error) {
    console.warn("Invalid site settings found");
    return null;
  }
}

function applyBrandSettings(brand) {
  if (!brand) return;
  const name = (brand.name || "").trim();
  const logo = (brand.logo || "").trim();
  const favicon = (brand.favicon || "").trim();

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
      if (/\bCommerza\b/i.test(node.nodeValue)) {
        node.nodeValue = node.nodeValue.replace(/\bCommerza\b/gi, brandName);
      }
    }
    node = walker.nextNode();
  }
}

function applyContactSettings(contact) {
  if (!contact) return;
  const email = contact.email;
  const phone = contact.phone;
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
    .map(
      (link) => `
        <a href="${link.url}" target="_blank" rel="noopener" aria-label="Follow on ${getSocialLabel(link)}">
            <i class="${link.icon}"></i>
        </a>
    `,
    )
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
      const label = slide.label
        ? `<span class="carousel-label">${slide.label}</span>`
        : "";
      const text = slide.text
        ? `<p class="carousel-text">${slide.text}</p>`
        : "";
      const button =
        slide.buttonText && slide.buttonLink
          ? `<a href="${slide.buttonLink}" class="btn carousel-btn">${slide.buttonText}</a>`
          : "";
      return `
            <div class="carousel-item ${index === 0 ? "active" : ""}">
                <img src="${slide.image}" class="d-block w-100 c-img" loading="lazy" alt="${slide.alt || "carousel image"}">
                <div class="carousel-overlay">
                    <div class="carousel-content">
                        ${label}
                        <h2 class="carousel-heading">${slide.heading || ""}</h2>
                        ${text}
                        ${button}
                    </div>
                </div>
            </div>
        `;
    })
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
    .map((message) => `<span>${message}</span>`)
    .join("");
}

function applySiteSettings() {
  const settings = getSiteSettings();
  if (!settings) return;
  applyBrandSettings(settings.brand);
  applyContactSettings(settings.contact);
  applySocialSettings(settings.socialLinks);
  applySliderSettings(settings.sliderImages);
  applyTickerSettings(settings.ticker);
}

function showAccountMessage(message, type = "success") {
  const bg = type === "success" ? "#1a472a" : "#332a1a";
  const color = type === "success" ? "#28a745" : "#ff6600";
  const icon = type === "success" ? "check-circle" : "exclamation-circle";
  $("body").append(
    `<div class="alert alert-${type} alert-dismissible fade show" role="alert" style="position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 320px; background-color: ${bg}; border: 2px solid ${color}; border-radius: 8px; padding: 20px; box-shadow: 0 4px 15px rgba(255, 102, 0, 0.3);"><div style="color: #fff; text-align: left;"><i class="bi bi-${icon}" style="font-size: 1.5rem; color: ${color}; display: inline-block; margin-right: 8px;"></i><span style="font-weight: 600;">${message}</span></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button></div>`,
  );
  setTimeout(
    () =>
      $(".alert").fadeOut(300, function () {
        $(this).remove();
      }),
    3500,
  );
}

function showNotif(msg, type = "success") {
  const bg = type === "success" ? "#1a472a" : "#332a1a";
  const color = type === "success" ? "#28a745" : "#ff6600";
  const icon = type === "success" ? "check-circle" : "exclamation-circle";
  $("body").append(
    `<div class="alert alert-${type} alert-dismissible fade show toast-alert" role="alert" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999; min-width: 380px; background-color: ${bg}; border: 2px solid ${color}; border-radius: 8px; padding: 30px; box-shadow: 0 4px 15px rgba(255, 102, 0, 0.3);"><div style="color: #fff; text-align: center;"><i class="bi bi-${icon}" style="font-size: 2rem; color: ${color}; display: block; margin-bottom: 10px;"></i><h5 style="margin: 10px 0; color: ${color};">${msg}</h5></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button></div>`,
  );
  setTimeout(
    () =>
      $(".alert").fadeOut(300, function () {
        $(this).remove();
      }),
    3500,
  );
}

function renderAccountOrders(user) {
  const container = $("#my-orders-container");
  if (!container.length) return;
  const orders = JSON.parse(localStorage.getItem("commerza_orders")) || [];
  const matches = orders.filter((order) => {
    if (order.email && order.email.toLowerCase() === user.email) return true;
    return (order.customerName || "").toLowerCase() === user.name.toLowerCase();
  });

  if (matches.length === 0) {
    container.html(`
            <div class="text-center py-5">
                <i class="bi bi-bag-x" style="font-size: 3rem; color: #ff6600;"></i>
                <h3 class="text-white mt-3">No Orders Yet</h3>
                <p class="text-secondary">You haven't placed any orders. Start shopping now!</p>
                <a href="index.html" class="btn product-btn-buy mt-3">Start Shopping</a>
            </div>
        `);
    return;
  }

  const list = matches
    .sort((a, b) => new Date(b.orderDate) - new Date(a.orderDate))
    .map(
      (order) => `
            <div class="card product-card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between flex-wrap gap-2">
                        <div>
                            <h3 class="product-name mb-1">${order.orderId}</h3>
                            <p class="product-desc mb-1">${order.orderDate}</p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-${order.status === "Delivered" ? "success" : order.status === "Cancelled" ? "danger" : "warning"} rounded-pill">${order.status}</span>
                            <p class="text-white fw-bold mt-2">PKR ${order.total.toLocaleString()}</p>
                        </div>
                    </div>
                    <div class="mt-3">
                        ${order.items
                          .map(
                            (item) => `
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <img src="${item.image}" alt="${item.name}" style="width: 48px; height: 48px; object-fit: cover; border-radius: 6px;">
                                <div>
                                    <div class="text-white fw-semibold">${item.name}</div>
                                    <div class="text-secondary small">Qty: ${item.quantity} · ${item.price} PKR</div>
                                </div>
                            </div>
                        `,
                          )
                          .join("")}
                    </div>
                </div>
            </div>
        `,
    )
    .join("");

  container.html(list);
}

function initAccountPage() {
  const profileName = $("#accountProfileName");
  const profileEmail = $("#accountProfileEmail");
  const profileImage = $("#accountProfileImage");
  const profileImageInput = $("#profilePictureInput");
  const profileForm = $("#updateProfileForm");
  const passwordForm = $("#updatePasswordForm");
  const profilePictureForm = $("#updateProfilePictureForm");
  const logoutBtn = $("#logoutBtn");

  if (!profileForm.length && !passwordForm.length) return;

  const user = window.CommerzaAuth?.getCurrentUser?.();
  if (!user) {
    window.location.href = "login.html";
    return;
  }

  if (profileName.length) profileName.text(user.name);
  if (profileEmail.length) profileEmail.text(user.email);
  $("#full-name").val(user.name || "");
  $("#email").val(user.email || "");
  $("#phone").val(user.phone || "");

  if (profileImage.length) {
    const storedImage = localStorage.getItem(
      `commerza_profile_image_${user.email}`,
    );
    if (storedImage) {
      profileImage.attr("src", storedImage);
    }
  }

  renderAccountOrders(user);

  profileForm.on("submit", function (e) {
    e.preventDefault();
    const updates = {
      name: $("#full-name").val().trim(),
      email: $("#email").val().trim(),
      phone: $("#phone").val().trim(),
    };
    const result = window.CommerzaAuth.updateUser(user.email, updates);
    if (!result.ok) {
      showAccountMessage(result.error, "warning");
      return;
    }
    if (profileName.length) profileName.text(result.user.name);
    if (profileEmail.length) profileEmail.text(result.user.email);
    showAccountMessage("Profile updated successfully.");
  });

  passwordForm.on("submit", function (e) {
    e.preventDefault();
    const currentPassword = $("#current-password").val();
    const newPassword = $("#new-password").val();
    const confirmPassword = $("#confirm-password").val();

    if (newPassword !== confirmPassword) {
      showAccountMessage("New passwords do not match.", "warning");
      return;
    }

    const pattern = /(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[@$!%*?&]).{6,}/;
    if (!pattern.test(newPassword)) {
      showAccountMessage(
        "Password must be at least 6 chars with 1 number, 1 capital, 1 special char.",
        "warning",
      );
      return;
    }

    const result = window.CommerzaAuth.updatePassword(
      user.email,
      currentPassword,
      newPassword,
    );
    if (!result.ok) {
      showAccountMessage(result.error, "warning");
      return;
    }

    $("#current-password").val("");
    $("#new-password").val("");
    $("#confirm-password").val("");
    showAccountMessage("Password updated successfully.");
  });

  profilePictureForm.on("submit", function (e) {
    e.preventDefault();
    const file = profileImageInput[0]?.files?.[0];
    if (!file) {
      showAccountMessage("Please choose an image first.", "warning");
      return;
    }
    const reader = new FileReader();
    reader.onload = function (evt) {
      const result = evt.target?.result;
      if (result) {
        localStorage.setItem(`commerza_profile_image_${user.email}`, result);
        profileImage.attr("src", result);
        showAccountMessage("Profile picture updated successfully.");
      }
    };
    reader.readAsDataURL(file);
  });

  logoutBtn.on("click", function () {
    window.CommerzaAuth.logoutUser();
    window.location.href = "login.html";
  });

  $(".toggle-password").on("click", function () {
    const target = $(this).data("target");
    if (!target) return;
    const input = $(target);
    input.attr("type", input.attr("type") === "password" ? "text" : "password");
    $(this).toggleClass("bi-eye bi-eye-slash");
  });
}

$(document).ready(function () {
  const upBtn = $("#backToTop");
  let cart = JSON.parse(localStorage.getItem("commerza_cart")) || [];

  applySiteSettings();
  
  
  const carouselElement = document.getElementById("carouselExampleIndicators");
  const playPauseBtn = document.getElementById("carouselPlayPause");
  
  if (carouselElement && playPauseBtn) {
    const carousel = new bootstrap.Carousel(carouselElement, {
      interval: 3500,
      pause: false
    });
    
    let isPlaying = true;
    
    playPauseBtn.addEventListener("click", function() {
      isPlaying = !isPlaying;
      
      if (isPlaying) {
        carousel.cycle();
        playPauseBtn.innerHTML = '<i class="bi bi-pause-fill"></i>';
      } else {
        carousel.pause();
        playPauseBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
      }
    });
  }
  
  initAccountPage();

  updateCartBadge();
  updateWishlistBadge();
  updateWishlistButtons();
  renderWishlistPage();
  renderComparePage();
  if ($("#cart-items-container").length > 0) {
    displayCartItems();
  }

  function getTotalCartQty() {
    return cart.reduce((total, item) => total + item.quantity, 0);
  }

  function ensureCustomAlert() {
    if (document.getElementById("customAlert")) return;
    const alertHtml = `
            <div id="customAlert" class="alert alert-danger text-center"
                style="display:none; position:fixed; top:20px; right:0; left:0; margin:auto; width:300px; z-index:9998;">
                Cart Full: Your 10-item limit has been reached.
            </div>
        `;
    $("body").append(alertHtml);
  }

  function triggerAlert() {
    ensureCustomAlert();
    const alertBox = $("#customAlert");
    alertBox.stop(true, true).fadeIn(300).delay(2000).fadeOut(300);
  }

  function getProductImageElement(btn) {
    let card = btn.closest(".product-card");
    if (card.length) return card.find("img.p-image");
    const detailCard = btn.closest(".product-detail-card");
    if (detailCard.length) return detailCard.find("img.p-image");
    return $("img.p-image").first();
  }

  function resolveCartItem(btn) {
    const dataName = btn.data("productName");
    const dataPrice = btn.data("productSalePrice") || btn.data("productPrice");
    const dataImage = btn.data("productImage");

    let name = dataName;
    let price = dataPrice;
    let image = dataImage;

    if (!name || !price || !image) {
      const card = btn.closest(".product-card");
      const detailCard = btn.closest(".product-detail-card");
      const scope = card.length ? card : detailCard;

      if (!name) {
        name = scope.find(".product-name").first().text().trim();
      }

      if (!price) {
        price =
          scope.find(".sale-price").first().text().trim() ||
          scope.find(".price-sale").first().text().trim() ||
          scope.find(".price-original").first().text().trim();
      }

      if (!image) {
        image = scope.find("img.p-image").first().attr("src");
      }
    }

    const normalizedPrice = (() => {
      if (price == null) return "";
      if (typeof price === "number") return `${price} PKR`;
      const text = String(price).trim();
      if (!text) return "";
      if (!/\d/.test(text)) return "";
      if (/PKR/i.test(text)) return text;
      return `${text} PKR`;
    })();

    return {
      name: name || "",
      price: normalizedPrice,
      image: image || "",
    };
  }

  $(document).on("click", ".product-btn-cart", function (e) {
    const href = $(this).attr("href");
    if (
      $(this).hasClass("change-qty") ||
      $(this).hasClass("wishlist-remove-btn") ||
      $(this).hasClass("compare-remove-btn") ||
      $(this).hasClass("wishlist-btn") ||
      $(this).hasClass("compare-link") ||
      (href && href.indexOf("compare.html") !== -1)
    )
      return;
    e.preventDefault();

    if (getTotalCartQty() >= 10) {
      triggerAlert();
      return;
    }

    const btn = $(this);
    const img = getProductImageElement(btn);
    let cartIcon = $("#cart-icon");
    const product = resolveCartItem(btn);
    const productName = product.name;
    const productPrice = product.price;
    const productImage = product.image;

    if (!productName || !productPrice || !productImage) {
      showNotif(
        "Unable to add this product. Please refresh and try again.",
        "warning",
      );
      return;
    }

    let existingItem = cart.find((item) => item.name === productName);
    if (existingItem) {
      existingItem.quantity += 1;
    } else {
      cart.push({
        name: productName,
        price: productPrice,
        image: productImage,
        quantity: 1,
      });
    }

    saveAndSync();

    let isBuyNow = btn.hasClass("product-btn-buy");
    if (isBuyNow) {
      setTimeout(() => {
        window.location.href = "cart.html";
      }, 600);
    }

    if (img.length && cartIcon.length && !isBuyNow) {
      let imgRect = img[0].getBoundingClientRect();
      let cartRect = cartIcon[0].getBoundingClientRect();
      let flyImg = $("<img />", {
        src: img.attr("src"),
        css: {
          position: "fixed",
          top: imgRect.top + "px",
          left: imgRect.left + "px",
          width: imgRect.width + "px",
          height: imgRect.height + "px",
          borderRadius: "12px",
          zIndex: 999999,
          pointerEvents: "none",
          opacity: 1,
        },
      }).appendTo("body");

      flyImg.animate(
        {
          top: cartRect.top + cartRect.height / 2 - 15 + "px",
          left: cartRect.left + cartRect.width / 2 - 15 + "px",
          width: "30px",
          height: "30px",
          opacity: 0.5,
        },
        1200,
        "swing",
        function () {
          $(this).remove();
          cartIcon.addClass("shake-animation");
          setTimeout(() => cartIcon.removeClass("shake-animation"), 400);
        },
      );
    }
  });

  $(document).on("click", ".wishlist-btn", function (e) {
    e.preventDefault();
    e.stopPropagation();
    const btn = $(this);
    const item = {
      id: btn.data("productId"),
      name: btn.data("productName"),
      image: btn.data("productImage"),
      price: btn.data("productPrice"),
      salePrice: btn.data("productSalePrice"),
    };
    const added = toggleWishlist(item);
    updateWishlistButtons();
    renderWishlistPage();
    if (added) {
      const img = getProductImageElement(btn);
      const wishlistLink = $('.nav-link[href="wishlist.html"]').first();
      const wishlistIcon = wishlistLink.find("i").first();
      if (img.length && wishlistIcon.length) {
        const imgRect = img[0].getBoundingClientRect();
        const wishRect = wishlistIcon[0].getBoundingClientRect();
        const flyImg = $("<img />", {
          src: img.attr("src"),
          css: {
            position: "fixed",
            top: imgRect.top + "px",
            left: imgRect.left + "px",
            width: imgRect.width + "px",
            height: imgRect.height + "px",
            borderRadius: "12px",
            zIndex: 999999,
            pointerEvents: "none",
            opacity: 1,
          },
        }).appendTo("body");

        flyImg.animate(
          {
            top: wishRect.top + wishRect.height / 2 - 15 + "px",
            left: wishRect.left + wishRect.width / 2 - 15 + "px",
            width: "30px",
            height: "30px",
            opacity: 0.5,
          },
          1200,
          "swing",
          function () {
            $(this).remove();
            wishlistIcon.addClass("shake-animation");
            setTimeout(() => wishlistIcon.removeClass("shake-animation"), 400);
          },
        );
      }
    }
  });

  $(document).on("click", ".wishlist-remove-btn", function () {
    const btn = $(this);
    const item = {
      id: btn.data("productId"),
      name: btn.data("productName"),
    };
    const list = getWishlist().filter(
      (entry) =>
        String(entry.id) !== String(item.id) && entry.name !== item.name,
    );
    saveWishlist(list);
    updateWishlistBadge();
    renderWishlistPage();
    updateWishlistButtons();
  });

  $(document).on("click", ".compare-btn", function (e) {
    e.preventDefault();
    const btn = $(this);
    const item = {
      id: btn.data("productId"),
      name: btn.data("productName"),
      image: btn.data("productImage"),
      price: btn.data("productPrice"),
      salePrice: btn.data("productSalePrice"),
      stock: btn.data("productStock"),
      movement: btn.data("productMovement"),
    };
    toggleCompare(item);
    updateCompareButtons();
    renderComparePage();
  });

  $(document).on("click", ".compare-remove-btn", function () {
    const btn = $(this);
    const item = {
      id: btn.data("productId"),
      name: btn.data("productName"),
    };
    const list = getCompare().filter(
      (entry) =>
        String(entry.id) !== String(item.id) && entry.name !== item.name,
    );
    saveCompare(list);
    renderComparePage();
    updateCompareButtons();
  });

  $(document).on("click", ".change-qty", function () {
    let index = $(this).data("index");
    let action = $(this).data("action");

    if (action === "plus") {
      if (getTotalCartQty() >= 10) {
        triggerAlert();
      } else {
        cart[index].quantity++;
        saveAndSync();
        displayCartItems();
      }
    } else if (action === "minus") {
      if (cart[index].quantity > 1) {
        cart[index].quantity--;
        saveAndSync();
        displayCartItems();
      }
    }
  });

  $(document).on("click", ".remove-item", function () {
    let index = $(this).data("index");
    cart.splice(index, 1);
    saveAndSync();
    displayCartItems();
  });

  function saveAndSync() {
    localStorage.setItem("commerza_cart", JSON.stringify(cart));
    updateCartBadge();
  }

  function updateCartBadge() {
    const count = getTotalCartQty();
    $("#cart-count").text(count);
    $("#cart-count-mobile").text(count);
  }

  function displayCartItems() {
    let container = $("#cart-items-container");
    container.empty();

    if (cart.length === 0) {
      container.html(`
                <div class="text-center py-5">
                    <i class="bi bi-cart-x" style="font-size: 4rem; color: #ff6600;"></i>
                    <h2 class="text-white mt-3">Your Cart is Empty</h2>
                    <p class="text-secondary">Looks like you haven't added anything to your cart yet.</p>
                    <a href="index.html" class="btn product-btn-buy mt-3 px-4 py-2">Continue Shopping</a>
                </div>
            `);
      updateSummary(0, 0);
      return;
    }

    cart.forEach((item, index) => {
      container.append(`
                <div class="card product-card mb-3">
                  <div class="card-body d-flex align-items-center gap-3">
                    <img src="${item.image}" class="cart-img me-3" alt="${item.name}" />
                    <div class="flex-grow-1 text-center">
                      <h3 class="product-name mb-1">${item.name}</h3>
                      <p class="product-desc mb-2 cart-item-price">${item.price}</p>
                      <div class="d-flex align-items-center justify-content-center gap-3 mx-auto" style="max-width: 150px;">
                        <button class="btn btn-sm product-btn-cart change-qty" data-index="${index}" data-action="minus">−</button>
                        <span class="text-white fw-bold">${item.quantity}</span>
                        <button class="btn btn-sm product-btn-cart change-qty" data-index="${index}" data-action="plus">+</button>
                      </div>
                    </div>
                    <button class="btn btn-sm btn-danger remove-item ms-3 align-self-start">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>
            `);
    });
    calculateTotal();
  }

  function calculateTotal() {
    let subtotal = 0;
    let totalItems = 0;
    cart.forEach((item) => {
      let priceNum = parseInt(String(item.price || "").replace(/[^0-9]/g, ""));
      subtotal += priceNum * item.quantity;
      totalItems += item.quantity;
    });
    updateSummary(subtotal, totalItems);
  }

  function updateSummary(subtotal, totalItems) {
    $("#total-items-qty").text(totalItems);
    $("#cart-subtotal").text(subtotal.toLocaleString() + " PKR");

    $("#cart-shipping").html(`
            <span style="text-decoration: line-through; color: #b0b0b0;">1000 PKR</span> 
            <span style="color: #28a745; margin-left: 10px; font-weight: bold;">FREE</span>
        `);

    $("#cart-total").text(subtotal.toLocaleString() + " PKR");
  }

  const searchConfig = [
    {
      containerId: "featured-products-container",
      sectionIds: ["featured-collection"],
    },
    {
      containerId: "automatic-vault-products-container",
      sectionIds: ["automatic-vault"],
    },
    {
      containerId: "smart-evolution-products-container",
      sectionIds: ["smart-evolution"],
    },
    {
      containerId: "signature-collection-products-container",
      sectionIds: ["signature-collection"],
    },
    {
      containerId: "sports-division-products-container",
      sectionIds: ["sports-sales-division"],
    },
  ];

  let productsCache = null;
  let productsCacheKey = null;

  initProductDetailPage();
  initNewsletterModal();
  initNewsletterInlineForm();
  initOrderTrackingPage();
  initProductFilters();

  function getActiveSearchTargets() {
    return searchConfig.filter((cfg) => $(`#${cfg.containerId}`).length > 0);
  }

  function resetProductSections() {
    const activeTargets = getActiveSearchTargets();
    activeTargets.forEach((target) => {
      target.sectionIds.forEach((sectionId) => {
        loadProductsBySection(sectionId, target.containerId);
      });
    });
  }

  function renderEmptyResult(containerId, query) {
    const container = $(`#${containerId}`);
    if (container.length === 0) return;
    container.html(`
            <div class="text-center py-5">
                <i class="bi bi-search" style="font-size: 3rem; color: #ff6600;"></i>
                <h3 class="text-white mt-3">No results found</h3>
                <p class="text-secondary">We couldn't find any matches for "${query}".</p>
            </div>
        `);
  }

  function getAllProducts(data) {
    return data.sections.flatMap((section) => section.products || []);
  }

  function uniqueProducts(products) {
    const seen = new Set();
    return products.filter((product) => {
      const key = `${product.name}-${product.salePrice}`;
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    });
  }

  function applySearchResults(query, data) {
    const normalized = query.toLowerCase();
    const activeTargets = getActiveSearchTargets();

    if (activeTargets.length === 0) return;

    const isIndexSearch =
      activeTargets.length === 1 &&
      activeTargets[0].containerId === "featured-products-container";
    const allProducts = uniqueProducts(getAllProducts(data));

    activeTargets.forEach((target) => {
      let matched = [];
      if (isIndexSearch) {
        matched = allProducts.filter((p) => {
          const haystack = `${p.name} ${p.description}`.toLowerCase();
          return haystack.includes(normalized);
        });
      } else {
        target.sectionIds.forEach((sectionId) => {
          const section = data.sections.find((s) => s.sectionId === sectionId);
          if (!section) return;
          const filtered = section.products.filter((p) => {
            const haystack = `${p.name} ${p.description}`.toLowerCase();
            return haystack.includes(normalized);
          });
          matched = matched.concat(filtered);
        });
      }

      if (matched.length === 0) {
        renderEmptyResult(target.containerId, query);
      } else {
        const uniqueMatched = uniqueProducts(matched);
        renderProducts(uniqueMatched, target.containerId);
        if (uniqueMatched.length > 0) {
          scrollToProduct(uniqueMatched[0].name);
        }
      }
    });
  }

  function normalizeName(name) {
    return (name || "").toLowerCase().trim();
  }

  function scrollToProduct(name) {
    const targetName = normalizeName(name);
    const card = $(`.product-card[data-product-name="${targetName}"]`).first();
    if (card.length === 0) return;
    const offsetTop = card.offset().top - 100;
    window.scrollTo({ top: offsetTop, behavior: "smooth" });
    card.addClass("product-focus");
    setTimeout(() => card.removeClass("product-focus"), 1500);
  }

  function readProductsStorage() {
    const stored = localStorage.getItem("commerza_products");
    if (!stored) return null;
    try {
      return { data: JSON.parse(stored), key: stored };
    } catch (error) {
      console.warn("Invalid local products data, fallback to JSON");
      return null;
    }
  }

  function fetchProductsData() {
    const storage = readProductsStorage();
    if (storage && storage.key !== productsCacheKey) {
      productsCache = storage.data;
      productsCacheKey = storage.key;
      return $.Deferred().resolve(productsCache).promise();
    }
    if (productsCache) return $.Deferred().resolve(productsCache).promise();
    return $.ajax({
      url: "products.json",
      method: "GET",
      dataType: "json",
    }).done((data) => {
      productsCache = data;
      productsCacheKey = null;
    });
  }

  function getCurrentPageKey() {
    const path = window.location.pathname.replace(/\\/g, "/");
    const file = path.split("/").pop();
    return file || "index.html";
  }

  function getSectionsForPage(data, pageKey) {
    if (!data?.sections) return [];
    return data.sections.filter((section) => (section.page || "") === pageKey);
  }

  function getProductsForSections(sections) {
    return sections.flatMap((section) => section.products || []);
  }

  function buildMovementOptions(products) {
    const movementSet = new Set();
    products.forEach((product) => {
      if (product.movement) {
        movementSet.add(product.movement);
      } else {
        movementSet.add("quartz");
      }
    });
    const order = ["auto", "smart", "quartz"];
    return order.filter((item) => movementSet.has(item));
  }

  function formatMovementLabel(value) {
    if (value === "auto") return "Automatic";
    if (value === "smart") return "Smart";
    return "Quartz";
  }

  function populateFilterOptions(data) {
    const sectionInput = $("#filter-section");
    const movementInput = $("#filter-movement");
    const sectionMenu = $("#filter-section-menu");
    const movementMenu = $("#filter-movement-menu");
    if (
      !sectionInput.length ||
      !movementInput.length ||
      !sectionMenu.length ||
      !movementMenu.length
    )
      return;

    const pageKey = getCurrentPageKey();
    const sections = getSectionsForPage(data, pageKey);
    const products = getProductsForSections(sections);

    const selectedSection = sectionInput.val();
    const selectedMovement = movementInput.val();

    sectionMenu.empty();
    sectionMenu.append(
      '<li><a class="dropdown-item filter-dropdown-item" href="#" data-target="filter-section" data-value="all" data-label="All Sections">All Sections</a></li>',
    );
    sectionMenu.append('<li><hr class="dropdown-divider"></li>');
    sections.forEach((section) => {
      sectionMenu.append(
        `<li><a class="dropdown-item filter-dropdown-item" href="#" data-target="filter-section" data-value="${section.sectionId}" data-label="${section.sectionName}">${section.sectionName}</a></li>`,
      );
    });

    movementMenu.empty();
    movementMenu.append(
      '<li><a class="dropdown-item filter-dropdown-item" href="#" data-target="filter-movement" data-value="all" data-label="All Movements">All Movements</a></li>',
    );
    movementMenu.append('<li><hr class="dropdown-divider"></li>');
    buildMovementOptions(products).forEach((movement) => {
      const label = formatMovementLabel(movement);
      movementMenu.append(
        `<li><a class="dropdown-item filter-dropdown-item" href="#" data-target="filter-movement" data-value="${movement}" data-label="${label}">${label}</a></li>`,
      );
    });

    if (selectedSection) {
      const sectionLabel =
        selectedSection === "all"
          ? "All Sections"
          : sections.find((section) => section.sectionId === selectedSection)
              ?.sectionName || "All Sections";
      $("#filter-section-btn").text(sectionLabel);
    }
    if (selectedMovement) {
      const movementLabel =
        selectedMovement === "all"
          ? "All Movements"
          : formatMovementLabel(selectedMovement);
      $("#filter-movement-btn").text(movementLabel);
    }
  }

  function getPriceValue(product) {
    const price = product.salePrice ?? product.price;
    return price != null ? parseInt(price, 10) : 0;
  }

  function sortProducts(products, sortValue) {
    const copy = [...products];
    if (sortValue === "price-asc") {
      copy.sort((a, b) => getPriceValue(a) - getPriceValue(b));
    } else if (sortValue === "price-desc") {
      copy.sort((a, b) => getPriceValue(b) - getPriceValue(a));
    } else if (sortValue === "name-asc") {
      copy.sort((a, b) => (a.name || "").localeCompare(b.name || ""));
    }
    return copy;
  }

  function toggleFilteredView(isFiltered) {
    const wrapper = $("#filtered-products-wrapper");
    if (!wrapper.length) return;
    wrapper.toggleClass("d-none", !isFiltered);
    $(".product-section-block").toggleClass("d-none", isFiltered);
  }

  function applyProductFilters() {
    const sectionValue = $("#filter-section").val() || "all";
    const movementValue = $("#filter-movement").val() || "all";
    const sortValue = $("#filter-sort").val() || "default";

    const isFiltered =
      sectionValue !== "all" ||
      movementValue !== "all" ||
      sortValue !== "default";
    if (!isFiltered) {
      toggleFilteredView(false);
      resetProductSections();
      return;
    }

    fetchProductsData().done((data) => {
      const pageKey = getCurrentPageKey();
      let sections = getSectionsForPage(data, pageKey);
      if (sectionValue !== "all") {
        sections = sections.filter(
          (section) => section.sectionId === sectionValue,
        );
      }
      let products = getProductsForSections(sections);
      if (movementValue !== "all") {
        products = products.filter(
          (product) => (product.movement || "quartz") === movementValue,
        );
      }
      products = sortProducts(products, sortValue);

      toggleFilteredView(true);
      if (products.length === 0) {
        renderEmptyResult("filtered-products-container", "filters");
        $("#filtered-count").text("0 items");
        return;
      }
      renderProducts(products, "filtered-products-container");
      $("#filtered-count").text(
        `${products.length} item${products.length === 1 ? "" : "s"}`,
      );
    });
  }

  function initProductFilters() {
    if (!$("#filter-section").length) return;
    fetchProductsData().done((data) => {
      populateFilterOptions(data);
    });

    $(document).on("click", ".filter-dropdown-item", function (e) {
      e.preventDefault();
      const item = $(this);
      const target = item.data("target");
      const value = item.data("value");
      const label = item.data("label");
      if (!target) return;
      $(`#${target}`).val(value);
      $(`#${target}-btn`).text(label);
      applyProductFilters();
    });
    $("#apply-filter").on("click", function (e) {
      e.preventDefault();
      applyProductFilters();
    });
    $("#reset-filter").on("click", function (e) {
      e.preventDefault();
      $("#filter-section").val("all");
      $("#filter-movement").val("all");
      $("#filter-sort").val("default");
      $("#filter-section-btn").text("All Sections");
      $("#filter-movement-btn").text("All Movements");
      $("#filter-sort-btn").text("Featured");
      toggleFilteredView(false);
      resetProductSections();
    });
  }

  window.addEventListener("storage", function (event) {
    if (event.key === "commerza_products") {
      productsCache = null;
      productsCacheKey = null;
      fetchProductsData().done((data) => populateFilterOptions(data));
      applyProductFilters();
    }
  });

  function updateProductMeta(product) {
    if (!product) return;
    const title = `${product.name} | Commerza`;
    const description =
      product.description ||
      "Discover premium Commerza watches and accessories.";
    const canonicalUrl = `https://commerza.com/products.html?${product.id != null ? `id=${product.id}` : `name=${encodeURIComponent(product.name)}`}`;
    const imageUrl = product.image?.startsWith("http")
      ? product.image
      : `${window.location.origin}/${product.image}`;

    document.title = title;
    $('meta[name="description"]').attr("content", description);
    $('meta[property="og:title"]').attr("content", title);
    $('meta[property="og:description"]').attr("content", description);
    $('meta[property="og:url"]').attr("content", canonicalUrl);
    if (imageUrl) $('meta[property="og:image"]').attr("content", imageUrl);
    $('link[rel="canonical"]').attr("href", canonicalUrl);
  }

  function findProduct(data, params) {
    if (!data?.sections) return null;
    const id = params?.id;
    const name = params?.name;
    for (const section of data.sections) {
      const products = section.products || [];
      for (const product of products) {
        if (id != null && String(product.id) === String(id)) {
          return {
            ...product,
            sectionName: section.sectionName,
            sectionId: section.sectionId,
          };
        }
        if (
          name &&
          (product.name || "").toLowerCase().trim() ===
            name.toLowerCase().trim()
        ) {
          return {
            ...product,
            sectionName: section.sectionName,
            sectionId: section.sectionId,
          };
        }
      }
    }
    return null;
  }

  function renderProductDetail(product) {
    const container = $("#product-detail-container");
    if (!container.length) return;

    if (!product) {
      container.html(`
                <div class="text-center py-5">
                    <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: #ff6600;"></i>
                    <h3 class="text-white mt-3">Product not found</h3>
                    <p class="text-secondary">We couldn’t locate that product. Please return to the shop.</p>
                    <a href="index.html" class="btn product-btn-buy mt-3">Back to Home</a>
                </div>
            `);
      return;
    }

    const originalPrice =
      product.price != null ? parseInt(product.price).toLocaleString() : "";
    const salePrice =
      product.salePrice != null
        ? parseInt(product.salePrice).toLocaleString()
        : "";
    const movementLabel =
      product.movement === "auto"
        ? "Automatic"
        : product.movement === "smart"
          ? "Smart"
          : "Quartz";
    const stockText =
      product.stock != null ? `${product.stock} in stock` : "Stock available";
    const movementClass =
      product.movement === "smart"
        ? "movement-smart"
        : product.movement === "auto"
          ? "movement-auto"
          : "movement-quartz";
    const liveViewers = Math.floor(Math.random() * 45) + 120;
    const wishlistActive = isInWishlist(product.id, product.name);
    const wishlistIcon = wishlistActive ? "bi-heart-fill" : "bi-heart";
    const compareActive = isInCompare(product.id, product.name);
    const compareIcon = compareActive ? "bi-check2-circle" : "bi-sliders";

    container.html(`
            <div class="product-detail-card">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-5">
                        <div class="product-media">
                            <img src="${product.image}" class="p-image" alt="${product.name}">
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="product-badge-row">
                            <span class="movement-badge ${movementClass}">${movementLabel}</span>
                            <span class="stock-pill">${stockText}</span>
                            <span class="live-viewers" data-live-count="${liveViewers}">
                                <span class="live-dot" aria-hidden="true"></span>
                                <i class="bi bi-eye" aria-hidden="true"></i>
                                <span>${liveViewers} people viewing now</span>
                            </span>
                        </div>
                        <h1 class="product-name">${product.name}</h1>
                        <p class="product-desc">${product.description || ""}</p>
                        <div class="price-stack">
                            ${originalPrice ? `<span class="price-original">${originalPrice} PKR</span>` : ""}
                            ${salePrice ? `<span class="price-sale">${salePrice} PKR</span>` : ""}
                        </div>
                        <div class="spec-grid">
                            <div class="spec-card">
                                <span class="spec-label">Movement</span>
                                <span class="spec-value">${movementLabel}</span>
                            </div>
                            <div class="spec-card">
                                <span class="spec-label">Collection</span>
                                <span class="spec-value">${product.sectionName || "Commerza"}</span>
                            </div>
                            <div class="spec-card">
                                <span class="spec-label">Availability</span>
                                <span class="spec-value">${stockText}</span>
                            </div>
                        </div>
                        <div class="product-actions">
                            <a href="#" class="btn product-btn-buy product-btn-cart" data-product-id="${product.id ?? ""}" data-product-name="${product.name}" data-product-image="${product.image}" data-product-price="${product.price}" data-product-sale-price="${product.salePrice}">Buy Now</a>
                            <a href="#" class="btn product-btn-cart" data-product-id="${product.id ?? ""}" data-product-name="${product.name}" data-product-image="${product.image}" data-product-price="${product.price}" data-product-sale-price="${product.salePrice}">Add to Cart</a>
                            <button class="btn product-btn-buy wishlist-btn ${wishlistActive ? "active" : ""}" data-product-id="${product.id ?? ""}" data-product-name="${product.name}" data-product-image="${product.image}" data-product-price="${product.price}" data-product-sale-price="${product.salePrice}" type="button">
                                ${wishlistActive ? "In Wishlist" : "Add to Wishlist"}
                            </button>
                            <button class="btn product-btn-buy compare-btn" data-product-id="${product.id ?? ""}" data-product-name="${product.name}" data-product-image="${product.image}" data-product-price="${product.price}" data-product-sale-price="${product.salePrice}" data-product-stock="${product.stock ?? ""}" data-product-movement="${product.movement ?? ""}" type="button">
                                <i class="bi ${compareIcon}"></i> Compare
                            </button>
                            <a href="compare.html" class="btn product-btn-cart compare-link">View Compare</a>
                        </div>
                    </div>
                </div>
            </div>
        `);

    updateProductMeta(product);
    updateWishlistButtons();
    updateCompareButtons();
  }

  function renderShareButtons(product) {
    const container = $("#product-share-buttons");
    if (!container.length || !product) return;

    const url = `https://commerza.com/products.html?${product.id != null ? `id=${product.id}` : `name=${encodeURIComponent(product.name)}`}`;
    const text = encodeURIComponent(`Check out ${product.name} on Commerza.`);
    container.html(`
            <a class="btn" href="https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}" target="_blank" rel="noopener">Facebook</a>
            <a class="btn" href="https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${text}" target="_blank" rel="noopener">X</a>
            <a class="btn" href="https://wa.me/?text=${text}%20${encodeURIComponent(url)}" target="_blank" rel="noopener">WhatsApp</a>
            <button type="button" class="btn" id="copyProductLink">Copy Link</button>
        `);

    $("#copyProductLink")
      .off("click")
      .on("click", function () {
        navigator.clipboard
          ?.writeText(url)
          .then(() => {
            showNotif("Product link copied.", "success");
          })
          .catch(() => {
            showNotif("Unable to copy link.", "warning");
          });
      });
  }

  function renderReviewsMarquee() {
    const track = $("#reviews-track");
    if (!track.length) return;

    const reviews = [
      {
        name: "Ayesha K.",
        rating: 5,
        text: "Premium feel and looks stunning in person.",
      },
      {
        name: "Bilal R.",
        rating: 4,
        text: "Comfortable strap and solid build quality.",
      },
      {
        name: "Hira S.",
        rating: 5,
        text: "Fast delivery and beautiful packaging.",
      },
      {
        name: "Usman A.",
        rating: 4,
        text: "Great value for the price, smooth dial.",
      },
      {
        name: "Sara J.",
        rating: 5,
        text: "Looks classy and premium. Highly recommended.",
      },
    ];

    const cardHtml = reviews
      .map((review) => {
        const stars = "★".repeat(review.rating) + "☆".repeat(5 - review.rating);
        return `
                <div class="review-card">
                    <div class="text-warning mb-2">${stars}</div>
                    <p class="mb-2">${review.text}</p>
                    <div class="text-secondary small">${review.name}</div>
                </div>
            `;
      })
      .join("");

    track.html(cardHtml + cardHtml);
  }

  function renderRelatedProducts(data, currentProduct) {
    const container = $("#related-products-container");
    if (!container.length || !data?.sections) return;

    const allProducts = uniqueProducts(getAllProducts(data));
    const filtered = allProducts.filter(
      (p) =>
        String(p.id) !== String(currentProduct?.id) &&
        p.name !== currentProduct?.name,
    );

    const shuffled = filtered.sort(() => 0.5 - Math.random());
    const selected = shuffled.slice(0, 4);

    container.empty();
    if (selected.length === 0) {
      container.html(
        '<p class="text-secondary">No related products found.</p>',
      );
      return;
    }
    selected.forEach((product) => {
      container.append(createProductCard(product));
    });
  }

  function initProductDetailPage() {
    const container = $("#product-detail-container");
    if (!container.length) return;

    const params = new URLSearchParams(window.location.search);
    const productId = params.get("id");
    const productName = params.get("name");

    fetchProductsData()
      .done((data) => {
        const product = findProduct(data, { id: productId, name: productName });
        renderProductDetail(product);
        renderShareButtons(product);
        renderReviewsMarquee();
        renderRelatedProducts(data, product);
      })
      .fail(() => {
        renderProductDetail(null);
      });
  }

  function handleSearch(query) {
    const trimmed = query.trim();
    if (trimmed.length === 0) {
      resetProductSections();
      return;
    }

    fetchProductsData()
      .done((data) => {
        applySearchResults(trimmed, data);
      })
      .fail(() => {
        showNotif("Search failed. Please try again.", "warning");
      });
  }

  $(document).on("submit", ".search-form", function (e) {
    e.preventDefault();
    const query = $(this).find('input[type="search"]').val() || "";
    handleSearch(query);
    const offcanvas = $(".offcanvas.show");
    if (offcanvas.length) {
      bootstrap.Offcanvas.getInstance(offcanvas[0])?.hide();
    }
  });

  function renderSuggestions(input, products) {
    const form = input.closest(".search-form");
    let list = form.find(".search-suggestions");
    if (list.length === 0) {
      list = $('<div class="search-suggestions"></div>');
      form.append(list);
    }
    if (products.length === 0) {
      list.removeClass("show").empty();
      return;
    }
    list.html(
      products
        .map(
          (p) => `
            <button type="button" class="suggestion-item" data-name="${p.name}">
                <span class="suggestion-name">${p.name}</span>
                <span class="suggestion-price">${parseInt(p.salePrice).toLocaleString()} PKR</span>
            </button>
        `,
        )
        .join(""),
    );
    list.addClass("show");
  }

  function buildSuggestions(query) {
    const trimmed = query.trim();
    if (trimmed.length < 2) return [];
    if (!productsCache) return [];
    const normalized = trimmed.toLowerCase();
    const allProducts = uniqueProducts(getAllProducts(productsCache));
    return allProducts
      .filter((p) => {
        const haystack = `${p.name} ${p.description}`.toLowerCase();
        return haystack.includes(normalized);
      })
      .slice(0, 6);
  }

  $(document).on("input", '.search-form input[type="search"]', function () {
    const input = $(this);
    const value = input.val().trim();
    if (value.length === 0) {
      resetProductSections();
      input
        .closest(".search-form")
        .find(".search-suggestions")
        .removeClass("show")
        .empty();
      return;
    }
    fetchProductsData().done(() => {
      const suggestions = buildSuggestions(value);
      renderSuggestions(input, suggestions);
    });
  });

  $(document).on("click", ".suggestion-item", function (e) {
    e.preventDefault();
    e.stopPropagation();
    const name = $(this).data("name");
    const form = $(this).closest(".search-form");
    form.find('input[type="search"]').val(name);
    form.find(".search-suggestions").removeClass("show").empty();
    handleSearch(name);
  });

  $(document).on("click", function (e) {
    if (!$(e.target).closest(".search-form").length) {
      $(".search-suggestions").removeClass("show").empty();
    }
  });

  $(window).on("scroll", function () {
    if ($(this).scrollTop() > 300) {
      upBtn.addClass("show");
    } else {
      upBtn.removeClass("show");
    }
  });

  upBtn.on("click", function (e) {
    e.preventDefault();
    window.scrollTo({ top: 0, behavior: "smooth" });
  });

  $("#completeCheckoutBtn").on("click", function () {
    const currentUser = window.CommerzaAuth?.getCurrentUser?.();
    if (!currentUser) {
      window.location.href = "login.html?redirect=cart.html";
      return;
    }

    if (cart.length === 0) {
      showNotif("Cart is Empty! Add products before checkout.", "warning");
      return;
    }

    const form = $("#checkoutForm");
    if (!form[0].checkValidity()) {
      alert("Please fill all required fields");
      return;
    }

    const orderData = {
      orderId:
        "#ORD-" + String(Math.floor(Math.random() * 10000)).padStart(4, "0"),
      customerName: $("#customerName").val(),
      paymentMethod: "Cash on Delivery (COD)",
      address: $("#customerAddress").val(),
      phone: $("#customerPhone").val(),
      email: $("#customerEmail").val() || "Not provided",
      items: cart.map((item) => ({
        name: item.name,
        price: item.price,
        quantity: item.quantity,
        image: item.image,
      })),
      subtotal: parseInt($("#cart-subtotal").text().replace(/\D/g, "")),
      shipping: 0,
      total: parseInt($("#cart-total").text().replace(/\D/g, "")),
      orderDate: new Date().toISOString().split("T")[0],
      status: "Pending",
    };

    let orders = JSON.parse(localStorage.getItem("commerza_orders")) || [];
    orders.push(orderData);
    localStorage.setItem("commerza_orders", JSON.stringify(orders));

    cart = [];
    localStorage.setItem("commerza_cart", JSON.stringify(cart));
    updateCartBadge();

    showNotif(`Order ${orderData.orderId} placed!`, "success");
    bootstrap.Modal.getInstance(
      document.getElementById("checkoutModal"),
    ).hide();
    $("#checkoutForm")[0].reset();
    setTimeout(
      () =>
        (window.location.href = "account.html?orderId=" + orderData.orderId),
      2000,
    );
  });
});

function loadProductsBySection(sectionId, containerId) {
  const stored = localStorage.getItem("commerza_products");
  if (stored) {
    try {
      const data = JSON.parse(stored);
      const section = data.sections.find((s) => s.sectionId === sectionId);
      if (section) renderProducts(section.products, containerId);
      return;
    } catch (error) {
      console.warn("Invalid local products data, fallback to JSON");
    }
  }

  fetch("products.json")
    .then((response) => response.json())
    .then((data) => {
      const section = data.sections.find((s) => s.sectionId === sectionId);
      if (section) renderProducts(section.products, containerId);
    })
    .catch((error) => console.error("Error loading products:", error));
}

function renderProducts(products, containerId) {
  const container = $(`#${containerId}`);
  if (container.length === 0) return;

  container.empty();

  const productsPerRow = 4;
  const remainder = products.length % productsPerRow;
  const shouldCenterLastRow = remainder > 0 && remainder <= 3;
  const splitIndex = shouldCenterLastRow
    ? products.length - remainder
    : products.length;

  const firstBatch = products.slice(0, splitIndex);
  firstBatch.forEach((product) => {
    container.append(createProductCard(product));
  });

  if (shouldCenterLastRow) {
    const lastBatch = products.slice(splitIndex);
    lastBatch.forEach((product) => {
      const productCardHtml = createProductCard(product);
      const wrappedCard = $(productCardHtml);
      wrappedCard.addClass('last-row-product');
      container.append(wrappedCard);
    });
  }
}

function createProductCard(product) {
  const originalPrice = parseInt(product.price).toLocaleString();
  const salePrice = parseInt(product.salePrice).toLocaleString();
  const movementType =
    product.movement === "auto"
      ? "auto"
      : product.movement === "smart"
        ? "smart"
        : "quartz";
  const saleBadge =
    product.movement !== "smart"
      ? '<span class="sale-badge">PREMIUM SALE</span>'
      : "";
  const detailQuery =
    product.id != null
      ? `id=${product.id}`
      : `name=${encodeURIComponent(product.name)}`;
  const detailUrl = `products.html?${detailQuery}`;
  const wishlistActive = isInWishlist(product.id, product.name);
  const wishlistIcon = wishlistActive ? "bi-heart-fill" : "bi-heart";

  return `
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4 d-flex">
            <div class="card product-card" data-price="${product.salePrice}" data-movement="${movementType}" data-product-id="${product.id ?? ""}" data-product-name="${(product.name || "").toLowerCase().trim()}">
                <div style="position: relative;">
                    <button class="wishlist-btn ${wishlistActive ? "active" : ""}" data-product-id="${product.id ?? ""}" data-product-name="${product.name}" data-product-image="${product.image}" data-product-price="${product.price}" data-product-sale-price="${product.salePrice}" type="button" aria-label="Toggle wishlist">
                        <i class="bi ${wishlistIcon}"></i>
                    </button>
                    <a href="${detailUrl}" style="text-decoration: none; color: inherit;">
                        <img src="${product.image}"
                            class="card-img-top p-image" loading="lazy" alt="${product.name}">
                    </a>
                    ${saleBadge}
                </div>
                <div class="card-body">
                    <h3 class="card-title product-name">
                        <a href="${detailUrl}" style="text-decoration: none; color: inherit;">${product.name}</a>
                    </h3>
                    <p class="card-text product-desc">${product.description}</p>
                    <div class="mb-3">
                        <span class="original-price"
                            style="text-decoration: line-through; color: #b0b0b0;">${originalPrice} PKR</span>
                        <span class="sale-price"
                            style="color: #ff6600; font-weight: bold; margin-left: 5px;">${salePrice} PKR</span>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="#" class="btn product-btn-buy product-btn-cart flex-fill text-center justify-content-center align-items-center" data-product-id="${product.id ?? ""}" data-product-name="${product.name}" data-product-image="${product.image}" data-product-price="${product.price}" data-product-sale-price="${product.salePrice}">Buy Now</a>
                        <a href="#" class="btn product-btn-cart flex-fill text-center justify-content-center align-items-center" data-product-id="${product.id ?? ""}" data-product-name="${product.name}" data-product-image="${product.image}" data-product-price="${product.price}" data-product-sale-price="${product.salePrice}">Add to Cart</a>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function initNewsletterModal() {
  const modalEl = document.getElementById("newsletterModal");
  if (!modalEl) return;

  const dismissed = localStorage.getItem("commerza_newsletter_dismissed");
  const dismissedAt = parseInt(
    localStorage.getItem("commerza_newsletter_dismissed_at") || "0",
    10,
  );
  const cooldownMs = 7 * 24 * 60 * 60 * 1000;
  if (dismissed && dismissedAt && Date.now() - dismissedAt < cooldownMs) return;
  if (dismissed && !dismissedAt) {
    localStorage.removeItem("commerza_newsletter_dismissed");
  }

  setTimeout(() => {
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  }, 1200);

  $("#newsletterForm").on("submit", function (e) {
    e.preventDefault();
    const email = $("#newsletterEmail").val().trim();
    if (!email) return;
    upsertNewsletterSubscriber(email, "modal");
    localStorage.setItem("commerza_newsletter_dismissed", "true");
    localStorage.setItem(
      "commerza_newsletter_dismissed_at",
      String(Date.now()),
    );
    showNotif("Thanks for subscribing!", "success");
    bootstrap.Modal.getInstance(modalEl)?.hide();
  });

  modalEl.addEventListener(
    "hidden.bs.modal",
    () => {
      localStorage.setItem("commerza_newsletter_dismissed", "true");
      localStorage.setItem(
        "commerza_newsletter_dismissed_at",
        String(Date.now()),
      );
    },
    { once: true },
  );
}

function normalizeNewsletterEmail(email) {
  return (email || "").toString().trim().toLowerCase();
}

function upsertNewsletterSubscriber(email, source) {
  const normalized = normalizeNewsletterEmail(email);
  if (!normalized || !normalized.includes("@")) return false;

  const key = "commerza_newsletter_subscribers";
  const list = (() => {
    const stored = localStorage.getItem(key);
    if (!stored) return [];
    try {
      return JSON.parse(stored);
    } catch (error) {
      return [];
    }
  })();

  const now = new Date().toISOString();
  const existingIndex = list.findIndex(
    (item) => normalizeNewsletterEmail(item.email || item) === normalized,
  );

  if (existingIndex >= 0) {
    const existing = list[existingIndex];
    const sources = Array.isArray(existing.sources) ? existing.sources : [];
    if (source && !sources.includes(source)) {
      sources.push(source);
    }
    list[existingIndex] = {
      ...existing,
      email: normalized,
      sources,
      updatedAt: now,
      subscribedAt: existing.subscribedAt || now,
    };
  } else {
    list.push({
      email: normalized,
      sources: source ? [source] : [],
      subscribedAt: now,
      updatedAt: now,
    });
  }

  localStorage.setItem(key, JSON.stringify(list));
  localStorage.setItem("commerza_newsletter_email", normalized);
  return true;
}

function initNewsletterInlineForm() {
  const form = document.querySelector(".newsletter-form");
  if (!form) return;

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    const input = form.querySelector('input[type="email"]');
    const email = input ? input.value.trim() : "";
    if (!upsertNewsletterSubscriber(email, "inline")) {
      showNotif("Please enter a valid email address.", "warning");
      return;
    }
    if (input) input.value = "";
    showNotif("You are on the list!", "success");
  });
}

function initOrderTrackingPage() {
  const form = $("#orderTrackingForm");
  if (!form.length) return;

  const result = $("#orderTrackingResult");

  form.on("submit", function (e) {
    e.preventDefault();
    const orderId = $("#orderIdInput").val().trim();
    const email = $("#orderEmailInput").val().trim().toLowerCase();
    const orders = JSON.parse(localStorage.getItem("commerza_orders")) || [];

    const match = orders.find((order) => {
      const matchesId =
        (order.orderId || "").toLowerCase() === orderId.toLowerCase();
      const matchesEmail =
        !email || (order.email || "").toLowerCase() === email;
      return matchesId && matchesEmail;
    });

    if (!match) {
      result.html(`
                <div class="card product-card">
                    <div class="card-body text-center">
                        <i class="bi bi-exclamation-triangle" style="font-size:2rem; color:#ff6600;"></i>
                        <h3 class="product-name mt-2">Order not found</h3>
                        <p class="product-desc">Please check your Order ID and email.</p>
                    </div>
                </div>
            `);
      return;
    }

    const statusClass =
      match.status === "Pending"
        ? "status-pending"
        : match.status === "Delivered"
          ? "status-delivered"
          : match.status === "Cancelled"
            ? "status-cancelled"
            : "status-shipped";

    result.html(`
            <div class="card product-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
                        <div>
                            <h3 class="product-name mb-1">${match.orderId}</h3>
                            <p class="product-desc mb-0">${match.orderDate}</p>
                        </div>
                        <span class="status-badge ${statusClass}">${match.status}</span>
                    </div>
                    <p class="text-secondary mb-2"><strong>Total:</strong> ${match.total.toLocaleString()} PKR</p>
                    <p class="text-secondary mb-2"><strong>Address:</strong> ${match.address}</p>
                    <div class="mt-3">
                        ${match.items
                          .map(
                            (item) => `
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <img src="${item.image}" alt="${item.name}" style="width: 42px; height: 42px; object-fit: cover; border-radius: 6px;">
                                <div>
                                    <div class="text-white">${item.name}</div>
                                    <div class="text-secondary small">Qty: ${item.quantity} · ${item.price} PKR</div>
                                </div>
                            </div>
                        `,
                          )
                          .join("")}
                    </div>
                </div>
            </div>
        `);
  });
}

// =========================
// Wishlist
// =========================
const WISHLIST_KEY = "commerza_wishlist";
const COMPARE_KEY = "commerza_compare";

function getWishlist() {
  try {
    return JSON.parse(localStorage.getItem(WISHLIST_KEY)) || [];
  } catch (error) {
    return [];
  }
}

function saveWishlist(items) {
  localStorage.setItem(WISHLIST_KEY, JSON.stringify(items));
}

function getCompare() {
  try {
    return JSON.parse(localStorage.getItem(COMPARE_KEY)) || [];
  } catch (error) {
    return [];
  }
}

function saveCompare(items) {
  localStorage.setItem(COMPARE_KEY, JSON.stringify(items));
}

function isInCompare(id, name) {
  const list = getCompare();
  return list.some(
    (item) => String(item.id) === String(id) || item.name === name,
  );
}

function toggleCompare(item) {
  const list = getCompare();
  const existsIndex = list.findIndex(
    (entry) => String(entry.id) === String(item.id) || entry.name === item.name,
  );
  if (existsIndex > -1) {
    list.splice(existsIndex, 1);
    saveCompare(list);
    showNotif("Removed from compare.", "warning");
    return false;
  }
  if (list.length >= 4) {
    showNotif("Compare limit is 4 products.", "warning");
    return false;
  }
  list.push(item);
  saveCompare(list);
  showNotif("Added to compare!", "success");
  return true;
}

function updateCompareButtons() {
  $(".compare-btn").each(function () {
    const btn = $(this);
    const id = btn.data("productId");
    const name = btn.data("productName");
    const active = isInCompare(id, name);
    btn.toggleClass("active", active);
    btn
      .find("i")
      .toggleClass("bi-check2-circle", active)
      .toggleClass("bi-sliders", !active);
  });
}

function updateWishlistBadge() {
  const count = getWishlist().length;
  $("#wishlist-count").text(count);
  $("#wishlist-count-mobile").text(count);
}

function isInWishlist(id, name) {
  const list = getWishlist();
  return list.some(
    (item) => String(item.id) === String(id) || item.name === name,
  );
}

function toggleWishlist(item) {
  const list = getWishlist();
  const existsIndex = list.findIndex(
    (entry) => String(entry.id) === String(item.id) || entry.name === item.name,
  );
  if (existsIndex > -1) {
    list.splice(existsIndex, 1);
    saveWishlist(list);
    updateWishlistBadge();
    showNotif("Removed from wishlist.", "warning");
    return false;
  }
  list.push(item);
  saveWishlist(list);
  updateWishlistBadge();
  showNotif("Added to wishlist!", "success");
  return true;
}

function updateWishlistButtons() {
  $(".wishlist-btn").each(function () {
    const btn = $(this);
    const id = btn.data("productId");
    const name = btn.data("productName");
    const active = isInWishlist(id, name);
    btn.toggleClass("active", active);
    btn
      .find("i")
      .toggleClass("bi-heart-fill", active)
      .toggleClass("bi-heart", !active);
    if (btn.closest(".product-detail-card").length) {
      btn.text(active ? "In Wishlist" : "Add to Wishlist");
    }
  });
}

function renderWishlistPage() {
  const container = $("#wishlist-container");
  if (!container.length) return;

  const list = getWishlist();
  if (list.length === 0) {
    container.html(`
            <div class="text-center py-5">
                <i class="bi bi-heart" style="font-size: 3rem; color: #ff6600;"></i>
                <h3 class="text-white mt-3">Your wishlist is empty</h3>
                <p class="text-secondary">Start saving your favorite watches.</p>
                <a href="index.html" class="btn product-btn-buy mt-3">Browse Products</a>
            </div>
        `);
    return;
  }

  container.empty();
  list.forEach((item) => {
    const originalPrice =
      item.price != null ? parseInt(item.price).toLocaleString() : "";
    const salePrice =
      item.salePrice != null ? parseInt(item.salePrice).toLocaleString() : "";
    container.append(`
            <div class="card product-card mb-3">
                <div class="card-body d-flex align-items-center gap-3">
                    <img src="${item.image}" class="wishlist-img me-2" alt="${item.name}" />
                    <div class="flex-grow-1">
                        <h3 class="product-name mb-1">${item.name}</h3>
                        <div class="mb-2">
                            ${originalPrice ? `<span class="original-price" style="text-decoration: line-through; color: #b0b0b0;">${originalPrice} PKR</span>` : ""}
                            ${salePrice ? `<span class="sale-price" style="color: #ff6600; font-weight: bold; margin-left: 6px;">${salePrice} PKR</span>` : ""}
                        </div>
                        <div class="d-flex gap-2">
                            <a href="products.html?id=${item.id}" class="btn product-btn-buy">View</a>
                            <button class="btn product-btn-cart wishlist-remove-btn" data-product-id="${item.id}" data-product-name="${item.name}">Remove</button>
                        </div>
                    </div>
                </div>
            </div>
        `);
  });
}

function renderComparePage() {
  const container = $("#compare-container");
  if (!container.length) return;

  const list = getCompare();
  if (list.length === 0) {
    container.html(`
            <div class="text-center py-5">
                <i class="bi bi-sliders" style="font-size: 3rem; color: #ff6600;"></i>
                <h3 class="text-white mt-3">No products to compare</h3>
                <p class="text-secondary">Add items from a product page to compare.</p>
                <a href="index.html" class="btn product-btn-buy mt-3">Browse Products</a>
            </div>
        `);
    return;
  }

  const rows = [
    {
      label: "Image",
      key: "image",
      render: (item) =>
        `<img src="${item.image}" alt="${item.name}" style="width: 120px; height: 120px; object-fit: cover; border-radius: 10px;" />`,
    },
    {
      label: "Name",
      key: "name",
      render: (item) => `<div class="text-white fw-bold">${item.name}</div>`,
    },
    {
      label: "Price",
      key: "price",
      render: (item) =>
        `${item.price != null ? parseInt(item.price).toLocaleString() : "N/A"} PKR`,
    },
    {
      label: "Sale Price",
      key: "salePrice",
      render: (item) =>
        `${item.salePrice != null ? parseInt(item.salePrice).toLocaleString() : "N/A"} PKR`,
    },
    {
      label: "Movement",
      key: "movement",
      render: (item) => (item.movement ? item.movement.toString() : "Quartz"),
    },
    {
      label: "Stock",
      key: "stock",
      render: (item) => (item.stock != null ? item.stock : "N/A"),
    },
    {
      label: "Action",
      key: "action",
      render: (item) =>
        `<button class="btn product-btn-buy compare-remove-btn" data-product-id="${item.id}" data-product-name="${item.name}">Remove</button>`,
    },
  ];

  let table =
    '<div class="table-responsive"><table class="table table-dark table-bordered align-middle">';
  table += "<tbody>";
  rows.forEach((row) => {
    table += `<tr><th scope="row" style="min-width: 140px;">${row.label}</th>`;
    list.forEach((item) => {
      table += `<td>${row.render(item)}</td>`;
    });
    table += "</tr>";
  });
  table += "</tbody></table></div>";
  container.html(table);
}
