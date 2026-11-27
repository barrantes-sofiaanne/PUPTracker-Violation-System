@forelse($reports as $report)
    <tr>
        <td>
            <strong>{{ $report->student?->student_number ?? '-' }}</strong>
            <br>
            <small>{{ trim(($report->student?->last_name ?? '') . ', ' . ($report->student?->first_name ?? '')) }}</small>
        </td>
        <td>{{ optional($report->student?->studentInfo?->program)->program_name ?? '-' }}</td>
        <td>{{ optional($report->violationType)->violation_type ?? '-' }}</td>
        <td>
            <span class="badge bg-maroon-soft text-maroon">
                {{ optional($report->violationType?->violationCategory)->category_name ?? '-' }}
            </span>
        </td>
        <td>
            <span class="badge bg-maroon-soft text-maroon">
                {{ $report->offense_level ?: '-' }}
            </span>
        </td>
        <td>{{ optional($report->sanction)->disciplinary_sanction ?? '-' }}</td>
        <td>{{ $report->violation_date ? \Carbon\Carbon::parse($report->violation_date)->format('M d, Y') : '-' }}</td>
        <td>{{ $report->recorded_by_display }}</td>
       
<td>
    @php
        // Use user_id as the primary student identifier.
        // Retain student_number as a fallback for legacy records.
        $studentIdentifier = $report->user_id
            ?: $report->student?->user_id
            ?: $report->student_number
            ?: $report->student?->student_number;

        $reportContext = array_merge(
            request()->query(),
            [
                'student_number' => $studentIdentifier,
                'source' => 'reports',
            ]
        );
    @endphp

    @if (filled($studentIdentifier))
        <a href="{{ route('admin.violations.show', $reportContext) }}"
           class="admin-btn admin-btn-view admin-btn-sm">
            <i class="bi bi-eye me-1"></i>View Record
        </a>
    @else
        <span class="text-muted small">
            Student record unavailable
        </span>
    @endif
</td>

    </tr>
@empty
    <x-admin.empty-state
        :colspan="9"
        icon="bi-file-earmark-x"
        title="No reports found"
        message="Try changing your filter criteria."
        :clearUrl="route('admin.reports')" />
@endforelse
