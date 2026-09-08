@extends('layouts.admin')

@section('title', 'Edit Announcement')

@section('content')

<x-admin.page-header title="Edit Announcement" description="Update the announcement and replace or remove the attachment.">
    <x-slot name="actions">
        <a href="{{ route('admin.announcements') }}" class="admin-btn admin-btn-neutral">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Back to Announcements
        </a>
    </x-slot>
</x-admin.page-header>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form action="{{ route('admin.announcements.update', $announcement) }}" method="POST" enctype="multipart/form-data" data-submit-guard="Saving...">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label for="title" class="form-label">Title</label>
                <input type="text" class="form-control" id="title" name="title" value="{{ old('title', $announcement->title) }}" required>
            </div>

            <div class="mb-4">
                <label for="content" class="form-label">Message</label>
                <input type="hidden" id="content" name="content" value="{{ old('content', $announcement->content) }}">
                <div id="announcementEditPageEditor" class="announcement-quill-editor"></div>
            </div>

            <div class="mb-4">
                <label for="attachments" class="form-label">Add Attachments</label>
                <input
                    class="form-control"
                    type="file"
                    id="attachments"
                    name="attachments[]"
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                    multiple>
                <div class="form-text">
                    Add one or more files. Supported formats: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, JPEG, PNG.
                    Maximum size per file 10MB. Files are stored securely.
                </div>
            </div>

            @php
                $secureAttachments = $announcement->attachments ?? collect();
            @endphp

            @if($secureAttachments->isNotEmpty())
                <div class="mb-4 p-3 bg-light rounded border">
                    <strong class="d-block mb-2">Current attachments</strong>
                    <ul class="list-unstyled mb-0">
                        @foreach($secureAttachments as $secure)
                            <li class="d-flex align-items-center justify-content-between flex-wrap gap-2 py-2 border-bottom">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi {{ $secure->is_image ? 'bi-file-earmark-image' : 'bi-file-earmark-text' }} text-maroon fs-5"></i>
                                    <div>
                                        <div class="fw-semibold small">{{ $secure->original_filename }}</div>
                                        <div class="text-muted small">
                                            {{ strtoupper($secure->mime_type ? \Illuminate\Support\Str::after($secure->mime_type, '/') : 'file') }}
                                            &bull; {{ $secure->human_size }}
                                            &bull; Uploaded {{ optional($secure->created_at)->format('M d, Y') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <a
                                        href="{{ route('announcements.attachments.show', $secure) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="admin-btn admin-btn-view admin-btn-sm">
                                        <i class="bi bi-eye me-1"></i>View
                                    </a>
                                    <a
                                        href="{{ route('announcements.attachments.download', $secure) }}"
                                        class="admin-btn admin-btn-view admin-btn-sm">
                                        <i class="bi bi-download me-1"></i>Download
                                    </a>
                                    <form
                                        action="{{ route('admin.announcements.attachments.destroy', $secure) }}"
                                        method="POST"
                                        class="d-inline-block"
                                        data-confirm="Remove this attachment? The file will be permanently deleted."
                                        data-confirm-title="Remove Attachment?"
                                        data-confirm-confirm-label="Remove">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">
                                            <i class="bi bi-trash me-1"></i>Remove
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($announcement->attachment_path)
                <div class="mb-4 p-3 bg-light rounded border">
                    <strong>Legacy attachment:</strong>
                    <div>
                        <a href="{{ asset($announcement->attachment_path) }}" target="_blank">View current file</a>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="remove_attachment" name="remove_attachment" value="1">
                        <label class="form-check-label" for="remove_attachment">Remove current attachment</label>
                    </div>
                </div>
            @endif

            @if(Auth::guard('admin')->user()?->isItAdministrator())
                <div class="mb-4 form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        value="1"
                        id="show_on_login"
                        name="show_on_login"
                        @checked(old('show_on_login', $announcement->show_on_login))>
                    <label class="form-check-label" for="show_on_login">
                        Show this announcement as a login modal across modules
                    </label>
                </div>

                @php
                    $existingExpiry = old('login_modal_expires_at', $announcement->login_modal_expires_at?->toDateString());
                    $existingDuration = old('login_modal_duration', $announcement->show_on_login ? ($existingExpiry ? 'custom' : '1_week') : '');
                @endphp
                <div class="mb-4 {{ (old('show_on_login', $announcement->show_on_login)) ? '' : 'd-none' }}" id="loginModalDurationWrap">
                    <label class="form-label">Duration shown as login modal</label>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <select class="form-select" id="login_modal_duration" name="login_modal_duration">
                                <option value="1_week" {{ $existingDuration === '1_week' ? 'selected' : '' }}>1 Week</option>
                                <option value="1_month" {{ $existingDuration === '1_month' ? 'selected' : '' }}>1 Month</option>
                                <option value="custom" {{ $existingDuration === 'custom' ? 'selected' : '' }}>Custom Date</option>
                            </select>
                        </div>
                        <div class="col-md-4 {{ $existingDuration === 'custom' ? '' : 'd-none' }}" id="loginModalCustomDateWrap">
                            <input
                                type="date"
                                class="form-control"
                                id="login_modal_expires_at"
                                name="login_modal_expires_at"
                                value="{{ $existingExpiry }}"
                                min="{{ now()->toDateString() }}">
                            <div class="form-text">Select the date until this login modal is shown.</div>
                        </div>
                    </div>
                </div>
            @endif

            <button type="submit" class="admin-btn admin-btn-primary">
    <i class="bi bi-check2" aria-hidden="true"></i>
    Save Changes
</button>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkbox = document.getElementById('show_on_login');
    const wrap = document.getElementById('loginModalDurationWrap');
    const duration = document.getElementById('login_modal_duration');
    const customWrap = document.getElementById('loginModalCustomDateWrap');

    if (!checkbox || !wrap) {
        return;
    }

    function toggleDuration() {
        wrap.classList.toggle('d-none', !checkbox.checked);
    }

    function toggleCustom() {
        if (!customWrap) { return; }
        customWrap.classList.toggle('d-none', !duration || duration.value !== 'custom');
    }

    checkbox.addEventListener('change', toggleDuration);
    if (duration) { duration.addEventListener('change', toggleCustom); }

    toggleDuration();
    toggleCustom();
});
</script>

@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/js/announcements.js'])
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.initAnnouncementQuill === 'function') {
        window.initAnnouncementQuill('announcementEditPageEditor', 'content');
    }
});
</script>
@endpush
