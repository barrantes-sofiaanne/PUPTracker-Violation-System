<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ViolationCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ViolationCategoryController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | List Categories
    |--------------------------------------------------------------------------
    */

public function index(Request $request)
{
    $categories = ViolationCategory::with([
        'violationTypes'
    ])
    ->orderBy('category_name')
    ->get();

    /*
    |--------------------------------------------------------------------------
    | AJAX Request
    |--------------------------------------------------------------------------
    */

    if ($request->ajax()) {

        return view(
            'admin.violations.configuration.category-accordion',
            compact('categories')
        );

    }

    /*
    |--------------------------------------------------------------------------
    | Fallback
    |--------------------------------------------------------------------------
    */

    return response()->json($categories);
}

    /*
    |--------------------------------------------------------------------------
    | Store Category
    |--------------------------------------------------------------------------
    */

public function store(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'category_name' => [
            'required',
            'string',
            'max:100',
            'unique:violation_category_tbl,category_name',
        ],
    ]);

    if ($validator->fails()) {

        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422);

    }

    $category = ViolationCategory::create([
        'category_name' => trim($request->category_name),
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Violation category added successfully.',
        'category' => $category,
    ]);
}

    /*
    |--------------------------------------------------------------------------
    | Show Category
    |--------------------------------------------------------------------------
    */

    public function show($id): JsonResponse
    {
        $category = ViolationCategory::findOrFail($id);

        return response()->json($category);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Category
    |--------------------------------------------------------------------------
    */

public function update(Request $request, $id): JsonResponse
{
    $category = ViolationCategory::findOrFail($id);

    $validator = Validator::make($request->all(), [
        'category_name' => [
            'required',
            'string',
            'max:100',
            'unique:violation_category_tbl,category_name,' .
            $id .
            ',violation_category_id',
        ],
    ]);

    if ($validator->fails()) {

        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422);

    }

    $category->update([
        'category_name' => trim($request->category_name),
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Category updated successfully.',
        'data' => $category,
    ]);
}

    /*
    |--------------------------------------------------------------------------
    | Delete Category
    |--------------------------------------------------------------------------
    */

    public function destroy($id): JsonResponse
    {
        $category = ViolationCategory::findOrFail($id);

        if ($category->violationTypes()->count() > 0) {

            return response()->json([
                'success' => false,
                'message' => 'This category cannot be deleted because it has violation types.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully.',
        ]);
    }
}