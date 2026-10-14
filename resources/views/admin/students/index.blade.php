@extends('layouts.admin')

@php
    $isItAdministrator = auth('admin')->check() && auth('admin')->user()->isItAdministrator();
    $ossStaffRoleId = $adminRoles->firstWhere('roles_name', 'OSS Staff')?->roles_id ?? null;
@endphp

@section('title', 'User Management')
@section('content')
<div class="container-fluid">
    {{-- Standardized header (Security-style hero). The PHT clock is opt-in and
         deliberately NOT requested here — this page is about student records. --}}
    <x-admin.page-header
        title="User Management"
        description="Manage student accounts and student records." />

    <div class="card shadow-sm user-management-shell">
        {{-- Card Header.
             The redundant secondary "User Management" heading has been REMOVED —
             the main page header above already names the page. The tabs are now
             the first visible module navigation. --}}
        <div class="card-header bg-white">
            <ul class="nav nav-tabs user-management-tabs" id="userManagementTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ request('tab', 'students') === 'students' ? 'active' : '' }}" id="students-tab" data-bs-toggle="tab" data-bs-target="#students-pane" type="button" role="tab">
                        <i class="bi bi-mortarboard-fill me-1"></i>
                        Students
                    </button>
                </li>
                @if($isItAdministrator)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ request('tab') === 'admins' ? 'active' : '' }}" id="admins-tab" data-bs-toggle="tab" data-bs-target="#admins-pane" type="button" role="tab">
                        <i class="bi bi-person-badge-fill me-1"></i>
                        Admins
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ request('tab') === 'security' ? 'active' : '' }}" id="security-tab" data-bs-toggle="tab" data-bs-target="#security-pane" type="button" role="tab">
                        <i class="bi bi-person-workspace me-1"></i>
                        Security &amp; OSS Staff
                    </button>
                </li>
                @endif
            </ul>
        </div>

        <div class="card-body">
            <div class="tab-content user-management-content">
                <div class="tab-pane fade {{ request('tab', 'students') === 'students' ? 'show active' : '' }}" id="students-pane" role="tabpanel">

                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show admin-alert mb-3" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show admin-alert mb-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if(session('info'))
                        <div class="alert alert-info alert-dismissible fade show admin-alert mb-3" role="alert">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            {{ session('info') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    @php
                        $studentFiltersActive = request()->filled('filter_program_id')
                            || request()->filled('filter_year_id')
                            || request()->filled('filter_section_id')
                            || request()->filled('filter_gender_id')
                            || request()->filled('filter_status_id')
                            || request()->filled('filter_ladderized');
                    @endphp
                    <form method="GET" action="{{ route('admin.students') }}" class="mb-4 user-management-toolbar">
                        <input type="hidden" name="tab" value="students">
                        <div class="d-flex flex-column flex-xl-row gap-3 align-items-stretch align-items-xl-center">
                            <div class="flex-grow-1 d-flex gap-2 align-items-stretch">
                                <div class="input-group user-management-search flex-grow-1">
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
                                <button type="submit" class="admin-btn admin-btn-primary user-management-search-submit">
                                    Search
                                </button>
                            </div>

                            <div class="d-flex flex-wrap gap-2 justify-content-xl-end user-management-actions">
                                <button
                                    type="button"
                                    class="admin-btn admin-btn-neutral"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#studentFiltersCollapse"
                                    aria-expanded="{{ $studentFiltersActive ? 'true' : 'false' }}"
                                    aria-controls="studentFiltersCollapse">
                                    <i class="bi bi-funnel me-1"></i>
                                    Filter
                                </button>
                                <button
                                    type="button"
                                    class="admin-btn admin-btn-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#createStudentModal">
                                    <i class="bi bi-person-plus-fill me-1"></i>
                                    Add Student
                                </button>
                                <button type="button" class="admin-btn admin-btn-neutral" data-bs-toggle="modal" data-bs-target="#importAccountsModal" data-import-account-type="student">
                                    <i class="bi bi-box-arrow-in-down-right me-1"></i>
                                    Import
                                </button>

                            </div>
                        </div>

                        <div class="collapse mt-3 {{ $studentFiltersActive ? 'show' : '' }}" id="studentFiltersCollapse">
                            <div class="student-filter-panel">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="admin-filter-label">Program</label>
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
                                        <label class="admin-filter-label">Year</label>
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
                                        <label class="admin-filter-label">Section</label>
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
                                        <label class="admin-filter-label">Gender</label>
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
                                        <label class="admin-filter-label">Status</label>
                                        <select name="filter_status_id" class="form-select">
                                            <option value="">All Statuses</option>
                                            @foreach($statuses as $status)
                                                <option value="{{ $status->status_id }}" {{ (string) request('filter_status_id') === (string) $status->status_id ? 'selected' : '' }}>
                                                    {{ $status->status_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 {{ in_array(strtoupper(optional($programs->firstWhere('program_id', request('filter_program_id')))->program_name ?? ''), ['BSIT','BSOA'], true) ? '' : 'd-none' }}" id="filterLadderizedWrap">
                                        <label class="admin-filter-label">Ladderized</label>
                                        <select name="filter_ladderized" class="form-select">
                                            <option value="">All</option>
                                            <option value="1" {{ request('filter_ladderized') === '1' ? 'selected' : '' }}>Yes</option>
                                            <option value="0" {{ request('filter_ladderized') === '0' ? 'selected' : '' }}>No</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a
                                        href="{{ route('admin.students', array_filter(['tab' => 'students', 'search' => request('search')])) }}"
                                        class="admin-btn admin-btn-neutral admin-btn-sm">
                                        Clear Filters
                                    </a>
                                    <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Apply Filters</button>
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
                                        <td>
                                            {{ $student->last_name }}
                                            @if(trim((string) $student->name_suffix) !== '')
                                                <span class="text-muted">{{ trim((string) $student->name_suffix) }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $student->first_name }}</td>
                                        <td>{{ $student->middle_name ?: '—' }}</td>
                                        <td>{{ $student->email }}</td>
                                        <td>
                                            {{ $student->program?->program_name ?? '—' }}
                                            @if((int) ($student->studentInfo?->ladderized ?? 0) === 1)
                                                <span class="badge bg-maroon-soft text-maroon ms-1">Ladderized</span>
                                            @endif
                                        </td>
                                        <td>{{ $student->year?->year ?? '—' }}</td>
                                        <td>{{ $student->section?->section_name ?? '—' }}</td>
                                        <td>
                                            @php
                                                $statusName = strtolower(trim((string) optional($student->status)->status_name));
                                            @endphp
                                            @if($statusName === 'active')
                                                <x-admin.badge status="active" label="Active" />
                                            @elseif($statusName === 'inactive')
                                                <x-admin.badge status="inactive" label="Inactive" />
                                            @else
                                                <x-admin.badge status="info" :label="$statusName !== '' ? ucfirst($statusName) : 'Unknown'" />
                                            @endif
                                        </td>
                                        <td class="text-end user-action-cell">
<div class="user-action-group">

    <button
        class="admin-btn admin-btn-view admin-btn-sm user-action-btn editStudentBtn"
        data-id="{{ $student->student_number }}"
        data-first="{{ $student->first_name }}"
        data-middle="{{ $student->middle_name }}"
        data-last="{{ $student->last_name }}"
        data-suffix="{{ $student->name_suffix }}"
        data-email="{{ $student->email }}"
        data-program="{{ $student->program_id }}"
        data-year="{{ $student->studentInfo?->year_id }}"
        data-section="{{ $student->section_id }}"
        data-gender="{{ $student->gender_id }}"
        data-status="{{ $student->status_id }}"
        data-ladderized="{{ $student->studentInfo?->ladderized ?? 0 }}"
    >
        <i class="bi bi-pencil-square me-1"></i>
        Edit
    </button>

    <button
        class="admin-btn admin-btn-danger admin-btn-sm user-action-btn deleteStudentBtn"
        data-id="{{ $student->student_number }}"
        data-name="{{ $student->first_name }} {{ $student->last_name }}"
    >
        <i class="bi bi-trash me-1"></i>
        Delete
    </button>

</div>
                                        </td>
                                    </tr>
                                @empty
                                    <x-admin.empty-state
                                        :colspan="10"
                                        icon="bi-people"
                                        title="No students found"
                                        message="No students match your current filters."
                                        :clearUrl="route('admin.students', ['tab' => 'students'])" />
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $students->appends(request()->query())->links() }}
                    </div>
                </div>

                @if($isItAdministrator)
                <div class="tab-pane fade {{ request('tab') === 'admins' ? 'show active' : '' }}" id="admins-pane" role="tabpanel">
                    <form method="GET" action="{{ route('admin.students') }}" class="mb-4 user-management-toolbar">
                        <input type="hidden" name="tab" value="admins">
                        <div class="d-flex flex-column flex-xl-row gap-3 align-items-stretch align-items-xl-center">
                            <div class="flex-grow-1 d-flex gap-2 align-items-stretch">
                                <div class="input-group user-management-search flex-grow-1">
                                    <span class="input-group-text bg-white">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" name="admin_search" class="form-control" placeholder="Search admin by name or email..." value="{{ request('admin_search') }}">
                                </div>
                                <button type="submit" class="admin-btn admin-btn-primary user-management-search-submit">Search</button>
                            </div>
                            <div class="d-flex flex-wrap gap-2 justify-content-xl-end user-management-actions">
                                <button type="button" class="admin-btn admin-btn-neutral" data-bs-toggle="modal" data-bs-target="#importAccountsModal" data-import-account-type="admin">
                                    <i class="bi bi-box-arrow-in-down-right me-1"></i>
                                    Import
                                </button>
                                <a href="{{ route('admin.students', ['tab' => 'admins']) }}" class="admin-btn admin-btn-neutral">
                                    <i class="bi bi-arrow-repeat me-1"></i>
                                    Refresh
                                </a>
                                <button type="button" class="admin-btn admin-btn-danger" data-bs-toggle="modal" data-bs-target="#createAdminModal">
                                    <i class="bi bi-plus-lg me-1"></i>
                                    Add Admin
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive user-management-table">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($admins as $admin)
                                    <tr>
                                        <td>{{ trim(($admin->adminInfo->firstname ?? '') . ' ' . ($admin->adminInfo->lastname ?? '') . ' ' . ($admin->adminInfo->name_suffix ?? '')) ?: '—' }}</td>
                                        <td>{{ $admin->email }}</td>
                                        <td>{{ $adminRoles->firstWhere('roles_id', $admin->adminInfo->role_id ?? 1)->roles_name ?? 'Admin' }}</td>
                                        <td>
                                            @php
                                                $adminStatus = strtolower(trim((string) optional($statuses->firstWhere('status_id', $admin->adminInfo->status_id ?? 1))->status_name));
                                            @endphp
                                            @if($adminStatus === 'active')
                                                <x-admin.badge status="active" label="Active" />
                                            @else
                                                <x-admin.badge status="inactive" label="Inactive" />
                                            @endif
                                        </td>
                                        <td class="text-end user-action-cell">
                                            <div class="user-action-group">
                                                <button
                                                    class="admin-btn admin-btn-view admin-btn-sm user-action-btn editAdminBtn"
                                                    data-id="{{ $admin->id }}"
                                                    data-email="{{ $admin->email }}"
                                                    data-firstname="{{ $admin->adminInfo->firstname ?? '' }}"
                                                    data-middlename="{{ $admin->adminInfo->middlename ?? '' }}"
                                                    data-lastname="{{ $admin->adminInfo->lastname ?? '' }}"
                                                    data-suffix="{{ $admin->adminInfo->name_suffix ?? '' }}"
                                                    data-status="{{ $admin->adminInfo->status_id ?? 1 }}"
                                                    data-role="{{ $admin->adminInfo->role_id ?? 1 }}">
                                                    <i class="bi bi-pencil-square me-1"></i>Edit
                                                </button>
                                                <button class="admin-btn admin-btn-danger admin-btn-sm user-action-btn deleteAdminBtn" data-id="{{ $admin->id }}" data-name="{{ $admin->email }}">
                                                    <i class="bi bi-trash me-1"></i>Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <x-admin.empty-state
                                        :colspan="5"
                                        icon="bi-person-badge"
                                        title="No admin accounts found"
                                        message="No admin accounts match your search."
                                        :clearUrl="route('admin.students', ['tab' => 'admins'])" />
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $admins->appends(request()->query())->links() }}
                    </div>
                </div>
                @endif

                @if($isItAdministrator)
                <div class="tab-pane fade {{ request('tab') === 'security' ? 'show active' : '' }}" id="security-pane" role="tabpanel">
                    <form method="GET" action="{{ route('admin.students') }}" class="mb-4 user-management-toolbar">
                        <input type="hidden" name="tab" value="security">
                        <div class="d-flex flex-column flex-xl-row gap-3 align-items-stretch align-items-xl-center">
                            <div class="flex-grow-1 d-flex gap-2 align-items-stretch">
                                <div class="input-group user-management-search flex-grow-1">
                                    <span class="input-group-text bg-white">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" name="security_search" class="form-control" placeholder="Search security or OSS Staff by name or email..." value="{{ request('security_search') }}">
                                </div>
                                <button type="submit" class="admin-btn admin-btn-primary">Search</button>
                            </div>
                            <div class="d-flex flex-wrap gap-2 justify-content-xl-end user-management-actions">
                                <button type="button" class="admin-btn admin-btn-neutral" data-bs-toggle="modal" data-bs-target="#importAccountsModal" data-import-account-type="security">
                                    <i class="bi bi-box-arrow-in-down-right me-1"></i>
                                    Import
                                </button>
                                <a href="{{ route('admin.students', ['tab' => 'security']) }}" class="admin-btn admin-btn-neutral">
                                    <i class="bi bi-arrow-repeat me-1"></i>
                                    Refresh
                                </a>
<button
    type="button"
    class="admin-btn admin-btn-danger"
    data-bs-toggle="modal"
    data-bs-target="#createSecurityModal">
    <i class="bi bi-person-plus-fill me-1"></i>
    Add Security/OSS Staff
</button>
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
                                        $securitySuffix = $security->securityInfo->name_suffix ?? '';
                                        $securityFullName = trim($securityFirstName . ' ' . $securityMiddleName . ' ' . $securityLastName . ' ' . $securitySuffix);
                                    @endphp
                                    <tr>
                                        <td>{{ $securityFullName ?: '—' }}</td>
                                        <td>{{ $security->email }}</td>
                                        <td>{{ optional($security->securityProfile)->position ?? 'Security Personnel' }}</td>
                                        <td><x-admin.badge status="active" label="Active" /></td>
                                        <td class="text-end user-action-cell">
                                            <div class="user-action-group">
                                                <button
                                                    class="admin-btn admin-btn-view admin-btn-sm user-action-btn editSecurityBtn"
                                                    data-id="{{ $security->id }}"
                                                    data-email="{{ $security->email }}"
                                                    data-firstname="{{ $securityFirstName }}"
                                                    data-middlename="{{ $securityMiddleName }}"
                                                    data-lastname="{{ $securityLastName }}"
                                                    data-suffix="{{ $securitySuffix }}"
                                                    data-position="{{ optional($security->securityProfile)->position ?? '' }}">
                                                    <i class="bi bi-pencil-square me-1"></i>Edit
                                                </button>
                                                <button class="admin-btn admin-btn-danger admin-btn-sm user-action-btn deleteSecurityBtn" data-id="{{ $security->id }}" data-name="{{ $security->email }}">
                                                    <i class="bi bi-trash me-1"></i>Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    @if($ossStaff->isEmpty())
                                        <x-admin.empty-state
                                            :colspan="5"
                                            icon="bi-person-workspace"
                                            title="No security or OSS Staff accounts found"
                                            message="No accounts match your search."
                                            :clearUrl="route('admin.students', ['tab' => 'security'])" />
                                    @endif
                                @endforelse

                                @foreach($ossStaff as $ossUser)
                                    @php
                                        $ossFirstName = $ossUser->adminInfo->firstname ?? '';
                                        $ossMiddleName = $ossUser->adminInfo->middlename ?? '';
                                        $ossLastName = $ossUser->adminInfo->lastname ?? '';
                                        $ossSuffix = $ossUser->adminInfo->name_suffix ?? '';
                                        $ossFullName = trim($ossFirstName . ' ' . $ossMiddleName . ' ' . $ossLastName . ' ' . $ossSuffix);
                                        $ossStatus = strtolower(trim((string) optional($statuses->firstWhere('status_id', $ossUser->adminInfo->status_id ?? 1))->status_name));
                                        $ossRoleId = $ossUser->adminInfo->role_id ?? $ossStaffRoleId;
                                    @endphp
                                    <tr>
                                        <td>{{ $ossFullName ?: '—' }}</td>
                                        <td>{{ $ossUser->email }}</td>
                                        <td>OSS Staff</td>
                                        <td>
                                            @if($ossStatus === 'active')
                                                <x-admin.badge status="active" label="Active" />
                                            @else
                                                <x-admin.badge status="inactive" label="Inactive" />
                                            @endif
                                        </td>
                                        <td class="text-end user-action-cell">
                                            <div class="user-action-group">
                                                <button
                                                    class="admin-btn admin-btn-view admin-btn-sm user-action-btn editAdminBtn"
                                                    data-id="{{ $ossUser->id }}"
                                                    data-email="{{ $ossUser->email }}"
                                                    data-firstname="{{ $ossFirstName }}"
                                                    data-middlename="{{ $ossMiddleName }}"
                                                    data-lastname="{{ $ossLastName }}"
                                                    data-suffix="{{ $ossSuffix }}"
                                                    data-status="{{ $ossUser->adminInfo->status_id ?? 1 }}"
                                                    data-role="{{ $ossRoleId }}">
                                                    <i class="bi bi-pencil-square me-1"></i>Edit
                                                </button>
                                                <button class="admin-btn admin-btn-danger admin-btn-sm user-action-btn deleteAdminBtn" data-id="{{ $ossUser->id }}" data-name="{{ $ossUser->email }}">
                                                    <i class="bi bi-trash me-1"></i>Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $securities->appends(request()->query())->links() }}
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="modal fade admin-modal" id="createStudentModal" tabindex="-1" aria-labelledby="createStudentModalLabel" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-lg"><div class="modal-content admin-modal-content">
        <form method="POST" action="{{ route('admin.students.store') }}">
            @csrf
            <div class="modal-header admin-modal-header"><h5 class="modal-title admin-modal-title" id="createStudentModalLabel">Create Student Account</h5><button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body admin-modal-body"><div class="row g-3">
                <div class="col-md-6"><label class="form-label">Student Number <span class="required-asterisk">*</span></label><input name="student_number" class="form-control" required value="{{ old('student_number') }}"></div>
                <div class="col-md-6"><label class="form-label">Gender <span class="required-asterisk">*</span></label><select name="gender_id" class="form-select" required>@foreach($genders as $gender)<option value="{{ $gender->gender_id }}">{{ $gender->gender_name }}</option>@endforeach</select></div>

                {{-- Identity fields.
                     The administrator enters the name so a student record is
                     complete even before the student links their PUP IDP account.
                     Only EMAIL is exclusively IDP-provided, so it is not an input
                     here at all. --}}
                <div class="col-md-6"><label class="form-label">First Name <span class="required-asterisk">*</span></label><input name="first_name" class="form-control" required value="{{ old('first_name') }}"></div>
                <div class="col-md-6"><label class="form-label">Middle Name</label><input name="middle_name" class="form-control" value="{{ old('middle_name') }}"></div>
                <div class="col-md-6"><label class="form-label">Last Name <span class="required-asterisk">*</span></label><input name="last_name" class="form-control" required value="{{ old('last_name') }}"></div>
                <div class="col-md-6"><label class="form-label">Name Suffix</label><input name="name_suffix" class="form-control" placeholder="e.g. Jr., III" value="{{ old('name_suffix') }}"></div>

                <div class="col-md-6"><label class="form-label">Program <span class="required-asterisk">*</span></label><select name="program_id" class="form-select" required onchange="toggleLadderizedField(this)">@foreach($programs as $program)<option value="{{ $program->program_id }}">{{ $program->program_name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Year <span class="required-asterisk">*</span></label><select name="year_id" class="form-select" required>@foreach($years as $year)<option value="{{ $year->year_id }}">{{ $year->year }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Section <span class="required-asterisk">*</span></label><select name="section_id" class="form-select" required>@foreach($sections as $section)<option value="{{ $section->section_id }}">{{ $section->section_name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Status <span class="required-asterisk">*</span></label><select name="status_id" class="form-select" required>@foreach($statuses as $status)<option value="{{ $status->status_id }}">{{ $status->status_name }}</option>@endforeach</select></div>
                <div class="col-12 d-none" id="createLadderizedWrap">
                    <label class="form-label d-block">Ladderized</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="ladderized" id="createLadderizedYes" value="1">
                        <label class="form-check-label" for="createLadderizedYes">Yes</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="ladderized" id="createLadderizedNo" value="0" checked>
                        <label class="form-check-label" for="createLadderizedNo">No</label>
                    </div>
                </div>
                <div class="col-12">
                    <div class="alert alert-info small mb-0 py-2">
                        <i class="bi bi-info-circle me-1"></i>
                        The student's <strong>email</strong> is provided by the PUP IDP and is stored when the student links their PUP IDP account. Authentication and passwords are also managed by the PUP Identity Provider (IDP) — no local password is required. The name and enrollment details above are stored on the student's PUPTracker record.
                    </div>
                </div>
            </div></div>
            <div class="modal-footer admin-modal-footer"><button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Cancel</button><button class="admin-btn admin-btn-primary">Create Student</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade admin-modal" id="createAdminModal" tabindex="-1" aria-labelledby="createAdminModalLabel" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal-dialog"><div class="modal-content admin-modal-content">
        <form method="POST" action="{{ route('admin.users.admins.store') }}">
            @csrf
            <div class="modal-header admin-modal-header"><h5 class="modal-title admin-modal-title" id="createAdminModalLabel">Create Admin Account</h5><button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body admin-modal-body"><div class="row g-3">
                <div class="col-12"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">First Name</label><input name="firstname" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Middle Name</label><input name="middlename" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Last Name <span class="required-asterisk">*</span></label><input name="lastname" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Suffix</label><input name="name_suffix" class="form-control" maxlength="20" placeholder="e.g. Jr., Sr., III"></div>
                <div class="col-md-6">
                    <label class="form-label">Role <span class="required-asterisk">*</span></label>
                    <select name="role_id" id="create_admin_role_id" class="form-select" required>
                        @foreach ($adminRoles->whereIn('roles_name', ['Admin', 'IT Administrator']) as $role)
                            <option value="{{ $role->roles_id }}" {{ $role->roles_name === 'Admin' ? 'selected' : '' }}>
                                {{ $role->roles_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Status</label><select name="status_id" class="form-select" required>@foreach($statuses as $status)<option value="{{ $status->status_id }}">{{ $status->status_name }}</option>@endforeach</select></div>
            </div></div>
            <div class="modal-footer admin-modal-footer"><button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Cancel</button><button class="admin-btn admin-btn-primary">Create Admin</button></div>
        </form>
    </div></div>
</div>


<div class="modal fade admin-modal" id="createSecurityModal"
     tabindex="-1" aria-labelledby="createSecurityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content admin-modal-content">
<form id="createSecurityAccountForm"
      method="POST"
      action="{{ route('admin.users.security.store') }}"
      data-security-url="{{ route('admin.users.security.store') }}"
      data-admin-url="{{ route('admin.users.admins.store') }}">
    @csrf
                <input type="hidden" id="ossStaffRoleId" value="{{ $ossStaffRoleId }}">
                <input type="hidden" id="securityFormRoleId" name="role_id" value="{{ $ossStaffRoleId }}" disabled>

                <div class="modal-header admin-modal-header">
                    <h5 class="modal-title admin-modal-title" id="createSecurityModalLabel">
                        Create Security Account
                    </h5>
                    <button type="button" class="btn-close admin-modal-close"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body admin-modal-body">
                    <div class="row g-3">

                        <div class="col-12">
                            <label for="securityAccountType" class="form-label">
                                Account Type <span class="required-asterisk">*</span>
                            </label>
                            <select id="securityAccountType" class="form-select" required>
                                <option value="security">Security Personnel</option>
                                <option value="oss_staff">OSS Staff</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Email <span class="required-asterisk">*</span></label>
                            <input name="email" type="email" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">First Name <span class="required-asterisk">*</span></label>
                            <input name="firstname" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input name="middlename" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Last Name <span class="required-asterisk">*</span></label>
                            <input name="lastname" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Suffix</label>
                            <input name="name_suffix" class="form-control" maxlength="20" placeholder="e.g. Jr., Sr., III">
                        </div>

                        <div class="col-12" id="securityPositionGroup">
                            <label class="form-label">Position</label>
                            <input name="position" id="create_security_position"
                                   class="form-control" value="Security Personnel" readonly>
                        </div>

                        <div class="col-12 d-none" id="ossStaffStatusGroup">
                            <label class="form-label">Status <span class="required-asterisk">*</span></label>
                            <select name="status_id" id="oss_staff_status_id" class="form-select" disabled>
                                @foreach($statuses as $status)
                                    <option value="{{ $status->status_id }}"
                                        {{ strtolower($status->status_name) === 'active' ? 'selected' : '' }}>
                                        {{ $status->status_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                </div>

                <div class="modal-footer admin-modal-footer">
                    <button type="button" class="admin-btn admin-btn-neutral"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="admin-btn admin-btn-primary"
                            id="createSecurityAccountSubmit">
                        Create Security Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade admin-modal" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true" role="dialog" aria-modal="true"><div class="modal-dialog modal-lg"><div class="modal-content admin-modal-content">
    <form id="editStudentForm" method="POST">@csrf @method('PUT')
        <div class="modal-header admin-modal-header"><h5 class="modal-title admin-modal-title" id="editStudentModalLabel">Edit Student</h5><button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body admin-modal-body"><div class="row g-3">
            <div class="col-md-6"><label class="form-label">Student Number</label><input id="student_number" class="form-control" readonly></div>
            <div class="col-md-4"><label class="form-label">First Name</label><input type="text" id="first_name" class="form-control" readonly></div>
            <div class="col-md-4"><label class="form-label">Middle Name</label><input type="text" id="middle_name" class="form-control" readonly></div>
            <div class="col-md-4"><label class="form-label">Last Name</label><input type="text" id="last_name" class="form-control" readonly></div>
            <div class="col-md-4"><label class="form-label">Name Suffix</label><input type="text" id="name_suffix" class="form-control" placeholder="—" readonly></div>
            <div class="col-md-4"><label class="form-label">Email</label><input type="email" id="email" class="form-control" readonly></div>
            <div class="col-md-4"><label class="form-label">Program</label><select name="program_id" id="program_id" class="form-select" onchange="toggleEditLadderizedField(this)">@foreach($programs as $program)<option value="{{ $program->program_id }}">{{ $program->program_name }}</option>@endforeach</select></div>
<div class="col-md-6">
    <label for="year_id" class="form-label">Year</label>
    <select name="year_id" id="year_id" class="form-select" required>
        @foreach($years as $year)
            <option value="{{ $year->year_id }}">
                Year {{ $year->year }}
            </option>
        @endforeach
    </select>
</div>          <div class="col-md-6"><label class="form-label">Section</label><select name="section_id" id="section_id" class="form-select">@foreach($sections as $section)<option value="{{ $section->section_id }}">{{ $section->section_name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Gender</label><select name="gender_id" id="gender_id" class="form-select">@foreach($genders as $gender)<option value="{{ $gender->gender_id }}">{{ $gender->gender_name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Status</label><select name="status_id" id="status_id" class="form-select">@foreach($statuses as $status)<option value="{{ $status->status_id }}">{{ $status->status_name }}</option>@endforeach</select></div>
            <div class="col-12 d-none" id="editLadderizedWrap">
                <label class="form-label d-block">Ladderized</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="ladderized" id="editLadderizedYes" value="1">
                    <label class="form-check-label" for="editLadderizedYes">Yes</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="ladderized" id="editLadderizedNo" value="0">
                    <label class="form-check-label" for="editLadderizedNo">No</label>
                </div>
            </div>
            <div class="col-12">
                <div class="alert alert-info small mb-0 py-2">
                    <i class="bi bi-shield-lock me-1"></i>
                    Name, suffix, and email are managed by the PUP IDP and cannot be edited here. Passwords are also managed by the PUP Identity Provider (IDP).
                </div>
            </div>
        </div></div>
        <div class="modal-footer admin-modal-footer"><button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Cancel</button><button class="admin-btn admin-btn-primary">Update Student</button></div>
    </form>
</div></div></div>

<div class="modal fade admin-modal" id="deleteStudentModal" tabindex="-1" aria-labelledby="deleteStudentModalLabel" aria-hidden="true" role="dialog" aria-modal="true"><div class="modal-dialog"><div class="modal-content admin-modal-content">
    <form method="POST" id="deleteStudentForm">@csrf @method('DELETE')
        <div class="modal-header admin-modal-header"><h5 class="modal-title admin-modal-title" id="deleteStudentModalLabel">Delete Student</h5><button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body admin-modal-body"><p>Are you sure you want to delete <strong id="studentDeleteName"></strong>?</p></div>
        <div class="modal-footer admin-modal-footer"><button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Cancel</button><button class="admin-btn admin-btn-danger">Delete</button></div>
    </form>
</div></div></div>

<div class="modal fade admin-modal" id="editAdminModal" tabindex="-1" aria-labelledby="editAdminModalLabel" aria-hidden="true" role="dialog" aria-modal="true"><div class="modal-dialog"><div class="modal-content admin-modal-content">
    <form method="POST" id="editAdminForm">@csrf @method('PUT')
        <div class="modal-header"><h5 class="modal-title">Edit Admin</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><div class="row g-3">
            <div class="col-12"><label class="form-label">Email</label><input name="email" id="admin_email" type="email" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">First Name</label><input name="firstname" id="admin_firstname" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Middle Name</label><input name="middlename" id="admin_middlename" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Last Name</label><input name="lastname" id="admin_lastname" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Suffix</label><input name="name_suffix" id="admin_name_suffix" class="form-control" maxlength="20" placeholder="e.g. Jr., Sr., III"></div>
            <div class="col-md-6">
                <label for="admin_role_id" class="form-label">Role <span class="required-asterisk">*</span></label>
                <select name="role_id" id="admin_role_id" class="form-select" required>
                    @foreach ($adminRoles->whereIn('roles_name', ['Admin', 'IT Administrator', 'OSS Staff']) as $role)
                        <option value="{{ $role->roles_id }}">{{ $role->roles_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6"><label class="form-label">Status</label><select name="status_id" id="admin_status_id" class="form-select">@foreach($statuses as $status)<option value="{{ $status->status_id }}">{{ $status->status_name }}</option>@endforeach</select></div>
        </div></div>
        <div class="modal-footer admin-modal-footer"><button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Cancel</button><button class="admin-btn admin-btn-primary">Update Admin</button></div>
    </form>
</div></div></div>

<div class="modal fade admin-modal" id="deleteAdminModal" tabindex="-1" aria-labelledby="deleteAdminModalLabel" aria-hidden="true" role="dialog" aria-modal="true"><div class="modal-dialog"><div class="modal-content admin-modal-content">
    <form method="POST" id="deleteAdminForm">@csrf @method('DELETE')
        <div class="modal-header admin-modal-header"><h5 class="modal-title admin-modal-title" id="deleteAdminModalLabel">Delete Admin</h5><button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body admin-modal-body"><p>Are you sure you want to delete <strong id="adminDeleteName"></strong>?</p></div>
        <div class="modal-footer admin-modal-footer"><button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Cancel</button><button class="admin-btn admin-btn-danger">Delete</button></div>
    </form>
</div></div></div>

<div class="modal fade admin-modal" id="editSecurityModal" tabindex="-1" aria-labelledby="editSecurityModalLabel" aria-hidden="true" role="dialog" aria-modal="true"><div class="modal-dialog"><div class="modal-content admin-modal-content">
    <form method="POST" id="editSecurityForm">@csrf @method('PUT')
        <div class="modal-header admin-modal-header"><h5 class="modal-title admin-modal-title" id="editSecurityModalLabel">Edit Security</h5><button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body admin-modal-body"><div class="row g-3">
            <div class="col-12"><label class="form-label">Email</label><input name="email" id="security_email" type="email" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">First Name</label><input name="firstname" id="security_firstname" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Middle Name</label><input name="middlename" id="security_middlename" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Last Name</label><input name="lastname" id="security_lastname" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Suffix</label><input name="name_suffix" id="security_name_suffix" class="form-control" maxlength="20" placeholder="e.g. Jr., Sr., III"></div>
            <div class="col-md-6"><label class="form-label">Position</label><input name="position" id="security_position" class="form-control" value="Security Personnel" readonly tabindex="-1"></div>
        </div></div>
        <div class="modal-footer admin-modal-footer"><button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Cancel</button><button class="admin-btn admin-btn-primary">Update Security</button></div>
    </form>
</div></div></div>

<div class="modal fade admin-modal" id="deleteSecurityModal" tabindex="-1" aria-labelledby="deleteSecurityModalLabel" aria-hidden="true" role="dialog" aria-modal="true"><div class="modal-dialog"><div class="modal-content admin-modal-content">
    <form method="POST" id="deleteSecurityForm">@csrf @method('DELETE')
        <div class="modal-header admin-modal-header"><h5 class="modal-title admin-modal-title" id="deleteSecurityModalLabel">Delete Security</h5><button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body admin-modal-body"><p>Are you sure you want to delete <strong id="securityDeleteName"></strong>?</p></div>
        <div class="modal-footer admin-modal-footer"><button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Cancel</button><button class="admin-btn admin-btn-danger">Delete</button></div>
    </form>
</div></div></div>

<div class="modal fade" id="importAccountsModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content import-modal-content">
    <form method="POST" action="{{ route('admin.users.import') }}" enctype="multipart/form-data">
        @csrf
        <div class="modal-header import-modal-header">
            <h5 class="modal-title mb-0" id="importModalTitle">Import Student Data</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
            <button type="submit" id="importSubmitButton" class="admin-btn admin-btn-primary w-100" disabled>Import Data</button>
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
        document.getElementById('name_suffix').value = this.dataset.suffix || '';
        document.getElementById('email').value = this.dataset.email;
document.getElementById('program_id').value = this.dataset.program;
document.getElementById('year_id').value = this.dataset.year;
document.getElementById('section_id').value = this.dataset.section;
        document.getElementById('gender_id').value = this.dataset.gender;
        document.getElementById('status_id').value = this.dataset.status;

        // Ladderized radio
        const editWrap = document.getElementById('editLadderizedWrap');
        if (editWrap) {
            const ladderized = String(this.dataset.ladderized || '0') === '1';
            document.getElementById('editLadderizedYes').checked = ladderized;
            document.getElementById('editLadderizedNo').checked = !ladderized;
            toggleEditLadderizedField(document.getElementById('program_id'));
        }

        document.getElementById('editStudentForm').action = "{{ url('/admin/students') }}/" + this.dataset.id;
        new bootstrap.Modal(document.getElementById('editStudentModal')).show();
    });
});

// Show the Ladderized radio group only for BSOA / BSIT programs (create modal).
function toggleLadderizedField(selectEl) {
    const wrap = document.getElementById('createLadderizedWrap');
    if (!wrap || !selectEl) {
        return;
    }
    const label = (selectEl.selectedOptions[0] || {}).textContent || '';
    const isLadderizable = /BSOA|BSIT/i.test(label);
    wrap.classList.toggle('d-none', !isLadderizable);
    if (isLadderizable) {
        document.getElementById('createLadderizedNo').checked = true;
    }
}

// Same for the edit modal.
function toggleEditLadderizedField(selectEl) {
    const wrap = document.getElementById('editLadderizedWrap');
    if (!wrap || !selectEl) {
        return;
    }
    const label = (selectEl.selectedOptions[0] || {}).textContent || '';
    const isLadderizable = /BSOA|BSIT/i.test(label);
    wrap.classList.toggle('d-none', !isLadderizable);
}

// Show the Ladderized filter only when the selected program is BSIT/BSOA.
(function initLadderizedFilterToggle() {
    const programSelect = document.querySelector('select[name="filter_program_id"]');
    const ladderizedWrap = document.getElementById('filterLadderizedWrap');
    if (!programSelect || !ladderizedWrap) {
        return;
    }

    function updateLadderizedFilterVisibility() {
        const label = (programSelect.selectedOptions[0] || {}).textContent || '';
        const isLadderizable = /BSOA|BSIT/i.test(label);
        ladderizedWrap.classList.toggle('d-none', !isLadderizable);
        if (!isLadderizable) {
            const select = ladderizedWrap.querySelector('select[name="filter_ladderized"]');
            if (select) { select.value = ''; }
        }
    }

    programSelect.addEventListener('change', updateLadderizedFilterVisibility);
    updateLadderizedFilterVisibility();
})();

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
        document.getElementById('admin_name_suffix').value = this.dataset.suffix || '';
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
        document.getElementById('security_name_suffix').value = this.dataset.suffix || '';
        document.getElementById('security_position').value = this.dataset.position || '';
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
        columns: ['student_number', 'first_name', 'middle_name', 'last_name', 'name_suffix', 'program', 'year', 'section', 'gender', 'ladderized'],
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
        columns: ['email', 'firstname', 'middlename', 'lastname', 'position', 'password'],
        help: 'Firstname and lastname are required. Position is optional but recommended for complete profiles.',
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

(function () {
    const accountType = document.getElementById('securityAccountType');
    const form = document.getElementById('createSecurityAccountForm');
    const positionGroup = document.getElementById('securityPositionGroup');
    const statusGroup = document.getElementById('ossStaffStatusGroup');
    const statusSelect = document.getElementById('oss_staff_status_id');
    const roleIdInput = document.getElementById('securityFormRoleId');
    const ossStaffRoleId = document.getElementById('ossStaffRoleId');
    const submitButton = document.getElementById('createSecurityAccountSubmit');
    const modalTitle = document.getElementById('createSecurityModalLabel');

    if (!accountType || !form) return;

const securityUrl = form.dataset.securityUrl;
const adminUrl = form.dataset.adminUrl;

    function updateAccountType() {
        const isOssStaff = accountType.value === 'oss_staff';

        form.action = isOssStaff ? adminUrl : securityUrl;

        positionGroup.classList.toggle('d-none', isOssStaff);
        statusGroup.classList.toggle('d-none', !isOssStaff);

        statusSelect.disabled = !isOssStaff;
        statusSelect.required = isOssStaff;

        if (roleIdInput) {
            roleIdInput.disabled = !isOssStaff;
            roleIdInput.value = ossStaffRoleId ? ossStaffRoleId.value : '';
        }

        if (isOssStaff && (!roleIdInput || !roleIdInput.value)) {
            submitButton.disabled = true;
            submitButton.title = 'OSS Staff role is not configured. Contact the system administrator.';
        } else {
            submitButton.disabled = false;
            submitButton.removeAttribute('title');
        }

        if (modalTitle) {
            modalTitle.textContent = isOssStaff
                ? 'Create OSS Staff Account'
                : 'Create Security Account';
        }

        submitButton.textContent = isOssStaff
            ? 'Create OSS Staff'
            : 'Create Security Account';
    }

    accountType.addEventListener('change', updateAccountType);

    document.getElementById('createSecurityModal')
        .addEventListener('show.bs.modal', updateAccountType);

    updateAccountType();
})();
</script>
@endsection
