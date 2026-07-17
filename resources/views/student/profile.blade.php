@extends('layouts.app')

@section('title', 'Student Profile')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/student.css') }}">
@endpush

@section('content')

<div class="dashboard-wrapper">


    <main class="main-content">


        <div class="container-fluid py-4">

            <div class="card shadow-sm border-0">

                <div class="card-header">

                    <h4 class="mb-0">

                        Student Profile

                    </h4>

                </div>

                <div class="card-body">

                    <div class="row">

                        <div class="col-md-3 text-center">

                            <img
                                src="{{ asset('assets/images/default-profile.png') }}"
                                class="img-fluid rounded-circle mb-3"
                                style="width:180px; height:180px; object-fit:cover;">

                        </div>

                        <div class="col-md-9">

                            <table class="table table-borderless">

                                <tr>
                                    <th width="200">Student Number</th>
                                    <td>{{ $user->student_number }}</td>
                                </tr>

                                <tr>
                                    <th>First Name</th>
                                    <td>{{ $user->first_name }}</td>
                                </tr>

                                <tr>
                                    <th>Middle Name</th>
                                    <td>{{ $user->middle_name }}</td>
                                </tr>

                                <tr>
                                    <th>Last Name</th>
                                    <td>{{ $user->last_name }}</td>
                                </tr>

                                <tr>
                                    <th>Email</th>
                                    <td>{{ $user->email }}</td>
                                </tr>

                                <tr>
                                    <th>Course</th>
                                    <td>{{ optional($user->course)->course_name }}</td>
                                </tr>

                                <tr>
                                    <th>Year</th>
                                    <td>{{ $user->year->year ?? '-' }}</td>
                                </tr>

                                <tr>
                                    <th>Section</th>
                                    <td>{{ optional($user->section)->section_name }}</td>
                                </tr>

                                <tr>
                                    <th>Gender</th>
                                    <td>{{ optional($user->gender)->gender_name }}</td>
                                </tr>

                                <tr>
                                    <th>Status</th>
                                    <td>{{ optional($user->status)->status_name }}</td>
                                </tr>

                            </table>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

@endsection