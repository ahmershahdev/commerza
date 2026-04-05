# assets Directory Guide

## Purpose
Documents admin panel structure and responsibilities for this folder.

## Scope
- Directory: admin/frontend/assets
- Primary audience: developers and maintainers
- Update frequency: when assets, APIs, or structure in this folder change

## File Standards
- Keep files focused on this folder scope.
- Use consistent naming and avoid ambiguous filenames.
- Keep documentation and assets synchronized with code.
- Remove obsolete files during maintenance.

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