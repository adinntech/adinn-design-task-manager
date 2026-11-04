@php
    $inhouseNatures = \App\Http\Controllers\Bd\TaskController::inhouseNatures();
    $inhouseNatureMap = collect($inhouseNatures)->map(fn (array $list) => collect($list)->map(fn (string $n) => [
        'value' => $n,
        'label' => ucwords(str_replace('_', ' ', $n)),
    ])->values())->all();
    $inhouseWasSubmitted = old('_form') === 'inhouse';
    $ihDraft = $inhouseDraft ?? null;
    $ihDraftReq = $ihDraft && is_array($ihDraft->requirements) ? $ihDraft->requirements : [];
    $ihVal = fn (string $key, string $fromDraft) => $inhouseWasSubmitted ? (string) old($key, '') : $fromDraft;
    $ihDraftId = old('draft_id', $ihDraft?->id);
    $ihDraftFiles = collect($ihDraftReq['attachments'] ?? [])->map(fn ($path) => [
        'path' => $path,
        'name' => basename($path),
        'url' => \Illuminate\Support\Facades\Storage::disk('spaces')->url($path),
    ])->values()->all();
@endphp

<form method="POST" action="{{ route($isDesignerActor ? 'designer.tasks.storeInhouse' : 'bd.tasks.storeInhouse') }}" enctype="multipart/form-data" id="inhouseForm" class="space-y-6" novalidate
      x-data="{
          taskName: @js($ihVal('task_name', (string) ($ihDraft?->task_name ?? ''))),
          vertical: @js($ihVal('vertical', (string) ($ihDraft?->vertical ?? ''))),
          nature: @js($ihVal('task_nature', (string) ($ihDraft?->task_nature ?? ''))),
          bdId: @js($inhouseWasSubmitted ? (string) old('bd_id', '') : ''),
          totalCreatives: @js($ihVal('total_creatives', (string) ($ihDraft?->total_creatives ?? ''))),
          natureOther: @js($ihVal('task_nature_other', (string) ($ihDraftReq['_nature_other'] ?? ''))),
          availability: null,
          availLoading: false,
          availError: false,
          availToken: 0,
          availUrl: @js(url('/bd/designers/__ID__/availability')),
          designerId: @js($ihVal('designer_id', (string) ($ihDraftReq['_draft_designer_id'] ?? ''))),
          description: @js($ihVal('description', (string) ($ihDraftReq['description'] ?? ''))),
          draftFiles: @js($ihDraftFiles),
          removedDraftFiles: [],
          natures: @js($inhouseNatureMap),
          sending: false,

          // Clone Existing In-house Ticket
          cloneQuery: '',
          cloneResults: [],
          clonedFrom: null,
          clonedName: '',
          clonedFiles: [],
          cloneTimer: null,
          cloneToken: 0,
          searchUrl: @js(route('bd.tasks.cloneSearch')),
          dataUrl: @js(route('bd.tasks.cloneData', ['task' => '__ID__'])),

          get removedFilesJson() { return JSON.stringify(this.removedDraftFiles.length ? { attachments: this.removedDraftFiles } : {}); },
          removeDraftFile(i) { this.removedDraftFiles.push(this.draftFiles[i].path); this.draftFiles.splice(i, 1); },
          get options() { return this.natures[this.vertical] || []; },
          get isOthers() { return this.nature === 'others'; },

          // Same informational availability meter the Client form shows under the designer picker.
          async loadAvailability() {
              const id = this.designerId;
              const token = ++this.availToken;
              this.availability = null;
              this.availError = false;
              if (!id) { this.availLoading = false; return; }
              this.availLoading = true;
              try {
                  const res = await fetch(this.availUrl.replace('__ID__', encodeURIComponent(id)), { headers: { 'Accept': 'application/json' } });
                  if (!res.ok) throw new Error('bad_response');
                  const data = await res.json();
                  if (token === this.availToken) this.availability = data;
              } catch (e) {
                  if (token === this.availToken) this.availError = true;
              } finally {
                  if (token === this.availToken) this.availLoading = false;
              }
          },
          checkinText(v) { return v === true ? '🟢 Checked In' : (v === false ? '🔴 Not Checked In' : '⚪ Unable to check'); },
          checkinClass(v) { return v === true ? 'checked-in' : (v === false ? 'not-checked-in' : 'unknown'); },
          get keptFilesJson() { return JSON.stringify(this.clonedFiles.length ? { attachments: this.clonedFiles.map(f => f.path) } : {}); },

          searchClones() {
              clearTimeout(this.cloneTimer);
              const q = this.cloneQuery.trim();
              if (!q) { this.cloneResults = []; return; }
              this.cloneTimer = setTimeout(async () => {
                  const token = ++this.cloneToken;
                  try {
                      const res = await fetch(this.searchUrl + '?category=in_house&q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                      const rows = res.ok ? await res.json() : [];
                      if (token === this.cloneToken) this.cloneResults = rows;
                  } catch (e) { /* ignore */ }
              }, 350);
          },
          async pickClone(row) {
              this.cloneResults = [];
              this.cloneQuery = '';
              try {
                  const res = await fetch(this.dataUrl.replace('__ID__', encodeURIComponent(row.id)), { headers: { 'Accept': 'application/json' } });
                  if (!res.ok) return;
                  const p = await res.json();
                  const t = p.task || {};
                  this.taskName = t.task_name || '';
                  this.vertical = t.vertical || '';
                  this.nature = '';
                  this.natureOther = '';
                  this.$nextTick(() => {
                      const known = this.options.some(o => o.value === t.task_nature);
                      this.nature = known ? (t.task_nature || '') : (t.task_nature ? 'others' : '');
                      this.natureOther = known ? '' : (t.task_nature || '');
                  });
                  this.totalCreatives = t.total_creatives ? String(t.total_creatives) : '';
                  this.designerId = this.$refs.designerSelect.querySelector('option[value=\'' + t.designer_id + '\']') ? String(t.designer_id) : '';
                  this.description = (p.requirements || {}).description || '';
                  this.clonedFiles = ((p.attachments || {}).attachments || []).map(f => ({ path: f.path, name: f.name || f.path.split('/').pop(), url: f.url || '' }));
                  this.clonedFrom = p.source_task_id || row.id;
                  this.clonedName = t.task_name || 'ticket';
              } catch (e) { /* ignore */ }
          },
          clearClone() {
              this.clonedFrom = null;
              this.clonedName = '';
              this.clonedFiles = [];
              this.taskName = ''; this.vertical = ''; this.nature = ''; this.natureOther = ''; this.designerId = ''; this.description = ''; this.totalCreatives = '';
          },
      }"
      x-init="$watch('designerId', () => { @unless($isDesignerActor) loadAvailability() @endunless }); @unless($isDesignerActor) if (designerId) loadAvailability() @endunless"
      @submit="sending = !($event.submitter && $event.submitter.dataset.draft)">
    @csrf
    <input type="hidden" name="_form" value="inhouse">
    <input type="hidden" name="cloned_from_task_id" :value="clonedFrom || ''">
    <input type="hidden" name="cloned_source_files_json" :value="keptFilesJson">
    <input type="hidden" name="draft_id" value="{{ $ihDraftId }}">
    <input type="hidden" name="removed_files_json" :value="removedFilesJson">

    @if($ihDraft)
        <div class="mb-6 bg-amber-50 border border-amber-200 rounded-xl p-4">
            <p class="font-semibold text-amber-900">Draft {{ $ihDraft->task_id }}</p>
            <p class="text-sm text-amber-800 mt-1">This in-house task is saved as a draft and is only visible to you. Click <strong>Create In-house Task</strong> to assign it, or <strong>Save as Draft</strong> to keep working on it later.</p>
        </div>
    @endif

    @unless($ihDraft || $isDesignerActor)
    <section class="panel panel-body">
        <h2 class="text-lg font-bold mb-1">Clone Existing In-house Ticket</h2>
        <p class="text-sm text-gray-500 mb-3">Search one of your previous in-house tickets to prefill this form. You can change anything before creating the new task.</p>
        <div style="position:relative;max-width:560px">
            <input type="text" class="field" placeholder="Search Task ID, Task Name" autocomplete="off"
                   x-model="cloneQuery" @input="searchClones()" @keydown.escape="cloneResults = []" @blur="setTimeout(() => cloneResults = [], 150)">
            <div x-show="cloneResults.length" x-cloak style="position:absolute;z-index:20;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:6px;max-height:260px;overflow-y:auto;box-shadow:0 4px 10px rgba(0,0,0,.08)">
                <template x-for="row in cloneResults" :key="row.id">
                    <div style="padding:8px 10px;cursor:pointer;border-bottom:1px solid #f1f5f9" @mousedown.prevent="pickClone(row)">
                        <div style="font-size:13px;color:#111827;font-weight:600" x-text="[row.task_id, row.task_name].filter(Boolean).join(' / ')"></div>
                    </div>
                </template>
            </div>
        </div>
        <div x-show="clonedFrom" x-cloak style="margin-top:10px;padding:8px 12px;background:#eff8ff;border:1px solid #b2ddff;border-radius:8px;font-size:12px;display:flex;justify-content:space-between;align-items:center;gap:10px">
            <span x-text="'Cloned from &quot;' + clonedName + '&quot; — review and adjust before creating.'"></span>
            <button type="button" class="btn btn-secondary" style="padding:4px 10px;font-size:11px" @click="clearClone()">Clear Clone</button>
        </div>
    </section>
    @endunless

    <section class="panel panel-body">
        <h2 class="text-lg font-bold mb-1">In-house Task Details</h2>
        <p class="text-sm text-gray-500 mb-5">For internal work that isn't tied to a client. {{ $isDesignerActor ? 'It is assigned to you and waits for the selected BD to approve it before work starts.' : 'It goes to the selected Designer and follows the normal task workflow.' }}</p>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div><label class="label">Task ID</label><input class="field bg-slate-100" value="Auto-generated after submission" disabled></div>
            <div><label class="label">Assigned At</label><input class="field bg-slate-100" value="{{ now()->format('d M Y') }}" disabled></div>
            <div><label class="label">Assigned By</label><input class="field bg-slate-100" value="{{ auth()->user()->name }}" disabled></div>

            <div class="lg:col-span-3">
                <label class="label">Task Name *</label>
                <input class="field" name="task_name" x-model="taskName" maxlength="180" required>
            </div>

            <div>
                <label class="label">Vertical *</label>
                <select class="field" name="vertical" x-model="vertical" @change="nature = ''; natureOther = ''" required>
                    <option value="">Select vertical</option>
                    @foreach(\App\Http\Controllers\Bd\TaskController::VERTICALS as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label">Task Nature *</label>
                <select class="field" name="task_nature" x-model="nature" :disabled="!vertical" required>
                    <option value="">Select task nature</option>
                    <template x-for="opt in options" :key="opt.value">
                        <option :value="opt.value" x-text="opt.label" :selected="opt.value === nature"></option>
                    </template>
                </select>
                <input class="field" style="margin-top:8px" type="text" name="task_nature_other" x-model="natureOther" x-show="isOthers" x-cloak
                       :required="isOthers" :disabled="!isOthers" maxlength="120" placeholder="Type the task nature">
            </div>

            @if($isDesignerActor)
                <div>
                    <label class="label">Assigned Designer</label>
                    <input class="field bg-slate-100" value="{{ auth()->user()->name }} (you)" disabled>
                </div>
                <div>
                    <label class="label">Assigned BD *</label>
                    <select class="field" name="bd_id" x-model="bdId" required>
                        <option value="">Select BD</option>
                        @foreach($assignees as $bd)
                            <option value="{{ $bd->id }}">{{ $bd->name }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div>
                    <label class="label">Assign Designer *</label>
                    <select class="field" name="designer_id" x-model="designerId" x-ref="designerSelect" required>
                        <option value="">Select designer</option>
                        @foreach($designers as $designer)
                            <option value="{{ $designer->id }}">{{ $designer->name }}</option>
                        @endforeach
                    </select>

                    <div class="designer-availability" x-show="designerId" x-cloak>
                        <template x-if="availLoading"><div class="designer-availability-note">Checking availability...</div></template>
                        <template x-if="availError && !availLoading"><div class="designer-availability-note">Availability unavailable</div></template>
                        <template x-if="availability && !availLoading">
                            <div>
                                <div class="designer-availability-name" x-text="'Available Occurance - ' + availability.designer_name"></div>
                                <div class="designer-availability-meta"><span x-text="'Active Tasks: ' + availability.active_tasks"></span><span x-text="'Assigned Creatives: ' + availability.active_creatives"></span></div>
                                <div class="designer-availability-meta"><span x-text="'Availability: ' + availability.availability_percent + '%'"></span></div>
                                <div class="designer-availability-track"><div class="designer-availability-fill" :class="availability.level" :style="'width:' + availability.availability_percent + '%'"></div></div>
                                <div class="designer-availability-status" :class="availability.level" x-text="availability.label"></div>
                                <div class="designer-checkin-label">Check-in Status:</div>
                                <div class="designer-checkin-status" :class="checkinClass(availability.checked_in)" x-text="checkinText(availability.checked_in)"></div>
                            </div>
                        </template>
                    </div>
                </div>
            @endif

            <div>
                <label class="label">Total Number of Creatives *</label>
                <input class="field" type="text" name="total_creatives" x-model="totalCreatives" inputmode="numeric" pattern="[0-9]*"
                       maxlength="4" placeholder="Enter total creatives" autocomplete="off" required
                       @input="totalCreatives = totalCreatives.replace(/\D/g, '')">
            </div>

            <div class="lg:col-span-3">
                <label class="label">Description *</label>
                <textarea class="field" name="description" x-model="description" rows="5" maxlength="10000" placeholder="Describe what needs to be designed..." required></textarea>
            </div>

            <div class="lg:col-span-3">
                <label class="label">Attachments</label>

                <div x-show="draftFiles.length" x-cloak style="margin-bottom:8px;display:grid;gap:6px">
                    <template x-for="(file, i) in draftFiles" :key="file.path">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:7px 10px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;font-size:12px">
                            <span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                📎 <a :href="file.url || '#'" target="_blank" rel="noopener" style="color:#175cd3;font-weight:700;text-decoration:none" x-text="file.name"></a>
                                <span style="color:#98a2b3"> · saved with draft</span>
                            </span>
                            <button type="button" style="border:1px solid #fecaca;background:#fff1f2;color:#b42318;border-radius:7px;font-size:11px;font-weight:800;padding:3px 8px;cursor:pointer" @click="removeDraftFile(i)">Remove</button>
                        </div>
                    </template>
                </div>

                <div x-show="clonedFiles.length" x-cloak style="margin-bottom:8px;display:grid;gap:6px">
                    <template x-for="(file, i) in clonedFiles" :key="file.path">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:7px 10px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;font-size:12px">
                            <span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                📎 <a :href="file.url || '#'" target="_blank" rel="noopener" style="color:#175cd3;font-weight:700;text-decoration:none" x-text="file.name"></a>
                                <span style="color:#98a2b3"> · from cloned ticket</span>
                            </span>
                            <button type="button" style="border:1px solid #fecaca;background:#fff1f2;color:#b42318;border-radius:7px;font-size:11px;font-weight:800;padding:3px 8px;cursor:pointer" @click="clonedFiles.splice(i, 1)">Remove</button>
                        </div>
                    </template>
                </div>

                <input class="field" type="file" name="attachments[]" multiple data-accumulate-files>
                <p class="multi-file-help" style="font-size:11px;color:#98a2b3;margin-top:5px">Optional · up to 20 files. Files of 500 MB or more must be a ZIP.</p>
            </div>
        </div>
    </section>

    <div class="flex justify-end gap-3">
        @unless($isDesignerActor)
            <button type="submit" class="btn btn-secondary" data-draft="1" formaction="{{ route('bd.drafts.storeInhouse') }}" :disabled="sending">Save as Draft</button>
        @endunless
        <button type="submit" class="btn btn-primary" :disabled="sending" x-text="sending ? 'Creating...' : 'Create In-house Task'">Create In-house Task</button>
    </div>
</form>
