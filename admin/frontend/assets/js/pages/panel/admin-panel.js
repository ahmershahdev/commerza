(function () {
  const runtimeDataNode = document.getElementById("commerzaAdminRuntimeData");
  if (!runtimeDataNode) {
    return;
  }

  let runtime = {};
  try {
    runtime = JSON.parse(runtimeDataNode.textContent || "{}");
  } catch (_error) {
    runtime = {};
  }

  window.CommerzaAdminRuntime = runtime;
})();
