<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StagingSite extends Model
{
    protected $fillable = [
        'site_access_file_id',
        'site_id',
        'ps_loc',
        'site_name',
        'area',
        'market',
        'group',
        'switch',
        'type',
        'site_type',
        'site_function',
        'brand',
        'tech_name',
        'address',
        'city',
        'st',
        'zip',
        'county',
        'owned',
        'lec_name',
        'gate_combo',
        'gate_combo2',
        'direction',
        'security_lock',
        'latitude',
        'longitude',
        'access_restrictions',
        'restriction',
        'rstr_isrestricted',
        'rstr_toweraccess',
        'rstr_groundaccess',
        'rstr_comments',
        'tower_manager_phone',
        'police_phone',
        'csr_mfg_vendor',
        'csr_model',
        'csr_software_version',
        'darkfiber_mfg_vendor',
        'darkfiber_model',
        'darkfiber_software_version',
        'lte_mfg_vendor',
        'lte_model',
        'lte_software_version',
        'microwave_mfg_vendor',
        'microwave_model',
        'microwave_software_version',
        'nid_mfg_vendor',
        'nid_model',
        'nid_software_version',
        'remote_monitor_model',
        'remote_monitor_sw_ver',
        'remote_monitor_vendor',
        'shelter_model_number',
        'shelter_vendor',
        'site_tech_name',
        'site_tech_phone',
        'site_tech_alt_phone',
        'site_tech_email',
        'tech_mgr_name',
        'tech_mgr_phone',
        'tech_mgr_alt_phone',
        'tech_mgr_email',
        'tech_dir_name',
        'tech_dir_phone',
        'tech_dir_alt_phone',
        'tech_dir_email',
        'site_mgr_name',
        'site_mgr_phone',
        'site_mgr_alt_phone',
        'site_mgr_email',
        'site_dir_name',
        'site_dir_phone',
        'site_dir_alt_phone',
        'site_dir_email',
        'status',
        'error_message'
    ];

    protected $casts = [
        'latitude' => 'decimal:9',
        'longitude' => 'decimal:9',
    ];

    public function siteAccessFile()
    {
        return $this->belongsTo(SiteAccessFile::class);
    }
}