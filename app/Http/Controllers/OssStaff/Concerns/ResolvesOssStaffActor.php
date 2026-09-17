<?php

namespace App\Http\Controllers\OssStaff\Concerns;

use App\Models\Admin;
use App\Models\Violation;
use Illuminate\Support\Facades\Auth;

/**
 * The OSS Staff identity rule, in ONE place.
 *
 * OSS Staff accounts are ADMIN accounts (the `admin` guard, an `admins` row)
 * whose assigned role is OSS Staff. They are deliberately NOT Security
 * accounts, so every identity decision in this module resolves through the
 * admin guard and the Admin model — never through `auth('security')` and never
 * through a Security profile table.
 *
 * The recorder fields written for OSS Staff activity are:
 *   recorder_type = 'admin'
 *   recorder_id   = the authenticated OSS Staff admin id
 *   recorder_name = the OSS Staff member's actual full name
 *
 * `recorder_id` is the reliable key (it cannot collide with another account),
 * so the recorder scope matches on it first and only falls back to the legacy
 * free-text naming convention used by earlier admin-recorded rows.
 */
trait ResolvesOssStaffActor
{
    /** The authenticated OSS Staff admin account, or null. */
    protected function ossStaffUser(): ?Admin
    {
        $user = Auth::guard('admin')->user();

        return $user instanceof Admin ? $user : null;
    }

    /** The authenticated OSS Staff admin id, or null. */
    protected function ossStaffId(): ?int
    {
        $id = Auth::guard('admin')->id();

        return $id !== null ? (int) $id : null;
    }

    /**
     * The OSS Staff member's full name, used as the human-readable
     * `recorder_name`. Falls back to the account email and finally to a stable
     * label so the column is never blank.
     */
    protected function ossStaffName(): string
    {
        $admin = $this->ossStaffUser();

        if (! $admin) {
            return 'OSS Staff';
        }

        $info = $admin->adminInfo;

        $fullName = trim(implode(' ', array_filter([
            $info?->firstname ?? null,
            $info?->middlename ?? null,
            $info?->lastname ?? null,
        ], fn ($value) => $value !== null && $value !== '')));

        if ($fullName !== '') {
            return $fullName;
        }

        return $admin->email ?: ('OSS Staff #' . $admin->id);
    }

    /**
     * Restrict a violation query to rows recorded by the CURRENT OSS Staff
     * account.
     *
     * Matching on `recorder_type = 'admin'` + `recorder_id` is the strong check.
     * The name labels are a bounded LEGACY fallback that only applies to rows
     * with a NULL `recorder_id` (written before the id was persisted), so a row
     * that already belongs to some other administrator can never be pulled in.
     */
    protected function applyRecorderScope($query, bool $includeDenied = false): void
    {
        $admin = $this->ossStaffUser();
        $adminId = $admin?->id;
        $labels = $this->recorderLabels();

        $query->where('recorder_type', 'admin')
            ->where(function ($innerQuery) use ($adminId, $labels) {
                if ($adminId !== null) {
                    $innerQuery->where('recorder_id', $adminId)
                        ->orWhere(function ($legacyQuery) use ($labels) {
                            $legacyQuery->whereNull('recorder_id')
                                ->whereIn('recorder_name', $labels);
                        });

                    return;
                }

                // Defensive: no authenticated account id available.
                $innerQuery->whereIn('recorder_name', $labels);
            });

        // Denied violations are not real violations, so by default they are
        // excluded from the record/dashboard views. The "Submitted Violations"
        // tracker opts in ($includeDenied = true) because the OSS Staff member
        // must see their rejected submissions there in order to resubmit them.
        if (! $includeDenied) {
            $query->notDenied();
        }
    }

    /**
     * Every label that identifies this OSS Staff account as the recorder.
     *
     * @return array<int, string>
     */
    private function recorderLabels(): array
    {
        $admin = $this->ossStaffUser();
        $adminId = $admin?->id;

        $labels = [
            $this->ossStaffName(),
            ! empty($admin?->email) ? 'Admin: ' . $admin->email : null,
            $adminId !== null ? 'Admin #' . $adminId : null,
        ];

        return array_values(array_unique(array_filter($labels, fn ($label) => $label !== null && $label !== '')));
    }

    /**
     * Whether the given violation was recorded by this OSS Staff account.
     */
    protected function recorderMatches(Violation $violation): bool
    {
        if ((string) $violation->recorder_type !== 'admin') {
            return false;
        }

        $adminId = $this->ossStaffId();

        // The persisted id is authoritative when both sides have one.
        if ($adminId !== null && $violation->recorder_id !== null) {
            return (int) $violation->recorder_id === $adminId;
        }

        // Legacy row without an id: fall back to this account's own labels.
        return in_array((string) $violation->recorder_name, $this->recorderLabels(), true);
    }
}
