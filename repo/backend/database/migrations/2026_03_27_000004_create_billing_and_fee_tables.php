<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->boolean('assessed_no_show')->default(false)->after('cancel_reason');
        });

        Schema::create('fee_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('fee_type', ['no_show', 'overdue', 'lost_damaged']);
            $table->decimal('amount', 10, 2);
            $table->decimal('rate', 5, 4)->nullable();
            $table->integer('period_days')->nullable();
            $table->integer('grace_minutes')->nullable();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['site_id', 'fee_type', 'is_active']);
        });

        Schema::create('fee_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->enum('fee_type', ['no_show', 'overdue', 'lost_damaged']);
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'paid', 'waived', 'written_off'])->default('pending');
            $table->foreignId('waiver_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('waiver_note')->nullable();
            $table->timestamp('assessed_at');
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
            $table->index(['client_id', 'fee_type']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id', 100)->unique();
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['cash', 'check', 'terminal_batch']);
            $table->foreignId('fee_assessment_id')->nullable()->constrained('fee_assessments')->nullOnDelete();
            $table->foreignId('posted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('refund_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected', 'processed'])->default('pending');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->text('reviewer_note')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
            $table->index(['client_id', 'status']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->enum('entry_type', ['payment', 'fee', 'refund', 'fine', 'waiver', 'writeoff']);
            $table->decimal('amount', 10, 2);
            $table->string('reference_id', 100);
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['site_id', 'created_at']);
            $table->index(['client_id', 'created_at']);
        });

        DB::table('fee_rules')->insert([
            [
                'fee_type' => 'no_show',
                'amount' => 25.00,
                'rate' => null,
                'period_days' => null,
                'grace_minutes' => 10,
                'site_id' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'fee_type' => 'overdue',
                'amount' => 0.00,
                'rate' => 0.015,
                'period_days' => 30,
                'grace_minutes' => null,
                'site_id' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'fee_type' => 'lost_damaged',
                'amount' => 50.00,
                'rate' => null,
                'period_days' => null,
                'grace_minutes' => null,
                'site_id' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('refund_orders');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('fee_assessments');
        Schema::dropIfExists('fee_rules');

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('assessed_no_show');
        });
    }
};
