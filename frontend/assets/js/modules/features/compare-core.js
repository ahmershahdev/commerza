function getCompare() {
  try {
    return JSON.parse(sessionStorage.getItem(COMPARE_KEY)) || [];
  } catch (error) {
    return [];
  }
}

function saveCompare(items) {
  sessionStorage.setItem(COMPARE_KEY, JSON.stringify(items));
}

function isInCompare(id, name) {
  const list = getCompare();
  return list.some(
    (item) => String(item.id) === String(id) || item.name === name,
  );
}

function toggleCompare(item) {
  const list = getCompare();
  const existsIndex = list.findIndex(
    (entry) => String(entry.id) === String(item.id) || entry.name === item.name,
  );
  if (existsIndex > -1) {
    list.splice(existsIndex, 1);
    saveCompare(list);
    showNotif("Removed from compare.", "warning");
    return false;
  }
  if (list.length >= 4) {
    showNotif("Compare limit is 4 products.", "warning");
    return false;
  }
  list.push(item);
  saveCompare(list);
  showNotif("Added to compare!", "success");
  return true;
}

function updateCompareButtons() {
  $(".compare-btn").each(function () {
    const btn = $(this);
    const id = btn.data("productId");
    const name = btn.data("productName");
    const active = isInCompare(id, name);
    btn.toggleClass("active", active);
    btn
      .find("i")
      .toggleClass("bi-check2-circle", active)
      .toggleClass("bi-sliders", !active);
  });
}
