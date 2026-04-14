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
    const stripeEnabled = Boolean(cartPageConfig.stripeEnabled);
    const stripePublishableKey = (cartPageConfig.stripePublishableKey || "")
      .toString()
      .trim();
    const stripeAvailable =
      stripeEnabled &&
      stripePublishableKey !== "" &&
      typeof window.Stripe === "function";

    const prefillData = {
      name: (prefill.name || "").toString(),
      email: (prefill.email || "").toString(),
      phone: (prefill.phone || "").toString(),
      address: (prefill.address || "").toString(),
    };

    const stripeState = {
      stripe: null,
      elements: null,
      cardElement: null,
      processing: false,
      paid: false,
      intentId: "",
      clientSecret: "",
      lastAmountMinor: 0,
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

    function currentPaymentMethod() {
      return ($("#paymentMethod").val() || "cod").toString().toLowerCase();
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

    function currentCheckoutTotalMinor() {
      return Math.max(0, Math.round(currentCheckoutTotalAmount() * 100));
    }

    function codOtpRequiredForCurrentCheckout() {
      if (currentPaymentMethod() !== "cod") {
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

    function setStripeFeedback(message, type) {
      const node = $("#stripeFeedback");
      if (!node.length) {
        return;
      }

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

    function setStripePaymentStatus(label, tone) {
      const badge = $("#stripePaymentStatus");
      if (!badge.length) {
        return;
      }

      badge
        .removeClass("bg-secondary bg-success bg-warning bg-danger")
        .addClass(`bg-${tone || "secondary"}`)
        .text((label || "Payment Pending").toString());
    }

    function refreshStripeActionButton() {
      const button = $("#stripePayBtn");
      if (!button.length) {
        return;
      }

      if (currentPaymentMethod() !== "stripe") {
        button.prop("disabled", true).text("Pay Securely");
        return;
      }

      if (!stripeAvailable) {
        button.prop("disabled", true).text("Stripe Unavailable");
        return;
      }

      if (stripeState.processing) {
        button.prop("disabled", true).text("Processing...");
        return;
      }

      if (stripeState.paid && stripeState.intentId) {
        button.prop("disabled", true).text("Payment Completed");
        return;
      }

      button.prop("disabled", false).text("Pay Securely");
    }

    function resetStripePaymentState(reasonMessage) {
      stripeState.paid = false;
      stripeState.intentId = "";
      stripeState.clientSecret = "";
      stripeState.lastAmountMinor = 0;
      $("#stripeIntentId").val("");
      setStripePaymentStatus("Payment Pending", "secondary");

      if ((reasonMessage || "").toString().trim() !== "") {
        setStripeFeedback(reasonMessage, "warning");
      }

      refreshStripeActionButton();
    }

    function invalidateStripePaymentIfNeeded(reasonMessage) {
      if (!stripeState.paid) {
        return;
      }

      resetStripePaymentState(
        reasonMessage ||
          "Checkout details changed. Please revalidate Stripe payment.",
      );
    }

    function ensureStripeCardElementMounted() {
      if (!stripeAvailable) {
        return false;
      }

      if (!stripeState.stripe) {
        stripeState.stripe = window.Stripe(stripePublishableKey);
      }

      if (!stripeState.elements) {
        stripeState.elements = stripeState.stripe.elements({
          appearance: {
            theme: "night",
            variables: {
              colorPrimary: "#ffcc00",
              colorBackground: "#121212",
              colorText: "#f8fafc",
              colorDanger: "#f87171",
              fontFamily: "Inter, sans-serif",
              borderRadius: "8px",
            },
          },
        });
      }

      if (!stripeState.cardElement) {
        stripeState.cardElement = stripeState.elements.create("card", {
          hidePostalCode: true,
        });

        stripeState.cardElement.on("change", function (event) {
          if (event && event.error && event.error.message) {
            setStripeFeedback(event.error.message, "danger");
          } else if (!stripeState.processing) {
            setStripeFeedback("", "");
          }
        });

        stripeState.cardElement.mount("#stripeCardElement");
      }

      return true;
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

    function updateStripeVisibility() {
      const usingStripe = currentPaymentMethod() === "stripe";
      const section = $("#stripeSection");
      if (!section.length) {
        return;
      }

      section.toggleClass("d-none", !usingStripe);
      if (!usingStripe) {
        setStripeFeedback("", "");
        refreshStripeActionButton();
        return;
      }

      if (!stripeAvailable) {
        setStripeFeedback(
          "Stripe checkout is currently unavailable. Please use Cash on Delivery.",
          "danger",
        );
        setStripePaymentStatus("Stripe Unavailable", "danger");
        refreshStripeActionButton();
        return;
      }

      if (!ensureStripeCardElementMounted()) {
        setStripeFeedback(
          "Unable to initialize Stripe card field. Please refresh and try again.",
          "danger",
        );
        setStripePaymentStatus("Stripe Unavailable", "danger");
        refreshStripeActionButton();
        return;
      }

      if (stripeState.paid && stripeState.intentId) {
        setStripePaymentStatus("Payment Completed", "success");
      } else {
        setStripePaymentStatus("Payment Pending", "secondary");
      }

      refreshStripeActionButton();
    }

    function updatePaymentMethodPreview() {
      const methodKey = currentPaymentMethod();
      const methodMap = {
        cod: {
          icon: "bi-cash-coin",
          title: "Cash on Delivery",
          desc: "Pay when your order reaches your doorstep.",
          recommended: false,
        },
        stripe: {
          icon: "bi-credit-card-2-front",
          title: "Stripe Card Payment",
          desc: "Pay securely with your card before order confirmation.",
          recommended: true,
        },
      };

      const selected = methodMap[methodKey] || methodMap.cod;
      $("#paymentMethodIcon").attr("class", `bi ${selected.icon}`);
      $("#paymentMethodTitle").text(selected.title);
      $("#paymentMethodDesc").text(selected.desc);
      $("#paymentMethodBadge").toggleClass(
        "d-none",
        !Boolean(selected.recommended),
      );

      updateCodOtpVisibility();
      updateStripeVisibility();
    }

    function setPaymentMethod(methodKey, labelText) {
      const requested = (methodKey || "cod").toString().toLowerCase();
      const normalized =
        requested === "stripe" && stripeAvailable ? "stripe" : "cod";

      if (normalized !== "stripe") {
        resetStripePaymentState("");
      }

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

    function collectStripeBillingDetails() {
      const name = ($("#customerName").val() || "").toString().trim();
      const email = ($("#customerEmail").val() || "").toString().trim();
      const phone = ($("#customerPhone").val() || "").toString().trim();
      const address = ($("#customerAddress").val() || "").toString().trim();

      if (name.length < 3) {
        return {
          ok: false,
          message: "Enter a valid full name before card payment.",
        };
      }

      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        return {
          ok: false,
          message: "Enter a valid email before card payment.",
        };
      }

      if (!/^\d{11,15}$/.test(phone.replace(/\s+/g, ""))) {
        return {
          ok: false,
          message: "Enter a valid phone number before card payment.",
        };
      }

      if (address.length < 10) {
        return {
          ok: false,
          message: "Enter a complete address before card payment.",
        };
      }

      return {
        ok: true,
        payload: {
          name,
          email,
          phone: phone.replace(/\s+/g, ""),
          address,
        },
      };
    }

    async function createStripeIntentPayload(billingDetails) {
      const payload = new URLSearchParams();
      payload.set(
        "csrf_token",
        ($('input[name="csrf_token"]').val() || "").toString(),
      );
      payload.set("action", "create_stripe_intent");
      payload.set("request_id", buildRequestId("checkout_stripe_intent"));
      payload.set("payment_method", "stripe");
      payload.set("customer_name", billingDetails.name);
      payload.set("customer_email", billingDetails.email);
      payload.set(
        "coupon_code",
        ($("#checkoutCouponCode").val() || "").toString().trim(),
      );

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
          result?.message || "Unable to initialize Stripe payment.",
        );
      }

      return result;
    }

    async function handleStripePayment() {
      if (!isLoggedIn) {
        window.location.href = "login.php?redirect=cart.php";
        return;
      }

      if (currentPaymentMethod() !== "stripe") {
        setStripeFeedback("Select Stripe payment method first.", "warning");
        return;
      }

      if (stripeState.paid && stripeState.intentId) {
        setStripeFeedback(
          "Stripe payment is already completed for this checkout state.",
          "success",
        );
        return;
      }

      if (!stripeAvailable) {
        setStripeFeedback(
          "Stripe checkout is unavailable. Please use Cash on Delivery.",
          "danger",
        );
        return;
      }

      if (!ensureStripeCardElementMounted()) {
        setStripeFeedback(
          "Unable to initialize Stripe card field. Refresh and try again.",
          "danger",
        );
        return;
      }

      const billing = collectStripeBillingDetails();
      if (!billing.ok) {
        setStripeFeedback(billing.message, "warning");
        return;
      }

      const totalMinor = currentCheckoutTotalMinor();
      if (totalMinor <= 0) {
        setStripeFeedback(
          "Your cart total is invalid for card payment.",
          "danger",
        );
        return;
      }

      if (stripeState.processing) {
        return;
      }

      stripeState.processing = true;
      refreshStripeActionButton();
      setStripePaymentStatus("Processing", "warning");
      setStripeFeedback("Preparing secure card payment session...", "");

      try {
        const intentPayload = await createStripeIntentPayload(billing.payload);
        const clientSecret = (intentPayload.client_secret || "").toString();
        const intentId = (intentPayload.intent_id || "").toString();

        if (!clientSecret || !intentId) {
          throw new Error("Invalid payment session response from Stripe.");
        }

        stripeState.intentId = intentId;
        stripeState.clientSecret = clientSecret;
        stripeState.lastAmountMinor =
          Number(intentPayload.amount_minor) || totalMinor;

        const confirmation = await stripeState.stripe.confirmCardPayment(
          stripeState.clientSecret,
          {
            payment_method: {
              card: stripeState.cardElement,
              billing_details: {
                name: billing.payload.name,
                email: billing.payload.email,
                phone: billing.payload.phone,
                address: {
                  line1: billing.payload.address,
                },
              },
            },
          },
        );

        if (confirmation?.error?.message) {
          throw new Error(confirmation.error.message);
        }

        const paymentIntent = confirmation?.paymentIntent || null;
        if (!paymentIntent || paymentIntent.status !== "succeeded") {
          throw new Error(
            "Card payment is not completed yet. Please retry and complete authentication.",
          );
        }

        stripeState.paid = true;
        stripeState.intentId = (paymentIntent.id || stripeState.intentId)
          .toString()
          .trim();
        $("#stripeIntentId").val(stripeState.intentId);
        setStripePaymentStatus("Payment Completed", "success");
        setStripeFeedback(
          "Stripe payment completed successfully. You can now place your order.",
          "success",
        );
      } catch (error) {
        stripeState.paid = false;
        stripeState.intentId = "";
        stripeState.clientSecret = "";
        stripeState.lastAmountMinor = 0;
        $("#stripeIntentId").val("");
        setStripePaymentStatus("Payment Pending", "secondary");
        setStripeFeedback(
          error && error.message
            ? error.message
            : "Unable to complete secure Stripe payment.",
          "danger",
        );
      } finally {
        stripeState.processing = false;
        refreshStripeActionButton();
      }
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

    $("#stripePayBtn").on("click", function () {
      handleStripePayment();
    });

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
        payload.set("payment_method", currentPaymentMethod());
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
      updateStripeVisibility();
      setStripeFeedback("", "");
      if (!stripeAvailable && stripeEnabled) {
        setStripePaymentStatus("Stripe Unavailable", "danger");
      }
    });

    const totalNode = document.getElementById("cart-total");
    let lastObservedTotalMinor = currentCheckoutTotalMinor();
    if (totalNode && window.MutationObserver) {
      const totalObserver = new MutationObserver(function () {
        const currentMinor = currentCheckoutTotalMinor();
        if (currentMinor !== lastObservedTotalMinor) {
          lastObservedTotalMinor = currentMinor;
          invalidateStripePaymentIfNeeded(
            "Order total changed. Please revalidate Stripe payment.",
          );
        }

        updateCodOtpVisibility();
        updateStripeVisibility();
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

      if (currentPaymentMethod() === "stripe") {
        if (!stripeAvailable) {
          event.preventDefault();
          setStripeFeedback(
            "Stripe checkout is unavailable. Please use Cash on Delivery.",
            "danger",
          );
          return;
        }

        const stripeIntentId = ($("#stripeIntentId").val() || "")
          .toString()
          .trim();
        if (!stripeState.paid || !/^pi_[A-Za-z0-9]+$/.test(stripeIntentId)) {
          event.preventDefault();
          setStripeFeedback(
            "Complete Stripe card payment before placing your order.",
            "warning",
          );
          setStripePaymentStatus("Payment Pending", "secondary");
          return;
        }
      }

      submitBtn.prop("disabled", true).text("Placing Order...");
    });

    $("#customerName, #customerEmail, #customerPhone, #customerAddress").on(
      "input change",
      function () {
        invalidateStripePaymentIfNeeded(
          "Checkout details changed. Please revalidate Stripe payment.",
        );
      },
    );

    $("#applyCouponBtn, #removeCouponBtn").on("click", function () {
      window.setTimeout(function () {
        invalidateStripePaymentIfNeeded(
          "Coupon or total changed. Please revalidate Stripe payment.",
        );
      }, 240);
    });

    if (!stripeAvailable && stripeEnabled) {
      setStripeFeedback(
        "Stripe script could not be loaded. Please refresh or use Cash on Delivery.",
        "danger",
      );
    }

    updatePaymentMethodPreview();
    refreshStripeActionButton();
  });
})();
