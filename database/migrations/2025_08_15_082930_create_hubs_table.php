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
        Schema::create('hubs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_access_file_id')
                ->constrained('site_access_files')
                ->cascadeOnDelete();

            // Hub specific fields based on Excel columns
            $table->string('ohpa_site', 150)->nullable()->comment('OHPA Site: Site Name');
            $table->string('location_code', 50)->nullable()->comment('Location Code [FZ to ST]');
            $table->string('uace_name', 150)->nullable()->comment('UACE NAME');
            $table->string('construction_vendor', 150)->nullable()->comment('CONSTRUCTION VENDOR');
            $table->string('contact', 150)->nullable()->comment('CONTACT');
            $table->text('street')->nullable()->comment('STREET');
            $table->string('city', 100)->nullable()->comment('CITY');
            $table->string('state', 5)->nullable()->comment('STATE');
            $table->string('zip_code', 20)->nullable()->comment('ZIP CODE');
            $table->decimal('lat', 10, 7)->nullable()->comment('LAT');
            $table->decimal('long', 10, 7)->nullable()->comment('LONG');
            $table->string('switch', 100)->nullable()->comment('SWITCH');
            $table->string('fa_engineering_manager', 150)->nullable()->comment('FA ENGINEERING MANAGER');
            $table->string('fa_engineer', 150)->nullable()->comment('FA ENGINEER');
            $table->string('site_id', 50)->nullable()->comment('SITE ID');
            $table->string('enobe_id', 50)->nullable()->comment('eNOBE ID');

            $table->timestamps();

            // Add indexes for commonly searched fields
            $table->index('ohpa_site');
            $table->index('location_code');
            $table->index('site_id');
            $table->index('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hubs');
    }
};
