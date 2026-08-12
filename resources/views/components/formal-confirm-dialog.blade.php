<div
    x-data="formalConfirmDialog()"
    x-on:formal-confirm.window="openDialog($event.detail)"
    x-show="open"
    x-cloak
    style="position:fixed;inset:0;z-index:100000"
>
    <div
        x-show="open"
        x-transition.opacity
        @click="cancel()"
        style="position:absolute;inset:0;background:rgba(17,24,39,.58);backdrop-filter:blur(3px)"
    ></div>

    <div
        x-show="open"
        x-transition
        @keydown.escape.window="cancel()"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="'formal-confirm-title'"
        style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:min(92vw,480px);background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 28px 80px rgba(15,23,42,.28);overflow:hidden"
    >
        <div style="padding:22px 22px 16px">
            <div style="display:flex;gap:13px;align-items:flex-start">
                <div
                    style="width:42px;height:42px;border-radius:12px;display:grid;place-items:center;flex:0 0 auto"
                    :style="tone === 'danger'
                        ? 'background:#fff1f2;color:#b42318'
                        : tone === 'success'
                            ? 'background:#ecfdf3;color:#067647'
                            : 'background:#fff7ed;color:#b54708'"
                >
                    <span x-show="tone === 'danger'" style="font-size:20px;font-weight:950">!</span>
                    <span x-show="tone === 'success'" style="font-size:18px;font-weight:950">✓</span>
                    <span x-show="tone !== 'danger' && tone !== 'success'" style="font-size:18px;font-weight:950">?</span>
                </div>

                <div style="min-width:0">
                    <h3
                        id="formal-confirm-title"
                        x-text="title"
                        style="margin:0;color:#101828;font-size:17px;line-height:1.35;font-weight:950"
                    ></h3>

                    <p
                        x-text="message"
                        style="margin:8px 0 0;color:#667085;font-size:12px;line-height:1.65"
                    ></p>
                </div>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:8px;padding:14px 22px;background:#f8fafc;border-top:1px solid #eaecf0">
            <button
                type="button"
                @click="cancel()"
                style="border:1px solid #d0d5dd;background:#fff;color:#344054;border-radius:10px;padding:10px 15px;font-size:11px;font-weight:850;cursor:pointer"
            >
                Cancel
            </button>

            <button
                type="button"
                @click="confirm()"
                :disabled="submitting"
                x-text="submitting ? processingLabel : confirmLabel"
                :style="tone === 'danger'
                    ? 'border:1px solid #d92d20;background:#d92d20;color:#fff;border-radius:12px'
                    : tone === 'success'
                        ? 'border:1px solid #067647;background:#067647;color:#fff'
                        : 'border:1px solid #101828;background:#101828;color:#fff'"
                style="border-radius:10px;padding:10px 15px;font-size:11px;font-weight:900;cursor:pointer;min-width:115px"
            ></button>
        </div>
    </div>
</div>

@once
<script>
    function formalConfirmDialog() {
        return {
            open: false,
            submitting: false,
            form: null,
            title: 'Confirm Action',
            message: 'Please confirm that you want to continue with this action.',
            note: '',
            confirmLabel: 'Confirm',
            processingLabel: 'Processing...',
            tone: 'warning',

            openDialog(detail = {}) {
                this.form = detail.form || null;
                this.title = detail.title || 'Confirm Action';
                this.message = detail.message || 'Please confirm that you want to continue with this action.';
                this.note = detail.note || '';
                this.confirmLabel = detail.confirmLabel || 'Confirm';
                this.processingLabel = detail.processingLabel || 'Processing...';
                this.tone = detail.tone || 'warning';
                this.submitting = false;
                this.open = true;

                this.$nextTick(() => {
                    document.body.style.overflow = 'hidden';
                });
            },

            cancel() {
                if (this.submitting) return;
                this.open = false;
                this.form = null;
                document.body.style.overflow = '';
            },

            confirm() {
                if (!this.form || this.submitting) return;

                this.submitting = true;

                const form = this.form;
                form.dataset.formalConfirmed = '1';
                document.body.style.overflow = '';

                HTMLFormElement.prototype.submit.call(form);
            }
        };
    }

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('form[data-formal-confirm]');
        if (!form) return;

        if (form.dataset.formalConfirmed === '1') {
            delete form.dataset.formalConfirmed;
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        window.dispatchEvent(new CustomEvent('formal-confirm', {
            detail: {
                form: form,
                title: form.dataset.confirmTitle || 'Confirm Action',
                message: form.dataset.confirmMessage || 'Please confirm that you want to continue.',
                note: form.dataset.confirmNote || '',
                confirmLabel: form.dataset.confirmLabel || 'Confirm',
                processingLabel: form.dataset.processingLabel || 'Processing...',
                tone: form.dataset.confirmTone || 'warning'
            }
        }));
    }, true);
</script>
@endonce
