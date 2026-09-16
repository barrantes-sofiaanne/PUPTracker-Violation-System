<?php

/**
 * TEMPORARY verification harness (deleted after use).
 *
 * Renders the REAL admin notification pages for a regular admin and for a Super
 * Admin, with seeded operational + system notifications, so the separation can
 * be confirmed in a browser with real stylesheets.
 */

$dbFile = sys_get_temp_dir() . '/pupt_sa_render.sqlite';
@unlink($dbFile);
touch($dbFile);

$appUrl = 'http://127.0.0.1:8126';
putenv('APP_ENV=local');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbFile);
putenv('APP_URL=' . $appUrl);

foreach (['APP_ENV' => 'local', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $dbFile, 'APP_URL' => $appUrl] as $k => $v) {
    $_ENV[$k] = $v;
    $_SERVER[$k] = $v;
}

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

foreach ([
    'status_tbl' => function ($t) { $t->increments('status_id'); $t->string('status_name'); },
    'roles_tbl' => function ($t) { $t->increments('roles_id'); $t->string('roles_name'); },
    'admins' => function ($t) {
        $t->increments('id');
        $t->string('username')->nullable();
        $t->string('email');
        $t->string('password');
        $t->text('mfa_totp_secret')->nullable();
        $t->boolean('mfa_totp_enabled')->default(false);
        $t->timestamps();
        $t->rememberToken();
    },
    'admin_info_tbl' => function ($t) {
        $t->increments('id');
        $t->unsignedBigInteger('admin_id')->nullable();
        $t->string('Position')->nullable();
        $t->string('firstname')->nullable();
        $t->string('middlename')->nullable();
        $t->string('lastname')->nullable();
    },
    'admin_notifications_tbl' => function ($t) {
        $t->increments('admin_notification_id');
        $t->text('message');
        $t->string('title')->nullable();
        $t->string('notification_type')->nullable();
        $t->string('priority', 16)->nullable();
        $t->string('target_type')->nullable();
        $t->unsignedBigInteger('target_id')->nullable();
        $t->string('recipient_type')->nullable();
        $t->unsignedBigInteger('recipient_id')->nullable();
        $t->boolean('is_read')->default(false);
        $t->dateTime('created_at')->nullable();
    },
    'backup_history' => function ($t) {
        $t->increments('id');
        $t->string('backup_name');
        $t->string('backup_type')->nullable();
        $t->string('status');
        $t->dateTime('started_at')->nullable();
        $t->dateTime('completed_at')->nullable();
        $t->string('destination')->nullable();
        $t->unsignedBigInteger('backup_size')->nullable();
        $t->unsignedBigInteger('file_count')->nullable();
        $t->string('checksum')->nullable();
        $t->string('checksum_algorithm')->nullable();
        $t->dateTime('verified_at')->nullable();
        $t->string('storage_path')->nullable();
        $t->unsignedBigInteger('parent_backup_id')->nullable();
        $t->string('base_backup_name')->nullable();
        $t->text('error_message')->nullable();
        $t->timestamps();
    },
    'audit_logs' => function ($t) { $t->increments('id'); $t->string('action')->nullable(); $t->string('module')->nullable(); $t->string('actor_type')->nullable(); $t->unsignedBigInteger('actor_id')->nullable(); $t->text('description')->nullable(); $t->timestamp('created_at')->nullable(); },
] as $name => $def) {
    if (! Schema::hasTable($name)) {
        Schema::create($name, $def);
    }
}

DB::table('status_tbl')->insert([['status_id' => 1, 'status_name' => 'Active']]);
DB::table('roles_tbl')->insert([['roles_id' => 1, 'roles_name' => 'Admin']]);

$adminId = DB::table('admins')->insertGetId([
    'email' => 'admin@example.com', 'password' => password_hash('x', PASSWORD_BCRYPT),
    'mfa_totp_enabled' => false, 'created_at' => now(), 'updated_at' => now(),
]);
DB::table('admin_info_tbl')->insert(['admin_id' => $adminId, 'Position' => 'Administrator', 'firstname' => 'Ada', 'lastname' => 'Admin']);

$superId = DB::table('admins')->insertGetId([
    'email' => 'it@example.com', 'password' => password_hash('x', PASSWORD_BCRYPT),
    'mfa_totp_enabled' => false, 'created_at' => now(), 'updated_at' => now(),
]);
DB::table('admin_info_tbl')->insert(['admin_id' => $superId, 'Position' => 'IT Administrator', 'firstname' => 'IT', 'lastname' => 'Admin']);

// Operational (student-specific) notifications — Super Admin must NOT see these.
foreach ([
    ['violation_confirmation', 'Violation Awaiting Confirmation', "Security recorded a violation for John Doe (2024-0001): Cheating. Confirm or deny this violation."],
    ['disciplinary_sanction_request', 'Disciplinary Sanction Request', "John Doe requested a disciplinary sanction."],
    ['completion_proof', 'Sanction Proof of Completion', "John Doe (2024-0001) submitted a proof of completion for a sanction."],
    ['sanction_overdue', 'Sanction Overdue', "Sanction record #12 for student John Doe (2024-0001) is overdue."],
] as [$type, $title, $msg]) {
    DB::table('admin_notifications_tbl')->insert([
        'message' => $msg, 'title' => $title, 'notification_type' => $type,
        'priority' => 'info', 'recipient_type' => 'admin', 'recipient_id' => null,
        'is_read' => false, 'created_at' => now()->subMinutes(5),
    ]);
}

// System-level notifications — Super Admin ONLY.
DB::table('admin_notifications_tbl')->insert([
    'message' => 'The scheduled FULL backup "full_2026-09-16_020000" FAILED and no recovery point was produced. Reason: mysqldump exited 1: connection lost. Review Backup Management immediately.',
    'title' => 'Database Backup Failed', 'notification_type' => 'system_backup_failure',
    'priority' => 'critical', 'target_type' => 'backup', 'target_id' => 0,
    'recipient_type' => 'super_admin', 'recipient_id' => null,
    'is_read' => false, 'created_at' => now()->subMinutes(2),
]);
DB::table('admin_notifications_tbl')->insert([
    'message' => 'No backup has reported in 30 hours. Verify the backup service.',
    'title' => 'Backup Has Not Reported', 'notification_type' => 'system_backup_not_reporting',
    'priority' => 'critical', 'target_type' => 'backup', 'target_id' => 0,
    'recipient_type' => 'super_admin', 'recipient_id' => null,
    'is_read' => false, 'created_at' => now()->subMinute(),
]);

$outDir = public_path('render-check');
if (! is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

function fetchAs(string $guard, int $id, string $url, string $file, string $outDir, string $appUrl): void
{
    // CRITICAL: the auth guard caches its resolved user for the life of the
    // process, and the session store is a singleton. Without clearing both, the
    // SECOND fetch is still authenticated as the FIRST account — which made the
    // Super Admin page render as the regular admin and hid the bug being tested.
    app('auth')->forgetGuards();

    $session = app('session.store');
    $session->setId(bin2hex(random_bytes(20)));
    $session->start();
    $session->put('login_' . $guard . '_' . sha1('Illuminate\Auth\SessionGuard'), $id);

    $request = Illuminate\Http\Request::create($appUrl . $url, 'GET');
    $request->setLaravelSession($session);

    /** @var \Illuminate\Contracts\Http\Kernel $k */
    $k = app(Illuminate\Contracts\Http\Kernel::class);
    $response = $k->handle($request);

    $html = $response->getContent();
    file_put_contents($outDir . '/' . $file, $html);

    // Report WHO the page was actually rendered as, so a harness mistake is
    // visible instead of silently producing a misleading result.
    $identity = app('auth')->guard($guard)->user();
    $isSuper = $identity && method_exists($identity, 'isItAdministrator') && $identity->isItAdministrator();

    printf(
        "  %-34s HTTP %d -> %s   [id=%s superAdmin=%s]\n",
        $url, $response->getStatusCode(), $file,
        $identity?->getKey() ?? 'null',
        $isSuper ? 'YES' : 'no'
    );

    app('auth')->forgetGuards();
}

fetchAs('admin', $adminId, '/admin/notifications', 'notif-admin.html', $outDir, $appUrl);
fetchAs('admin', $superId, '/admin/notifications', 'notif-superadmin.html', $outDir, $appUrl);

@unlink($dbFile);
echo "DONE\n";
