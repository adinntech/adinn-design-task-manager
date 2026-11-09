@extends('layouts.guest')

@section('title', 'Forgot password - Adinn Design Task Manager')

@section('content')
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-brand">
            <img src="{{ asset('images/adinn-logo.png') }}" alt="Adinn Advertising Services Ltd." class="auth-logo">
        </div>

        <h1>Forgot password?</h1>
        <p>Enter your Username, Email or Employee Code. If it matches your account, we will email you a 6-digit OTP to reset your password.</p>

        @if($errors->any())
            <div class="flash flash-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" onsubmit="const b=this.querySelector('button[type=submit]');if(b){b.disabled=true;b.innerText='Sending OTP...';}">
            @csrf
            <div class="auth-group">
                <label class="label" for="login">Username / Email / Employee Code</label>
                <input class="premium-input" id="login" name="login" type="text" value="{{ old('login') }}" autocomplete="username" required autofocus placeholder="Username, email or employee code">
            </div>

            <button class="btn btn-primary auth-submit" type="submit">Send OTP</button>
        </form>

        <div class="auth-foot"><a href="{{ route('login') }}" style="color:#e30613;font-weight:800;text-decoration:none">&larr; Back to sign in</a></div>
    </div>
</div>
@endsection
