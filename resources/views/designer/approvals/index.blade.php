@extends('layouts.app')

@section('title', 'Approvals')
@section('workspace-title', 'Approvals')
@section('workspace-subtitle', 'Review Swap and Split requests raised by other Designers on your team')

@section('content')
<style>
    .apv-note{padding:10px 12px;border-radius:10px;background:#f4f0ff;border:1px solid #d9d6fe;color:#53389e;font-size:11px;font-weight:650;margin-bottom:14px}
    .apv-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:14px}
    .apv-card{display:block;border:1px solid #fecaca;border-radius:14px;background:#fff8f7;padding:14px;color:inherit;text-decoration:none;box-shadow:0 4px 14px rgba(16,24,40,.04)}
    .apv-card:hover{border-color:#e30613}
    .apv-head{display:flex;justify-content:space-between;gap:8px;align-items:flex-start}
    .apv-id{font-size:11px;font-weight:900;color:#475467}
    .apv-pill{border-radius:999px;padding:3px 9px;font-size:10px;font-weight:800;border:1px solid transparent}
    .apv-pill.split{background:#f4f0ff;color:#6938ef;border-color:#d9d6fe}
    .apv-pill.swap{background:#eff8ff;color:#175cd3;border-color:#b2ddff}
    .apv-name{font-size:14px;font-weight:850;margin-top:8px;color:#101828}
    .apv-client{font-size:11px;color:#667085;margin-top:2px}
    .apv-meta{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}
    .apv-meta div{background:#fff;border:1px solid #f2f4f7;border-radius:9px;padding:7px 9px;font-size:11px;color:#344054;font-weight:650}
    .apv-meta strong{display:block;font-size:9px;text-transform:uppercase;color:#98a2b3;margin-bottom:2px}
    .apv-open{margin-top:10px;font-size:11px;font-weight:850;color:#e30613}
    .apv-empty{padding:34px;text-align:center;border:1px dashed #e4e7ec;border-radius:14px;color:#667085;font-size:13px;background:#fff}
</style>

<div class="apv-note">You can approve or decline these requests on behalf of your Designer Head. Your own requests are decided by your Designer Head only. Once anyone decides a request it leaves this list.</div>

<div class="apv-grid">
    @forelse($pendingRequests as $req)
        <a class="apv-card" href="{{ route('designer.approvals.show', $req) }}">
            <div class="apv-head">
                <div class="apv-id">{{ $req->task->task_id }}</div>
                <span class="apv-pill {{ $req->request_type }}">{{ $req->request_type === 'swap' ? 'Task Transfer' : 'Split' }}</span>
            </div>
            <div class="apv-name">{{ $req->task->display_task_name ?? $req->task->task_name }}</div>
            <div class="apv-client">{{ $req->task->party_name }}</div>
            <div class="apv-meta">
                <div><strong>Requested By</strong>{{ $req->requester?->name ?? '—' }}</div>
                <div><strong>Requested At</strong>{{ $req->created_at?->format('d M, h:i A') }}</div>
                @if($req->request_type === 'split')
                    <div><strong>Requested Split</strong>{{ data_get($req->split_details, 'creative_count', '—') }}</div>
                @endif
                <div><strong>Preferred Designer</strong>{{ $req->targetDesigner?->name ?? '—' }}</div>
            </div>
            <div class="apv-open">Open to review</div>
        </a>
    @empty
        <div class="apv-empty" style="grid-column:1/-1">No pending Swap or Split requests.</div>
    @endforelse
</div>
@endsection
