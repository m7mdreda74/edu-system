# Performance Report

## Improvements delivered

- Teacher group index changed from full per-group student serialization to aggregate roster counts.
- Student roster data is fetched only when requested and is server-side paginated with a bounded page size of 1–50.
- Roster search runs in the database by name/email rather than filtering an already-loaded personal-data payload in the browser.
- Group workspace reuses existing curriculum, materials, worksheet, live-session, analytics, and announcement routes instead of duplicating business queries.

## Evidence and limits

- Full Laravel suite passed: 368 tests, 2,712 assertions.
- Production Vite build passed.
- No production APM, browser performance trace, load test, or serverless multi-instance benchmark was available.
- Vercel is configured to use the shared database cache table rather than `/tmp`; cross-instance lock/rate-limit behavior still needs staging verification.
