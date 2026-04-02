<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppointmentVersion;
use App\Models\FeeAssessment;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\RefundOrder;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\WaitlistEntry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $dayAfterTomorrow = $today->copy()->addDays(2);

        $admin = User::withoutGlobalScopes()
            ->whereIn('identifier', ['admin', 'admin001'])
            ->first();

        if (! $admin) {
            $admin = User::withoutGlobalScopes()->updateOrCreate(
                ['identifier' => 'admin'],
                [
                    'password_hash' => Hash::make('Admin@NexusCare1', ['rounds' => 12]),
                    'role' => 'administrator',
                    'site_id' => 1,
                    'department_id' => 1,
                    'is_banned' => false,
                    'failed_attempts' => 0,
                ],
            );
        }

        $staff1 = $this->upsertUser('staff1', 'Staff@NexusCare1', 'staff');
        $reviewer1 = $this->upsertUser('reviewer1', 'Reviewer@NexusCare1', 'reviewer');
        $client1 = $this->upsertUser('client1', 'Client@NexusCare1', 'staff');

        // SECTION 1 - Extra Users
        $staff3 = $this->upsertUser('staff3', 'Staff3@NexusCare1', 'staff');
        $client2 = $this->upsertUser('client2', 'Client2@NexusCare1', 'staff');
        $client3 = $this->upsertUser('client3', 'Client3@NexusCare1', 'staff');
        $bannedUser = $this->upsertUser('banned_user', 'Banned@NexusCare1', 'staff', [
            'is_banned' => true,
        ]);
        $mutedUser = $this->upsertUser('muted_user', 'Muted@NexusCare1', 'staff', [
            'muted_until' => $now->copy()->addHours(20),
        ]);

        foreach ([$admin, $staff1, $reviewer1, $client1, $staff3, $client2, $client3, $bannedUser, $mutedUser] as $user) {
            $this->assignRole($user);
        }

        // SECTION 2 - Appointments
        $appointmentDefinitions = [
            ['service_type' => 'General Consultation', 'status' => Appointment::STATUS_REQUESTED, 'start' => $tomorrow->copy()->setTime(9, 0), 'end' => $tomorrow->copy()->setTime(9, 30), 'cancel_reason' => null, 'assessed_no_show' => false],
            ['service_type' => 'Follow-up Visit', 'status' => Appointment::STATUS_CONFIRMED, 'start' => $tomorrow->copy()->setTime(10, 0), 'end' => $tomorrow->copy()->setTime(10, 30), 'cancel_reason' => null, 'assessed_no_show' => false],
            ['service_type' => 'Lab Review', 'status' => Appointment::STATUS_CONFIRMED, 'start' => $tomorrow->copy()->setTime(11, 0), 'end' => $tomorrow->copy()->setTime(11, 30), 'cancel_reason' => null, 'assessed_no_show' => false],
            ['service_type' => 'Physiotherapy', 'status' => Appointment::STATUS_CHECKED_IN, 'start' => $today->copy()->setTime(8, 0), 'end' => $today->copy()->setTime(8, 30), 'cancel_reason' => null, 'assessed_no_show' => false],
            ['service_type' => 'Dental Checkup', 'status' => Appointment::STATUS_NO_SHOW, 'start' => $today->copy()->subDay()->setTime(9, 0), 'end' => $today->copy()->subDay()->setTime(9, 30), 'cancel_reason' => null, 'assessed_no_show' => true],
            ['service_type' => 'Eye Exam', 'status' => Appointment::STATUS_COMPLETED, 'start' => $today->copy()->subDays(3)->setTime(14, 0), 'end' => $today->copy()->subDays(3)->setTime(14, 30), 'cancel_reason' => null, 'assessed_no_show' => false],
            ['service_type' => 'Blood Test', 'status' => Appointment::STATUS_COMPLETED, 'start' => $today->copy()->subDays(5)->setTime(10, 0), 'end' => $today->copy()->subDays(5)->setTime(10, 30), 'cancel_reason' => null, 'assessed_no_show' => false],
            ['service_type' => 'X-Ray', 'status' => Appointment::STATUS_CANCELLED, 'start' => $today->copy()->subDays(2)->setTime(15, 0), 'end' => $today->copy()->subDays(2)->setTime(15, 30), 'cancel_reason' => 'Client requested reschedule', 'assessed_no_show' => false],
            ['service_type' => 'Nutrition Consult', 'status' => Appointment::STATUS_CANCELLED, 'start' => $today->copy()->subDays(4)->setTime(11, 0), 'end' => $today->copy()->subDays(4)->setTime(11, 30), 'cancel_reason' => 'Provider unavailable', 'assessed_no_show' => false],
            ['service_type' => 'Mental Health Session', 'status' => Appointment::STATUS_REQUESTED, 'start' => $dayAfterTomorrow->copy()->setTime(14, 0), 'end' => $dayAfterTomorrow->copy()->setTime(14, 30), 'cancel_reason' => null, 'assessed_no_show' => false],
        ];

        $appointments = [];
        foreach ($appointmentDefinitions as $idx => $item) {
            $client = $idx % 2 === 0 ? $client1 : $client2;

            $appointment = Appointment::withoutGlobalScopes()->updateOrCreate(
                [
                    'site_id' => 1,
                    'service_type' => $item['service_type'],
                ],
                [
                    'client_id' => $client->id,
                    'provider_id' => $staff1->id,
                    'resource_id' => 1,
                    'start_time' => $item['start'],
                    'end_time' => $item['end'],
                    'status' => $item['status'],
                    'cancel_reason' => $item['cancel_reason'],
                    'assessed_no_show' => $item['assessed_no_show'],
                    'department_id' => 1,
                ],
            );

            $appointments[$idx + 1] = $appointment;

            $snapshot = $appointment->fresh()->toArray();

            if (Schema::hasColumn('appointment_versions', 'reason')) {
                DB::table('appointment_versions')->updateOrInsert(
                    ['appointment_id' => $appointment->id, 'changed_by' => $staff1->id, 'reason' => 'initial'],
                    ['snapshot' => json_encode($snapshot), 'created_at' => $appointment->created_at ?? $now],
                );
            } else {
                AppointmentVersion::query()->updateOrCreate(
                    ['appointment_id' => $appointment->id, 'changed_by' => $staff1->id],
                    ['snapshot' => $snapshot, 'created_at' => $appointment->created_at ?? $now],
                );
            }
        }

        // SECTION 3 - Waitlist Entries
        WaitlistEntry::withoutGlobalScopes()->updateOrCreate(
            ['site_id' => 1, 'client_id' => $client1->id, 'service_type' => 'General Consultation'],
            [
                'status' => 'waiting',
                'preferred_start' => $tomorrow->copy()->setTime(8, 0),
                'preferred_end' => $tomorrow->copy()->setTime(12, 0),
                'priority' => 1,
            ],
        );

        $nextMonday = $today->copy()->next('Monday')->setTime(9, 0);
        WaitlistEntry::withoutGlobalScopes()->updateOrCreate(
            ['site_id' => 1, 'client_id' => $client2->id, 'service_type' => 'Follow-up Visit'],
            [
                'status' => 'waiting',
                'preferred_start' => $nextMonday,
                'preferred_end' => $nextMonday->copy()->addHours(2),
                'priority' => 2,
            ],
        );

        WaitlistEntry::withoutGlobalScopes()->updateOrCreate(
            ['site_id' => 1, 'client_id' => $client3->id, 'service_type' => 'Lab Review'],
            [
                'status' => 'proposed',
                'preferred_start' => $tomorrow->copy()->setTime(9, 0),
                'preferred_end' => $tomorrow->copy()->setTime(9, 30),
                'priority' => 1,
            ],
        );

        // SECTION 4 - Fee Assessments
        $fee1 = FeeAssessment::withoutGlobalScopes()->updateOrCreate(
            [
                'client_id' => $client1->id,
                'fee_type' => 'no_show',
                'appointment_id' => $appointments[5]->id,
            ],
            [
                'amount' => 25.00,
                'status' => 'pending',
                'assessed_at' => $now->copy()->subDays(2),
                'due_date' => $today->copy()->subDay()->toDateString(),
                'waiver_by' => null,
                'waiver_note' => null,
            ],
        );

        $fee2 = FeeAssessment::withoutGlobalScopes()->updateOrCreate(
            [
                'client_id' => $client2->id,
                'fee_type' => 'no_show',
                'appointment_id' => $appointments[7]->id,
            ],
            [
                'amount' => 25.00,
                'status' => 'paid',
                'assessed_at' => $now->copy()->subDays(5),
                'due_date' => $today->copy()->subDays(4)->toDateString(),
                'waiver_by' => null,
                'waiver_note' => null,
            ],
        );

        $fee3 = FeeAssessment::withoutGlobalScopes()->updateOrCreate(
            [
                'client_id' => $client1->id,
                'fee_type' => 'overdue',
                'appointment_id' => null,
                'amount' => 15.00,
            ],
            [
                'status' => 'pending',
                'assessed_at' => $now->copy()->subDays(10),
                'due_date' => $today->copy()->subDays(5)->toDateString(),
                'waiver_by' => null,
                'waiver_note' => null,
            ],
        );

        $fee4 = FeeAssessment::withoutGlobalScopes()->updateOrCreate(
            [
                'client_id' => $client2->id,
                'fee_type' => 'overdue',
                'appointment_id' => null,
                'amount' => 8.50,
            ],
            [
                'status' => 'waived',
                'assessed_at' => $now->copy()->subDays(15),
                'due_date' => $today->copy()->subDays(10)->toDateString(),
                'waiver_by' => $reviewer1->id,
                'waiver_note' => 'First-time waiver approved',
            ],
        );

        $fee5 = FeeAssessment::withoutGlobalScopes()->updateOrCreate(
            [
                'client_id' => $client3->id,
                'fee_type' => 'lost_damaged',
                'appointment_id' => null,
            ],
            [
                'amount' => 50.00,
                'status' => 'pending',
                'assessed_at' => $now->copy()->subDays(3),
                'due_date' => $today->toDateString(),
                'waiver_by' => null,
                'waiver_note' => null,
            ],
        );

        // SECTION 5 - Payments
        $pay1 = Payment::withoutGlobalScopes()->updateOrCreate(
            ['reference_id' => 'PAY-001'],
            [
                'amount' => 25.00,
                'method' => 'cash',
                'fee_assessment_id' => $fee2->id,
                'posted_by' => $staff1->id,
                'site_id' => 1,
                'notes' => 'Demo fee payment',
                'created_at' => $now,
            ],
        );

        $pay2 = Payment::withoutGlobalScopes()->updateOrCreate(
            ['reference_id' => 'PAY-002'],
            [
                'amount' => 100.00,
                'method' => 'check',
                'fee_assessment_id' => null,
                'posted_by' => $staff1->id,
                'site_id' => 1,
                'notes' => 'Demo general payment',
                'created_at' => $now,
            ],
        );

        Payment::withoutGlobalScopes()->updateOrCreate(
            ['reference_id' => 'PAY-003'],
            [
                'amount' => 50.00,
                'method' => 'terminal_batch',
                'fee_assessment_id' => null,
                'posted_by' => $staff1->id,
                'site_id' => 1,
                'notes' => 'Demo terminal batch payment',
                'created_at' => $now,
            ],
        );

        Payment::withoutGlobalScopes()->updateOrCreate(
            ['reference_id' => 'PAY-004'],
            [
                'amount' => 75.00,
                'method' => 'cash',
                'fee_assessment_id' => null,
                'posted_by' => $staff1->id,
                'site_id' => 1,
                'notes' => 'Demo cash payment',
                'created_at' => $now,
            ],
        );

        LedgerEntry::withoutGlobalScopes()->updateOrCreate(
            ['entry_type' => 'payment', 'reference_id' => 'PAY-001'],
            [
                'amount' => 25.00,
                'client_id' => $client2->id,
                'site_id' => 1,
                'description' => 'No-show fee payment',
                'created_at' => $now,
            ],
        );

        LedgerEntry::withoutGlobalScopes()->updateOrCreate(
            ['entry_type' => 'payment', 'reference_id' => 'PAY-002'],
            [
                'amount' => 100.00,
                'client_id' => $client1->id,
                'site_id' => 1,
                'description' => 'General payment',
                'created_at' => $now,
            ],
        );

        // SECTION 6 - Refund Order
        RefundOrder::withoutGlobalScopes()->updateOrCreate(
            ['payment_id' => $pay2->id],
            [
                'client_id' => $client1->id,
                'amount' => 30.00,
                'reason' => 'Overcharge on consultation',
                'status' => 'pending',
                'requested_by' => $staff1->id,
                'approved_by' => null,
                'site_id' => 1,
                'reviewer_note' => null,
            ],
        );

        // SECTION 7 - Soft-deleted Users
        $deletedUser1 = $this->upsertUser('deleted_user1', 'Deleted1@NexusCare1', 'staff');
        if (! $deletedUser1->trashed()) {
            $deletedUser1->delete();
        }

        $deletedUser2 = $this->upsertUser('deleted_user2', 'Deleted2@NexusCare1', 'staff');
        if (! $deletedUser2->trashed()) {
            $deletedUser2->delete();
        }

        // SECTION 8 - Audit Logs
        DB::table('audit_logs')->updateOrInsert(
            ['action' => 'CREATE_USER', 'target_type' => 'User', 'target_id' => $staff3->id],
            ['user_id' => $admin->id, 'payload' => json_encode(['identifier' => $staff3->identifier]), 'ip_address' => '192.168.1.10', 'created_at' => $now],
        );

        DB::table('audit_logs')->updateOrInsert(
            ['action' => 'CREATE_APPT', 'target_type' => 'Appointment', 'target_id' => $appointments[1]->id],
            ['user_id' => $staff1->id, 'payload' => json_encode(['service_type' => $appointments[1]->service_type]), 'ip_address' => '192.168.1.11', 'created_at' => $now],
        );

        DB::table('audit_logs')->updateOrInsert(
            ['action' => 'BAN_USER', 'target_type' => 'User', 'target_id' => $bannedUser->id],
            ['user_id' => $admin->id, 'payload' => json_encode(['identifier' => $bannedUser->identifier]), 'ip_address' => '192.168.1.10', 'created_at' => $now],
        );

        DB::table('audit_logs')->updateOrInsert(
            ['action' => 'WAIVE_FEE', 'target_type' => 'FeeAssessment', 'target_id' => $fee4->id],
            ['user_id' => $reviewer1->id, 'payload' => json_encode(['waiver_note' => $fee4->waiver_note]), 'ip_address' => '192.168.1.12', 'created_at' => $now],
        );

        DB::table('audit_logs')->updateOrInsert(
            ['action' => 'LOGIN', 'target_type' => 'User', 'target_id' => $admin->id],
            ['user_id' => $admin->id, 'payload' => json_encode(['identifier' => $admin->identifier]), 'ip_address' => '192.168.1.10', 'created_at' => $now],
        );

    }

    private function upsertUser(string $identifier, string $password, string $role, array $overrides = []): User
    {
        $defaults = [
            'password_hash' => Hash::make($password, ['rounds' => 12]),
            'role' => $role,
            'site_id' => 1,
            'department_id' => 1,
            'is_banned' => false,
            'muted_until' => null,
            'locked_until' => null,
            'failed_attempts' => 0,
        ];

        return User::withoutGlobalScopes()->updateOrCreate(
            ['identifier' => $identifier],
            array_merge($defaults, $overrides),
        );
    }

    private function assignRole(User $user): void
    {
        $role = Role::query()->where('name', $user->role)->first();

        if (! $role) {
            return;
        }

        UserRole::withoutGlobalScopes()->updateOrCreate(
            [
                'user_id' => $user->id,
                'role_id' => $role->id,
                'site_id' => 1,
            ],
            [
                'created_at' => now(),
            ],
        );
    }
}
