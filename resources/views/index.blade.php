@extends('layouts.app')

@section('title', 'PUPTracker Violation System')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/landing.css') }}">
@endpush

@section('content')

<div class="landing-page">


    <div class="container d-flex justify-content-center align-items-center min-vh-100">

        <div class="landing-card">

            <div class="landing-logo-wrap">
                <img
                    src="{{ asset('assets/images/Tracker-logo.png') }}"
                    class="logo"
                    alt="PUPTracker Logo">
            </div>

            <p class="system-pill">Polytechnic University of the Philippines</p>

            <h1>PUPTracker</h1>

            <h5>Violation Management System</h5>

            <p class="description">
                Official portal for managing campus violations,
                disciplinary records, and announcements.
            </p>

            <div class="highlights">
                <span><i class="bi bi-shield-check"></i> Secure Access</span>
                <span><i class="bi bi-clipboard2-check"></i> Organized Records</span>
                <span><i class="bi bi-graph-up-arrow"></i> Faster Tracking</span>
            </div>

            <div class="portal-buttons">

                <a href="{{ route('student.login') }}" class="portal-btn">
                    <i class="bi bi-mortarboard-fill"></i>
                    Student Portal
                </a>

                <a href="{{ route('security.login') }}" class="portal-btn">
                    <i class="bi bi-shield-lock-fill"></i>
                    Security Portal
                </a>

                <a href="{{ route('admin.login') }}" class="portal-btn">
                    <i class="bi bi-person-workspace"></i>
                    Administrator
                </a>

            </div>

            <div class="footer-links">

                <a href="https://www.pup.edu.ph/privacy/" target="_blank">
                    Privacy Statement
                </a>

                <span>•</span>

                <a href="https://www.pup.edu.ph/terms/" target="_blank">
                    Terms of Use
                </a>

            </div>

        </div>

    </div>

</div>

@endsection