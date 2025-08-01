<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RefreshToken extends Model
{
    use HasFactory;

    protected $fillable = ['user_uuid', 'token', 'expires_at'];

    public $timestamps = true;

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
