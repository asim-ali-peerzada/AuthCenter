<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Cache;

/**
 * Custom Pivot Model to handle User-Specific Cache Flushing.
 */
class UserDomainAccess extends Pivot
{
    protected $table = 'user_domain_access';

    protected static function booted()
    {
        // Fired when a user is granted access (attach/sync)
        static::created(function ($pivot) {
            self::flushUserCache($pivot->user_id);
        });

        // Fired when access is revoked (detach/sync)
        static::deleted(function ($pivot) {
            self::flushUserCache($pivot->user_id);
        });
        
        // Fired if pivot metadata is updated
        static::updated(function ($pivot) {
            self::flushUserCache($pivot->user_id);
        });
    }

    /**
     * Flush only the specific keys for the user involved in this transaction.
     */
    protected static function flushUserCache($userId)
    {
        Cache::forget("user_{$userId}_assigned_domains");
        Cache::forget("user_{$userId}_page_permissions");
    }
}