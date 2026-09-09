@extends('layouts.app')
@section('title','My Profile')
@section('workspace-title','My Profile')
@section('workspace-subtitle','Your account details and password')
@section('content')
<div class="page-head"><div><h1>My Profile</h1><p>Account details are managed by an administrator. You can update your password here.</p></div></div>

<div class="panel" style="max-width:640px"><div class="panel-body">
    <div class="form-grid" style="margin-bottom:18px">
        <div><label class="label">Full Name</label><input class="premium-input" value="{{ $user->name }}" disabled></div>
        <div><label class="label">Username</label><input class="premium-input" value="{{ $user->username ?? '—' }}" disabled></div>
        <div><label class="label">Employee Code</label><input class="premium-input" value="{{ $user->employee_code ?? '—' }}" disabled></div>
        <div><label class="label">Email Address</label><input class="premium-input" value="{{ $user->email }}" disabled></div>
        <div><label class="label">Last Login</label><input class="premium-input" value="{{ optional($user->last_login_at)->format('d M Y \• h:i A') ?? 'This is your first login' }}" disabled></div>
    </div>

    <form method="POST" action="{{ route('profile.password.update') }}">
        @csrf
        @method('PUT')
        @if($errors->any())<div class="flash flash-error">{{ $errors->first() }}</div>@endif

        <div class="form-grid">
            <div>
                <label class="label" for="profile-password">New Password</label>
                <div style="position:relative">
                    <input class="premium-input" id="profile-password" type="password" name="password" required style="padding-right:65px">
                    <button type="button" onclick="const i=document.getElementById('profile-password');const b=this;if(i.type==='password'){i.type='text';b.innerText='Hide';}else{i.type='password';b.innerText='Show';}" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);border:0;background:transparent;padding:0;margin:0;font-size:11px;font-weight:800;color:#667085;cursor:pointer;width:auto">Show</button>
                </div>
            </div>
            <div>
                <label class="label" for="profile-password-confirmation">Confirm New Password</label>
                <div style="position:relative">
                    <input class="premium-input" id="profile-password-confirmation" type="password" name="password_confirmation" required style="padding-right:65px">
                    <button type="button" onclick="const i=document.getElementById('profile-password-confirmation');const b=this;if(i.type==='password'){i.type='text';b.innerText='Hide';}else{i.type='password';b.innerText='Show';}" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);border:0;background:transparent;padding:0;margin:0;font-size:11px;font-weight:800;color:#667085;cursor:pointer;width:auto">Show</button>
                </div>
            </div>
        </div>

        <div class="form-actions"><button class="btn btn-primary">Update Password</button></div>
    </form>
</div></div>

@if($user->role === 'designer')
    <div class="panel" style="max-width:640px;margin-top:18px"><div class="panel-body">
        <h2 style="font-size:14px;font-weight:900;margin:0 0 4px">Experienced Verticals & Skills</h2>
        <p style="font-size:11px;color:#667085;margin:0 0 16px">Keep this up to date so BD can see your capability before assigning a task.</p>

        <form
            method="POST"
            action="{{ route('profile.designer-profile.update') }}"
            x-data="{
                verticalLabels: @js(\App\Http\Controllers\Bd\TaskController::VERTICALS),
                verticals: @js(old('experienced_verticals', $user->experienced_verticals ?? [])),
                verticalToAdd: '',
                skills: @js(old('skills', $user->skills ?? [])),
                skillInput: '',
                addVertical(){
                    if(!this.verticalToAdd) return;
                    if(!this.verticals.includes(this.verticalToAdd)) this.verticals.push(this.verticalToAdd);
                    this.verticalToAdd = '';
                },
                addSkill(){
                    const v = this.skillInput.trim();
                    if(!v) return;
                    if(!this.skills.some(s => s.toLowerCase() === v.toLowerCase())) this.skills.push(v);
                    this.skillInput = '';
                }
            }"
        >
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div>
                    <label class="label">Experienced Verticals</label>
                    <select class="premium-select" x-model="verticalToAdd" @change="addVertical()">
                        <option value="">Add a vertical…</option>
                        @foreach(\App\Http\Controllers\Bd\TaskController::VERTICALS as $vKey=>$vLabel)
                            <option value="{{ $vKey }}" :disabled="verticals.includes('{{ $vKey }}')">{{ $vLabel }}</option>
                        @endforeach
                    </select>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">
                        <template x-for="(v, index) in verticals" :key="v">
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 8px;border-radius:999px;background:#f2f4f7;border:1px solid #e4e7ec;font-size:11px;font-weight:700">
                                <span x-text="verticalLabels[v] ?? v"></span>
                                <button type="button" @click="verticals.splice(index,1)" style="border:0;background:transparent;padding:0;margin:0;cursor:pointer;font-size:13px;line-height:1;color:#667085">&times;</button>
                                <input type="hidden" name="experienced_verticals[]" :value="v">
                            </span>
                        </template>
                    </div>
                </div>

                <div>
                    <label class="label" for="profile-skill-input">Skills</label>
                    <input
                        class="premium-input"
                        id="profile-skill-input"
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

            <div class="form-actions"><button class="btn btn-primary">Save Profile Details</button></div>
        </form>
    </div></div>
@endif
@endsection
