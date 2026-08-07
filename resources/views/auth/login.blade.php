@extends('layouts.guest')

@section('title','Sign in - Adinn Design Task Manager')

@section('content')
<div class="auth-page">
    <section class="auth-visual">
        <div class="auth-kicker">Adinn Design Operations</div>

        <div>
            <h2 class="auth-heading">One premium workspace for every design task.</h2>
            <p class="auth-copy">
                Business Development assigns work, Designers manage production,
                and every status, file and comment remains clearly traceable.
            </p>
        </div>

        <div class="auth-features">
            <div class="auth-feature"><span class="auth-dot"></span>Role-based access for BD and Designers</div>
            <div class="auth-feature"><span class="auth-dot"></span>Live task pipeline with clear ownership</div>
            <div class="auth-feature"><span class="auth-dot"></span>Organized cloud files and activity history</div>
        </div>
    </section>

    <section class="auth-form-side">
        <div class="auth-card">
            <h1>Welcome back</h1>
            <p>Sign in with your authorized Adinn account.</p>

            @if($errors->any())
                <div class="flash flash-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}">
                @csrf

                <div class="auth-group">
                    <label class="label" for="email">Email address</label>
                    <input class="premium-input"
                           id="email"
                           name="email"
                           type="email"
                           value="{{ old('email') }}"
                           autocomplete="email"
                           required
                           autofocus>
                </div>

                <div class="auth-group">
                    <label class="label" for="password">Password</label>
                    <input class="premium-input"
                           id="password"
                           name="password"
                           type="password"
                           autocomplete="current-password"
                           required>
                </div>

                <label style="display:flex;align-items:center;gap:9px;color:#475467;font-size:13px;">
                    <input type="checkbox" name="remember" value="1">
                    Keep me signed in
                </label>

                <button class="btn btn-primary auth-submit" type="submit">
                    Sign in securely
                </button>
            </form>

            <div class="auth-help">
                Your account will automatically open the correct BD or Designer workspace.
            </div>
        </div>
    </section>
</div>
@endsection
