<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use App\Models\Hub;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;


class ExternalDataController extends Controller
{
    /**
     * Fetch unique site and hub names for external services in a single request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSiteData(): JsonResponse
    {
        $sites = Site::whereNotNull('site_name')
            ->select('site_name')
            ->distinct()
            ->orderBy('site_name')
            ->pluck('site_name');
 
        $hubs = Hub::whereNotNull('ohpa_site')
            ->select('ohpa_site')
            ->distinct()
            ->orderBy('ohpa_site')
            ->pluck('ohpa_site');
 
        return response()->json([
            'small_cell_names' => $sites,
            'hub_names' => $hubs,
        ]);
    }
}
