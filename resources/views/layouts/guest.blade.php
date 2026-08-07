<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign In')</title>
    <link rel="stylesheet" href="{{ asset('css/adinn-premium.css') }}">
    <style>
        body{background:#f4f5f8}.auth-shell{min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at 15% 10%,rgba(227,6,19,.08),transparent 26rem),#f4f5f8}.auth-card{width:min(430px,100%);background:#fff;border:1px solid #e7e9ef;border-radius:20px;box-shadow:0 28px 70px rgba(16,24,40,.12);padding:30px}.auth-brand{display:flex;justify-content:center;margin-bottom:25px}.auth-logo{display:block;width:min(220px,72%);height:auto}.auth-card h1{margin:0;text-align:center;font-size:27px;letter-spacing:-.035em;font-weight:950}.auth-card>p{margin:7px auto 23px;max-width:340px;text-align:center;color:#697386;font-size:12px;line-height:1.6}.auth-group{margin-bottom:14px}.auth-options{display:flex;align-items:center;justify-content:space-between;margin:4px 0 15px;font-size:11px;color:#697386}.auth-submit{width:100%;padding:12px}.auth-foot{margin-top:18px;padding-top:15px;border-top:1px solid #edf0f3;text-align:center;font-size:10px;color:#8b929e;line-height:1.6}
    </style>
</head>
<body>@yield('content')</body>
</html>
