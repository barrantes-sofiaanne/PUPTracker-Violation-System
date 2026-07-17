@extends('layouts.app')

@section('title', 'Announcements')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/student.css') }}">
@endpush

@section('content')

<div class="dashboard-wrapper">


    <main class="main-content">


        <div class="container-fluid py-4">

            {{-- Page Header --}}
            <div class="card shadow-sm border-0 mb-4">

                <div class="card-body">

                    <h2 class="fw-bold mb-1">

                        Student Announcements

                    </h2>

                    <p class="text-muted mb-0">

                        Stay updated with the latest announcements from the administration.

                    </p>

                </div>

            </div>

            @forelse($announcements as $announcement)

                <div class="card shadow-sm border-0 mb-4">

                    <div class="card-body">

                        <div class="d-flex justify-content-between">

                            <div>

                                <h4 class="fw-bold">

                                    {{ $announcement->title }}

                                </h4>

                                <small class="text-muted">

                                    Posted

                                    {{ $announcement->created_at->format('F d, Y h:i A') }}

                                </small>

                            </div>

                            <i class="bi bi-megaphone-fill text-primary fs-2"></i>

                        </div>

                        <hr>

                        <p style="white-space: pre-line">

                            {{!! $announcement->content !!}

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

                <div class="card shadow-sm">

                    <div class="card-body text-center py-5">

                        <i class="bi bi-megaphone fs-1 text-muted"></i>

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

    </main>

</div>

@endsection