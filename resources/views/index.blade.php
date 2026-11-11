@extends('layouts.auth-portal', [
    'title'  => 'Choose Your PUPTracker Portal',
    'portal' => 'access',
])

@section('ap-content')
    <div class="ap-select">

        <h2 class="ap-select-title">Choose Your Portal</h2>
        <p class="ap-select-sub">Select the portal you will log in to.</p>
        <span class="ap-select-rule" aria-hidden="true"></span>

        <nav class="ap-portal-grid" aria-label="Choose a portal">

            <a href="{{ route('student.login') }}"
               class="ap-portal-card ap-portal-card--student"
               data-portal-card
               data-portal-theme="student">
                <span class="ap-portal-icon" aria-hidden="true">
                    <i class="bi bi-mortarboard-fill"></i>
                </span>
                <strong class="ap-portal-name">Student Portal</strong>
                <span class="ap-portal-desc">View records and announcements.</span>
                <span class="ap-portal-arrow" aria-hidden="true">
                    <i class="bi bi-arrow-right"></i>
                </span>
            </a>

            <a href="{{ route('security.login') }}"
               class="ap-portal-card ap-portal-card--security"
               data-portal-card
               data-portal-theme="security">
                <span class="ap-portal-icon" aria-hidden="true">
                    <i class="bi bi-shield-fill-check"></i>
                </span>
                <strong class="ap-portal-name">Security Portal</strong>
                <span class="ap-portal-desc">Record violations and evidence.</span>
                <span class="ap-portal-arrow" aria-hidden="true">
                    <i class="bi bi-arrow-right"></i>
                </span>
            </a>

            <a href="{{ route('security.login', ['portal' => 'oss']) }}"
               class="ap-portal-card ap-portal-card--oss"
               data-portal-card
               data-portal-theme="oss">
                <span class="ap-portal-icon" aria-hidden="true">
                    <i class="bi bi-people-fill"></i>
                </span>
                <strong class="ap-portal-name">OSS Staff Portal</strong>
                <span class="ap-portal-desc">Review submissions and records.</span>
                <span class="ap-portal-arrow" aria-hidden="true">
                    <i class="bi bi-arrow-right"></i>
                </span>
            </a>

            <a href="{{ route('admin.login') }}"
               class="ap-portal-card ap-portal-card--admin"
               data-portal-card
               data-portal-theme="admin">
                <span class="ap-portal-icon" aria-hidden="true">
                    <i class="bi bi-gear-fill"></i>
                </span>
                <strong class="ap-portal-name">Administrator Portal</strong>
                <span class="ap-portal-desc">Manage users and settings.</span>
                <span class="ap-portal-arrow" aria-hidden="true">
                    <i class="bi bi-arrow-right"></i>
                </span>
            </a>

        </nav>
    </div>
@endsection
