<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title')</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <link rel="stylesheet"
        href="{{ asset('css/admin.css') }}">

    @stack('styles')

</head>

<body>

<div class="admin-wrapper">

    @include('admin.partials.sidebar')

    <main class="main-content">

        @include('admin.partials.navbar')

        <div class="container-fluid py-4">

            @yield('content')

        </div>

    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

<script src="{{ asset('js/admin.js') }}"></script>

@stack('scripts')

</body>

</html>