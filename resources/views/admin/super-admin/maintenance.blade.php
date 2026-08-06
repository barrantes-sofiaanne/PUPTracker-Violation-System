@extends('layouts.admin')

@section('title', 'Super Admin Maintenance Configuration')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h2 class="mb-1">Maintenance Configuration</h2>
        <p class="text-muted mb-0">Control application availability during maintenance windows.</p>
    </div>
    <a href="{{ route('admin.super-admin.dashboard') }}" class="btn btn-outline-secondary">Back to Super Admin</a>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="mb-4">
            <h6 class="text-muted mb-2">Current Status</h6>
            <span class="badge {{ $maintenanceStatus ? 'bg-danger' : 'bg-success' }} px-3 py-2">
                {{ $maintenanceStatus ? 'Maintenance Mode Enabled' : 'Maintenance Mode Disabled' }}
            </span>
        </div>

        <form id="maintenanceModeForm" action="{{ route('admin.super-admin.maintenance.update') }}" method="POST" class="d-flex flex-wrap gap-2">
            @csrf
            <button
                name="action"
                value="enable"
                type="submit"
                class="btn btn-danger"
                @disabled($maintenanceStatus)
            >
                Enable Maintenance Mode
            </button>

            <button
                name="action"
                value="disable"
                type="submit"
                class="btn btn-success"
                @disabled(!$maintenanceStatus)
            >
                Disable Maintenance Mode
            </button>
        </form>

        <div id="maintenanceBypassPanel" class="alert alert-warning mt-4 mb-0 d-none" role="status">
            <h6 class="alert-heading">Maintenance bypass key</h6>
            <p class="mb-2">Save this key securely. It grants access during maintenance mode.</p>
            <div class="input-group">
                <input id="maintenanceBypassKey" type="text" class="form-control font-monospace" readonly>
                <button id="copyMaintenanceBypassKey" type="button" class="btn btn-outline-secondary">Copy</button>
            </div>
            <a id="maintenanceBypassLink" class="btn btn-primary btn-sm mt-3" href="#">Continue during maintenance</a>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <h6 class="mb-2">Latest Maintenance Action</h6>
        @if($latestMaintenanceLog)
            <p class="mb-1"><strong>{{ $latestMaintenanceLog->action }}</strong></p>
            <p class="text-muted mb-0">
                {{ $latestMaintenanceLog->description }}
                ({{ optional($latestMaintenanceLog->created_at)->format('M d, Y h:i A') }})
            </p>
        @else
            <p class="text-muted mb-0">No maintenance actions have been recorded yet.</p>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('maintenanceModeForm');
        const panel = document.getElementById('maintenanceBypassPanel');
        const key = document.getElementById('maintenanceBypassKey');
        const copyButton = document.getElementById('copyMaintenanceBypassKey');
        const bypassLink = document.getElementById('maintenanceBypassLink');

        if (!form || !panel || !key || !copyButton || !bypassLink) {
            return;
        }

        form.addEventListener('submit', async function (event) {
            const submitter = event.submitter;

            if (!submitter || submitter.value !== 'enable') {
                return;
            }

            event.preventDefault();
            submitter.disabled = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: new FormData(form),
                });
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.message || 'Unable to enable maintenance mode.');
                }

                key.value = payload.secret;
                bypassLink.href = payload.bypass_url;
                panel.classList.remove('d-none');
                submitter.closest('form').querySelectorAll('button').forEach(function (button) {
                    button.disabled = true;
                });
            } catch (error) {
                submitter.disabled = false;
                window.Swal
                    ? Swal.fire({ icon: 'error', title: 'Maintenance mode', text: error.message })
                    : window.alert(error.message);
            }
        });

        copyButton.addEventListener('click', async function () {
            try {
                await navigator.clipboard.writeText(key.value);
                copyButton.textContent = 'Copied';
            } catch (error) {
                key.select();
                document.execCommand('copy');
                copyButton.textContent = 'Copied';
            }
        });
    });
</script>
@endpush
