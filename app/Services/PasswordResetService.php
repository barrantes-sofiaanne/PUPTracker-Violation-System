<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\PasswordReset;
use App\Models\Security;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Password reset token lifecycle.
 *
 * Tokens are bound to the account's primary key (never the email) and stored
 * hashed. The plaintext token only ever appears in the emailed reset link.
 */
class PasswordResetService
{
    public const TOKEN_LIFETIME_MINUTES = 15;

    private const MODELS = [
        'student' => User::class,
        'admin' => Admin::class,
        'security' => Security::class,
    ];



    public function guardModel(string $guard): string
    {
        if (!isset(self::MODELS[$guard])) {
            throw new \InvalidArgumentException("Unsupported guard: {$guard}");
        }

        return self::MODELS[$guard];
    }



    public function findAccountByEmail(string $guard, string $email): Admin|Security|User|null
    {
        $model = $this->guardModel($guard);

        /** @var Admin|Security|User|null $account */
        $account = $model::query()->where('email', $email)->first();

        return $account;
    }

    /**
     * Create a single-use, hashed reset token for an account.
     *
     * @return array{record: PasswordReset, token: string} The DB record (hash only)
     *                                                      plus the plaintext token for the email.
     */
    public function createReset(string $guard, Admin|Security|User $account): array
    {
        $token = Str::random(64);

        $record = PasswordReset::create([
            'guard' => $guard,
            'account_id' => $account->getKey(),
            'token_hash' => Hash::make($token),
            'expires_at' => now()->addMinutes(self::TOKEN_LIFETIME_MINUTES),
            'created_at' => now(),
        ]);

        return [
            'record' => $record,
            'token' => $token,
        ];
    }

    /**
     * Find the single-use reset record for a plaintext token.
     *
     * Returns null when the token is missing, expired, already used, or when
     * the hash does not match — callers must not expose which case occurred.
     */
    public function findValidReset(string $guard, string $token): ?PasswordReset
    {
        $candidates = PasswordReset::query()
            ->where('guard', $guard)
            ->unused()
            ->expiresAfter(now())
            ->latest('id')
            ->get();

        foreach ($candidates as $candidate) {
            if (Hash::check($token, (string) $candidate->token_hash)) {
                return $candidate;
            }
        }

        return null;
    }

    public function resolveAccount(string $guard, PasswordReset $reset): Admin|Security|User|null
    {
        $model = $this->guardModel($guard);

        /** @var Admin|Security|User|null $account */
        $account = $model::find($reset->account_id);

        return $account;
    }

    /**
     * Invalidate a token (and every other unused token for the same account)
     * immediately after a successful password change.
     */
    public function invalidateReset(PasswordReset $reset): void
    {
        $reset->update(['used_at' => now()]);

        PasswordReset::query()
            ->where('guard', $reset->guard)
            ->where('account_id', $reset->account_id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }
}
