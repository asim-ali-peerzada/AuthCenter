<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Modify the user_origin enum to add 'site_access_info' value
            $table->enum('user_origin', ['ccms', 'jobfinder', 'solucomp', 'authcenter', 'site_access_info'])
                ->default('authcenter')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Revert the user_origin enum to original values (remove 'site_access_info')
            $table->enum('user_origin', ['ccms', 'jobfinder', 'solucomp', 'authcenter'])
                ->default('authcenter')
                ->change();
        });
    }
};
