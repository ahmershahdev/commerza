$(function () {
  let submitted = false;
  const csrf = $("input[name='csrf_token']").val();
  const fieldState = {
    email: false,
    phone: false,
    username: false,
  };

  if ($("#serverAlert").length) {
    setTimeout(() => $("#serverAlert").fadeOut(400), 3500);
  }

  if ($("#successAlert").length) {
    setTimeout(() => $("#successAlert").fadeOut(400), 3500);
  }

  function showAlert(msg) {
    $("#clientAlert").text(msg).fadeIn(200);
    setTimeout(() => $("#clientAlert").fadeOut(400), 2800);
  }

  function setFieldStatus(input, taken, message) {
    const wrapper = input.closest(".mb-3");
    wrapper.find(".live-feedback").remove();

    const color = taken ? "#ff4444" : "#22c55e";
    const icon = taken ? "bi-x-circle-fill" : "bi-check-circle-fill";
    const text = taken ? message : "Available";

    input.css("border-color", color);
    wrapper.append(
      `<div class="live-feedback" style="font-family:'JetBrains Mono',monospace;font-size:11px;margin-top:5px;color:${color};"><i class="bi ${icon}"></i> ${text}</div>`,
    );
  }

  function clearFieldStatus(input) {
    input.closest(".mb-3").find(".live-feedback").remove();
    input.css("border-color", "");
  }

  function isValidLiveEmail(value) {
    const normalized = (value || "").toString().trim().toLowerCase();
    return (
      /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalized) && normalized.length <= 150
    );
  }

  function isValidLivePhone(value) {
    const normalized = (value || "").toString().trim();
    return /^\d{11,15}$/.test(normalized);
  }

  function isValidLiveUsername(value) {
    const normalized = (value || "").toString().trim().toLowerCase();
    return /^[a-z][a-z0-9_]{2,23}$/.test(normalized);
  }

  function checkField(input, field, value, message) {
    $.post("backend/check_exists.php", {
      csrf_token: csrf,
      field,
      value,
    })
      .done(function (res) {
        if (res?.blocked) {
          fieldState[field] = true;
          setFieldStatus(
            input,
            true,
            (
              res?.message ||
              "This value is blocked by admin and cannot be used."
            ).toString(),
          );
          return;
        }

        fieldState[field] = !!res?.exists;
        const takenMessage =
          !!res?.exists &&
          typeof res?.message === "string" &&
          res.message.trim() !== ""
            ? res.message
            : message;
        setFieldStatus(input, !!res?.exists, takenMessage);
      })
      .fail(function () {
        clearFieldStatus(input);
        fieldState[field] = false;
      });
  }

  let emailTimer;
  let phoneTimer;
  let usernameTimer;

  $("#signup-username").on("input", function () {
    const input = $(this);
    const normalized = input
      .val()
      .toString()
      .toLowerCase()
      .replace(/\s+/g, "_")
      .replace(/[^a-z0-9_]/g, "")
      .replace(/_+/g, "_")
      .replace(/^_+|_+$/g, "");

    input.val(normalized);
    clearTimeout(usernameTimer);
    clearFieldStatus(input);
    fieldState.username = false;

    if (normalized.length < 3) {
      return;
    }

    if (!isValidLiveUsername(normalized)) {
      setFieldStatus(input, true, "Use 3-24 chars, start with a letter.");
      fieldState.username = false;
      return;
    }

    usernameTimer = setTimeout(() => {
      checkField(input, "username", normalized, "Username already taken");
    }, 450);
  });

  $("#signup-email").on("input", function () {
    const val = $(this).val().trim().toLowerCase();
    clearTimeout(emailTimer);
    const input = $(this);
    clearFieldStatus(input);
    fieldState.email = false;
    if (!val) {
      return;
    }

    if (!isValidLiveEmail(val)) {
      setFieldStatus(input, true, "Enter a valid email first.");
      fieldState.email = false;
      return;
    }

    emailTimer = setTimeout(() => {
      checkField(input, "email", val, "Email already registered");
    }, 500);
  });

  $("#signup-phone").on("input", function () {
    const val = $(this).val().trim();
    clearTimeout(phoneTimer);
    const input = $(this);
    clearFieldStatus(input);
    fieldState.phone = false;
    if (!val) {
      return;
    }

    if (!isValidLivePhone(val)) {
      setFieldStatus(input, true, "Use 11 to 15 digits.");
      fieldState.phone = false;
      return;
    }

    phoneTimer = setTimeout(() => {
      checkField(input, "phone", val, "Phone already registered");
    }, 500);
  });

  $(".toggle-password").on("click", function () {
    const id = $(this).data("target");
    const input = $("#" + id);
    input.attr("type", input.attr("type") === "password" ? "text" : "password");
    $(this).toggleClass("bi-eye bi-eye-slash");
  });

  $("#signup-password").on("input", function () {
    const pw = $(this).val();
    let strength = 0;
    if (pw.length >= 10) strength++;
    if (/[A-Z]/.test(pw)) strength++;
    if (/[a-z]/.test(pw)) strength++;
    if (/[0-9]/.test(pw)) strength++;
    if (/[@$!%*?&]/.test(pw)) strength++;

    $("#strengthBar").toggle(pw.length > 0);
    $("#passwordStrength")
      .css("width", (strength / 5) * 100 + "%")
      .removeClass()
      .addClass(
        "progress-bar " +
          (strength <= 1
            ? "bg-warning"
            : strength === 2
              ? "bg-success"
              : strength === 3
                ? "bg-info"
                : strength === 4
                  ? "bg-orange"
                  : "bg-danger"),
      );
  });

  $("#signupForm").on("submit", function () {
    if (submitted) {
      return false;
    }

    const takenMessages = [];
    if (fieldState.email) takenMessages.push("Email already registered.");
    if (fieldState.phone) takenMessages.push("Phone already registered.");
    if (fieldState.username) takenMessages.push("Username already taken.");

    if (takenMessages.length > 0) {
      showAlert(takenMessages.join(" "));
      return false;
    }

    const pw = $("#signup-password").val();
    const cpw = $("#signup-confirm-password").val();

    if (pw !== cpw) {
      showAlert("Passwords do not match.");
      return false;
    }

    submitted = true;
    $("#submitBtn").prop("disabled", true).text("Creating Account...");
    return true;
  });

  $("#verifySignupForm").on("submit", function () {
    $("#verifyBtn").prop("disabled", true).text("Verifying...");
  });
});
