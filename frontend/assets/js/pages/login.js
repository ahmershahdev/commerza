$(function () {
  let submitted = false;

  $("#togglePassword").on("click", function () {
    const input = $("#user-login-password");
    input.attr("type", input.attr("type") === "password" ? "text" : "password");
    $(this).toggleClass("bi-eye bi-eye-slash");
  });

  $("#serverAlert, #successAlert").each(function () {
    const element = $(this);
    setTimeout(function () {
      element.fadeOut(400);
    }, 3500);
  });

  $("#loginForm").on("submit", function () {
    if (submitted) {
      return false;
    }

    submitted = true;
    $("#loginSubmitBtn").prop("disabled", true).text("Signing In...");
    return true;
  });
});
