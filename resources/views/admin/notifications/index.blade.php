@extends('layouts.admin')

@section('title', 'Notifications')

@section('content')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h2 class="mb-1">Admin Notifications</h2>
        <p class="text-muted mb-0">View the latest notifications for administrative actions.</p>
    </div>
    <a href="{{ route('admin.announcements') }}" class="btn btn-outline-secondary">Announcements</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        @if($notifications->count())
            <div class="list-group">
                @foreach($notifications as $notification)
                    <a href="{{ $notification->link ?: '#' }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start {{ $notification->is_read ? '' : 'list-group-item-warning' }}" target="{{ $notification->link ? '_blank' : '_self' }}">
                        <div>
                            <div class="fw-semibold">{{ $notification->message }}</div>
                            <small class="text-muted">{{ optional($notification->created_at)->format('F d, Y h:i A') }}</small>
                        </div>
                        <span class="badge rounded-pill {{ $notification->is_read ? 'bg-secondary' : 'bg-primary' }}">
                            {{ $notification->is_read ? 'Read' : 'Unread' }}
                        </span>
                    </a>
                @endforeach
            </div>

            <div class="mt-4 d-flex justify-content-center">
                {{ $notifications->links() }}
            </div>
        @else
            <div class="text-center py-5">
                <i class="bi bi-bell-slash fs-1 text-muted"></i>
                <h5 class="mt-3">No notifications found.</h5>
                <p class="text-muted">Once notifications are generated, they will appear here.</p>
            </div>
        @endif
    </div>
</div>

@endsection
