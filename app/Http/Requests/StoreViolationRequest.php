<?php

namespace App\Http\Requests;

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
            'student_number' => [
                'required',
                'exists:users_tbl,student_number',
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

    public function messages(): array
    {
        return [
            'student_number.required' => 'A student number is required.',
            'student_number.exists' => 'The selected student does not exist.',
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
