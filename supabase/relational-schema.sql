-- ADINN Design Task Manager - Relational Supabase schema
-- Run this in Supabase Dashboard -> SQL Editor.
-- Existing public.app_state data is not deleted.

create extension if not exists pgcrypto;

create table if not exists public.users (
  id uuid primary key,
  name text not null,
  email text not null unique,
  password_hash text not null,
  role text not null check (role in ('admin','bd','designer')),
  department text not null default '',
  designation text not null default '',
  phone text not null default '',
  verticals text[] not null default '{}',
  status text not null default 'active',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.tasks (
  id uuid primary key,
  task_title text not null,
  client_name text not null default '',
  category text not null default '',
  description text not null default '',
  assigned_by uuid references public.users(id) on delete set null,
  assigned_to uuid references public.users(id) on delete set null,
  assignment_date date,
  assignment_time text not null default '',
  zoho_project_no text not null default '',
  vertical text not null default '',
  priority text not null default 'Medium',
  planned_start_date date,
  planned_start_time text not null default '',
  estimated_hours text not null default '',
  started_working_date date,
  started_working_time text not null default '',
  end_time text not null default '',
  status_of_day text not null default '',
  action_field text not null default '',
  rating_quality text not null default '',
  rating_timeliness text not null default '',
  rating_understanding text not null default '',
  rating_revision_handling text not null default '',
  rating_overall text not null default '',
  rating_remarks text not null default '',
  rated_by uuid references public.users(id) on delete set null,
  rated_at timestamptz,
  deadline_date date,
  deadline_time text not null default '',
  output_required text not null default '',
  status text not null default 'pending_acceptance',
  decline_reason text not null default '',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  accepted_at timestamptz,
  completed_at timestamptz
);

create table if not exists public.task_files (
  id uuid primary key,
  task_id uuid not null references public.tasks(id) on delete cascade,
  file_name text not null default '',
  stored_name text not null default '',
  storage_provider text not null default '',
  storage_bucket text not null default '',
  file_url text not null default '',
  file_type text not null default '',
  size_bytes bigint not null default 0,
  uploaded_by uuid references public.users(id) on delete set null,
  created_at timestamptz not null default now()
);

create table if not exists public.task_comments (
  id uuid primary key,
  task_id uuid not null references public.tasks(id) on delete cascade,
  comment text not null,
  commented_by uuid references public.users(id) on delete set null,
  created_at timestamptz not null default now()
);

create table if not exists public.task_history (
  id uuid primary key,
  task_id uuid not null references public.tasks(id) on delete cascade,
  action text not null default '',
  old_status text not null default '',
  new_status text not null default '',
  performed_by uuid references public.users(id) on delete set null,
  remarks text not null default '',
  created_at timestamptz not null default now()
);

create table if not exists public.notifications (
  id uuid primary key,
  user_id uuid not null references public.users(id) on delete cascade,
  type text not null default 'task_update',
  title text not null default '',
  message text not null default '',
  task_id uuid references public.tasks(id) on delete cascade,
  actor_id uuid references public.users(id) on delete set null,
  read boolean not null default false,
  created_at timestamptz not null default now()
);

create table if not exists public.app_settings (
  id integer primary key default 1 check (id = 1),
  categories text[] not null default '{}',
  priorities text[] not null default '{}',
  statuses text[] not null default '{}',
  action_options text[] not null default '{}',
  verticals text[] not null default '{}',
  roles jsonb not null default '[]'::jsonb,
  updated_at timestamptz not null default now()
);

create index if not exists tasks_assigned_by_idx on public.tasks (assigned_by);
create index if not exists tasks_assigned_to_idx on public.tasks (assigned_to);
create index if not exists tasks_status_idx on public.tasks (status);
create index if not exists tasks_deadline_idx on public.tasks (deadline_date, deadline_time);
create index if not exists tasks_created_at_idx on public.tasks (created_at desc);
create index if not exists task_files_task_idx on public.task_files (task_id);
create index if not exists task_comments_task_idx on public.task_comments (task_id, created_at);
create index if not exists task_history_task_idx on public.task_history (task_id, created_at);
create index if not exists notifications_user_idx on public.notifications (user_id, read, created_at desc);

-- Browser clients must not directly access internal tables. Backend service-role access bypasses RLS.
alter table public.users enable row level security;
alter table public.tasks enable row level security;
alter table public.task_files enable row level security;
alter table public.task_comments enable row level security;
alter table public.task_history enable row level security;
alter table public.notifications enable row level security;
alter table public.app_settings enable row level security;

revoke all on public.users, public.tasks, public.task_files, public.task_comments,
  public.task_history, public.notifications, public.app_settings from anon, authenticated;

-- Returns the same object shape used by the existing API, while data remains visible in separate tables.
create or replace function public.get_app_state_relational()
returns jsonb
language sql
security definer
set search_path = public
as $$
  select jsonb_build_object(
    'users', coalesce((select jsonb_agg(to_jsonb(u) order by u.created_at) from public.users u), '[]'::jsonb),
    'tasks', coalesce((select jsonb_agg(to_jsonb(t) order by t.created_at) from public.tasks t), '[]'::jsonb),
    'task_files', coalesce((select jsonb_agg(to_jsonb(f) order by f.created_at) from public.task_files f), '[]'::jsonb),
    'task_comments', coalesce((select jsonb_agg(to_jsonb(c) order by c.created_at) from public.task_comments c), '[]'::jsonb),
    'task_history', coalesce((select jsonb_agg(to_jsonb(h) order by h.created_at) from public.task_history h), '[]'::jsonb),
    'notifications', coalesce((select jsonb_agg(to_jsonb(n) order by n.created_at) from public.notifications n), '[]'::jsonb),
    'settings', coalesce((select jsonb_build_object(
      'categories', s.categories,
      'priorities', s.priorities,
      'statuses', s.statuses,
      'action_options', s.action_options,
      'verticals', s.verticals,
      'roles', s.roles
    ) from public.app_settings s where s.id = 1), '{}'::jsonb)
  );
$$;

-- Atomically synchronizes the API state into relational tables.
create or replace function public.save_app_state_relational(payload jsonb)
returns void
language plpgsql
security definer
set search_path = public
as $$
begin
  insert into public.users (id,name,email,password_hash,role,department,designation,phone,verticals,status,created_at,updated_at)
  select x.id,x.name,lower(x.email),x.password_hash,x.role,coalesce(x.department,''),coalesce(x.designation,''),coalesce(x.phone,''),coalesce(x.verticals,'{}'),coalesce(x.status,'active'),coalesce(x.created_at,now()),coalesce(x.updated_at,now())
  from jsonb_to_recordset(coalesce(payload->'users','[]'::jsonb)) as x(
    id uuid,name text,email text,password_hash text,role text,department text,designation text,phone text,verticals text[],status text,created_at timestamptz,updated_at timestamptz)
  on conflict (id) do update set name=excluded.name,email=excluded.email,password_hash=excluded.password_hash,role=excluded.role,department=excluded.department,designation=excluded.designation,phone=excluded.phone,verticals=excluded.verticals,status=excluded.status,updated_at=excluded.updated_at;

  insert into public.tasks (id,task_title,client_name,category,description,assigned_by,assigned_to,assignment_date,assignment_time,zoho_project_no,vertical,priority,planned_start_date,planned_start_time,estimated_hours,started_working_date,started_working_time,end_time,status_of_day,action_field,rating_quality,rating_timeliness,rating_understanding,rating_revision_handling,rating_overall,rating_remarks,rated_by,rated_at,deadline_date,deadline_time,output_required,status,decline_reason,created_at,updated_at,accepted_at,completed_at)
  select x.id,x.task_title,coalesce(x.client_name,''),coalesce(x.category,''),coalesce(x.description,''),x.assigned_by,x.assigned_to,x.assignment_date,coalesce(x.assignment_time,''),coalesce(x.zoho_project_no,''),coalesce(x.vertical,''),coalesce(x.priority,'Medium'),x.planned_start_date,coalesce(x.planned_start_time,''),coalesce(x.estimated_hours,''),x.started_working_date,coalesce(x.started_working_time,''),coalesce(x.end_time,''),coalesce(x.status_of_day,''),coalesce(x.action_field,''),coalesce(x.rating_quality,''),coalesce(x.rating_timeliness,''),coalesce(x.rating_understanding,''),coalesce(x.rating_revision_handling,''),coalesce(x.rating_overall,''),coalesce(x.rating_remarks,''),x.rated_by,x.rated_at,x.deadline_date,coalesce(x.deadline_time,''),coalesce(x.output_required,''),coalesce(x.status,'pending_acceptance'),coalesce(x.decline_reason,''),coalesce(x.created_at,now()),coalesce(x.updated_at,now()),x.accepted_at,x.completed_at
  from jsonb_to_recordset(coalesce(payload->'tasks','[]'::jsonb)) as x(
    id uuid,task_title text,client_name text,category text,description text,assigned_by uuid,assigned_to uuid,assignment_date date,assignment_time text,zoho_project_no text,vertical text,priority text,planned_start_date date,planned_start_time text,estimated_hours text,started_working_date date,started_working_time text,end_time text,status_of_day text,action_field text,rating_quality text,rating_timeliness text,rating_understanding text,rating_revision_handling text,rating_overall text,rating_remarks text,rated_by uuid,rated_at timestamptz,deadline_date date,deadline_time text,output_required text,status text,decline_reason text,created_at timestamptz,updated_at timestamptz,accepted_at timestamptz,completed_at timestamptz)
  on conflict (id) do update set task_title=excluded.task_title,client_name=excluded.client_name,category=excluded.category,description=excluded.description,assigned_by=excluded.assigned_by,assigned_to=excluded.assigned_to,assignment_date=excluded.assignment_date,assignment_time=excluded.assignment_time,zoho_project_no=excluded.zoho_project_no,vertical=excluded.vertical,priority=excluded.priority,planned_start_date=excluded.planned_start_date,planned_start_time=excluded.planned_start_time,estimated_hours=excluded.estimated_hours,started_working_date=excluded.started_working_date,started_working_time=excluded.started_working_time,end_time=excluded.end_time,status_of_day=excluded.status_of_day,action_field=excluded.action_field,rating_quality=excluded.rating_quality,rating_timeliness=excluded.rating_timeliness,rating_understanding=excluded.rating_understanding,rating_revision_handling=excluded.rating_revision_handling,rating_overall=excluded.rating_overall,rating_remarks=excluded.rating_remarks,rated_by=excluded.rated_by,rated_at=excluded.rated_at,deadline_date=excluded.deadline_date,deadline_time=excluded.deadline_time,output_required=excluded.output_required,status=excluded.status,decline_reason=excluded.decline_reason,updated_at=excluded.updated_at,accepted_at=excluded.accepted_at,completed_at=excluded.completed_at;

  insert into public.task_files select * from jsonb_to_recordset(coalesce(payload->'task_files','[]'::jsonb)) as x(id uuid,task_id uuid,file_name text,stored_name text,storage_provider text,storage_bucket text,file_url text,file_type text,size_bytes bigint,uploaded_by uuid,created_at timestamptz)
  on conflict (id) do update set task_id=excluded.task_id,file_name=excluded.file_name,stored_name=excluded.stored_name,storage_provider=excluded.storage_provider,storage_bucket=excluded.storage_bucket,file_url=excluded.file_url,file_type=excluded.file_type,size_bytes=excluded.size_bytes,uploaded_by=excluded.uploaded_by;

  insert into public.task_comments select * from jsonb_to_recordset(coalesce(payload->'task_comments','[]'::jsonb)) as x(id uuid,task_id uuid,comment text,commented_by uuid,created_at timestamptz)
  on conflict (id) do update set task_id=excluded.task_id,comment=excluded.comment,commented_by=excluded.commented_by;

  insert into public.task_history select * from jsonb_to_recordset(coalesce(payload->'task_history','[]'::jsonb)) as x(id uuid,task_id uuid,action text,old_status text,new_status text,performed_by uuid,remarks text,created_at timestamptz)
  on conflict (id) do update set task_id=excluded.task_id,action=excluded.action,old_status=excluded.old_status,new_status=excluded.new_status,performed_by=excluded.performed_by,remarks=excluded.remarks;

  insert into public.notifications select * from jsonb_to_recordset(coalesce(payload->'notifications','[]'::jsonb)) as x(id uuid,user_id uuid,type text,title text,message text,task_id uuid,actor_id uuid,read boolean,created_at timestamptz)
  on conflict (id) do update set user_id=excluded.user_id,type=excluded.type,title=excluded.title,message=excluded.message,task_id=excluded.task_id,actor_id=excluded.actor_id,read=excluded.read;

  insert into public.app_settings (id,categories,priorities,statuses,action_options,verticals,roles,updated_at)
  values (1,
    coalesce(array(select jsonb_array_elements_text(coalesce(payload#>'{settings,categories}','[]'::jsonb))), '{}'),
    coalesce(array(select jsonb_array_elements_text(coalesce(payload#>'{settings,priorities}','[]'::jsonb))), '{}'),
    coalesce(array(select jsonb_array_elements_text(coalesce(payload#>'{settings,statuses}','[]'::jsonb))), '{}'),
    coalesce(array(select jsonb_array_elements_text(coalesce(payload#>'{settings,action_options}','[]'::jsonb))), '{}'),
    coalesce(array(select jsonb_array_elements_text(coalesce(payload#>'{settings,verticals}','[]'::jsonb))), '{}'),
    coalesce(payload#>'{settings,roles}','[]'::jsonb),now())
  on conflict (id) do update set categories=excluded.categories,priorities=excluded.priorities,statuses=excluded.statuses,action_options=excluded.action_options,verticals=excluded.verticals,roles=excluded.roles,updated_at=now();

  delete from public.notifications n where not exists (select 1 from jsonb_array_elements(coalesce(payload->'notifications','[]'::jsonb)) e where (e->>'id')::uuid=n.id);
  delete from public.task_files f where not exists (select 1 from jsonb_array_elements(coalesce(payload->'task_files','[]'::jsonb)) e where (e->>'id')::uuid=f.id);
  delete from public.task_comments c where not exists (select 1 from jsonb_array_elements(coalesce(payload->'task_comments','[]'::jsonb)) e where (e->>'id')::uuid=c.id);
  delete from public.task_history h where not exists (select 1 from jsonb_array_elements(coalesce(payload->'task_history','[]'::jsonb)) e where (e->>'id')::uuid=h.id);
  delete from public.tasks t where not exists (select 1 from jsonb_array_elements(coalesce(payload->'tasks','[]'::jsonb)) e where (e->>'id')::uuid=t.id);
  delete from public.users u where not exists (select 1 from jsonb_array_elements(coalesce(payload->'users','[]'::jsonb)) e where (e->>'id')::uuid=u.id);
end;
$$;

revoke all on function public.get_app_state_relational() from public, anon, authenticated;
revoke all on function public.save_app_state_relational(jsonb) from public, anon, authenticated;
grant execute on function public.get_app_state_relational() to service_role;
grant execute on function public.save_app_state_relational(jsonb) to service_role;
