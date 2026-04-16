function formatShortDate(value) {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "-";
  return date.toISOString().split("T")[0];
}

function isFakeReviewEmail(value) {
  const normalized = normalizeEmailValue(value);
  return normalized.endsWith("@fake-review.local");
}

function updateEmailPreview() {
  const subject = ($("#emailSubjectInput").val() || "").trim();
  const body = ($("#emailBodyInput").val() || "").trim();
  const preview = $("#emailPreview");
  if (!preview.length) return;
  const attachmentInput = document.getElementById("emailAttachmentInput");
  const files = attachmentInput?.files
    ? Array.from(attachmentInput.files).map((file) => file.name)
    : [];
  const attachmentLine = files.length
    ? `Attachments: ${files.join(", ")}`
    : "Attachments: None";
  const title = subject ? `Subject: ${subject}` : "Subject: (No subject)";
  preview.text(`${title}\n${attachmentLine}\n\n${body}`.trim());
}

function getNewsletterSubscribers() {
  const rawList = readJsonStorage(NEWSLETTER_SUBSCRIBERS_KEY, []);
  const list = Array.isArray(rawList) ? rawList : [];
  const legacyEmail = normalizeEmailValue(
    sessionStorage.getItem(NEWSLETTER_EMAIL_KEY),
  );

  if (
    legacyEmail &&
    !list.some(
      (item) => normalizeEmailValue(item.email || item) === legacyEmail,
    )
  ) {
    list.push({
      email: legacyEmail,
      sources: ["modal"],
      subscribedAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    });
  }

  return list
    .map((item) => {
      if (typeof item === "string") {
        return {
          email: normalizeEmailValue(item),
          sources: ["modal"],
          subscribedAt: new Date().toISOString(),
          updatedAt: new Date().toISOString(),
        };
      }
      return {
        email: normalizeEmailValue(item.email),
        sources: Array.isArray(item.sources)
          ? item.sources
          : [item.source || "modal"],
        subscribedAt: item.subscribedAt,
        updatedAt: item.updatedAt || item.subscribedAt,
      };
    })
    .filter((entry) => entry.email);
}

function getManualRecipients() {
  const raw = readJsonStorage(EMAIL_MANUAL_RECIPIENTS_KEY, []);
  if (!Array.isArray(raw)) return [];
  return raw
    .map((item) => {
      if (typeof item === "string") {
        return {
          email: normalizeEmailValue(item),
          addedAt: new Date().toISOString(),
        };
      }
      return {
        email: normalizeEmailValue(item.email),
        addedAt: item.addedAt || new Date().toISOString(),
      };
    })
    .filter((item) => item.email);
}

function saveManualRecipients(list) {
  sessionStorage.setItem(EMAIL_MANUAL_RECIPIENTS_KEY, JSON.stringify(list));
}

function buildEmailDirectory() {
  const directory = new Map();
  const suppressed = getSuppressedEmails();

  const addEntry = (email, source, meta = {}) => {
    const normalized = normalizeEmailValue(email);
    if (!normalized) return;
    if (isFakeReviewEmail(normalized)) return;
    if (suppressed.has(normalized)) return;
    const existing = directory.get(normalized) || {
      email: normalized,
      name: meta.name || "",
      sources: new Set(),
      firstSeen: meta.firstSeen || meta.lastSeen || new Date().toISOString(),
      lastSeen: meta.lastSeen || meta.firstSeen || new Date().toISOString(),
    };
    existing.sources.add(source);
    if (meta.name && !existing.name) {
      existing.name = meta.name;
    }
    if (
      meta.firstSeen &&
      (!existing.firstSeen ||
        new Date(meta.firstSeen) < new Date(existing.firstSeen))
    ) {
      existing.firstSeen = meta.firstSeen;
    }
    if (
      meta.lastSeen &&
      new Date(meta.lastSeen) > new Date(existing.lastSeen)
    ) {
      existing.lastSeen = meta.lastSeen;
    }
    directory.set(normalized, existing);
  };

  getNewsletterSubscribers().forEach((sub) => {
    const lastSeen = sub.updatedAt || sub.subscribedAt;
    addEntry(sub.email, "Newsletter", {
      lastSeen,
      firstSeen: sub.subscribedAt,
    });
  });

  const users = getAdminCustomersData();
  if (Array.isArray(users)) {
    users.forEach((user) => {
      addEntry(user.email, "Account", {
        name: user.name,
        lastSeen: user.registeredAt,
        firstSeen: user.registeredAt,
      });
    });
  }

  const orders = getAdminOrdersData();
  if (Array.isArray(orders)) {
    orders.forEach((order) => {
      addEntry(order.email, "Order", {
        name: order.customerName,
        lastSeen: order.orderDate,
        firstSeen: order.orderDate,
      });
    });
  }

  getManualRecipients().forEach((item) => {
    addEntry(item.email, "Manual", {
      lastSeen: item.addedAt,
      firstSeen: item.addedAt,
    });
  });

  return Array.from(directory.values())
    .map((entry) => ({
      ...entry,
      sources: Array.from(entry.sources),
    }))
    .sort((a, b) => new Date(b.lastSeen) - new Date(a.lastSeen));
}

function getEmailTemplates() {
  const stored = readJsonStorage(EMAIL_TEMPLATES_KEY, []);
  if (Array.isArray(stored) && stored.length) {
    return stored;
  }
  return DEFAULT_EMAIL_TEMPLATES.map((template) => ({ ...template }));
}

function saveEmailTemplates(list) {
  sessionStorage.setItem(EMAIL_TEMPLATES_KEY, JSON.stringify(list));
}

function renderTemplateSelect() {
  const menu = $("#emailTemplateMenu");
  if (!menu.length) return;
  menu.empty();
  menu.append(
    '<li><a class="dropdown-item admin-dropdown-item" href="#" data-template-id="">Custom</a></li>',
  );
  menu.append('<li><hr class="dropdown-divider border-secondary"></li>');
  emailTemplates.forEach((template) => {
    menu.append(
      `<li><a class="dropdown-item admin-dropdown-item" href="#" data-template-id="${template.id}">${template.name}</a></li>`,
    );
  });
}

function renderEmailRecipients() {
  const tbody = $("#emailRecipientsTable tbody");
  if (!tbody.length) return;
  const filter = ($("#emailSourceFilter").val() || "all").toLowerCase();
  const query = ($("#emailSearchInput").val() || "").trim().toLowerCase();

  emailFiltered = emailDirectory.filter((entry) => {
    const inNewsletter = entry.sources.includes("Newsletter");
    const inCustomers =
      entry.sources.includes("Order") || entry.sources.includes("Account");
    if (filter === "newsletter" && !inNewsletter) return false;
    if (filter === "customers" && !inCustomers) return false;
    if (query) {
      const name = (entry.name || "").toLowerCase();
      return entry.email.includes(query) || name.includes(query);
    }
    return true;
  });

  $("#emailRecipientCount").text(emailDirectory.length);

  tbody.empty();
  if (!emailFiltered.length) {
    tbody.append(
      '<tr><td colspan="5" class="text-center py-4 text-secondary">No recipients found</td></tr>',
    );
    updateSelectedCount();
    return;
  }

  emailFiltered.forEach((entry) => {
    const isChecked = emailSelected.has(entry.email);
    const badges = entry.sources
      .map((source) => {
        const badgeClass = EMAIL_SOURCE_BADGES[source] || "bg-secondary";
        return `<span class="badge ${badgeClass} me-1">${source}</span>`;
      })
      .join("");
    tbody.append(`
            <tr class="border-bottom border-secondary">
                <td class="ps-4 py-3">
                    <input type="checkbox" class="form-check-input email-recipient-check" data-email="${entry.email}" ${isChecked ? "checked" : ""}>
                </td>
                <td class="py-3 text-light fw-semibold">${entry.email}</td>
                <td class="py-3">${badges}</td>
                <td class="py-3 text-secondary small">${formatShortDate(entry.lastSeen)}</td>
                <td class="pe-4 py-3">
                    <button class="btn btn-sm btn-outline-danger email-remove-recipient" data-email="${entry.email}" title="Remove">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `);
  });

  updateSelectedCount();
}

function updateSelectedCount() {
  $("#emailSelectedCount").text(emailSelected.size);
}

function addManualRecipient(email) {
  const normalized = normalizeEmailValue(email);
  if (!normalized || !normalized.includes("@")) return false;
  const suppressed = getSuppressedEmails();
  if (suppressed.has(normalized)) {
    suppressed.delete(normalized);
    saveSuppressedEmails(suppressed);
  }
  const existing = getManualRecipients();
  if (!existing.some((item) => item.email === normalized)) {
    existing.push({ email: normalized, addedAt: new Date().toISOString() });
    saveManualRecipients(existing);
  }
  if (!emailDirectory.some((entry) => entry.email === normalized)) {
    emailDirectory.push({
      email: normalized,
      name: "",
      sources: ["Manual"],
      firstSeen: new Date().toISOString(),
      lastSeen: new Date().toISOString(),
    });
    emailDirectory = emailDirectory.sort(
      (a, b) => new Date(b.lastSeen) - new Date(a.lastSeen),
    );
  }
  emailSelected.add(normalized);
  return true;
}

function applyTemplateToComposer(templateId) {
  const template = emailTemplates.find(
    (item) => String(item.id) === String(templateId),
  );
  if (!template) return;
  $("#emailTemplateId").val(template.id);
  $("#emailTemplateBtn").text(template.name || "Custom");
  $("#emailTemplateName").val(template.name || "");
  $("#emailSubjectInput").val(template.subject || "");
  $("#emailBodyInput").val(template.body || "");
  updateEmailPreview();
}

function saveTemplateFromComposer() {
  const name = ($("#emailTemplateName").val() || "").trim();
  const subject = ($("#emailSubjectInput").val() || "").trim();
  const body = ($("#emailBodyInput").val() || "").trim();
  if (!name || (!subject && !body)) {
    showNotification("Add a template name and content before saving", "danger");
    return;
  }

  const selectedId = $("#emailTemplateId").val();
  if (selectedId) {
    const index = emailTemplates.findIndex(
      (item) => String(item.id) === String(selectedId),
    );
    if (index !== -1) {
      emailTemplates[index] = { ...emailTemplates[index], name, subject, body };
      saveEmailTemplates(emailTemplates);
      renderTemplateSelect();
      $("#emailTemplateId").val(selectedId);
      $("#emailTemplateBtn").text(name || "Custom");
      showNotification("Template updated!", "success");
      return;
    }
  }

  const nextId = Math.max(0, ...emailTemplates.map((item) => item.id || 0)) + 1;
  emailTemplates.push({ id: nextId, name, subject, body });
  saveEmailTemplates(emailTemplates);
  renderTemplateSelect();
  $("#emailTemplateId").val(String(nextId));
  $("#emailTemplateBtn").text(name || "Custom");
  showNotification("Template saved!", "success");
}

function resetComposerTemplate() {
  $("#emailTemplateId").val("");
  $("#emailTemplateBtn").text("Custom");
  $("#emailTemplateName").val("");
  $("#emailSubjectInput").val("");
  $("#emailBodyInput").val("");
  updateEmailPreview();
}

function removeEmailRecipient(email) {
  const normalized = normalizeEmailValue(email);
  if (!normalized) return;

  const suppressed = getSuppressedEmails();
  suppressed.add(normalized);
  saveSuppressedEmails(suppressed);

  const manual = getManualRecipients().filter(
    (item) => item.email !== normalized,
  );
  saveManualRecipients(manual);

  const newsletter = readJsonStorage(NEWSLETTER_SUBSCRIBERS_KEY, []);
  if (Array.isArray(newsletter)) {
    const filtered = newsletter.filter(
      (item) => normalizeEmailValue(item.email || item) !== normalized,
    );
    sessionStorage.setItem(
      NEWSLETTER_SUBSCRIBERS_KEY,
      JSON.stringify(filtered),
    );
  }

  emailDirectory = emailDirectory.filter((entry) => entry.email !== normalized);
  emailSelected.delete(normalized);
}

function sendEmailFromComposer() {
  const recipients = Array.from(emailSelected);
  const subject = ($("#emailSubjectInput").val() || "").trim();
  const body = ($("#emailBodyInput").val() || "").trim();
  const attachmentInput = document.getElementById("emailAttachmentInput");
  const hasFiles = attachmentInput?.files && attachmentInput.files.length > 0;

  if (!recipients.length) {
    showNotification("Select at least one recipient", "danger");
    return;
  }
  if (!subject && !body) {
    showNotification("Add a subject or message before sending", "danger");
    return;
  }

  if (hasFiles) {
    showNotification(
      "Attachments must be added in your email client after it opens.",
      "warning",
    );
  }

  const mailto = `mailto:?bcc=${encodeURIComponent(recipients.join(","))}&subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
  if (mailto.length > 1900) {
    showNotification(
      "Too many recipients for a mailto link. Copy emails instead.",
      "warning",
    );
    return;
  }

  const outbox = readJsonStorage(EMAIL_OUTBOX_KEY, []);
  if (Array.isArray(outbox)) {
    outbox.unshift({
      subject,
      body,
      recipients,
      sentAt: new Date().toISOString(),
    });
    sessionStorage.setItem(
      EMAIL_OUTBOX_KEY,
      JSON.stringify(outbox.slice(0, 50)),
    );
  }

  window.location.href = mailto;
}
