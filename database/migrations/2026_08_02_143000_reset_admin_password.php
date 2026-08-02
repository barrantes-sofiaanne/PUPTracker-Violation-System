<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('admins')
            ->where('email', 'sabarrantes2911@gmail.com')
            ->update(['password' => Hash::make('pup 123')]);
    }

    public function down(): void
    {
        // No rollback needed
    }
};
