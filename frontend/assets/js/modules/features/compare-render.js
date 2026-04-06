function compareEscapeHtml(value) {
  return (value || "")
    .toString()
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/\"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function compareSanitizeAssetUrl(value) {
  const raw = (value || "").toString().trim();
  if (!raw) {
    return "";
  }

  if (!/^(https?:\/\/|\/|frontend\/assets\/)/i.test(raw)) {
    return "";
  }

  return raw.replace(/[\u0000-\u001F\u007F]/g, "");
}

function renderComparePage() {
  const container = $("#compare-container");
  if (!container.length) return;

  const list = getCompare();
  if (list.length === 0) {
    container.html(`
            <div class="text-center py-5">
                <i class="bi bi-sliders" style="font-size: 3rem; color: #ff6600;"></i>
                <h3 class="text-white mt-3">No products to compare</h3>
                <p class="text-secondary">Add items from a product page to compare.</p>
                <a href="index.php" class="btn product-btn-buy mt-3">Browse Products</a>
            </div>
        `);
    return;
  }

  const rows = [
    {
      label: "Image",
      key: "image",
      render: (item) => {
        const resolvedImage =
          compareSanitizeAssetUrl(item.image) ||
          "frontend/assets/images/logo/commerza-logo.webp";
        const safeImage = compareEscapeHtml(resolvedImage);
        const safeName = compareEscapeHtml(item.name || "Product image");
        return `<img src="${safeImage}" alt="${safeName}" style="width: 120px; height: 120px; object-fit: contain; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.03); padding: 4px;" />`;
      },
    },
    {
      label: "Name",
      key: "name",
      render: (item) =>
        `<div class="text-white fw-bold">${compareEscapeHtml(item.name || "")}</div>`,
    },
    {
      label: "Price",
      key: "price",
      render: (item) =>
        `${item.price != null ? parseInt(item.price).toLocaleString() : "N/A"} PKR`,
    },
    {
      label: "Sale Price",
      key: "salePrice",
      render: (item) =>
        `${item.salePrice != null ? parseInt(item.salePrice).toLocaleString() : "N/A"} PKR`,
    },
    {
      label: "Movement",
      key: "movement",
      render: (item) =>
        compareEscapeHtml(item.movement ? item.movement.toString() : "Quartz"),
    },
    {
      label: "Stock",
      key: "stock",
      render: (item) => (item.stock != null ? item.stock : "N/A"),
    },
    {
      label: "Action",
      key: "action",
      render: (item) => {
        const numericProductId = Number.parseInt(item.id, 10);
        const safeProductId = Number.isInteger(numericProductId)
          ? String(numericProductId)
          : "";
        const safeName = compareEscapeHtml(item.name || "");
        return `<button class="btn product-btn-buy compare-remove-btn" data-product-id="${safeProductId}" data-product-name="${safeName}">Remove</button>`;
      },
    },
  ];

  let table =
    '<div class="table-responsive"><table class="table table-dark table-bordered align-middle">';
  table += "<tbody>";
  rows.forEach((row) => {
    table += `<tr><th scope="row" style="min-width: 140px;">${row.label}</th>`;
    list.forEach((item) => {
      table += `<td>${row.render(item)}</td>`;
    });
    table += "</tr>";
  });
  table += "</tbody></table></div>";
  container.html(table);
}
