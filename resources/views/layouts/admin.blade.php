<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title')</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/images/SystemLogo_w/name.png') }}">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <link rel="stylesheet"
        href="{{ asset('css/admin.css') }}">

    <link rel="stylesheet"
        href="{{ asset('css/admin-components.css') }}">

<link rel="stylesheet"
    href="{{ asset('css/user-menu.css') }}">

<link rel="stylesheet"
    href="{{ asset('css/inactivity-timeout.css') }}">

<link rel="stylesheet"
    href="{{ asset('css/portal.css') }}">
<link rel="stylesheet" href="{{ asset('css/module-dashboard-ui.css') }}">
    @stack('styles')

</head>

<body
    data-inactivity-timeout-minutes="{{ config('auth.inactivity_timeout_minutes', 15) }}"
    data-inactivity-warning-seconds="{{ config('auth.inactivity_warning_seconds', 10) }}"
    data-session-activity-url="{{ route('session.activity') }}"
>
<div class="admin-wrapper module-admin">

    @include('admin.partials.sidebar')

    <main class="main-content">

        @include('admin.partials.navbar')

        <div class="container-fluid py-4">

            @yield('content')

        </div>

    </main>

</div>

<div id="sidebarBackdrop" aria-hidden="true"></div>

<x-admin.toast />

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="{{ asset('js/admin.js') }}"></script>
<script src="{{ asset('js/admin-ui.js') }}"></script>
<script src="{{ asset('js/inactivity-timeout.js') }}"></script>
<script src="{{ asset('js/logout.js') }}"></script>

@include('partials.sweetalert')
@include('partials.privacy-consent-modal')
@include('partials.login-announcement-modal')
@include('partials.inactivity-timeout-modal')

@stack('scripts')

</body>

</html>