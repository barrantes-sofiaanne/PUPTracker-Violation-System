@extends('layouts.admin')

@section('title', 'Audit and Risk Plan')

@push('styles')
<style>
    .plan-table thead th {
        background: #224d73;
        color: #fff;
        border-color: #1a3a56;
        vertical-align: middle;
    }

    .plan-table td,
    .plan-table th {
        border: 1px solid #2f2f2f;
        vertical-align: top;
    }

    .status-pill {
        display: inline-block;
        padding: 0.3rem 0.6rem;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .status-completed {
        background: rgba(25, 135, 84, 0.15);
        color: #0f5132;
        border: 1px solid rgba(25, 135, 84, 0.25);
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="portal-hero mb-4">
        <h2 class="fw-bold mb-2">Audit, Control Plan, and Risk Assessment</h2>
        <p class="mb-0">In-system implementation tracker based on corrective actions and treatment recommendations.</p>
    </div>

    <div class="card portal-card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Corrective Action Plan</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 plan-table">
                    <thead>
                        <tr>
                            <th style="width: 7%;">Finding No.</th>
                            <th style="width: 25%;">Corrective Action</th>
                            <th style="width: 17%;">Person Responsible</th>
                            <th style="width: 17%;">Required Resources</th>
                            <th style="width: 12%;">Target Date</th>
                            <th style="width: 10%;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($correctivePlan as $item)
                            <tr>
                                <td>{{ $item['finding_no'] }}</td>
                                <td>{{ $item['corrective_action'] }}</td>
                                <td>{{ $item['owner'] }}</td>
                                <td>{{ $item['resources'] }}</td>
                                <td>{{ $item['target_date'] }}</td>
                                <td>
                                    <span class="status-pill status-completed">{{ $item['status'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card portal-card">
        <div class="card-header bg-white">
            <h5 class="mb-0">Risk Treatment Plan</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 plan-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">No.</th>
                            <th style="width: 17%;">Risk / Finding Reference</th>
                            <th style="width: 22%;">Recommended Treatment / Action</th>
                            <th style="width: 13%;">Control Owner</th>
                            <th style="width: 14%;">Resources Needed</th>
                            <th style="width: 10%;">Target Date</th>
                            <th style="width: 9%;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($treatmentPlan as $item)
                            <tr>
                                <td>{{ $item['no'] }}</td>
                                <td>{{ $item['risk_reference'] }}</td>
                                <td>{{ $item['treatment'] }}</td>
                                <td>{{ $item['owner'] }}</td>
                                <td>{{ $item['resources'] }}</td>
                                <td>{{ $item['target_date'] }}</td>
                                <td>
                                    <span class="status-pill status-completed">{{ $item['status'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
