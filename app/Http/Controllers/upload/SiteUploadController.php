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
     * Get all uploaded files with their status and details.
     */
    public function getUploadedFiles(Request $request): JsonResponse
    {
        try {
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            $query = SiteAccessFile::orderBy('uploaded_at', 'desc')
                ->select([
                    'id',
                    'file_type',
                    'original_file_name',
                    'uploaded_at',
                    'processed',
                    'status',
                    'total_records',
                    'processed_records',
                    'failed_records',
                    'completed_at'
                ]);

            $paginatedFiles = $query->paginate($perPage, ['*'], 'page', $page);

            $files = $paginatedFiles->getCollection()->map(function ($file) {
                return [
                    'id' => $file->id,
                    'name' => $file->original_file_name,
                    'type' => ucfirst($file->file_type),
                    'size' => $this->formatFileSize($file),
                    'status' => $this->getDisplayStatus($file),
                    'uploadDate' => $this->formatUploadDate($file->uploaded_at),
                    'progress' => [
                        'total_records' => $file->total_records ?? 0,
                        'processed_records' => $file->processed_records ?? 0,
                        'failed_records' => $file->failed_records ?? 0,
                        'percentage' => $this->calculateProgressPercentage($file),
                    ],
                    'completed_at' => $this->formatDateTime($file->completed_at),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $files,
                'pagination' => [
                    'current_page' => $paginatedFiles->currentPage(),
                    'last_page' => $paginatedFiles->lastPage(),
                    'per_page' => $paginatedFiles->perPage(),
                    'total' => $paginatedFiles->total(),
                    'from' => $paginatedFiles->firstItem(),
                    'to' => $paginatedFiles->lastItem(),
                    'has_more_pages' => $paginatedFiles->hasMorePages(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch uploaded files.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get polling recommendations based on current status.
     */
    private function getPollingRecommendations(string $status): array
    {
        switch ($status) {
            case 'pending':
                return [
                    'should_continue' => true,
                    'recommended_interval_ms' => 2000, // 2 seconds
                    'max_wait_time_ms' => 300000, // 5 minutes
                ];
            case 'processing':
                return [
                    'should_continue' => true,
                    'recommended_interval_ms' => 3000, // 3 seconds
                    'max_wait_time_ms' => 1800000, // 30 minutes
                ];
            case 'completed':
                return [
                    'should_continue' => false,
                    'recommended_interval_ms' => null,
                    'max_wait_time_ms' => null,
                ];
            case 'failed':
                return [
                    'should_continue' => false,
                    'recommended_interval_ms' => null,
                    'max_wait_time_ms' => null,
                ];
            default:
                return [
                    'should_continue' => true,
                    'recommended_interval_ms' => 5000, // 5 seconds
                    'max_wait_time_ms' => 600000, // 10 minutes
                ];
        }
    }

    /**
     * Format file size for display.
     */
    private function formatFileSize(SiteAccessFile $file): string
    {
        // Since we don't store file size in the database, we'll estimate based on records
        $estimatedSize = ($file->total_records ?? 0) * 0.5; // Rough estimate: 0.5KB per record
        return $this->formatBytes($estimatedSize * 1024); // Convert to bytes
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes($bytes, $precision = 1): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Get display status for the file.
     */
    private function getDisplayStatus(SiteAccessFile $file): string
    {
        if ($file->status === 'pending') {
            return 'Processing';
        }

        if ($file->status === 'completed') {
            return 'Completed';
        }

        if ($file->status === 'failed') {
            return 'Failed';
        }

        return ucfirst($file->status);
    }

    /**
     * Delete an uploaded file and all its related records.
     */
    public function deleteFile(Request $request, $fileId): JsonResponse
    {
        try {
            $siteAccessFile = SiteAccessFile::findOrFail($fileId);

            // Get file type for related records deletion
            $fileType = $siteAccessFile->file_type;
            $fileName = $siteAccessFile->original_file_name;

            // Delete related records based on file type
            if ($fileType === 'hub') {
                // Delete related Hub records
                Hub::where('id', $fileId)->delete();
            } else {
                // Delete related Site records
                Site::where('id', $fileId)->delete();
            }

            // Delete the file record itself
            $siteAccessFile->delete();

            return response()->json([
                'success' => true,
                'message' => 'File and all related records deleted successfully.',
                'data' => [
                    'deleted_file' => $fileName,
                    'file_type' => $fileType,
                    'deleted_at' => now()->toISOString(),
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'File not found.',
                'error_code' => 'FILE_NOT_FOUND',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format upload date for display.
     */
    private function formatUploadDate($uploadedAt): string
    {
        if (!$uploadedAt) {
            return 'Unknown';
        }

        $date = $uploadedAt instanceof \Carbon\Carbon ? $uploadedAt : \Carbon\Carbon::parse($uploadedAt);
        $now = now();

        $diffInMinutes = $date->diffInMinutes($now);
        $diffInHours = $date->diffInHours($now);
        $diffInDays = $date->diffInDays($now);

        if ($diffInMinutes < 1) {
            return 'Just now';
        } elseif ($diffInMinutes < 60) {
            return $diffInMinutes . ' minute' . ($diffInMinutes > 1 ? 's' : '') . ' ago';
        } elseif ($diffInHours < 24) {
            return $diffInHours . ' hour' . ($diffInHours > 1 ? 's' : '') . ' ago';
        } elseif ($diffInDays < 7) {
            return $diffInDays . ' day' . ($diffInDays > 1 ? 's' : '') . ' ago';
        } elseif ($diffInDays < 30) {
            $weeks = floor($diffInDays / 7);
            return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
        } elseif ($diffInDays < 365) {
            $months = floor($diffInDays / 30);
            return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
        } else {
            $years = floor($diffInDays / 365);
            return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
        }
    }
}
