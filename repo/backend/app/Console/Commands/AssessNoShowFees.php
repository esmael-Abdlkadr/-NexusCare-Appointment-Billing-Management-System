<?php

namespace App\Console\Commands;

use App\Repositories\AppointmentBillingRepository;
use App\Repositories\FeeRuleRepository;
use App\Services\FeeService;
use Illuminate\Console\Command;

class AssessNoShowFees extends Command
{
    protected $signature = 'fees:assess-noshows';
    protected $description = 'Assess no-show fees for confirmed appointments past grace period';

    public function __construct(
        private readonly AppointmentBillingRepository $appointmentBillingRepository,
        private readonly FeeRuleRepository $feeRuleRepository,
        private readonly FeeService $feeService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $assessed = 0;

        $rules = $this->feeRuleRepository->activeNoShowRulesBySite();

        foreach ($rules as $rule) {
            $siteId = (int) $rule->site_id;
            $graceMinutes = (int) $rule->grace_minutes;
            $appointments = $this->appointmentBillingRepository->dueForNoShowAssessment($graceMinutes)
                ->where('site_id', $siteId);

            foreach ($appointments as $appointment) {
                $this->feeService->assessNoShowFee($appointment);
                $appointment->assessed_no_show = true;
                $this->appointmentBillingRepository->save($appointment);
                $assessed++;
            }
        }

        $this->info('No-show fees assessed: '.$assessed);

        return self::SUCCESS;
    }
}
