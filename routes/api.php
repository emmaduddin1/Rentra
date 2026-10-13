<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| SRS 9.3 / NFR-MNT-004 - the versioned REST API the Flutter clients consume.
| Every endpoint delegates to the same actions, policies, and form requests as
| the web portal, so the business rules cannot drift between channels
| (NFR-MNT-001).
|
| Authentication is by Sanctum personal access token. Suspended accounts are
| stopped by EnsureUserIsActive on the api middleware group (FR-AUTH-006).
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    /*
    | Public - the listing feed and authentication.
    | NFR-SEC-006 - login, password reset, and search are rate limited.
    */
    Route::post('login', [V1\AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');

    Route::post('register', [V1\AuthController::class, 'register'])
        ->middleware('throttle:10,1')
        ->name('register');

    Route::post('password/forgot', [V1\AuthController::class, 'forgotPassword'])
        ->middleware('throttle:6,1')
        ->name('password.forgot');

    Route::get('listings', [V1\ListingController::class, 'index'])
        ->middleware('throttle:120,1')
        ->name('listings.index');
    Route::get('listings/{property}', [V1\ListingController::class, 'show'])->name('listings.show');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [V1\AuthController::class, 'logout'])->name('logout');
        Route::get('me', [V1\AuthController::class, 'me'])->name('me');

        // FR-NOT-001/003.
        Route::get('notifications', [V1\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read', [V1\NotificationController::class, 'read'])->name('notifications.read');

        // FR-APP-005 - renters apply; FR-APP-006 - owners respond.
        Route::get('applications', [V1\ApplicationController::class, 'index'])->name('applications.index');
        Route::get('applications/{application}', [V1\ApplicationController::class, 'show'])->name('applications.show');
        Route::post('listings/{property}/applications', [V1\ApplicationController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('applications.store');
        Route::patch('applications/{application}', [V1\ApplicationController::class, 'update'])->name('applications.update');

        Route::get('conversations', [V1\ConversationController::class, 'index'])->name('conversations.index');
        Route::get('conversations/{conversation}', [V1\ConversationController::class, 'show'])->name('conversations.show');
        Route::post('conversations/{conversation}/messages', [V1\ConversationController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('conversations.messages.store');

        // FR-PROP-001 to FR-PROP-005 - owner only.
        Route::middleware('role:owner')->group(function () {
            Route::get('renters', [V1\RenterController::class, 'index'])->name('renters.index');

            Route::get('properties', [V1\PropertyController::class, 'index'])->name('properties.index');
            Route::post('properties', [V1\PropertyController::class, 'store'])->name('properties.store');
            Route::get('properties/{property}', [V1\PropertyController::class, 'show'])->name('properties.show');
            Route::patch('properties/{property}', [V1\PropertyController::class, 'update'])->name('properties.update');

            // FR-MON-006 to FR-MON-009.
            Route::post('monthly-records/{monthlyRecord}/approve', [V1\MonthlyRecordApprovalController::class, 'approve'])
                ->name('monthly-records.approve');
            Route::post('monthly-records/{monthlyRecord}/reject', [V1\MonthlyRecordApprovalController::class, 'reject'])
                ->name('monthly-records.reject');
            Route::post('monthly-records/{monthlyRecord}/reopen', [V1\MonthlyRecordApprovalController::class, 'reopen'])
                ->name('monthly-records.reopen');
        });

        // FR-TEN-001 to FR-TEN-005 - the list is role-aware; writes are owner only.
        Route::get('tenancies', [V1\TenancyController::class, 'index'])->name('tenancies.index');
        Route::get('tenancies/{tenancy}', [V1\TenancyController::class, 'show'])->name('tenancies.show');

        Route::middleware('role:owner')->group(function () {
            Route::post('tenancies', [V1\TenancyController::class, 'store'])->name('tenancies.store');
            Route::patch('tenancies/{tenancy}', [V1\TenancyController::class, 'update'])->name('tenancies.update');
            Route::patch('tenancies/{tenancy}/end', [V1\TenancyController::class, 'end'])->name('tenancies.end');
        });

        // FR-MON-001 to FR-MON-005 - the list is role-aware; writes are renter only.
        Route::get('monthly-records', [V1\MonthlyRecordController::class, 'index'])->name('monthly-records.index');
        Route::get('monthly-records/{monthlyRecord}', [V1\MonthlyRecordController::class, 'show'])->name('monthly-records.show');

        Route::middleware('role:renter')->group(function () {
            Route::post('monthly-records', [V1\MonthlyRecordController::class, 'store'])->name('monthly-records.store');
            Route::patch('monthly-records/{monthlyRecord}', [V1\MonthlyRecordController::class, 'update'])
                ->name('monthly-records.update');
            Route::post('monthly-records/{monthlyRecord}/proofs', [V1\MonthlyRecordController::class, 'storeProof'])
                ->name('monthly-records.proofs.store');
            Route::post('monthly-records/{monthlyRecord}/submit', [V1\MonthlyRecordController::class, 'submit'])
                ->name('monthly-records.submit');
        });

        // FR-MNT-001 to FR-MNT-004.
        Route::get('maintenance-requests', [V1\MaintenanceRequestController::class, 'index'])->name('maintenance-requests.index');
        Route::post('maintenance-requests', [V1\MaintenanceRequestController::class, 'store'])->name('maintenance-requests.store');
        Route::get('maintenance-requests/{maintenance}', [V1\MaintenanceRequestController::class, 'show'])
            ->name('maintenance-requests.show');
        Route::post('maintenance-requests/{maintenance}/comments', [V1\MaintenanceRequestController::class, 'comment'])
            ->name('maintenance-requests.comments.store');
        Route::patch('maintenance-requests/{maintenance}/status', [V1\MaintenanceRequestController::class, 'updateStatus'])
            ->middleware('role:owner')
            ->name('maintenance-requests.status');
    });
});
