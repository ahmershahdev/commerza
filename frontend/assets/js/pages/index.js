(function () {
  const script = document.currentScript;
  const csrfToken = script
    ? (script.getAttribute("data-csrf-token") || "").toString().trim()
    : "";

  window.CommerzaCsrfToken = csrfToken;

  document.addEventListener("DOMContentLoaded", function () {
    if (typeof window.commerzaOnReady === "function") {
      window.commerzaOnReady(function () {
        loadProductsBySection(
          "featured-collection",
          "featured-products-container",
        );
      });
    }

    (function initStoryBook() {
      const stageElement = document.getElementById("storyBookStage");
      const bookElement = document.getElementById("commerzaStoryBook");
      if (!bookElement || !stageElement) {
        return;
      }

      const pageIndicator = document.getElementById("storyBookPageIndicator");
      const pageNodes = Array.from(bookElement.querySelectorAll(".book-page"));
      const fallbackTotalPages = Number(pageNodes.length) || 1;
      const storyBookScope =
        stageElement.closest(".storybook-showcase") || document;
      const navPrevButton = storyBookScope.querySelector(
        '[data-storybook-nav="previous"]',
      );
      const navNextButton = storyBookScope.querySelector(
        '[data-storybook-nav="next"]',
      );

      let fallbackCurrentPage = 1;
      let usingTurnJs = false;
      let turnLocked = false;
      let $book = null;

      const clampPage = function (value, min, max) {
        return Math.min(max, Math.max(min, value));
      };

      const updateNavButtons = function (current, total) {
        const canGoPrev = current > 1;
        const canGoNext = current < total;

        if (navPrevButton) {
          navPrevButton.disabled = !canGoPrev;
          navPrevButton.setAttribute(
            "aria-disabled",
            canGoPrev ? "false" : "true",
          );
        }

        if (navNextButton) {
          navNextButton.disabled = !canGoNext;
          navNextButton.setAttribute(
            "aria-disabled",
            canGoNext ? "false" : "true",
          );
        }
      };

      const syncFallbackPage = function () {
        if (pageNodes.length === 0) {
          return;
        }

        pageNodes.forEach(function (pageNode, index) {
          const pageNumber = index + 1;
          const isVisible = pageNumber === fallbackCurrentPage;
          pageNode.style.display = isVisible ? "flex" : "none";
          pageNode.style.height = "100%";
        });
      };

      const syncPageUi = function () {
        const current =
          usingTurnJs && $book
            ? Number($book.turn("page")) || 1
            : fallbackCurrentPage;
        const total =
          usingTurnJs && $book
            ? Number($book.turn("pages")) || fallbackTotalPages
            : fallbackTotalPages;

        if (pageIndicator) {
          pageIndicator.textContent = `${current} / ${total}`;
        }

        updateNavButtons(current, total);
      };

      const applyResponsiveBookLayout = function () {
        const stageWidth = stageElement.clientWidth || 0;
        if (stageWidth <= 0) {
          return;
        }

        const isMobile =
          window.matchMedia &&
          window.matchMedia("(max-width: 767.98px)").matches;
        const targetWidth = isMobile
          ? Math.min(420, Math.max(240, stageWidth - 26))
          : Math.min(760, Math.max(430, stageWidth - 92));
        const targetHeight = isMobile
          ? Math.round(targetWidth * 1.22)
          : Math.round(targetWidth * 0.74);

        if (usingTurnJs && $book) {
          try {
            $book.turn("display", "single");
            $book.turn("size", targetWidth, targetHeight);
          } catch (_error) {
            usingTurnJs = false;
            bookElement.classList.add("storybook-fallback");
            bookElement.classList.remove("is-ready");
            syncFallbackPage();
          }
          return;
        }

        bookElement.style.width = `${targetWidth}px`;
        bookElement.style.height = `${targetHeight}px`;
      };

      if (window.jQuery && typeof window.jQuery.fn.turn === "function") {
        const TURN_DURATION_MS = 1450;
        $book = window.jQuery(bookElement);

        try {
          $book.turn({
            duration: TURN_DURATION_MS,
            gradients: true,
            acceleration: true,
            autoCenter: true,
            elevation: 56,
            display: "single",
          });

          usingTurnJs = true;
          bookElement.classList.remove("storybook-fallback");
          bookElement.classList.add("is-ready");
        } catch (_error) {
          usingTurnJs = false;
        }
      }

      if (!usingTurnJs) {
        bookElement.classList.add("storybook-fallback");
        bookElement.classList.remove("is-ready");
        syncFallbackPage();
      }

      const requestTurn = function (directionOrPage) {
        if (turnLocked) {
          return;
        }

        if (usingTurnJs && $book) {
          const current = Number($book.turn("page")) || 1;
          const total = Number($book.turn("pages")) || 1;

          let targetPage = current;
          if (directionOrPage === "next") {
            targetPage = current + 1;
          } else if (directionOrPage === "previous") {
            targetPage = current - 1;
          } else {
            targetPage = Number(directionOrPage) || current;
          }

          targetPage = clampPage(targetPage, 1, total);
          if (targetPage === current) {
            syncPageUi();
            return;
          }

          turnLocked = true;
          try {
            $book.turn("page", targetPage);
          } catch (_error) {
            turnLocked = false;
            bookElement.classList.remove("is-turning");
          }

          return;
        }

        const current = fallbackCurrentPage;
        let targetPage = current;
        if (directionOrPage === "next") {
          targetPage = current + 1;
        } else if (directionOrPage === "previous") {
          targetPage = current - 1;
        } else {
          targetPage = Number(directionOrPage) || current;
        }

        targetPage = clampPage(targetPage, 1, fallbackTotalPages);
        if (targetPage === current) {
          syncPageUi();
          return;
        }

        fallbackCurrentPage = targetPage;
        syncFallbackPage();
        syncPageUi();
      };

      let resizeDebounce = null;
      const handleResize = function () {
        if (resizeDebounce) {
          window.clearTimeout(resizeDebounce);
        }
        resizeDebounce = window.setTimeout(function () {
          applyResponsiveBookLayout();
          syncPageUi();
        }, 110);
      };

      if (navPrevButton) {
        navPrevButton.addEventListener("click", function (event) {
          event.preventDefault();
          event.stopPropagation();
          requestTurn("previous");
        });
      }

      if (navNextButton) {
        navNextButton.addEventListener("click", function (event) {
          event.preventDefault();
          event.stopPropagation();
          requestTurn("next");
        });
      }

      bookElement.addEventListener("keydown", function (event) {
        if (event.key === "ArrowRight") {
          event.preventDefault();
          requestTurn("next");
        }

        if (event.key === "ArrowLeft") {
          event.preventDefault();
          requestTurn("previous");
        }
      });

      if (usingTurnJs && $book) {
        $book.on("turning", function () {
          turnLocked = true;
          bookElement.classList.add("is-turning");
        });

        $book.on("turned", function () {
          window.setTimeout(function () {
            turnLocked = false;
            bookElement.classList.remove("is-turning");
            syncPageUi();
          }, 45);
        });

        $book.on("end", function () {
          turnLocked = false;
          bookElement.classList.remove("is-turning");
        });
      }

      applyResponsiveBookLayout();
      syncPageUi();
      window.addEventListener("resize", handleResize, { passive: true });

      if (typeof window.ResizeObserver === "function") {
        const stageResizeObserver = new window.ResizeObserver(function () {
          handleResize();
        });
        stageResizeObserver.observe(stageElement);
      }

    })();

    const heroTypingNode = document.getElementById("heroTypingText");
    if (heroTypingNode) {
      const typingPhrases = [
        "Skeleton Dials • Collector Grade",
        "24k Accents • Limited Drops",
        "Precision Caliber • Hand Finished",
        "Express Dispatch • Insured Delivery",
      ];

      const reduceMotion =
        window.matchMedia &&
        window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      if (reduceMotion || typingPhrases.length === 0) {
        heroTypingNode.textContent =
          typingPhrases[0] || "Premium Commerza Highlights";
      } else {
        let phraseIndex = 0;
        let charIndex = 0;
        let deleting = false;

        const randomDelay = function (base, variance) {
          return base + Math.floor(Math.random() * variance);
        };

        const runTypingLoop = function () {
          const phrase = typingPhrases[phraseIndex] || "";

          if (!deleting) {
            charIndex = Math.min(phrase.length, charIndex + 1);
          } else {
            charIndex = Math.max(0, charIndex - 1);
          }

          heroTypingNode.textContent = phrase.slice(0, charIndex);

          let nextDelay = deleting ? randomDelay(38, 36) : randomDelay(68, 56);

          if (!deleting && charIndex >= phrase.length) {
            deleting = true;
            nextDelay = 1450;
          } else if (deleting && charIndex === 0) {
            deleting = false;
            phraseIndex = (phraseIndex + 1) % typingPhrases.length;
            nextDelay = 330;
          }

          window.setTimeout(runTypingLoop, nextDelay);
        };

        runTypingLoop();
      }
    }
  });
})();
