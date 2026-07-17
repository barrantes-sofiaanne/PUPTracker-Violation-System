<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Year;
use App\Models\Status;
use App\Models\Section;
use App\Models\Gender;
class StudentController extends Controller
{
public function index(Request $request)
{
    $students = User::query()
        ->whereHas('role', function ($q) {
            $q->where('roles_name', 'Student');
        });

    if ($request->filled('search')) {

        $search = $request->search;

        $students->where(function ($q) use ($search) {
           
        });
    }

    $students = $students
        ->orderBy('last_name')
        ->paginate(15);
$courses = Course::orderBy('course_name')->get();

$years = Year::orderBy('year')->get();

$sections = Section::orderBy('section_name')->get();

$genders = Gender::orderBy('gender_name')->get();

$statuses = Status::orderBy('status_name')->get();
    return view('admin.students.index', compact(
        'students',
        'courses',
        'years',
        'sections',
        'genders',
        'statuses'
    ));
}
public function show($student_number)
{
    $student = User::with([
        'course',
        'year',
        'section',
        'gender',
        'status',
        'violations.violationType.violationCategory'
    ])
    ->where('student_number', $student_number)
    ->firstOrFail();

    return view(
        'admin.students.show',
        compact('student')
    );
}
public function edit($student_number)
{
    $student = User::where(
        'student_number',
        $student_number
    )->firstOrFail();

    $courses = Course::orderBy('course_name')->get();

    $years = Year::orderBy('year')->get();

    $sections = Section::orderBy('section_name')->get();

    $genders = Gender::orderBy('gender_name')->get();

    $statuses = Status::orderBy('status_name')->get();

    return view(
        'admin.students.edit',
        compact(
            'student',
            'courses',
            'years',
            'sections',
            'genders',
            'statuses'
        )
    );
}
public function update(Request $request, $student_number)
{
    $student = User::where(
        'student_number',
        $student_number
    )->firstOrFail();

    $request->validate([

        'first_name' => 'required|max:50',
        'middle_name' => 'nullable|max:50',
        'last_name' => 'required|max:50',

        'email' => 'required|email',

        'course_id' => 'required',
        'year_id' => 'required',
        'section_id' => 'required',
        'gender_id' => 'required',
        'status_id' => 'required',

    ]);

    $student->update([

        'first_name' => $request->first_name,
        'middle_name' => $request->middle_name,
        'last_name' => $request->last_name,

        'email' => $request->email,

        'course_id' => $request->course_id,
        'year_id' => $request->year_id,
        'section_id' => $request->section_id,
        'gender_id' => $request->gender_id,
        'status_id' => $request->status_id,

    ]);

    return redirect()
        ->route('admin.students')
        ->with(
            'success',
            'Student updated successfully.'
        );
}
public function destroy($student_number)
{
    $student = User::where(
        'student_number',
        $student_number
    )->firstOrFail();

    $student->delete();

    return redirect()
        ->route('admin.students')
        ->with(
            'success',
            'Student deleted successfully.'
        );
}
}
