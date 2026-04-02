<?php

namespace App\Models;

use App\Models\Concerns\HasSiteScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnomalyAlert extends Model
{
    use HasFactory, HasSiteScope;

    public $timestamps = false;

    protected $fillable = [
        'import_id',
        'site_id',
        'variance_amount',
        'status',
        'acknowledged_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'variance_amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function settlementImport(): BelongsTo
    {
        return $this->belongsTo(SettlementImport::class, 'import_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
