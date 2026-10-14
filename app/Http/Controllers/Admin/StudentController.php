<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminInfo;
use App\Models\Gender;
use App\Models\Program;
use App\Models\Role;
use App\Models\Security;
use App\Models\SecurityInfo;
use App\Models\Section;
use App\Models\Status;
use App\Models\StudentInfo;
use App\Models\StudentStatus;
use App\Models\User;
use App\Models\Year;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class StudentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Student / Admin / Security Index
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $students = User::query()
            ->with([
                'role',
                'studentInfo.program',
                'studentInfo.year',
                'studentInfo.section',
                'gender',
                'status'
            ])
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

        if ($request->filled('filter_program_id')) {
            $students->whereHas('studentInfo', function ($query) use ($request) {
                $query->where(
                    'program_id',
                    $request->input('filter_program_id')
                );
            });
        }

        if ($request->filled('filter_year_id')) {
            $students->whereHas('studentInfo', function ($query) use ($request) {
                $query->where(
                    'year_id',
                    $request->input('filter_year_id')
                );
            });
        }

        if ($request->filled('filter_section_id')) {
            $students->whereHas('studentInfo', function ($query) use ($request) {
                $query->where(
                    'section_id',
                    $request->input('filter_section_id')
                );
            });
        }

        if ($request->filled('filter_gender_id')) {
            $students->where(
                'gender_id',
                $request->input('filter_gender_id')
            );
        }

        if ($request->filled('filter_status_id')) {
            $students->where(
                'status_id',
                $request->input('filter_status_id')
            );
        }

        if ($request->filled('filter_ladderized')) {
            $students->whereHas('studentInfo', function ($query) use ($request) {
                $query->where(
                    'ladderized',
                    (int) $request->input('filter_ladderized')
                );
            });
        }

        $students = $students
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(
                15,
                ['*'],
                'students_page'
            )
            ->withQueryString();

        $admins = Admin::query()
            ->with('adminInfo')
            ->when(
                $request->filled('admin_search'),
                function ($query) use ($request) {

                    $search = '%' . trim($request->admin_search) . '%';

                    $query->where(function ($searchQuery) use ($search) {

                        $searchQuery
                            ->where('email', 'like', $search)
                            ->orWhere('username', 'like', $search)
                            ->orWhereHas(
                                'adminInfo',
                                function ($adminInfoQuery) use ($search) {

                                    $adminInfoQuery
                                        ->where(
                                            'firstname',
                                            'like',
                                            $search
                                        )
                                        ->orWhere(
                                            'lastname',
                                            'like',
                                            $search
                                        );
                                }
                            );
                    });
                }
            )
            ->orderBy('id')
            ->paginate(
                15,
                ['*'],
                'admins_page'
            )
            ->withQueryString();

        $securities = Security::query()
            ->with('securityInfo')
            ->when(
                $request->filled('security_search'),
                function ($query) use ($request) {

                    $search = '%' . trim($request->security_search) . '%';

                    $query->where(function ($searchQuery) use ($search) {

                        $searchQuery
                            ->where('email', 'like', $search)
                            ->orWhereHas(
                                'securityInfo',
                                function ($securityInfoQuery) use ($search) {

                                    $securityInfoQuery
                                        ->where(
                                            'firstname',
                                            'like',
                                            $search
                                        )
                                        ->orWhere(
                                            'middlename',
                                            'like',
                                            $search
                                        )
                                        ->orWhere(
                                            'lastname',
                                            'like',
                                            $search
                                        );
                                }
                            )
                            ->orWhereHas(
                                'securityInfo',
                                function ($securityInfoQuery) use ($search) {

                                    $securityInfoQuery
                                        ->where(
                                            'contact_number',
                                            'like',
                                            $search
                                        )
                                        ->orWhere(
                                            'address',
                                            'like',
                                            $search
                                        );
                                }
                            );
                    });
                }
            )
            ->orderBy('id')
            ->paginate(
                15,
                ['*'],
                'security_page'
            )
            ->withQueryString();

        // OSS Staff accounts are stored in the admins table and identified by
        // their structured role, while Security Personnel use the security table.
        // Load OSS Staff separately so both account types appear in the same tab.
        $ossStaffRoleId = Role::query()
            ->where('roles_name', 'OSS Staff')
            ->value('roles_id');

        $ossStaff = Admin::query()
            ->with('adminInfo')
            ->when($ossStaffRoleId !== null, function ($query) use ($ossStaffRoleId) {
                $query->whereHas('adminInfo', function ($adminInfoQuery) use ($ossStaffRoleId) {
                    $adminInfoQuery->where('role_id', $ossStaffRoleId);
                });
            }, function ($query) {
                // Fail closed if the role is not configured.
                $query->whereRaw('1 = 0');
            })
            ->when($request->filled('security_search'), function ($query) use ($request) {
                $search = '%' . trim($request->input('security_search')) . '%';

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('email', 'like', $search)
                        ->orWhereHas('adminInfo', function ($infoQuery) use ($search) {
                            $infoQuery
                                ->where('firstname', 'like', $search)
                                ->orWhere('middlename', 'like', $search)
                                ->orWhere('lastname', 'like', $search);
                        });
                });
            })
            ->orderBy('id')
            ->get();

        $programs = Program::orderBy('program_name')->get();

        $years = Year::orderBy('year')->get();

        $sections = Section::orderBy('section_name')->get();

        $genders = Gender::orderBy('gender_name')->get();

        $statuses = Status::orderBy('status_name')->get();

        $adminRoles = Role::query()
            ->whereIn(
                'roles_name',
                [
                    'Admin',
                    'IT Administrator',
                    'OSS Staff'
                ]
            )
            ->orderBy('roles_name')
            ->get();

        return view(
            'admin.students.index',
            compact(
                'students',
                'admins',
                'securities',
                'ossStaff',
                'programs',
                'years',
                'sections',
                'genders',
                'statuses',
                'adminRoles'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create Student
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_number' =>
                'required|string|max:50|unique:users_tbl,student_number',

            'first_name' =>
                'required|string|max:100',

            'middle_name' =>
                'nullable|string|max:100',

            'last_name' =>
                'required|string|max:100',

            'name_suffix' =>
                'nullable|string|max:20',

            'program_id' =>
                'required|exists:program_tbl,program_id',

            'section_id' =>
                'required|exists:section_tbl,section_id',

            'year_id' =>
                'required|exists:year_tbl,year_id',

            'gender_id' =>
                'required|exists:gender_tbl,gender_id',

            'status_id' =>
                'required|exists:status_tbl,status_id',

            'ladderized' =>
                'nullable|in:0,1',
        ]);

        $student = null;

        DB::transaction(function () use (
            $validated,
            &$student
        ): void {

            $studentStatusId =
                $this->resolveStudentStatusIdFromUserStatusId(
                    (int) $validated['status_id']
                );

            if (!$studentStatusId) {

                throw ValidationException::withMessages([
                    'status_id' =>
                        'Unable to map the selected status to a valid student status.',
                ]);
            }

            $student = User::create([
                'student_number' =>
                    $validated['student_number'],

                'first_name' =>
                    trim((string) $validated['first_name']),

                'middle_name' =>
                    $this->nullableTrim(
                        $validated['middle_name'] ?? null
                    ),

                'last_name' =>
                    trim((string) $validated['last_name']),

                'name_suffix' =>
                    $this->nullableTrim(
                        $validated['name_suffix'] ?? null
                    ),

                'gender_id' =>
                    $validated['gender_id'],

                'status_id' =>
                    $validated['status_id'],

                'roles_id' =>
                    2,

                'password_hash' =>
                    '',
            ]);

            $student->studentInfo()->create([
                'program_id' =>
                    $validated['program_id'],

                'year_id' =>
                    $validated['year_id'],

                'section_id' =>
                    $validated['section_id'],

                'student_status_id' =>
                    $studentStatusId,

                'ladderized' =>
                    (int) ($validated['ladderized'] ?? 0),
            ]);
        });

        $this->logUserManagementAction(
            'student',
            'Create',
            $student?->user_id,
            $student?->email
        );

        return redirect()
            ->route(
                'admin.students',
                ['tab' => 'students']
            )
            ->with(
                'success',
                'Student account created successfully. The student will connect their PUP IDP account to supply their institutional email.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Utility
    |--------------------------------------------------------------------------
    */

    private function nullableTrim($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === ''
            ? null
            : $trimmed;
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Account Management
    |--------------------------------------------------------------------------
    */

    public function storeAdmin(Request $request)
    {
        $validated = $request->validate([
            'email' =>
                'required|email|max:255|unique:admins,email',

            'firstname' =>
                'required|string|max:50',

            'middlename' =>
                'nullable|string|max:50',

            'lastname' =>
                'required|string|max:50',

            'name_suffix' =>
                'nullable|string|max:20',

            'status_id' =>
                'required|exists:status_tbl,status_id',

            'role_id' =>
                'required|exists:roles_tbl,roles_id',
        ]);

        $admin = null;

        DB::transaction(function () use (
            $validated,
            &$admin
        ): void {

            $admin = Admin::create([
                'username' =>
                    $validated['email'],

                'email' =>
                    $validated['email'],

                'password' =>
                    Hash::make(Str::random(64)),

                'mfa_totp_enabled' =>
                    0,
            ]);

            AdminInfo::updateOrCreate(
                ['admin_id' => $admin->id],
                [
                    'Position' =>
                        'Admin',

                    'firstname' =>
                        $validated['firstname'],

                    'middlename' =>
                        $validated['middlename'] ?? null,

                    'lastname' =>
                        $validated['lastname'],

                    'name_suffix' =>
                        $this->nullableTrim($validated['name_suffix'] ?? null),

                    'status_id' =>
                        $validated['status_id'],

                    'role_id' =>
                        $validated['role_id'],
                ]
            );
        });

        $this->logUserManagementAction(
            'admin',
            'Create',
            $admin?->id,
            $validated['email']
        );

        return redirect()
            ->route(
                'admin.students',
                ['tab' => 'admins']
            )
            ->with(
                'success',
                'Admin account created successfully.'
            );
    }

    public function storeSecurity(Request $request)
    {
        $validated = $request->validate([
            'email' =>
                'required|email|max:255|unique:security,email',

            'firstname' =>
                'required|string|max:50',

            'middlename' =>
                'nullable|string|max:50',

            'lastname' =>
                'required|string|max:50',

            'name_suffix' =>
                'nullable|string|max:20',
        ]);

        $security = null;

        DB::transaction(function () use (
            $validated,
            &$security
        ): void {

            $security = Security::create([
                'email' =>
                    $validated['email'],

                'password' =>
                    Hash::make(Str::random(64)),

                'mfa_totp_enabled' =>
                    0,
            ]);

            SecurityInfo::updateOrCreate(
                ['security_id' => $security->id],
                $this->buildSecurityInfoData($validated)
            );
        });

        $this->logUserManagementAction(
            'security',
            'Create',
            $security?->id,
            $validated['email']
        );

        return redirect()
            ->route(
                'admin.students',
                ['tab' => 'security']
            )
            ->with(
                'success',
                'Security account created successfully.'
            );
    }

    public function updateAdmin(
        Request $request,
        Admin $admin
    ) {
        $validated = $request->validate([
            'email' =>
                'required|email|max:255|unique:admins,email,' . $admin->id,

            'firstname' =>
                'required|string|max:50',

            'middlename' =>
                'nullable|string|max:50',

            'lastname' =>
                'required|string|max:50',

            'name_suffix' =>
                'nullable|string|max:20',

            'status_id' =>
                'required|exists:status_tbl,status_id',

            'role_id' =>
                'required|exists:roles_tbl,roles_id',

            'password' =>
                'nullable|string|min:8',
        ]);

        $adminEmailBefore = $admin->email;

        $adminInfoBefore = $admin->adminInfo
            ? [
                'firstname' =>
                    $admin->adminInfo->firstname,

                'middlename' =>
                    $admin->adminInfo->middlename,

                'lastname' =>
                    $admin->adminInfo->lastname,

                'name_suffix' =>
                    $admin->adminInfo->name_suffix ?? '',

                'status_id' =>
                    $admin->adminInfo->status_id,

                'role_id' =>
                    $admin->adminInfo->role_id,
            ]
            : [];

        DB::transaction(function () use (
            $admin,
            $validated
        ): void {

            $admin->email =
                $validated['email'];

            $admin->username =
                $validated['email'];

            if (
                !empty($validated['password'])
            ) {
                $admin->password =
                    Hash::make(
                        $validated['password']
                    );
            }

            $admin->save();

            AdminInfo::updateOrCreate(
                ['admin_id' => $admin->id],
                [
                    'Position' =>
                        'Admin',

                    'firstname' =>
                        $validated['firstname'],

                    'middlename' =>
                        $validated['middlename'] ?? null,

                    'lastname' =>
                        $validated['lastname'],

                    'name_suffix' =>
                        $this->nullableTrim($validated['name_suffix'] ?? null),

                    'status_id' =>
                        $validated['status_id'],

                    'role_id' =>
                        $validated['role_id'],
                ]
            );
        });

        $this->logUserManagementAction(
            'admin',
            'Update',
            $admin->id,
            $admin->email,
            $this->collectAdminChanges(
                $adminEmailBefore,
                $adminInfoBefore,
                $admin,
                $validated
            )
        );

        $ossStaffRoleId = Role::query()
            ->where('roles_name', 'OSS Staff')
            ->value('roles_id');

        $returnTab = $ossStaffRoleId !== null
            && (int) $validated['role_id'] === (int) $ossStaffRoleId
                ? 'security'
                : 'admins';

        return redirect()
            ->route('admin.students', ['tab' => $returnTab])
            ->with('success', 'Account updated successfully.');
    }

    public function destroyAdmin(Admin $admin)
    {
        $wasOssStaff = $admin->isOssStaff();
        $adminEmail = $admin->email;

        $adminName = trim(
            ($admin->adminInfo?->firstname ?? '')
            . ' '
            . ($admin->adminInfo?->lastname ?? '')
        ) ?: $adminEmail;

        if ($admin->adminInfo) {
            $admin->adminInfo->delete();
        }

        $adminId = $admin->id;

        $admin->delete();

        $this->logUserManagementAction(
            'admin',
            'Delete',
            $adminId,
            $adminEmail,
            [
                [
                    'field' =>
                        'Name',

                    'from' =>
                        $adminName,

                    'to' =>
                        '— (deleted)',
                ],

                [
                    'field' =>
                        'Email',

                    'from' =>
                        $adminEmail,

                    'to' =>
                        '— (deleted)',
                ],
            ]
        );

        return redirect()
            ->route('admin.students', ['tab' => $wasOssStaff ? 'security' : 'admins'])
            ->with('success', 'Account deleted successfully.');
    }

    private function collectAdminChanges(
        string $emailBefore,
        array $infoBefore,
        Admin $admin,
        array $validated
    ): array {

        $changes = [];

        if (
            (string) $admin->email !==
            $emailBefore
        ) {

            $changes[] = [
                'field' =>
                    'Email',

                'from' =>
                    $emailBefore,

                'to' =>
                    (string) $admin->email,
            ];
        }

        $fieldMap = [
            'firstname' =>
                'First Name',

            'middlename' =>
                'Middle Name',

            'lastname' =>
                'Last Name',

            'name_suffix' =>
                'Suffix',

            'status_id' =>
                'Status',

            'role_id' =>
                'Role',
        ];

        $infoAfter =
            $admin->fresh()?->adminInfo;

        foreach (
            $fieldMap as $key => $label
        ) {

            $from =
                (string) (
                    $infoBefore[$key] ?? ''
                );

            $to =
                (string) (
                    $infoAfter->{$key}
                    ?? $validated[$key]
                    ?? ''
                );

            if ($from !== $to) {

                $changes[] = [
                    'field' =>
                        $label,

                    'from' =>
                        $from,

                    'to' =>
                        $to,
                ];
            }
        }

        return $changes;
    }

    /*
    |--------------------------------------------------------------------------
    | Security Account Management
    |--------------------------------------------------------------------------
    */

    public function updateSecurity(
        Request $request,
        Security $security
    ) {

        $validated = $request->validate([
            'email' =>
                'required|email|max:255|unique:security,email,' . $security->id,

            'firstname' =>
                'required|string|max:50',

            'middlename' =>
                'nullable|string|max:50',

            'lastname' =>
                'required|string|max:50',

            'name_suffix' =>
                'nullable|string|max:20',

            'password' =>
                'nullable|string|min:8',
        ]);

        $securityEmailBefore =
            $security->email;

        $securityNameBefore = [
            'firstname' =>
                $security->securityInfo->firstname ?? '',

            'middlename' =>
                $security->securityInfo->middlename ?? '',

            'lastname' =>
                $security->securityInfo->lastname ?? '',

            'name_suffix' =>
                $security->securityInfo->name_suffix ?? '',
        ];

        DB::transaction(function () use (
            $security,
            $validated
        ): void {

            $security->email =
                $validated['email'];

            if (
                !empty($validated['password'])
            ) {
                $security->password =
                    Hash::make(
                        $validated['password']
                    );
            }

            $security->save();

            SecurityInfo::updateOrCreate(
                ['security_id' => $security->id],
                $this->buildSecurityInfoData(
                    $validated
                )
            );
        });

        $this->logUserManagementAction(
            'security',
            'Update',
            $security->id,
            $security->email,
            $this->collectSecurityChanges(
                $securityEmailBefore,
                $securityNameBefore,
                $security,
                $validated
            )
        );

        return redirect()
            ->route(
                'admin.students',
                ['tab' => 'security']
            )
            ->with(
                'success',
                'Security account updated successfully.'
            );
    }

    public function destroySecurity(
        Security $security
    ) {

        $securityEmail =
            $security->email;

        $securityName = trim(
            ($security->securityInfo?->firstname ?? '')
            . ' '
            . ($security->securityInfo?->lastname ?? '')
        ) ?: $securityEmail;

        $securityId =
            $security->id;

        if ($security->securityInfo) {
            $security->securityInfo->delete();
        }

        if ($security->securityProfile) {
            $security->securityProfile->delete();
        }

        $security->delete();

        $this->logUserManagementAction(
            'security',
            'Delete',
            $securityId,
            $securityEmail,
            [
                [
                    'field' =>
                        'Name',

                    'from' =>
                        $securityName,

                    'to' =>
                        '— (deleted)',
                ],

                [
                    'field' =>
                        'Email',

                    'from' =>
                        $securityEmail,

                    'to' =>
                        '— (deleted)',
                ],
            ]
        );

        return redirect()
            ->route(
                'admin.students',
                ['tab' => 'security']
            )
            ->with(
                'success',
                'Security account deleted successfully.'
            );
    }

    private function collectSecurityChanges(
        string $emailBefore,
        array $nameBefore,
        Security $security,
        array $validated
    ): array {

        $changes = [];

        if (
            (string) $security->email !==
            $emailBefore
        ) {

            $changes[] = [
                'field' =>
                    'Email',

                'from' =>
                    $emailBefore,

                'to' =>
                    (string) $security->email,
            ];
        }

        $fieldMap = [
            'firstname' =>
                'First Name',

            'middlename' =>
                'Middle Name',

            'lastname' =>
                'Last Name',

            'name_suffix' =>
                'Suffix',
        ];

        $fresh =
            $security->fresh();

        $nameAfter = [
            'firstname' =>
                $fresh?->securityInfo?->firstname ?? '',

            'middlename' =>
                $fresh?->securityInfo?->middlename ?? '',

            'lastname' =>
                $fresh?->securityInfo?->lastname ?? '',

            'name_suffix' =>
                $fresh?->securityInfo?->name_suffix ?? '',
        ];

        foreach (
            $fieldMap as $key => $label
        ) {

            $from =
                (string) (
                    $nameBefore[$key] ?? ''
                );

            $to =
                (string) (
                    $nameAfter[$key]
                    ?? $validated[$key]
                    ?? ''
                );

            if ($from !== $to) {

                $changes[] = [
                    'field' =>
                        $label,

                    'from' =>
                        $from,

                    'to' =>
                        $to,
                ];
            }
        }

        return $changes;
    }

    /*
    |--------------------------------------------------------------------------
    | Import Accounts
    |--------------------------------------------------------------------------
    */

    public function importAccounts(
        Request $request
    ) {

        $validated = $request->validate([
            'account_type' =>
                'required|in:student,admin,security',

            'import_action' =>
                'nullable|in:create_activate,deactivate_students',

            'import_file' =>
                'required|file|mimes:xlsx,xls,csv',
        ]);

        $importAction =
            $validated['import_action']
            ?? 'create_activate';

        if (
            $validated['account_type'] !== 'student'
            &&
            $importAction === 'deactivate_students'
        ) {

            throw ValidationException::withMessages([
                'import_action' =>
                    'Deactivate import is only available for student accounts.',
            ]);
        }

        $rows =
            $this->extractRowsFromSpreadsheet(
                $request
                    ->file('import_file')
                    ->getRealPath()
            );

        $created = 0;

        DB::transaction(
            function () use (
                $validated,
                $rows,
                $importAction,
                &$created
            ): void {

                foreach ($rows as $row) {

                    if (
                        $validated['account_type']
                        === 'student'
                    ) {

                        if (
                            $importAction
                            === 'deactivate_students'
                        ) {

                            $created +=
                                $this->deactivateStudentRow(
                                    $row
                                );

                            continue;
                        }

                        $created +=
                            $this->importStudentRow(
                                $row
                            );

                        continue;
                    }

                    if (
                        $validated['account_type']
                        === 'admin'
                    ) {

                        $created +=
                            $this->importAdminRow(
                                $row
                            );

                        continue;
                    }

                    $created +=
                        $this->importSecurityRow(
                            $row
                        );
                }
            }
        );

        $tab =
            $validated['account_type']
            === 'student'
                ? 'students'
                : (
                    $validated['account_type']
                    === 'admin'
                        ? 'admins'
                        : 'security'
                );

        if (
            $validated['account_type']
            === 'student'
            &&
            $importAction
            === 'deactivate_students'
        ) {

            return redirect()
                ->route(
                    'admin.students',
                    ['tab' => $tab]
                )
                ->with(
                    'success',
                    $created
                    . ' student account(s) deactivated successfully.'
                );
        }

        return redirect()
            ->route(
                'admin.students',
                ['tab' => $tab]
            )
            ->with(
                'success',
                $created
                . ' '
                . $validated['account_type']
                . ' account(s) imported successfully.'
            );
    }

    public function downloadImportTemplate(
        Request $request
    ) {

        $validated = $request->validate([
            'account_type' =>
                'required|in:student,admin,security',

            'import_action' =>
                'nullable|in:create_activate,deactivate_students',
        ]);

        $importAction =
            $validated['import_action']
            ?? 'create_activate';

        if (
            $validated['account_type'] !== 'student'
            &&
            $importAction === 'deactivate_students'
        ) {

            throw ValidationException::withMessages([
                'import_action' =>
                    'Deactivate template is only available for student accounts.',
            ]);
        }

        [
            $headers,
            $sampleRow
        ] = match (true) {

            $validated['account_type']
                === 'student'
            &&
            $importAction
                === 'deactivate_students'
                => [
                    [
                        'student_number'
                    ],
                    [
                        '2024-00001-TG-0'
                    ]
                ],

            $validated['account_type']
                === 'student'
                => [
                    [
                        'student_number',
                        'first_name',
                        'middle_name',
                        'last_name',
                        'name_suffix',
                        'program',
                        'year',
                        'section',
                        'gender',
                        'ladderized',
                    ],
                    [
                        '2024-00001-TG-0',
                        'Juan',
                        'Dela',
                        'Cruz',
                        '',
                        'BSIT',
                        '1',
                        '1A',
                        'Male',
                        'No',
                    ]
                ],

            $validated['account_type']
                === 'admin'
                => [
                    [
                        'email',
                        'firstname',
                        'middlename',
                        'lastname',
                        'position',
                        'role',
                        'status',
                        'password',
                    ],
                    [
                        'admin.user@example.com',
                        'Ana',
                        'Santos',
                        'Reyes',
                        'Admin',
                        'Admin',
                        'Active',
                        'Temp1234!',
                    ]
                ],

            default
                => [
                    [
                        'email',
                        'firstname',
                        'middlename',
                        'lastname',
                        'position',
                        'contact_number',
                        'address',
                        'password',
                    ],
                    [
                        'security.user@example.com',
                        'Maria',
                        'Santos',
                        'Dela Cruz',
                        'Security Personnel',
                        '09171234567',
                        'PUP Main Campus',
                        'Temp1234!',
                    ]
                ],
        };

        $fileName =
            $validated['account_type']
            . '-import-template.xlsx';

        if (
            $validated['account_type']
            === 'student'
            &&
            $importAction
            === 'deactivate_students'
        ) {

            $fileName =
                'student-deactivation-template.xlsx';
        }

        $spreadsheet =
            new Spreadsheet();

        $sheet =
            $spreadsheet->getActiveSheet();

        $sheet->fromArray(
            $headers,
            null,
            'A1'
        );

        $sheet->fromArray(
            $sampleRow,
            null,
            'A2'
        );

        $tempFile =
            tempnam(
                sys_get_temp_dir(),
                'import_tpl_'
            );

        $writer =
            new Xlsx($spreadsheet);

        $writer->save(
            $tempFile
        );

        return response()
            ->download(
                $tempFile,
                $fileName,
                [
                    'Content-Type' =>
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                ]
            )
            ->deleteFileAfterSend(true);
    }

    private function deactivateStudentRow(
        array $row
    ): int {

        $studentNumber =
            trim(
                (string) (
                    $row['student_number']
                    ?? ''
                )
            );

        if ($studentNumber === '') {
            return 0;
        }

        $inactiveStatusId =
            Status::whereRaw(
                'LOWER(status_name) = ?',
                ['inactive']
            )
            ->value('status_id');

        $inactiveStudentStatusId =
            $this->resolveStudentStatusIdByName(
                'inactive'
            );

        if (
            !$inactiveStatusId
            ||
            !$inactiveStudentStatusId
        ) {
            return 0;
        }

        $student =
            User::where(
                'student_number',
                $studentNumber
            )->first();

        if (
            !$student
            ||
            (int) $student->status_id
            === (int) $inactiveStatusId
        ) {
            return 0;
        }

        $student->status_id =
            $inactiveStatusId;

        $student->save();

        if ($student->studentInfo) {

            $student->studentInfo->student_status_id =
                $inactiveStudentStatusId;

            $student->studentInfo->save();
        }

        return 1;
    }

    /*
    |--------------------------------------------------------------------------
    | Student View / Edit
    |--------------------------------------------------------------------------
    */

    public function show(
        string $student_number
    ) {

        $student = User::query()
            ->with([
                'studentInfo.program',
                'studentInfo.year',
                'studentInfo.section',
                'studentInfo.studentStatus',
                'gender',
                'status',
                'violations' => function ($query) {
                    $query
                        ->notDenied()
                        ->with(
                            'violationType.violationCategory'
                        );
                },
            ])
            ->where(
                'student_number',
                $student_number
            )
            ->firstOrFail();

        return view(
            'admin.students.show',
            compact('student')
        );
    }

    public function edit(
        string $student_number
    ) {

        $student = User::query()
            ->with('studentInfo')
            ->where(
                'student_number',
                $student_number
            )
            ->firstOrFail();

        $programs =
            Program::orderBy('program_name')->get();

        $years =
            Year::orderBy('year')->get();

        $sections =
            Section::orderBy('section_name')->get();

        $genders =
            Gender::orderBy('gender_name')->get();

        $statuses =
            Status::orderBy('status_name')->get();

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

    /**
     * Update normal student information.
     *
     * Year level can be manually changed from the normal Edit Student form.
     */
    public function update(
        Request $request,
        string $student_number
    ) {

        $student = User::query()
            ->where(
                'student_number',
                $student_number
            )
            ->firstOrFail();

        $request->validate([
            'program_id' =>
                'required|exists:program_tbl,program_id',

            'section_id' =>
                'required|exists:section_tbl,section_id',

            'year_id' =>
                'required|exists:year_tbl,year_id',

            'gender_id' =>
                'required|exists:gender_tbl,gender_id',

            'status_id' =>
                'required|exists:status_tbl,status_id',

            'ladderized' =>
                'nullable|in:0,1',
        ]);

        /*
         * Student passwords are managed by the PUP IDP.
         *
         * Identity fields are not editable here.
         *
         * Year level is intentionally editable from the normal Edit Student
         * form and is stored only in student_info_tbl.year_id.
         */
        $studentInfoBefore =
            $student->studentInfo
                ? [
                    'program_id' =>
                        $student->studentInfo->program_id,

                    'year_id' =>
                        $student->studentInfo->year_id,

                    'section_id' =>
                        $student->studentInfo->section_id,

                    'student_status_id' =>
                        $student->studentInfo->student_status_id,

                    'ladderized' =>
                        $student->studentInfo->ladderized,
                ]
                : [];

        $student->update([
            'gender_id' =>
                $request->input('gender_id'),

            'status_id' =>
                $request->input('status_id'),
        ]);

        $studentStatusId =
            $this->resolveStudentStatusIdFromUserStatusId(
                (int) $request->input('status_id')
            );

        if (!$studentStatusId) {

            throw ValidationException::withMessages([
                'status_id' =>
                    'Unable to map the selected status to a valid student status.',
            ]);
        }

        $studentInfo =
            $student->studentInfo()
                ->firstOrNew([
                    'user_id' =>
                        $student->getKey()
                ]);

        $studentInfo->fill([
            'user_id' =>
                $student->getKey(),

            'program_id' =>
                $request->input('program_id'),

            'year_id' =>
                $request->input('year_id'),

            'section_id' =>
                $request->input('section_id'),

            'student_status_id' =>
                $studentStatusId,

            'ladderized' =>
                (int) (
                    $request->input('ladderized')
                    ?? 0
                ),
        ]);

        $studentInfo->save();

        $this->logUserManagementAction(
            'student',
            'Update',
            $student->getKey(),
            $student->email,
            $this->collectStudentChanges(
                $studentInfoBefore,
                $student,
                $studentInfo,
                $request
            )
        );

        return redirect()
            ->route(
                'admin.students',
                ['tab' => 'students']
            )
            ->with(
                'success',
                'Student updated successfully.'
            );
    }

    private function collectStudentChanges(
        array $infoBefore,
        User $student,
        StudentInfo $studentInfo,
        Request $request
    ): array {

        $changes = [];

        $studentFieldMap = [
            'gender_id' =>
                'Gender',

            'status_id' =>
                'Status',
        ];

        foreach (
            $studentFieldMap as $key => $label
        ) {

            $from =
                (string) $student->getOriginal(
                    $key
                );

            $to =
                (string) $student->{$key};

            if ($from !== $to) {

                $changes[] = [
                    'field' =>
                        $label,

                    'from' =>
                        $from,

                    'to' =>
                        $to,
                ];
            }
        }

        $infoFieldMap = [
            'program_id' =>
                'Program',

            /*
             * Year level is editable through the normal Edit Student form,
             * so changes to year_id are included in the audit history.
             */
            'year_id' =>
                'Year',

            'section_id' =>
                'Section',

            'student_status_id' =>
                'Student Status',

            'ladderized' =>
                'Ladderized',
        ];

        foreach (
            $infoFieldMap as $key => $label
        ) {

            $from =
                (string) (
                    $infoBefore[$key] ?? ''
                );

            $to =
                (string) (
                    $studentInfo->{$key}
                    ??
                    $request->input(
                        $key,
                        ''
                    )
                );

            if ($from !== $to) {

                $changes[] = [
                    'field' =>
                        $label,

                    'from' =>
                        $from,

                    'to' =>
                        $to,
                ];
            }
        }

        return $changes;
    }

    /*
    |--------------------------------------------------------------------------
    | IDP Linking
    |--------------------------------------------------------------------------
    */

    public function linkIdpAccount(
        Request $request,
        string $student_number
    ) {

        $student = User::query()
            ->where(
                'student_number',
                $student_number
            )
            ->firstOrFail();

        $validated = $request->validate([
            'idp_subject' =>
                [
                    'required',
                    'string',
                    'max:191'
                ],
        ]);

        $idpSubject =
            trim(
                $validated['idp_subject']
            );

        if ($idpSubject === '') {

            throw ValidationException::withMessages([
                'idp_subject' =>
                    'The IDP Account ID cannot be empty.',
            ]);
        }

        $existing = User::query()
            ->where(
                'idp_subject',
                $idpSubject
            )
            ->where(
                'user_id',
                '!=',
                $student->getKey()
            )
            ->first();

        if ($existing) {

            throw ValidationException::withMessages([
                'idp_subject' =>
                    'This IDP Account ID is already linked to student '
                    . $existing->student_number
                    . '.',
            ]);
        }

        $admin =
            Auth::guard('admin')->user();

        $adminId =
            $admin instanceof Admin
                ? $admin->id
                : null;

        $adminEmail =
            $admin instanceof Admin
                ? $admin->email
                : 'unknown';

        $previousSubject =
            (string) (
                $student->idp_subject ?? ''
            );

        $student->forceFill([
            'idp_subject' =>
                $idpSubject,

            'idp_email' =>
                $request->filled('idp_email')
                    ? strtolower(
                        trim(
                            (string) $request->input(
                                'idp_email'
                            )
                        )
                    )
                    : $student->idp_email,

            'idp_connected_at' =>
                $student->idp_connected_at
                    ?? now(),
        ])->save();

        app(
            \App\Services\AuditLogService::class
        )->userAccountChange(
            userId:
                $student->getKey(),

            actorId:
                $adminId,

            accountType:
                'student',

            action:
                'IDP_LINKED',

            changes:
                [
                    [
                        'field' =>
                            'IDP Subject',

                        'from' =>
                            $previousSubject,

                        'to' =>
                            $idpSubject,
                    ],
                ],

            displayLabel:
                $student->student_number,
        );

        app(
            \App\Services\AuditLogService::class
        )->audit([
            'actor_type' =>
                'admin',

            'actor_id' =>
                $adminId,

            'action' =>
                'Link IDP Account',

            'module' =>
                'Students',

            'description' =>
                'Linked IDP Account ID to student '
                . $student->student_number
                . ' by '
                . $adminEmail
                . '.',
        ], $request);

        return back()
            ->with(
                'success',
                'IDP Account linked to this student successfully.'
            );
    }

    public function unlinkIdpAccount(
        Request $request,
        string $student_number
    ) {

        $student = User::query()
            ->where(
                'student_number',
                $student_number
            )
            ->firstOrFail();

        $admin =
            Auth::guard('admin')->user();

        $adminId =
            $admin instanceof Admin
                ? $admin->id
                : null;

        $adminEmail =
            $admin instanceof Admin
                ? $admin->email
                : 'unknown';

        $previousSubject =
            (string) (
                $student->idp_subject ?? ''
            );

        $student->forceFill([
            'idp_subject' =>
                null,

            'idp_email' =>
                null,

            'idp_connected_at' =>
                null,
        ])->save();

        app(
            \App\Services\AuditLogService::class
        )->userAccountChange(
            userId:
                $student->getKey(),

            actorId:
                $adminId,

            accountType:
                'student',

            action:
                'IDP_UNLINKED',

            changes:
                [
                    [
                        'field' =>
                            'IDP Subject',

                        'from' =>
                            $previousSubject,

                        'to' =>
                            '',
                    ],
                ],

            displayLabel:
                $student->student_number,
        );

        app(
            \App\Services\AuditLogService::class
        )->audit([
            'actor_type' =>
                'admin',

            'actor_id' =>
                $adminId,

            'action' =>
                'Unlink IDP Account',

            'module' =>
                'Students',

            'description' =>
                'Unlinked IDP Account from student '
                . $student->student_number
                . ' by '
                . $adminEmail
                . '.',
        ], $request);

        return back()
            ->with(
                'success',
                'IDP Account unlinked from this student.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Student
    |--------------------------------------------------------------------------
    */

    public function destroy(
        string $student_number
    ) {

        $student = User::query()
            ->where(
                'student_number',
                $student_number
            )
            ->firstOrFail();

        $studentName = trim(
            ($student->first_name ?? '')
            . ' '
            . ($student->middle_name ?? '')
            . ' '
            . ($student->last_name ?? '')
        ) ?: $student_number;

        $program =
            optional(
                $student->studentInfo
            )->program?->program_name
            ?? '—';

        $section =
            optional(
                $student->studentInfo
            )->section?->section_name
            ?? '—';

        $studentId =
            $student->getKey();

        $studentEmail =
            $student->email;

        if ($student->studentInfo) {
            $student->studentInfo->delete();
        }

        $student->delete();

        $this->logUserManagementAction(
            'student',
            'Delete',
            $studentId,
            $studentEmail,
            [
                [
                    'field' =>
                        'Student Number',

                    'from' =>
                        $student_number,

                    'to' =>
                        '— (deleted)',
                ],

                [
                    'field' =>
                        'Name',

                    'from' =>
                        $studentName,

                    'to' =>
                        '— (deleted)',
                ],

                [
                    'field' =>
                        'Program',

                    'from' =>
                        $program,

                    'to' =>
                        '— (deleted)',
                ],

                [
                    'field' =>
                        'Section',

                    'from' =>
                        $section,

                    'to' =>
                        '— (deleted)',
                ],
            ]
        );

        return redirect()
            ->route(
                'admin.students',
                ['tab' => 'students']
            )
            ->with(
                'success',
                'Student deleted successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Spreadsheet Helpers
    |--------------------------------------------------------------------------
    */

    private function extractRowsFromSpreadsheet(
        string $filePath
    ): array {

        $sheet =
            IOFactory::load($filePath)
                ->getActiveSheet();

        $rows =
            $sheet->toArray(
                null,
                true,
                true,
                false
            );

        if (count($rows) < 2) {
            return [];
        }

        $headers =
            array_map(
                function ($value) {

                    $header =
                        strtolower(
                            trim(
                                (string) $value
                            )
                        );

                    return str_replace(
                        [
                            ' ',
                            '-'
                        ],
                        '_',
                        $header
                    );
                },
                $rows[0]
            );

        $normalizedRows = [];

        foreach (
            array_slice(
                $rows,
                1
            ) as $row
        ) {

            $mapped = [];

            $hasValue = false;

            foreach (
                $headers as $index => $header
            ) {

                if ($header === '') {
                    continue;
                }

                $mapped[$header] =
                    isset($row[$index])
                        ? trim(
                            (string) $row[$index]
                        )
                        : null;

                if (
                    ($mapped[$header] ?? '')
                    !== ''
                ) {
                    $hasValue = true;
                }
            }

            if ($hasValue) {
                $normalizedRows[] =
                    $mapped;
            }
        }

        return $normalizedRows;
    }

    private function importStudentRow(
        array $row
    ): int {

        $studentNumber =
            $row['student_number']
            ?? null;

        $email =
            $row['email']
            ?? null;

        if (
            empty($studentNumber)
        ) {
            return 0;
        }

        if (
            User::where(
                'student_number',
                $studentNumber
            )->exists()
        ) {
            return 0;
        }

        $programId =
            Program::where(
                'program_name',
                $row['program'] ?? ''
            )->value('program_id');

        $yearId =
            Year::where(
                'year',
                $row['year'] ?? ''
            )->value('year_id');

        $sectionId =
            Section::where(
                'section_name',
                $row['section'] ?? ''
            )->value('section_id');

        $genderId =
            Gender::where(
                'gender_name',
                $row['gender'] ?? ''
            )->value('gender_id');

        $statusName =
            trim(
                (string) (
                    $row['status'] ?? ''
                )
            );

        $statusId =
            Status::whereRaw(
                'LOWER(status_name) = ?',
                [
                    strtolower(
                        $statusName
                    )
                ]
            )
            ->value('status_id')
            ?? 1;

        $studentStatusId =
            $this->resolveStudentStatusIdByName(
                $statusName
            );

        $ladderized =
            $this->normalizeLadderizedFlag(
                $row['ladderized'] ?? null
            );

        if (!$studentStatusId) {

            $studentStatusId =
                $this->resolveStudentStatusIdFromUserStatusId(
                    (int) $statusId
                );
        }

        if (
            !$programId
            ||
            !$yearId
            ||
            !$sectionId
            ||
            !$genderId
            ||
            !$studentStatusId
        ) {
            return 0;
        }

        $identity = [];

        foreach (
            [
                'first_name' =>
                    'first_name',

                'middle_name' =>
                    'middle_name',

                'last_name' =>
                    'last_name',

                'name_suffix' =>
                    'name_suffix',
            ]
            as $field => $sourceKey
        ) {

            $value =
                $row[$sourceKey]
                ?? null;

            if (
                $value !== null
                &&
                trim((string) $value) !== ''
            ) {

                $identity[$field] =
                    trim(
                        (string) $value
                    );
            }
        }

        if (
            $email !== null
            &&
            trim((string) $email) !== ''
        ) {

            $identity['email'] =
                trim(
                    (string) $email
                );
        }

        $student =
            User::create(
                array_merge(
                    [
                        'student_number' =>
                            $studentNumber,

                        'gender_id' =>
                            $genderId,

                        'status_id' =>
                            $statusId,

                        'roles_id' =>
                            2,

                        'password_hash' =>
                            '',
                    ],
                    $identity
                )
            );

        $student->studentInfo()->create([
            'program_id' =>
                $programId,

            'year_id' =>
                $yearId,

            'section_id' =>
                $sectionId,

            'student_status_id' =>
                $studentStatusId,

            'ladderized' =>
                $ladderized,
        ]);

        return 1;
    }

    private function importAdminRow(
        array $row
    ): int {

        $email =
            $row['email']
            ?? null;

        if (
            empty($email)
            ||
            Admin::where(
                'email',
                $email
            )->exists()
        ) {
            return 0;
        }

        $roleId =
            Role::where(
                'roles_name',
                $row['role'] ?? 'Admin'
            )
            ->value('roles_id')
            ?? 1;

        $statusId =
            Status::where(
                'status_name',
                $row['status'] ?? ''
            )
            ->value('status_id')
            ?? 1;

        $admin =
            Admin::create([
                'username' =>
                    $email,

                'email' =>
                    $email,

                'password' =>
                    Hash::make(
                        $row['password']
                        ?? 'Temp1234!'
                    ),

                'mfa_totp_enabled' =>
                    0,
            ]);

        AdminInfo::updateOrCreate(
            ['admin_id' => $admin->id],
            [
                'Position' =>
                    $row['position']
                    ?? 'Admin',

                'firstname' =>
                    $row['firstname']
                    ?? '',

                'middlename' =>
                    $row['middlename']
                    ?? null,

                'lastname' =>
                    $row['lastname']
                    ?? '',

                'status_id' =>
                    $statusId,

                'role_id' =>
                    $roleId,
            ]
        );

        return 1;
    }

    private function importSecurityRow(
        array $row
    ): int {

        $email =
            $row['email']
            ?? null;

        if (
            empty($email)
            ||
            Security::where(
                'email',
                $email
            )->exists()
        ) {
            return 0;
        }

        $security =
            Security::create([
                'email' =>
                    $email,

                'password' =>
                    Hash::make(
                        $row['password']
                        ?? 'Temp1234!'
                    ),

                'mfa_totp_enabled' =>
                    0,
            ]);

        SecurityInfo::updateOrCreate(
            ['security_id' => $security->id],
            $this->buildSecurityInfoData(
                $row
            )
        );

        return 1;
    }

    private function buildSecurityInfoData(
        array $data
    ): array {

        $securityInfoData = [];

        if (
            Schema::hasColumn(
                'security_info_tbl',
                'firstname'
            )
        ) {

            $securityInfoData['firstname'] =
                $data['firstname']
                ?? '';
        }

        if (
            Schema::hasColumn(
                'security_info_tbl',
                'middlename'
            )
        ) {

            $securityInfoData['middlename'] =
                $data['middlename']
                ?? null;
        }

        if (
            Schema::hasColumn(
                'security_info_tbl',
                'lastname'
            )
        ) {

            $securityInfoData['lastname'] =
                $data['lastname']
                ?? '';
        }

        if (Schema::hasColumn('security_info_tbl', 'name_suffix')) {
            $securityInfoData['name_suffix'] =
                $this->nullableTrim($data['name_suffix'] ?? null);
        }

        return $securityInfoData;
    }

    /*
    |--------------------------------------------------------------------------
    | Student Status Helpers
    |--------------------------------------------------------------------------
    */

    private function resolveStudentStatusIdFromUserStatusId(
        ?int $statusId
    ): ?int {

        if (!$statusId) {
            return null;
        }

        $statusName =
            Status::where(
                'status_id',
                $statusId
            )->value('status_name');

        if (!$statusName) {
            return null;
        }

        return $this->resolveStudentStatusIdByName(
            (string) $statusName
        );
    }

    private function resolveStudentStatusIdByName(
        ?string $statusName
    ): ?int {

        $normalized =
            strtolower(
                trim(
                    (string) $statusName
                )
            );

        if ($normalized === '') {
            return null;
        }

        $existingId =
            StudentStatus::whereRaw(
                'LOWER(status_name) = ?',
                [$normalized]
            )
            ->value('student_status_id');

        if ($existingId) {
            return (int) $existingId;
        }

        if (
            in_array(
                $normalized,
                [
                    'active',
                    'inactive'
                ],
                true
            )
        ) {

            $status =
                StudentStatus::firstOrCreate([
                    'status_name' =>
                        ucfirst($normalized),
                ]);

            return (int)
                $status->student_status_id;
        }

        return null;
    }

    private function normalizeLadderizedFlag(
        mixed $value
    ): int {

        $normalized =
            strtolower(
                trim(
                    (string) $value
                )
            );

        if ($normalized === '') {
            return 0;
        }

        return in_array(
            $normalized,
            [
                '1',
                'yes',
                'y',
                'true',
                'ladderized'
            ],
            true
        )
            ? 1
            : 0;
    }

    /*
    |--------------------------------------------------------------------------
    | History / Audit
    |--------------------------------------------------------------------------
    */

    private function resolveUserDisplayName(
        string $accountType,
        mixed $userId,
        ?string $email = null
    ): string {

        if (
            $accountType === 'student'
        ) {

            $student =
                User::query()
                    ->find($userId);

            if (
                $student
                &&
                $student->student_number
            ) {

                return $student->student_number
                    . (
                        $email
                            ? ' (' . $email . ')'
                            : ''
                    );
            }

            return $email
                ?: (
                    'Student #'
                    . $userId
                );
        }

        if (
            $accountType === 'admin'
        ) {

            $admin =
                Admin::query()
                    ->with('adminInfo')
                    ->find($userId);

            if (
                $admin
                &&
                $admin->adminInfo
            ) {

                $name = trim(
                    ($admin->adminInfo->firstname ?? '')
                    . ' '
                    . ($admin->adminInfo->lastname ?? '')
                );

                return $name
                    ?: $admin->email;
            }

            return $email
                ?: (
                    'Admin #'
                    . $userId
                );
        }

        if (
            $accountType === 'security'
        ) {

            $security =
                Security::query()
                    ->with('securityInfo')
                    ->find($userId);

            if ($security) {

                $info =
                    $security->securityInfo;

                $name = trim(
                    ($info->firstname ?? '')
                    . ' '
                    . ($info->lastname ?? '')
                );

                return $name
                    ?: $security->email;
            }

            return $email
                ?: (
                    'Security #'
                    . $userId
                );
        }

        return $email
            ?: (
                'User #'
                . $userId
            );
    }

    private function logUserManagementAction(
        string $accountType,
        string $action,
        mixed $userId,
        ?string $userEmail,
        array $changes = []
    ): void {

        $admin =
            Auth::guard('admin')->user();

        $adminId =
            $admin instanceof Admin
                ? $admin->id
                : null;

        $userDisplayName =
            $this->resolveUserDisplayName(
                $accountType,
                $userId,
                $userEmail
            );

        app(
            \App\Services\AuditLogService::class
        )->userAccountChange(
            userId:
                $userId,

            actorId:
                $adminId,

            accountType:
                $accountType,

            action:
                $action,

            changes:
                $changes,

            displayLabel:
                $userDisplayName,
        );
    }

    private function auditUserAction(
        string $action,
        string $description
    ): void {

        $admin =
            Auth::guard('admin')->user();

        app(
            \App\Services\AuditLogService::class
        )->audit([
            'actor_type' =>
                'admin',

            'actor_id' =>
                $admin instanceof Admin
                    ? $admin->id
                    : null,

            'action' =>
                $action,

            'module' =>
                'User Management',

            'description' =>
                $description,
        ]);
    }
}