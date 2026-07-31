@extends('layouts.admin')

@section('title', 'Create Announcement')

@section('content')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h2 class="mb-1">Create Announcement</h2>
        <p class="text-muted mb-0">Publish a new announcement with an optional file attachment.</p>
    </div>
    <a href="{{ route('admin.announcements') }}" class="btn btn-outline-secondary">Back to Announcements</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form action="{{ route('admin.announcements.store') }}" method="POST" enctype="multipart/form-data">
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
            @endif

            <button type="submit" class="btn btn-primary">Publish Announcement</button>
        </form>
    </div>
</div>

@endsection
