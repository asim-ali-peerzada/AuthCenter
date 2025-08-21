<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SiteAccessFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_type',
        'original_file_name',
        'stored_file_path',
        'uploaded_at',
        'processed',
        'status',
        'total_records',
        'processed_records',
        'failed_records',
        'errors',
        'completed_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'processed' => 'boolean',
        'completed_at' => 'datetime',
        'errors' => 'array',
    ];

    public function sites()
    {
        return $this->hasMany(Site::class);
    }

    public function hubs()
    {
        return $this->hasMany(Hub::class);
    }
}
