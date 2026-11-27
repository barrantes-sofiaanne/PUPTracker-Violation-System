<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ViolationType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ViolationTypeController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | List Violation Types
    |--------------------------------------------------------------------------
    */

    public function index(): JsonResponse
    {
        $types = ViolationType::with('violationCategory')
            ->orderBy('violation_type')
            ->get();

        return response()->json($types);
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

public function store(Request $request): JsonResponse
{
    $validated = $request->validate([

        'violation_category_id' => [
            'required',
            'exists:violation_category_tbl,violation_category_id'
        ],

        'violation_type' => [
            'required',
            'string',
            'max:255',
            'unique:violation_type_tbl,violation_type'
        ],

        'violation_description' => [
            'nullable',
            'string'
        ],

        'resolution_number' => [
            'nullable',
            'string',
            'max:100'
        ],

    ]);

    // The publication timestamp is assigned by the SERVER in Philippine
    // Standard Time; the administrator never types it.
    $validated['date_published'] = now('Asia/Manila');

    $type = ViolationType::create($validated);

    $this->auditViolationTypeChange(
        $request,
        'Created',
        $type,
        [],
        $this->violationTypeSnapshot($type)
    );

    return response()->json([
        'success' => true,
        'message' => 'Violation type added successfully.',
        'data' => $type->load('violationCategory')
    ]);
}

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show($id): JsonResponse
    {
        $type = ViolationType::with('violationCategory')
            ->findOrFail($id);

        return response()->json($type);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id): JsonResponse
    {
        $type = ViolationType::findOrFail($id);

        $validated = $request->validate([

            'violation_category_id' => [
                'required',
                'exists:violation_category_tbl,violation_category_id'
            ],

            'violation_type' => [
                'required',
                'string',
                'max:255',
                'unique:violation_type_tbl,violation_type,' .
                $id .
                ',violation_type_id'
            ],

            'violation_description' => [
                'nullable',
                'string'
            ],

            'resolution_number' => [
                'nullable',
                'string',
                'max:100'
            ],

        ]);

        // The original publication timestamp is intentionally preserved: it is
        // never part of the editable payload and is not reset on update.
        $before = $this->violationTypeSnapshot($type);

        $type->update($validated);

        $this->auditViolationTypeChange(
            $request,
            'Updated',
            $type->fresh(),
            $before,
            $this->violationTypeSnapshot($type->fresh())
        );

        return response()->json([

            'success' => true,

            'message' => 'Violation type updated successfully.',

            'data' => $type->load('violationCategory')

        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function destroy($id): JsonResponse
    {
        $type = ViolationType::findOrFail($id);

        if ($type->violations()->exists()) {

            return response()->json([

                'success' => false,

                'message' =>
                    'This violation type cannot be deleted because it is already used.'

            ], 422);
        }

        $type->delete();

        return response()->json([

            'success' => true,

            'message' => 'Violation type deleted successfully.'

        ]);
    }

    /**
     * Snapshot the auditable fields of a violation type.
     *
     * @return array<string, mixed>
     */
    private function violationTypeSnapshot(ViolationType $type): array
    {
        return [
            'violation_type_id' => (int) $type->violation_type_id,
            'violation_category_id' => $type->violation_category_id,
            'violation_type' => $type->violation_type,
            'violation_description' => $type->violation_description,
            'resolution_number' => $type->resolution_number,
            'date_published' => optional($type->date_published)->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Append-only audit entry for a violation-type create/update.
     *
     * Uses the established AuditLogService (no separate audit store) and
     * records only fields that actually changed on updates.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function auditViolationTypeChange(
        Request $request,
        string $action,
        ViolationType $type,
        array $before,
        array $after
    ): void {
        $changes = [];

        foreach ($after as $field => $newValue) {
            $oldValue = $before[$field] ?? null;

            if ($action === 'Updated' && $oldValue === $newValue) {
                continue;
            }

            $changes[$field] = [
                'from' => $oldValue,
                'to' => $newValue,
            ];
        }

        $description = sprintf(
            '%s violation type #%d "%s" (module: Violation Types, actor role: %s).',
            $action,
            (int) $type->violation_type_id,
            (string) $type->violation_type,
            (string) (auth('admin')->check() ? 'admin' : 'system')
        );

        if ($changes !== []) {
            $parts = [];

            foreach ($changes as $field => $change) {
                $parts[] = sprintf(
                    '%s from "%s" to "%s"',
                    $field,
                    $this->stringifyAuditValue($change['from']),
                    $this->stringifyAuditValue($change['to'])
                );
            }

            $description .= ' ' . ($action === 'Created' ? 'New values: ' : 'Changes: ')
                . implode('; ', $parts) . '.';
        } else {
            $description .= ' No field values changed.';
        }

        $audit = app(\App\Services\AuditLogService::class)->audit([
            'action' => $action,
            'module' => 'Violation Types',
            'description' => $description,
        ], $request);

        // The established policy is fail-safe for the business action, but an
        // audit write must never be reported as successful when it was not
        // recorded: surface it in the application log.
        if ($audit === null) {
            \Illuminate\Support\Facades\Log::warning(
                'Violation type audit entry could not be written.',
                [
                    'action' => $action,
                    'violation_type_id' => $type->violation_type_id,
                ]
            );
        }
    }

    private function stringifyAuditValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    public function categoryTypes($categoryId)
{
    $types = ViolationType::where(
        'violation_category_id',
        $categoryId
    )
    ->orderBy('violation_type')
    ->paginate(5);

    if (request()->ajax()) {

        return view(
            'admin.violations.configuration.partials.violation-types-table',
            compact('types')
        );
    }

    return response()->json($types);
}
}