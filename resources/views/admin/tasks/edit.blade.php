@extends('layouts.app')

@section('title','Edit '.$task->task_id)
@section('workspace-title','Admin Task Edit')
@section('workspace-subtitle','Update task information, allocation and status')

@section('content')
<style>
    .admin-edit-wrap{max-width:1180px}
    .admin-edit-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .admin-edit-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
    .admin-edit-field label{display:block;font-size:11px;font-weight:800;color:#344054;margin-bottom:6px}
    .admin-edit-section{margin-bottom:14px}
    .admin-edit-note{padding:11px 13px;border-radius:10px;border:1px solid #fedf89;background:#fffaeb;color:#93370d;font-size:10px;line-height:1.5}
    .requirement-edit-row{padding:11px 0;border-bottom:1px solid #eef0f3}
    .requirement-edit-row:last-child{border-bottom:0}
    .readonly-file{padding:9px 11px;border:1px solid #eaecf0;background:#f8f9fb;border-radius:8px;color:#667085;font-size:10px}
    .save-bar{display:flex;justify-content:flex-end;gap:8px;position:sticky;bottom:12px;padding:12px;background:rgba(255,255,255,.96);backdrop-filter:blur(10px);border:1px solid #e5e7eb;border-radius:13px;box-shadow:0 12px 32px rgba(16,24,40,.12);z-index:20}
    @media(max-width:850px){.admin-edit-grid,.admin-edit-grid-3{grid-template-columns:1fr}}
</style>

<div class="admin-edit-wrap" x-data="{
    vertical: @js(old('vertical', $task->vertical)),
    nature: @js(old('task_nature', $task->task_nature)),
    natures: @js($natures)
}">
    <div class="page-head">
        <div>
            <h1>Edit Task</h1>
            <p>{{ $task->task_id }} · Admin changes are applied to the live task.</p>
        </div>
        <a href="{{ route('admin.tasks.show', $task) }}" class="btn btn-secondary">Cancel</a>
    </div>

    @if($errors->any())
        <div class="flash flash-error" style="margin-bottom:14px">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.tasks.update', $task) }}">
        @csrf
        @method('PUT')

        <section class="panel admin-edit-section">
            <div class="panel-header"><div class="panel-title">Task Information</div></div>
            <div class="panel-body">
                <div class="admin-edit-grid">
                    <div class="admin-edit-field">
                        <label>Task Name</label>
                        <input class="premium-input" name="task_name" value="{{ old('task_name',$task->task_name) }}" required>
                    </div>

                    <div class="admin-edit-field">
                        <label>Client / Agency Name</label>
                        <input class="premium-input" name="party_name" value="{{ old('party_name',$task->party_name) }}" required>
                    </div>

                    <div class="admin-edit-field">
                        <label>Client / Agency Type</label>
                        <select class="premium-select" name="party_type" required>
                            <option value="client" @selected(old('party_type',$task->party_type)==='client')>Client</option>
                            <option value="agency" @selected(old('party_type',$task->party_type)==='agency')>Agency</option>
                        </select>
                    </div>

                    <div class="admin-edit-field">
                        <label>Contact Person</label>
                        <input class="premium-input" name="contact_person" value="{{ old('contact_person',$task->contact_person) }}" required>
                    </div>

                    <div class="admin-edit-field">
                        <label>Mobile Number</label>
                        <input class="premium-input" name="mobile_number" value="{{ old('mobile_number',$task->mobile_number) }}" required>
                    </div>

                    <div class="admin-edit-field">
                        <label>Assigned Designer</label>
                        <select class="premium-select" name="designer_id" required>
                            @foreach($designers as $designer)
                                <option value="{{ $designer->id }}" @selected((int)old('designer_id',$task->designer_id)===(int)$designer->id)>
                                    {{ $designer->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="admin-edit-grid-3" style="margin-top:14px">
                    <div class="admin-edit-field">
                        <label>Vertical</label>
                        <select
                            class="premium-select"
                            name="vertical"
                            x-model="vertical"
                            @change="if (!(natures[vertical] || []).includes(nature)) nature=(natures[vertical] || [])[0] || ''"
                            required
                        >
                            @foreach(array_keys($natures) as $verticalKey)
                                <option value="{{ $verticalKey }}">{{ \Illuminate\Support\Str::headline($verticalKey) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="admin-edit-field">
                        <label>Task Nature</label>
                        <select class="premium-select" name="task_nature" x-model="nature" required>
                            <template x-for="item in (natures[vertical] || [])" :key="item">
                                <option :value="item" x-text="item.replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase())"></option>
                            </template>
                        </select>
                    </div>

                    <div class="admin-edit-field">
                        <label>Priority</label>
                        <select class="premium-select" name="priority" required>
                            @foreach(['urgent'=>'Urgent','high'=>'High','medium'=>'Medium','low'=>'Low'] as $key=>$label)
                                <option value="{{ $key }}" @selected(old('priority',$task->priority)===$key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="admin-edit-field">
                        <label>Total Creatives</label>
                        <input class="premium-input" type="number" min="1" max="9999" name="total_creatives" value="{{ old('total_creatives',$task->total_creatives) }}" required>
                    </div>

                    <div class="admin-edit-field">
                        <label>Due Date & Time</label>
                        <input class="premium-input" type="datetime-local" name="due_at" value="{{ old('due_at',$task->due_at?->format('Y-m-d\TH:i')) }}" required>
                    </div>

                    <div class="admin-edit-field">
                        <label>Status</label>
                        <select class="premium-select" name="status" required>
                            @foreach($statuses as $statusKey=>$statusLabel)
                                <option value="{{ $statusKey }}" @selected(old('status',$task->status)===$statusKey)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel admin-edit-section">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Requirement Details</div>
                    <div style="font-size:10px;color:#667085;margin-top:3px">System metadata and uploaded files are protected.</div>
                </div>
            </div>
            <div class="panel-body">
                @php
                    $requirements = collect($task->requirements ?? [])
                        ->reject(fn($value,$key) => str_starts_with((string)$key,'_'));
                @endphp

                @forelse($requirements as $key=>$value)
                    @php
                        $isFile = is_string($value) && str_contains($value,'/');
                        $isFileArray = is_array($value)
                            && collect($value)->contains(fn($item) => is_string($item) && str_contains($item,'/'));
                    @endphp

                    <div class="requirement-edit-row">
                        <div class="admin-edit-field">
                            <label>{{ \Illuminate\Support\Str::headline((string)$key) }}</label>

                            @if($isFile || $isFileArray)
                                <div class="readonly-file">Existing uploaded file{{ $isFileArray ? 's' : '' }} retained.</div>
                            @elseif(is_array($value))
                                <textarea class="premium-input" style="min-height:90px" name="requirements[{{ $key }}]">{{ old('requirements.'.$key,json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)) }}</textarea>
                            @else
                                <input class="premium-input" name="requirements[{{ $key }}]" value="{{ old('requirements.'.$key,$value) }}">
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty-state">No editable requirement details.</div>
                @endforelse
            </div>
        </section>

        <div class="admin-edit-note">
            Changing Status or Assigned Designer directly is an Admin override. Use it only when a correction is required.
        </div>

        <div class="save-bar">
            <a href="{{ route('admin.tasks.show',$task) }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary" type="submit">Save Changes</button>
        </div>
    </form>
</div>
@endsection
