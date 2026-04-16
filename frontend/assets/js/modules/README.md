# modules Directory Guide

## Purpose
Stores JavaScript files for behavior, API calls, and page interactions.

## Scope
- Directory: frontend/assets/js/modules
- Primary audience: developers and maintainers
- Update frequency: when assets, APIs, or structure in this folder change

## File Standards
- Keep side effects minimal and gate DOM usage after ready state.
- Use API helpers for requests and centralize repeated logic.
- Preserve CSRF/session-aware request patterns.
- Prefer readable function names over inline anonymous blocks.

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