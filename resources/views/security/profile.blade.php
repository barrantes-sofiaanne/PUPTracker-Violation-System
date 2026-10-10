@extends('layouts.security')

@section('title', 'Security Profile')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@section('content')

@php
    $securityInfo = $security->securityInfo;

    $securityFirstName = $securityInfo?->firstname ?? '';
    $securityMiddleName = $securityInfo?->middlename ?? '';
    $securityLastName = $securityInfo?->lastname ?? '';

    $securityFullName = trim(
        $securityFirstName . ' ' .
        $securityMiddleName . ' ' .
        $securityLastName
    ) ?: ($security->email ?? 'Security');

    $securityPosition = 'Security Personnel';
@endphp

<div class="container-fluid py-4">
    <div class="profile-page">
        <div class="card profile-card">

            {{-- Profile Hero --}}
            <div class="profile-hero">
                <div class="profile-avatar-lg">
                    {{ strtoupper(mb_substr($securityFirstName ?: 'S', 0, 1)) }}{{ strtoupper(mb_substr($securityLastName ?: '', 0, 1)) }}
                </div>
                <h2 class="profile-hero-name">
                    {{ $securityFullName }}
                </h2>
                <p class="profile-hero-sub">
                    {{ $security->email }}
                </p>
                <div class="profile-hero-badges">
                    <span class="profile-hero-badge">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        {{ $securityPosition }}
                    </span>
                </div>
            </div>

            {{-- Profile Info --}}
            <div class="profile-body">
                <div class="profile-info-grid">

                    <div class="profile-info-item">
                        <div class="profile-info-label">
                            <i class="bi bi-person" aria-hidden="true"></i> Full Name
                        </div>
                        <div class="profile-info-value">
                            {{ $securityFullName }}
                        </div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">
                            <i class="bi bi-envelope" aria-hidden="true"></i> Email
                        </div>
                        <div class="profile-info-value">
                            {{ $security->email }}
                        </div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">
                            <i class="bi bi-briefcase" aria-hidden="true"></i> Position
                        </div>
                        <div class="profile-info-value">
                            {{ $securityPosition }}
                        </div>
                    </div>

                </div>
            </div>

            {{-- Footer --}}
            <div class="profile-footer">
                <div class="profile-footer-note">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    <span>
                        Only the Administrator or IT Administrator can change security personnel information. Please contact the administrator for any profile updates.
                    </span>
                </div>
                <a href="{{ route('security.dashboard') }}" class="btn portal-btn-outline">
                    <i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Back to Dashboard
                </a>
            </div>

        </div>
    </div>
</div>

@endsection
