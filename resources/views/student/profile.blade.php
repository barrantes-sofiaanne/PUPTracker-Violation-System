@extends('layouts.app')

@section('title', 'Student Profile')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/student.css') }}">
<style>
    .profile-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 15px 15px 0 0;
        text-align: center;
    }
    
    .profile-image {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 5px solid white;
        margin-bottom: 1rem;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    .profile-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
        padding: 2rem;
    }
    
    .info-card {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 10px;
        border-left: 4px solid #667eea;
    }
    
    .info-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.25rem;
    }
    
    .info-value {
        font-size: 1.1rem;
        font-weight: 600;
        color: #212529;
    }
    
    .badge-status {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }
    
    .status-active {
        background: #d4edda;
        color: #155724;
    }
    
    .status-inactive {
        background: #f8d7da;
        color: #721c24;
    }
</style>
@endpush

@section('content')

<div class="container-fluid py-4">

    <div class="card shadow-sm border-0">

        {{-- Profile Header --}}
        <div class="profile-header">
            <img
                src="{{ asset('assets/images/default-profile.png') }}"
                alt="Student Profile"
                class="profile-image">
            <h2 class="fw-bold mb-1">
                {{ $user->first_name }} {{ $user->last_name }}
            </h2>
            <p class="mb-0 opacity-75">
                Student ID: {{ $user->student_number }}
            </p>
        </div>

        {{-- Profile Info --}}
        <div class="profile-info-grid">

            {{-- Student Number --}}
            <div class="info-card">
                <div class="info-label">
                    <i class="bi bi-person-badge"></i> Student Number
                </div>
                <div class="info-value">
                    {{ $user->student_number }}
                </div>
            </div>

            {{-- Full Name --}}
            <div class="info-card">
                <div class="info-label">
                    <i class="bi bi-person"></i> Full Name
                </div>
                <div class="info-value">
                    {{ $user->first_name }} 
                    @if($user->middle_name)
                        {{ $user->middle_name }}
                    @endif
                    {{ $user->last_name }}
                </div>
            </div>

            {{-- Email --}}
            <div class="info-card">
                <div class="info-label">
                    <i class="bi bi-envelope"></i> Email
                </div>
                <div class="info-value">
                    {{ $user->email }}
                </div>
            </div>

            {{-- Course --}}
            <div class="info-card">
                <div class="info-label">
                    <i class="bi bi-book"></i> Course
                </div>
                <div class="info-value">
                    {{ optional($user->course)->course_name ?? 'N/A' }}
                </div>
            </div>

            {{-- Year --}}
            <div class="info-card">
                <div class="info-label">
                    <i class="bi bi-calendar-event"></i> Year Level
                </div>
                <div class="info-value">
                    {{ $user->year->year ?? 'N/A' }}
                </div>
            </div>

            {{-- Section --}}
            <div class="info-card">
                <div class="info-label">
                    <i class="bi bi-diagram-3"></i> Section
                </div>
                <div class="info-value">
                    {{ optional($user->section)->section_name ?? 'N/A' }}
                </div>
            </div>

            {{-- Gender --}}
            <div class="info-card">
                <div class="info-label">
                    <i class="bi bi-person-circle"></i> Gender
                </div>
                <div class="info-value">
                    {{ optional($user->gender)->gender_name ?? 'N/A' }}
                </div>
            </div>

            {{-- Status --}}
            <div class="info-card">
                <div class="info-label">
                    <i class="bi bi-check-circle"></i> Status
                </div>
                <div class="info-value">
                    @if(optional($user->status)->status_name === 'Active')
                        <span class="badge-status status-active">
                            {{ optional($user->status)->status_name }}
                        </span>
                    @else
                        <span class="badge-status status-inactive">
                            {{ optional($user->status)->status_name ?? 'Unknown' }}
                        </span>
                    @endif
                </div>
            </div>

        </div>

        {{-- Action Buttons --}}
        <div style="padding: 2rem; border-top: 1px solid #dee2e6;">
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('student.settings') }}" class="btn btn-primary">
                    <i class="bi bi-pencil me-2"></i>Edit Profile
                </a>
                <a href="{{ route('student.dashboard') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

    </div>

</div>

@endsection