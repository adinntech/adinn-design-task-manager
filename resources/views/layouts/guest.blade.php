<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title','Sign In')</title>
    <link rel="stylesheet" href="{{ asset('css/adinn-premium.css') }}">
    <style>
        .auth-page{
            min-height:100vh;display:grid;grid-template-columns:1.05fr .95fr;
            background:#0f1115;
        }
        .auth-visual{
            position:relative;padding:64px;display:flex;flex-direction:column;justify-content:space-between;
            overflow:hidden;color:#fff;
            background:
                linear-gradient(145deg,rgba(227,6,19,.96),rgba(122,0,10,.94)),
                #e30613;
        }
        .auth-visual:before,.auth-visual:after{
            content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.10)
        }
        .auth-visual:before{width:420px;height:420px;right:-120px;top:-110px}
        .auth-visual:after{width:280px;height:280px;left:-90px;bottom:-80px}
        .auth-kicker{font-size:13px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
        .auth-heading{max-width:620px;font-size:58px;line-height:1.02;font-weight:950;letter-spacing:-.05em}
        .auth-copy{max-width:560px;font-size:17px;line-height:1.65;color:rgba(255,255,255,.82)}
        .auth-features{display:grid;gap:13px}
        .auth-feature{display:flex;align-items:center;gap:11px;font-weight:800}
        .auth-dot{width:11px;height:11px;border-radius:50%;background:#fff}
        .auth-form-side{display:grid;place-items:center;padding:36px;background:#f5f7fb}
        .auth-card{
            width:min(470px,100%);background:#fff;border:1px solid #e6e8ee;border-radius:24px;
            box-shadow:0 28px 80px rgba(16,24,40,.14);padding:34px
        }
        .auth-card h1{font-size:34px;font-weight:950;letter-spacing:-.035em;margin:0}
        .auth-card p{color:#667085;margin:8px 0 26px}
        .auth-group{margin-bottom:17px}
        .auth-submit{width:100%;margin-top:8px}
        .auth-help{margin-top:18px;padding:13px;border-radius:12px;background:#f7f8fb;color:#667085;font-size:12px}
        @media(max-width:900px){
            .auth-page{grid-template-columns:1fr}
            .auth-visual{display:none}
            .auth-form-side{padding:20px}
        }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
