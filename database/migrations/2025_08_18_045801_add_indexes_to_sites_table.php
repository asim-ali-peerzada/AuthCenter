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
            // Add unique index on site_name for fast lookups and upsert operations
            $table->unique('site_name', 'sites_site_name_unique');

            // Optional: Add composite index for common queries
            $table->index(['area', 'market'], 'sites_area_market_index');
            $table->index(['city', 'st'], 'sites_city_state_index');

            // Optional: Add index on site_access_file_id for foreign key lookups
            $table->index('site_access_file_id', 'sites_file_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropUnique('sites_site_name_unique');
            $table->dropIndex('sites_area_market_index');
            $table->dropIndex('sites_city_state_index');
            $table->dropIndex('sites_file_id_index');
        });
    }
};
