<?php

namespace Tests;

use App\Models\BackupHistory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Clear memoised schema lookups between tests.
     *
     * Each test rebuilds its own in-memory schema, but PHP static state survives
     * across tests in the same process. `BackupHistory::hasColumn()` memoises per
     * connection, so a test whose fixture omits a column could otherwise teach a
     * later test that the column does not exist — which silently disabled
     * `base_backup_name` / `backup_type` and broke backup reporting.
     */
    protected function tearDown(): void
    {
        BackupHistory::forgetColumnCache();

        parent::tearDown();
    }
}
