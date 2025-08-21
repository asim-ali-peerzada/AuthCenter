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
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_access_file_id')
                ->constrained('site_access_files')
                ->cascadeOnDelete();

            // Core identifiers
            $table->string('site_id', 50)->nullable();
            $table->string('ps_loc', 50)->nullable();
            $table->string('site_name', 150)->nullable()->index();
            $table->string('area', 100)->nullable();
            $table->string('market', 100)->nullable();
            $table->string('group', 100)->nullable();
            $table->string('switch', 100)->nullable();
            $table->string('type', 50)->nullable();
            $table->string('site_type', 50)->nullable()->index();
            $table->string('site_function', 100)->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('tech_name', 100)->nullable();

            // Location
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('st', 5)->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('county', 100)->nullable();
            $table->string('owned', 50)->nullable();
            $table->string('lec_name', 100)->nullable();
            $table->string('gate_combo', 50)->nullable();
            $table->string('gate_combo2', 50)->nullable();
            $table->text('direction')->nullable();
            $table->string('security_lock', 50)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable()->index();

            // Restrictions
            $table->string('access_restrictions', 100)->nullable();
            $table->string('restriction', 100)->nullable();
            $table->string('rstr_isrestricted', 50)->nullable();
            $table->string('rstr_toweraccess', 50)->nullable();
            $table->string('rstr_groundaccess', 50)->nullable();
            $table->text('rstr_comments')->nullable();

            // Contact numbers
            $table->string('tower_manager_phone', 20)->nullable();
            $table->string('police_phone', 20)->nullable();

            // Equipment fields
            $table->string('csr_mfg_vendor', 100)->nullable();
            $table->string('csr_model', 100)->nullable();
            $table->string('csr_software_version', 50)->nullable();

            $table->string('darkfiber_mfg_vendor', 100)->nullable();
            $table->string('darkfiber_model', 100)->nullable();
            $table->string('darkfiber_software_version', 50)->nullable();

            $table->string('lte_mfg_vendor', 100)->nullable();
            $table->string('lte_model', 100)->nullable();
            $table->string('lte_software_version', 50)->nullable();

            $table->string('microwave_mfg_vendor', 100)->nullable();
            $table->string('microwave_model', 100)->nullable();
            $table->string('microwave_software_version', 50)->nullable();

            $table->string('nid_mfg_vendor', 100)->nullable();
            $table->string('nid_model', 100)->nullable();
            $table->string('nid_software_version', 50)->nullable();

            $table->string('remote_monitor_model', 100)->nullable();
            $table->string('remote_monitor_sw_ver', 50)->nullable();
            $table->string('remote_monitor_vendor', 100)->nullable();

            // Shelter
            $table->string('shelter_model_number', 100)->nullable();
            $table->string('shelter_vendor', 100)->nullable();

            // Site Tech
            $table->string('site_tech_name', 100)->nullable();
            $table->string('site_tech_phone', 20)->nullable();
            $table->string('site_tech_alt_phone', 20)->nullable();
            $table->string('site_tech_email', 150)->nullable();

            // Tech Manager
            $table->string('tech_mgr_name', 100)->nullable();
            $table->string('tech_mgr_phone', 20)->nullable();
            $table->string('tech_mgr_alt_phone', 20)->nullable();
            $table->string('tech_mgr_email', 150)->nullable();

            // Tech Director
            $table->string('tech_dir_name', 100)->nullable();
            $table->string('tech_dir_phone', 20)->nullable();
            $table->string('tech_dir_alt_phone', 20)->nullable();
            $table->string('tech_dir_email', 150)->nullable();

            // Site Manager
            $table->string('site_mgr_name', 100)->nullable();
            $table->string('site_mgr_phone', 20)->nullable();
            $table->string('site_mgr_alt_phone', 20)->nullable();
            $table->string('site_mgr_email', 150)->nullable();

            // Site Director
            $table->string('site_dir_name', 100)->nullable();
            $table->string('site_dir_phone', 20)->nullable();
            $table->string('site_dir_alt_phone', 20)->nullable();
            $table->string('site_dir_email', 150)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
