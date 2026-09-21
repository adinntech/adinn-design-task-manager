<div
    x-data="{ pendingUploads: 0, previewSrc: null, previewName: '', previewType: null }"
    @mail-attachment-pending.window="pendingUploads = $event.detail.count"
    @pf-preview-open.window="previewSrc = $event.detail.url; previewName = $event.detail.name; previewType = $event.detail.type"
    @keydown.escape.window="$wire.closeHistoryModal(); previewSrc = null"
>
    <style>
        .pf-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        @media (max-width: 860px){ .pf-grid{grid-template-columns:1fr} }
        .pf-panel{background:#fff;border:1px solid #e4e7ec;border-radius:10px;padding:16px}
        .pf-panel + .pf-panel{margin-top:16px}
        .pf-section-title{font-size:11px;font-weight:800;color:#344054;letter-spacing:.02em;text-transform:uppercase;margin-bottom:8px}
        .pf-file-input{font-size:10px;color:#344054}
        .pf-helper{font-size:9px;color:#667085;margin-top:6px}
        .pf-attachment-row{border:1px solid #e4e7ec;border-radius:8px;padding:8px 10px;margin-top:8px}
        .pf-attachment-row-top{display:flex;justify-content:space-between;gap:8px;font-size:10px;font-weight:700;color:#344054}
        .pf-attachment-row-bar{height:5px;border-radius:3px;background:#eef1f5;margin-top:6px;overflow:hidden}
        .pf-attachment-row-fill{height:100%;width:0;background:#2970ff;transition:width .2s ease}
        .pf-attachment-row-detail{font-size:9px;color:#667085;margin-top:4px}
        .upload-status-note{font-size:9px;font-weight:700;margin-top:4px}
        .upload-status-note--resume{color:#b54708}
        .upload-status-note--uploading,.upload-status-note--preparing,.upload-status-note--finalizing{color:#175cd3}
        .upload-status-note--completed{color:#067647}
        .upload-status-note--failed,.upload-status-note--error{color:#b4232f}
        .pf-chip-field{border:1px solid #d0d5dd;border-radius:8px;padding:6px 8px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;position:relative}
        .pf-chip{background:#eaf2ff;color:#1d4ed8;border-radius:14px;padding:3px 8px;font-size:10px;font-weight:700;display:flex;align-items:center;gap:5px}
        .pf-chip button{border:none;background:none;color:#1d4ed8;cursor:pointer;font-weight:900;line-height:1}
        .pf-chip-input{border:none;outline:none;flex:1;min-width:120px;font-size:11px;padding:3px}
        .pf-autocomplete{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e4e7ec;border-radius:8px;box-shadow:0 8px 20px rgba(16,24,40,.08);z-index:20;max-height:180px;overflow-y:auto;margin-top:4px}
        .pf-autocomplete-item{padding:7px 10px;font-size:10px;cursor:pointer}
        .pf-autocomplete-item:hover{background:#f5f7fa}
        .pf-autocomplete-item small{display:block;color:#667085;font-size:9px}
        .pf-preview-attachment{display:flex;justify-content:space-between;font-size:10px;padding:5px 0;border-bottom:1px solid #f2f4f7}
        .pf-history-row{border-top:1px solid #eef1f5;padding:10px 0;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
        .pf-modal-backdrop{position:fixed;inset:0;background:rgba(16,24,40,.5);display:flex;align-items:center;justify-content:center;z-index:60;padding:16px}
        .pf-modal{background:#fff;border-radius:12px;padding:20px;max-width:520px;width:100%;max-height:80vh;overflow-y:auto}
        .pf-success{background:#eafbf0;border:1px solid #b7ebc6;color:#067647;border-radius:8px;padding:10px 12px;font-size:11px;font-weight:700;margin-bottom:12px}
        .pf-error{background:#fef3f2;border:1px solid #fecdca;color:#b42318;border-radius:8px;padding:10px 12px;font-size:11px;font-weight:700;margin-bottom:12px}
        .pf-attachment-grid{display:flex;flex-wrap:wrap;gap:10px;margin-top:8px}
        .pf-att-card{display:flex;align-items:center;gap:8px;border:1px solid #e4e7ec;border-radius:8px;padding:6px 8px;width:220px}
        .pf-att-thumb{width:44px;height:44px;border-radius:6px;object-fit:cover;cursor:pointer;flex:0 0 auto}
        .pf-att-icon{width:44px;height:44px;border-radius:6px;background:#f5f7fa;display:flex;align-items:center;justify-content:center;font-size:18px;flex:0 0 auto}
        .pf-att-meta{flex:1;min-width:0}
        .pf-att-name{font-size:10px;font-weight:700;color:#344054;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .pf-att-size{font-size:9px;color:#667085;margin-top:2px}
        .pf-att-download{flex:0 0 auto;color:#2970ff;text-decoration:none;font-size:16px;padding:4px}
        .pf-lightbox{position:fixed;inset:0;background:rgba(16,24,40,.75);display:flex;align-items:center;justify-content:center;z-index:70;padding:20px}
        .pf-lightbox-inner{max-width:92vw;max-height:92vh;display:flex;flex-direction:column;gap:10px}
        .pf-lightbox-header{display:flex;justify-content:space-between;align-items:center;gap:10px;color:#fff;font-size:11px;font-weight:700}
        .pf-lightbox-header a, .pf-lightbox-header button{margin-left:8px}
        .pf-lightbox-img{max-width:92vw;max-height:80vh;object-fit:contain;border-radius:6px}
        .pf-lightbox-pdf{width:80vw;height:80vh;border:none;border-radius:6px;background:#fff}
        .mail-attachment-row-actions{display:flex;gap:6px;margin-top:6px}
        .mail-attachment-row-actions .pf-chip{cursor:pointer;border:none}
    </style>

    {{-- Compose/send UI only for an active task; once Completed the tab is a
         read-only history view — except while an explicit Resend is in
         progress (resendFrom() populates toRecipients), so resend keeps
         working exactly as it already does. --}}
    @if($task->status !== 'completed' || count($toRecipients) > 0)
    <div class="pf-grid">
        {{-- LEFT: Printing inputs --}}
        <div>
            <div class="pf-panel">
                <div class="pf-section-title">WeTransfer Link</div>
                <input
                    type="text"
                    class="premium-input"
                    placeholder="https://we.tl/..."
                    wire:model.live.debounce.300ms="weTransferLink"
                >
                @error('weTransferLink')<div class="error">{{ $message }}</div>@enderror
            </div>

            <div class="pf-panel">
                <div class="pf-section-title">Mail Attachments</div>
                <div wire:ignore>
                    <input type="file" id="pfFileInput" class="pf-file-input" multiple>
                    <div class="pf-helper">Files smaller than 1 GB can be uploaded in any supported format. Files that are 1 GB or larger must be in ZIP format.</div>

                    <div id="pfAttachmentRows"></div>
                    <template id="pfAttachmentRowTemplate">
                        <div class="pf-attachment-row">
                            <div class="pf-attachment-row-top">
                                <span class="mail-attachment-row-name"></span>
                                <span class="mail-attachment-row-percent"></span>
                            </div>
                            <div class="pf-attachment-row-bar"><div class="pf-attachment-row-fill mail-attachment-row-fill"></div></div>
                            <div class="pf-attachment-row-detail mail-attachment-row-detail"></div>
                            <div class="mail-attachment-row-status"></div>
                            <div class="mail-attachment-row-actions"></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- RIGHT: Mail Compose --}}
        <div>
            <div class="pf-panel">
                @if($sent)
                    <div class="pf-success">
                        Mail sent successfully<br>
                        Sent: {{ $sentAtLabel }}
                    </div>
                @endif
                @if($mailError)
                    <div class="pf-error">{{ $mailError }}</div>
                @endif

                <div class="pf-section-title">To</div>
                <div class="pf-chip-field">
                    @foreach($toRecipients as $i => $recipient)
                        <span class="pf-chip">{{ $recipient['name'] }} &lt;{{ $recipient['mail'] }}&gt;
                            <button type="button" wire:click="removeRecipient('to', {{ $i }})">&times;</button>
                        </span>
                    @endforeach
                    <input type="text" class="pf-chip-input" placeholder="Search name or email…" wire:model.live.debounce.250ms="toQuery">
                    @if(count($toResults) > 0)
                        <div class="pf-autocomplete">
                            @foreach($toResults as $result)
                                <div class="pf-autocomplete-item" wire:click="addRecipient('to', {{ $result['id'] }})">
                                    {{ $result['name'] }}<small>{{ $result['mail'] }}</small>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                @error('toRecipients')<div class="error">{{ $message }}</div>@enderror

                <div class="pf-section-title" style="margin-top:14px">CC</div>
                <div class="pf-chip-field">
                    @foreach($ccRecipients as $i => $recipient)
                        <span class="pf-chip">{{ $recipient['name'] }} &lt;{{ $recipient['mail'] }}&gt;
                            <button type="button" wire:click="removeRecipient('cc', {{ $i }})">&times;</button>
                        </span>
                    @endforeach
                    <input type="text" class="pf-chip-input" placeholder="Search name or email…" wire:model.live.debounce.250ms="ccQuery">
                    @if(count($ccResults) > 0)
                        <div class="pf-autocomplete">
                            @foreach($ccResults as $result)
                                <div class="pf-autocomplete-item" wire:click="addRecipient('cc', {{ $result['id'] }})">
                                    {{ $result['name'] }}<small>{{ $result['mail'] }}</small>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="pf-section-title" style="margin-top:14px">Subject</div>
                <input type="text" class="premium-input" wire:model.blur="subject">
                @error('subject')<div class="error">{{ $message }}</div>@enderror

                <div class="pf-section-title" style="margin-top:14px">Mail Body</div>
                <textarea class="premium-input" rows="7" wire:model.blur="body"></textarea>
                @error('body')<div class="error">{{ $message }}</div>@enderror

                {{-- Display-only preview — the API generates this signature itself from
                     the username/email/rolename/phone payload fields, so it is never
                     part of $body/mail_content. --}}
                <div class="pf-section-title" style="margin-top:14px">Signature Preview <span style="text-transform:none;font-weight:600;color:#98a2b3;letter-spacing:0">(added automatically, not part of Mail Body)</span></div>
                <div style="border:1px solid #e4e7ec;border-radius:8px;padding:10px 12px;font-size:11px;line-height:1.6;color:#475467;white-space:pre-wrap;background:#f9fafb">{{ $this->previewSignature() }}</div>

                @if(count($attachments) > 0)
                    <div class="pf-section-title" style="margin-top:14px">Attachments</div>
                    @foreach($attachments as $attachment)
                        @php
                            $pfComposeExt = strtolower(pathinfo($attachment['name'] ?? '', PATHINFO_EXTENSION));
                            $pfComposeIsImage = in_array($pfComposeExt, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
                            $pfComposeIsPdf = $pfComposeExt === 'pdf';
                        @endphp
                        <div class="pf-preview-attachment">
                            <span>{{ $attachment['name'] }} <span style="color:#667085">({{ number_format($attachment['size_bytes'] / 1048576, 1) }} MB)</span></span>
                            <span style="display:flex;gap:8px;align-items:center">
                                @if(($pfComposeIsImage || $pfComposeIsPdf) && ! empty($attachment['url']))
                                    @php
                                        // Resend-prefilled attachment: url is already the promoted,
                                        // correctly-named permanent path — resend flow untouched.
                                        $pfComposePreviewUrl = ! empty($attachment['reused'])
                                            ? $attachment['url']
                                            : route('designer.uploads.preview', $attachment['id']);
                                    @endphp
                                    <button type="button" class="pf-chip" @click="previewSrc = @js($pfComposePreviewUrl); previewName = @js($attachment['name']); previewType = @js($pfComposeIsPdf ? 'pdf' : 'image')">View</button>
                                @endif
                                @if(! empty($attachment['reused']) && ! empty($attachment['url']))
                                    {{-- Resend-prefilled attachment: url is already the promoted,
                                         correctly-named permanent path — resend flow untouched. --}}
                                    <a class="pf-chip" href="{{ $attachment['url'] }}" download="{{ $attachment['name'] }}" target="_blank" rel="noopener">Download</a>
                                @elseif(! empty($attachment['id']))
                                    <a class="pf-chip" href="{{ route('designer.uploads.download', $attachment['id']) }}" download="{{ $attachment['name'] }}" target="_blank" rel="noopener">Download</a>
                                @endif
                                <button type="button" class="pf-chip" style="background:none;color:#b4232f" wire:click="removeAttachment({{ $attachment['id'] }})">Remove</button>
                            </span>
                        </div>
                    @endforeach
                @endif

                <button
                    class="btn btn-primary"
                    style="margin-top:16px;width:100%"
                    wire:click="sendMail"
                    wire:loading.attr="disabled"
                    wire:target="sendMail"
                    :disabled="pendingUploads > 0"
                >
                    <span wire:loading.remove wire:target="sendMail">Send Mail</span>
                    <span wire:loading wire:target="sendMail">Sending...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Mail History --}}
    <div class="pf-panel" style="margin-top:16px">
        <div class="pf-section-title">Mail History</div>
        @if($history->isEmpty())
            <div class="empty-state">No printing file mail sent yet.</div>
        @else
            @foreach($history as $item)
                <div class="pf-history-row">
                    <div>
                        <div style="font-size:11px;font-weight:700">{{ $item->subject }}</div>
                        <div style="font-size:9px;color:#667085">{{ $item->sent_at?->format('d M Y') }} • {{ $item->sent_at?->format('h:i A') }}</div>
                    </div>
                    <div style="display:flex;gap:10px">
                        <button type="button" class="btn" wire:click="viewHistory({{ $item->id }})">View Mail Details</button>
                        <button type="button" class="btn" wire:click="resendFrom({{ $item->id }})">Resend</button>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    {{-- View Mail Details modal — always rendered from the DB record for
         $viewingHistoryId (never from a client-side snapshot), so it shows
         the exact saved content even for a mail just sent in this request. --}}
    @if($viewingRecord)
        @php
            $pfFileIcons = ['pdf' => '📄', 'doc' => '📝', 'docx' => '📝', 'xls' => '📊', 'xlsx' => '📊', 'zip' => '🗄️'];
            $pfImageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
        @endphp
        <div class="pf-modal-backdrop" wire:click.self="closeHistoryModal">
            <div class="pf-modal" x-data="{ previewSrc: null, previewName: '' }">
                <div class="panel-title" style="margin-bottom:10px">Mail Details</div>
                @if(! empty($viewingRecord->to_recipients))
                    <div style="font-size:10px"><strong>To:</strong> {{ collect($viewingRecord->to_recipients)->map(fn ($r) => $r['name'].' <'.$r['mail'].'>')->implode(', ') }}</div>
                @endif
                @if(! empty($viewingRecord->cc_recipients))
                    <div style="font-size:10px"><strong>CC:</strong> {{ collect($viewingRecord->cc_recipients)->map(fn ($r) => $r['name'].' <'.$r['mail'].'>')->implode(', ') }}</div>
                @endif
                <div style="font-size:10px;margin-top:8px"><strong>Subject:</strong> {{ $viewingRecord->subject }}</div>
                <div style="font-size:10px;margin-top:8px;white-space:pre-wrap">{{ $viewingRecord->body }}</div>
                @if($viewingRecord->transfer_url)
                    <div style="font-size:10px;margin-top:8px"><strong>WeTransfer:</strong> <a href="{{ $viewingRecord->transfer_url }}" target="_blank" rel="noopener">{{ $viewingRecord->transfer_url }}</a></div>
                @endif

                <div style="font-size:10px;margin-top:8px"><strong>Attachments:</strong></div>
                @if(empty($viewingRecord->attachments))
                    <div class="empty-state" style="font-size:9px">No attachments</div>
                @else
                    <div class="pf-attachment-grid">
                        @foreach($viewingRecord->attachments as $attachment)
                            @php
                                $pfExt = strtolower(pathinfo($attachment['name'] ?? '', PATHINFO_EXTENSION));
                                $pfIsImage = in_array($pfExt, $pfImageExts, true);
                                $pfSize = ! empty($attachment['size_bytes']) ? number_format($attachment['size_bytes'] / 1048576, 1).' MB' : '';
                            @endphp
                            <div class="pf-att-card">
                                @if($pfIsImage)
                                    <img
                                        src="{{ $attachment['url'] }}"
                                        alt="{{ $attachment['name'] }}"
                                        class="pf-att-thumb"
                                        @click="previewSrc = @js($attachment['url']); previewName = @js($attachment['name'])"
                                    >
                                @else
                                    <div class="pf-att-icon">{{ $pfFileIcons[$pfExt] ?? '📎' }}</div>
                                @endif
                                <div class="pf-att-meta">
                                    <div class="pf-att-name" title="{{ $attachment['name'] }}">{{ $attachment['name'] }}</div>
                                    @if($pfSize)<div class="pf-att-size">{{ $pfSize }}</div>@endif
                                </div>
                                <a class="pf-att-download" href="{{ $attachment['url'] }}" download="{{ $attachment['name'] }}" target="_blank" rel="noopener" title="Download">⬇</a>
                            </div>
                        @endforeach
                    </div>

                    <div class="pf-lightbox" x-show="previewSrc" style="display:none" @click.self="previewSrc = null">
                        <div class="pf-lightbox-inner">
                            <div class="pf-lightbox-header">
                                <span x-text="previewName"></span>
                                <span>
                                    <a class="btn" :href="previewSrc" :download="previewName" target="_blank" rel="noopener">Download</a>
                                    <button type="button" class="btn" @click="previewSrc = null">Close</button>
                                </span>
                            </div>
                            <img class="pf-lightbox-img" :src="previewSrc" :alt="previewName">
                        </div>
                    </div>
                @endif

                <div style="font-size:10px;margin-top:8px;color:#667085">
                    Sent by {{ $viewingRecord->sender?->email }} · {{ $viewingRecord->sent_at?->format('d M Y') }} • {{ $viewingRecord->sent_at?->format('h:i A') }}
                </div>
                <button type="button" class="btn" style="margin-top:14px" wire:click="closeHistoryModal">Close</button>
            </div>
        </div>
    @endif

    {{-- Shared View preview (image lightbox / PDF embed) for the left-side
         uploaded file rows and the right-side compose Attachments list.
         Opened either directly by Alpine (right side) or via the
         'pf-preview-open' window event dispatched from plain JS (left
         side, since those rows are created outside Livewire/Alpine). --}}
    <div class="pf-lightbox" x-show="previewSrc" style="display:none" @click.self="previewSrc = null">
        <div class="pf-lightbox-inner">
            <div class="pf-lightbox-header">
                <span x-text="previewName"></span>
                <span>
                    <a class="btn" :href="previewSrc" :download="previewName" target="_blank" rel="noopener">Download</a>
                    <button type="button" class="btn" @click="previewSrc = null">Close</button>
                </span>
            </div>
            <template x-if="previewType === 'pdf'">
                <iframe class="pf-lightbox-pdf" :src="previewSrc"></iframe>
            </template>
            <template x-if="previewType !== 'pdf'">
                <img class="pf-lightbox-img" :src="previewSrc" :alt="previewName">
            </template>
        </div>
    </div>

    <script>
    (function () {
        if (typeof AdinnMailAttachmentUpload === 'undefined') return;
        var input = document.getElementById('pfFileInput');
        if (!input || input.dataset.pfBound) return;
        input.dataset.pfBound = '1';

        AdinnMailAttachmentUpload.init({
            input: input,
            rowsContainer: document.getElementById('pfAttachmentRows'),
            rowTemplate: document.getElementById('pfAttachmentRowTemplate'),
            taskId: {{ (int) $task->id }},
            urls: {
                initiate: '{{ route('designer.uploads.initiate') }}',
                active: '{{ route('designer.uploads.active') }}',
                partUrl: function (id) { return '{{ url('/designer/uploads') }}/' + id + '/part-url'; },
                parts: function (id) { return '{{ url('/designer/uploads') }}/' + id + '/parts'; },
                complete: function (id) { return '{{ url('/designer/uploads') }}/' + id + '/complete'; },
                download: function (id) { return '{{ url('/designer/uploads') }}/' + id + '/download'; },
                preview: function (id) { return '{{ url('/designer/uploads') }}/' + id + '/preview'; },
            },
            onCountChange: function (count) {
                window.dispatchEvent(new CustomEvent('mail-attachment-pending', { detail: { count: count } }));
            },
        });
    })();
    </script>
</div>
