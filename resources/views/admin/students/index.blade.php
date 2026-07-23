@extends('layouts.admin')

@section('content')
<div class="container-fluid">
   <div class="page-header-modern d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
       <div>
           <h3 class="mb-1">Student Management</h3>
           <p class="mb-0">Browse, search, and update student profiles while preserving their academic record.</p>
       </div>
       <div class="small">
           Showing {{ $students->firstItem() ?? 0 }}-{{ $students->lastItem() ?? 0 }} of {{ $students->total() }} students
       </div>
   </div>

   <div class="card shadow-sm border-0">
       <div class="card-body">
           <form method="GET" class="row g-2 align-items-end">
               <div class="col-md-8 col-lg-9">
                   <label class="form-label small text-muted">Search</label>
                   <input
                       type="text"
                       name="search"
                       class="form-control"
                       placeholder="Search by student number, name, program, year, or section"
                       value="{{ request('search') }}">
               </div>
               <div class="col-md-4 col-lg-3">
                   <button class="btn btn-primary w-100">Search</button>
               </div>
           </form>
       </div>
   </div>

   <div class="card shadow-sm border-0 mt-3">
       <div class="card-body p-0">
           <div class="table-responsive">
               <table class="table table-hover align-middle mb-0">
                   <thead class="table-light">
                       <tr>
                           <th>Student Number</th>
                           <th>Name</th>
                           <th>Program</th>
                           <th>Year</th>
                           <th>Section</th>
                           <th class="text-end">Action</th>
                       </tr>
                   </thead>
                   <tbody>
                       @forelse($students as $student)
                           <tr>
                               <td class="fw-semibold">{{ $student->student_number }}</td>
                               <td>
                                   <div>{{ $student->last_name }}, {{ $student->first_name }} {{ $student->middle_name }}</div>
                                   <div class="small text-muted">{{ $student->email }}</div>
                               </td>
                               <td>{{ $student->program?->program_name ?? '—' }}</td>
                               <td>{{ $student->year?->year ?? '—' }}</td>
                               <td>{{ $student->section?->section_name ?? '—' }}</td>
                               <td class="text-end">
                                   <button
                                       class="btn btn-outline-primary btn-sm editStudentBtn"
                                       data-id="{{ $student->student_number }}"
                                       data-first="{{ $student->first_name }}"
                                       data-middle="{{ $student->middle_name }}"
                                       data-last="{{ $student->last_name }}"
                                       data-email="{{ $student->email }}"
                                       data-program="{{ $student->program_id }}"
                                       data-year="{{ $student->year_id }}"
                                       data-section="{{ $student->section_id }}"
                                       data-gender="{{ $student->gender_id }}"
                                       data-status="{{ $student->status_id }}">
                                       Edit
                                   </button>
                                   <button
                                       class="btn btn-outline-danger btn-sm deleteStudentBtn"
                                       data-id="{{ $student->student_number }}"
                                       data-name="{{ $student->first_name }} {{ $student->last_name }}">
                                       Delete
                                   </button>
                               </td>
                           </tr>
                       @empty
                           <tr>
                               <td colspan="6" class="text-center py-4 text-muted">
                                   No students found.
                               </td>
                           </tr>
                       @endforelse
                   </tbody>
               </table>
           </div>
       </div>
   </div>

   <div class="mt-3">
       {{ $students->appends(request()->query())->links() }}
   </div>
</div>

<div class="modal fade" id="editStudentModal" tabindex="-1">
   <div class="modal-dialog modal-lg">
       <div class="modal-content">
           <form id="editStudentForm" method="POST">
               @csrf
               @method('PUT')

               <div class="modal-header">
                   <h5 class="modal-title">Edit Student</h5>
                   <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>

               <div class="modal-body">
                   <div class="row g-3">
                       <div class="col-md-6">
                           <label class="form-label">Student Number</label>
                           <input id="student_number" class="form-control" readonly>
                       </div>
                       <div class="col-md-6">
                           <label class="form-label">First Name</label>
                           <input type="text" name="first_name" id="first_name" class="form-control">
                       </div>
                       <div class="col-md-6">
                           <label class="form-label">Middle Name</label>
                           <input type="text" name="middle_name" id="middle_name" class="form-control">
                       </div>
                       <div class="col-md-6">
                           <label class="form-label">Last Name</label>
                           <input type="text" name="last_name" id="last_name" class="form-control">
                       </div>
                       <div class="col-md-6">
                           <label class="form-label">Email</label>
                           <input type="email" name="email" id="email" class="form-control">
                       </div>
                       <div class="col-md-6">
                           <label class="form-label">Program</label>
                           <select name="program_id" id="program_id" class="form-select">
                               @foreach($programs as $program)
                                   <option value="{{ $program->program_id }}">{{ $program->program_name }}</option>
                               @endforeach
                           </select>
                       </div>
                       <div class="col-md-6">
                           <label class="form-label">Year</label>
                           <select name="year_id" id="year_id" class="form-select">
                               @foreach($years as $year)
                                   <option value="{{ $year->year_id }}">{{ $year->year }}</option>
                               @endforeach
                           </select>
                       </div>
                       <div class="col-md-6">
                           <label class="form-label">Section</label>
                           <select name="section_id" id="section_id" class="form-select">
                               @foreach($sections as $section)
                                   <option value="{{ $section->section_id }}">{{ $section->section_name }}</option>
                               @endforeach
                           </select>
                       </div>
                       <div class="col-md-6">
                           <label class="form-label">Gender</label>
                           <select name="gender_id" id="gender_id" class="form-select">
                               @foreach($genders as $gender)
                                   <option value="{{ $gender->gender_id }}">{{ $gender->gender_name }}</option>
                               @endforeach
                           </select>
                       </div>
                       <div class="col-md-6">
                           <label class="form-label">Status</label>
                           <select name="status_id" id="status_id" class="form-select">
                               @foreach($statuses as $status)
                                   <option value="{{ $status->status_id }}">{{ $status->status_name }}</option>
                               @endforeach
                           </select>
                       </div>
                   </div>
               </div>

               <div class="modal-footer">
                   <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                   <button class="btn btn-primary">Update Student</button>
               </div>
           </form>
       </div>
   </div>
</div>

<div class="modal fade" id="deleteStudentModal" tabindex="-1">
   <div class="modal-dialog">
       <div class="modal-content">
           <form method="POST" id="deleteStudentForm">
               @csrf
               @method('DELETE')

               <div class="modal-header">
                   <h5 class="modal-title">Delete Student</h5>
                   <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>

               <div class="modal-body">
                   <p>Are you sure you want to delete <strong id="studentDeleteName"></strong>?</p>
               </div>

               <div class="modal-footer">
                   <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                   <button class="btn btn-danger">Delete</button>
               </div>
           </form>
       </div>
   </div>
</div>

<script>
   document.querySelectorAll('.editStudentBtn').forEach(function (button) {
       button.addEventListener('click', function () {
           document.getElementById('student_number').value = this.dataset.id;
           document.getElementById('first_name').value = this.dataset.first;
           document.getElementById('middle_name').value = this.dataset.middle;
           document.getElementById('last_name').value = this.dataset.last;
           document.getElementById('email').value = this.dataset.email;
           document.getElementById('program_id').value = this.dataset.program;
           document.getElementById('year_id').value = this.dataset.year;
           document.getElementById('section_id').value = this.dataset.section;
           document.getElementById('gender_id').value = this.dataset.gender;
           document.getElementById('status_id').value = this.dataset.status;

           document.getElementById('editStudentForm').action = '/admin/students/' + this.dataset.id;
           new bootstrap.Modal(document.getElementById('editStudentModal')).show();
       });
   });

   document.querySelectorAll('.deleteStudentBtn').forEach(function (button) {
       button.addEventListener('click', function () {
           document.getElementById('studentDeleteName').textContent = this.dataset.name;
           document.getElementById('deleteStudentForm').action = '/admin/students/' + this.dataset.id;
           new bootstrap.Modal(document.getElementById('deleteStudentModal')).show();
       });
   });
</script>

@endsection