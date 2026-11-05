/**
 * Multi-file picker for Progress Update / Rework submissions. Each file gets
 * its own row and is uploaded (one after another, because the server keeps a
 * single in-flight session per task + purpose) through AdinnZipUpload. The
 * finished upload ids are written to the Livewire property as "12,13,14".
 *
 * Rule: several files, or any non-ZIP file, must total under 350 MB; a single
 * ZIP can be up to 6 GB.
 *
 * Optional options (used by the task chat; Progress Update / Rework ignore them):
 *   hardLimit  - total of ALL files (ZIP or not) may not exceed 350 MB, mixes are fine
 *   maxFiles   - cap on the number of files (default 20)
 *   onChange   - function (idsCsv, busy, items) called whenever the upload state changes
 *   resetEvent - window event that clears the list (default 'eod-updated')
 *   wireProp   - may be omitted when there is no Livewire component to write to
 */
(function () {
    'use strict';

    var LIMIT = 350 * 1024 * 1024;

    function formatBytes(bytes) {
        if (bytes >= 1024 * 1024 * 1024) return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
        if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
        return bytes + ' B';
    }

    function isZip(file) { return /\.zip$/i.test(file.name); }

    function init(o) {
        // o: { input, list, notice, purpose, taskId, wireProp, urls, submitBtn }
        var items = [];
        var running = false;

        function wire() {
            var root = o.input.closest('[wire\\:id]');
            return root && window.Livewire ? window.Livewire.find(root.getAttribute('wire:id')) : null;
        }

        function notify(message) {
            if (o.notice) o.notice.textContent = message || '';
        }

        function sync() {
            var ids = items.filter(function (i) { return i.state === 'done'; }).map(function (i) { return i.id; });
            var busy = items.some(function (i) { return i.state === 'queued' || i.state === 'uploading'; });
            if (o.wireProp) {
                var w = wire();
                if (w) w.set(o.wireProp, busy ? '' : ids.join(','));
            }
            if (o.onChange) o.onChange(busy ? '' : ids.join(','), busy, items);
            if (o.submitBtn) o.submitBtn.disabled = busy || ids.length === 0 || items.some(function (i) { return i.state === 'failed'; });
        }

        function totalSize(extra) {
            return items.concat(extra || []).reduce(function (sum, i) { return sum + (i.file ? i.file.size : i.size); }, 0);
        }

        function render(item) {
            if (!item.row) {
                var row = document.createElement('div');
                row.className = 'mf-row';
                row.innerHTML = '<div class="mf-top"><span class="mf-name"></span><span class="mf-percent"></span></div>'
                    + '<div class="mf-bar"><div class="mf-fill"></div></div>'
                    + '<div class="mf-detail"></div><div class="mf-status"></div>'
                    + '<button type="button" class="mf-remove" title="Remove">&times;</button>';
                row.querySelector('.mf-name').textContent = item.file.name;
                row.querySelector('.mf-remove').addEventListener('click', function () {
                    // X on a file that is uploading cancels it: the running
                    // upload is aborted and the next queued file carries on.
                    if (item.state === 'uploading' && item.uploader) {
                        item.uploader.cancel();
                        running = false;
                    }
                    items.splice(items.indexOf(item), 1);
                    row.remove();
                    sync();
                    pump();
                });
                o.list.appendChild(row);
                item.row = row;
            }
            item.row.querySelector('.mf-detail').textContent = formatBytes(item.file.size);
            item.row.className = 'mf-row mf-' + item.state;
        }

        function pump() {
            if (running) return;
            var next = items.filter(function (i) { return i.state === 'queued'; })[0];
            if (!next) { sync(); return; }

            running = true;
            next.state = 'uploading';
            render(next);
            sync();

            var row = next.row;
            var uploader = new AdinnZipUpload({
                input: null,
                purpose: o.purpose,
                taskId: o.taskId,
                urls: o.urls,
                els: {
                    status: row.querySelector('.mf-status'),
                    percent: row.querySelector('.mf-percent'),
                    detail: row.querySelector('.mf-detail'),
                },
                onProgress: function (pct) { row.querySelector('.mf-fill').style.width = pct + '%'; },
                onComplete: function (upload) {
                    next.id = String(upload.id);
                    next.state = 'done';
                    row.querySelector('.mf-fill').style.width = '100%';
                    row.querySelector('.mf-status').innerHTML = '<span class="mf-ok">✓ Uploaded</span>';
                    running = false;
                    render(next);
                    pump();
                },
                onError: function () {
                    next.state = 'failed';
                    running = false;
                    render(next);
                    sync();
                    pump();
                },
            });
            next.uploader = uploader;
            uploader.startUpload(next.file);
        }

        o.input.addEventListener('change', function () {
            var picked = Array.prototype.slice.call(o.input.files || []);
            o.input.value = '';
            notify('');

            picked = picked.filter(function (f) {
                return !items.some(function (i) { return i.file.name === f.name && i.file.size === f.size; });
            });
            if (!picked.length) return;

            var addedTotal = picked.reduce(function (s, f) { return s + f.size; }, 0);
            var combined = totalSize() + addedTotal;
            var count = items.length + picked.length;
            var singleZip = count === 1 && isZip(picked[0]);
            var maxFiles = o.maxFiles || 20;

            if (o.hardLimit) {
                if (combined > LIMIT) {
                    notify('These files total ' + formatBytes(combined) + '. Attachments can total up to 350 MB.');
                    return;
                }
            } else {
                if (!singleZip && combined >= LIMIT) {
                    notify('These files total ' + formatBytes(combined) + '. Files totalling 350 MB or more must be uploaded as one ZIP.');
                    return;
                }
                if (count > 1 && (picked.some(isZip) || items.some(function (i) { return isZip(i.file); }))) {
                    notify('Add either one ZIP file or several individual files — not a mix.');
                    return;
                }
            }
            if (count > maxFiles) {
                notify('You can add up to ' + maxFiles + ' files.');
                return;
            }

            picked.forEach(function (file) {
                var item = { file: file, state: 'queued', id: null, row: null };
                items.push(item);
                render(item);
            });
            sync();
            pump();
        });

        // Livewire fires this after a successful submission.
        window.addEventListener(o.resetEvent || 'eod-updated', function () {
            items = [];
            running = false;
            o.list.innerHTML = '';
            notify('');
            if (o.submitBtn) o.submitBtn.disabled = true;
            if (o.onChange) o.onChange('', false, items);
        });
    }

    window.AdinnMultiFileUpload = { init: init };
})();
