/**
 * Direct-to-cloud (DigitalOcean Spaces) multipart ZIP upload for large
 * (up to 6 GB) Progress Update / Rework files. Bytes never touch the
 * Laravel app server — each part is PUT straight to a presigned S3 URL.
 *
 * Resume model: a full page reload (in-app navigation to another task,
 * or closing/reopening the browser) always destroys this script's JS
 * state — that is a browser security boundary, not something any web
 * app can work around. What DOES survive is server-side checkpointing:
 * every part that finished PUTting is already durably on Spaces. So on
 * return, if the same file (same name + size) is re-selected, already
 * uploaded parts are detected via /parts and skipped — only the
 * remaining bytes are sent. A tab switch (same tab, same JS context)
 * needs no special handling at all: the underlying XMLHttpRequest keeps
 * running in the background exactly as it would in the foreground.
 */
(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function formatBytes(bytes) {
        if (bytes >= 1024 * 1024 * 1024) return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
        if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
        return bytes + ' B';
    }

    function formatEta(seconds) {
        if (!isFinite(seconds) || seconds <= 0) return 'Calculating…';
        if (seconds < 60) return 'Less than a minute remaining';
        var h = Math.floor(seconds / 3600);
        var m = Math.round((seconds % 3600) / 60);
        if (m === 60) { h += 1; m = 0; }
        if (h > 0) return '~' + h + 'h' + (m > 0 ? ' ' + m + 'm' : '') + ' remaining';
        return '~' + m + 'm remaining';
    }

    function findWire(el) {
        var root = el.closest('[wire\\:id]');
        if (!root || !window.Livewire) return null;
        return window.Livewire.find(root.getAttribute('wire:id'));
    }

    function AdinnZipUpload(options) {
        this.input = options.input;
        this.purpose = options.purpose;
        this.taskId = options.taskId;
        this.wireProp = options.wireProp;
        this.urls = options.urls;
        this.els = options.els; // { status, percent, detail, eta, circle, submitBtn }

        this.uploadedBytesBase = 0;
        this.uploadedBytesSession = 0;
        this.totalBytes = 0;
        this.partSize = 0;
        this.smoothedSpeed = 0;
        this.lastSampleTime = 0;
        this.lastSampleBytes = 0;
        this.currentFile = null;
        this.currentUploadId = null;

        var self = this;
        this.input.addEventListener('change', function () { self.onFileSelected(); });
        this.checkActive();

        // Fired by TaskDetail::submitEod()/submitReworkUpdate() after a
        // successful submission — the Livewire-side upload-id property is
        // already reset by then, but this widget's DOM lives under
        // wire:ignore (so it survives re-renders while uploading), which
        // means Livewire's own re-render can never clear it. Reset it here
        // instead, so the form is ready for the next submission without
        // touching the file that was just persisted.
        window.addEventListener('eod-updated', function () { self.reset(); });
    }

    AdinnZipUpload.prototype.reset = function () {
        this.currentFile = null;
        this.currentUploadId = null;
        this.uploadedBytesBase = 0;
        this.uploadedBytesSession = 0;
        this.totalBytes = 0;
        this.smoothedSpeed = 0;
        this.lastSampleTime = 0;
        this.lastSampleBytes = 0;
        this.input.value = '';

        if (this.els.status) this.els.status.innerHTML = '';
        if (this.els.percent) this.els.percent.textContent = '';
        if (this.els.detail) this.els.detail.textContent = '';
        if (this.els.eta) this.els.eta.textContent = '';
        if (this.els.circle) this.els.circle.setAttribute('stroke-dasharray', '0, 100');

        this.setSubmitEnabled(false);
    };

    AdinnZipUpload.prototype.checkActive = function () {
        var self = this;
        fetch(this.urls.active + '?task_id=' + this.taskId + '&purpose=' + this.purpose, { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) { if (data.upload) self.renderResumeBanner(data.upload); })
            .catch(function () {});
    };

    AdinnZipUpload.prototype.renderResumeBanner = function (upload) {
        if (!this.els.status) return;
        var pct = upload.size_bytes > 0 ? Math.round((upload.uploaded_bytes / upload.size_bytes) * 100) : 0;
        this.els.status.innerHTML = '<div class="upload-status-note upload-status-note--resume">'
            + 'Incomplete upload found: <strong>' + this.escapeHtml(upload.original_filename) + '</strong> '
            + '(' + pct + '% — ' + formatBytes(upload.uploaded_bytes) + ' / ' + formatBytes(upload.size_bytes) + '). '
            + 'Select the same file below to resume, or choose a different file to start over.</div>';
    };

    AdinnZipUpload.prototype.escapeHtml = function (value) {
        var div = document.createElement('div');
        div.textContent = String(value == null ? '' : value);
        return div.innerHTML;
    };

    AdinnZipUpload.prototype.onFileSelected = function () {
        var file = this.input.files && this.input.files[0];

        // No file (input cleared with nothing re-selected) — same as an
        // invalid/failed selection: the previously completed upload no
        // longer has a matching file, so the reference must be dropped and
        // Submit disabled again, not left pointing at a stale upload.
        if (!file) {
            this.currentFile = null;
            this.setWireProp('');
            this.setSubmitEnabled(false);
            if (this.els.status) this.els.status.innerHTML = '';
            return;
        }

        if (!/\.zip$/i.test(file.name)) {
            this.setWireProp('');
            this.setSubmitEnabled(false);
            this.showError('Only ZIP files are allowed.');
            this.input.value = '';
            return;
        }

        if (file.size > 6 * 1024 * 1024 * 1024) {
            this.setWireProp('');
            this.setSubmitEnabled(false);
            this.showError('Maximum file size is 6 GB.');
            this.input.value = '';
            return;
        }

        this.currentFile = file;
        this.totalBytes = file.size;
        this.uploadedBytesSession = 0;
        this.smoothedSpeed = 0;
        this.lastSampleTime = 0;
        this.lastSampleBytes = 0;
        this.setSubmitEnabled(false);
        this.setWireProp('');
        this.showState('preparing');

        var self = this;

        this.postJson(this.urls.initiate, {
            task_id: this.taskId, purpose: this.purpose, filename: file.name, size_bytes: file.size,
        })
            .then(function (upload) {
                self.currentUploadId = upload.id;
                self.uploadedBytesBase = upload.uploaded_bytes || 0;
                self.partSize = upload.part_size_bytes;
                return self.fetchExistingParts();
            })
            .then(function (existingPartNumbers) { return self.uploadRemainingParts(existingPartNumbers); })
            .then(function () {
                self.showState('finalizing');
                return self.postJson(self.urls.complete(self.currentUploadId), {});
            })
            .then(function (upload) {
                self.setWireProp(String(upload.id));
                self.showState('completed', upload);
                self.setSubmitEnabled(true);
            })
            .catch(function (err) {
                self.setSubmitEnabled(false);
                self.showState('failed', null, err && err.message);
            });
    };

    AdinnZipUpload.prototype.fetchExistingParts = function () {
        var self = this;
        return fetch(this.urls.parts(this.currentUploadId), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                self.uploadedBytesBase = data.uploaded_bytes || 0;
                self.render();
                return data.part_numbers || [];
            });
    };

    AdinnZipUpload.prototype.uploadRemainingParts = function (existingPartNumbers) {
        var totalParts = Math.ceil(this.totalBytes / this.partSize) || 1;
        var pending = [];
        for (var n = 1; n <= totalParts; n++) {
            if (existingPartNumbers.indexOf(n) === -1) pending.push(n);
        }
        if (pending.length > 0) this.showState('uploading');
        return this.uploadPartsSequentially(pending, 0);
    };

    AdinnZipUpload.prototype.uploadPartsSequentially = function (pending, index) {
        var self = this;
        if (index >= pending.length) return Promise.resolve();
        return this.uploadOnePart(pending[index]).then(function () {
            return self.uploadPartsSequentially(pending, index + 1);
        });
    };

    AdinnZipUpload.prototype.uploadOnePart = function (partNumber) {
        var self = this;
        var start = (partNumber - 1) * this.partSize;
        var end = Math.min(start + this.partSize, this.totalBytes);
        var blob = this.currentFile.slice(start, end);

        return fetch(this.urls.partUrl(this.currentUploadId) + '?part_number=' + partNumber, { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) { return self.putPart(data.url, blob); });
    };

    AdinnZipUpload.prototype.putPart = function (url, blob) {
        var self = this;
        var lastLoaded = 0;

        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('PUT', url, true);
            xhr.upload.onprogress = function (e) {
                self.recordProgress(e.loaded - lastLoaded);
                lastLoaded = e.loaded;
            };
            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) resolve();
                else reject(new Error('Upload failed while sending part of the file. You can try again — progress made so far is kept.'));
            };
            xhr.onerror = function () {
                reject(new Error('Network error while uploading. You can try again — progress made so far is kept.'));
            };
            xhr.send(blob);
        });
    };

    AdinnZipUpload.prototype.recordProgress = function (deltaBytes) {
        this.uploadedBytesSession += deltaBytes;
        var now = Date.now();

        if (!this.lastSampleTime) {
            this.lastSampleTime = now;
            this.lastSampleBytes = this.uploadedBytesSession;
            this.render();
            return;
        }

        var dt = (now - this.lastSampleTime) / 1000;
        if (dt < 0.4) return;

        var instantSpeed = (this.uploadedBytesSession - this.lastSampleBytes) / dt;
        this.smoothedSpeed = this.smoothedSpeed ? (this.smoothedSpeed * 0.7 + instantSpeed * 0.3) : instantSpeed;
        this.lastSampleTime = now;
        this.lastSampleBytes = this.uploadedBytesSession;
        this.render();
    };

    AdinnZipUpload.prototype.render = function () {
        var uploaded = Math.min(this.totalBytes, this.uploadedBytesBase + this.uploadedBytesSession);
        var pct = this.totalBytes > 0 ? Math.min(100, Math.round((uploaded / this.totalBytes) * 100)) : 0;
        var remaining = Math.max(0, this.totalBytes - uploaded);
        var eta = this.smoothedSpeed > 1024 ? remaining / this.smoothedSpeed : NaN;

        if (this.els.percent) this.els.percent.textContent = pct + '%';
        if (this.els.detail) this.els.detail.textContent = formatBytes(uploaded) + ' / ' + formatBytes(this.totalBytes);
        if (this.els.eta) this.els.eta.textContent = pct >= 100 ? '' : formatEta(eta);
        if (this.els.circle) this.els.circle.setAttribute('stroke-dasharray', pct + ', 100');
    };

    AdinnZipUpload.prototype.showState = function (state, upload, errorMessage) {
        if (!this.els.status) return;

        var labels = {
            preparing: 'Preparing upload…',
            uploading: 'Uploading…',
            finalizing: 'Finalizing on cloud storage…',
            completed: '✓ Uploaded — ready to submit',
            failed: 'Upload failed',
        };

        this.els.status.innerHTML = '<div class="upload-status-note upload-status-note--' + state + '">' + labels[state] + '</div>';

        if (state === 'preparing' || state === 'uploading' || state === 'finalizing') {
            this.render();
        }

        if (state === 'completed') {
            if (this.els.percent) this.els.percent.textContent = '100%';
            if (this.els.eta) this.els.eta.textContent = '';
            if (this.els.circle) this.els.circle.setAttribute('stroke-dasharray', '100, 100');
        }

        if (state === 'failed' && errorMessage) {
            this.els.status.innerHTML += '<div class="upload-status-note upload-status-note--error">' + this.escapeHtml(errorMessage) + '</div>';
        }
    };

    AdinnZipUpload.prototype.showError = function (message) {
        this.showState('failed', null, message);
    };

    AdinnZipUpload.prototype.setSubmitEnabled = function (enabled) {
        if (this.els.submitBtn) this.els.submitBtn.disabled = !enabled;
    };

    AdinnZipUpload.prototype.setWireProp = function (value) {
        var wire = findWire(this.input);
        if (wire) wire.set(this.wireProp, value);
    };

    AdinnZipUpload.prototype.postJson = function (url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            body: JSON.stringify(body),
        }).then(function (r) {
            if (r.ok) return r.json();

            return r.json().catch(function () { return {}; }).then(function (body) {
                var message = body && body.errors ? Object.values(body.errors)[0][0] : 'The upload could not be processed.';
                throw new Error(message);
            });
        });
    };

    window.AdinnZipUpload = AdinnZipUpload;
})();
