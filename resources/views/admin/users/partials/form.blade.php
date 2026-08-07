@if($errors->any())<div class="flash flash-error">{{ $errors->first() }}</div>@endif
<div class="form-grid">
<div><label class="label">Full Name</label><input class="premium-input" name="name" value="{{ old('name',$user?->name) }}" required></div>
<div><label class="label">Email Address</label><input class="premium-input" type="email" name="email" value="{{ old('email',$user?->email) }}" required></div>
<div><label class="label">Role</label><select class="premium-select" name="role" required>@foreach(['admin'=>'Admin','bd'=>'BD','designer'=>'Designer','designer_head'=>'Designer Head'] as $k=>$v)<option value="{{ $k }}" @selected(old('role',$user?->role ?? 'designer')===$k)>{{ $v }}</option>@endforeach</select></div>
<div style="display:flex;align-items:end"><label style="display:flex;align-items:center;gap:8px;font-size:12px;font-weight:800"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$user?->is_active ?? true))> Active account</label></div>
<div><label class="label">{{ $user ? 'New Password (optional)' : 'Password' }}</label><input class="premium-input" type="password" name="password" {{ $user ? '' : 'required' }}></div>
<div><label class="label">Confirm Password</label><input class="premium-input" type="password" name="password_confirmation" {{ $user ? '' : 'required' }}></div>
</div>
<div class="form-actions"><a class="btn btn-secondary" href="{{ route('admin.users.index') }}">Cancel</a><button class="btn btn-primary">{{ $user ? 'Save Changes' : 'Create User' }}</button></div>
