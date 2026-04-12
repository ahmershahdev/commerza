function initAccountPage() {
  const logoutBtn = $("#logoutBtn");
  const logoutForm = $("#logoutForm");

  logoutBtn.off("click").on("click", function (event) {
    if (!logoutForm.length) {
      event.preventDefault();
      window.location.href = "login.php";
      return;
    }

    event.preventDefault();

    const nativeForm = logoutForm.get(0);
    if (nativeForm && typeof nativeForm.requestSubmit === "function") {
      nativeForm.requestSubmit();
      return;
    }

    logoutForm.trigger("submit");
  });

  $(".toggle-password").on("click", function () {
    const target = $(this).data("target");
    if (!target) return;
    const input = $(target);
    input.attr("type", input.attr("type") === "password" ? "text" : "password");
    $(this).toggleClass("bi-eye bi-eye-slash");
  });
}
