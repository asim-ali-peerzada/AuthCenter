<?php

namespace App\Jobs;

use App\Models\Hub;
use App\Models\SiteAccessFile;
use App\Services\SiteExcelService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Illuminate\Support\Collection;

HeadingRowFormatter::default('none');

class ProcessHubExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected SiteAccessFile $uploadedFile;
    protected SiteExcelService $siteExcelService;

    /**
     * Create a new job instance.
     */
    public function __construct(SiteAccessFile $uploadedFile)
    {
        $this->uploadedFile = $uploadedFile;
    }

    /**
     * Execute the job.
     */
    public function handle(SiteExcelService $siteExcelService): void
    {
        $this->siteExcelService = $siteExcelService;
        $filePath = $this->siteExcelService->getFullPath($this->uploadedFile->stored_file_path);

        // Update status to processing
        $this->uploadedFile->update(['status' => 'processing']);

        try {
            $import = new HubExcelImport($this->uploadedFile->id);
            Excel::import($import, $filePath);

            // Update final statistics
            $this->uploadedFile->update([
                'status' => 'completed',
                'total_records' => $import->getTotalRecords(),
                'processed_records' => $import->getProcessedRecords(),
                'failed_records' => $import->getFailedRecords(),
                'errors' => $import->getErrors(),
                'completed_at' => now(),
            ]);

            Log::info("Hub Excel processing completed for file ID: {$this->uploadedFile->id}");
        } catch (\Exception $e) {
            // Update status to failed
            $this->uploadedFile->update([
                'status' => 'failed',
                'errors' => [['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]],
                'completed_at' => now(),
            ]);

            Log::error("Hub Excel processing failed for file ID: {$this->uploadedFile->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}

class HubExcelImport implements ToCollection, WithChunkReading, WithHeadingRow, WithCalculatedFormulas
{
    protected int $fileId;
    protected int $currentRow = 0;
    protected int $totalRecords = 0;
    protected int $processedRecords = 0;
    protected int $failedRecords = 0;
    protected array $errors = [];

    public function __construct(int $fileId)
    {
        $this->fileId = $fileId;
    }

    public function collection(Collection $rows): void
    {
        $validRows = [];
        $actualRowCount = 0;

        foreach ($rows as $row) {
            $this->currentRow++;

            try {
                $hubData = $this->mapRowToHubData($row);

                // Check if row has any meaningful data before counting it
                $hasAnyData = false;
                foreach (['ohpa_site', 'location_code', 'uace_name', 'construction_vendor', 'contact', 'street', 'city', 'state', 'zip_code'] as $field) {
                    if (!empty($hubData[$field])) {
                        $hasAnyData = true;
                        break;
                    }
                }

                // Only count rows that have actual data
                if ($hasAnyData) {
                    $actualRowCount++;

                    if ($this->validateHubData($hubData)) {
                        $validRows[] = $hubData;
                    } else {
                        $this->failedRecords++;
                    }
                }
                // Skip empty rows silently without counting them as failed

                // Process in batches of 100
                if (count($validRows) >= 100) {
                    $this->insertBatch($validRows);
                    $validRows = [];
                }
            } catch (\Exception $e) {
                $this->failedRecords++;
                $this->logError($this->currentRow, $e->getMessage());
            }
        }

        // Set the actual total records count
        $this->totalRecords = $actualRowCount;

        // Insert remaining rows
        if (!empty($validRows)) {
            $this->insertBatch($validRows);
        }
    }

    protected function mapRowToHubData(Collection $row): array
    {
        return [
            'site_access_file_id' => $this->fileId,
            'ohpa_site' => $this->getTruncatedValue($row, 'OHPA Site: Site Name', 150),
            'location_code' => $this->getTruncatedValue($row, 'Location Code [FZ to ST]', 50),
            'uace_name' => $this->getTruncatedValue($row, 'UACE NAME', 150),
            'construction_vendor' => $this->getTruncatedValue($row, 'CONSTRUCTION VENDOR', 150),
            'contact' => $this->getTruncatedValue($row, 'CONTACT', 150),
            'street' => $this->getValue($row, 'STREET'), // TEXT field, no limit
            'city' => $this->getTruncatedValue($row, 'CITY', 100),
            'state' => $this->getTruncatedValue($row, 'STATE', 5),
            'zip_code' => $this->getTruncatedValue($row, 'ZIP CODE', 20),
            'lat' => $this->getNumericValue($row, 'LAT'),
            'long' => $this->getNumericValue($row, 'LONG'),
            'switch' => $this->getTruncatedValue($row, 'SWITCH', 100),
            'fa_engineering_manager' => $this->getTruncatedValue($row, 'FA ENGINEERING MANAGER', 150),
            'fa_engineer' => $this->getTruncatedValue($row, 'FA ENGINEER', 150),
            'site_id' => $this->getTruncatedValue($row, 'SITE ID', 50),
            'enobe_id' => $this->getTruncatedValue($row, 'eNOBE ID', 50),
        ];
    }

    protected function getValue(Collection $row, string $key): ?string
    {
        $normalizedKey = strtoupper(trim($key));

        // First try direct key lookup
        $value = $row->get($key);

        // If exact match fails, try normalized (trim/case-insensitive) key matching
        if ($value === null) {
            $foundKey = null;

            foreach ($row->keys() as $availableKey) {
                if (!is_string($availableKey)) {
                    continue;
                }
                $normalizedAvailableKey = strtoupper(trim($availableKey));
                if ($normalizedAvailableKey === $normalizedKey) {
                    $foundKey = $availableKey;
                    break;
                }
            }

            if ($foundKey !== null) {
                $value = $row->get($foundKey);
            } else {
                return null;
            }
        }

        if ($value === null) {
            return null;
        }

        return trim((string) $value);
    }

    protected function getTruncatedValue(Collection $row, string $key, int $maxLength): ?string
    {
        $value = $this->getValue($row, $key);
        return $value !== null ? substr($value, 0, $maxLength) : null;
    }

    protected function getNumericValue(Collection $row, string $key): ?float
    {
        $value = $this->getValue($row, $key);

        if ($value === null) {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    protected function validateHubData(array $data): bool
    {
        if (empty($data['ohpa_site'])) {
            $this->logError($this->currentRow, "Missing required field 'OHPA Site: Site Name'. Please ensure this column has a value.");
            return false;
        }

        // Validate lat/long if provided
        if ($data['lat'] !== null && ($data['lat'] < -90 || $data['lat'] > 90)) {
            $this->logError($this->currentRow, "Invalid latitude value: {$data['lat']}. Latitude must be between -90 and 90.");
            return false;
        }

        if ($data['long'] !== null && ($data['long'] < -180 || $data['long'] > 180)) {
            $this->logError($this->currentRow, "Invalid longitude value: {$data['long']}. Longitude must be between -180 and 180.");
            return false;
        }

        return true;
    }

    protected function insertBatch(array $rows): void
    {
        try {
            DB::transaction(function () use ($rows) {
                foreach ($rows as $row) {
                    // Check if record with same ohpa_site already exists
                    $existingHub = Hub::where('ohpa_site', $row['ohpa_site'])->first();

                    if ($existingHub) {
                        // Compare data to avoid redundant operations
                        $existingData = $existingHub->only(array_keys($row));
                        $incomingData = $row;

                        // Remove timestamps and id from comparison if present
                        unset($existingData['created_at'], $existingData['updated_at'], $existingData['id']);
                        unset($incomingData['created_at'], $incomingData['updated_at'], $incomingData['id']);

                        // Normalize values to strings and sort by keys to avoid false mismatches
                        $existingDataNormalized = [];
                        foreach ($existingData as $k => $v) {
                            $existingDataNormalized[$k] = $v === null ? null : (string) $v;
                        }
                        $incomingDataNormalized = [];
                        foreach ($incomingData as $k => $v) {
                            $incomingDataNormalized[$k] = $v === null ? null : (string) $v;
                        }

                        ksort($existingDataNormalized);
                        ksort($incomingDataNormalized);

                        // Skip if data is identical after normalization
                        if ($existingDataNormalized !== $incomingDataNormalized) {
                            // Update existing record with latest data
                            $existingHub->update($row);
                            $this->processedRecords++;
                        }
                        // If data is identical, skip without incrementing counters
                    } else {
                        // Insert new record
                        Hub::create($row);
                        $this->processedRecords++;
                    }
                }
            });
        } catch (\Exception $e) {
            $this->failedRecords += count($rows);
            $this->logError(0, "Failed to process batch: " . $e->getMessage());

            Log::error("Failed to process hub batch for file ID: {$this->fileId}", [
                'error' => $e->getMessage(),
                'rows_count' => count($rows),
            ]);
            throw $e;
        }
    }

    protected function logError(int $rowNumber, string $error): void
    {
        $errorData = [
            'row' => $rowNumber,
            'message' => $error,
            'timestamp' => now()->toISOString()
        ];

        $this->errors[] = $errorData;

        $logMessage = now()->format('Y-m-d H:i:s') . " | File ID: {$this->fileId} | Row: {$rowNumber} | Error: {$error}";

        Log::channel('single')->info($logMessage, ['context' => 'hub_upload_errors']);

        // Also write to specific log file
        file_put_contents(
            storage_path('logs/hub_upload_errors.log'),
            $logMessage . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    public function getTotalRecords(): int
    {
        return $this->totalRecords;
    }

    public function getProcessedRecords(): int
    {
        return $this->processedRecords;
    }

    public function getFailedRecords(): int
    {
        return $this->failedRecords;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
