# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Laravel app that manages design task requests across multiple "verticals" (outdoor, roadshow, fixtures, signage, pop_offsets, digital_marketing, events_activations) for BD (business development) staff, designers, a designer head, and admins. First implemented slice: **BD Login → Create Task → Outdoor vertical**; other verticals exist in validation/form logic but are less built out.

## Commands

```powershell
# Install
composer install

# Env setup (Windows)
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

- Run all tests: `php artisan test` (or `vendor/bin/phpunit`)
- Run a single test file: `php artisan test tests/Feature/SomeTest.php`
- Run a single test method: `php artisan test --filter=test_method_name`
- Lint/format: `vendor/bin/pint` (Laravel Pint; `vendor/bin/pint --test` to check without fixing)
- Tinker/REPL: `php artisan tinker`

There is currently no `tests/Feature` or `tests/Unit` test suite checked in (only `.gitignore` placeholders) — when adding tests, this is greenfield.

There is **no Node/npm build step**. Tailwind is loaded via CDN script tag (`resources/views/layouts/app.blade.php`), and custom styles live as a static file at `public/css/adinn-premium.css`. Don't introduce a Vite/Mix pipeline unless asked — just edit that CSS file directly.

## Local environment quirks

- `README.md` describes a SQLite quick-start, but the actual `.env` in this working copy runs **MySQL** (`DB_CONNECTION=mysql`) and **DigitalOcean Spaces** for file storage (`FILESYSTEM_DISK=spaces`). Check `.env` before assuming SQLite/local disk in this checkout.
- The `spaces` disk (`config/filesystems.php`) is an S3-compatible driver (`league/flysystem-aws-s3-v3`) pointed at DigitalOcean Spaces; it has `visibility => public` and `throw => true` (upload errors throw rather than fail silently).
- Demo/seeded logins (from `database/seeders/DatabaseSeeder.php`, password `Password@123` for all): `bd@adinn.com` (bd), `designer1@adinn.com` / `designer2@adinn.com` / `designer3@adinn.com` (designer), `head@adinn.com` (designer_head). There is no seeded `admin` user.

## Architecture

### Roles and routing

`User.role` is a plain string column (`admin`, `bd`, `designer`, `designer_head`) — not an enum. Authorization is entirely middleware-based via `App\Http\Middleware\EnsureRole`, aliased as `role` in `bootstrap/app.php`. Routes are split one file per role and all required from `routes/web.php`:

- `routes/web.php` — BD task create/store/show + requires the others
- `routes/auth.php` — login/logout, wired to `App\Http\Controllers\Auth\AuthenticatedSessionController` (the real auth flow)
- `routes/admin.php`, `routes/designer.php`, `routes/designer-head.php` — one `Route::middleware(['auth','role:<role>'])->prefix(...)->name(...)` group each
- `routes/premium-ui.php` — BD's read view of assigned tasks (`Bd\AssignedTaskController`)

`App\Http\Controllers\AuthController` is a legacy/unused duplicate of the login flow — it is not wired into any route file. Prefer `Auth\AuthenticatedSessionController`.

`AuthenticatedSessionController::redirectForRole()` only matches `admin`, `bd`, `designer` — a logged-in `designer_head` hits the `default => abort(403, ...)` branch on the root `/` redirect (they still reach `/designer-head` directly via its own route). Keep this in mind if you touch login redirect behavior.

### Design task domain model

- `DesignTask` — the core entity. `requirements` is a JSON column holding all the vertical/nature-specific dynamic form fields (see below); it is *not* modeled as separate columns.
- `DesignTaskStatusHistory` — append-only audit log of every status transition (`from_status`, `to_status`, `changed_by`, `change_source`).
- `DesignTaskComment` / `DesignTaskCommentAttachment` — task comments with file attachments.
- `DesignTaskRequest` — designer-initiated requests against a task (e.g. reassignment/split), gated by **dual approval**: independent `designer_head_status` and `admin_status` fields roll up into a single `overall_status` (starts `pending_designer_head`). Created via `DesignTaskRequestService::create()`.

### Status workflow is centralized in a service, not the model

`App\Services\DesignTaskStatusService` owns the full task status state machine:
- `STATUSES` — the canonical ordered list of valid status keys/labels (`assigned_tasks → review_analysis → need_clarification → yet_to_start → in_progress → waiting_confirmation → rework → completed`).
- `designerCanMove($from, $to)` — encodes the allowed-transition rules (e.g. designers can never move a task directly to `rework` or `completed`; `rework` can only go to `yet_to_start`; `waiting_confirmation`/`completed` are terminal for the designer).
- `moveAsDesigner()` — the only sanctioned way to change a task's status as a designer; it authorizes (task must belong to the designer), validates the transition, and writes both the `DesignTask.status` update and the `DesignTaskStatusHistory` row inside one `DB::transaction`.

**Always route status changes through this service** rather than calling `$task->update(['status' => ...])` directly, so the history log and transition rules stay authoritative. Livewire's `TaskKanban::moveTask()` is the reference caller.

### Dynamic requirements form (BD task creation)

`App\Http\Controllers\Bd\TaskController::store()` is the most complex controller and worth reading directly before touching task creation:
- `NATURES` maps each `vertical` to its valid `task_nature` values; `requirementRules()` then builds a per-(vertical, task_nature) validation ruleset via a big `match` on `"{$vertical}.{$nature}"`, marking specific `requirements.*` fields as `required` for that combination.
- File fields are split into `SINGLE_FILES` (one file) and `ARRAY_FILES` (up to 20 files each); everything else keyed outside the base task columns is collapsed into the `requirements` JSON blob.
- `board_width`/`board_height` (feet) get converted into a `board_size` object with auto-computed `square_feet`.
- Task creation is two-phase: the `DesignTask` row is created first with a placeholder `task_id` (`PENDING-<uuid>`), then immediately updated to the real `DT-{year}-{zero-padded id}` format once the DB id is known (so the human-readable ID embeds the autoincrement id).
- Uploaded files are stored on the `spaces` disk under a deterministic path (`{DO_SPACES_ROOT}/{year}/{vertical-slug}/{task_id}_{task-name-slug}/{task-nature-slug}/{field}/...`) with a long descriptive filename encoding task id, field, original name, and timestamp. If any file fails to store, the whole task row and any partially-uploaded directory are rolled back/deleted (not wrapped in the same DB transaction as task creation, since it happens after).

### Livewire

Livewire (v4) components live under `app/Livewire/Designer/` (`TaskKanban`, `TaskDetail`, `TaskRequestModal`) with matching views in `resources/views/livewire/...`. `TaskKanban` self-authorizes in `mount()` (`abort_unless(role === 'designer')`) rather than relying solely on route middleware, and dispatches browser-level events (`kanban-updated`, `task-status-changed`) for JS/UI to react to after a drag-and-drop move.
