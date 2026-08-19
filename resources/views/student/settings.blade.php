@extends('layouts.student')

@section('title', 'Settings')

@section('content')

<div class="container-fluid py-1">
    <div class="portal-hero mb-4">
        <h2 class="fw-bold mb-1">Account Settings</h2>
        <p class="mb-0">Manage your account security.</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card portal-card">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <i class="bi bi-shield-lock-fill fs-3 text-muted" aria-hidden="true"></i>
                        <div>
                            <h6 class="mb-1">Password managed by the PUP Identity Provider (IDP)</h6>
                            <p class="text-muted mb-0">
                                To change your password, please follow the password update process through the
                                PUP Identity Provider (IDP) website.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection