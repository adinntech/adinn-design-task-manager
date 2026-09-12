@extends('layouts.app')
@section('title','Create Design Task')
@section('workspace-title','Create Design Task')
@section('workspace-subtitle','Capture client requirements and assign the task to a Designer')
@section('content')

@php
    $actorRole = $actorRole ?? 'bd';
    $isDesignerActor = $actorRole === 'designer';

    $maxDueDate = now()->copy()->startOfDay();
    $workingDaysAdded = 0;

    while ($workingDaysAdded < 7) {
        $maxDueDate->addDay();

        if (! $maxDueDate->isWeekend()) {
            $workingDaysAdded++;
        }
    }

    $minDueDate = now()->format('Y-m-d\TH:i');
    $maxDueDateInput = $maxDueDate->endOfDay()->format('Y-m-d\TH:i');

    $draftValues = [];

    if (isset($draft) && $draft) {
        $draftReq = $draft->requirements && is_array($draft->requirements) ? $draft->requirements : [];

        $draftValues = [
            'task_name' => $draft->task_name,
            'vertical' => $draft->vertical,
            'zoho_project_number' => $draft->zoho_project_number,
            'task_nature' => $draft->task_nature,
            'party_type' => $draft->party_type,
            'party_name' => $draft->party_name,
            'contact_person' => $draft->contact_person,
            'mobile_number' => $draft->mobile_number,
            'priority' => $draft->priority,
            'due_at' => $draft->due_at ? \Illuminate\Support\Carbon::parse($draft->due_at)->format('Y-m-d\TH:i') : '',
            'designer_id' => $draft->designer_id,
            'total_creatives' => $draft->total_creatives,
            'dimension_rows' => $draftReq['board_details'] ?? [],
            'size_rows' => $draftReq['size_details'] ?? [],
            'media_size_rows' => $draftReq['creative_size_details'] ?? [],
        ];

        unset(
            $draftReq['board_details'],
            $draftReq['size_details'],
            $draftReq['creative_size_details'],
            $draftReq['dimension_rows'],
            $draftReq['size_rows'],
            $draftReq['media_size_rows']
        );

        $draftValues = array_merge($draftValues, $draftReq);
    }

    $formValues = array_merge($draftValues, old());

    $jsFormValues = $formValues;
    unset($jsFormValues['_token'], $jsFormValues['_method'], $jsFormValues['draft_id']);
@endphp

<style>
.live-field-error{margin-top:5px;font-size:11px;font-weight:700;color:#b42318}
.field.has-error{border-color:#f04438!important;box-shadow:0 0 0 3px rgba(240,68,56,.08)}
.dimension-card{grid-column:1/-1;border:1px solid #e4e7ec;border-radius:14px;background:#fafafa;padding:15px}
.dimension-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:12px}
.dimension-title{font-size:12px;font-weight:900;color:#1d2939}
.dimension-subtitle{font-size:10px;color:#667085;margin-top:3px}
.dimension-row{display:grid;grid-template-columns:minmax(150px,1.4fr) minmax(100px,.8fr) minmax(100px,.8fr) minmax(120px,.9fr) auto;gap:9px;align-items:end;margin-top:9px}
.dimension-row .label{font-size:9px;margin-bottom:4px}
.dimension-area{background:#f2f4f7}
.dimension-remove{height:42px;min-width:42px;border:1px solid #fecaca;background:#fff;color:#b42318;border-radius:10px;font-weight:900;cursor:pointer}
.dimension-upload{margin-top:14px;padding:12px;border:1px dashed #d0d5dd;border-radius:11px;background:#fff}
@media(max-width:900px){.dimension-row{grid-template-columns:1fr 1fr}.dimension-row>div:first-child{grid-column:1/-1}.dimension-remove{width:100%}}

.vehicle-preview-card{grid-column:1/-1;border:1px solid #e4e7ec;border-radius:14px;background:#fff;padding:14px;margin-top:8px}
.vehicle-preview-inner{display:grid;grid-template-columns:minmax(0,1fr) 220px;gap:16px;align-items:center}
.vehicle-preview-copy strong{display:block;font-size:12px;color:#1d2939;margin-bottom:4px}
.vehicle-preview-copy span{font-size:10px;color:#667085}
.vehicle-preview-image-wrap{border:1px solid #eaecf0;border-radius:12px;background:#f8fafc;overflow:hidden;display:flex;align-items:center;justify-content:center;min-height:140px}
.vehicle-preview-image{display:block;width:100%;height:140px;object-fit:contain;background:#fff}
@media(max-width:900px){.vehicle-preview-inner{grid-template-columns:1fr}.vehicle-preview-image{height:180px}}

    .standard-form-section{
        margin-top:8px;
        padding-top:16px;
        border-top:1px solid #e8ebf0;
    }
    .standard-form-section:first-child{margin-top:0;padding-top:0;border-top:0}
    .standard-form-section-title{
        font-size:12px;
        font-weight:950;
        color:#101828;
        letter-spacing:-.01em;
    }
    .media-size-card{
        grid-column:1/-1;
        border:1px solid #e4e7ec;
        border-radius:13px;
        background:#fff;
        padding:14px;
    }
    .media-size-head{
        display:flex;
        justify-content:space-between;
        gap:12px;
        align-items:center;
        margin-bottom:12px;
    }
    .media-size-title{font-size:11px;font-weight:900;color:#101828}
    .media-size-subtitle{font-size:9px;color:#667085;margin-top:3px}
    .media-size-row{
        display:grid;
        grid-template-columns:minmax(170px,1.35fr) minmax(90px,.7fr) minmax(90px,.7fr) minmax(90px,.7fr) 48px;
        gap:9px;
        align-items:end;
        margin-top:9px;
    }
    .media-size-remove{
        height:42px;
        border:1px solid #fecaca;
        background:#fff1f2;
        color:#b42318;
        border-radius:9px;
        font-size:18px;
        font-weight:800;
        cursor:pointer;
    }
    @media(max-width:850px){
        .media-size-row{grid-template-columns:1fr 1fr}
        .media-size-remove{width:48px}
    }

    .designer-availability{margin-top:8px;padding:10px 12px;border:1px solid #e4e7ec;border-radius:10px;background:#fafafa}
    .designer-availability-name{font-size:11px;font-weight:900;color:#101828;margin-bottom:4px}
    .designer-availability-meta{display:flex;justify-content:space-between;gap:8px;font-size:10px;color:#475467;margin-bottom:6px}
    .designer-availability-track{height:8px;border-radius:999px;background:#eaecf0;overflow:hidden}
    .designer-availability-fill{height:100%;border-radius:999px;width:0;transition:width .3s ease}
    .designer-availability-fill.available{background:#16c60c}
    .designer-availability-fill.moderate{background:#e9961a}
    .designer-availability-fill.busy{background:#f7630c}
    .designer-availability-fill.critical{background:#e81224}
    .designer-availability-status{margin-top:6px;font-size:10px;font-weight:900}
    .designer-availability-status.available{color:#16c60c}
    .designer-availability-status.moderate{color:#e9961a}
    .designer-availability-status.busy{color:#f7630c}
    .designer-availability-status.critical{color:#e81224}
    .designer-availability-note{font-size:10px;color:#667085;font-style:italic}
    .designer-checkin-label{margin-top:8px;font-size:10px;color:#475467}
    .designer-checkin-status{font-size:10px;font-weight:900}
    .designer-checkin-status.checked-in{color:#16c60c}
    .designer-checkin-status.not-checked-in{color:#e81224}
    .designer-checkin-status.unknown{color:#667085}
    .designer-profile-list{margin-top:8px;max-height:190px;overflow-y:auto;border:1px solid #e4e7ec;border-radius:10px}
    .designer-profile-card{display:block;width:100%;text-align:left;padding:8px 12px;border:0;border-bottom:1px solid #eef0f3;background:#fff;cursor:pointer}
    .designer-profile-card:last-child{border-bottom:0}
    .designer-profile-card:hover{background:#f9fafb}
    .designer-profile-card.is-selected{background:#fff1f0;box-shadow:inset 3px 0 0 #e30613}
    .designer-profile-name{font-size:11px;font-weight:900;color:#101828}
    .designer-profile-meta{font-size:9.5px;color:#667085;margin-top:2px}
    .designer-profile-meta strong{color:#475467}

    .draft-attached-files{margin-bottom:8px;display:flex;flex-direction:column;gap:6px}
    .draft-attached-file{display:flex;align-items:center;gap:10px;padding:7px 10px;border:1px solid #e4e7ec;border-radius:9px;background:#f7fdf8;font-size:11px}
    .draft-attached-file span{flex:1;color:#101828;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .draft-attached-file a{color:#175cd3;font-weight:800;text-decoration:none;font-size:10px}
    .draft-attached-remove{border:1px solid #fecaca;background:#fff1f2;color:#b42318;border-radius:7px;font-size:10px;font-weight:800;padding:3px 8px;cursor:pointer}

</style>

<div class="page-head">
    <div>
        <h1>{{ isset($draft) && $draft ? 'Edit Design Task Draft' : 'Create Design Task' }}</h1>
        <p>{{ isset($draft) && $draft ? 'Finish this draft and click "Create Task" to assign it, or keep saving your progress.' : 'Select a vertical and task nature to load the relevant requirement form.' }}</p>
    </div>
    <a href="{{ route($isDesignerActor ? 'designer.tasks.index' : 'bd.tasks.index') }}" class="btn btn-secondary">Back to {{ $isDesignerActor ? 'My Tasks' : 'Assigned Tasks' }}</a>
</div>

@if(isset($draft) && $draft)
<div class="mb-6 bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
    <div>
        <p class="font-semibold text-amber-900">Draft {{ $draft->task_id }}</p>
        <p class="text-sm text-amber-800 mt-1">This task is saved as a draft and is only visible to you. Click <strong>Create Task</strong> to finalize and assign it to a designer, or <strong>Save as Draft</strong> to keep working on it later. Previously uploaded draft files are preserved automatically.</p>
    </div>
</div>
@endif

@if($errors->any())
<div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4">
    <p class="font-semibold text-red-800">Please correct the highlighted fields.</p>
    <ul class="text-sm text-red-700 mt-2 list-disc ml-5">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route($isDesignerActor ? 'designer.tasks.store' : 'bd.tasks.store') }}" enctype="multipart/form-data" id="taskForm" class="space-y-6" novalidate>
@csrf
<input type="hidden" name="draft_id" id="draftIdInput" value="{{ old('draft_id', isset($draft) && $draft ? $draft->id : '') }}">
<input type="hidden" name="_method" id="methodInput" value="">
<input type="hidden" name="removed_files_json" id="removedFilesInput" value="{}">
<section class="panel panel-body">
    <h2 class="text-lg font-bold mb-5">Common Task Details</h2>
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div><label class="label">Task ID</label><input class="field bg-slate-100" value="Auto-generated after submission" disabled></div>
        <div><label class="label">Assigned At</label><input class="field bg-slate-100" value="{{ now()->format('d M Y') }}" disabled></div>
        <div><label class="label">Assigned By</label><input class="field bg-slate-100" value="{{ auth()->user()->name }}" disabled></div>

        <div class="lg:col-span-2"><label class="label">Task Name *</label><input class="field" name="task_name" value="{{ $formValues['task_name'] ?? '' }}" required></div>
        <div>
            <label class="label">Vertical *</label>
            <select class="field" id="vertical" name="vertical" required>
                <option value="">Select vertical</option>
                @foreach(\App\Http\Controllers\Bd\TaskController::VERTICALS as $value=>$label)
                    <option value="{{ $value }}" @selected(($formValues['vertical']??'')===$value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div><label class="label">Client / Agency *</label><select class="field" name="party_type" id="partyType" required><option value="client" @selected(($formValues['party_type']??'')==='client')>Client</option><option value="agency" @selected(($formValues['party_type']??'')==='agency')>Agency</option></select></div>
        <div><label class="label" id="partyNameLabel">Client Name *</label><input class="field" name="party_name" value="{{ $formValues['party_name'] ?? '' }}" required></div>
        <div>
            <label class="label" for="contact_person">Contact Person Name</label>
            <input class="field" id="contact_person" name="contact_person" type="text" maxlength="100"
                   value="{{ $formValues['contact_person'] ?? '' }}" placeholder="Enter contact person name">
        </div>

        <div>
            <label class="label" for="mobile_number">Mobile Number</label>
            <input class="field" id="mobile_number" name="mobile_number" type="text"
                   inputmode="numeric" pattern="[0-9]{10}" minlength="10" maxlength="10"
                   autocomplete="tel" value="{{ $formValues['mobile_number'] ?? '' }}"
                   placeholder="Enter 10-digit mobile number"
                   oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)">
        </div>

        <div><label class="label">Priority *</label><select class="field" name="priority" required>@foreach(['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent'] as $v=>$l)<option value="{{ $v }}" @selected(($formValues['priority']??'')===$v)>{{ $l }}</option>@endforeach</select></div>
        <div><label class="label">Due Date & Time *</label><input class="field" id="dueAt" type="datetime-local" name="due_at" value="{{ $formValues['due_at'] ?? '' }}" required
            min="{{ $minDueDate }}"
            max="{{ $maxDueDateInput }}"
        ></div>
        <div>
            <label class="label" for="zoho_project_number">Zoho Project Number</label>
            <input class="field" id="zoho_project_number" name="zoho_project_number" type="text" maxlength="60" placeholder="ZOHO-12345" value="{{ $formValues['zoho_project_number'] ?? '' }}">
        </div>

        <div>
            <label class="label" for="total_creatives">Total Number of Creatives *</label>
            <input class="field" id="total_creatives" name="total_creatives" type="number"
                   min="1" max="9999" step="1" inputmode="numeric"
                   value="{{ $formValues['total_creatives'] ?? '' }}" placeholder="Enter total creatives"
                   oninput="if(this.value!==''){this.value=Math.max(1,Math.min(9999,Math.trunc(Number(this.value)||1)));}" required>
        </div>

        <div>
            <label class="label" for="taskNature">Task Nature *</label>
            <select class="field" id="taskNature" name="task_nature" required disabled>
                <option value="">Select vertical first</option>
            </select>
        </div>

        <div>
        @if($isDesignerActor)
            <label class="label">Assigned BD *</label>
            <select class="field" id="designerSelect" name="bd_id" required><option value="">Select BD</option>@foreach($assignees as $bd)<option value="{{ $bd->id }}" @selected((string)($formValues['bd_id']??'')===(string)$bd->id)>{{ $bd->name }}</option>@endforeach</select>
            <div id="designerProfiles" class="designer-profile-list">
                @foreach($assignees as $bd)
                    @php
                        $bdVerticalLabels = collect($bd->experienced_verticals ?? [])
                            ->map(fn ($v) => \App\Http\Controllers\Bd\TaskController::VERTICALS[$v] ?? $v)
                            ->implode(', ');
                    @endphp
                    <button
                        type="button"
                        class="designer-profile-card {{ (string)($formValues['bd_id']??'')===(string)$bd->id ? 'is-selected' : '' }}"
                        data-designer-id="{{ $bd->id }}"
                    >
                        <div class="designer-profile-name">{{ $bd->name }}</div>
                        <div class="designer-profile-meta"><strong>Verticals:</strong> {{ $bdVerticalLabels ?: 'No verticals assigned' }}</div>
                    </button>
                @endforeach
            </div>
        @else
            <label class="label">Designer Name *</label>
            <select class="field" id="designerSelect" name="designer_id" required><option value="">Select designer</option>@foreach($designers as $designer)<option value="{{ $designer->id }}" @selected((string)($formValues['designer_id']??'')===(string)$designer->id)>{{ $designer->name }}</option>@endforeach</select>
            <div id="designerProfiles" class="designer-profile-list">
                @foreach($designers as $designer)
                    @php
                        $designerVerticalLabels = collect($designer->experienced_verticals ?? [])
                            ->map(fn ($v) => \App\Http\Controllers\Bd\TaskController::VERTICALS[$v] ?? $v)
                            ->implode(', ');
                        $designerSkillLabels = implode(', ', $designer->skills ?? []);
                    @endphp
                    <button
                        type="button"
                        class="designer-profile-card {{ (string)($formValues['designer_id']??'')===(string)$designer->id ? 'is-selected' : '' }}"
                        data-designer-id="{{ $designer->id }}"
                    >
                        <div class="designer-profile-name">{{ $designer->name }}</div>
                        <div class="designer-profile-meta"><strong>Experienced:</strong> {{ $designerVerticalLabels ?: '—' }}</div>
                        <div class="designer-profile-meta"><strong>Skills:</strong> {{ $designerSkillLabels ?: '—' }}</div>
                    </button>
                @endforeach
            </div>
            <div id="designerAvailability" class="designer-availability hidden"></div>
        @endif
        </div>
    </div>
</section>

<section id="dynamicSection" class="panel panel-body hidden">
    <div class="mb-5">
        <h2 id="dynamicTitle" class="text-lg font-bold">Requirements</h2>
        <p class="text-sm text-slate-500">Fields are loaded based on the selected vertical and task nature.</p>
    </div>

    <div id="dynamicFields" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
</section>

<div class="flex justify-end gap-3">
    <button type="reset" class="btn btn-secondary">Clear</button>
    <button id="submitBtn" disabled class="btn btn-primary disabled:opacity-40">Create Task</button>
    {{-- Kept in the DOM (just hidden) rather than removed for the Designer flow —
         the submit-handling JS below unconditionally reads/toggles this element. --}}
    <button id="draftBtn" value="draft" disabled class="btn btn-secondary disabled:opacity-40 {{ $isDesignerActor ? 'hidden' : '' }}">Save as Draft</button>
</div>
</form>

<datalist id="locationOptions">
    <option value="Chennai"><option value="Coimbatore"><option value="Madurai"><option value="Trichy"><option value="Salem"><option value="Bengaluru"><option value="Kochi"><option value="Hyderabad"><option value="Mumbai"><option value="Delhi">
</datalist>

<script>
const oldValues=@json($jsFormValues);
const oldVertical=@json($formValues['vertical'] ?? null);
const oldNature=@json($formValues['task_nature'] ?? null);

const forms={"outdoor":{"label":"Outdoor","natures":{"mockup_requirements":{"label":"Mockup","fields":[["__section_details","Outdoor / Board Details","section",false,[]],["outdoor_type","Outdoor Type","select",true,["Bus Shelter","Unipole","Standard","Auto Branding","Pole Kiosk","Digital","Signal Post"]],["board_type","Display Type","select",true,["Static","Digital"]],["board_details","Board / Display Size Details","dimensions",true,[]],["mockup_type","Mockup Type","select",true,["Mock-up","Innovative Mock-up"]],["site_photo","Site Photo","file",false,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["website_link","Website Link","url",false,[]],["__section_creative","Creative","section",false,[]],["creative","Creative","file",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]},"creative_adaptation":{"label":"Creative Adaptation","fields":[["__section_details","Outdoor / Board Details","section",false,[]],["outdoor_type","Outdoor Type","select",true,["Bus Shelter","Unipole","Standard","Auto Branding","Pole Kiosk","Digital","Signal Post"]],["board_type","Display Type","select",true,["Static","Digital"]],["board_details","Board / Display Size Details","dimensions",true,[]],["site_photo","Site Photo","file",false,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["creative","Creative","file",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]},"new_creative_design":{"label":"Own Creative","fields":[["__section_details","Outdoor / Board Details","section",false,[]],["outdoor_type","Outdoor Type","select",true,["Bus Shelter","Unipole","Standard","Auto Branding","Pole Kiosk","Digital","Signal Post"]],["board_type","Display Type","select",true,["Static","Digital"]],["board_details","Board / Display Size Details","dimensions",true,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["brand_name","Brand Name","text",true,[]],["creative_contact_person","Contact Person","text",false,[]],["creative_mobile_number","Mobile Number","text",false,[]],["address","Address","textarea",false,[]],["company_details_document","Company Details Document","file",false,[]],["__section_creative","Creative","section",false,[]],["content_images","Creative Content / Assets","mediafiles",false,[]],["logo_images","Logo / Brand Assets","files",false,[]],["creative","Creative","files",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]},"cutout_size_calculation":{"label":"3D Cutout Size Calculation","fields":[["__section_details","Outdoor / Board Details","section",false,[]],["outdoor_type","Outdoor Type","select",true,["Bus Shelter","Unipole","Standard","Auto Branding","Pole Kiosk","Digital","Signal Post"]],["board_details","Board / Display Size Details","dimensions",true,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["hoarding_artwork","Creative / Artwork","file",true,[]],["creative","Creative","files",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]}}},"roadshow":{"label":"Road Show","natures":{"creative_adaptation_requirements":{"label":"Creative Adaptation","fields":[["__section_details","Vehicle / Campaign Details","section",false,[]],["roadshow_subtype","Road Show Type","select",true,["Creative Adaptation","3D Mockup Creative Adaptation"]],["vehicle_type","Vehicle Type","vehicle_select",true,["3 Side LED 14 feet","3 Side LED 18 feet","7x5 LED Hybrid 8 feet","Box Model Triangle Roof","Center Portion Triangle Roof","Center Portion Without Roof","L-Model Box Roof with Utility Room","L-Model Box Roof","L-Model Without Roof","L-Shape LED","Single Side LED 17 feet","Static Model"]],["vehicle_quantity","Vehicle Quantity","number",false,[]],["location","Campaign Location","location",false,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["creative","Creative","file",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]},"new_creative_design":{"label":"Own Creative","fields":[["__section_details","Vehicle / Campaign Details","section",false,[]],["roadshow_subtype","Road Show Type","select",true,["New Creative Design","3D Mockup New Creative Design"]],["vehicle_type","Vehicle Type","vehicle_select",true,["3 Side LED 14 feet","3 Side LED 18 feet","7x5 LED Hybrid 8 feet","Box Model Triangle Roof","Center Portion Triangle Roof","Center Portion Without Roof","L-Model Box Roof with Utility Room","L-Model Box Roof","L-Model Without Roof","L-Shape LED","Single Side LED 17 feet","Static Model"]],["vehicle_details","Vehicle Details","file",false,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["brand_details","Brand Details","textarea",false,[]],["brand_details_upload","Brand Details Upload","file",false,[]],["brand_name","Brand Name","text",false,[]],["creative_contact_person","Contact Person","text",false,[]],["creative_mobile_number","Mobile Number","text",false,[]],["address","Address","textarea",false,[]],["__section_creative","Creative","section",false,[]],["logo_images","Logo / Brand Assets","files",false,[]],["content_images","Creative Content / Assets","mediafiles",false,[]],["creative","Creative","files",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]}}},"fixtures":{"label":"Fixtures","natures":{"design_with_creative":{"label":"Creative Adaptation","fields":[["__section_details","Fixture Details","section",false,[]],["recce_report","Site Recce / Measurement Details","file",true,[]],["client_format_manual","Client Brand / Format Guidelines","file",false,[]],["fixture_details","Fixture Specifications / Details","file",true,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["creative","Creative","file",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]},"design_without_creative":{"label":"Own Creative","fields":[["__section_details","Fixture Details","section",false,[]],["recce_report","Site Recce / Measurement Details","file",true,[]],["client_format_manual","Client Brand / Format Guidelines","file",false,[]],["fixture_details","Fixture Specifications / Details","file",true,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["website_link","Client Website","url",false,[]],["__section_creative","Creative","section",false,[]],["logo_images","Logo / Brand Assets","files",false,[]],["creative","Creative","files",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]}}},"signage":{"label":"Signage","natures":{"mockup":{"label":"Mockup","fields":[["__section_details","Signage / Site Details","section",false,[]],["recce_report","Site Recce / Measurement Details","file",true,[]],["material_specifications","Material Details","file",false,[]],["client_format_manual","Client Brand / Format Guidelines","file",false,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["creative","Creative","file",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]},"creative_adaptation":{"label":"Creative Adaptation","fields":[["__section_details","Signage / Site Details","section",false,[]],["recce_report","Site Recce / Measurement Details","file",false,[]],["material_specifications","Material Details","file",true,[]],["client_format_manual","Client Brand / Format Guidelines","file",false,[]],["dealer_details","Dealer / Location Details","file",false,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["creative","Creative","file",true,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]},"new_creative":{"label":"Own Creative","fields":[["__section_details","Signage / Site Details","section",false,[]],["recce_report","Site Recce / Measurement Details","file",true,[]],["material_specifications","Material Details","file",true,[]],["client_format_manual","Client Brand / Format Guidelines","file",false,[]],["dealer_details","Dealer / Location Details","file",false,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["logo_images","Logo / Brand Assets","files",false,[]],["creative","Creative","files",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]},"technical_drawing":{"label":"Technical Drawing","fields":[["__section_details","Signage / Site Details","section",false,[]],["recce_report","Site Recce / Measurement Details","file",true,[]],["client_format_manual","Client Brand / Format Guidelines","file",false,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["creative","Creative","file",false,[]],["logo_images","Logo / Brand Assets","files",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]},"three_d_design":{"label":"3D Design","fields":[["__section_details","Signage / Site Details","section",false,[]],["recce_report","Site Recce / Measurement Details","file",true,[]],["technical_drawing","Technical Drawing","file",false,[]],["client_format_manual","Client Brand / Format Guidelines","file",false,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["creative","Creative","file",false,[]],["logo_images","Logo / Brand Assets","files",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]},"technical_and_three_d":{"label":"Technical Drawing + 3D Design","fields":[["__section_details","Signage / Site Details","section",false,[]],["recce_report","Site Recce / Measurement Details","file",true,[]],["client_format_manual","Client Brand / Format Guidelines","file",false,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["creative","Creative","file",false,[]],["logo_images","Logo / Brand Assets","files",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]}}},"pop_offsets":{"label":"Print / POP","natures":{"mockup_design":{"label":"Mockup","fields":[["__section_details","Print / Product Details","section",false,[]],["product_type","Print / Product Type","select",true,["Leaflets","Poster","Brochure","Visiting Card","Pocket Card","Dangler","Roll Up Standee","Sunpack Sheet","Calendar","ID Card","Other"]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["logo_images","Logo / Brand Assets","files",false,[]],["creative","Creative","file",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]},"design_adaptation":{"label":"Creative Adaptation","fields":[["__section_details","Print / Product Details","section",false,[]],["product_type","Print / Product Type","select",true,["Leaflets","Poster","Brochure","Visiting Card","Pocket Card","Dangler","Roll Up Standee","Sunpack Sheet","Calendar","ID Card","Other"]],["size_details","Print Size Details","sizes",true,[]],["element_list","Print / Element Details","file",false,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["logo_images","Logo / Brand Assets","files",false,[]],["creative","Creative","file",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]},"creative_design":{"label":"Own Creative","fields":[["__section_details","Print / Product Details","section",false,[]],["product_type","Print / Product Type","select",true,["Leaflets","Poster","Brochure","Visiting Card","Pocket Card","Dangler","Roll Up Standee","Sunpack Sheet","Calendar","ID Card","Other"]],["size_details","Print Size Details","sizes",true,[]],["element_list","Print / Element Details","file",false,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["logo_images","Logo / Brand Assets","files",false,[]],["creative","Creative","files",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]}}},"events_activations":{"label":"Events & Activations","natures":{"proposal_designs":{"label":"Proposal Design","fields":[["__section_details","Event / Activation Details","section",false,[]],["location","Event / Activation Location","location",false,[]],["requirement_list","Event / Activation Requirement Details","file",true,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["creative","Creative","files",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]},"element_design_with_creative":{"label":"Creative Adaptation","fields":[["__section_details","Event / Activation Details","section",false,[]],["location","Event / Activation Location","location",false,[]],["recce_report","Venue / Site Recce Details","file",true,[]],["brand_guidelines","Brand Guidelines","file",false,[]],["requirement_list","Event / Activation Requirement Details","file",true,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["creative","Creative","file",true,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]},"element_design_without_creative":{"label":"Own Creative","fields":[["__section_details","Event / Activation Details","section",false,[]],["location","Event / Activation Location","location",false,[]],["recce_report","Venue / Site Recce Details","file",false,[]],["brand_guidelines","Brand Guidelines","file",false,[]],["requirement_list","Event / Activation Requirement Details","file",false,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["logo_images","Logo / Brand Assets","files",false,[]],["creative","Creative","files",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]},"three_d_layout":{"label":"3D Layout","fields":[["__section_details","Event / Activation Details","section",false,[]],["location","Event / Activation Location","location",false,[]],["recce_report","Venue / Site Recce Details","file",false,[]],["brand_guidelines","Brand Guidelines","file",false,[]],["requirement_list","Event / Activation Requirement Details","file",true,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["creative","Creative","files",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]}}},"media":{"label":"Media","natures":{"creative_adaptation":{"label":"Creative Adaptation","fields":[["__section_details","Media Details","section",false,[]],["media_type","Media Type","select",true,["Theatre Ads","Newspaper Ads","TV Ads"]],["creative_size_details","Creative Size Details","media_sizes",true,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",false,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["creative","Creative","files",true,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]},"own_creative":{"label":"Own Creative","fields":[["__section_details","Media Details","section",false,[]],["media_type","Media Type","select",true,["Theatre Ads","Newspaper Ads","TV Ads"]],["creative_size_details","Creative Size Details","media_sizes",true,[]],["__section_description","Description","section",false,[]],["description","Description","textarea",true,[]],["__section_company","Company Details","section",false,[]],["company_details","Company Details","textarea",true,[]],["company_details_upload","Company Details Upload","files",false,[]],["__section_creative","Creative","section",false,[]],["creative","Creative / Sample Assets","files",false,[]],["__section_references","References","section",false,[]],["reference_notes","References","textarea",false,[]],["attachments","Attachments","files",false,[]],["__section_audio","Audio Reference","section",false,[]],["client_audio","Audio Reference","audio",false,[]]]}}}};

const outdoorTypes=['Bus Shelter','Unipole','Standard','Auto Branding','Pole Kiosk','Digital','Signal Post'];
const outdoorBoardTypeNatures=new Set(['mockup_requirements','creative_adaptation','new_creative_design']);
const companyDetailsNatures=new Set(['outdoor.new_creative_design','roadshow.new_creative_design','fixtures.design_without_creative','signage.new_creative','signage.three_d_design','events_activations.element_design_without_creative']);

const taskForm=document.getElementById('taskForm');
const vertical=document.getElementById('vertical');
const nature=document.getElementById('taskNature');
const section=document.getElementById('dynamicSection');
const fieldsBox=document.getElementById('dynamicFields');
const title=document.getElementById('dynamicTitle');
const submit=document.getElementById('submitBtn');
const draftBtn=document.getElementById('draftBtn');
const draftIdInput=document.getElementById('draftIdInput');
const methodInput=document.getElementById('methodInput');
const draftStoreUrl=@json(route('bd.drafts.store'));
const draftUpdateUrlTemplate=@json(route('bd.drafts.update',['task'=>'__DRAFT_ID__']));
const draftFiles=@json($draftFiles ?? []);
const removedFilesInput=document.getElementById('removedFilesInput');
const removedDraftFiles={};

function esc(value=''){return String(value??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
function previous(name){return oldValues[name]??'';}

/** Already-uploaded draft files for one field — informational list + Remove, rendered above the file input. */
function attachedFilesHtml(name){
 const removed=removedDraftFiles[name]||[];
 const files=(draftFiles[name]||[]).filter(f=>!removed.includes(f.path));
 if(!files.length)return '';
 return `<div class="draft-attached-files" data-attached-files="${esc(name)}">${files.map(f=>`<div class="draft-attached-file" data-attached-path="${esc(f.path)}"><span>✓ ${esc(f.name)}</span><a href="${esc(f.url)}" target="_blank" rel="noopener">View</a><button type="button" class="draft-attached-remove" data-remove-attached data-field="${esc(name)}" data-path="${esc(f.path)}">Remove</button></div>`).join('')}</div>`;
}
function bindAttachedFiles(root){
 root.querySelectorAll('[data-remove-attached]').forEach(btn=>{
  if(btn.dataset.attachedBound==='1')return;
  btn.dataset.attachedBound='1';
  btn.addEventListener('click',()=>{
   const field=btn.dataset.field,path=btn.dataset.path;
   (removedDraftFiles[field]=removedDraftFiles[field]||[]).push(path);
   btn.closest('[data-attached-file]')?.remove();
  });
 });
}

function getErrorNode(input){
 let node=input.parentElement?.querySelector(':scope > .live-field-error');
 if(!node){node=document.createElement('p');node.className='live-field-error hidden';input.insertAdjacentElement('afterend',node);}
 return node;
}
function getLabel(input){
 const label=input.closest('div')?.querySelector('label');
 return (label?.textContent||input.name||'This field').replace(/\s*\*+\s*$/,'').trim();
}
function setError(input,message=''){
 if(!input||input.type==='hidden'||input.readOnly)return true;
 const node=getErrorNode(input);
 input.classList.toggle('has-error',!!message);
 node.textContent=message;
 node.classList.toggle('hidden',!message);
 return !message;
}
const MAX_FILE_BYTES=6*1024*1024*1024;
const ZIP_ONLY_THRESHOLD_BYTES=500*1024*1024;
function validateField(input,show=true){
 if(!input||input.disabled||input.type==='hidden'||input.readOnly)return true;
 const value=input.type==='file'?(input.files?.length||0):String(input.value??'').trim();
 let message='';
 if(input.required&&!value)message=`${getLabel(input)} is required.`;
 else if(input.type==='file'&&input.dataset.audioOnly==='1'&&value){
  const invalid=Array.from(input.files).some(file=>!/\.(mp3|wav)$/i.test(file.name));
  if(invalid)message='Only MP3 or WAV audio files are allowed.';
 }
 else if(input.type==='file'&&input.dataset.audioOnly!=='1'&&value){
  const files=Array.from(input.files);
  const tooLarge=files.find(file=>file.size>MAX_FILE_BYTES);
  const needsZip=files.find(file=>file.size>ZIP_ONLY_THRESHOLD_BYTES&&!/\.zip$/i.test(file.name));
  if(tooLarge)message='Maximum file size is 6 GB.';
  else if(needsZip)message='Files larger than 500 MB must be ZIP format.';
 }
 else if(value&&input.validity){
  if(input.validity.patternMismatch)message=`${getLabel(input)} is not in the correct format.`;
  else if(input.validity.typeMismatch)message=`Enter a valid ${getLabel(input).toLowerCase()}.`;
  else if(input.validity.rangeUnderflow)message=`${getLabel(input)} is below the allowed minimum.`;
  else if(input.validity.rangeOverflow)message=`${getLabel(input)} exceeds the allowed maximum.`;
 }
 if(show)setError(input,message);
 return !message;
}
function bindLiveValidation(root=document){
 root.querySelectorAll('input,select,textarea').forEach(input=>{
  if(input.dataset.liveValidationBound==='1')return;
  input.dataset.liveValidationBound='1';
  input.addEventListener('blur',()=>validateField(input,true));
  input.addEventListener('change',()=>validateField(input,true));
  input.addEventListener('input',()=>{if(input.classList.contains('has-error')||String(input.value??'').trim()!=='')validateField(input,true);});
 });
}

function uploadHtml(name,label,required=false,accept='',audioOnly=false){
 const star=required?' *':'',req=required?'required':'',acceptAttr=accept?`accept="${accept}"`:'',audioAttr=audioOnly?'data-audio-only="1"':'';
 const help=audioOnly?'Only MP3 and WAV audio files are allowed. Select multiple files together, or choose more files later. Use × to remove any file before submitting.':'Select multiple files together, or choose more files later. Files over 500 MB must be ZIP format. Maximum 6 GB per file.';
 return `<div class="md:col-span-2"><label class="label">${label}${star}</label>${attachedFilesHtml(name)}<input class="field" type="file" name="${name}[]" multiple data-accumulate-files ${acceptAttr} ${audioAttr} ${req}><p class="multi-file-help">${help}</p></div>`;
}
function dimensionRowHtml(index,row={}){
 const name=esc(row.name??''),width=esc(row.width??''),height=esc(row.height??'');
 const area=width&&height?(Number(width)*Number(height)).toFixed(2):'';
 return `<div class="dimension-row" data-dimension-row>
  <div><label class="label">Name</label><input class="field dimension-name" type="text" name="dimension_rows[${index}][name]" value="${name}" placeholder="e.g. Main Board"></div>
  <div><label class="label">Width (ft)</label><input class="field dimension-width" type="number" min="0.01" step="0.01" name="dimension_rows[${index}][width]" value="${width}" placeholder="Width"></div>
  <div><label class="label">Height (ft)</label><input class="field dimension-height" type="number" min="0.01" step="0.01" name="dimension_rows[${index}][height]" value="${height}" placeholder="Height"></div>
  <div><label class="label">Area (sq.ft)</label><input class="field dimension-area" type="number" step="0.01" name="dimension_rows[${index}][area]" value="${area}" readonly></div>
  <button type="button" class="dimension-remove" data-remove-dimension title="Remove row">×</button>
 </div>`;
}
function dimensionsHtml(){
 const rows=Array.isArray(oldValues.dimension_rows)&&oldValues.dimension_rows.length?oldValues.dimension_rows:[{}];
 return `<div class="dimension-card" data-dimension-block>
  <div class="dimension-head"><div><div class="dimension-title">Board Details *</div><div class="dimension-subtitle">Fill at least one complete row, or upload the Board Details. Area is calculated automatically.</div></div><button type="button" class="btn btn-secondary" data-add-dimension>+ Add New Row</button></div>
  <div data-dimension-rows data-next-index="${rows.length}">${rows.map((row,index)=>dimensionRowHtml(index,row)).join('')}</div>
  <div class="dimension-upload"><label class="label">Or Upload Board Details / Dimensions</label>${attachedFilesHtml('dimension_upload')}<input class="field" type="file" name="dimension_upload[]" multiple data-accumulate-files data-dimension-upload><p class="text-xs text-slate-500 mt-1">Multiple files are allowed. Either a complete row or an upload is mandatory.</p></div>
  <p class="live-field-error hidden" data-dimension-error></p>
 </div>`;
}

function sizeRowHtml(index,row={}){
 const name=esc(row.name??''),width=esc(row.width??''),height=esc(row.height??'');
 const area=width&&height?(Number(width)*Number(height)).toFixed(2):'';
 return `<div class="dimension-row" data-size-row>
  <div><label class="label">Name</label><input class="field size-name" type="text" name="size_rows[${index}][name]" value="${name}" placeholder="e.g. Poster"></div>
  <div><label class="label">Width (ft)</label><input class="field size-width" type="number" min="0.01" step="0.01" name="size_rows[${index}][width]" value="${width}" placeholder="Width"></div>
  <div><label class="label">Height (ft)</label><input class="field size-height" type="number" min="0.01" step="0.01" name="size_rows[${index}][height]" value="${height}" placeholder="Height"></div>
  <div><label class="label">Area (sq.ft)</label><input class="field dimension-area" type="number" step="0.01" name="size_rows[${index}][area]" value="${area}" readonly></div>
  <button type="button" class="dimension-remove" data-remove-size title="Remove row">×</button>
 </div>`;
}
function sizesHtml(){
 const rows=Array.isArray(oldValues.size_rows)&&oldValues.size_rows.length?oldValues.size_rows:[{}];
 return `<div class="dimension-card" data-size-block>
  <div class="dimension-head"><div><div class="dimension-title">Size Details *</div><div class="dimension-subtitle">Fill at least one complete row, or upload the Size Details. Area is calculated automatically.</div></div><button type="button" class="btn btn-secondary" data-add-size>+ Add New Row</button></div>
  <div data-size-rows data-next-index="${rows.length}">${rows.map((row,index)=>sizeRowHtml(index,row)).join('')}</div>
  <div class="dimension-upload"><label class="label">Or Upload Size Details</label>${attachedFilesHtml('size_upload')}<input class="field" type="file" name="size_upload[]" multiple data-accumulate-files data-size-upload><p class="text-xs text-slate-500 mt-1">Multiple files are allowed. Either a complete row or an upload is mandatory.</p></div>
  <p class="live-field-error hidden" data-size-error></p>
 </div>`;
}

function mediaSizeRowHtml(index,row={}){
 const name=esc(row.name??''),width=esc(row.width??''),height=esc(row.height??''),ratio=esc(row.ratio??'');
 return `<div class="media-size-row" data-media-size-row>
  <div><label class="label">Name</label><input class="field media-size-name" type="text" name="media_size_rows[${index}][name]" value="${name}" placeholder="e.g. Main Creative"></div>
  <div><label class="label">Width</label><input class="field media-size-width" type="number" min="0.01" step="0.01" name="media_size_rows[${index}][width]" value="${width}" placeholder="Width"></div>
  <div><label class="label">Height</label><input class="field media-size-height" type="number" min="0.01" step="0.01" name="media_size_rows[${index}][height]" value="${height}" placeholder="Height"></div>
  <div><label class="label">Ratio</label><input class="field media-size-ratio" type="text" name="media_size_rows[${index}][ratio]" value="${ratio}" placeholder="e.g. 16:9"></div>
  <button type="button" class="media-size-remove" data-remove-media-size title="Remove row">×</button>
 </div>`;
}
function mediaSizesHtml(){
 const rows=Array.isArray(oldValues.media_size_rows)&&oldValues.media_size_rows.length?oldValues.media_size_rows:[{}];
 return `<div class="media-size-card" data-media-size-block>
  <div class="media-size-head">
   <div><div class="media-size-title">Creative Size Details *</div><div class="media-size-subtitle">Add every required creative size using Name, Width, Height and Ratio.</div></div>
   <button type="button" class="btn btn-secondary" data-add-media-size>+ Add New Row</button>
  </div>
  <div data-media-size-rows data-next-index="${rows.length}">${rows.map((row,index)=>mediaSizeRowHtml(index,row)).join('')}</div>
  <p class="live-field-error hidden" data-media-size-error></p>
 </div>`;
}
function validateMediaSizes(show=true){
 const block=fieldsBox.querySelector('[data-media-size-block]');
 if(!block)return true;
 const rows=[...block.querySelectorAll('[data-media-size-row]')];
 let hasComplete=false,hasPartial=false;
 rows.forEach(row=>{
  const name=String(row.querySelector('.media-size-name')?.value||'').trim();
  const width=parseFloat(row.querySelector('.media-size-width')?.value||0);
  const height=parseFloat(row.querySelector('.media-size-height')?.value||0);
  const ratio=String(row.querySelector('.media-size-ratio')?.value||'').trim();
  const any=!!name||width>0||height>0||!!ratio;
  const complete=!!name&&width>0&&height>0&&!!ratio;
  if(complete)hasComplete=true;
  if(any&&!complete)hasPartial=true;
 });
 let message='';
 if(hasPartial)message='Complete Name, Width, Height and Ratio for every Creative Size Details row, or remove the incomplete row.';
 else if(!hasComplete)message='Add at least one complete Creative Size Details row.';
 if(show&&block.querySelector('[data-media-size-error]')){
  const error=block.querySelector('[data-media-size-error]');
  error.textContent=message;
  error.classList.toggle('hidden',!message);
  block.style.borderColor=message?'#f04438':'#e4e7ec';
 }
 return !message;
}
function bindMediaSizes(){
 const block=fieldsBox.querySelector('[data-media-size-block]');
 if(!block)return;
 const rowsBox=block.querySelector('[data-media-size-rows]');
 const bindRow=row=>{
  if(row.dataset.mediaSizeBound==='1')return;
  row.dataset.mediaSizeBound='1';
  row.querySelectorAll('input').forEach(input=>input.addEventListener('input',()=>validateMediaSizes(true)));
  row.querySelector('[data-remove-media-size]')?.addEventListener('click',()=>{
   if(rowsBox.querySelectorAll('[data-media-size-row]').length===1){
    row.querySelectorAll('input').forEach(input=>input.value='');
   }else row.remove();
   validateMediaSizes(true);
  });
  bindLiveValidation(row);
 };
 rowsBox.querySelectorAll('[data-media-size-row]').forEach(bindRow);
 block.querySelector('[data-add-media-size]')?.addEventListener('click',()=>{
  const index=Number(rowsBox.dataset.nextIndex||0);
  rowsBox.dataset.nextIndex=String(index+1);
  rowsBox.insertAdjacentHTML('beforeend',mediaSizeRowHtml(index,{}));
  bindRow(rowsBox.lastElementChild);
 });
}

const roadshowVehicleImages={
 '3 Side LED 14 feet':'/images/roadshow-vehicles/3-side-led-14-feet.jpeg',
 '3 Side LED 18 feet':'/images/roadshow-vehicles/3-side-led-18-feet.jpeg',
 '7x5 LED Hybrid 8 feet':'/images/roadshow-vehicles/7x5-led-hybrid-8-feet.jpeg',
 'Box Model Triangle Roof':'/images/roadshow-vehicles/box-model-triangle-roof.jpeg',
 'Center Portion Triangle Roof':'/images/roadshow-vehicles/center-portion-triangle-roof.jpeg',
 'Center Portion Without Roof':'/images/roadshow-vehicles/center-portion-without-roof.jpeg',
 'L-Model Box Roof with Utility Room':'/images/roadshow-vehicles/l-model-box-roof-with-utility-room.jpeg',
 'L-Model Box Roof':'/images/roadshow-vehicles/l-model-box-roof.jpeg',
 'L-Model Without Roof':'/images/roadshow-vehicles/l-model-without-roof.jpeg',
 'L-Shape LED':'/images/roadshow-vehicles/l-shape-led.jpeg',
 'Single Side LED 17 feet':'/images/roadshow-vehicles/single-side-led-17-feet.jpeg',
 'Static Model':'/images/roadshow-vehicles/static-model.jpeg'
};

function fieldHtml(f){
 const [name,label,type,required=false,options=[]]=f;
 if(type==='section')return `<div class="md:col-span-2 standard-form-section"><div class="standard-form-section-title">${label}</div></div>`;
 if(type==='media_sizes')return mediaSizesHtml();
 const star=required?' *':'',req=required?'required':'',rawOld=previous(name),old=esc(rawOld);
 const wrap=name==='description'||name.includes('details')||['file','files','audio','mediafiles'].includes(type)?'md:col-span-2':'';
 if(type==='dimensions')return dimensionsHtml();
 if(type==='sizes')return sizesHtml();
 if(type==='textarea')return `<div class="${wrap}"><label class="label">${label}${star}</label><textarea class="field" rows="4" name="${name}" ${req}>${old}</textarea></div>`;
 if(type==='vehicle_select'){
  const previewSrc=roadshowVehicleImages[rawOld]||'';
  return `<div class="md:col-span-2">
   <label class="label">${label}${star}</label>
   <select class="field" name="${name}" data-roadshow-vehicle ${req}>
    <option value="">Select Vehicle Name</option>
    ${options.map(o=>`<option value="${esc(o)}" ${rawOld===o?'selected':''}>${esc(o)}</option>`).join('')}
   </select>
   <div class="vehicle-preview-card ${previewSrc?'':'hidden'}" data-roadshow-vehicle-preview>
    <div class="vehicle-preview-inner">
     <div class="vehicle-preview-copy">
      <strong data-roadshow-vehicle-title>${rawOld?esc(rawOld):'Selected Vehicle'}</strong>
      <span>The selected Road Show vehicle preview is shown here.</span>
     </div>
     <div class="vehicle-preview-image-wrap">
      <img class="vehicle-preview-image" data-roadshow-vehicle-image src="${previewSrc}" alt="${rawOld?esc(rawOld):'Road Show vehicle preview'}">
     </div>
    </div>
   </div>
  </div>`;
 }
 if(type==='select'){
  const hasOther=options.includes('Other');
  const otherName=`${name}_other`;
  const oldOther=esc(previous(otherName));
  return `<div class="${wrap}">
   <label class="label">${label}${star}</label>
   <select class="field" name="${name}" data-select-name="${name}" ${hasOther?'data-has-other="1"':''} ${req}>
    <option value="">Select</option>${options.map(o=>`<option value="${esc(o)}" ${rawOld===o?'selected':''}>${esc(o)}</option>`).join('')}
   </select>
   ${hasOther?`<div data-other-wrap="${name}" class="${rawOld==='Other'?'':'hidden'}" style="margin-top:8px"><label class="label">Specify Other *</label><input class="field" type="text" name="${otherName}" value="${oldOther}" ${rawOld==='Other'?'required':''}></div>`:''}
  </div>`;
 }
 if(type==='file'||type==='files')return uploadHtml(name,label,required);
 if(type==='mediafiles')return uploadHtml(name,label,required,'image/*,video/*');
 if(type==='audio')return uploadHtml(name,label,required,'.mp3,.wav,audio/mpeg,audio/mp3,audio/wav,audio/x-wav',true);
 if(type==='location')return `<div class="${wrap}"><label class="label">${label}${star}</label><input class="field" type="text" list="locationOptions" name="${name}" value="${old}" placeholder="Search or enter location" ${req}></div>`;
 if(name==='creative_mobile_number')return `<div class="${wrap}"><label class="label">${label}${star}</label><input class="field" type="text" name="${name}" value="${old}" inputmode="numeric" pattern="[0-9]{10}" minlength="10" maxlength="10" placeholder="Enter 10-digit mobile number" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)" ${req}></div>`;
 if(type==='number')return `<div class="${wrap}"><label class="label">${label}${star}</label><input class="field" type="number" name="${name}" value="${old}" min="1" max="9999" step="1" inputmode="numeric" ${req}></div>`;
 const inputType=['url','email'].includes(type)?type:'text';
 return `<div class="${wrap}"><label class="label">${label}${star}</label><input class="field" type="${inputType}" name="${name}" value="${old}" ${req}></div>`;
}
function effectiveFields(form){ return [...form.fields]; }
function populateNatures(selected=''){
 nature.innerHTML='<option value="">Select task nature</option>';
 const cfg=forms[vertical.value];
 nature.disabled=!cfg;
 if(!cfg){renderFields();return;}
 Object.entries(cfg.natures).forEach(([value,item])=>nature.insertAdjacentHTML('beforeend',`<option value="${value}" ${selected===value?'selected':''}>${item.label}</option>`));
 renderFields();
}
function calculateDimensionRow(row){
 const width=parseFloat(row.querySelector('.dimension-width')?.value||0),height=parseFloat(row.querySelector('.dimension-height')?.value||0),area=row.querySelector('.dimension-area');
 if(area)area.value=width>0&&height>0?(width*height).toFixed(2):'';
}
function validateDimensions(show=true){
 const block=fieldsBox.querySelector('[data-dimension-block]');
 if(!block)return true;
 const rows=[...block.querySelectorAll('[data-dimension-row]')],upload=block.querySelector('[data-dimension-upload]'),error=block.querySelector('[data-dimension-error]');
 let hasComplete=false,hasPartial=false;
 rows.forEach(row=>{
  const name=String(row.querySelector('.dimension-name')?.value||'').trim(),width=parseFloat(row.querySelector('.dimension-width')?.value||0),height=parseFloat(row.querySelector('.dimension-height')?.value||0);
  const any=!!name||width>0||height>0,complete=!!name&&width>0&&height>0;
  if(complete)hasComplete=true;
  if(any&&!complete)hasPartial=true;
 });
 const hasUpload=(upload?.files?.length||0)>0;
 let message='';
 if(hasPartial)message='Complete Name, Width and Height for every Board / Display Size row, or remove the incomplete row.';
 else if(!hasComplete&&!hasUpload)message='Provide at least one complete Board / Display Size row or upload the details.';
 if(show&&error){error.textContent=message;error.classList.toggle('hidden',!message);block.style.borderColor=message?'#f04438':'#e4e7ec';}
 return !message;
}
function bindDimensions(){
 const block=fieldsBox.querySelector('[data-dimension-block]');
 if(!block)return;
 const rowsBox=block.querySelector('[data-dimension-rows]');
 const bindRow=row=>{
  if(row.dataset.bound==='1')return;
  row.dataset.bound='1';
  row.querySelectorAll('.dimension-width,.dimension-height,.dimension-name').forEach(input=>input.addEventListener('input',()=>{calculateDimensionRow(row);validateDimensions(true);}));
  row.querySelector('[data-remove-dimension]')?.addEventListener('click',()=>{
   if(rowsBox.querySelectorAll('[data-dimension-row]').length===1){row.querySelectorAll('input:not([readonly])').forEach(input=>input.value='');calculateDimensionRow(row);}else row.remove();
   validateDimensions(true);
  });
  calculateDimensionRow(row);bindLiveValidation(row);
 };
 rowsBox.querySelectorAll('[data-dimension-row]').forEach(bindRow);
 block.querySelector('[data-add-dimension]')?.addEventListener('click',()=>{
  const index=Number(rowsBox.dataset.nextIndex||0);rowsBox.dataset.nextIndex=String(index+1);rowsBox.insertAdjacentHTML('beforeend',dimensionRowHtml(index,{}));bindRow(rowsBox.lastElementChild);
 });
 block.querySelector('[data-dimension-upload]')?.addEventListener('change',()=>validateDimensions(true));
}

function bindOtherFields(root=fieldsBox){
 root.querySelectorAll('select[data-has-other="1"]').forEach(select=>{
  const name=select.dataset.selectName;
  const wrap=root.querySelector(`[data-other-wrap="${name}"]`);
  const input=wrap?.querySelector('input');
  const refresh=()=>{
   const show=select.value==='Other';
   wrap?.classList.toggle('hidden',!show);
   if(input){
    input.required=show;
    if(!show){
     input.value='';
     setError(input,'');
    }
   }
  };
  if(select.dataset.otherBound!=='1'){
   select.dataset.otherBound='1';
   select.addEventListener('change',refresh);
  }
  refresh();
 });
}

function calculateSizeRow(row){
 const width=parseFloat(row.querySelector('.size-width')?.value||0);
 const height=parseFloat(row.querySelector('.size-height')?.value||0);
 const area=row.querySelector('input[readonly]');
 if(area)area.value=width>0&&height>0?(width*height).toFixed(2):'';
}
function validateSizes(show=true){
 const block=fieldsBox.querySelector('[data-size-block]');
 if(!block)return true;
 const rows=[...block.querySelectorAll('[data-size-row]')];
 const upload=block.querySelector('[data-size-upload]');
 const error=block.querySelector('[data-size-error]');
 let hasComplete=false,hasPartial=false;

 rows.forEach(row=>{
  const name=String(row.querySelector('.size-name')?.value||'').trim();
  const width=parseFloat(row.querySelector('.size-width')?.value||0);
  const height=parseFloat(row.querySelector('.size-height')?.value||0);
  const any=!!name||width>0||height>0;
  const complete=!!name&&width>0&&height>0;
  if(complete)hasComplete=true;
  if(any&&!complete)hasPartial=true;
 });

 const hasUpload=(upload?.files?.length||0)>0;
 let message='';
 if(hasPartial)message='Complete Name, Width and Height for every Size Details row, or remove the incomplete row.';
 else if(!hasComplete&&!hasUpload)message='Provide at least one complete Size Details row or upload the Size Details.';

 if(show&&error){
  error.textContent=message;
  error.classList.toggle('hidden',!message);
  block.style.borderColor=message?'#f04438':'#e4e7ec';
 }
 return !message;
}
function bindSizes(){
 const block=fieldsBox.querySelector('[data-size-block]');
 if(!block)return;
 const rowsBox=block.querySelector('[data-size-rows]');

 const bindRow=row=>{
  if(row.dataset.sizeBound==='1')return;
  row.dataset.sizeBound='1';

  row.querySelectorAll('.size-width,.size-height,.size-name').forEach(input=>{
   input.addEventListener('input',()=>{
    calculateSizeRow(row);
    validateSizes(true);
   });
  });

  row.querySelector('[data-remove-size]')?.addEventListener('click',()=>{
   if(rowsBox.querySelectorAll('[data-size-row]').length===1){
    row.querySelectorAll('input:not([readonly])').forEach(input=>input.value='');
    calculateSizeRow(row);
   }else{
    row.remove();
   }
   validateSizes(true);
  });

  calculateSizeRow(row);
  bindLiveValidation(row);
 };

 rowsBox.querySelectorAll('[data-size-row]').forEach(bindRow);

 block.querySelector('[data-add-size]')?.addEventListener('click',()=>{
  const index=Number(rowsBox.dataset.nextIndex||0);
  rowsBox.dataset.nextIndex=String(index+1);
  rowsBox.insertAdjacentHTML('beforeend',sizeRowHtml(index,{}));
  bindRow(rowsBox.lastElementChild);
 });

 block.querySelector('[data-size-upload]')?.addEventListener('change',()=>validateSizes(true));
}


function applyMediaTaskNatureVisibility(){}
function bindRoadshowVehiclePreview(){
 const select=fieldsBox.querySelector('[data-roadshow-vehicle]');
 if(!select)return;
 const card=fieldsBox.querySelector('[data-roadshow-vehicle-preview]');
 const image=fieldsBox.querySelector('[data-roadshow-vehicle-image]');
 const title=fieldsBox.querySelector('[data-roadshow-vehicle-title]');

 const refresh=()=>{
  const selected=select.value;
  const src=roadshowVehicleImages[selected]||'';
  card?.classList.toggle('hidden',!src);
  if(image){
   image.src=src;
   image.alt=selected?`${selected} preview`:'Road Show vehicle preview';
  }
  if(title)title.textContent=selected||'Selected Vehicle';
 };

 if(select.dataset.vehiclePreviewBound!=='1'){
  select.dataset.vehiclePreviewBound='1';
  select.addEventListener('change',refresh);
 }
 refresh();
}

function renderFields(){
 const cfg=forms[vertical.value],form=cfg?.natures?.[nature.value];
 section.classList.toggle('hidden',!form);submit.disabled=!form;draftBtn.disabled=!form;
 if(!form){fieldsBox.innerHTML='';return;}
 title.textContent=`${cfg.label} — ${form.label}`;
 fieldsBox.innerHTML=effectiveFields(form).map(fieldHtml).join('');
 bindDimensions();bindSizes();bindMediaSizes();bindOtherFields(fieldsBox);bindLiveValidation(fieldsBox);bindRoadshowVehiclePreview();bindAttachedFiles(fieldsBox);
}

vertical.addEventListener('change',()=>populateNatures());
nature.addEventListener('change',renderFields);
document.getElementById('partyType').addEventListener('change',e=>document.getElementById('partyNameLabel').textContent=(e.target.value==='agency'?'Agency':'Client')+' Name *');

@if(!$isDesignerActor)
// Designer Availability meter — informational only; never blocks or alters task creation.
(function(){
 const select=document.getElementById('designerSelect');
 const box=document.getElementById('designerAvailability');
 const availabilityUrlTemplate=@json(url('/bd/designers/__ID__/availability'));
 let requestToken=0;
 let controller=null;

 function render(html){box.innerHTML=html;box.classList.remove('hidden');}
 function checkinClass(v){return v===true?'checked-in':v===false?'not-checked-in':'unknown';}
 function checkinText(v){return v===true?'🟢 Checked In':v===false?'🔴 Not Checked In':'⚪ Unable to check';}

 // Profile cards are a convenience picker only — they just drive the same
 // native select + change event the availability meter already listens to,
 // so assignment/availability logic below stays completely untouched.
 document.querySelectorAll('.designer-profile-card').forEach(card=>{
  card.addEventListener('click',()=>{
   select.value=card.dataset.designerId;
   select.dispatchEvent(new Event('change'));
  });
 });

 select.addEventListener('change',()=>{
  document.querySelectorAll('.designer-profile-card').forEach(card=>{
   card.classList.toggle('is-selected',card.dataset.designerId===select.value);
  });

  const designerId=select.value;
  const token=++requestToken;
  if(controller)controller.abort();
  if(!designerId){box.classList.add('hidden');box.innerHTML='';return;}

  render('<div class="designer-availability-note">Checking availability...</div>');
  controller=new AbortController();

  fetch(availabilityUrlTemplate.replace('__ID__',encodeURIComponent(designerId)),{
   headers:{'Accept':'application/json'},
   signal:controller.signal,
  })
   .then(res=>{if(!res.ok)throw new Error('bad_response');return res.json();})
   .then(data=>{
    if(token!==requestToken)return;
    render(`
     <div class="designer-availability-name">Available Occurance - ${esc(data.designer_name)}</div>
     <div class="designer-availability-meta"><span>Active Tasks: ${esc(data.active_tasks)}</span><span>Assigned Creatives: ${esc(data.active_creatives)}</span></div>
     <div class="designer-availability-meta"><span>Availability: ${esc(data.availability_percent)}%</span></div>
     <div class="designer-availability-track"><div class="designer-availability-fill ${esc(data.level)}" style="width:${esc(data.availability_percent)}%"></div></div>
     <div class="designer-availability-status ${esc(data.level)}">${esc(data.label)}</div>
     <div class="designer-checkin-label">Check-in Status:</div>
     <div class="designer-checkin-status ${checkinClass(data.checked_in)}">${checkinText(data.checked_in)}</div>
    `);
   })
   .catch(err=>{
    if(err.name==='AbortError')return;
    if(token!==requestToken)return;
    render('<div class="designer-availability-note">Availability unavailable</div>');
   });
 });
})();
@else
// Designer flow: the assignee is a BD, not a designer, so no availability
// meter — but clicking a profile card still needs to select + highlight it.
(function(){
 const select=document.getElementById('designerSelect');
 document.querySelectorAll('.designer-profile-card').forEach(card=>{
  card.addEventListener('click',()=>{
   select.value=card.dataset.designerId;
   document.querySelectorAll('.designer-profile-card').forEach(c=>{
    c.classList.toggle('is-selected',c.dataset.designerId===select.value);
   });
   select.dispatchEvent(new Event('change'));
  });
 });
})();
@endif

taskForm.addEventListener('reset',()=>setTimeout(()=>{vertical.value='';populateNatures();taskForm.querySelectorAll('.has-error').forEach(el=>el.classList.remove('has-error'));taskForm.querySelectorAll('.live-field-error').forEach(el=>el.classList.add('hidden'));},0));
taskForm.addEventListener('submit',event=>{
 if(submit.dataset.submitting==='1'||draftBtn.dataset.submitting==='1'){event.preventDefault();return;}
 removedFilesInput.value=JSON.stringify(removedDraftFiles);
 const isDraft=event.submitter===draftBtn;
 if(isDraft){
  // Draft saves are intentionally loose: no required-field validation, so a
  // partially-filled task can be stored. Only basic shape rules run server-side.
  const draftId=draftIdInput.value;
  if(draftId){
   taskForm.action=draftUpdateUrlTemplate.replace('__DRAFT_ID__',encodeURIComponent(draftId));
   methodInput.value='PUT';
  }else{
   taskForm.action=draftStoreUrl;
   methodInput.value='';
  }
  draftBtn.dataset.submitting='1';
  draftBtn.dataset.originalText=draftBtn.textContent;
  draftBtn.disabled=true;submit.disabled=true;
  draftBtn.innerHTML='<span class="btn-spinner is-dark"></span>Saving Draft...';
  return;
 }
 let valid=true;
 taskForm.querySelectorAll('input,select,textarea').forEach(input=>{if(!validateField(input,true))valid=false;});
 if(!validateDimensions(true))valid=false;
 if(!validateSizes(true))valid=false;
 if(!validateMediaSizes(true))valid=false;
 if(!valid){event.preventDefault();taskForm.querySelector('.has-error,[data-dimension-error]:not(.hidden)')?.scrollIntoView({behavior:'smooth',block:'center'});return;}
 submit.dataset.submitting='1';
 submit.dataset.originalText=submit.textContent;
 submit.disabled=true;draftBtn.disabled=true;
 submit.innerHTML='<span class="btn-spinner"></span>{{ $isDesignerActor ? 'Submitting...' : 'Creating Task...' }}';
});

// bfcache restore (e.g. browser back after a failed submit elsewhere) must not
// leave the button permanently stuck disabled/relabeled.
window.addEventListener('pageshow',event=>{
 if(!event.persisted)return;
 submit.dataset.submitting='';
 draftBtn.dataset.submitting='';
 if(submit.dataset.originalText)submit.textContent=submit.dataset.originalText;
 if(draftBtn.dataset.originalText)draftBtn.textContent=draftBtn.dataset.originalText;
 submit.disabled=!(vertical.value&&nature.value);
 draftBtn.disabled=!(vertical.value&&nature.value);
});

bindLiveValidation(taskForm);
if(oldVertical){vertical.value=oldVertical;populateNatures(oldNature);}else populateNatures();

const dueAtInput=document.getElementById('dueAt');
if(dueAtInput){
 const nowDate=new Date(),pad=value=>String(value).padStart(2,'0');
 const localValue=date=>`${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
 dueAtInput.min=localValue(nowDate);
}
</script>
@endsection