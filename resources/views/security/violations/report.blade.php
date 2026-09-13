<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUPT Student Violation Report</title>
    {{--
        This template is rendered to PDF by mPDF (Security -> Violations ->
        Generate Report). It therefore must be fully SELF-CONTAINED: mPDF tries
        to fetch every remote/absolute URL while writing the document, so a
        remote stylesheet or an HTTP logo makes the request hang (the page
        appears to "just load") whenever the server cannot reach that host.
        The Bootstrap CDN link and the window.print() script were removed for
        that reason, and the logo is embedded from disk below.
    --}}
    <style>
        /* REGRESSION GUARD (do not "tidy" this back to `size: A4`):
           mPDF v8.2.0 mis-parses the `A4` page-size KEYWORD inside an `@page`
           rule and re-paginates the document thousands of times -- a report
           with 10 records was being emitted as 1,845 pages. The page size is
           already applied via the mPDF constructor ('format' => 'A4'), so this
           rule only has to carry the margins; the size is repeated here as an
           explicit millimetre pair (identical geometry to A4) which mPDF
           handles correctly. */
        @page {
            size: 210mm 297mm;
            margin: 12mm;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #222;
            background: #fff;
            font-size: 11px;
        }

        .report-header {
            text-align: center;
            margin-bottom: 10px;
        }

        .report-header img {
            width: 42px;
            height: 42px;
            object-fit: contain;
            display: block;
            margin: 0 auto 6px;
        }

        .report-title {
            font-size: 20px;
            font-weight: 800;
            margin: 0;
        }

        .meta-line {
            margin-bottom: 8px;
            font-size: 11px;
        }

        .student-block {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }

        .student-line {
            background: #e9e9e9;
            border: 1px solid #777;
            font-weight: 700;
            padding: 6px 8px;
        }

        table.report-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.report-table th,
        table.report-table td {
            border: 1px solid #333;
            padding: 4px 6px;
            vertical-align: top;
        }

        table.report-table thead th {
            background: #8b0000;
            color: #fff;
            text-align: center;
            font-size: 10px;
        }

        .column-violation { width: 28%; }
        .column-offense { width: 14%; text-align: center; }
        .column-date { width: 20%; text-align: center; }
        .column-status { width: 14%; text-align: center; }
        .column-remarks { width: 24%; }

        .remarks {
            margin-top: 2px;
            font-style: italic;
        }

        .footer-note {
            text-align: center;
            font-style: italic;
            margin-top: 12px;
        }

        /* Replacement for the two Bootstrap utility classes that were used in
           the body, so the layout is byte-for-byte identical without the CDN. */
        .report-shell {
            width: 100%;
            padding: 0;
        }

        .report-empty {
            text-align: center;
            padding: 48px 0;
        }
    </style>
</head>
<body>
<div class="report-shell">
    <div class="report-header">
        {{-- Embedded from disk (base64) rather than asset(), so mPDF never has
             to make an HTTP request to render the report. --}}
        @php
            $logoPath = public_path('assets/images/Tracker-logo.png');
            $logoData = is_file($logoPath)
                ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
                : null;
        @endphp

        @if($logoData)
            <img src="{{ $logoData }}" alt="Tracker logo">
        @endif
        <h1 class="report-title">PUPT Student Violation Report</h1>
    </div>

    <div class="meta-line">
        <div><strong>Report Generated On:</strong> {{ $generatedAt->format('F j, Y, h:i a') }}</div>
        <div><strong>Reported By:</strong> {{ $reportedBy ?? 'Security Account' }}</div>
        <div><strong>Filters Applied:</strong> {{ $filtersApplied ?: 'None' }}</div>
    </div>

    @forelse($reportStudents as $studentGroup)
        @php($student = $studentGroup->student)
        <div class="student-block">
            <div class="student-line">
                Student: {{ trim(($student?->last_name ?? 'Unknown') . ', ' . ($student?->first_name ?? '')) }}
                | Number: {{ $student?->student_number ?? 'N/A' }}
                | Program: {{ optional(optional($student?->studentInfo)->program)->program_name ?? 'N/A' }}
                | Year: {{ optional(optional($student?->studentInfo)->year)->year ?? 'N/A' }}
                | Section: {{ optional(optional($student?->studentInfo)->section)->section_name ?? 'N/A' }}
            </div>

            <table class="report-table">
                <thead>
                    <tr>
                        <th class="column-violation">VIOLATION TYPE</th>
                        <th class="column-status">CATEGORY</th>
                        <th class="column-offense">OFFENSE LEVEL</th>
                        <th class="column-status">SANCTION</th>
                        <th class="column-date">DATE RECORDED</th>
                        <th class="column-status">STATUS</th>
                        <th class="column-remarks">DESCRIPTION / REMARKS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($studentGroup->records as $record)
                        <tr>
                            <td class="column-violation">
                                {{ $record->type }}
                            </td>
                            <td class="column-status">{{ $record->category }}</td>
                            <td class="column-offense">{{ $record->offense_level }}</td>
                            <td class="column-status">{{ $record->sanction }}</td>
                            <td class="column-date">
                                {{ $record->date_recorded ? \Carbon\Carbon::parse($record->date_recorded)->format('F j, Y, h:i:s a') : '-' }}
                            </td>
                            <td class="column-status">{{ $record->status }}</td>
                            <td class="column-remarks">{{ $record->remarks }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div class="report-empty">No violations available.</div>
    @endforelse

    <div class="footer-note">Page 1/1</div>
</div>
</body>
</html>