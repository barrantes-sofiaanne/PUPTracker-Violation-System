@extends('layouts.oss-staff')

@section('title', 'OSS Staff Profile')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@section('content')

@php
    /**
     * OSS Staff profile.
     *
     * The account is an ADMIN account with the OSS Staff role, so the identity
     * comes from `admin_info_tbl` through the Admin model's `adminInfo`
     * relation. No Security profile table is consulted.
     */
    $ossStaffInfo = $admin->adminInfo ?? null;

    $ossStaffFirstName = $ossStaffInfo->firstname ?? '';
    $ossStaffMiddleName = $ossStaffInfo->middlename ?? '';
    $ossStaffLastName = $ossStaffInfo->lastname ?? '';

    $ossStaffFullName = trim($ossStaffFirstName . ' ' . $ossStaffMiddleName . ' ' . $ossStaffLastName)
        ?: ($admin->email ?? 'OSS Staff');

    // The assigned position/designation is optional legacy data; the role name
    // is the authoritative label for this module.
    $ossStaffPosition = $ossStaffInfo->Position
        ?? $ossStaffInfo->position
        ?? $ossStaffInfo->designation
        ?? 'OSS Staff';
@endphp

<div class="container-fluid py-4">
    <div class="profile-page">
        <div class="card profile-card">

            {{-- Profile Hero --}}
            <div class="profile-hero">
                <div class="profile-avatar-lg">
                    {{ strtoupper(mb_substr($ossStaffFirstName ?: 'O', 0, 1)) }}{{ strtoupper(mb_substr($ossStaffLastName ?: '', 0, 1)) }}
                </div>
                <h2 class="profile-hero-name">
                    {{ $ossStaffFullName }}
                </h2>
                <p class="profile-hero-sub">
                    {{ $admin->email }}
                </p>
                <div class="profile-hero-badges">
                    <span class="profile-hero-badge">
                        <i class="bi bi-person-badge" aria-hidden="true"></i>
                        OSS Staff
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
                            {{ $ossStaffFullName }}
                        </div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">
                            <i class="bi bi-envelope" aria-hidden="true"></i> Email
                        </div>
                        <div class="profile-info-value">
                            {{ $admin->email }}
                        </div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">
                            <i class="bi bi-briefcase" aria-hidden="true"></i> Position
                        </div>
                        <div class="profile-info-value">
                            {{ $ossStaffPosition }}
                        </div>
                    </div>

                </div>
            </div>

            {{-- Footer --}}
            <div class="profile-footer">
                <div class="profile-footer-note">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    <span>
                        The system currently keeps OSS Staff profile updates in the backend. Contact your system administrator to request account changes.
                    </span>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('oss-staff.dashboard') }}" class="btn portal-btn-outline">
                        <i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Back to Dashboard
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

@endsection
