<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gender;
use App\Models\Program;
use App\Models\Section;
use App\Models\Status;
use App\Models\User;
use App\Models\Year;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $students = User::query()
            ->with(['role', 'studentInfo.program', 'studentInfo.year', 'studentInfo.section', 'gender', 'status'])
            ->whereHas('role', function ($query) {
                $query->where('roles_name', 'Student');
            });

        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';

            $students->where(function ($query) use ($search) {
                $query->where('student_number', 'like', $search)
                    ->orWhere('first_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhereHas('studentInfo.program', function ($programQuery) use ($search) {
                        $programQuery->where('program_name', 'like', $search);
                    })
                    ->orWhereHas('studentInfo.year', function ($yearQuery) use ($search) {
                        $yearQuery->where('year', 'like', $search);
                    })
                    ->orWhereHas('studentInfo.section', function ($sectionQuery) use ($search) {
                        $sectionQuery->where('section_name', 'like', $search);
                    });
            });
        }

        $students = $students
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15);

        $programs = Program::orderBy('program_name')->get();
        $years = Year::orderBy('year')->get();
        $sections = Section::orderBy('section_name')->get();
        $genders = Gender::orderBy('gender_name')->get();
        $statuses = Status::orderBy('status_name')->get();

        return view('admin.students.index', compact(
            'students',
            'programs',
            'years',
            'sections',
            'genders',
            'statuses'
        ));
    }

    public function show(string $student_number)
    {
        $student = User::query()
            ->with([
                'studentInfo.program',
                'studentInfo.year',
                'studentInfo.section',
                'studentInfo.studentStatus',
                'gender',
                'status',
                'violations.violationType.violationCategory',
            ])
            ->where('student_number', $student_number)
            ->firstOrFail();

        return view('admin.students.show', compact('student'));
    }

    public function edit($student_number)
    {
        $student = User::query()
            ->with('studentInfo')
            ->where('student_number', $student_number)
            ->firstOrFail();

        $programs = Program::orderBy('program_name')->get();
        $years = Year::orderBy('year')->get();
        $sections = Section::orderBy('section_name')->get();
        $genders = Gender::orderBy('gender_name')->get();
        $statuses = Status::orderBy('status_name')->get();

        return view(
            'admin.students.edit',
            compact(
                'student',
                'programs',
                'years',
                'sections',
                'genders',
                'statuses'
            )
        );
    }

    public function update(Request $request, $student_number)
    {
        $student = User::query()
            ->where('student_number', $student_number)
            ->firstOrFail();

        $request->validate([
            'first_name' => 'required|max:50',
            'middle_name' => 'nullable|max:50',
            'last_name' => 'required|max:50',
            'email' => 'required|email',
            'program_id' => 'required',
            'year_id' => 'required',
            'section_id' => 'required',
            'gender_id' => 'required',
            'status_id' => 'required',
        ]);

        $student->update([
            'first_name' => $request->input('first_name'),
            'middle_name' => $request->input('middle_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'gender_id' => $request->input('gender_id'),
            'status_id' => $request->input('status_id'),
        ]);

        $studentInfo = $student->studentInfo()->firstOrCreate(['user_id' => $student->getKey()]);
        $studentInfo->fill([
            'program_id' => $request->input('program_id'),
            'year_id' => $request->input('year_id'),
            'section_id' => $request->input('section_id'),
        ]);
        $studentInfo->save();

        return redirect()
            ->route('admin.students')
            ->with('success', 'Student updated successfully.');
    }

    public function destroy($student_number)
    {
        $student = User::query()
            ->where('student_number', $student_number)
            ->firstOrFail();

        if ($student->studentInfo) {
            $student->studentInfo->delete();
        }

        $student->delete();

        return redirect()
            ->route('admin.students')
            ->with('success', 'Student deleted successfully.');
    }
}
