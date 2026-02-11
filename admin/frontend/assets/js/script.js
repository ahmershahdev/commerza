let productsData = [];
let allSections = [];
let nextId = 41;
let nextSectionId = 1;
const PRODUCTS_STORAGE_KEY = 'commerza_products';
const SITE_SETTINGS_KEY = 'commerza_site_settings';
let siteSettings = null;
let nextSocialId = 1;
let nextSliderId = 1;
const NEWSLETTER_SUBSCRIBERS_KEY = 'commerza_newsletter_subscribers';
const NEWSLETTER_EMAIL_KEY = 'commerza_newsletter_email';
const USERS_KEY = 'commerza_users';
const ORDERS_KEY = 'commerza_orders';
const EMAIL_TEMPLATES_KEY = 'commerza_email_templates';
const EMAIL_OUTBOX_KEY = 'commerza_email_outbox';
const EMAIL_MANUAL_RECIPIENTS_KEY = 'commerza_email_manual_recipients';
const EMAIL_SUPPRESSED_KEY = 'commerza_email_suppressed';
const VIEWERS_MODE_KEY = 'commerza_viewers_mode';
const PAGE_META_KEY = 'commerza_page_meta';
const PAGE_CONTENT_KEY = 'commerza_page_content';
const DEFAULT_EMAIL_TEMPLATES = [
    {
        id: 1,
        name: 'Welcome to Commerza Circle',
        subject: 'Welcome to the Commerza Circle',
        body: 'Hi there,\n\nThanks for joining the Commerza Circle. You will get early access to launches, exclusive offers, and collector stories.\n\n- The Commerza Team'
    },
    {
        id: 2,
        name: 'New Arrivals Drop',
        subject: 'New arrivals just landed',
        body: 'Hello,\n\nOur latest watches are live now. Explore the newest drops and find your next statement piece.\n\nShop now: https://commerza.com\n\n- The Commerza Team'
    },
    {
        id: 3,
        name: 'Limited Time Offer',
        subject: 'Limited-time offer inside',
        body: 'Hi,\n\nFor a limited time, enjoy exclusive pricing on selected collections. The offer ends soon, so do not miss out.\n\n- The Commerza Team'
    },
    {
        id: 4,
        name: 'Back in Stock Alert',
        subject: 'Back in stock: your favorites',
        body: 'Hello,\n\nGood news! Popular watches are back in stock. Quantities are limited, so grab yours soon.\n\n- The Commerza Team'
    },
    {
        id: 5,
        name: 'Order Update',
        subject: 'Your Commerza order update',
        body: 'Hi,\n\nWe wanted to share a quick update about your order. If you have any questions, reply to this email and our team will help.\n\n- The Commerza Team'
    },
    {
        id: 6,
        name: 'Shipping Delay Notice',
        subject: 'Shipping update from Commerza',
        body: 'Hello,\n\nWe are experiencing a short shipping delay due to high demand. Your order is still on the way, and we will share tracking soon.\n\n- The Commerza Team'
    },
    {
        id: 7,
        name: 'VIP Early Access',
        subject: 'VIP early access is live',
        body: 'Hi,\n\nAs a Commerza subscriber, you get early access to our newest collection. Take a first look before the public launch.\n\n- The Commerza Team'
    },
    {
        id: 8,
        name: 'Holiday Gift Guide',
        subject: 'Holiday gift picks from Commerza',
        body: 'Hello,\n\nNeed a gift that stands out? Our holiday guide highlights the best watches for every style and budget.\n\n- The Commerza Team'
    },
    {
        id: 9,
        name: 'Feedback Request',
        subject: 'We would love your feedback',
        body: 'Hi,\n\nYour feedback helps us improve. If you have a moment, let us know what you love and what we can do better.\n\n- The Commerza Team'
    },
    {
        id: 10,
        name: 'Monthly Newsletter',
        subject: 'Your Commerza monthly roundup',
        body: 'Hello,\n\nHere is your monthly roundup with new releases, staff picks, and limited offers.\n\n- The Commerza Team'
    },
    {
        id: 11,
        name: 'Support Reply',
        subject: 'Re: Support request',
        body: 'Hi,\n\nThanks for reaching out to Commerza support. We are looking into this and will update you shortly.\n\nIf you can share your order ID and any extra details, we can help faster.\n\n- Commerza Support'
    }
];
const EMAIL_SOURCE_BADGES = {
    Newsletter: 'bg-info text-dark',
    Account: 'bg-warning text-dark',
    Order: 'bg-success',
    Manual: 'bg-secondary'
};
let emailDirectory = [];
let emailSelected = new Set();
let emailFiltered = [];
let emailTemplates = [];
const ADMIN_PAGES = [
    { id: 'index.html', label: 'Home' },
    { id: 'products.html', label: 'Products' },
    { id: 'shop-category-a.html', label: 'Shop Category A' },
    { id: 'shop-category-b.html', label: 'Shop Category B' },
    { id: 'about.html', label: 'About' },
    { id: 'contact.html', label: 'Contact' },
    { id: 'faq.html', label: 'FAQ' },
    { id: 'returns.html', label: 'Returns' },
    { id: 'shipping.html', label: 'Shipping' },
    { id: 'warranty.html', label: 'Warranty' },
    { id: 'login.html', label: 'Login' },
    { id: 'signup.html', label: 'Signup' },
    { id: 'cart.html', label: 'Cart' },
    { id: 'wishlist.html', label: 'Wishlist' },
    { id: 'order-tracking.html', label: 'Order Tracking' },
    { id: 'compare.html', label: 'Compare' },
    { id: 'account.html', label: 'Account' }
];
// Notification timing rules keep the bell focused on recent actions.
const NOTIFICATION_RULES = {
    recentOrderDays: 7,
    pendingOrderDays: 14,
    newCustomerDays: 14,
    newProductDays: 7,
    lowStockThreshold: 5
};

function normalizeEmailValue(email) {
    return (email || '').toString().trim().toLowerCase();
}

function readJsonStorage(key, fallback) {
    const stored = localStorage.getItem(key);
    if (!stored) return fallback;
    try {
        return JSON.parse(stored);
    } catch (error) {
        return fallback;
    }
}

function formatShortDate(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toISOString().split('T')[0];
}

function updateEmailPreview() {
    const subject = ($('#emailSubjectInput').val() || '').trim();
    const body = ($('#emailBodyInput').val() || '').trim();
    const preview = $('#emailPreview');
    if (!preview.length) return;
    const attachmentInput = document.getElementById('emailAttachmentInput');
    const files = attachmentInput?.files ? Array.from(attachmentInput.files).map(file => file.name) : [];
    const attachmentLine = files.length ? `Attachments: ${files.join(', ')}` : 'Attachments: None';
    const title = subject ? `Subject: ${subject}` : 'Subject: (No subject)';
    preview.text(`${title}\n${attachmentLine}\n\n${body}`.trim());
}

function getNewsletterSubscribers() {
    const rawList = readJsonStorage(NEWSLETTER_SUBSCRIBERS_KEY, []);
    const list = Array.isArray(rawList) ? rawList : [];
    const legacyEmail = normalizeEmailValue(localStorage.getItem(NEWSLETTER_EMAIL_KEY));

    if (legacyEmail && !list.some(item => normalizeEmailValue(item.email || item) === legacyEmail)) {
        list.push({
            email: legacyEmail,
            sources: ['modal'],
            subscribedAt: new Date().toISOString(),
            updatedAt: new Date().toISOString()
        });
    }

    return list.map(item => {
        if (typeof item === 'string') {
            return {
                email: normalizeEmailValue(item),
                sources: ['modal'],
                subscribedAt: new Date().toISOString(),
                updatedAt: new Date().toISOString()
            };
        }
        return {
            email: normalizeEmailValue(item.email),
            sources: Array.isArray(item.sources) ? item.sources : [item.source || 'modal'],
            subscribedAt: item.subscribedAt,
            updatedAt: item.updatedAt || item.subscribedAt
        };
    }).filter(entry => entry.email);
}

function getManualRecipients() {
    const raw = readJsonStorage(EMAIL_MANUAL_RECIPIENTS_KEY, []);
    if (!Array.isArray(raw)) return [];
    return raw.map(item => {
        if (typeof item === 'string') {
            return { email: normalizeEmailValue(item), addedAt: new Date().toISOString() };
        }
        return {
            email: normalizeEmailValue(item.email),
            addedAt: item.addedAt || new Date().toISOString()
        };
    }).filter(item => item.email);
}

function saveManualRecipients(list) {
    localStorage.setItem(EMAIL_MANUAL_RECIPIENTS_KEY, JSON.stringify(list));
}

function buildEmailDirectory() {
    const directory = new Map();
    const suppressed = getSuppressedEmails();

    const addEntry = (email, source, meta = {}) => {
        const normalized = normalizeEmailValue(email);
        if (!normalized) return;
        if (suppressed.has(normalized)) return;
        const existing = directory.get(normalized) || {
            email: normalized,
            name: meta.name || '',
            sources: new Set(),
            firstSeen: meta.firstSeen || meta.lastSeen || new Date().toISOString(),
            lastSeen: meta.lastSeen || meta.firstSeen || new Date().toISOString()
        };
        existing.sources.add(source);
        if (meta.name && !existing.name) {
            existing.name = meta.name;
        }
        if (meta.firstSeen && (!existing.firstSeen || new Date(meta.firstSeen) < new Date(existing.firstSeen))) {
            existing.firstSeen = meta.firstSeen;
        }
        if (meta.lastSeen && new Date(meta.lastSeen) > new Date(existing.lastSeen)) {
            existing.lastSeen = meta.lastSeen;
        }
        directory.set(normalized, existing);
    };

    getNewsletterSubscribers().forEach(sub => {
        const lastSeen = sub.updatedAt || sub.subscribedAt;
        addEntry(sub.email, 'Newsletter', { lastSeen, firstSeen: sub.subscribedAt });
    });

    const users = readJsonStorage(USERS_KEY, []);
    if (Array.isArray(users)) {
        users.forEach(user => {
            addEntry(user.email, 'Account', { name: user.name, lastSeen: user.createdAt, firstSeen: user.createdAt });
        });
    }

    const orders = readJsonStorage(ORDERS_KEY, []);
    if (Array.isArray(orders)) {
        orders.forEach(order => {
            addEntry(order.email, 'Order', { name: order.customerName, lastSeen: order.orderDate, firstSeen: order.orderDate });
        });
    }

    getManualRecipients().forEach(item => {
        addEntry(item.email, 'Manual', { lastSeen: item.addedAt, firstSeen: item.addedAt });
    });

    return Array.from(directory.values()).map(entry => ({
        ...entry,
        sources: Array.from(entry.sources)
    })).sort((a, b) => new Date(b.lastSeen) - new Date(a.lastSeen));
}

function getEmailTemplates() {
    const stored = readJsonStorage(EMAIL_TEMPLATES_KEY, []);
    if (Array.isArray(stored) && stored.length) {
        return stored;
    }
    return DEFAULT_EMAIL_TEMPLATES.map(template => ({ ...template }));
}

function saveEmailTemplates(list) {
    localStorage.setItem(EMAIL_TEMPLATES_KEY, JSON.stringify(list));
}

function renderTemplateSelect() {
    const menu = $('#emailTemplateMenu');
    if (!menu.length) return;
    menu.empty();
    menu.append('<li><a class="dropdown-item text-light" href="#" data-template-id="">Custom</a></li>');
    menu.append('<li><hr class="dropdown-divider border-secondary"></li>');
    emailTemplates.forEach(template => {
        menu.append(`<li><a class="dropdown-item text-light" href="#" data-template-id="${template.id}">${template.name}</a></li>`);
    });
}

function renderEmailRecipients() {
    const tbody = $('#emailRecipientsTable tbody');
    if (!tbody.length) return;
    const filter = ($('#emailSourceFilter').val() || 'all').toLowerCase();
    const query = ($('#emailSearchInput').val() || '').trim().toLowerCase();

    emailFiltered = emailDirectory.filter(entry => {
        const inNewsletter = entry.sources.includes('Newsletter');
        const inCustomers = entry.sources.includes('Order') || entry.sources.includes('Account');
        if (filter === 'newsletter' && !inNewsletter) return false;
        if (filter === 'customers' && !inCustomers) return false;
        if (query) {
            const name = (entry.name || '').toLowerCase();
            return entry.email.includes(query) || name.includes(query);
        }
        return true;
    });

    $('#emailRecipientCount').text(emailDirectory.length);

    tbody.empty();
    if (!emailFiltered.length) {
        tbody.append('<tr><td colspan="5" class="text-center py-4 text-secondary">No recipients found</td></tr>');
        updateSelectedCount();
        return;
    }

    emailFiltered.forEach(entry => {
        const isChecked = emailSelected.has(entry.email);
        const badges = entry.sources.map(source => {
            const badgeClass = EMAIL_SOURCE_BADGES[source] || 'bg-secondary';
            return `<span class="badge ${badgeClass} me-1">${source}</span>`;
        }).join('');
        tbody.append(`
            <tr class="border-bottom border-secondary">
                <td class="ps-4 py-3">
                    <input type="checkbox" class="form-check-input email-recipient-check" data-email="${entry.email}" ${isChecked ? 'checked' : ''}>
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
    $('#emailSelectedCount').text(emailSelected.size);
}

function addManualRecipient(email) {
    const normalized = normalizeEmailValue(email);
    if (!normalized || !normalized.includes('@')) return false;
    const suppressed = getSuppressedEmails();
    if (suppressed.has(normalized)) {
        suppressed.delete(normalized);
        saveSuppressedEmails(suppressed);
    }
    const existing = getManualRecipients();
    if (!existing.some(item => item.email === normalized)) {
        existing.push({ email: normalized, addedAt: new Date().toISOString() });
        saveManualRecipients(existing);
    }
    if (!emailDirectory.some(entry => entry.email === normalized)) {
        emailDirectory.push({
            email: normalized,
            name: '',
            sources: ['Manual'],
            firstSeen: new Date().toISOString(),
            lastSeen: new Date().toISOString()
        });
        emailDirectory = emailDirectory.sort((a, b) => new Date(b.lastSeen) - new Date(a.lastSeen));
    }
    emailSelected.add(normalized);
    return true;
}

function applyTemplateToComposer(templateId) {
    const template = emailTemplates.find(item => String(item.id) === String(templateId));
    if (!template) return;
    $('#emailTemplateId').val(template.id);
    $('#emailTemplateBtn').text(template.name || 'Custom');
    $('#emailTemplateName').val(template.name || '');
    $('#emailSubjectInput').val(template.subject || '');
    $('#emailBodyInput').val(template.body || '');
    updateEmailPreview();
}

function saveTemplateFromComposer() {
    const name = ($('#emailTemplateName').val() || '').trim();
    const subject = ($('#emailSubjectInput').val() || '').trim();
    const body = ($('#emailBodyInput').val() || '').trim();
    if (!name || (!subject && !body)) {
        showNotification('Add a template name and content before saving', 'danger');
        return;
    }

    const selectedId = $('#emailTemplateId').val();
    if (selectedId) {
        const index = emailTemplates.findIndex(item => String(item.id) === String(selectedId));
        if (index !== -1) {
            emailTemplates[index] = { ...emailTemplates[index], name, subject, body };
            saveEmailTemplates(emailTemplates);
            renderTemplateSelect();
            $('#emailTemplateId').val(selectedId);
            $('#emailTemplateBtn').text(name || 'Custom');
            showNotification('Template updated!', 'success');
            return;
        }
    }

    const nextId = Math.max(0, ...emailTemplates.map(item => item.id || 0)) + 1;
    emailTemplates.push({ id: nextId, name, subject, body });
    saveEmailTemplates(emailTemplates);
    renderTemplateSelect();
    $('#emailTemplateId').val(String(nextId));
    $('#emailTemplateBtn').text(name || 'Custom');
    showNotification('Template saved!', 'success');
}

function resetComposerTemplate() {
    $('#emailTemplateId').val('');
    $('#emailTemplateBtn').text('Custom');
    $('#emailTemplateName').val('');
    $('#emailSubjectInput').val('');
    $('#emailBodyInput').val('');
    updateEmailPreview();
}

function removeEmailRecipient(email) {
    const normalized = normalizeEmailValue(email);
    if (!normalized) return;

    const suppressed = getSuppressedEmails();
    suppressed.add(normalized);
    saveSuppressedEmails(suppressed);

    const manual = getManualRecipients().filter(item => item.email !== normalized);
    saveManualRecipients(manual);

    const newsletter = readJsonStorage(NEWSLETTER_SUBSCRIBERS_KEY, []);
    if (Array.isArray(newsletter)) {
        const filtered = newsletter.filter(item => normalizeEmailValue(item.email || item) !== normalized);
        localStorage.setItem(NEWSLETTER_SUBSCRIBERS_KEY, JSON.stringify(filtered));
    }

    emailDirectory = emailDirectory.filter(entry => entry.email !== normalized);
    emailSelected.delete(normalized);
}

function sendEmailFromComposer() {
    const recipients = Array.from(emailSelected);
    const subject = ($('#emailSubjectInput').val() || '').trim();
    const body = ($('#emailBodyInput').val() || '').trim();
    const attachmentInput = document.getElementById('emailAttachmentInput');
    const hasFiles = attachmentInput?.files && attachmentInput.files.length > 0;

    if (!recipients.length) {
        showNotification('Select at least one recipient', 'danger');
        return;
    }
    if (!subject && !body) {
        showNotification('Add a subject or message before sending', 'danger');
        return;
    }

    if (hasFiles) {
        showNotification('Attachments must be added in your email client after it opens.', 'warning');
    }

    const mailto = `mailto:?bcc=${encodeURIComponent(recipients.join(','))}&subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    if (mailto.length > 1900) {
        showNotification('Too many recipients for a mailto link. Copy emails instead.', 'warning');
        return;
    }

    const outbox = readJsonStorage(EMAIL_OUTBOX_KEY, []);
    if (Array.isArray(outbox)) {
        outbox.unshift({
            subject,
            body,
            recipients,
            sentAt: new Date().toISOString()
        });
        localStorage.setItem(EMAIL_OUTBOX_KEY, JSON.stringify(outbox.slice(0, 50)));
    }

    window.location.href = mailto;
}

function initEmailCenter() {
    if (!$('#emailSection').length) return;
    emailDirectory = buildEmailDirectory();
    emailTemplates = getEmailTemplates();
    renderTemplateSelect();
    renderEmailRecipients();
    updateEmailPreview();

    $(document).on('click', '#emailSourceMenu .dropdown-item', function (event) {
        event.preventDefault();
        const source = $(this).data('source') || 'all';
        $('#emailSourceFilter').val(source);
        $('#emailSourceBtn').text($(this).text().trim());
        renderEmailRecipients();
    });
    $('#emailSearchInput').on('input', renderEmailRecipients);

    $(document).on('change', '.email-recipient-check', function () {
        const email = $(this).data('email');
        if (!email) return;
        if (this.checked) {
            emailSelected.add(email);
        } else {
            emailSelected.delete(email);
        }
        updateSelectedCount();
    });

    $('#emailSelectAllBtn').on('click', function () {
        emailFiltered.forEach(entry => emailSelected.add(entry.email));
        renderEmailRecipients();
    });

    $('#emailClearBtn').on('click', function () {
        emailSelected.clear();
        renderEmailRecipients();
    });

    $('#emailCopyBtn').on('click', function () {
        const list = Array.from(emailSelected);
        if (!list.length) {
            showNotification('Select recipients to copy', 'warning');
            return;
        }
        const text = list.join(', ');
        navigator.clipboard?.writeText(text).then(() => {
            showNotification('Emails copied to clipboard', 'success');
        }).catch(() => {
            showNotification('Unable to copy emails', 'danger');
        });
    });

    $('#emailAddRecipientBtn').on('click', function () {
        const input = $('#emailAddRecipientInput');
        const value = input.val();
        if (!addManualRecipient(value)) {
            showNotification('Enter a valid email address', 'danger');
            return;
        }
        input.val('');
        renderEmailRecipients();
        showNotification('Recipient added', 'success');
    });

    $(document).on('click', '.email-remove-recipient', function () {
        const email = $(this).data('email');
        if (!email) return;
        removeEmailRecipient(email);
        renderEmailRecipients();
        showNotification('Recipient removed', 'success');
    });

    $(document).on('click', '#emailTemplateMenu .dropdown-item', function (event) {
        event.preventDefault();
        const templateId = $(this).data('template-id');
        if (!templateId) {
            $('#emailTemplateId').val('');
            $('#emailTemplateBtn').text('Custom');
            $('#emailTemplateName').val('');
            updateEmailPreview();
            return;
        }
        applyTemplateToComposer(templateId);
    });

    $('#emailSubjectInput, #emailBodyInput, #emailAttachmentInput').on('input change', updateEmailPreview);

    $('#emailSaveTemplateBtn').on('click', saveTemplateFromComposer);
    $('#emailNewTemplateBtn').on('click', resetComposerTemplate);
    $('#emailSendBtn').on('click', sendEmailFromComposer);
}

function isWithinDays(dateString, days) {
    if (!dateString) return false;
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return false;
    const now = new Date();
    const diffMs = now - date;
    const diffDays = diffMs / (1000 * 60 * 60 * 24);
    return diffDays <= days;
}

function get30DaysAgo() {
    const date = new Date();
    date.setDate(date.getDate() - 30);
    return date.toISOString().split('T')[0];
}

function calculateDashboardMetrics() {
    const thirtyDaysAgo = get30DaysAgo();
    
    let orders = JSON.parse(localStorage.getItem('commerza_orders')) || [];
    const recentOrders = orders.filter(o => o.orderDate >= thirtyDaysAgo && o.status === 'Delivered');
    const totalRevenue = recentOrders.reduce((sum, order) => sum + (order.total || 0), 0);
    
    const totalOrdersCount = orders.filter(o => o.orderDate >= thirtyDaysAgo).length;
    
    const customerSet = new Set();
    orders.filter(o => o.orderDate >= thirtyDaysAgo).forEach(o => {
        customerSet.add(o.customerName);
    });
    const totalCustomers = customerSet.size;
    
    document.getElementById('totalRevenueValue').textContent = 'PKR ' + totalRevenue.toLocaleString();
    
    document.getElementById('totalOrdersValue').textContent = totalOrdersCount;
    
    document.getElementById('totalCustomersValue').textContent = totalCustomers;
    
    let totalProducts = 0;
    
    if (allSections && allSections.length > 0) {
        allSections.forEach(section => {
            if (section.products && Array.isArray(section.products)) {
                totalProducts += section.products.length;
            }
        });
    }
    
    document.getElementById('totalProductsValue').textContent = totalProducts;
}

function loadProductsFromJSON() {
    const stored = localStorage.getItem(PRODUCTS_STORAGE_KEY);
    if (stored) {
        try {
            const data = JSON.parse(stored);
            hydrateProductsData(data);
            return;
        } catch (error) {
            console.warn('Invalid stored products, reloading from JSON');
        }
    }

    fetch('../../products.json')
        .then(response => response.json())
        .then(data => {
            hydrateProductsData(data);
        })
        .catch(error => {
            console.error('Error loading products.json:', error);
            showNotification('Error loading products. Please refresh the page.', 'danger');
        });
}

function hydrateProductsData(data) {
    allSections = data.sections || [];
    productsData = [];
    allSections.forEach(section => {
        (section.products || []).forEach(product => {
            productsData.push({
                ...product,
                category: section.category,
                subcategory: section.subcategory,
                sectionName: section.sectionName,
                sectionId: section.sectionId,
                page: section.page
            });
        });
    });
    nextId = productsData.length ? Math.max(...productsData.map(p => p.id)) + 1 : 1;
    nextSectionId = allSections.length ? allSections.length + 1 : 1;
    renderSectionDropdowns();
    renderSectionsTable();
    renderProductsTable();
    calculateDashboardMetrics();
    updateNotifications();
}

function saveProductsToJSON() {
    const dataToSave = {
        meta: {
            total: productsData.length,
            currency: "PKR",
            lastUpdated: new Date().toISOString().split('T')[0]
        },
        sections: allSections.map(section => ({
            ...section,
            products: productsData.filter(p => p.sectionId === section.sectionId)
        }))
    };
    
    localStorage.setItem(PRODUCTS_STORAGE_KEY, JSON.stringify(dataToSave));
    console.log('Products synced to localStorage:', dataToSave);
}

function renderProductsTable() {
    const filterSection = window.currentSectionFilter || '';
    const tbody = $('#productsTable tbody');
    tbody.empty();
    
    let filteredProducts = productsData;
    if (filterSection) {
        filteredProducts = productsData.filter(p => p.sectionId === filterSection);
    }

    $('#productCount').text(filteredProducts.length);
    
    if (filteredProducts.length === 0) {
        tbody.append('<tr><td colspan="6" class="text-center py-4 text-secondary">No products found</td></tr>');
        return;
    }

    filteredProducts.forEach(product => {
        const stock = product.stock > 10 ? `<span class="badge bg-success rounded-pill">In Stock (${product.stock})</span>` : `<span class="badge bg-warning text-dark rounded-pill">Low (${product.stock})</span>`;
        tbody.append(`
            <tr class="border-bottom border-secondary">
                <td class="ps-4 py-3"><img src="../../${product.image}" alt="${product.name}" class="rounded" width="50" height="50" style="object-fit: cover; cursor: pointer;" onerror="this.src='assests/images/products/placeholder.webp'"></td>
                <td class="py-3 text-light fw-semibold" style="max-width: 200px;">${product.name}</td>
                <td class="py-3 text-secondary small"><span class="d-block text-warning">${product.sectionName}</span><span class="text-secondary">${product.category}</span></td>
                <td class="py-3 text-light fw-semibold"><span class="text-secondary" style="text-decoration: line-through; font-size: 0.9rem;">${product.price}</span><span class="ms-2 text-orange">${product.salePrice}</span></td>
                <td class="py-3">${stock}</td>
                <td class="pe-4 py-3"><button class="btn btn-sm btn-outline-orange me-1" onclick="editProduct(${product.id})" title="Edit"><i class="bi bi-pencil"></i></button><button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(${product.id})" title="Delete"><i class="bi bi-trash"></i></button></td>
            </tr>
        `);
    });
}

function editProduct(id) {
    const product = productsData.find(p => p.id === id);
    if (product) {
        $('#productId').val(product.id);
        $('#productName').val(product.name);
        $('#productPrice').val(product.price);
        $('#productSalePrice').val(product.salePrice);
        $('#productStock').val(product.stock);
        
        const sectionName = allSections.find(s => s.sectionId === product.sectionId)?.sectionName || 'Select Section';
        $('#productSectionBtn').text(sectionName);
        $('#productSection').val(product.sectionId);
        
        const movementType = product.movement || 'quartz';
        const movementDisplay = movementType === 'quartz' ? 'Quartz' : movementType === 'auto' ? 'Automatic' : 'Smart';
        $('#productMovementBtn').text(movementDisplay);
        $('#productMovement').val(movementType);
        
        $('#productImage').val(product.image);
        $('#productDescription').val(product.description);
        $('#productModalLabel').text('Edit Product');
        new bootstrap.Modal(document.getElementById('productModal')).show();
    }
}

function deleteProduct(id) {
    if (confirm('Delete this product?')) {
        const index = productsData.findIndex(p => p.id === id);
        if (index > -1) {
            const name = productsData[index].name;
            productsData.splice(index, 1);
            saveProductsToJSON();
            renderProductsTable();
            calculateDashboardMetrics();
            updateNotifications();
            showNotification(`"${name}" deleted!`, 'success');
        }
    }
}

function filterBySection(sectionId, sectionName) {
    $('#sectionFilterBtn').text(sectionName);
    window.currentSectionFilter = sectionId;
    renderProductsTable();
}

function selectProductSection(sectionId, sectionName) {
    $('#productSectionBtn').text(sectionName);
    $('#productSection').val(sectionId);
}

function renderSectionDropdowns() {
    const filterMenu = $('#sectionFilterMenu');
    const productMenu = $('#productSectionMenu');

    if (filterMenu.length) {
        filterMenu.empty();
        filterMenu.append('<li><a class="dropdown-item text-light" href="#" onclick="filterBySection(\'\', \'All Sections\'); return false;">All Sections</a></li>');
        filterMenu.append('<li><hr class="dropdown-divider border-secondary"></li>');
        allSections.forEach(section => {
            filterMenu.append(`<li><a class="dropdown-item text-light" href="#" onclick="filterBySection('${section.sectionId}', '${section.sectionName}'); return false;">${section.sectionName}</a></li>`);
        });
    }

    if (productMenu.length) {
        productMenu.empty();
        productMenu.append('<li><a class="dropdown-item text-light" href="#" onclick="selectProductSection(\'\', \'Select Section\'); return false;">Select Section</a></li>');
        productMenu.append('<li><hr class="dropdown-divider border-secondary"></li>');
        allSections.forEach(section => {
            productMenu.append(`<li><a class="dropdown-item text-light" href="#" onclick="selectProductSection('${section.sectionId}', '${section.sectionName}'); return false;">${section.sectionName}</a></li>`);
        });
    }
}

function renderSectionsTable() {
    const tbody = $('#sectionsTable tbody');
    if (!tbody.length) return;

    tbody.empty();

    if (!allSections.length) {
        tbody.append('<tr><td colspan="4" class="text-center py-4 text-secondary">No sections created</td></tr>');
        return;
    }

    allSections.forEach(section => {
        tbody.append(`
            <tr class="border-bottom border-secondary">
                <td class="ps-4 py-3 text-light fw-semibold">${section.sectionName}<div class="text-secondary small">${section.sectionId}</div></td>
                <td class="py-3 text-secondary small">${section.page || 'N/A'}</td>
                <td class="py-3 text-secondary small">${section.category || 'Uncategorized'}<div class="text-secondary">${section.subcategory || ''}</div></td>
                <td class="pe-4 py-3">
                    <button class="btn btn-sm btn-outline-orange me-1" onclick="editSection('${section.sectionId}')"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteSection('${section.sectionId}')"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
        `);
    });
}

function slugifySection(name) {
    return (name || '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
}

function ensureUniqueSectionId(baseId) {
    let id = baseId || `section-${nextSectionId}`;
    let counter = 2;
    while (allSections.some(section => section.sectionId === id)) {
        id = `${baseId}-${counter++}`;
    }
    return id;
}

function editSection(sectionId) {
    const section = allSections.find(item => item.sectionId === sectionId);
    if (!section) return;
    $('#sectionFormId').val(section.sectionId);
    $('#sectionName').val(section.sectionName || '');
    $('#sectionId').val(section.sectionId || '');
    $('#sectionPage').val(section.page || '');
    $('#sectionCategory').val(section.category || '');
    $('#sectionSubcategory').val(section.subcategory || '');
    $('#saveSectionBtn').html('<i class="bi bi-save2 me-1"></i>Update Section');
}

function deleteSection(sectionId) {
    if (!confirm('Delete this section and all its products?')) return;
    allSections = allSections.filter(section => section.sectionId !== sectionId);
    productsData = productsData.filter(product => product.sectionId !== sectionId);
    if (window.currentSectionFilter === sectionId) {
        window.currentSectionFilter = '';
        $('#sectionFilterBtn').text('All Sections');
    }
    saveProductsToJSON();
    renderSectionDropdowns();
    renderSectionsTable();
    renderProductsTable();
    calculateDashboardMetrics();
    updateNotifications();
    resetSectionForm();
    showNotification('Section deleted!', 'success');
}

function resetSectionForm() {
    $('#sectionFormId').val('');
    $('#sectionName').val('');
    $('#sectionId').val('');
    $('#sectionPage').val('');
    $('#sectionCategory').val('');
    $('#sectionSubcategory').val('');
    $('#saveSectionBtn').html('<i class="bi bi-plus-circle me-1"></i>Add Section');
}

function selectProductMovement(movementId, movementName) {
    $('#productMovementBtn').text(movementName);
    $('#productMovement').val(movementId);
}

function exportProductsData() {
    if (productsData.length === 0) {
        showNotification('No products to export', 'warning');
        return;
    }
    const dataToExport = {
        meta: {
            total: productsData.length,
            currency: "PKR",
            exportedDate: new Date().toISOString().split('T')[0],
            exportedTime: new Date().toLocaleTimeString()
        },
        sections: allSections.map(section => ({
            ...section,
            products: productsData.filter(p => p.sectionId === section.sectionId)
        }))
    };
    const blob = new Blob([JSON.stringify(dataToExport, null, 2)], { type: 'application/json' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `products-export-${new Date().toISOString().split('T')[0]}.json`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showNotification('Products exported!', 'success');
}

function exportProductsAsCSV() {
    if (productsData.length === 0) {
        showNotification('No products to export', 'warning');
        return;
    }
    const headers = ['ID', 'Name', 'Section', 'Category', 'Price', 'Sale Price', 'Stock', 'Movement', 'Description'];
    const rows = productsData.map(p => [p.id, `"${p.name}"`, p.sectionName, p.category, p.price, p.salePrice, p.stock, p.movement, `"${p.description}"`]);
    const csvContent = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `products-export-${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showNotification('CSV exported!', 'success');
}

function showNotification(message, type) {
    const alertClass = type === 'success' ? 'alert-success' : type === 'danger' ? 'alert-danger' : 'alert-warning';
    $('body').append(`
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert" style="position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `);
    setTimeout(() => $('.alert').fadeOut('slow', function() { $(this).remove(); }), 3000);
}

function showStatusNotification(orderId, oldStatus, newStatus) {
    const notif = $(`<div class="alert alert-info alert-dismissible fade show" role="alert" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999; min-width: 380px; background-color: #1a3a4a; border: 2px solid #0d6efd; border-radius: 8px; padding: 30px; box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);"><div style="color: #fff; text-align: center;"><i class="bi bi-arrow-left-right" style="font-size: 2rem; color: #0d6efd; display: block; margin-bottom: 10px;"></i><h5 style="margin: 10px 0; color: #0d6efd;">Order Status Updated!</h5><p style="margin: 5px 0; font-size: 0.95rem;">Order: <strong>${orderId}</strong></p><p style="margin: 10px 0; font-size: 0.9rem; color: #b0b0b0;"><span style="background-color: #332a1a; padding: 4px 8px; border-radius: 4px;">${oldStatus}</span><i class="bi bi-arrow-right" style="margin: 0 8px; color: #0d6efd;"></i><span style="background-color: #1a332a; padding: 4px 8px; border-radius: 4px;">${newStatus}</span></p></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button></div>`);
    $('body').append(notif);
    setTimeout(() => notif.remove(), 4000);
}

function updateNotifications() {
    const list = $('#notificationList');
    const count = $('#notificationCount');
    if (!list.length || !count.length) return;

    const notifications = [];
    const orders = JSON.parse(localStorage.getItem('commerza_orders')) || [];

    const recentOrders = orders.filter(order => isWithinDays(order.orderDate, NOTIFICATION_RULES.recentOrderDays));
    if (recentOrders.length > 0) {
        const latestOrder = [...recentOrders].sort((a, b) => new Date(b.orderDate) - new Date(a.orderDate))[0];
        if (latestOrder && latestOrder.orderId) {
            notifications.push({
                text: `New order ${latestOrder.orderId} (last ${NOTIFICATION_RULES.recentOrderDays} days)`,
                icon: 'bi-receipt',
                type: 'info'
            });
        }
    }

    const pendingOrders = orders.filter(order => {
        const status = (order.status || '').toLowerCase();
        return isWithinDays(order.orderDate, NOTIFICATION_RULES.pendingOrderDays)
            && (status === 'pending' || status === 'processing');
    });
    if (pendingOrders.length > 0) {
        notifications.push({
            text: `${pendingOrders.length} order(s) waiting for action (last ${NOTIFICATION_RULES.pendingOrderDays} days)`,
            icon: 'bi-clock-history',
            type: 'warning'
        });
    }

    const newCustomers = new Set();
    orders.forEach(order => {
        if (isWithinDays(order.orderDate, NOTIFICATION_RULES.newCustomerDays) && order.customerName) {
            newCustomers.add(order.customerName);
        }
    });
    if (newCustomers.size > 0) {
        notifications.push({
            text: `New customers: ${newCustomers.size} (last ${NOTIFICATION_RULES.newCustomerDays} days)`,
            icon: 'bi-person-check',
            type: 'success'
        });
    }

    const newProducts = productsData.filter(product => isWithinDays(product.createdAt, NOTIFICATION_RULES.newProductDays));
    if (newProducts.length > 0) {
        notifications.push({
            text: `New products added: ${newProducts.length} (last ${NOTIFICATION_RULES.newProductDays} days)`,
            icon: 'bi-bag-plus',
            type: 'info'
        });
    }

    const lowStock = productsData.filter(product => Number(product.stock) <= NOTIFICATION_RULES.lowStockThreshold);
    if (lowStock.length > 0) {
        notifications.push({
            text: `${lowStock.length} product(s) low stock (check today)`,
            icon: 'bi-exclamation-triangle',
            type: 'danger'
        });
    }

    list.empty();
    list.append('<li><h6 class="dropdown-header text-secondary">Notifications</h6></li>');
    list.append('<li><hr class="dropdown-divider border-secondary"></li>');

    if (notifications.length === 0) {
        count.text('0');
        count.addClass('d-none');
        list.append('<li><span class="dropdown-item text-secondary">No new notifications</span></li>');
        return;
    }

    count.text(Math.min(notifications.length, 9));
    count.removeClass('d-none');

    notifications.forEach((notif, index) => {
        list.append(`
            <li><a class="dropdown-item text-light d-flex align-items-center gap-2" href="#" onclick="return false;">
                <i class="bi ${notif.icon} text-${notif.type}"></i>
                <span>${notif.text}</span>
            </a></li>
        `);
        if (index < notifications.length - 1) {
            list.append('<li><hr class="dropdown-divider border-secondary"></li>');
        }
    });
}

$(document).ready(function() {
    const config = window.CommerzaAdminConfig || {};
    const authKey = 'commerza_admin_auth';
    const emailKey = 'commerza_admin_email';
    const passwordKey = 'commerza_admin_password';
    const resetKeyStorage = 'commerza_admin_reset_key';
    const defaultEmail = (config.adminEmailDefault || 'commerza.ahmer@gmail.com').toLowerCase();
    const defaultPassword = config.adminPasswordDefault || 'Commerza@2026';
    const defaultResetKey = config.resetKey || 'COMMERZA-RESET-2026';

    if (!localStorage.getItem(emailKey)) {
        localStorage.setItem(emailKey, defaultEmail);
    }
    if (!localStorage.getItem(passwordKey)) {
        localStorage.setItem(passwordKey, defaultPassword);
    }
    if (!localStorage.getItem(resetKeyStorage)) {
        localStorage.setItem(resetKeyStorage, defaultResetKey);
    }

    const sidebar = document.getElementById('sidebarMenu');
    if (sidebar) {
        sidebar.addEventListener('shown.bs.collapse', () => {
            document.body.classList.add('sidebar-open');
        });
        sidebar.addEventListener('hidden.bs.collapse', () => {
            document.body.classList.remove('sidebar-open');
        });
    }

    function applyButtonCooldown(selector, duration = 1200) {
        $(document).on('click', selector, function () {
            const btn = $(this);
            if (btn.prop('disabled')) return;
            btn.prop('disabled', true);
            setTimeout(() => btn.prop('disabled', false), duration);
        });
    }

    applyButtonCooldown('#saveProductBtn');
    applyButtonCooldown('#saveSectionBtn');
    applyButtonCooldown('#resetSectionBtn');
    applyButtonCooldown('#saveContactBtn');
    applyButtonCooldown('#saveSocialBtn');
    applyButtonCooldown('#resetSocialBtn');
    applyButtonCooldown('#saveTickerBtn');
    applyButtonCooldown('#resetTickerBtn');
    applyButtonCooldown('#saveSliderBtn');
    applyButtonCooldown('#resetSliderBtn');
    applyButtonCooldown('#saveAdminEmailBtn');
    applyButtonCooldown('#saveAdminPasswordBtn');
    applyButtonCooldown('#saveAdminResetKeyBtn');

    $(document).on('click', '.password-toggle', function () {
        const target = $(this).data('target');
        const input = $(target);
        if (!input.length) return;
        input.attr('type', input.attr('type') === 'password' ? 'text' : 'password');
        $(this).toggleClass('bi-eye bi-eye-slash');
    });

    loadProductsFromJSON();
    initWebsiteSettings();
    initEmailCenter();
    
    setTimeout(() => {
        calculateDashboardMetrics();
        displayRecentOrders();
        displayAllOrders();
        displayAllCustomers();
        updateNotifications();
    }, 1000);

    $('#saveProductBtn').off('click').on('click', function() {
        if (!$('#productForm')[0].checkValidity()) {
            showNotification('Please fill in all required fields', 'danger');
            return;
        }

        const productId = $('#productId').val();
        const sectionId = $('#productSection').val();
        const section = allSections.find(s => s.sectionId === sectionId);
        const existingProduct = productId ? productsData.find(p => p.id === parseInt(productId)) : null;

        if (!section) {
            showNotification('Please select a valid section', 'danger');
            return;
        }
        
        const productData = {
            id: productId ? parseInt(productId) : nextId++,
            name: $('#productName').val(),
            price: parseInt($('#productPrice').val()),
            salePrice: parseInt($('#productSalePrice').val()),
            stock: parseInt($('#productStock').val()),
            image: $('#productImage').val(),
            description: $('#productDescription').val(),
            movement: $('#productMovement').val(),
            category: section ? section.category : 'Uncategorized',
            subcategory: section ? section.subcategory : 'General',
            sectionName: section ? section.sectionName : 'General',
            sectionId: sectionId,
            page: section ? section.page : 'index.html',
            createdAt: existingProduct?.createdAt || new Date().toISOString()
        };

        if (productId) {
            const index = productsData.findIndex(p => p.id === parseInt(productId));
            if (index > -1) {
                productsData[index] = productData;
                showNotification('Product updated!', 'success');
            }
        } else {
            productsData.push(productData);
            showNotification('Product added!', 'success');
        }

        saveProductsToJSON();
        renderProductsTable();
        calculateDashboardMetrics();
        updateNotifications();
        $('#productForm')[0].reset();
        $('#productId').val('');
        bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
    });

    $(document).off('click', '#addNewProductBtn').on('click', '#addNewProductBtn', function() {
        $('#productForm')[0].reset();
        $('#productId').val('');
        $('#productModalLabel').text('Add New Product');
        new bootstrap.Modal(document.getElementById('productModal')).show();
    });

    $('#saveSectionBtn').off('click').on('click', function() {
        const formId = $('#sectionFormId').val();
        const sectionName = $('#sectionName').val().trim();
        const rawId = $('#sectionId').val().trim();
        const page = $('#sectionPage').val().trim() || 'index.html';
        const category = $('#sectionCategory').val().trim() || 'Uncategorized';
        const subcategory = $('#sectionSubcategory').val().trim() || 'General';

        if (!sectionName) {
            showNotification('Section name is required', 'danger');
            return;
        }

        const baseId = rawId || slugifySection(sectionName);
        if (!baseId) {
            showNotification('Section ID is required', 'danger');
            return;
        }

        if (formId) {
            if (formId !== baseId && allSections.some(section => section.sectionId === baseId)) {
                showNotification('Section ID already exists', 'danger');
                return;
            }
            const section = allSections.find(item => item.sectionId === formId);
            if (section) {
                section.sectionName = sectionName;
                section.sectionId = baseId;
                section.page = page;
                section.category = category;
                section.subcategory = subcategory;
                productsData = productsData.map(product => {
                    if (product.sectionId !== formId) return product;
                    return {
                        ...product,
                        sectionId: baseId,
                        sectionName: sectionName,
                        category: category,
                        subcategory: subcategory,
                        page: page
                    };
                });
                showNotification('Section updated!', 'success');
            }
        } else {
            const uniqueId = ensureUniqueSectionId(baseId);
            allSections.push({
                sectionName,
                sectionId: uniqueId,
                page,
                category,
                subcategory,
                products: []
            });
            showNotification('Section added!', 'success');
        }

        saveProductsToJSON();
        renderSectionDropdowns();
        renderSectionsTable();
        renderProductsTable();
        updateNotifications();
        resetSectionForm();
    });

    $('#resetSectionBtn').off('click').on('click', function() {
        resetSectionForm();
    });

    $('#saveContactBtn').off('click').on('click', function() {
        if (!siteSettings) return;
        const address = $('#siteAddress').val().trim();
        const email = $('#siteEmail').val().trim();
        const phone = $('#sitePhone').val().trim();

        if (!address || !email || !phone) {
            showNotification('Please enter address, email and phone', 'danger');
            return;
        }

        siteSettings.contact = { address, email, phone };
        saveSiteSettings();
        showNotification('Contact details updated!', 'success');
    });

    $('#saveBrandBtn').off('click').on('click', function() {
        if (!siteSettings) return;
        const name = $('#siteName').val().trim();
        const logo = $('#siteLogo').val().trim();
        const favicon = $('#siteFavicon').val().trim();

        if (!name || !logo || !favicon) {
            showNotification('Please enter website name, logo, and favicon', 'danger');
            return;
        }

        siteSettings.brand = { name, logo, favicon };
        saveSiteSettings();
        showNotification('Branding updated!', 'success');
        if (typeof window.applyAdminBranding === 'function') {
            window.applyAdminBranding();
        }
    });

    $('#saveSocialBtn').off('click').on('click', function() {
        if (!siteSettings) return;
        const id = $('#socialId').val();
        const label = $('#socialLabel').val().trim();
        const url = $('#socialUrl').val().trim();
        const icon = $('#socialIcon').val().trim();

        if (!label || !url || !icon) {
            showNotification('Please fill in label, URL and icon', 'danger');
            return;
        }

        if (id) {
            const index = siteSettings.socialLinks.findIndex(link => link.id === parseInt(id));
            if (index !== -1) {
                siteSettings.socialLinks[index] = { id: parseInt(id), label, url, icon };
                showNotification('Social link updated!', 'success');
            }
        } else {
            siteSettings.socialLinks.push({ id: nextSocialId++, label, url, icon });
            showNotification('Social link added!', 'success');
        }

        saveSiteSettings();
        renderSocialLinksTable();
        resetSocialForm();
    });

    $('#resetSocialBtn').on('click', function() {
        resetSocialForm();
    });

    $('#saveTickerBtn').off('click').on('click', function() {
        if (!siteSettings) return;
        const enabled = $('#tickerEnabled').is(':checked');
        const messages = $('#tickerMessages').val()
            .split('\n')
            .map(line => line.trim())
            .filter(Boolean);

        if (messages.length === 0) {
            showNotification('Please add at least one ticker message', 'danger');
            return;
        }

        siteSettings.ticker = { enabled, messages };
        saveSiteSettings();
        showNotification('Ticker updated!', 'success');
    });

    $('#resetTickerBtn').on('click', function() {
        resetTickerForm();
    });

    $('#saveSliderBtn').off('click').on('click', function() {
        if (!siteSettings) return;
        const id = $('#sliderId').val();
        const image = $('#sliderImage').val().trim();
        const alt = $('#sliderAlt').val().trim();
        const label = $('#sliderLabel').val().trim();
        const heading = $('#sliderHeading').val().trim();
        const text = $('#sliderText').val().trim();
        const buttonText = $('#sliderButtonText').val().trim();
        const buttonLink = $('#sliderButtonLink').val().trim();

        if (!image || !heading) {
            showNotification('Please add image and heading', 'danger');
            return;
        }

        const slide = {
            id: id ? parseInt(id) : nextSliderId++,
            image,
            alt,
            label,
            heading,
            text,
            buttonText,
            buttonLink
        };

        if (id) {
            const index = siteSettings.sliderImages.findIndex(item => item.id === parseInt(id));
            if (index !== -1) {
                siteSettings.sliderImages[index] = slide;
                showNotification('Slide updated!', 'success');
            }
        } else {
            siteSettings.sliderImages.push(slide);
            showNotification('Slide added!', 'success');
        }

        saveSiteSettings();
        renderSliderTable();
        resetSliderForm();
    });

    $('#resetSliderBtn').on('click', function() {
        resetSliderForm();
    });

    $('#saveAdminEmailBtn').on('click', function() {
        const currentPassword = $('#securityEmailPassword').val();
        const resetKey = $('#securityEmailResetKey').val();
        const newEmail = ($('#securityEmailNew').val() || '').trim().toLowerCase();
        const confirmEmail = ($('#securityEmailConfirm').val() || '').trim().toLowerCase();

        const storedPassword = localStorage.getItem(passwordKey) || defaultPassword;
        const storedResetKey = localStorage.getItem(resetKeyStorage) || defaultResetKey;

        if (!currentPassword || !resetKey) {
            showNotification('Password and reset key are required', 'danger');
            return;
        }

        if (currentPassword !== storedPassword || resetKey !== storedResetKey) {
            showNotification('Invalid password or reset key', 'danger');
            return;
        }

        if (!newEmail || !confirmEmail || newEmail !== confirmEmail || !newEmail.includes('@')) {
            showNotification('Enter a valid matching email', 'danger');
            return;
        }

        localStorage.setItem(emailKey, newEmail);
        const authData = JSON.parse(localStorage.getItem(authKey) || '{}');
        authData.email = newEmail;
        authData.loggedInAt = authData.loggedInAt || new Date().toISOString();
        localStorage.setItem(authKey, JSON.stringify(authData));
        showNotification('Admin email updated!', 'success');
    });

    $('#saveAdminPasswordBtn').on('click', function() {
        const currentEmail = ($('#securityPasswordEmail').val() || '').trim().toLowerCase();
        const resetKey = $('#securityPasswordResetKey').val();
        const newPassword = $('#securityPasswordNew').val();
        const confirmPassword = $('#securityPasswordConfirm').val();

        const storedEmail = (localStorage.getItem(emailKey) || defaultEmail).toLowerCase();
        const storedResetKey = localStorage.getItem(resetKeyStorage) || defaultResetKey;

        if (!currentEmail || !resetKey) {
            showNotification('Email and reset key are required', 'danger');
            return;
        }

        if (currentEmail !== storedEmail || resetKey !== storedResetKey) {
            showNotification('Invalid email or reset key', 'danger');
            return;
        }

        if (!newPassword || newPassword !== confirmPassword) {
            showNotification('Passwords do not match', 'danger');
            return;
        }

        localStorage.setItem(passwordKey, newPassword);
        showNotification('Admin password updated!', 'success');
    });

    $('#saveAdminResetKeyBtn').on('click', function() {
        const currentEmail = ($('#securityKeyEmail').val() || '').trim().toLowerCase();
        const currentPassword = $('#securityKeyPassword').val();
        const newKey = ($('#securityKeyNew').val() || '').trim();
        const confirmKey = ($('#securityKeyConfirm').val() || '').trim();

        const storedEmail = (localStorage.getItem(emailKey) || defaultEmail).toLowerCase();
        const storedPassword = localStorage.getItem(passwordKey) || defaultPassword;

        if (!currentEmail || !currentPassword) {
            showNotification('Email and password are required', 'danger');
            return;
        }

        if (currentEmail !== storedEmail || currentPassword !== storedPassword) {
            showNotification('Invalid email or password', 'danger');
            return;
        }

        if (!newKey || newKey !== confirmKey) {
            showNotification('Reset keys do not match', 'danger');
            return;
        }

        localStorage.setItem(resetKeyStorage, newKey);
        showNotification('Reset key updated!', 'success');
    });
});

function displayRecentOrders() {
    let orders = JSON.parse(localStorage.getItem('commerza_orders')) || [];
    orders = orders.sort((a, b) => new Date(b.orderDate) - new Date(a.orderDate)).slice(0, 5);
    
    let tbody = document.querySelector('#dashboardSection .table tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (orders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-secondary">No orders yet</td></tr>';
        return;
    }
    
    orders.forEach((order, idx) => {
        const statusColor = order.status === 'Pending' ? 'bg-warning text-dark' : order.status === 'Shipped' ? 'bg-info text-dark' : order.status === 'Cancelled' ? 'bg-danger' : 'bg-success';
        const row = document.createElement('tr');
        row.className = 'border-bottom border-secondary';
        const itemCount = order.items.length;
        row.innerHTML = `
            <td class="ps-4 py-3 fw-semibold text-light">${order.orderId}</td>
            <td class="py-3 text-light">${order.customerName.split(' ')[0]}</td>
            <td class="py-3 text-secondary small">${order.orderDate}</td>
            <td class="py-3 text-light fw-semibold">PKR ${order.total.toLocaleString()}</td>
            <td class="pe-4 py-3">
                <span class="badge ${statusColor} rounded-pill px-3 py-2">${order.status}</span>
            </td>
        `;
        row.style.cursor = 'pointer';
        row.onclick = () => toggleOrderDetails('recentOrderDetails-' + idx);
        tbody.appendChild(row);
        const detailsRow = document.createElement('tr');
        detailsRow.id = 'recentOrderDetails-' + idx;
        detailsRow.style.display = 'none';
        detailsRow.className = 'bg-dark';
        detailsRow.innerHTML = `
            <td colspan="5" class="py-3 px-4">
                <div style="background-color: #2a2a2a; padding: 15px; border-radius: 6px;">
                    <h6 class="text-orange mb-3 fw-bold">📦 Products in Order</h6>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        ${order.items.map((item, i) => {
                            const price = typeof item.price === 'string' ? parseInt(item.price.replace(/\D/g, '')) : item.price;
                            const qty = parseInt(item.quantity) || 0;
                            const lineTotal = price * qty;
                            const imgSrc = item.image ? (item.image.startsWith('http') ? item.image : '../../' + item.image) : 'https://via.placeholder.com/50?text=No+Image';
                            return `
                            <div style="background-color: #1a1a1a; padding: 12px; border-radius: 4px; border: 1px solid #444; display: flex; gap: 12px;">
                                <img src="${imgSrc}" alt="${item.name}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; background-color: #333;" onerror="this.src='https://via.placeholder.com/50?text=No+Image';">
                                <div style="flex: 1;">
                                    <p class="text-light fw-semibold mb-1" style="font-size: 0.95rem;">${item.name}</p>
                                    <p class="text-secondary mb-0" style="font-size: 0.85rem;">Price: <strong class="text-orange">PKR ${price.toLocaleString()}</strong> × <strong>${qty}</strong> = <strong class="text-orange">PKR ${lineTotal.toLocaleString()}</strong></p>
                                </div>
                            </div>
                        `;
                        }).join('')}
                    </div>
                </div>
            </td>
        `;
        tbody.appendChild(detailsRow);
    });
}

function displayAllOrders() {
    let orders = JSON.parse(localStorage.getItem('commerza_orders')) || [];
    orders = orders.sort((a, b) => new Date(b.orderDate) - new Date(a.orderDate));
    
    let tbody = document.querySelector('#ordersSection .table tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (orders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-secondary">No orders found</td></tr>';
        return;
    }
    
    orders.forEach((order, idx) => {
        const statusColor = order.status === 'Pending' ? 'bg-warning text-dark' : order.status === 'Shipped' ? 'bg-info text-dark' : order.status === 'Cancelled' ? 'bg-danger' : 'bg-success';
        const row = document.createElement('tr');
        row.className = 'border-bottom border-secondary';
        row.innerHTML = `
            <td class="ps-4 py-3 fw-semibold text-light">${order.orderId}</td>
            <td class="py-3 text-light">${order.customerName}</td>
            <td class="py-3 text-secondary small">${order.orderDate}</td>
            <td class="py-3 text-light fw-semibold">PKR ${order.total.toLocaleString()}</td>
            <td class="py-3"><span class="badge bg-info text-dark rounded-pill">${order.paymentMethod}</span></td>
            <td class="py-3"><span class="badge ${statusColor} rounded-pill">${order.status}</span></td>
            <td class="pe-4 py-3">
                <button class="btn btn-sm btn-outline-danger" onclick="deleteOrder('${order.orderId}'); event.stopPropagation();"><i class="bi bi-trash"></i></button>
            </td>
        `;
        row.style.cursor = 'pointer';
        row.onclick = () => toggleOrderDetails('orderDetails-' + idx);
        tbody.appendChild(row);
        
        const detailsRow = document.createElement('tr');
        detailsRow.id = 'orderDetails-' + idx;
        detailsRow.style.display = 'none';
        detailsRow.className = 'bg-dark';
        detailsRow.innerHTML = `
            <td colspan="7" class="py-3 px-4">
                <div style="background-color: #2a2a2a; padding: 20px; border-radius: 6px;">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-orange mb-3 fw-bold">📋 Customer Details</h6>
                            <p class="text-secondary mb-1"><strong class="text-light">Name:</strong> ${order.customerName}</p>
                            <p class="text-secondary mb-1"><strong class="text-light">Email:</strong> ${order.email || 'N/A'}</p>
                            <p class="text-secondary mb-1"><strong class="text-light">Phone:</strong> ${order.phone}</p>
                            <p class="text-secondary"><strong class="text-light">Address:</strong> ${order.address}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-orange mb-3 fw-bold">💳 Order Summary</h6>
                            <p class="text-secondary mb-1"><strong class="text-light">Subtotal:</strong> PKR ${order.subtotal.toLocaleString()}</p>
                            <p class="text-secondary mb-1"><strong class="text-light">Shipping:</strong> PKR ${order.shipping}</p>
                            <p class="text-orange fw-bold"><strong>Total:</strong> PKR ${order.total.toLocaleString()}</p>
                            <div style="margin-top: 15px;">
                                <h6 class="text-orange mb-2 fw-bold">📊 Change Status</h6>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <button class="btn btn-sm btn-warning text-dark fw-semibold" onclick="updateOrderStatus('${order.orderId}', 'Pending'); event.stopPropagation();"><i class="bi bi-hourglass"></i> Pending</button>
                                    <button class="btn btn-sm btn-info text-dark fw-semibold" onclick="updateOrderStatus('${order.orderId}', 'Shipped'); event.stopPropagation();"><i class="bi bi-truck"></i> Shipped</button>
                                    <button class="btn btn-sm btn-success fw-semibold" onclick="updateOrderStatus('${order.orderId}', 'Delivered'); event.stopPropagation();"><i class="bi bi-check-circle"></i> Delivered</button>
                                    <button class="btn btn-sm btn-danger fw-semibold" onclick="updateOrderStatus('${order.orderId}', 'Cancelled'); event.stopPropagation();"><i class="bi bi-x-circle"></i> Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr style="border-color: #444;">
                    <h6 class="text-orange mb-3 fw-bold">📦 Products in Order</h6>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        ${order.items.map((item, i) => {
                            const price = typeof item.price === 'string' ? parseInt(item.price.replace(/\D/g, '')) : item.price;
                            const qty = parseInt(item.quantity) || 0;
                            const lineTotal = price * qty;
                            const imgSrc = item.image ? (item.image.startsWith('http') ? item.image : '../../' + item.image) : 'https://via.placeholder.com/60?text=No+Image';
                            return `
                            <div style="background-color: #1a1a1a; padding: 12px; border-radius: 4px; border: 1px solid #444; display: flex; gap: 12px;">
                                <img src="${imgSrc}" alt="${item.name}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; background-color: #333;" onerror="this.src='https://via.placeholder.com/60?text=No+Image';">
                                <div style="flex: 1;">
                                    <p class="text-light fw-semibold mb-1" style="font-size: 0.95rem;">${item.name}</p>
                                    <p class="text-secondary mb-0" style="font-size: 0.9rem;"><strong>Unit Price:</strong> PKR ${price.toLocaleString()} | <strong>Quantity:</strong> ${qty} | <strong class="text-orange">Total: PKR ${lineTotal.toLocaleString()}</strong></p>
                                </div>
                            </div>
                        `;
                        }).join('')}
                    </div>
                </div>
            </td>
        `;
        tbody.appendChild(detailsRow);
    });
}

function displayAllCustomers() {
    let orders = JSON.parse(localStorage.getItem('commerza_orders')) || [];
    
    const customerMap = {};
    orders.forEach(order => {
        if (!customerMap[order.customerName]) {
            customerMap[order.customerName] = {
                name: order.customerName,
                email: order.email,
                phone: order.phone,
                orderCount: 0,
                totalSpent: 0
            };
        }
        customerMap[order.customerName].orderCount++;
        customerMap[order.customerName].totalSpent += order.total;
    });
    
    let tbody = document.querySelector('#customersSection .table tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    const customers = Object.values(customerMap);
    
    if (customers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-secondary">No customers found</td></tr>';
        return;
    }
    
    customers.forEach(customer => {
        const initials = customer.name.split(' ').map(n => n[0]).join('');
        const row = document.createElement('tr');
        row.className = 'border-bottom border-secondary';
        row.innerHTML = `
            <td class="ps-4 py-3">
                <img src="https://ui-avatars.com/api/?name=${customer.name}&background=ff6600&color=000" alt="Customer" class="rounded-circle" width="40" height="40">
            </td>
            <td class="py-3 text-light fw-semibold">${customer.name}</td>
            <td class="py-3 text-secondary">${customer.email || 'N/A'}</td>
            <td class="py-3 text-secondary">${customer.phone || 'N/A'}</td>
            <td class="py-3 text-light">${customer.orderCount}</td>
            <td class="pe-4 py-3 text-light fw-semibold">PKR ${customer.totalSpent.toLocaleString()}</td>
        `;
        tbody.appendChild(row);
    });
}

function deleteOrder(orderId) {
    if (confirm('Are you sure you want to cancel this order?')) {
        updateOrderStatus(orderId, 'Cancelled');
    }
}

function updateOrderStatus(orderId, newStatus) {
    let orders = JSON.parse(localStorage.getItem('commerza_orders')) || [];
    const orderIndex = orders.findIndex(o => o.orderId === orderId);
    
    if (orderIndex !== -1) {
        const oldStatus = orders[orderIndex].status;
        orders[orderIndex].status = newStatus;
        localStorage.setItem('commerza_orders', JSON.stringify(orders));
        
        displayRecentOrders();
        displayAllOrders();
        displayAllCustomers();
        calculateDashboardMetrics();
        
        showStatusNotification(orderId, oldStatus, newStatus);
    }
}

function toggleOrderDetails(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.style.display = element.style.display === 'none' ? 'table-row' : 'none';
    }
}
function buildDefaultSiteSettings() {
    return {
        brand: {
            name: 'COMMERZA',
            logo: 'frontend/assets/images/logo/commerza-logo.webp',
            favicon: 'frontend/assets/images/favicon/commerza-watches-icon.ico'
        },
        contact: {
            address: 'Barrage Colony, HYD, PK',
            email: 'commerza.ahmer@gmail.com',
            phone: '+92 314 8396293'
        },
        ticker: {
            enabled: true,
            messages: [
                'SALE IS LIVE: PREMIUM AUTOMATIC WATCHES UP TO 20% OFF',
                'COLLECTION UPDATE: NEW SKELETON SERIES NOW AVAILABLE',
                'FREE SHIPPING: NATIONWIDE DELIVERY ON ALL PREMIUM ORDERS'
            ]
        },
        socialLinks: [
            { id: 1, label: 'Facebook', url: 'https://www.facebook.com/commerza.ahmer', icon: 'bi bi-facebook' },
            { id: 2, label: 'X', url: 'https://x.com/commerza_ahmer', icon: 'bi bi-twitter' },
            { id: 3, label: 'Instagram', url: 'https://www.instagram.com/commerza.ahmer', icon: 'bi bi-instagram' }
        ],
        sliderImages: [
            {
                id: 1,
                image: 'frontend/assets/images/slider/watch-banner-chronograph.webp',
                alt: 'luxury chronograph watch banner premium collection',
                label: 'Premium Collection',
                heading: 'Chronograph Precision',
                text: 'Engineered movements with dual finish cases',
                buttonText: 'Explore Now',
                buttonLink: 'shop-category-a.html'
            },
            {
                id: 2,
                image: 'frontend/assets/images/slider/watch-banner-collection.webp',
                alt: 'complete watch collection showcase all styles',
                label: 'Complete Series',
                heading: 'Every Style, One Place',
                text: 'From minimalist to bold statement pieces',
                buttonText: 'View Collection',
                buttonLink: 'shop-category-b.html'
            },
            {
                id: 3,
                image: 'frontend/assets/images/slider/watch-banner-premium.webp',
                alt: 'premium watches exclusive luxury timepieces',
                label: 'Exclusive Launch',
                heading: 'Limited Editions',
                text: 'Hand assembled luxury with skeleton dials',
                buttonText: 'Shop Limited',
                buttonLink: 'shop-category-b.html'
            }
        ]
    };
}

function loadSiteSettings() {
    const defaults = buildDefaultSiteSettings();
    const stored = localStorage.getItem(SITE_SETTINGS_KEY);
    if (!stored) return defaults;

    try {
        const parsed = JSON.parse(stored);
        return {
            ...defaults,
            ...parsed,
            brand: { ...defaults.brand, ...(parsed.brand || {}) },
            contact: { ...defaults.contact, ...(parsed.contact || {}) },
            ticker: {
                ...defaults.ticker,
                ...(parsed.ticker || {}),
                messages: Array.isArray(parsed.ticker?.messages) && parsed.ticker.messages.length
                    ? parsed.ticker.messages
                    : defaults.ticker.messages
            },
            socialLinks: Array.isArray(parsed.socialLinks) ? parsed.socialLinks : defaults.socialLinks,
            sliderImages: Array.isArray(parsed.sliderImages) ? parsed.sliderImages : defaults.sliderImages
        };
    } catch (error) {
        console.warn('Invalid site settings, using defaults');
        return defaults;
    }
}

function saveSiteSettings() {
    localStorage.setItem(SITE_SETTINGS_KEY, JSON.stringify(siteSettings));
}

function initWebsiteSettings() {
    if (!$('#websiteSection').length) return;

    siteSettings = loadSiteSettings();
    nextSocialId = Math.max(0, ...siteSettings.socialLinks.map(link => link.id || 0)) + 1;
    nextSliderId = Math.max(0, ...siteSettings.sliderImages.map(item => item.id || 0)) + 1;

    $('#siteAddress').val(siteSettings.contact.address || '');
    $('#siteEmail').val(siteSettings.contact.email || '');
    $('#sitePhone').val(siteSettings.contact.phone || '');
    $('#siteName').val(siteSettings.brand?.name || '');
    $('#siteLogo').val(siteSettings.brand?.logo || '');
    $('#siteFavicon').val(siteSettings.brand?.favicon || '');

    $('#tickerEnabled').prop('checked', siteSettings.ticker?.enabled !== false);
    $('#tickerMessages').val((siteSettings.ticker?.messages || []).join('\n'));

    renderSocialLinksTable();
    renderSliderTable();
}

function resetTickerForm() {
    const defaults = buildDefaultSiteSettings();
    $('#tickerEnabled').prop('checked', defaults.ticker.enabled);
    $('#tickerMessages').val(defaults.ticker.messages.join('\n'));
}

function renderSocialLinksTable() {
    const tbody = $('#socialLinksTable tbody');
    if (tbody.length === 0) return;
    tbody.empty();

    if (!siteSettings.socialLinks || siteSettings.socialLinks.length === 0) {
        tbody.append('<tr><td colspan="4" class="text-center py-4 text-secondary">No social links added</td></tr>');
        return;
    }

    siteSettings.socialLinks.forEach(link => {
        tbody.append(`
            <tr class="border-bottom border-secondary">
                <td class="ps-4 py-3 text-light fw-semibold">${link.label}</td>
                <td class="py-3 text-secondary small">${link.url}</td>
                <td class="py-3"><i class="${link.icon} text-orange"></i></td>
                <td class="pe-4 py-3">
                    <button class="btn btn-sm btn-outline-orange me-1" onclick="editSocialLink(${link.id})"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteSocialLink(${link.id})"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
        `);
    });
}

function editSocialLink(id) {
    const link = siteSettings.socialLinks.find(item => item.id === id);
    if (!link) return;
    $('#socialId').val(link.id);
    $('#socialLabel').val(link.label);
    $('#socialUrl').val(link.url);
    $('#socialIcon').val(link.icon);
    $('#saveSocialBtn').html('<i class="bi bi-save2 me-1"></i>Update Social');
}

function deleteSocialLink(id) {
    if (!confirm('Delete this social link?')) return;
    siteSettings.socialLinks = siteSettings.socialLinks.filter(link => link.id !== id);
    saveSiteSettings();
    renderSocialLinksTable();
    resetSocialForm();
    showNotification('Social link deleted!', 'success');
}

function resetSocialForm() {
    $('#socialId').val('');
    $('#socialLabel').val('');
    $('#socialUrl').val('');
    $('#socialIcon').val('');
    $('#saveSocialBtn').html('<i class="bi bi-plus-circle me-1"></i>Add Social');
}

function resolveAdminImagePath(path) {
    if (!path) return '';
    if (path.startsWith('http')) return path;
    const cleaned = path.replace(/^\.\//, '');
    return `../../${cleaned}`;
}

function renderSliderTable() {
    const tbody = $('#sliderTable tbody');
    if (tbody.length === 0) return;
    tbody.empty();

    if (!siteSettings.sliderImages || siteSettings.sliderImages.length === 0) {
        tbody.append('<tr><td colspan="4" class="text-center py-4 text-secondary">No slides added</td></tr>');
        return;
    }

    siteSettings.sliderImages.forEach(item => {
        const preview = resolveAdminImagePath(item.image);
        tbody.append(`
            <tr class="border-bottom border-secondary">
                <td class="ps-4 py-3"><img src="${preview}" alt="${item.alt || 'Slide'}" style="width: 90px; height: 50px; object-fit: cover; border-radius: 6px;" onerror="this.src='assests/images/products/placeholder.webp'"></td>
                <td class="py-3 text-light fw-semibold">${item.heading || 'Untitled'}</td>
                <td class="py-3 text-secondary small">${item.buttonText || 'CTA'} → ${item.buttonLink || '#'}</td>
                <td class="pe-4 py-3">
                    <button class="btn btn-sm btn-outline-orange me-1" onclick="editSlider(${item.id})"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteSlider(${item.id})"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
        `);
    });
}

function editSlider(id) {
    const item = siteSettings.sliderImages.find(slide => slide.id === id);
    if (!item) return;
    $('#sliderId').val(item.id);
    $('#sliderImage').val(item.image);
    $('#sliderAlt').val(item.alt || '');
    $('#sliderLabel').val(item.label || '');
    $('#sliderHeading').val(item.heading || '');
    $('#sliderText').val(item.text || '');
    $('#sliderButtonText').val(item.buttonText || '');
    $('#sliderButtonLink').val(item.buttonLink || '');
    $('#saveSliderBtn').html('<i class="bi bi-save2 me-1"></i>Update Slide');
}

function deleteSlider(id) {
    if (!confirm('Delete this slide?')) return;
    siteSettings.sliderImages = siteSettings.sliderImages.filter(slide => slide.id !== id);
    saveSiteSettings();
    renderSliderTable();
    resetSliderForm();
    showNotification('Slide deleted!', 'success');
}

function resetSliderForm() {
    $('#sliderId').val('');
    $('#sliderImage').val('');
    $('#sliderAlt').val('');
    $('#sliderLabel').val('');
    $('#sliderHeading').val('');
    $('#sliderText').val('');
    $('#sliderButtonText').val('');
    $('#sliderButtonLink').val('');
    $('#saveSliderBtn').html('<i class="bi bi-plus-circle me-1"></i>Add Slide');
}

function getSuppressedEmails() {
    const list = readJsonStorage(EMAIL_SUPPRESSED_KEY, []);
    if (!Array.isArray(list)) return new Set();
    return new Set(list.map(email => normalizeEmailValue(email)).filter(Boolean));
}

function saveSuppressedEmails(set) {
    localStorage.setItem(EMAIL_SUPPRESSED_KEY, JSON.stringify(Array.from(set)));
}