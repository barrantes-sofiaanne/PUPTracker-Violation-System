{{--
    Shared "View Announcement" interface.

    This is the single announcement viewer used by every module (student,
    security, admin). The Security Module's Announcement Tab is the design
    reference, so the card/typography/badge/attachment-row language here is
    taken from `resources/views/security/announcements/index.blade.php`.

    Content order is fixed and matches the agreed specification:

        TITLE
        MESSAGE
        ACTUAL PHOTO ATTACHMENT   (rendered inline)
        other attachments         (unchanged behaviour)
        [Close]

    ATTACHMENT SECURITY
    -------------------
    Photos are NOT fetched from public storage. Each `<img>` points at the
    existing authorized endpoint `announcements.attachments.show`, which:
      * requires an authenticated student/security/admin actor,
      * re-validates that the attachment belongs to a real announcement,
      * streams the bytes from the PRIVATE disk with `Content-Disposition:
        inline`, and
      * writes an ATTACHMENT_VIEWED audit entry.
    No public URLs, no public disk, no authorization bypass.

    USAGE
    -----
    Include once per layout. Any element carrying the
    `announcement-view-trigger` class opens the modal. Recognised data
    attributes (all optional except the title):

        data-announcement-title
        data-announcement-posted
        data-announcement-content-html   JSON string of server-sanitized HTML
        data-announcement-content-text   plain-text fallback
        data-announcement-attachments    JSON array of
                                         { name, ext, size, date, view,
                                           download, is_image }
        data-announcement-legacy         legacy public attachment URL
        data-announcement-legacy-name
--}}

<div class="modal fade"
     id="announcementViewerModal"
     tabindex="-1"
     aria-labelledby="announcementViewerTitle"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="announcementViewerTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <small class="text-muted d-block mb-3" id="announcementViewerDate"></small>

                {{-- 1. TITLE (in the modal header) / 2. MESSAGE --}}
                <div class="announcement-rendered-content" id="announcementViewerContent"></div>

                {{-- 3. ACTUAL PHOTO ATTACHMENT --}}
                <div id="announcementViewerPhotos" class="mt-3"></div>

                {{-- Other (non-image) attachments — behaviour preserved --}}
                <div id="announcementViewerAttachments" class="mt-3"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    function parseJson(value) {
        if (!value) {
            return null;
        }
        try {
            return JSON.parse(value);
        } catch (e) {
            return null;
        }
    }

    function buildAttachmentRow(file) {
        var row = document.createElement('div');
        row.className = 'd-flex align-items-center justify-content-between flex-wrap gap-2 border rounded p-2 mb-2 bg-white';

        var info = document.createElement('div');
        info.className = 'd-flex align-items-center gap-2';

        var icon = document.createElement('i');
        icon.className = 'bi bi-file-earmark-text fs-4 text-maroon';
        info.appendChild(icon);

        var meta = document.createElement('div');
        var name = document.createElement('div');
        name.className = 'fw-semibold small';
        name.textContent = file.name || 'Attachment';
        meta.appendChild(name);

        var detail = document.createElement('div');
        detail.className = 'text-muted small';
        var bits = [];
        if (file.ext) { bits.push(String(file.ext).toUpperCase()); }
        if (file.size) { bits.push(file.size); }
        if (file.date) { bits.push('Uploaded ' + file.date); }
        detail.textContent = bits.join(' \u2022 ');
        meta.appendChild(detail);
        info.appendChild(meta);
        row.appendChild(info);

        var actions = document.createElement('div');
        actions.className = 'd-flex gap-2';
        if (file.view) {
            var view = document.createElement('a');
            view.href = file.view;
            view.target = '_blank';
            view.rel = 'noopener noreferrer';
            view.className = 'btn btn-sm btn-outline-primary';
            view.innerHTML = '<i class="bi bi-eye me-1"></i>View';
            actions.appendChild(view);
        }
        if (file.download) {
            var dl = document.createElement('a');
            dl.href = file.download;
            dl.className = 'btn btn-sm btn-outline-secondary';
            dl.innerHTML = '<i class="bi bi-download me-1"></i>Download';
            actions.appendChild(dl);
        }
        row.appendChild(actions);

        return row;
    }

    function buildPhoto(file) {
        var wrap = document.createElement('div');
        wrap.className = 'announcement-viewer-photo';

        var img = document.createElement('img');
        // Authorized endpoint: streams inline from the private disk and
        // re-checks authorization server-side on every request.
        img.src = file.view;
        img.alt = file.name ? ('Announcement photo: ' + file.name) : 'Announcement photo';
        img.loading = 'lazy';
        img.decoding = 'async';
        wrap.appendChild(img);

        var caption = document.createElement('div');
        caption.className = 'announcement-viewer-photo-caption';
        caption.textContent = file.name || 'Photo attachment';
        if (file.download) {
            var dl = document.createElement('a');
            dl.href = file.download;
            dl.className = 'ms-2';
            dl.innerHTML = '<i class="bi bi-download me-1"></i>Download';
            caption.appendChild(dl);
        }
        wrap.appendChild(caption);

        return wrap;
    }

    function sectionHeading(text) {
        var heading = document.createElement('div');
        heading.className = 'fw-semibold small text-uppercase text-muted mb-2';
        heading.textContent = text;
        return heading;
    }

    function render(data) {
        var titleEl = document.getElementById('announcementViewerTitle');
        var dateEl = document.getElementById('announcementViewerDate');
        var contentEl = document.getElementById('announcementViewerContent');
        var photosEl = document.getElementById('announcementViewerPhotos');
        var attachmentsEl = document.getElementById('announcementViewerAttachments');

        if (!titleEl) {
            return;
        }

        // 1. TITLE
        titleEl.textContent = data.title || 'Announcement';

        // Metadata (date/time) — kept as the Security Module presents it.
        dateEl.textContent = data.posted ? 'Posted ' + data.posted : '';

        // 2. MESSAGE — server-sanitized HTML, falling back to plain text.
        contentEl.innerHTML = '';
        if (data.contentHtml) {
            contentEl.innerHTML = data.contentHtml;
        } else {
            contentEl.textContent = data.content || '';
        }

        var items = data.attachments || [];
        var photos = items.filter(function (f) { return !!f.is_image && !!f.view; });
        var others = items.filter(function (f) { return !f.is_image; });

        // Legacy single public attachment (old rows) — unchanged behaviour.
        if (data.legacyAttachment) {
            others.push({
                name: data.legacyName || 'Attachment',
                ext: 'Legacy',
                is_image: false,
                view: data.legacyAttachment,
                download: data.legacyAttachment
            });
        }

        // 3. ACTUAL PHOTO ATTACHMENT — rendered inline, directly under the
        //    message. When there is no photo the container is left empty so no
        //    blank image slot appears.
        photosEl.innerHTML = '';
        if (photos.length) {
            photos.forEach(function (file) {
                photosEl.appendChild(buildPhoto(file));
            });
        }

        // Non-image attachments — existing behaviour preserved.
        attachmentsEl.innerHTML = '';
        if (others.length) {
            attachmentsEl.appendChild(sectionHeading('Attachments'));
            others.forEach(function (file) {
                attachmentsEl.appendChild(buildAttachmentRow(file));
            });
        }
    }

    function collect(trigger) {
        var attachments = parseJson(trigger.dataset.announcementAttachments) || [];
        var contentHtml = parseJson(trigger.dataset.announcementContentHtml);

        return {
            title: trigger.dataset.announcementTitle || '',
            posted: trigger.dataset.announcementPosted || '',
            content: trigger.dataset.announcementContentText || '',
            contentHtml: contentHtml,
            attachments: attachments,
            legacyAttachment: trigger.dataset.announcementLegacy || null,
            legacyName: trigger.dataset.announcementLegacyName || null
        };
    }

    function bind() {
        var modalEl = document.getElementById('announcementViewerModal');
        if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return;
        }

        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        document.querySelectorAll('.announcement-view-trigger').forEach(function (trigger) {
            if (trigger.dataset.announcementViewerBound === '1') {
                return;
            }
            trigger.dataset.announcementViewerBound = '1';
            trigger.addEventListener('click', function () {
                render(collect(trigger));
                modal.show();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }

    // Re-bind after dynamic DOM updates (e.g. pagination/AJAX list refresh).
    window.refreshAnnouncementViewer = bind;
})();
</script>
