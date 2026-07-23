<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Violation Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <h3 class="fw-bold mb-3">Violation Report</h3>
    <p class="text-muted">Generated from the current report filters.</p>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Student</th>
                <th>Course</th>
                <th>Violation</th>
                <th>Category</th>
                <th>Severity</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
        @foreach($reports as $report)
            <tr>
                <td>{{ $report->student?->student_number ?? '-' }}</td>
                <td>{{ optional($report->student?->studentInfo?->program)->program_name ?? '-' }}</td>
                <td>{{ optional($report->violationType)->violation_type ?? '-' }}</td>
                <td>{{ optional($report->violationType?->violationCategory)->category_name ?? '-' }}</td>
                <td>{{ optional($report->violationType)->severity_level ?? '-' }}</td>
                <td>{{ $report->violation_date ? \Carbon\Carbon::parse($report->violation_date)->format('M d, Y') : '-' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<script>window.print();</script>
</body>
</html>
