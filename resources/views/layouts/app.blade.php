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

<style>
    .multi-file-list{
        display:flex;
        flex-direction:column;
        gap:6px;
        margin-top:8px;
    }
    .multi-file-list:empty{display:none}
    .multi-file-item{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        min-height:36px;
        padding:7px 9px;
        border:1px solid #e4e7ec;
        border-radius:9px;
        background:#f8fafc;
    }
    .multi-file-info{
        min-width:0;
        display:flex;
        align-items:center;
        gap:7px;
    }
    .multi-file-icon{
        width:22px;
        height:22px;
        border-radius:6px;
        display:grid;
        place-items:center;
        background:#fff0f1;
        color:#e30613;
        font-size:11px;
        font-weight:900;
        flex:0 0 auto;
    }
    .multi-file-copy{min-width:0}
    .multi-file-name{
        display:block;
        max-width:460px;
        overflow:hidden;
        text-overflow:ellipsis;
        white-space:nowrap;
        font-size:12px;
        font-weight:800;
        color:#344054;
    }
    .multi-file-size{
        display:block;
        margin-top:2px;
        font-size:10px;
        color:#98a2b3;
    }
    .multi-file-remove{
        width:25px;
        height:25px;
        border:1px solid #fecaca;
        border-radius:7px;
        background:#fff1f2;
        color:#b42318;
        font-size:16px;
        line-height:1;
        font-weight:700;
        cursor:pointer;
        flex:0 0 auto;
    }
    .multi-file-remove:hover{background:#ffe4e6}
    .multi-file-help{
        margin-top:5px;
        font-size:11px;
        color:#667085;
        line-height:1.5;
    }
    @media(max-width:700px){
        .multi-file-name{max-width:62vw}
    }
</style>

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
                <a href="{{ route('bd.dashboard') }}" class="{{ request()->routeIs('bd.dashboard') ? 'active' : '' }}"><span>▦</span>Dashboard</a>
                <a href="{{ route('bd.tasks.index') }}" class="{{ request()->routeIs('bd.tasks.index','bd.tasks.show','bd.tasks.edit') ? 'active' : '' }}"><span>▤</span>Assigned Tasks</a>
                <a href="{{ route('bd.tasks.create') }}" class="{{ request()->routeIs('bd.tasks.create') ? 'active' : '' }}"><span>＋</span>Create Task</a>
            @elseif($role === 'designer')
                <a href="{{ route('designer.dashboard') }}" class="{{ request()->routeIs('designer.dashboard') ? 'active' : '' }}"><span>▦</span>Dashboard</a>
                <a href="{{ route('designer.tasks.index') }}" class="{{ request()->routeIs('designer.tasks.*') ? 'active' : '' }}"><span>▤</span>My Tasks</a>
            @elseif($role === 'designer_head')
                <a href="{{ route('designer-head.dashboard') }}" class="{{ request()->routeIs('designer-head.dashboard') ? 'active' : '' }}"><span>▦</span>Dashboard</a>
                <a href="{{ route('designer-head.assigned-tasks') }}" class="{{ request()->routeIs('designer-head.assigned-tasks','designer-head.tasks.*') ? 'active' : '' }}"><span>▤</span>Assigned Tasks</a>
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


<script>
(function () {
    const selectedFiles = new WeakMap();

    function fileKey(file) {
        return [file.name, file.size, file.lastModified, file.type].join('::');
    }

    function formatSize(bytes) {
        const value = Number(bytes || 0);
        if (value < 1024) return value + ' B';
        if (value < 1024 * 1024) return (value / 1024).toFixed(1) + ' KB';
        return (value / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function dedupe(files) {
        const seen = new Set();
        return files.filter(file => {
            const key = fileKey(file);
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });
    }

    function assignFiles(input, files) {
        const transfer = new DataTransfer();
        files.forEach(file => transfer.items.add(file));
        input.files = transfer.files;
        selectedFiles.set(input, files);
    }

    function listContainer(input) {
        let list = input.parentElement?.querySelector(':scope > .multi-file-list');
        if (!list) {
            list = document.createElement('div');
            list.className = 'multi-file-list';
            input.insertAdjacentElement('afterend', list);
        }
        return list;
    }

    function render(input) {
        const list = listContainer(input);
        const files = selectedFiles.get(input) || Array.from(input.files || []);
        list.innerHTML = '';

        files.forEach((file, index) => {
            const row = document.createElement('div');
            row.className = 'multi-file-item';

            const info = document.createElement('div');
            info.className = 'multi-file-info';

            const icon = document.createElement('span');
            icon.className = 'multi-file-icon';
            icon.textContent = 'F';

            const copy = document.createElement('div');
            copy.className = 'multi-file-copy';

            const name = document.createElement('span');
            name.className = 'multi-file-name';
            name.title = file.name;
            name.textContent = file.name;

            const size = document.createElement('span');
            size.className = 'multi-file-size';
            size.textContent = formatSize(file.size);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'multi-file-remove';
            remove.setAttribute('aria-label', 'Remove ' + file.name);
            remove.title = 'Remove file';
            remove.innerHTML = '&times;';
            remove.addEventListener('click', function () {
                const current = selectedFiles.get(input) || [];
                const updated = current.filter((_, fileIndex) => fileIndex !== index);

                input.dataset.fileAccumulatorInternal = '1';
                assignFiles(input, updated);
                render(input);

                // Inform normal forms / Livewire that the selected FileList changed.
                input.dispatchEvent(new Event('change', { bubbles: true }));
                queueMicrotask(() => delete input.dataset.fileAccumulatorInternal);
            });

            copy.append(name, size);
            info.append(icon, copy);
            row.append(info, remove);
            list.append(row);
        });
    }

    // Capture phase is intentional: the merged FileList must be ready before
    // Livewire or form-specific change listeners read input.files.
    document.addEventListener('change', function (event) {
        const input = event.target;
        if (!(input instanceof HTMLInputElement)) return;
        if (input.type !== 'file' || !input.matches('[data-accumulate-files]')) return;

        if (input.dataset.fileAccumulatorInternal === '1') {
            render(input);
            return;
        }

        const previous = selectedFiles.get(input) || [];
        const newlyChosen = Array.from(input.files || []);
        const merged = dedupe([...previous, ...newlyChosen]);

        assignFiles(input, merged);
        render(input);
    }, true);

    // If a form reset happens, clear accumulated lists as well.
    document.addEventListener('reset', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        setTimeout(() => {
            form.querySelectorAll('input[type="file"][data-accumulate-files]').forEach(input => {
                selectedFiles.delete(input);
                const list = input.parentElement?.querySelector(':scope > .multi-file-list');
                if (list) list.innerHTML = '';
            });
        }, 0);
    });
})();
</script>


@livewireScripts
@stack('scripts')
</body>
</html>
