$(function () {
  const form = $("#adminVerify2faForm");
  const verifyBtn = $("#adminVerify2faSubmitBtn");
  const resendBtn = $("#adminVerify2faResendBtn");

  if (!form.length || !verifyBtn.length || !resendBtn.length) {
    return;
  }

  let submitted = false;

  form.on("submit", function (event) {
    if (submitted) {
      event.preventDefault();
      return false;
    }

    submitted = true;

    const submitter = event.originalEvent?.submitter || null;
    const action = (submitter?.value || "verify").toString().toLowerCase();

    verifyBtn.prop("disabled", true);
    resendBtn.prop("disabled", true);

    if (action === "resend") {
      resendBtn.text("Sending...");
    } else {
      verifyBtn.text("Verifying...");
    }

    return true;
  });
});
