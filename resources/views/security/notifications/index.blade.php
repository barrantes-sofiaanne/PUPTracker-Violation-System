@extends('layouts.security')

@section('title', 'Security Notifications')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="mb-1">Security Notifications</h2>
            <p class="mb-0">You have <strong>{{ $unreadCount }}</strong> unread notification(s). Monitor confirmations and updates on the violations you recorded.</p>
        </div>
        <a href="{{ route('security.dashboard') }}" class="btn portal-btn-outline">Back to Dashboard</a>
    </div>

    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card portal-card">
        <div class="card-body p-0">
            @forelse($notifications as $notification)
                <div class="portal-list-item d-flex justify-content-between align-items-start gap-3 border-bottom">
                    <div class="d-flex align-items-start gap-3 w-100">
                        <div class="pt-1">
                            @if($notification->notification_type === 'violation')
                                <i class="bi bi-exclamation-triangle-fill fs-4" style="color: #7a0f1e;"></i>
                            @else
                                <i class="bi bi-bell-fill fs-4" style="color: #c9981f;"></i>
                            @endif
                        </div>
                        <div class="w-100">
                            <div class="mb-2">
                                @if(! $notification->is_read)
                                    <span class="portal-badge maroon">Unread</span>
                                @else
                                    <span class="portal-badge muted">Read</span>
                                @endif
                            </div>
                            @if($notification->title)
                                <div class="fw-bold mb-1">{{ $notification->title }}</div>
                            @endif
                            <div>{{ $notification->message }}</div>
                            <div class="small mt-1" style="color: #67585c;">
                                {{ optional($notification->created_at)->format('M d, Y h:i A') }}
                            </div>
                        </div>
                    </div>
                    @if($notification->target_type)
                        <a href="{{ route('security.notifications.open', $notification) }}" class="btn btn-sm portal-btn-outline flex-shrink-0">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Open
                        </a>
                    @elseif($notification->link)
                        <a href="{{ $notification->link }}" class="btn btn-sm portal-btn-outline flex-shrink-0">Open</a>
                    @endif
                </div>
            @empty
                <div class="text-center py-5" style="color: #67585c;">
                    <i class="bi bi-bell-slash fs-1 d-block mb-3"></i>
                    No notifications available.
                </div>
            @endforelse
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-center">
        {{ $notifications->links() }}
    </div>
</div>
@endsection
