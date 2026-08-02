@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="page-header-modern mb-3">
        <div>
            <h3 class="mb-1">User Management</h3>
            <p class="mb-0">Manage student, admin, and security accounts in one place.</p>
        </div>
    </div>

    <div class="card shadow-sm user-management-shell">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="bi bi-people-fill me-2"></i>
                    User Management
                </h4>
            </div>

            <ul class="nav nav-tabs mt-3 user-management-tabs" id="userManagementTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ request('tab', 'students') === 'students' ? 'active' : '' }}" id="students-tab" data-bs-toggle="tab" data-bs-target="#students-pane" type="button" role="tab">
                        <i class="bi bi-mortarboard-fill me-1"></i>
                        Students
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ request('tab') === 'admins' ? 'active' : '' }}" id="admins-tab" data-bs-toggle="tab" data-bs-target="#admins-pane" type="button" role="tab">
                        <i class="bi bi-person-badge-fill me-1"></i>
                        Admins
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ request('tab') === 'security' ? 'active' : '' }}" id="security-tab" data-bs-toggle="tab" data-bs-target="#security-pane" type="button" role="tab">
                        <i class="bi bi-person-workspace me-1"></i>
                        Security
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body">
            <div class="tab-content user-management-content">
                <div class="tab-pane fade {{ request('tab', 'students') === 'students' ? 'show active' : '' }}" id="students-pane" role="tabpanel">
                    @php
                        $studentFiltersActive = request()->filled('filter_program_id')
                            || request()->filled('filter_year_id')
                            || request()->filled('filter_section_id')
                            || request()->filled('filter_gender_id')
                            || request()->filled('filter_status_id');
                    @endphp
                    <form method="GET" action="{{ route('admin.students') }}" class="mb-4 user-management-toolbar">
                        <input type="hidden" name="tab" value="students">
                        <div class="d-flex flex-column flex-xl-row gap-3 align-items-stretch align-items-xl-center">
                            <div class="flex-grow-1">
                                <div class="input-group user-management-search">
                                    <span class="input-group-text bg-white">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input
                                        type="text"
                                        name="search"
                                        class="form-control"
                                        placeholder="Search by Student Number or Name..."
                                        value="{{ request('search') }}">
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 justify-content-xl-end user-management-actions">
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#studentFiltersCollapse"
                                    aria-expanded="{{ $studentFiltersActive ? 'true' : 'false' }}"
                                    aria-controls="studentFiltersCollapse">
                                    <i class="bi bi-funnel me-1"></i>
                                    Filter
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importAccountsModal" data-import-account-type="student">
                                    <i class="bi bi-box-arrow-in-down-right me-1"></i>
                                    Import
                                </button>
                                <a href="{{ route('admin.user-management-history') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>
                                    History
                                </a>
                                <a href="{{ route('admin.students', ['tab' => 'students']) }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-repeat me-1"></i>
                                    Refresh
                                </a>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#createStudentModal">
                                    <i class="bi bi-plus-lg me-1"></i>
                                    Add Student
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    Search
                                </button>
                            </div>
                        </div>

                        <div class="collapse mt-3 {{ $studentFiltersActive ? 'show' : '' }}" id="studentFiltersCollapse">
                            <div class="student-filter-panel">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label mb-1">Program</label>
                                        <select name="filter_program_id" class="form-select">
                                            <option value="">All Programs</option>
                                            @foreach($programs as $program)
                                                <option value="{{ $program->program_id }}" {{ (string) request('filter_program_id') === (string) $program->program_id ? 'selected' : '' }}>
                                                    {{ $program->program_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Year</label>
                                        <select name="filter_year_id" class="form-select">
                                            <option value="">All Years</option>
                                            @foreach($years as $year)
                                                <option value="{{ $year->year_id }}" {{ (string) request('filter_year_id') === (string) $year->year_id ? 'selected' : '' }}>
                                                    {{ $year->year }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Section</label>
                                        <select name="filter_section_id" class="form-select">
                                            <option value="">All Sections</option>
                                            @foreach($sections as $section)
                                                <option value="{{ $section->section_id }}" {{ (string) request('filter_section_id') === (string) $section->section_id ? 'selected' : '' }}>
                                                    {{ $section->section_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Gender</label>
                                        <select name="filter_gender_id" class="form-select">
                                            <option value="">All Genders</option>
                                            @foreach($genders as $gender)
                                                <option value="{{ $gender->gender_id }}" {{ (string) request('filter_gender_id') === (string) $gender->gender_id ? 'selected' : '' }}>
                                                    {{ $gender->gender_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Status</label>
                                        <select name="filter_status_id" class="form-select">
                                            <option value="">All Statuses</option>
                                            @foreach($statuses as $status)
                                                <option value="{{ $status->status_id }}" {{ (string) request('filter_status_id') === (string) $status->status_id ? 'selected' : '' }}>
                                                    {{ $status->status_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a
                                        href="{{ route('admin.students', array_filter(['tab' => 'students', 'search' => request('search')])) }}"
                                        class="btn btn-outline-secondary btn-sm">
                                        Clear Filters
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive user-management-table">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Student Number</th>
                                    <th>Last Name</th>
                                    <th>First Name</th>
                                    <th>Middle Name</th>
                                    <th>Email</th>
                                    <th>Program</th>
                                    <th>Year</th>
                                    <th>Section</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($students as $student)
                                    <tr>
                                        <td class="fw-semibold">{{ $student->student_number }}</td>
                                        <td>{{ $student->last_name }}</td>
                                        <td>{{ $student->first_name }}</td>
                                        <td>{{ $student->middle_name ?: '—' }}</td>
                                        <td>{{ $student->email }}</td>
                                        <td>
                                            {{ $student->program?->program_name ?? '—' }}
                                            @if((int) ($student->studentInfo?->ladderized ?? 0) === 1)
                                                - ladderized program
                                            @endif
                                        </td>
                                        <td>{{ $student->year?->year ?? '—' }}</td>
                                        <td>{{ $student->section?->section_name ?? '—' }}</td>
                                        <td>
                                            @php
                                                $statusName = strtoupper((string) optional($student->status)->status_name);
                                            @endphp
                                            @if($statusName === 'ACTIVE')
                                                <span class="badge bg-success user-status-badge">ACTIVE</span>
                                            @elseif($statusName === 'INACTIVE')
                                                <span class="badge bg-secondary user-status-badge">INACTIVE</span>
                                            @else
                                                <span class="badge bg-info text-dark user-status-badge">{{ $statusName !== '' ? $statusName : 'UNKNOWN' }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end user-action-cell">
                                            <div class="user-action-group">
                                                <button
                                                    class="btn btn-primary btn-sm user-action-btn editStudentBtn"
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
                                                    <i class="bi bi-pencil-square me-1"></i>
                                                    Edit
                                                </button>
                                                <button
                                                    class="btn btn-danger btn-sm user-action-btn deleteStudentBtn"
                                                    data-id="{{ $student->student_number }}"
                                                    data-name="{{ $student->first_name }} {{ $student->last_name }}">
                                                    <i class="bi bi-trash me-1"></i>
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center py-4 text-muted user-management-empty">No students found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $students->appends(request()->query())->links() }}
                    </div>
                </div>

                <div class="tab-pane fade {{ request('tab') === 'admins' ? 'show active' : '' }}" id="admins-pane" role="tabpanel">
                    <form method="GET" action="{{ route('admin.students') }}" class="mb-4 user-management-toolbar">
                        <input type="hidden" name="tab" value="admins">
                        <div class="d-flex flex-column flex-xl-row gap-3 align-items-stretch align-items-xl-center">
                            <div class="flex-grow-1">
                                <div class="input-group user-management-search">
                                    <span class="input-group-text bg-white">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" name="admin_search" class="form-control" placeholder="Search admin by name or email..." value="{{ request('admin_search') }}">
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 justify-content-xl-end user-management-actions">
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importAccountsModal" data-import-account-type="admin">
                                    <i class="bi bi-box-arrow-in-down-right me-1"></i>
                                    Import
                                </button>
                                <a href="{{ route('admin.students', ['tab' => 'admins']) }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-repeat me-1"></i>
                                    Refresh
                                </a>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#createAdminModal">
                                    <i class="bi bi-plus-lg me-1"></i>
                                    Add Admin
                                </button>
                                <button type="submit" class="btn btn-primary">Search</button>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive user-management-table">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Position</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($admins as $admin)
                                    <tr>
                                        <td>{{ trim(($admin->adminInfo->firstname ?? '') . ' ' . ($admin->adminInfo->lastname ?? '')) ?: '—' }}</td>
                                        <td>{{ $admin->email }}</td>
                                        <td>{{ $admin->adminInfo->Position ?? 'Admin' }}</td>
                                        <td>{{ $adminRoles->firstWhere('roles_id', $admin->adminInfo->role_id ?? 1)->roles_name ?? 'Admin' }}</td>
                                        <td>
                                            @php
                                                $adminStatus = strtoupper((string) optional($statuses->firstWhere('status_id', $admin->adminInfo->status_id ?? 1))->status_name);
                                            @endphp
                                            <span class="badge user-status-badge {{ $adminStatus === 'ACTIVE' ? 'bg-success' : 'bg-secondary' }}">{{ $adminStatus !== '' ? $adminStatus : 'ACTIVE' }}</span>
                                        </td>
                                        <td class="text-end user-action-cell">
                                            <div class="user-action-group">
                                                <button
                                                    class="btn btn-primary btn-sm user-action-btn editAdminBtn"
                                                    data-id="{{ $admin->id }}"
                                                    data-email="{{ $admin->email }}"
                                                    data-firstname="{{ $admin->adminInfo->firstname ?? '' }}"
                                                    data-middlename="{{ $admin->adminInfo->middlename ?? '' }}"
                                                    data-lastname="{{ $admin->adminInfo->lastname ?? '' }}"
                                                    data-position="{{ $admin->adminInfo->Position ?? 'Admin' }}"
                                                    data-status="{{ $admin->adminInfo->status_id ?? 1 }}"
                                                    data-role="{{ $admin->adminInfo->role_id ?? 1 }}">
                                                    <i class="bi bi-pencil-square me-1"></i>Edit
                                                </button>
                                                <button class="btn btn-danger btn-sm user-action-btn deleteAdminBtn" data-id="{{ $admin->id }}" data-name="{{ $admin->email }}">
                                                    <i class="bi bi-trash me-1"></i>Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted user-management-empty">No admin accounts found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $admins->appends(request()->query())->links() }}
                    </div>
                </div>

                <div class="tab-pane fade {{ request('tab') === 'security' ? 'show active' : '' }}" id="security-pane" role="tabpanel">
                    <form method="GET" action="{{ route('admin.students') }}" class="mb-4 user-management-toolbar">
                        <input type="hidden" name="tab" value="security">
                        <div class="d-flex flex-column flex-xl-row gap-3 align-items-stretch align-items-xl-center">
                            <div class="flex-grow-1">
                                <div class="input-group user-management-search">
                                    <span class="input-group-text bg-white">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" name="security_search" class="form-control" placeholder="Search security by name, email, contact, or address..." value="{{ request('security_search') }}">
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 justify-content-xl-end user-management-actions">
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importAccountsModal" data-import-account-type="security">
                                    <i class="bi bi-box-arrow-in-down-right me-1"></i>
                                    Import
                                </button>
                                <a href="{{ route('admin.students', ['tab' => 'security']) }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-repeat me-1"></i>
                                    Refresh
                                </a>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#createSecurityModal">
                                    <i class="bi bi-plus-lg me-1"></i>
                                    Add Security
                                </button>
                                <button type="submit" class="btn btn-primary">Search</button>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive user-management-table">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Contact Number</th>
                                    <th>Address</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($securities as $security)
                                    @php
                                        $securityFirstName = $security->securityInfo->firstname
                                            ?? $security->securityProfile->firstname
                                            ?? '';
                                        $securityMiddleName = $security->securityInfo->middlename
                                            ?? $security->securityProfile->middlename
                                            ?? '';
                                        $securityLastName = $security->securityInfo->lastname
                                            ?? $security->securityProfile->lastname
                                            ?? '';
                                        $securityFullName = trim($securityFirstName . ' ' . $securityMiddleName . ' ' . $securityLastName);
                                    @endphp
                                    <tr>
                                        <td>{{ $securityFullName ?: '—' }}</td>
                                        <td>{{ $security->email }}</td>
                                        <td>{{ $security->securityInfo->contact_number ?? '—' }}</td>
                                        <td>{{ $security->securityInfo->address ?? '—' }}</td>
                                        <td><span class="badge bg-success user-status-badge">ACTIVE</span></td>
                                        <td class="text-end user-action-cell">
                                            <div class="user-action-group">
                                                <button
                                                    class="btn btn-primary btn-sm user-action-btn editSecurityBtn"
                                                    data-id="{{ $security->id }}"
                                                    data-email="{{ $security->email }}"
                                                    data-firstname="{{ $securityFirstName }}"
                                                    data-middlename="{{ $securityMiddleName }}"
                                                    data-lastname="{{ $securityLastName }}"
                                                    data-contact="{{ $security->securityInfo->contact_number ?? '' }}"
                                                    data-address="{{ $security->securityInfo->address ?? '' }}">
                                                    <i class="bi bi-pencil-square me-1"></i>Edit
                                                </button>
                                                <button class="btn btn-danger btn-sm user-action-btn deleteSecurityBtn" data-id="{{ $security->id }}" data-name="{{ $security->email }}">
                                                    <i class="bi bi-trash me-1"></i>Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted user-management-empty">No security accounts found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $securities->appends(request()->query())->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <form method="POST" action="{{ route('admin.students.store') }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Create Student Account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6"><label class="form-label">Student Number</label><input name="student_number" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">First Name</label><input name="first_name" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Middle Name</label><input name="middle_name" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Last Name</label><input name="last_name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Program</label><select name="program_id" class="form-select" required>@foreach($programs as $program)<option value="{{ $program->program_id }}">{{ $program->program_name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Year</label><select name="year_id" class="form-select" required>@foreach($years as $year)<option value="{{ $year->year_id }}">{{ $year->year }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Section</label><select name="section_id" class="form-select" required>@foreach($sections as $section)<option value="{{ $section->section_id }}">{{ $section->section_name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Gender</label><select name="gender_id" class="form-select" required>@foreach($genders as $gender)<option value="{{ $gender->gender_id }}">{{ $gender->gender_name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Status</label><select name="status_id" class="form-select" required>@foreach($statuses as $status)<option value="{{ $status->status_id }}">{{ $status->status_name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Password</label><input name="password" type="password" class="form-control" required></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create Student</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="createAdminModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('admin.users.admins.store') }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Create Admin Account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="row g-3">
                <div class="col-12"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">First Name</label><input name="firstname" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Middle Name</label><input name="middlename" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Last Name</label><input name="lastname" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Position</label><input name="position" class="form-control" value="Admin"></div>
                <div class="col-md-6"><label class="form-label">Role</label><select name="role_id" class="form-select" required>@foreach($adminRoles as $role)<option value="{{ $role->roles_id }}">{{ $role->roles_name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Status</label><select name="status_id" class="form-select" required>@foreach($statuses as $status)<option value="{{ $status->status_id }}">{{ $status->status_name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Password</label><input name="password" type="password" class="form-control" required></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create Admin</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="createSecurityModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('admin.users.security.store') }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Create Security Account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="row g-3">
                <div class="col-12"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">First Name</label><input name="firstname" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Middle Name</label><input name="middlename" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Last Name</label><input name="lastname" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Contact Number</label><input name="contact_number" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Password</label><input name="password" type="password" class="form-control" required></div>
                <div class="col-12"><label class="form-label">Address</label><input name="address" class="form-control"></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create Security</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="editStudentModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form id="editStudentForm" method="POST">@csrf @method('PUT')
        <div class="modal-header"><h5 class="modal-title">Edit Student</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="row g-3">
            <div class="col-md-6"><label class="form-label">Student Number</label><input id="student_number" class="form-control" readonly></div>
            <div class="col-md-6"><label class="form-label">First Name</label><input type="text" name="first_name" id="first_name" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Middle Name</label><input type="text" name="middle_name" id="middle_name" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" name="last_name" id="last_name" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="email" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Program</label><select name="program_id" id="program_id" class="form-select">@foreach($programs as $program)<option value="{{ $program->program_id }}">{{ $program->program_name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Year</label><select name="year_id" id="year_id" class="form-select">@foreach($years as $year)<option value="{{ $year->year_id }}">{{ $year->year }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Section</label><select name="section_id" id="section_id" class="form-select">@foreach($sections as $section)<option value="{{ $section->section_id }}">{{ $section->section_name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Gender</label><select name="gender_id" id="gender_id" class="form-select">@foreach($genders as $gender)<option value="{{ $gender->gender_id }}">{{ $gender->gender_name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Status</label><select name="status_id" id="status_id" class="form-select">@foreach($statuses as $status)<option value="{{ $status->status_id }}">{{ $status->status_name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">New Password (optional)</label><input type="password" name="password" class="form-control"></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Update Student</button></div>
    </form>
</div></div></div>

<div class="modal fade" id="deleteStudentModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" id="deleteStudentForm">@csrf @method('DELETE')
        <div class="modal-header"><h5 class="modal-title">Delete Student</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><p>Are you sure you want to delete <strong id="studentDeleteName"></strong>?</p></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Delete</button></div>
    </form>
</div></div></div>

<div class="modal fade" id="editAdminModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" id="editAdminForm">@csrf @method('PUT')
        <div class="modal-header"><h5 class="modal-title">Edit Admin</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="row g-3">
            <div class="col-12"><label class="form-label">Email</label><input name="email" id="admin_email" type="email" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">First Name</label><input name="firstname" id="admin_firstname" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Middle Name</label><input name="middlename" id="admin_middlename" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Last Name</label><input name="lastname" id="admin_lastname" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Position</label><input name="position" id="admin_position" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Role</label><select name="role_id" id="admin_role_id" class="form-select">@foreach($adminRoles as $role)<option value="{{ $role->roles_id }}">{{ $role->roles_name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Status</label><select name="status_id" id="admin_status_id" class="form-select">@foreach($statuses as $status)<option value="{{ $status->status_id }}">{{ $status->status_name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">New Password (optional)</label><input name="password" type="password" class="form-control"></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Update Admin</button></div>
    </form>
</div></div></div>

<div class="modal fade" id="deleteAdminModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" id="deleteAdminForm">@csrf @method('DELETE')
        <div class="modal-header"><h5 class="modal-title">Delete Admin</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><p>Are you sure you want to delete <strong id="adminDeleteName"></strong>?</p></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Delete</button></div>
    </form>
</div></div></div>

<div class="modal fade" id="editSecurityModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" id="editSecurityForm">@csrf @method('PUT')
        <div class="modal-header"><h5 class="modal-title">Edit Security</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="row g-3">
            <div class="col-12"><label class="form-label">Email</label><input name="email" id="security_email" type="email" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">First Name</label><input name="firstname" id="security_firstname" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Middle Name</label><input name="middlename" id="security_middlename" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Last Name</label><input name="lastname" id="security_lastname" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Contact Number</label><input name="contact_number" id="security_contact" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">New Password (optional)</label><input name="password" type="password" class="form-control"></div>
            <div class="col-12"><label class="form-label">Address</label><input name="address" id="security_address" class="form-control"></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Update Security</button></div>
    </form>
</div></div></div>

<div class="modal fade" id="deleteSecurityModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" id="deleteSecurityForm">@csrf @method('DELETE')
        <div class="modal-header"><h5 class="modal-title">Delete Security</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><p>Are you sure you want to delete <strong id="securityDeleteName"></strong>?</p></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Delete</button></div>
    </form>
</div></div></div>

<div class="modal fade" id="importAccountsModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content import-modal-content">
    <form method="POST" action="{{ route('admin.users.import') }}" enctype="multipart/form-data">
        @csrf
        <div class="modal-header import-modal-header">
            <h5 class="modal-title mb-0" id="importModalTitle">Import Student Data</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body import-modal-body">
            <p class="import-modal-subtitle">Choose an import action and upload your file.</p>
            <input type="hidden" name="account_type" id="importAccountType" value="student">
            <input type="hidden" name="import_action" id="importAction" value="create_activate">

            <div class="import-action-grid">
                <button type="button" class="import-action-card active" id="importCreateActivateCard" data-import-action="create_activate">
                    <i class="bi bi-person-plus-fill import-action-icon"></i>
                    <h6 class="import-action-title mb-2">Import &amp; Activate</h6>
                    <p class="import-action-text mb-3">Upload a list of new accounts to create and activate.</p>
                    <a
                        href="{{ route('admin.users.import.template', ['account_type' => 'student', 'import_action' => 'create_activate']) }}"
                        id="downloadImportTemplateLink"
                        class="btn btn-sm btn-light import-template-btn">
                        <i class="bi bi-download me-1"></i>
                        Download Template
                    </a>
                </button>

                <button type="button" class="import-action-card" id="importDeactivateCard" data-import-action="deactivate_students">
                    <i class="bi bi-person-slash import-action-icon"></i>
                    <h6 class="import-action-title mb-2">Deactivate Students</h6>
                    <p class="import-action-text mb-3">Upload a list of student numbers to set accounts to Inactive.</p>
                    <a
                        href="{{ route('admin.users.import.template', ['account_type' => 'student', 'import_action' => 'deactivate_students']) }}"
                        id="downloadDeactivateTemplateLink"
                        class="btn btn-sm btn-light import-template-btn"
                        title="Download deactivation template">
                        <i class="bi bi-download"></i>
                    </a>
                </button>
            </div>

            <label for="importFileInput" class="import-dropzone mt-3" id="importDropzone">
                <input id="importFileInput" type="file" name="import_file" class="d-none" accept=".xlsx,.xls,.csv" required>
                <span class="import-dropzone-icon"><i class="bi bi-cloud-arrow-up-fill"></i></span>
                <span class="import-dropzone-text">Drag &amp; drop your CSV/XLSX file here, or click to select a file.</span>
                <small class="text-muted mt-2" id="importSelectedFileName">No file selected</small>
            </label>

            <div class="import-help-block mt-2" id="importHelpBlock">
                <ul id="importRequiredColumns" class="import-required-columns mb-1"></ul>
                <small id="importHelpText" class="text-muted d-block"></small>
            </div>
        </div>
        <div class="modal-footer import-modal-footer">
            <button type="submit" id="importSubmitButton" class="btn btn-primary w-100" disabled>Import Data</button>
        </div>
    </form>
</div></div></div>

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
        document.getElementById('editStudentForm').action = "{{ url('/admin/students') }}/" + this.dataset.id;
        new bootstrap.Modal(document.getElementById('editStudentModal')).show();
    });
});

document.querySelectorAll('.deleteStudentBtn').forEach(function (button) {
    button.addEventListener('click', function () {
        document.getElementById('studentDeleteName').textContent = this.dataset.name;
        document.getElementById('deleteStudentForm').action = "{{ url('/admin/students') }}/" + this.dataset.id;
        new bootstrap.Modal(document.getElementById('deleteStudentModal')).show();
    });
});

document.querySelectorAll('.editAdminBtn').forEach(function (button) {
    button.addEventListener('click', function () {
        document.getElementById('admin_email').value = this.dataset.email;
        document.getElementById('admin_firstname').value = this.dataset.firstname;
        document.getElementById('admin_middlename').value = this.dataset.middlename;
        document.getElementById('admin_lastname').value = this.dataset.lastname;
        document.getElementById('admin_position').value = this.dataset.position;
        document.getElementById('admin_status_id').value = this.dataset.status;
        document.getElementById('admin_role_id').value = this.dataset.role;
        document.getElementById('editAdminForm').action = "{{ url('/admin/users/admins') }}/" + this.dataset.id;
        new bootstrap.Modal(document.getElementById('editAdminModal')).show();
    });
});

document.querySelectorAll('.deleteAdminBtn').forEach(function (button) {
    button.addEventListener('click', function () {
        document.getElementById('adminDeleteName').textContent = this.dataset.name;
        document.getElementById('deleteAdminForm').action = "{{ url('/admin/users/admins') }}/" + this.dataset.id;
        new bootstrap.Modal(document.getElementById('deleteAdminModal')).show();
    });
});

document.querySelectorAll('.editSecurityBtn').forEach(function (button) {
    button.addEventListener('click', function () {
        document.getElementById('security_email').value = this.dataset.email;
        document.getElementById('security_firstname').value = this.dataset.firstname;
        document.getElementById('security_middlename').value = this.dataset.middlename;
        document.getElementById('security_lastname').value = this.dataset.lastname;
        document.getElementById('security_contact').value = this.dataset.contact;
        document.getElementById('security_address').value = this.dataset.address;
        document.getElementById('editSecurityForm').action = "{{ url('/admin/users/security') }}/" + this.dataset.id;
        new bootstrap.Modal(document.getElementById('editSecurityModal')).show();
    });
});

document.querySelectorAll('.deleteSecurityBtn').forEach(function (button) {
    button.addEventListener('click', function () {
        document.getElementById('securityDeleteName').textContent = this.dataset.name;
        document.getElementById('deleteSecurityForm').action = "{{ url('/admin/users/security') }}/" + this.dataset.id;
        new bootstrap.Modal(document.getElementById('deleteSecurityModal')).show();
    });
});

const importAccountType = document.getElementById('importAccountType');
const importActionInput = document.getElementById('importAction');
const importModalTitle = document.getElementById('importModalTitle');
const importHelpBlock = document.getElementById('importHelpBlock');
const importCreateActivateCard = document.getElementById('importCreateActivateCard');
const importDeactivateCard = document.getElementById('importDeactivateCard');
const importFileInput = document.getElementById('importFileInput');
const importSelectedFileName = document.getElementById('importSelectedFileName');
const importSubmitButton = document.getElementById('importSubmitButton');
const downloadImportTemplateLink = document.getElementById('downloadImportTemplateLink');
const downloadDeactivateTemplateLink = document.getElementById('downloadDeactivateTemplateLink');
const importRequiredColumns = document.getElementById('importRequiredColumns');
const importHelpText = document.getElementById('importHelpText');
const importTemplateBaseUrl = "{{ route('admin.users.import.template') }}";
const importAccountsModal = document.getElementById('importAccountsModal');
const importGuides = {
    student_create_activate: {
        columns: ['student_number', 'first_name', 'middle_name', 'last_name', 'email', 'program', 'year', 'section', 'gender', 'ladderized'],
        help: 'Program, year, section, and gender can use names (e.g., BSIT, 1, 1A, Male). Ladderized accepts Yes/No or 1/0.',
    },
    student_deactivate_students: {
        columns: ['student_number'],
        help: 'Only existing student numbers will be updated to Inactive status.',
    },
    admin_create_activate: {
        columns: ['email', 'firstname', 'middlename', 'lastname', 'position', 'role', 'status', 'password'],
        help: 'Role and status values can use readable names (e.g., Admin, IT Administrator, Active).',
    },
    security_create_activate: {
        columns: ['email', 'firstname', 'middlename', 'lastname', 'contact_number', 'address', 'password'],
        help: 'Firstname and lastname are required. Contact number and address are optional but recommended for complete profiles.',
    },
};

function setActiveImportAction(action) {
    if (!importActionInput || !importCreateActivateCard || !importDeactivateCard) {
        return;
    }

    importActionInput.value = action;
    importCreateActivateCard.classList.toggle('active', action === 'create_activate');
    importDeactivateCard.classList.toggle('active', action === 'deactivate_students');
}

function renderImportGuide(accountType, importAction) {
    const guideKey = accountType + '_' + importAction;
    if (!importGuides[guideKey] || !importRequiredColumns || !importHelpText || !downloadImportTemplateLink) {
        return;
    }

    importRequiredColumns.innerHTML = '';
    importGuides[guideKey].columns.forEach(function (columnName) {
        const item = document.createElement('li');
        item.innerHTML = '<i class="bi bi-check2-circle text-success me-1"></i><code>' + columnName + '</code>';
        importRequiredColumns.appendChild(item);
    });

    importHelpText.textContent = importGuides[guideKey].help;
    downloadImportTemplateLink.href = importTemplateBaseUrl + '?account_type=' + encodeURIComponent(accountType) + '&import_action=create_activate';

    if (downloadDeactivateTemplateLink) {
        downloadDeactivateTemplateLink.href = importTemplateBaseUrl + '?account_type=' + encodeURIComponent(accountType) + '&import_action=deactivate_students';
    }
}

function configureImportModalForType(accountType) {
    if (!importAccountType || !importModalTitle || !importDeactivateCard || !importHelpBlock || !importCreateActivateCard) {
        return;
    }

    importAccountType.value = accountType;

    if (accountType === 'student') {
        importModalTitle.textContent = 'Import Student Data';
        importCreateActivateCard.querySelector('.import-action-title').textContent = 'Import & Activate';
        importCreateActivateCard.querySelector('.import-action-text').textContent = 'Upload a list of new students to create and activate their accounts.';
        importDeactivateCard.classList.remove('d-none');
    } else if (accountType === 'admin') {
        importModalTitle.textContent = 'Import Admin Data';
        importCreateActivateCard.querySelector('.import-action-title').textContent = 'Import Admin Accounts';
        importCreateActivateCard.querySelector('.import-action-text').textContent = 'Upload a list of admin users to create new accounts.';
        importDeactivateCard.classList.add('d-none');
    } else {
        importModalTitle.textContent = 'Import Security Data';
        importCreateActivateCard.querySelector('.import-action-title').textContent = 'Import Security Accounts';
        importCreateActivateCard.querySelector('.import-action-text').textContent = 'Upload a list of security users to create new accounts.';
        importDeactivateCard.classList.add('d-none');
    }

    setActiveImportAction('create_activate');
    renderImportGuide(accountType, 'create_activate');
}

if (importAccountsModal) {
    importAccountsModal.addEventListener('show.bs.modal', function (event) {
        const triggerButton = event.relatedTarget;
        const accountType = triggerButton ? (triggerButton.getAttribute('data-import-account-type') || 'student') : 'student';
        configureImportModalForType(accountType);
        if (importFileInput) {
            importFileInput.value = '';
        }
        if (importSelectedFileName) {
            importSelectedFileName.textContent = 'No file selected';
        }
        if (importSubmitButton) {
            importSubmitButton.disabled = true;
        }
    });
}

if (importCreateActivateCard) {
    importCreateActivateCard.addEventListener('click', function () {
        setActiveImportAction('create_activate');
        renderImportGuide(importAccountType.value, 'create_activate');
    });
}

if (importDeactivateCard) {
    importDeactivateCard.addEventListener('click', function () {
        if (importDeactivateCard.classList.contains('d-none')) {
            return;
        }
        setActiveImportAction('deactivate_students');
        renderImportGuide(importAccountType.value, 'deactivate_students');
    });
}

if (importFileInput && importSelectedFileName) {
    importFileInput.addEventListener('change', function () {
        if (importFileInput.files && importFileInput.files.length > 0) {
            importSelectedFileName.textContent = importFileInput.files[0].name;
            if (importSubmitButton) {
                importSubmitButton.disabled = false;
            }
            return;
        }
        importSelectedFileName.textContent = 'No file selected';
        if (importSubmitButton) {
            importSubmitButton.disabled = true;
        }
    });
}
</script>
@endsection
