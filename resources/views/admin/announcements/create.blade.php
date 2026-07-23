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

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card shadow-sm">
    <div class="card-body">
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

            <button type="submit" class="btn btn-primary">Publish Announcement</button>
        </form>
    </div>
</div>

@endsection
