@extends('layouts.app')

@section('title', 'Student Dashboard')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/student.css') }}">
@endpush

@section('content')

<div class="container-fluid py-4">

    {{-- Welcome --}}
    <div class="card shadow-sm border-0 mb-4">

        <div class="card-body">

            <h2 class="fw-bold">
                Welcome,
                {{ $user->first_name }}
                {{ $user->last_name }}
            </h2>

            <p class="text-muted mb-0">
                Student Number:
                <strong>{{ $user->student_number }}</strong>
            </p>

        </div>

    </div>

    {{-- Summary --}}
    <div class="row mb-4">

        <div class="col-md-4">

            <div class="card border-0 shadow-sm">

                <div class="card-body text-center">

                    <h6 class="text-muted">
                        Total Violations
                    </h6>

                    <h1 class="display-4 fw-bold text-danger">
                        {{ $totalViolations }}
                    </h1>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card border-0 shadow-sm">

                <div class="card-body text-center">

                    <h6 class="text-muted">
                        Unread Notifications
                    </h6>

                    <h1 class="display-4 fw-bold text-primary">
                        {{ $notificationCount }}
                    </h1>

                </div>

            </div>

        </div>

    </div>

    {{-- Recent Violations --}}
    <div class="card shadow-sm border-0 mb-4">

        <div class="card-header bg-white">

            <h5 class="mb-0">
                Recent Violations
            </h5>

        </div>

        <div class="card-body p-0">

            <table class="table table-hover mb-0">

                <thead>

                    <tr>

                        <th>Date</th>
                        <th>Violation</th>
                        <th>Category</th>
                        <th>Description</th>

                    </tr>

                </thead>

                <tbody>

                    @forelse($recentViolations as $violation)

                        <tr>

                            <td>

                                {{ \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y') }}

                            </td>

                            <td>

                                {{ $violation->violationType->violation_type ?? '-' }}

                            </td>

                            <td>

                                {{ $violation->violationType->violationCategory->category_name ?? '-' }}

                            </td>

                            <td>

                                {{ $violation->description }}

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="4" class="text-center py-4">

                                No violation records found.

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

    {{-- Notifications --}}
    <div class="card shadow-sm border-0 mb-4">

        <div class="card-header bg-white">

            <h5 class="mb-0">
                Latest Notifications
            </h5>

        </div>

        <div class="list-group list-group-flush">

            @forelse($notifications as $notification)

                <a
                    href="{{ $notification->link ?: '#' }}"
                    class="list-group-item list-group-item-action">

                    <div class="fw-semibold">

                        {{ $notification->message }}

                    </div>

                    <small class="text-muted">

                        {{ $notification->created_at->format('M d, Y h:i A') }}

                    </small>

                </a>

            @empty

                <div class="list-group-item text-center text-muted">

                    No notifications found.

                </div>

            @endforelse

        </div>

    </div>

    {{-- Student Handbook --}}
    <div class="card shadow-sm border-0">

        <div class="card-header bg-white">

            <h5 class="mb-0">
                Student Handbook
            </h5>

        </div>

        <div class="card-body">

            <div class="accordion" id="handbookAccordion">

                @foreach($categories as $category)

                    <div class="accordion-item">

                        <h2 class="accordion-header">

                            <button
                                class="accordion-button collapsed"
                                data-bs-toggle="collapse"
                                data-bs-target="#category{{ $category->violation_category_id }}">

                                {{ $category->category_name }}

                            </button>

                        </h2>

                        <div
                            id="category{{ $category->violation_category_id }}"
                            class="accordion-collapse collapse"
                            data-bs-parent="#handbookAccordion">

                            <div class="accordion-body">

                                @foreach($category->violationTypes as $type)

                                    <div class="mb-4">

                                        <h6 class="fw-bold">

                                            {{ $type->violation_type }}

                                        </h6>

                                        <p class="text-muted">

                                            {{ $type->violation_description }}

                                        </p>

                                        <table class="table table-sm">

                                            <thead>

                                                <tr>

                                                    <th>Offense</th>
                                                    <th>Sanction</th>

                                                </tr>

                                            </thead>

                                            <tbody>

                                                @foreach($type->disciplinarySanctions as $sanction)

                                                    <tr>

                                                        <td>

                                                            {{ $sanction->offense_level }}

                                                        </td>

                                                        <td>

                                                            {{ $sanction->disciplinary_sanction }}

                                                        </td>

                                                    </tr>

                                                @endforeach

                                            </tbody>

                                        </table>

                                    </div>

                                @endforeach

                            </div>

                        </div>

                    </div>

                @endforeach

            </div>

        </div>

    </div>

</div>

@endsection

@push('scripts')
<script src="{{ asset('js/student.js') }}"></script>
@endpush