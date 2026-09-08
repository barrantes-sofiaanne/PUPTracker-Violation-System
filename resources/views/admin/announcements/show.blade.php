@extends('layouts.admin')

@section('title', 'Announcement Details')

@section('content')

@php
    $currentAdmin = Auth::guard('admin')->user();
    $canManage = (bool) $currentAdmin?->isItAdministrator()
        || (int) $announcement->admin_id === (int) $currentAdmin?->getKey();
@endphp

<x-admin.page-header title="Announcement Details" description="Review the announcement content and attachments.">
    <x-slot name="actions">
        <a href="{{ route('admin.announcements') }}" class="admin-btn admin-btn-neutral">Back to Announcements</a>
        @if($canManage)
            <a href="{{ route('admin.announcements.edit', $announcement) }}" class="admin-btn admin-btn-primary">Edit Announcement</a>
        @endif
    </x-slot>
</x-admin.page-header>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <div class="mb-4">
            <h4 class="fw-bold">{{ $announcement->title }}</h4>
            <div class="text-muted">
                Posted on {{ optional($announcement->created_at)->format('F d, Y h:i A') }}
                @if(optional($announcement->adminInfo)->firstname)
                    &middot; by {{ optional($announcement->adminInfo)->firstname }} {{ optional($announcement->adminInfo)->lastname }}
                @endif
            </div>
        </div>

        <div class="mb-4 announcement-rendered-content">
            {!! $announcement->rendered_content !!}
        </div>

        @php
            $secureAttachments = $announcement->attachments ?? collect();
        @endphp

        @if($secureAttachments->isNotEmpty() || $announcement->attachment_path)
            <div class="border rounded p-3 bg-light mb-4">
                <strong class="d-block mb-2">
                    <i class="bi bi-paperclip me-1"></i>Attachments
                </strong>

                @foreach($secureAttachments as $file)
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 border rounded p-2 mb-2 bg-white">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi {{ $file->is_image ? 'bi-file-earmark-image' : 'bi-file-earmark-text' }} fs-4 text-maroon"></i>
                            <div>
                                <div class="fw-semibold small">{{ $file->original_filename }}</div>
                                <div class="text-muted small">
                                    {{ $file->is_image ? 'IMAGE' : strtoupper((string) \Illuminate\Support\Str::after((string) $file->mime_type, '/')) }}
                                    @if($file->human_size !== '0 B') &bull; {{ $file->human_size }} @endif
                                    @if($file->created_at) &bull; Uploaded {{ $file->created_at->format('F d, Y') }} @endif
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('announcements.attachments.show', $file) }}" target="_blank" rel="noopener noreferrer" class="admin-btn admin-btn-view admin-btn-sm">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                            <a href="{{ route('announcements.attachments.download', $file) }}" class="admin-btn admin-btn-view admin-btn-sm">
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                            @if($canManage)
                                <form action="{{ route('admin.announcements.attachments.destroy', $file) }}" method="POST" class="d-inline-block"
                                      data-confirm="Remove this attachment? The file will be permanently deleted."
                                      data-confirm-title="Remove Attachment?" data-confirm-confirm-label="Remove">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">
                                        <i class="bi bi-trash me-1"></i>Remove
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if($announcement->attachment_path)
                    <div class="mt-2">
                        <a href="{{ asset($announcement->attachment_path) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-paperclip me-1"></i>Legacy attachment
                        </a>
                    </div>
                @endif
            </div>
        @endif

        @if($canManage)
            <div class="border rounded p-3 bg-light">
                <form action="{{ route('admin.announcements.attachments.store', $announcement) }}" method="POST" enctype="multipart/form-data" data-submit-guard="Adding...">
                    @csrf
                    <label for="add_attachments" class="form-label fw-semibold"><i class="bi bi-plus-circle me-1"></i>Add Attachments</label>
                    <div class="d-flex flex-column flex-md-row gap-2">
                        <input
                            class="form-control"
                            type="file"
                            id="add_attachments"
                            name="attachments[]"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                            multiple
                            required>
                        <button type="submit" class="admin-btn admin-btn-primary text-nowrap">
                            <i class="bi bi-upload me-1"></i>Upload
                        </button>
                    </div>
                    <div class="form-text">PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, JPEG, PNG. Max 10MB per file.</div>
                </form>
            </div>
        @endif
    </div>
</div>

@endsection
