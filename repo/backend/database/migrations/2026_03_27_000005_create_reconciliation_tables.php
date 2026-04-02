<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_imports', function (Blueprint $table) {
            $table->id();
            $table->string('filename', 255);
            $table->char('file_hash', 64)->unique();
            $table->foreignId('imported_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->integer('row_count')->default(0);
            $table->integer('matched_count')->default(0);
            $table->integer('discrepancy_count')->default(0);
            $table->decimal('daily_variance', 10, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['site_id', 'created_at']);
        });

        Schema::create('reconciliation_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('settlement_imports')->cascadeOnDelete();
            $table->json('row_data');
            $table->decimal('expected_amount', 10, 2)->nullable();
            $table->decimal('actual_amount', 10, 2)->nullable();
            $table->string('reason', 255);
            $table->enum('status', ['unresolved', 'resolved'])->default('unresolved');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['import_id', 'status']);
            $table->index(['reason', 'status']);
        });

        Schema::create('anomaly_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('settlement_imports')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->decimal('variance_amount', 10, 2);
            $table->enum('status', ['unresolved', 'acknowledged'])->default('unresolved');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomaly_alerts');
        Schema::dropIfExists('reconciliation_exceptions');
        Schema::dropIfExists('settlement_imports');
    }
};
