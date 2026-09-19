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
        <div class="table-wrap">
            <table class="premium-table">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Name</th>
                        <th>Email Address</th>
                        <th>Created At</th>
                        <th>Updated At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $index => $record)
                        <tr>
                            <td>{{ $records->firstItem() + $index }}</td>
                            <td><strong>{{ $record->name }}</strong></td>
                            <td>{{ $record->mail }}</td>
                            <td>{{ $record->created_at?->format('d M Y, h:i A') }}</td>
                            <td>{{ $record->updated_at?->format('d M Y, h:i A') }}</td>
                            <td>
                                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                                    <a class="btn btn-secondary" href="{{ route('admin.manage-email.edit',$record) }}">Edit</a>

                                    <form
                                        method="POST"
                                        action="{{ route('admin.manage-email.destroy',$record) }}"
                                        data-formal-confirm
                                        data-confirm-title="Delete Record?"
                                        data-confirm-message="Are you sure you want to delete {{ $record->name }} ({{ $record->mail }})?"
                                        data-confirm-label="Yes, Delete"
                                        data-processing-label="Deleting..."
                                        data-confirm-tone="danger"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn" style="background:#fff1f2;color:#b42318;border:1px solid #fecdd3">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">No records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">{{ $records->links() }}</div>
    </div>
</div>

<x-formal-confirm-dialog />
@endsection
