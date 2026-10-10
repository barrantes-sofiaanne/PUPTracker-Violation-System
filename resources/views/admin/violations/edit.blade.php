@extends('layouts.admin')

@section('title', 'Correct Violation')

@section('content')

<div class="container-fluid">

    <div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1">Correct Violation</h3>
            <p class="text-muted mb-0">
                Make a controlled correction to an existing violation record.
            </p>
        </div>

        <a
            href="{{ route('admin.violations.index', ['tab' => 'history']) }}"
            class="admin-btn admin-btn-neutral"
        >
            <i class="bi bi-arrow-left"></i>
            Back to History
        </a>
    </div>


    {{-- Warning --}}
    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>

        <div>
            <strong>Correction Notice</strong>

            <div class="small mt-1">
                Corrections are permanently recorded with the administrator,
                date, original values, updated values, and reason.
                Student identity cannot be changed from this form.
            </div>
        </div>
    </div>


    {{-- Validation errors --}}
    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Please correct the following:</strong>

            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif


    <div class="row g-4">

        {{-- CURRENT RECORD --}}
        <div class="col-lg-5">

            <div class="card shadow-sm">

                <div class="card-header bg-white">
                    <h5 class="mb-0 fw-bold">
                        Current Record
                    </h5>
                </div>

                <div class="card-body">

                    {{-- Student --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Student
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="{{ trim(($violation->student?->first_name ?? '') . ' ' . ($violation->student?->last_name ?? '')) }}"
                            readonly
                        >

                        <div class="form-text">
                            Student identity cannot be changed.
                        </div>
                    </div>


                    {{-- Student Number --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Student Number
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="{{ $violation->student_number }}"
                            readonly
                        >
                    </div>


                    {{-- Current Violation --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Current Violation
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="{{ $violation->violationType?->violation_type ?? $violation->violation_type_display }}"
                            readonly
                        >
                    </div>


                    {{-- Current Date --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Current Incident Date
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="{{ $violation->violation_date ? \Carbon\Carbon::parse($violation->violation_date)->format('M d, Y h:i A') : '-' }}"
                            readonly
                        >
                    </div>


                    {{-- Current Description --}}
                    <div class="mb-0">
                        <label class="form-label fw-semibold">
                            Current Description
                        </label>

                        <textarea
                            class="form-control"
                            rows="5"
                            readonly
                        >{{ $violation->description }}</textarea>
                    </div>

                </div>

            </div>

        </div>


        {{-- CORRECTION --}}
        <div class="col-lg-7">

            <form
                method="POST"
                action="{{ route('admin.violations.update', $violation) }}"
            >

                @csrf
                @method('PUT')

                <div class="card shadow-sm">

                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            Correction
                        </h5>
                    </div>

                    <div class="card-body">

                        @if ($hasAssignedSanction)

                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-1"></i>

                                <strong>Sanction already assigned.</strong>

                                <div class="small mt-1">
                                    The violation type and incident date are
                                    locked because changing them could alter
                                    the basis of the existing disciplinary
                                    sanction.
                                </div>
                            </div>

                        @endif


                        {{-- Violation Type --}}
                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Violation Type
                            </label>

                            <select
                                name="violation_type_id"
                                class="form-select"
                                {{ $hasAssignedSanction ? 'disabled' : '' }}
                            >

                                @foreach ($violationTypes as $type)

                                    <option
                                        value="{{ $type->violation_type_id }}"
                                        {{ (int) old(
                                            'violation_type_id',
                                            $violation->violation_type_id
                                        ) === (int) $type->violation_type_id ? 'selected' : '' }}
                                    >
                                        {{ $type->violation_type }}
                                        @if($type->violationCategory)
                                            — {{ $type->violationCategory->category_name }}
                                        @endif
                                    </option>

                                @endforeach

                            </select>

                            @if ($hasAssignedSanction)
                                <input
                                    type="hidden"
                                    name="violation_type_id"
                                    value="{{ $violation->violation_type_id }}"
                                >
                            @endif

                        </div>


                        {{-- Violation Date --}}
                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Incident Date
                            </label>

                            <input
                                type="datetime-local"
                                name="violation_date"
                                class="form-control"
                                value="{{ old(
                                    'violation_date',
                                    $violation->violation_date
                                        ? \Carbon\Carbon::parse($violation->violation_date)->format('Y-m-d\TH:i')
                                        : ''
                                ) }}"
                                {{ $hasAssignedSanction ? 'disabled' : '' }}
                            >

                            @if ($hasAssignedSanction)
                                <input
                                    type="hidden"
                                    name="violation_date"
                                    value="{{ $violation->violation_date
                                        ? \Carbon\Carbon::parse($violation->violation_date)->format('Y-m-d\TH:i')
                                        : '' }}"
                                >
                            @endif

                        </div>


                        {{-- Description --}}
                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Description
                            </label>

                            <textarea
                                name="description"
                                class="form-control"
                                rows="6"
                                maxlength="5000"
                                placeholder="Enter the corrected description..."
                            >{{ old('description', $violation->description) }}</textarea>

                            <div class="form-text">
                                Maximum 5,000 characters.
                            </div>

                        </div>


                        {{-- Reason --}}
                        <div class="mb-4">

                            <label class="form-label fw-semibold">
                                Reason for Correction
                                <span class="text-danger">*</span>
                            </label>

                            <textarea
                                name="reason"
                                class="form-control"
                                rows="5"
                                minlength="10"
                                maxlength="2000"
                                required
                                placeholder="Explain why this violation record needs to be corrected..."
                            >{{ old('reason') }}</textarea>

                            <div class="form-text">
                                Minimum 10 characters. This explanation will
                                be permanently recorded in the correction history.
                            </div>

                        </div>


                        {{-- Confirmation --}}
                        <div class="form-check mb-4">

                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="confirmCorrection"
                                required
                            >

                            <label
                                class="form-check-label"
                                for="confirmCorrection"
                            >
                                I confirm that the correction is accurate and
                                authorized, and I understand that the original
                                values will remain in the correction history.
                            </label>

                        </div>


                        <div class="d-flex justify-content-end gap-2">

                            <a
                                href="{{ route('admin.violations.index', ['tab' => 'history']) }}"
                                class="admin-btn admin-btn-neutral"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="admin-btn admin-btn-primary"
                            >
                                <i class="bi bi-check-circle me-1"></i>
                                Save Correction
                            </button>

                        </div>

                    </div>

                </div>

            </form>

        </div>

    </div>


    {{-- CORRECTION HISTORY --}}
    @if ($violation->editHistories->count())

        <div class="card shadow-sm mt-4">

            <div class="card-header bg-white">

                <h5 class="mb-0 fw-bold">
                    Correction History
                </h5>

            </div>

            <div class="card-body">

                @foreach ($violation->editHistories as $edit)

                    <div class="border rounded p-3 mb-3">

                        <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">

                            <strong>
                                Correction #{{ $edit->id }}
                            </strong>

                            <span class="text-muted small">
                                {{ $edit->created_at?->format('M d, Y h:i A') }}
                            </span>

                        </div>

                        <div class="small mb-2">

                            <strong>Administrator:</strong>

                            {{ $edit->admin?->username ?? 'Administrator #' . $edit->admin_id }}

                        </div>

                        <div class="small">

                            <strong>Reason:</strong>

                            {{ $edit->reason }}

                        </div>

                    </div>

                @endforeach

            </div>

        </div>

    @endif

</div>

@endsection