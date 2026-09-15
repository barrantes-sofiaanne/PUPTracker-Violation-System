<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Secure Announcement Attachment storage tests.
 *
 * Physical files are stored on a PRIVATE disk (never the public web root and
 * never MySQL BLOBs); the database keeps metadata + the announcement
 * relationship. Access is enforced server-side; uploads are validated against
 * an allow-list; deletes remove the object + metadata; every action is
 * audited in audit_logs (never user_management_histories).
 */
class AnnouncementAttachmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTablesForTests();
    }

    // ------------------------------------------------------------------
    // Upload
    // ------------------------------------------------------------------

    public function test_authorized_admin_can_upload_allowed_file(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin('admin@example.com');
        $announcement = $this->createAnnouncement($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [
                    UploadedFile::fake()->create('orientation.pdf', 10, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $this->assertSame(1, $announcement->attachments()->count());

        $attachment = $announcement->attachments()->first();
        $this->assertSame('orientation.pdf', $attachment->original_filename);
        $this->assertNotNull($attachment->storage_path);
        $this->assertNotNull($attachment->storage_disk);
        $this->assertSame(64, strlen((string) $attachment->file_hash));
        $this->assertSame($admin->id, (int) $attachment->uploaded_by);

        // File stored on the private disk (not public).
        $this->privateDisk()->assertExists($attachment->storage_path);
        $this->assertStringStartsWith('announcements/', $attachment->storage_path);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ATTACHMENT_UPLOADED',
            'module' => 'Announcement',
        ]);
    }

    public function test_multiple_attachments_work_correctly(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin('admin@example.com');
        $announcement = $this->createAnnouncement($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [
                    UploadedFile::fake()->create('one.pdf', 10, 'application/pdf'),
                    UploadedFile::fake()->create('two.jpg', 10, 'image/jpeg'),
                    UploadedFile::fake()->create('three.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                ],
            ])
            ->assertRedirect();

        $this->assertSame(3, $announcement->attachments()->count());
    }

    public function test_unauthorized_user_cannot_upload(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin('owner@example.com');
        $otherAdmin = $this->createAdmin('other@example.com');
        $announcement = $this->createAnnouncement($admin);

        // A different admin (not owner, not IT) cannot add to another's announcement.
        $this->actingAs($otherAdmin, 'admin')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [
                    UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
                ],
            ])
            ->assertForbidden();

        $this->assertSame(0, $announcement->attachments()->count());

        // A student cannot reach the admin upload endpoint at all.
        $student = $this->createStudent('2024-0001');
        $this->actingAs($student, 'student')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [
                    UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
                ],
            ])
            ->assertForbidden();
    }

    public function test_unsupported_extension_is_rejected(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin('admin@example.com');
        $announcement = $this->createAnnouncement($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [
                    UploadedFile::fake()->create('evil.php', 10, 'text/php'),
                ],
            ]);

        $this->assertSame(0, $announcement->attachments()->count());
    }

    public function test_svg_and_executables_are_rejected(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin('admin@example.com');
        $announcement = $this->createAnnouncement($admin);

        foreach (['evil.svg', 'evil.exe', 'evil.sh', 'evil.js', 'evil.html'] as $name) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $mime = match ($ext) {
                'svg' => 'image/svg+xml',
                'exe' => 'application/octet-stream',
                'sh' => 'application/x-sh',
                'js' => 'text/javascript',
                'html' => 'text/html',
                default => 'application/octet-stream',
            };

            $this->actingAs($admin, 'admin')
                ->post(route('admin.announcements.attachments.store', $announcement), [
                    'attachments' => [UploadedFile::fake()->create($name, 10, $mime)],
                ]);
        }

        $this->assertSame(0, $announcement->attachments()->count());
    }

    public function test_invalid_mime_type_is_rejected(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin('admin@example.com');
        $announcement = $this->createAnnouncement($admin);

        // A file named .pdf but actually an HTML payload (MIME spoofing).
        $file = UploadedFile::fake()->createWithContent('fake.pdf', '<html>nope</html>');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [$file],
            ]);

        // MIME sniffing rejects the spoofed file.
        $this->assertSame(0, $announcement->attachments()->count());
    }

    public function test_oversized_file_is_rejected(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin('admin@example.com');
        $announcement = $this->createAnnouncement($admin);

        // 11 MB > 10 MB limit.
        $big = UploadedFile::fake()->create('big.pdf', 11 * 1024, 'application/pdf');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [$big],
            ]);

        $this->assertSame(0, $announcement->attachments()->count());
    }

    public function test_stored_filename_is_random_and_not_the_original(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin('admin@example.com');
        $announcement = $this->createAnnouncement($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [
                    UploadedFile::fake()->create('Student Orientation Schedule.pdf', 10, 'application/pdf'),
                ],
            ]);

        $attachment = $announcement->attachments()->first();
        $this->assertSame('Student Orientation Schedule.pdf', $attachment->original_filename);

        // The physical name must be a random server-generated name, never the
        // user-supplied filename, and under a year/month path.
        $this->assertStringStartsWith('announcements/' . now()->format('Y/m'), $attachment->storage_path);
        $this->assertStringNotContainsString('Student Orientation Schedule', (string) $attachment->storage_path);
        $this->assertNotSame($attachment->original_filename, $attachment->stored_filename);
    }

    // ------------------------------------------------------------------
    // Access
    // ------------------------------------------------------------------

    public function test_authorized_student_can_access_published_announcement_attachment(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin('admin@example.com');
        $announcement = $this->createAnnouncement($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [
                    UploadedFile::fake()->create('handbook.pdf', 10, 'application/pdf'),
                ],
            ]);

        $attachment = $announcement->attachments()->first();

        $student = $this->createStudent('2024-0001');
        $response = $this->actingAs($student, 'student')
            ->get(route('announcements.attachments.show', $attachment));

        $response->assertOk();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ATTACHMENT_VIEWED',
            'module' => 'Announcement',
        ]);
    }

    public function test_authorized_student_can_download_published_announcement_attachment(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin('admin@example.com');
        $announcement = $this->createAnnouncement($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [
                    UploadedFile::fake()->create('handbook.pdf', 10, 'application/pdf'),
                ],
            ]);

        $attachment = $announcement->attachments()->first();

        $student = $this->createStudent('2024-0001');
        $this->actingAs($student, 'student')
            ->get(route('announcements.attachments.download', $attachment))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ATTACHMENT_DOWNLOADED',
            'module' => 'Announcement',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_attachment(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin('admin@example.com');
        $announcement = $this->createAnnouncement($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [
                    UploadedFile::fake()->create('handbook.pdf', 10, 'application/pdf'),
                ],
            ]);

        $attachment = $announcement->attachments()->first();

        // Drop the admin session so the next request is unauthenticated.
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $this->flushSession();

        // Unauthenticated requests are redirected by the auth middleware and the
        // file is never streamed.
        $this->get(route('announcements.attachments.show', $attachment))
            ->assertRedirect();
        $this->assertGuest();
    }

    public function test_id_tampering_cannot_access_attachment_of_missing_announcement(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin('admin@example.com');
        $announcement = $this->createAnnouncement($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [
                    UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
                ],
            ]);

        $attachment = $announcement->attachments()->first();
        /** @var AnnouncementAttachment|null $attachment */

        // Tamper: re-point the attachment to an announcement that no longer
        // exists (simulating a replay of an old URL after the announcement is
        // gone). The controller must reject it and audit the denial.
        $attachment->forceFill(['announcement_id' => 999999])->save();

        $student = $this->createStudent('2024-0001');
        $this->actingAs($student, 'student')
            ->get(route('announcements.attachments.show', $attachment))
            ->assertNotFound();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ATTACHMENT_ACCESS_DENIED',
            'module' => 'Announcement',
        ]);
    }

    public function test_attachment_missing_from_storage_returns_404(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin('admin@example.com');
        $announcement = $this->createAnnouncement($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [
                    UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
                ],
            ]);

        $attachment = $announcement->attachments()->first();

        // Remove the physical object (simulates a storage-level loss).
        $this->privateDisk()->delete($attachment->storage_path);

        $student = $this->createStudent('2024-0001');
        $this->actingAs($student, 'student')
            ->get(route('announcements.attachments.show', $attachment))
            ->assertNotFound();
    }

    // ------------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------------

    public function test_authorized_admin_can_delete_attachment(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin('admin@example.com');
        $announcement = $this->createAnnouncement($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [
                    UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
                ],
            ]);

        $attachment = $announcement->attachments()->first();
        $path = $attachment->storage_path;
        $this->privateDisk()->assertExists($path);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.announcements.attachments.destroy', $attachment))
            ->assertRedirect();

        // Metadata removed + object removed + audited.
        $this->assertDatabaseMissing('announcement_attachments', ['attachment_id' => $attachment->attachment_id]);
        $this->privateDisk()->assertMissing($path);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ATTACHMENT_DELETED',
            'module' => 'Announcement',
        ]);
    }

    public function test_unauthorized_admin_cannot_delete_attachment(): void
    {
        Storage::fake('local');

        $owner = $this->createAdmin('owner@example.com');
        $other = $this->createAdmin('other@example.com');
        $announcement = $this->createAnnouncement($owner);

        $this->actingAs($owner, 'admin')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [
                    UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
                ],
            ]);

        $attachment = $announcement->attachments()->first();
        $path = $attachment->storage_path;

        $this->actingAs($other, 'admin')
            ->delete(route('admin.announcements.attachments.destroy', $attachment))
            ->assertForbidden();

        $this->assertDatabaseHas('announcement_attachments', ['attachment_id' => $attachment->attachment_id]);
        $this->privateDisk()->assertExists($path);
    }

    // ------------------------------------------------------------------
    // Announcement deletion cascades to attachments
    // ------------------------------------------------------------------

    public function test_deleting_announcement_removes_its_attachments_and_files(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin('admin@example.com');
        $announcement = $this->createAnnouncement($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [
                    UploadedFile::fake()->create('one.pdf', 10, 'application/pdf'),
                    UploadedFile::fake()->create('two.jpg', 10, 'image/jpeg'),
                ],
            ]);

        $paths = $announcement->attachments()->pluck('storage_path')->all();

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.announcements.destroy', $announcement))
            ->assertRedirect();

        $this->assertDatabaseMissing('announcements_tbl', ['announcement_id' => $announcement->announcement_id]);
        $this->assertSame(0, AnnouncementAttachment::where('announcement_id', $announcement->announcement_id)->count());
        foreach ($paths as $path) {
            $this->privateDisk()->assertMissing($path);
        }
    }

    // ------------------------------------------------------------------
    // Storage is private, not in public webroot
    // ------------------------------------------------------------------

    public function test_attachment_files_are_not_publicly_served_from_webroot(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin('admin@example.com');
        $announcement = $this->createAnnouncement($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [
                    UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
                ],
            ]);

        $attachment = $announcement->attachments()->first();

        // The metadata must point to the private disk, and the file must NOT
        // exist under the public uploads directory.
        $this->assertNotEquals('public', $attachment->storage_disk);
        $this->assertStringNotContainsString('uploads/announcements', (string) $attachment->storage_path);

        // There is no public URL for the secure attachment; only the guarded route.
        $this->assertStringContainsString('/announcements/attachments/', route('announcements.attachments.show', $attachment));
    }

    // ------------------------------------------------------------------
    // Announcement store / update carry attachments
    // ------------------------------------------------------------------

    public function test_creating_announcement_with_attachments_stores_them_securely(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin('admin@example.com');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.announcements.store'), [
                'title' => 'Orientation Week',
                'content' => 'Please attend.',
                'attachments' => [
                    UploadedFile::fake()->create('schedule.pdf', 10, 'application/pdf'),
                    UploadedFile::fake()->create('map.png', 10, 'image/png'),
                ],
            ])
            ->assertRedirect(route('admin.announcements'));

        $announcement = Announcement::where('title', 'Orientation Week')->first();
        $this->assertNotNull($announcement);
        $this->assertSame(2, $announcement->attachments()->count());

        // New announcements never write the legacy public path.
        $this->assertNull($announcement->attachment_path);

        foreach ($announcement->attachments as $attachment) {
            $this->assertStringStartsWith('announcements/', $attachment->storage_path);
            $this->privateDisk()->assertExists($attachment->storage_path);
        }
    }

    public function test_updating_announcement_can_add_secure_attachments_and_keep_legacy_flow(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin('admin@example.com');
        $announcement = $this->createAnnouncement($admin);

        // Add one secure attachment via the store route.
        $this->actingAs($admin, 'admin')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [UploadedFile::fake()->create('one.pdf', 10, 'application/pdf')],
            ])
            ->assertRedirect();

        // Update the announcement and add another attachment.
        $this->actingAs($admin, 'admin')
            ->put(route('admin.announcements.update', $announcement), [
                'title' => 'Updated Title',
                'content' => 'Updated body.',
                'attachments' => [UploadedFile::fake()->create('two.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
            ])
            ->assertRedirect(route('admin.announcements'));

        $announcement->refresh();
        $this->assertSame('Updated Title', $announcement->title);
        $this->assertSame(2, $announcement->attachments()->count());
    }

    public function test_normal_admin_cannot_upload_to_another_admins_announcement_even_via_update(): void
    {
        Storage::fake('local');

        $owner = $this->createAdmin('owner@example.com');
        $other = $this->createAdmin('other@example.com');
        $announcement = $this->createAnnouncement($owner);

        $this->actingAs($other, 'admin')
            ->put(route('admin.announcements.update', $announcement), [
                'title' => 'Hijacked',
                'content' => 'no',
                'attachments' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
            ])
            ->assertForbidden();

        $announcement->refresh();
        $this->assertNotSame('Hijacked', $announcement->title);
        $this->assertSame(0, $announcement->attachments()->count());
    }

    public function test_it_admin_can_manage_any_announcement_attachments(): void
    {
        Storage::fake('local');

        $owner = $this->createAdmin('owner@example.com');
        $itAdmin = $this->createAdmin('it@example.com', true);
        $announcement = $this->createAnnouncement($owner);

        $this->actingAs($itAdmin, 'admin')
            ->post(route('admin.announcements.attachments.store', $announcement), [
                'attachments' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
            ])
            ->assertRedirect();

        $this->assertSame(1, $announcement->attachments()->count());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createAdmin(string $email, bool $isItAdmin = false): Admin
    {
        $admin = Admin::create([
            'username' => $email,
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'mfa_totp_enabled' => false,
        ]);

        DB::table('admin_info_tbl')->insert([
            'admin_id' => $admin->id,
            'position' => $isItAdmin ? 'IT Administrator' : 'Guidance Administrator',
            'firstname' => 'Admin',
            'lastname' => $email,
        ]);

        return $admin;
    }

    private function createStudent(string $studentNumber): User
    {
        return User::create([
            'student_number' => $studentNumber,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => $studentNumber . '@example.com',
            'password_hash' => Hash::make('Password123!'),
            'roles_id' => 2,
            'status_id' => 1,
        ]);
    }

    private function createAnnouncement(Admin $admin): Announcement
    {
        return Announcement::create([
            'admin_id' => $admin->id,
            'title' => 'Test Announcement',
            'content' => 'Hello students.',
            'show_on_login' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * The fake private disk used by these tests (Storage::fake('local')).
     * Typed as FilesystemAdapter so intelephense recognizes the test-only
     * assertExists()/assertMissing() helpers added by Storage::fake().
     */
    private function privateDisk(): FilesystemAdapter
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        return $disk;
    }

    private function ensureTablesForTests(): void
    {
        $tables = [
            'admins' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('username')->nullable();
                $table->string('email');
                $table->string('password');
                $table->boolean('mfa_totp_enabled')->default(false);
            },
            'users_tbl' => function (Blueprint $table): void {
                $table->increments('user_id');
                $table->string('student_number')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('password_hash')->nullable();
                $table->unsignedInteger('roles_id')->nullable();
                $table->unsignedInteger('status_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            },
            'roles_tbl' => function (Blueprint $table): void {
                $table->increments('roles_id');
                $table->string('roles_name');
            },
            'announcements_tbl' => function (Blueprint $table): void {
                $table->increments('announcement_id');
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->string('title')->nullable();
                $table->text('content')->nullable();
                $table->string('attachment_path', 500)->nullable();
                $table->boolean('show_on_login')->default(false);
                $table->timestamp('login_modal_expires_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            },
            'announcement_attachments' => function (Blueprint $table): void {
                $table->increments('attachment_id');
                $table->unsignedBigInteger('announcement_id')->nullable()->index();
                $table->string('original_filename', 255)->nullable();
                $table->string('stored_filename', 255)->nullable();
                $table->string('storage_disk', 50)->nullable()->default('local');
                $table->string('storage_path', 500)->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->char('file_hash', 64)->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            },
            'audit_logs' => function (Blueprint $table): void {
                $table->increments('audit_log_id');
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action');
                $table->string('module')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('created_at')->nullable();
            },
            'admin_info_tbl' => function (Blueprint $table): void {
                $table->unsignedBigInteger('admin_id')->primary();
                $table->string('position')->nullable();
                $table->string('firstname')->nullable();
                $table->string('lastname')->nullable();
            },
            'notifications_tbl' => function (Blueprint $table): void {
                $table->increments('notification_id');
                $table->string('student_number')->nullable();
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->string('type')->nullable();
                $table->string('title')->nullable();
                $table->string('message')->nullable();
                $table->string('target_type')->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('created_at')->nullable();
            },
            'admin_notifications_tbl' => function (Blueprint $table): void {
                $table->increments('admin_notification_id');
                $table->text('message')->nullable();
                $table->string('title')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('created_at')->nullable();
            },
        ];

        foreach ($tables as $table => $blueprint) {
            if (! Schema::hasTable($table)) {
                Schema::create($table, $blueprint);
            }
        }
    }
}
