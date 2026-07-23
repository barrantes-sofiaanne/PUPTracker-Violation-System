@extends('layouts.app')

@section('title', 'Security Notifications')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="mb-1">Security Notifications</h2>
            <p class="text-muted mb-0">Monitor the latest notices and follow-up actions.</p>
        </div>
        <a href="{{ route('security.dashboard') }}" class="btn btn-outline-secondary">Back to Dashboard</a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            @forelse($notifications as $notification)
                <div class="border-bottom p-4 d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="mb-2">
                            @if(! $notification->is_read)
                                <span class="badge bg-primary">Unread</span>
                            @else
                                <span class="badge bg-secondary">Read</span>
                            @endif
                        </div>
                        <div class="fw-semibold">{{ $notification->message }}</div>
                        <div class="text-muted small mt-1">{{ optional($notification->created_at)->format('M d, Y h:i A') }}</div>
                    </div>
                    @if($notification->link)
                        <a href="{{ $notification->link }}" class="btn btn-sm btn-outline-primary">Open</a>
                    @endif
                </div>
            @empty
                <div class="text-center py-5 text-muted">No notifications available.</div>
            @endforelse
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-center">
        {{ $notifications->links() }}
    </div>
</div>
@endsection
