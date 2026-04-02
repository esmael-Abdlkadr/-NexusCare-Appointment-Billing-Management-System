<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('batch_file_path', 500)->nullable()->after('notes');
            $table->unsignedSmallInteger('batch_row_count')->nullable()->after('batch_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['batch_file_path', 'batch_row_count']);
        });
    }
};
