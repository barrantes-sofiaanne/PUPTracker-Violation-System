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
use App\Support\Storage\EvidenceDisk;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
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

        // Programs that offer the Ladderized track, resolved through the SAME
        // rules used by student registration and by the Add/Edit Student
        // server-side validation.
        $ladderizedProgramIds = app(\App\Support\Student\LadderizedPrograms::class)
            ->programIds();

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
                'adminRoles',
                'ladderizedProgramIds'
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
            // Student number is OPTIONAL, exactly like the student
            // registration/profile workflows: a student account can exist
            // before the number is assigned, and identity is always user_id.
            // When a number IS provided it must still be unique.
            'student_number' =>
                [
                    'nullable',
                    'string',
                    'max:50',
                    Rule::unique('users_tbl', 'student_number'),
                ],

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

        // Blank input normalises to NULL (never an empty string or a fabricated
        // placeholder number).
        $studentNumber = $this->nullableTrim($validated['student_number'] ?? null);
        $ladderized = app(\App\Support\Student\LadderizedPrograms::class)
            ->resolveForProgram(
                (int) $validated['program_id'],
                $validated['ladderized'] ?? null
            );

        $student = null;

        DB::transaction(function () use (
            $validated,
            $studentNumber,
            $ladderized,
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
                    $studentNumber,

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
                    $ladderized,
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
    'email' => [
        'required',
        'email',
        'max:255',
        'unique:security,email',
    ],

    'firstname' => [
        'required',
        'string',
        'max:50',
        "regex:/^[\p{L}]+(?:[ .’'-][\p{L}]+)*$/u",
    ],

    'middlename' => [
        'nullable',
        'string',
        'max:50',
        "regex:/^[\p{L}]+(?:[ .’'-][\p{L}]+)*$/u",
    ],

    'lastname' => [
        'required',
        'string',
        'max:50',
        "regex:/^[\p{L}]+(?:[ .’'-][\p{L}]+)*$/u",
    ],

    'name_suffix' => [
        'nullable',
        'string',
        Rule::in([
            'Jr',
            'Jr.',
            'Sr',
            'Sr.',
            'II',
            'III',
            'IV',
            'V',
        ]),
    ],
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
                'Middle Initials',

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
    'email' => [
        'required',
        'email',
        'max:255',
        'unique:security,email,' . $security->id,
    ],

    'firstname' => [
        'required',
        'string',
        'max:50',
        "regex:/^[\p{L}]+(?:[ .’'-][\p{L}]+)*$/u",
    ],

    'middlename' => [
        'nullable',
        'string',
        'max:50',
        "regex:/^[\p{L}]+(?:[ .’'-][\p{L}]+)*$/u",
    ],

    'lastname' => [
        'required',
        'string',
        'max:50',
        "regex:/^[\p{L}]+(?:[ .’'-][\p{L}]+)*$/u",
    ],

    'name_suffix' => [
        'nullable',
        'string',
        Rule::in([
            'Jr',
            'Jr.',
            'Sr',
            'Sr.',
            'II',
            'III',
            'IV',
            'V',
        ]),
    ],

    'password' => [
        'nullable',
        'string',
        'min:8',
    ],
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
                'Middle Initials',

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

        // Pull dropdown options from the same reference tables used by the forms.
        // This keeps the Excel values identical to the values accepted by imports.
        $programOptions = Program::orderBy('program_name')
            ->pluck('program_name')
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->values()
            ->all();

        $yearOptions = Year::orderBy('year')
            ->pluck('year')
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->values()
            ->all();

        $sectionOptions = Section::orderBy('section_name')
            ->pluck('section_name')
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->values()
            ->all();

        $genderOptions = Gender::orderBy('gender_name')
            ->pluck('gender_name')
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->values()
            ->all();

        // Match the roles exposed by the Admin account form.
        $adminRoleOptions = Role::query()
            ->whereIn('roles_name', ['Admin', 'IT Administrator', 'OSS Staff'])
            ->orderBy('roles_name')
            ->pluck('roles_name')
            ->all();

        $accountStatusOptions = Status::orderBy('status_name')
            ->pluck('status_name')
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->values()
            ->all();

        [
            $headers,
            $sampleRow
        ] = match (true) {

            $validated['account_type'] === 'student'
            && $importAction === 'deactivate_students'
                => [
                    ['student_number'],
                    ['2024-00001-TG-0'],
                ],

            $validated['account_type'] === 'student'
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
                        $programOptions[0] ?? '',
                        $yearOptions[0] ?? '',
                        $sectionOptions[0] ?? '',
                        $genderOptions[0] ?? '',
                        'No',
                    ],
                ],

            $validated['account_type'] === 'admin'
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
                        $adminRoleOptions[0] ?? 'Admin',
                        $accountStatusOptions[0] ?? 'Active',
                        'Temp1234!',
                    ],
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
                    ],
                ],
        };

        $fileName = $validated['account_type'] . '-import-template.xlsx';

        if (
            $validated['account_type'] === 'student'
            && $importAction === 'deactivate_students'
        ) {
            $fileName = 'student-deactivation-template.xlsx';
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import Template');

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($sampleRow, null, 'A2');

        $sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex(count($headers)) . '1')
            ->getFont()
            ->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:' . Coordinate::stringFromColumnIndex(count($headers)) . '1');

        foreach (range(1, count($headers)) as $columnIndex) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Put dropdown values on a separate hidden worksheet and reference them
        // through workbook-level named ranges (Excel requires this for cross-sheet lists).
        $listSheet = $spreadsheet->createSheet();
        $listSheet->setTitle('_dropdowns');
        $listSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        $listColumnIndex = 1;
        $addNamedList = function (string $rangeName, array $values) use (
            $spreadsheet,
            $listSheet,
            &$listColumnIndex
        ): bool {
            $values = array_values(array_unique(array_filter(
                $values,
                fn ($value) => trim((string) $value) !== ''
            )));

            if ($values === []) {
                return false;
            }

            $column = Coordinate::stringFromColumnIndex($listColumnIndex++);
            $listSheet->setCellValue($column . '1', $rangeName);

            foreach ($values as $index => $value) {
                $listSheet->setCellValue($column . ($index + 2), (string) $value);
            }

            $startRow = 2;
            $endRow = count($values) + 1;
            $spreadsheet->addNamedRange(new NamedRange(
                $rangeName,
                $listSheet,
                '$' . $column . '$' . $startRow . ':$' . $column . '$' . $endRow
            ));

            return true;
        };

        $dropdowns = [];

        if (
            $validated['account_type'] === 'student'
            && $importAction !== 'deactivate_students'
        ) {
            $dropdowns = [
                'F' => ['ProgramOptions', $programOptions],
                'G' => ['YearOptions', $yearOptions],
                'H' => ['SectionOptions', $sectionOptions],
                'I' => ['GenderOptions', $genderOptions],
                'J' => ['LadderizedOptions', ['Yes', 'No']],
            ];
        } elseif ($validated['account_type'] === 'admin') {
            $dropdowns = [
                'F' => ['AdminRoleOptions', $adminRoleOptions],
                'G' => ['AccountStatusOptions', $accountStatusOptions],
            ];
        }

        foreach ($dropdowns as $column => [$rangeName, $values]) {
            if (!$addNamedList($rangeName, $values)) {
                continue;
            }

            $validation = clone $sheet->getCell($column . '2')->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            // PhpSpreadsheet writes this flag inversely to Excel's showDropDown attribute.
            // true keeps the in-cell dropdown arrow visible in Excel.
            $validation->setShowDropDown(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Invalid selection');
            $validation->setError('Please select a value from the dropdown list.');
            $validation->setPromptTitle('Select a value');
            $validation->setPrompt('Choose a value from the dropdown list.');
            $validation->setFormula1('=' . $rangeName);

            // Provide dropdowns for up to 500 student/account rows.
            for ($rowNumber = 2; $rowNumber <= 501; $rowNumber++) {
                $sheet->getCell($column . $rowNumber)
                    ->setDataValidation(clone $validation);
            }
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'import_tpl_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return response()
            ->download(
                $tempFile,
                $fileName,
                [
                    'Content-Type' =>
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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

        // Accept the internal account id as well as the student number, so a
        // student whose number has not been assigned yet can still be opened.
        // Identity remains users_tbl.user_id.
        $student = $this->findStudentForEditOrFail($student_number);

        $student->load([
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
        ]);

        return view(
            'admin.students.show',
            compact('student')
        );
    }

    public function edit(
        string $student_number
    ) {

        $student = $this->findStudentForEditOrFail($student_number)
            ->load('studentInfo');

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
     * Resolve the student targeted by the edit form.
     *
     * The modal posts the internal account id, while legacy links may still be
     * addressed by student number. `user_id` wins when it matches so a student
     * without a student number remains editable; otherwise the number is used.
     */
    private function findStudentForEditOrFail(string $identifier): User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            abort(404);
        }

        if (ctype_digit($identifier)) {
            $student = User::query()->where('user_id', (int) $identifier)->first();

            if ($student !== null) {
                return $student;
            }
        }

        return User::query()
            ->where('student_number', $identifier)
            ->firstOrFail();
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

        $student = $this->findStudentForEditOrFail($student_number);

        $request->validate([
            'student_number' =>
                [
                    'nullable',
                    'string',
                    'max:50',
                    // Uniqueness is enforced against the canonical account
                    // table, ignoring this student's own row.
                    Rule::unique('users_tbl', 'student_number')
                        ->ignore($student->user_id, 'user_id'),
                ],

            'program_id' =>
                'nullable|exists:program_tbl,program_id',

            'section_id' =>
                'required|exists:section_tbl,section_id',

            'year_id' =>
                'nullable|exists:year_tbl,year_id',

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

        // Ladderized value for this save.
        //
        // • A program that does not offer the track always stores 0.
        // • When the request carries an explicit choice (the modal radios), the
        //   server validates it again against the same program rules.
        // • When the field is absent (e.g. the simple edit form), the stored
        //   value is preserved instead of being silently reset.
        $ladderizedPrograms = app(\App\Support\Student\LadderizedPrograms::class);
        $programId = (int) $request->input('program_id');
        $ladderizedValue = $ladderizedPrograms->supportsProgramId($programId)
            ? ($request->has('ladderized')
                ? (int) $request->input('ladderized')
                : (int) ($studentInfoBefore['ladderized'] ?? 0))
            : 0;

        // Empty input normalises to NULL so the optional student number keeps a
        // single representation. When the field is absent entirely (older
        // callers), the existing value is preserved rather than wiped.
        $newStudentNumber = $request->has('student_number')
            ? trim((string) $request->input('student_number', ''))
            : trim((string) $student->student_number);

        $newStudentNumber = $newStudentNumber === '' ? null : $newStudentNumber;

        $previousStudentNumber = trim((string) $student->student_number);

        $studentBefore = [
            'student_number' => $previousStudentNumber,
            'gender_id' => (string) $student->gender_id,
            'status_id' => (string) $student->status_id,
        ];

        $studentInfo = DB::transaction(function () use (
            $student,
            $request,
            $studentStatusId,
            $newStudentNumber,
            $previousStudentNumber,
            $ladderizedValue
        ): StudentInfo {
            $student->update([
                'student_number' =>
                    $newStudentNumber,

                'gender_id' =>
                    $request->input('gender_id'),

                'status_id' =>
                    $request->input('status_id'),
            ]);

            // Refresh ONLY this account's denormalized display snapshots.
            // Violation/sanction/proof/notification ownership stays anchored
            // to user_id; the number is display data.
            if ($previousStudentNumber !== (string) $newStudentNumber) {
                app(\App\Services\StudentProfileSyncService::class)
                    ->synchronizeStudentNumber(
                        $student,
                        $newStudentNumber,
                        $previousStudentNumber
                    );
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

                // The SAME ladderized rules as registration/Add Student: a
                // program that does not offer the track always stores 0.
                'ladderized' =>
                    $ladderizedValue,
            ]);

            $studentInfo->save();

            return $studentInfo;
        });

        $this->logUserManagementAction(
            'student',
            'Update',
            $student->getKey(),
            $student->email,
            $this->collectStudentChanges(
                $studentInfoBefore,
                $student,
                $studentInfo,
                $request,
                $studentBefore
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
        Request $request,
        array $studentBefore = []
    ): array {

        $changes = [];

        $studentFieldMap = [
            'student_number' =>
                'Student Number',

            'gender_id' =>
                'Gender',

            'status_id' =>
                'Status',
        ];

        foreach (
            $studentFieldMap as $key => $label
        ) {

            $from = (string) (
                array_key_exists($key, $studentBefore)
                    ? $studentBefore[$key]
                    : $student->getOriginal($key)
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

public function destroy(int $user_id)
{
    $student = User::query()
        ->where('user_id', $user_id)
        ->firstOrFail();

    $studentName = trim(
        ($student->first_name ?? '') . ' ' .
        ($student->middle_name ?? '') . ' ' .
        ($student->last_name ?? '')
    );

    if ($studentName === '') {
        $studentName = $student->email ?: 'Student Account #' . $user_id;
    }

    $studentNumberRaw = trim((string) ($student->student_number ?? ''));
    $studentNumber = $studentNumberRaw !== '' ? $studentNumberRaw : '— (not provided)';
    $program = $student->studentInfo?->program?->program_name ?? '—';
    $section = $student->studentInfo?->section?->section_name ?? '—';
    $studentId = (int) $student->user_id;
    $studentEmail = $student->email;

    // Delete the student and every dependent disciplinary row atomically.
    // Legacy violations may have user_id=NULL, so match those by student_number too.
    $cleanup = DB::transaction(function () use ($studentId, $studentNumberRaw): array {
        $violationQuery = DB::table('violation_tbl')->where(function ($query) use ($studentId, $studentNumberRaw) {
            $query->where('user_id', $studentId);
            if ($studentNumberRaw !== '') {
                $query->orWhere(function ($legacy) use ($studentNumberRaw) {
                    $legacy->whereNull('user_id')->where('student_number', $studentNumberRaw);
                });
            }
        });
        $violationIds = $violationQuery->pluck('violation_id')->map(fn ($id) => (int) $id)->all();

        $evidencePaths = [];
        $evidenceIds = [];
        if (Schema::hasTable('violation_evidence_tbl') && $violationIds !== []) {
            $evidenceRows = DB::table('violation_evidence_tbl')
                ->whereIn('violation_id', $violationIds)
                ->get(['evidence_id', 'disk_path']);
            $evidenceIds = $evidenceRows->pluck('evidence_id')->map(fn ($id) => (int) $id)->all();
            $evidencePaths = $evidenceRows->pluck('disk_path')->filter()->values()->all();
        }

        $sanctionRows = collect();
        $sanctionRecordIds = [];
        $proofPaths = [];
        if (Schema::hasTable('student_sanction_records_tbl')) {
            $sanctionRows = DB::table('student_sanction_records_tbl')
                ->where('student_number', $studentNumberRaw)
                ->get();
            $sanctionRecordIds = $sanctionRows->pluck('record_id')->map(fn ($id) => (int) $id)->all();
            if (Schema::hasColumn('student_sanction_records_tbl', 'proof_path')) {
                $proofPaths = $sanctionRows->pluck('proof_path')->filter()->values()->all();
            }
        }

        if (Schema::hasTable('sanction_proofs_tbl')) {
            $proofQuery = DB::table('sanction_proofs_tbl');
            $hasProofMatch = false;
            if ($sanctionRecordIds !== []) {
                $proofQuery->whereIn('record_id', $sanctionRecordIds);
                $hasProofMatch = true;
            }
            if (Schema::hasColumn('sanction_proofs_tbl', 'student_number') && $studentNumberRaw !== '') {
                $proofQuery->when($hasProofMatch,
                    fn ($query) => $query->orWhere('student_number', $studentNumberRaw),
                    fn ($query) => $query->where('student_number', $studentNumberRaw)
                );
                $hasProofMatch = true;
            }
            if ($hasProofMatch) {
                $proofPaths = array_merge($proofPaths, $proofQuery->pluck('disk_path')->filter()->values()->all());
                $proofQuery->delete();
            }
        }

        // Remove evidence-related audit events before their evidence/violation rows.
        if (Schema::hasTable('evidence_security_events_tbl') && ($violationIds !== [] || $evidenceIds !== [])) {
            DB::table('evidence_security_events_tbl')->where(function ($query) use ($violationIds, $evidenceIds) {
                if ($violationIds !== []) {
                    $query->whereIn('violation_id', $violationIds);
                }
                if ($evidenceIds !== []) {
                    $query->orWhereIn('evidence_id', $evidenceIds);
                }
            })->delete();
        }

        if (Schema::hasTable('violation_evidence_tbl') && $violationIds !== []) {
            DB::table('violation_evidence_tbl')->whereIn('violation_id', $violationIds)->delete();
        }

        if (Schema::hasTable('student_sanction_records_tbl') && $sanctionRecordIds !== []) {
            DB::table('student_sanction_records_tbl')->whereIn('record_id', $sanctionRecordIds)->delete();
        }

        // Explicitly delete the violation rows (including legacy rows); dependent
        // edit histories and correction requests cascade through their FKs.
        if ($violationIds !== []) {
            DB::table('violation_tbl')->whereIn('violation_id', $violationIds)->delete();
        }

        $lockedStudent = User::query()->where('user_id', $studentId)->lockForUpdate()->firstOrFail();
        if ($lockedStudent->studentInfo) {
            $lockedStudent->studentInfo->delete();
        }
        $lockedStudent->delete();

        return [
            'file_paths' => array_values(array_unique(array_merge($evidencePaths, $proofPaths))),
            'violations_deleted' => count($violationIds),
            'sanctions_deleted' => count($sanctionRecordIds),
        ];
    });

    // Remove private evidence/proof objects after the database commit. A storage
    // outage must not roll back or partially undo the account deletion.
    foreach ($cleanup['file_paths'] as $path) {
        try {
            EvidenceDisk::disk()->delete($path);
        } catch (\Throwable $exception) {
            Log::warning('Student deletion left a private evidence/proof object for later cleanup.', [
                'user_id' => $studentId,
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    $this->logUserManagementAction(
        'student',
        'Delete',
        $studentId,
        $studentEmail,
        [
            ['field' => 'Student Number', 'from' => $studentNumber, 'to' => '— (deleted)'],
            ['field' => 'Name', 'from' => $studentName, 'to' => '— (deleted)'],
            ['field' => 'Program', 'from' => $program, 'to' => '— (deleted)'],
            ['field' => 'Section', 'from' => $section, 'to' => '— (deleted)'],
            ['field' => 'Violations deleted', 'from' => (string) $cleanup['violations_deleted'], 'to' => '0 remaining'],
            ['field' => 'Sanction records deleted', 'from' => (string) $cleanup['sanctions_deleted'], 'to' => '0 remaining'],
        ]
    );

    return redirect()
        ->route('admin.students', ['tab' => 'students'])
        ->with('success', 'Student and associated violation/sanction records deleted successfully.');
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


private function importStudentRow(array $row): int
    {
        // Student number is optional. Only check duplicates when supplied.
        $studentNumber = $this->nullableTrim($row['student_number'] ?? null);

        if (
            $studentNumber !== null &&
            User::where('student_number', $studentNumber)->exists()
        ) {
            return 0;
        }

        // Email is optional and stored as NULL when blank.
        $email = $this->nullableTrim($row['email'] ?? null);

        if (
            $email !== null &&
            User::where('email', $email)->exists()
        ) {
            return 0;
        }

        // Program is optional. If provided, it must exist in program_tbl.
        $programName = $this->nullableTrim($row['program'] ?? null);
        $programId = $programName !== null
            ? Program::where('program_name', $programName)->value('program_id')
            : null;

        if ($programName !== null && $programId === null) {
            return 0;
        }

        // Year is optional. If provided, it must exist in year_tbl.
        $yearValue = $this->nullableTrim($row['year'] ?? null);
        $yearId = $yearValue !== null
            ? Year::where('year', $yearValue)->value('year_id')
            : null;

        if ($yearValue !== null && $yearId === null) {
            return 0;
        }

        // Section and gender remain optional.
        $sectionName = $this->nullableTrim($row['section'] ?? null);
        $sectionId = $sectionName !== null
            ? Section::where('section_name', $sectionName)->value('section_id')
            : null;

        if ($sectionName !== null && $sectionId === null) {
            return 0;
        }

        $genderName = $this->nullableTrim($row['gender'] ?? null);
        $genderId = $genderName !== null
            ? Gender::where('gender_name', $genderName)->value('gender_id')
            : null;

        if ($genderName !== null && $genderId === null) {
            return 0;
        }

        // Resolve account/student status using the existing system defaults.
        $statusName = trim((string) ($row['status'] ?? 'Active'));
        $statusId = Status::whereRaw(
            'LOWER(status_name) = ?',
            [strtolower($statusName)]
        )->value('status_id') ?? 1;

        $studentStatusId = $this->resolveStudentStatusIdByName($statusName);

        if (!$studentStatusId) {
            $studentStatusId = $this->resolveStudentStatusIdFromUserStatusId(
                (int) $statusId
            );
        }

        if (!$studentStatusId) {
            return 0;
        }

        $ladderized = $this->normalizeLadderizedFlag($row['ladderized'] ?? null);

        $identity = [];
        foreach (['first_name', 'middle_name', 'last_name', 'name_suffix'] as $field) {
            $value = $this->nullableTrim($row[$field] ?? null);
            if ($value !== null) {
                $identity[$field] = $value;
            }
        }

        // Create the student account. Blank optional fields are persisted as NULL.
        $student = User::create(array_merge([
            'student_number' => $studentNumber,
            'email'          => $email,
            'gender_id'      => $genderId,
            'status_id'      => $statusId,
            'roles_id'       => 2,
            'password_hash'  => '',
        ], $identity));

        $student->studentInfo()->create([
            'program_id'        => $programId,
            'year_id'           => $yearId,
            'section_id'        => $sectionId,
            'student_status_id' => $studentStatusId,
            'ladderized'        => $ladderized,
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