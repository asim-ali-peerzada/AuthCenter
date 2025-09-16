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
            // Change string columns that are causing "Data too long" errors to TEXT
            $table->text('gate_combo')->nullable()->change();
            $table->text('gate_combo2')->nullable()->change();
            $table->text('rstr_isrestricted')->nullable()->change();
            $table->text('rstr_toweraccess')->nullable()->change();
            $table->text('rstr_groundaccess')->nullable()->change();
            $table->text('access_restrictions')->nullable()->change();
            $table->text('restriction')->nullable()->change();
            $table->text('tower_manager_phone')->nullable()->change();
            
            // Also expand other potentially problematic columns
            $table->text('site_function')->nullable()->change();
            $table->text('brand')->nullable()->change();
            $table->text('tech_name')->nullable()->change();
            $table->text('lec_name')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staging_sites', function (Blueprint $table) {
            // Revert back to original string lengths
            $table->string('gate_combo', 100)->nullable()->change();
            $table->string('gate_combo2', 100)->nullable()->change();
            $table->string('rstr_isrestricted', 500)->nullable()->change();
            $table->string('rstr_toweraccess', 500)->nullable()->change();
            $table->string('rstr_groundaccess', 500)->nullable()->change();
            $table->string('access_restrictions', 255)->nullable()->change();
            $table->string('restriction', 255)->nullable()->change();
            $table->string('tower_manager_phone', 500)->nullable()->change();
            $table->string('site_function', 255)->nullable()->change();
            $table->string('brand', 255)->nullable()->change();
            $table->string('tech_name', 255)->nullable()->change();
            $table->string('lec_name', 255)->nullable()->change();
        });
    }
};