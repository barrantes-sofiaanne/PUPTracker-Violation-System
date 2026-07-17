@extends('layouts.admin')

@section('content')

<div class="container-fluid">

    <div class="card shadow-sm">

        <div class="card-header">

            <h4>Students</h4>

        </div>

        <div class="card-body">

            <table class="table table-hover">

                <thead>

                    <tr>

                        <th>Student Number</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Year</th>
                        <th>Section</th>
                        <th>Status</th>
                        <th></th>

                    </tr>

                </thead>

                <tbody>

                @foreach($students as $student)

                    <tr>

                        <td>{{ $student->student_number }}</td>

                        <td>
                            {{ $student->last_name }},
                            {{ $student->first_name }}
                        </td>

                        <td>{{ $student->course?->course_name }}</td>

                        <td>{{ $student->year?->year }}</td>

                        <td>{{ $student->section?->section_name }}</td>

                        <td>{{ $student->status?->status_name }}</td>

                        <td>

                            <a
                                href="{{ route('admin.students.show',$student->student_number) }}"
                                class="btn btn-primary btn-sm">

                                View

                            </a>

                        </td>

                    </tr>

                @endforeach

                </tbody>

            </table>

            {{ $students->links() }}

        </div>

    </div>

</div>

@endsection