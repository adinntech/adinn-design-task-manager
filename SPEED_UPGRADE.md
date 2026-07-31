# Speed upgrade notes

This version keeps the existing Supabase app_state data safe and improves speed without a database reset.

## Improvements

- Backend Supabase state is ensured only once at startup instead of before every read.
- Backend in-memory state cache default increased to 30 seconds. Mutations refresh the cache immediately.
- Concurrent backend reads are coalesced into one Supabase read.
- Task list endpoint returns lightweight rows and loads full comments/files/history only when a task is opened.
- Task list default page size reduced to 50 rows for faster rendering.
- Frontend GET requests are cached and deduped for 10 seconds, reducing repeated users/meta/report calls.

## Recommended Render environment

DB_CACHE_TTL_MS=30000

## Recommended Vercel environment

VITE_API_CACHE_TTL_MS=10000
