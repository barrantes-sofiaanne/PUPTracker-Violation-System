<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Central place for generic client-facing error responses.
 *
 * Unexpected internal exceptions must never leak file paths, line numbers,
 * stack traces, SQL details, or internal messages to the browser. Detailed
 * diagnostics belong in server-side logs only. This helper returns the single
 * safe generic response used across controllers.
 */
class Responses
{
    /**
     * The generic message shown to the client for unexpected errors.
     */
    public const UNEXPECTED_ERROR_MESSAGE = 'An unexpected error occurred. Please try again later.';

    /**
     * Build a generic JSON error response for an unexpected internal error.
     * Detailed technical information must be logged by the caller server-side.
     */
    public static function unexpectedError(int $status = 500): JsonResponse
    {
        return response()->json([
            'message' => self::UNEXPECTED_ERROR_MESSAGE,
        ], $status);
    }

    /**
     * Build a generic JSON "success:false" error response (client expects a
     * success boolean in some flows, e.g. student sanction requests).
     */
    public static function unexpectedErrorFlag(int $status = 500): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => self::UNEXPECTED_ERROR_MESSAGE,
        ], $status);
    }
}
