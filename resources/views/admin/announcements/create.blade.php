@extends('layouts.admin')

@section('title', 'Create Announcement')

@section('content')

<x-admin.page-header title="Create Announcement" description="Publish a new announcement with an optional file attachment.">
    <x-slot name="actions">
        <a href="{{ route('admin.announcements') }}" class="admin-btn admin-btn-neutral">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Back to Announcements
        </a>
    </x-slot>
</x-admin.page-header>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form action="{{ route('admin.announcements.store') }}" method="POST" enctype="multipart/form-data" data-submit-guard="Publishing...">
            @csrf

            <div class="mb-4">
                <label for="title" class="form-label">Title</label>
                <input type="text" class="form-control" id="title" name="title" value="{{ old('title') }}" required>
            </div>

            <div class="mb-4">
                <label for="content" class="form-label">Message</label>
                <textarea class="form-control" id="content" name="content" rows="6" required>{{ old('content') }}</textarea>
            </div>

            <div class="mb-4">
                <label for="attachment" class="form-label">Attachment</label>
                <input class="form-control" type="file" id="attachment" name="attachment" accept=".jpg,.jpeg,.png,.gif,.pdf">
                <div class="form-text">Supported formats: JPG, PNG, GIF, PDF. Maximum size 5MB.</div>
            </div>

            @if(Auth::guard('admin')->user()?->isItAdministrator())
                <div class="mb-4 form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        value="1"
                        id="show_on_login"
                        name="show_on_login"
                        @checked(old('show_on_login'))>
                    <label class="form-check-label" for="show_on_login">
                        Show this announcement as a login modal across modules
                    </label>
                </div>

                <div class="mb-4 {{ old('show_on_login') ? '' : 'd-none' }}" id="loginModalDurationWrap">
                    <label class="form-label">Duration shown as login modal</label>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <select class="form-select" id="login_modal_duration" name="login_modal_duration">
                                <option value="1_week" {{ old('login_modal_duration') === '1_week' ? 'selected' : '' }}>1 Week</option>
                                <option value="1_month" {{ old('login_modal_duration') === '1_month' ? 'selected' : '' }}>1 Month</option>
                                <option value="custom" {{ old('login_modal_duration') === 'custom' ? 'selected' : '' }}>Custom Date</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-none" id="loginModalCustomDateWrap">
                            <input
                                type="date"
                                class="form-control"
                                id="login_modal_expires_at"
                                name="login_modal_expires_at"
                                value="{{ old('login_modal_expires_at') }}"
                                min="{{ now()->toDateString() }}">
                            <div class="form-text">Select the date until this login modal is shown.</div>
                        </div>
                    </div>
                </div>
            @endif

            <button type="submit" class="admin-btn admin-btn-primary">
    <i class="bi bi-megaphone" aria-hidden="true"></i>
    Publish Announcement
</button>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkbox = document.getElementById('show_on_login');
    const wrap = document.getElementById('loginModalDurationWrap');
    const duration = document.getElementById('login_modal_duration');
    const customWrap = document.getElementById('loginModalCustomDateWrap');

    if (!checkbox || !wrap) {
        return;
    }

    function toggleDuration() {
        wrap.classList.toggle('d-none', !checkbox.checked);
    }

    function toggleCustom() {
        if (!customWrap) { return; }
        customWrap.classList.toggle('d-none', !duration || duration.value !== 'custom');
    }

    checkbox.addEventListener('change', toggleDuration);
    if (duration) { duration.addEventListener('change', toggleCustom); }

    toggleDuration();
    toggleCustom();
});
</script>
@endpush
