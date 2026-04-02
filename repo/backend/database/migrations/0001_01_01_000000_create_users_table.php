<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 100)->unique();
            $table->string('password_hash', 255);
            $table->enum('role', ['staff', 'reviewer', 'administrator']);
            $table->unsignedBigInteger('site_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->boolean('is_banned')->default(false);
            $table->timestamp('muted_until')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->integer('failed_attempts')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 100);
            $table->timestamp('attempted_at');
            $table->string('ip_address', 45)->nullable();
        });

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('token_jti')->unique();
            $table->timestamp('last_active_at');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('users');
    }
};
