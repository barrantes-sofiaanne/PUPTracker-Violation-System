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

public function updateCategory(Request $request, $id)
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

public function destroyCategory($id)
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
        'severity_level' => 'nullable|string|max:100',
    ]);

    ViolationType::create([
        'violation_category_id' => $request->violation_category_id,
        'violation_type' => $request->violation_type,
        'resolution_number' => $request->resolution_number,
        'violation_description' => $request->violation_description,
        'severity_level' => $request->severity_level,
    ]);

    return back()->with('success', 'Violation type added successfully.');
}

public function updateViolationType(Request $request, $id)
{
    $request->validate([
        'violation_category_id' => 'required|exists:violation_category_tbl,violation_category_id',
        'violation_type' => 'required|string|max:255',
        'resolution_number' => 'nullable|string|max:100',
        'violation_description' => 'nullable|string',
        'severity_level' => 'nullable|string|max:100',
    ]);

    $type = ViolationType::findOrFail($id);

    $type->update([
        'violation_category_id' => $request->violation_category_id,
        'violation_type' => $request->violation_type,
        'resolution_number' => $request->resolution_number,
        'violation_description' => $request->violation_description,
        'severity_level' => $request->severity_level,
    ]);

    return back()->with('success', 'Violation type updated successfully.');
}

public function destroyViolationType($id)
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

public function updateSanction(Request $request, $id)
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

public function destroySanction($id)
{
    DisciplinarySanction::findOrFail($id)->delete();

    return back()->with('success', 'Sanction deleted successfully.');
}
}