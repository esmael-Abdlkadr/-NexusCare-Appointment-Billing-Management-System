<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'client_id',
        'fee_type',
        'amount',
        'status',
        'waiver_by',
        'waiver_note',
        'notes',
        'assessed_at',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'assessed_at' => 'datetime',
            'due_date' => 'date',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function waiverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiver_by');
    }
}
