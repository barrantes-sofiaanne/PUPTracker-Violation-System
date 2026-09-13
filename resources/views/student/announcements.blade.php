@extends('layouts.student')

@section('title', 'Announcements')

@section('content')

    {{-- Announcements use ONE shared UI (partials/announcement-list,
         partials/announcement-card and partials/announcement-view-modal).
         Student is the source of truth, so this page only supplies its own
         title and subtitle; the markup itself is shared with Security. --}}
    @include('partials.announcement-list', [
        'heroTitle' => 'Student Announcements',
        'heroSubtitle' => 'Stay updated with the latest announcements from the administration.',
    ])

    {{-- Shared announcement detail modal. --}}
    @include('partials.announcement-view-modal')

@endsection
