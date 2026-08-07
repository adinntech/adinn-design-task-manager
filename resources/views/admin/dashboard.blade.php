@extends('layouts.app')

@section('title', 'Admin Workspace | Adinn Design Task Manager')

@section('content')
<div class="max-w-[1600px] mx-auto">
    <div class="mb-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="inline-flex items-center rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-[#E30613]">
                        Administration
                    </span>
                </div>

                <h1 class="text-3xl font-bold tracking-tight text-gray-950">Admin Workspace</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-500">
                    Monitor users, design tasks and overall system activity from one central workspace.
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Signed in as</p>
                <p class="mt-1 font-semibold text-gray-900">{{ auth()->user()->name }}</p>
                <p class="mt-0.5 text-sm text-gray-500">Administrator</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Total Users</p>
            <p class="mt-3 text-3xl font-bold tracking-tight text-gray-950">{{ number_format($stats['total_users']) }}</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Active BD</p>
            <p class="mt-3 text-3xl font-bold tracking-tight text-gray-950">{{ number_format($stats['bd_users']) }}</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Active Designers</p>
            <p class="mt-3 text-3xl font-bold tracking-tight text-gray-950">{{ number_format($stats['designer_users']) }}</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Total Tasks</p>
            <p class="mt-3 text-3xl font-bold tracking-tight text-gray-950">{{ number_format($stats['total_tasks']) }}</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Active Tasks</p>
            <p class="mt-3 text-3xl font-bold tracking-tight text-gray-950">{{ number_format($stats['active_tasks']) }}</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Completed</p>
            <p class="mt-3 text-3xl font-bold tracking-tight text-gray-950">{{ number_format($stats['completed_tasks']) }}</p>
        </div>
    </div>

    <div class="mt-8">
        <div class="mb-4">
            <h2 class="text-lg font-bold text-gray-950">Administration</h2>
            <p class="mt-1 text-sm text-gray-500">Central controls for the Design Task Manager.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="font-semibold text-gray-950">User Management</h3>
                <p class="mt-2 text-sm leading-6 text-gray-500">
                    Create and manage Admin, BD, Designer and Designer Head accounts.
                </p>
                <div class="mt-5 text-xs font-semibold text-gray-400">Coming next</div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="font-semibold text-gray-950">Task Monitoring</h3>
                <p class="mt-2 text-sm leading-6 text-gray-500">
                    Monitor tasks across BD employees, Designers and verticals.
                </p>
                <div class="mt-5 text-xs font-semibold text-gray-400">Coming next</div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="font-semibold text-gray-950">Master Controls</h3>
                <p class="mt-2 text-sm leading-6 text-gray-500">
                    Manage verticals, task natures, design types and future configuration.
                </p>
                <div class="mt-5 text-xs font-semibold text-gray-400">Coming next</div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="font-semibold text-gray-950">System Activity</h3>
                <p class="mt-2 text-sm leading-6 text-gray-500">
                    Review application activity, task movement and operational statistics.
                </p>
                <div class="mt-5 text-xs font-semibold text-gray-400">Coming next</div>
            </div>
        </div>
    </div>
</div>
@endsection
