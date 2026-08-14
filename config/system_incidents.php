<?php

return [

    /*
    |--------------------------------------------------------------------------
    | System Incident Simulation (Risk No. 7)
    |--------------------------------------------------------------------------
    |
    | Controls the "Simulate Email Failure" development/testing mechanism on
    | the Super Admin System Logs page. It exercises the exact same failure
    | path as a real email failure (error log -> incident -> Super Admin
    | notification) without touching production SMTP / mailgun credentials.
    |
    | Set to false in production to fully disable the simulation button.
    |
    */

    'simulate_enabled' => env('INCIDENT_SIMULATION_ENABLED', true),

];
