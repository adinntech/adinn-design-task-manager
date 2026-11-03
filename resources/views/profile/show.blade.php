@extends('layouts.app')
@section('title','My Profile')
@section('workspace-title','My Profile')
@section('workspace-subtitle','Your account details and password')
@section('content')
<div class="page-head"><div><h1>My Profile</h1><p>Update your profile details below. Email Address is fixed and cannot be changed here.</p></div></div>

<div class="panel" style="max-width:640px"><div class="panel-body">
    <form method="POST" action="{{ route('profile.basic-info.update') }}" onsubmit="const b=this.querySelector('button[type=submit],button:not([type])');if(b){b.disabled=true;b.innerHTML='<span class=btn-spinner></span>Saving...';}">
        @csrf
        @method('PUT')
        @if($errors->any())<div class="flash flash-error">{{ $errors->first() }}</div>@endif

        <div class="form-grid" style="margin-bottom:18px">
            <div><label class="label">Full Name</label><input class="premium-input" name="name" value="{{ old('name',$user->name) }}" required></div>
            <div><label class="label">Username</label><input class="premium-input" name="username" value="{{ old('username',$user->username) }}" required></div>
            <div><label class="label">Employee Code</label><input class="premium-input" name="employee_code" value="{{ old('employee_code',$user->employee_code) }}" required></div>
            <div><label class="label">Role Name</label><input class="premium-input" name="role_name" value="{{ old('role_name',$user->role_name) }}"></div>
            <div><label class="label">Email Address</label><input class="premium-input" value="{{ $user->email }}" disabled></div>
            <div><label class="label">Phone Number</label><input class="premium-input" type="tel" name="phone_number" value="{{ old('phone_number',$user->phone_number) }}" maxlength="10" pattern="[6-9][0-9]{9}" required></div>
            <div><label class="label">Last Login</label><input class="premium-input" value="{{ optional($user->last_login_at)->format('d M Y \• h:i A') ?? 'This is your first login' }}" disabled></div>
        </div>

        <div class="form-actions"><button class="btn btn-primary">Save Profile Details</button></div>
    </form>
</div></div>

<div class="panel" style="max-width:640px;margin-top:18px"><div class="panel-body">
    <form method="POST" action="{{ route('profile.password.update') }}" onsubmit="const b=this.querySelector('button[type=submit],button:not([type])');if(b){b.disabled=true;b.innerHTML='<span class=btn-spinner></span>Updating...';}">
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

@php($pushService = app(\App\Services\WebPushService::class))
<div class="panel" style="max-width:640px;margin-top:18px"><div class="panel-body"
    x-data="{
        available: @js($pushService->isConfigured() && $pushService->isEnabledFor($user)),
        on: @js($pushService->isOnFor($user)),
        busy: false,
        message: '',
        error: false,
        say(text, isError){ this.message = text; this.error = !!isError; },
        save(value){
            return fetch(@js(route('profile.notifications.update')), {
                method: 'PUT',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                body: JSON.stringify({ web_push_enabled: value }),
            }).then(r => { if (!r.ok) throw new Error('save'); return r.json(); });
        },
        async enable(){
            if (!window.AdinnPush || !('Notification' in window)) {
                throw new Error('This browser does not support desktop notifications. Please use the latest Chrome, Edge or Firefox.');
            }
            if (Notification.permission === 'denied') {
                throw new Error('Notifications are blocked for this site. Click the lock icon in the address bar → Site settings → Notifications → Allow, then try again.');
            }
            const permission = Notification.permission === 'granted' ? 'granted' : await Notification.requestPermission();
            if (permission !== 'granted') {
                throw new Error('Notification permission was not allowed in this browser.');
            }
            const res = await window.AdinnPush.subscribe();
            if (!res || !res.ok) throw new Error('Could not register this browser for notifications. Please try again.');
        },
        async toggle(){
            if (this.busy || !this.available) return;
            const target = !this.on;
            this.busy = true;
            this.say('');
            try {
                if (target) await this.enable();
                const data = await this.save(target).catch(() => { throw new Error('Could not save your preference. Please try again.'); });
                this.on = !!data.web_push_enabled;
                this.say(this.on ? 'Web push notifications turned on.' : 'Web push notifications turned off.');
            } catch (e) {
                this.say(e.message || 'Something went wrong. Please try again.', true);
            } finally {
                this.busy = false;
            }
        }
    }">
    <h2 style="font-size:14px;font-weight:900;margin:0 0 4px">Notification Settings</h2>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
        <div style="min-width:0;flex:1">
            <div class="label" style="margin:0">Web Push Notifications</div>
            <p style="font-size:11px;color:#667085;margin:2px 0 0">Enable or disable browser notifications for this account.</p>
        </div>
        <button type="button" role="switch" :aria-checked="on.toString()" aria-label="Web Push Notifications"
            @click="toggle()" :disabled="busy || !available"
            :style="'position:relative;width:46px;height:26px;border-radius:999px;border:0;padding:0;margin:0;flex:none;transition:background .15s;cursor:' + ((busy || !available) ? 'not-allowed' : 'pointer') + ';opacity:' + ((busy || !available) ? '.6' : '1') + ';background:' + (on ? '#16a34a' : '#d0d5dd')">
            <span :style="'position:absolute;top:3px;left:' + (on ? '23px' : '3px') + ';width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(16,24,40,.25);transition:left .15s'"></span>
        </button>
    </div>
    <div style="font-size:11px;font-weight:700;margin-top:8px" :style="'color:' + (on ? '#027a48' : '#667085')" x-text="busy ? 'Saving...' : (on ? 'ON' : 'OFF')"></div>
    <template x-if="!available"><div style="font-size:11px;color:#667085;margin-top:6px">Desktop notifications are not set up on this server yet.</div></template>
    <div x-show="message" x-cloak style="font-size:11px;margin-top:6px" :style="'color:' + (error ? '#b42318' : '#027a48')" x-text="message"></div>
</div></div>

@if($user->role === 'designer')
    <div class="panel" style="max-width:640px;margin-top:18px"><div class="panel-body">
        <h2 style="font-size:14px;font-weight:900;margin:0 0 4px">Experienced Verticals & Skills</h2>
        <p style="font-size:11px;color:#667085;margin:0 0 16px">Keep this up to date so BD can see your capability before assigning a task.</p>

        <form
            method="POST"
            action="{{ route('profile.designer-profile.update') }}"
            onsubmit="const b=this.querySelector('button[type=submit],button:not([type])');if(b){b.disabled=true;b.innerHTML='<span class=btn-spinner></span>Saving...';}"
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

@if($user->role === 'bd')
    <div class="panel" style="max-width:640px;margin-top:18px"><div class="panel-body">
        <h2 style="font-size:14px;font-weight:900;margin:0 0 4px">Working Verticals</h2>
        <p style="font-size:11px;color:#667085;margin:0 0 16px">Keep this up to date so Designers can see which verticals you handle before selecting you.</p>

        <form
            method="POST"
            action="{{ route('profile.bd-profile.update') }}"
            onsubmit="const b=this.querySelector('button[type=submit],button:not([type])');if(b){b.disabled=true;b.innerHTML='<span class=btn-spinner></span>Saving...';}"
            x-data="{
                verticalLabels: @js(\App\Http\Controllers\Bd\TaskController::VERTICALS),
                verticals: @js(old('experienced_verticals', $user->experienced_verticals ?? [])),
                verticalToAdd: '',
                addVertical(){
                    if(!this.verticalToAdd) return;
                    if(!this.verticals.includes(this.verticalToAdd)) this.verticals.push(this.verticalToAdd);
                    this.verticalToAdd = '';
                }
            }"
        >
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div>
                    <label class="label">Working Verticals</label>
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
            </div>

            <div class="form-actions"><button class="btn btn-primary">Save Working Verticals</button></div>
        </form>
    </div></div>
@endif
@endsection
