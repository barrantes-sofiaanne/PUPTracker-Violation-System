<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DisciplinarySanction;
use App\Models\ViolationType;
use Illuminate\Http\Request;

class DisciplinarySanctionController extends Controller
{
public function index()
{
    $sanctions = DisciplinarySanction::with('violationType.violationCategory')
        ->orderBy('violation_type_id')
        ->orderBy('offense_level')
        ->get();

    if (request()->ajax()) {

        return view(
            'admin.violations.configuration.sanction-list',
            compact('sanctions')
        );

    }

    return response()->json($sanctions);
}
    public function store(Request $request)
    {
        $validated = $request->validate([
            'violation_type_id' => [
                'required',
                'exists:violation_type_tbl,violation_type_id'
            ],

            'offense_level' => [
                'required',
                'string',
                'max:50'
            ],

            'disciplinary_sanction' => [
                'required',
                'string'
            ]
        ]);

        $sanction = DisciplinarySanction::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Disciplinary sanction added successfully.',
            'data' => $sanction
        ]);
    }

public function show($id)
{
    $sanction = DisciplinarySanction::findOrFail($id);

    return response()->json([
        'disciplinary_sanction_id' => $sanction->disciplinary_sanction_id,
        'violation_type_id'        => $sanction->violation_type_id,
        'offense_level'            => $sanction->offense_level,
        'disciplinary_sanction'    => $sanction->disciplinary_sanction,
    ]);
}

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'violation_type_id' => [
                'required',
                'exists:violation_type_tbl,violation_type_id'
            ],

            'offense_level' => [
                'required',
                'string',
                'max:50'
            ],

            'disciplinary_sanction' => [
                'required',
                'string'
            ]
        ]);

        $sanction = DisciplinarySanction::findOrFail($id);

        $sanction->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Disciplinary sanction updated successfully.'
        ]);
    }

public function destroy($id)
{
    $sanction = DisciplinarySanction::findOrFail($id);

    $sanction->delete();

    return response()->json([
        'success' => true,
        'message' => 'Disciplinary sanction deleted successfully.'
    ]);
}
}