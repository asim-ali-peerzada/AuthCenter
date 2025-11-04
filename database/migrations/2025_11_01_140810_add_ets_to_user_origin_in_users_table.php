<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE users 
            MODIFY COLUMN user_origin 
            ENUM(
                'ccms',
                'jobfinder',
                'solucomp',
                'authcenter',
                'site_access_info',
                'ets'
            ) NOT NULL DEFAULT 'ccms'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE users 
            MODIFY COLUMN user_origin 
            ENUM(
                'ccms',
                'jobfinder',
                'solucomp',
                'authcenter',
                'site_access_info'
            ) NOT NULL DEFAULT 'ccms'
        ");
    }
};
