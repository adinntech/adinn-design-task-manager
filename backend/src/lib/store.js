const fs = require('fs');
const path = require('path');
const bcrypt = require('bcryptjs');
const crypto = require('crypto');
const { createClient } = require('@supabase/supabase-js');

const VERTICALS = [
  'RoadShow',
  'Signage and Fixtures',
  'Outdoor',
  'Events and Activations',
  'Media',
  'Digital Marketing',
  'Print Services',
  'Wall Painting'
];

const TASK_ACTION_OPTIONS = [
  '1st Modification Started',
  '2nd Modification Started',
  'Preparing for Print File',
  'Print File Shared',
  'Project Completed'
];

const ROLE_LABELS = {
  admin: 'Admin',
  bd: 'Business Developer (BD)',
  designer: 'Designer',
  manager: 'Business Developer (BD)'
};

const STATE_KEY = process.env.SUPABASE_STATE_KEY || 'default';
const STATE_TABLE = process.env.SUPABASE_STATE_TABLE || 'app_state';
const RELATIONAL_DRIVER = 'supabase_relational';
const dataFile = path.resolve(process.cwd(), process.env.DATA_FILE || './data/db.json');

let supabaseClient = null;
let mutationQueue = Promise.resolve();
let dbCache = null;
let dbCacheAt = 0;
let dbReadInFlight = null;
let dbEnsured = false;
let dbEnsureInFlight = null;
const DB_CACHE_TTL_MS = Number(process.env.DB_CACHE_TTL_MS || 30000);

function uuid() {
  return crypto.randomUUID();
}

function now() {
  return new Date().toISOString();
}

function deepClone(value) {
  return JSON.parse(JSON.stringify(value));
}

function clearDbCache() {
  dbCache = null;
  dbCacheAt = 0;
  dbReadInFlight = null;
}

function getCachedDb() {
  if (!usingSupabase() || !dbCache) return null;
  if ((Date.now() - dbCacheAt) > DB_CACHE_TTL_MS) return null;
  return deepClone(dbCache);
}

function setCachedDb(db) {
  if (!usingSupabase()) return;
  dbCache = deepClone(db);
  dbCacheAt = Date.now();
}

function normalizeVerticals(value) {
  if (!value) return [];
  if (Array.isArray(value)) {
    return [...new Set(value.map((item) => String(item).trim()).filter(Boolean))];
  }
  return [...new Set(String(value).split(',').map((item) => item.trim()).filter(Boolean))];
}

function makeUser({ name, email, password, role, department = 'Design', designation = '', phone = '', status = 'active', verticals = [] }) {
  return {
    id: uuid(),
    name,
    email: email.toLowerCase(),
    password_hash: bcrypt.hashSync(password, 10),
    role,
    department,
    designation,
    phone,
    verticals: role === 'designer' ? normalizeVerticals(verticals) : [],
    status,
    created_at: now(),
    updated_at: now()
  };
}

function seedDb() {
  const users = [
    makeUser({ name: 'Super Admin', email: 'admin@adinn.com', password: 'admin123', role: 'admin', department: 'Management', designation: 'Admin' }),
    makeUser({ name: 'Business Developer', email: 'bd@adinn.com', password: 'bd123', role: 'bd', department: 'Business Development', designation: 'Business Developer' }),
    makeUser({ name: 'Designer One', email: 'designer@adinn.com', password: 'designer123', role: 'designer', department: 'Design', designation: 'Graphic Designer', verticals: ['RoadShow', 'Outdoor', 'Media'] }),
    makeUser({ name: 'Designer Two', email: 'designer2@adinn.com', password: 'designer123', role: 'designer', department: 'Design', designation: 'Senior Designer', verticals: ['Signage and Fixtures', 'Print Services', 'Wall Painting'] }),
    makeUser({ name: 'Designer Three', email: 'designer3@adinn.com', password: 'designer123', role: 'designer', department: 'Design', designation: 'Digital Designer', verticals: ['Digital Marketing', 'Events and Activations'] })
  ];

  const taskId = uuid();
  const bd = users.find((u) => u.role === 'bd');
  const designer = users.find((u) => u.email === 'designer@adinn.com');

  return {
    users,
    tasks: [
      {
        id: taskId,
        task_title: 'Sample hoarding design for real estate client',
        client_name: 'Demo Builder Project',
        category: 'Hoarding',
        description: 'Create a premium outdoor hoarding layout. Use clean typography, strong headline space, and luxury real estate visual treatment.',
        assigned_by: bd.id,
        assigned_to: designer.id,
        assignment_date: new Date().toISOString().slice(0, 10),
        zoho_project_no: 'ZP-DEMO-001',
        vertical: 'Outdoor',
        priority: 'High',
        planned_start_date: new Date().toISOString().slice(0, 10),
        planned_start_time: '10:00',
        estimated_hours: '6',
        started_working_date: '',
        started_working_time: '',
        end_time: '',
        status_of_day: 'Assigned',
        action_field: '',
        rating_quality: '',
        rating_timeliness: '',
        rating_understanding: '',
        rating_revision_handling: '',
        rating_overall: '',
        rating_remarks: '',
        rated_by: '',
        rated_at: '',
        deadline_date: new Date(Date.now() + 2 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10),
        deadline_time: '18:00',
        output_required: 'JPEG, PDF, editable source file',
        status: 'pending_acceptance',
        decline_reason: '',
        created_at: now(),
        updated_at: now(),
        accepted_at: '',
        completed_at: ''
      }
    ],
    task_files: [],
    task_comments: [],
    task_history: [
      {
        id: uuid(),
        task_id: taskId,
        action: 'task_created',
        old_status: '',
        new_status: 'pending_acceptance',
        performed_by: bd.id,
        remarks: 'Task created and assigned to Designer One.',
        created_at: now()
      }
    ],
    notifications: [],
    settings: {
      categories: ['Poster', 'Hoarding', 'Brochure', 'Presentation', 'Social Media', 'Logo', 'Vehicle Branding', 'Print Artwork', 'OOH Artwork', 'RoadShow Branding', 'Events Collateral', 'Signage Artwork', 'Digital Creative', 'Wall Painting Artwork', 'Other'],
      priorities: ['Low', 'Medium', 'High', 'Urgent'],
      statuses: ['pending_acceptance', 'accepted', 'declined', 'in_progress', 'on_hold', 'submitted_for_review', 'changes_requested', 'completed', 'overdue'],
      action_options: TASK_ACTION_OPTIONS,
      verticals: VERTICALS,
      roles: [
        { value: 'admin', label: 'Admin' },
        { value: 'bd', label: 'Business Developer (BD)' },
        { value: 'designer', label: 'Designer' }
      ]
    }
  };
}


function statusTextForMigration(status) {
  const labels = {
    pending_acceptance: 'Assigned',
    accepted: 'Accepted',
    declined: 'Declined',
    in_progress: 'Working',
    on_hold: 'On Hold',
    submitted_for_review: 'Submitted for Review',
    changes_requested: 'Changes Requested',
    completed: 'Completed',
    overdue: 'Overdue'
  };
  return labels[status] || '';
}

function migrateDb(db) {
  const source = db && typeof db === 'object' ? db : seedDb();
  let changed = !db || typeof db !== 'object';

  if (!source.settings) {
    source.settings = {};
    changed = true;
  }

  if (!Array.isArray(source.settings.categories)) {
    source.settings.categories = ['Poster', 'Hoarding', 'Brochure', 'Presentation', 'Social Media', 'Other'];
    changed = true;
  }

  if (!Array.isArray(source.settings.priorities)) {
    source.settings.priorities = ['Low', 'Medium', 'High', 'Urgent'];
    changed = true;
  }

  if (!Array.isArray(source.settings.statuses)) {
    source.settings.statuses = ['pending_acceptance', 'accepted', 'declined', 'in_progress', 'on_hold', 'submitted_for_review', 'changes_requested', 'completed', 'overdue'];
    changed = true;
  }

  if (!Array.isArray(source.settings.action_options)) {
    source.settings.action_options = TASK_ACTION_OPTIONS;
    changed = true;
  }

  if (!Array.isArray(source.settings.verticals)) {
    source.settings.verticals = VERTICALS;
    changed = true;
  }

  if (!Array.isArray(source.settings.roles)) {
    source.settings.roles = [
      { value: 'admin', label: 'Admin' },
      { value: 'bd', label: 'Business Developer (BD)' },
      { value: 'designer', label: 'Designer' }
    ];
    changed = true;
  }

  source.users = (source.users || []).map((user) => {
    const next = { ...user };
    if (next.role === 'manager') {
      next.role = 'bd';
      if (!next.designation || next.designation === 'Design Manager') next.designation = 'Business Developer';
      if (!next.department || next.department === 'Design') next.department = 'Business Development';
      changed = true;
    }
    if (!Array.isArray(next.verticals)) {
      next.verticals = next.role === 'designer' ? normalizeVerticals(next.vertical || []) : [];
      delete next.vertical;
      changed = true;
    }
    if (next.role !== 'designer' && next.verticals.length) {
      next.verticals = [];
      changed = true;
    }
    if (!next.created_at) { next.created_at = now(); changed = true; }
    if (!next.updated_at) { next.updated_at = next.created_at; changed = true; }
    return next;
  });

  if (!Array.isArray(source.tasks)) source.tasks = [];
  source.tasks = source.tasks.map((task) => {
    const next = { ...task };
    if (next.assignment_date === undefined) { next.assignment_date = next.created_at ? String(next.created_at).slice(0, 10) : now().slice(0, 10); changed = true; }
    if (next.zoho_project_no === undefined) { next.zoho_project_no = ''; changed = true; }
    if (next.vertical === undefined) {
      const designer = source.users.find((u) => u.id === next.assigned_to);
      next.vertical = normalizeVerticals(designer?.verticals)[0] || '';
      changed = true;
    }
    if (next.planned_start_date === undefined) { next.planned_start_date = ''; changed = true; }
    if (next.planned_start_time === undefined) { next.planned_start_time = ''; changed = true; }
    if (next.estimated_hours === undefined) { next.estimated_hours = ''; changed = true; }
    if (next.started_working_date === undefined) { next.started_working_date = next.planned_start_date || ''; changed = true; }
    if (next.started_working_time === undefined) { next.started_working_time = next.planned_start_time || ''; changed = true; }
    if (next.end_time === undefined) { next.end_time = ''; changed = true; }
    if (next.status_of_day === undefined) { next.status_of_day = next.status ? (next.status === 'completed' ? 'Completed' : statusTextForMigration(next.status)) : ''; changed = true; }
    if (next.action_field === undefined) { next.action_field = ''; changed = true; }
    if (next.rating_quality === undefined) { next.rating_quality = ''; changed = true; }
    if (next.rating_timeliness === undefined) { next.rating_timeliness = ''; changed = true; }
    if (next.rating_understanding === undefined) { next.rating_understanding = ''; changed = true; }
    if (next.rating_revision_handling === undefined) { next.rating_revision_handling = ''; changed = true; }
    if (next.rating_overall === undefined) { next.rating_overall = ''; changed = true; }
    if (next.rating_remarks === undefined) { next.rating_remarks = ''; changed = true; }
    if (next.rated_by === undefined) { next.rated_by = ''; changed = true; }
    if (next.rated_at === undefined) { next.rated_at = ''; changed = true; }
    if (!next.created_at) { next.created_at = now(); changed = true; }
    if (!next.updated_at) { next.updated_at = next.created_at; changed = true; }
    return next;
  });
  if (!Array.isArray(source.task_files)) { source.task_files = []; changed = true; }
  if (!Array.isArray(source.task_comments)) { source.task_comments = []; changed = true; }
  if (!Array.isArray(source.task_history)) { source.task_history = []; changed = true; }
  if (!Array.isArray(source.notifications)) { source.notifications = []; changed = true; }
  source.notifications = source.notifications.map((item) => {
    const next = { ...item };
    if (next.read === undefined) { next.read = false; changed = true; }
    if (!next.created_at) { next.created_at = now(); changed = true; }
    return next;
  });

  return { db: source, changed };
}


function nullable(value) {
  return value === '' || value === undefined ? null : value;
}

function prepareRelationalPayload(db) {
  const payload = deepClone(db);
  const validUserIds = new Set((payload.users || []).map((user) => String(user.id)));
  const validTaskIds = new Set((payload.tasks || []).map((task) => String(task.id)));

  const validUserRef = (value) => {
    const normalized = nullable(value);
    return normalized && validUserIds.has(String(normalized)) ? normalized : null;
  };

  payload.tasks = (payload.tasks || []).map((task) => ({
    ...task,
    assigned_by: validUserRef(task.assigned_by),
    assigned_to: validUserRef(task.assigned_to),
    rated_by: validUserRef(task.rated_by),
    assignment_date: nullable(task.assignment_date),
    planned_start_date: nullable(task.planned_start_date),
    started_working_date: nullable(task.started_working_date),
    deadline_date: nullable(task.deadline_date),
    rated_at: nullable(task.rated_at),
    accepted_at: nullable(task.accepted_at),
    completed_at: nullable(task.completed_at)
  }));

  payload.task_files = (payload.task_files || [])
    .filter((file) => validTaskIds.has(String(file.task_id)))
    .map((file) => ({
      ...file,
      uploaded_by: validUserRef(file.uploaded_by)
    }));

  payload.task_comments = (payload.task_comments || [])
    .filter((comment) => validTaskIds.has(String(comment.task_id)))
    .map((comment) => ({
      ...comment,
      commented_by: validUserRef(comment.commented_by)
    }));

  payload.task_history = (payload.task_history || [])
    .filter((item) => validTaskIds.has(String(item.task_id)))
    .map((item) => ({
      ...item,
      performed_by: validUserRef(item.performed_by)
    }));

  payload.notifications = (payload.notifications || [])
    .filter((item) => validUserIds.has(String(item.user_id)))
    .map((item) => ({
      ...item,
      task_id: item.task_id && validTaskIds.has(String(item.task_id)) ? item.task_id : null,
      actor_id: validUserRef(item.actor_id)
    }));

  return payload;
}

function writeJsonAtomic(filePath, payload) {
  const dir = path.dirname(filePath);
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
  const tmpFile = `${filePath}.${process.pid}.${Date.now()}.tmp`;
  fs.writeFileSync(tmpFile, JSON.stringify(payload, null, 2));
  fs.renameSync(tmpFile, filePath);
}

function getStorageDriver() {
  const explicit = String(process.env.DATA_DRIVER || '').trim().toLowerCase();
  if (explicit) return explicit;
  if (process.env.SUPABASE_URL && process.env.SUPABASE_SERVICE_ROLE_KEY) return 'supabase';
  return 'file';
}

function usingSupabase() {
  return ['supabase', RELATIONAL_DRIVER].includes(getStorageDriver());
}

function usingRelationalSupabase() {
  return getStorageDriver() === RELATIONAL_DRIVER;
}

function getSupabaseClient() {
  if (!usingSupabase()) return null;
  if (!process.env.SUPABASE_URL || !process.env.SUPABASE_SERVICE_ROLE_KEY) {
    throw new Error('DATA_DRIVER=supabase requires SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY.');
  }
  if (!supabaseClient) {
    supabaseClient = createClient(process.env.SUPABASE_URL, process.env.SUPABASE_SERVICE_ROLE_KEY, {
      auth: { persistSession: false, autoRefreshToken: false }
    });
  }
  return supabaseClient;
}

function formatSupabaseError(error) {
  if (!error) return 'Unknown Supabase error';
  if (String(error.message || '').includes(`relation`) && String(error.message || '').includes(STATE_TABLE)) {
    return `Supabase table "${STATE_TABLE}" was not found. Run supabase/schema.sql in the Supabase SQL Editor first.`;
  }
  return error.message || JSON.stringify(error);
}

async function ensureFileDb() {
  const dir = path.dirname(dataFile);
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
  if (!fs.existsSync(dataFile)) {
    writeJsonAtomic(dataFile, seedDb());
    return;
  }

  const current = JSON.parse(fs.readFileSync(dataFile, 'utf8'));
  const { db, changed } = migrateDb(current);
  if (changed) writeJsonAtomic(dataFile, db);
}

async function ensureSupabaseDb() {
  const client = getSupabaseClient();

  if (usingRelationalSupabase()) {
    const { data: current, error: currentError } = await client.rpc('get_app_state_relational');
    if (currentError) {
      throw new Error(`Relational Supabase schema is not ready. Run supabase/schema.sql first. ${currentError.message}`);
    }

    if (Array.isArray(current?.users) && current.users.length > 0) {
      const { db, changed } = migrateDb(current);
      if (changed) await writeDb(db);
      return;
    }

    // One-time automatic migration from the legacy app_state JSON row when present.
    const { data: legacy, error: legacyError } = await client
      .from(STATE_TABLE)
      .select('data')
      .eq('key', STATE_KEY)
      .maybeSingle();

    if (legacyError && !String(legacyError.message || '').includes('does not exist')) {
      console.warn(`Legacy app_state lookup skipped: ${legacyError.message}`);
    }

    const initial = legacy?.data ? migrateDb(legacy.data).db : seedDb();
    const { error: saveError } = await client.rpc('save_app_state_relational', { payload: prepareRelationalPayload(initial) });
    if (saveError) throw new Error(`Unable to initialize relational Supabase tables: ${saveError.message}`);
    setCachedDb(initial);
    return;
  }

  const { data, error } = await client
    .from(STATE_TABLE)
    .select('key,data')
    .eq('key', STATE_KEY)
    .maybeSingle();

  if (error) throw new Error(formatSupabaseError(error));

  if (!data) {
    const initial = seedDb();
    const { error: insertError } = await client
      .from(STATE_TABLE)
      .insert({ key: STATE_KEY, data: initial, updated_at: now() });
    if (insertError) throw new Error(formatSupabaseError(insertError));
    return;
  }

  const { db, changed } = migrateDb(data.data);
  if (changed) await writeDb(db);
}

async function ensureDb() {
  if (dbEnsured) return;
  if (dbEnsureInFlight) return dbEnsureInFlight;

  dbEnsureInFlight = (async () => {
    if (usingSupabase()) {
      await ensureSupabaseDb();
    } else {
      await ensureFileDb();
    }
    dbEnsured = true;
  })();

  try {
    await dbEnsureInFlight;
  } finally {
    dbEnsureInFlight = null;
  }
}

async function readDb() {
  await ensureDb();

  if (usingSupabase()) {
    const cached = getCachedDb();
    if (cached) return cached;

    if (dbReadInFlight) return deepClone(await dbReadInFlight);

    dbReadInFlight = (async () => {
      const client = getSupabaseClient();
      let raw;

      if (usingRelationalSupabase()) {
        const { data, error } = await client.rpc('get_app_state_relational');
        if (error) throw new Error(`Unable to read relational Supabase tables: ${error.message}`);
        raw = data;
      } else {
        const { data, error } = await client
          .from(STATE_TABLE)
          .select('data')
          .eq('key', STATE_KEY)
          .single();
        if (error) throw new Error(formatSupabaseError(error));
        raw = data.data;
      }

      const { db, changed } = migrateDb(deepClone(raw));
      if (changed) await writeDb(db);
      setCachedDb(db);
      return db;
    })();

    try {
      return deepClone(await dbReadInFlight);
    } finally {
      dbReadInFlight = null;
    }
  }

  const current = JSON.parse(fs.readFileSync(dataFile, 'utf8'));
  const { db, changed } = migrateDb(current);
  if (changed) writeJsonAtomic(dataFile, db);
  return db;
}

async function writeDb(db) {
  const { db: migrated } = migrateDb(deepClone(db));

  if (usingSupabase()) {
    const client = getSupabaseClient();
    let error;

    if (usingRelationalSupabase()) {
      ({ error } = await client.rpc('save_app_state_relational', { payload: prepareRelationalPayload(migrated) }));
      if (error) throw new Error(`Unable to save relational Supabase tables: ${error.message}`);
    } else {
      ({ error } = await client
        .from(STATE_TABLE)
        .upsert({ key: STATE_KEY, data: migrated, updated_at: now() }, { onConflict: 'key' }));
      if (error) throw new Error(formatSupabaseError(error));
    }

    setCachedDb(migrated);
    return migrated;
  }

  await ensureFileDb();
  writeJsonAtomic(dataFile, migrated);
  return migrated;
}

function updateDb(mutator) {
  const run = mutationQueue.catch(() => {}).then(async () => {
    const db = await readDb();
    const result = await mutator(db);
    await writeDb(db);
    return result;
  });
  mutationQueue = run.then(() => {}, () => {});
  return run;
}

function publicUser(user) {
  if (!user) return null;
  const { password_hash, ...safe } = user;
  return {
    ...safe,
    verticals: normalizeVerticals(safe.verticals),
    role_label: ROLE_LABELS[safe.role] || safe.role
  };
}

function storageInfo() {
  return {
    driver: getStorageDriver(),
    table: usingRelationalSupabase() ? 'relational tables' : (usingSupabase() ? STATE_TABLE : ''),
    state_key: usingRelationalSupabase() ? '' : (usingSupabase() ? STATE_KEY : ''),
    data_file: usingSupabase() ? '' : dataFile,
    cache_ttl_ms: usingSupabase() ? DB_CACHE_TTL_MS : 0,
    cache_active: usingSupabase() ? Boolean(dbCache) : false,
    cache_age_ms: usingSupabase() && dbCacheAt ? Date.now() - dbCacheAt : 0
  };
}

module.exports = {
  VERTICALS,
  TASK_ACTION_OPTIONS,
  ROLE_LABELS,
  dataFile,
  now,
  uuid,
  normalizeVerticals,
  seedDb,
  migrateDb,
  ensureDb,
  readDb,
  writeDb,
  updateDb,
  publicUser,
  storageInfo,
  usingSupabase,
  usingRelationalSupabase,
  clearDbCache
};
