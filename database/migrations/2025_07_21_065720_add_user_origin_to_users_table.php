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
             $table->enum('user_origin', ['ccms', 'jobfinder', 'solucomp','authcenter'])
                  ->default('authcenter')
                  ->after('status')
                  ->comment('Indicates the source system from which the user was registered');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('user_origin');
        });
    }
};
