# SEO, Crawlers, and LLM Discovery

## Public Indexing Strategy

- Keep canonical public pages indexable.
- Keep account, cart, tracking, compare, auth, backend, and admin routes restricted.

## robots.txt Policy

- `Allow: /` for standard bots
- Explicit `Disallow` for sensitive/auth/admin/backend routes
- AI bot sections should allow `llms.txt` and core informational pages only

## sitemap.xml Policy

- Include only public, canonical pages.
- Keep `lastmod` values current when page-level updates occur.
- Avoid including private or utility routes.

## llms.txt Policy

- List canonical public sources for summarization.
- Mark sensitive/private routes as restricted.
- Add short project-level security handling guidance.

## Change Workflow

1. Update public page list in `sitemap.xml`.
2. Mirror indexability constraints in `robots.txt`.
3. Keep `llms.txt` aligned with both files.
4. Re-check noindex pages are not accidentally exposed in sitemap.
