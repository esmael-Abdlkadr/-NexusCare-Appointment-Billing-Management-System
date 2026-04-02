<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waitlist', function (Blueprint $table): void {
            $table->foreignId('department_id')
                ->nullable()
                ->after('site_id')
                ->constrained('departments')
                ->nullOnDelete();

            $table->index(['site_id', 'department_id', 'status'], 'waitlist_site_department_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('waitlist', function (Blueprint $table): void {
            $table->dropIndex('waitlist_site_department_status_index');
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
