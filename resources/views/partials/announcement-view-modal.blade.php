{{--
    Shared "Announcement" detail modal — ONE implementation for every module.

    Opened by any `announcement-view-trigger` element (the card's
    "Read More →" button). The Student and Security announcement pages both
    include this exact partial, so there is a single announcement-detail UI.

    Structure, per the standardized design:

        ┌──────────────────────────────────────────┐
        │ ANNOUNCEMENT TITLE                    X  │
        ├──────────────────────────────────────────┤
        │ By [Author]            [Date/Time]        │
        │ ───────────────────────────────────────  │
        │ Full announcement message/content        │
        │ (paragraphs / breaks / links / bold ...) │
        │ [IMAGE ATTACHMENT, IF PRESENT]           │
        │ [OTHER ATTACHMENTS, IF PRESENT]          │
        └──────────────────────────────────────────┘

    CONTENT RULES
    - The full announcement body is rendered — never truncated. Formatting is
      preserved because `rendered_content` is the server-sanitized HTML
      (paragraphs, <br>, strong/em/u, lists, and <a href> links, which the
      sanitizer already normalizes to target="_blank" rel="noopener
      noreferrer").
    - The body area scrolls; the modal itself stays inside the viewport.

    ATTACHMENT SECURITY
    - Images are NOT fetched from public storage. Every `src` is the authorized
      `announcements.attachments.show` endpoint, which requires an
      authenticated student/security/admin actor, re-validates that the
      attachment belongs to a real announcement, and streams the bytes from the
      PRIVATE R2 disk with `Content-Disposition: inline`. No public object URL
      is ever exposed and no authorization is bypassed.
    - Non-image attachments (PDF, documents) keep an authorized inline "View"
      and an authorized "Download".

    USAGE
    -----
    Include once per page. Recognised data attributes on the trigger:
        data-announcement-title
        data-announcement-author
        data-announcement-posted
        data-announcement-content-html   JSON string of server-sanitized HTML
        data-announcement-content-text   plain-text fallback
        data-announcement-attachments    JSON array of
                                         { name, ext, size, date, view,
                                           download, is_image }
        data-announcement-legacy         legacy public attachment URL
        data-announcement-legacy-name
--}}

<div class="modal fade announcement-modal"
     id="announcementViewerModal"
     tabindex="-1"
     aria-labelledby="announcementViewerTitle"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header announcement-modal-header">
                <h5 class="modal-title announcement-modal-title" id="announcementViewerTitle"></h5>
                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <div class="modal-body announcement-modal-body">

                {{-- Author + date/time metadata row --}}
                <div class="announcement-modal-meta">
                    <span class="announcement-card-author" id="announcementViewerAuthor"></span>
                    <span class="announcement-card-date" id="announcementViewerDate"></span>
                </div>

                <hr class="border-secondary-subtle">

                {{-- Full announcement content (never truncated). --}}
                <div class="announcement-rendered-content" id="announcementViewerContent"></div>

                {{-- ACTUAL PHOTO ATTACHMENT, rendered inline. --}}
                <div id="announcementViewerPhotos"></div>

                {{-- Other (non-image) attachments. --}}
                <div id="announcementViewerAttachments"></div>

            </div>

            <div class="modal-footer announcement-modal-footer">
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

    function sectionHeading(text) {
        var heading = document.createElement('div');
        heading.className = 'fw-semibold small text-uppercase text-muted announcement-modal-section-title';
        heading.textContent = text;
        return heading;
    }

    /**
     * One attachment row: authorized inline View + authorized Download.
     * Used for non-image attachments (PDF, documents, legacy).
     */
    function buildAttachmentRow(file) {
        var row = document.createElement('div');
        row.className = 'announcement-modal-attachment-row';

        var info = document.createElement('div');
        info.className = 'announcement-modal-attachment-info';

        var icon = document.createElement('i');
        icon.className = 'bi bi-file-earmark-text announcement-modal-attachment-icon';
        icon.setAttribute('aria-hidden', 'true');
        info.appendChild(icon);

        var meta = document.createElement('div');
        meta.className = 'announcement-attachment-meta';

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
        actions.className = 'announcement-modal-attachment-actions';

        if (file.view) {
            var view = document.createElement('a');
            view.href = file.view;
            view.target = '_blank';
            view.rel = 'noopener noreferrer';
            view.className = 'btn btn-sm btn-outline-primary';
            view.setAttribute('aria-label', 'View attachment ' + (file.name || ''));
            view.innerHTML = '<i class="bi bi-eye me-1" aria-hidden="true"></i>View';
            actions.appendChild(view);
        }

        if (file.download) {
            var dl = document.createElement('a');
            dl.href = file.download;
            dl.className = 'btn btn-sm btn-outline-secondary';
            dl.setAttribute('aria-label', 'Download attachment ' + (file.name || ''));
            dl.innerHTML = '<i class="bi bi-download me-1" aria-hidden="true"></i>Download';
            actions.appendChild(dl);
        }

        row.appendChild(actions);

        return row;
    }

    /**
     * Inline photo. `src` is the AUTHORIZED attachment endpoint — the image is
     * painted directly in the modal with no new window and no public URL.
     */
    function buildPhoto(file) {
        var figure = document.createElement('figure');
        figure.className = 'announcement-viewer-photo';

        var img = document.createElement('img');
        img.src = file.view;
        img.alt = file.name ? ('Announcement photo: ' + file.name) : 'Announcement photo';
        img.loading = 'lazy';
        img.decoding = 'async';

        // Never leave a broken-image icon behind. The real error is still
        // logged so it stays visible while debugging.
        img.addEventListener('error', function () {
            console.error('[announcement] image failed to load', {
                url: file.view,
                attachment: file.name || null
            });

            var fallback = document.createElement('div');
            fallback.className = 'announcement-viewer-photo-fallback text-muted small';
            fallback.innerHTML = '<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>'
                + 'Unable to load image.'
                + (file.download
                    ? ' <a href="' + file.download + '">Download it instead</a>.'
                    : '');

            if (img.parentNode) {
                img.parentNode.replaceChild(fallback, img);
            }
        });

        figure.appendChild(img);

        var caption = document.createElement('figcaption');
        caption.className = 'announcement-viewer-photo-caption';
        caption.appendChild(document.createTextNode(file.name || 'Photo attachment'));

        if (file.download) {
            var dl = document.createElement('a');
            dl.href = file.download;
            dl.className = 'ms-2';
            dl.setAttribute('aria-label', 'Download image ' + (file.name || ''));
            dl.innerHTML = '<i class="bi bi-download me-1" aria-hidden="true"></i>Download';
            caption.appendChild(dl);
        }

        figure.appendChild(caption);

        return figure;
    }

    function render(data) {
        var titleEl = document.getElementById('announcementViewerTitle');
        var authorEl = document.getElementById('announcementViewerAuthor');
        var dateEl = document.getElementById('announcementViewerDate');
        var contentEl = document.getElementById('announcementViewerContent');
        var photosEl = document.getElementById('announcementViewerPhotos');
        var attachmentsEl = document.getElementById('announcementViewerAttachments');

        if (!titleEl) {
            return;
        }

        titleEl.textContent = data.title || 'Announcement';

        authorEl.textContent = data.author ? ('By ' + data.author) : '';
        dateEl.textContent = data.posted || '';

        // Full content — server-sanitized HTML preserved verbatim (paragraphs,
        // line breaks, lists, bold, and already-normalized links). Falls back
        // to plain text only when no HTML is available.
        contentEl.innerHTML = '';
        if (data.contentHtml) {
            contentEl.innerHTML = data.contentHtml;
        } else {
            contentEl.textContent = data.content || '';
        }

        // Presentation hook only (no behavior change): flags a file-only
        // announcement so the Student theme can collapse the empty body area.
        contentEl.setAttribute(
            'data-empty',
            (contentEl.textContent || '').trim() === ''
                && !contentEl.querySelector('img, video, iframe')
                ? 'true'
                : 'false'
        );

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

        // ACTUAL PHOTO ATTACHMENT — inline, directly under the message. With no
        // photo the container stays empty, so no blank slot is rendered.
        photosEl.innerHTML = '';
        if (photos.length) {
            photos.forEach(function (file) {
                photosEl.appendChild(buildPhoto(file));
            });
        }

        // Non-image attachments.
        attachmentsEl.innerHTML = '';
        if (others.length) {
            attachmentsEl.appendChild(sectionHeading('Attachments'));
            others.forEach(function (file) {
                attachmentsEl.appendChild(buildAttachmentRow(file));
            });
        }
    }

    function collect(trigger) {
        return {
            title: trigger.dataset.announcementTitle || '',
            author: trigger.dataset.announcementAuthor || '',
            posted: trigger.dataset.announcementPosted || '',
            content: trigger.dataset.announcementContentText || '',
            contentHtml: parseJson(trigger.dataset.announcementContentHtml),
            attachments: parseJson(trigger.dataset.announcementAttachments) || [],
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
            if (trigger.dataset.announcementBound === '1') {
                return;
            }
            trigger.dataset.announcementBound = '1';
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

    // Re-bind after dynamic DOM updates.
    window.refreshAnnouncementViewer = bind;
})();
</script>
