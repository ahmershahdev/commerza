function initEmailCenter() {
  if (!$("#emailSection").length) return;
  emailDirectory = buildEmailDirectory();
  emailTemplates = getEmailTemplates();
  renderTemplateSelect();
  renderEmailRecipients();
  updateEmailPreview();

  $(document).on("click", "#emailSourceMenu .dropdown-item", function (event) {
    event.preventDefault();
    const source = $(this).data("source") || "all";
    $("#emailSourceFilter").val(source);
    $("#emailSourceBtn").text($(this).text().trim());
    renderEmailRecipients();
  });
  $("#emailSearchInput").on("input", renderEmailRecipients);

  $(document).on("change", ".email-recipient-check", function () {
    const email = $(this).data("email");
    if (!email) return;
    if (this.checked) {
      emailSelected.add(email);
    } else {
      emailSelected.delete(email);
    }
    updateSelectedCount();
  });

  $("#emailSelectAllBtn").on("click", function () {
    emailFiltered.forEach((entry) => emailSelected.add(entry.email));
    renderEmailRecipients();
  });

  $("#emailClearBtn").on("click", function () {
    emailSelected.clear();
    renderEmailRecipients();
  });

  $("#emailCopyBtn").on("click", function () {
    const list = Array.from(emailSelected);
    if (!list.length) {
      showNotification("Select recipients to copy", "warning");
      return;
    }
    const text = list.join(", ");
    navigator.clipboard
      ?.writeText(text)
      .then(() => {
        showNotification("Emails copied to clipboard", "success");
      })
      .catch(() => {
        showNotification("Unable to copy emails", "danger");
      });
  });

  $("#emailAddRecipientBtn").on("click", function () {
    const input = $("#emailAddRecipientInput");
    const value = input.val();
    if (!addManualRecipient(value)) {
      showNotification("Enter a valid email address", "danger");
      return;
    }
    input.val("");
    renderEmailRecipients();
    showNotification("Recipient added", "success");
  });

  $(document).on("click", ".email-remove-recipient", function () {
    const email = $(this).data("email");
    if (!email) return;
    removeEmailRecipient(email);
    renderEmailRecipients();
    showNotification("Recipient removed", "success");
  });

  $(document).on(
    "click",
    "#emailTemplateMenu .dropdown-item",
    function (event) {
      event.preventDefault();
      const templateId = $(this).data("template-id");
      if (!templateId) {
        $("#emailTemplateId").val("");
        $("#emailTemplateBtn").text("Custom");
        $("#emailTemplateName").val("");
        updateEmailPreview();
        return;
      }
      applyTemplateToComposer(templateId);
    },
  );

  $("#emailSubjectInput, #emailBodyInput, #emailAttachmentInput").on(
    "input change",
    updateEmailPreview,
  );

  $("#emailSaveTemplateBtn").on("click", saveTemplateFromComposer);
  $("#emailNewTemplateBtn").on("click", resetComposerTemplate);
  $("#emailSendBtn").on("click", sendEmailFromComposer);
}

