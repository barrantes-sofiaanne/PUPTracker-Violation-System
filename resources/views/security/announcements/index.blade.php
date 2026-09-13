@extends('layouts.security')

@section('title', 'Security Announcements')

@section('content')

    {{-- Standardized against the Student Announcements UI, which is the source
         of truth. Same hero, card, spacing, typography, attachment indicator,
         View action and empty state — rendered from the shared partials so the
         two modules cannot drift apart again.

         The Security module keeps its own real title and subtitle, its full
         announcement body plus per-attachment View/Download rows on the card
         (cardVariant = 'security'), and its Back to Dashboard navigation. --}}
    @include('partials.announcement-list', [
        'heroTitle' => 'Security Announcements',
        'heroSubtitle' => 'Review announcements shared by the administration.',
        'cardVariant' => 'security',
        'heroActions' => new \Illuminate\Support\HtmlString(
            '<a href="' . e(route('security.dashboard')) . '" class="announcement-hero-link">'
            . '<i class="bi bi-speedometer2" aria-hidden="true"></i>'
            . '<span>Back to Dashboard</span>'
            . '</a>'
        ),
    ])

    {{-- Shared View Announcement interface. --}}
    @include('partials.announcement-view-modal')

@endsection
