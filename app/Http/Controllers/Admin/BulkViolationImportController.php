<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationType;
use App\Services\StudentNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BulkViolationImportController extends Controller
{
    private const SESSION_KEY = 'admin_bulk_violation_preview';
    private const MAX_ROWS = 500;

    public function show()
    {
        return view('admin.violations.bulk-import', [
            'preview' => session(self::SESSION_KEY),
        ]);
    }

    public function template(): StreamedResponse
    {
        $headers = ['student_number', 'first_name', 'middle_name', 'last_name', 'program', 'year', 'violation_type', 'violation_date', 'description'];
        $sample = ['2022-00001-TG-0', 'JUAN', 'SANTOS', 'DELA CRUZ', 'Bachelor of Science in Information Technology', '3', 'Wearing Improper Uniform', '2026-11-24', 'Brief description of the incident'];

        return response()->streamDownload(function () use ($headers, $sample): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fputcsv($out, $sample);
            fclose($out);
        }, 'PUPTracker_Bulk_Violation_Template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $sheetRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        } catch (Throwable $e) {
            return back()->withErrors(['file' => 'The uploaded file could not be read. Please use the provided CSV template or a valid Excel file.']);
        }

        if (count($sheetRows) < 2) {
            return back()->withErrors(['file' => 'The spreadsheet has no data rows.']);
        }

        $headers = array_map(fn ($value) => $this->normalizeHeader((string) $value), array_shift($sheetRows));
        $required = ['violation_type', 'violation_date', 'description'];
        $missing = array_values(array_diff($required, $headers));
        if (!in_array('student_number', $headers, true) && !in_array('first_name', $headers, true) && !in_array('last_name', $headers, true)) {
            $missing[] = 'student_number or first_name + last_name';
        }
        if ($missing) {
            return back()->withErrors(['file' => 'Missing required column(s): ' . implode(', ', $missing) . '. Download the template for the correct format.']);
        }

        $dataRows = array_slice($sheetRows, 0, self::MAX_ROWS);
        $previewRows = [];
        foreach ($dataRows as $index => $values) {
            $row = ['line' => $index + 2];
            foreach ($headers as $columnIndex => $header) {
                if ($header !== '') {
                    $row[$header] = trim((string) ($values[$columnIndex] ?? ''));
                }
            }
            if (collect($row)->except('line')->every(fn ($value) => $value === '')) {
                continue;
            }
            $previewRows[] = $this->inspectRow($row);
        }

        if (!$previewRows) {
            return back()->withErrors(['file' => 'No usable data rows were found.']);
        }

        $token = Str::random(40);
        session([self::SESSION_KEY => ['token' => $token, 'rows' => $previewRows, 'created_at' => now()->timestamp]]);

        return redirect()->route('admin.violations.bulk-import')->with('success', 'Spreadsheet analyzed. Review the results before importing.');
    }

    public function commit(Request $request)
    {
        $request->validate(['token' => ['required', 'string']]);
        $preview = session(self::SESSION_KEY);
        if (!$preview || !hash_equals((string) ($preview['token'] ?? ''), (string) $request->input('token')) || now()->timestamp - (int) ($preview['created_at'] ?? 0) > 1800) {
            return redirect()->route('admin.violations.bulk-import')->withErrors(['file' => 'The preview expired. Please upload the spreadsheet again.']);
        }

        $validRows = array_values(array_filter($preview['rows'], fn ($row) => ($row['status'] ?? '') === 'Valid'));
        if (!$validRows) {
            return back()->withErrors(['file' => 'There are no valid rows to import. Resolve the issues and upload the file again.']);
        }

        $admin = Auth::guard('admin')->user();
        $adminId = (int) Auth::guard('admin')->id();
        $adminName = trim(implode(' ', array_filter([
            $admin?->adminInfo?->firstname,
            $admin?->adminInfo?->middlename,
            $admin?->adminInfo?->lastname,
        ])));
        if ($adminName === '') {
            $adminName = $admin?->email ? 'Admin: ' . $admin->email : 'Admin #' . $adminId;
        }

        $created = [];
        $skipped = 0;
        try {
            DB::transaction(function () use ($validRows, $adminId, $adminName, &$created, &$skipped): void {
                foreach ($validRows as $row) {
                    $student = User::with('studentInfo')->find((int) $row['user_id']);
                    $type = ViolationType::find((int) $row['violation_type_id']);
                    if (!$student || !$student->isActiveAccount() || !$type) {
                        $skipped++;
                        continue;
                    }

                    $date = Carbon::parse($row['violation_date'])->toDateString();
                    $duplicate = Violation::where('user_id', $student->user_id)
                        ->where('violation_type_id', $type->violation_type_id)
                        ->whereDate('violation_date', $date)
                        ->notDenied()->exists();
                    if ($duplicate) {
                        $skipped++;
                        continue;
                    }

                    $violation = Violation::create([
                        'user_id' => $student->user_id,
                        'student_number' => $student->student_number,
                        'violation_type_id' => $type->violation_type_id,
                        'violation_date' => Carbon::parse($row['violation_date'])->format('Y-m-d H:i:s'),
                        'description' => trim($row['description']),
                        'recorder_id' => $adminId,
                        'recorder_type' => 'admin',
                        'recorder_name' => $adminName,
                        'status' => Violation::STATUS_CONFIRMED,
                        'reviewed_by_admin_id' => $adminId,
                        'reviewed_at' => now(),
                    ]);
                    $created[] = ['student_number' => (string) ($student->student_number ?? ''), 'violation_id' => (int) $violation->violation_id, 'type' => $type->violation_type];
                }
            });
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['file' => 'The import could not be completed. No records were committed. Please contact the system administrator if this continues.']);
        }

        foreach ($created as $item) {
            try {
                app(StudentNotificationService::class)->create(
                    studentNumber: $item['student_number'],
                    type: 'violation',
                    title: 'Violation Notice',
                    message: 'A violation has been recorded against your record: ' . $item['type'] . '. Please review your Student Record.',
                    targetType: 'violation',
                    targetId: $item['violation_id'],
                );
            } catch (Throwable $e) {
                report($e);
            }
        }

        session()->forget(self::SESSION_KEY);
        return redirect()->route('admin.violations.bulk-import')->with('success', count($created) . ' violation(s) imported successfully. ' . $skipped . ' row(s) were skipped because the student/type changed or a duplicate was detected.');
    }

    private function inspectRow(array $row): array
    {
        $issues = [];
        $student = $this->findStudent($row, $issues);
        $typeName = trim((string) ($row['violation_type'] ?? ''));
        $type = $typeName !== '' ? ViolationType::whereRaw('LOWER(TRIM(violation_type)) = ?', [mb_strtolower($typeName)])->first() : null;
        if (!$type) {
            $issues[] = $typeName === '' ? 'Violation type is required.' : 'Violation type was not found in Configuration.';
        }

        $dateValue = trim((string) ($row['violation_date'] ?? ''));
        $date = null;
        try {
            if ($dateValue === '') {
                throw new \InvalidArgumentException();
            }
            // Excel date serials may arrive as numeric values; otherwise parse the supplied date text.
            if (is_numeric($dateValue) && (float) $dateValue > 20000 && (float) $dateValue < 80000) {
                $date = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $dateValue))->toDateString();
            } else {
                $date = Carbon::parse($dateValue)->toDateString();
            }
            if (Carbon::parse($date)->isFuture()) {
                $issues[] = 'Violation date cannot be in the future.';
            }
        } catch (Throwable $e) {
            $issues[] = 'A valid violation date is required (YYYY-MM-DD).';
        }

        $description = trim((string) ($row['description'] ?? ''));
        if ($description === '') {
            $issues[] = 'Description is required.';
        }

        if ($student && $type && $date) {
            $duplicate = Violation::where('user_id', $student->user_id)
                ->where('violation_type_id', $type->violation_type_id)
                ->whereDate('violation_date', $date)->notDenied()->exists();
            if ($duplicate) {
                $issues[] = 'Duplicate: this student already has this violation type on this date.';
            }
        }

        $row['student_name'] = $student ? trim(implode(' ', array_filter([$student->first_name, $student->middle_name, $student->last_name, $student->name_suffix]))) : '—';
        $row['matched_student_number'] = $student?->student_number ?: '—';
        $row['user_id'] = $student?->user_id;
        $row['violation_type_id'] = $type?->violation_type_id;
        $row['violation_date'] = $date;
        $row['status'] = $issues ? 'Needs review' : 'Valid';
        $row['issues'] = array_values(array_unique($issues));
        return $row;
    }

    private function findStudent(array $row, array &$issues): ?User
    {
        $number = trim((string) ($row['student_number'] ?? ''));
        if ($number !== '') {
            $student = User::with('studentInfo.program', 'studentInfo.year')->where('student_number', $number)->first();
            if (!$student) {
                $issues[] = 'Student number was not found.';
                return null;
            }
        } else {
            $first = mb_strtolower(trim((string) ($row['first_name'] ?? '')));
            $last = mb_strtolower(trim((string) ($row['last_name'] ?? '')));
            $middle = mb_strtolower(trim((string) ($row['middle_name'] ?? '')));
            $program = trim((string) ($row['program'] ?? ''));
            $year = trim((string) ($row['year'] ?? ''));
            if ($first === '' || $last === '' || $program === '' || $year === '') {
                $issues[] = 'Provide student_number, or provide first_name, last_name, program, and year for safe matching.';
                return null;
            }
            $matches = User::with('studentInfo.program', 'studentInfo.year')
                ->whereRaw('LOWER(TRIM(first_name)) = ?', [$first])
                ->whereRaw('LOWER(TRIM(last_name)) = ?', [$last])
                ->when($middle !== '', fn ($q) => $q->whereRaw('LOWER(TRIM(COALESCE(middle_name, ""))) = ?', [$middle]))
                ->whereHas('studentInfo.program', fn ($q) => $q->whereRaw('LOWER(TRIM(program_name)) = ?', [mb_strtolower($program)]))
                ->whereHas('studentInfo.year', fn ($q) => $q->whereRaw('LOWER(TRIM(CAST(year AS CHAR))) = ?', [mb_strtolower($year)]))
                ->limit(3)->get();
            if ($matches->count() !== 1) {
                $issues[] = $matches->isEmpty() ? 'No exact student match. Check the name, program, and year.' : 'Ambiguous student match. Add the exact student_number to this row.';
                return null;
            }
            $student = $matches->first();
        }

        if (!$student->isActiveAccount()) {
            $issues[] = 'Student account is inactive.';
        }
        return $student;
    }

    private function normalizeHeader(string $header): string
    {
        $header = Str::lower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? '';
        $header = trim($header, '_');
        return match ($header) {
            'student_no', 'student_id', 'id_number' => 'student_number',
            'firstname', 'first' => 'first_name',
            'middlename', 'middle' => 'middle_name',
            'lastname', 'last' => 'last_name',
            'program_name', 'course', 'course_name' => 'program',
            'year_level', 'year_name' => 'year',
            'violation', 'offense', 'offense_type' => 'violation_type',
            'date', 'incident_date' => 'violation_date',
            'remarks', 'details' => 'description',
            default => $header,
        };
    }
}
