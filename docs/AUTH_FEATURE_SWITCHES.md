# Auth Feature Switches

Two controls are **disabled by default**. The implementation is fully intact —
re-enabling either one is a single flag change, not a code restore.

| Feature                             | Default | Flag                               | Env variable                      |
| ----------------------------------- | ------- | ---------------------------------- | --------------------------------- |
| "Remember this device for 12 hours" | **off** | `auth.features.remember_device`    | `AUTH_REMEMBER_DEVICE_ENABLED`    |
| 15-minute inactivity timeout        | **off** | `auth.features.inactivity_timeout` | `AUTH_INACTIVITY_TIMEOUT_ENABLED` |

Defined in `config/auth.php` → `features`.

## Remember this device

**When off:**

- The checkbox is not rendered on the MFA code-input page.
- The backend **refuses** the flag, so a hand-crafted `remember=1` cannot create a
  persistent credential. Hiding the input is not the only control:
    - `MfaController::resolveRememberSelection()` returns `false` unconditionally.
    - `MfaService::beginChallenge()` never stores the flag on the pending challenge.
- No recaller cookie and no `remember_token` are written.

The recaller lifetime (`auth.guards.admin.remember` / `.security.remember`, 720
minutes = 12 hours) is **left configured** so re-enabling needs no other change.

**To re-enable:** set `AUTH_REMEMBER_DEVICE_ENABLED=true` (or the config flag) and
rebuild the config cache. The checkbox returns on the code-input stage only —
never on the credential login page or the method-selection stage.

## Inactivity timeout

**When off:** `EnforceInactivityTimeout` returns immediately as a pass-through.
It writes no activity stamp, expires nothing, produces no "session expired"
notice, and does not purge recaller cookies.

**Still in effect:** the ordinary `session.lifetime` (`SESSION_LIFETIME`,
default 120 minutes). Sessions therefore still expire on the framework's own
sliding window — they just do not expire after 15 minutes of idleness.

**To re-enable:** set `AUTH_INACTIVITY_TIMEOUT_ENABLED=true` and rebuild the
config cache.

## Configuration cache

Both flags are read via `config()`, so if the configuration is cached you must
rebuild it after changing an env variable:

```
php artisan config:clear
php artisan config:cache
```

## Test coverage

Both modes stay tested, so the disabled behaviour cannot quietly rot:

| Suite                                | Covers                                                                                                               |
| ------------------------------------ | -------------------------------------------------------------------------------------------------------------------- |
| `RememberDeviceToggleTest`           | Defaults are off; crafted `remember=1` is refused; idle sessions are not expired; re-enabling restores both controls |
| `RememberMeTest`                     | Opts in — the enabled implementation (cookie issuance, placement, lifetime)                                          |
| `SessionAndRememberMeSecurityTest`   | Opts in — the enabled inactivity + remember implementation                                                           |
| `AdminAssistantAndLoginSecurityTest` | The enabled placement rule (selection vs code stage)                                                                 |

The opting-in suites set `config()->set('auth.features.*', true)` in `setUp`, so
the control keeps its regression coverage while shipping off.
