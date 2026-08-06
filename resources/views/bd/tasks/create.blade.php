@extends('layouts.app')
@section('title','Create Design Task')
@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-bold">Create Design Task</h1>
    <p class="text-slate-500 mt-1">Select a vertical and task nature to load the relevant requirement form.</p>
</div>

@if($errors->any())
<div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4">
    <p class="font-semibold text-red-800">Please correct the highlighted fields.</p>
    <ul class="text-sm text-red-700 mt-2 list-disc ml-5">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('bd.tasks.store') }}" enctype="multipart/form-data" id="taskForm" class="space-y-6">
@csrf
<section class="bg-white border rounded-2xl shadow-sm p-6">
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
                    'pop_offsets'=>'POP and Offsets','digital_marketing'=>'Digital Marketing','events_activations'=>'Events and Activations'
                ] as $value=>$label)
                    <option value="{{ $value }}" @selected(old('vertical')===$value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div><label class="label">Client / Agency *</label><select class="field" name="party_type" id="partyType" required><option value="client" @selected(old('party_type')==='client')>Client</option><option value="agency" @selected(old('party_type')==='agency')>Agency</option></select></div>
        <div><label class="label" id="partyNameLabel">Client Name *</label><input class="field" name="party_name" value="{{ old('party_name') }}" required></div>
        <div>
            <label class="label" for="contact_person">Contact Person Name *</label>
            <input class="field" id="contact_person" name="contact_person" type="text" maxlength="100"
                   value="{{ old('contact_person') }}" placeholder="Enter contact person name" required>
        </div>

        <div>
            <label class="label" for="mobile_number">Mobile Number *</label>
            <input class="field" id="mobile_number" name="mobile_number" type="text"
                   inputmode="numeric" pattern="[0-9]{10}" minlength="10" maxlength="10"
                   autocomplete="tel" value="{{ old('mobile_number') }}"
                   placeholder="Enter 10-digit mobile number"
                   oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)" required>
        </div>

        <div><label class="label">Priority *</label><select class="field" name="priority" required>@foreach(['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent'] as $v=>$l)<option value="{{ $v }}" @selected(old('priority')===$v)>{{ $l }}</option>@endforeach</select></div>
        <div><label class="label">Due Date & Time *</label><input class="field" type="datetime-local" name="due_at" value="{{ old('due_at') }}" required></div>
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

<section id="dynamicSection" class="bg-white border rounded-2xl shadow-sm p-6 hidden">
    <div class="mb-5">
        <h2 id="dynamicTitle" class="text-lg font-bold">Requirements</h2>
        <p class="text-sm text-slate-500">Fields are loaded based on the selected vertical and task nature.</p>
    </div>
    <div id="dynamicFields" class="grid md:grid-cols-2 gap-5"></div>
</section>

<div class="flex justify-end gap-3">
    <button type="reset" class="px-5 py-3 rounded-xl border bg-white">Clear</button>
    <button id="submitBtn" disabled class="px-6 py-3 rounded-xl bg-slate-950 text-white font-semibold disabled:opacity-40">Create Task</button>
</div>
</form>

<datalist id="locationOptions">
    <option value="Chennai"><option value="Coimbatore"><option value="Madurai"><option value="Trichy"><option value="Salem"><option value="Bengaluru"><option value="Kochi"><option value="Hyderabad"><option value="Mumbai"><option value="Delhi">
</datalist>

<script>
const oldValues = @json(old());
const oldVertical = @json(old('vertical'));
const oldNature = @json(old('task_nature'));

const forms = {
outdoor: {
 label:'Outdoor',
 natures:{
  mockup_requirements:{label:'Mock-up Requirements',fields:[
   ['mockup_type','Mock-up Type','select',true,['Mock-up','Innovative Mock-up']],['description','Description / Content','textarea',true],['site_photo','Site Photo','file'],['creative','Creative','file'],['board_size','Board Size','board',true],['website_link','Website Link','url'],['supporting_documents','Reference / Supporting Documents','files'],['client_audio','Client Audio','audio']
  ]},
  creative_adaptation:{label:'Creative Adaptation',fields:[
   ['description','Description / Content','textarea',true],['site_photo','Site Photo','file'],['creative','Creative','file'],['board_size','Board Size','board',true],['supporting_documents','Excel / PPT / Other Documents','files'],['reference_image','Reference Image','file'],['client_audio','Client Audio','audio']
  ]},
  new_creative_design:{label:'New Creative Design',fields:[
   ['description','Description / Content','textarea',true],['content_images','Content Images','files'],['logo_images','Logo Images','files'],['brand_name','Brand Name','text',true],['creative_contact_person','Contact Person','text'],['creative_mobile_number','Mobile Number','text'],['address','Address','textarea'],['company_details_other','Other Company Details','textarea'],['company_details_document','Company Details Document','file'],['supporting_documents','Excel / PPT / Other Documents','files'],['client_audio','Client Audio','audio']
  ]},
  cutout_size_calculation:{label:'3D Cut-out Size Calculation',fields:[
   ['description','Description','textarea',true],['hoarding_artwork','Hoarding Image / Open Artwork','file',true],['supporting_documents','Excel / PPT / Other Documents','files'],['client_audio','Client Audio','audio']
  ]}
 }
},
roadshow:{label:'RoadShow',natures:{
 creative_adaptation_requirements:{label:'Creative Adaptation Requirements',fields:[
  ['roadshow_subtype','Task Nature Type','select',true,['Creative Adaptation','3D Mockup Creative Adaptation']],['description','Description / Content','textarea'],['description_upload','Description Upload','file'],['vehicle_type','Type of Vehicle','select',true,['Promotional Van','Single-sided LED Vehicle','4-sided LED Vehicle','Display Van','Other']],['media','Media','select',true,['LED','Printed Branding','LED + Printed Branding','Audio','Sampling','Other']],['creative','Creative','file'],['vehicle_quantity','Quantity of Vehicles','number'],['location','Location','location'],['supporting_documents','Reference / Excel / PPT / Other Documents','files'],['client_audio','Client Audio','audio']
 ]},
 new_creative_design:{label:'New Creative Design',fields:[
  ['roadshow_subtype','Task Nature Type','select',true,['New Creative Design','3D Mockup New Creative Design']],['vehicle_type','Type of Vehicle','select',true,['Promotional Van','Single-sided LED Vehicle','4-sided LED Vehicle','Display Van','Other']],['vehicle_details','Vehicle Details','file'],['media','Media','select',true,['LED','Printed Branding','LED + Printed Branding','Audio','Sampling','Other']],['description','Description / Content','textarea'],['description_upload','Description Upload','file'],['logo_images','Logo','files'],['reference_images','Reference Images','files'],['content_images','High-resolution Content Images','files'],['brand_details','Brand Details','textarea'],['brand_details_upload','Brand Details Upload','file'],['brand_name','Brand Name','text'],['creative_contact_person','Contact Person','text'],['creative_mobile_number','Mobile Number','text'],['address','Address','textarea'],['company_details_other','Other Company Details','textarea'],['supporting_documents','Excel / PPT / Other Documents','files'],['client_audio','Client Audio','audio']
 ]}
}},
fixtures:{label:'Fixtures',natures:{
 design_with_creative:{label:'Design with Creative Given',fields:[['description','Description','textarea',true],['recce_report','Recce Report','file',true],['client_format_manual','Client Format Manual','file'],['creative','Creative (can be uploaded later)','file'],['fixture_details','All Fixture Details','file',true],['additional_attachments','Additional Attachments','files'],['client_audio','Client Recording','audio']]},
 design_without_creative:{label:'Design without Creative',fields:[['description','Description','textarea',true],['recce_report','Recce Report (PPT/PDF/JPG)','file',true],['logo_images','Client Logo','files'],['website_link','Client Website','url'],['client_format_manual','Client Format Manual','file'],['fixture_details','All Fixture Details (Excel)','file',true],['reference_images','References','files'],['client_audio','Client Recording','audio']]}
}},
signage:{label:'Signage',natures:{
 mockup:{label:'Mock Up',fields:[['description','Description','textarea',true],['recce_report','Recce PPT','file',true],['client_format_manual','Client Format Manual','file'],['creative','Creative (Optional)','file'],['client_audio','Client Recording','audio'],['reference_images','References','files']]},
 creative_adaptation:{label:'Creative Adaptation',fields:[['description','Description','textarea',true],['client_format_manual','Client Format Manual','file'],['creative','Creative','file',true],['material_specifications','Material Specifications','file',true],['dealer_details','Dealer Details (can be uploaded later)','file'],['client_audio','Client Recording','audio'],['reference_images','References','files']]},
 new_creative:{label:'New Creative',fields:[['description','Description','textarea',true],['recce_report','Recce PPT','file',true],['logo_images','Logo','files'],['client_format_manual','Client Format Manual','file'],['dealer_details','Dealer Details','file'],['material_specifications','Material Specifications','file',true],['client_audio','Audio Reference','audio'],['reference_images','References','files']]},
 technical_drawing:{label:'Technical Drawing Design',fields:[['description','Description','textarea',true],['creative','Creative','file'],['recce_report','Recce PPT','file',true],['logo_images','Logo','files'],['client_format_manual','Client Format Manual','file'],['material_specifications','Material Specifications','file',true],['client_audio','Audio Reference','audio'],['reference_images','References','files']]},
 three_d_design:{label:'3D Design',fields:[['signage_subtype','Task Nature','select',true,['New Creative']],['description','Description','textarea',true],['technical_drawing','Technical Drawing','file'],['creative','Creative','file'],['recce_report','Recce PPT','file',true],['logo_images','Logo','files'],['client_format_manual','Client Format Manual','file'],['material_specifications','Material Specifications','file',true],['client_audio','Audio Reference','audio'],['reference_images','References','files']]},
 technical_and_three_d:{label:'Technical Drawing & 3D Design',fields:[['description','Description','textarea',true],['creative','Creative','file'],['recce_report','Recce PPT','file',true],['logo_images','Logo','files'],['client_format_manual','Client Format Manual','file'],['material_specifications','Material Specifications','file',true],['client_audio','Audio Reference','audio'],['reference_images','References','files']]}
}},
pop_offsets:{label:'POP and Offsets',natures:{
 mockup_design:{label:'Mockup Design',fields:[['design_type','Type of Design','select',false,['Display Unit','Counter Unit','Standee','Danglers','Shelf Branding','Other']],['description','Description','textarea',true],['creative','Creative','file'],['client_audio','Client Call Recording','audio'],['reference_images','References','files']]},
 design_adaptation:{label:'Design Adaptation',fields:[['description','Description','textarea',true],['design_type','Design Type','select',false,['Display Unit','Counter Unit','Standee','Danglers','Shelf Branding','Other']],['creative','Creative','file'],['reference_images','References','files'],['element_list','Element List','file'],['client_audio','Client Call Recording','audio']]},
 creative_design:{label:'Creative Design',fields:[['pop_subtype','Task Nature','select',true,['New Creative']],['description','Description','textarea',true],['logo_images','Logo','files'],['reference_images','References','files'],['element_list','Element List','file'],['client_audio','Client Call Recording','audio']]}
}},
digital_marketing:{label:'Digital Marketing',natures:{
 proposal:{label:'Proposal',fields:[['logo_images','Logo','files'],['description','Description','textarea',true],['location','Location','location'],['email_id','Mail ID','email'],['instagram_link','Instagram','url'],['facebook_link','Facebook','url'],['website_link','Website','url'],['client_audio','Client Call Recording','audio'],['reference_images','References','files']]},
 logo_design:{label:'Logo Design',fields:[['description','Description','textarea',true],['website_link','Website','url'],['previous_logo','Previous Logo (if available)','file'],['reference_images','References','files'],['client_audio','Client Call Recording','audio']]},
 poster_design:{label:'Poster Design',fields:[['description','Description','textarea',true],['logo_images','Logo','files'],['ratio','Ratio','select',false,['1:1','4:5','9:16','16:9','Custom']],['creative','Creative (if available)','file'],['email_id','Mail ID','email'],['instagram_link','Instagram','url'],['facebook_link','Facebook','url'],['website_link','Website','url'],['reference_images','References','files'],['client_audio','Client Call Recording','audio']]},
 video_design:{label:'Video Design',fields:[['description','Description','textarea',true],['logo_images','Logo','files'],['creative','Creative (if available)','file'],['ratio','Ratio','select',false,['1:1','4:5','9:16','16:9','Custom']],['email_id','Mail ID','email'],['instagram_link','Instagram','url'],['facebook_link','Facebook','url'],['website_link','Website','url'],['reference_images','References','files'],['client_audio','Client Call Recording','audio']]}
}},
events_activations:{label:'Events and Activations',natures:{
 proposal_designs:{label:'Proposal Designs',fields:[['description','Description','textarea',true],['location','Location','location'],['requirement_list','Requirement List','file',true],['reference_images','References','files'],['client_audio','Audio Reference','audio']]},
 element_design_with_creative:{label:'Element Design with Creative Given',fields:[['description','Description','textarea',true],['location','Location','location'],['creative','Creatives','file',true],['reference_images','Reference Images','files'],['recce_report','Recce','file',true],['brand_guidelines','Brand Guidelines','file'],['requirement_list','Requirement List','file',true],['client_audio','Audio Reference','audio']]},
 element_design_without_creative:{label:'Element Design Creative Not Given',fields:[['description','Description','textarea',true],['location','Location','location'],['logo_images','Logo','files'],['reference_images','References','files'],['recce_report','Recce','file'],['brand_guidelines','Brand Guidelines','file'],['requirement_list','Requirement List','file'],['client_audio','Audio Reference','audio']]},
 three_d_layout:{label:'3D Layout',fields:[['events_subtype','Task Nature','select',true,['3D Layout']],['description','Description','textarea',true],['location','Location','location'],['reference_images','References','files'],['recce_report','Recce','file'],['brand_guidelines','Brand Guidelines','file'],['requirement_list','Requirement List','file',true],['client_audio','Audio Reference','audio']]}
}}
};

const vertical = document.getElementById('vertical');
const nature = document.getElementById('taskNature');
const section = document.getElementById('dynamicSection');
const fieldsBox = document.getElementById('dynamicFields');
const title = document.getElementById('dynamicTitle');
const submit = document.getElementById('submitBtn');

function esc(value=''){ return String(value).replace(/[&<>'"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c])); }
function previous(name){ return oldValues[name] ?? ''; }
function fieldHtml(f){
 const [name,label,type,required=false,options=[]]=f;
 const star=required?' *':''; const req=required?'required':''; const old=esc(previous(name));
 const wrap = name==='description'||name.includes('details')||type==='files'||type==='board' ? 'md:col-span-2' : '';
 if(type==='textarea') return `<div class="${wrap}"><label class="label">${label}${star}</label><textarea class="field" rows="4" name="${name}" ${req}>${old}</textarea></div>`;
 if(type==='select') return `<div class="${wrap}"><label class="label">${label}${star}</label><select class="field" name="${name}" ${req}><option value="">Select</option>${options.map(o=>`<option value="${esc(o)}" ${previous(name)===o?'selected':''}>${esc(o)}</option>`).join('')}</select></div>`;
 if(type==='file') return `<div class="${wrap}"><label class="label">${label}${star}</label><input class="field" type="file" name="${name}" ${req}></div>`;
 if(type==='files') return `<div class="${wrap}"><label class="label">${label}${star}</label><input class="field" type="file" name="${name}[]" multiple ${req}><p class="text-xs text-slate-500 mt-1">Multiple files are allowed.</p></div>`;
 if(type==='audio') return `<div class="${wrap}"><label class="label">${label}${star}</label><input class="field" type="file" name="${name}" accept="audio/*,.mp3,.wav,.m4a,.aac,.ogg" ${req}></div>`;
 if(type==='location') return `<div class="${wrap}"><label class="label">${label}${star}</label><input class="field" type="text" list="locationOptions" name="${name}" value="${old}" placeholder="Search or enter location" ${req}></div>`;
 if(type==='board') return `<div class="md:col-span-2 rounded-xl bg-slate-50 border p-4"><label class="label">Board Size (Feet) *</label><div class="grid grid-cols-1 md:grid-cols-3 gap-3"><div><label class="label">Width *</label><input class="field" type="number" min="0.01" step="0.01" name="board_width" id="boardWidth" value="${esc(previous('board_width'))}" required></div><div><label class="label">Height *</label><input class="field" type="number" min="0.01" step="0.01" name="board_height" id="boardHeight" value="${esc(previous('board_height'))}" required></div><div><label class="label">Square Feet</label><div class="field bg-white"><strong id="sqft">0.00</strong></div></div></div></div>`;
 const inputType = ['url','email','number'].includes(type)?type:'text';

 if(name==='creative_mobile_number'){
  return `<div class="${wrap}"><label class="label">${label}${star}</label><input class="field" type="text" name="${name}" value="${old}" inputmode="numeric" pattern="[0-9]{10}" minlength="10" maxlength="10" placeholder="Enter 10-digit mobile number" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)" ${req}></div>`;
 }

 if(type==='number'){
  return `<div class="${wrap}"><label class="label">${label}${star}</label><input class="field" type="number" name="${name}" value="${old}" min="1" max="9999" step="1" inputmode="numeric" oninput="if(this.value!==''){this.value=Math.max(1,Math.min(9999,Math.trunc(Number(this.value)||1)));}" ${req}></div>`;
 }

 return `<div class="${wrap}"><label class="label">${label}${star}</label><input class="field" type="${inputType}" name="${name}" value="${old}" ${req}></div>`;
}
function populateNatures(selected=''){
 nature.innerHTML='<option value="">Select task nature</option>';
 const cfg=forms[vertical.value];
 nature.disabled=!cfg;
 if(!cfg){renderFields();return;}
 Object.entries(cfg.natures).forEach(([value,item])=>nature.insertAdjacentHTML('beforeend',`<option value="${value}" ${selected===value?'selected':''}>${item.label}</option>`));
 renderFields();
}
function renderFields(){
 const cfg=forms[vertical.value]; const form=cfg?.natures?.[nature.value];
 section.classList.toggle('hidden',!form); submit.disabled=!form;
 if(!form){fieldsBox.innerHTML='';return;}
 title.textContent=`${cfg.label} — ${form.label}`;
 fieldsBox.innerHTML=form.fields.map(fieldHtml).join('');
 const w=document.getElementById('boardWidth'), h=document.getElementById('boardHeight'), sqft=document.getElementById('sqft');
 const calc=()=>{if(sqft)sqft.textContent=((parseFloat(w?.value||0))*(parseFloat(h?.value||0))).toFixed(2)};
 w?.addEventListener('input',calc); h?.addEventListener('input',calc); calc();
}
vertical.addEventListener('change',()=>populateNatures());
nature.addEventListener('change',renderFields);
document.getElementById('partyType').addEventListener('change',e=>document.getElementById('partyNameLabel').textContent=(e.target.value==='agency'?'Agency':'Client')+' Name *');
document.getElementById('taskForm').addEventListener('reset',()=>setTimeout(()=>{vertical.value='';populateNatures();},0));
if(oldVertical){vertical.value=oldVertical;populateNatures(oldNature);} else populateNatures();
</script>
@endsection