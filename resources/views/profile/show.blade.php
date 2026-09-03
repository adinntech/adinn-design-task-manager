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
@endsection
