function isLightThemeActive() {
  const root = document.documentElement;
  const rootTheme = (
    root.getAttribute("data-commerza-theme") ||
    root.getAttribute("data-bs-theme") ||
    ""
  )
    .toString()
    .trim()
    .toLowerCase();

  if (rootTheme === "light") {
    return true;
  }

  return !!document.body?.classList.contains("light-theme");
}

function escapeNotifText(value) {
  return (value ?? "").toString().replace(/[&<>"']/g, (char) => {
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    };

    return map[char] || char;
  });
}

function notificationPalette(type = "success") {
  const isSuccess = type === "success";
  if (isLightThemeActive()) {
    return {
      background: isSuccess ? "#edf9f1" : "#edf4ff",
      border: isSuccess ? "#22a06b" : "#2552d6",
      accent: isSuccess ? "#15803d" : "#1d4ed8",
      text: isSuccess ? "#14532d" : "#1e3a8a",
      shadow: "0 12px 26px rgba(37, 82, 214, 0.18)",
      closeClass: "btn-close",
    };
  }

  return {
    background: isSuccess ? "#1a472a" : "#332a1a",
    border: isSuccess ? "#28a745" : "#ff6600",
    accent: isSuccess ? "#28a745" : "#ff6600",
    text: "#ffffff",
    shadow: "0 4px 15px rgba(255, 102, 0, 0.3)",
    closeClass: "btn-close btn-close-white",
  };
}

function showAccountMessage(message, type = "success") {
  const palette = notificationPalette(type);
  const icon = type === "success" ? "check-circle" : "exclamation-circle";
  const safeMessage = escapeNotifText(message);
  $("body").append(
    `<div class="alert alert-${type} alert-dismissible fade show" role="alert" style="position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 320px; background-color: ${palette.background}; border: 2px solid ${palette.border}; border-radius: 8px; padding: 20px; box-shadow: ${palette.shadow};"><div style="color: ${palette.text}; text-align: left;"><i class="bi bi-${icon}" style="font-size: 1.5rem; color: ${palette.accent}; display: inline-block; margin-right: 8px;"></i><span style="font-weight: 600;">${safeMessage}</span></div><button type="button" class="${palette.closeClass}" data-bs-dismiss="alert"></button></div>`,
  );
  setTimeout(
    () =>
      $(".alert").fadeOut(300, function () {
        $(this).remove();
      }),
    3500,
  );
}

function showNotif(msg, type = "success") {
  const palette = notificationPalette(type);
  const icon = type === "success" ? "check-circle" : "exclamation-circle";
  const safeMessage = escapeNotifText(msg);
  $("body").append(
    `<div class="alert alert-${type} alert-dismissible fade show toast-alert" role="alert" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999; min-width: 380px; background-color: ${palette.background}; border: 2px solid ${palette.border}; border-radius: 8px; padding: 30px; box-shadow: ${palette.shadow};"><div style="color: ${palette.text}; text-align: center;"><i class="bi bi-${icon}" style="font-size: 2rem; color: ${palette.accent}; display: block; margin-bottom: 10px;"></i><h5 style="margin: 10px 0; color: ${palette.accent};">${safeMessage}</h5></div><button type="button" class="${palette.closeClass}" data-bs-dismiss="alert"></button></div>`,
  );
  setTimeout(
    () =>
      $(".alert").fadeOut(300, function () {
        $(this).remove();
      }),
    3500,
  );
}
