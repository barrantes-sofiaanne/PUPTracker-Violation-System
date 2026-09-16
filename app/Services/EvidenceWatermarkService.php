<?php

namespace App\Services;

use App\Models\ViolationEvidence;
use App\Support\Storage\EvidenceDisk;
use Illuminate\Support\Facades\Storage;

/**
 * Server-side evidence watermarking + integrity helpers.
 *
 * The browser may bake a CLIENT-SIDE watermark for instant preview, but that
 * flag is never trusted as proof. This service produces the FINAL trusted
 * image on the server:
 *
 *   1. Visible watermark baked INTO the image pixels (GD): lines
 *        PUPTracker
 *        Evidence ID: EVD-YYYY-NNNNNN
 *        Student ID: <student number>
 *        Captured: YYYY-MM-DD HH:MM:SS
 *      using a translucent band so the underlying evidence stays legible.
 *
 *   2. An auxiliary non-sensitive verification marker stored alongside the
 *      image in private storage: a JSON sidecar containing the evidence code,
 *      a random verification token, and a SHA-256 of that token. This is an
 *      integrity/verification aid (NOT LSB steganography and NOT a guaranteed
 *      screenshot-proof mechanism). No personal data is embedded.
 *
 *   3. SHA-256 of the FINAL stored bytes is returned so the caller can record
 *      it in the DB. The official evidence file is never silently overwritten.
 *
 * Honest limits: an OS-level screenshot/crop/recompress can remove or distort
 * any watermark. Watermark + private storage + auth + audit + SHA-256 are
 * layered controls, not a guarantee of screenshot prevention.
 */
class EvidenceWatermarkService
{
    /** Font path for the baked text (1 = built-in GD font). */
    private const FONT = 2;

    /** Height of the translucent watermark band at the top of the image. */
    private const BAND_HEIGHT = 64;

    /**
     * Bake a visible server-side watermark into a JPG/PNG byte stream and
     * return the re-encoded bytes (final image). If the input is not a
     * readable GD image, returns null (caller decides fallback).
     *
     * @param  string  $imageBytes   original file bytes
     * @param  string  $evidenceCode EVD-YYYY-NNNNNN
     * @param  string  $studentId    student number (no other PII)
     * @param  string|null  $capturedAt display timestamp
     */
    public function watermarkImage(
        string $imageBytes,
        string $evidenceCode,
        string $studentId,
        ?string $capturedAt = null,
    ): ?string {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring($imageBytes);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width < 40 || $height < 40) {
            imagedestroy($source);

            return null;
        }

        // Work on a truecolor copy.
        $canvas = imagecreatetruecolor($width, $height);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $width, $height);
        imagedestroy($source);

        // Semi-transparent dark band + white text. GD alpha range is 0-127
        // (0 = fully opaque, 127 = fully transparent). 75 ≈ 41% opacity.
        $bandAlpha = 75;
        $band = imagecolorallocatealpha($canvas, 0, 0, 0, $bandAlpha);
        imagefilledrectangle($canvas, 0, 0, $width - 1, self::BAND_HEIGHT - 1, $band);

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $shadow = imagecolorallocate($canvas, 0, 0, 0);

        $lines = [
            'PUPTracker',
            'Evidence ID: ' . $evidenceCode,
            'Student ID: ' . $studentId,
            'Captured: ' . ($capturedAt !== null && $capturedAt !== '' ? $capturedAt : now()->format('Y-m-d H:i:s')),
        ];

        $y = 8;
        foreach ($lines as $line) {
            // Draw a subtle shadow for legibility over light photos.
            imagestring($canvas, self::FONT, 7, $y + 1, $line, $shadow);
            imagestring($canvas, self::FONT, 6, $y, $line, $white);
            $y += 14;
        }

        ob_start();
        imagejpeg($canvas, null, 92);
        $out = (string) ob_get_clean();
        imagedestroy($canvas);

        return $out;
    }

    /**
     * Compute the SHA-256 of the FINAL stored bytes.
     */
    public function sha256(string $bytes): string
    {
        return hash('sha256', $bytes);
    }

    /**
     * Persist an auxiliary verification marker next to the image.
     *
     * Marker payload (non-sensitive, no student PII beyond what the image
     * already carries): evidence code, a random verification token, and
     * SHA-256(token). Stored as a private JSON sidecar.
     */
    public function storeVerificationMarker(
        string $diskPath,
        string $evidenceCode,
        string $token,
    ): string {
        $marker = json_encode([
            'schema' => 'puptracker-evidence-marker-v1',
            'evidence_code' => $evidenceCode,
            'token_sha256' => hash('sha256', $token),
            'created_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES);

        // Marker goes to the SAME disk as the image. Resolving it independently
        // is what previously allowed the image to be written to R2 while the
        // marker landed on the local disk (or vice versa), so a marker could
        // exist for an image that was never stored.
        $disk = EvidenceDisk::disk();
        $markerPath = $diskPath . '.marker.json';
        $disk->put($markerPath, $marker);

        return $markerPath;
    }

    /**
     * Convenience: build the final evidence image + marker + hash for a model.
     *
     * @param  \Illuminate\Http\UploadedFile|\Illuminate\Http\File|string  $fileBytesOrFile
     */
    public function prepareImageEvidence(
        ViolationEvidence $evidence,
        string $imageBytes,
        ?string $capturedAt = null,
    ): array {
        $watermarked = $this->watermarkImage(
            $imageBytes,
            (string) $evidence->evidence_code,
            (string) ($evidence->violation?->student_number ?? ''),
            $capturedAt,
        );

        $finalBytes = $watermarked ?? $imageBytes;
        $hash = $this->sha256($finalBytes);

        return [
            'bytes' => $finalBytes,
            'watermarked' => $watermarked !== null,
            'sha256' => $hash,
        ];
    }

    /**
     * Store an official evidence file on the private disk, applying a
     * server-side watermark for raster images (JPG/PNG) and computing the
     * SHA-256 of the FINAL stored bytes.
     *
     * Returns:
     *   path             storage-relative path of the final file
     *   sha256           SHA-256 of the final stored bytes
     *   server_watermarked  true only when the server baked a watermark
     *   marker_path      path of the auxiliary verification marker (or null)
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  string  $directory       e.g. violation-evidence/123
     * @param  string  $filename        e.g. EVD-2026-000001.jpg
     * @param  string  $evidenceCode
     * @param  string  $studentNumber
     * @param  string|null  $capturedAt  display timestamp for the watermark
     * @return array{path:string,sha256:string,server_watermarked:bool,marker_path:?string}
     */
    public function storeOfficialFile(
        $file,
        string $directory,
        string $filename,
        string $evidenceCode,
        string $studentNumber,
        ?string $capturedAt = null,
    ): array {
        // Centralised disk resolution + production guard: a misconfigured
        // deployment must fail loudly here rather than quietly writing
        // confidential evidence to an ephemeral filesystem.
        EvidenceDisk::assertWritable();

        $disk = EvidenceDisk::disk();

        $originalBytes = method_exists($file, 'getContent')
            ? (string) $file->getContent()
            : (string) file_get_contents($file);

        $relative = $directory . '/' . $filename;
        $mime = strtolower((string) $file->getMimeType());

        $finalBytes = $originalBytes;
        $serverWatermarked = false;

        // Only raster images get a server-side watermark. Documents (PDF) are
        // preserved byte-for-byte (never modified).
        if (in_array($mime, ['image/jpeg', 'image/pjpeg', 'image/png'], true)) {
            $watermarked = $this->watermarkImage(
                $originalBytes,
                $evidenceCode,
                $studentNumber,
                $capturedAt,
            );

            if ($watermarked !== null) {
                $finalBytes = $watermarked;
                $serverWatermarked = true;
            }
        }

        $disk->put($relative, $finalBytes);

        $sha256 = $this->sha256($finalBytes);
        $markerPath = null;

        if ($serverWatermarked) {
            $token = bin2hex(random_bytes(16));
            $markerPath = $this->storeVerificationMarker($relative, $evidenceCode, $token);
        }

        return [
            'path' => $relative,
            'sha256' => $sha256,
            'server_watermarked' => $serverWatermarked,
            'marker_path' => $markerPath,
        ];
    }
}
