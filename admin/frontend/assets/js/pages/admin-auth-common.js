(function () {
  if (window.CommerzaAdminAuthUtils) {
    return;
  }

  function bindPasswordToggle(buttonSelector, inputSelector) {
    $(buttonSelector).on("click", function () {
      const input = $(inputSelector);
      const isPassword = input.attr("type") === "password";
      input.attr("type", isPassword ? "text" : "password");
      $(this).find("i").toggleClass("bi-eye bi-eye-slash");
      $(this).attr("aria-pressed", String(isPassword));
      $(this).attr(
        "aria-label",
        isPassword ? "Hide password" : "Show password",
      );
    });
  }

  function bindIconPasswordToggle(iconSelector) {
    $(iconSelector).on("click", function () {
      const target = $(this).data("target");
      const input = $(target);
      input.attr(
        "type",
        input.attr("type") === "password" ? "text" : "password",
      );
      $(this).toggleClass("bi-eye bi-eye-slash");
    });
  }

  function bindSingleSubmit(formSelector, submitSelector, busyLabel) {
    let submitted = false;

    $(formSelector).on("submit", function () {
      if (submitted) {
        return false;
      }

      submitted = true;
      if (submitSelector) {
        $(submitSelector)
          .prop("disabled", true)
          .text(busyLabel || "Submitting...");
      }
      return true;
    });
  }

  window.CommerzaAdminAuthUtils = {
    bindPasswordToggle,
    bindIconPasswordToggle,
    bindSingleSubmit,
  };
})();
