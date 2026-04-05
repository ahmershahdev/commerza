# database Directory Guide

## Purpose
Stores database definitions, schema references, and data setup notes.

## Scope
- Directory: backend/database
- Primary audience: developers and maintainers
- Update frequency: when assets, APIs, or structure in this folder change

## File Standards
- Keep schema updates additive and migration-safe.
- Document table purpose, indexes, and key constraints.
- Avoid destructive changes without rollback planning.
- Validate schema against live DB before deployment.

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