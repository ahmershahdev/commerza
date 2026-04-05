# products Directory Guide

## Purpose
Stores image assets used by this feature area.

## Scope
- Directory: admin/frontend/assets/images/products
- Primary audience: developers and maintainers
- Update frequency: when assets, APIs, or structure in this folder change

## File Standards
- Use optimized formats: WEBP first, PNG/JPG only when required.
- Keep filenames lowercase with hyphens.
- Prefer descriptive names tied to product or section context.
- Avoid uploading duplicates with different names.

## Change Workflow
1. Add or update files only for this directory responsibility.
2. Verify references from pages/APIs before committing.
3. Validate production-safe paths and naming consistency.
4. Remove stale files that are no longer referenced.

## Quality Checklist
- Paths resolve correctly from consuming pages or scripts.
- No debug-only or temporary files are left behind.
- Naming remains consistent with existing conventions.
- Documentation is updated when behavior/usage changes.

## Notes
- Keep this guide concise but current.
- Prefer incremental updates over large, undocumented restructures.