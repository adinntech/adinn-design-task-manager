{{-- Manage Email records table — swapped in/out by the live search box above it (see index.blade.php). No <style>/<script>, safe to AJAX-swap. --}}
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
                <tr><td colspan="6" class="empty-state">{{ request()->filled('search') ? 'No records match your search.' : 'No records yet.' }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="pagination-wrap">{{ $records->links() }}</div>
