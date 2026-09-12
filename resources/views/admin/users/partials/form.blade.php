@if($errors->any())<div class="flash flash-error">{{ $errors->first() }}</div>@endif
<div x-data="{ role: '{{ old('role',$user?->role ?? 'designer') }}' }">
<div class="form-grid">
<div><label class="label">Full Name</label><input class="premium-input" name="name" value="{{ old('name',$user?->name) }}" required></div>
<div><label class="label">Username</label><input class="premium-input" name="username" value="{{ old('username',$user?->username) }}" required></div>
<div><label class="label">Employee Code</label><input class="premium-input" name="employee_code" value="{{ old('employee_code',$user?->employee_code) }}" required></div>
<div><label class="label">Email Address</label><input class="premium-input" type="email" name="email" value="{{ old('email',$user?->email) }}" required></div>
<div><label class="label">Role</label><select class="premium-select" name="role" x-model="role" required>@foreach(['admin'=>'Admin','bd'=>'BD','designer'=>'Designer','designer_head'=>'Designer Head'] as $k=>$v)<option value="{{ $k }}" @selected(old('role',$user?->role ?? 'designer')===$k)>{{ $v }}</option>@endforeach</select></div>
<div style="display:flex;align-items:end"><label style="display:flex;align-items:center;gap:8px;font-size:12px;font-weight:800"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$user?->is_active ?? true))> Active account</label></div>
<div>
    <label class="label" for="user-password">{{ $user ? 'New Password (optional)' : 'Password' }}</label>
    <div style="position:relative">
        <input class="premium-input" id="user-password" type="password" name="password" {{ $user ? '' : 'required' }} style="padding-right:65px">
        <button type="button" onclick="const i=document.getElementById('user-password');const b=this;if(i.type==='password'){i.type='text';b.innerText='Hide';}else{i.type='password';b.innerText='Show';}" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);border:0;background:transparent;padding:0;margin:0;font-size:11px;font-weight:800;color:#667085;cursor:pointer;width:auto">Show</button>
    </div>
</div>
<div>
    <label class="label" for="user-password-confirmation">Confirm Password</label>
    <div style="position:relative">
        <input class="premium-input" id="user-password-confirmation" type="password" name="password_confirmation" {{ $user ? '' : 'required' }} style="padding-right:65px">
        <button type="button" onclick="const i=document.getElementById('user-password-confirmation');const b=this;if(i.type==='password'){i.type='text';b.innerText='Hide';}else{i.type='password';b.innerText='Show';}" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);border:0;background:transparent;padding:0;margin:0;font-size:11px;font-weight:800;color:#667085;cursor:pointer;width:auto">Show</button>
    </div>
</div>
</div>

<div x-show="role === 'designer'" x-cloak style="margin-top:18px;padding-top:18px;border-top:1px solid var(--line, #e4e7ec)">
    <div class="form-grid">
        <div>
            <label class="label">Experienced Verticals</label>
            <div style="display:flex;flex-wrap:wrap;gap:10px;padding:10px 12px;border:1px solid #e4e7ec;border-radius:10px">
                @foreach(\App\Http\Controllers\Bd\TaskController::VERTICALS as $vKey=>$vLabel)
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700">
                        <input type="checkbox" name="experienced_verticals[]" value="{{ $vKey }}" @checked(in_array($vKey, old('experienced_verticals', $user?->experienced_verticals ?? []), true))>
                        {{ $vLabel }}
                    </label>
                @endforeach
            </div>
        </div>

        <div x-data="{
                skills: @js(old('skills', $user?->skills ?? [])),
                skillInput: '',
                addSkill(){
                    const v = this.skillInput.trim();
                    if(!v) return;
                    if(!this.skills.some(s => s.toLowerCase() === v.toLowerCase())) this.skills.push(v);
                    this.skillInput = '';
                }
             }">
            <label class="label" for="skill-input">Skills</label>
            <input
                class="premium-input"
                id="skill-input"
                type="text"
                placeholder="Type a skill and press Enter"
                x-model="skillInput"
                @keydown.enter.prevent="addSkill()"
                @keydown.comma.prevent="addSkill()"
                @blur="addSkill()"
            >
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">
                <template x-for="(skill, index) in skills" :key="skill">
                    <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 8px;border-radius:999px;background:#f2f4f7;border:1px solid #e4e7ec;font-size:11px;font-weight:700">
                        <span x-text="skill"></span>
                        <button type="button" @click="skills.splice(index,1)" style="border:0;background:transparent;padding:0;margin:0;cursor:pointer;font-size:13px;line-height:1;color:#667085">&times;</button>
                        <input type="hidden" name="skills[]" :value="skill">
                    </span>
                </template>
            </div>
        </div>
    </div>
</div>

<div x-show="role === 'bd'" x-cloak style="margin-top:18px;padding-top:18px;border-top:1px solid var(--line, #e4e7ec)">
    <div class="form-grid">
        <div>
            <label class="label">Working Verticals</label>
            <div style="display:flex;flex-wrap:wrap;gap:10px;padding:10px 12px;border:1px solid #e4e7ec;border-radius:10px">
                @foreach(\App\Http\Controllers\Bd\TaskController::VERTICALS as $vKey=>$vLabel)
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700">
                        <input type="checkbox" name="bd_experienced_verticals[]" value="{{ $vKey }}" @checked(in_array($vKey, old('bd_experienced_verticals', $user?->role === 'bd' ? ($user?->experienced_verticals ?? []) : []), true))>
                        {{ $vLabel }}
                    </label>
                @endforeach
            </div>
        </div>
    </div>
</div>
</div>
<div class="form-actions"><a class="btn btn-secondary" href="{{ route('admin.users.index') }}">Cancel</a><button class="btn btn-primary">{{ $user ? 'Save Changes' : 'Create User' }}</button></div>
