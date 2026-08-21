@extends('layouts.security')

@section('title', 'Security Announcements')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="mb-1">Security Announcements</h2>
            <p class="mb-0">Review announcements shared by the administration.</p>
        </div>
        <a href="{{ route('security.dashboard') }}" class="btn portal-btn-outline">Back to Dashboard</a>
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
                        <p class="mb-0" style="white-space: pre-line; color: #3c3134;">{{ $announcement->content }}</p>
                        @if($announcement->attachment_path)
                            <div class="mt-3">
                                <a href="{{ asset($announcement->attachment_path) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm portal-btn-outline">
                                    <i class="bi bi-paperclip me-1"></i>
                                    View Attachment
                                </a>
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
