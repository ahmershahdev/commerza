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

  const sendEmailInput = document.getElementById("admin-email-send");
  const resetEmailInput = document.getElementById("admin-email-reset");
  const syncEmailValue = (source, target) => {
    if (!source || !target) {
      return;
    }

    const nextValue = (source.value || "").toString().trim();
    if (nextValue !== "") {
      target.value = nextValue;
    }
  };

  if (sendEmailInput && resetEmailInput) {
    sendEmailInput.addEventListener("input", function () {
      syncEmailValue(sendEmailInput, resetEmailInput);
    });

    resetEmailInput.addEventListener("input", function () {
      syncEmailValue(resetEmailInput, sendEmailInput);
    });
  }

  if (window.CommerzaAdminResetComplete) {
    setTimeout(function () {
      window.location.href = "admin-login.php";
    }, 1400);
  }
});
