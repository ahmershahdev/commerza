// Reviews and live-viewer functions.

function clearLiveViewerPolling() {
  if (liveViewerIntervalId) {
    clearInterval(liveViewerIntervalId);
    liveViewerIntervalId = null;
  }
}

function updateLiveViewerBadge(count) {
  const label = $(".live-viewers [data-live-viewers-text]").first();
  if (!label.length) {
    return;
  }

  const normalized =
    Number.isFinite(count) && count >= 0 ? Math.round(count) : 0;
  label.text(`${normalized} people viewing now`);
}

async function sendLiveViewerHeartbeat(productId) {
  const csrfToken = (window.CommerzaCsrfToken || reviewsCsrfToken || "")
    .toString()
    .trim();

  if (!csrfToken) {
    return;
  }

  const body = new URLSearchParams({
    action: "heartbeat",
    product_id: String(productId),
    csrf_token: csrfToken,
  });

  const response = await fetch("backend/api/viewers_api.php", {
    method: "POST",
    credentials: "same-origin",
    cache: "no-store",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
    },
    body: body.toString(),
  });

  if (!response.ok) {
    throw new Error("Unable to send live viewer heartbeat.");
  }
}

async function fetchLiveViewerCount(productId) {
  const params = new URLSearchParams({
    action: "count",
    product_id: String(productId),
  });

  const response = await fetch(
    `backend/api/viewers_api.php?${params.toString()}`,
    {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store",
    },
  );

  const payload = await response.json();
  if (!response.ok || !payload?.ok) {
    throw new Error(payload?.message || "Unable to fetch live viewers.");
  }

  const safeWindowSeconds = Number(payload?.window_seconds);

  return {
    count: Number(payload?.display_count) || 0,
    windowSeconds:
      Number.isFinite(safeWindowSeconds) && safeWindowSeconds >= 30
        ? safeWindowSeconds
        : 45,
  };
}

function startLiveViewerPolling(productId) {
  clearLiveViewerPolling();

  const normalizedProductId = parseInt(productId, 10);
  if (!Number.isInteger(normalizedProductId) || normalizedProductId <= 0) {
    updateLiveViewerBadge(0);
    return;
  }

  const syncCount = async (heartbeat) => {
    try {
      if (heartbeat) {
        await sendLiveViewerHeartbeat(normalizedProductId);
      }

      const result = await fetchLiveViewerCount(normalizedProductId);
      updateLiveViewerBadge(result.count);

      if (!liveViewerIntervalId) {
        const intervalMs = Math.max(
          30000,
          Math.min(60000, Math.floor((result.windowSeconds * 1000) / 3)),
        );
        liveViewerIntervalId = setInterval(() => {
          syncCount(true);
        }, intervalMs);
      }
    } catch (error) {
      updateLiveViewerBadge(0);
    }
  };

  syncCount(true);
}

$(window).on("beforeunload", clearLiveViewerPolling);

function formatReviewDate(value) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "";
  }
  return date.toLocaleDateString();
}

function renderReviewImagesMarkup(images) {
  const list = Array.isArray(images) ? images : [];
  if (!list.length) {
    return "";
  }

  const html = list
    .slice(0, 2)
    .map((image) => {
      const path = sanitizeClientAssetUrl((image?.path || "").toString());
      if (!path) {
        return "";
      }

      return `
          <a href="${encodeURI(path)}" target="_blank" rel="noopener" class="d-inline-block me-2 mb-2">
            <img src="${encodeURI(path)}" alt="Review image" style="width: 68px; height: 68px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255, 102, 0, 0.4);">
          </a>
        `;
    })
    .join("");

  return html ? `<div class="mt-2">${html}</div>` : "";
}

const REVIEW_MAX_UPLOAD_FILES = 2;
const REVIEW_MAX_UPLOAD_BYTES = 6 * 1024 * 1024;
const REVIEW_WEBP_TARGET_KB = 260;
const REVIEW_WEBP_MAX_DIMENSION = 1800;
const REVIEW_WEBP_QUALITY_STEPS = [
  0.86, 0.8, 0.74, 0.68, 0.62, 0.56, 0.5, 0.44,
];

let reviewSelectedFiles = [];

function reviewFormatSizeKb(bytes) {
  const numeric = Number(bytes);
  if (!Number.isFinite(numeric) || numeric <= 0) {
    return "0 KB";
  }

  return `${Math.max(1, Math.round(numeric / 1024))} KB`;
}

function reviewBaseName(fileName) {
  const stem = (fileName || "review-image")
    .toString()
    .replace(/\.[^.]+$/, "")
    .replace(/[^a-z0-9-_]+/gi, "-")
    .replace(/^-+|-+$/g, "");

  return stem || "review-image";
}

function reviewCanvasToBlob(canvas, type, quality) {
  return new Promise((resolve) => {
    if (!canvas || typeof canvas.toBlob !== "function") {
      resolve(null);
      return;
    }

    canvas.toBlob(
      (blob) => {
        resolve(blob || null);
      },
      type,
      quality,
    );
  });
}

function reviewLoadImageFromFile(file) {
  return new Promise((resolve, reject) => {
    if (!(file instanceof File)) {
      reject(new Error("Invalid review image file."));
      return;
    }

    const objectUrl = URL.createObjectURL(file);
    const image = new Image();
    image.decoding = "async";
    image.onload = () => {
      URL.revokeObjectURL(objectUrl);
      resolve(image);
    };
    image.onerror = () => {
      URL.revokeObjectURL(objectUrl);
      reject(new Error("Unable to parse selected image."));
    };
    image.src = objectUrl;
  });
}

async function reviewCompressFileToWebp(file) {
  const mime = (file?.type || "").toString().toLowerCase();
  const size = Number(file?.size) || 0;

  if (
    !(
      mime === "image/png" ||
      mime === "image/jpeg" ||
      mime === "image/webp" ||
      mime === "image/gif"
    )
  ) {
    throw new Error("Only JPG, PNG, WEBP, and GIF images are allowed.");
  }

  if (size <= 0 || size >= REVIEW_MAX_UPLOAD_BYTES) {
    throw new Error("Each image must be less than 6 MB.");
  }

  const image = await reviewLoadImageFromFile(file);
  const sourceWidth = Number(image.naturalWidth || image.width || 0);
  const sourceHeight = Number(image.naturalHeight || image.height || 0);

  if (!sourceWidth || !sourceHeight) {
    return file;
  }

  const longestSide = Math.max(sourceWidth, sourceHeight);
  const scale =
    longestSide > REVIEW_WEBP_MAX_DIMENSION
      ? REVIEW_WEBP_MAX_DIMENSION / longestSide
      : 1;

  const outputWidth = Math.max(1, Math.round(sourceWidth * scale));
  const outputHeight = Math.max(1, Math.round(sourceHeight * scale));

  const canvas = document.createElement("canvas");
  canvas.width = outputWidth;
  canvas.height = outputHeight;

  const context = canvas.getContext("2d");
  if (!context) {
    return file;
  }

  context.drawImage(image, 0, 0, outputWidth, outputHeight);

  const targetBytes = REVIEW_WEBP_TARGET_KB * 1024;
  let bestBlob = null;

  for (const quality of REVIEW_WEBP_QUALITY_STEPS) {
    const blob = await reviewCanvasToBlob(canvas, "image/webp", quality);
    if (!blob) {
      continue;
    }

    if (!bestBlob || blob.size < bestBlob.size) {
      bestBlob = blob;
    }

    if (blob.size <= targetBytes) {
      bestBlob = blob;
      break;
    }
  }

  if (!bestBlob) {
    return file;
  }

  if (mime === "image/webp" && bestBlob.size >= size) {
    return file;
  }

  return new File([bestBlob], `${reviewBaseName(file.name)}.webp`, {
    type: "image/webp",
    lastModified: Date.now(),
  });
}

function reviewUpdateUploadShell(
  stageText,
  percent,
  tone = "bg-warning",
  shouldShow = true,
) {
  const shell = $("#reviewUploadProgress");
  if (!shell.length) {
    return;
  }

  const safePercent = Math.max(0, Math.min(100, Math.round(percent || 0)));
  const stage = shell.find("[data-upload-stage]").first();
  const bar = shell.find("[data-upload-bar]").first();

  shell.toggleClass("d-none", !shouldShow);
  if (!shouldShow) {
    return;
  }

  if (stage.length) {
    stage.text((stageText || "Optimizing selected images...").toString());
  }

  if (bar.length) {
    bar.removeClass("bg-warning bg-danger bg-success");
    bar.addClass(tone || "bg-warning");
    bar.css("width", `${safePercent}%`);
    bar.text(`${safePercent}%`);
  }
}

function reviewRenderSelectedFiles() {
  const selection = $("#reviewFileSelection");
  if (!selection.length) {
    return;
  }

  if (!reviewSelectedFiles.length) {
    selection.text("No images selected yet.");
    return;
  }

  const markup = reviewSelectedFiles
    .map((file, index) => {
      const safeName = escapeHtml(
        (file?.name || `review-image-${index + 1}.webp`).toString(),
      );
      const sizeLabel = reviewFormatSizeKb(file?.size || 0);

      return `
          <span class="badge rounded-pill text-bg-dark border border-warning-subtle me-2 mb-2">
            <span>${safeName} (${sizeLabel})</span>
            <button type="button" class="btn btn-link btn-sm text-warning p-0 ms-2 review-remove-file-btn" data-file-index="${index}" aria-label="Remove ${safeName}">
              <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
          </span>
        `;
    })
    .join("");

  selection.html(markup);
}

function reviewSyncInputFiles() {
  const input = document.getElementById("reviewImages");
  if (!input) {
    return;
  }

  if (typeof DataTransfer === "undefined") {
    return;
  }

  try {
    const transfer = new DataTransfer();
    reviewSelectedFiles.forEach((file) => {
      transfer.items.add(file);
    });

    input.files = transfer.files;
  } catch (_error) {
    // Some older engines may block FileList mutation; keeping state fallback is safe.
  }
}

function reviewClearSelectedFiles() {
  reviewSelectedFiles = [];

  const input = document.getElementById("reviewImages");
  if (input) {
    input.value = "";
  }

  reviewUpdateUploadShell(
    "Waiting to optimize selected images...",
    0,
    "bg-warning",
    false,
  );
  reviewRenderSelectedFiles();
}

async function reviewHandleFileSelectionChange(event) {
  const input = event?.target;
  const files = input?.files ? Array.from(input.files) : [];

  if (!files.length) {
    reviewClearSelectedFiles();
    return;
  }

  if (files.length > REVIEW_MAX_UPLOAD_FILES) {
    showNotif("You can upload up to 2 images only.", "warning");
  }

  const normalizedFiles = files.slice(0, REVIEW_MAX_UPLOAD_FILES);
  reviewUpdateUploadShell("Preparing selected images...", 5, "bg-warning");
  $("#reviewFileSelection").text("Optimizing selected images...");

  const compressedFiles = [];
  let originalBytes = 0;
  const totalFiles = normalizedFiles.length;

  for (let index = 0; index < totalFiles; index += 1) {
    const file = normalizedFiles[index];
    originalBytes += Number(file?.size) || 0;
    const beforeCompress = 20 + Math.round((index / totalFiles) * 55);
    reviewUpdateUploadShell(
      `Parsing and compressing image ${index + 1} of ${totalFiles}...`,
      beforeCompress,
      "bg-warning",
    );

    const compressed = await reviewCompressFileToWebp(file);
    compressedFiles.push(compressed instanceof File ? compressed : file);

    const afterCompress = 20 + Math.round(((index + 1) / totalFiles) * 60);
    reviewUpdateUploadShell(
      `Optimized image ${index + 1} of ${totalFiles}.`,
      afterCompress,
      "bg-warning",
    );
  }

  reviewSelectedFiles = compressedFiles;
  reviewSyncInputFiles();
  reviewRenderSelectedFiles();
  reviewUpdateUploadShell(
    "Image optimization complete. Ready to submit review.",
    100,
    "bg-success",
  );

  const optimizedBytes = reviewSelectedFiles.reduce(
    (total, file) => total + (Number(file?.size) || 0),
    0,
  );

  if (originalBytes > 0) {
    const savingsRatio = Math.max(0, 1 - optimizedBytes / originalBytes);
    const savingsPercent = Math.round(savingsRatio * 100);
    const optimizedLabel = reviewFormatSizeKb(optimizedBytes);

    if (savingsPercent > 0) {
      showNotif(
        `Images converted to WebP (${savingsPercent}% smaller, ${optimizedLabel} total).`,
        "success",
      );
    } else {
      showNotif(
        `Images converted to WebP (${optimizedLabel} total).`,
        "success",
      );
    }
  }
}

function reviewInitializeUploader() {
  const input = document.getElementById("reviewImages");
  if (!input) {
    return;
  }

  if (input.getAttribute("data-review-uploader-ready") !== "1") {
    input.addEventListener("change", (event) => {
      reviewHandleFileSelectionChange(event).catch((error) => {
        showNotif(
          error?.message || "Unable to optimize selected images.",
          "warning",
        );
        reviewClearSelectedFiles();
      });
    });

    input.setAttribute("data-review-uploader-ready", "1");
  }

  $(document)
    .off("click.reviewRemoveSelectedImage")
    .on(
      "click.reviewRemoveSelectedImage",
      "#reviewFileSelection .review-remove-file-btn",
      function (event) {
        event.preventDefault();

        const index = parseInt($(this).attr("data-file-index"), 10);
        if (
          !Number.isInteger(index) ||
          index < 0 ||
          index >= reviewSelectedFiles.length
        ) {
          return;
        }

        reviewSelectedFiles.splice(index, 1);
        reviewSyncInputFiles();
        reviewRenderSelectedFiles();
      },
    );

  reviewRenderSelectedFiles();
}

const REVIEW_RATING_LABELS = {
  1: "1 star - Poor",
  2: "2 stars - Fair",
  3: "3 stars - Good",
  4: "4 stars - Very good",
  5: "5 stars - Excellent",
};

function normalizeReviewRatingSelection(value) {
  const parsed = parseInt(value, 10);
  if (Number.isInteger(parsed) && parsed >= 1 && parsed <= 5) {
    return parsed;
  }

  return 0;
}

function reviewRatingLabel(value) {
  const rating = normalizeReviewRatingSelection(value);
  if (rating === 0) {
    return "Select a rating to continue";
  }

  return REVIEW_RATING_LABELS[rating] || REVIEW_RATING_LABELS[5];
}

function setReviewRatingSelection(value) {
  const rating = normalizeReviewRatingSelection(value);
  const ratingInput = $("#reviewRating");
  const starsContainer = $("#reviewStarsInput");

  ratingInput.val(rating > 0 ? String(rating) : "");

  if (starsContainer.length) {
    starsContainer.find(".review-star-btn").each(function () {
      const star = $(this);
      const starRating = parseInt(star.data("rating"), 10) || 0;
      const isActive = rating > 0 && starRating <= rating;
      star.toggleClass("active", isActive);
      star.attr("aria-pressed", isActive ? "true" : "false");
    });

    starsContainer.attr("data-rating", rating > 0 ? String(rating) : "0");
  }

  const label = $("#reviewRatingLabel");
  if (label.length) {
    label.text(reviewRatingLabel(rating));
  }

  return rating;
}

function getReviewRatingSelection() {
  const inputRating = normalizeReviewRatingSelection($("#reviewRating").val());
  if (inputRating > 0) {
    return inputRating;
  }

  const lastActive = $("#reviewStarsInput .review-star-btn.active").last();
  const fallbackRating = normalizeReviewRatingSelection(
    lastActive.data("rating"),
  );
  if (fallbackRating > 0) {
    return fallbackRating;
  }

  return 0;
}

function setupReviewStarInput(isReadOnly = false) {
  const starsContainer = $("#reviewStarsInput");
  if (!starsContainer.length) {
    return;
  }

  starsContainer
    .find(".review-star-btn")
    .prop("disabled", !!isReadOnly)
    .off("click")
    .off("keydown")
    .on("click", function (event) {
      event.preventDefault();
      if (isReadOnly) {
        return;
      }

      const rating = normalizeReviewRatingSelection($(this).data("rating"));
      setReviewRatingSelection(rating);
    })
    .on("keydown", function (event) {
      if (isReadOnly) {
        return;
      }

      const current = normalizeReviewRatingSelection($(this).data("rating"));
      if (event.key === "ArrowRight" || event.key === "ArrowUp") {
        event.preventDefault();
        const next = Math.min(5, current + 1);
        setReviewRatingSelection(next);
        starsContainer
          .find(`.review-star-btn[data-rating="${next}"]`)
          .trigger("focus");
        return;
      }

      if (event.key === "ArrowLeft" || event.key === "ArrowDown") {
        event.preventDefault();
        const prev = Math.max(1, current - 1);
        setReviewRatingSelection(prev);
        starsContainer
          .find(`.review-star-btn[data-rating="${prev}"]`)
          .trigger("focus");
        return;
      }

      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        setReviewRatingSelection(current);
      }
    });

  setReviewRatingSelection($("#reviewRating").val());
}

// Fallback delegated binding so stars remain selectable even if a late render
// occurs before direct bindings are applied.
$(document)
  .off("click.reviewStarsFallback")
  .on(
    "click.reviewStarsFallback",
    "#reviewStarsInput .review-star-btn",
    function (event) {
      event.preventDefault();
      const rating = normalizeReviewRatingSelection($(this).data("rating"));
      if (rating > 0) {
        setReviewRatingSelection(rating);
      }
    },
  );

function renderReviewFormState(eligibility, productId) {
  const form = $("#productReviewForm");
  const message = $("#reviewEligibilityMessage");
  const ratingInput = $("#reviewRating");
  const textInput = $("#reviewText");
  const imageInput = $("#reviewImages");
  const removeExistingWrap = $("#reviewRemoveExistingWrap");
  const removeExistingInput = $("#reviewRemoveExistingImages");
  const submitBtn = $("#reviewSubmitBtn");
  const lockNotice = $("#reviewLockNotice");
  const hiddenProductInput = $("#reviewProductId");

  if (!form.length) {
    return;
  }

  hiddenProductInput.val(String(productId || ""));

  const existingReview = eligibility?.existing_review || null;
  const existingImages = Array.isArray(existingReview?.images)
    ? existingReview.images
    : [];
  const isLocked = !!existingReview?.is_locked;
  const canReview =
    !isLocked && (!!eligibility?.can_review || !!existingReview);
  const statusMessage = (eligibility?.message || "").toString().trim();

  reviewInitializeUploader();

  if (existingReview) {
    setReviewRatingSelection(existingReview.rating || 5);
    if (textInput.length && !textInput.val()) {
      textInput.val((existingReview.text || "").toString());
    }
  } else if (!(ratingInput.val() || "").toString().trim()) {
    setReviewRatingSelection(0);
  }

  ratingInput.prop("disabled", !canReview);
  textInput.prop("disabled", !canReview);
  imageInput.prop("disabled", !canReview);
  removeExistingInput.prop("checked", false);
  removeExistingInput.prop("disabled", !(canReview && existingImages.length));
  removeExistingWrap.toggleClass(
    "d-none",
    !(canReview && existingImages.length),
  );
  submitBtn.data("reviewLocked", isLocked ? 1 : 0);
  submitBtn.prop("disabled", !canReview);
  $("#reviewStarsInput").toggleClass("is-readonly", !canReview);
  setupReviewStarInput(!canReview);

  if (!canReview) {
    reviewClearSelectedFiles();
  }

  if (lockNotice.length) {
    lockNotice.toggleClass("d-none", !isLocked);
    if (isLocked) {
      lockNotice.text("This review is locked by admin and cannot be edited.");
    }
  }

  submitBtn.text(
    isLocked
      ? "Review Locked"
      : existingReview
        ? "Update Review"
        : "Submit Review",
  );

  if (message.length) {
    message
      .removeClass("text-secondary text-warning text-success")
      .addClass(canReview ? "text-success" : "text-warning")
      .text(
        statusMessage ||
          (canReview
            ? "You can submit a verified review for this product."
            : "Login and purchase this product with a delivered order to review."),
      );
  }
}

function bindProductReviewSubmit(product) {
  const form = $("#productReviewForm");
  if (!form.length) {
    return;
  }

  form.off("submit").on("submit", async function (event) {
    event.preventDefault();

    const productId = parseInt(product?.id, 10);
    if (!Number.isInteger(productId) || productId <= 0) {
      showNotif("Invalid product context for review.", "warning");
      return;
    }

    const rating = getReviewRatingSelection();
    const text = ($("#reviewText").val() || "").toString().trim();
    const selectedFiles = reviewSelectedFiles.slice(0, REVIEW_MAX_UPLOAD_FILES);
    const removeExistingImages = $("#reviewRemoveExistingImages").is(
      ":checked",
    );
    const submitBtn = $("#reviewSubmitBtn");
    const isLocked = parseInt(submitBtn.data("reviewLocked"), 10) === 1;

    if (isLocked) {
      showNotif(
        "This review has been locked by admin and cannot be edited.",
        "warning",
      );
      return;
    }

    if (rating < 1 || rating > 5) {
      showNotif("Select a rating between 1 and 5.", "warning");
      return;
    }

    if (text.length < 10 || text.length > 500) {
      showNotif(
        "Review text must be between 10 and 500 characters.",
        "warning",
      );
      return;
    }

    if (selectedFiles.length > REVIEW_MAX_UPLOAD_FILES) {
      showNotif("You can upload up to 2 images only.", "warning");
      return;
    }

    const maxBytes = REVIEW_MAX_UPLOAD_BYTES;
    for (const file of selectedFiles) {
      const mime = (file?.type || "").toString().toLowerCase();
      if (
        !(
          mime === "image/png" ||
          mime === "image/jpeg" ||
          mime === "image/webp" ||
          mime === "image/gif"
        )
      ) {
        showNotif(
          "Only JPG, PNG, WEBP, and GIF images are allowed.",
          "warning",
        );
        return;
      }

      if ((Number(file?.size) || 0) >= maxBytes) {
        showNotif("Each image must be less than 6 MB.", "warning");
        return;
      }
    }

    const originalText = submitBtn.text();
    submitBtn.prop("disabled", true).text("Submitting...");

    try {
      const formCsrfToken = (form.find("input[name='csrf_token']").val() || "")
        .toString()
        .trim();
      const requestCsrfToken = (
        reviewsCsrfToken ||
        formCsrfToken ||
        window.CommerzaCsrfToken ||
        ""
      )
        .toString()
        .trim();

      if (!requestCsrfToken) {
        throw new Error(
          "Security token missing. Refresh the page and try again.",
        );
      }

      const body = new FormData();
      body.set("action", "submit");
      body.set("product_id", String(productId));
      body.set("rating", String(rating));
      body.set("review_text", text);
      body.set("csrf_token", requestCsrfToken);

      if (removeExistingImages) {
        body.set("remove_existing_images", "1");
      }

      selectedFiles.slice(0, REVIEW_MAX_UPLOAD_FILES).forEach((file) => {
        body.append("review_images[]", file);
      });

      const response = await fetch("backend/api/reviews_api.php", {
        method: "POST",
        credentials: "same-origin",
        body,
      });

      const result = await response.json();
      if (result?.csrf_token) {
        reviewsCsrfToken = String(result.csrf_token);
        form.find("input[name='csrf_token']").val(reviewsCsrfToken);
      }

      if (!response.ok || !result?.ok) {
        throw new Error(result?.message || "Unable to submit review.");
      }

      showNotif(result?.message || "Review submitted successfully.", "success");

      const existing = result?.payload?.eligibility?.existing_review;
      if (existing && Number.isInteger(Number(existing.rating))) {
        setReviewRatingSelection(existing.rating);
      }

      $("#reviewText").val("");
      $("#reviewRemoveExistingImages").prop("checked", false);
      reviewClearSelectedFiles();
      renderReviewsMarquee(product);
    } catch (error) {
      showNotif(error?.message || "Unable to submit review.", "warning");
    } finally {
      submitBtn.prop("disabled", false).text(originalText);
    }
  });
}

function reviewMarqueeDurationSeconds(reviewCount) {
  const safeCount = Math.max(0, parseInt(reviewCount, 10) || 0);

  // Keep card movement readable by increasing duration as travel distance grows.
  const baseDuration = 40;
  const extraPerCard = 6;
  const duration = baseDuration + Math.max(0, safeCount - 3) * extraPerCard;

  return Math.min(140, Math.max(40, duration));
}

function renderReviewsMarquee(product) {
  const track = $("#reviews-track");
  const summary = $("#reviewsSummaryText");
  if (!track.length) return;

  const stopMarquee = function () {
    track.removeClass("is-marquee");
    track.css("--review-marquee-duration", "48s");
  };

  const startMarquee = function (durationSeconds) {
    const safeDuration = Math.max(
      40,
      Math.min(140, Number(durationSeconds) || 48),
    );
    track.css("--review-marquee-duration", `${safeDuration}s`);
    track.addClass("is-marquee");
  };

  const productId = parseInt(product?.id, 10);
  if (!Number.isInteger(productId) || productId <= 0) {
    stopMarquee();
    track.html(
      '<div class="review-card"><p class="mb-2">Reviews are not available for this product.</p></div>',
    );
    renderReviewFormState(
      {
        can_review: false,
        message: "Login with an eligible account to post a review.",
      },
      0,
    );
    return;
  }

  stopMarquee();
  track.html(
    '<div class="review-card"><p class="mb-0">Loading customer reviews...</p></div>',
  );

  fetch(`backend/api/reviews_api.php?action=list&product_id=${productId}`, {
    method: "GET",
    credentials: "same-origin",
    cache: "no-store",
  })
    .then((response) =>
      response.json().then((result) => ({
        ok: response.ok,
        result,
      })),
    )
    .then(({ ok, result }) => {
      if (result?.csrf_token) {
        reviewsCsrfToken = String(result.csrf_token);
        $("#productReviewForm input[name='csrf_token']").val(reviewsCsrfToken);
      }

      if (!ok || !result?.ok) {
        throw new Error(result?.message || "Unable to load reviews.");
      }

      const payload = result?.payload || {};
      const reviews = Array.isArray(payload.reviews) ? payload.reviews : [];
      const count = Number(payload?.summary?.count || 0);
      const average = Number(payload?.summary?.average || 0);

      if (summary.length) {
        if (count > 0) {
          summary.text(
            `${average.toFixed(1)} / 5 (${count} review${count === 1 ? "" : "s"})`,
          );
        } else {
          summary.text("No reviews yet. Be the first to share your feedback.");
        }
      }

      if (!reviews.length) {
        stopMarquee();
        track.html(`
            <div class="review-card">
              <div class="text-warning mb-2">☆☆☆☆☆</div>
              <p class="mb-2">No customer reviews yet. You can be the first reviewer.</p>
              <div class="text-secondary small">Commerza Community</div>
            </div>
          `);
      } else {
        const cardHtml = reviews
          .map((review) => {
            const rating = Math.max(1, Math.min(5, Number(review.rating) || 0));
            const stars = "★".repeat(rating) + "☆".repeat(5 - rating);
            const safeName = escapeHtml(review.name || "Customer");
            const safeText = escapeHtml(review.text || "");
            const imagesMarkup = renderReviewImagesMarkup(review.images || []);
            const dateLabel = formatReviewDate(
              review.updated_at || review.created_at,
            );

            return `
                <div class="review-card">
                  <div class="review-stars-line mb-2">
                    <span class="review-stars">${stars}</span>
                    <span class="review-score">${rating}/5</span>
                  </div>
                  <p class="mb-2">${safeText}</p>
                  ${imagesMarkup}
                  <div class="text-secondary small">${safeName}${dateLabel ? ` · ${dateLabel}` : ""}</div>
                </div>
              `;
          })
          .join("");

        const shouldMarquee = reviews.length >= 3;
        const marqueeDuration = reviewMarqueeDurationSeconds(reviews.length);
        if (shouldMarquee) {
          startMarquee(marqueeDuration);
        } else {
          stopMarquee();
        }
        track.html(shouldMarquee ? cardHtml + cardHtml : cardHtml);
      }

      renderReviewFormState(payload.eligibility || null, productId);
      bindProductReviewSubmit(product);
    })
    .catch((error) => {
      stopMarquee();
      track.html(`
          <div class="review-card">
            <div class="text-warning mb-2">☆☆☆☆☆</div>
            <p class="mb-2">${escapeHtml(error?.message || "Unable to load reviews right now.")}</p>
            <div class="text-secondary small">Commerza</div>
          </div>
        `);
      renderReviewFormState(
        {
          can_review: false,
          message: "Reviews are temporarily unavailable.",
        },
        productId,
      );
    });
}
