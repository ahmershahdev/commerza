$(function () {
  const compareStorageKey = "commerza_compare";

  const readCompareStorage = () => {
    try {
      const parsed = JSON.parse(
        sessionStorage.getItem(compareStorageKey) || "[]",
      );
      return Array.isArray(parsed) ? parsed : [];
    } catch (_error) {
      return [];
    }
  };

  const writeCompareStorage = (items) => {
    const safeItems = Array.isArray(items) ? items : [];
    sessionStorage.setItem(compareStorageKey, JSON.stringify(safeItems));
  };

  const toIds = (items) =>
    [
      ...new Set(
        items
          .map((item) => parseInt(item?.id, 10))
          .filter((value) => Number.isInteger(value) && value > 0),
      ),
    ].slice(0, 4);

  const clearForm = $(
    'form input[name="action"][value="clear_compare"]',
  ).closest("form");
  clearForm.on("submit", function () {
    writeCompareStorage([]);
  });

  $('form input[name="action"][value="remove_compare"]')
    .closest("form")
    .on("submit", function () {
      const productId = parseInt(
        $(this).find('input[name="product_id"]').val(),
        10,
      );
      if (!Number.isInteger(productId) || productId <= 0) {
        return;
      }

      const next = readCompareStorage().filter(
        (item) => parseInt(item?.id, 10) !== productId,
      );
      writeCompareStorage(next);
    });

  const sessionIdsRaw = (
    ($("#compareSessionData").data("sessionIds") || "") + ""
  ).trim();
  const sessionIds = sessionIdsRaw
    .split(",")
    .map((value) => parseInt(value, 10))
    .filter((value) => Number.isInteger(value) && value > 0)
    .slice(0, 4);

  const localCompare = readCompareStorage();
  const localIds = toIds(localCompare);
  const localIdsSignature = localIds.join(",");
  const sessionIdsSignature = sessionIds.join(",");

  if (sessionIdsSignature === "" && localIdsSignature !== "") {
    $("#compareIdsInput").val(localIdsSignature);
    $("#compareSyncForm").trigger("submit");
    return;
  }

  if (localIdsSignature !== "" && localIdsSignature !== sessionIdsSignature) {
    $("#compareIdsInput").val(localIdsSignature);
    $("#compareSyncForm").trigger("submit");
    return;
  }

  if (localIdsSignature === "" && sessionIdsSignature !== "") {
    const localMap = new Map();
    localCompare.forEach((item) => {
      const id = parseInt(item?.id, 10);
      if (Number.isInteger(id) && id > 0 && !localMap.has(id)) {
        localMap.set(id, item);
      }
    });

    const merged = sessionIds.map((id) => localMap.get(id) || { id });
    writeCompareStorage(merged);
  }
});
