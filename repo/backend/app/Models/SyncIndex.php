<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncIndex extends Model
{
    use HasFactory;

    protected $table = 'sync_index';

    protected $fillable = [
        'site_id',
        'entity_type',
        'entity_id',
        'fingerprint',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }
}
