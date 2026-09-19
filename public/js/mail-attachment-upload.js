/**
 * Multi-file upload wrapper around AdinnZipUpload (large-zip-upload.js) for
 * the Printing File mail compose attachments. Each selected file gets its
 * own row + its own AdinnZipUpload instance (independent progress), so one
 * slow/failed file never blocks the others.
 */
(function () {
    'use strict';

    function formatBytes(bytes) {
        if (bytes >= 1024 * 1024 * 1024) return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
        if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
        return bytes + ' B';
    }

    function findWireId(el) {
        var root = el.closest('[wire\\:id]');
        return root ? root.getAttribute('wire:id') : null;
    }

    var IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];

    function extensionOf(filename) {
        var parts = String(filename || '').split('.');
        return parts.length > 1 ? parts.pop().toLowerCase() : '';
    }

    // View/Download actions for a completed row — built with DOM APIs (not
    // innerHTML) so an uploaded filename can never inject markup. ZIP (and
    // anything else that isn't an image/PDF) only ever gets Download.
    function renderRowActions(actionsEl, upload, downloadUrl, previewUrl) {
        if (!actionsEl) return;
        actionsEl.textContent = '';

        var ext = extensionOf(upload.original_filename);
        var isImage = IMAGE_EXTENSIONS.indexOf(ext) !== -1;
        var isPdf = ext === 'pdf';

        if ((isImage || isPdf) && previewUrl) {
            var viewBtn = document.createElement('button');
            viewBtn.type = 'button';
            viewBtn.className = 'pf-chip';
            viewBtn.textContent = 'View';
            viewBtn.addEventListener('click', function () {
                window.dispatchEvent(new CustomEvent('pf-preview-open', {
                    detail: { url: previewUrl, name: upload.original_filename, type: isPdf ? 'pdf' : 'image' },
                }));
            });
            actionsEl.appendChild(viewBtn);
        }

        if (downloadUrl) {
            var downloadLink = document.createElement('a');
            downloadLink.className = 'pf-chip';
            downloadLink.href = downloadUrl;
            downloadLink.download = upload.original_filename;
            downloadLink.target = '_blank';
            downloadLink.rel = 'noopener';
            downloadLink.textContent = 'Download';
            actionsEl.appendChild(downloadLink);
        }
    }

    function init(options) {
        // options: { input, rowsContainer, rowTemplate, taskId, urls, onCountChange }
        var pending = 0;

        function setPending(delta) {
            pending += delta;
            if (options.onCountChange) options.onCountChange(pending);
        }

        function addFile(file) {
            var row = options.rowTemplate.content.firstElementChild.cloneNode(true);
            options.rowsContainer.appendChild(row);

            var nameEl = row.querySelector('.mail-attachment-row-name');
            var percentEl = row.querySelector('.mail-attachment-row-percent');
            var detailEl = row.querySelector('.mail-attachment-row-detail');
            var statusEl = row.querySelector('.mail-attachment-row-status');
            var fillEl = row.querySelector('.mail-attachment-row-fill');
            var actionsEl = row.querySelector('.mail-attachment-row-actions');

            if (nameEl) nameEl.textContent = file.name;
            if (detailEl) detailEl.textContent = '0 MB / ' + formatBytes(file.size);

            setPending(1);

            var uploader = new AdinnZipUpload({
                input: null,
                purpose: 'mail_attachment',
                taskId: options.taskId,
                urls: options.urls,
                els: { status: statusEl, percent: percentEl, detail: detailEl },
                onProgress: function (pct) {
                    if (fillEl) fillEl.style.width = pct + '%';
                },
                onComplete: function (upload) {
                    setPending(-1);
                    var downloadUrl = options.urls.download ? options.urls.download(upload.id) : null;
                    var previewUrl = options.urls.preview ? options.urls.preview(upload.id) : null;
                    renderRowActions(actionsEl, upload, downloadUrl, previewUrl);
                    var wireId = findWireId(options.input);
                    if (wireId && window.Livewire) {
                        window.Livewire.find(wireId).call('addAttachment', {
                            id: upload.id,
                            name: upload.original_filename,
                            size: upload.size_bytes,
                            url: upload.url || null,
                        });
                    }
                },
                onError: function () {
                    setPending(-1);
                },
            });

            uploader.startUpload(file);
        }

        function handleFiles(fileList) {
            Array.prototype.forEach.call(fileList, function (file) { addFile(file); });
        }

        options.input.addEventListener('change', function () {
            handleFiles(options.input.files);
            options.input.value = '';
        });
    }

    window.AdinnMailAttachmentUpload = { init: init };
})();
