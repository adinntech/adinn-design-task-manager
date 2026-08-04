# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

Adinn Design Work Allocation — an internal web app for design task allocation, BD (Business Developer) workflow tracking, designer assignment, task acceptance/decline, status tracking, reports, and admin management. Three roles: `admin`, `bd`, `designer`.

Monorepo with two independently deployed halves:
- `backend/` — Express API (Node >=20, CommonJS)
- `frontend/` — React 18 + Vite SPA

## Common commands

Run from the repo root (`package.json` has thin wrapper scripts) or from `backend/`/`frontend/` directly.

```bash
npm run install:all       # installs backend and frontend deps
npm run dev:backend       # cd backend && npm run dev  (node src/server.js, no reload)
npm run dev:frontend      # cd frontend && npm run dev  (vite --host 0.0.0.0, http://localhost:5173)
npm run build:frontend    # cd frontend && npm run build
npm run preflight         # backend/src/scripts/preflight.js — validates env, DB connectivity, upload dir
npm run reset:data        # backend/src/scripts/resetData.js — wipes/reseeds the data store
```

Backend-specific (run inside `backend/`):
```bash
npm run watch                       # nodemon src/server.js
npm run health                      # src/scripts/healthCheck.js
npm run backup:data                 # src/scripts/backupData.js
npm run migrate:file-to-supabase    # one-time: local JSON file -> Supabase JSON blob
npm run migrate:relational          # one-time: Supabase JSON blob -> relational Supabase tables
node src/scripts/migrateSupabaseToMongo.js   # one-time: Supabase -> MongoDB (not wired into package.json scripts)
node src/scripts/generateSecret.js  # print a random JWT secret
```

There is no automated test suite in this repo (no `test` script exists in either package). CI (`.github/workflows/ci.yml`) only runs backend `preflight` and a frontend production `build` on push/PR to `main` — treat those two as the minimum bar before committing. Frontend `lint:check` is a no-op placeholder, not real linting.

## Architecture

### Storage is a single JSON "state blob", not per-table CRUD

The entire application state (`users`, `tasks`, `task_files`, `task_comments`, `task_history`, `notifications`, `settings`) is modeled as one in-memory JS object, regardless of which backing store is configured. All of it lives in `backend/src/lib/store.js`:

- `readDb()` — loads the whole state object (from file, Supabase, or MongoDB depending on driver), running it through `migrateDb()` first to backfill any missing fields on read.
- `writeDb(db)` — persists the whole state object back.
- `updateDb(mutator)` — the primary way routes mutate data: it serializes all writes through a single in-process `mutationQueue` promise chain (read → run mutator → write), so concurrent requests don't race. Route handlers should call `updateDb(async (db) => { ...mutate db in place... })` rather than calling `readDb`/`writeDb` directly for anything that changes state.

Four storage drivers, selected by `DATA_DRIVER` (`file` | `supabase` | `supabase_relational` | `mongodb`), all normalized behind the same read/write shape:
- `file` — single JSON file on disk (`DATA_FILE`), for local dev only.
- `supabase` — the entire state object stored as one `jsonb` column in a single-row `app_state` table.
- `supabase_relational` — same logical state, but split into real Supabase tables (`users`, `tasks`, `task_files`, `task_comments`, `task_history`, `notifications`, `app_settings`) via `get_app_state_relational` / `save_app_state_relational` RPCs (see `supabase/relational-schema.sql`). Reads/writes still round-trip the whole state object per call.
- `mongodb` — same logical state, one MongoDB collection per top-level key (see `MONGO_COLLECTIONS` in `store.js`); each write does a full `deleteMany({})` + `insertMany()` per collection, i.e. still whole-state-replace semantics, not incremental document updates.

Remote drivers (`supabase*`, `mongodb`) share an in-process TTL cache (`DB_CACHE_TTL_MS`, default 30s) via `getCachedDb()`/`setCachedDb()` to avoid round-tripping on every read. `usingRemoteDb()` gates this. When editing store internals, keep this cache invalidated correctly on every write path.

`migrateDb()` is the schema-evolution mechanism: instead of SQL migrations for the JSON-blob drivers, every read/write passes the state through this function, which backfills missing fields on users/tasks/notifications/settings and normalizes legacy role names (`manager` → `bd`). When adding a new field to users/tasks/settings, add its default/backfill logic here so existing stored data upgrades in place.

Storage driver auto-detection: if `DATA_DRIVER` isn't set explicitly, `file` is used unless `SUPABASE_URL`+`SUPABASE_SERVICE_ROLE_KEY` are present (then `supabase`). `DATA_DRIVER=mongodb` requires `MONGODB_URI` and `MONGODB_DATABASE`; validated in `backend/src/config/env.js`.

There is a separate, independent file-storage driver (`FILE_STORAGE_DRIVER=local|supabase`) for uploaded task attachments, decoupled from `DATA_DRIVER` — see `backend/src/utils/upload.js`. Local uploads live under `UPLOAD_DIR`; Supabase mode uploads to `SUPABASE_STORAGE_BUCKET`.

`backend-mongodb-backup-20260803-134147/` at the repo root is a pre-migration snapshot of a few `backend/src` files (`store.js`, `env.js`, `server.js`, `preflight.js`) kept for reference during the MongoDB migration — it is not part of the running app.

### Backend request flow

`backend/src/server.js` wires: helmet, compression, a global rate limiter, CORS restricted to `FRONTEND_ORIGIN` (comma-separated allow-list, trailing slash stripped), JSON/urlencoded body parsing, then route mounts under `/api/*` (`auth`, `users`, `tasks`, `reports`, `settings`, `admin`, `notifications`). `ensureDb()` runs at startup before the server binds.

Auth (`backend/src/middleware/auth.js`): JWT bearer token, `authenticate` middleware loads the full user from `readDb()` on every request (no session store), rejects inactive/missing users. `permit(...roles)` is the role gate used per-route. `publicUser()` (in `store.js`) strips `password_hash` and adds `role_label`/normalized `verticals` before anything reaches a response.

Task visibility is role-scoped via `canSeeTask()` in `backend/src/utils/tasks.js` — admins see everything, BDs see tasks they created, designers see tasks assigned to them. Always route new task-reading endpoints through this rather than reimplementing the check.

Designers carry a `verticals` array (e.g. `RoadShow`, `Outdoor`, `Digital Marketing` — full list is `VERTICALS` in `store.js`) used to filter/assign tasks by category of work; tasks also carry a single `vertical`, defaulted from the assigned designer's first vertical when absent (`taskVertical`/`taskVerticalFast` in `routes/tasks.js`).

### Roles: two legacy aliases are still live

`migrateDb()` rewrites the stored role `manager` → `bd`, but `manager` and `superadmin` are still accepted throughout route guards and `canSeeTask()` (`permit('admin', 'superadmin', 'bd', 'manager')`). Treat `superadmin` as an alias of `admin` and `manager` as an alias of `bd`; when adding a role check, include the aliases the surrounding code does, or the check will silently diverge from its neighbours.

### Task domain model

Two independent progress tracks live on every task, and both must be kept in mind when touching task routes:

1. **`status`** — one of `pending_acceptance`, `accepted`, `declined`, `in_progress`, `on_hold`, `submitted_for_review`, `changes_requested`, `completed` (`STATUS_LABELS` in `utils/tasks.js`). Mutated by `PATCH /api/tasks/:id/{accept,decline,status}`.
2. **`action_field`** — a free-standing designer workflow step from `TASK_ACTION_OPTIONS` in `store.js` (`1st Modification Started` … `Project Completed`), mutated by `PATCH /api/tasks/:id/action`. The two tracks are coupled at exactly one point: setting `action_field = 'Project Completed'` also forces `status = 'completed'`.

`overdue` is a **computed** status, never stored. `applyComputedStatus()` derives `computed_status`/`computed_status_label` on read from `deadline_date`/`deadline_time`, skipping tasks already `completed`/`declined`/`submitted_for_review`/`changes_requested`. List filtering matches against both `status` and `computed_status`, so a query for `status=overdue` works even though no row holds that value. Any new read path that exposes status must map through `applyComputedStatus` or overdue tasks will appear as their raw status.

Per-transition authorization is enforced inside the `updateDb` mutator (not by `permit`), because it depends on the task row: only the assigner/admin may `changes_requested` or set `Project Completed`; only the assigned designer or the assigner may move through `in_progress`/`on_hold`/`submitted_for_review`. Throw an `Error` with a `.status` property from inside the mutator to return a specific HTTP code.

### Every task mutation writes an audit + notification

Task-mutating handlers follow a fixed three-step shape inside `updateDb`: mutate the record, then `createHistory(db, id, action, oldValue, newValue, userId, remarks)` (local helper in `routes/tasks.js`, appends to `db.task_history`), then `notifyTaskStakeholders(db, task, actor, payload)` (`utils/notifications.js`, fans out to assigner + assignee + all active admins/BDs, self-excluded). Both write into the same state object inside the same mutator, so they persist atomically with the change. New task mutations should keep this shape — the frontend timeline and notification bell read directly from these two collections.

`GET /api/tasks` is paginated (`page`, `limit`, default 50, hard cap 200) and returns a *list-shaped* item via `enrichTaskListItem` (a `file_count` only) rather than the full `enrichTask` payload (embedded `files`/`comments`/`history`), which is what `GET /api/tasks/:id` returns. `parseTaskFilters()` is shared by the list and `GET /api/tasks/export.csv` so both stay in sync — add new filters there, not in either handler.

### Frontend

`frontend/src/App.jsx` is a single ~1800-line file containing the whole SPA — router-less page switching via local `page` state in the top-level `App()` component, plus every page component (`Dashboard`, `TasksPage`, `CreateTaskPage`, `TaskDetail`, `UsersPage`, etc.) and small shared UI helpers (`StarRatingInput`, `Toast`, `NotificationBell`, date formatters). There is no client-side router and no component-per-file split — when adding a page or feature, follow the existing pattern of adding another function in this file rather than introducing a new structure unilaterally.

`frontend/src/api.js` is the sole HTTP client: wraps `fetch` against `VITE_API_URL`, attaches the JWT bearer token from `localStorage` (`dtm_token`/`dtm_user`), and layers a short-TTL (`VITE_API_CACHE_TTL_MS`, default 10s) in-memory GET cache with request de-duplication (`inFlightRequests`). Any non-GET request clears the whole cache. All backend calls should go through the `api` object here, not raw `fetch`.

## Environment configuration

Copy `.env.example` at the repo root (covers both backend and frontend vars) or `backend/.env.example` / `frontend/.env.example` per-package. Key backend vars: `DATA_DRIVER`, `FILE_STORAGE_DRIVER`, `JWT_SECRET`/`JWT_EXPIRES_IN`, `FRONTEND_ORIGIN` (CORS allow-list), `SUPABASE_*`, `MONGODB_URI`/`MONGODB_DATABASE`. `backend/src/config/env.js` (`validateEnv()`) throws on invalid/missing combinations — run `npm run preflight` after changing env vars to catch mistakes before starting the server.

Production requires a real `JWT_SECRET` (32+ chars) — `validateEnv()` hard-fails startup in `NODE_ENV=production` if left at the dev default.

`seedDb()` in `store.js` creates the demo accounts used for local dev and documented in `README.md` (`admin@adinn.com`, `bd@adinn.com`, `designer@adinn.com` … with trivial passwords). `npm run reset:data` restores exactly these. Never point a reset/seed at a deployed database.

## Gotchas

- `store.js` starts with `require('ws')` + `global.WebSocket = ws` — a polyfill the Supabase realtime client needs on Node. It looks like a stray unused import; removing it breaks the Supabase drivers.
- `mongoose` is in `backend/package.json` but nothing imports it — the MongoDB driver is used directly via `MongoClient`. Don't reach for mongoose models; there are none.
- Uploaded files are served as static assets from `/uploads` (not `/api/uploads`), outside the auth middleware — anything written there is publicly readable by URL.

## Deployment

Recommended stack: frontend on Vercel, backend on Render (`render.yaml` targets `supabase_relational` by default), database on Supabase or MongoDB. See `DEPLOYMENT.md`, `RELATIONAL_DATABASE_MIGRATION.md`, and `README.md` for the full setup/migration sequences between storage drivers — read the relevant one before changing `DATA_DRIVER` in a deployed environment, since switching drivers has one-way migration behavior (e.g. rolling back from `supabase_relational` to `supabase` does not copy data created after the switch back to the legacy row).
