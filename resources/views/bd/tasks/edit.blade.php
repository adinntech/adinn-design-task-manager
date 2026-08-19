@extends('layouts.app')

@section('title','Edit '.$task->task_id)
@section('workspace-title','BD Task Edit')
@section('workspace-subtitle','Update allowed task information and complete requirement details')

@section('content')
<style>
    .edit-wrap{max-width:1180px}
    .edit-section{margin-bottom:16px}
    .edit-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .edit-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
    .edit-field label{display:block;font-size:9px;font-weight:900;color:#344054;margin-bottom:6px}
    .edit-readonly{min-height:42px;padding:10px 11px;border:1px solid #e4e7ec;border-radius:10px;background:#f8fafc}
    .edit-readonly span{display:block;font-size:8px;font-weight:850;color:#667085;text-transform:uppercase;letter-spacing:.03em}
    .edit-readonly strong{display:block;margin-top:4px;font-size:10px;line-height:1.4;color:#101828}
    .edit-help{font-size:8px;color:#667085;margin-top:5px;line-height:1.45}
    .edit-permission-note{display:flex;gap:9px;align-items:flex-start;padding:11px 12px;border:1px solid #fedf89;background:#fffaeb;border-radius:11px;color:#93370d;font-size:9px;line-height:1.55;margin-bottom:14px}
    .requirement-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .requirement-field{padding:12px;border:1px solid #e7e9ee;border-radius:11px;background:#fff;min-width:0}
    .requirement-field.is-wide{grid-column:1/-1}
    .requirement-empty{font-size:8px;color:#98a2b3;margin-top:5px}
    .file-current{display:flex;flex-direction:column;gap:6px;margin-bottom:8px}
    .file-current-row{display:flex;justify-content:space-between;gap:9px;align-items:center;padding:8px 9px;border:1px solid #eaecf0;border-radius:8px;background:#fafbfc}
    .file-current-name{min-width:0;font-size:9px;font-weight:750;color:#344054;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .file-remove{font-size:8px;color:#b42318;font-weight:850;white-space:nowrap}
    .save-bar{position:sticky;bottom:12px;z-index:30;margin-top:18px;padding:12px;border:1px solid #e5e7eb;border-radius:14px;background:rgba(255,255,255,.96);backdrop-filter:blur(10px);box-shadow:0 12px 35px rgba(16,24,40,.12);display:flex;justify-content:flex-end;gap:8px}
    @media(max-width:850px){.edit-grid,.edit-grid-3,.requirement-grid{grid-template-columns:1fr}.requirement-field.is-wide{grid-column:auto}}
</style>

<div class="edit-wrap">
    <div class="page-head">
        <div>
            <h1>Edit Task</h1>
            <p>{{ $task->task_id }} · Only Deadline, Priority and Creative Count can be changed in Task Information.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('bd.tasks.show', $task) }}" class="btn btn-secondary">Cancel</a>
        </div>
    </div>

    <div class="edit-permission-note">
        <strong>Editing rule:</strong>
        <span>Task Information is protected except for Deadline, Priority and Creative Count. Requirement Details remain editable so missing information can be completed later.</span>
    </div>

    @if($errors->any())
        <div class="panel edit-section">
            <div class="panel-body">
                <div style="color:#b42318;font-weight:900;font-size:11px;margin-bottom:7px">Please correct the highlighted information.</div>
                @foreach($errors->all() as $error)
                    <div style="font-size:9px;color:#b42318;margin-top:3px">{{ $error }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('bd.tasks.update', $task) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <section class="panel edit-section">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Task Information</div>
                    <div style="font-size:9px;color:#667085;margin-top:3px">Protected information is shown for reference only.</div>
                </div>
            </div>

            <div class="panel-body">
                <div class="edit-grid">
                    @foreach([
                        'Task Name' => $task->task_name,
                        'Client / Agency Name' => $task->party_name,
                        'Client / Agency Type' => ucfirst($task->party_type),
                        'Contact Person' => $task->contact_person ?: '—',
                        'Mobile Number' => $task->mobile_number ?: '—',
                        'Assigned Designer' => $task->designer?->name ?? '—',
                        'Vertical' => match ($task->vertical) {
                            'roadshow' => 'Road Show',
                            'pop_offsets' => 'Print / POP',
                            'events_activations' => 'Events & Activations',
                            default => \Illuminate\Support\Str::headline($task->vertical),
                        },
                        'Task Nature' => match ($task->task_nature) {
                            'creative_adaptation_requirements', 'creative_adaptation', 'design_with_creative', 'design_adaptation', 'element_design_with_creative' => 'Creative Adaptation',
                            'new_creative_design', 'own_creative', 'design_without_creative', 'new_creative', 'creative_design', 'element_design_without_creative' => 'Own Creative',
                            'mockup_requirements', 'mockup', 'mockup_design' => 'Mockup',
                            'technical_drawing' => 'Technical Drawing',
                            'three_d_design' => '3D Design',
                            'technical_and_three_d' => 'Technical Drawing + 3D Design',
                            'three_d_layout' => '3D Layout',
                            'proposal_designs' => 'Proposal Design',
                            default => \Illuminate\Support\Str::headline($task->task_nature),
                        },
                    ] as $label => $value)
                        <div class="edit-readonly">
                            <span>{{ $label }}</span>
                            <strong>{{ $value }}</strong>
                        </div>
                    @endforeach
                </div>

                <div class="edit-grid-3" style="margin-top:14px">
                    <div class="edit-field">
                        <label>Priority</label>
                        <select class="premium-input" name="priority" required>
                            @foreach(['low','medium','high','urgent'] as $priority)
                                <option value="{{ $priority }}" @selected(old('priority', $task->priority) === $priority)>{{ ucfirst($priority) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="edit-field">
                        <label>Creative Count</label>
                        <input
                            class="premium-input"
                            type="number"
                            min="{{ max(1, $completedCreatives) }}"
                            max="9999"
                            name="total_creatives"
                            value="{{ old('total_creatives', $task->total_creatives) }}"
                            required
                        >
                        @if($completedCreatives > 0)
                            <div class="edit-help">{{ $completedCreatives }} creative(s) are already completed, so the count cannot be reduced below this value.</div>
                        @endif
                    </div>

                    <div class="edit-field">
                        <label>Deadline</label>
                        <input
                            class="premium-input"
                            type="datetime-local"
                            name="due_at"
                            value="{{ old('due_at', optional($task->due_at)->format('Y-m-d\TH:i')) }}"
                            min="{{ $minDueDate }}"
                            max="{{ $maxDueDate }}"
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
                    <div style="font-size:9px;color:#667085;margin-top:3px">
                        Every field for {{ \Illuminate\Support\Str::headline($task->vertical) }} · {{ \Illuminate\Support\Str::headline($task->task_nature) }} is shown, including fields that are currently empty.
                    </div>
                </div>
                <span class="badge badge-dark">{{ count($requirementFields) }} Fields</span>
            </div>

            <div class="panel-body">
                <div class="requirement-grid">
                    @forelse($requirementFields as $field)
                        @php
                            $key = $field['key'];
                            $type = $field['type'];
                            $options = $field['options'] ?? [];
                            $value = old('requirements.'.$key, data_get($task->requirements ?? [], $key));
                            if ($key === 'media_type' && blank($value) && $task->vertical === 'media') {
                                $value = match ($task->task_nature) {
                                    'theatre_ads' => 'Theatre Ads',
                                    'newspaper_ads' => 'Newspaper Ads',
                                    'tv_ads' => 'TV Ads',
                                    default => $value,
                                };
                            }
                            $fileGroup = $requirementAttachmentGroups[$key] ?? null;
                            $wide = in_array($type, ['textarea','dimensions','sizes','media_sizes','file','files','mediafiles','audio'], true);
                        @endphp

                        <div class="requirement-field {{ $wide ? 'is-wide' : '' }}">
                            <div class="edit-field">
                                <label>{{ $field['label'] }}</label>

                                @if(in_array($type, ['file','files','mediafiles','audio'], true))
                                    @if($fileGroup && !empty($fileGroup['files']))
                                        <div class="file-current">
                                            @foreach($fileGroup['files'] as $file)
                                                <label class="file-current-row">
                                                    <span class="file-current-name" title="{{ $file['name'] }}">{{ $file['name'] }}</span>
                                                    <span class="file-remove">
                                                        <input
                                                            type="checkbox"
                                                            name="remove_requirement_files[{{ $key }}][]"
                                                            value="{{ $file['path'] }}"
                                                        >
                                                        Remove
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="requirement-empty">No file uploaded yet.</div>
                                    @endif

                                    <input
                                        class="premium-input"
                                        type="file"
                                        name="new_requirement_files[{{ $key }}][]"
                                        multiple
                                        data-accumulate-files
                                        @if($type === 'audio') accept="audio/*,.mp3,.wav,.m4a,.aac,.ogg" @endif
                                        @if($type === 'mediafiles') accept="image/*,video/*" @endif
                                    >
                                    <div class="edit-help">Existing files stay unless you select Remove. New files are added to the same requirement.</div>

                                @elseif(in_array($type, ['dimensions','sizes'], true))
                                    @php
                                        $fieldKey = $key;
                                        $rows = is_array($value) ? $value : [];
                                        $rowLabel = $type === 'sizes' ? 'Size' : 'Board';
                                        $addLabel = $type === 'sizes' ? 'Add Size' : 'Add Board';
                                    @endphp
                                    @include('partials.board-details-edit-table', compact('fieldKey','rows','rowLabel','addLabel'))

                                @elseif($type === 'media_sizes')
                                    @php
                                        $fieldKey = $key;
                                        $rows = is_array($value) ? $value : [];
                                    @endphp
                                    @include('partials.creative-size-details-edit-table', compact('fieldKey','rows'))

                                @elseif($type === 'textarea')
                                    <textarea
                                        class="premium-input"
                                        style="min-height:96px"
                                        name="requirements[{{ $key }}]"
                                        placeholder="Enter {{ strtolower($field['label']) }}"
                                    >{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $value }}</textarea>

                                @elseif(in_array($type, ['select','vehicle_select'], true))
                                    <select class="premium-input" name="requirements[{{ $key }}]">
                                        <option value="">Select</option>
                                        @foreach($options as $option)
                                            <option value="{{ $option }}" @selected((string)$value === (string)$option)>{{ $option }}</option>
                                        @endforeach
                                    </select>

                                @else
                                    <input
                                        class="premium-input"
                                        type="{{ $type === 'number' ? 'number' : ($type === 'url' ? 'url' : 'text') }}"
                                        @if($type === 'number') step="any" @endif
                                        name="requirements[{{ $key }}]"
                                        value="{{ is_scalar($value) ? $value : '' }}"
                                        placeholder="Enter {{ strtolower($field['label']) }}"
                                    >
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="empty-state" style="grid-column:1/-1">No requirement configuration is available for this Task Nature.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <div class="save-bar">
            <a href="{{ route('bd.tasks.show', $task) }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>
@endsection
