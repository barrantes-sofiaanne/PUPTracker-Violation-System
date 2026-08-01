<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(config('app.env') === 'production')
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    @endif

    <title>@yield('title', 'PUPTracker Violation System')</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
          rel="stylesheet">

    <!-- Global CSS -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    <!-- Page CSS -->
    @stack('styles')
</head>

<body>
    

    @yield('content')

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Global JS -->
    <script src="{{ asset('js/app.js') }}"></script>

    @include('partials.sweetalert')

    <!-- Page JS -->
    @stack('scripts')

</body>
</html>