@extends('layouts.admin')

@section('title', 'Admin Profile')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@section('content')

@php
    $adminFirstName = optional($admin->adminInfo)->firstname ?? '';
    $adminLastName = optional($admin->adminInfo)->lastname ?? '';
    $adminFullName = trim($adminFirstName . ' ' . $adminLastName)
        ?: ($admin->name ?? ($admin->username ?? 'Administrator'));
    $adminPosition = optional($admin->adminInfo)->Position
        ?? optional($admin->adminInfo)->position
        ?? optional($admin->adminInfo)->designation
        ?? 'Administrator';
    $adminUsername = $admin->username ?? '—';
@endphp

<div class="container-fluid py-4">
    <div class="profile-page">
        <div class="card profile-card">

            {{-- Profile Hero --}}
            <div class="profile-hero">
                <div class="profile-avatar-lg">
                    {{ strtoupper(mb_substr($adminFirstName ?: 'A', 0, 1)) }}{{ strtoupper(mb_substr($adminLastName ?: '', 0, 1)) }}
                </div>
                <h2 class="profile-hero-name">
                    {{ $adminFullName }}
                </h2>
                <p class="profile-hero-sub">
                    {{ $admin->email }}
                </p>
                <div class="profile-hero-badges">
                    <span class="profile-hero-badge">
                        <i class="bi bi-person-gear" aria-hidden="true"></i>
                        {{ $adminPosition }}
                    </span>
                    <span class="profile-hero-badge">
                        <i class="bi bi-person-badge" aria-hidden="true"></i>
                        {{ $adminUsername }}
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
                            {{ $adminFullName }}
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
                            <i class="bi bi-person-badge" aria-hidden="true"></i> Username
                        </div>
                        <div class="profile-info-value">
                            {{ $adminUsername }}
                        </div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">
                            <i class="bi bi-briefcase" aria-hidden="true"></i> Position
                        </div>
                        <div class="profile-info-value">
                            {{ $adminPosition }}
                        </div>
                    </div>

                </div>
            </div>

            {{-- Footer --}}
            <div class="profile-footer">
                <div class="profile-footer-note">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    <span>
                        The system currently keeps administrator profile updates in the backend. Contact your system administrator to request account changes.
                    </span>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('admin.dashboard') }}" class="btn portal-btn-outline">
                        <i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Back to Dashboard
                    </a>
                    <a href="{{ route('admin.settings') }}" class="btn portal-btn">
                        <i class="bi bi-gear me-2" aria-hidden="true"></i>Settings
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

@endsection
