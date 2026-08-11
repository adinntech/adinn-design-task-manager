@extends('layouts.app')

@section('title','Edit '.$task->task_id)
@section('workspace-title','BD Task Edit')
@section('workspace-subtitle','Update task information while preserving a complete edit trail')

@section('content')
<div class="page-head">
    <div>
        <h1>Edit Task</h1>
        <p>{{ $task->task_id }} · Every saved change will be recorded in Edit History.</p>
    </div>

    <div class="page-actions">
        <a href="{{ route('bd.tasks.show', $task) }}" class="btn btn-secondary">Cancel</a>
    </div>
</div>

<style>
    .edit-wrap{max-width:1180px}
    .edit-section{margin-bottom:14px}
    .edit-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .edit-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
    .edit-field label{display:block;font-size:11px;font-weight:800;color:#344054;margin-bottom:6px}
    .edit-help{font-size:10px;color:#667085;margin-top:5px}
    .edit-note{padding:12px 14px;border:1px solid #fed7d7;background:#fff7f7;border-radius:11px;color:#7a271a;font-size:11px;line-height:1.55}
    .file-readonly{padding:10px 12px;border-radius:9px;background:#f7f8fa;border:1px solid #eaecf0;font-size:10px;color:#667085}
    .requirement-edit-row{padding:12px 0;border-bottom:1px solid #eef0f3}
    .requirement-edit-row:last-child{border-bottom:0}
    .save-bar{position:sticky;bottom:12px;z-index:30;margin-top:18px;padding:12px;border:1px solid #e5e7eb;border-radius:14px;background:rgba(255,255,255,.96);backdrop-filter:blur(10px);box-shadow:0 12px 35px rgba(16,24,40,.12);display:flex;justify-content:flex-end;gap:8px}
    @media(max-width:850px){.edit-grid,.edit-grid-3{grid-template-columns:1fr}}
</style>

<div class="edit-wrap" x-data="{
    vertical: @js(old('vertical', $task->vertical)),
    nature: @js(old('task_nature', $task->task_nature)),
    natures: @js($natures)
}">
    @if($errors->any())
        <div class="panel edit-section">
            <div class="panel-body">
                <div style="color:#b42318;font-weight:800;font-size:12px;margin-bottom:7px">Please correct the highlighted information.</div>
                @foreach($errors->all() as $error)
                    <div style="font-size:10px;color:#b42318;margin-top:3px">{{ $error }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('bd.tasks.update', $task) }}">
        @csrf
        @method('PUT')

        <section class="panel edit-section">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Task Information</div>
                    <div style="font-size:10px;color:#667085;margin-top:3px">Edit the information that needs correction.</div>
                </div>
            </div>

            <div class="panel-body">
                <div class="edit-grid">
                    <div class="edit-field">
                        <label>Task Name</label>
                        <input class="premium-input" name="task_name" value="{{ old('task_name', $task->task_name) }}" required>
                    </div>

                    <div class="edit-field">
                        <label>Client / Agency Name</label>
                        <input class="premium-input" name="party_name" value="{{ old('party_name', $task->party_name) }}" required>
                    </div>

                    <div class="edit-field">
                        <label>Client / Agency Type</label>
                        <select class="premium-select" name="party_type" required>
                            <option value="client" @selected(old('party_type', $task->party_type) === 'client')>Client</option>
                            <option value="agency" @selected(old('party_type', $task->party_type) === 'agency')>Agency</option>
                        </select>
                    </div>

                    <div class="edit-field">
                        <label>Contact Person</label>
                        <input class="premium-input" name="contact_person" value="{{ old('contact_person', $task->contact_person) }}" required>
                    </div>

                    <div class="edit-field">
                        <label>Mobile Number</label>
                        <input class="premium-input" name="mobile_number" value="{{ old('mobile_number', $task->mobile_number) }}" maxlength="10" required>
                    </div>

                    <div class="edit-field">
                        <label>Assigned Designer</label>
                        <select class="premium-select" name="designer_id" required>
                            @foreach($designers as $designer)
                                <option value="{{ $designer->id }}" @selected((int) old('designer_id', $task->designer_id) === (int) $designer->id)>
                                    {{ $designer->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="edit-grid-3" style="margin-top:14px">
                    <div class="edit-field">
                        <label>Vertical</label>
                        <select
                            class="premium-select"
                            name="vertical"
                            x-model="vertical"
                            @change="if (!(natures[vertical] || []).includes(nature)) nature = (natures[vertical] || [])[0] || ''"
                            required
                        >
                            @foreach(array_keys($natures) as $verticalKey)
                                <option value="{{ $verticalKey }}">{{ \Illuminate\Support\Str::headline($verticalKey) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="edit-field">
                        <label>Task Nature</label>
                        <select class="premium-select" name="task_nature" x-model="nature" required>
                            <template x-for="item in (natures[vertical] || [])" :key="item">
                                <option :value="item" x-text="item.replaceAll('_',' ').replace(/\b\w/g, c => c.toUpperCase())"></option>
                            </template>
                        </select>
                    </div>

                    <div class="edit-field">
                        <label>Priority</label>
                        <select class="premium-select" name="priority" required>
                            @foreach(['low','medium','high','urgent'] as $priority)
                                <option value="{{ $priority }}" @selected(old('priority', $task->priority) === $priority)>{{ ucfirst($priority) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="edit-field">
                        <label>Total Creatives</label>
                        <input class="premium-input" type="number" min="1" max="9999" name="total_creatives" value="{{ old('total_creatives', $task->total_creatives) }}" required>
                    </div>

                    <div class="edit-field">
                        <label>Due Date & Time</label>
                        <input
                            class="premium-input"
                            type="datetime-local"
                            name="due_at"
                            value="{{ old('due_at', optional($task->due_at)->format('Y-m-d\TH:i')) }}"
                            required
                        >
                    </div>
                </div>
            </div>
        </section>

        <section class="panel edit-section">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Requirement Details</div>
                    <div style="font-size:10px;color:#667085;margin-top:3px">Existing uploaded files are preserved and cannot be accidentally removed here.</div>
                </div>
            </div>

            <div class="panel-body">
                @php
                    $editableRequirements = collect($task->requirements ?? [])
                        ->reject(fn ($value, $key) => str_starts_with((string) $key, '_'));
                @endphp

                @forelse($editableRequirements as $key => $value)
                    <div class="requirement-edit-row">
                        <div class="edit-field">
                            <label>{{ \Illuminate\Support\Str::headline((string) $key) }}</label>

                            @php
                                $isFileValue = is_string($value) && str_contains($value, '/');
                                $isFileArray = is_array($value)
                                    && collect($value)->contains(fn ($item) => is_string($item) && str_contains($item, '/'));
                            @endphp

                            @if($isFileValue || $isFileArray)
                                <div class="file-readonly">
                                    Existing uploaded file{{ $isFileArray ? 's' : '' }} retained. File replacement is not included in this edit action.
                                </div>
                            @elseif(is_array($value))
                                <textarea
                                    class="premium-input"
                                    style="min-height:96px"
                                    name="requirements[{{ $key }}]"
                                >{{ old('requirements.'.$key, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) }}</textarea>
                                <div class="edit-help">Structured requirement value. Edit only when correction is required.</div>
                            @else
                                <input
                                    class="premium-input"
                                    name="requirements[{{ $key }}]"
                                    value="{{ old('requirements.'.$key, $value) }}"
                                >
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty-state">No editable requirement fields are available.</div>
                @endforelse
            </div>
        </section>

        <div class="edit-note">
            <strong>Edit tracking:</strong> Only fields whose values actually change will be added to Edit History. The previous value will remain permanently visible alongside the new value.
        </div>

        <div class="save-bar">
            <a href="{{ route('bd.tasks.show', $task) }}" class="btn btn-secondary">Cancel</a>
            <button
                type="submit"
                class="btn btn-primary"
                onclick="this.disabled=true;this.innerText='Saving Changes...';this.form.submit();"
            >
                Save Changes
            </button>
        </div>
    </form>
</div>
@endsection
