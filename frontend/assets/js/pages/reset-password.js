$(function () {
  $("#serverAlert").each(function () {
    const element = $(this);
    setTimeout(function () {
      element.fadeOut(400);
    }, 3500);
  });

  $(".toggle-password").on("click", function () {
    const target = $(this).data("target");
    if (!target) {
      return;
    }

    const input = $(target);
    const isPassword = input.attr("type") === "password";
    input.attr("type", isPassword ? "text" : "password");
    $(this).toggleClass("bi-eye bi-eye-slash");
  });

  const resetCodeInput = $("#reset-code");
  resetCodeInput.on("input", function () {
    const raw = String($(this).val() || "");
    const digitsOnly = raw.replace(/\D+/g, "").slice(0, 6);
    if (raw !== digitsOnly) {
      $(this).val(digitsOnly);
    }
  });

  let submitted = false;
  $("#resetForm").on("submit", function () {
    if (submitted) {
      return false;
    }

    submitted = true;
    $("#resetSubmitBtn").prop("disabled", true).text("Updating...");
    return true;
  });
});
