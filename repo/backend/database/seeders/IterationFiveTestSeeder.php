<?php

namespace Database\Seeders;

use App\Models\Payment;
use Illuminate\Database\Seeder;

class IterationFiveTestSeeder extends Seeder
{
    public function run(): void
    {
        Payment::withoutGlobalScopes()->firstOrCreate(
            ['reference_id' => 'REC-TXN-001'],
            [
                'amount' => 100.00,
                'method' => 'check',
                'fee_assessment_id' => null,
                'posted_by' => 6,
                'site_id' => 1,
                'notes' => 'reconciliation seed payment',
                'created_at' => now(),
            ],
        );

        Payment::withoutGlobalScopes()->firstOrCreate(
            ['reference_id' => 'REC-TXN-002'],
            [
                'amount' => 30.00,
                'method' => 'cash',
                'fee_assessment_id' => null,
                'posted_by' => 6,
                'site_id' => 1,
                'notes' => 'reconciliation seed payment mismatch',
                'created_at' => now(),
            ],
        );
    }
}
