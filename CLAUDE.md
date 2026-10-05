# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Laravel app that manages design task requests across multiple "verticals" (outdoor, roadshow, fixtures, signage, pop_offsets, digital_marketing, events_activations) for BD (business development) staff, designers, a designer head, and admins. The app is well past its first slice (BD Create Task → Outdoor) and now covers the full lifecycle for all four roles:

- **BD:** dashboard, kanban, drafts, clone-from-task, client search, designer-availability meter, export, edit (`Bd\TaskEditController`), review/rework/complete-with-rating, "Prepare Printing File".
- **Designer / Designer Head:** kanban boards, task detail, EOD records, decline/split/swap requests with dual approval, printing-file mail tab, exports.
- **Admin:** dashboard, user management, task monitoring (edit/delete), manage-email (Excel import behind `config/features.php`), reports (summary/export/preview), activity, master controls.
- **Cross-cutting:** database notifications + bell (`TaskNotificationService`), Reverb broadcasting, multipart cloud uploads (`CloudMultipartUploadService`), Zoho attendance/project numbers, designer workload config (`config/workload.php`).

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

### Testing

The suite is small: `tests/Feature/Bd/TaskEditDesignerChangeTest.php` (BD designer reassignment + edit-flow regression) and `tests/Feature/RoleSmokeTest.php` (every role's main pages + role boundaries). Two gotchas:

- **`tests/Feature/.gitignore` is `*`** — new test files there are git-ignored. Use `git add -f` (or remove that `.gitignore`) to commit them.
- **Do not run tests against the dev DB.** `phpunit.xml` targets SQLite `:memory:`, but this machine's PHP has **no `pdo_sqlite`**, and `migrate:fresh` can't build the schema from scratch on MariaDB 10.4 (the `design_tasks` migration has `due_at timestamp not null` with no default → error 1067). So the existing tests use `DatabaseTransactions` against a **scratch MySQL database that already has the schema** (import a phpMyAdmin dump of the dev DB into e.g. `adinn_scratch`), and refuse to run unless the DB name contains `test` or `scratch`. Run them with env overrides, never `.env`:

```powershell
$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='adinn_scratch'; $env:DB_USERNAME='root'; $env:DB_PASSWORD=''; $env:BROADCAST_CONNECTION='log'; php artisan test
```

`vendor/bin/pint --test` reports CRLF/style findings on existing files — pre-existing, don't reformat whole files when making a small change.

There is **no Node/npm build step**. Tailwind is loaded via CDN script tag (`resources/views/layouts/app.blade.php`), and custom styles live as a static file at `public/css/adinn-premium.css`. Don't introduce a Vite/Mix pipeline unless asked — just edit that CSS file directly.

## Local environment quirks

- `README.md` describes a SQLite quick-start, but the actual `.env` in this working copy runs **MySQL** (`DB_CONNECTION=mysql`, MariaDB 10.4 via XAMPP), `SESSION_DRIVER`/`CACHE_STORE`/`QUEUE_CONNECTION=database`, `BROADCAST_CONNECTION=reverb`, and `FILESYSTEM_DISK=public`. Check `.env` before assuming anything.
- Task requirement uploads (and attachment deletes in `TaskEditController`) still go straight to the **`spaces`** disk regardless of `FILESYSTEM_DISK` — an S3-compatible driver (`league/flysystem-aws-s3-v3`) for DigitalOcean Spaces with `visibility => public` and `throw => true` (upload errors throw rather than fail silently).
- **XAMPP on this machine** (installed at `D:\xampp`): Apache listens on **8080** (not 80) — phpMyAdmin is at `http://localhost:8080/phpmyadmin/`, the app runs separately via `php artisan serve` on 8000. **Reverb is on port 8081** (`REVERB_PORT` + `REVERB_SERVER_PORT` in `.env`) because Apache owns 8080; start it with `php artisan reverb:start`. PHP is 8.5.x; `error_reporting` in `D:\xampp\php\php.ini` excludes `E_DEPRECATED`/`E_STRICT` so phpMyAdmin doesn't flood the page. Apache's popup about `php_curl.dll`/`SSL_get0_group_name` is a harmless OpenSSL mismatch (Apache 3.1.3 vs PHP 3.5) — curl just isn't loaded under Apache.
- **MySQL is fragile after forced kills.** Symptoms: panel says "shutdown unexpectedly", app/phpMyAdmin hang on every page, log stops after `Server socket created`. Don't trust the log file alone — run `mysqld.exe --defaults-file=D:\xampp\mysql\bin\my.ini --console` and read stderr. Past causes: damaged `aria_log_control` / Aria system tables marked crashed (fix: move `aria_log*` aside, then `aria_chk --zerofill --force` on `data\mysql\*.MAI` **run from inside `data\`**), and stray empty `master.info`/`relay-log.info`/`mysql-relay-bin.*` making it start as a replica (move aside). `my.ini` has `bind-address=127.0.0.1` + `skip-name-resolve`. Always stop MySQL from the XAMPP panel — processes started elsewhere can't be stopped without elevation.
- Demo/seeded logins (from `database/seeders/DatabaseSeeder.php`, password `Password@123` for all): `bd@adinn.com` (bd), `designer1@adinn.com` / `designer2@adinn.com` / `designer3@adinn.com` (designer), `head@adinn.com` (designer_head). There is no seeded `admin` user.

## Architecture

### Roles and routing

`User.role` is a plain string column (`admin`, `bd`, `designer`, `designer_head`) — not an enum. Authorization is entirely middleware-based via `App\Http\Middleware\EnsureRole`, aliased as `role` in `bootstrap/app.php`. Routes are split one file per role and all required from `routes/web.php`:

- `routes/web.php` — BD task create/store/drafts/clone/availability/export/show + requires the others
- `routes/auth.php` — login/logout, wired to `App\Http\Controllers\Auth\AuthenticatedSessionController` (the real auth flow)
- `routes/admin.php`, `routes/designer.php`, `routes/designer-head.php` — one `Route::middleware(['auth','role:<role>'])->prefix(...)->name(...)` group each
- `routes/premium-ui.php` — BD's assigned-task views/actions (`Bd\AssignedTaskController`: show, comments, rework, complete-with-rating) and BD edit (`Bd\TaskEditController`: `bd.tasks.edit` / `bd.tasks.update`)
- `routes/profile.php` — shared profile page

`App\Http\Controllers\AuthController` is a legacy/unused duplicate of the login flow — it is not wired into any route file. Prefer `Auth\AuthenticatedSessionController`.

`AuthenticatedSessionController::redirectForRole()` matches all four roles (`admin`, `bd`, `designer`, `designer_head`); any other role gets `abort(403)`.

### Design task domain model

- `DesignTask` — the core entity. `requirements` is a JSON column holding all the vertical/nature-specific dynamic form fields (see below); it is *not* modeled as separate columns.
- `DesignTaskStatusHistory` — append-only audit log of every status transition (`from_status`, `to_status`, `changed_by`, `change_source`).
- `DesignTaskComment` / `DesignTaskCommentAttachment` — task comments with file attachments.
- `DesignTaskRequest` — designer-initiated requests against a task (e.g. reassignment/split), gated by **dual approval**: independent `designer_head_status` and `admin_status` fields roll up into a single `overall_status` (starts `pending_designer_head`). Created via `DesignTaskRequestService::create()`.

### Status workflow is centralized in a service, not the model

`App\Services\DesignTaskStatusService` owns the full task status state machine:
- `STATUSES` — the canonical list of valid status keys/labels (`assigned_tasks → review_analysis → need_clarification → yet_to_start → in_progress → waiting_confirmation → rework → prepare_printing_file → completed`, plus `swap_tasks`). `prepare_printing_file` is BD-initiated (set by `Bd\AssignedTaskController::completeWithRating()`) and never entered via `moveAsDesigner()`; the private `ORDER` map only covers the original 8 designer-movable statuses.
- `designerCanMove($from, $to)` — encodes the allowed-transition rules (e.g. designers can never move a task directly to `rework` or `completed`; `rework` can only go to `yet_to_start`; `waiting_confirmation`/`completed` are terminal for the designer).
- `moveAsDesigner()` — the only sanctioned way to change a task's status as a designer; it authorizes (task must belong to the designer), validates the transition, and writes both the `DesignTask.status` update and the `DesignTaskStatusHistory` row inside one `DB::transaction`.

**Always route designer status changes through this service** rather than calling `$task->update(['status' => ...])` directly, so the history log and transition rules stay authoritative. Livewire's `TaskKanban::moveTask()` is the reference caller. Note that several BD/admin/request paths (`completeWithRating`, `DesignTaskRequestService::executeDecline/executeSwap`, `TaskEditController`) do their own hand-rolled lock/update/`DesignTaskStatusHistory` write — if you add one, always write the history row with a distinct `change_source`.

### BD task edit (`Bd\TaskEditController`)

- BD can edit only Priority, Due Date and Total Creatives (`EDITABLE_CORE_FIELDS`) plus the vertical/nature-specific requirement fields (`REQUIREMENT_FIELDS`); everything else is shown read-only. `LOCKED_EDIT_STATUSES = ['completed']`. Only the BD who created the task (`assigned_by`) can edit.
- **Assigned Designer can be changed** (to fix a wrong assignment) only while `canChangeDesigner()` is true: status in `DESIGNER_REASSIGNABLE_STATUSES` (`assigned_tasks`, `review_analysis`, `need_clarification`, `yet_to_start`) **and** no open request (`pending_approval|pending_designer_head|pending_admin`) on the task. Once work starts (`in_progress`+), it stays read-only and must go through the decline/swap request flow. The check is repeated inside the update transaction.
- A designer change writes an `Assigned Designer` row to `design_task_edit_histories`, sets `assigned_at = now()`, returns the task to `assigned_tasks` (with a `bd_edit_designer_changed` status-history row) if it had moved on, notifies the new designer via `TaskNotificationService::taskAssigned()` and flags the old designer's board via `designerUnassigned()`. The `designer_id` field is optional in the request so existing submissions behave exactly as before.
- Every edit is recorded field-by-field in `design_task_edit_histories` under one `edit_batch_id`.

### Dynamic requirements form (BD task creation)

`App\Http\Controllers\Bd\TaskController::store()` is the most complex controller and worth reading directly before touching task creation:
- `NATURES` maps each `vertical` to its valid `task_nature` values; `requirementRules()` then builds a per-(vertical, task_nature) validation ruleset via a big `match` on `"{$vertical}.{$nature}"`, marking specific `requirements.*` fields as `required` for that combination.
- File fields are split into `SINGLE_FILES` (one file) and `ARRAY_FILES` (up to 20 files each); everything else keyed outside the base task columns is collapsed into the `requirements` JSON blob.
- `board_width`/`board_height` (feet) get converted into a `board_size` object with auto-computed `square_feet`.
- Task creation is two-phase: the `DesignTask` row is created first with a placeholder `task_id` (`PENDING-<uuid>`), then immediately updated to the real `DT-{year}-{zero-padded id}` format once the DB id is known (so the human-readable ID embeds the autoincrement id).
- Uploaded files are stored on the `spaces` disk under a deterministic path (`{DO_SPACES_ROOT}/{year}/{vertical-slug}/{task_id}_{task-name-slug}/{task-nature-slug}/{field}/...`) with a long descriptive filename encoding task id, field, original name, and timestamp. If any file fails to store, the whole task row and any partially-uploaded directory are rolled back/deleted (not wrapped in the same DB transaction as task creation, since it happens after).

### Livewire

Livewire (v4) components live under `app/Livewire/` — `Designer/` (`TaskKanban`, `TaskDetail`, `TaskRequestModal`, `PrintingFileTab`), `Bd/` (`TaskKanban`, `TaskConfirmationActions`), `DesignerHead/TaskKanban`, and the shared `NotificationBell` — with matching views in `resources/views/livewire/...`. `TaskKanban` self-authorizes in `mount()` (`abort_unless(role === 'designer')`) rather than relying solely on route middleware, and dispatches browser-level events (`kanban-updated`, `task-status-changed`) for JS/UI to react to after a drag-and-drop move.
