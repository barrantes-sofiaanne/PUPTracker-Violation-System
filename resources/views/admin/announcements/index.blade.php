@extends('layouts.admin')

@section('title', 'Announcements')

@section('content')

@php
    $currentAdmin = Auth::guard('admin')->user();
    $isItAdmin = (bool) $currentAdmin?->isItAdministrator();
@endphp

<div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="fw-bold mb-1">Announcements</h2>
        <p class="text-muted mb-0">Publish and manage announcements for students and staff.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.notifications') }}" class="admin-btn admin-btn-neutral">
            Notifications
            @if($unreadNotificationCount > 0)
                <span class="badge bg-danger ms-2">{{ $unreadNotificationCount }}</span>
            @endif
        </a>
        <a href="{{ route('admin.announcements.create') }}" class="admin-btn admin-btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            New Announcement
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase small text-muted fw-semibold">Posted announcements</div>
                        <div class="display-6 fw-bold">{{ $announcementStats['total'] }}</div>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                        <i class="bi bi-megaphone-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase small text-muted fw-semibold">With attachments</div>
                        <div class="display-6 fw-bold">{{ $announcementStats['with_attachments'] }}</div>
                    </div>
                    <div class="rounded-circle bg-maroon-soft p-3 text-maroon">
                        <i class="bi bi-paperclip fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <ul class="nav nav-tabs" id="announcementTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="announcement-tab" data-bs-toggle="tab" data-bs-target="#announcement-pane" type="button" role="tab">
                    <i class="bi bi-megaphone me-1"></i>
                    Announcements
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="configure-tab" data-bs-toggle="tab" data-bs-target="#configure-pane" type="button" role="tab">
                    <i class="bi bi-gear me-1"></i>
                    Configure Announcements
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body p-0">
        <div class="tab-content">
            {{-- Announcements tab (all announcements, view enlarges) --}}
            <div class="tab-pane fade show active" id="announcement-pane" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 announcements-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Posted</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($announcements as $announcement)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $announcement->title }}</div>
                                        @if($announcement->attachment_path)
                                            <span class="badge bg-secondary mt-2">Attachment</span>
                                        @endif
                                        @if($announcement->show_on_login)
                                            <span class="badge bg-info text-dark mt-2">Login Modal</span>
                                        @endif
                                        <div class="text-muted small mt-2">
                                            {{ Str::limit(strip_tags($announcement->content), 80) }}
                                        </div>
                                    </td>
                                    <td>
                                        {{ optional($announcement->adminInfo)->firstname ? optional($announcement->adminInfo)->firstname . ' ' . optional($announcement->adminInfo)->lastname : 'Admin #' . $announcement->admin_id }}
                                    </td>
                                    <td>{{ optional($announcement->created_at)->format('M d, Y h:i A') }}</td>
                                    <td class="text-end">
                                        <button
                                            type="button"
                                            class="admin-btn admin-btn-view admin-btn-sm announcement-view-btn"
                                            data-title="{{ e($announcement->title) }}"
                                            data-author="{{ optional($announcement->adminInfo)->firstname ? optional($announcement->adminInfo)->firstname . ' ' . optional($announcement->adminInfo)->lastname : 'Admin #' . $announcement->admin_id }}"
                                            data-created="{{ optional($announcement->created_at)->format('F d, Y h:i A') }}"
                                            data-content="{{ e($announcement->content) }}"
                                            data-attachment="{{ $announcement->attachment_path ? asset($announcement->attachment_path) : '' }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#announcementViewModal">
                                            <i class="bi bi-eye me-1"></i>View
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <x-admin.empty-state
                                    :colspan="4"
                                    icon="bi-megaphone"
                                    title="No announcements yet"
                                    message="No announcements have been posted. Create the first one to keep students informed."
                                    :clearUrl="null" />
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-3 d-flex justify-content-center">
                    {{ $announcements->links() }}
                </div>
            </div>

            {{-- Configure Announcements tab (only own announcements, edit/delete) --}}
            <div class="tab-pane fade" id="configure-pane" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 announcements-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Posted</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($myAnnouncements as $announcement)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $announcement->title }}</div>
                                        @if($announcement->attachment_path)
                                            <span class="badge bg-secondary mt-2">Attachment</span>
                                        @endif
                                        <div class="text-muted small mt-2">
                                            {{ Str::limit(strip_tags($announcement->content), 80) }}
                                        </div>
                                    </td>
                                    <td>{{ optional($announcement->created_at)->format('M d, Y h:i A') }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.announcements.edit', $announcement) }}" class="admin-btn admin-btn-view admin-btn-sm me-1">
                                            <i class="bi bi-pencil me-1"></i>Edit
                                        </a>
                                        <form action="{{ route('admin.announcements.destroy', $announcement) }}" method="POST" class="d-inline-block" data-confirm="This will permanently delete the announcement. This action cannot be undone."
                                              data-confirm-title="Delete Announcement?" data-confirm-confirm-label="Delete">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">
                                                <i class="bi bi-trash me-1"></i>Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <x-admin.empty-state
                                    :colspan="3"
                                    icon="bi-megaphone"
                                    title="No announcements by you"
                                    message="You have not posted any announcements yet."
                                    :clearUrl="null" />
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-3 d-flex justify-content-center">
                    {{ $myAnnouncements->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Announcement view modal --}}
<div class="modal fade" id="announcementViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="announcementViewTitle">Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-muted small mb-3">
                    <span id="announcementViewAuthor"></span>
                    <span class="mx-1">•</span>
                    <span id="announcementViewCreated"></span>
                </div>
                <p class="mb-0" style="white-space: pre-line;" id="announcementViewContent"></p>
            </div>
            <div class="modal-footer">
                <a href="#" target="_blank" rel="noopener noreferrer" class="admin-btn admin-btn-view d-none" id="announcementViewAttachment">
                    <i class="bi bi-paperclip me-1"></i>View Attachment
                </a>
                <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('announcementViewModal');
    if (!modal) {
        return;
    }

    const titleEl = document.getElementById('announcementViewTitle');
    const authorEl = document.getElementById('announcementViewAuthor');
    const createdEl = document.getElementById('announcementViewCreated');
    const contentEl = document.getElementById('announcementViewContent');
    const attachmentEl = document.getElementById('announcementViewAttachment');

    document.querySelectorAll('.announcement-view-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            titleEl.textContent = button.dataset.title || 'Announcement';
            authorEl.textContent = button.dataset.author ? 'By ' + button.dataset.author : '';
            createdEl.textContent = button.dataset.created || '';
            contentEl.textContent = button.dataset.content || '';

            if (button.dataset.attachment) {
                attachmentEl.href = button.dataset.attachment;
                attachmentEl.classList.remove('d-none');
            } else {
                attachmentEl.href = '#';
                attachmentEl.classList.add('d-none');
            }
        });
    });
});
</script>
@endpush
