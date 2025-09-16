<?php

namespace App\Observers;

use App\Models\SiteAccessFile;
use Illuminate\Support\Facades\Cache;

class SiteAccessFileObserver
{
    /**
     * Handle the SiteAccessFile "updated" event.
     *
     * @param  \App\Models\SiteAccessFile  $siteAccessFile
     * @return void
     */
    public function updated(SiteAccessFile $siteAccessFile)
    {
        Cache::forget('file_status_' . $siteAccessFile->id);
    }
}
