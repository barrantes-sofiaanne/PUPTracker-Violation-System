<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Builds a TRUE incremental backup from MySQL binary logs.
 *
 * Binary logs are streamed directly from the configured remote MySQL server
 * using the Oracle MySQL mysqlbinlog client.
 *
 * The incremental range is bounded by:
 *
 *   previous backup ending position
 *              ↓
 *        current position
 *
 * The resulting binlog stream is gzip-compressed and passed back to
 * BackupEngine for upload to the configured backup disk (R2).
 */
class BinlogArchiveService
{
    public const CHECKSUM_ALGORITHM = 'sha256';

    /**
     * @param array{
     *   base_file: string,
     *   base_position: int
     * } $rangeStart
     *
     * @return array{
     *   success: bool,
     *   temp_file: string|null,
     *   size: int|null,
     *   checksum: string|null,
     *   binlog_file_end: string|null,
     *   binlog_position_end: int|null,
     *   error: string|null,
     * }
     */
    public function archiveIncremental(
        array $rangeStart,
        ?array $currentPosition = null
    ): array {
        $current = $currentPosition
            ?: (new BinaryLogSupportService())->currentPosition();

        if ($current === null) {
            return $this->failure(
                'Could not read the current MySQL binary-log position.'
            );
        }

        $baseFile = trim((string) ($rangeStart['base_file'] ?? ''));
        $basePosition = (int) ($rangeStart['base_position'] ?? 0);

        if ($baseFile === '') {
            return $this->failure(
                'The incremental backup has no valid starting binary-log file.'
            );
        }

        if ($basePosition < 4) {
            return $this->failure(
                'The incremental backup has no valid starting binary-log position.'
            );
        }

        $currentFile = trim((string) ($current['file'] ?? ''));
        $currentPositionValue = (int) ($current['position'] ?? 0);

        if ($currentFile === '' || $currentPositionValue < 4) {
            return $this->failure(
                'The current MySQL binary-log position is invalid.'
            );
        }

        /*
         * Nothing new has been written since the previous backup.
         */
        if (
            $baseFile === $currentFile
            && $currentPositionValue <= $basePosition
        ) {
            return $this->failure(
                'No new binary-log events are available for this incremental backup.'
            );
        }

        $mysqlbinlog = (string) config(
            'backup.mysqlbinlog_path',
            'mysqlbinlog'
        );

        $config = config('database.connections.mysql');

        $host = (string) ($config['host'] ?? '');
        $port = (string) ($config['port'] ?? '3306');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');

        if ($host === '' || $username === '') {
            return $this->failure(
                'The MySQL connection configuration is incomplete.'
            );
        }

        /*
         * mysqlbinlog reads the actual binary-log bytes remotely.
         *
         * --stop-never is intentionally NOT used because every incremental
         * backup must have a deterministic ending boundary.
         *
         * --stop-position applies to the final/current binlog file.
         *
         * When the start and end files differ, mysqlbinlog automatically
         * processes the required sequence of binlog files from the server.
         */
        $command = [
            $mysqlbinlog,
            '--read-from-remote-server',
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $username,
            '--start-position=' . $basePosition,
            '--stop-position=' . $currentPositionValue,
            $baseFile,
        ];

        $process = new Process($command);

        $process->setTimeout(
            (int) config('backup.dump_timeout_seconds', 1800)
        );

        /*
         * MYSQL_PWD prevents the database password from appearing in the
         * process command line.
         */
        $process->setEnv([
            'MYSQL_PWD' => $password,
            'PATH' => (string) getenv('PATH'),
            'HOME' => (string) (getenv('HOME') ?: sys_get_temp_dir()),
        ]);

        $base = tempnam(sys_get_temp_dir(), 'pup_binlog_');

        if ($base === false) {
            return $this->failure(
                'Unable to allocate a temporary incremental file.'
            );
        }

        @unlink($base);

        $rawFile = $base . '.sql';
        $gzipTarget = $base . '.sql.gz';

        $handle = @fopen($rawFile, 'wb');

        if ($handle === false) {
            return $this->failure(
                'Unable to create the temporary incremental file.'
            );
        }

        try {
            $process->run(
                function ($type, $buffer) use ($handle): void {
                    if ($type === Process::OUT) {
                        @fwrite($handle, $buffer);
                    }
                }
            );
        } catch (\Throwable $e) {
            fclose($handle);

            @unlink($rawFile);
            @unlink($gzipTarget);

            Log::warning(
                'Remote mysqlbinlog process failed to start.',
                [
                    'error' => $this->safeMessage($e),
                ]
            );

            return $this->failure(
                'mysqlbinlog could not be started.'
            );
        }

        fclose($handle);

        if (! $process->isSuccessful()) {
            @unlink($rawFile);
            @unlink($gzipTarget);

            Log::warning(
                'Remote mysqlbinlog failed.',
                [
                    'error' => $this->safeProcessError($process),
                ]
            );

            return $this->failure(
                'mysqlbinlog failed while streaming the incremental binary log.'
            );
        }

        /*
         * An empty stream means the requested range produced no usable
         * binary-log data.
         */
        if (! is_file($rawFile) || filesize($rawFile) === 0) {
            @unlink($rawFile);
            @unlink($gzipTarget);

            return $this->failure(
                'The incremental binary-log stream contained no data.'
            );
        }

        if (! $this->gzip($rawFile, $gzipTarget)) {
            @unlink($rawFile);
            @unlink($gzipTarget);

            return $this->failure(
                'Compression of the incremental archive failed.'
            );
        }

        @unlink($rawFile);

        $checksum = hash_file(
            self::CHECKSUM_ALGORITHM,
            $gzipTarget
        );

        if ($checksum === false) {
            @unlink($gzipTarget);

            return $this->failure(
                'Unable to compute the incremental SHA-256 checksum.'
            );
        }

        return [
            'success' => true,
            'temp_file' => $gzipTarget,
            'size' => (int) filesize($gzipTarget),
            'checksum' => $checksum,
            'binlog_file_end' => $currentFile,
            'binlog_position_end' => $currentPositionValue,
            'error' => null,
        ];
    }

    protected function gzip(string $source, string $target): bool
    {
        $src = @fopen($source, 'rb');
        $dst = @gzopen($target, 'wb9');

        if (! $src || ! $dst) {
            if ($src) {
                fclose($src);
            }

            if ($dst) {
                gzclose($dst);
            }

            return false;
        }

        while (! feof($src)) {
            $chunk = fread($src, 1048576);

            if (
                $chunk === false
                || gzwrite($dst, $chunk) === false
            ) {
                fclose($src);
                gzclose($dst);

                return false;
            }
        }

        fclose($src);
        gzclose($dst);

        return true;
    }

    /**
     * @return array{
     *   success: false,
     *   temp_file: null,
     *   size: null,
     *   checksum: null,
     *   binlog_file_end: null,
     *   binlog_position_end: null,
     *   error: string
     * }
     */
    protected function failure(string $message): array
    {
        return [
            'success' => false,
            'temp_file' => null,
            'size' => null,
            'checksum' => null,
            'binlog_file_end' => null,
            'binlog_position_end' => null,
            'error' => $message,
        ];
    }

    protected function safeMessage(\Throwable $e): string
    {
        $message = $e->getMessage();

        $message = preg_replace(
            '/password[=:\s]+[^\s,;]+/i',
            'password=***',
            $message
        ) ?? $message;

        return substr($message, 0, 300);
    }

    protected function safeProcessError(Process $process): string
    {
        $message = trim($process->getErrorOutput());

        if ($message === '') {
            $message = trim($process->getOutput());
        }

        $message = preg_replace(
            '/password[=:\s]+[^\s,;]+/i',
            'password=***',
            $message
        ) ?? $message;

        return substr($message, 0, 300);
    }
}