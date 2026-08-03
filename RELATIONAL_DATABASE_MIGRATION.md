# Supabase Relational Database Migration

This version stores application data in separate Supabase tables instead of one large `app_state.data` JSON value.

## Tables visible in Supabase Table Editor

- `users`
- `tasks`
- `task_files`
- `task_comments`
- `task_history`
- `notifications`
- `app_settings`

## Safe deployment sequence

1. Open **Supabase Dashboard → SQL Editor → New query**.
2. Paste and run `supabase/schema.sql` from this project.
3. Do not delete the existing `app_state` table. It remains as a backup.
4. In **Render → Environment**, change:

   ```env
   DATA_DRIVER=supabase_relational
   ```

5. Keep the existing values for:

   ```env
   SUPABASE_URL=...
   SUPABASE_SERVICE_ROLE_KEY=...
   SUPABASE_STATE_TABLE=app_state
   SUPABASE_STATE_KEY=adinn-design-task-manager
   ```

6. Deploy the latest backend.

On the first backend startup, the app automatically checks the relational tables. When they are empty, it copies the existing JSON data from `app_state` into the new tables. If no legacy data exists, it creates the standard seed data.

## Verification

In Supabase, open **Table Editor** and confirm that rows appear in `users` and `tasks`. Then test:

- Admin login and all-task visibility
- Admin task creation
- BD login and own-created-task visibility
- Designer login and assigned-task visibility
- Task acceptance/status update
- File upload
- Comments and notifications

## Manual migration command

The automatic migration should be enough. A manual command is also available:

```bash
cd backend
npm run migrate:relational
```

Use it only after running `supabase/schema.sql` and setting the Supabase environment variables.

## Rollback

To temporarily return to the old JSON storage, set:

```env
DATA_DRIVER=supabase
```

The old `app_state` row is intentionally retained. Data created after switching to relational mode will not automatically be copied back to the legacy row, so rollback should only be used for emergency testing.
