<?php

namespace App\Console\Commands;

use App\Services\SyncService;
use Illuminate\Console\Command;

class IncrementalSync extends Command
{
    protected $signature = 'sync:incremental';
    protected $description = 'Run incremental sync for all sites';

    public function __construct(
        private readonly SyncService $syncService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->syncService->runAllSites();
        $this->info('Incremental sync completed');

        return self::SUCCESS;
    }
}
