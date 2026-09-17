@extends('layouts.oss-staff')

@section('title', 'OSS Staff Announcements')

@section('content')

    {{-- Announcements use ONE shared UI (partials/announcement-list,
         partials/announcement-card and partials/announcement-view-modal) —
         exactly the same markup the Student module renders. Nothing about the
         design branches on the module.

         OSS Staff keeps only its legitimate module-specific differences: its
         own title and subtitle, its own navigation (Back to Dashboard), and
         its own authorization/data handling in the controller. --}}
    @include('partials.announcement-list', [
        'heroTitle' => 'OSS Staff Announcements',
        'heroSubtitle' => 'Review announcements shared by the administration.',
        'heroActions' => new \Illuminate\Support\HtmlString(
            '<a href="' . e(route('oss-staff.dashboard')) . '" class="announcement-hero-link">'
            . '<i class="bi bi-speedometer2" aria-hidden="true"></i>'
            . '<span>Back to Dashboard</span>'
            . '</a>'
        ),
    ])

    {{-- Shared announcement detail modal (same UI as Student). --}}
    @include('partials.announcement-view-modal')

@endsection
