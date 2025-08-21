document.addEventListener("DOMContentLoaded", () => {
  const bogoBtn = document.querySelector(".bogo-get-product-button");
  const bogoProductName = document.querySelector(".bogo-get-product-name");
  const bogoProductId = document.getElementById("_bogo_get_product_id");

  if (bogoBtn) {
    bogoBtn.addEventListener("click", (e) => {
      e.preventDefault();

      const productSearchFrame = wp.media({
        states: [new wp.media.controller.ProductSearch()],
      });

      productSearchFrame.on("select", () => {
        const selection = productSearchFrame
          .state()
          .get("selection")
          .first()
          .toJSON();
        if (selection) {
          bogoProductName.textContent = selection.title;
          bogoProductId.value = selection.id;
        }
      });

      productSearchFrame.open();
    });
  }
});
