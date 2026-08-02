@extends('layouts.admin')

@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-key"></i> Save Your Backup Codes
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>Important!</strong> Save these backup codes in a secure location.
                        Each code can be used once if you lose access to your authenticator app.
                    </div>

                    <h6 class="mb-3">Your Backup Codes</h6>
                    <p class="text-muted small mb-3">
                        Keep these codes safe. You can use them to sign in if you lose access to your authenticator app.
                        Each code can only be used once.
                    </p>

                    <div class="backup-codes-container" style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #28a745;">
                        <div class="row">
                            @foreach (collect($backupCodes)->chunk(5) as $chunk)
                                <div class="col-md-6">
                                    @foreach ($chunk as $code)
                                        <div class="backup-code mb-2">
                                            <code class="user-select-all">{{ $code }}</code>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6>How to Save Your Codes</h6>
                        <ul class="small">
                            <li>
                                <strong>Print:</strong>
                                <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                                    <i class="fas fa-print"></i> Print Codes
                                </button>
                            </li>
                            <li class="mt-2">
                                <strong>Download:</strong>
                                <button class="btn btn-sm btn-outline-primary" onclick="downloadCodes()">
                                    <i class="fas fa-download"></i> Download as Text
                                </button>
                            </li>
                            <li class="mt-2">
                                <strong>Copy:</strong> Copy and save codes to your password manager or secure note-taking app
                            </li>
                        </ul>
                    </div>

                    <div class="alert alert-danger mt-4" role="alert">
                        <h6><i class="fas fa-exclamation-triangle"></i> Security Warning</h6>
                        <ul class="small mb-0">
                            <li>Store these codes in a secure location (NOT on your computer or phone)</li>
                            <li>Don't share these codes with anyone</li>
                            <li>Once you close this page, you won't be able to see these codes again</li>
                            <li>If you lose both your authenticator and backup codes, your account will be locked</li>
                        </ul>
                    </div>

                    <div class="mt-4 text-center">
                        <p class="text-muted small mb-3">
                            Once you've saved these codes, click the button below to complete setup
                        </p>
                        <form method="POST" action="{{ route('totp.confirm-backup-codes') }}">
                            @csrf
                            <input type="hidden" name="guard" value="{{ $guard }}">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-check-circle"></i> I've Saved My Backup Codes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function downloadCodes() {
        const codes = @json($backupCodes);
        const content = `PUPTracker TOTP Backup Codes\n\nGenerated: ${new Date().toLocaleString()}\n\nIMPORTANT: Keep these codes safe and secure!\n\n${codes.join('\n')}\n\nEach code can only be used once to regain access to your account.`;

        const blob = new Blob([content], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'puptracker-backup-codes.txt';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    }
</script>

<style>
    .backup-code {
        padding: 8px 12px;
        background: white;
        border-radius: 4px;
        border: 1px solid #dee2e6;
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .backup-code code {
        color: #495057;
        background: transparent;
        padding: 0;
    }

    @media print {
        .btn, .alert-warning, .mt-4:has(.btn) {
            display: none;
        }

        .backup-codes-container {
            border: 1px solid #333;
            page-break-inside: avoid;
        }
    }
</style>
@endsection
