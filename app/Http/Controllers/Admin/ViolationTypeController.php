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

        'severity_level' => [
            'required',
            'integer'
        ],

    ]);

    $type = ViolationType::create($validated);

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

          'severity_level' => [
    'required',
    'integer'
],

        ]);

        $type->update($validated);

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