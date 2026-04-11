$(function () {
  $("#serverAlert, #successAlert").each(function () {
    const element = $(this);
    setTimeout(function () {
      element.fadeOut(400);
    }, 3500);
  });

  let submitted = false;

  const inquiryInput = $("#contact-inquiry-type");
  const inquiryLabel = $("#contactInquiryLabel");
  $(".contact-inquiry-option").on("click", function () {
    const nextValue = ($(this).data("value") || "").toString().trim();
    const nextLabel = ($(this).text() || "").toString().trim();

    if (!nextValue || !nextLabel) {
      return;
    }

    inquiryInput.val(nextValue);
    inquiryLabel.text(nextLabel);
    $(".contact-inquiry-option").removeClass("active");
    $(this).addClass("active");
  });

  $("#contactForm").on("submit", function () {
    if (submitted) {
      return false;
    }

    submitted = true;
    $("#contactSubmitBtn").prop("disabled", true).text("Sending...");
    return true;
  });
});
