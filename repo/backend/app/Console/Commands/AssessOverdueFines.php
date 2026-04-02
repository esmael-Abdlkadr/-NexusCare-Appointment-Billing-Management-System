<?php

namespace App\Console\Commands;

use App\Services\FeeService;
use Illuminate\Console\Command;

class AssessOverdueFines extends Command
{
    protected $signature = 'fees:assess-overdue';
    protected $description = 'Assess overdue fines for pending fee assessments';

    public function __construct(
        private readonly FeeService $feeService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->feeService->assessOverdueFines();
        $this->info('Overdue fines assessed: '.$count);

        return self::SUCCESS;
    }
}
