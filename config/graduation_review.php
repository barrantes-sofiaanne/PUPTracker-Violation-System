<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider
    |--------------------------------------------------------------------------
    |
    | Server-side configuration for the AI-assisted graduation disciplinary
    | review. The API key is read ONLY from the environment (.env) and is never
    | exposed to the client.
    |
    */

    'ai' => [
        'enabled' => (bool) env('AI_GRADUATION_REVIEW_ENABLED', false),
        'base_url' => env('AI_GRADUATION_REVIEW_BASE_URL', ''),
        'api_key' => env('AI_GRADUATION_REVIEW_API_KEY', ''),
        'model' => env('AI_GRADUATION_REVIEW_MODEL', ''),
        'timeout' => (int) env('AI_GRADUATION_REVIEW_TIMEOUT', 30),
        'batch_size' => (int) env('AI_GRADUATION_REVIEW_BATCH_SIZE', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Processing
    |--------------------------------------------------------------------------
    |
    | Individual student AI reviews are dispatched as queue jobs (one per
    | student) so 1,000+ students can be processed without holding the browser
    | open or issuing thousands of synchronous API calls in one request.
    |
    */

    'queue' => [
        'connection' => env('QUEUE_CONNECTION', 'database'),
        'name' => env('GRADUATION_REVIEW_QUEUE', 'graduation-review'),
        'chunk' => (int) env('GRADUATION_REVIEW_QUEUE_CHUNK', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Graduating Cohort Filter
    |--------------------------------------------------------------------------
    |
    | The system has no explicit "graduating" flag. Graduating students are
    | identified by joining student_info_tbl and matching one of the following
    | documented filters. No student records are modified.
    |
    | - program_ids:    restrict to these program_tbl.program_id values (empty = all)
    | - year_ids:       restrict to these year_tbl.year_id values (empty = all)
    | - student_status: match student_status_tbl.status_name (case-insensitive,
    |                   substring). Empty = do not filter by status.
    |
    */

    'graduating' => [
        'program_ids' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('GRADUATION_REVIEW_PROGRAM_IDS', '')),
        ))),
        'year_ids' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('GRADUATION_REVIEW_YEAR_IDS', '')),
        ))),
        'student_status' => env('GRADUATION_REVIEW_STUDENT_STATUS', ''),
        // First 4 digits of the student number = admission year. Default
        // graduating admission year for the current cycle (e.g. 2022). Users
        // can add additional years in the UI for returnees with a different
        // start year. This is NOT the "year level" filter.
        'admission_year' => (int) env('GRADUATION_REVIEW_ADMISSION_YEAR', 2022),
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch Processing
    |--------------------------------------------------------------------------
    |
    | Controls how many students are processed per "Generate AI Review" batch
    | request to respect API cost / rate limits.
    |
    */

    'batch_limit' => (int) env('GRADUATION_REVIEW_BATCH_LIMIT', 25),

];
