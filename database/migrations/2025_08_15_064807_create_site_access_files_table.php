<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('site_access_files', function (Blueprint $table) {
             $table->id();
            $table->enum('file_type', ['hub', 'small_cell'])
                  ->comment('Type of site file: hub or small_cell');
            $table->string('original_file_name')
                  ->comment('Original name of the uploaded file');
            $table->string('stored_file_path')
                  ->comment('Path where the file is stored in storage');
            $table->timestamp('uploaded_at')
                  ->useCurrent()
                  ->comment('Timestamp when file was uploaded');
            $table->boolean('processed')
                  ->default(false)
                  ->comment('Whether the file has been processed and imported to DB');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_access_files');
    }
};
