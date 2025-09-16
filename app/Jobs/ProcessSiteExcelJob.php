<?php

namespace App\Jobs;

use App\Models\Site;
use App\Models\StagingSite;
use App\Models\SiteAccessFile;
use App\Services\SiteImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

class ProcessSiteExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;
    public $tries = 3;
    public $maxExceptions = 1;

    public function __construct(protected int $fileId) {}

    public function handle(SiteImportService $importService): void
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $file = SiteAccessFile::findOrFail($this->fileId);

        try {
            // Convert Excel to CSV
            $csvPath = $this->convertExcelToCsv($file->stored_file_path);

            // Load CSV into staging table with validation
            $totalRecords = $this->loadCsvToStaging($csvPath, $file->id);

            // Process staging records in optimized batches
            $this->processStagingRecords($importService, $file->id);

            // Clean up
            $this->cleanup($csvPath, $file->id);

            $processingTime = microtime(true) - $startTime;
            $peakMemory = memory_get_peak_usage(true);

            $file->update([
                'status' => 'completed',
                'total_records' => $totalRecords,
                'completed_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Site excel processing failed', [
                'file_id' => $this->fileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $file->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            // Don't rethrow the exception to prevent queue from stopping
            return;
        }
    }

    protected function convertExcelToCsv(string $excelPath): string
    {
        try {
            $reader = IOFactory::createReaderForFile(Storage::path($excelPath));
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(true);

            // Disable formula calculation for performance
            \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(
                new \PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder()
            );

            $spreadsheet = $reader->load(Storage::path($excelPath));

            // Clear formulas to prevent calculation overhead
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                foreach ($sheet->getCoordinates() as $coord) {
                    $cell = $sheet->getCell($coord);
                    if ($cell->isFormula()) {
                        $cell->setValue($cell->getOldCalculatedValue());
                    }
                }
            }
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to load Excel file: " . $e->getMessage());
        }

        $tempDir = 'uploads/temp';
        if (!Storage::exists($tempDir)) {
            Storage::makeDirectory($tempDir);
        }

        $csvPath = $tempDir . '/' . uniqid() . '.csv';
        $fullPath = Storage::path($csvPath);

        (new Csv($spreadsheet))->save($fullPath);

        // Free memory
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        gc_collect_cycles();

        return $csvPath;
    }

    protected function loadCsvToStaging(string $csvPath, int $fileId): int
    {
        $fullPath = Storage::path($csvPath);

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("CSV file not found at: $fullPath");
        }

        $handle = fopen($fullPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Could not open CSV file: $fullPath");
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new \RuntimeException("Invalid CSV file: no headers found");
        }

        $mapping = (new SiteImportService())->getColumnMapping();
        $validColumns = (new StagingSite())->getFillable();

        $normalizedHeaders = [];
        foreach ($headers as $header) {
            $cleanHeader = trim($header);
            if (empty($cleanHeader)) {
                continue;
            }

            $mappedColumn = $mapping[$cleanHeader] ?? Str::snake($cleanHeader);

            if (in_array($mappedColumn, $validColumns)) {
                $normalizedHeaders[] = $mappedColumn;
            } else {
                Log::warning('Unmapped column ignored', [
                    'original_header' => $cleanHeader,
                    'mapped_column' => $mappedColumn
                ]);
                $normalizedHeaders[] = null;
            }
        }

        $batch = [];
        $batchSize = 500;
        $totalRecords = 0;
        $batchCount = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row))) {
                continue;
            }

            $data = [];
            foreach ($normalizedHeaders as $index => $column) {
                if ($column && isset($row[$index])) {
                    $value = trim($row[$index]);
                    if (in_array($column, ['latitude', 'longitude'])) {
                        $numeric = filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                        $data[$column] = $numeric !== false ? $numeric : null;
                    } else {
                        $data[$column] = $value === '' ? null : $value;
                    }
                }
            }


            $data['site_access_file_id'] = $fileId;
            $data['status'] = 'pending';

            $batch[] = $data;
            $totalRecords++;

            if (count($batch) >= $batchSize) {
                StagingSite::insert($batch);
                $batch = [];
                $batchCount++;

                // Memory management
                if ($batchCount % 10 === 0) {
                    gc_collect_cycles();
                }
            }
        }

        if (!empty($batch)) {
            StagingSite::insert($batch);
        }

        fclose($handle);
        return $totalRecords;
    }

    protected function processStagingRecords(SiteImportService $importService, int $fileId): void
    {
        $chunkSize = 1000;
        $processedCount = 0;
        $failedCount = 0;
        $batchSize = 500;

        StagingSite::where('site_access_file_id', $fileId)
            ->where('status', 'pending')
            ->chunkById($chunkSize, function ($stagingRecords) use ($importService, &$processedCount, &$failedCount, $batchSize, $fileId) {
                // Process records in bulk batches
                $stagingRecords->chunk($batchSize)->each(function ($batch) use ($importService, &$processedCount, &$failedCount) {
                    $this->processBulkUpsert($batch, $importService);
                    $processedCount += $batch->count();
                    $failedCount += $batch->where('status', 'failed')->count();
                });

                // Update progress in the file record
                SiteAccessFile::where('id', $fileId)->update([
                    'processed_records' => $processedCount,
                    'failed_records' => $failedCount
                ]);

                // Memory management
                gc_collect_cycles();
            });
    }

    protected function processBulkUpsert($stagingRecords, SiteImportService $importService): void
    {
        $upsertData = [];
        $updateColumns = [];

        foreach ($stagingRecords as $record) {
            try {
                $siteData = $importService->transformStagingToSiteData($record);

                if ($siteData) {
                    $upsertData[] = $siteData;
                    $record->update(['status' => 'processed']);
                }
            } catch (\Exception $e) {
                $record->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);
            }
        }

        if (!empty($upsertData)) {
            $fillableColumns = (new Site())->getFillable();
            $updateColumns = array_diff($fillableColumns, ['site_name']);

            Site::upsert(
                $upsertData,
                ['site_name'],
                $updateColumns
            );
        }
    }

    protected function cleanup(string $csvPath, int $fileId): void
    {
        if (Storage::exists($csvPath)) {
            Storage::delete($csvPath);
        }

        StagingSite::where('site_access_file_id', $fileId)->delete();
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
