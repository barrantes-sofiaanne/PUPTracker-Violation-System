@extends('layouts.student')

@section('title', 'Student Profile')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@section('content')

<div class="container-fluid py-4">
    <div class="profile-page">
        <div class="card profile-card">

            {{-- Profile Hero --}}
            <div class="profile-hero">
                <div class="profile-avatar-lg">
                    {{ strtoupper(mb_substr($user->first_name ?? 'S', 0, 1)) }}{{ strtoupper(mb_substr($user->last_name ?? '', 0, 1)) }}
                </div>
                <h2 class="profile-hero-name">
                    {{ $user->first_name }} {{ $user->last_name }}
                </h2>
                <p class="profile-hero-sub">
                    Student ID: {{ $user->student_number }}
                </p>
                <div class="profile-hero-badges">
                    <span class="profile-hero-badge">
                        <i class="bi bi-mortarboard-fill" aria-hidden="true"></i>
                        {{ optional(optional($user->studentInfo)->program)->program_name ?? 'N/A' }}
                    </span>
                    <span class="profile-hero-badge">
                        <i class="bi bi-calendar-event" aria-hidden="true"></i>
                        Year {{ optional(optional($user->studentInfo)->year)->year ?? 'N/A' }}
                    </span>
                    <span class="profile-hero-badge">
                        <i class="bi bi-diagram-3" aria-hidden="true"></i>
                        Section {{ optional(optional($user->studentInfo)->section)->section_name ?? 'N/A' }}
                    </span>
                </div>
            </div>

            {{-- Profile Info --}}
            <div class="profile-body">
                <div class="profile-info-grid">

                    <div class="profile-info-item">
                        <div class="profile-info-label">
                            <i class="bi bi-person-badge" aria-hidden="true"></i> Student Number
                        </div>
                        <div class="profile-info-value">
                            {{ $user->student_number }}
                        </div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">
                            <i class="bi bi-person" aria-hidden="true"></i> Full Name
                        </div>
                        <div class="profile-info-value">
                            {{ $user->first_name }}
                            @if($user->middle_name)
                                {{ $user->middle_name }}
                            @endif
                            {{ $user->last_name }}
                        </div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">
                            <i class="bi bi-envelope" aria-hidden="true"></i> Email
                        </div>
                        <div class="profile-info-value">
                            {{ $user->email }}
                        </div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">
                            <i class="bi bi-book" aria-hidden="true"></i> Course
                        </div>
                        <div class="profile-info-value">
                            {{ optional(optional($user->studentInfo)->program)->program_name ?? 'N/A' }}
                        </div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">
                            <i class="bi bi-calendar-event" aria-hidden="true"></i> Year Level
                        </div>
                        <div class="profile-info-value">
                            {{ optional(optional($user->studentInfo)->year)->year ?? 'N/A' }}
                        </div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">
                            <i class="bi bi-diagram-3" aria-hidden="true"></i> Section
                        </div>
                        <div class="profile-info-value">
                            {{ optional(optional($user->studentInfo)->section)->section_name ?? 'N/A' }}
                        </div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">
                            <i class="bi bi-person-circle" aria-hidden="true"></i> Gender
                        </div>
                        <div class="profile-info-value">
                            {{ optional($user->gender)->gender_name ?? 'N/A' }}
                        </div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">
                            <i class="bi bi-check-circle" aria-hidden="true"></i> Status
                        </div>
                        <div class="profile-info-value">
                            @if(optional($user->status)->status_name === 'Active')
                                <span class="profile-status-active">
                                    <i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>
                                    {{ optional($user->status)->status_name }}
                                </span>
                            @else
                                <span class="profile-status-inactive">
                                    <i class="bi bi-x-circle-fill me-1" aria-hidden="true"></i>
                                    {{ optional($user->status)->status_name ?? 'Unknown' }}
                                </span>
                            @endif
                        </div>
                    </div>

                </div>
            </div>

            {{-- Footer --}}
            <div class="profile-footer">
                <div class="profile-footer-note">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    <span>
                        Only the Administrator or IT Administrator can change student information. Please contact the administrator for any profile updates.
                    </span>
                </div>
                <a href="{{ route('student.dashboard') }}" class="btn portal-btn-outline">
                    <i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Back to Dashboard
                </a>
            </div>

        </div>
    </div>
</div>

@endsection
