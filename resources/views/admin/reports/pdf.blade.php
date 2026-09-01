<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PUPT Violation Report — PDF</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            background: #fff;
            font-size: 11px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .page { max-width: 860px; margin: 0 auto; padding: 16px 12px 22px; }
        .report-header { display: grid; grid-template-columns: 120px 1fr 120px; align-items: center; margin-bottom: 14px; min-height: 40px; }
        .report-logo { width: 112px; }
        .report-title { font-size: 18px; font-weight: 700; margin: 0; letter-spacing: 0.1px; text-align: center; line-height: 1.2; }
        .meta { margin-bottom: 6px; font-size: 13px; font-weight: 700; }
        .filters { margin: 0 0 12px; font-size: 13px; font-weight: 700; }

        .summary-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
        .summary-card {
            flex: 1 1 22%;
            min-width: 140px;
            border: 1px solid #8b8b8b;
            border-radius: 6px;
            padding: 8px 10px;
            text-align: center;
        }
        .summary-card .label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.2px; color: #555; margin-bottom: 3px; }
        .summary-card .value { font-size: 20px; font-weight: 700; color: #8b0000; }

        .chart-section { margin-bottom: 16px; }
        .chart-title { font-size: 13px; font-weight: 700; margin: 0 0 6px; color: #8b0000; }
        .chart-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .chart-table th, .chart-table td { border: 1px solid #9a9a9a; padding: 4px 6px; font-size: 11px; }
        .chart-table th { background: #efefef; text-align: left; }

        table.records { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.records th, table.records td { border: 1px solid #2f2f2f; padding: 4px 6px; vertical-align: top; font-size: 10px; line-height: 1.25; }
        table.records th { background: #8b0000; color: #fff; text-transform: uppercase; font-size: 9.5px; letter-spacing: 0.2px; }
        .footer { text-align: center; margin-top: 14px; font-size: 11px; font-style: italic; color: #222; }
    </style>
</head>
<body>
<div class="page">
    <div class="report-header">
        <img src="{{ asset('assets/images/Tracker-logo.png') }}" alt="PUPTracker Logo" class="report-logo">
        <h1 class="report-title">PUPT Violation Report</h1>
    </div>

    <p class="meta">Report Generated On: {{ $generatedAt ?? now()->format('F d, Y, h:i a') }}</p>
    <p class="filters">Filters Applied: {{ $filtersApplied ?? 'None' }}</p>

    {{-- Summary Cards --}}
    <div class="summary-grid">
        <div class="summary-card">
            <div class="label">Total Violations</div>
            <div class="value">{{ $statistics['total'] ?? $reports->count() }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Minor</div>
            <div class="value">{{ $statistics['minor'] ?? 0 }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Major</div>
            <div class="value">{{ $statistics['major'] ?? 0 }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Repeat Offenders</div>
            <div class="value">{{ $statistics['repeat_offenders'] ?? 0 }}</div>
        </div>
    </div>

    {{-- Monthly Trend --}}
    @if(!empty($charts['monthly']['labels']))
        <div class="chart-section">
            <h3 class="chart-title">Monthly Violation Trend</h3>
            <table class="chart-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($charts['monthly']['labels'] as $i => $label)
                        <tr>
                            <td>{{ $label }}</td>
                            <td>{{ $charts['monthly']['data'][$i] ?? 0 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Category Distribution --}}
    @if(!empty($charts['categories']['labels']))
        <div class="chart-section">
            <h3 class="chart-title">Category Distribution</h3>
            <table class="chart-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($charts['categories']['labels'] as $i => $label)
                        <tr>
                            <td>{{ $label }}</td>
                            <td>{{ $charts['categories']['data'][$i] ?? 0 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Top Violations --}}
    @if(!empty($charts['topViolations']['labels']))
        <div class="chart-section">
            <h3 class="chart-title">Top 10 Most Common Violations</h3>
            <table class="chart-table">
                <thead>
                    <tr>
                        <th>Violation</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($charts['topViolations']['labels'] as $i => $label)
                        <tr>
                            <td>{{ $label }}</td>
                            <td>{{ $charts['topViolations']['data'][$i] ?? 0 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Records Table --}}
    <h3 class="chart-title">Violation Records</h3>
    <table class="records">
        <thead>
            <tr>
                <th style="width: 12%;">Student #</th>
                <th style="width: 18%;">Student Name</th>
                <th style="width: 14%;">Course</th>
                <th style="width: 20%;">Violation</th>
                <th style="width: 12%;">Category</th>
                <th style="width: 10%;">Date</th>
                <th style="width: 14%;">Recorded By</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reports as $report)
                <tr>
                    <td>{{ $report->student?->student_number ?? '-' }}</td>
                    <td>{{ trim((string) ($report->student?->last_name ?? '') . ', ' . (string) ($report->student?->first_name ?? '')) }}</td>
                    <td>{{ optional($report->student?->studentInfo?->program)->program_name ?? '-' }}</td>
                    <td>{{ optional($report->violationType)->violation_type ?: ($report->violation_type ?: '-') }}</td>
                    <td>{{ optional($report->violationType?->violationCategory)->category_name ?? '-' }}</td>
                    <td>{{ $report->violation_date ? \Carbon\Carbon::parse($report->violation_date)->format('M d, Y') : '-' }}</td>
                    <td>{{ $report->recorded_by_display ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center;">No violation records match the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">This report was generated by the PUPTracker Violation System. For official university use only.</p>
</div>
</body>
</html>
