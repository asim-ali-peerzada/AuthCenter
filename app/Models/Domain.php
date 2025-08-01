<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


/**
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 */
class Domain extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'key', 'url', 'image_url', 'detail'];

    protected $appends = ['image_full_url'];

    public function getImageFullUrlAttribute(): ?string
    {
        return $this->image_url
            ? asset('storage/' . $this->image_url)
            : null;
    }

    /** Users who can log in to this domain */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_domain_access')
            ->withTimestamps();
    }
}
