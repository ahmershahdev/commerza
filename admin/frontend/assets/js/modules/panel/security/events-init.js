function initSecurityEventsSection() {
  if (!$("#securityEventsSection").length) {
    return;
  }

  setAdminDropdownSelection(
    "securitySeverityFilter",
    $("#securitySeverityFilter").val() || "",
    securitySeverityLabel($("#securitySeverityFilter").val() || ""),
  );
  setAdminDropdownSelection(
    "securityActorTypeFilter",
    $("#securityActorTypeFilter").val() || "",
    securityActorTypeLabel($("#securityActorTypeFilter").val() || ""),
  );

  $("#securityEventsApplyBtn")
    .off("click")
    .on("click", function () {
      applySecurityEventsFilters(true);
    });

  $("#securityEventsClearBtn")
    .off("click")
    .on("click", function () {
      clearSecurityEventsFilters();
    });

  $("#securityEventsRefreshBtn")
    .off("click")
    .on("click", function () {
      loadSecurityEvents(false);
    });

  $("#securityEventsPrevBtn")
    .off("click")
    .on("click", function () {
      if (securityEventsState.page <= 1) {
        return;
      }
      securityEventsState.page -= 1;
      loadSecurityEvents(false);
    });

  $("#securityEventsNextBtn")
    .off("click")
    .on("click", function () {
      if (securityEventsState.page >= securityEventsState.totalPages) {
        return;
      }
      securityEventsState.page += 1;
      loadSecurityEvents(false);
    });
}

