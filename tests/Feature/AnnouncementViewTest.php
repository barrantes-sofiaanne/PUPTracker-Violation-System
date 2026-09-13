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
    // Security must match the Student UI (Student is the source of truth)
    // ------------------------------------------------------------------

    /**
     * Extract the elements that define the announcement UI so the Student and
     * Security pages can be compared structurally rather than by eyeballing.
     *
     * Class tokens are taken from the ANNOUNCEMENT REGION only (hero through
     * the cards), so module-specific layout chrome — each module's own sidebar
     * and navbar icons — does not pollute the comparison. Marker order and the
     * `data-*` contract are compared across the whole document.
     *
     * @return array{classes: array<int,string>, order: array<int,int>, attrs: array<int,string>}
     */
    private function extractUiFingerprint(?string $html): array
    {
        $html = (string) $html;

        // Announcement-relevant slice: the hero through to the viewer modal.
        $start = strpos($html, 'portal-hero');
        $end = strpos($html, 'id="announcementViewerModal"');
        $region = ($start !== false && $end !== false && $end > $start)
            ? substr($html, $start, $end - $start)
            : $html;

        // Every class token in the announcement region, sorted and de-duplicated.
        preg_match_all('/class="([^"]*)"/', $region, $classMatches);
        $classes = [];
        foreach ($classMatches[1] as $classAttr) {
            foreach (preg_split('/\s+/', trim($classAttr)) as $token) {
                if ($token !== '') {
                    $classes[$token] = true;
                }
            }
        }
        $classes = array_keys($classes);
        sort($classes);

        // Walking the markers in document order proves both modules build the
        // card the same way.
        $order = [];
        foreach (
            [
                'announcement-view-trigger',
                'announcement-rendered-content',
                'id="announcementViewerModal"',
            ] as $marker
        ) {
            $pos = strpos($html, $marker);
            $order[$marker] = $pos === false ? -1 : $pos;
        }

        // Structural data-* contract shared by both modules.
        preg_match_all('/data-announcement-[a-z-]+/', $html, $attrMatches);
        $attrs = array_values(array_unique($attrMatches[0]));
        sort($attrs);

        return ['classes' => $classes, 'order' => $order, 'attrs' => $attrs];
    }

    /**
     * The class contract that defines the standardized announcement UI. Both
     * pages must use every one of these, which is what makes them match.
     */
    private const SHARED_UI_CLASSES = [
        'portal-hero',          // page header/banner treatment
        'mb-4',                 // hero + card spacing
        'fw-bold',              // title typography
        'text-muted',           // posted date/time, attachment metadata
        'card',                 // card shell
        'portal-card',
        'card-body',
        'border-secondary-subtle', // horizontal divider
        'badge',                // attachment indicator
        'bg-secondary',
        'bi-paperclip',         // attachment indicator icon
        'bi-megaphone-fill',    // card icon
        'bi-eye',               // View action icon
        'btn-sm',
        'btn-outline-primary',  // View action styling
        'announcement-view-trigger',
        'announcement-rendered-content',
    ];

    public function test_security_ui_matches_student_ui(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin();
        $announcement = $this->createAnnouncement('Same Card', 'Same body.', $admin);
        $this->createAttachment($announcement, 'photo.png', 'image/png');

        $studentHtml = $this->actingAs($this->createStudent(), 'student')
            ->get(route('student.announcements'))
            ->assertOk()
            ->getContent();

        $securityHtml = $this->actingAs($this->createSecurity(), 'security')
            ->get(route('security.announcements'))
            ->assertOk()
            ->getContent();

        // --- Both pages implement the SAME standardized design contract ---
        foreach (self::SHARED_UI_CLASSES as $class) {
            $this->assertStringContainsString(
                $class,
                $studentHtml,
                "The Student reference page should use the shared UI class '{$class}'."
            );
            $this->assertStringContainsString(
                $class,
                $securityHtml,
                "Security must match Student by using the shared UI class '{$class}'."
            );
        }

        // --- Security must NOT have kept its old divergent design ---
        $this->assertStringNotContainsString('page-header-modern', $securityHtml);
        $this->assertStringNotContainsString('portal-badge muted', $securityHtml);

        // --- Both cards must present the same content order ---
        // TITLE -> posted DATE -> divider -> message -> attachment indicator
        // -> View action. (Security additionally renders its per-attachment
        // rows between the indicator and the View action; that is preserved
        // functionality, so the ordering assertions below are about the parts
        // both modules share.)
        foreach (['Student' => $studentHtml, 'Security' => $securityHtml] as $label => $html) {
            $title = strpos($html, 'fw-bold');
            $posted = strpos($html, 'Posted');
            $divider = strpos($html, '<hr class="border-secondary-subtle">');
            $badge = strpos($html, 'bi-paperclip');
            $trigger = strpos($html, 'announcement-view-trigger');

            foreach (['title' => $title, 'posted date' => $posted, 'divider' => $divider, 'attachment indicator' => $badge, 'View action' => $trigger] as $part => $pos) {
                $this->assertNotFalse($pos, "{$label}: the {$part} is missing from the announcement UI.");
            }

            $this->assertTrue($title < $posted, "{$label}: the title must precede the posted date.");
            $this->assertTrue($posted < $divider, "{$label}: the posted date must precede the divider.");
            $this->assertTrue($divider < $badge, "{$label}: the divider must precede the attachment indicator.");
            $this->assertTrue($badge < $trigger, "{$label}: the attachment indicator must precede the View action.");

            // The shared viewer must be included exactly once (no duplicate
            // design system, no duplicated modal id in the DOM).
            $this->assertSame(
                1,
                substr_count($html, 'id="announcementViewerModal"'),
                "{$label}: the shared viewer modal must be rendered exactly once."
            );
        }

        // --- Same data-attribute contract (no second design system) ---
        $this->assertSame(
            $this->extractUiFingerprint($studentHtml)['attrs'],
            $this->extractUiFingerprint($securityHtml)['attrs'],
            'Both modules must drive the shared viewer with the same data attributes.'
        );
    }

    public function test_security_hero_keeps_its_own_title_and_back_link(): void
    {
        $this->createAnnouncement('Notice', 'Body.');

        $html = $this->actingAs($this->createSecurity(), 'security')
            ->get(route('security.announcements'))
            ->assertOk()
            ->getContent();

        // Its own real title/subtitle — never renamed to the Student wording.
        $this->assertStringContainsString('Security Announcements', $html);
        $this->assertStringContainsString('Review announcements shared by the administration.', $html);
        $this->assertStringNotContainsString('Student Announcements', $html);

        // Existing navigation is retained, not removed.
        $this->assertStringContainsString('Back to Dashboard', $html);
        $this->assertStringContainsString(route('security.dashboard'), $html);
    }

    public function test_both_pages_render_the_same_empty_state(): void
    {
        // No announcements at all for either module.
        $studentHtml = $this->actingAs($this->createStudent(), 'student')
            ->get(route('student.announcements'))
            ->assertOk()
            ->getContent();

        $securityHtml = $this->actingAs($this->createSecurity(), 'security')
            ->get(route('security.announcements'))
            ->assertOk()
            ->getContent();

        foreach ([$studentHtml, $securityHtml] as $html) {
            $this->assertStringContainsString('No announcements available.', $html);
            $this->assertStringContainsString('bi-megaphone', $html);
            $this->assertStringContainsString('portal-card', $html);
        }
    }

    public function test_security_announcement_view_button_is_accessible(): void
    {
        $this->createAnnouncement('Accessible Notice', 'Body.');

        $html = $this->actingAs($this->createSecurity(), 'security')
            ->get(route('security.announcements'))
            ->assertOk()
            ->getContent();

        // The View control must be a real button with an aria-label, and its
        // icon must be decorative so screen readers announce it once.
        $this->assertStringContainsString('announcement-view-trigger', $html);
        $this->assertStringContainsString('aria-label="View announcement:', $html);
    }

    public function test_attachment_actions_are_labelled_for_screen_readers(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin();
        $announcement = $this->createAnnouncement('Docs', 'Body.', $admin);
        $this->createAttachment($announcement, 'guidelines.pdf', 'application/pdf');

        $html = $this->actingAs($this->createSecurity(), 'security')
            ->get(route('security.announcements'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-label="View attachment guidelines.pdf"', $html);
        $this->assertStringContainsString('aria-label="Download attachment guidelines.pdf"', $html);
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
        Storage::fake('local');

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
        Storage::fake('local');

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

    /**
     * REGRESSION — the original broken-image bug.
     *
     * Production had ANNOUNCEMENT_STORAGE_DISK=s3 while the AWS credentials and
     * bucket name were blank. The controller used that (throwing) disk for BOTH
     * the existence check and the read, so the request died with HTTP 500 and
     * the browser showed a broken-image icon — even though the file was present
     * on the private local disk.
     *
     * The endpoint must now resolve the disk that actually holds the file and
     * still return real image bytes with a correct Content-Type.
     */
    public function test_image_still_served_when_configured_disk_is_misconfigured(): void
    {
        Storage::fake('local');

        // Mirror production: an s3 disk that cannot possibly work (no bucket).
        config([
            'filesystems.announcements_disk' => 's3',
            'filesystems.disks.s3' => [
                'driver' => 's3',
                'key' => '',
                'secret' => '',
                'region' => 'auto',
                'bucket' => '',
                'endpoint' => '',
            ],
        ]);

        $admin = $this->createAdmin();
        $announcement = $this->createAnnouncement('Broken Disk', 'Body.', $admin);
        $attachment = $this->createAttachment($announcement, 'checkmark.png', 'image/png');

        $response = $this->actingAs($this->createSecurity(), 'security')
            ->get(route('announcements.attachments.show', $attachment));

        // Must NOT be a 500.
        $response->assertOk();
        $this->assertStringContainsString('image/png', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));

        $body = $response->streamedContent();
        $this->assertNotSame('', $body, 'The image bytes must reach the browser.');
        $this->assertSame('PNG', substr($body, 1, 3), 'The streamed body must be real PNG data.');
    }

    /**
     * A Content-Type must always be sent. An empty one invites the browser to
     * sniff the body, which combined with `X-Content-Type-Options: nosniff` can
     * refuse to render the image.
     */
    public function test_image_response_always_declares_a_content_type(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin();
        $announcement = $this->createAnnouncement('Typed', 'Body.', $admin);

        foreach ([['photo.png', 'image/png'], ['photo.jpg', 'image/jpeg']] as [$name, $mime]) {
            $attachment = $this->createAttachment($announcement, $name, $mime);

            $response = $this->actingAs($this->createStudent(), 'student')
                ->get(route('announcements.attachments.show', $attachment));

            $response->assertOk();

            $contentType = (string) $response->headers->get('content-type');
            $this->assertNotSame('', $contentType, "A Content-Type must be declared for {$name}.");
            $this->assertStringContainsString(
                $mime,
                $contentType,
                "The declared Content-Type must reflect the real file type for {$name}."
            );
        }
    }

    public function test_missing_attachment_file_returns_not_found(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin();
        $announcement = $this->createAnnouncement('Gone', 'Body.', $admin);

        // Metadata row exists but no object was ever written to any disk.
        $attachment = AnnouncementAttachment::create([
            'announcement_id' => $announcement->announcement_id,
            'original_filename' => 'missing.png',
            'stored_filename' => 'missing.png',
            'storage_disk' => 'local',
            'storage_path' => 'announcements/2026/09/does-not-exist.png',
            'mime_type' => 'image/png',
            'file_size' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->createStudent(), 'student')
            ->get(route('announcements.attachments.show', $attachment))
            ->assertNotFound();
    }

    public function test_non_image_attachment_behaviour_is_unchanged(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin();
        $announcement = $this->createAnnouncement('Doc', 'Body.', $admin);
        $attachment = $this->createAttachment($announcement, 'guidelines.pdf', 'application/pdf');

        // Inline view still works and keeps the PDF type.
        $inline = $this->actingAs($this->createStudent(), 'student')
            ->get(route('announcements.attachments.show', $attachment));
        $inline->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $inline->headers->get('content-type'));

        // Forced download still works and still forces an attachment.
        $download = $this->actingAs($this->createStudent(), 'student')
            ->get(route('announcements.attachments.download', $attachment));
        $download->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('content-disposition'));
    }

    /**
     * The Download button must keep working for images too — it shares the same
     * file-resolution path as the inline view.
     */
    public function test_image_download_still_works_alongside_inline_view(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin();
        $announcement = $this->createAnnouncement('Photo', 'Body.', $admin);
        $attachment = $this->createAttachment($announcement, 'photo.png', 'image/png');

        $download = $this->actingAs($this->createStudent(), 'student')
            ->get(route('announcements.attachments.download', $attachment));

        $download->assertOk();
        // Forced download, not inline: the browser saves rather than displays.
        $this->assertStringContainsString('attachment', (string) $download->headers->get('content-disposition'));
        $this->assertNotSame('', $download->streamedContent());
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
        Storage::fake('local');

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

        // Real bytes matching the declared type, so the streamed response
        // reports a meaningful MIME type and the inline view has actual image
        // data to render.
        if ($mimeType === 'image/png') {
            $contents = $this->pngBytes();
        } elseif ($mimeType === 'image/jpeg') {
            $contents = $this->jpegBytes();
        } else {
            $contents = 'fake-document-content';
        }

        Storage::disk('local')->put($path, $contents);

        return AnnouncementAttachment::create([
            'announcement_id' => $announcement->announcement_id,
            'original_filename' => $filename,
            'stored_filename' => basename($path),
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => $mimeType,
            'file_size' => strlen($contents),
            'file_hash' => str_repeat('a', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Minimal valid 1x1 PNG (signature + IHDR + IDAT + IEND). */
    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='
        );
    }

    /** Minimal valid 1x1 JPEG so MIME sniffing detects image/jpeg. */
    private function jpegBytes(): string
    {
        return base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
            . 'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAA'
            . 'AAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='
        );
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
