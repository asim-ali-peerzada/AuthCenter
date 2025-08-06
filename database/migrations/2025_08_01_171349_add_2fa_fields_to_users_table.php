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
        Schema::table('users', function (Blueprint $table) {
            $table->text('google2fa_secret')->nullable()->after('external_role');
            $table->boolean('is_2fa_enabled')->default(false)->after('google2fa_secret');
            $table->boolean('is_2fa_verified')->default(false)->after('is_2fa_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google2fa_secret', 'is_2fa_enabled', 'is_2fa_verified']);
        });
    }
};
