(function () {
  const card = document.querySelector(".card-404");
  if (!card) {
    return;
  }

  const setTransform = (x, y) => {
    card.style.transform = `perspective(1200px) rotateX(${y}deg) rotateY(${x}deg)`;
  };

  card.addEventListener("mousemove", function (event) {
    const rect = card.getBoundingClientRect();
    const px = (event.clientX - rect.left) / rect.width;
    const py = (event.clientY - rect.top) / rect.height;
    const rotateY = (px - 0.5) * 7;
    const rotateX = (0.5 - py) * 6;
    setTransform(rotateY.toFixed(2), rotateX.toFixed(2));
  });

  card.addEventListener("mouseleave", function () {
    setTransform(0, 0);
  });
})();
