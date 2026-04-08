function getCookieValue(name) {
  const match = document.cookie.match(
    new RegExp(
      `(?:^|; )${name.replace(/[.$?*|{}()\[\]\\/+^]/g, "\\$&")}=([^;]*)`,
    ),
  );
  return match ? decodeURIComponent(match[1]) : "";
}

function setCookieValue(name, value, maxAgeSeconds) {
  document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=${maxAgeSeconds}; SameSite=Lax`;
}

function initNewsletterModal() {
  const modalEl = document.getElementById("newsletterModal");
  if (!modalEl) return;

  const dismissed = getCookieValue("commerza_newsletter_dismissed");
  const dismissedAt = parseInt(
    getCookieValue("commerza_newsletter_dismissed_at") || "0",
    10,
  );
  const cooldownMs = 7 * 24 * 60 * 60 * 1000;
  if (dismissed && dismissedAt && Date.now() - dismissedAt < cooldownMs) return;

  setTimeout(() => {
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  }, 1200);

  $("#newsletterForm").on("submit", async function (e) {
    e.preventDefault();
    const email = $("#newsletterEmail").val().trim();
    const csrfToken = resolveNewsletterCsrfToken(this);
    if (!email) return;

    const ok = await upsertNewsletterSubscriber(email, "modal", csrfToken);
    if (!ok) {
      showNotif("Unable to subscribe right now.", "warning");
      return;
    }

    const weekInSeconds = 7 * 24 * 60 * 60;
    setCookieValue("commerza_newsletter_dismissed", "true", weekInSeconds);
    setCookieValue(
      "commerza_newsletter_dismissed_at",
      String(Date.now()),
      weekInSeconds,
    );
    showNotif("Thanks for subscribing!", "success");
    bootstrap.Modal.getInstance(modalEl)?.hide();
  });

  modalEl.addEventListener(
    "hidden.bs.modal",
    () => {
      const weekInSeconds = 7 * 24 * 60 * 60;
      setCookieValue("commerza_newsletter_dismissed", "true", weekInSeconds);
      setCookieValue(
        "commerza_newsletter_dismissed_at",
        String(Date.now()),
        weekInSeconds,
      );
    },
    { once: true },
  );
}

function normalizeNewsletterEmail(email) {
  return (email || "").toString().trim().toLowerCase();
}

function resolveNewsletterCsrfToken(formElement = null) {
  const formToken = formElement?.querySelector(
    'input[name="csrf_token"]',
  )?.value;
  if ((formToken || "").toString().trim()) {
    return formToken.toString().trim();
  }

  const fallbackField = document.querySelector(
    '#newsletterForm input[name="csrf_token"], .newsletter-form input[name="csrf_token"]',
  );
  const fallbackToken = (fallbackField?.value || "").toString().trim();
  if (fallbackToken) {
    return fallbackToken;
  }

  return (window.CommerzaCsrfToken || "").toString().trim();
}

async function upsertNewsletterSubscriber(email, source, csrfOverride = "") {
  const normalized = normalizeNewsletterEmail(email);
  if (!normalized || !normalized.includes("@")) {
    return false;
  }

  const csrfToken = (csrfOverride || "").toString().trim();
  if (!csrfToken) {
    return false;
  }

  const payload = new URLSearchParams();
  payload.set("email", normalized);
  payload.set("source", source || "website");
  payload.set("csrf_token", csrfToken);

  try {
    const response = await fetch("backend/newsletter_api.php", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
      },
      body: payload.toString(),
    });

    const data = await response.json();
    if (!response.ok || !data?.ok) {
      return false;
    }

    setCookieValue("commerza_newsletter_email", normalized, 90 * 24 * 60 * 60);
    return true;
  } catch (error) {
    return false;
  }
}

function initNewsletterInlineForm() {
  const form = document.querySelector(".newsletter-form");
  if (!form) return;

  form.addEventListener("submit", async function (event) {
    event.preventDefault();
    const input = form.querySelector('input[type="email"]');
    const email = input ? input.value.trim() : "";
    const csrfToken = resolveNewsletterCsrfToken(form);

    const ok = await upsertNewsletterSubscriber(email, "inline", csrfToken);
    if (!ok) {
      showNotif("Please enter a valid email address.", "warning");
      return;
    }
    if (input) input.value = "";
    showNotif("You are on the list!", "success");
  });
}
