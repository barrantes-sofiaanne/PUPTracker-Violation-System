<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\Security;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Standardized "View Announcement" interface across every module.
 *
 * Covers the requirements that:
 *   - the Security Module design is the shared reference (one viewer component);
 *   - every module exposes the same View/Eye action that opens it;
 *   - the viewer is structured TITLE -> MESSAGE -> ACTUAL PHOTO ATTACHMENT;
 *   - the photo is rendered inline from the AUTHORIZED attachment endpoint and
 *     is never moved to public storage or exposed through a public URL.
 */
class AnnouncementViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTablesForTests();
    }

    // ------------------------------------------------------------------
    // Shared viewer is present in every module
    // ------------------------------------------------------------------

    public function test_security_announcements_page_uses_shared_viewer(): void
    {
        $announcement = $this->createAnnouncement('Security Notice', 'Body text.');

        $response = $this->actingAs($this->createSecurity(), 'security')
            ->get(route('security.announcements'));

        $response->assertOk();
        $this->assertSharedViewerRendered($response->getContent());
    }

    public function test_student_announcements_page_uses_shared_viewer(): void
    {
        $this->createAnnouncement('Student Notice', 'Body text.');

        $response = $this->actingAs($this->createStudent(), 'student')
            ->get(route('student.announcements'));

        $response->assertOk();
        $this->assertSharedViewerRendered($response->getContent());
    }

    public function test_admin_announcements_page_uses_shared_viewer(): void
    {
        $admin = $this->createAdmin();
        $this->createAnnouncement('Admin Notice', 'Body text.', $admin);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.announcements'));

        $response->assertOk();
        $this->assertSharedViewerRendered($response->getContent());
    }

    // ------------------------------------------------------------------
    // Photo is offered to the viewer through the AUTHORIZED endpoint
    // ------------------------------------------------------------------

    public function test_image_attachment_is_exposed_to_viewer_via_authorized_route(): void
    {
        $admin = $this->createAdmin();
        $announcement = $this->createAnnouncement('With Photo', 'See photo.', $admin);
        $attachment = $this->createAttachment($announcement, 'photo.jpg', 'image/jpeg');

        $html = $this->actingAs($this->createSecurity(), 'security')
            ->get(route('security.announcements'))
            ->assertOk()
            ->getContent();

        // The payload must flag the file as an image so the viewer renders it.
        $this->assertStringContainsString('&quot;is_image&quot;:true', $html);

        // The URL handed to the <img> must be the authorized endpoint — never a
        // public storage path.
        $this->assertStringContainsString(
            'announcements/attachments/' . $attachment->attachment_id,
            $html
        );
        $this->assertStringNotContainsString('/storage/announcements/', $html);
    }

    /**
     * The shared viewer is a single component: the modal scaffolding, the
     * inline-photo container and the photo styling must all be present, and the
     * module must expose the standardized trigger.
     */
    private function assertSharedViewerRendered(?string $html): void
    {
        $this->assertNotNull($html);

        // Shared viewer markup.
        $this->assertStringContainsString('id="announcementViewerModal"', $html);
        $this->assertStringContainsString('id="announcementViewerTitle"', $html);
        $this->assertStringContainsString('id="announcementViewerContent"', $html);
        $this->assertStringContainsString('id="announcementViewerPhotos"', $html);
        $this->assertStringContainsString('announcement-viewer-photo', $html);

        // Standardized interaction: the Security Module's View/Eye action.
        $this->assertStringContainsString('announcement-view-trigger', $html);

        // No bespoke per-module viewer may linger.
        $this->assertStringNotContainsString('id="announcementDetailModal"', $html);
        $this->assertStringNotContainsString('admin-announcement-view-trigger', $html);
    }

    // ------------------------------------------------------------------
    // Non-image attachments keep their existing behaviour
    // ------------------------------------------------------------------

    public function test_non_image_attachment_is_not_flagged_as_image(): void
    {
        $admin = $this->createAdmin();
        $announcement = $this->createAnnouncement('With PDF', 'See doc.', $admin);
        $this->createAttachment($announcement, 'guidelines.pdf', 'application/pdf');

        $html = $this->actingAs($this->createSecurity(), 'security')
            ->get(route('security.announcements'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('&quot;is_image&quot;:false', $html);
    }

    public function test_announcement_without_attachments_still_renders_viewer(): void
    {
        $this->createAnnouncement('No Attachments', 'Just text.');

        $html = $this->actingAs($this->createSecurity(), 'security')
            ->get(route('security.announcements'))
            ->assertOk()
            ->getContent();

        // The viewer is present (so the message can be read), and no image
        // payload is advertised — there is no blank photo slot.
        $this->assertStringContainsString('id="announcementViewerPhotos"', $html);
        $this->assertStringNotContainsString('&quot;is_image&quot;:true', $html);
    }

    // ------------------------------------------------------------------
    // Photo authorization is preserved
    // ------------------------------------------------------------------

    public function test_authorized_user_receives_image_inline_from_private_disk(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin();
        $announcement = $this->createAnnouncement('Photo', 'Body.', $admin);
        $attachment = $this->createAttachment($announcement, 'photo.jpg', 'image/jpeg');

        $response = $this->actingAs($this->createStudent(), 'student')
            ->get(route('announcements.attachments.show', $attachment));

        $response->assertOk();
        // Inline disposition is what allows the browser to paint the <img>
        // without the user downloading the file first.
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('image/jpeg', (string) $response->headers->get('content-type'));

        // Access remains audited.
        $this->assertDatabaseHas('audit_logs', ['action' => 'ATTACHMENT_VIEWED', 'module' => 'Announcement']);
    }

    public function test_unauthenticated_user_cannot_reach_attachment_endpoint(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin();
        $announcement = $this->createAnnouncement('Photo', 'Body.', $admin);
        $attachment = $this->createAttachment($announcement, 'photo.jpg', 'image/jpeg');

        // Guests are stopped by the route middleware before the controller runs.
        $this->get(route('announcements.attachments.show', $attachment))
            ->assertRedirect(route('student.login'));
    }

    /**
     * Attachments stay on the private disk and are never referenced by a public
     * storage URL in the rendered page.
     */
    public function test_private_attachment_is_never_exposed_as_public_url(): void
    {
        $admin = $this->createAdmin();
        $announcement = $this->createAnnouncement('Photo', 'Body.', $admin);
        $this->createAttachment($announcement, 'photo.jpg', 'image/jpeg');

        $html = $this->actingAs($this->createSecurity(), 'security')
            ->get(route('security.announcements'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('/storage/announcements/', $html);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createAdmin(): Admin
    {
        $admin = Admin::create([
            'username' => 'admin-' . bin2hex(random_bytes(2)),
            'email' => uniqid('admin-', true) . '@example.com',
            'password' => Hash::make('Password123!'),
            'mfa_totp_enabled' => false,
        ]);

        DB::table('admin_info_tbl')->insert([
            'admin_id' => $admin->id,
            'position' => 'Guidance Administrator',
            'firstname' => 'Admin',
            'lastname' => 'User',
        ]);

        return $admin;
    }

    private function createSecurity(): Security
    {
        return Security::create([
            'email' => uniqid('security-', true) . '@example.com',
            'password' => Hash::make('Password123!'),
            'mfa_totp_enabled' => false,
        ]);
    }

    private function createStudent(): User
    {
        return User::create([
            'student_number' => '2026-' . random_int(10000, 99999),
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => uniqid('student-', true) . '@example.com',
            'password_hash' => Hash::make('Password123!'),
            'roles_id' => 2,
            'status_id' => 1,
        ]);
    }

    private function createAnnouncement(string $title, string $content, ?Admin $admin = null): Announcement
    {
        return Announcement::create([
            'admin_id' => $admin?->id,
            'title' => $title,
            'content' => $content,
            'show_on_login' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAttachment(
        Announcement $announcement,
        string $filename,
        string $mimeType,
    ): AnnouncementAttachment {
        $path = 'announcements/' . uniqid('file-', true);

        // Real bytes so the streamed response can report a meaningful MIME type
        // (a 1x1 JPEG for images, plain text for everything else).
        Storage::disk('local')->put(
            $path,
            str_starts_with($mimeType, 'image/')
                ? base64_decode($this->jpegBytes())
                : 'fake-document-content'
        );

        return AnnouncementAttachment::create([
            'announcement_id' => $announcement->announcement_id,
            'original_filename' => $filename,
            'stored_filename' => basename($path),
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => $mimeType,
            'file_size' => 1024,
            'file_hash' => str_repeat('a', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Minimal valid 1x1 JPEG so MIME sniffing detects image/jpeg. */
    private function jpegBytes(): string
    {
        return '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
            . 'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAA'
            . 'AAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==';
    }

    private function ensureTablesForTests(): void
    {
        $tables = [
            'admins' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('username')->nullable();
                $table->string('email');
                $table->string('password');
                $table->string('remember_token', 100)->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
            },
            'security' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('email');
                $table->string('password');
                $table->string('remember_token', 100)->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
            },
            'admin_info_tbl' => function (Blueprint $table): void {
                $table->unsignedBigInteger('admin_id')->primary();
                $table->string('position')->nullable();
                $table->string('designation')->nullable();
                $table->string('role')->nullable();
                $table->string('firstname')->nullable();
                $table->string('lastname')->nullable();
                $table->string('title')->nullable();
            },
            'users_tbl' => function (Blueprint $table): void {
                $table->increments('user_id');
                $table->string('student_number')->nullable();
                $table->string('first_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('name_suffix')->nullable();
                $table->string('email')->nullable();
                $table->string('password_hash')->nullable();
                $table->string('idp_subject')->nullable();
                $table->string('idp_email')->nullable();
                $table->unsignedInteger('roles_id')->nullable();
                $table->unsignedInteger('status_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            },
            'announcements_tbl' => function (Blueprint $table): void {
                $table->increments('announcement_id');
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->string('title')->nullable();
                $table->longText('content')->nullable();
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
            'notifications_tbl' => function (Blueprint $table): void {
                $table->increments('notification_id');
                $table->string('student_number')->nullable();
                $table->string('type')->nullable();
                $table->string('notification_type')->nullable();
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
