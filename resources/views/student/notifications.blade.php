@extends('layouts.app')

@section('title', 'Notifications')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/student.css') }}">
@endpush

@section('content')

<div class="dashboard-wrapper">


    <main class="main-content">


        <div class="container-fluid py-4">

            {{-- Header --}}
            <div class="card shadow-sm border-0 mb-4">

                <div class="card-body d-flex justify-content-between align-items-center">

                    <div>

                        <h2 class="fw-bold mb-1">

                            Notifications

                        </h2>

                        <p class="text-muted mb-0">

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

            <div class="card shadow-sm border-0">

                <div class="card-body p-0">

                    @forelse($notifications as $notification)

                        <a
                            href="{{ $notification->link ?: '#' }}"
                            class="text-decoration-none text-dark">

                            <div
                                class="border-bottom p-4 d-flex justify-content-between align-items-start">

                                <div>

                                    <div class="mb-2">

                                        @if(!$notification->is_read)

                                            <span class="badge bg-primary">

                                                Unread

                                            </span>

                                        @else

                                            <span class="badge bg-secondary">

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

                                            <i class="bi bi-megaphone-fill fs-4 text-primary"></i>

                                            @break

                                        @case('violation')

                                            <i class="bi bi-exclamation-triangle-fill fs-4 text-danger"></i>

                                            @break

                                        @case('sanction')

                                            <i class="bi bi-shield-check fs-4 text-success"></i>

                                            @break

                                        @default

                                            <i class="bi bi-bell-fill fs-4 text-warning"></i>

                                    @endswitch

                                </div>

                            </div>

                        </a>

                    @empty

                        <div class="text-center py-5">

                            <i class="bi bi-bell-slash fs-1 text-muted"></i>

                            <h5 class="mt-3">

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

    </main>

</div>

@endsection