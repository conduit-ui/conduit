<?php

declare(strict_types=1);

namespace ConduitIo\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Component extends Model
{
    protected $fillable = [
        'name',
        'package_name', 
        'version',
        'description',
        'metadata',
        'installed_at',
        'status'
    ];

    protected $casts = [
        'metadata' => 'array',
        'installed_at' => 'datetime'
    ];
}