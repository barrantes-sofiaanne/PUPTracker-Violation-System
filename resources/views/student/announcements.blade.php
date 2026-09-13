@extends('layouts.student')

@section('title', 'Announcements')

@section('content')

    {{-- Student Announcements IS the reference design. Its markup now lives in
         the shared announcement partials so the Security module can reuse the
         exact same UI. --}}
    @include('partials.announcement-list', [
        'heroTitle' => 'Student Announcements',
        'heroSubtitle' => 'Stay updated with the latest announcements from the administration.',
        'cardVariant' => 'student',
    ])

    {{-- Shared View Announcement interface. --}}
    @include('partials.announcement-view-modal')

@endsection
