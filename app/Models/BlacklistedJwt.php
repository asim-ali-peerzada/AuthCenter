<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlacklistedJwt extends Model
{
    protected $fillable = ['jti', 'user_id', 'expires_at'];
    protected $dates    = ['expires_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
