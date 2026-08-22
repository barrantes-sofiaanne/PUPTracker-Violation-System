@extends('layouts.admin')

@section('title', 'Edit Announcement')

@section('content')

<x-admin.page-header title="Edit Announcement" description="Update the announcement and replace or remove the attachment.">
    <x-slot name="actions">
        <a href="{{ route('admin.announcements') }}" class="admin-btn admin-btn-neutral">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Back to Announcements
        </a>
    </x-slot>
</x-admin.page-header>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form action="{{ route('admin.announcements.update', $announcement) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label for="title" class="form-label">Title</label>
                <input type="text" class="form-control" id="title" name="title" value="{{ old('title', $announcement->title) }}" required>
            </div>

            <div class="mb-4">
                <label for="content" class="form-label">Message</label>
                <textarea class="form-control" id="content" name="content" rows="6" required>{{ old('content', $announcement->content) }}</textarea>
            </div>

            <div class="mb-4">
                <label for="attachment" class="form-label">Attachment</label>
                <input class="form-control" type="file" id="attachment" name="attachment" accept=".jpg,.jpeg,.png,.gif,.pdf">
                <div class="form-text">Upload a new file to replace the current attachment.</div>
            </div>

            @if($announcement->attachment_path)
                <div class="mb-4 p-3 bg-light rounded border">
                    <strong>Current attachment:</strong>
                    <div>
                        <a href="{{ asset($announcement->attachment_path) }}" target="_blank">View current file</a>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="remove_attachment" name="remove_attachment" value="1">
                        <label class="form-check-label" for="remove_attachment">Remove current attachment</label>
                    </div>
                </div>
            @endif

            @if(Auth::guard('admin')->user()?->isItAdministrator())
                <div class="mb-4 form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        value="1"
                        id="show_on_login"
                        name="show_on_login"
                        @checked(old('show_on_login', $announcement->show_on_login))>
                    <label class="form-check-label" for="show_on_login">
                        Show this announcement as a login modal across modules
                    </label>
                </div>
            @endif

            <button type="submit" class="admin-btn admin-btn-primary">
    <i class="bi bi-check2" aria-hidden="true"></i>
    Save Changes
</button>
        </form>
    </div>
</div>

@endsection
