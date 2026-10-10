<?php

namespace App\Services;

use App\Models\DisciplinarySanction;
use App\Models\EvidenceSecurityEvent;
use App\Models\Violation;
use App\Models\ViolationEvidence;
use Carbon\Carbon;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Identity-free violation-recording helpers shared by the Security module and
 * the OSS Staff module.
 *
 * WHY THIS EXISTS
 * ---------------
 * Security and OSS Staff record violations the same way (same validation, same
 * evidence pipeline, same private storage, same report content) but they are
 * DIFFERENT ACTORS: a Security officer is a `security`-guard account, while OSS
 * Staff is an `admin`-guard account holding the OSS Staff role.
 *
 * Everything in this class is deliberately IDENTITY-FREE: the caller states the
 * actor type + id it wants recorded (or the recorder scope it wants applied),
 * and this class never reaches for a guard itself. That keeps a single
 * implementation of the evidence + report rules while guaranteeing the two
 * modules can never mislabel one another's actor.
 *
 * NOTE: the `uploader_type` / `recorder_type` values written here are supplied
 * by the caller, so the OSS Staff module records 'admin' and the Security
 * module records 'security'.
 */
class ViolationRecordingService
{
    /** Report + offense-level timezone (Philippine Standard Time). */
    public const REPORT_TIMEZONE = 'Asia/Manila';

    /**
     * Resolve the configured evidence storage disk (S3-ready on Railway).
     *
     * Delegates to the central resolver so an upload and a read can never
     * resolve different disks.
     */
    public function evidenceDisk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = \App\Support\Storage\EvidenceDisk::disk();

        return $disk;
    }

    /**
     * Store uploaded evidence for a violation privately and record audit /
     * security events. Runs inside the caller's DB transaction.
     *
     * @param  string  $uploaderType  Actor marker written to `uploader_type`
     *                                ('security' | 'admin') and used as the
     *                                audit actor type for the security events.
     * @param  int|null  $uploaderId  The acting account id for that module.
     */
    public function storeEvidence(
        Violation $violation,
        Request $request,
        string $uploaderType,
        ?int $uploaderId,
        ?string $captureSessionId = null
    ): void {
        $evidenceFiles = $request->allFiles()['evidence'] ?? [];

        if ($evidenceFiles === []) {
            return;
        }

        // Capture metadata is submitted as parallel arrays (one entry per
        // staged evidence item). The camera path marks capture_method as
        // 'camera' and records when the photo was taken. This is audit
        // metadata only — it never proves identity.
        $captureMethods = (array) $request->input('capture_methods', []);
        $capturedAts = (array) $request->input('captured_ats', []);
        $evidenceService = app(EvidenceSecurityService::class);

        // Production guard: refuse to write confidential evidence to an
        // ephemeral filesystem when object storage is misconfigured.
        \App\Support\Storage\EvidenceDisk::assertWritable();

        foreach ($evidenceFiles as $index => $evidenceFile) {
            $evidenceCode = $evidenceService->nextEvidenceCode();
            $directory = 'violation-evidence/' . $violation->violation_id;
            $extension = strtolower($evidenceFile->getClientOriginalExtension());
            // Server-side filename derived from the unique evidence code —
            // the original filename is never trusted or used on disk.
            $filename = $evidenceCode . '.' . ($extension !== '' ? $extension : 'bin');
            $disk = $this->evidenceDisk();

            $isCamera = strcasecmp((string) ($captureMethods[$index] ?? ''), 'camera') === 0;
            $capturedAt = isset($capturedAts[$index]) && $capturedAts[$index] !== ''
                ? Carbon::parse($capturedAts[$index])
                : null;

            // Build the row first so we can derive the official path + hash.
            $evidence = ViolationEvidence::create([
                'evidence_code' => $evidenceCode,
                'access_key' => bin2hex(random_bytes(32)),
                'capture_session_id' => $captureSessionId,
                'violation_id' => $violation->violation_id,
                'disk_path' => $directory . '/' . $filename,
                'original_name' => $evidenceFile->getClientOriginalName(),
                'mime_type' => $evidenceFile->getMimeType(),
                'capture_method' => $isCamera
                    ? ViolationEvidence::CAPTURE_CAMERA
                    : ViolationEvidence::CAPTURE_UPLOAD,
                // The client `watermarked` flag is never proof. The server
                // re-bakes the watermark and records server_watermarked.
                'watermarked' => false,
                'server_watermarked' => false,
                'captured_at' => $capturedAt,
                'file_size' => $evidenceFile->getSize(),
                'ip_address' => $evidenceService->clientIp($request),
                'user_agent' => $evidenceService->userAgent($request),
                'uploader_type' => $uploaderType,
                'uploader_id' => $uploaderId,
            ]);

            // Final trusted image: server-side watermark (images only), stored
            // on the private disk, with SHA-256 of the FINAL bytes.
            $store = app(\App\Services\EvidenceWatermarkService::class)->storeOfficialFile(
                $evidenceFile,
                $directory,
                $filename,
                $evidenceCode,
                (string) ($violation->student_number ?? ''),
                $capturedAt?->format('Y-m-d H:i:s'),
            );

            $storedPath = $store['path'];
            if ($storedPath === '' || ! $disk->exists($storedPath)) {
                // The row was created moments ago inside the caller's
                // transaction, so re-throwing rolls it back. Remove any object
                // that did land so no unreferenced confidential file survives.
                try {
                    $disk->delete($storedPath);
                } catch (\Throwable $ignored) {
                    // Best-effort cleanup only.
                }

                throw new \RuntimeException('Unable to store evidence file.');
            }

            $storedSize = \App\Support\Storage\EvidenceDisk::size($storedPath);

            $evidence->update([
                'disk_path' => $storedPath,
                'file_size' => $storedSize ?? $evidenceFile->getSize(),
                'server_watermarked' => $store['server_watermarked'],
                'watermarked' => $store['server_watermarked'] || $isCamera,
                'sha256_hash' => $store['sha256'],
            ]);

            $evidenceService->logEvent(
                EvidenceSecurityEvent::EVIDENCE_UPLOADED,
                $violation->violation_id,
                $evidence->evidence_id,
                'info',
                $request,
                [
                    'method' => $evidence->capture_method,
                    'code' => $evidenceCode,
                    'sha256' => $store['sha256'],
                    'server_watermarked' => $store['server_watermarked'],
                ],
                $uploaderType,
                $uploaderId,
                $captureSessionId,
            );
        }
    }

    /**
     * The violation types configured for one category (identity-free lookup).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\ViolationType>
     */
    public function violationTypesForCategory(?int $categoryId)
    {
        if (! $categoryId) {
            return \App\Models\ViolationType::query()->whereRaw('1 = 0')->get();
        }

        return \App\Models\ViolationType::where('violation_category_id', $categoryId)
            ->orderBy('violation_type')
            ->get([
                'violation_type_id',
                'violation_type',
                'violation_description',
                'resolution_number',
            ]);
    }

    /**
     * The offense level + configured sanction for a (student, violation type)
     * pair, used by the "preview" step of the record-violation wizard.
     *
     * Returns null when the violation type does not exist. Identity-free: the
     * count is derived from the violation rows, never from the acting account.
     *
     * @return array<string, mixed>|null
     */
    public function previewViolationData(string $studentNumber, int $violationTypeId): ?array
    {
        $violationType = \App\Models\ViolationType::with('violationCategory')
            ->find($violationTypeId);

        if (! $violationType) {
            return null;
        }

$previousCount = Violation::where('student_number', $studentNumber)
    ->where('violation_type_id', $violationType->violation_type_id)
    ->effective()
    ->count();

        $currentOffenseNumber = $previousCount + 1;
        $offenseLevel = $this->formatOffenseLevel($currentOffenseNumber);

        $sanction = DisciplinarySanction::where('violation_type_id', $violationType->violation_type_id)
            ->where('offense_level', $offenseLevel)
            ->first();

        return [
            'category' => optional($violationType->violationCategory)->category_name,
            'violation_type' => $violationType->violation_type,
            'severity_level' => $violationType->severity_level,
            'offense_level' => $offenseLevel,
            'sanction' => $sanction?->disciplinary_sanction ?? 'No disciplinary sanction configured.',
        ];
    }

    /**
     * "1st Offense", "2nd Offense", ... for an offense count.
     */
    public function formatOffenseLevel(int $count): string
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

    /** Current Philippine Standard Time (Asia/Manila). */
    public function phtNow(): Carbon
    {
        return Carbon::now(self::REPORT_TIMEZONE);
    }

    /**
     * Parse a report date filter into a day boundary in PHT, or null when it is
     * empty/unparseable.
     */
    public function normalizeDateBoundary(?string $date, bool $endOfDay): ?Carbon
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

    /** Human-readable description of the custom date range that was applied. */
    public function formatCustomDateFilter(?string $dateFrom, ?string $dateTo): string
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

    /**
     * Build the report's student groups for the given period.
     *
     * The recorder restriction is supplied by the caller as `$applyScope` so the
     * report content/ordering/filters stay identical between the Security and
     * OSS Staff modules while never crossing actor boundaries.
     *
     * @param  callable  $applyScope  fn($query): void — restricts the query to the
     *                                acting recorder's own violations.
     * @return Collection<int, object>
     */
    public function buildReportStudents(
        string $period = 'last7',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?callable $applyScope = null
    ): Collection {
        $violationsQuery = Violation::with([
                'student.studentInfo.program',
                'student.studentInfo.year',
                'student.studentInfo.section',
                'violationType.violationCategory',
                'violationType.disciplinarySanctions',
            ])
            ->where(function ($query) use ($applyScope) {
                // Authorization: only the acting recorder's own violations.
                if ($applyScope !== null) {
                    $applyScope($query);
                }
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
                    ->map(function (Violation $record) {
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

    /**
     * Render the report as a downloadable PDF.
     *
     * The template must stay self-contained: mPDF resolves every remote URL
     * while writing the document, so a remote stylesheet or logo makes the
     * request hang whenever the server cannot reach that host.
     *
     * @param  string  $view           Blade template to render.
     * @param  string  $fallbackRoute  Where to send the user when generation
     *                                 fails (the caller's violation list).
     * @param  string  $logLabel       Log context label, e.g. 'Security'.
     */
    public function reportPdf(
        array $report,
        string $view,
        string $fallbackRoute,
        string $logLabel = 'Violation'
    ): \Symfony\Component\HttpFoundation\Response {
        $html = view($view, $report)->render();

        // mPDF writes scratch files; make sure the directory exists so it never
        // fails on a fresh deployment.
        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        try {
            $mpdf = new \Mpdf\Mpdf([
                // A4 portrait, matching the template's own @page rule.
                'format' => 'A4',
                'margin_left' => 12,
                'margin_right' => 12,
                'margin_top' => 12,
                'margin_bottom' => 14,
                'tempDir' => $tempDir,
                // Keep the rendered widths inside the printable area so the
                // table never overflows or gets clipped.
                'shrink_tables_to_fit' => 1,
            ]);

            $mpdf->SetTitle('PUPT Student Violation Report');
            $mpdf->WriteHTML($html);

            $pdf = $mpdf->Output('', 'S');
        } catch (\Throwable $e) {
            // Never leave the officer staring at a spinner: log the real reason
            // server-side and return a clear, actionable message.
            Log::error($logLabel . ' violation report PDF generation failed', [
                'reported_by' => $report['reportedBy'] ?? null,
                'student_groups' => isset($report['reportStudents'])
                    ? $report['reportStudents']->count()
                    : null,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return redirect()
                ->route($fallbackRoute)
                ->with('error', 'Unable to generate the PDF report right now. Please try again.');
        }

        // Required download filename: PUPTracker_Violation_Report-YY-MM-DD.pdf
        $fileName = 'PUPTracker_Violation_Report-' . $this->phtNow()->format('y-m-d') . '.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Content-Length' => (string) strlen($pdf),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
