require('dotenv').config();
const { createClient } = require('@supabase/supabase-js');
const { migrateDb } = require('../lib/store');

async function main() {
  const url = process.env.SUPABASE_URL;
  const key = process.env.SUPABASE_SERVICE_ROLE_KEY;
  const stateTable = process.env.SUPABASE_STATE_TABLE || 'app_state';
  const stateKey = process.env.SUPABASE_STATE_KEY || 'adinn-design-task-manager';

  if (!url || !key) throw new Error('SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY are required.');
  const client = createClient(url, key, { auth: { persistSession: false, autoRefreshToken: false } });

  const { data: legacy, error } = await client.from(stateTable).select('data').eq('key', stateKey).maybeSingle();
  if (error) throw error;
  if (!legacy?.data) throw new Error(`No legacy row found in ${stateTable} for key ${stateKey}.`);

  const { db } = migrateDb(legacy.data);
  const nullable = (value) => value === '' || value === undefined ? null : value;
  db.tasks = (db.tasks || []).map((task) => ({
    ...task,
    assigned_by: nullable(task.assigned_by), assigned_to: nullable(task.assigned_to), rated_by: nullable(task.rated_by),
    assignment_date: nullable(task.assignment_date), planned_start_date: nullable(task.planned_start_date),
    started_working_date: nullable(task.started_working_date), deadline_date: nullable(task.deadline_date),
    rated_at: nullable(task.rated_at), accepted_at: nullable(task.accepted_at), completed_at: nullable(task.completed_at)
  }));
  db.task_files = (db.task_files || []).map((x) => ({ ...x, uploaded_by: nullable(x.uploaded_by) }));
  db.task_comments = (db.task_comments || []).map((x) => ({ ...x, commented_by: nullable(x.commented_by) }));
  db.task_history = (db.task_history || []).map((x) => ({ ...x, performed_by: nullable(x.performed_by) }));
  db.notifications = (db.notifications || []).map((x) => ({ ...x, task_id: nullable(x.task_id), actor_id: nullable(x.actor_id) }));

  const { error: saveError } = await client.rpc('save_app_state_relational', { payload: db });
  if (saveError) throw saveError;
  console.log(`Migration complete: ${db.users.length} users, ${db.tasks.length} tasks, ${db.task_files.length} files.`);
}

main().catch((error) => {
  console.error(error.message || error);
  process.exit(1);
});
