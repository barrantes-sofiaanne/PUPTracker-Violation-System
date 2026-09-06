<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Executes mysqldump safely and produces a gzip-compressed dump with a
 * SHA-256 checksum.
 *
 * Security rules enforced here:
 *   - The process is launched with an argument array (no shell), so no user
 *     input can become a shell command.
 *   - The password is passed through the MYSQL_PWD environment variable
 *     (never on the command line, never logged).
 *   - Errors are sanitized before they reach logs / users.
 *
 * IMPORTANT: this produces a LOGICAL snapshot. It is never labelled
 * "incremental". TRUE incremental backups require binary logs (see
 * BinaryLogSupportService); logical dumps are FULL or DAILY_SNAPSHOT only.
 */
class DatabaseDumpService
{
    public const CHECKSUM_ALGORITHM = 'sha256';

    /**
     * Dump the whole configured database to a temporary gzip file.
     *
     * @return array{
     *   success: bool,
     *   temp_file: string|null,
     *   compressed_size: int|null,
     *   checksum: string|null,
     *   error: string|null,
     * } Returns success=false with a sanitized error on any failure.
     */
    public function dumpDatabase(): array
    {
        $base = tempnam(sys_get_temp_dir(), 'pup_backup_');
        if ($base === false) {
            return $this->failure('Unable to allocate a temporary backup file.');
        }
        if (is_file($base)) {
            @unlink($base);
        }

        $sqlFile = $base . '.sql';
        $gzipTarget = $base . '.sql.gz';

        $sqlFileHandle = @fopen($sqlFile, 'wb');
        if ($sqlFileHandle === false) {
            return $this->failure('Unable to open the temporary dump file for writing.');
        }

        $config = $this->databaseConfig();
        $password = (string) ($config['password'] ?? '');

        // Do NOT include the password anywhere in the argument list.
        $command = array_values(array_filter([
            (string) config('backup.mysqldump_path', 'mysqldump'),
            '--host=' . ($config['host'] ?? '127.0.0.1'),
            '--port=' . ($config['port'] ?? '3306'),
            '--user=' . ($config['username'] ?? 'root'),
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            '--quick',
            '--no-tablespaces',
            '--default-character-set=' . ($config['charset'] ?? 'utf8mb4'),
            ...config('backup.mysqldump_extra_flags', []),
            '--databases',
            (string) ($config['database'] ?? ''),
        ], fn ($v) => $v !== null && $v !== ''));

        $process = new Process($command);
        $process->setTimeout((int) config('backup.dump_timeout_seconds', 1800));
        $process->setEnv($this->processEnv($password));

        try {
            // Stream stdout to the temp file chunk-by-chunk (never buffer the
            // full dump in memory). stderr is collected separately for errors.
            $process->run(function ($type, $buffer) use ($sqlFileHandle): void {
                if ($type === Process::OUT) {
                    @fwrite($sqlFileHandle, $buffer);
                }
            });
        } catch (\Throwable $e) {
            fclose($sqlFileHandle);
            @unlink($sqlFile);
            @unlink($gzipTarget);
            Log::error('Database backup: mysqldump could not run', ['error' => $this->sanitizeError($e->getMessage())]);

            return $this->failure('Database export could not be started (mysqldump).');
        }

        fclose($sqlFileHandle);

        if (! $process->isSuccessful()) {
            @unlink($sqlFile);
            @unlink($gzipTarget);
            Log::error('Database backup: mysqldump failed', [
                'exit_code' => $process->getExitCode(),
                'error' => $this->sanitizeError($process->getErrorOutput() ?: 'unknown mysqldump error'),
            ]);

            return $this->failure('Database export failed (mysqldump).');
        }

        if (! is_file($sqlFile) || filesize($sqlFile) === 0) {
            @unlink($sqlFile);
            @unlink($gzipTarget);

            return $this->failure('Database export produced an empty dump.');
        }

        if (! $this->gzipFile($sqlFile, $gzipTarget)) {
            @unlink($sqlFile);
            @unlink($gzipTarget);

            return $this->failure('Backup compression (gzip) failed.');
        }
        @unlink($sqlFile);

        if (! is_file($gzipTarget) || filesize($gzipTarget) === 0) {
            @unlink($gzipTarget);

            return $this->failure('Compressed backup is empty or unreadable.');
        }

        $checksum = hash_file(self::CHECKSUM_ALGORITHM, $gzipTarget);
        if ($checksum === false) {
            @unlink($gzipTarget);

            return $this->failure('Unable to compute the backup SHA-256 checksum.');
        }

        return [
            'success' => true,
            'temp_file' => $gzipTarget,
            'compressed_size' => (int) filesize($gzipTarget),
            'checksum' => $checksum,
            'error' => null,
        ];
    }

    /**
     * gzip-compress one file into another using PHP's zlib (no external binary,
     * no shell). The intermediate SQL is streamed so memory stays bounded.
     */
    protected function gzipFile(string $sourceFile, string $gzipTarget): bool
    {
        $source = @fopen($sourceFile, 'rb');
        if ($source === false) {
            return false;
        }

        $dest = @gzopen($gzipTarget, 'wb9');
        if ($dest === false) {
            fclose($source);

            return false;
        }

        $ok = true;
        while (! feof($source)) {
            $chunk = fread($source, 1048576);
            if ($chunk === false || gzwrite($dest, $chunk) === false) {
                $ok = false;
                break;
            }
        }

        fclose($source);
        gzclose($dest);

        return $ok;
    }

    /**
     * @return array{success: false, temp_file: null, compressed_size: null, checksum: null, error: string}
     */
    protected function failure(string $message): array
    {
        return [
            'success' => false,
            'temp_file' => null,
            'compressed_size' => null,
            'checksum' => null,
            'error' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function databaseConfig(): array
    {
        return config('database.connections.' . config('database.default')) ?? [];
    }

    /**
     * Environment for the child process. MYSQL_PWD is used instead of
     * --password= so the credential never shows in process listings or logs.
     *
     * @return array<string, string>
     */
    protected function processEnv(string $password): array
    {
        return [
            'MYSQL_PWD' => $password,
            'PATH' => (string) getenv('PATH'),
            'HOME' => (string) (getenv('HOME') ?: sys_get_temp_dir()),
        ];
    }

    protected function sanitizeError(string $raw): string
    {
        $message = Str::limit($raw, 500);
        $message = preg_replace('/password[=:\s]+[^\s,;]+/i', 'password=***', $message) ?? $message;

        return $message;
    }
}
