<?php

namespace App\Models;

use App\Models\Concerns\HasSiteScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory, HasSiteScope;

    public $timestamps = false;

    protected $fillable = [
        'reference_id',
        'amount',
        'method',
        'fee_assessment_id',
        'posted_by',
        'site_id',
        'notes',
        'batch_file_path',
        'batch_row_count',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function feeAssessment(): BelongsTo
    {
        return $this->belongsTo(FeeAssessment::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
