@extends('layouts.guest')

@section('title', 'Reset password - Adinn Design Task Manager')

@section('content')
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-brand">
            <img src="{{ asset('images/adinn-logo.png') }}" alt="Adinn Advertising Services Ltd." class="auth-logo">
        </div>

        <h1>Reset password</h1>
        <p>Enter the 6-digit OTP we emailed you (valid for {{ (int) config('password-reset.otp_ttl_minutes', 10) }} minutes) and choose a new password.</p>

        @if(session('status'))
            <div class="flash flash-success">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="flash flash-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" onsubmit="const b=this.querySelector('button[type=submit]');if(b){b.disabled=true;b.innerText='Resetting...';}">
            @csrf
            <input type="hidden" name="login" value="{{ $login }}">

            <div class="auth-group">
                <label class="label" for="otp">OTP</label>
                <input class="premium-input" id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus placeholder="6-digit OTP" style="letter-spacing:.4em;font-weight:800;font-size:16px;text-align:center">
            </div>

            <div class="auth-group">
                <label class="label" for="new-password">New Password</label>
                <div style="position:relative">
                    <input class="premium-input" id="new-password" name="password" type="password" autocomplete="new-password" required minlength="8" placeholder="At least 8 characters" style="padding-right:65px">
                    <button type="button" onclick="const i=document.getElementById('new-password');const b=this;if(i.type==='password'){i.type='text';b.innerText='Hide';}else{i.type='password';b.innerText='Show';}" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);border:0;background:transparent;padding:0;margin:0;font-size:11px;font-weight:800;color:#667085;cursor:pointer;width:auto">Show</button>
                </div>
            </div>

            <div class="auth-group">
                <label class="label" for="new-password-confirmation">Confirm New Password</label>
                <div style="position:relative">
                    <input class="premium-input" id="new-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8" placeholder="Re-enter the new password" style="padding-right:65px">
                    <button type="button" onclick="const i=document.getElementById('new-password-confirmation');const b=this;if(i.type==='password'){i.type='text';b.innerText='Hide';}else{i.type='password';b.innerText='Show';}" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);border:0;background:transparent;padding:0;margin:0;font-size:11px;font-weight:800;color:#667085;cursor:pointer;width:auto">Show</button>
                </div>
            </div>

            <button class="btn btn-primary auth-submit" type="submit">Reset password</button>
        </form>

        <form method="POST" action="{{ route('password.email') }}" style="margin-top:12px;text-align:center">
            @csrf
            <input type="hidden" name="login" value="{{ $login }}">
            <span style="font-size:11px;color:#697386">Did not get the mail?</span>
            <button type="submit" style="border:0;background:transparent;padding:0 0 0 4px;font-size:11px;font-weight:800;color:#e30613;cursor:pointer;width:auto">Resend OTP</button>
        </form>

        <div class="auth-foot"><a href="{{ route('login') }}" style="color:#e30613;font-weight:800;text-decoration:none">&larr; Back to sign in</a></div>
    </div>
</div>
@endsection
