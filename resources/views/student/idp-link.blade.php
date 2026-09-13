@extends('layouts.auth-portal', ['title' => 'Connect Your PUPTracker Account', 'portal' => 'student'])

@section('auth-content')

        <p class="module-chip">Student Module</p>

        <h2>Connect Your PUPTracker Account</h2>

        <p class="subtitle">
            Your PUP IDP account has been successfully authenticated. To connect it to your existing PUPTracker
            student record, enter your student number.
        </p>

        @if (session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
        @endif

        @if($errors->has('login'))
            <div class="login-error">
                <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                <span>{{ $errors->first('login') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('student.idp.link.post') }}" autocomplete="off" class="auth-login-form" id="connectAccountForm">
            @csrf

            <div class="mb-3">
                <label class="form-label" for="student_number">Student Number</label>
                <div class="input-group login-input-group">
                    <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                    <input
                        id="student_number"
                        type="text"
                        name="student_number"
                        class="form-control @error('student_number') is-invalid @enderror"
                        value="{{ old('student_number') }}"
                        placeholder="e.g. 2026-00001"
                        autocomplete="off"
                        required>
                </div>
                @error('student_number')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <button type="button" class="btn btn-success w-100 login-btn" id="continueBtn">
                Continue
            </button>

            <p class="security-note">
                <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                Your PUP IDP account is verified securely on the server. You are only connecting it to the
                PUPTracker student record that belongs to you.
            </p>
        </form>

        <div class="footer-links">
            <a href="{{ route('student.login') }}">&larr; Back to Login</a>
        </div>

        {{-- Confirm Student Number modal --}}
        <div class="modal fade auth-modal" id="confirmStudentNumberModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-person-check me-2"></i>Confirm Student Number</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">You entered:</p>
                        <p class="fs-4 fw-bold text-center" id="confirmStudentNumberDisplay">—</p>
                        <p class="text-muted text-center mb-0">Is this your correct student number?</p>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Go Back</button>
                        <button type="button" class="btn portal-btn" id="confirmStudentNumberBtn">
                            <i class="bi bi-check-circle me-1"></i>Yes, Continue
                        </button>
                    </div>
                </div>
            </div>
        </div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('connectAccountForm');
    const input = document.getElementById('student_number');
    const continueBtn = document.getElementById('continueBtn');
    const modalEl = document.getElementById('confirmStudentNumberModal');
    if (!form || !input || !continueBtn || !modalEl) {
        return;
    }

    // The auth portal uses isolation:isolate + backdrop-filter on its shell,
    // which traps nested Bootstrap modals inside a low stacking context (the
    // body-level .modal-backdrop ends up covering the dialog). Relocating the
    // modal to <body> lets Bootstrap stack it correctly (dialog above backdrop).
    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }

    const modal = new bootstrap.Modal(modalEl);
    const display = document.getElementById('confirmStudentNumberDisplay');
    const confirmBtn = document.getElementById('confirmStudentNumberBtn');

    continueBtn.addEventListener('click', function () {
        const value = input.value.trim();
        if (!value) {
            input.focus();
            return;
        }
        display.textContent = value;
        modal.show();
    });

    confirmBtn.addEventListener('click', function () {
        modal.hide();
        form.submit();
    });
});
</script>
@endpush
