<?php

// Controller Imports
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityTypeController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ArchivedAccountController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CampaignAdminController;
use App\Http\Controllers\CampaignWizardController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\DormantUserController;
use App\Http\Controllers\IdCardController;
use App\Http\Controllers\IdSignatureController;
use App\Http\Controllers\LifecycleController;
use App\Http\Controllers\LogController;
// use App\Http\Controllers\Messaging\ComposerController;
use App\Http\Controllers\MembershipFeeController;
use App\Http\Controllers\MembershipPaymentController;
use App\Http\Controllers\MessagingCampaignController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RedCrossUnitController;
use App\Http\Controllers\Reports\AdminActivityReportController;
use App\Http\Controllers\Reports\BranchReportController;
use App\Http\Controllers\Reports\DashboardController;
use App\Http\Controllers\Reports\DatabaseAccessReportController;
use App\Http\Controllers\Reports\DonationReportController;
use App\Http\Controllers\Reports\FinancialReportController;
use App\Http\Controllers\Reports\LifecycleReportController;
use App\Http\Controllers\Reports\MemberReportController;
use App\Http\Controllers\Reports\RCUnitReportController;
use App\Http\Controllers\Reports\RegistrationReportController;
use App\Http\Controllers\Reports\TrainingReportController;
use App\Http\Controllers\Reports\VolunteerReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\SignatureTitleController;
use App\Http\Controllers\TaskForceController;
use App\Http\Controllers\TaskForceTypeController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\TrainingTypeController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PolicyAcceptanceController;
use App\Http\Controllers\WelcomeController;
// Laravel Core Imports
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
| Routes accessible to all users, including guests. This section includes
| general informational pages, authentication, and email verification.
*/

// Welcome and informational pages
Route::get('/', [WelcomeController::class, 'index'])->name('welcome');
Route::get('/volunteer-journey', function () {
    return view('pages.volunteer-journey');
})->name('volunteer.journey');
Route::get('/membership-journey', function () {
    return view('pages.membership-journey');
})->name('membership.journey');
Route::get('/corporate-membership', function () {
    return view('pages.corporate-journey', [
        'membershipFees' => \App\Models\MembershipFee::where('for_organizations', 1)->where('is_active', 1)->orderBy('amount')->get(),
        'branch' => auth()->check() ? auth()->user()->branch : null,
        'isAuthenticated' => auth()->check(),
    ]);
})->name('corporate.journey');

// Archived account notice (public — no auth)
Route::get('/account-deactivated', [ArchivedAccountController::class, 'show'])->name('archived-account.show');

// Authentication Routes
Route::group([], function () {
    // Login
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');

    // Registration
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
    Route::get('/registration/success', function () {
        return view('auth.registration-success');
    })->name('registration.success');

    // Password Reset
    Route::get('forgot-password', [NewPasswordController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [NewPasswordController::class, 'store'])->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'edit'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'update'])->name('password.update');
});

// Password confirmation (re-auth gate for sensitive areas, e.g. Settings)
Route::middleware('auth')->group(function () {
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])->name('password.confirm.store');
});

// Email Verification Routes
Route::group([], function () {
    // Email verification notice
    Route::get('/email/verify', function () {
        return view('auth.verify');
    })->name('verification.notice');

    // Email verification handler
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login')->with('error', 'Please log in first');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('welcome')->with('success', 'Email already verified!');
        }

        try {
            $request->fulfill();

            return redirect()->route('welcome')->with('success', 'Email verified successfully!');
        } catch (\Exception $e) {
            Log::error('Email verification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('welcome')->with('error', 'Verification failed. Please try again.');
        }
    })->name('verification.verify');

    // Resend verification email
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('message', 'Verification link sent!');
    })->name('verification.resend');
});

// Public AJAX routes for registration form
// Used for cascading dropdowns in public forms like registration.
Route::get('/register/divisions/by-branch', [RegisterController::class, 'getDivisions'])
    ->name('register.divisions.by-branch'); // Changed URI and name

// Add this single route for Red Cross Units by division
Route::get('/red-cross-units/by-division', [RedCrossUnitController::class, 'getRedCrossUnitsByDivision'])
    ->name('red-cross-units.by-division');

// Public ID card verification
Route::get('/idcheck/{token}', [IdCardController::class, 'verifyId'])->name('id.verify');

// Public unsubscribe / communication preference routes
Route::get('/u/{token}/email', [UnsubscribeController::class, 'showEmail'])->name('unsubscribe.email.show');
Route::post('/u/{token}/email', [UnsubscribeController::class, 'handleEmail'])->name('unsubscribe.email.handle');
Route::get('/u/{token}/sms', [UnsubscribeController::class, 'showSms'])->name('unsubscribe.sms.show');
Route::post('/u/{token}/sms', [UnsubscribeController::class, 'handleSms'])->name('unsubscribe.sms.handle');

/*
|--------------------------------------------------------------------------
| Authenticated User Routes
|--------------------------------------------------------------------------
| These routes require the user to be logged in.
*/
Route::middleware('auth')->group(function () {
    // Logout Route
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Data handling policy acknowledgement (one-time, for staff/admins)
    Route::get('/policy/accept', [PolicyAcceptanceController::class, 'show'])->name('policy.accept');
    Route::post('/policy/accept', [PolicyAcceptanceController::class, 'store'])->name('policy.accept.store');

    // Protected photo/signature streaming — serves files from storage/app/
    Route::get('/photos/{user}/{type}', [PhotoController::class, 'show'])
        ->name('photos.show')
        ->whereIn('type', ['profile', 'passport', 'signature']);

    // --- SHARED AJAX ROUTES (OVERRIDE PUBLIC) ---
    // These routes override the public definitions for authenticated users,
    // ensuring the correct controllers are used within the admin panel.
    Route::get('/divisions/by-branch', [UserController::class, 'getDivisionsByBranch']);
    Route::get('/red-cross-units/by-division', [UserController::class, 'getRedCrossUnitsByDivision']);
    Route::get('/task-forces/by-branch', [UserController::class, 'getTaskForcesByBranch']);

    /*
    |--------------------------------------------------------------------------
    | Lifecycle Routes (Engagement/Dormancy)
    |--------------------------------------------------------------------------
    */
    Route::prefix('lifecycle')
        ->name('lifecycle.')
        ->group(function () {

            Route::get('/awaiting-engagement', [LifecycleController::class, 'awaitingEngagement'])
                ->name('awaiting_engagement');

            Route::get('/active', [LifecycleController::class, 'active'])
                ->name('active');

            Route::get('/dormant', [LifecycleController::class, 'dormant'])
                ->name('dormant');

            Route::get('/archived', [LifecycleController::class, 'archived'])
                ->name('archived');
        });

    // Profile Management
    Route::prefix('profile')->name('profile.')
        ->middleware(\App\Http\Middleware\RedirectSuperAdmins::class)
        ->group(function () {
            Route::get('/', [ProfileController::class, 'show'])->name('show');
            Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
            Route::get('/edit-photo', [ProfileController::class, 'editPhoto'])->name('edit-photo');
            Route::get('/edit-signature', [ProfileController::class, 'editSignature'])->name('edit-signature');
            Route::put('/', [ProfileController::class, 'update'])->name('update');
            Route::post('/update-profile-picture', [ProfileController::class, 'updateProfilePicture'])->name('update-profile-picture');
            Route::post('/update-signature', [ProfileController::class, 'updateSignature'])->name('update-signature');
            Route::get('/branches/{branch}/divisions', [ProfileController::class, 'getDivisionsByBranch'])->name('branches.divisions');
            Route::get('/organisation/{organisation}', [ProfileController::class, 'organisationProfile'])->name('organisation');
            Route::post('/communication-preferences', [ProfileController::class, 'updateCommunicationPreferences'])->name('communication.update');
        });

    // User-specific Red Cross Unit and Task Force views
    Route::get('/my-unit', [RedCrossUnitController::class, 'myUnit'])->name('red-cross-units.my-unit');
    Route::get('my-unit/report', [RedCrossUnitController::class, 'myUnitReport'])->name('red-cross-units.my-unit-report');
    Route::get('/my-unit/tables', [RedCrossUnitController::class, 'myUnitTables'])->name('red-cross-units.my-unit-tables');
    Route::get('/my-unit/comparison', [RedCrossUnitController::class, 'myUnitComparison'])->name('red-cross-units.my-unit-comparison');
    Route::get('/my-task-force/{taskForce}', [TaskForceController::class, 'myShow'])->name('my-task-force.show');

    /*
    |--------------------------------------------------------------------------
    | Admin & Resource Management Routes
    |--------------------------------------------------------------------------
    | Routes for managing various resources and administrative functions.
    | These routes typically require administrative privileges.
    | Replace 'can:manage-admin-panel' with your actual admin middleware.
    */
    Route::group(['middleware' => ['can:manage-admin-panel']], function () {
        Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');

        // Logs (Audit Trail)
        Route::get('/logs', [LogController::class, 'index'])
            ->name('logs.index')
            ->middleware('can:view_log');

        // User Management
        Route::prefix('users')->name('users.')->group(function () {
            // User Roles and Permissions Management
            // MUST be defined before routes with a {user} parameter
            Route::group(['middleware' => ['can:manage_roles_and_permissions', 'password.confirm']], function () {
                Route::get('/roles', [UserController::class, 'editRoles'])->name('roles.edit');
                Route::post('/roles', [UserController::class, 'updateRoles'])->name('roles.update');
                Route::get('/search-for-roles', [UserController::class, 'searchUsersForRoles'])->name('search-for-roles');
            });

            // JSON search — used for live-search inputs across the app
            Route::get('/search', [UserController::class, 'search'])->name('search');

            // View Users (Index)
            Route::middleware('can:view_user')->group(function () {
                Route::get('/', [UserController::class, 'index'])->name('index');
            });

            // Create User
            Route::middleware('can:add_user')->group(function () {
                Route::get('/create', [UserController::class, 'create'])->name('create');
                Route::post('/', [UserController::class, 'store'])->name('store');
            });

            // Routes with {user} parameter (must come after specific routes like /roles)
            Route::middleware('can:view_user')->group(function () {
                Route::get('/{user}', [UserController::class, 'show'])->name('show');
            });

            Route::middleware('can:edit_user')->group(function () {
                Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
                Route::put('/{user}', [UserController::class, 'update'])->name('update');
                Route::post('/{user}/profile-picture', [UserController::class, 'updateProfilePicture'])->name('update-profile-picture');
                Route::post('/{user}/update-signature', [UserController::class, 'updateSignature'])->name('update-signature');
            });

            Route::middleware('can:remove_user')->group(function () {
                Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
            });
        });

        // Branch Management
        Route::prefix('branches')->name('branches.')->group(function () {
            Route::group(['middleware' => ['can:view_branch_information']], function () {
                Route::get('/', [BranchController::class, 'index'])->name('index');
                Route::get('/{branch}', [BranchController::class, 'show'])->name('show');
                Route::get('/{branch}/statistics', [BranchController::class, 'getStatistics'])->name('statistics');
                Route::get('/api/list', [BranchController::class, 'getBranchesApi'])->name('api'); // API for branches
            });

            Route::group(['middleware' => ['can:edit_branch_information']], function () {
                Route::get('/{branch}/edit', [BranchController::class, 'edit'])->name('edit');
                Route::put('/{branch}', [BranchController::class, 'update'])->name('update');
            });
        });

        // Division Management
        Route::prefix('divisions')->name('divisions.')->group(function () {
            Route::group(['middleware' => ['can:view_division_information']], function () {
                Route::get('/', [DivisionController::class, 'index'])->name('index');
                Route::get('/{division}', [DivisionController::class, 'show'])->name('show');
            });

            Route::group(['middleware' => ['can:edit_division_information']], function () {
                Route::get('/{division}/edit', [DivisionController::class, 'edit'])->name('edit');
                Route::put('/{division}', [DivisionController::class, 'update'])->name('update');
            });
        });

        Route::middleware(['can:view_idcards'])->group(function () {
            Route::get('/id-cards/prepare-bulk-print', [IdCardController::class, 'showBulkPrintForm'])
                ->name('id-cards.prepare-bulk-print');
            Route::get('/id-cards/prints-report', [IdCardController::class, 'showIdCardPrintsReport'])->name('id-cards.prints-report');
        });

        Route::middleware(['can:print_idcards'])->group(function () {
            Route::get('/id-card/print/{user}', [IdCardController::class, 'printCard'])->name('id-card.print');
            Route::post('/id-cards/print-bulk', [IdCardController::class, 'printBulkCards'])->name('id-cards.print-bulk');
            Route::post('/id-cards/record-bulk-prints', [IdCardController::class, 'recordBulkIdCardPrints'])->name('id-cards.record-bulk-prints');
        });

        Route::delete('/id-cards/bulk-delete-prints', [IdCardController::class, 'bulkDeletePrints'])
            ->name('id-cards.bulk-delete-prints')
            ->middleware(['auth', 'can:print_idcards']); // Add middleware for authentication and permission

        Route::middleware(['can:view_certificates'])->group(function () {
            Route::get('/certificates', [CertificateController::class, 'index'])
                ->name('certificates.index');
            Route::get('/certificates/prints-report', [CertificateController::class, 'showCertificatePrintsReport'])
                ->name('certificates.prints-report');
        });

        Route::middleware(['can:print_certificates'])->group(function () {
            // Bulk print – PRE-PRINTED PAPER (no logo / frame, coordinate-based)
            Route::post('/certificates/bulk/print/plain', [CertificateController::class, 'bulkPrintPlain'])
                ->name('certificates.bulk.print.plain');

            // Bulk print – BRANDED (logo + frame, normal layout)
            Route::post('/certificates/bulk/print/branded', [CertificateController::class, 'bulkPrintBranded'])
                ->name('certificates.bulk.print.branded');

            Route::post('/certificates/bulk-print/branded-portrait', [CertificateController::class, 'bulkPrintBrandedPortrait'])
                ->name('certificates.bulk_print_branded_portrait');

            // Bulk delete of print records (soft delete)
            Route::delete('/certificates/prints-report/bulk-delete', [CertificateController::class, 'bulkDeletePrints'])
                ->name('certificates.bulk-delete-prints');

            Route::post('/certificates/mark-as-printed', [App\Http\Controllers\CertificateController::class, 'markAsPrinted'])->name('certificates.mark-as-printed');

            Route::get('/certificates/verify', [CertificateController::class, 'verify'])
                ->name('certificates.verify')
                ->middleware('signed');
        });

        // Membership Fees Management (settings area — requires recent password confirmation)
        Route::resource('membership-fees', MembershipFeeController::class)->middleware('password.confirm');

        // In-app notifications (bell dropdown)
        Route::get('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

        // Membership Payments Management
        Route::prefix('membership-payments')->name('membership-payments.')->group(function () {
            // AJAX and utility routes used in forms and filters
            Route::get('/search-users', [MembershipPaymentController::class, 'searchUsers'])->name('search-users')->middleware('can:add_payments');
            Route::get('/user/{user}/current-membership', [MembershipPaymentController::class, 'getCurrentMembership'])->name('current-membership');
            Route::get('/divisions/by-branch', [MembershipPaymentController::class, 'getDivisionsByBranch'])->name('divisions.by-branch')->middleware('can:view_payments');

            // Resourceful routes with permissions
            Route::get('/', [MembershipPaymentController::class, 'index'])->name('index')->middleware('can:view_payments');
            // Approvals tab (must precede /{membership_payment}).
            Route::get('/approvals', [MembershipPaymentController::class, 'approvals'])->name('approvals')->middleware('can:approve_payments');
            Route::post('/approvals/bulk-approve', [MembershipPaymentController::class, 'bulkApprove'])->name('bulk-approve')->middleware('can:approve_payments');

            // This route now handles both creating a payment from scratch and with a pre-selected user
            Route::get('/create/{user?}', [MembershipPaymentController::class, 'create'])->name('create')->middleware('can:add_payments');

            Route::post('/', [MembershipPaymentController::class, 'store'])->name('store')->middleware('can:add_payments');
            Route::get('/{membership_payment}', [MembershipPaymentController::class, 'show'])->name('show')->middleware('can:view_payments');
            Route::get('/{membership_payment}/edit', [MembershipPaymentController::class, 'edit'])->name('edit')->middleware('can:edit_payments');
            Route::put('/{membership_payment}', [MembershipPaymentController::class, 'update'])->name('update')->middleware('can:edit_payments');
            Route::delete('/{membership_payment}', [MembershipPaymentController::class, 'destroy'])->name('destroy')->middleware('can:remove_payments');

            // Approval workflow (Phase 2). Approve/reject gated by approve permission;
            // withdraw is submitter-only and checked inside the controller.
            Route::get('/{id}/review', [MembershipPaymentController::class, 'review'])->name('review');
            Route::post('/{id}/approve', [MembershipPaymentController::class, 'approve'])->name('approve')->middleware('can:approve_payments');
            Route::post('/{id}/reject', [MembershipPaymentController::class, 'reject'])->name('reject')->middleware('can:approve_payments');
            Route::post('/{id}/withdraw', [MembershipPaymentController::class, 'withdraw'])->name('withdraw');
        });

        // Activity Management
        Route::prefix('activities')->name('activities.')->group(function () {
            // AJAX and utility routes
            Route::get('/search-users', [ActivityController::class, 'searchUsers'])->name('search-users')->middleware('can:add_volunteering');
            Route::get('/divisions-by-branch', [ActivityController::class, 'getDivisionsByBranch'])->name('divisions-by-branch')->middleware('can:view_volunteering');

            // Resourceful routes with permissions
            Route::get('/', [ActivityController::class, 'index'])->name('index')->middleware('can:view_volunteering');
            // Approvals tab (must precede /{activity}).
            Route::get('/approvals', [ActivityController::class, 'approvals'])->name('approvals')->middleware('can:approve_volunteering');
            Route::post('/approvals/bulk-approve', [ActivityController::class, 'bulkApprove'])->name('bulk-approve')->middleware('can:approve_volunteering');
            Route::get('/create/{user?}', [ActivityController::class, 'create'])->name('create')->middleware('can:add_volunteering');
            Route::post('/', [ActivityController::class, 'store'])->name('store')->middleware('can:add_volunteering');
            Route::get('/{activity}', [ActivityController::class, 'show'])->name('show')->middleware('can:view_volunteering');
            Route::get('/{activity}/edit', [ActivityController::class, 'edit'])->name('edit')->middleware('can:edit_volunteering');
            Route::put('/{activity}', [ActivityController::class, 'update'])->name('update')->middleware('can:edit_volunteering');
            Route::delete('/{activity}', [ActivityController::class, 'destroy'])->name('destroy')->middleware('can:remove_volunteering');

            // Approval workflow (Phase 2).
            Route::get('/{id}/review', [ActivityController::class, 'review'])->name('review');
            Route::post('/{id}/approve', [ActivityController::class, 'approve'])->name('approve')->middleware('can:approve_volunteering');
            Route::post('/{id}/reject', [ActivityController::class, 'reject'])->name('reject')->middleware('can:approve_volunteering');
            Route::post('/{id}/withdraw', [ActivityController::class, 'withdraw'])->name('withdraw');

        });

        // Activity Type Management
        Route::resource('activity-types', ActivityTypeController::class);

        // Training Management
        Route::prefix('trainings')->name('trainings.')->group(function () {
            // AJAX and utility routes
            Route::get('/search-users', [TrainingController::class, 'searchUsers'])->name('search-users')->middleware('can:add_trainings');

            // Resourceful routes with permissions
            Route::get('/', [TrainingController::class, 'index'])->name('index')->middleware('can:view_trainings');
            // Approvals tab (must precede /{training}).
            Route::get('/approvals', [TrainingController::class, 'approvals'])->name('approvals')->middleware('can:approve_training');
            Route::post('/approvals/bulk-approve', [TrainingController::class, 'bulkApprove'])->name('bulk-approve')->middleware('can:approve_training');
            Route::get('/create/{user?}', [TrainingController::class, 'create'])->name('create')->middleware('can:add_trainings');
            Route::post('/', [TrainingController::class, 'store'])->name('store')->middleware('can:add_trainings');
            Route::get('/{training}', [TrainingController::class, 'show'])->name('show')->middleware('can:view_trainings');
            Route::get('/{training}/edit', [TrainingController::class, 'edit'])->name('edit')->middleware('can:edit_trainings');
            Route::put('/{training}', [TrainingController::class, 'update'])->name('update')->middleware('can:edit_trainings');
            Route::delete('/{training}', [TrainingController::class, 'destroy'])->name('destroy')->middleware('can:remove_trainings');

            // Approval workflow (Phase 2).
            Route::get('/{id}/review', [TrainingController::class, 'review'])->name('review');
            Route::post('/{id}/approve', [TrainingController::class, 'approve'])->name('approve')->middleware('can:approve_training');
            Route::post('/{id}/reject', [TrainingController::class, 'reject'])->name('reject')->middleware('can:approve_training');
            Route::post('/{id}/withdraw', [TrainingController::class, 'withdraw'])->name('withdraw');
        });

        // Training Types (settings area — requires recent password confirmation)
        Route::resource('training-types', TrainingTypeController::class)->only([
            'index', 'create', 'store', 'edit', 'update', 'destroy', // Ensure 'edit' is in this list
        ])->middleware('password.confirm');

        // Donation Management
        Route::prefix('donations')->name('donations.')->group(function () {
            // AJAX and utility routes
            Route::get('/search-users', [DonationController::class, 'searchUsers'])->name('search-users')->middleware('can:add_donations');

            // Resourceful routes with permissions
            Route::get('/', [DonationController::class, 'index'])->name('index')->middleware('can:view_donations');
            // Approvals tab (must precede /{donation}); review page resolves any status.
            Route::get('/approvals', [DonationController::class, 'approvals'])->name('approvals')->middleware('can:approve_donations');
            Route::post('/approvals/bulk-approve', [DonationController::class, 'bulkApprove'])->name('bulk-approve')->middleware('can:approve_donations');
            Route::get('/create/{user?}', [DonationController::class, 'create'])->name('create')->middleware('can:add_donations');
            Route::post('/', [DonationController::class, 'store'])->name('store')->middleware('can:add_donations');
            Route::get('/{donation}', [DonationController::class, 'show'])->name('show')->middleware('can:view_donations');
            Route::get('/{donation}/edit', [DonationController::class, 'edit'])->name('edit')->middleware('can:edit_donations');
            Route::put('/{donation}', [DonationController::class, 'update'])->name('update')->middleware('can:edit_donations');
            Route::delete('/{donation}', [DonationController::class, 'destroy'])->name('destroy')->middleware('can:remove_donations');

            // Approval workflow (Phase 2/3).
            Route::get('/{id}/review', [DonationController::class, 'review'])->name('review');
            Route::post('/{id}/approve', [DonationController::class, 'approve'])->name('approve')->middleware('can:approve_donations');
            Route::post('/{id}/reject', [DonationController::class, 'reject'])->name('reject')->middleware('can:approve_donations');
            Route::post('/{id}/withdraw', [DonationController::class, 'withdraw'])->name('withdraw');
        });

        // Red Cross Unit Management
        Route::prefix('red-cross-units')->name('red-cross-units.')->group(function () {
            // Static routes (index, create, store) must come before dynamic routes
            Route::get('/', [RedCrossUnitController::class, 'index'])->name('index')->middleware('can:view_red_cross_unit');
            Route::get('/create', [RedCrossUnitController::class, 'create'])->name('create')->middleware('can:add_red_cross_unit');
            Route::post('/', [RedCrossUnitController::class, 'store'])->name('store')->middleware('can:add_red_cross_unit');

            // Dynamic routes (show, edit, update, destroy)
            Route::get('/{red_cross_unit}', [RedCrossUnitController::class, 'show'])->name('show')->middleware('can:view_red_cross_unit');
            Route::get('/{red_cross_unit}/edit', [RedCrossUnitController::class, 'edit'])->name('edit')->middleware('can:edit_red_cross_unit');
            Route::put('/{red_cross_unit}', [RedCrossUnitController::class, 'update'])->name('update')->middleware('can:edit_red_cross_unit');
            Route::put('/{red_cross_unit}/reactivate', [RedCrossUnitController::class, 'reactivate'])->name('reactivate')->middleware('can:edit_red_cross_unit');
            Route::delete('/{red_cross_unit}', [RedCrossUnitController::class, 'destroy'])->name('destroy')->middleware('can:remove_red_cross_unit');
        });

        // Task Force Management
        Route::prefix('task-forces')->name('task-forces.')->group(function () {
            // Resource routes (index, create, store)
            Route::get('/', [TaskForceController::class, 'index'])->name('index')->middleware('can:view_task_force');
            Route::get('/create', [TaskForceController::class, 'create'])->name('create')->middleware('can:add_task_force');
            Route::post('/', [TaskForceController::class, 'store'])->name('store')->middleware('can:add_task_force');

            // Member and specific task force routes
            Route::get('/{task_force}/members/search', [TaskForceController::class, 'searchUsers'])->name('search-users')->middleware('can:edit_task_force');
            Route::post('/{task_force}/add-member', [TaskForceController::class, 'addMember'])->name('add-member')->middleware('can:edit_task_force');
            Route::post('/{task_force}/remove-member', [TaskForceController::class, 'removeMember'])->name('remove-member')->middleware('can:edit_task_force');
            Route::put('/{task_force}/reactivate', [TaskForceController::class, 'reactivate'])->name('reactivate')->middleware('can:edit_task_force');

            // Dynamic resource routes (show, edit, update, destroy)
            Route::get('/{task_force}', [TaskForceController::class, 'show'])->name('show')->middleware('can:view_task_force');
            Route::get('/{task_force}/edit', [TaskForceController::class, 'edit'])->name('edit')->middleware('can:edit_task_force');
            Route::put('/{task_force}', [TaskForceController::class, 'update'])->name('update')->middleware('can:edit_task_force');
            Route::delete('/{task_force}', [TaskForceController::class, 'destroy'])->name('destroy')->middleware('can:remove_task_force');
        });

        // Task Force Type Management
        Route::resource('task-force-types', TaskForceTypeController::class);

        // Organisation Management
        Route::middleware(['can:print_certificates'])->group(function () {
            Route::get('/organisations/certificates', [CertificateController::class, 'organisationIndex'])->name('organisations.certificates.index');
            Route::post('/organisations/certificates/print/plain', [CertificateController::class, 'organisationBulkPrintPlain'])->name('organisations.certificates.print.plain');
            Route::post('/organisations/certificates/print/branded', [CertificateController::class, 'organisationBulkPrintBranded'])->name('organisations.certificates.print.branded');
        });
        Route::resource('organisations', OrganisationController::class, ['except' => ['destroy']]);
        Route::post('/organisations/{organisation}/archive', [OrganisationController::class, 'archive'])->name('organisations.archive');
        Route::post('/organisations/{id}/restore', [OrganisationController::class, 'restore'])->name('organisations.restore');
        Route::post('/organisations/{organisation}/link-user', [OrganisationController::class, 'linkUser'])->name('organisations.link-user');
        Route::delete('/organisations/{organisation}/users/{user}/unlink', [OrganisationController::class, 'unlinkUser'])->name('organisations.unlink-user');
        Route::put('/organisations/{organisation}/users/{user}/set-primary-contact', [OrganisationController::class, 'setPrimaryContact'])->name('organisations.set-primary-contact');
        Route::get('/organisations/{organisation}/payments/create', [MembershipPaymentController::class, 'createForOrganisation'])->name('organisations.payments.create');
        Route::get('/organisations/{organisation}/donations/create', [DonationController::class, 'createForOrganisation'])->name('organisations.donations.create');

        /*// Messaging System Routes
        Route::prefix('messaging')->name('messaging.')->group(function () {
            Route::get('/filter-users', [ComposerController::class, 'filterUsers'])->name('filter-users');
            Route::get('/compose', [ComposerController::class, 'compose'])->name('compose');
            Route::post('/preview', [ComposerController::class, 'preview'])->name('preview');
            Route::post('/send', [ComposerController::class, 'send'])->name('send');

            // Messaging Campaigns
            Route::get('campaigns', [ComposerController::class, 'campaignsIndex'])->name('campaigns.index');
            Route::get('campaigns/{campaign}', [ComposerController::class, 'campaignsShow'])->name('campaigns.show');
        });*/

        Route::middleware(['can:campaign_request_approve'])
            ->prefix('campaigns/admin')
            ->name('campaigns.admin.')
            ->group(function () {
                Route::get('/proposed', [CampaignAdminController::class, 'index'])->name('proposed');
                Route::get('/{campaign}', [CampaignAdminController::class, 'show'])->name('show');
                Route::post('/{campaign}/approve', [CampaignAdminController::class, 'approve'])->name('approve');
                Route::post('/{campaign}/reject', [CampaignAdminController::class, 'reject'])->name('reject');
                Route::post('/{campaign}/queue', [CampaignAdminController::class, 'queue'])->name('queue');
                Route::post('/{campaign}/build-recipients', [CampaignAdminController::class, 'buildRecipients'])->name('buildRecipients');
                Route::post('/{campaign}/recipients/reset-failed', [CampaignAdminController::class, 'resetFailedRecipients'])->name('recipients.resetFailed');
                Route::post('/{campaign}/start-sending', [CampaignAdminController::class, 'startSending'])->name('startSending');
                Route::post('/{campaign}/stop-sending', [CampaignAdminController::class, 'stopSending'])->name('stopSending');
                Route::get('/{campaign}/monitor', [CampaignAdminController::class, 'monitor'])->name('monitor');
                Route::post('/{campaign}/run-once', [CampaignAdminController::class, 'runOnce'])->name('runOnce');

            });

        /* Route::post('/campaigns/{campaign}/approve', [MessagingCampaignController::class, 'approve'])
             ->middleware('can:campaign_request_approve')
             ->name('campaigns.approve');

         Route::post('/campaigns/{campaign}/reject', [MessagingCampaignController::class, 'reject'])
             ->middleware('can:campaign_request_approve')
             ->name('campaigns.reject');*/

        // routes/web.php

        Route::middleware(['can:campaign_request_create'])
            ->prefix('campaigns')
            ->name('campaigns.')
            ->group(function () {

                // ─────────────────────────────────────────
                // "My campaigns"
                // ─────────────────────────────────────────
                Route::get('/mine', [\App\Http\Controllers\CampaignMyController::class, 'index'])
                    ->name('mine');

                Route::get('/mine/{campaign}', [\App\Http\Controllers\CampaignMyController::class, 'show'])
                    ->name('mine.show');

                Route::delete('/mine/{campaign}', [\App\Http\Controllers\CampaignMyController::class, 'destroy'])
                    ->name('mine.destroy');

                Route::post('/{campaign}/duplicate', [\App\Http\Controllers\CampaignMyController::class, 'duplicate'])
                    ->name('duplicate');

                // ─────────────────────────────────────────
                // Campaign request (create/store)
                // ─────────────────────────────────────────
                Route::get('/create', [MessagingCampaignController::class, 'create'])
                    ->name('create');

                Route::post('/', [MessagingCampaignController::class, 'store'])
                    ->name('store');

                // ─────────────────────────────────────────
                // Campaign wizard
                // ─────────────────────────────────────────
                Route::prefix('wizard')
                    ->name('wizard.')
                    ->group(function () {

                        Route::match(['GET', 'POST'], '/start', [CampaignWizardController::class, 'start'])
                            ->name('start');

                        Route::get('/{campaign}/step-1', [CampaignWizardController::class, 'step1'])
                            ->name('step1');
                        Route::post('/{campaign}/step-1', [CampaignWizardController::class, 'postStep1'])
                            ->name('step1.post');

                        Route::get('/{campaign}/step-2', [CampaignWizardController::class, 'step2'])
                            ->name('step2');
                        Route::post('/{campaign}/step-2', [CampaignWizardController::class, 'postStep2'])
                            ->name('step2.post');

                        Route::get('/{campaign}/step-3', [CampaignWizardController::class, 'step3'])
                            ->name('step3');
                        Route::post('/{campaign}/step-3', [CampaignWizardController::class, 'postStep3'])
                            ->name('step3.post');

                        Route::get('/{campaign}/step-4', [CampaignWizardController::class, 'step4'])
                            ->name('step4');
                        Route::post('/{campaign}/step-4', [CampaignWizardController::class, 'postStep4'])
                            ->name('step4.post');

                        Route::get('/{campaign}/step-5', [CampaignWizardController::class, 'step5'])
                            ->name('step5');

                        Route::post('/{campaign}/submit', [CampaignWizardController::class, 'submit'])
                            ->name('submit');
                    });
            });

        Route::post('/campaigns/{campaign}/send', [MessagingCampaignController::class, 'send'])
            ->middleware('can:campaign_send')
            ->name('campaigns.send');
    });

    /*
    |--------------------------------------------------------------------------
    | Reports & Analytics Routes
    |--------------------------------------------------------------------------
    | Routes for generating and displaying reports and analytics dashboards
    | related to RedCross volunteer activities and operations.
    */
    Route::middleware(['auth', 'can:view_reports'])
        ->prefix('reports')
        ->name('reports.')
        ->group(function () {

            // Dashboard
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
            Route::get('/policies', [\App\Http\Controllers\Reports\PoliciesController::class, 'index'])->name('policies');
            Route::get('/pending-approvals',
                [\App\Http\Controllers\Reports\PendingApprovalsReportController::class, 'index'])
                ->name('pending-approvals');
            Route::get('/migration',
                [\App\Http\Controllers\Reports\MigrationReportController::class, 'index'])
                ->name('migration');

            // --- MEMBERS ---
            Route::prefix('members')->name('members.')->group(function () {
                Route::get('/national', [MemberReportController::class, 'national'])->name('national');
                Route::get('/branch/{branch}', [MemberReportController::class, 'branch'])->name('branch');
                Route::get('/division/{division}', [MemberReportController::class, 'division'])->name('division');
            });

            // --- VOLUNTEERS ---
            Route::prefix('volunteers')->name('volunteers.')->group(function () {
                Route::get('/national', [VolunteerReportController::class, 'national'])->name('national');
                Route::get('/branch/{branch}', [VolunteerReportController::class, 'branch'])->name('branch');
                Route::get('/division/{division}', [VolunteerReportController::class, 'division'])->name('division');
            });

            // --- BRANCHES ---
            Route::prefix('branches')->name('branches.')->group(function () {
                Route::get('/', [BranchReportController::class, 'index'])->name('index');
                Route::get('/comparison', [BranchReportController::class, 'comparison'])->name('comparison');
                Route::get('/growth', [BranchReportController::class, 'growth'])->name('growth');
                Route::get('/export/{type}', [BranchReportController::class, 'export'])->name('export');
            });

            // --- UNITS ---
            Route::prefix('units')->name('units.')->group(function () {
                Route::get('/', [RCUnitReportController::class, 'index'])->name('index');
                Route::get('/performance', [RCUnitReportController::class, 'performance'])->name('performance');
                Route::get('/distribution', [RCUnitReportController::class, 'distribution'])->name('distribution');
                Route::get('/export/{type}', [RCUnitReportController::class, 'export'])->name('export');
            });

            // --- FINANCIAL ---
            Route::prefix('financial')->name('financial.')->group(function () {
                Route::get('/national', [FinancialReportController::class, 'national'])->name('national');
                Route::get('/branch/{branch}', [FinancialReportController::class, 'branch'])->name('branch');
            });

            // --- TRAININGS ---
            Route::prefix('trainings')->name('trainings.')->group(function () {
                Route::get('/national', [TrainingReportController::class, 'national'])
                    ->name('national');

                Route::get('/branch/{branch}', [TrainingReportController::class, 'branch'])
                    ->name('branch');
            });

            Route::get('trainings/stats', [\App\Http\Controllers\Reports\TrainingStatsReportController::class, 'index'])->name('trainings.stats');

            Route::get('campaign-planning/welcome', [\App\Http\Controllers\Reports\WelcomeCampaignPlanningController::class, 'index'])->name('campaign-planning.welcome');
            Route::get('campaign-planning/dormant', [\App\Http\Controllers\Reports\DormantCampaignPlanningController::class, 'index'])->name('campaign-planning.dormant');
            Route::get('campaign-planning/expiring-membership', [\App\Http\Controllers\Reports\ExpiringMembershipCampaignPlanningController::class, 'index'])->name('campaign-planning.expiring-membership');
            Route::get('campaign-planning/donation-appreciation', [\App\Http\Controllers\Reports\DonationAppreciationCampaignPlanningController::class, 'index'])->name('campaign-planning.donation-appreciation');
            Route::get('campaign-planning/campaigns', [\App\Http\Controllers\Reports\CampaignsReportController::class, 'index'])->name('campaign-planning.campaigns');

            // --- DONATIONS ---
            Route::prefix('donations')->name('donations.')->group(function () {

                // National donations report
                Route::get('/national', [DonationReportController::class, 'national'])
                    ->name('national');

                // Branch donations report
                Route::get('/branch/{branch}', [DonationReportController::class, 'branch'])
                    ->name('branch');
            });

            Route::prefix('registrations')
                ->name('registrations.')
                ->group(function () {

                    // National registrations report
                    Route::get('/national', [RegistrationReportController::class, 'national'])
                        ->name('national');

                    // Branch registrations report
                    Route::get('/branch/{branchId}', [RegistrationReportController::class, 'branch'])
                        ->name('branch');
                });

            Route::get('admin-activities', [AdminActivityReportController::class, 'index'])
                ->name('admin-activities.index');

            // Single-route drill (branch → division, no unit level — stats_snapshots
            // has no unit granularity), same pattern as admin-activities above.
            Route::get('lifecycle', [LifecycleReportController::class, 'index'])
                ->name('lifecycle.national');

            Route::prefix('database-access')
                ->name('database-access.')
                ->group(function () {

                    Route::get('/', [DatabaseAccessReportController::class, 'index'])
                        ->name('index');

                });

            Route::get('database-team', [\App\Http\Controllers\Reports\DatabaseTeamReportController::class, 'index'])
                ->name('database-team.index');

            Route::get('tutorial-completion', [\App\Http\Controllers\Reports\TutorialCompletionReportController::class, 'index'])
                ->name('tutorial-completion');

            Route::get('red-cross-units', [\App\Http\Controllers\Reports\RedCrossUnitsReportController::class, 'index'])
                ->name('red-cross-units.index');

            Route::get('financial', [\App\Http\Controllers\Reports\FinancialOverviewReportController::class, 'index'])
                ->name('financial.index');

            // --- MAPS ---
            Route::prefix('maps')->name('maps.')->group(function () {
                Route::get('volunteers/branches', [\App\Http\Controllers\Reports\VolunteerMapController::class, 'branches'])->name('volunteers.branches');
                Route::get('volunteers/divisions', [\App\Http\Controllers\Reports\VolunteerMapController::class, 'divisions'])->name('volunteers.divisions');
                Route::get('first-aid/branches', [\App\Http\Controllers\Reports\FirstAidMapController::class, 'branches'])->name('first-aid.branches');
                Route::get('first-aid/divisions', [\App\Http\Controllers\Reports\FirstAidMapController::class, 'divisions'])->name('first-aid.divisions');
            });

            // --- ID CARD EXPIRY ---
            Route::get('id-card-expiry', [\App\Http\Controllers\Reports\IdCardExpiryReportController::class, 'national'])->name('id-card-expiry.national');
            Route::get('id-card-expiry/branch/{branch}', [\App\Http\Controllers\Reports\IdCardExpiryReportController::class, 'branch'])->name('id-card-expiry.branch');
            Route::get('id-card-expiry/branch/{branch}/division/{division}', [\App\Http\Controllers\Reports\IdCardExpiryReportController::class, 'division'])->name('id-card-expiry.division');

            // --- API ---
            Route::prefix('api')->name('api.')->group(function () {
                Route::get('/volunteer-stats', [VolunteerReportController::class, 'getStats'])->name('volunteer-stats');
                // Route::get('/activity-stats', [ActivityReportController::class, 'getStats'])->name('activity-stats');
                Route::get('/branch-stats', [BranchReportController::class, 'getStats'])->name('branch-stats');
                //        Route::get('/unit-stats', [UnitReportController::class, 'getStats'])->name('unit-stats');

                Route::get('/filter-options', [DashboardController::class, 'getFilterOptions'])->name('filter-options');
            });
        });

    // Tutorials — URL prefix is /learn to avoid collision with public/tutorials/ asset directory
    Route::prefix('learn')->name('tutorials.')->group(function () {
        Route::get('/', [\App\Http\Controllers\TutorialController::class, 'index'])->name('index');
        Route::get('/level/{level}', [\App\Http\Controllers\TutorialController::class, 'level'])->name('level');
        Route::get('/lesson/{key}', [\App\Http\Controllers\TutorialController::class, 'lesson'])->name('lesson');
        Route::post('/lesson/{key}/complete', [\App\Http\Controllers\TutorialController::class, 'complete'])->name('complete');
    });

    // Dormant Users / Archive Tool
    Route::middleware('can:use_archive_tool')->group(function () {
        Route::get('/dormant-users', [DormantUserController::class, 'index'])->name('dormant-users.index');
        Route::post('/dormant-users/archive', [DormantUserController::class, 'bulkArchive'])->name('dormant-users.archive');
    });

    // Signature Titles Settings
    Route::middleware(['can:change_settings', 'password.confirm'])->group(function () {
        Route::get('/settings/signature-titles', [SignatureTitleController::class, 'index'])->name('signature-titles.index');
        Route::post('/settings/signature-titles', [SignatureTitleController::class, 'store'])->name('signature-titles.store');
        Route::put('/settings/signature-titles/{signatureTitle}', [SignatureTitleController::class, 'update'])->name('signature-titles.update');
        Route::delete('/settings/signature-titles/{signatureTitle}', [SignatureTitleController::class, 'destroy'])->name('signature-titles.destroy');
    });

    // Settings Routes
    Route::middleware(['auth', 'can:change_settings', 'password.confirm'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::get('/settings/edit', [SettingController::class, 'edit'])->name('settings.edit');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');

        Route::get('/settings/signatures', [SignatureController::class, 'index'])->name('settings.signatures.index');
        Route::post('/settings/signatures', [SignatureController::class, 'store'])->name('settings.signatures.store');
        Route::delete('/settings/signatures/{filename}', [SignatureController::class, 'destroy'])->name('settings.signatures.destroy');

        Route::get('/settings/id-signature', [IdSignatureController::class, 'index'])->name('settings.id-signature.index');
        Route::post('/settings/id-signature', [IdSignatureController::class, 'store'])->name('settings.id-signature.store');

        Route::get('/settings/campaign-purposes', [\App\Http\Controllers\CampaignPurposeSettingsController::class, 'index'])->name('settings.campaign-purposes.index');
        Route::post('/settings/campaign-purposes', [\App\Http\Controllers\CampaignPurposeSettingsController::class, 'update'])->name('settings.campaign-purposes.update');
    });

    /*
    |--------------------------------------------------------------------------
    | Authenticated API Routes
    |--------------------------------------------------------------------------
    | API endpoints primarily used for dynamic content loading within the application,
    | accessible only by authenticated users.
    */
    Route::prefix('api')->group(function () {
        // Cascading dropdowns
        Route::get('/divisions/by-branch', [RedCrossUnitController::class, 'getDivisionsByBranch']); // Added for Red Cross Units index page
        // Route::get('/divisions/by-branch', [DivisionController::class, 'getDivisionsByBranch']); // This is covered by the ProfileController route for users.
        Route::get('/task-forces/by-division', [ActivityController::class, 'getTaskForcesByDivision'])
            ->name('task-forces.by-division');

    });
});

Route::prefix('api')->group(function () {
    // Divisions for a given branch (for map)
    Route::get('/branches/{branch}/divisions', [DivisionController::class, 'getDivisionsForBranch'])
        ->name('api.branches.divisions');
    Route::get('/divisions/{division}/units', [DivisionController::class, 'getDivisionWithUnits'])
        ->name('api.divisions.units');
});
