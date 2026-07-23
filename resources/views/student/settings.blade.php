@extends('layouts.student')

@section('title', 'Settings')

@section('content')

<div class="container-fluid py-1">
            <div class="portal-hero mb-4">
                <h2 class="fw-bold mb-1">Account Settings</h2>
                <p class="mb-0">Manage your account security.</p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card portal-card">
                        <div class="card-header">
                            <h5 class="mb-0">Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('student.change-password') }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-control" required>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Confirm Password</label>
                                    <input type="password" name="new_password_confirmation" class="form-control" required>
                                </div>

                                <div class="text-end">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-key-fill"></i>
                                        Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card portal-card">
                        <div class="card-header">
                            <h5 class="mb-0">Security Tips</h5>
                        </div>
                        <div class="card-body">
                            <ul class="mb-0">
                                <li>Use at least 8 characters.</li>
                                <li>Include uppercase letters.</li>
                                <li>Include lowercase letters.</li>
                                <li>Include numbers.</li>
                                <li>Include special characters.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</div>

@endsection