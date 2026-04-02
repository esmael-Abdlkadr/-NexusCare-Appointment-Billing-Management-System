<?php

namespace App\Models;

use App\Models\Concerns\HasSiteScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SettlementImport extends Model
{
    use HasFactory, HasSiteScope;

    public $timestamps = false;

    protected $fillable = [
        'filename',
        'file_hash',
        'imported_by',
        'site_id',
        'row_count',
        'matched_count',
        'discrepancy_count',
        'daily_variance',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'daily_variance' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ReconciliationException::class, 'import_id');
    }

    public function anomalyAlerts(): HasMany
    {
        return $this->hasMany(AnomalyAlert::class, 'import_id');
    }
}
