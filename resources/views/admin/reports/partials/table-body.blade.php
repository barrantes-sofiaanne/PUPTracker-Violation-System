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
            <span class="badge bg-secondary">
                {{ optional($report->violationType?->violationCategory)->category_name ?? '-' }}
            </span>
        </td>
        <td>{{ optional($report->violationType)->severity_level ?? '-' }}</td>
        <td>{{ optional($report->sanction)->disciplinary_sanction ?? '-' }}</td>
        <td>{{ $report->violation_date ? \Carbon\Carbon::parse($report->violation_date)->format('M d, Y') : '-' }}</td>
        <td>{{ optional($report->recorder)->first_name ?? '-' }}</td>
        <td>
            <a href="{{ route('admin.violations.show', $report->student?->student_number) }}" class="btn btn-sm btn-outline-primary">View</a>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="9" class="text-center py-5">
            <i class="bi bi-file-earmark-x display-4 text-muted"></i>
            <h5 class="mt-3">No Report Found</h5>
            <p class="text-muted">Try changing your filter criteria.</p>
        </td>
    </tr>
@endforelse
