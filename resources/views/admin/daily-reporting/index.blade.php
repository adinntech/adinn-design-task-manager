@extends('layouts.app')
@section('title','Daily Reporting')
@section('workspace-title','Daily Reporting')
@section('workspace-subtitle','Choose who receives the daily report')
@section('content')

@php
    // After a failed save, restore exactly what the Admin submitted (mail only);
    // otherwise show the saved configuration.
    $toInitial = old('to') !== null ? array_map(fn ($m) => ['name' => null, 'mail' => (string) $m], (array) old('to')) : $to;
    $ccInitial = old('cc') !== null ? array_map(fn ($m) => ['name' => null, 'mail' => (string) $m], (array) old('cc')) : $cc;
@endphp

<style>
.dr-field{position:relative}
.dr-chip-box{border:1px solid #d0d5dd;border-radius:10px;padding:6px 8px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;background:#fff;min-height:44px}
.dr-chip-box:focus-within{border-color:#98a2b3;box-shadow:0 0 0 3px rgba(16,24,40,.06)}
.dr-chip-box.has-error{border-color:#f04438}
.dr-chip{background:#eaf2ff;color:#1d4ed8;border-radius:14px;padding:3px 6px 3px 9px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:5px;max-width:100%}
.dr-chip.is-external{background:#f2f4f7;color:#344054}
.dr-chip span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dr-chip button{border:none;background:none;color:inherit;cursor:pointer;font-weight:900;font-size:13px;line-height:1;padding:0 2px}
.dr-input{border:none;outline:none;flex:1;min-width:160px;font-size:12px;padding:5px 3px;background:transparent}
.dr-add{border:1px solid #d0d5dd;background:#fff;border-radius:8px;font-size:11px;font-weight:800;padding:5px 10px;cursor:pointer}
.dr-suggestions{display:none;position:absolute;z-index:30;top:100%;left:0;right:0;margin-top:4px;background:#fff;border:1px solid #d0d5dd;border-radius:10px;max-height:260px;overflow-y:auto;box-shadow:0 8px 20px rgba(16,24,40,.08)}
.dr-suggestion{padding:8px 11px;cursor:pointer;border-bottom:1px solid #f2f4f7}
.dr-suggestion:last-child{border-bottom:0}
.dr-suggestion.is-active,.dr-suggestion:hover{background:#f9fafb}
.dr-suggestion-name{font-size:12px;font-weight:800;color:#101828}
.dr-suggestion-mail{font-size:11px;color:#667085}
.dr-suggestion-empty{padding:8px 11px;font-size:11px;color:#667085}
.dr-help{font-size:11px;color:#667085;margin-top:5px}
.dr-error{font-size:11px;font-weight:700;color:#b42318;margin-top:5px}
</style>

<div class="page-head">
    <div>
        <h1>Daily Reporting</h1>
        <p>Select the To and CC recipients for the daily report. Saving here does not send any email.</p>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <form method="POST" action="{{ route('admin.daily-reporting.update') }}" id="dailyReportingForm" novalidate>
            @csrf
            @method('PUT')

            @foreach(['to' => ['To', $toInitial, true], 'cc' => ['CC', $ccInitial, false]] as $field => [$label, $initial, $required])
                <div style="margin-bottom:18px">
                    <label class="label" for="dr-input-{{ $field }}">{{ $label }}{{ $required ? ' *' : '' }}</label>
                    <div class="dr-field" data-recipient-field="{{ $field }}" data-label="{{ $label }}">
                        <div class="dr-chip-box {{ $errors->has($field) || $errors->has($field.'.*') ? 'has-error' : '' }}" data-chip-box>
                            @foreach($initial as $recipient)
                                <span class="dr-chip {{ empty($recipient['name']) ? 'is-external' : '' }}" data-chip data-mail="{{ $recipient['mail'] }}">
                                    <span>{{ empty($recipient['name']) ? $recipient['mail'] : $recipient['name'].' <'.$recipient['mail'].'>' }}</span>
                                    <button type="button" data-remove-chip aria-label="Remove {{ $recipient['mail'] }}">×</button>
                                    <input type="hidden" name="{{ $field }}[]" value="{{ $recipient['mail'] }}">
                                </span>
                            @endforeach
                            <input type="text" class="dr-input" id="dr-input-{{ $field }}" placeholder="Type a name or email…" autocomplete="off" data-recipient-input>
                            <button type="button" class="dr-add" data-add-external>Add</button>
                        </div>
                        <div class="dr-suggestions" data-suggestions role="listbox"></div>
                    </div>
                    <p class="dr-help">Pick from Manage Email suggestions, or type any email address and press Enter / Add.</p>
                    <p class="dr-error" data-field-error @if(! $errors->has($field) && ! $errors->has($field.'.*')) hidden @endif>
                        {{ $errors->first($field) ?: $errors->first($field.'.*') }}
                    </p>
                </div>
            @endforeach

            <div style="display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary" id="dailyReportingSave">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
(function(){
 const form=document.getElementById('dailyReportingForm');
 const saveBtn=document.getElementById('dailyReportingSave');
 const searchUrl=@json(route('admin.daily-reporting.recipients'));
 const EMAIL_RE=/^[^\s@]+@[^\s@]+\.[^\s@]+$/;
 const fields={};

 function esc(v=''){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
 function mailsOf(name){return [...fields[name].root.querySelectorAll('[data-chip]')].map(c=>c.dataset.mail.toLowerCase());}

 /** Same address may not appear twice in one field, nor in both To and CC. */
 function duplicateMessage(name,mail){
  const key=mail.toLowerCase();
  if(mailsOf(name).includes(key))return `${mail} is already added to ${fields[name].label}.`;
  const other=name==='to'?'cc':'to';
  if(mailsOf(other).includes(key))return `${mail} is already added to ${fields[other].label}.`;
  return '';
 }

 function setError(name,message=''){
  const f=fields[name];
  f.error.textContent=message;
  f.error.hidden=!message;
  f.box.classList.toggle('has-error',!!message);
 }

 function addChip(name,mail,displayName=null){
  mail=String(mail||'').trim();
  const f=fields[name];
  if(!EMAIL_RE.test(mail)){setError(name,'Enter a valid email address.');return false;}
  const dup=duplicateMessage(name,mail);
  if(dup){setError(name,dup);return false;}
  const text=displayName?`${displayName} <${mail}>`:mail;
  f.input.insertAdjacentHTML('beforebegin',`<span class="dr-chip ${displayName?'':'is-external'}" data-chip data-mail="${esc(mail)}"><span>${esc(text)}</span><button type="button" data-remove-chip aria-label="Remove ${esc(mail)}">×</button><input type="hidden" name="${name}[]" value="${esc(mail)}"></span>`);
  f.input.value='';
  hideSuggestions(name);
  setError(name,'');
  return true;
 }

 function hideSuggestions(name){const f=fields[name];f.list.style.display='none';f.list.innerHTML='';f.items=[];f.active=-1;}

 function renderSuggestions(name,rows){
  const f=fields[name];
  const taken=new Set([...mailsOf('to'),...mailsOf('cc')]);
  f.items=rows.filter(r=>!taken.has(String(r.mail).toLowerCase()));
  f.active=-1;
  const typed=f.input.value.trim();
  if(!f.items.length){
   f.list.innerHTML=EMAIL_RE.test(typed)?`<div class="dr-suggestion-empty">Press Enter or Add to use “${esc(typed)}”.</div>`:'<div class="dr-suggestion-empty">No matching users in Manage Email.</div>';
  }else{
   f.list.innerHTML=f.items.map((r,i)=>`<div class="dr-suggestion" role="option" data-index="${i}"><div class="dr-suggestion-name">${esc(r.name)}</div><div class="dr-suggestion-mail">${esc(r.mail)}</div></div>`).join('');
  }
  f.list.style.display='block';
 }

 function highlight(name,index){
  const f=fields[name];
  f.active=index;
  f.list.querySelectorAll('.dr-suggestion').forEach((el,i)=>el.classList.toggle('is-active',i===index));
 }

 document.querySelectorAll('[data-recipient-field]').forEach(root=>{
  const name=root.dataset.recipientField;
  const f=fields[name]={
   root,label:root.dataset.label,
   box:root.querySelector('[data-chip-box]'),
   input:root.querySelector('[data-recipient-input]'),
   list:root.querySelector('[data-suggestions]'),
   error:root.parentElement.querySelector('[data-field-error]'),
   items:[],active:-1,timer:null,controller:null,
  };

  f.box.addEventListener('click',e=>{
   const remove=e.target.closest('[data-remove-chip]');
   if(remove){remove.closest('[data-chip]').remove();setError(name,'');return;}
   if(e.target===f.box)f.input.focus();
  });

  root.querySelector('[data-add-external]').addEventListener('click',()=>{addChip(name,f.input.value);f.input.focus();});

  f.input.addEventListener('input',()=>{
   setError(name,'');
   clearTimeout(f.timer);
   if(f.controller)f.controller.abort();
   const q=f.input.value.trim();
   if(!q){hideSuggestions(name);return;}
   f.timer=setTimeout(()=>{
    f.controller=new AbortController();
    fetch(`${searchUrl}?q=${encodeURIComponent(q)}`,{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},signal:f.controller.signal})
     .then(r=>r.ok?r.json():[])
     .then(rows=>{if(f.input.value.trim()===q)renderSuggestions(name,Array.isArray(rows)?rows:[]);})
     .catch(()=>{});
   },300);
  });

  f.input.addEventListener('keydown',e=>{
   if(e.key==='ArrowDown'&&f.items.length){e.preventDefault();highlight(name,Math.min(f.active+1,f.items.length-1));}
   else if(e.key==='ArrowUp'&&f.items.length){e.preventDefault();highlight(name,Math.max(f.active-1,0));}
   else if(e.key==='Escape'){hideSuggestions(name);}
   else if(e.key==='Enter'){
    e.preventDefault();
    const picked=f.items[f.active];
    if(picked)addChip(name,picked.mail,picked.name);
    else if(f.input.value.trim())addChip(name,f.input.value);
   }
  });

  f.list.addEventListener('mousedown',e=>{
   const row=e.target.closest('.dr-suggestion');
   if(!row)return;
   e.preventDefault();
   const picked=f.items[Number(row.dataset.index)];
   if(picked)addChip(name,picked.mail,picked.name);
  });

  f.input.addEventListener('blur',()=>setTimeout(()=>hideSuggestions(name),150));
 });

 form.addEventListener('submit',e=>{
  if(saveBtn.dataset.submitting==='1'){e.preventDefault();return;}
  let valid=true;
  Object.keys(fields).forEach(name=>{
   if(fields[name].input.value.trim()){
    setError(name,'Press Enter or Add to include the typed address, or clear it before saving.');valid=false;
   }
  });
  if(valid&&!mailsOf('to').length){setError('to','Add at least one To recipient.');valid=false;}
  if(!valid){e.preventDefault();return;}
  saveBtn.dataset.submitting='1';
  saveBtn.disabled=true;
  saveBtn.innerHTML='<span class="btn-spinner"></span>Saving...';
 });

 // bfcache restore must not leave Save stuck disabled.
 window.addEventListener('pageshow',e=>{
  if(!e.persisted)return;
  saveBtn.dataset.submitting='';
  saveBtn.disabled=false;
  saveBtn.textContent='Save';
 });
})();
</script>
@endsection
