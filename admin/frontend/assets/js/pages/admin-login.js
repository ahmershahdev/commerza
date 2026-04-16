$(function () {
  const authUtils = window.CommerzaAdminAuthUtils;
  if (!authUtils) {
    return;
  }

  authUtils.bindPasswordToggle("#togglePassword", "#admin-password");
  authUtils.bindSingleSubmit(
    "#adminLoginForm",
    "#adminLoginSubmitBtn",
    "Signing In...",
  );
});
