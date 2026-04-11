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

    const heroTypingNode = document.getElementById("heroTypingText");
    if (!heroTypingNode) {
      return;
    }

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
      return;
    }

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
  });
})();
