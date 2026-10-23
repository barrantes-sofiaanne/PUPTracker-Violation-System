{{-- Use the correct portal layout for the authenticated guard. --}}
@extends(($guard ?? 'admin') === 'security' ? 'layouts.security' : 'layouts.admin')

@section('content')
<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-key" aria-hidden="true"></i> Save Your Backup Codes</h5>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                        <strong>Important!</strong> Save these backup codes in a secure location.
                        Each code can be used once if you lose access to your authenticator app.
                    </div>

                    <h6 class="mb-3">Your Backup Codes</h6>
                    <p class="text-muted mb-3">
                        Keep these codes safe. You can use them to sign in if you lose access to your authenticator app.
                        Each code can only be used once.
                    </p>

                    <div class="backup-codes-container">
                        <div class="row g-2">
                            @foreach (collect($backupCodes)->chunk(5) as $chunk)
                                <div class="col-12 col-md-6">
                                    @foreach ($chunk as $code)
                                        <div class="backup-code mb-2"><code class="user-select-all">{{ $code }}</code></div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6>How to Save Your Codes</h6>
                        <ul class="small ps-3">
                            <li class="mb-2">
                                <strong>Print:</strong>
                                <button type="button" class="btn btn-sm btn-outline-primary ms-1" onclick="window.print()">
                                    <i class="fas fa-print" aria-hidden="true"></i> Print Codes
                                </button>
                            </li>
                            <li class="mb-2">
                                <strong>Download:</strong>
                                <button type="button" class="btn btn-sm btn-outline-primary ms-1" onclick="downloadCodes()">
                                    <i class="fas fa-download" aria-hidden="true"></i> Download as Text
                                </button>
                            </li>
                            <li><strong>Copy:</strong> Copy and save codes to your password manager or secure note-taking app.</li>
                        </ul>
                    </div>

                    <div class="alert alert-danger mt-4" role="alert">
                        <h6><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> Security Warning</h6>
                        <ul class="small mb-0 ps-3">
                            <li>Store these codes in a secure location.</li>
                            <li>Do not share these codes with anyone.</li>
                            <li>Once you leave this page, you may not be able to view these codes again.</li>
                            <li>If you lose both your authenticator and backup codes, you may need administrator assistance to regain access.</li>
                        </ul>
                    </div>

                    <div class="mt-4 text-center">
                        <p class="text-muted small mb-3">Once you've saved these codes, confirm below to complete setup.</p>
                        <form method="POST" action="{{ route('totp.confirm-backup-codes') }}">
                            @csrf
                            <input type="hidden" name="guard" value="{{ $guard }}">
                            <button type="submit" class="btn btn-success btn-lg w-100 w-sm-auto">
                                <i class="fas fa-check-circle" aria-hidden="true"></i> I've Saved My Backup Codes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="backup-codes-data" value="{{ json_encode(array_values($backupCodes), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}">

<script>
    function downloadCodes() {
        const codesInput = document.getElementById('backup-codes-data');
        const codes = JSON.parse(codesInput ? codesInput.value : '[]');
        const content = `PUPTracker TOTP Backup Codes\n\nGenerated: ${new Date().toLocaleString()}\n\nIMPORTANT: Keep these codes safe and secure!\n\n${codes.join('\n')}\n\nEach code can only be used once to regain access to your account.`;
        const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'puptracker-backup-codes.txt';
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    }
</script>

<style>
    .backup-codes-container {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        border-left: 4px solid #28a745;
    }
    .backup-code {
        padding: 8px 12px;
        background: #fff;
        border-radius: 4px;
        border: 1px solid #dee2e6;
        font-family: 'Courier New', monospace;
        font-size: .9rem;
        font-weight: 500;
        overflow-wrap: anywhere;
    }
    .backup-code code {
        color: #495057;
        background: transparent;
        padding: 0;
    }
    @media (max-width: 575.98px) {
        .backup-codes-container { padding: 12px; }
        .btn-lg { white-space: normal; }
    }
    @media print {
        .btn, .alert-warning, form { display: none !important; }
        .backup-codes-container { border: 1px solid #333; page-break-inside: avoid; }
    }
</style>
@endsection
