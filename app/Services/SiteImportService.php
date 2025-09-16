<?php

namespace App\Services;

use App\Models\Site;
use App\Models\StagingSite;
use Illuminate\Support\Facades\Log;

class SiteImportService
{
    /**
     * Process a staging record and map to Site.
     */
    public function processStagingRecord(StagingSite $record): void
    {
        try {
            // Validate required fields
            if (empty($record->site_name)) {
                throw new \InvalidArgumentException('Site name is required');
            }

            Site::upsert(
                $record->only($this->getSiteColumns()),
                ['site_name'], // Unique key
                array_diff($this->getSiteColumns(), ['site_name'])
            );

            $record->update(['status' => 'processed']);
        } catch (\Exception $e) {
            Log::error('Failed to process staging record', [
                'record_id' => $record->id,
                'site_name' => $record->site_name,
                'error' => $e->getMessage()
            ]);

            $record->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Define the columns that should be mapped from staging to sites table.
     */
    protected function getSiteColumns(): array
    {
        return [
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
            'site_dir_email'
        ];
    }

    public function getColumnMapping(): array
    {
        return [
            'Site Id'                         => 'site_id',
            'Ps Loc'                          => 'ps_loc',
            'Site Name'                       => 'site_name',
            'Area'                            => 'area',
            'Market'                          => 'market',
            'Group'                           => 'group',
            'Switch'                          => 'switch',
            'Type'                            => 'type',
            'Site Type'                       => 'site_type',
            'Site Function'                   => 'site_function',
            'Brand'                           => 'brand',
            'Tech Name'                       => 'tech_name',
            'Address'                         => 'address',
            'City'                            => 'city',
            'St'                              => 'st',
            'Zip'                             => 'zip',
            'County'                          => 'county',
            'Owned'                           => 'owned',
            'Lec Name'                        => 'lec_name',
            'Gate Combo'                      => 'gate_combo',
            'Gate Combo2'                     => 'gate_combo2',
            'Direction'                       => 'direction',
            'Security Lock'                   => 'security_lock',
            'Latitude'                        => 'latitude',
            'Longitude'                       => 'longitude',
            'Access Restrictions'             => 'access_restrictions',
            'Restriction'                     => 'restriction',
            'Rstr Isrestricted'               => 'rstr_isrestricted',
            'Rstr Toweraccess'                => 'rstr_toweraccess',
            'Rstr Groundaccess'               => 'rstr_groundaccess',
            'Rstr Comments'                   => 'rstr_comments',
            'Tower Manager Phone'             => 'tower_manager_phone',
            'Police Phone'                    => 'police_phone',
            'Csr Mfg Vendor'                  => 'csr_mfg_vendor',
            'Csr Model'                       => 'csr_model',
            'Csr Software Version'            => 'csr_software_version',
            'Darkfiber Mfg Vendor'            => 'darkfiber_mfg_vendor',
            'Darkfiber Model'                 => 'darkfiber_model',
            'Darkfiber Software Version'      => 'darkfiber_software_version',
            'Lte Mfg Vendor'                  => 'lte_mfg_vendor',
            'Lte Model'                       => 'lte_model',
            'Lte Software Version'            => 'lte_software_version',
            'Microwave Mfg Vendor'            => 'microwave_mfg_vendor',
            'Microwave Model'                 => 'microwave_model',
            'Microwave Software Version'      => 'microwave_software_version',
            'Nid Mfg Vendor'                  => 'nid_mfg_vendor',
            'Nid Model'                       => 'nid_model',
            'Nid Software Version'            => 'nid_software_version',
            'Remote Monitor Model'            => 'remote_monitor_model',
            'Remote Monitor Sw Ver'           => 'remote_monitor_sw_ver',
            'Remote Monitor Vendor'           => 'remote_monitor_vendor',
            'Shelter Model Number'            => 'shelter_model_number',
            'Shelter Vendor'                  => 'shelter_vendor',
            'Site Tech Name'                  => 'site_tech_name',
            'Site Tech Phone'                 => 'site_tech_phone',
            'Site Tech Alt. Phone'            => 'site_tech_alt_phone',
            'Site Tech Email'                 => 'site_tech_email',
            'Tech Mgr Name'                   => 'tech_mgr_name',
            'Tech Mgr Phone'                  => 'tech_mgr_phone',
            'Tech Mgr Alt. Phone'             => 'tech_mgr_alt_phone',
            'Tech Mgr Email'                  => 'tech_mgr_email',
            'Tech Dir. Name'                  => 'tech_dir_name',
            'Tech Dir. Phone'                 => 'tech_dir_phone',
            'Tech Dir. Alt. Phone'            => 'tech_dir_alt_phone',
            'Tech Dir. Email'                 => 'tech_dir_email',
            'Site Mgr. Name'                  => 'site_mgr_name',
            'Site Mgr. Phone'                 => 'site_mgr_phone',
            'Site Mgr. Alt. Phone'            => 'site_mgr_alt_phone',
            'Site Mgr. Email'                 => 'site_mgr_email',
            'Site Dir. Name'                  => 'site_dir_name',
            'Site Dir. Phone'                 => 'site_dir_phone',
            'Site Dir. Alt. Phone'            => 'site_dir_alt_phone',
            'Site Dir. Email'                 => 'site_dir_email',
        ];
    }

    public function transformStagingToSiteData(StagingSite $stagingRecord): ?array
    {
        try {
            // Transform staging record to site data format with ALL required columns
            $siteData = [
                'site_access_file_id' => $stagingRecord->site_access_file_id,
                'site_id' => $stagingRecord->site_id,
                'ps_loc' => $stagingRecord->ps_loc,
                'site_name' => $stagingRecord->site_name,
                'area' => $stagingRecord->area,
                'market' => $stagingRecord->market,
                'group' => $stagingRecord->group,
                'switch' => $stagingRecord->switch,
                'type' => $stagingRecord->type,
                'site_type' => $stagingRecord->site_type,
                'site_function' => $stagingRecord->site_function,
                'brand' => $stagingRecord->brand,
                'tech_name' => $stagingRecord->tech_name,
                'address' => $stagingRecord->address,
                'city' => $stagingRecord->city,
                'st' => $stagingRecord->st,
                'zip' => $stagingRecord->zip,
                'county' => $stagingRecord->county,
                'owned' => $stagingRecord->owned,
                'lec_name' => $stagingRecord->lec_name,
                'gate_combo' => $stagingRecord->gate_combo,
                'gate_combo2' => $stagingRecord->gate_combo2,
                'direction' => $stagingRecord->direction,
                'security_lock' => $stagingRecord->security_lock,
                'latitude' => $stagingRecord->latitude ?: null,
                'longitude' => $stagingRecord->longitude ?: null,
                'access_restrictions' => $stagingRecord->access_restrictions,
                'restriction' => $stagingRecord->restriction,
                'rstr_isrestricted' => $stagingRecord->rstr_isrestricted,
                'rstr_toweraccess' => $stagingRecord->rstr_toweraccess,
                'rstr_groundaccess' => $stagingRecord->rstr_groundaccess,
                'rstr_comments' => $stagingRecord->rstr_comments,
                'tower_manager_phone' => $stagingRecord->tower_manager_phone,
                'police_phone' => $stagingRecord->police_phone,
                'csr_mfg_vendor' => $stagingRecord->csr_mfg_vendor,
                'csr_model' => $stagingRecord->csr_model,
                'csr_software_version' => $stagingRecord->csr_software_version,
                'darkfiber_mfg_vendor' => $stagingRecord->darkfiber_mfg_vendor,
                'darkfiber_model' => $stagingRecord->darkfiber_model,
                'darkfiber_software_version' => $stagingRecord->darkfiber_software_version,
                'lte_mfg_vendor' => $stagingRecord->lte_mfg_vendor,
                'lte_model' => $stagingRecord->lte_model,
                'lte_software_version' => $stagingRecord->lte_software_version,
                'microwave_mfg_vendor' => $stagingRecord->microwave_mfg_vendor,
                'microwave_model' => $stagingRecord->microwave_model,
                'microwave_software_version' => $stagingRecord->microwave_software_version,
                'nid_mfg_vendor' => $stagingRecord->nid_mfg_vendor,
                'nid_model' => $stagingRecord->nid_model,
                'nid_software_version' => $stagingRecord->nid_software_version,
                'remote_monitor_model' => $stagingRecord->remote_monitor_model,
                'remote_monitor_sw_ver' => $stagingRecord->remote_monitor_sw_ver,
                'remote_monitor_vendor' => $stagingRecord->remote_monitor_vendor,
                'shelter_model_number' => $stagingRecord->shelter_model_number,
                'shelter_vendor' => $stagingRecord->shelter_vendor,
                'site_tech_name' => $stagingRecord->site_tech_name,
                'site_tech_phone' => $stagingRecord->site_tech_phone,
                'site_tech_alt_phone' => $stagingRecord->site_tech_alt_phone,
                'site_tech_email' => $stagingRecord->site_tech_email,
                'tech_mgr_name' => $stagingRecord->tech_mgr_name,
                'tech_mgr_phone' => $stagingRecord->tech_mgr_phone,
                'tech_mgr_alt_phone' => $stagingRecord->tech_mgr_alt_phone,
                'tech_mgr_email' => $stagingRecord->tech_mgr_email,
                'tech_dir_name' => $stagingRecord->tech_dir_name,
                'tech_dir_phone' => $stagingRecord->tech_dir_phone,
                'tech_dir_alt_phone' => $stagingRecord->tech_dir_alt_phone,
                'tech_dir_email' => $stagingRecord->tech_dir_email,
                'site_mgr_name' => $stagingRecord->site_mgr_name,
                'site_mgr_phone' => $stagingRecord->site_mgr_phone,
                'site_mgr_alt_phone' => $stagingRecord->site_mgr_alt_phone,
                'site_mgr_email' => $stagingRecord->site_mgr_email,
                'site_dir_name' => $stagingRecord->site_dir_name,
                'site_dir_phone' => $stagingRecord->site_dir_phone,
                'site_dir_alt_phone' => $stagingRecord->site_dir_alt_phone,
                'site_dir_email' => $stagingRecord->site_dir_email,
            ];

            // Convert empty strings to null for decimal fields to prevent database errors
            if (empty($siteData['latitude']) || $siteData['latitude'] === '') {
                $siteData['latitude'] = null;
            }
            if (empty($siteData['longitude']) || $siteData['longitude'] === '') {
                $siteData['longitude'] = null;
            }

            return $siteData;
        } catch (\Exception $e) {
            Log::error('Failed to transform staging record', [
                'staging_id' => $stagingRecord->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
