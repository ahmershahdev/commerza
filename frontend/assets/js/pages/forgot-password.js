$(function () {
  $("#serverAlert, #successAlert").each(function () {
    const element = $(this);
    setTimeout(function () {
      element.fadeOut(400);
    }, 3500);
  });

  let submitted = false;
  $("#forgotPasswordForm").on("submit", function () {
    if (submitted) {
      return false;
    }

    submitted = true;
    $("#forgotPasswordSubmitBtn").prop("disabled", true).text("Sending...");
    return true;
  });
});
