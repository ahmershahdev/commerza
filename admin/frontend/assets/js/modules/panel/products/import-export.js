function exportProductsData() {
  if (productsData.length === 0) {
    showNotification("No products to export", "warning");
    return;
  }
  const dataToExport = {
    meta: {
      total: productsData.length,
      currency: "PKR",
      exportedDate: new Date().toISOString().split("T")[0],
      exportedTime: new Date().toLocaleTimeString(),
    },
    sections: allSections.map((section) => ({
      ...section,
      products: productsData.filter((p) => p.sectionId === section.sectionId),
    })),
  };
  const blob = new Blob([JSON.stringify(dataToExport, null, 2)], {
    type: "application/json",
  });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = `products-export-${new Date().toISOString().split("T")[0]}.json`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  showNotification("Products exported!", "success");
}

function exportProductsAsCSV() {
  if (productsData.length === 0) {
    showNotification("No products to export", "warning");
    return;
  }
  const headers = [
    "ID",
    "Name",
    "Product Code",
    "Section",
    "Category",
    "Price",
    "Sale Price",
    "Stock",
    "Movement",
    "Warranty",
    "Dispatch",
    "Description",
  ];
  const rows = productsData.map((p) => [
    p.id,
    `"${p.name}"`,
    `"${p.productCode || ""}"`,
    p.sectionName,
    p.category,
    p.price,
    p.salePrice,
    p.stock,
    p.movement,
    `"${p.warrantyInfo || ""}"`,
    `"${p.dispatchInfo || ""}"`,
    `"${p.description}"`,
  ]);
  const csvContent = [headers.join(","), ...rows.map((r) => r.join(","))].join(
    "\n",
  );
  const blob = new Blob([csvContent], { type: "text/csv" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = `products-export-${new Date().toISOString().split("T")[0]}.csv`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  showNotification("CSV exported!", "success");
}

function downloadSampleProductsCSV() {
  const headers = [
    "section_id",
    "section_name",
    "page",
    "category",
    "subcategory",
    "name",
    "description",
    "image",
    "video",
    "product_code",
    "warranty_info",
    "dispatch_info",
    "price",
    "sale_price",
    "stock",
    "movement",
  ];

  const rows = [
    [
      "featured-collection",
      "Featured Collection",
      "index.php",
      "Premium Watches",
      "Luxury",
      "Aurora Black Steel",
      "Elegant black steel watch with premium finish.",
      "frontend/assets/images/products/featured/aurora-black-steel.webp",
      "",
      "CMRZ-00041",
      "12-month seller warranty",
      "Dispatch in 24-48 hours",
      "12900",
      "11499",
      "17",
      "quartz",
    ],
    [
      "sports-division",
      "Sports & Sales Division",
      "shop-category-b.php",
      "Sports Watches",
      "Performance",
      "Runner Tactical Pro",
      "Durable sports watch built for active daily use.",
      "frontend/assets/images/products/sports/runner-tactical-pro.webp",
      "",
      "CMRZ-00042",
      "18-month seller warranty",
      "Dispatch in 24-48 hours",
      "9900",
      "8500",
      "22",
      "smart",
    ],
  ];

  const csv = [headers, ...rows]
    .map((row) =>
      row
        .map((value) => `"${String(value || "").replace(/"/g, '""')}"`)
        .join(","),
    )
    .join("\n");

  const blob = new Blob([csv], { type: "text/csv" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = "commerza-products-sample.csv";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  showNotification("Sample CSV downloaded.", "success");
}

function parseCsvRecords(csvText) {
  const records = [];
  let row = [];
  let value = "";
  let inQuotes = false;

  for (let i = 0; i < csvText.length; i += 1) {
    const char = csvText[i];
    const next = csvText[i + 1];

    if (char === '"') {
      if (inQuotes && next === '"') {
        value += '"';
        i += 1;
      } else {
        inQuotes = !inQuotes;
      }
      continue;
    }

    if (char === "," && !inQuotes) {
      row.push(value);
      value = "";
      continue;
    }

    if ((char === "\n" || char === "\r") && !inQuotes) {
      if (char === "\r" && next === "\n") {
        i += 1;
      }

      row.push(value);
      if (row.some((cell) => String(cell || "").trim() !== "")) {
        records.push(row);
      }
      row = [];
      value = "";
      continue;
    }

    value += char;
  }

  row.push(value);
  if (row.some((cell) => String(cell || "").trim() !== "")) {
    records.push(row);
  }

  return records;
}

function normalizeImportKey(value) {
  return (value || "")
    .toString()
    .toLowerCase()
    .replace(/[^a-z0-9]/g, "");
}

function parseCsvObjects(csvText) {
  const records = parseCsvRecords(csvText || "");
  if (records.length < 2) {
    return [];
  }

  const headers = records[0].map((header) => normalizeImportKey(header));
  const rows = [];

  for (let i = 1; i < records.length; i += 1) {
    const record = records[i];
    const row = {};
    headers.forEach((header, index) => {
      if (!header) {
        return;
      }

      row[header] = (record[index] || "").toString().trim();
    });

    rows.push(row);
  }

  return rows;
}

function readImportField(row, aliases) {
  for (const alias of aliases) {
    const key = normalizeImportKey(alias);
    const value = (row?.[key] || "").toString().trim();
    if (value !== "") {
      return value;
    }
  }

  return "";
}

function normalizeImportMovement(value) {
  const normalized = (value || "").toString().trim().toLowerCase();
  if (normalized === "auto" || normalized === "automatic") {
    return "auto";
  }
  if (normalized === "smart" || normalized === "digital") {
    return "smart";
  }
  return "quartz";
}

function buildCatalogFromCsvRows(rows) {
  const sectionMap = new Map();
  const usedSectionIds = new Set();
  const products = [];
  let productIdSeed = 1;

  rows.forEach((row) => {
    const productName = readImportField(row, ["name", "product_name"]);
    if (!productName) {
      return;
    }

    const rawSectionName = readImportField(row, ["section_name", "section"]);
    const rawSectionId = readImportField(row, ["section_id", "sectionid"]);
    const sectionName = rawSectionName || "Imported Section";
    const sectionIdBase = slugifySection(
      rawSectionId || sectionName || "imported",
    );
    let sectionId = sectionIdBase || "imported";
    let suffix = 2;

    while (!sectionMap.has(sectionId) && usedSectionIds.has(sectionId)) {
      sectionId = `${sectionIdBase || "imported"}-${suffix++}`;
    }

    if (!sectionMap.has(sectionId)) {
      sectionMap.set(sectionId, {
        sectionId,
        sectionName,
        page: readImportField(row, ["page"]) || "index.php",
        category: readImportField(row, ["category"]) || "Imported Products",
        subcategory: readImportField(row, ["subcategory"]),
      });
      usedSectionIds.add(sectionId);
    }

    const stock = Math.max(
      0,
      parseInt(readImportField(row, ["stock"]), 10) || 0,
    );
    const productId = productIdSeed++;
    const basePrice = Number.parseFloat(readImportField(row, ["price"])) || 0;
    const salePriceRaw = Number.parseFloat(
      readImportField(row, ["sale_price", "saleprice"]),
    );
    const salePrice = Number.isFinite(salePriceRaw) ? salePriceRaw : basePrice;

    products.push({
      id: productId,
      sectionId,
      sectionName: sectionMap.get(sectionId)?.sectionName || sectionName,
      page: sectionMap.get(sectionId)?.page || "index.php",
      category: sectionMap.get(sectionId)?.category || "Imported Products",
      subcategory: sectionMap.get(sectionId)?.subcategory || "",
      name: productName,
      description: readImportField(row, ["description"]) || "Imported product",
      image:
        readImportField(row, ["image", "image_url"]) ||
        "frontend/assets/images/products/placeholder.webp",
      video: readImportField(row, ["video", "video_url"]),
      productCode: normalizeProductCodeInput(
        readImportField(row, ["product_code", "productcode", "code"]),
        productId,
      ),
      warrantyInfo: normalizeProductMetaInput(
        readImportField(row, ["warranty_info", "warranty"]),
        "12-month seller warranty",
        120,
      ),
      dispatchInfo: normalizeProductMetaInput(
        readImportField(row, ["dispatch_info", "dispatch"]),
        stock > 0 ? "Dispatch in 24-48 hours" : "Pre-order availability",
        120,
      ),
      price: Math.max(0, basePrice),
      salePrice: Math.max(0, salePrice),
      stock,
      movement: normalizeImportMovement(readImportField(row, ["movement"])),
      createdAt: new Date().toISOString().slice(0, 10),
    });
  });

  if (!products.length) {
    throw new Error("The uploaded CSV has no valid product rows.");
  }

  return {
    sections: Array.from(sectionMap.values()),
    products,
  };
}

function buildCatalogFromJsonPayload(rawData) {
  const inputSections = Array.isArray(rawData?.sections)
    ? rawData.sections
    : Array.isArray(rawData)
      ? rawData
      : [];

  if (!inputSections.length) {
    throw new Error("JSON must contain a sections array with products.");
  }

  const sectionMap = new Map();
  const usedSectionIds = new Set();
  const products = [];
  let productIdSeed = 1;
  const usedProductIds = new Set();

  inputSections.forEach((section) => {
    const sectionName = (
      section?.sectionName ||
      section?.name ||
      "Imported Section"
    )
      .toString()
      .trim();
    const sectionIdBase = slugifySection(
      (section?.sectionId || section?.id || sectionName || "imported")
        .toString()
        .trim(),
    );

    let sectionId = sectionIdBase || "imported";
    let suffix = 2;
    while (!sectionMap.has(sectionId) && usedSectionIds.has(sectionId)) {
      sectionId = `${sectionIdBase || "imported"}-${suffix++}`;
    }

    sectionMap.set(sectionId, {
      sectionId,
      sectionName: sectionName || "Imported Section",
      page: (section?.page || "index.php").toString().trim() || "index.php",
      category: (section?.category || "Imported Products").toString().trim(),
      subcategory: (section?.subcategory || "").toString().trim(),
    });
    usedSectionIds.add(sectionId);

    const sectionProducts = Array.isArray(section?.products)
      ? section.products
      : [];

    sectionProducts.forEach((product) => {
      const name = (product?.name || "").toString().trim();
      if (!name) {
        return;
      }

      const providedId = parseInt(product?.id, 10);
      let productId =
        Number.isInteger(providedId) && providedId > 0
          ? providedId
          : productIdSeed;

      while (usedProductIds.has(productId)) {
        productId += 1;
      }

      usedProductIds.add(productId);
      productIdSeed = Math.max(productIdSeed + 1, productId + 1);

      const stock = Math.max(0, parseInt(product?.stock, 10) || 0);
      const rawPrice = Number.parseFloat(product?.price);
      const basePrice = Number.isFinite(rawPrice) ? Math.max(0, rawPrice) : 0;
      const rawSale = Number.parseFloat(product?.salePrice);
      const salePrice = Number.isFinite(rawSale)
        ? Math.max(0, rawSale)
        : basePrice;

      products.push({
        id: productId,
        sectionId,
        sectionName:
          sectionMap.get(sectionId)?.sectionName || "Imported Section",
        page: sectionMap.get(sectionId)?.page || "index.php",
        category: sectionMap.get(sectionId)?.category || "Imported Products",
        subcategory: sectionMap.get(sectionId)?.subcategory || "",
        name,
        description: (product?.description || "Imported product").toString(),
        image:
          (product?.image || "").toString().trim() ||
          "frontend/assets/images/products/placeholder.webp",
        video: (product?.video || "").toString().trim(),
        productCode: normalizeProductCodeInput(
          (product?.productCode || product?.product_code || "").toString(),
          productId,
        ),
        warrantyInfo: normalizeProductMetaInput(
          (product?.warrantyInfo || product?.warranty_info || "").toString(),
          "12-month seller warranty",
          120,
        ),
        dispatchInfo: normalizeProductMetaInput(
          (product?.dispatchInfo || product?.dispatch_info || "").toString(),
          stock > 0 ? "Dispatch in 24-48 hours" : "Pre-order availability",
          120,
        ),
        price: basePrice,
        salePrice,
        stock,
        movement: normalizeImportMovement(product?.movement || "quartz"),
        createdAt: new Date().toISOString().slice(0, 10),
      });
    });
  });

  if (!products.length) {
    throw new Error("JSON import has no valid products.");
  }

  return {
    sections: Array.from(sectionMap.values()),
    products,
  };
}

async function applyImportedCatalogData(catalog, onProgress = null) {
  const importedSections = Array.isArray(catalog?.sections)
    ? catalog.sections
    : [];
  const importedProducts = Array.isArray(catalog?.products)
    ? catalog.products
    : [];

  if (!importedSections.length || !importedProducts.length) {
    throw new Error("Imported file has no usable sections/products.");
  }

  const totalSteps = importedSections.length + importedProducts.length;
  let importProgressStep = 0;
  const reportProgress = (label) => {
    if (typeof onProgress !== "function") {
      return;
    }

    importProgressStep += 1;
    onProgress({
      step: importProgressStep,
      total: Math.max(totalSteps, 1),
      label: (label || "Processing").toString(),
    });
  };

  allSections = [];
  const sectionMap = new Map();
  importedSections.forEach((section, index) => {
    const normalized = {
      sectionName: (section?.sectionName || `Section ${index + 1}`).toString(),
      sectionId: (section?.sectionId || `section-${index + 1}`).toString(),
      page: (section?.page || "index.php").toString(),
      category: (section?.category || "Uncategorized").toString(),
      subcategory: (section?.subcategory || "General").toString(),
      products: [],
    };

    allSections.push(normalized);
    sectionMap.set(normalized.sectionId, normalized);
    reportProgress(`Section: ${normalized.sectionName}`);
  });

  productsData = [];
  const usedProductIds = new Set();
  let productIdSeed = 1;

  for (let index = 0; index < importedProducts.length; index += 1) {
    const product = importedProducts[index] || {};
    const preferredSectionId = (product?.sectionId || "").toString();
    const section =
      sectionMap.get(preferredSectionId) ||
      allSections.find((entry) => entry.sectionId === preferredSectionId) ||
      allSections[0];

    let productId = parseInt(product?.id, 10);
    if (!Number.isInteger(productId) || productId <= 0) {
      productId = productIdSeed;
    }

    while (usedProductIds.has(productId)) {
      productId += 1;
    }

    usedProductIds.add(productId);
    productIdSeed = Math.max(productIdSeed + 1, productId + 1);

    productsData.push({
      ...product,
      id: productId,
      sectionId: section?.sectionId || preferredSectionId,
      sectionName: section?.sectionName || product?.sectionName || "Section",
      page: section?.page || product?.page || "index.php",
      category: section?.category || product?.category || "Uncategorized",
      subcategory: section?.subcategory || product?.subcategory || "General",
    });

    reportProgress(
      `Product: ${(product?.name || `Item ${index + 1}`).toString()}`,
    );

    if (index % 20 === 0) {
      // Yield so the UI can refresh progress text for large imports.
      // eslint-disable-next-line no-await-in-loop
      await new Promise((resolve) => setTimeout(resolve, 0));
    }
  }

  nextId = productsData.length
    ? Math.max(...productsData.map((p) => parseInt(p?.id, 10) || 0)) + 1
    : 1;
  nextSectionId = allSections.length + 1;
  window.currentSectionFilter = "";

  renderSectionDropdowns();
  renderSectionsTable();
  renderProductsTable();
  calculateDashboardMetrics();
  updateNotifications();
}

async function importProductsFromFileInput() {
  const input = document.getElementById("bulkProductsFile");
  if (!input || !input.files || input.files.length === 0) {
    showNotification("Please choose a CSV or JSON file first.", "warning");
    return;
  }

  const file = input.files[0];
  const confirmed = await showCustomConfirmDialog(
    "Import will replace current product sections and products. Continue?",
    "Confirm Bulk Import",
  );
  if (!confirmed) {
    return;
  }

  const importBtn = $("#bulkProductsImportBtn");
  const progressEl = $("#bulkImportProgress");
  const originalBtnHtml = importBtn.html();
  const setImportProgress = (message, toneClass = "text-secondary") => {
    if (!progressEl.length) {
      return;
    }
    progressEl
      .removeClass(
        "text-secondary text-warning text-success text-danger text-info",
      )
      .addClass(toneClass)
      .text((message || "").toString());
  };

  importBtn
    .prop("disabled", true)
    .html(
      '<span class="spinner-border spinner-border-sm"></span> Importing...',
    );
  setImportProgress("Reading import file...", "text-warning");

  try {
    const text = await new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve((reader.result || "").toString());
      reader.onerror = () => reject(new Error("Unable to read uploaded file."));
      reader.readAsText(file);
    });

    const fileName = (file?.name || "").toLowerCase();
    const isJson = fileName.endsWith(".json") || file.type.includes("json");
    let catalog = null;

    if (isJson) {
      setImportProgress("Parsing JSON payload...", "text-info");
      const parsed = JSON.parse(text || "{}");
      catalog = buildCatalogFromJsonPayload(parsed);
    } else {
      setImportProgress("Parsing CSV rows one-by-one...", "text-info");
      const rows = parseCsvObjects(text || "");
      catalog = buildCatalogFromCsvRows(rows);
    }

    await applyImportedCatalogData(catalog, ({ step, total, label }) => {
      setImportProgress(`Processing ${step}/${total} | ${label}`, "text-info");
    });

    setImportProgress("Syncing imported catalog to server...", "text-warning");
    const syncResult = await saveProductsToJSON();
    if (!syncResult?.ok) {
      throw new Error("Imported rows were prepared but server sync failed.");
    }

    input.value = "";
    const importedCount = Array.isArray(catalog?.products)
      ? catalog.products.length
      : 0;
    setImportProgress(
      `Import complete. ${importedCount} product(s) processed one-by-one.`,
      "text-success",
    );
    showNotification("Bulk upload completed successfully.", "success");
  } catch (error) {
    setImportProgress(
      error?.message || "Import failed. Check your file and retry.",
      "text-danger",
    );
    showNotification(
      error?.message || "Unable to import products file.",
      "danger",
    );
  } finally {
    importBtn.prop("disabled", false).html(originalBtnHtml);
  }
}

