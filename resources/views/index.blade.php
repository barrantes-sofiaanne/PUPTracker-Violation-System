@extends('layouts.auth-portal', ['title' => 'PUPTracker Violation System', 'portal' => 'access'])

@section('auth-content')
    <p class="module-chip">Access Portal</p>
    <h2>Select a Portal</h2>
    <p class="subtitle">Choose where you want to continue.</p>

    <div class="portal-buttons">
        <a href="{{ route('student.login') }}" class="portal-btn">
            <span class="portal-icon"><i class="bi bi-mortarboard-fill"></i></span>
            <span class="portal-copy">
                <strong>Student Portal</strong>
                <small>View your records and updates</small>
            </span>
            <i class="bi bi-arrow-up-right portal-arrow" aria-hidden="true"></i>
        </a>

        <a href="{{ route('security.login') }}" class="portal-btn">
            <span class="portal-icon"><i class="bi bi-shield-fill-check"></i></span>
            <span class="portal-copy">
                <strong>Security Portal</strong>
                <small>Manage campus conduct reports</small>
            </span>
            <i class="bi bi-arrow-up-right portal-arrow" aria-hidden="true"></i>
        </a>

        <a href="{{ route('admin.login') }}" class="portal-btn">
            <span class="portal-icon"><i class="bi bi-gear-fill"></i></span>
            <span class="portal-copy">
                <strong>Administrator</strong>
                <small>Oversee records and system activity</small>
            </span>
            <i class="bi bi-arrow-up-right portal-arrow" aria-hidden="true"></i>
        </a>
    </div>
@endsection
