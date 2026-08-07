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
@php
    $role = auth()->user()->role ?? null;
    $workspace = match($role) {
        'admin' => 'Administration',
        'bd' => 'Business Development',
        'designer' => 'Designer Workspace',
        'designer_head' => 'Designer Head',
        default => 'Design Operations',
    };
@endphp

<div class="workspace-shell" x-data="{ mobileNav: false }">
    <aside class="workspace-sidebar" :class="{ 'is-open': mobileNav }">
        <div class="sidebar-brand">
            <div class="sidebar-logo-card">
                <img src="{{ asset('images/adinn-logo.png') }}" alt="Adinn Advertising Services Ltd." class="sidebar-logo">
            </div>
            <div class="brand-system">Design Task Manager</div>
        </div>

        <div class="sidebar-workspace">
            <span class="sidebar-eyebrow">Workspace</span>
            <strong>{{ $workspace }}</strong>
        </div>

        <nav class="sidebar-nav">
            @if($role === 'admin')
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><span>▦</span>Dashboard</a>
                <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}"><span>◉</span>User Management</a>
                <a href="{{ route('admin.tasks.index') }}" class="{{ request()->routeIs('admin.tasks.*') ? 'active' : '' }}"><span>▤</span>Task Monitoring</a>
                <a href="{{ route('admin.master.index') }}" class="{{ request()->routeIs('admin.master.*') ? 'active' : '' }}"><span>⌘</span>Master Controls</a>
                <a href="{{ route('admin.activity.index') }}" class="{{ request()->routeIs('admin.activity.*') ? 'active' : '' }}"><span>↻</span>System Activity</a>
            @elseif($role === 'bd')
                <a href="{{ route('bd.tasks.index') }}" class="{{ request()->routeIs('bd.tasks.index','bd.tasks.show') ? 'active' : '' }}"><span>▤</span>Assigned Tasks</a>
                <a href="{{ route('bd.tasks.create') }}" class="{{ request()->routeIs('bd.tasks.create') ? 'active' : '' }}"><span>＋</span>Create Task</a>
            @elseif($role === 'designer')
                <a href="{{ route('designer.tasks.index') }}" class="{{ request()->routeIs('designer.tasks.*') ? 'active' : '' }}"><span>▦</span>My Tasks</a>
            @elseif($role === 'designer_head')
                <a href="{{ route('designer-head.dashboard') }}" class="{{ request()->routeIs('designer-head.dashboard') ? 'active' : '' }}"><span>▦</span>Dashboard</a>
            @endif
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                <div class="sidebar-user-copy">
                    <strong>{{ auth()->user()->name }}</strong>
                    <span>{{ ucwords(str_replace('_', ' ', $role)) }}</span>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="sidebar-logout">Logout</button>
            </form>
        </div>
    </aside>

    <div class="workspace-main">
        <header class="workspace-topbar">
            <div class="topbar-left">
                <button class="mobile-menu-btn" type="button" @click="mobileNav = !mobileNav">☰</button>
                <div>
                    <div class="topbar-title">@yield('workspace-title', $workspace)</div>
                    <div class="topbar-subtitle">@yield('workspace-subtitle', 'Adinn Design Task Manager')</div>
                </div>
            </div>

            <div class="topbar-right">
                <div class="topbar-role-pill">{{ ucwords(str_replace('_', ' ', $role)) }}</div>
                <div class="topbar-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
            </div>
        </header>

        <main class="workspace-content">
            @if(session('success'))
                <div class="flash flash-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="flash flash-error">{{ session('error') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>

@livewireScripts
@stack('scripts')
</body>
</html>
