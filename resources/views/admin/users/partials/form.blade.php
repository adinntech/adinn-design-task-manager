@if($errors->any())<div class="flash flash-error">{{ $errors->first() }}</div>@endif
<div class="form-grid">
<div><label class="label">Full Name</label><input class="premium-input" name="name" value="{{ old('name',$user?->name) }}" required></div>
<div><label class="label">Username</label><input class="premium-input" name="username" value="{{ old('username',$user?->username) }}" required></div>
<div><label class="label">Employee Code</label><input class="premium-input" name="employee_code" value="{{ old('employee_code',$user?->employee_code) }}" required></div>
<div><label class="label">Email Address</label><input class="premium-input" type="email" name="email" value="{{ old('email',$user?->email) }}" required></div>
<div><label class="label">Role</label><select class="premium-select" name="role" required>@foreach(['admin'=>'Admin','bd'=>'BD','designer'=>'Designer','designer_head'=>'Designer Head'] as $k=>$v)<option value="{{ $k }}" @selected(old('role',$user?->role ?? 'designer')===$k)>{{ $v }}</option>@endforeach</select></div>
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
<div class="form-actions"><a class="btn btn-secondary" href="{{ route('admin.users.index') }}">Cancel</a><button class="btn btn-primary">{{ $user ? 'Save Changes' : 'Create User' }}</button></div>
