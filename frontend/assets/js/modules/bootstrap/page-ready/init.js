window.commerzaOnReady(function () {
  upBtn = $("#backToTop");
  reviewsCsrfToken = (
    $("#productReviewForm input[name='csrf_token']").val() ||
    window.CommerzaCsrfToken ||
    ""
  ).toString().trim();

  applySiteSettings();
  ensureLegalNavLinks();
  ensureDesktopMegaDropdown();

  const carouselElement = document.getElementById("carouselExampleIndicators");
  const playPauseBtn = document.getElementById("carouselPlayPause");

  if (
    carouselElement &&
    playPauseBtn &&
    window.bootstrap &&
    typeof window.bootstrap.Carousel === "function"
  ) {
    const carousel = new window.bootstrap.Carousel(carouselElement, {
      interval: 3500,
      pause: false,
    });

    let isPlaying = true;

    const applyCarouselPlayState = () => {
      if (isPlaying) {
        carousel.cycle();
        playPauseBtn.innerHTML = '<i class="bi bi-pause-fill"></i>';
      } else {
        carousel.pause();
        playPauseBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
      }
    };

    const keepCarouselPausedAfterManualMove = () => {
      if (!isPlaying) {
        window.setTimeout(() => {
          carousel.pause();
        }, 0);
      }
    };

    carouselElement.addEventListener("slide.bs.carousel", keepCarouselPausedAfterManualMove);
    carouselElement.addEventListener("slid.bs.carousel", keepCarouselPausedAfterManualMove);

    carouselElement.querySelectorAll("[data-bs-slide], [data-bs-slide-to]").forEach((control) => {
      control.addEventListener("click", keepCarouselPausedAfterManualMove);
    });

    playPauseBtn.addEventListener("click", function () {
      isPlaying = !isPlaying;
      applyCarouselPlayState();
    });

    applyCarouselPlayState();
  }

  initAccountPage();

  initCartState().finally(() => {
    updateCartBadge();
    if ($("#cart-items-container").length > 0) {
      displayCartItems();
    }
  });

  initWishlistState().finally(() => {
    updateWishlistBadge();
    updateWishlistButtons();
    renderWishlistPage();
  });
  renderComparePage();

  initProductDetailPage();
  initNewsletterModal();
  initNewsletterInlineForm();
  initOrderTrackingPage();
  initProductFilters();

  $(window).on("scroll", function () {
    if (!upBtn) return;
    if ($(this).scrollTop() > 300) {
      upBtn.addClass("show");
    } else {
      upBtn.removeClass("show");
    }
  });

  if (upBtn && upBtn.length) {
    upBtn.on("click", function (e) {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }

  $("#completeCheckoutBtn").on("click", function () {
    if (window.CommerzaUseServerCheckout) {
      return;
    }

    showNotif(
      "Server checkout is required for order placement in this build.",
      "warning",
    );
  });
});
