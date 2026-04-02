<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationException extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'import_id',
        'row_data',
        'expected_amount',
        'actual_amount',
        'reason',
        'status',
        'resolved_by',
        'resolution_note',
        'resolved_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'row_data' => 'array',
            'expected_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function settlementImport(): BelongsTo
    {
        return $this->belongsTo(SettlementImport::class, 'import_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
