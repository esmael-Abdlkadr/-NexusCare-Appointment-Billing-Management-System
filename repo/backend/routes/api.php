<?php

use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FeeAssessmentController;
use App\Http\Controllers\Api\FeeRuleController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReconciliationController;
use App\Http\Controllers\Api\RecycleBinController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\RefundOrderController;
use App\Http\Controllers\Api\UserSearchController;
use App\Http\Controllers\Api\WaitlistController;
use App\Http\Controllers\Api\AdminManagementController;
use App\Http\Controllers\Api\AuditLogController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        return response()->json([
            'success' => true,
            'data' => [
                'status' => 'ok',
                'db' => 'connected'
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'data' => [
                'status' => 'error',
                'db' => 'disconnected'
            ]
        ], 500);
    }
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
});

Route::middleware(['app.jwt', 'scope.check', 'check.muted', 'audit.logger'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
    });

    Route::post('/admin/users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])
        ->name('admin.users.reset-password');

    Route::prefix('admin')->group(function () {
        Route::get('/users', [AdminManagementController::class, 'index'])
            ->middleware('role:administrator')
            ->name('admin.users.index');
        Route::post('/users', [AdminManagementController::class, 'store'])
            ->middleware('role:administrator')
            ->name('admin.users.store');
        Route::get('/users/{id}', [AdminManagementController::class, 'show'])
            ->middleware('role:reviewer,administrator')
            ->name('admin.users.show');
        Route::patch('/users/{id}', [AdminManagementController::class, 'update'])
            ->middleware('role:administrator')
            ->name('admin.users.update');
        Route::delete('/users/{id}', [AdminManagementController::class, 'destroy'])
            ->middleware('role:administrator')
            ->name('admin.users.destroy');
        Route::post('/users/bulk', [AdminManagementController::class, 'bulk'])
            ->middleware('role:administrator')
            ->name('admin.users.bulk');
        Route::post('/users/{id}/unlock', [AdminManagementController::class, 'unlock'])
            ->middleware('role:administrator')
            ->name('admin.users.unlock');

        Route::get('/recycle-bin', [RecycleBinController::class, 'index'])
            ->middleware('role:administrator')
            ->name('admin.recycle-bin.index');
        Route::post('/recycle-bin/{type}/{id}/restore', [RecycleBinController::class, 'restore'])
            ->middleware('role:administrator')
            ->name('admin.recycle-bin.restore');
        Route::delete('/recycle-bin/{type}/{id}', [RecycleBinController::class, 'destroy'])
            ->middleware('role:administrator')
            ->name('admin.recycle-bin.destroy');
        Route::post('/recycle-bin/bulk-restore', [RecycleBinController::class, 'bulkRestore'])
            ->middleware('role:administrator')
            ->name('admin.recycle-bin.bulk-restore');
        Route::delete('/recycle-bin/bulk', [RecycleBinController::class, 'bulkDestroy'])
            ->middleware('role:administrator')
            ->name('admin.recycle-bin.bulk-destroy');
    });

    Route::prefix('appointments')->group(function () {
        Route::get('/', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/', [AppointmentController::class, 'store'])
            ->middleware('role:staff,administrator')
            ->name('appointments.store');
        Route::get('/{id}', [AppointmentController::class, 'show'])->name('appointments.show');
        Route::put('/{id}', [AppointmentController::class, 'update'])
            ->middleware('role:staff,administrator')
            ->name('appointments.update');
        Route::patch('/{id}', [AppointmentController::class, 'update'])
            ->middleware('role:staff,administrator')
            ->name('appointments.update.patch');
        Route::patch('/{id}/status', [AppointmentController::class, 'transitionStatus'])
            ->middleware('role:staff,administrator')
            ->name('appointments.transition-status');
        Route::get('/{id}/versions', [AppointmentController::class, 'versions'])
            ->middleware('role:reviewer,administrator')
            ->name('appointments.versions');
    });

    Route::get('/resources', [ResourceController::class, 'index'])->name('resources.index');
    Route::get('/users/search', [UserSearchController::class, 'index'])->name('users.search');

    Route::prefix('waitlist')->group(function () {
        Route::get('/', [WaitlistController::class, 'index'])
            ->middleware('role:staff,administrator')
            ->name('waitlist.index');
        Route::post('/', [WaitlistController::class, 'store'])
            ->middleware('role:staff,administrator')
            ->name('waitlist.store');
        Route::post('/{id}/confirm-backfill', [WaitlistController::class, 'confirmBackfill'])
            ->middleware('role:staff,administrator')
            ->name('waitlist.confirm-backfill');
        Route::delete('/{id}', [WaitlistController::class, 'destroy'])
            ->middleware('role:staff,administrator')
            ->name('waitlist.destroy');
    });

    Route::prefix('fee-rules')->group(function () {
        Route::get('/', [FeeRuleController::class, 'index'])
            ->middleware('role:administrator')
            ->name('fee-rules.index');
        Route::post('/', [FeeRuleController::class, 'store'])
            ->middleware('role:administrator')
            ->name('fee-rules.store');
        Route::delete('/{id}', [FeeRuleController::class, 'destroy'])
            ->middleware('role:administrator')
            ->name('fee-rules.destroy');
    });

    Route::prefix('fee-assessments')->group(function () {
        Route::get('/', [FeeAssessmentController::class, 'index'])
            ->middleware('role:staff,reviewer,administrator')
            ->name('fee-assessments.index');
        Route::post('/', [FeeAssessmentController::class, 'store'])
            ->middleware('role:staff,administrator')
            ->name('fee-assessments.store');
        Route::get('/{id}', [FeeAssessmentController::class, 'show'])
            ->middleware('role:staff,reviewer,administrator')
            ->name('fee-assessments.show');
        Route::post('/{id}/waiver', [FeeAssessmentController::class, 'waiver'])
            ->middleware('role:reviewer,administrator')
            ->name('fee-assessments.waiver');
    });

    Route::prefix('fees')->group(function () {
        Route::get('/', [FeeAssessmentController::class, 'index'])
            ->middleware('role:staff,reviewer,administrator')
            ->name('fees.index');
        Route::post('/{id}/write-off', [FeeAssessmentController::class, 'writeOff'])
            ->middleware('role:reviewer,administrator')
            ->name('fees.write-off');
    });

    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])
            ->middleware('role:staff,reviewer,administrator')
            ->name('payments.index');
        Route::post('/', [PaymentController::class, 'store'])
            ->middleware('role:staff,administrator')
            ->name('payments.store');
    });

    Route::prefix('refund-orders')->group(function () {
        Route::get('/', [RefundOrderController::class, 'index'])
            ->middleware('role:staff,reviewer,administrator')
            ->name('refund-orders.index');
        Route::post('/', [RefundOrderController::class, 'store'])
            ->middleware('role:staff,administrator')
            ->name('refund-orders.store');
        Route::patch('/{id}/approve', [RefundOrderController::class, 'approve'])
            ->middleware('role:reviewer,administrator')
            ->name('refund-orders.approve');
    });

    Route::get('/ledger', [LedgerController::class, 'index'])
        ->middleware('role:administrator')
        ->name('ledger.index');

    Route::prefix('reconciliation')->group(function () {
        Route::post('/import', [ReconciliationController::class, 'import'])
            ->middleware('role:reviewer,administrator')
            ->name('reconciliation.import');
        Route::get('/imports', [ReconciliationController::class, 'imports'])
            ->middleware('role:reviewer,administrator')
            ->name('reconciliation.imports');
        Route::get('/exceptions', [ReconciliationController::class, 'exceptions'])
            ->middleware('role:reviewer,administrator')
            ->name('reconciliation.exceptions');
        Route::patch('/exceptions/{id}/resolve', [ReconciliationController::class, 'resolveException'])
            ->middleware('role:reviewer,administrator')
            ->name('reconciliation.exceptions.resolve');
        Route::get('/anomalies', [ReconciliationController::class, 'anomalies'])
            ->middleware('role:reviewer,administrator')
            ->name('reconciliation.anomalies');
        Route::patch('/anomalies/{id}/acknowledge', [ReconciliationController::class, 'acknowledgeAnomaly'])
            ->middleware('role:reviewer,administrator')
            ->name('reconciliation.anomalies.acknowledge');
    });

    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('role:reviewer,administrator')
        ->name('audit-logs.index');

    Route::prefix('reports')->group(function () {
        Route::get('/appointments', [ReportController::class, 'appointments'])
            ->middleware('role:reviewer,administrator')
            ->name('reports.appointments');
        Route::get('/financial', [ReportController::class, 'financial'])
            ->middleware('role:reviewer,administrator')
            ->name('reports.financial');
        Route::get('/audit', [ReportController::class, 'audit'])
            ->middleware('role:administrator')
            ->name('reports.audit');
    });
});
