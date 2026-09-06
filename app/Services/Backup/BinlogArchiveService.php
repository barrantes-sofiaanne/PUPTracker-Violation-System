<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Builds a TRUE incremental backup from MySQL binary logs.
 *
 * This service is only ever invoked when BinaryLogSupportService::probe()
 * reports `available = true`. It requires:
 *
 *   - a readable archive directory (BACKUP_BINLOG_ARCHIVE_DIR) containing the
 *     binary-log bytes produced by the MySQL server, and
 *   - the mysqlbinlog client (BACKUP_MYSQLBINLOG_PATH).
 *
 * When those prerequisites are not met the caller MUST NOT call this service;
 * the decision layer falls back to a DAILY_SNAPSHOT instead (never faking an
 * incremental).
 *
 * The incremental output is the raw binary-log bytes emitted by mysqlbinlog
 * for the range (start_file, start_position] .. (end_file, end_position],
 * gzip-compressed, so it can be replayed on top of a restored FULL backup.
 */
class BinlogArchiveService
{
    public const CHECKSUM_ALGORITHM = 'sha256';

    /**
     * @param  array{
     *    base_file: string,
     *    base_position: int,
     *    archive_dir: string
     *  }  $rangeStart  boundary from the previous backup in the chain
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
    public function archiveIncremental(array $rangeStart, ?array $currentPosition = null): array
    {
        $archiveDir = (string) config('backup.binlog_archive_dir', '');
        if ($archiveDir === '' || ! is_dir($archiveDir)) {
            return $this->failure('Binlog archive directory is not readable.');
        }

        $current = $currentPosition ?: (new BinaryLogSupportService())->currentPosition();
        if ($current === null) {
            return $this->failure('Could not read the current MySQL binary-log position.');
        }

        // Only archive files at-or-after the start file, up to the current file.
        $files = $this->binlogFilesInRange($archiveDir, $rangeStart['base_file'], $current['file']);
        if (count($files) === 0) {
            return $this->failure('No new binary-log files found in the archive directory.');
        }

        $mysqlbinlog = (string) config('backup.mysqlbinlog_path', 'mysqlbinlog');

        $command = array_values(array_filter([
            $mysqlbinlog,
            '--start-position=' . (int) $rangeStart['base_position'],
            ...$files,
        ]));

        $process = new Process($command);
        $process->setTimeout((int) config('backup.dump_timeout_seconds', 1800));
        $process->setEnv([
            'PATH' => (string) getenv('PATH'),
            'HOME' => (string) (getenv('HOME') ?: sys_get_temp_dir()),
        ]);

        $base = tempnam(sys_get_temp_dir(), 'pup_binlog_');
        if ($base === false) {
            return $this->failure('Unable to allocate a temporary incremental file.');
        }
        if (is_file($base)) {
            @unlink($base);
        }
        $rawFile = $base . '.sql';
        $gzipTarget = $base . '.sql.gz';
        $handle = @fopen($rawFile, 'wb');

        try {
            $process->run(function ($type, $buffer) use ($handle): void {
                if ($handle && $type === Process::OUT) {
                    @fwrite($handle, $buffer);
                }
            });
        } catch (\Throwable $e) {
            if ($handle) {
                fclose($handle);
            }
            @unlink($rawFile);
            @unlink($gzipTarget);

            return $this->failure('mysqlbinlog could not be started.');
        }
        if ($handle) {
            fclose($handle);
        }

        if (! $process->isSuccessful()) {
            @unlink($rawFile);
            @unlink($gzipTarget);

            return $this->failure('mysqlbinlog failed while archiving binary logs.');
        }

        if (! $this->gzip($rawFile, $gzipTarget)) {
            @unlink($rawFile);
            @unlink($gzipTarget);

            return $this->failure('Compression of the incremental archive failed.');
        }
        @unlink($rawFile);

        $checksum = hash_file(self::CHECKSUM_ALGORITHM, $gzipTarget);
        if ($checksum === false) {
            @unlink($gzipTarget);

            return $this->failure('Unable to compute the incremental SHA-256 checksum.');
        }

        return [
            'success' => true,
            'temp_file' => $gzipTarget,
            'size' => (int) filesize($gzipTarget),
            'checksum' => $checksum,
            'binlog_file_end' => $current['file'],
            'binlog_position_end' => $current['position'],
            'error' => null,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function binlogFilesInRange(string $dir, string $startFile, string $endFile): array
    {
        $all = glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '*');
        if ($all === false) {
            return [];
        }

        // mysql binlogs sort like binlog.000001, binlog.000002 ...
        $candidate = [];
        foreach ($all as $path) {
            if (! is_file($path)) {
                continue;
            }
            $candidate[] = $path;
        }

        usort($candidate, function ($a, $b) {
            return strcmp(basename($a), basename($b));
        });

        $include = false;
        $result = [];
        foreach ($candidate as $path) {
            $name = basename($path);
            if (! $include && $name === $startFile) {
                $include = true;
            }
            if ($include) {
                $result[] = $path;
            }
            if ($name === $endFile) {
                break;
            }
        }

        return $result;
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
            if ($chunk === false || gzwrite($dst, $chunk) === false) {
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
     * @return array{success: false, temp_file: null, size: null, checksum: null, binlog_file_end: null, binlog_position_end: null, error: string}
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
}
