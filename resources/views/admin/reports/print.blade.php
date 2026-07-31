<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUPT Student Violation Report</title>
    <style>
        :root {
            --brand-red: #8b0000;
            --border-color: #2f2f2f;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            background: #fff;
            font-size: 11px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .page {
            width: 100%;
            max-width: 860px;
            margin: 0 auto;
            padding: 16px 12px 22px;
        }

        .report-header {
            display: grid;
            grid-template-columns: 120px 1fr 120px;
            align-items: center;
            margin-bottom: 14px;
            min-height: 40px;
        }

        .report-logo {
            width: 112px;
        }

        .report-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.1px;
            text-align: center;
            line-height: 1.2;
        }

        .meta {
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 700;
        }

        .filters {
            margin: 0 0 10px;
            font-size: 13px;
            font-weight: 700;
        }

        .student-block {
            margin-bottom: 10px;
            border: 1px solid #8b8b8b;
            page-break-inside: avoid;
        }

        .student-bar {
            background: #efefef;
            border-bottom: 1px solid #8b8b8b;
            padding: 6px 8px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.25;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid var(--border-color);
            padding: 4px 6px;
            vertical-align: top;
            font-size: 11px;
            line-height: 1.25;
        }

        th {
            background: var(--brand-red);
            color: #fff;
            text-transform: uppercase;
            font-size: 10.5px;
            letter-spacing: 0.2px;
        }

        .col-violation { width: 47%; }
        .col-offense { width: 16%; text-align: center; }
        .col-date { width: 23%; text-align: center; }
        .col-status { width: 14%; text-align: center; }

        .text-center {
            text-align: center;
        }

        .remarks {
            margin-top: 2px;
            display: block;
        }

        .footer {
            text-align: center;
            margin-top: 14px;
            font-size: 12px;
            font-style: italic;
            color: #222;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm;
            }

            .page {
                max-width: none;
                padding: 0;
            }

            .student-block {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="report-header">
        <img
            src="{{ asset('assets/images/Tracker-logo.png') }}"
            alt="PUPTracker Logo"
            class="report-logo">
        <h1 class="report-title">PUPT Student Violation Report</h1>
    </div>

    <p class="meta">Report Generated On: {{ $generatedAt ?? now()->format('F d, Y, h:i a') }}</p>
    <p class="filters">Filters Applied: {{ $filtersApplied ?? 'None' }}</p>

    @php
        $groupedReports = $reports
            ->groupBy('student_number')
            ->map(function ($studentReports) {
                $offenseCounter = [];

                return $studentReports
                    ->sortBy('violation_date')
                    ->map(function ($record) use (&$offenseCounter) {
                        $key = (string) $record->violation_type;
                        $offenseCounter[$key] = ($offenseCounter[$key] ?? 0) + 1;

                        return [
                            'record' => $record,
                            'offense_count' => $offenseCounter[$key],
                        ];
                    })
                    ->sortByDesc(fn ($item) => $item['record']->violation_date)
                    ->values();
            });

        $toOrdinal = function (int $count): string {
            if ($count === 1) {
                return '1st Offense';
            }
            if ($count === 2) {
                return '2nd Offense';
            }
            if ($count === 3) {
                return '3rd Offense';
            }

            return $count . 'th Offense';
        };
    @endphp

    @forelse($groupedReports as $studentNumber => $studentRows)
        @php
            $firstRow = $studentRows->first()['record'] ?? null;
            $studentName = $firstRow
                ? trim((string) ($firstRow->student?->last_name ?? '') . ', ' . (string) ($firstRow->student?->first_name ?? '') . ' ' . (string) ($firstRow->student?->middle_name ?? ''))
                : '-';
        @endphp

        <section class="student-block">
            <div class="student-bar">
                Student: {{ $studentName !== ',' ? trim($studentName) : '-' }}
                | Number: {{ $studentNumber ?: '-' }}
            </div>

            <table>
                <thead>
                    <tr>
                        <th class="col-violation">Violation Type &amp; Remarks</th>
                        <th class="col-offense">Offense Level</th>
                        <th class="col-date">Date Recorded</th>
                        <th class="col-status">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($studentRows as $row)
                        @php
                            $record = $row['record'];
                            $offenseLevel = $toOrdinal((int) $row['offense_count']);
                            $status = optional($record->sanction)->disciplinary_sanction ? 'Sanction' : 'Warning';
                        @endphp
                        <tr>
                            <td>
                                {{ optional($record->violationType)->violation_type ?: ($record->violation_type ?: '-') }}
                                @if(!empty($record->description))
                                    <span class="remarks">Remarks: {{ $record->description }}</span>
                                @endif
                            </td>
                            <td class="text-center">{{ $offenseLevel }}</td>
                            <td class="text-center">{{ $record->violation_date ? \Carbon\Carbon::parse($record->violation_date)->format('M d, Y, g:i a') : '-' }}</td>
                            <td class="text-center">{{ $status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @empty
        <p>No matching records found.</p>
    @endforelse

    <div class="footer">Page 1/1</div>
</div>
<script>window.print();</script>
</body>
</html>
