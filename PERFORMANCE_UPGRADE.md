# Performance Upgrade Notes

This release improves speed without resetting Supabase data.

## What changed

- Added an in-memory Supabase read cache on the backend.
- Added request coalescing so simultaneous screens share one Supabase read.
- Added paginated task loading: the task list loads 100 tasks at a time instead of the full history.
- Added task page controls in the UI.
- Added cache metadata in system health.

## Recommended Render environment variable

Add this optional variable in Render:

```text
DB_CACHE_TTL_MS=8000
```

Use `5000` to refresh very quickly or `15000` to reduce Supabase reads more aggressively.

## Data safety

This update does not run reset scripts and does not delete the existing Supabase `app_state` row.
