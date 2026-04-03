# Admin CSS Folder Guide

This folder contains admin stylesheet entry points and module files.

## Files

- `style.css`: Main admin CSS entry file.

## Subfolders

- `modules/`: Layered CSS modules split by concern:
  - `01-base.css`: Base tokens and element defaults.
  - `02-layout.css`: Structural layout rules.
  - `03-components.css`: Shared UI component styles.
  - `04-utilities.css`: Utility/helper classes.
  - `05-responsive.css`: Breakpoint and responsive behavior rules.

## Notes

- Module numbering encodes intended load order.
- Keep component visual rules in modules; keep page-specific overrides minimal.
