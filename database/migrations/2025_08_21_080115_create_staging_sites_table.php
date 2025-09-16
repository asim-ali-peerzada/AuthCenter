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
        Schema::create('staging_sites', function (Blueprint $table) {
            $table->id();
            $table->string('site_id', 100)->nullable();
            $table->unsignedBigInteger('site_access_file_id');
            $table->string('ps_loc', 100)->nullable();
            $table->string('site_name', 255)->nullable(); // increased
            $table->string('area', 255)->nullable();
            $table->string('market', 255)->nullable();
            $table->string('group', 255)->nullable();
            $table->string('switch', 255)->nullable();
            $table->string('type', 100)->nullable();
            $table->string('site_type', 100)->nullable();
            $table->string('site_function', 255)->nullable();
            $table->string('brand', 255)->nullable();
            $table->string('tech_name', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 255)->nullable();
            $table->string('st', 255)->nullable(); // expanded
            $table->string('zip', 50)->nullable();
            $table->string('county', 255)->nullable();
            $table->string('owned', 100)->nullable();
            $table->string('lec_name', 255)->nullable();
            $table->string('gate_combo', 100)->nullable();
            $table->string('gate_combo2', 100)->nullable();
            $table->text('direction')->nullable();
            $table->text('security_lock')->nullable(); // changed to text
            $table->decimal('latitude', 18, 12)->nullable();  // safe precision
            $table->decimal('longitude', 18, 12)->nullable(); // safe precision
            $table->string('access_restrictions', 255)->nullable();
            $table->string('restriction', 255)->nullable();
            $table->string('rstr_isrestricted', 500)->nullable();
            $table->string('rstr_toweraccess', 500)->nullable();
            $table->string('rstr_groundaccess', 500)->nullable();
            $table->text('rstr_comments')->nullable();
            $table->string('tower_manager_phone', 500)->nullable(); // expanded
            $table->string('police_phone', 100)->nullable();
            $table->string('csr_mfg_vendor', 255)->nullable();
            $table->string('csr_model', 255)->nullable();
            $table->string('csr_software_version', 100)->nullable();
            $table->string('darkfiber_mfg_vendor', 255)->nullable();
            $table->string('darkfiber_model', 255)->nullable();
            $table->string('darkfiber_software_version', 100)->nullable();
            $table->string('lte_mfg_vendor', 255)->nullable();
            $table->string('lte_model', 255)->nullable();
            $table->string('lte_software_version', 100)->nullable();
            $table->string('microwave_mfg_vendor', 255)->nullable();
            $table->string('microwave_model', 255)->nullable();
            $table->string('microwave_software_version', 100)->nullable();
            $table->string('nid_mfg_vendor', 255)->nullable();
            $table->string('nid_model', 255)->nullable();
            $table->string('nid_software_version', 100)->nullable();
            $table->string('remote_monitor_model', 255)->nullable();
            $table->string('remote_monitor_sw_ver', 100)->nullable();
            $table->string('remote_monitor_vendor', 255)->nullable();
            $table->string('shelter_model_number', 255)->nullable();
            $table->string('shelter_vendor', 255)->nullable();
            $table->string('site_tech_name', 255)->nullable();
            $table->string('site_tech_phone', 50)->nullable();
            $table->string('site_tech_alt_phone', 50)->nullable();
            $table->string('site_tech_email', 255)->nullable();
            $table->string('tech_mgr_name', 255)->nullable();
            $table->string('tech_mgr_phone', 50)->nullable();
            $table->string('tech_mgr_alt_phone', 50)->nullable();
            $table->string('tech_mgr_email', 255)->nullable();
            $table->string('tech_dir_name', 255)->nullable();
            $table->string('tech_dir_phone', 50)->nullable();
            $table->string('tech_dir_alt_phone', 50)->nullable();
            $table->string('tech_dir_email', 255)->nullable();
            $table->string('site_mgr_name', 255)->nullable();
            $table->string('site_mgr_phone', 50)->nullable();
            $table->string('site_mgr_alt_phone', 50)->nullable();
            $table->string('site_mgr_email', 255)->nullable();
            $table->string('site_dir_name', 255)->nullable();
            $table->string('site_dir_phone', 50)->nullable();
            $table->string('site_dir_alt_phone', 50)->nullable();
            $table->string('site_dir_email', 255)->nullable();
            $table->string('status', 50)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('site_access_file_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staging_sites');
    }
};
