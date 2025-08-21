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
        Schema::table('site_access_files', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                ->default('pending')
                ->after('processed');

            $table->unsignedInteger('total_records')->nullable()->after('status');
            $table->unsignedInteger('processed_records')->default(0)->after('total_records');
            $table->unsignedInteger('failed_records')->default(0)->after('processed_records');

            $table->json('errors')->nullable()->after('failed_records');

            $table->timestamp('completed_at')->nullable()->after('uploaded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_access_files', function (Blueprint $table) {
             $table->dropColumn([
                'status',
                'total_records',
                'processed_records',
                'failed_records',
                'errors',
                'completed_at',
            ]);
        });
    }
};
