# css Directory Guide

## Purpose
Stores stylesheet assets and style modules for this area.

## Scope
- Directory: frontend/assets/css
- Primary audience: developers and maintainers
- Update frequency: when assets, APIs, or structure in this folder change

## File Standards
- Keep shared styles in modules and avoid page-only duplication.
- Group tokens, layout, and component rules in clear sections.
- Avoid overriding unrelated selectors from other modules.
- Validate mobile breakpoints before release.

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