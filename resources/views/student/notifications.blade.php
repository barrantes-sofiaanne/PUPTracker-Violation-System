@extends('layouts.student')

@section('title', 'Notifications')

@push('styles')
@endpush

@section('content')

<div class="container-fluid py-1">

            {{-- Header --}}
            <div class="portal-hero mb-4">

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                    <div>

                        <h2 class="fw-bold mb-1">

                            Notifications

                        </h2>

                        <p class="mb-0">

                            You have

                            <strong>

                                {{ $unreadCount }}

                            </strong>

                            unread notifications.

                        </p>

                    </div>

                </div>

            </div>

            {{-- Notifications --}}

            <div class="card portal-card">

                <div class="card-body p-0">

                    @forelse($notifications as $notification)

                        <a
                            href="{{ $notification->link ?: '#' }}"
                            class="text-decoration-none text-dark">

                            <div class="portal-list-item d-flex justify-content-between align-items-start gap-3">

                                <div>

                                    <div class="mb-2">

                                        @if(!$notification->is_read)

                                            <span class="portal-badge maroon">

                                                Unread

                                            </span>

                                        @else

                                            <span class="portal-badge muted">

                                                Read

                                            </span>

                                        @endif

                                    </div>

                                    <h6 class="mb-2">

                                        {{ $notification->message }}

                                    </h6>

                                    <small class="text-muted">

                                        {{ $notification->created_at->format('M d, Y h:i A') }}

                                    </small>

                                </div>

                                <div>

                                    @switch($notification->notification_type)

                                        @case('announcement')

                                            <i class="bi bi-megaphone-fill fs-4" style="color: var(--portal-maroon);"></i>

                                            @break

                                        @case('violation')

                                            <i class="bi bi-exclamation-triangle-fill fs-4" style="color: var(--portal-maroon);"></i>

                                            @break

                                        @case('sanction')

                                            <i class="bi bi-shield-check fs-4" style="color: var(--portal-goldenrod);"></i>

                                            @break

                                        @default

                                            <i class="bi bi-bell-fill fs-4" style="color: var(--portal-goldenrod);"></i>

                                    @endswitch

                                </div>

                            </div>

                        </a>

                    @empty

                        <div class="text-center py-5">

                            <i class="bi bi-bell-slash fs-1 text-muted"></i>

                            <h5 class="mt-3 text-dark">

                                No Notifications

                            </h5>

                            <p class="text-muted">

                                You're all caught up.

                            </p>

                        </div>

                    @endforelse

                </div>

                @if($notifications->hasPages())

                    <div class="card-footer">

                        {{ $notifications->links() }}

                    </div>

                @endif

            </div>

        </div>

@endsection