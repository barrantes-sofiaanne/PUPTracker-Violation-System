@extends('layouts.student')

@section('title', 'Announcements')

@push('styles')
@endpush

@section('content')

<div class="container-fluid py-1">

            {{-- Page Header --}}
            <div class="portal-hero mb-4">

                <h2 class="fw-bold mb-1">

                        Student Announcements

                </h2>

                <p class="mb-0">

                        Stay updated with the latest announcements from the administration.

                    </p>

                </div>

            </div>

            @forelse($announcements as $announcement)

                @php
                    // Payload for the shared "View Announcement" interface.
                    // Photos point at the authorized attachment endpoint, which
                    // streams inline from the private disk after re-checking
                    // authorization server-side — no public URLs are used.
                    $attachmentItems = $announcement->attachments->map(function ($file) {
                        return [
                            'name' => $file->original_filename,
                            'ext' => $file->is_image ? 'image' : strtoupper((string) \Illuminate\Support\Str::after((string) $file->mime_type, '/')),
                            'size' => $file->human_size,
                            'date' => optional($file->created_at)->format('F d, Y'),
                            'view' => route('announcements.attachments.show', $file),
                            'download' => route('announcements.attachments.download', $file),
                            'is_image' => (bool) $file->is_image,
                        ];
                    })->values()->all();

                    $hasLegacyAttachment = (bool) $announcement->attachment_path;
                @endphp

                <div class="card portal-card mb-4 w-100">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">

                            <div>

                                <h4 class="fw-bold mb-1">

                                    {{ $announcement->title }}

                                </h4>

                                <small class="text-muted">

                                    Posted

                                    {{ $announcement->created_at->format('F d, Y h:i A') }}

                                </small>

                            </div>

                            <i class="bi bi-megaphone-fill fs-2" style="color: var(--portal-goldenrod);"></i>

                        </div>

                        <hr class="border-secondary-subtle">

                        <p style="white-space: pre-line" class="mb-0 text-dark">
                            {{ \Illuminate\Support\Str::limit(strip_tags($announcement->content), 180) }}
                        </p>

                        @if(! empty($attachmentItems))
                            <span class="badge bg-secondary mt-3">
                                <i class="bi bi-paperclip"></i>
                                {{ count($attachmentItems) }} attachment(s)
                            </span>
                        @endif

                        {{-- Standardized View action (Security Module design). --}}
                        <div class="d-flex justify-content-end mt-3">
                            <button type="button"
                                class="btn btn-sm btn-outline-primary announcement-view-trigger"
                                data-announcement-title="{{ $announcement->title }}"
                                data-announcement-posted="{{ $announcement->created_at->format('F d, Y h:i A') }}"
                                data-announcement-content-html="{{ json_encode($announcement->rendered_content) }}"
                                data-announcement-content-text="{{ e($announcement->content) }}"
                                data-announcement-attachments="{{ json_encode($attachmentItems) }}"
                                @if($hasLegacyAttachment)
                                data-announcement-legacy="{{ asset($announcement->attachment_path) }}"
                                data-announcement-legacy-name="{{ basename($announcement->attachment_path) }}"
                                @endif>
                                <i class="bi bi-eye me-1"></i>View
                            </button>
                        </div>

                    </div>

                </div>

            @empty

                <div class="card portal-card">

                    <div class="card-body text-center py-5">

                        <i class="bi bi-megaphone fs-1" style="color: var(--portal-goldenrod);"></i>

                        <h4 class="mt-3">

                            No announcements available.

                        </h4>

                    </div>

                </div>

            @endforelse

            @if($announcements->hasPages())

                <div class="mt-4">

                    {{ $announcements->links() }}

                </div>

            @endif

        </div>

{{-- Shared View Announcement interface (same UI as the Security Module). --}}
@include('partials.announcement-view-modal')

@endsection