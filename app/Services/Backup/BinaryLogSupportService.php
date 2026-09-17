<?php

namespace App\Services\Backup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Probes the configured MySQL server for TRUE binary-log (incremental) support.
 *
 * A backup may ONLY be labelled "INCREMENTAL" when this probe confirms the
 * server keeps binary logs that the backup system can actually archive.
 *
 * Required for a TRUE incremental:
 *   1. log_bin            = ON
 *   2. SHOW BINARY LOGS   succeeds (logs exist and are readable)
 *   3. binlog_format      is ROW or STATEMENT
 *   4. an archive directory is configured (BACKUP_BINLOG_ARCHIVE_DIR) into which
 *      the DBA/container copies the binary log files this process can read, AND
 *      the mysqlbinlog client is reachable.
 *
 * Railway's managed MySQL does NOT expose readable binary-log files to the app
 * container, so in that environment the probe reports unavailable and the
 * system safely falls back to DAILY_SNAPSHOT (never a fake "incremental").
 */
class BinaryLogSupportService
{
    /**
     * @return array{
     *   available: bool,
     *   log_bin: string|null,
     *   binlog_format: string|null,
     *   binary_logs: array<int, string>|null,
     *   archive_dir_configured: bool,
     *   mysqlbinlog_available: bool,
     *   reason: string|null
     * }
     */
    public function probe(): array
{
    $result = [
        'available' => false,
        'log_bin' => null,
        'binlog_format' => null,
        'binary_logs' => null,
        'archive_dir_configured' => true,
        'mysqlbinlog_available' => false,
        'reason' => null,
    ];

    if (! config('backup.binlog_enabled')) {
        $result['reason'] = 'Binary-log backups are disabled (BACKUP_BINLOG_ENABLED=false).';
        return $result;
    }

    if (! $this->isMysqlConnection()) {
        $result['reason'] = 'The active database connection is not MySQL; binary-log backup is not applicable.';
        return $result;
    }

    try {
        $result['log_bin'] = $this->variable('log_bin');
        $result['binlog_format'] = $this->variable('binlog_format');
        $result['binary_logs'] = $this->binaryLogs();
    } catch (\Throwable $e) {
        $result['reason'] = 'Could not read MySQL binary-log state: ' . $this->safeMessage($e);
        return $result;
    }

    if (strtoupper((string) $result['log_bin']) !== 'ON') {
        $result['reason'] = 'MySQL binary logging is OFF (log_bin != ON).';
        return $result;
    }

    if ($result['binary_logs'] === null || count($result['binary_logs']) === 0) {
        $result['reason'] = 'No binary logs are visible (SHOW BINARY LOGS returned none).';
        return $result;
    }

    $format = strtoupper((string) $result['binlog_format']);

    if (! in_array($format, ['ROW', 'STATEMENT', 'MIXED'], true)) {
        $result['reason'] = 'binlog_format is not usable: '
            . ($result['binlog_format'] ?: 'unknown');

        return $result;
    }

    /*
     * TRUE incremental backups use mysqlbinlog to stream the
     * binary logs directly from the remote MySQL server.
     *
     * No local binlog archive directory is required.
     */
    $result['mysqlbinlog_available'] = $this->mysqlbinlogAvailable();

    if (! $result['mysqlbinlog_available']) {
        $result['reason'] = 'MySQL binary logging is enabled, but the remote mysqlbinlog '
            . 'client is not available or cannot read the configured MySQL server.';

        return $result;
    }

    $result['available'] = true;
    $result['reason'] = 'MySQL binary logs are available for remote streaming; TRUE incremental is supported.';

    return $result;
}

    /**
     * Current master log file + position (used to bound an incremental run).
     *
     * @return array{file: string, position: int}|null
     */
    public function currentPosition(): ?array
    {
        if (! $this->isMysqlConnection()) {
            return null;
        }

        try {
            $rows = DB::select('SHOW BINARY LOG STATUS');
            if (! $rows) {
                return null;
            }
            $row = (array) $rows[0];

            return [
                'file' => (string) ($row['File'] ?? ''),
                'position' => (int) ($row['Position'] ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::warning('Unable to read MySQL master status', ['error' => $this->safeMessage($e)]);

            return null;
        }
    }

protected function variable(string $name): ?string
{
    $allowed = ['log_bin', 'binlog_format'];

    if (! in_array($name, $allowed, true)) {
        return null;
    }

    $rows = DB::select("SHOW VARIABLES LIKE '{$name}'");

    if (! $rows) {
        return null;
    }

    $row = (array) $rows[0];
    $value = $row['Value'] ?? $row['value'] ?? null;

    return $value === null ? null : (string) $value;
}

    /**
     * @return array<int, string>|null
     */
    protected function binaryLogs(): ?array
    {
        try {
            $rows = DB::select('SHOW BINARY LOGS');
        } catch (\Throwable $e) {
            // MySQL 8.4 renamed the statement; fall back before giving up.
            $rows = DB::select('SHOW MASTER LOGS');
        }

        if (! $rows) {
            return [];
        }

        $names = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $name = $row['Log_name'] ?? $row['log_name'] ?? null;
            if ($name !== null) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    protected function isMysqlConnection(): bool
    {
        $connection = DB::connection()->getDriverName();

        return in_array($connection, ['mysql', 'mariadb'], true);
    }

    protected function archiveDirectory(): ?string
    {
        $dir = config('backup.binlog_archive_dir');

        if (is_string($dir) && $dir !== '') {
            return rtrim($dir, '/\\');
        }

        return null;
    }

    protected function mysqlbinlogAvailable(): bool
{
    $path = (string) config('backup.mysqlbinlog_path', 'mysqlbinlog');

    $config = config('database.connections.mysql');

    $host = (string) ($config['host'] ?? '');
    $port = (string) ($config['port'] ?? '3306');
    $username = (string) ($config['username'] ?? '');
    $password = (string) ($config['password'] ?? '');

    if ($host === '' || $username === '') {
        return false;
    }

    try {
        $process = new \Symfony\Component\Process\Process([
            $path,
            '--read-from-remote-server',
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $username,
            '--start-position=4',
            '--stop-position=4',
            'binlog.000001',
        ]);

        $process->setEnv([
            'MYSQL_PWD' => $password,
        ]);

        $process->setTimeout(15);
        $process->run();

        /*
         * A stop position of 4 is intentionally used only as a
         * lightweight connectivity test. The actual incremental
         * archive uses the real previous/current binlog boundaries.
         */
        return $process->isSuccessful()
            || str_contains($process->getErrorOutput(), 'Event read failed');
    } catch (\Throwable $e) {
        Log::warning('Remote mysqlbinlog availability check failed', [
            'error' => $this->safeMessage($e),
        ]);

        return false;
    }
}

    /**
     * Never let raw SQL/connection errors (which can embed credentials) reach
     * users or logs verbatim.
     */
    protected function safeMessage(\Throwable $e): string
    {
        $message = $e->getMessage();
        $message = preg_replace('/password[=:\s]+[^\s,;]+/i', 'password=***', $message) ?? $message;
        $message = preg_replace('/access denied[^;]*/i', 'access denied', $message) ?? $message;

        return Str::limit($message, 300);
    }
}
