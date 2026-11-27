<?php

namespace App\Support\Student;

use App\Models\Program;

/**
 * Single source of truth for the Ladderized track rules.
 *
 * The Student IDP registration/profile workflow and Admin User Management
 * (Add Student / Edit Student) must classify students identically, so both
 * delegate here instead of repeating the program list and matching rules.
 *
 * The eligible programs come from
 * `config('graduation_review.graduating.ladderized_programs')` (BSOA / BSIT by
 * default), the same key the Graduation Report filter already uses. An
 * institution can change the list through configuration without code changes.
 */
class LadderizedPrograms
{
    /**
     * Configured program codes, upper-cased.
     *
     * @return array<int, string>
     */
    public function codes(): array
    {
        $configured = (array) config(
            'graduation_review.graduating.ladderized_programs',
            ['BSOA', 'BSIT']
        );

        $codes = array_map(
            static fn ($name): string => strtoupper(trim((string) $name)),
            $configured
        );

        return array_values(array_filter(
            $codes,
            static fn (string $name): bool => $name !== ''
        ));
    }

    /**
     * Does this program offer the Ladderized option?
     *
     * Matched case-insensitively, and accepted when the stored name merely
     * CONTAINS the code — program names are hand-entered, so "BS Information
     * Technology" and "BSIT" are the same program. Official names such as
     * "Bachelor of Science in Information Technology" are matched by their
     * initials (BSIT) too.
     */
    public function supports(?string $programName): bool
    {
        $name = strtoupper(trim((string) $programName));

        if ($name === '') {
            return false;
        }

        foreach ($this->codes() as $code) {
            if ($name === $code || str_contains($name, $code)) {
                return true;
            }

            $words = preg_split('/[^A-Z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $initials = '';
            foreach ($words as $word) {
                if (! in_array($word, ['IN', 'OF', 'THE', 'AND'], true)) {
                    $initials .= $word[0];
                }
            }

            if ($initials === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does the program with this id offer the Ladderized track?
     */
    public function supportsProgramId(?int $programId): bool
    {
        if (! $programId || $programId <= 0) {
            return false;
        }

        $programName = Program::query()->whereKey($programId)->value('program_name');

        return $this->supports(is_string($programName) ? $programName : null);
    }

    /**
     * Resolve the value to store for the chosen program.
     *
     * Returns 0 whenever the program does not offer the option, so a forged
     * `ladderized=1` can never be persisted for a non-ladderized program.
     *
     * @param  mixed  $requested  the raw submitted value, if any
     */
    public function resolveForProgram(?int $programId, $requested): int
    {
        return $this->supportsProgramId($programId)
            ? (int) ($requested ?? 0)
            : 0;
    }

    /**
     * Program ids that currently offer the Ladderized track.
     *
     * @return array<int, int>
     */
    public function programIds(): array
    {
        return Program::query()
            ->orderBy('program_name')
            ->get(['program_id', 'program_name'])
            ->filter(fn (Program $program): bool => $this->supports($program->program_name))
            ->map(fn (Program $program): int => (int) $program->program_id)
            ->values()
            ->all();
    }
}
