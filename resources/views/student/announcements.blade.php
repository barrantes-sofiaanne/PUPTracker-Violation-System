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

                <div class="card portal-card mb-4">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">

                            <div>

                                <h4 class="fw-bold">

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

                            {!! $announcement->content !!}

                        </p>

                        @if($announcement->attachment_path)

                            <hr>

                            <a
                                href="{{ asset($announcement->attachment_path) }}"
                                target="_blank"
                                class="btn btn-outline-primary">

                                <i class="bi bi-paperclip"></i>

                                Download Attachment

                            </a>

                        @endif

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

@endsection