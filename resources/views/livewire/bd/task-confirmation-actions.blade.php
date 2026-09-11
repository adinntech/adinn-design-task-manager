<section class="panel" style="margin-bottom:20px;border:1px solid #fecaca;background:#fff7f7">
    <div class="panel-header">
        <div class="panel-title">New Task Confirmation Request</div>
    </div>
    <div class="panel-body">
        <p style="font-size:11px;color:#667085;margin:0 0 12px">
            {{ $task->designer?->name ?? 'The designer' }} created this task — it stays in Waiting for Confirmation until you decide.
        </p>

        @if(! $rejecting)
            <div>
                <label class="label">Comment (optional)</label>
                <textarea class="premium-textarea" rows="2" wire:model="comment" placeholder="Optional note for the Designer..."></textarea>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                <button type="button" wire:click="approve" class="btn btn-primary" wire:loading.attr="disabled" wire:target="approve">
                    <span wire:loading.remove wire:target="approve">Approve</span>
                    <span wire:loading wire:target="approve"><span class="btn-spinner"></span>Approving...</span>
                </button>
                <button type="button" wire:click="startReject" class="btn btn-secondary" style="color:#b42318;border-color:#fecaca" wire:loading.attr="disabled" wire:target="approve">Reject</button>
            </div>
        @else
            <div>
                <label class="label">Reason for rejection *</label>
                <textarea class="premium-textarea" rows="3" wire:model="comment" placeholder="Explain why this task request is rejected..."></textarea>
                @error('comment') <div class="field-error" style="color:#b42318;font-size:10px;margin-top:5px">{{ $message }}</div> @enderror
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                <button type="button" wire:click="reject" class="btn btn-primary" style="background:#b42318;border-color:#b42318" wire:loading.attr="disabled" wire:target="reject">
                    <span wire:loading.remove wire:target="reject">Confirm Reject</span>
                    <span wire:loading wire:target="reject"><span class="btn-spinner"></span>Rejecting...</span>
                </button>
                <button type="button" wire:click="cancelReject" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="reject">Cancel</button>
            </div>
        @endif
    </div>
</section>
