@extends('layouts.app')
@section('title','Create Design Task')
@section('workspace-title','Create Design Task')
@section('workspace-subtitle','Capture client requirements and assign the task to a Designer')
@section('content')

@php
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
</style>

<div class="page-head">
    <div>
        <h1>Create Design Task</h1>
        <p>Select a vertical and task nature to load the relevant requirement form.</p>
    </div>
    <a href="{{ route('bd.tasks.index') }}" class="btn btn-secondary">Back to Assigned Tasks</a>
</div>

@if($errors->any())
<div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4">
    <p class="font-semibold text-red-800">Please correct the highlighted fields.</p>
    <ul class="text-sm text-red-700 mt-2 list-disc ml-5">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('bd.tasks.store') }}" enctype="multipart/form-data" id="taskForm" class="space-y-6" novalidate>
@csrf
<section class="panel panel-body">
    <h2 class="text-lg font-bold mb-5">Common Task Details</h2>
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div><label class="label">Task ID</label><input class="field bg-slate-100" value="Auto-generated after submission" disabled></div>
        <div><label class="label">Assigned At</label><input class="field bg-slate-100" value="{{ now()->format('d M Y, h:i A') }}" disabled></div>
        <div><label class="label">Assigned By</label><input class="field bg-slate-100" value="{{ auth()->user()->name }}" disabled></div>

        <div class="lg:col-span-2"><label class="label">Task Name *</label><input class="field" name="task_name" value="{{ old('task_name') }}" required></div>
        <div>
            <label class="label">Vertical *</label>
            <select class="field" id="vertical" name="vertical" required>
                <option value="">Select vertical</option>
                @foreach([
                    'outdoor'=>'Outdoor','roadshow'=>'RoadShow','fixtures'=>'Fixtures','signage'=>'Signage',
                    'pop_offsets'=>'POP and Offsets','events_activations'=>'Events and Activations','media'=>'Media'
                ] as $value=>$label)
                    <option value="{{ $value }}" @selected(old('vertical')===$value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div><label class="label">Client / Agency *</label><select class="field" name="party_type" id="partyType" required><option value="client" @selected(old('party_type')==='client')>Client</option><option value="agency" @selected(old('party_type')==='agency')>Agency</option></select></div>
        <div><label class="label" id="partyNameLabel">Client Name *</label><input class="field" name="party_name" value="{{ old('party_name') }}" required></div>
        <div>
            <label class="label" for="contact_person">Contact Person Name</label>
            <input class="field" id="contact_person" name="contact_person" type="text" maxlength="100"
                   value="{{ old('contact_person') }}" placeholder="Enter contact person name">
        </div>

        <div>
            <label class="label" for="mobile_number">Mobile Number</label>
            <input class="field" id="mobile_number" name="mobile_number" type="text"
                   inputmode="numeric" pattern="[0-9]{10}" minlength="10" maxlength="10"
                   autocomplete="tel" value="{{ old('mobile_number') }}"
                   placeholder="Enter 10-digit mobile number"
                   oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)">
        </div>

        <div><label class="label">Priority *</label><select class="field" name="priority" required>@foreach(['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent'] as $v=>$l)<option value="{{ $v }}" @selected(old('priority')===$v)>{{ $l }}</option>@endforeach</select></div>
        <div><label class="label">Due Date & Time *</label><input class="field" id="dueAt" type="datetime-local" name="due_at" value="{{ old('due_at') }}" required
            min="{{ $minDueDate }}"
            max="{{ $maxDueDateInput }}"
        ></div>
        <div><label class="label">Designer Name *</label><select class="field" name="designer_id" required><option value="">Select designer</option>@foreach($designers as $designer)<option value="{{ $designer->id }}" @selected((string)old('designer_id')===(string)$designer->id)>{{ $designer->name }}</option>@endforeach</select></div>

        <div>
            <label class="label" for="total_creatives">Total Number of Creatives *</label>
            <input class="field" id="total_creatives" name="total_creatives" type="number"
                   min="1" max="9999" step="1" inputmode="numeric"
                   value="{{ old('total_creatives',1) }}" placeholder="Enter total creatives"
                   oninput="if(this.value!==''){this.value=Math.max(1,Math.min(9999,Math.trunc(Number(this.value)||1)));}" required>
        </div>

        <div>
            <label class="label" for="taskNature">Task Nature *</label>
            <select class="field" id="taskNature" name="task_nature" required disabled>
                <option value="">Select vertical first</option>
            </select>
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
</div>
</form>

<datalist id="locationOptions">
    <option value="Chennai"><option value="Coimbatore"><option value="Madurai"><option value="Trichy"><option value="Salem"><option value="Bengaluru"><option value="Kochi"><option value="Hyderabad"><option value="Mumbai"><option value="Delhi">
</datalist>

<script>
const oldValues=@json(old());
const oldVertical=@json(old('vertical'));
const oldNature=@json(old('task_nature'));

const forms={
outdoor:{label:'Outdoor',natures:{
 mockup_requirements:{label:'Mock-up Requirements',fields:[['mockup_type','Mock-up Type','select',true,['Mock-up','Innovative Mock-up']],['description','Description / Content','textarea',true],['site_photo','Site Photo','file'],['creative','Creative','file'],['website_link','Website Link','url'],['supporting_documents','Reference / Supporting Documents','files'],['client_audio','Client Audio','audio']]},
 creative_adaptation:{label:'Creative Adaptation',fields:[['description','Description / Content','textarea',true],['site_photo','Site Photo','file'],['creative','Creative','file'],['supporting_documents','Excel / PPT / Other Documents','files'],['reference_image','Reference Image','file'],['client_audio','Client Audio','audio']]},
 new_creative_design:{label:'New Creative Design',fields:[['description','Description / Content','textarea',true],['content_images','Content Image / Video','mediafiles'],['logo_images','Logo Images','files'],['brand_name','Brand Name','text',true],['creative_contact_person','Contact Person','text'],['creative_mobile_number','Mobile Number','text'],['address','Address','textarea'],['company_details_document','Company Details Document','file'],['supporting_documents','Excel / PPT / Other Documents','files'],['client_audio','Client Audio','audio']]},
 cutout_size_calculation:{label:'3D Cut-out Size Calculation',fields:[['description','Description','textarea',true],['hoarding_artwork','Hoarding Image / Open Artwork','file',true],['supporting_documents','Excel / PPT / Other Documents','files'],['client_audio','Client Audio','audio']]}
}},
roadshow:{label:'RoadShow',natures:{
 creative_adaptation_requirements:{label:'Creative Adaptation Requirements',fields:[
  ['roadshow_subtype','Task Nature Type','select',true,['Creative Adaptation','3D Mockup Creative Adaptation']],
  ['description','Description / Content','textarea'],
  ['description_upload','Reference','file'],
  ['vehicle_type','Vehicle Name','vehicle_select',true,['3 Side LED 14 feet','3 Side LED 18 feet','7x5 LED Hybrid 8 feet','Box Model Triangle Roof','Center Portion Triangle Roof','Center Portion Without Roof','L-Model Box Roof with Utility Room','L-Model Box Roof','L-Model Without Roof','L-Shape LED','Single Side LED 17 feet','Static Model']],
  ['creative','Creative','file'],
  ['vehicle_quantity','Quantity of Vehicles','number'],
  ['location','Location','location'],
  ['supporting_documents','Reference / Excel / PPT / Other Documents','files'],
  ['client_audio','Client Audio','audio']
 ]},
 new_creative_design:{label:'New Creative Design',fields:[
  ['roadshow_subtype','Task Nature Type','select',true,['New Creative Design','3D Mockup New Creative Design']],
  ['vehicle_type','Vehicle Name','vehicle_select',true,['3 Side LED 14 feet','3 Side LED 18 feet','7x5 LED Hybrid 8 feet','Box Model Triangle Roof','Center Portion Triangle Roof','Center Portion Without Roof','L-Model Box Roof with Utility Room','L-Model Box Roof','L-Model Without Roof','L-Shape LED','Single Side LED 17 feet','Static Model']],
  ['vehicle_details','Vehicle Details','file'],
  ['description','Description / Content','textarea'],
  ['description_upload','Reference','file'],
  ['logo_images','Logo','files'],
  ['reference_images','Reference Images','files'],
  ['content_images','Content Image / Video','mediafiles'],
  ['brand_details','Brand Details','textarea'],
  ['brand_details_upload','Brand Details Upload','file'],
  ['brand_name','Brand Name','text'],
  ['creative_contact_person','Contact Person','text'],
  ['creative_mobile_number','Mobile Number','text'],
  ['address','Address','textarea'],
  ['supporting_documents','Excel / PPT / Other Documents','files'],
  ['client_audio','Client Audio','audio']
 ]}
}},
fixtures:{label:'Fixtures',natures:{
 design_with_creative:{label:'Design with Creative Given',fields:[['description','Description','textarea',true],['recce_report','Recce Report','file',true],['client_format_manual','Client Format Manual','file'],['creative','Creative','file'],['fixture_details','All Fixture Details','file',true],['additional_attachments','References','files'],['client_audio','Client Recording','audio']]},
 design_without_creative:{label:'Design without Creative',fields:[['description','Description','textarea',true],['recce_report','Recce Report (PPT/PDF/JPG)','file',true],['logo_images','Client Logo','files'],['website_link','Client Website','url'],['client_format_manual','Client Format Manual','file'],['fixture_details','All Fixture Details (Excel)','file',true],['reference_images','References','files'],['client_audio','Client Recording','audio']]}
}},
signage:{label:'Signage',natures:{
 mockup:{label:'Mock Up',fields:[
  ['description','Description','textarea',true],
  ['recce_report','Recce PPT','file',true],
  ['material_specifications','Material Detail','file'],
  ['client_format_manual','Client Format Manual','file'],
  ['creative','Creative (Optional)','file'],
  ['reference_images','References','files'],
  ['client_audio','Client Recording','audio']
 ]},
 creative_adaptation:{label:'Creative Adaptation',fields:[
  ['description','Description','textarea',true],
  ['recce_report','Recce PPT','file'],
  ['material_specifications','Material Detail','file',true],
  ['client_format_manual','Client Format Manual','file'],
  ['creative','Creative','file',true],
  ['dealer_details','Dealer Details','file'],
  ['reference_images','References','files'],
  ['client_audio','Client Recording','audio']
 ]},
 new_creative:{label:'New Creative',fields:[
  ['description','Description','textarea',true],
  ['recce_report','Recce PPT','file',true],
  ['material_specifications','Material Detail','file',true],
  ['logo_images','Logo','files'],
  ['client_format_manual','Client Format Manual','file'],
  ['dealer_details','Dealer Details','file'],
  ['reference_images','References','files'],
  ['client_audio','Audio Reference','audio']
 ]},
 technical_drawing:{label:'Technical Drawing Design',fields:[
  ['description','Description','textarea',true],
  ['recce_report','Recce PPT','file',true],
  ['creative','Creative','file'],
  ['logo_images','Logo','files'],
  ['client_format_manual','Client Format Manual','file'],
  ['reference_images','References','files'],
  ['client_audio','Audio Reference','audio']
 ]},
 three_d_design:{label:'3D Design',fields:[
  
  ['description','Description','textarea',true],
  ['recce_report','Recce PPT','file',true],
  ['technical_drawing','Technical Drawing','file'],
  ['creative','Creative','file'],
  ['logo_images','Logo','files'],
  ['client_format_manual','Client Format Manual','file'],
  ['reference_images','References','files'],
  ['client_audio','Audio Reference','audio']
 ]},
 technical_and_three_d:{label:'Technical Drawing & 3D Design',fields:[
  ['description','Description','textarea',true],
  ['recce_report','Recce PPT','file',true],
  ['creative','Creative','file'],
  ['logo_images','Logo','files'],
  ['client_format_manual','Client Format Manual','file'],
  ['reference_images','References','files'],
  ['client_audio','Audio Reference','audio']
 ]}
}},
pop_offsets:{label:'POP and Offsets',natures:{
 mockup_design:{label:'Mockup Design',fields:[
  ['product_type','Product Type','select',true,['Leaflets','Poster','Brochure','Visiting Card','Pocket Card','Dangler','Roll Up Standee','Sunpack Sheet','Calendar','ID Card','Other']],
  ['description','Description','textarea',true],
  ['company_details','Company Details','textarea',true],
  ['logo_images','Logo','files'],
  ['creative','Creative','file'],
  ['reference_images','References','files'],
  ['client_audio','Client Call Recording','audio']
 ]},
 design_adaptation:{label:'Creative Adaptation',fields:[
  ['product_type','Product Type','select',true,['Leaflets','Poster','Brochure','Visiting Card','Pocket Card','Dangler','Roll Up Standee','Sunpack Sheet','Calendar','ID Card','Other']],
  ['description','Description','textarea',true],
  ['company_details','Company Details','textarea',true],
  ['logo_images','Logo','files'],
  ['size_details','Size Details','sizes',true],
  ['creative','Creative','file'],
  ['reference_images','References','files'],
  ['element_list','Element List','file'],
  ['client_audio','Client Call Recording','audio']
 ]},
 creative_design:{label:'Own Creative',fields:[
  
  ['product_type','Product Type','select',true,['Leaflets','Poster','Brochure','Visiting Card','Pocket Card','Dangler','Roll Up Standee','Sunpack Sheet','Calendar','ID Card','Other']],
  ['description','Description','textarea',true],
  ['company_details','Company Details','textarea',true],
  ['logo_images','Logo','files'],
  ['size_details','Size Details','sizes',true],
  ['reference_images','References','files'],
  ['element_list','Element List','file'],
  ['client_audio','Client Call Recording','audio']
 ]}
}},
events_activations:{label:'Events and Activations',natures:{
 proposal_designs:{label:'Proposal Designs',fields:[['description','Description','textarea',true],['location','Location','location'],['requirement_list','Requirement List','file',true],['reference_images','References','files'],['client_audio','Audio Reference','audio']]},
 element_design_with_creative:{label:'Element Design with Creative Given',fields:[['description','Description','textarea',true],['location','Location','location'],['creative','Creatives','file',true],['reference_images','Reference Images','files'],['recce_report','Recce','file',true],['brand_guidelines','Brand Guidelines','file'],['requirement_list','Requirement List','file',true],['client_audio','Audio Reference','audio']]},
 element_design_without_creative:{label:'Element Design Creative Not Given',fields:[['description','Description','textarea',true],['location','Location','location'],['logo_images','Logo','files'],['reference_images','References','files'],['recce_report','Recce','file'],['brand_guidelines','Brand Guidelines','file'],['requirement_list','Requirement List','file'],['client_audio','Audio Reference','audio']]},
 three_d_layout:{label:'3D Layout',fields:[['description','Description','textarea',true],['location','Location','location'],['reference_images','References','files'],['recce_report','Recce','file'],['brand_guidelines','Brand Guidelines','file'],['requirement_list','Requirement List','file',true],['client_audio','Audio Reference','audio']]}
}},
media:{label:'Media',natures:{
 theatre_ads:{label:'Theatre Ads',fields:[
  ['media_task_nature','Task Nature','select',true,['Creative Adaptation','Own Creative']],
  ['theatre_screen_name','Theatre / Screen Name','text',true],
  ['ad_type','Ad Type','select',true,['Slide','Video']],
  ['screen_width','Screen Size – Width','number',true],
  ['screen_height','Screen Size – Height','number',true],
  ['screen_ratio','Screen Ratio','text',true],
  ['description','Description / Content','textarea',true],
  ['creative','Creative Upload','file'],
  ['video_clip','Video Clip','mediafiles'],
  ['reference_images','Reference Attachment','files'],
  ['company_details','Company / Brand Details','textarea'],
  ['logo_images','Logo Image','files'],
  ['creative_content_details','Creative Content / Details','textarea'],
  ['creative_content_upload','Creative Content Upload','file'],
  ['client_audio','Client Audio','audio']
 ]},
 newspaper_ads:{label:'News Paper Ads',fields:[
  ['media_task_nature','Task Nature','select',true,['Creative Adaptation','Own Creative']],
  ['description','Description / Content','textarea',true],
  ['creative_width','Creative Size – Width','number',true],
  ['creative_height','Creative Size – Height','number',true],
  ['size_unit','Size Unit','select',true,['px','mm','cm','inch','ft']],
  ['creative','Creative Upload','file'],
  ['reference_images','Reference Image / Attachment','files'],
  ['company_details','Company / Brand Details','textarea'],
  ['logo_brand_image','Logo / Brand Image','files']
 ]},
 fm:{label:'FM',fields:[
  ['media_task_nature','Task Nature','select',true,['Creative Adaptation','Own Creative']],
  ['fm_station','FM Station / Channel','text',true],
  ['description','Description / Content','textarea',true],
  ['existing_audio_creative','Existing Audio / Creative Upload','audio'],
  ['reference_images','Reference Attachment','files'],
  ['company_details','Company / Brand Details','textarea'],
  ['client_audio','Client Audio / Voice Reference','audio']
 ]},
 tv_ads:{label:'TV Ads',fields:[
  ['media_task_nature','Task Nature','select',true,['Creative Adaptation','Own Creative']],
  ['tv_type','TV Type','select',true,['Local','Satellite','Channel']],
  ['ad_type','Ad Type','select',true,['Slide','Video']],
  ['creative_width','Creative Size – Width','number',true],
  ['creative_height','Creative Size – Height','number',true],
  ['description','Description / Content','textarea',true],
  ['creative','Creative Upload / Creative Content / Sample Image','file'],
  ['video_clip','Video Clip','mediafiles'],
  ['sample_video_clip','Sample Video Clip','mediafiles'],
  ['company_details','Company / Brand Details','textarea'],
  ['logo_images','Logo Image','files'],
  ['screen_ratio','Screen Ratio','text'],
  ['reference_images','Reference Attachment','files'],
  ['client_audio','Client Audio','audio']
 ]}
}}};

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

function esc(value=''){return String(value??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
function previous(name){return oldValues[name]??'';}

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
function validateField(input,show=true){
 if(!input||input.disabled||input.type==='hidden'||input.readOnly)return true;
 const value=input.type==='file'?(input.files?.length||0):String(input.value??'').trim();
 let message='';
 if(input.required&&!value)message=`${getLabel(input)} is required.`;
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

function uploadHtml(name,label,required=false,accept=''){
 const star=required?' *':'',req=required?'required':'',acceptAttr=accept?`accept="${accept}"`:'';
 return `<div class="md:col-span-2"><label class="label">${label}${star}</label><input class="field" type="file" name="${name}[]" multiple ${acceptAttr} ${req}><p class="text-xs text-slate-500 mt-1">Multiple files are allowed.</p></div>`;
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
  <div class="dimension-upload"><label class="label">Or Upload Board Details / Dimensions</label><input class="field" type="file" name="dimension_upload[]" multiple data-dimension-upload><p class="text-xs text-slate-500 mt-1">Multiple files are allowed. Either a complete row or an upload is mandatory.</p></div>
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
  <div class="dimension-upload"><label class="label">Or Upload Size Details</label><input class="field" type="file" name="size_upload[]" multiple data-size-upload><p class="text-xs text-slate-500 mt-1">Multiple files are allowed. Either a complete row or an upload is mandatory.</p></div>
  <p class="live-field-error hidden" data-size-error></p>
 </div>`;
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
 if(type==='audio')return uploadHtml(name,label,required,'audio/*,.mp3,.wav,.m4a,.aac,.ogg');
 if(type==='location')return `<div class="${wrap}"><label class="label">${label}${star}</label><input class="field" type="text" list="locationOptions" name="${name}" value="${old}" placeholder="Search or enter location" ${req}></div>`;
 if(name==='creative_mobile_number')return `<div class="${wrap}"><label class="label">${label}${star}</label><input class="field" type="text" name="${name}" value="${old}" inputmode="numeric" pattern="[0-9]{10}" minlength="10" maxlength="10" placeholder="Enter 10-digit mobile number" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)" ${req}></div>`;
 if(type==='number')return `<div class="${wrap}"><label class="label">${label}${star}</label><input class="field" type="number" name="${name}" value="${old}" min="1" max="9999" step="1" inputmode="numeric" ${req}></div>`;
 const inputType=['url','email'].includes(type)?type:'text';
 return `<div class="${wrap}"><label class="label">${label}${star}</label><input class="field" type="${inputType}" name="${name}" value="${old}" ${req}></div>`;
}
function effectiveFields(form){
 let fields=[...form.fields];
 if(vertical.value==='outdoor'){
  const prefix=[['outdoor_type','Outdoor Type','select',true,outdoorTypes]];
  if(outdoorBoardTypeNatures.has(nature.value))prefix.push(['board_type','Board Type','select',true,['Static','Digital']]);
  prefix.push(['board_details','Board Details','dimensions',true]);
  fields=[...prefix,...fields];
 }
 if(companyDetailsNatures.has(`${vertical.value}.${nature.value}`))fields.push(['company_details','Company Details','textarea']);
 return fields;
}
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
 if(hasPartial)message='Complete Name, Width and Height for every Board Details row, or remove the incomplete row.';
 else if(!hasComplete&&!hasUpload)message='Provide at least one complete Board Details row or upload the Board Details.';
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


function applyMediaTaskNatureVisibility(){
 if(vertical.value!=='media')return;

 const mediaNatureSelect=fieldsBox.querySelector('select[name="media_task_nature"]');
 if(!mediaNatureSelect)return;

 const mediaType=nature.value;

 const rows=[...fieldsBox.children];
 const findWrap=name=>fieldsBox.querySelector(`[name="${name}"]`)?.closest('div.md\\:col-span-2, div');

 const setVisible=(name,visible,required=false)=>{
  const input=fieldsBox.querySelector(`[name="${name}"], [name="${name}[]"]`);
  const wrap=input?.closest('div.md\\:col-span-2, div');
  if(!input||!wrap)return;
  wrap.classList.toggle('hidden',!visible);
  input.required=visible&&required;
  if(!visible){
   if(input.type==='file')input.value='';
   else input.value='';
   setError(input,'');
  }
 };

 const refresh=()=>{
  const mode=mediaNatureSelect.value;
  const own=mode==='Own Creative';
  const adapt=mode==='Creative Adaptation';

  if(mediaType==='theatre_ads'){
   setVisible('description',adapt||own,true);
   setVisible('creative',adapt,false);
   setVisible('video_clip',adapt,false);
   setVisible('reference_images',adapt||own,false);
   setVisible('client_audio',adapt||own,false);
   setVisible('company_details',own,true);
   setVisible('logo_images',own,false);
   setVisible('creative_content_details',own,false);
   setVisible('creative_content_upload',own,false);
  }

  if(mediaType==='newspaper_ads'){
   setVisible('creative',adapt,false);
   setVisible('reference_images',adapt||own,false);
   setVisible('company_details',own,true);
   setVisible('logo_brand_image',own,false);
  }

  if(mediaType==='fm'){
   setVisible('existing_audio_creative',adapt,false);
   setVisible('reference_images',adapt||own,false);
   setVisible('client_audio',adapt||own,false);
   setVisible('company_details',own,true);
  }

  if(mediaType==='tv_ads'){
   setVisible('creative',adapt||own,false);
   setVisible('video_clip',adapt,false);
   setVisible('sample_video_clip',own,false);
   setVisible('company_details',own,true);
   setVisible('logo_images',own,false);
   setVisible('screen_ratio',own,true);
   setVisible('reference_images',adapt||own,false);
   setVisible('client_audio',adapt||own,false);
  }
 };

 if(mediaNatureSelect.dataset.mediaBound!=='1'){
  mediaNatureSelect.dataset.mediaBound='1';
  mediaNatureSelect.addEventListener('change',refresh);
 }
 refresh();
}


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
 section.classList.toggle('hidden',!form);submit.disabled=!form;
 if(!form){fieldsBox.innerHTML='';return;}
 title.textContent=`${cfg.label} — ${form.label}`;
 fieldsBox.innerHTML=effectiveFields(form).map(fieldHtml).join('');
 bindDimensions();bindSizes();bindOtherFields(fieldsBox);bindLiveValidation(fieldsBox);bindRoadshowVehiclePreview();applyMediaTaskNatureVisibility();
}

vertical.addEventListener('change',()=>populateNatures());
nature.addEventListener('change',renderFields);
document.getElementById('partyType').addEventListener('change',e=>document.getElementById('partyNameLabel').textContent=(e.target.value==='agency'?'Agency':'Client')+' Name *');

taskForm.addEventListener('reset',()=>setTimeout(()=>{vertical.value='';populateNatures();taskForm.querySelectorAll('.has-error').forEach(el=>el.classList.remove('has-error'));taskForm.querySelectorAll('.live-field-error').forEach(el=>el.classList.add('hidden'));},0));
taskForm.addEventListener('submit',event=>{
 let valid=true;
 taskForm.querySelectorAll('input,select,textarea').forEach(input=>{if(!validateField(input,true))valid=false;});
 if(!validateDimensions(true))valid=false;
 if(!validateSizes(true))valid=false;
 if(!valid){event.preventDefault();taskForm.querySelector('.has-error,[data-dimension-error]:not(.hidden)')?.scrollIntoView({behavior:'smooth',block:'center'});}
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