$(function () {
  const authUtils = window.CommerzaAdminAuthUtils;
  if (!authUtils) {
    return;
  }

  if (!$("#resetSuccess").hasClass("d-none")) {
    setTimeout(function () {
      window.location.href = "admin-login.php";
    }, 1200);
  }

  authUtils.bindSingleSubmit(
    "#forgotEmailForm",
    "#forgotEmailSubmitBtn",
    "Updating...",
  );
});
