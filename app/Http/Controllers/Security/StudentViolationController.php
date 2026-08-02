<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\DisciplinarySanction;
use App\Models\Program;
use App\Models\ViolationCategory;
use App\Models\ViolationType;
use App\Models\Year;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Support\Collection;

class StudentViolationController extends Controller
{
    private const REPORT_TIMEZONE = 'Asia/Manila';

    /**
     * Display a listing of students with violations
     */
    public function index()
    {
        $search = trim((string) request('search', ''));
        $programId = request('program_id');
        $yearId = request('year_id');

        $students = User::whereHas('violations', function ($query) {
                $this->applyRecorderScope($query);
            })
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('student_number', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->when(!empty($programId), function (Builder $query) use ($programId) {
                $query->whereHas('studentInfo', function (Builder $infoQuery) use ($programId) {
                    $infoQuery->where('program_id', $programId);
                });
            })
            ->when(!empty($yearId), function (Builder $query) use ($yearId) {
                $query->whereHas('studentInfo', function (Builder $infoQuery) use ($yearId) {
                    $infoQuery->where('year_id', $yearId);
                });
            })
            ->with(['violations' => function ($query) {
                $this->applyRecorderScope($query);
                $query->latest('violation_date')->take(5);
            }])
            ->with([
                'studentInfo.program',
                'studentInfo.year',
            ])
            ->withCount([
                'violations as violations_count' => function ($query) {
                    $this->applyRecorderScope($query);
                },
            ])
            ->orderByDesc('violations_count')
            ->paginate(15)
            ->withQueryString();

        $categories = ViolationCategory::orderBy('category_name')->get();
        $programs = Program::orderBy('program_name')->get(['program_id', 'program_name']);
        $years = Year::orderBy('year')->get(['year_id', 'year']);

        return view('security.violations.students', compact('students', 'categories', 'programs', 'years', 'search', 'programId', 'yearId'));
    }

    /**
     * Display violations for a specific student
     */
    public function show(string $student_number)
    {
        $student = User::where('student_number', $student_number)
            ->with(['violations' => function ($query) {
                $this->applyRecorderScope($query);
                $query->latest('violation_date');
            }, 'violations.violationType.violationCategory'])
            ->firstOrFail();

        $violations = $student->violations;
        $violationStats = $this->getViolationStats($student_number);

        return view('security.violations.show', compact('student', 'violations', 'violationStats'));
    }

    /**
     * Search for students
     */
    public function search(Request $request)
    {
        $query = (string) $request->input('search', $request->input('q', ''));

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $students = User::where('student_number', 'like', "%{$query}%")
            ->orWhere('first_name', 'like', "%{$query}%")
            ->orWhere('last_name', 'like', "%{$query}%")
            ->with([
                'violations',
                'studentInfo.program',
                'studentInfo.year',
                'studentInfo.section',
                'studentInfo.studentStatus',
            ])
            ->withCount('violations')
            ->limit(10)
            ->get();

        return response()->json($students->map(function ($student) {
            return [
                'id' => $student->student_number,
                'student_number' => $student->student_number,
                'first_name' => $student->first_name,
                'middle_name' => $student->middle_name,
                'last_name' => $student->last_name,
                'text' => "{$student->student_number} - {$student->last_name}, {$student->first_name}",
                'violations_count' => $student->violations_count,
                'program' => [
                    'program_name' => optional($student->studentInfo?->program)->program_name,
                ],
                'year' => [
                    'year_name' => optional($student->studentInfo?->year)->year,
                ],
                'section' => [
                    'section_name' => optional($student->studentInfo?->section)->section_name,
                ],
                'student_status' => [
                    'status_name' => optional($student->studentInfo?->studentStatus)->status_name,
                ],
            ];
        }));
    }

    public function getViolationTypes(Request $request): JsonResponse
    {
        $categoryId = $request->get('category_id');

        if (!$categoryId) {
            return response()->json([]);
        }

        $types = ViolationType::where('violation_category_id', $categoryId)
            ->orderBy('violation_type')
            ->get([
                'violation_type_id',
                'violation_type',
                'violation_description',
                'resolution_number',
            ]);

        return response()->json($types);
    }

    public function previewViolation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_number' => ['required', 'string'],
            'violation_type_id' => ['required', 'integer'],
        ]);

        $violationType = ViolationType::with('violationCategory')
            ->find($validated['violation_type_id']);

        if (!$violationType) {
            return response()->json([
                'message' => 'Violation type not found.',
            ], 404);
        }

        $previousCount = Violation::where('student_number', $validated['student_number'])
            ->where(function ($query) use ($violationType) {
                $query->where('violation_type', (string) $violationType->violation_type_id)
                    ->orWhere('violation_type', $violationType->violation_type);
            })
            ->count();

        $currentOffenseNumber = $previousCount + 1;
        $offenseLevel = $this->formatOffenseLevel($currentOffenseNumber);

        $sanction = DisciplinarySanction::where('violation_type_id', $violationType->violation_type_id)
            ->where('offense_level', $offenseLevel)
            ->first();

        return response()->json([
            'category' => optional($violationType->violationCategory)->category_name,
            'violation_type' => $violationType->violation_type,
            'severity_level' => $violationType->severity_level,
            'offense_level' => $offenseLevel,
            'sanction' => $sanction?->disciplinary_sanction ?? 'No disciplinary sanction configured.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_number' => ['required', 'exists:users_tbl,student_number'],
            'violation_type_id' => ['required', 'exists:violation_type_tbl,violation_type_id'],
            'violation_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        $violationType = ViolationType::findOrFail($validated['violation_type_id']);

        DB::beginTransaction();

        try {
            $exists = Violation::where('student_number', $validated['student_number'])
                ->where(function ($query) use ($violationType) {
                    $query->where('violation_type', (string) $violationType->violation_type_id)
                        ->orWhere('violation_type', $violationType->violation_type);
                })
                ->whereDate('violation_date', Carbon::parse($validated['violation_date'])->toDateString())
                ->exists();

            if ($exists) {
                DB::rollBack();

                return response()->json([
                    'message' => 'A violation of this type has already been recorded for this student today.',
                ], 422);
            }

            $security = Auth::guard('security')->user();
            $securityLabel = $this->buildSecurityRecorderLabel($security?->id, $security?->email);

            $violation = Violation::create([
                'student_number' => $validated['student_number'],
                'violation_type' => $violationType->violation_type_id,
                'violation_date' => $validated['violation_date'],
                'description' => trim($validated['description']),
                // recorder_id references admins.id; security accounts are in a different table.
                'recorder_id' => null,
                'recorder_type' => 'security',
                'recorder_name' => $securityLabel,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Violation recorded successfully.',
                'data' => $violation,
            ]);
        } catch (\Throwable $exception) {
            DB::rollBack();

            return response()->json([
                'message' => $exception->getMessage(),
                'line' => $exception->getLine(),
                'file' => basename($exception->getFile()),
            ], 500);
        }
    }

    public function report(Request $request)
    {
        $period = (string) $request->input('period', 'last7');
        $period = in_array($period, ['today', 'last7', 'last30', 'all', 'custom'], true) ? $period : 'last7';

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $reportStudents = $this->buildReportStudents($period, $dateFrom, $dateTo);

        $security = Auth::guard('security')->user();
        $reportedBy = $this->buildSecurityAccountName($security);

        $filtersApplied = match ($period) {
            'today' => 'Today (PHT)',
            'last7' => 'Last 7 Days',
            'last30' => 'Last 30 Days',
            'all' => 'All My Recorded Violations',
            'custom' => $this->formatCustomDateFilter($dateFrom, $dateTo),
            default => 'Last 7 Days',
        };

        return view('security.violations.report', [
            'reportStudents' => $reportStudents,
            'generatedAt' => $this->phtNow(),
            'filtersApplied' => $filtersApplied,
            'reportedBy' => $reportedBy,
        ]);
    }

    /**
     * Get violation statistics for a student
     */
    private function getViolationStats(string $student_number)
    {
        $violations = Violation::where('student_number', $student_number)
            ->where(function ($query) {
                $this->applyRecorderScope($query);
            })
            ->with(['violationType.violationCategory']);

        $byCategory = (clone $violations)
            ->get()
            ->groupBy(fn ($violation) => $violation->violation_category_display)
            ->map(function ($items, $categoryName) {
                return (object) [
                    'category_name' => $categoryName,
                    'total' => $items->count(),
                ];
            })
            ->values();

        $severityCounts = (clone $violations)
            ->leftJoin(
                'violation_type_tbl',
                'violation_tbl.violation_type',
                '=',
                'violation_type_tbl.violation_type_id'
            )
            ->selectRaw('COALESCE(violation_type_tbl.severity_level, 0) as severity_level, COUNT(*) as count')
            ->groupBy('severity_level')
            ->pluck('count', 'severity_level');

        return [
            'total' => $violations->count(),
            'major' => (int) ($severityCounts[1] ?? 0),
            'minor' => (int) ($severityCounts[2] ?? 0),
            'by_category' => $byCategory,
        ];
    }

    private function buildReportStudents(string $period = 'last7', ?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        $violationsQuery = Violation::with([
                'student.studentInfo.program',
                'student.studentInfo.year',
                'student.studentInfo.section',
                'violationType.violationCategory',
                'violationType.disciplinarySanctions',
            ])
            ->where(function ($query) {
                $this->applyRecorderScope($query);
            });

        $phtNow = $this->phtNow();

        if ($period === 'today') {
            $violationsQuery->whereBetween('violation_date', [
                $phtNow->copy()->startOfDay(),
                $phtNow->copy()->endOfDay(),
            ]);
        } elseif ($period === 'last7') {
            $violationsQuery->where('violation_date', '>=', $phtNow->copy()->subDays(7));
        } elseif ($period === 'last30') {
            $violationsQuery->where('violation_date', '>=', $phtNow->copy()->subDays(30));
        } elseif ($period === 'custom') {
            $from = $this->normalizeDateBoundary($dateFrom, false);
            $to = $this->normalizeDateBoundary($dateTo, true);

            if ($from !== null && $to !== null) {
                $violationsQuery->whereBetween('violation_date', [$from, $to]);
            } elseif ($from !== null) {
                $violationsQuery->where('violation_date', '>=', $from);
            } elseif ($to !== null) {
                $violationsQuery->where('violation_date', '<=', $to);
            }
        }

        $violations = $violationsQuery
            ->latest('violation_date')
            ->get();

        return $violations
            ->groupBy('student_number')
            ->map(function ($studentViolations) {
                $firstViolation = $studentViolations->first();
                $student = $firstViolation?->student;

                $detailedRecords = $studentViolations
                    ->sortByDesc('violation_date')
                    ->values()
                    ->map(function ($record) {
                        $offenseLevel = $record->offense_level ?: 'N/A';
                        $resolvedViolationType = $record->resolvedViolationType();

                        $sanctionText = 'N/A';
                        if (!empty($resolvedViolationType?->violation_type_id)) {
                            $disciplinarySanction = DisciplinarySanction::where(
                                    'violation_type_id',
                                    $resolvedViolationType->violation_type_id
                                )
                                ->whereRaw('LOWER(TRIM(offense_level)) = ?', [strtolower(trim($offenseLevel))])
                                ->first();

                            if (!$disciplinarySanction) {
                                $disciplinarySanction = DisciplinarySanction::where(
                                        'violation_type_id',
                                        $resolvedViolationType->violation_type_id
                                    )
                                    ->first();
                            }

                            $sanctionText = $disciplinarySanction?->disciplinary_sanction ?? 'N/A';
                        }

                        $status = 'N/A';
                        if ($sanctionText !== 'N/A') {
                            $status = str_contains(strtolower($sanctionText), 'warning') ? 'Warning' : 'Sanction';
                        }

                        return (object) [
                            'category' => $record->violation_category_display,
                            'type' => $record->violation_type_display,
                            'offense_level' => $offenseLevel,
                            'remarks' => $record->description ?: 'No remarks',
                            'date_recorded' => $record->violation_date,
                            'status' => $status,
                            'sanction' => $sanctionText,
                            'recorded_by' => $record->recorded_by_display,
                        ];
                    });

                return (object) [
                    'student' => $student,
                    'records' => $detailedRecords,
                ];
            })
            ->values()
            ->sortBy(function ($entry) {
                return trim(($entry->student?->last_name ?? '') . ', ' . ($entry->student?->first_name ?? ''));
            })
            ->values();
    }

    private function normalizeDateBoundary(?string $date, bool $endOfDay): ?Carbon
    {
        if (empty($date)) {
            return null;
        }

        try {
            $parsed = Carbon::parse($date, self::REPORT_TIMEZONE);
            return $endOfDay ? $parsed->endOfDay() : $parsed->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function phtNow(): Carbon
    {
        return Carbon::now(self::REPORT_TIMEZONE);
    }

    private function formatCustomDateFilter(?string $dateFrom, ?string $dateTo): string
    {
        $from = $this->normalizeDateBoundary($dateFrom, false);
        $to = $this->normalizeDateBoundary($dateTo, true);

        if ($from && $to) {
            return 'Custom Date Range: ' . $from->format('M d, Y') . ' to ' . $to->format('M d, Y');
        }

        if ($from) {
            return 'Custom Date Range: From ' . $from->format('M d, Y');
        }

        if ($to) {
            return 'Custom Date Range: Until ' . $to->format('M d, Y');
        }

        return 'Custom Date Range: No valid dates provided';
    }

    private function formatOffenseLevel(int $count): string
    {
        if ($count === 1) {
            return '1st Offense';
        }

        if ($count === 2) {
            return '2nd Offense';
        }

        if ($count === 3) {
            return '3rd Offense';
        }

        $suffix = match (true) {
            $count % 100 >= 11 && $count % 100 <= 13 => 'th',
            $count % 10 === 1 => 'st',
            $count % 10 === 2 => 'nd',
            $count % 10 === 3 => 'rd',
            default => 'th',
        };

        return "{$count}{$suffix} Offense";
    }

    private function applyRecorderScope($query): void
    {
        $security = Auth::guard('security')->user();
        $securityLabel = $this->buildSecurityRecorderLabel($security?->id, $security?->email);
        $legacySecurityIdLabel = 'Security #' . ($security?->id ?? 'Unknown');

        $query->where('recorder_type', 'security')
            ->where(function ($innerQuery) use ($securityLabel, $legacySecurityIdLabel) {
                $innerQuery->where('recorder_name', $securityLabel)
                    ->orWhere('recorder_name', $legacySecurityIdLabel);
            });
    }

    private function buildSecurityRecorderLabel($securityId, ?string $email): string
    {
        if (!empty($email)) {
            return 'Security: ' . $email;
        }

        return 'Security #' . ($securityId ?? 'Unknown');
    }

    private function buildSecurityAccountName($security): string
    {
        if (!$security) {
            return 'Security Account';
        }

        $info = $security->securityInfo;
        $legacyInfo = $security->securityProfile;

        $fullName = trim(implode(' ', array_filter([
            $info?->firstname ?? $legacyInfo?->firstname,
            $info?->middlename ?? $legacyInfo?->middlename,
            $info?->lastname ?? $legacyInfo?->lastname,
        ], fn ($value) => !empty($value))));

        if ($fullName !== '') {
            return $fullName;
        }

        return $security->email ?: 'Security Account';
    }
}
