{{--
    Shared "Designer Availability" meter for Designer / Designer Head screens.
    Any <select data-availability-url="…/__ID__/availability"> followed (inside
    the same parent) by <div data-availability-box wire:ignore></div> gets the
    same meter BD sees when choosing a designer. Uses event delegation, so it
    also works for selects Livewire renders later (the split/swap modal).
    Informational only — never blocks or changes a selection.
--}}
@once
<style>
    .designer-availability{margin-top:8px;padding:10px 12px;border:1px solid #e4e7ec;border-radius:10px;background:#fafafa}
    .designer-availability.hidden{display:none}
    .designer-availability-name{font-size:11px;font-weight:900;color:#101828;margin-bottom:4px}
    .designer-availability-meta{display:flex;justify-content:space-between;gap:8px;font-size:10px;color:#475467;margin-bottom:6px}
    .designer-availability-track{height:8px;border-radius:999px;background:#eaecf0;overflow:hidden}
    .designer-availability-fill{height:100%;border-radius:999px;width:0;transition:width .3s ease}
    .designer-availability-fill.available{background:#16c60c}
    .designer-availability-fill.moderate{background:#e9961a}
    .designer-availability-fill.busy{background:#f7630c}
    .designer-availability-fill.critical{background:#e81224}
    .designer-availability-status{margin-top:6px;font-size:10px;font-weight:900}
    .designer-availability-status.available{color:#16c60c}
    .designer-availability-status.moderate{color:#e9961a}
    .designer-availability-status.busy{color:#f7630c}
    .designer-availability-status.critical{color:#e81224}
    .designer-availability-note{font-size:10px;color:#667085;font-style:italic}
    .designer-checkin-label{margin-top:8px;font-size:10px;color:#475467}
    .designer-checkin-status{font-size:10px;font-weight:900}
    .designer-checkin-status.checked-in{color:#16c60c}
    .designer-checkin-status.not-checked-in{color:#e81224}
    .designer-checkin-status.unknown{color:#667085}
</style>
<script>
(function(){
    if (window.__designerAvailabilityMeter) return;
    window.__designerAvailabilityMeter = true;

    const esc = (v = '') => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
    const checkinClass = v => v === true ? 'checked-in' : v === false ? 'not-checked-in' : 'unknown';
    const checkinText = v => v === true ? '🟢 Checked In' : v === false ? '🔴 Not Checked In' : '⚪ Unable to check';
    const tokens = new WeakMap();
    const controllers = new WeakMap();

    function boxFor(select) {
        return select.parentElement ? select.parentElement.querySelector('[data-availability-box]') : null;
    }

    function show(select) {
        const box = boxFor(select);
        if (!box) return;

        const id = select.value;
        const token = (tokens.get(select) || 0) + 1;
        tokens.set(select, token);
        if (controllers.get(select)) controllers.get(select).abort();

        if (!id) { box.classList.add('hidden'); box.innerHTML = ''; return; }

        box.innerHTML = '<div class="designer-availability-note">Checking availability...</div>';
        box.classList.remove('hidden');

        const controller = new AbortController();
        controllers.set(select, controller);

        fetch(select.dataset.availabilityUrl.replace('__ID__', encodeURIComponent(id)), {
            headers: {'Accept': 'application/json'},
            signal: controller.signal,
            credentials: 'same-origin',
        })
            .then(res => { if (!res.ok) throw new Error('bad_response'); return res.json(); })
            .then(data => {
                if (tokens.get(select) !== token) return;
                box.innerHTML = `
                    <div class="designer-availability-name">Available Occurance - ${esc(data.designer_name)}</div>
                    <div class="designer-availability-meta"><span>Active Tasks: ${esc(data.active_tasks)}</span><span>Assigned Creatives: ${esc(data.active_creatives)}</span></div>
                    <div class="designer-availability-meta"><span>Availability: ${esc(data.availability_percent)}%</span></div>
                    <div class="designer-availability-track"><div class="designer-availability-fill ${esc(data.level)}" style="width:${esc(data.availability_percent)}%"></div></div>
                    <div class="designer-availability-status ${esc(data.level)}">${esc(data.label)}</div>
                    <div class="designer-checkin-label">Check-in Status:</div>
                    <div class="designer-checkin-status ${checkinClass(data.checked_in)}">${checkinText(data.checked_in)}</div>`;
            })
            .catch(err => {
                if (err.name === 'AbortError' || tokens.get(select) !== token) return;
                box.innerHTML = '<div class="designer-availability-note">Availability unavailable</div>';
            });
    }

    document.addEventListener('change', event => {
        const select = event.target;
        if (select && select.matches && select.matches('select[data-availability-url]')) show(select);
    });

    // Pre-selected options (e.g. the requester's preferred designer on the approval forms).
    const init = () => document.querySelectorAll('select[data-availability-url]').forEach(s => { if (s.value) show(s); });
    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('livewire:navigated', init);
})();
</script>
@endonce
