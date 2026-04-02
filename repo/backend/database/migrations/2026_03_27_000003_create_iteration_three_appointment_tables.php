<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('display_name', 100);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'role_id', 'site_id']);
        });

        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->enum('type', ['room', 'device', 'vehicle']);
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->string('service_type', 100);
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->enum('status', ['requested', 'confirmed', 'checked_in', 'no_show', 'completed', 'cancelled'])->default('requested');
            $table->text('cancel_reason')->nullable();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['site_id', 'provider_id', 'start_time']);
            $table->index(['site_id', 'resource_id', 'start_time']);
            $table->index(['status', 'start_time']);
        });

        Schema::create('appointment_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->json('snapshot');
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('waitlist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('service_type', 100);
            $table->integer('priority')->default(100);
            $table->dateTime('preferred_start');
            $table->dateTime('preferred_end');
            $table->enum('status', ['waiting', 'proposed', 'booked', 'expired'])->default('waiting');
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['site_id', 'status', 'priority']);
        });

        DB::table('organizations')->insert([
            'id' => 1,
            'name' => 'NexusCare Organization',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sites')->insert([
            'id' => 1,
            'organization_id' => 1,
            'name' => 'Main Site',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('departments')->insert([
            'id' => 1,
            'site_id' => 1,
            'name' => 'General Services',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('roles')->insert([
            ['name' => 'staff', 'display_name' => 'Staff', 'created_at' => now()],
            ['name' => 'reviewer', 'display_name' => 'Reviewer', 'created_at' => now()],
            ['name' => 'administrator', 'display_name' => 'Administrator', 'created_at' => now()],
        ]);

        DB::table('permissions')->insert([
            ['name' => 'appointment.create', 'description' => 'Create appointment', 'created_at' => now()],
            ['name' => 'appointment.update', 'description' => 'Update appointment', 'created_at' => now()],
            ['name' => 'appointment.view_versions', 'description' => 'View appointment versions', 'created_at' => now()],
            ['name' => 'waitlist.manage', 'description' => 'Manage waitlist and backfill', 'created_at' => now()],
            ['name' => 'user.manage', 'description' => 'Manage users', 'created_at' => now()],
        ]);

        $roles = DB::table('roles')->pluck('id', 'name');
        $permissions = DB::table('permissions')->pluck('id', 'name');

        DB::table('role_permissions')->insert([
            ['role_id' => $roles['staff'], 'permission_id' => $permissions['appointment.create']],
            ['role_id' => $roles['staff'], 'permission_id' => $permissions['appointment.update']],
            ['role_id' => $roles['staff'], 'permission_id' => $permissions['waitlist.manage']],
            ['role_id' => $roles['reviewer'], 'permission_id' => $permissions['appointment.view_versions']],
            ['role_id' => $roles['administrator'], 'permission_id' => $permissions['appointment.create']],
            ['role_id' => $roles['administrator'], 'permission_id' => $permissions['appointment.update']],
            ['role_id' => $roles['administrator'], 'permission_id' => $permissions['appointment.view_versions']],
            ['role_id' => $roles['administrator'], 'permission_id' => $permissions['waitlist.manage']],
            ['role_id' => $roles['administrator'], 'permission_id' => $permissions['user.manage']],
        ]);

        $users = DB::table('users')->select('id', 'role', 'site_id')->get();

        foreach ($users as $user) {
            $roleId = $roles[$user->role] ?? null;

            if (! $roleId) {
                continue;
            }

            DB::table('user_roles')->insert([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'site_id' => $user->site_id,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist');
        Schema::dropIfExists('appointment_versions');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('organizations');
    }
};
