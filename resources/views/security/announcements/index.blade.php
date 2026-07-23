@extends('layouts.app')

@section('title', 'Security Announcements')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="mb-1">Security Announcements</h2>
            <p class="text-muted mb-0">Review announcements shared by the administration.</p>
        </div>
        <a href="{{ route('security.dashboard') }}" class="btn btn-outline-secondary">Back to Dashboard</a>
    </div>

    <div class="row g-3">
        @forelse($announcements as $announcement)
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                            <h5 class="fw-bold mb-0">{{ $announcement->title }}</h5>
                            <span class="badge bg-secondary">{{ optional($announcement->created_at)->format('M d, Y') }}</span>
                        </div>
                        <p class="text-muted mb-0" style="white-space: pre-line;">{{ $announcement->content }}</p>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5 text-muted">No announcements available.</div>
                </div>
            </div>
        @endforelse
    </div>

    <div class="mt-4 d-flex justify-content-center">
        {{ $announcements->links() }}
    </div>
</div>
@endsection
