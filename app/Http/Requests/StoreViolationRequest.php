<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the Administrator "Record Violation" submission.
 *
 * Replaces the inline $request->validate() block in
 * Admin\ViolationController::store with an identical, reusable rule set.
 * Authorization for recording a violation is handled by the auth:admin route
 * middleware (an authenticated administrator), not here.
 */
class StoreViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authentication is enforced by the route middleware (auth:admin /
        // auth:security). The request itself does not re-authorize so it can
        // be shared by both the admin and security record-violation flows.
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users_tbl,user_id',
            ],
            'student_number' => [
                'nullable',
                'string',
                'max:50',
            ],
            'violation_type_id' => [
                'required',
                'exists:violation_type_tbl,violation_type_id',
            ],
            'violation_date' => [
                'required',
                'date',
            ],
            'description' => [
                'required',
                'string',
                'max:1000',
            ],
            // Optional at the shared request layer because Security and OSS
            // Staff use the same request class. The Admin controller enforces
            // this flag for administrator-recorded violations.
            'identity_confirmed' => [
                'nullable',
                'boolean',
            ],
            // Evidence is optional and reuses the existing evidence subsystem
            // (camera-captured photos + secure PDF uploads), up to 5 files,
            // 5MB each.
            'evidence' => [
                'nullable',
                'array',
                'max:5',
            ],
            'evidence.*' => [
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ];
    }

    /**
     * ACTIVE-STUDENT RULE (server-side).
     *
     * A violation may only be recorded against an account whose PUPTracker
     * status is Active. IDP authentication and the plain `exists` rule say
     * nothing about whether the student may be acted upon, so the status is
     * re-checked here — on the SERVER, not just in the UI.
     *
     * This closes the bypasses the rule is meant to prevent: a hand-crafted
     * POST, an edited student number, a stale form left open after the account
     * was deactivated, or a disabled button being re-enabled in devtools.
     *
     * Historically recorded violations are untouched; only NEW records are
     * blocked.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $userId = $this->input('user_id');

            if (! is_numeric($userId) || $validator->errors()->has('user_id')) {
                return;
            }

            $student = User::query()->find((int) $userId);

            if (! $student) {
                $validator->errors()->add('user_id', 'The selected student account does not exist.');
                return;
            }

            if (! $student->isActiveAccount()) {
                $validator->errors()->add(
                    'user_id',
                    'Violation cannot be recorded because this student account is inactive.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Please select a student account.',
            'user_id.exists' => 'The selected student account does not exist.',
            'violation_type_id.required' => 'A violation type is required.',
            'violation_type_id.exists' => 'The selected violation type is invalid.',
            'violation_date.required' => 'The violation date and time is required.',
            'violation_date.date' => 'Please provide a valid date and time.',
            'description.required' => 'An incident description is required.',
            'description.max' => 'The description may not exceed 1000 characters.',
            'evidence.max' => 'You may attach up to 5 evidence files.',
            'evidence.*.mimes' => 'Evidence must be a JPG, PNG, or PDF file.',
            'evidence.*.max' => 'Each evidence file may not exceed 5MB.',
        ];
    }
}
