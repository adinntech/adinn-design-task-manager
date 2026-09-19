@extends('layouts.app')
@section('title','Manage Email')
@section('workspace-title','Manage Email')
@section('workspace-subtitle','Maintain the standalone Name/Email mailing list')
@section('content')

<div class="page-head">
    <div>
        <h1>Manage Email</h1>
        <p>This list is separate from application login users.</p>
    </div>
</div>

@if($excelImportEnabled)
<div class="panel" style="margin-bottom:16px">
    <div class="panel-header"><div class="panel-title">Excel Import (Development Only)</div></div>
    <div class="panel-body">
        <p class="muted" style="margin:0 0 10px">Upload an .xlsx with columns: S.No (ignored), Name, Email Address, Status (ignored).</p>
        <form method="POST" action="{{ route('admin.manage-email.import') }}" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap" onsubmit="const b=this.querySelector('button[type=submit]');b.disabled=true;b.innerHTML='<span class=btn-spinner></span>Importing...';">
            @csrf
            <input class="premium-input" type="file" name="excel_file" accept=".xlsx" required style="max-width:340px">
            <button type="submit" class="btn btn-primary">Upload &amp; Import</button>
        </form>
        @error('excel_file')<div class="error" style="margin-top:8px">{{ $message }}</div>@enderror
    </div>
</div>
@endif

<div class="panel" style="margin-bottom:16px">
    <div class="panel-header"><div class="panel-title">Add New User</div></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('admin.manage-email.store') }}" style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap" onsubmit="const b=this.querySelector('button[type=submit]');b.disabled=true;b.innerHTML='<span class=btn-spinner></span>Saving...';">
            @csrf
            <div>
                <input class="premium-input" name="name" value="{{ old('name') }}" placeholder="Name" required>
                @error('name')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div>
                <input class="premium-input" type="email" name="mail" value="{{ old('mail') }}" placeholder="Email Address" required>
                @error('mail')<div class="error">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="filter-bar compact" style="margin-bottom:14px">
            <input class="premium-input" id="manage-email-search" value="{{ request('search') }}" placeholder="Search name or email...">
        </div>

        <div id="manage-email-table-root">
            @include('admin.manage-email.table-partial', ['records' => $records])
        </div>
    </div>
</div>

<x-formal-confirm-dialog />

<script>
(function () {
    var input = document.getElementById('manage-email-search');
    var root = document.getElementById('manage-email-table-root');
    var base = "{{ route('admin.manage-email.table') }}";
    var timer = null;

    function reload() {
        fetch(base + '?search=' + encodeURIComponent(input.value) + '&page=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.text(); })
            .then(function (html) {
                var tmp = document.createElement('div');
                tmp.innerHTML = html;
                root.replaceChildren.apply(root, Array.prototype.slice.call(tmp.childNodes));
            });
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(reload, 400);
    });
})();
</script>
@endsection
