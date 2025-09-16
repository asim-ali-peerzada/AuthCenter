<?php

namespace App\Http\Controllers\upload;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Hub;
use App\Models\Site;
use App\Models\SiteAccessFile;
use App\Http\Requests\Upload\UploadSiteExcelRequest;
use App\Http\Requests\Upload\SiteDetailsRequest;
use App\Jobs\ProcessSiteExcelJob;
use App\Jobs\ProcessHubExcelJob;
use App\Services\SiteExcelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;


class SiteUploadController extends Controller
{
    protected SiteExcelService $siteExcelService;

    public function __construct(SiteExcelService $siteExcelService)
    {
        $this->siteExcelService = $siteExcelService;
    }

    /**
     * Upload and process Excel file containing site data.
     */
    public function uploadExcel(UploadSiteExcelRequest $request): JsonResponse
    {
        try {
            $fileType = $request->input('file_type');

            // Store the file and create database record
            $uploadedFile = $this->siteExcelService->storeFile(
                $request->file('file'),
                $fileType
            );

            // Dispatch the appropriate processing job based on file type
            if ($fileType === 'hub') {
                ProcessHubExcelJob::dispatch($uploadedFile);
                $message = 'Hub file uploaded successfully and processing has started.';
            } else {
                ProcessSiteExcelJob::dispatch($uploadedFile->id);
                $message = 'Site file uploaded successfully and processing has started.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'file_id' => $uploadedFile->id,
                    'file_name' => $uploadedFile->original_file_name,
                    'file_type' => $uploadedFile->file_type,
                    'uploaded_at' => $uploadedFile->uploaded_at->toISOString(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sites or hubs based on type parameter.
     * Supports pagination for handling large datasets efficiently.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $type = $request->query('type', 'smallcell'); // Default to smallcell

            if ($type === 'hub') {
                // Fetch unique hub records by ohpa_site with a representative id
                $data = Hub::whereNotNull('ohpa_site')
                    ->selectRaw('MIN(id) as id, ohpa_site')
                    ->groupBy('ohpa_site')
                    ->orderBy('ohpa_site')
                    ->get();

                return response()->json([
                    'success' => true,
                    'type' => 'hub',
                    'data' => $data,
                ], 200);
            } else {
                // Fetch unique site records by site_name with a representative id (smallcell)
                $data = Site::whereNotNull('site_name')
                    ->selectRaw('MIN(id) as id, site_name')
                    ->groupBy('site_name')
                    ->orderBy('site_name')
                    ->get();

                return response()->json([
                    'success' => true,
                    'type' => 'smallcell',
                    'data' => $data,
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detailed information for specific sites based on site type and names.
     */
    public function getSiteDetails(SiteDetailsRequest $request): JsonResponse
    {
        try {
            $siteName = $request->input('siteNames'); // single value now
            $siteType = $request->input('site_type');

            // If siteName is "selected_All", fetch all records for the given type
            $isAll = $siteName === 'selected_All';

            if ($siteType === 'hub') {
                // Search in Hub model using ohpa_site field
                $query = Hub::orderBy('ohpa_site');

                if ($isAll) {
                    $query->whereNotNull('ohpa_site');
                } else {
                    $query->where('ohpa_site', $siteName);
                }

                $sites = $query->get();

                return response()->json([
                    'success' => true,
                    'site_type' => 'hub',
                    'requested_site' => $siteName,
                    'found_count' => $sites->count(),
                    'data' => $sites,
                ], 200);
            } else {
                // Search in Site model using site_name field (smallcell)
                $query = Site::orderBy('site_name');

                if ($isAll) {
                    $query->whereNotNull('site_name');
                } else {
                    $query->where('site_name', $siteName);
                }

                $sites = $query->get();

                return response()->json([
                    'success' => true,
                    'site_type' => 'smallcell',
                    'requested_site' => $siteName,
                    'found_count' => $sites->count(),
                    'data' => $sites,
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch site details.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Update access details for a specific hub site.
     */
    public function updateAccessDetails(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'access_details' => 'required|string|max:65535', // TEXT field limit
            ]);

            $hub = Hub::findOrFail($id);

            $hub->update([
                'access_details' => $request->input('access_details')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Access details updated successfully.',
                'data' => [
                    'id' => $hub->id,
                    'access_details' => $hub->access_details,
                    'updated_at' => $hub->updated_at,
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hub site not found.',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update access details.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the processing status of an uploaded file.
     * This endpoint is designed for frontend polling to track file processing progress.
     */
    public function getFileStatus(Request $request, $fileId): JsonResponse
    {
        try {
            $response = Cache::remember('file_status_' . $fileId, 60, function () use ($request, $fileId) {
                $request->validate([
                    'include_errors' => 'sometimes',
                ]);

                $siteAccessFile = SiteAccessFile::findOrFail($fileId);

                $includeErrors = $request->boolean('include_errors', false);

                $response = [
                    'success' => true,
                    'data' => [
                        'file_id' => $siteAccessFile->id,
                        'file_name' => $siteAccessFile->original_file_name,
                        'file_type' => $siteAccessFile->file_type,
                        'status' => $siteAccessFile->status === 'pending' ? 'processing' : $siteAccessFile->status,
                        'uploaded_at' => $this->formatDateTime($siteAccessFile->uploaded_at),
                        'completed_at' => $this->formatDateTime($siteAccessFile->completed_at),
                        'progress' => [
                            'total_records' => $siteAccessFile->total_records ?? 0,
                            'processed_records' => $siteAccessFile->processed_records ?? 0,
                            'failed_records' => $siteAccessFile->failed_records ?? 0,
                            'success_records' => max(0, ($siteAccessFile->processed_records ?? 0) - ($siteAccessFile->failed_records ?? 0)),
                            'percentage' => $this->calculateProgressPercentage($siteAccessFile),
                        ],
                        'processing_time' => $this->calculateProcessingTime($siteAccessFile),
                    ],
                ];

                // Include errors only if requested and they exist
                if ($includeErrors && !empty($siteAccessFile->errors)) {
                    $response['data']['errors'] = $siteAccessFile->errors;
                    $response['data']['error_count'] = count($siteAccessFile->errors);
                }

                // Add polling recommendations based on status
                $response['data']['polling'] = $this->getPollingRecommendations($siteAccessFile->status);

                return $response;
            });

            return response()->json($response, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'File not found.',
                'error_code' => 'FILE_NOT_FOUND',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request parameters.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve file status.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Safely format datetime to ISO string, handling both Carbon instances and strings.
     */
    private function formatDateTime($datetime): ?string
    {
        if ($datetime === null) {
            return null;
        }

        // If it's already a Carbon instance, use toISOString()
        if ($datetime instanceof \Carbon\Carbon) {
            return $datetime->toISOString();
        }

        // If it's a string, try to parse it as Carbon first
        if (is_string($datetime)) {
            try {
                return \Carbon\Carbon::parse($datetime)->toISOString();
            } catch (\Exception $e) {
                // If parsing fails, return the string as-is or null
                return null;
            }
        }

        return null;
    }

    /**
     * Calculate processing progress percentage.
     */
    private function calculateProgressPercentage(SiteAccessFile $file): float
    {
        if (!$file->total_records || $file->total_records <= 0) {
            return $file->status === 'completed' ? 100.0 : 0.0;
        }

        $processed = $file->processed_records ?? 0;
        return round(($processed / $file->total_records) * 100, 2);
    }

    /**
     * Calculate processing time in seconds.
     */
    private function calculateProcessingTime(SiteAccessFile $file): int
    {
        $endTime = $file->completed_at ?? now();
        return (int) $file->uploaded_at->diffInSeconds($endTime);
    }

    /**
     * Get polling recommendations based on current status.
     */
    private function getPollingRecommendations(string $status): array
    {
        return match ($status) {
            'pending' => [
                'should_continue' => true,
                'recommended_interval_ms' => 2000, // 2 seconds
                'max_wait_time_ms' => 300000, // 5 minutes
            ],
            'processing' => [
                'should_continue' => true,
                'recommended_interval_ms' => 3000, // 3 seconds
                'max_wait_time_ms' => 1800000, // 30 minutes
            ],
            'completed' => [
                'should_continue' => false,
                'recommended_interval_ms' => null,
                'max_wait_time_ms' => null,
            ],
            'failed' => [
                'should_continue' => false,
                'recommended_interval_ms' => null,
                'max_wait_time_ms' => null,
            ],
            default => [
                'should_continue' => true,
                'recommended_interval_ms' => 5000, // 5 seconds
                'max_wait_time_ms' => 600000, // 10 minutes
            ],
        };
    }
}
