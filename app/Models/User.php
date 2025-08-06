<?php

namespace App\Models;


use App\Traits\Uuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;


/**
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\Domain> $domains
 */

class User extends Authenticatable
{
    /**
     * @mixin \Laravel\Sanctum\HasApiTokens
     * @mixin \Illuminate\Database\Eloquent\SoftDeletes
     * @mixin \App\Traits\Uuids
     */
    use HasApiTokens, SoftDeletes, Uuids;

    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'email',
        'password',
        'status',
        'is_approved',
        'user_origin',
        'role',
        'external_role',
        'image_url',
        'failed_attempts',
        'locked_until',
        'google2fa_secret',
        'is_2fa_enabled',
        'is_2fa_verified',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'locked_until' => 'datetime',
    ];

    protected $appends = ['image_url_full'];

    public function getImageUrlFullAttribute(): ?string
    {
        if (!$this->image_url) {
            return null;
        }

        // Manually construct the URL without double slashes
        return str_replace('//', '/', url('storage/' . $this->image_url));
    }


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Domains the user may access */
    public function domains()
    {
        return $this->belongsToMany(Domain::class, 'user_domain_access')
            ->withTimestamps();
    }

    public function blacklistedJwts()
    {
        return $this->hasMany(BlacklistedJwt::class);
    }

    /** Activities by the user */
    public function activities()
    {
        return $this->hasMany(UserActivity::class);
    }
    
    /**
     * Check if the user has an admin role.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        // Accommodate both a direct 'role' property and a potential 'hasRole' method from a package.
        return (method_exists($this, 'hasRole') && $this->hasRole('admin')) || $this->role === 'admin';
    }
}
