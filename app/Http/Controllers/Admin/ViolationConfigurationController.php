<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ViolationCategory;
use Illuminate\Http\Request;
use App\Models\ViolationType;
use App\Models\DisciplinarySanction;

class ViolationConfigurationController extends Controller
{
    public function index()
{
    $categories = ViolationCategory::with([
        'violationTypes' => function ($query) {
            $query->orderBy('violation_type');
        }
    ])->orderBy('category_name')->get();

    $violationTypes = ViolationType::with('violationCategory')
        ->orderBy('violation_type')
        ->get();

    $sanctions = DisciplinarySanction::with('violationType.violationCategory')
        ->orderBy('offense_level')
        ->get();

    return view(
        'admin.violations.configuration.index',
        compact('categories', 'violationTypes', 'sanctions')
    );
}
    public function storeCategory(Request $request)
{
    $request->validate([
        'category_name' => 'required|string|max:255|unique:violation_category_tbl,category_name',
    ]);

    ViolationCategory::create([
        'category_name' => $request->category_name,
    ]);

    return back()->with('success', 'Category added successfully.');
}

public function updateCategory(Request $request, int $id)
{
    $request->validate([
        'category_name' => 'required|string|max:255',
    ]);

    $category = ViolationCategory::findOrFail($id);

    $category->update([
        'category_name' => $request->category_name,
    ]);

    return back()->with('success', 'Category updated successfully.');
}

public function destroyCategory(int $id)
{
    $category = ViolationCategory::findOrFail($id);

    if ($category->violationTypes()->exists()) {
        return back()->with(
            'error',
            'Cannot delete a category that still contains violation types.'
        );
    }

    $category->delete();

    return back()->with('success', 'Category deleted successfully.');
}

public function storeViolationType(Request $request)
{
    $request->validate([
        'violation_category_id' => 'required|exists:violation_category_tbl,violation_category_id',
        'violation_type' => 'required|string|max:255',
        'resolution_number' => 'nullable|string|max:100',
        'violation_description' => 'nullable|string',
    ]);

    $type = ViolationType::create([
        'violation_category_id' => $request->violation_category_id,
        'violation_type' => $request->violation_type,
        'resolution_number' => $request->resolution_number,
        'violation_description' => $request->violation_description,
        // Server-side publication timestamp in Philippine Standard Time.
        'date_published' => now('Asia/Manila'),
    ]);

    $this->auditViolationType($request, 'Created', $type, []);

    return back()->with('success', 'Violation type added successfully.');
}

    /**
     * Append-only violation-type audit entry through the existing service.
     *
     * @param  array<string, mixed>  $before
     */
    private function auditViolationType(
        Request $request,
        string $action,
        ViolationType $type,
        array $before = []
    ): void {
        $after = [
            'violation_category_id' => $type->violation_category_id,
            'violation_type' => $type->violation_type,
            'violation_description' => $type->violation_description,
            'resolution_number' => $type->resolution_number,
        ];

        $parts = [];

        foreach ($after as $field => $value) {
            $old = $before[$field] ?? null;

            if ($action === 'Updated' && (string) $old === (string) $value) {
                continue;
            }

            $parts[] = sprintf('%s from "%s" to "%s"', $field, (string) $old, (string) $value);
        }

        $audit = app(\App\Services\AuditLogService::class)->audit([
            'action' => $action,
            'module' => 'Violation Types',
            'description' => sprintf(
                '%s violation type #%d "%s" (module: Violation Types, actor role: %s).%s',
                $action,
                (int) $type->violation_type_id,
                (string) $type->violation_type,
                (string) (auth('admin')->check() ? 'admin' : 'system'),
                $parts === [] ? ' No field values changed.' : ' Changes: ' . implode('; ', $parts) . '.'
            ),
        ], $request);

        if ($audit === null) {
            \Illuminate\Support\Facades\Log::warning(
                'Violation type audit entry could not be written.',
                ['action' => $action, 'violation_type_id' => $type->violation_type_id]
            );
        }
    }

public function updateViolationType(Request $request, int $id)
{
    $request->validate([
        'violation_category_id' => 'required|exists:violation_category_tbl,violation_category_id',
        'violation_type' => 'required|string|max:255',
        'resolution_number' => 'nullable|string|max:100',
        'violation_description' => 'nullable|string',
    ]);

    $type = ViolationType::findOrFail($id);

    $before = $type->only([
        'violation_category_id',
        'violation_type',
        'resolution_number',
        'violation_description',
        'date_published',
    ]);

    // `date_published` is never part of the update payload, so the original
    // publication timestamp is preserved.
    $type->update([
        'violation_category_id' => $request->violation_category_id,
        'violation_type' => $request->violation_type,
        'resolution_number' => $request->resolution_number,
        'violation_description' => $request->violation_description,
    ]);

    $this->auditViolationType($request, 'Updated', $type->fresh(), $before);

    return back()->with('success', 'Violation type updated successfully.');
}

public function destroyViolationType(int $id)
{
    $type = ViolationType::findOrFail($id);

    if ($type->violations()->exists()) {
        return back()->with(
            'error',
            'This violation type cannot be deleted because it is already used in violation records.'
        );
    }

    $type->delete();

    return back()->with('success', 'Violation type deleted successfully.');
}
public function storeSanction(Request $request)
{
    $request->validate([
        'violation_type_id' => 'required|exists:violation_type_tbl,violation_type_id',
        'offense_level' => 'required|string|max:50',
        'disciplinary_sanction' => 'required|string',
    ]);

    DisciplinarySanction::create([
        'violation_type_id' => $request->violation_type_id,
        'offense_level' => $request->offense_level,
        'disciplinary_sanction' => $request->disciplinary_sanction,
    ]);

    return back()->with('success', 'Sanction added successfully.');
}

public function updateSanction(Request $request, int $id)
{
    $request->validate([
        'offense_level' => 'required|string|max:50',
        'disciplinary_sanction' => 'required|string',
    ]);

    $sanction = DisciplinarySanction::findOrFail($id);

    $sanction->update([
        'offense_level' => $request->offense_level,
        'disciplinary_sanction' => $request->disciplinary_sanction,
    ]);

    return back()->with('success', 'Sanction updated successfully.');
}

public function destroySanction(int $id)
{
    DisciplinarySanction::findOrFail($id)->delete();

    return back()->with('success', 'Sanction deleted successfully.');
}
}