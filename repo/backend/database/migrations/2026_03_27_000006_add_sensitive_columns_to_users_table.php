<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email', 255)->nullable()->after('identifier');
            $table->text('government_id_encrypted')->nullable()->after('password_hash');
            $table->text('phone_encrypted')->nullable()->after('government_id_encrypted');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email', 'government_id_encrypted', 'phone_encrypted']);
        });
    }
};
