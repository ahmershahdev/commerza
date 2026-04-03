# Frontend CSS Folder Guide

This folder contains storefront CSS entry points and theme modules.

## Files

- `style.css`: Main storefront stylesheet entry.

## Subfolders

- `modules/`: Split CSS modules loaded by the entry file:
  - `base.css`: Base variables/typography/default element styles.
  - `navigation.css`: Header/navigation styling.
  - `search.css`: Search bar interactions and presentation.
  - `search-suggestions.css`: Suggestion dropdown styling.
  - `carousel.css`: Hero/product carousel styling.
  - `products.css`: Product card/grid styles.
  - `layout-sections.css`: Shared section layout blocks.
  - `footer.css`: Footer visual styles.
  - `newsletter.css`: Newsletter section and form styles.
  - `offcanvas.css`: Offcanvas/nav drawer styling.
  - `wishlist-tracking.css`: Wishlist/tracking page styles.
  - `page-hero-wishlist.css`: Wishlist hero/banner styling.

## Notes

- Keep module names semantic and reusable.
- Prefer updating modules instead of adding page-inline CSS where possible.
