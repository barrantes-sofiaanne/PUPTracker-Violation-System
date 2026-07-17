@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/student.css') }}">
@endpush

@section('content')

<div class="dashboard-wrapper">

    @include('student.partials.sidebar')

    <div class="main-content">

        @include('student.partials.navbar')

        @yield('student-content')

    </div>

</div>

@endsection

@push('scripts')
<script src="{{ asset('assets/js/student.js') }}"></script>
@endpush