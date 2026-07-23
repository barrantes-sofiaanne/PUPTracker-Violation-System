@extends('layouts.security')

@section('title', 'Security Notifications')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="mb-1">Security Notifications</h2>
            <p class="mb-0">Monitor the latest notices and follow-up actions.</p>
        </div>
        <a href="{{ route('security.dashboard') }}" class="btn portal-btn-outline">Back to Dashboard</a>
    </div>

    <div class="card portal-card">
        <div class="card-body p-0">
            @forelse($notifications as $notification)
                <div class="portal-list-item d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="mb-2">
                            @if(! $notification->is_read)
                                <span class="portal-badge maroon">Unread</span>
                            @else
                                <span class="portal-badge muted">Read</span>
                            @endif
                        </div>
                        <div class="fw-semibold">{{ $notification->message }}</div>
                        <div class="small mt-1" style="color: #67585c;">{{ optional($notification->created_at)->format('M d, Y h:i A') }}</div>
                    </div>
                    @if($notification->link)
                        <a href="{{ $notification->link }}" class="btn btn-sm portal-btn-outline">Open</a>
                    @endif
                </div>
            @empty
                <div class="text-center py-5" style="color: #67585c;">No notifications available.</div>
            @endforelse
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-center">
        {{ $notifications->links() }}
    </div>
</div>
@endsection
