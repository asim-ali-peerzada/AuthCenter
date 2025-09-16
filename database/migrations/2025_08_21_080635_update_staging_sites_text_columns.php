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
        Schema::table('staging_sites', function (Blueprint $table) {
            // Change restriction columns to TEXT to accommodate long descriptions
            $table->text('rstr_isrestricted')->nullable()->change();
            $table->text('rstr_toweraccess')->nullable()->change();
            $table->text('rstr_groundaccess')->nullable()->change();
            $table->text('access_restrictions')->nullable()->change();
            $table->text('restriction')->nullable()->change();
            $table->text('tower_manager_phone')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staging_sites', function (Blueprint $table) {
            $table->string('rstr_isrestricted', 500)->nullable()->change();
            $table->string('rstr_toweraccess', 500)->nullable()->change();
            $table->string('rstr_groundaccess', 500)->nullable()->change();
            $table->string('access_restrictions', 255)->nullable()->change();
            $table->string('restriction', 255)->nullable()->change();
            $table->string('tower_manager_phone', 500)->nullable()->change();
        });
    }
};
