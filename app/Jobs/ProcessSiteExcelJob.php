<?php

namespace App\Jobs;

use App\Models\Site;
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
use Maatwebsite\Excel\HeadingRow;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;

HeadingRowFormatter::default('none');

class ProcessSiteExcelJob implements ShouldQueue
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
            $import = new SiteExcelImport($this->uploadedFile->id);
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
            Log::info("Site Excel processing completed for file ID: {$this->uploadedFile->id}");
        } catch (\Exception $e) {
            // Update status to failed
            $this->uploadedFile->update([
                'status' => 'failed',
                'errors' => [['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]],
                'completed_at' => now(),
            ]);
            Log::error("Site Excel processing failed for file ID: {$this->uploadedFile->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}

class SiteExcelImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    protected int $fileId;
    protected int $currentRow = 1;
    protected int $totalRecords = 0;
    protected int $processedRecords = 0;
    protected int $failedRecords = 0;
    protected array $errors = [];

    public function __construct(int $fileId)
    {
        $this->fileId = $fileId;
    }

    private array $requiredColumns = [
        'Site Id',
        'Ps Loc',
        'Site Name',
        'Area',
        'Market',
        'Group',
        'Switch',
        'Type',
        'Site Type',
        'Site Function',
        'Brand',
        'Tech Name',
        'Address',
        'City',
        'St',
        'Zip',
        'County',
        'Owned',
        'Lec Name',
        'Gate Combo',
        'Gate Combo2',
        'Direction',
        'Security Lock',
        'Latitude',
        'Longitude',
        'Access Restrictions',
        'Restriction',
        'Rstr Isrestricted',
        'Rstr Toweraccess',
        'Rstr Groundaccess',
        'Rstr Comments',
        'Tower Manager Phone',
        'Police Phone',
        'Csr Mfg Vendor',
        'Csr Model',
        'Csr Software Version',
        'Darkfiber Mfg Vendor',
        'Darkfiber Model',
        'Darkfiber Software Version',
        'Lte Mfg Vendor',
        'Lte Model',
        'Lte Software Version',
        'Microwave Mfg Vendor',
        'Microwave Model',
        'Microwave Software Version',
        'Nid Mfg Vendor',
        'Nid Model',
        'Nid Software Version',
        'Remote Monitor Model',
        'Remote Monitor Sw Ver',
        'Remote Monitor Vendor',
        'Shelter Model Number',
        'Shelter Vendor',
        'Site Tech Name',
        'Site Tech Phone',
        'Site Tech Alt. Phone',
        'Site Tech Email',
        'Tech Mgr Name',
        'Tech Mgr Phone',
        'Tech Mgr Alt. Phone',
        'Tech Mgr Email',
        'Tech Dir. Name',
        'Tech Dir. Phone',
        'Tech Dir. Alt. Phone',
        'Tech Dir. Email',
        'Site Mgr. Name',
        'Site Mgr. Phone',
        'Site Mgr. Alt. Phone',
        'Site Mgr. Email',
        'Site Dir. Name',
        'Site Dir. Phone',
        'Site Dir. Alt. Phone',
        'Site Dir. Email',
    ];

    public function collection(Collection $rows): void
    {
        // Add column validation at start
        if ($rows->isEmpty()) {
            throw new \Exception('Excel file is empty');
        }

        // Get actual columns from first row
        $actualColumns = $rows->first()->keys()->toArray();
        // Check for missing columns
        $missingColumns = array_diff($this->requiredColumns, $actualColumns);
        if (!empty($missingColumns)) {
            throw new \Exception('It looks like some required columns are missing from your file. Could you double-check the format and add the necessary columns?: ' . implode(', ', $missingColumns));
        }

        $validRows = [];
        $actualRowCount = 0; // Count only non-empty rows

        foreach ($rows as $row) {
            $this->currentRow++;
            try {
                $siteData = $this->mapRowToSiteData($row);
                // Check if row has any meaningful data before counting it
                $hasAnyData = false;
                foreach (['site_name', 'site_id', 'ps_loc', 'area', 'market', 'address', 'city'] as $field) {
                    if (!empty($siteData[$field])) {
                        $hasAnyData = true;
                        break;
                    }
                }

                // Only count rows that have actual data
                if ($hasAnyData) {
                    $actualRowCount++;
                    if ($this->validateSiteData($siteData)) {
                        $validRows[] = $siteData;
                    } else {
                        $this->failedRecords++;
                    }
                }
                // Skip empty rows silently without counting them as failed

                // Process in batches of 1000
                if (count($validRows) >= 1000) {
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

    protected function mapRowToSiteData(Collection $row): array
    {
        return [
            'site_id' => $this->getTruncatedValue($row, 'Site Id', 50),
            'site_access_file_id' => $this->fileId,
            'ps_loc' => $this->getTruncatedValue($row, 'Ps Loc', 50),
            'site_name' => $this->getTruncatedValue($row, 'Site Name', 150),
            'area' => $this->getTruncatedValue($row, 'Area', 100),
            'market' => $this->getTruncatedValue($row, 'Market', 100),
            'group' => $this->getTruncatedValue($row, 'Group', 100),
            'switch' => $this->getTruncatedValue($row, 'Switch', 100),
            'type' => $this->getTruncatedValue($row, 'Type', 50),
            'site_type' => $this->getTruncatedValue($row, 'Site Type', 50),
            'site_function' => $this->getTruncatedValue($row, 'Site Function', 100),
            'brand' => $this->getTruncatedValue($row, 'Brand', 100),
            'tech_name' => $this->getTruncatedValue($row, 'Tech Name', 100),
            'address' => $this->getValue($row, 'Address'), // TEXT field, no limit
            'city' => $this->getTruncatedValue($row, 'City', 100),
            'st' => $this->getTruncatedValue($row, 'St', 5),
            'zip' => $this->getTruncatedValue($row, 'Zip', 20),
            'county' => $this->getTruncatedValue($row, 'County', 100),
            'owned' => $this->getTruncatedValue($row, 'Owned', 50),
            'lec_name' => $this->getTruncatedValue($row, 'Lec Name', 100),
            'gate_combo' => $this->getTruncatedValue($row, 'Gate Combo', 50),
            'gate_combo2' => $this->getTruncatedValue($row, 'Gate Combo2', 50),
            'direction' => $this->getValue($row, 'Direction'), // TEXT field, no limit
            'security_lock' => $this->getTruncatedValue($row, 'Security Lock', 50),
            'latitude' => $this->getNumericValue($row, 'Latitude'),
            'longitude' => $this->getNumericValue($row, 'Longitude'),
            'access_restrictions' => $this->getTruncatedValue($row, 'Access Restrictions', 100),
            'restriction' => $this->getTruncatedValue($row, 'Restriction', 100),
            'rstr_isrestricted' => $this->getTruncatedValue($row, 'Rstr Isrestricted', 50),
            'rstr_toweraccess' => $this->getTruncatedValue($row, 'Rstr Toweraccess', 50),
            'rstr_groundaccess' => $this->getTruncatedValue($row, 'Rstr Groundaccess', 50),
            'rstr_comments' => $this->getValue($row, 'Rstr Comments'), // TEXT field, no limit
            'tower_manager_phone' => $this->getTruncatedValue($row, 'Tower Manager Phone', 20),
            'police_phone' => $this->getTruncatedValue($row, 'Police Phone', 20),
            'csr_mfg_vendor' => $this->getTruncatedValue($row, 'Csr Mfg Vendor', 100),
            'csr_model' => $this->getTruncatedValue($row, 'Csr Model', 100),
            'csr_software_version' => $this->getTruncatedValue($row, 'Csr Software Version', 50),
            'darkfiber_mfg_vendor' => $this->getTruncatedValue($row, 'Darkfiber Mfg Vendor', 100),
            'darkfiber_model' => $this->getTruncatedValue($row, 'Darkfiber Model', 100),
            'darkfiber_software_version' => $this->getTruncatedValue($row, 'Darkfiber Software Version', 50),
            'lte_mfg_vendor' => $this->getTruncatedValue($row, 'Lte Mfg Vendor', 100),
            'lte_model' => $this->getTruncatedValue($row, 'Lte Model', 100),
            'lte_software_version' => $this->getTruncatedValue($row, 'Lte Software Version', 50),
            'microwave_mfg_vendor' => $this->getTruncatedValue($row, 'Microwave Mfg Vendor', 100),
            'microwave_model' => $this->getTruncatedValue($row, 'Microwave Model', 100),
            'microwave_software_version' => $this->getTruncatedValue($row, 'Microwave Software Version', 50),
            'nid_mfg_vendor' => $this->getTruncatedValue($row, 'Nid Mfg Vendor', 100),
            'nid_model' => $this->getTruncatedValue($row, 'Nid Model', 100),
            'nid_software_version' => $this->getTruncatedValue($row, 'Nid Software Version', 50),
            'remote_monitor_model' => $this->getTruncatedValue($row, 'Remote Monitor Model', 100),
            'remote_monitor_sw_ver' => $this->getTruncatedValue($row, 'Remote Monitor Sw Ver', 50),
            'remote_monitor_vendor' => $this->getTruncatedValue($row, 'Remote Monitor Vendor', 100),
            'shelter_model_number' => $this->getTruncatedValue($row, 'Shelter Model Number', 100),
            'shelter_vendor' => $this->getTruncatedValue($row, 'Shelter Vendor', 100),
            'site_tech_name' => $this->getTruncatedValue($row, 'Site Tech Name', 100),
            'site_tech_phone' => $this->getTruncatedValue($row, 'Site Tech Phone', 20),
            'site_tech_alt_phone' => $this->getTruncatedValue($row, 'Site Tech Alt. Phone', 20),
            'site_tech_email' => $this->getTruncatedValue($row, 'Site Tech Email', 150),
            'tech_mgr_name' => $this->getTruncatedValue($row, 'Tech Mgr Name', 100),
            'tech_mgr_phone' => $this->getTruncatedValue($row, 'Tech Mgr Phone', 20),
            'tech_mgr_alt_phone' => $this->getTruncatedValue($row, 'Tech Mgr Alt. Phone', 20),
            'tech_mgr_email' => $this->getTruncatedValue($row, 'Tech Mgr Email', 150),
            'tech_dir_name' => $this->getTruncatedValue($row, 'Tech Dir. Name', 100),
            'tech_dir_phone' => $this->getTruncatedValue($row, 'Tech Dir. Phone', 20),
            'tech_dir_alt_phone' => $this->getTruncatedValue($row, 'Tech Dir. Alt. Phone', 20),
            'tech_dir_email' => $this->getTruncatedValue($row, 'Tech Dir. Email', 150),
            'site_mgr_name' => $this->getTruncatedValue($row, 'Site Mgr. Name', 100),
            'site_mgr_phone' => $this->getTruncatedValue($row, 'Site Mgr. Phone', 20),
            'site_mgr_alt_phone' => $this->getTruncatedValue($row, 'Site Mgr. Alt. Phone', 20),
            'site_mgr_email' => $this->getTruncatedValue($row, 'Site Mgr. Email', 150),
            'site_dir_name' => $this->getTruncatedValue($row, 'Site Dir. Name', 100),
            'site_dir_phone' => $this->getTruncatedValue($row, 'Site Dir. Phone', 20),
            'site_dir_alt_phone' => $this->getTruncatedValue($row, 'Site Dir. Alt. Phone', 20),
            'site_dir_email' => $this->getTruncatedValue($row, 'Site Dir. Email', 150),
        ];
    }

    protected function getValue(Collection $row, string $key): ?string
    {
        $value = $row->get($key);
        if ($value === null) {
            return null;
        }
        $stringValue = trim((string) $value);
        // Check for Excel errors and formulas
        if ($this->isExcelErrorOrFormula($stringValue)) {
            return null;
        }
        return $stringValue;
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
        // Additional check for numeric values
        if ($this->isExcelErrorOrFormula($value)) {
            return null;
        }
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Check if a value is an Excel error or formula
     */
    protected function isExcelErrorOrFormula(string $value): bool
    {
        // Check for Excel errors
        $excelErrors = ['#ERROR!', '#DIV/0!', '#N/A', '#NAME?', '#NULL!', '#NUM!', '#REF!', '#VALUE!', '#GETTING_DATA'];
        foreach ($excelErrors as $error) {
            if (stripos($value, $error) !== false) {
                return true;
            }
        }
        // Check for Excel formulas (starting with =)
        if (strpos($value, '=') === 0) {
            return true;
        }
        // Check for partial formulas that might be truncated
        if (preg_match('/=\w+\(|\[\d+\]|\$[A-Z]+\$\d+/', $value)) {
            return true;
        }
        return false;
    }

    protected function validateSiteData(array $data): bool
    {
        if (empty($data['site_name'])) {
            $this->logError($this->currentRow, "Site name is required but was not found in this row. Please check your data.");
            return false;
        }

        if (isset($data['latitude']) && ($data['latitude'] < -90 || $data['latitude'] > 90)) {
            $this->logError($this->currentRow, "Latitude value '{$data['latitude']}' is invalid. Latitude must be between -90 and 90 degrees.");
            return false;
        }

        if (isset($data['longitude']) && ($data['longitude'] < -180 || $data['longitude'] > 180)) {
            $this->logError($this->currentRow, "Longitude value '{$data['longitude']}' is invalid. Longitude must be between -180 and 180 degrees.");
            return false;
        }

        return true;
    }

    protected function insertBatch(array $rows): void
    {
        try {
            DB::transaction(function () use ($rows) {
                foreach ($rows as $row) {
                    try {
                        Site::updateOrCreate(
                            ['site_name' => $row['site_name']],
                            $row
                        );
                        $this->processedRecords++;
                    } catch (\Exception $e) {
                        $this->failedRecords++;
                        $this->logError($this->currentRow, "Unable to save site data for row {$this->currentRow}: " . $e->getMessage());
                    }
                }
            });
        } catch (\Exception $e) {
            $this->logError(0, "Batch processing failed: " . $e->getMessage());
            Log::error("Failed to insert/update batch for file ID: {$this->fileId}", [
                'error' => $e->getMessage(),
                'rows_count' => count($rows),
            ]);
            throw $e;
        }
    }

    protected function logInfo(int $rowNumber, string $message): void
    {
        $infoData = [
            'row' => $rowNumber,
            'message' => $message,
            'timestamp' => now()->toISOString(),
            'type' => 'info'
        ];
        // Add to errors array but mark as info type for display purposes
        $this->errors[] = $infoData;
        $logMessage = now()->format('Y-m-d H:i:s') . " | File ID: {$this->fileId} | Row: {$rowNumber} | Info: {$message}";
        Log::channel('single')->info($logMessage, ['context' => 'site_upload_info']);
        // Also write to specific log file
        file_put_contents(
            storage_path('logs/site_upload_info.log'),
            $logMessage . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
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
        Log::channel('single')->info($logMessage, ['context' => 'site_upload_errors']);
        // Also write to specific log file
        file_put_contents(
            storage_path('logs/site_upload_errors.log'),
            $logMessage . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    protected function debugRowKeys(Collection $row): void
    {
        Log::info('Available row keys:', $row->keys()->toArray());
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
