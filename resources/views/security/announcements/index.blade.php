@extends('layouts.security')

@section('title', 'Security Announcements')

@section('content')
<div class="container-fluid py-1">
    <div class="page-header-modern d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Security Announcements</h2>
            <p class="text-muted mb-0">Review announcements shared by the administration.</p>
        </div>
        <a href="{{ route('security.dashboard') }}" class="admin-btn admin-btn-neutral">
            <i class="bi bi-speedometer2 me-1"></i>Back to Dashboard
        </a>
    </div>

    <div class="row g-3">
        @forelse($announcements as $announcement)
            <div class="col-12">
                <div class="card portal-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                            <h5 class="fw-bold mb-0">{{ $announcement->title }}</h5>
                            <span class="portal-badge muted">{{ optional($announcement->created_at)->format('M d, Y') }}</span>
                        </div>
                        <div class="announcement-rendered-content">{!! $announcement->rendered_content !!}</div>

                        @php
                            $hasSecure = $announcement->attachments->isNotEmpty();
                            $hasLegacy = (bool) $announcement->attachment_path;
                        @endphp

                        @if($hasSecure || $hasLegacy)
                            <div class="mt-3">
                                <div class="fw-semibold small text-uppercase text-muted mb-2">Attachments</div>
                                @foreach($announcement->attachments as $file)
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 border rounded p-2 mb-2 bg-white">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi {{ $file->is_image ? 'bi-file-earmark-image' : 'bi-file-earmark-text' }} fs-4 text-maroon"></i>
                                            <div>
                                                <div class="fw-semibold small">{{ $file->original_filename }}</div>
                                                <div class="text-muted small">
                                                    {{ $file->is_image ? 'IMAGE' : strtoupper((string) \Illuminate\Support\Str::after((string) $file->mime_type, '/')) }}
                                                    @if($file->human_size !== '0 B') &bull; {{ $file->human_size }} @endif
                                                    @if($file->created_at) &bull; Uploaded {{ $file->created_at->format('M d, Y') }} @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('announcements.attachments.show', $file) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye me-1"></i>View
                                            </a>
                                            <a href="{{ route('announcements.attachments.download', $file) }}" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-download me-1"></i>Download
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                                @if($hasLegacy)
                                    <a href="{{ asset($announcement->attachment_path) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm portal-btn-outline">
                                        <i class="bi bi-paperclip me-1"></i>
                                        View Attachment
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card portal-card">
                    <div class="card-body text-center py-5" style="color: #67585c;">No announcements available.</div>
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection
