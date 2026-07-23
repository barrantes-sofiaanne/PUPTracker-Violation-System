@extends('layouts.admin')

@section('title', 'Announcement Details')

@section('content')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h2 class="mb-1">Announcement Details</h2>
        <p class="text-muted mb-0">Review the announcement content and attachments.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.announcements') }}" class="btn btn-outline-secondary">Back to Announcements</a>
        <a href="{{ route('admin.announcements.edit', $announcement) }}" class="btn btn-primary">Edit Announcement</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="mb-4">
            <h4 class="fw-bold">{{ $announcement->title }}</h4>
            <div class="text-muted">
                Posted on {{ optional($announcement->created_at)->format('F d, Y h:i A') }}
                @if(optional($announcement->adminInfo)->firstname)
                    &middot; by {{ optional($announcement->adminInfo)->firstname }} {{ optional($announcement->adminInfo)->lastname }}
                @endif
            </div>
        </div>

        <div class="mb-4">
            <p style="white-space: pre-line;">{{ $announcement->content }}</p>
        </div>

        @if($announcement->attachment_path)
            <div class="border rounded p-3 bg-light">
                <strong>Attachment:</strong>
                <div>
                    <a href="{{ asset($announcement->attachment_path) }}" target="_blank">Download or view file</a>
                </div>
            </div>
        @endif
    </div>
</div>

@endsection
