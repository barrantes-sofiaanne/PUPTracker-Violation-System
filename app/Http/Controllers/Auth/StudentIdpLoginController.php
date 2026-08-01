<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StudentIdpLoginController extends Controller
{
    public function start(Request $request)
    {
        $validated = $request->validate([
            'student_number' => ['required', 'string'],
        ]);

        $student = User::query()
            ->where('student_number', $validated['student_number'])
            ->first();

        if (! $student || (int) $student->status_id !== 1) {
            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'Student account was not found or is inactive.',
                ])
                ->withInput();
        }

        $request->session()->put('student.idp.student_number', (string) $student->student_number);

        return redirect()->route('student.idp.login');
    }

    public function redirect(Request $request)
    {
        $clientId = (string) config('services.idp.client_id');

        if ($clientId === '') {
            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'IDP login is not configured yet.',
                ]);
        }

        $state = Str::random(40);
        $request->session()->put('student.idp.state', $state);

        $authorizeUrl = rtrim((string) config('services.idp.base_url'), '/') . '/auth/authorize';

        return redirect()->away($authorizeUrl . '?' . http_build_query([
            'client_id' => $clientId,
            'state' => $state,
        ]));
    }

    public function callback(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['nullable', 'string'],
        ]);

        $expectedState = $request->session()->pull('student.idp.state');

        if (
            is_string($expectedState)
            && $expectedState !== ''
            && $request->filled('state')
            && ! hash_equals($expectedState, (string) $request->string('state'))
        ) {
            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'The IDP login session expired. Please try again.',
                ]);
        }

        $clientId = (string) config('services.idp.client_id');
        $clientSecret = (string) config('services.idp.client_secret');
        $baseUrl = rtrim((string) config('services.idp.base_url'), '/');

        if ($clientId === '' || $clientSecret === '' || $baseUrl === '') {
            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'IDP login is not configured yet.',
                ]);
        }

        try {
            $tokenResponse = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->post('/auth/token', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'code' => $validated['code'],
                ]);

            if (! $tokenResponse->successful()) {
                throw new \RuntimeException('Token exchange failed.');
            }

            $accessToken = (string) $tokenResponse->json('access_token');

            if ($accessToken === '') {
                throw new \RuntimeException('Missing access token in IDP response.');
            }

            $profileResponse = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->withToken($accessToken)
                ->get('/me');

            if (! $profileResponse->successful()) {
                throw new \RuntimeException('Unable to retrieve IDP profile.');
            }

            $profile = $profileResponse->json();
            $idpSubject = trim((string) data_get($profile, 'id', ''));
            $email = strtolower(trim((string) data_get($profile, 'email', '')));

            if ($idpSubject === '') {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'Your IDP account is missing a stable identity reference.',
                    ]);
            }

            $roles = strtolower((string) data_get($profile, 'roles', ''));
            if ($roles !== '' && ! str_contains($roles, 'student')) {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'Your IDP account is not allowed to sign in as a student.',
                    ]);
            }

            $user = null;
            
            // Try to find user by IDP subject (if column exists)
            try {
                $user = User::query()
                    ->where('idp_subject', $idpSubject)
                    ->first();
            } catch (\Throwable $e) {
                Log::warning('Could not query idp_subject column', ['error' => $e->getMessage()]);
            }

            if (! $user) {
                $pendingStudentNumber = (string) $request->session()->pull('student.idp.student_number', '');
                if ($pendingStudentNumber !== '') {
                    $user = User::query()
                        ->where('student_number', $pendingStudentNumber)
                        ->first();
                }
            }

            // Legacy fallback for accounts linked before subject-based binding was introduced.
            if (! $user && $email !== '') {
                $user = User::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->first();
            }

            if (! $user) {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'No local student account is linked to this IDP profile. Enter your student number first, then try again.',
                    ]);
            }

            // Update IDP subject and email if needed (handle if columns don't exist yet)
            try {
                if ($user->idp_subject !== $idpSubject || strtolower((string) $user->idp_email) !== $email) {
                    $user->forceFill([
                        'idp_subject' => $idpSubject,
                        'idp_email' => $email !== '' ? $email : $user->idp_email,
                        'idp_connected_at' => $user->idp_connected_at ?? now(),
                    ])->save();
                }
            } catch (\Throwable $e) {
                Log::warning('Could not update idp fields', ['error' => $e->getMessage()]);
            }

            if ((int) $user->status_id !== 1) {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'Your account has been deactivated. Please contact an administrator.',
                    ]);
            }

            // Update last login timestamp (if column exists)
            try {
                $user->forceFill([
                    'idp_last_login_at' => now(),
                ])->save();
            } catch (\Throwable $e) {
                Log::warning('Could not update idp_last_login_at', ['error' => $e->getMessage()]);
            }

            Auth::guard('student')->login($user);
            $request->session()->regenerate();

            return redirect()->route('student.dashboard');
        } catch (\Throwable $exception) {
            Log::error('Student IDP login failed.', [
                'error' => $exception->getMessage(),
            ]);

            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'Unable to complete IDP login right now. Please try again later.',
                ]);
        }
    }
}