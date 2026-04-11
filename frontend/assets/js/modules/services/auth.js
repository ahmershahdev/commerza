(function () {
  if (window.CommerzaAuth) return;

  const serverAuthError = {
    ok: false,
    error: "This action is handled by secure server forms.",
  };

  function getCurrentUser() {
    return null;
  }

  function logoutUser() {
    window.location.href = "account.php";
  }

  window.CommerzaAuth = {
    registerUser: function () {
      return serverAuthError;
    },
    loginUser: function () {
      return serverAuthError;
    },
    logoutUser,
    getCurrentUser,
    updateUser: function () {
      return serverAuthError;
    },
    updatePassword: function () {
      return serverAuthError;
    },
    requestPasswordReset: function () {
      return serverAuthError;
    },
  };
})();
