<?php

namespace Tests\Feature;

use App\Models\BackupHistory;

class BackupHistoryAccessTest extends BackupTestCase
{
    public function test_normal_admin_cannot_access_backup_history(): void
    {
        $admin = $this->createAdminWithPosition('Guidance Administrator');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.super-admin.backups.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_access_backup_history(): void
    {
        BackupHistory::create([
            'backup_name' => 'full_2026-09-01_020000',
            'backup_type' => 'full',
            'status' => 'success',
            'completed_at' => now()->subDay(),
            'checksum' => str_repeat('c', 64),
            'checksum_algorithm' => 'SHA-256',
            'verified_at' => now()->subDay(),
        ]);

        $admin = $this->createAdminWithPosition('IT Administrator');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.super-admin.backups.index'))
            ->assertOk()
            ->assertSee('full_2026-09-01_020000')
            ->assertSee('FULL');
    }

    public function test_student_cannot_access_backup_history(): void
    {
        $this->get(route('admin.super-admin.backups.index'))
            ->assertRedirect(); // not authenticated -> redirected to login
    }

    public function test_super_admin_backup_history_shows_type_and_integrity(): void
    {
        BackupHistory::create([
            'backup_name' => 'incremental_2026-09-02_020000',
            'backup_type' => 'incremental',
            'parent_backup_id' => null,
            'status' => 'success',
            'completed_at' => now()->subDay(),
            'backup_size' => 1048576,
            'checksum' => str_repeat('d', 64),
            'checksum_algorithm' => 'SHA-256',
            'verified_at' => now()->subDay(),
        ]);

        $admin = $this->createAdminWithPosition('IT Administrator');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.super-admin.backups.show', BackupHistory::first()->id))
            ->assertOk()
            ->assertSee('INCREMENTAL')
            ->assertSee('SHA-256 VERIFIED');
    }
}
