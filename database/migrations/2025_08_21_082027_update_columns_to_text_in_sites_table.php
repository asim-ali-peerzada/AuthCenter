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
        Schema::table('sites', function (Blueprint $table) {
            // Change columns that are causing "Data too long" errors to TEXT
            $table->text('security_lock')->nullable()->change();
            $table->text('gate_combo')->nullable()->change();
            $table->text('gate_combo2')->nullable()->change();
            $table->text('access_restrictions')->nullable()->change();
            $table->text('restriction')->nullable()->change();
            $table->text('rstr_isrestricted')->nullable()->change();
            $table->text('rstr_toweraccess')->nullable()->change();
            $table->text('rstr_groundaccess')->nullable()->change();
            $table->text('tower_manager_phone')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            // Revert back to original string lengths
            $table->string('security_lock', 50)->nullable()->change();
            $table->string('gate_combo', 50)->nullable()->change();
            $table->string('gate_combo2', 50)->nullable()->change();
            $table->string('access_restrictions', 100)->nullable()->change();
            $table->string('restriction', 100)->nullable()->change();
            $table->string('rstr_isrestricted', 50)->nullable()->change();
            $table->string('rstr_toweraccess', 50)->nullable()->change();
            $table->string('rstr_groundaccess', 50)->nullable()->change();
            $table->string('tower_manager_phone', 20)->nullable()->change();
        });
    }
};