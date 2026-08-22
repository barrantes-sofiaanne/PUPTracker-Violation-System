@extends('layouts.admin')

@section('title', 'Announcement Details')

@section('content')

@php
    $currentAdmin = Auth::guard('admin')->user();
    $canManage = (bool) $currentAdmin?->isItAdministrator()
        || (int) $announcement->admin_id === (int) $currentAdmin?->getKey();
@endphp

<x-admin.page-header title="Announcement Details" description="Review the announcement content and attachments.">
    <x-slot name="actions">
        <a href="{{ route('admin.announcements') }}" class="admin-btn admin-btn-neutral">Back to Announcements</a>
        @if($canManage)
            <a href="{{ route('admin.announcements.edit', $announcement) }}" class="admin-btn admin-btn-primary">Edit Announcement</a>
        @endif
    </x-slot>
</x-admin.page-header>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
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
