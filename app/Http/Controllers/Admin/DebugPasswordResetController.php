<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class DebugPasswordResetController extends Controller
{
    /**
     * TEMPORARY: Reset admin password for debugging
     * DELETE THIS CONTROLLER AND ROUTE AFTER USE
     */
    public function __construct()
    {
        // Don't apply any middleware to this debug route
    }

    public function resetAdminPassword()
    {
        try {
            $admin = Admin::where('email', 'sabarrantes2911@gmail.com')->first();
            
            if (!$admin) {
                return response()->json(['error' => 'Admin not found'], 404);
            }

            $admin->update(['password' => Hash::make('pup 123')]);

            return response()->json([
                'success' => true,
                'message' => 'Admin password reset successfully',
                'email' => 'sabarrantes2911@gmail.com',
                'new_password' => 'pup 123',
                'admin_id' => $admin->id,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}
