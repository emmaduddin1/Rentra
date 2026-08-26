<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\MaintenanceAttachmentDownloadController;
use App\Http\Controllers\MonthlyProofDownloadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Owner;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Renter;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
|
| SRS 5.5 / FR-APP-001 to FR-APP-004 - the public listing feed is open to guests.
| NFR-SEC-006 - the search endpoint is rate limited.
|
*/

Route::get('/', HomeController::class)->name('home');

Route::get('listings', [ListingController::class, 'index'])
    ->middleware('throttle:120,1')
    ->name('listings.index');

Route::get('listings/{property}', [ListingController::class, 'show'])->name('listings.show');

/*
|--------------------------------------------------------------------------
| Guest authentication (SRS 6.1)
|--------------------------------------------------------------------------
|
| NFR-SEC-006 - login and password reset endpoints are rate limited.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

/*
|--------------------------------------------------------------------------
| Authenticated
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // FR-AUTH-004 - route each role to its own portal.
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    /*
    | NFR-SEC-007 - the only routes out of the private disk. Shared by owner and
    | renter; the record and request policies decide who gets through.
    */
    Route::get('monthly-proofs/{proof}/download', MonthlyProofDownloadController::class)
        ->name('monthly-proofs.download');
    Route::get('maintenance-attachments/{attachment}/download', MaintenanceAttachmentDownloadController::class)
        ->name('maintenance-attachments.download');

    // FR-NOT-001/003 - in-app notifications for every role.
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    /*
    | Owner portal (SRS 11.2) - tenancy, approval, maintenance and reporting
    | screens are added in Phases 3 to 6.
    */
    Route::middleware('role:owner')->prefix('owner')->name('owner.')->group(function () {
        Route::get('dashboard', Owner\DashboardController::class)->name('dashboard');

        // FR-PROP-001 to FR-PROP-005.
        Route::resource('properties', Owner\PropertyController::class);

        // BR-009 - archiving is reversible, so the binding has to see trashed rows.
        Route::patch('properties/{property}/restore', [Owner\PropertyController::class, 'restore'])
            ->withTrashed()
            ->name('properties.restore');

        // FR-PROP-004 - publication and occupancy from the list view.
        Route::post('properties/{property}/publish', [Owner\PropertyPublicationController::class, 'publish'])
            ->name('properties.publish');
        Route::delete('properties/{property}/publish', [Owner\PropertyPublicationController::class, 'unpublish'])
            ->name('properties.unpublish');
        Route::patch('properties/{property}/status', [Owner\PropertyPublicationController::class, 'changeStatus'])
            ->name('properties.status');

        // FR-PROP-001 / BR-013 - the floors of a house let floor by floor.
        // A floor is a property, so it is edited, published, photographed and
        // archived through the property routes above.
        Route::get('properties/{property}/floors/create', [Owner\PropertyFloorController::class, 'create'])
            ->name('properties.floors.create');
        Route::post('properties/{property}/floors', [Owner\PropertyFloorController::class, 'store'])
            ->name('properties.floors.store');

        // FR-PROP-003 - gallery management.
        Route::post('properties/{property}/images', [Owner\PropertyImageController::class, 'store'])
            ->name('properties.images.store');
        Route::delete('properties/{property}/images/{image}', [Owner\PropertyImageController::class, 'destroy'])
            ->name('properties.images.destroy');
        Route::patch('properties/{property}/images/{image}/primary', [Owner\PropertyImageController::class, 'makePrimary'])
            ->name('properties.images.primary');

        // FR-PROP-003 - walkthrough videos, kept apart from the gallery.
        Route::post('properties/{property}/videos', [Owner\PropertyVideoController::class, 'store'])
            ->name('properties.videos.store');
        Route::delete('properties/{property}/videos/{video}', [Owner\PropertyVideoController::class, 'destroy'])
            ->name('properties.videos.destroy');

        // FR-TEN-001 to FR-TEN-005.
        Route::resource('tenancies', Owner\TenancyController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::patch('tenancies/{tenancy}/end', [Owner\TenancyController::class, 'end'])->name('tenancies.end');

        // FR-TEN-003.
        Route::get('renters', [Owner\RenterController::class, 'index'])->name('renters.index');
        Route::get('renters/{renter}', [Owner\RenterController::class, 'show'])->name('renters.show');

        // FR-MON-006 to FR-MON-009 - the approval queue.
        Route::get('monthly-records', [Owner\MonthlyRecordController::class, 'index'])->name('monthly-records.index');
        Route::get('monthly-records/{monthlyRecord}', [Owner\MonthlyRecordController::class, 'show'])
            ->name('monthly-records.show');
        Route::post('monthly-records/{monthlyRecord}/approve', [Owner\MonthlyRecordController::class, 'approve'])
            ->name('monthly-records.approve');
        Route::post('monthly-records/{monthlyRecord}/reject', [Owner\MonthlyRecordController::class, 'reject'])
            ->name('monthly-records.reject');
        Route::post('monthly-records/{monthlyRecord}/reopen', [Owner\MonthlyRecordController::class, 'reopen'])
            ->name('monthly-records.reopen');

        // FR-MNT-003/004.
        Route::get('maintenance', [Owner\MaintenanceRequestController::class, 'index'])->name('maintenance.index');
        Route::get('maintenance/{maintenance}', [Owner\MaintenanceRequestController::class, 'show'])->name('maintenance.show');
        Route::patch('maintenance/{maintenance}/status', [Owner\MaintenanceRequestController::class, 'updateStatus'])
            ->name('maintenance.status');
        Route::post('maintenance/{maintenance}/comments', [Owner\MaintenanceRequestController::class, 'comment'])
            ->name('maintenance.comments.store');

        // FR-RPT-001/003 and FR-ADM-004 - reporting, charts, and export.
        Route::get('reports', [Owner\ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [Owner\ReportController::class, 'export'])->name('reports.export');

        // SRS 13 - the audited trail for this owner's portfolio.
        Route::get('audit-logs', [Owner\AuditLogController::class, 'index'])->name('audit-logs.index');

        // FR-APP-006.
        Route::get('applications', [Owner\ApplicationController::class, 'index'])->name('applications.index');
        Route::get('applications/{application}', [Owner\ApplicationController::class, 'show'])->name('applications.show');
        Route::patch('applications/{application}', [Owner\ApplicationController::class, 'update'])->name('applications.update');
    });

    /*
    | Renter portal (SRS 11.3).
    */
    Route::middleware('role:renter')->prefix('renter')->name('renter.')->group(function () {
        Route::get('dashboard', Renter\DashboardController::class)->name('dashboard');

        // FR-APP-005 - the renter applies for a listing; NFR-SEC-006 rate
        // limits the contact/application endpoint.
        Route::get('applications', [Renter\ApplicationController::class, 'index'])->name('applications.index');
        Route::post('listings/{property}/applications', [Renter\ApplicationController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('applications.store');
        Route::delete('applications/{application}', [Renter\ApplicationController::class, 'destroy'])
            ->name('applications.destroy');

        // FR-APP-007 - saved listings.
        Route::get('favorites', [Renter\FavoriteController::class, 'index'])->name('favorites.index');
        Route::post('favorites/{property}', [Renter\FavoriteController::class, 'toggle'])
            ->middleware('throttle:60,1')
            ->name('favorites.toggle');

        // SRS 11.3 "My property" - current and historical tenancies.
        Route::get('tenancies', [Renter\TenancyController::class, 'index'])->name('tenancies.index');
        Route::get('tenancies/{tenancy}', [Renter\TenancyController::class, 'show'])->name('tenancies.show');

        // FR-MON-001 to FR-MON-005 - monthly submission.
        Route::get('monthly-records', [Renter\MonthlyRecordController::class, 'index'])->name('monthly-records.index');
        Route::get('monthly-records/create', [Renter\MonthlyRecordController::class, 'create'])->name('monthly-records.create');
        Route::post('monthly-records', [Renter\MonthlyRecordController::class, 'store'])->name('monthly-records.store');
        Route::get('monthly-records/{monthlyRecord}', [Renter\MonthlyRecordController::class, 'show'])->name('monthly-records.show');
        Route::get('monthly-records/{monthlyRecord}/edit', [Renter\MonthlyRecordController::class, 'edit'])->name('monthly-records.edit');
        Route::patch('monthly-records/{monthlyRecord}', [Renter\MonthlyRecordController::class, 'update'])->name('monthly-records.update');
        Route::post('monthly-records/{monthlyRecord}/submit', [Renter\MonthlyRecordController::class, 'submit'])->name('monthly-records.submit');

        // FR-MON-003 - proof attachments.
        Route::post('monthly-records/{monthlyRecord}/proofs', [Renter\MonthlyProofController::class, 'store'])
            ->name('monthly-records.proofs.store');
        Route::delete('monthly-records/{monthlyRecord}/proofs/{proof}', [Renter\MonthlyProofController::class, 'destroy'])
            ->name('monthly-records.proofs.destroy');

        // FR-MNT-001/002/004.
        Route::get('maintenance', [Renter\MaintenanceRequestController::class, 'index'])->name('maintenance.index');
        Route::get('maintenance/create', [Renter\MaintenanceRequestController::class, 'create'])->name('maintenance.create');
        Route::post('maintenance', [Renter\MaintenanceRequestController::class, 'store'])->name('maintenance.store');
        Route::get('maintenance/{maintenance}', [Renter\MaintenanceRequestController::class, 'show'])->name('maintenance.show');
        Route::post('maintenance/{maintenance}/comments', [Renter\MaintenanceRequestController::class, 'comment'])
            ->name('maintenance.comments.store');
    });
});
