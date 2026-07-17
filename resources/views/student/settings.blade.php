@extends('layouts.app')

@section('title', 'Settings')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/student.css') }}">
@endpush

@section('content')

<div class="dashboard-wrapper">


    <main class="main-content">


        <div class="container-fluid py-4">

            {{-- Page Header --}}
            <div class="card shadow-sm border-0 mb-4">

                <div class="card-body">

                    <h2 class="fw-bold mb-1">

                        Account Settings

                    </h2>

                    <p class="text-muted mb-0">

                        Manage your account security.

                    </p>

                </div>

            </div>

            <div class="row">

                {{-- Change Password --}}

                <div class="col-lg-8">

                    <div class="card shadow-sm border-0">

                        <div class="card-header">

                            <h5 class="mb-0">

                                Change Password

                            </h5>

                        </div>

                        <div class="card-body">
@if(session('success'))

<div class="alert alert-success">

    {{ session('success') }}

</div>

@endif
@if($errors->any())

<div class="alert alert-danger">

    <ul class="mb-0">

        @foreach($errors->all() as $error)

            <li>{{ $error }}</li>

        @endforeach

    </ul>

</div>

@endif
                            <form action="{{ route('student.change-password') }}" method="POST">

    @csrf

                                <div class="mb-3">
    <label class="form-label">Current Password</label>

    <input
        type="password"
        name="current_password"
        class="form-control"
        required>
</div>

<div class="mb-3">
    <label class="form-label">New Password</label>

    <input
        type="password"
        name="new_password"
        class="form-control"
        required>
</div>

<div class="mb-4">
    <label class="form-label">Confirm Password</label>

    <input
        type="password"
        name="new_password_confirmation"
        class="form-control"
        required>
</div>

                        </div>

                    </div>

                </div>

                {{-- Account Info --}}

                <div class="col-lg-4">

                    <div class="card shadow-sm border-0">

                        <div class="card-header">

                            <h5 class="mb-0">

                                Security Tips

                            </h5>

                        </div>

                        <div class="card-body">

                            <ul class="mb-0">

                                <li>
                                    Use at least 8 characters.
                                </li>

                                <li>
                                    Include uppercase letters.
                                </li>

                                <li>
                                    Include lowercase letters.
                                </li>

                                <li>
                                    Include numbers.
                                </li>

                                <li>
                                    Include special characters.
                                </li>

                            </ul>
<div class="text-end mt-4">
    <button type="submit" class="btn btn-danger">
        <i class="bi bi-key-fill"></i>
        Change Password
    </button>
</div>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

@endsection