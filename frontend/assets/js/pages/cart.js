(function () {
  function readCartPageConfig() {
    const configNode = document.getElementById("commerzaCartPageConfig");
    if (!configNode) {
      return {};
    }

    try {
      const parsed = JSON.parse(configNode.textContent || "{}");
      return parsed && typeof parsed === "object" ? parsed : {};
    } catch (error) {
      return {};
    }
  }

  const cartPageConfig = readCartPageConfig();

  $(function () {
    const prefill =
      cartPageConfig.prefillData &&
      typeof cartPageConfig.prefillData === "object"
        ? cartPageConfig.prefillData
        : {};
    const isLoggedIn = Boolean(cartPageConfig.isLoggedIn);
    const captchaEnabled = Boolean(cartPageConfig.captchaEnabled);
    const captchaFieldName = (cartPageConfig.captchaFieldName || "").toString();
    const codHighValueThreshold =
      Number(cartPageConfig.codHighValueThreshold) || 0;
    const prefillData = {
      name: (prefill.name || "").toString(),
      email: (prefill.email || "").toString(),
      phone: (prefill.phone || "").toString(),
      address: (prefill.address || "").toString(),
    };

    function buildRequestId(scope) {
      const prefix = (scope || "checkout")
        .toString()
        .replace(/[^a-z0-9_-]/gi, "")
        .toLowerCase();
      const timePart = Date.now().toString(36);

      let randomPart = "";
      if (
        window.crypto &&
        typeof window.crypto.getRandomValues === "function"
      ) {
        const bytes = new Uint8Array(8);
        window.crypto.getRandomValues(bytes);
        randomPart = Array.from(bytes, (value) =>
          value.toString(16).padStart(2, "0"),
        ).join("");
      } else {
        randomPart =
          Math.random().toString(16).slice(2) +
          Math.random().toString(16).slice(2);
      }

      return `${prefix}-${timePart}-${randomPart}`;
    }

    function updatePaymentMethodPreview() {
      const methodKey = ($("#paymentMethod").val() || "cod")
        .toString()
        .toLowerCase();
      const methodMap = {
        cod: {
          icon: "bi-cash-coin",
          title: "Cash on Delivery",
          desc: "Pay when your order reaches your doorstep.",
        },
        jazzcash: {
          icon: "bi-phone",
          title: "JazzCash (Sandbox)",
          desc: "Sandbox wallet flow selected. Add payer details and reference below.",
        },
        easypaisa: {
          icon: "bi-wallet2",
          title: "Easypaisa (Sandbox)",
          desc: "Sandbox wallet flow selected. Add payer details and reference below.",
        },
        paypal: {
          icon: "bi-paypal",
          title: "PayPal (Sandbox)",
          desc: "Sandbox PayPal flow selected. Add payer details and reference below.",
        },
        stripe: {
          icon: "bi-credit-card",
          title: "Stripe (Sandbox)",
          desc: "Sandbox Stripe flow selected. Add payer details and reference below.",
        },
        card: {
          icon: "bi-credit-card-2-front",
          title: "Credit/Debit Card (Sandbox)",
          desc: "Sandbox card flow selected. Add payer details and reference below.",
        },
      };

      const selected = methodMap[methodKey] || methodMap.cod;
      $("#paymentMethodIcon").attr("class", `bi ${selected.icon}`);
      $("#paymentMethodTitle").text(selected.title);
      $("#paymentMethodDesc").text(selected.desc);

      const requiresSandboxDetails = methodKey !== "cod";
      $("#paymentExtraFields").toggleClass("d-none", !requiresSandboxDetails);
      $("#paymentSender, #paymentReference").prop(
        "required",
        requiresSandboxDetails,
      );
      updateCodOtpVisibility();
    }

    function parseAmountFromText(rawValue) {
      const normalized = (rawValue || "").toString().replace(/,/g, "");
      const match = normalized.match(/-?\d+(?:\.\d+)?/);
      if (!match) {
        return 0;
      }

      const parsed = Number(match[0]);
      return Number.isFinite(parsed) ? parsed : 0;
    }

    function currentCheckoutTotalAmount() {
      return parseAmountFromText($("#cart-total").text());
    }

    function codOtpRequiredForCurrentCheckout() {
      const methodKey = ($("#paymentMethod").val() || "cod")
        .toString()
        .toLowerCase();
      if (methodKey !== "cod") {
        return false;
      }

      if (
        !Number.isFinite(codHighValueThreshold) ||
        codHighValueThreshold <= 0
      ) {
        return false;
      }

      return currentCheckoutTotalAmount() >= codHighValueThreshold;
    }

    function setCodOtpFeedback(message, type) {
      const node = $("#codOtpFeedback");
      node.removeClass("text-success text-danger text-warning text-secondary");
      const safeType = (type || "").toString().toLowerCase();
      if (safeType === "success") {
        node.addClass("text-success");
      } else if (safeType === "warning") {
        node.addClass("text-warning");
      } else if (safeType === "danger") {
        node.addClass("text-danger");
      } else {
        node.addClass("text-secondary");
      }
      node.text((message || "").toString());
    }

    function updateCodOtpVisibility() {
      const required = codOtpRequiredForCurrentCheckout();
      $("#codOtpSection").toggleClass("d-none", !required);

      const hint =
        Number.isFinite(codHighValueThreshold) && codHighValueThreshold > 0
          ? `For COD orders of PKR ${codHighValueThreshold.toFixed(2)} or above, verify the 6-digit code sent to your email.`
          : "COD verification code is currently disabled for this store.";
      $("#codOtpHint").text(hint);

      if (!required) {
        $("#codEmailOtp").val("");
        setCodOtpFeedback("", "");
      }
    }

    function setPaymentMethod(methodKey, labelText) {
      const normalized = (methodKey || "cod").toString().toLowerCase();
      $("#paymentMethod").val(normalized);

      const label = (labelText || "").toString().trim();
      if (label !== "") {
        $("#paymentMethodBtn").text(label);
      }

      $("#paymentMethodMenu .checkout-payment-option").removeClass("active");
      $(
        `#paymentMethodMenu .checkout-payment-option[data-value="${normalized}"]`,
      ).addClass("active");
      updatePaymentMethodPreview();
    }

    $("#paymentMethodMenu").on(
      "click",
      ".checkout-payment-option",
      function (event) {
        event.preventDefault();
        const methodKey = ($(this).data("value") || "cod").toString();
        const label = ($(this).data("label") || $(this).text() || "")
          .toString()
          .trim();
        setPaymentMethod(methodKey, label);
      },
    );

    $("#sendCodOtpBtn").on("click", async function () {
      if (!isLoggedIn) {
        window.location.href = "login.php?redirect=cart.php";
        return;
      }

      if (!codOtpRequiredForCurrentCheckout()) {
        setCodOtpFeedback(
          "Verification code is required only for high-value COD orders.",
          "warning",
        );
        return;
      }

      const email = ($("#customerEmail").val() || "")
        .toString()
        .trim()
        .toLowerCase();
      const fullName = ($("#customerName").val() || "").toString().trim();

      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        setCodOtpFeedback("Enter a valid checkout email first.", "warning");
        return;
      }

      const button = $(this);
      button.prop("disabled", true).text("Sending OTP...");
      setCodOtpFeedback("Sending verification code...", "");

      try {
        const payload = new URLSearchParams();
        payload.set(
          "csrf_token",
          ($('input[name="csrf_token"]').val() || "").toString(),
        );
        payload.set("action", "request_cod_otp");
        payload.set(
          "payment_method",
          ($("#paymentMethod").val() || "cod").toString(),
        );
        payload.set("customer_name", fullName);
        payload.set("customer_email", email);
        payload.set("order_total", String(currentCheckoutTotalAmount()));

        const response = await fetch("cart.php", {
          method: "POST",
          credentials: "same-origin",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
            "X-Requested-With": "XMLHttpRequest",
            Accept: "application/json",
          },
          body: payload.toString(),
        });

        const result = await response.json().catch(() => null);
        if (!response.ok || !result?.ok) {
          throw new Error(
            result?.message || "Unable to send verification code.",
          );
        }

        setCodOtpFeedback(
          result?.message || "Verification code sent to your email.",
          "success",
        );
      } catch (error) {
        setCodOtpFeedback(
          error && error.message
            ? error.message
            : "Unable to send verification code.",
          "danger",
        );
      } finally {
        button.prop("disabled", false).text("Send OTP");
      }
    });

    $("#serverAlert, #successAlert").each(function () {
      const element = $(this);
      setTimeout(function () {
        element.fadeOut(400);
      }, 3500);
    });

    function showCheckoutEmptyAlert() {
      const alertBox = $("#checkoutEmptyAlert");
      alertBox.stop(true, true).fadeIn(220).delay(2200).fadeOut(280);
    }

    function hasCompletedCaptchaChallenge() {
      if (!captchaEnabled) {
        return true;
      }

      const form = document.getElementById("checkoutForm");
      if (!form) {
        return false;
      }

      const readValue = function (name) {
        const field = form.querySelector(`[name="${name}"]`);
        if (!field) {
          return "";
        }

        return (field.value || "").toString().trim();
      };

      const v2Token = captchaFieldName ? readValue(captchaFieldName) : "";
      const v3Token = readValue("g-recaptcha-v3-response");
      const fallbackAnswer = readValue("commerza_captcha_answer");
      const fallbackToken = readValue("commerza_captcha_token");
      const hasFallback = fallbackAnswer !== "" && fallbackToken !== "";

      return v2Token !== "" || v3Token !== "" || hasFallback;
    }

    function setCheckoutCaptchaError(message) {
      $("#checkoutCaptchaError").text((message || "").toString());
    }

    $("#checkoutModal").on("show.bs.modal", function (event) {
      const totalItems = parseInt($("#total-items-qty").text(), 10) || 0;

      if (totalItems <= 0) {
        showCheckoutEmptyAlert();
        event.preventDefault();
        return;
      }

      if (!isLoggedIn) {
        window.location.href = "login.php?redirect=cart.php";
        event.preventDefault();
        return;
      }

      if (!$("#customerName").val()) {
        $("#customerName").val(prefillData.name || "");
      }
      if (!$("#customerEmail").val()) {
        $("#customerEmail").val(prefillData.email || "");
      }
      if (!$("#customerPhone").val()) {
        $("#customerPhone").val(prefillData.phone || "");
      }
      if (!$("#customerAddress").val()) {
        $("#customerAddress").val(prefillData.address || "");
      }

      setPaymentMethod("cod", "Cash on Delivery (COD)");
      updateCodOtpVisibility();
    });

    const totalNode = document.getElementById("cart-total");
    if (totalNode && window.MutationObserver) {
      const totalObserver = new MutationObserver(function () {
        updateCodOtpVisibility();
      });

      totalObserver.observe(totalNode, {
        childList: true,
        characterData: true,
        subtree: true,
      });
    }

    $("#checkoutForm").on("submit", function (event) {
      if (!$("#checkoutRequestId").val()) {
        $("#checkoutRequestId").val(buildRequestId("checkout_place_order"));
      }

      setCheckoutCaptchaError("");

      const totalItems = parseInt($("#total-items-qty").text(), 10) || 0;
      const submitBtn = $("#completeCheckoutBtn");

      if (captchaEnabled && !hasCompletedCaptchaChallenge()) {
        event.preventDefault();
        setCheckoutCaptchaError(
          "Complete one verification method: Google CAPTCHA or backup question.",
        );
        return;
      }

      if (totalItems <= 0) {
        showCheckoutEmptyAlert();
        event.preventDefault();
        return;
      }

      if (!this.checkValidity()) {
        this.reportValidity();
        event.preventDefault();
        return;
      }

      submitBtn.prop("disabled", true).text("Placing Order...");
    });
  });
})();
