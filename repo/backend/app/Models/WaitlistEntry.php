<?php

namespace App\Models;

use App\Models\Concerns\HasSiteScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WaitlistEntry extends Model
{
    use HasFactory, HasSiteScope, SoftDeletes;

    protected $table = 'waitlist';

    protected $fillable = [
        'client_id',
        'service_type',
        'priority',
        'preferred_start',
        'preferred_end',
        'status',
        'site_id',
        'department_id',
    ];

    protected function casts(): array
    {
        return [
            'preferred_start' => 'datetime',
            'preferred_end' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
