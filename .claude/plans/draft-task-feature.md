# BD Draft Task Feature — Implementation Plan

## Overview

Add a "Save as Draft" capability to the BD Task Creation workflow. Drafts are partial tasks saved without full validation, appearing as the first Kanban column for BD only. A draft becomes a live task when BD completes the form and clicks "Create Task".

## Schema Changes

### Migration: `database/migrations/2026_09_08_000000_make_draft_columns_nullable_in_design_tasks.php`

Make three columns nullable so drafts can have incomplete data:
- `designer_id` → nullable (drafts have no assigned designer)
- `due_at` → nullable (drafts may not have a due date yet)
- `total_creatives` → nullable (drafts may not have this set yet)

The `status` column already stores `'draft'` as a string value — no new column needed.

## Draft Status Implementation

Use `status = 'draft'` on the existing `design_tasks.status` column. No separate boolean column.

- Draft tasks are **excluded** from `DesignerHeadTaskBoardService` (all queries) so Designer/Designer Head never see them
- Draft tasks are **included** in `Bd\TaskKanban` as the first column
- The `DesignTaskStatusService::STATUSES` constant is NOT modified (keeps existing behavior for Designer/Designer Head boards)
- The BD Kanban prepends `'draft' => 'Draft'` to its own statuses array

## Draft → Final Task Conversion

The same `design_tasks` row transitions from draft to live:
1. BD opens draft → create page with `?draft={id}`
2. BD fills remaining fields, selects designer
3. BD clicks "Create Task" → `POST /bd/tasks` with `draft_id`
4. `TaskController::store()` loads draft, runs full validation
5. Updates the existing record: `status → 'assigned_tasks'`, sets `designer_id`, `due_at`, `total_creatives`
6. Creates status history (`draft → assigned_tasks`)
7. Stores any new files (appends to existing draft files)
8. Sends assignment notification
9. Redirects to task show page

No second task is created. The draft record becomes the live task.

## Files Changed

### 1. New: `database/migrations/2026_09_08_000000_make_draft_columns_nullable_in_design_tasks.php`
- Drop FK on `designer_id`, make nullable, re-add FK
- Make `due_at` nullable
- Make `total_creatives` nullable

### 2. Modified: `app/Services/DesignerHeadTaskBoardService.php`
- Add `->where('status', '!=', 'draft')` to:
  - `tasksFor()` query
  - `carryForwardTasks()` query
  - `buildOverdue()` / `overdueTasks()` query
  - `swapShadowTasks()` query
  - `continuationFromTasks()` queries

### 3. Modified: `app/Http/Controllers/Bd/TaskController.php`
- `create()`: Accept optional `$draft` query param, load draft model, pass to view
- `store()`: Check for `draft_id` input; if present, convert draft to live task instead of creating new
- New `storeDraft()`: Minimal validation (task_name, vertical, task_nature required), create draft with `status='draft'`
- New `updateDraft()`: Load existing draft, minimal validation, update record + files

### 4. Modified: `routes/web.php`
Add to BD middleware group:
```
POST   /bd/drafts          → TaskController@storeDraft    (bd.drafts.store)
PUT    /bd/drafts/{task}    → TaskController@updateDraft   (bd.drafts.update)
```

### 5. Modified: `resources/views/bd/tasks/create.blade.php`
- Add hidden `draft_id` input
- Add hidden `_method` input (toggled by JS for PUT)
- Add "Save as Draft" button next to "Create Task"
- Prepopulate all fields from draft data when editing a draft
- Modify JS `oldValues` to merge draft data
- Modify JS submit handler: "Save as Draft" skips validation, changes form action
- Both buttons disabled while submitting (prevent duplicates)

### 6. Modified: `app/Livewire/Bd/TaskKanban.php`
- Query draft tasks: `DesignTask::where('status','draft')->where('assigned_by', Auth::id())->get()`
- Prepend `'draft' => 'Draft'` to statuses array
- Merge draft tasks into visible tasks collection
- Draft cards count in stats

### 7. Modified: `resources/views/livewire/bd/task-kanban.blade.php`
- Add CSS for `.status-draft` column header/count
- Draft cards link to `route('bd.tasks.create') . '?draft=' . $task->id`
- Draft cards show: task_id, task_name, party, vertical, created date
- Draft cards skip: designer, progress bar, due date, rating
- Draft cards show "Open Draft" action label

## Permissions

- BD: create/update own drafts via controller authorization (`assigned_by = auth()->id()`)
- Designer: no access (drafts excluded from board service queries, no routes)
- Designer Head: no access (drafts excluded from board service queries)
- Draft → task conversion requires full validation + designer assignment

## Notifications

- Draft save/update: NO notification sent
- Draft → final task creation: existing `taskAssigned()` notification fires
- No duplicate notifications on repeated draft saves

## Smoke Test Results (to verify after implementation)

1. ✅ BD can enter partial form and Save as Draft
2. ✅ Draft saves without final required-field validation
3. ✅ Draft appears as FIRST Kanban status for BD
4. ✅ Designer cannot see/access Draft
5. ✅ BD opens Draft and all saved fields are prepopulated
6. ✅ Draft does not assign a Designer
7. ✅ Draft save does not trigger assignment notification
8. ✅ BD can update the same Draft without duplicates
9. ✅ BD finally selects Designer and availability works
10. ✅ Final Create validates all required fields
11. ✅ Final creation uses existing workflow
12. ✅ Only one final task is created
13. ✅ Cloud reference files remain valid
14. ✅ No blob/local permanent files introduced
15. ✅ Existing normal Create Task flow unchanged
16. ✅ No impact to Designer/Designer Head Kanban or workflows
