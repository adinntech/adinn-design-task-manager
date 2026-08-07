<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Adinn Design Task Manager')</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/adinn-premium.css') }}">
    @livewireStyles
    @stack('styles')
</head>
<body>
<div class="app-shell">
    <header class="app-topbar">
        <div class="app-topbar-inner">
            <div class="brand-block">
                <div class="brand-title">Adinn Design Task Manager</div>
                <div class="brand-subtitle">
                    @auth
                        {{ match(auth()->user()->role) {
                            'bd' => 'Business Development Workspace',
                            'designer' => 'Designer Task Section',
                            'designer_head' => 'Designer Head Workspace',
                            'admin' => 'Administration Workspace',
                            default => 'Task Management Workspace',
                        } }}
                    @else
                        Premium Design Operations
                    @endauth
                </div>
            </div>

            @auth
                <nav class="app-nav">
                    @if(auth()->user()->role === 'admin' && Route::has('admin.dashboard'))
                        <a href="{{ route('admin.dashboard') }}"
                           class="{{ request()->routeIs('admin.*') ? 'active' : '' }}">
                            Admin Dashboard
                        </a>
                    @endif

                    @if(auth()->user()->role === 'bd')
                        @if(Route::has('bd.tasks.create'))
                            <a href="{{ route('bd.tasks.create') }}"
                               class="{{ request()->routeIs('bd.tasks.create') ? 'active' : '' }}">
                                Create Task
                            </a>
                        @endif

                        @if(Route::has('bd.tasks.index'))
                            <a href="{{ route('bd.tasks.index') }}"
                               class="{{ request()->routeIs('bd.tasks.index', 'bd.tasks.show') ? 'active' : '' }}">
                                Assigned Tasks
                            </a>
                        @endif
                    @endif

                    @if(auth()->user()->role === 'designer' && Route::has('designer.tasks.index'))
                        <a href="{{ route('designer.tasks.index') }}"
                           class="{{ request()->routeIs('designer.tasks.*') ? 'active' : '' }}">
                            My Tasks
                        </a>
                    @endif
                </nav>

                <div class="user-menu">
                    <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                    <div class="user-meta">
                        <div class="user-name">{{ auth()->user()->name }}</div>
                        <div class="user-role">{{ ucwords(str_replace('_', ' ', auth()->user()->role)) }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="logout-btn" type="submit">Logout</button>
                    </form>
                </div>
            @endauth
        </div>
    </header>

    <main class="app-main">
        @if(session('success'))
            <div class="flash flash-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="flash flash-error">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>
</div>

@livewireScripts
@stack('scripts')
</body>
</html>
