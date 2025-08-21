<?php

namespace App\Services;

use App\Models\SiteAccessFile;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SiteExcelService
{
    /**
     * Store the uploaded file and create database record.
     */
    public function storeFile(HttpUploadedFile $file, string $fileType = 'small_cell'): SiteAccessFile
    {
        $now = now();
        
        // Create directory based on file type
        $directory = $fileType === 'hub' 
            ? 'uploads/hubs/' . $now->format('Y/m')
            : 'uploads/sites/' . $now->format('Y/m');
            
        $filename = $now->format('YmdHis') . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
        
        // Store file in storage/app directory
        $filePath = $file->storeAs($directory, $filename);
        
        // Create database record
        return SiteAccessFile::create([
            'original_file_name' => $file->getClientOriginalName(),
            'stored_file_path' => $filePath,
            'file_type' => $fileType,
            'uploaded_at' => $now,
        ]);
    }

    /**
     * Get the full storage path for a file.
     */
    public function getFullPath(string $filePath): string
    {
        return Storage::path($filePath);
    }
}