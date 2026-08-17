@php
    $titles = ['decline' => 'Decline Request', 'split' => 'Split Request', 'swap' => 'Swap Request'];
@endphp

<div>
    <style>
        .request-modal-overlay{position:fixed;inset:0;background:rgba(15,17,22,.52);display:flex;align-items:center;justify-content:center;z-index:9998;padding:20px;backdrop-filter:blur(2px)}
        .request-modal-box{background:#fff;border-radius:18px;width:100%;max-width:560px;max-height:90vh;overflow:auto;box-shadow:0 28px 70px rgba(0,0,0,.28)}
        .request-modal-head{display:flex;align-items:center;justify-content:space-between;padding:18px 20px;border-bottom:1px solid var(--line)}
        .request-modal-head h2{margin:0;font-size:16px;font-weight:900}
        .request-modal-close{border:0;background:transparent;font-size:20px;cursor:pointer;color:#7c8492;line-height:1}
        .request-modal-body{padding:20px;display:grid;gap:14px}
        .request-modal-foot{display:flex;justify-content:flex-end;gap:9px;padding:16px 20px;border-top:1px solid var(--line)}
        .field-error{color:#b4232f;font-size:10px;margin-top:5px}
        .request-hint{border:1px solid #f1d5d8;background:#fff7f8;border-radius:10px;padding:10px 12px;font-size:10px;color:#6f2930;line-height:1.5}
    </style>

    @if($open)
        <div class="request-modal-overlay" wire:key="request-modal-{{ $type }}">
            <div class="request-modal-box">
                <div class="request-modal-head">
                    <div>
                        <h2>{{ $titles[$type] ?? 'Request' }}</h2>
                        <div class="muted" style="margin-top:3px">{{ $task->task_id }} · {{ $task->display_task_name }}</div>
                    </div>
                    <button type="button" class="request-modal-close" wire:click="close">&times;</button>
                </div>

                <div class="request-modal-body">
<div>
                        <label class="label">Reason *</label>
                        <textarea class="premium-textarea" rows="3" wire:model="reason" placeholder="Explain why this request is needed..."></textarea>
                        @error('reason') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    @if($type === 'split')
                        <div>
                            <label class="label">Creative Count to Split *</label>
                            <input type="number" min="1" max="{{ max(1, $task->total_creatives - 1) }}" class="field" wire:model="creativeCount" placeholder="Number of creatives to move to the split task">
                            <div class="muted" style="margin-top:4px">Current task has {{ $task->total_creatives }} creatives. At least 1 creative must remain on the original task.</div>
                            @error('creativeCount') <div class="field-error">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="label">Split Details *</label>
                            <textarea class="premium-textarea" rows="3" wire:model="splitDetailsText" placeholder="Describe which creatives/work should move to the split task..."></textarea>
                            @error('splitDetailsText') <div class="field-error">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="label">Preferred Designer (optional)</label>
                            <select class="premium-select" wire:model="targetDesignerId">
                                <option value="">Keep with current designer</option>
                                @foreach($designers as $designer)
                                    <option value="{{ $designer->id }}">{{ $designer->name }}</option>
                                @endforeach
                            </select>
                            @error('targetDesignerId') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    @if($type === 'swap')
                        <div>
                            <label class="label">Swap With Designer *</label>
                            <select class="premium-select" wire:model="targetDesignerId">
                                <option value="">Choose an active designer</option>
                                @foreach($designers as $designer)
                                    <option value="{{ $designer->id }}">{{ $designer->name }}</option>
                                @endforeach
                            </select>
                            @error('targetDesignerId') <div class="field-error">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="label">Additional Notes</label>
                            <textarea class="premium-textarea" rows="3" wire:model="notes" placeholder="Any extra context for the swap..."></textarea>
                            @error('notes') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="label">Attachment (optional)</label>
                        <input type="file" wire:model="attachment">
                        <div class="muted" style="margin-top:4px">Maximum 100 MB.</div>
                        <div class="muted" wire:loading wire:target="attachment" style="margin-top:4px">Preparing attachment...</div>
                        @error('attachment') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    @error('type') <div class="field-error">{{ $message }}</div> @enderror
                </div>

                <div class="request-modal-foot">
                    <button type="button" class="btn btn-secondary" wire:click="close">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="submit" wire:loading.attr="disabled">Submit Request</button>
                </div>
            </div>
        </div>
    @endif
</div>
