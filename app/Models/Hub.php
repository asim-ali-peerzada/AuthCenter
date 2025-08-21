<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Hub extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_access_file_id',
        'ohpa_site',
        'location_code',
        'uace_name',
        'construction_vendor',
        'contact',
        'street',
        'city',
        'state',
        'zip_code',
        'lat',
        'long',
        'switch',
        'fa_engineering_manager',
        'fa_engineer',
        'site_id',
        'enobe_id',
        'access_details',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'long' => 'decimal:7',
    ];

    /**
     * Get the site access file that owns the hub.
     */
    public function siteAccessFile()
    {
        return $this->belongsTo(SiteAccessFile::class);
    }
}
