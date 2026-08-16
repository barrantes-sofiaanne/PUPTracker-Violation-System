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
                            <h5 class="mb-0">Account Security</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-3">
                                <i class="bi bi-shield-lock-fill fs-3 text-muted" aria-hidden="true"></i>
                                <div>
                                    <h6 class="mb-1">Password managed by the PUP Identity Provider</h6>
                                    <p class="text-muted mb-0">
                                        Your password is managed by the PUP Identity Provider (IDP). To change your
                                        password, use the PUP account password management options available through your
                                        PUP Identity Provider account.
                                    </p>
                                </div>
                            </div>
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
                                <li>Keep your PUP account password private.</li>
                                <li>Use a strong, unique password.</li>
                                <li>Never share your password with others.</li>
                                <li>Sign in securely through the PUP Identity Provider.</li>
                                <li>Contact the administrator if you need account assistance.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</div>

@endsection