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
      render: (item) =>
        `<img src="${item.image}" alt="${item.name}" style="width: 120px; height: 120px; object-fit: cover; border-radius: 10px;" />`,
    },
    {
      label: "Name",
      key: "name",
      render: (item) => `<div class="text-white fw-bold">${item.name}</div>`,
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
      render: (item) => (item.movement ? item.movement.toString() : "Quartz"),
    },
    {
      label: "Stock",
      key: "stock",
      render: (item) => (item.stock != null ? item.stock : "N/A"),
    },
    {
      label: "Action",
      key: "action",
      render: (item) =>
        `<button class="btn product-btn-buy compare-remove-btn" data-product-id="${item.id}" data-product-name="${item.name}">Remove</button>`,
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
