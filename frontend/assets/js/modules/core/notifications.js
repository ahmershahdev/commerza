function showAccountMessage(message, type = "success") {
  const bg = type === "success" ? "#1a472a" : "#332a1a";
  const color = type === "success" ? "#28a745" : "#ff6600";
  const icon = type === "success" ? "check-circle" : "exclamation-circle";
  $("body").append(
    `<div class="alert alert-${type} alert-dismissible fade show" role="alert" style="position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 320px; background-color: ${bg}; border: 2px solid ${color}; border-radius: 8px; padding: 20px; box-shadow: 0 4px 15px rgba(255, 102, 0, 0.3);"><div style="color: #fff; text-align: left;"><i class="bi bi-${icon}" style="font-size: 1.5rem; color: ${color}; display: inline-block; margin-right: 8px;"></i><span style="font-weight: 600;">${message}</span></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button></div>`,
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
  const bg = type === "success" ? "#1a472a" : "#332a1a";
  const color = type === "success" ? "#28a745" : "#ff6600";
  const icon = type === "success" ? "check-circle" : "exclamation-circle";
  $("body").append(
    `<div class="alert alert-${type} alert-dismissible fade show toast-alert" role="alert" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999; min-width: 380px; background-color: ${bg}; border: 2px solid ${color}; border-radius: 8px; padding: 30px; box-shadow: 0 4px 15px rgba(255, 102, 0, 0.3);"><div style="color: #fff; text-align: center;"><i class="bi bi-${icon}" style="font-size: 2rem; color: ${color}; display: block; margin-bottom: 10px;"></i><h5 style="margin: 10px 0; color: ${color};">${msg}</h5></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button></div>`,
  );
  setTimeout(
    () =>
      $(".alert").fadeOut(300, function () {
        $(this).remove();
      }),
    3500,
  );
}