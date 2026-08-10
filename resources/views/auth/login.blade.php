@extends('layouts.guest')

@section('title', 'Sign in - Adinn Design Task Manager')

@section('content')
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-brand">
            <img src="{{ asset('images/adinn-logo.png') }}" alt="Adinn Advertising Services Ltd." class="auth-logo">
        </div>

        <h1>Welcome back</h1>
        <p>Sign in to Adinn Design Task Manager. Your workspace will open automatically based on your account role.</p>

        @if($errors->any())
            <div class="flash flash-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <div class="auth-group">
                <label class="label" for="email">Email address</label>
                <input class="premium-input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus placeholder="name@adinn.com">
            </div>

<div class="auth-group">
    <label class="label" for="password">Password</label>

    <div style="position: relative;">
        <input
            class="premium-input"
            id="password"
            name="password"
            type="password"
            autocomplete="current-password"
            required
            placeholder="Enter your password"
            style="padding-right: 65px;"
        >

        <button
            type="button"
            id="togglePassword"
            onclick="
                const input = document.getElementById('password');
                const button = document.getElementById('togglePassword');

                if (input.type === 'password') {
                    input.type = 'text';
                    button.innerText = 'Hide';
                } else {
                    input.type = 'password';
                    button.innerText = 'Show';
                }
            "
            style="
                position: absolute;
                right: 14px;
                top: 50%;
                transform: translateY(-50%);
                border: 0;
                background: transparent;
                padding: 0;
                margin: 0;
                font-size: 11px;
                font-weight: 800;
                color: #667085;
                cursor: pointer;
                width: auto;
            "
        >
            Show
        </button>
    </div>
</div>

            <div class="auth-options">
                <label style="display:flex;align-items:center;gap:7px;"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
                <span>Secure role-based access</span>
            </div>

            <button class="btn btn-primary auth-submit" type="submit">Sign in to workspace</button>
        </form>

        <div class="auth-foot">Authorized Adinn employees only. Your account role determines the workspace and permissions available after sign in.</div>
    </div>
</div>
@endsection
