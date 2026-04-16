$(function () {
  const authUtils = window.CommerzaAdminAuthUtils;
  if (!authUtils) {
    return;
  }

  authUtils.bindIconPasswordToggle(".password-toggle");
  authUtils.bindSingleSubmit(
    "#sendResetForm",
    "#sendResetCodeBtn",
    "Sending...",
  );
  authUtils.bindSingleSubmit(
    "#resetPasswordForm",
    "#resetPasswordBtn",
    "Updating...",
  );

  if (window.CommerzaAdminResetComplete) {
    setTimeout(function () {
      window.location.href = "admin-login.php";
    }, 1400);
  }
});
