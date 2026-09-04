# PRD: Fix fill-blank 500 and file preview signed URLs

## Problem

1. `/api/word/fill-blank` returns HTTP 500 on production (PostgreSQL) because the query compares a `json` column with string literals (`!= '[]'`), which PostgreSQL rejects (`operator does not exist: json <> unknown`).
2. Cloud file list/preview returns short-lived signed absolute URLs. Behind CDN/TLS termination without trusted proxies, Laravel generates `http://` URLs; HTTPS frontends then show broken image previews (mixed content).

## Acceptance Criteria

- Authenticated `GET /api/word/fill-blank` returns 200 with a well-formed WordResource collection (possibly empty), never an unhandled 500 from JSON comparison.
- JSON emptiness is checked with driver-portable `whereJsonLength`, not string inequality on JSON columns.
- Proxies are trusted so request scheme/`X-Forwarded-Proto` is respected when generating signed raw URLs.
- When `APP_URL` is HTTPS, URL generation is forced to HTTPS.
- Feature tests cover fill-blank success, empty-state, and signed URL HTTPS generation behind a forwarded proto header.
