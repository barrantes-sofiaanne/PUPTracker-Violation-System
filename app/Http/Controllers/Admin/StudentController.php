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
use App\Models\StudentStatus;
use App\Models\User;
use App\Models\Year;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

        if ($request->filled('filter_program_id')) {
            $students->whereHas('studentInfo', function ($query) use ($request) {
                $query->where('program_id', $request->input('filter_program_id'));
            });
        }

        if ($request->filled('filter_year_id')) {
            $students->whereHas('studentInfo', function ($query) use ($request) {
                $query->where('year_id', $request->input('filter_year_id'));
            });
        }

        if ($request->filled('filter_section_id')) {
            $students->whereHas('studentInfo', function ($query) use ($request) {
                $query->where('section_id', $request->input('filter_section_id'));
            });
        }

        if ($request->filled('filter_gender_id')) {
            $students->where('gender_id', $request->input('filter_gender_id'));
        }

        if ($request->filled('filter_status_id')) {
            $students->where('status_id', $request->input('filter_status_id'));
        }

        $students = $students
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15, ['*'], 'students_page');

        $admins = Admin::query()
            ->with('adminInfo')
            ->when($request->filled('admin_search'), function ($query) use ($request) {
                $search = '%' . trim($request->admin_search) . '%';
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('email', 'like', $search)
                        ->orWhere('username', 'like', $search)
                        ->orWhereHas('adminInfo', function ($adminInfoQuery) use ($search) {
                            $adminInfoQuery->where('firstname', 'like', $search)
                                ->orWhere('lastname', 'like', $search);
                        });
                });
            })
            ->orderBy('id')
            ->paginate(15, ['*'], 'admins_page');

        $securities = Security::query()
            ->with(['securityInfo', 'securityProfile'])
            ->when($request->filled('security_search'), function ($query) use ($request) {
                $search = '%' . trim($request->security_search) . '%';
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('email', 'like', $search)
                        ->orWhereHas('securityProfile', function ($securityProfileQuery) use ($search) {
                            $securityProfileQuery->where('firstname', 'like', $search)
                                ->orWhere('middlename', 'like', $search)
                                ->orWhere('lastname', 'like', $search);
                        })
                        ->orWhereHas('securityInfo', function ($securityInfoQuery) use ($search) {
                            $securityInfoQuery->where('contact_number', 'like', $search)
                                ->orWhere('address', 'like', $search);
                        });
                });
            })
            ->orderBy('id')
            ->paginate(15, ['*'], 'security_page');

        $programs = Program::orderBy('program_name')->get();
        $years = Year::orderBy('year')->get();
        $sections = Section::orderBy('section_name')->get();
        $genders = Gender::orderBy('gender_name')->get();
        $statuses = Status::orderBy('status_name')->get();
        $adminRoles = Role::query()
            ->whereIn('roles_name', ['Admin', 'IT Administrator'])
            ->orderBy('roles_name')
            ->get();

        return view('admin.students.index', compact(
            'students',
            'admins',
            'securities',
            'programs',
            'years',
            'sections',
            'genders',
            'statuses',
            'adminRoles'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_number' => 'required|string|max:50|unique:users_tbl,student_number',
            'first_name' => 'required|string|max:50',
            'middle_name' => 'nullable|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|email|max:255|unique:users_tbl,email',
            'program_id' => 'required|exists:program_tbl,program_id',
            'year_id' => 'required|exists:year_tbl,year_id',
            'section_id' => 'required|exists:section_tbl,section_id',
            'gender_id' => 'required|exists:gender_tbl,gender_id',
            'status_id' => 'required|exists:status_tbl,status_id',
            'password' => 'required|string|min:8',
        ]);

        DB::transaction(function () use ($validated): void {
            $studentStatusId = $this->resolveStudentStatusIdFromUserStatusId((int) $validated['status_id']);

            if (!$studentStatusId) {
                throw ValidationException::withMessages([
                    'status_id' => 'Unable to map the selected status to a valid student status.',
                ]);
            }

            $student = User::create([
                'student_number' => $validated['student_number'],
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'gender_id' => $validated['gender_id'],
                'status_id' => $validated['status_id'],
                'roles_id' => 2,
                'password_hash' => Hash::make($validated['password']),
            ]);

            $student->studentInfo()->create([
                'program_id' => $validated['program_id'],
                'year_id' => $validated['year_id'],
                'section_id' => $validated['section_id'],
                'student_status_id' => $studentStatusId,
                'ladderized' => 0,
            ]);
        });

        return redirect()->route('admin.students', ['tab' => 'students'])
            ->with('success', 'Student account created successfully.');
    }

    public function storeAdmin(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255|unique:admins,email',
            'firstname' => 'required|string|max:50',
            'middlename' => 'nullable|string|max:50',
            'lastname' => 'required|string|max:50',
            'position' => 'nullable|string|max:100',
            'status_id' => 'required|exists:status_tbl,status_id',
            'role_id' => 'required|exists:roles_tbl,roles_id',
            'password' => 'required|string|min:8',
        ]);

        DB::transaction(function () use ($validated): void {
            $admin = Admin::create([
                'username' => $validated['email'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'mfa_totp_enabled' => 0,
            ]);

            AdminInfo::updateOrCreate(
                ['admin_id' => $admin->id],
                [
                    'Position' => $validated['position'] ?? 'Admin',
                    'firstname' => $validated['firstname'],
                    'middlename' => $validated['middlename'] ?? null,
                    'lastname' => $validated['lastname'],
                    'status_id' => $validated['status_id'],
                    'role_id' => $validated['role_id'],
                ]
            );
        });

        return redirect()->route('admin.students', ['tab' => 'admins'])
            ->with('success', 'Admin account created successfully.');
    }

    public function storeSecurity(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255|unique:security,email',
            'firstname' => 'required|string|max:50',
            'middlename' => 'nullable|string|max:50',
            'lastname' => 'required|string|max:50',
            'password' => 'required|string|min:8',
            'contact_number' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated): void {
            $security = Security::create([
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'mfa_totp_enabled' => 0,
            ]);

            SecurityInfo::updateOrCreate(
                ['security_id' => $security->id],
                $this->buildSecurityInfoData($validated)
            );

            $this->syncLegacySecurityNameData($security->id, $validated);
        });

        return redirect()->route('admin.students', ['tab' => 'security'])
            ->with('success', 'Security account created successfully.');
    }

    public function updateAdmin(Request $request, Admin $admin)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255|unique:admins,email,' . $admin->id,
            'firstname' => 'required|string|max:50',
            'middlename' => 'nullable|string|max:50',
            'lastname' => 'required|string|max:50',
            'position' => 'nullable|string|max:100',
            'status_id' => 'required|exists:status_tbl,status_id',
            'role_id' => 'required|exists:roles_tbl,roles_id',
            'password' => 'nullable|string|min:8',
        ]);

        DB::transaction(function () use ($admin, $validated): void {
            $admin->email = $validated['email'];
            $admin->username = $validated['email'];

            if (!empty($validated['password'])) {
                $admin->password = Hash::make($validated['password']);
            }

            $admin->save();

            AdminInfo::updateOrCreate(
                ['admin_id' => $admin->id],
                [
                    'Position' => $validated['position'] ?? 'Admin',
                    'firstname' => $validated['firstname'],
                    'middlename' => $validated['middlename'] ?? null,
                    'lastname' => $validated['lastname'],
                    'status_id' => $validated['status_id'],
                    'role_id' => $validated['role_id'],
                ]
            );
        });

        return redirect()->route('admin.students', ['tab' => 'admins'])
            ->with('success', 'Admin account updated successfully.');
    }

    public function destroyAdmin(Admin $admin)
    {
        if ($admin->adminInfo) {
            $admin->adminInfo->delete();
        }

        $admin->delete();

        return redirect()->route('admin.students', ['tab' => 'admins'])
            ->with('success', 'Admin account deleted successfully.');
    }

    public function updateSecurity(Request $request, Security $security)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255|unique:security,email,' . $security->id,
            'firstname' => 'required|string|max:50',
            'middlename' => 'nullable|string|max:50',
            'lastname' => 'required|string|max:50',
            'password' => 'nullable|string|min:8',
            'contact_number' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($security, $validated): void {
            $security->email = $validated['email'];

            if (!empty($validated['password'])) {
                $security->password = Hash::make($validated['password']);
            }

            $security->save();

            SecurityInfo::updateOrCreate(
                ['security_id' => $security->id],
                $this->buildSecurityInfoData($validated)
            );

            $this->syncLegacySecurityNameData($security->id, $validated);
        });

        return redirect()->route('admin.students', ['tab' => 'security'])
            ->with('success', 'Security account updated successfully.');
    }

    public function destroySecurity(Security $security)
    {
        if ($security->securityInfo) {
            $security->securityInfo->delete();
        }

        if ($security->securityProfile) {
            $security->securityProfile->delete();
        }

        $security->delete();

        return redirect()->route('admin.students', ['tab' => 'security'])
            ->with('success', 'Security account deleted successfully.');
    }

    public function importAccounts(Request $request)
    {
        $validated = $request->validate([
            'account_type' => 'required|in:student,admin,security',
            'import_action' => 'nullable|in:create_activate,deactivate_students',
            'import_file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $importAction = $validated['import_action'] ?? 'create_activate';
        if ($validated['account_type'] !== 'student' && $importAction === 'deactivate_students') {
            throw ValidationException::withMessages([
                'import_action' => 'Deactivate import is only available for student accounts.',
            ]);
        }

        $rows = $this->extractRowsFromSpreadsheet($request->file('import_file')->getRealPath());
        $created = 0;

        DB::transaction(function () use ($validated, $rows, $importAction, &$created): void {
            foreach ($rows as $row) {
                if ($validated['account_type'] === 'student') {
                    if ($importAction === 'deactivate_students') {
                        $created += $this->deactivateStudentRow($row);
                        continue;
                    }

                    $created += $this->importStudentRow($row);
                    continue;
                }

                if ($validated['account_type'] === 'admin') {
                    $created += $this->importAdminRow($row);
                    continue;
                }

                $created += $this->importSecurityRow($row);
            }
        });

        $tab = $validated['account_type'] === 'student'
            ? 'students'
            : ($validated['account_type'] === 'admin' ? 'admins' : 'security');

        if ($validated['account_type'] === 'student' && $importAction === 'deactivate_students') {
            return redirect()->route('admin.students', ['tab' => $tab])
                ->with('success', $created . ' student account(s) deactivated successfully.');
        }

        return redirect()->route('admin.students', ['tab' => $tab])
            ->with('success', $created . ' ' . $validated['account_type'] . ' account(s) imported successfully.');
    }

    public function downloadImportTemplate(Request $request)
    {
        $validated = $request->validate([
            'account_type' => 'required|in:student,admin,security',
            'import_action' => 'nullable|in:create_activate,deactivate_students',
        ]);

        $importAction = $validated['import_action'] ?? 'create_activate';
        if ($validated['account_type'] !== 'student' && $importAction === 'deactivate_students') {
            throw ValidationException::withMessages([
                'import_action' => 'Deactivate template is only available for student accounts.',
            ]);
        }

        [$headers, $sampleRow] = match (true) {
            $validated['account_type'] === 'student' && $importAction === 'deactivate_students' => [[
                'student_number',
            ], [
                '2024-00001-TG-0',
            ]],
            $validated['account_type'] === 'student' => [[
                'student_number', 'first_name', 'middle_name', 'last_name', 'email',
                'program', 'year', 'section', 'gender', 'ladderized',
            ], [
                '2024-00001-TG-0', 'Juan', 'Dela', 'Cruz', 'juan.delacruz@example.com',
                'BSIT', '1', '1A', 'Male', 'No',
            ]],
            $validated['account_type'] === 'admin' => [[
                'email', 'firstname', 'middlename', 'lastname', 'position', 'role', 'status', 'password',
            ], [
                'admin.user@example.com', 'Ana', 'Santos', 'Reyes', 'Admin', 'Admin', 'Active', 'Temp1234!',
            ]],
            default => [[
                'email', 'contact_number', 'address', 'password',
            ], [
                'security.user@example.com', '09171234567', 'PUP Main Campus', 'Temp1234!',
            ]],
        };

        $fileName = $validated['account_type'] . '-import-template.xlsx';
        if ($validated['account_type'] === 'student' && $importAction === 'deactivate_students') {
            $fileName = 'student-deactivation-template.xlsx';
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($sampleRow, null, 'A2');

        $tempFile = tempnam(sys_get_temp_dir(), 'import_tpl_');

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return response()->download(
            $tempFile,
            $fileName,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }
    
    private function deactivateStudentRow(array $row): int
    {
        $studentNumber = trim((string) ($row['student_number'] ?? ''));
        if ($studentNumber === '') {
            return 0;
        }

        $inactiveStatusId = Status::whereRaw('LOWER(status_name) = ?', ['inactive'])->value('status_id');
        $inactiveStudentStatusId = $this->resolveStudentStatusIdByName('inactive');

        if (!$inactiveStatusId || !$inactiveStudentStatusId) {
            return 0;
        }

        $student = User::where('student_number', $studentNumber)->first();
        if (!$student || (int) $student->status_id === (int) $inactiveStatusId) {
            return 0;
        }

        $student->status_id = $inactiveStatusId;
        $student->save();

        if ($student->studentInfo) {
            $student->studentInfo->student_status_id = $inactiveStudentStatusId;
            $student->studentInfo->save();
        }

        return 1;
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
            'password' => 'nullable|string|min:8',
        ]);

        $student->update([
            'first_name' => $request->input('first_name'),
            'middle_name' => $request->input('middle_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'gender_id' => $request->input('gender_id'),
            'status_id' => $request->input('status_id'),
            'password_hash' => $request->filled('password')
                ? Hash::make($request->input('password'))
                : $student->password_hash,
        ]);

        $studentStatusId = $this->resolveStudentStatusIdFromUserStatusId((int) $request->input('status_id'));

        if (!$studentStatusId) {
            throw ValidationException::withMessages([
                'status_id' => 'Unable to map the selected status to a valid student status.',
            ]);
        }

        $studentInfo = $student->studentInfo()->firstOrNew(['user_id' => $student->getKey()]);

        $studentInfo->fill([
            'user_id' => $student->getKey(),
            'program_id' => $request->input('program_id'),
            'year_id' => $request->input('year_id'),
            'section_id' => $request->input('section_id'),
            'student_status_id' => $studentStatusId,
        ]);
        $studentInfo->save();

        return redirect()
            ->route('admin.students', ['tab' => 'students'])
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
            ->route('admin.students', ['tab' => 'students'])
            ->with('success', 'Student deleted successfully.');
    }

    private function extractRowsFromSpreadsheet(string $filePath): array
    {
        $sheet = IOFactory::load($filePath)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        if (count($rows) < 2) {
            return [];
        }

        $headers = array_map(function ($value) {
            $header = strtolower(trim((string) $value));
            $header = str_replace([' ', '-'], '_', $header);

            return $header;
        }, $rows[0]);

        $normalizedRows = [];
        foreach (array_slice($rows, 1) as $row) {
            $mapped = [];
            $hasValue = false;

            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $mapped[$header] = isset($row[$index]) ? trim((string) $row[$index]) : null;
                if (($mapped[$header] ?? '') !== '') {
                    $hasValue = true;
                }
            }

            if ($hasValue) {
                $normalizedRows[] = $mapped;
            }
        }

        return $normalizedRows;
    }

    private function importStudentRow(array $row): int
    {
        $studentNumber = $row['student_number'] ?? null;
        $email = $row['email'] ?? null;

        if (empty($studentNumber) || empty($email)) {
            return 0;
        }

        if (User::where('student_number', $studentNumber)->exists()) {
            return 0;
        }

        $programId = Program::where('program_name', $row['program'] ?? '')->value('program_id');
        $yearId = Year::where('year', $row['year'] ?? '')->value('year_id');
        $sectionId = Section::where('section_name', $row['section'] ?? '')->value('section_id');
        $genderId = Gender::where('gender_name', $row['gender'] ?? '')->value('gender_id');
        $statusName = trim((string) ($row['status'] ?? ''));
        $statusId = Status::whereRaw('LOWER(status_name) = ?', [strtolower($statusName)])->value('status_id') ?? 1;
        $studentStatusId = $this->resolveStudentStatusIdByName($statusName);
        $ladderized = $this->normalizeLadderizedFlag($row['ladderized'] ?? null);

        if (!$studentStatusId) {
            $studentStatusId = $this->resolveStudentStatusIdFromUserStatusId((int) $statusId);
        }

        if (!$programId || !$yearId || !$sectionId || !$genderId || !$studentStatusId) {
            return 0;
        }

        $student = User::create([
            'student_number' => $studentNumber,
            'first_name' => $row['first_name'] ?? '',
            'middle_name' => $row['middle_name'] ?? null,
            'last_name' => $row['last_name'] ?? '',
            'email' => $email,
            'gender_id' => $genderId,
            'status_id' => $statusId,
            'roles_id' => 2,
            'password_hash' => Hash::make($row['password'] ?? 'Temp1234!'),
        ]);

        $student->studentInfo()->create([
            'program_id' => $programId,
            'year_id' => $yearId,
            'section_id' => $sectionId,
            'student_status_id' => $studentStatusId,
            'ladderized' => $ladderized,
        ]);

        return 1;
    }

    private function importAdminRow(array $row): int
    {
        $email = $row['email'] ?? null;
        if (empty($email) || Admin::where('email', $email)->exists()) {
            return 0;
        }

        $roleId = Role::where('roles_name', $row['role'] ?? 'Admin')->value('roles_id') ?? 1;
        $statusId = Status::where('status_name', $row['status'] ?? '')->value('status_id') ?? 1;

        $admin = Admin::create([
            'username' => $email,
            'email' => $email,
            'password' => Hash::make($row['password'] ?? 'Temp1234!'),
            'mfa_totp_enabled' => 0,
        ]);

        AdminInfo::updateOrCreate(
            ['admin_id' => $admin->id],
            [
                'Position' => $row['position'] ?? 'Admin',
                'firstname' => $row['firstname'] ?? '',
                'middlename' => $row['middlename'] ?? null,
                'lastname' => $row['lastname'] ?? '',
                'status_id' => $statusId,
                'role_id' => $roleId,
            ]
        );

        return 1;
    }

    private function importSecurityRow(array $row): int
    {
        $email = $row['email'] ?? null;
        if (empty($email) || Security::where('email', $email)->exists()) {
            return 0;
        }

        $security = Security::create([
            'email' => $email,
            'password' => Hash::make($row['password'] ?? 'Temp1234!'),
            'mfa_totp_enabled' => 0,
        ]);

        SecurityInfo::updateOrCreate(
            ['security_id' => $security->id],
            $this->buildSecurityInfoData($row)
        );

        $this->syncLegacySecurityNameData($security->id, $row);

        return 1;
    }

    private function buildSecurityInfoData(array $data): array
    {
        $securityInfoData = [
            'contact_number' => $data['contact_number'] ?? '',
            'address' => $data['address'] ?? '',
        ];

        // Some environments may still be pending the name-fields migration.
        if (Schema::hasColumn('security_info_tbl', 'firstname')) {
            $securityInfoData['firstname'] = $data['firstname'] ?? '';
        }

        if (Schema::hasColumn('security_info_tbl', 'middlename')) {
            $securityInfoData['middlename'] = $data['middlename'] ?? null;
        }

        if (Schema::hasColumn('security_info_tbl', 'lastname')) {
            $securityInfoData['lastname'] = $data['lastname'] ?? '';
        }

        return $securityInfoData;
    }

    private function syncLegacySecurityNameData(int $securityId, array $data): void
    {
        if (!Schema::hasTable('security_info')) {
            return;
        }

        $legacyData = [];

        if (Schema::hasColumn('security_info', 'firstname')) {
            $legacyData['firstname'] = $data['firstname'] ?? '';
        }

        if (Schema::hasColumn('security_info', 'middlename')) {
            $legacyData['middlename'] = $data['middlename'] ?? null;
        }

        if (Schema::hasColumn('security_info', 'lastname')) {
            $legacyData['lastname'] = $data['lastname'] ?? '';
        }

        if (Schema::hasColumn('security_info', 'updated_at')) {
            $legacyData['updated_at'] = now();
        }

        if (!empty($legacyData)) {
            $existing = DB::table('security_info')
                ->where('security_id', $securityId)
                ->exists();

            if ($existing) {
                DB::table('security_info')
                    ->where('security_id', $securityId)
                    ->update($legacyData);
                return;
            }

            if (Schema::hasColumn('security_info', 'created_at')) {
                $legacyData['created_at'] = now();
            }

            $legacyData['security_id'] = $securityId;
            DB::table('security_info')->insert($legacyData);
        }
    }

    private function resolveStudentStatusIdFromUserStatusId(?int $statusId): ?int
    {
        if (!$statusId) {
            return null;
        }

        $statusName = Status::where('status_id', $statusId)->value('status_name');

        if (!$statusName) {
            return null;
        }

        return $this->resolveStudentStatusIdByName((string) $statusName);
    }

    private function resolveStudentStatusIdByName(?string $statusName): ?int
    {
        $normalized = strtolower(trim((string) $statusName));

        if ($normalized === '') {
            return null;
        }

        $existingId = StudentStatus::whereRaw('LOWER(status_name) = ?', [$normalized])
            ->value('student_status_id');

        if ($existingId) {
            return (int) $existingId;
        }

        // Auto-provision standard statuses so imports/deactivations don't fail
        // when student_status_tbl is missing baseline values.
        if (in_array($normalized, ['active', 'inactive'], true)) {
            $status = StudentStatus::firstOrCreate([
                'status_name' => ucfirst($normalized),
            ]);

            return (int) $status->student_status_id;
        }

        return null;
    }

    private function normalizeLadderizedFlag(mixed $value): int
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return 0;
        }

        return in_array($normalized, ['1', 'yes', 'y', 'true', 'ladderized'], true)
            ? 1
            : 0;
    }
}
