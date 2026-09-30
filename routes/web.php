<?php

use App\Enums\StartupPrimaryRoundStatusEnum;
use App\Enums\Utills\StatusEnum;
use App\Helpers\FileUpDownHelper;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\Web\Admin\Auth\AuthenticatedSessionController as AdminAuthController;
use App\Http\Controllers\Web\Admin\Auth\PasswordResetLinkController as AdminPasswordResetController;
use App\Http\Controllers\Web\Admin\CmsReportController as AdminCmsReportController;
use App\Http\Controllers\Web\Admin\Dashboard\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\InvestorController as AdminInvestorController;
use App\Http\Controllers\Web\Admin\Master\BankAccountTypeController as MasterBankAccountTypeController;
use App\Http\Controllers\Web\Admin\Master\BankController as MasterBankController;
use App\Http\Controllers\Web\Admin\Master\BlogController as MasterBlogController;
use App\Http\Controllers\Web\Admin\Master\CityController as MasterCityController;
use App\Http\Controllers\Web\Admin\Master\CountryController as MasterCountryController;
use App\Http\Controllers\Web\Admin\Master\FamilyRelationController as MasterFamilyRelationController;
use App\Http\Controllers\Web\Admin\Master\HeaderTokenController;
use App\Http\Controllers\Web\Admin\Master\IndustryController as MasterIndustryController;
use App\Http\Controllers\Web\Admin\Master\InstrumentTypeController as MasterInstrumentTypeController;
use App\Http\Controllers\Web\Admin\Master\InvestorTypeController as MasterInvestorTypeController;
use App\Http\Controllers\Web\Admin\Master\ManageInfoIconController as MasterManageInfoIconController;
use App\Http\Controllers\Web\Admin\Master\PagesController as MasterPagesController;
use App\Http\Controllers\Web\Admin\Master\SectorController as MasterSectorController;
use App\Http\Controllers\Web\Admin\Master\SocialMediaLinkController as MasterSocialMediaLinkController;
use App\Http\Controllers\Web\Admin\Master\StartupRoundTypeController as MasterStartupRoundTypeController;
use App\Http\Controllers\Web\Admin\Master\StateController as MasterStateController;
use App\Http\Controllers\Web\Admin\Master\WebsiteSocialMediaController as MasterWebsiteSocialMediaController;
use App\Http\Controllers\Web\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Web\Admin\ProfileMenu\NotificationController as AdminProfileNotificationController;
use App\Http\Controllers\Web\Admin\Partner\DistributorController as AdminPartnerDistributorController;
use App\Http\Controllers\Web\Admin\Partner\InstitutionController as AdminPartnerInstitutionController;
use App\Http\Controllers\Web\Admin\Partner\RetailersController as AdminPartnerRetailersController;
use App\Http\Controllers\Web\Admin\Partner\WealthManagerController as AdminPartnerWealthManagerController;
use App\Http\Controllers\Web\Admin\PortfolioController as AdminPortfolioController;
use App\Http\Controllers\Web\Admin\PrimaryTransactionController as AdminPrimaryTransactionController;
use App\Http\Controllers\Web\Admin\Setting\SettingController;
use App\Http\Controllers\Web\Admin\Startup\MGT14Controller as AdminMGT14Controller;
use App\Http\Controllers\Web\Admin\Startup\UpdateController as AdminStartupUpdateController;
use App\Http\Controllers\Web\Admin\Startup\MISController as AdminStartupMISController;
use App\Http\Controllers\Web\Admin\Startup\OfferRequestController as AdminOfferRequestController;
use App\Http\Controllers\Web\Admin\Startup\Pas3Controller as AdminPas3Controller;
use App\Http\Controllers\Web\Admin\StartupController;
use App\Http\Controllers\Web\Admin\WhatsappBroadcastController as AdminWhatsappBroadcastController;
use App\Http\Controllers\DynamicUrlController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\Web\Admin\AdminApiClientsController;
use App\Http\Controllers\Web\Admin\AiAutowork\AiAutoworkController;
use App\Http\Controllers\Web\Admin\AiAutowork\AiCompanyIngestAdminController;
use App\Http\Controllers\Web\Admin\BseHolidayController;
use App\Http\Controllers\Web\Admin\CompanyController;
use App\Http\Controllers\Web\Admin\CompanyDealController;
use App\Http\Controllers\Web\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Web\Admin\ManagerController as AdminManagerController;
use App\Http\Controllers\Web\Admin\ManualController as AdminManualController;
use App\Http\Controllers\Web\Admin\Master\AvtarController as MasterAvtarController;
use App\Http\Controllers\Web\Admin\Master\FaqsController as MasterFaqsController;
use App\Http\Controllers\Web\Admin\Master\FindCmlController;
use App\Http\Controllers\Web\Admin\Master\ProjectController as MasterProjectController;
use App\Http\Controllers\Web\Admin\Master\ResourceBillingController;
use App\Http\Controllers\Web\Admin\Master\SupportedCountriesController as MasterSupportedCountriesController;
use App\Http\Controllers\Web\Admin\MyProfileController;
use App\Http\Controllers\Web\Admin\NotificationsController as AdminReportsNotificationsController;
use App\Http\Controllers\Web\Admin\Partner\RelationalManagerController as AdminRelationalManagerController;
use App\Http\Controllers\Web\Admin\PaymentReceiptController as AdminPaymentReceiptController;
use App\Http\Controllers\Web\Admin\PreIpoTransactionController as AdminPreIpoTransactionController;
use App\Http\Controllers\Web\Admin\PushNotificationController as AdminPushNotificationController;
use App\Http\Controllers\Web\Admin\SecondaryPaymentReceiptController as AdminSecondaryPaymentReceiptController;
use App\Http\Controllers\Web\Admin\SecondaryTransactionController as AdminSecondaryTransactionController;
use App\Http\Controllers\Web\Admin\CompanyEnquiryController as AdminCompanyEnquiryController;
use App\Http\Controllers\Web\Admin\SellerMasterController as AdminSellerMasterController;
use App\Http\Controllers\Web\Admin\Setting\AppVersionController;
use App\Http\Controllers\Web\Admin\Startup\LivepitchController as AdminStartupLivepitchController;
use App\Http\Controllers\Web\Admin\UploadDocumentController as AdminUploadDocumentController;
use App\Http\Controllers\Web\Admin\WebsiteController as AdminWebsiteController;
use App\Http\Controllers\Web\Front\User\Investor\DashboardController as InvestorDashboardController;
use App\Http\Controllers\Web\Front\User\Investor\MandateController;
use App\Http\Controllers\Web\Front\User\Investor\MISController as InvestorMISController;
use App\Http\Controllers\Web\Front\User\Investor\PortfolioController as InvestorPortfolioController;
use App\Http\Controllers\Web\Front\User\Investor\ProfileController as InvestorProfileController;
use App\Http\Controllers\Web\Front\User\Partner\ChannelPartnerController;
use App\Http\Controllers\Web\Front\User\Partner\CommonController as PartnerCommonController;
use App\Http\Controllers\Web\Front\User\Partner\DashboardController as PartnerDashboardController;
use App\Http\Controllers\Web\Front\User\PrimaryTransactionController as UsersPrimaryTransactionController;
use App\Http\Controllers\Web\Front\User\Startup\CommonController as StartupCommonController;
use App\Http\Controllers\Web\Front\User\Startup\DashboardController as StartupDashboardController;
use App\Http\Controllers\Web\Front\User\Startup\ManageCaptableController as StartupManageCaptableController;
use App\Http\Controllers\Web\Front\User\Startup\MISController as StartupMISController;
use App\Http\Controllers\Web\Front\User\Startup\StartupMgt14Controller as StartupMgt14Controller;
use App\Http\Controllers\Web\Front\User\Startup\Pas3Controller as StartupPas3Controller;
use App\Http\Controllers\Web\Front\User\Startup\SecondaryController as StartupSecondaryController;
use App\Http\Controllers\Web\Front\User\Startup\UpdatesController as StartupUpdatesController;
use App\Http\Controllers\Web\Front\MarketingPageController;
use App\Http\Controllers\Web\SessionController;
use App\Http\Middleware\AdminRedirectIfAuthenticatedMiddleware;
use App\Http\Middleware\CoreMiddleware;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\InvestorRedirectIfAuthenticatedMiddleware;
use App\Http\Middleware\InvestorRedirectIfNotAuthenticatedMiddleware;
use App\Http\Middleware\isMaintenanceMiddleware;
use App\Http\Middleware\isUserMiddleware;
use App\Http\Middleware\PartnerRedirectIfAuthenticatedMiddleware;
use App\Http\Middleware\PartnerRedirectIfNotAuthenticatedMiddleware;
use App\Http\Middleware\StartupRedirectIfAuthenticatedMiddleware;
use App\Http\Middleware\StartupRedirectIfNotAuthenticatedMiddleware;
use App\Http\Middleware\ValidateApiIframeAccess;
use App\Models\ApiClient;
use App\Models\InvestorModel;
use App\Models\PortfolioModel;
use App\Models\StartupModel;
use App\Models\StartupRoundModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
// use Maatwebsite\Excel\Excel;
use App\Models\ApiLogModel;
use App\Exports\TopActiveInvestorsExport;
use App\Http\Controllers\Web\Admin\Setting\AppBuildController;
use App\Http\Controllers\Web\Website\SiteController;
use Maatwebsite\Excel\Facades\Excel;

// Route::get('sync-data', [TestController::class, 'syncData']);
Route::get('export-csv', [TestController::class, 'exportCSV']);

// SEO: marketing sitemap
Route::get('sitemap.xml', [MarketingPageController::class, 'sitemap'])->name('sitemap.xml');

Route::group(['middleware' => [CoreMiddleware::class]], function () {
    // Route::get('test', [TestController::class, 'test']);

    Route::get('download-file', [DownloadController::class, 'web'])->name('download.web');
    Route::group(['middleware' => [isUserMiddleware::class]], function () {

        Route::post('notifications', [DownloadController::class, 'notifications'])->name('notifications');
    });

    Route::group(['middleware' => [isMaintenanceMiddleware::class]], function () {

        // Public marketing website (migrated from PrivatedealsWebsite)
        Route::get('/', [MarketingPageController::class, 'show'])
            ->defaults('slug', 'home')
            ->name('home');

        Route::redirect('/login', config('pages.partner_login_url'), 301);

        foreach (config('pages.pages', []) as $slug => $page) {
            if (in_array($slug, ['home', 'not-found', 'login'], true)) {
                continue;
            }

            Route::get($page['path'], [MarketingPageController::class, 'show'])
                ->defaults('slug', $slug)
                ->name('page.' . $slug);
        }

        foreach (config('pages.legacy_redirects', []) as $from => $to) {
            Route::redirect($from, $to, 301);
        }

        // Legacy Project 2 public URLs → new marketing paths
        Route::redirect('/aboutus', '/about', 301);
        Route::redirect('/contactus', '/contact', 301);
        Route::redirect('/terms-of-use', '/terms-conditions', 301);
        Route::redirect('/risk-disclouser', '/risk-disclosure', 301);

        Route::fallback(function () {
            $page = config('pages.pages.not-found');

            if (! $page || ! view()->exists('marketing.pages.not-found')) {
                abort(404);
            }

            return response()->view('marketing.pages.not-found', compact('page'), 404);
        });
    });
});

Route::get('preipo-investor', [AdminDashboardController::class, 'preIpoInvestor'])->name('preipo_investor');

// Admin session keep-alive (used by admin layout session.blade.php)
Route::post('session/restore', [SessionController::class, 'restore'])
    ->middleware(App\Http\Middleware\AdminRedirectIfNotAuthenticatedMiddleware::class)
    ->name('session.restore');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('', function () {
        return redirect('admin/login');
    });

    Route::group(['middleware' => [App\Http\Middleware\AdminRedirectIfNotAuthenticatedMiddleware::class]], function () {
        Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::middleware('hasPermission:partner')->name('partner.')->prefix('partner')->group(function () {
            Route::name('wealthmanager.')->prefix('wealth-manager')->group(function () {
                Route::get('create', [AdminPartnerWealthManagerController::class, 'create'])->name('create');
                Route::get('list', [AdminPartnerWealthManagerController::class, 'list'])->name('list');
                Route::get('view/{uuid}', [AdminPartnerWealthManagerController::class, 'view'])->name('view');
                Route::get('edit/{uuid}', [AdminPartnerWealthManagerController::class, 'edit'])->name('edit');

                Route::post('save', [AdminPartnerWealthManagerController::class, 'store'])->name('store');
                Route::put('update/{uuid}', [AdminPartnerWealthManagerController::class, 'update'])->name('update');
                Route::delete('delete/{id}', [AdminPartnerWealthManagerController::class, 'delete'])->name('destroy');
            });

            Route::name('distributor.')->prefix('distributor')->group(function () {
                Route::get('list', [AdminPartnerDistributorController::class, 'list'])->name('list');
                Route::get('create', [AdminPartnerDistributorController::class, 'create'])->name('create');
                Route::get('view/{uuid}', [AdminPartnerDistributorController::class, 'view'])->name('view');
                Route::get('edit/{uuid}', [AdminPartnerDistributorController::class, 'edit'])->name('edit');

                Route::post('save', [AdminPartnerDistributorController::class, 'store'])->name('store');
                Route::put('update/{uuid}', [AdminPartnerDistributorController::class, 'update'])->name('update');
                Route::delete('delete/{id}', [AdminPartnerDistributorController::class, 'delete'])->name('destroy');
            });

            Route::name('institution.')->prefix('institution')->group(function () {
                Route::get('list', [AdminPartnerInstitutionController::class, 'list'])->name('list');
                Route::get('create', [AdminPartnerInstitutionController::class, 'create'])->name('create');
                Route::get('view/{uuid}', [AdminPartnerInstitutionController::class, 'view'])->name('view');
                Route::get('edit/{uuid}', [AdminPartnerInstitutionController::class, 'edit'])->name('edit');

                Route::post('save', [AdminPartnerInstitutionController::class, 'store'])->name('store');
                Route::put('update/{uuid}', [AdminPartnerInstitutionController::class, 'update'])->name('update');
                Route::delete('delete/{id}', [AdminPartnerInstitutionController::class, 'delete'])->name('destroy');
            });

            Route::name('retailers.')->prefix('retailers')->group(function () {
                Route::get('list', [AdminPartnerRetailersController::class, 'list'])->name('list');
                Route::get('create', [AdminPartnerRetailersController::class, 'create'])->name('create');
                Route::get('view/{uuid}', [AdminPartnerRetailersController::class, 'view'])->name('view');
                Route::get('update/{uuid}', [AdminPartnerRetailersController::class, 'edit'])->name('edit');

                Route::post('save', [AdminPartnerRetailersController::class, 'store'])->name('store');
                Route::put('update/{uuid}', [AdminPartnerRetailersController::class, 'update'])->name('update');
                Route::delete('delete/{id}', [AdminPartnerRetailersController::class, 'delete'])->name('destroy');
            });

            Route::name('relationalManager.')->prefix('relation-manager')->group(function () {
                Route::get('list', [AdminRelationalManagerController::class, 'list'])->name('list');
                Route::get('create', [AdminRelationalManagerController::class, 'create'])->name('create');
                Route::get('view/{uuid}', [AdminRelationalManagerController::class, 'view'])->name('view');
                Route::get('update/{uuid}', [AdminRelationalManagerController::class, 'edit'])->name('edit');

                Route::post('save', [AdminRelationalManagerController::class, 'store'])->name('store');
                Route::put('update/{uuid}', [AdminRelationalManagerController::class, 'update'])->name('update');
                Route::delete('delete/{id}', [AdminRelationalManagerController::class, 'delete'])->name('destroy');
            });

            Route::get('demo', [AdminPartnerWealthManagerController::class, 'list'])->name('demo');
            Route::get('mark-demo/{uuid}', [AdminPartnerWealthManagerController::class, 'markAsDemo'])->name('mark-demo');
            Route::post('manager', [AdminPartnerWealthManagerController::class, 'manager'])->name('manager');
        });

        Route::middleware('hasPermission:investor')->name('investor.')->prefix('investor')->group(function () {
            Route::get('create', [AdminInvestorController::class, 'create'])->name('create');
            Route::get('active', [AdminInvestorController::class, 'list'])->name('active');
            Route::get('inactive', [AdminInvestorController::class, 'list'])->name('inactive');
            Route::get('rejected', [AdminInvestorController::class, 'list'])->name('rejected');
            Route::get('demo', [AdminInvestorController::class, 'list'])->name('demo');
            Route::get('block', [AdminInvestorController::class, 'list'])->name('block');
            Route::get('filter', [AdminInvestorController::class, 'filteredList'])->name('filter');
            Route::get('aadhar-pan-verification', [AdminInvestorController::class, 'aadharPanVerification'])->name('aadharPanVerification');
            Route::get('export-excel', [AdminInvestorController::class, 'exportExcel'])->name('export');
            Route::get('pending-kyc', [AdminInvestorController::class, 'list'])->name('pendingkyc');
            // Global documents list for investors
            Route::get('documents', [AdminInvestorController::class, 'documents'])->name('documents');
            Route::get('documents/data', [AdminInvestorController::class, 'documentsData'])->name('documents.data');
            Route::get('processing-kyc', [AdminInvestorController::class, 'processingKyc'])->name('processing-kyc');
            Route::get('processing-kyc/data', [AdminInvestorController::class, 'processingKycData'])->name('processing-kyc.data');
            Route::post('submit-kyc', [AdminInvestorController::class, 'manualKycSubmit'])->name('manualKycSubmit');
            Route::post('reject-manual-kyc', [AdminInvestorController::class, 'manualKycReject'])->name('manualKycReject');
            Route::get('update/{uuid}', [AdminInvestorController::class, 'edit'])->name('edit');
            Route::get('active-status/{uuid}/{status}', [AdminInvestorController::class, 'activestatus'])->name('activestatus');
            Route::get('view/{uuid}', [AdminInvestorController::class, 'view'])->name('view');
            Route::get('mark-demo/{uuid}', [AdminInvestorController::class, 'markAsDemo'])->name('mark-demo');
            Route::get('mark-block/{uuid}', [AdminInvestorController::class, 'markBlock'])->name('markBlock');

            Route::post('store', [AdminInvestorController::class, 'store'])->name('store');
            Route::post('manager', [AdminInvestorController::class, 'manager'])->name('manager');
            Route::post('get-temp-aadhar-pan-details', [AdminInvestorController::class, 'getTempAadharPanDetails'])->name('getTempAadharPanDetails');
            Route::post('approve-aadhar-pan', [AdminInvestorController::class, 'approveAadharPan'])->name('approveAadharPan');
            Route::post('process-demat-pdf', [AdminInvestorController::class, 'processDematPdf'])->name('process-demat-pdf');
            Route::post('{uuid}/demat-kyc', [AdminInvestorController::class, 'storeDematKyc'])->name('demat-kyc.store');
            Route::put('update/{uuid}', [AdminInvestorController::class, 'update'])->name('update');
            Route::delete('delete/{id}', [AdminInvestorController::class, 'delete'])->name('destroy');


            Route::name('manual-kyc.')->prefix('manual-kyc')->group(function () {
                Route::get('create/{uuid}', [AdminInvestorController::class, 'kycUpload'])->name('create');
                Route::post('save', [AdminInvestorController::class, 'kycUpload'])->name('save');
            });

            Route::name('manual-aif.')->prefix('manual-aif')->group(function () {
                Route::get('create/{uuid}', [AdminInvestorController::class, 'aifUpload'])->name('create');
                Route::post('save', [AdminInvestorController::class, 'aifUpload'])->name('save');
            });

            Route::get('edit-kyc/{uuid}', [AdminInvestorController::class, 'editKYCDetails'])->name('editKYCDetails');
            Route::post('update-kyc', [AdminInvestorController::class, 'updateKYCDetails'])->name('updateKYCDetails');
        });

        Route::middleware('hasPermission:manual kyc requests')->name('manualkyc.')->prefix('manual-kyc-request')->group(function () {
            Route::get('pending', [AdminInvestorController::class, 'manualKycPending'])->name('pending');
            Route::get('approved', [AdminInvestorController::class, 'manualKycApproved'])->name('approved');
            Route::get('rejected', [AdminInvestorController::class, 'manualKycRejected'])->name('rejected');
            Route::get('view/{uuid}', [AdminInvestorController::class, 'manualKycView'])->name('view');

            Route::get('reject-kyc/{id}', [AdminInvestorController::class, 'rejectManualKYC'])->name('reject');
            Route::post('approve-kyc', [AdminInvestorController::class, 'approveManualKYC'])->name('approve');
            Route::post('update-bank-status/{id}', [AdminInvestorController::class, 'updateBankStatus'])->name('updateBankStatus');
            Route::post('update-demat-status/{id}', [AdminInvestorController::class, 'updateDematStatus'])->name('updateDematStatus');
            Route::post('update-pan-status/{id}', [AdminInvestorController::class, 'updatePanStatus'])->name('updatePanStatus');
            Route::post('update-aadhar-status/{id}', [AdminInvestorController::class, 'updateAadharStatus'])->name('updateAadharStatus');
        });

        Route::middleware('hasPermission:aif onboard')->name('aifonboard.')->prefix('aif-onboard')->group(function () {
            Route::get('pending', [AdminInvestorController::class, 'aifOnboardPending'])->name('pending');
            Route::get('approved', [AdminInvestorController::class, 'aifOnboardApproved'])->name('approved');
            Route::get('rejected', [AdminInvestorController::class, 'aifOnboardRejected'])->name('rejected');
            Route::get('view/{uuid}', [AdminInvestorController::class, 'aifOnboardView'])->name('view');

            Route::post('status', [AdminInvestorController::class, 'aifOnboardapprove'])->name('approve');
            Route::get('document-status/{id}/{type}', [AdminInvestorController::class, 'aifDocumentStatus'])->name('documentstatus');
        });

        Route::middleware('hasPermission:primary payment receipt')->name('paymentReceipt.')->prefix('payment-receipt')->group(function () {
            Route::get('pending', [AdminPaymentReceiptController::class, 'pending'])->name('pending');
            Route::get('approved', [AdminPaymentReceiptController::class, 'approved'])->name('approved');
            Route::get('rejected', [AdminPaymentReceiptController::class, 'rejected'])->name('rejected');
            Route::get('approve_status/{id}', [AdminPaymentReceiptController::class, 'approve'])->name('approve');
            Route::get('reject_status/{id}', [AdminPaymentReceiptController::class, 'reject'])->name('reject');
        });

        Route::middleware('hasPermission:primary payment receipt')->name('secpaymentReceipt.')->prefix('secondary-payment-receipt')->group(function () {
            Route::get('pending', [AdminSecondaryPaymentReceiptController::class, 'pending'])->name('pending');
            Route::get('approved', [AdminSecondaryPaymentReceiptController::class, 'approved'])->name('approved');
            Route::get('rejected', [AdminSecondaryPaymentReceiptController::class, 'rejected'])->name('rejected');
            Route::get('approve_status/{id}', [AdminSecondaryPaymentReceiptController::class, 'approve'])->name('approve');
            Route::get('reject_status/{id}', [AdminSecondaryPaymentReceiptController::class, 'reject'])->name('reject');
        });

        Route::name('profile.')->prefix('profile')->group(function () {
            Route::name('notification.')->prefix('notification')->group(function () {
                Route::get('', [AdminProfileNotificationController::class, 'list'])->name('list');
            });
        });

        Route::middleware('hasPermission:primary transaction')->name('primarytransactions.')->prefix('primary-transactions')->group(function () {
            Route::get('pending', [AdminPrimaryTransactionController::class, 'list'])->name('pending');
            Route::get('completed', [AdminPrimaryTransactionController::class, 'list'])->name('completed');

            Route::delete('delete/{id}', [AdminPrimaryTransactionController::class, 'delete'])->name('delete');

            Route::name('uploadDocument.')->prefix('upload-document')->group(function () {
                // Route::get('list', [AdminWhatsappBroadcastController::class, 'list'])->name('list');
                Route::get('create', [AdminUploadDocumentController::class, 'create'])->name('create');
                // Route::get('view/{item}', [AdminWhatsappBroadcastController::class, 'view'])->name('view');
                Route::post('store', [AdminUploadDocumentController::class, 'store'])->name('store');
                Route::post('documents', [AdminUploadDocumentController::class, 'storeSingleTransactionDoc'])->name('storeSingleTransactionDoc');
                Route::post('sec-documents', [AdminUploadDocumentController::class, 'storeSingleSecTransactionDoc'])->name('storeSingleSecTransactionDoc');
                Route::post('preipo-documents', [AdminUploadDocumentController::class, 'storeSinglePreipoTransactionDoc'])->name('storeSinglePreipoTransactionDoc');
            });
        });

        Route::middleware('hasPermission:secondary transaction')->name('secondarytransactions.')->prefix('secondary-transactions')->group(function () {
            Route::get('pending', [AdminSecondaryTransactionController::class, 'list'])->name('pending');
            Route::get('completed', [AdminSecondaryTransactionController::class, 'list'])->name('completed');
        });

        Route::middleware('hasPermission:pre ipo transaction')->name('preipotransaction.')->prefix('pre-ipo-transactions')->group(function () {
            Route::get('status/{transaction_id}', [AdminPreIpoTransactionController::class, 'status'])->name('status');
            Route::get('market', [AdminPreIpoTransactionController::class, 'market'])->name('market');
            Route::get('pending', [AdminPreIpoTransactionController::class, 'list'])->name('pending');
            Route::get('rejected', [AdminPreIpoTransactionController::class, 'list'])->name('rejected');
            Route::get('completed', [AdminPreIpoTransactionController::class, 'list'])->name('completed');

            Route::get('slip-status/{transaction_id}', [AdminPreIpoTransactionController::class, 'slipStatus'])->name('slipStatus');
            Route::get('pre-ipo/download-deal-slip/{id}', [AdminPreIpoTransactionController::class, 'downloadDealSlip'])->name('downloadDealSlip');

            Route::get('resend-deal-slip/{transaction_id}', [AdminPreIpoTransactionController::class, 'resendDealSlip'])->name('resendDealSlip');
            Route::post('approve-transaction', [AdminPreIpoTransactionController::class, 'approveTransaction'])->name('approve');
            Route::delete('delete/{id}', [AdminPreIpoTransactionController::class, 'deletePreIpoTransaction'])->name('delete');
            Route::post('extend-timer/{transaction_id}', [AdminPreIpoTransactionController::class, 'extendTimer'])->name('extendTimer');
            Route::post('retrieve-transaction/{transaction_id}', [AdminPreIpoTransactionController::class, 'retrieveTransaction'])->name('retrieve');



            Route::name('uploadDocument.')->prefix('upload-document')->group(function () {
                Route::post('deal-slip', [AdminUploadDocumentController::class, 'storeSinglePreipoTransactionDoc'])->name('storeDealSlip');
            });
        });

        Route::name('secondarySellRequest.')->prefix('secondary-sell-request')->group(function () {
            Route::get('pending', [AdminSecondaryTransactionController::class, 'sellRequestList'])->name('pending');
            Route::get('in-progress', [AdminSecondaryTransactionController::class, 'sellRequestList'])->name('inProgress');
            Route::get('completed', [AdminSecondaryTransactionController::class, 'sellRequestList'])->name('completed');
            Route::delete('delete/{id}', [AdminSecondaryTransactionController::class, 'delete'])->name('destroy');
        });

        Route::name('companyEnquiry.')->prefix('company-enquiry')->group(function () {
            Route::get('pending', [AdminCompanyEnquiryController::class, 'list'])->name('pending');
            Route::get('completed', [AdminCompanyEnquiryController::class, 'list'])->name('completed');
            Route::post('{id}/complete', [AdminCompanyEnquiryController::class, 'complete'])->name('complete');
            Route::delete('delete/{id}', [AdminCompanyEnquiryController::class, 'delete'])->name('destroy');
        });

        Route::name('reports.')->prefix('reports')->group(function () {
            Route::middleware('hasPermission:cms reports')->name('cms.')->prefix('cms')->group(function () {
                Route::get('feedbacklist', [AdminCmsReportController::class, 'feedbacklist'])->name('feedback.list');
                Route::get('contactlist', [AdminCmsReportController::class, 'contactlist'])->name('contact.list');
                Route::get('contactlist/export-excel', [AdminCmsReportController::class, 'exportContactExcel'])->name('contact.export');
                Route::get('contactlist/view/{id}', [AdminCmsReportController::class, 'viewContact'])->name('contact.view');
                Route::delete('contactlist/delete/{id}', [AdminCmsReportController::class, 'deleteContact'])->name('contact.delete');
                Route::post('contactlist/bulk-delete', [AdminCmsReportController::class, 'bulkDeleteContact'])->name('contact.bulk-delete');
                Route::get('leads', [AdminCmsReportController::class, 'reportLeads'])->name('leads');
                Route::get('preipo-device-export', [AdminCmsReportController::class, 'preIpoDeviceExport'])->name('preipoDeviceExport');
                Route::name('requestaccess.')->prefix('request-access')->group(function () {
                    Route::get('pending', [AdminCmsReportController::class, 'requestAccessList'])->name('pending');
                    Route::get('completed', [AdminCmsReportController::class, 'requestAccessList'])->name('completed');
                    Route::post('mark-as-read', [AdminCmsReportController::class, 'markAsRead'])->name('markAsRead');
                });
            });

            Route::middleware('hasPermission:resource billing management')->name('resourceBilling.')->prefix('resource-billing')->group(function () {
                Route::get('list', [ResourceBillingController::class, 'list'])->name('list');
                Route::get('create', [ResourceBillingController::class, 'create'])->name('create');
                Route::post('store', [ResourceBillingController::class, 'store'])->name('store');
                Route::post('billing-history', [ResourceBillingController::class, 'billingHistory'])->name('createBillingHistory');
            });

            Route::name('notifications.')->prefix('notifications')->group(function () {
                Route::get('whatsapp', [AdminReportsNotificationsController::class, 'whatsappList'])->name('whatsappList');
                // Route::get('create', [ResourceBillingController::class, 'create'])->name('create');
                // Route::post('store', [ResourceBillingController::class, 'store'])->name('store');
                // Route::post('billing-history', [ResourceBillingController::class, 'billingHistory'])->name('createBillingHistory');
            });

            Route::middleware('hasPermission:application logs')->name('applogs.')->prefix('applogs')->group(function () {
                Route::name('investor.')->prefix('investor')->group(function () {
                    Route::get('android', [AdminCmsReportController::class, 'appLogs'])->name('android');
                    Route::get('ios', [AdminCmsReportController::class, 'appLogs'])->name('ios');
                    Route::get('windows', [AdminCmsReportController::class, 'appLogs'])->name('windows');
                    Route::name('active-today.')->prefix('active-today')->group(function () {
                        Route::get('list', [AdminCmsReportController::class, 'activeTodayList'])->name('list');
                        Route::get('view/{userid}/{usertype}/{date}', [AdminCmsReportController::class, 'activeTodayView'])->name('view');
                        // Route::get('view/{item}', [AdminWhatsappBroadcastController::class, 'view'])->name('view');
                    });
                });

                Route::name('distributer.')->prefix('distributer')->group(function () {
                    Route::get('android', [AdminCmsReportController::class, 'appLogs'])->name('android');
                    Route::get('ios', [AdminCmsReportController::class, 'appLogs'])->name('ios');
                    Route::get('windows', [AdminCmsReportController::class, 'appLogs'])->name('windows');
                    Route::name('active-today.')->prefix('active-today')->group(function () {
                        Route::get('list', [AdminCmsReportController::class, 'activeTodayList'])->name('list');
                        Route::get('view/{userid}/{usertype}/{date}', [AdminCmsReportController::class, 'activeTodayView'])->name('view');
                        // Route::get('view/{item}', [AdminWhatsappBroadcastController::class, 'view'])->name('view');
                    });
                });
            });
            // Route::get('request-beta-access', [AdminCmsReportController::class, 'requestBetaAccessList'])->name('requestbetaaccess.list');
        });



        Route::name('startup.')->prefix('startup')->group(function () {

            Route::name('updateteam.')->prefix('update-team')->group(function () {
                Route::get('{uuid}', [StartupController::class, 'editTeam'])->name('get');
                Route::post('', [StartupController::class, 'updateTeam'])->name('post');
            });

            Route::get('view/{uuid}', [StartupController::class, 'view'])->name('view');
            Route::get('view-old/{uuid}', [StartupController::class, 'viewOld'])->name('view.old');
            Route::get('create', [StartupController::class, 'create'])->name('create');
            Route::get('list', [StartupController::class, 'list'])->name('list');
            Route::post('store', [StartupController::class, 'store'])->name('store');
            // Route::post('store', [StartupController::class, 'store'])->name('store');
            Route::get('update/{uuid}', [StartupController::class, 'edit'])->name('edit');
            Route::get('edt/{uuid}', [StartupController::class, 'editet'])->name('editet');
            Route::post('update', [StartupController::class, 'update'])->name('update');

            Route::delete('delete/{id}', [StartupController::class, 'delete'])->name('destroy');
            Route::name('shareprice.')->prefix('share-price')->group(function () {
                Route::get('{uuid}', [StartupController::class, 'sharePrice'])->name('get');
                Route::get('delete/{id}', [StartupController::class, 'sharePriceDelete'])->name('delete');
                Route::post('', [StartupController::class, 'sharePriceSave'])->name('post');
            });
            Route::middleware('hasPermission:startup')->name('manage.')->prefix('manage')->group(function () {
                Route::get('pending', [StartupController::class, 'list'])->name('pending');
                Route::get('coming_soon', [StartupController::class, 'list'])->name('coming_soon');
                Route::get('raising_now', [StartupController::class, 'list'])->name('raising_now');
                Route::get('completed', [StartupController::class, 'list'])->name('completed');
            });

            Route::middleware('hasPermission:sm updates news')->name('update.')->prefix('update')->group(function () {
                Route::get('pending', [AdminStartupUpdateController::class, 'pending'])->name('pending');
                Route::get('approved', [AdminStartupUpdateController::class, 'approved'])->name('approved');
                Route::get('rejected', [AdminStartupUpdateController::class, 'rejected'])->name('rejected');

                Route::get('status/{id}/{status}', [AdminStartupUpdateController::class, 'status'])->name('status');
                Route::get('delete/{id}', [AdminStartupUpdateController::class, 'delete'])->name('delete');
            });

            Route::middleware('hasPermission:sm mgt14')->name('mgt14.')->prefix('mgt14')->group(function () {
                Route::get('pending', [AdminMGT14Controller::class, 'pending'])->name('pending');
                Route::get('approved', [AdminMGT14Controller::class, 'approved'])->name('approved');
                Route::get('rejected', [AdminMGT14Controller::class, 'rejected'])->name('rejected');
                Route::get('approve_status/{id}', [AdminMGT14Controller::class, 'approve'])->name('approve');
                Route::get('reject_status/{id}', [AdminMGT14Controller::class, 'reject'])->name('reject');
            });

            Route::middleware('hasPermission:sm offer request')->name('offerrequest.')->prefix('offerrequest')->group(function () {
                Route::get('pending', [AdminOfferRequestController::class, 'pending'])->name('pending');
                Route::get('approved', [AdminOfferRequestController::class, 'approved'])->name('approved');
                Route::get('rejected', [AdminOfferRequestController::class, 'rejected'])->name('rejected');
                Route::get('approve_status/{id}', [AdminOfferRequestController::class, 'approve'])->name('approve');
                Route::get('reject_status/{id}', [AdminOfferRequestController::class, 'reject'])->name('reject');
                Route::post('approve', [AdminOfferRequestController::class, 'approveSave'])->name('approveSave');
            });

            Route::middleware('hasPermission:sm pas3')->name('pas3.')->prefix('pas3')->group(function () {
                Route::get('pending', [AdminPas3Controller::class, 'pending'])->name('pending');
                Route::get('approved', [AdminPas3Controller::class, 'approved'])->name('approved');
                Route::get('rejected', [AdminPas3Controller::class, 'rejected'])->name('rejected');
                Route::get('approve_status/{id}', [AdminPas3Controller::class, 'approve'])->name('approve');
                Route::get('reject_status/{id}', [AdminPas3Controller::class, 'reject'])->name('reject');
            });

            Route::middleware('hasPermission:sm mis')->name('mis.')->prefix('mis')->group(function () {
                Route::get('create', [AdminStartupMISController::class, 'create'])->name('create');
                Route::get('pending', [AdminStartupMISController::class, 'pending'])->name('pending');
                Route::get('approved', [AdminStartupMISController::class, 'approved'])->name('approved');
                Route::get('rejected', [AdminStartupMISController::class, 'rejected'])->name('rejected');

                Route::post('save', [AdminStartupMISController::class, 'store'])->name('store');
                Route::get('status/{id}/{status}', [AdminStartupMISController::class, 'status'])->name('status');
                Route::get('delete/{id}', [AdminStartupMISController::class, 'delete'])->name('delete');
            });
            Route::middleware('hasPermission:sm live pitch')->resource('livepitch', AdminStartupLivepitchController::class);

            Route::get('edit-round/{round_id}', [StartupController::class, 'editRoundDetails'])->name('editRoundDetails');
            Route::post('update-round', [StartupController::class, 'updateRoundDetails'])->name('updateRoundDetails');
            Route::post('update-cms', [StartupController::class, 'updateCMS'])->name('updatecms');
        });

        Route::middleware('hasPermission:company')->name('coupon.')->prefix('coupon')->group(function () {
            Route::get('list', [AdminCouponController::class, 'list'])->name('list');
            Route::get('create', [AdminCouponController::class, 'create'])->name('create');
            Route::get('edit/{uuid}', [AdminCouponController::class, 'edit'])->name('edit');
            Route::post('store', [AdminCouponController::class, 'store'])->name('store');
            Route::put('update/{uuid}', [AdminCouponController::class, 'update'])->name('update');
            Route::delete('delete/{uuid}', [AdminCouponController::class, 'delete'])->name('delete');

            Route::get('settings', [AdminCouponController::class, 'settings'])->name('settings');
            Route::post('settings', [AdminCouponController::class, 'saveSettings'])->name('settings.save');
            Route::get('assign', [AdminCouponController::class, 'assignPage'])->name('assign');
            Route::post('assign', [AdminCouponController::class, 'assignToInvestors'])->name('assign.store');
        });

        Route::middleware('hasPermission:company')->name('bse-holiday.')->prefix('bse-holiday')->group(function () {
            Route::get('', [BseHolidayController::class, 'index'])->name('index');
            Route::get('list', [BseHolidayController::class, 'list'])->name('list');
            Route::get('create', [BseHolidayController::class, 'create'])->name('create');
            Route::post('store', [BseHolidayController::class, 'store'])->name('store');
            Route::get('edit/{uuid}', [BseHolidayController::class, 'edit'])->name('edit');
            Route::put('update/{uuid}', [BseHolidayController::class, 'update'])->name('update');
            Route::delete('delete/{uuid}', [BseHolidayController::class, 'destroy'])->name('delete');
            Route::patch('toggle-active/{uuid}', [BseHolidayController::class, 'toggleActive'])->name('toggleActive');
            Route::get('holidays-by-year', [BseHolidayController::class, 'getHolidaysByYear'])->name('getByYear');
            Route::post('bulk-import', [BseHolidayController::class, 'bulkImport'])->name('bulkImport');
        });

        Route::middleware('hasPermission:ai_autowork')->name('ai-autowork.')->prefix('ai-autowork')->group(function () {
            Route::get('', [AiAutoworkController::class, 'overview'])->name('overview');
            Route::get('company-ingest', [AiCompanyIngestAdminController::class, 'inbox'])->name('company-ingest.inbox');
            Route::get('company-ingest/guide', [AiCompanyIngestAdminController::class, 'guide'])->name('company-ingest.guide');
            Route::get('company-ingest/{uuid}', [AiCompanyIngestAdminController::class, 'review'])->name('company-ingest.review');
            Route::post('company-ingest/{uuid}', [AiCompanyIngestAdminController::class, 'update'])->name('company-ingest.update');
            Route::post('company-ingest/{uuid}/approve', [AiCompanyIngestAdminController::class, 'approve'])->name('company-ingest.approve');
            Route::post('company-ingest/{uuid}/reject', [AiCompanyIngestAdminController::class, 'reject'])->name('company-ingest.reject');
            Route::post('company-ingest/{uuid}/reopen', [AiCompanyIngestAdminController::class, 'reopen'])->name('company-ingest.reopen');
            Route::delete('company-ingest/{uuid}', [AiCompanyIngestAdminController::class, 'destroy'])->name('company-ingest.destroy');
            Route::get('share-prices', [AiAutoworkController::class, 'sharePricesStub'])->name('share-prices.stub');
            Route::get('share-prices/guide', [AiAutoworkController::class, 'sharePricesGuide'])->name('share-prices.guide');
        });

        Route::middleware('hasPermission:company')->name('company-deals.')->prefix('company-deals')->group(function () {
            Route::get('', [CompanyDealController::class, 'index'])->name('index');
            Route::get('list', [CompanyDealController::class, 'list'])->name('list');
            Route::get('create', [CompanyDealController::class, 'create'])->name('create');
            Route::post('store', [CompanyDealController::class, 'store'])->name('store');
            Route::get('edit/{uuid}', [CompanyDealController::class, 'edit'])->name('edit');
            Route::put('update/{uuid}', [CompanyDealController::class, 'update'])->name('update');
            Route::delete('delete/{uuid}', [CompanyDealController::class, 'destroy'])->name('delete');
        });

        Route::middleware('hasPermission:company')->name('company.')->prefix('company')->group(function () {
            Route::get('view/{uuid}', [CompanyController::class, 'view'])->name('view');
            Route::get('create', [CompanyController::class, 'create'])->name('create');
            Route::get('update-share-price', [CompanyController::class, 'updateSharePrice'])->name('updateSharePrice');
            Route::get('import', [CompanyController::class, 'import'])->name('import');
            Route::get('list', [CompanyController::class, 'list'])->name('list');
            Route::get('companyList', [CompanyController::class, 'getCompanies'])->name('companyList');
            Route::get('pending-seller', [CompanyController::class, 'pendingSellerCompanies'])->name('pendingSeller');
            Route::post('approve-seller/{uuid}', [CompanyController::class, 'approveSellerCompany'])->name('approveSeller');
            Route::post('reject-seller/{uuid}', [CompanyController::class, 'rejectSellerCompany'])->name('rejectSeller');

            Route::get('edit/{uuid}', [CompanyController::class, 'edit'])->name('edit');
            Route::get('delete/{uuid}', [CompanyController::class, 'delete'])->name('delete');
            Route::get('promoter/{uuid}', [CompanyController::class, 'promoter'])->name('promoter');
            Route::get('peer-ratio/{uuid}', [CompanyController::class, 'peerRatio'])->name('peerRatio');
            Route::get('share-price/{uuid}', [CompanyController::class, 'sharePrice'])->name('sharePrice');
            Route::delete('share-price/{uuid}/{id}', [CompanyController::class, 'sharePriceDelete'])->name('sharePriceDelete');
            Route::get('share-holders/{uuid}', [CompanyController::class, 'shareHolders'])->name('shareHolders');

            Route::get('financials-download/{uuid}', [CompanyController::class, 'financialsDownload'])->name('financialsDownload');
            Route::get('custom-data/{uuid}', [CompanyController::class, 'customData'])->name('customData');
            Route::get('event/{uuid}', [CompanyController::class, 'event'])->name('event');
            Route::get('news/{uuid}', [CompanyController::class, 'news'])->name('news');
            //excel download
            Route::get('download', [CompanyController::class, 'download'])->name('download');

            Route::post('upload-company-excel', [CompanyController::class, 'uploadCompanyExcel'])->name('ExcelDataSave');
            Route::post('update-share-price', [CompanyController::class, 'updateSharePriceSave'])->name('updateSharePriceSave');
            Route::post('process-ocr-financial-data', [CompanyController::class, 'processOcrFinancialData'])->name('processOcrFinancialData');
            Route::post('import', [CompanyController::class, 'importSave'])->name('import.save');
            Route::post('save', [CompanyController::class, 'save'])->name('save');
            Route::post('update', [CompanyController::class, 'update'])->name('update');
            Route::post('promoter', [CompanyController::class, 'promoterSave'])->name('promoterSave');
            Route::post('peer-ratio', [CompanyController::class, 'peerRatioSave'])->name('peerRatioSave');
            Route::post('share-price', [CompanyController::class, 'sharePriceSave'])->name('sharePriceSave');
            Route::post('share-holders', [CompanyController::class, 'shareHoldersSave'])->name('shareHoldersSave');
            Route::post('custom-data', [CompanyController::class, 'customDataSave'])->name('customDataSave');
            Route::post('event', [CompanyController::class, 'eventSave'])->name('eventSave');
            Route::post('news', [CompanyController::class, 'newsSave'])->name('newsSave');

            Route::get('get-financial-data', [CompanyController::class, 'getFinancialData'])
                ->name('get-financial-data');

            Route::post('update-financial-data', [CompanyController::class, 'updateFinancialData'])
                ->name('update-financial-data');

            Route::post('add-financial-year', [CompanyController::class, 'addFinancialYear'])
                ->name('add-financial-year');

            // Add these routes to your web.php or admin routes
            Route::post('/{id}/apply-split', [CompanyController::class, 'applySplit'])
                ->name('apply-split');

            Route::post('/{id}/revert-split', [CompanyController::class, 'revertSplit'])
                ->name('revert-split');

            Route::get('/{id}/split-history', [CompanyController::class, 'getSplitHistory'])
                ->name('split-history');

            // Route::post('financials-update', [CompanyController::class, 'updateFinancials'])->name('financials.update');
            Route::name('export.')->prefix('export')->group(function () {
                Route::get('', [CompanyController::class, 'exportView'])->name('view');
                Route::post('pdf', [CompanyController::class, 'exportPdf'])->name('pdf');
                Route::post('send-partner', [CompanyController::class, 'sendToPartner'])->name('send-partner');
                Route::post('send-demo', [CompanyController::class, 'sendToDemo'])->name('send-demo');
                Route::get('get-companies', [CompanyController::class, 'getCompaniesForVerification'])->name('get-companies');
            });
        });

        Route::middleware('hasPermission:seller')->name('preiposeller.')->prefix('seller')->group(function () {
            Route::get('create', [AdminSellerMasterController::class, 'create'])->name('create');
            Route::get('list', [AdminSellerMasterController::class, 'list'])->name('list');
            Route::post('save', [AdminSellerMasterController::class, 'save'])->name('save');
            Route::get('edit/{uuid}', [AdminSellerMasterController::class, 'edit'])->name('edit');
            Route::post('update/{uuid}', [AdminSellerMasterController::class, 'update'])->name('update');
            Route::delete('delete/{uuid}', [AdminSellerMasterController::class, 'delete'])->name('delete');
        });

        Route::middleware('hasPermission:portfolio')->name('portfolioInsights.')->prefix('portfolio-insights')->group(function () {
            Route::name('startupPortfolio.')->prefix('startup-portfolio')->group(function () {
                Route::get('list', [AdminPortfolioController::class, 'list'])->name('list');
                Route::get('view/{id}', [AdminPortfolioController::class, 'view'])->name('view');
            });
            Route::name('preIpoPortfolio.')->prefix('pre-ipo-portfolio')->group(function () {
                Route::get('list', [AdminPortfolioController::class, 'preIpoList'])->name('list');
            });
            Route::get('porfolio-upload-request', [AdminPortfolioController::class, 'portfolioUploadRequest'])->name('portfolioUploadRequest');
        });



        Route::middleware('hasPermission:masters')->name('master.')->prefix('master')->group(function () {
            Route::resource('supported-countries', MasterSupportedCountriesController::class);
            Route::resource('country', MasterCountryController::class);
            Route::resource('state', MasterStateController::class);
            Route::resource('city', MasterCityController::class);
            Route::resource('bank', MasterBankController::class);
            Route::resource('bank-account-type', MasterBankAccountTypeController::class);
            Route::resource('family-relation', MasterFamilyRelationController::class);
            Route::resource('investor-type', MasterInvestorTypeController::class);
            Route::resource('startup-round-type', MasterStartupRoundTypeController::class);
            Route::resource('instrument-type', MasterInstrumentTypeController::class);
            Route::resource('industry', MasterIndustryController::class);
            Route::resource('sector', MasterSectorController::class);
            Route::resource('project', MasterProjectController::class);
            Route::resource('social-media', MasterSocialMediaLinkController::class);
            Route::name('headertoken.')->prefix('headertoken')->group(function () {
                Route::get('list', [HeaderTokenController::class, 'list'])->name('list');
                Route::get('create', [HeaderTokenController::class, 'create'])->name('create');
                Route::get('delete/{id}', [HeaderTokenController::class, 'delete'])->name('delete');
            });
            Route::prefix('find-cml')->name('findcml.')->group(function () {
                Route::get('list', [FindCmlController::class, 'list'])->name('list');
                Route::get('create', [FindCmlController::class, 'create'])->name('create');
                Route::post('store', [FindCmlController::class, 'store'])->name('store');
                Route::get('edit/{uuid}', [FindCmlController::class, 'edit'])->name('edit');
                Route::put('update/{uuid}', [FindCmlController::class, 'update'])->name('update');
                Route::delete('delete/{id}', [FindCmlController::class, 'delete'])->name('delete');
            });
        });

        Route::middleware('hasPermission:cms management')->name('cms.')->prefix('cms')->group(function () {
            Route::resource('blog', MasterBlogController::class);
            Route::resource('avtar', MasterAvtarController::class);
            Route::resource('website-social-media', MasterWebsiteSocialMediaController::class);
            Route::resource('pages', MasterPagesController::class);
            Route::resource('manage-info-icon', MasterManageInfoIconController::class);
            Route::resource('faqs', MasterFaqsController::class);

            Route::name('media.')->prefix('media')->group(function () {
                Route::get('create', [AdminWebsiteController::class, 'create'])->name('create');
                Route::get('list', [AdminWebsiteController::class, 'list'])->name('list');

                Route::post('store', [AdminWebsiteController::class, 'store'])->name('store');
                Route::delete('delete/{id}', [AdminWebsiteController::class, 'delete'])->name('destroy');
            });
        });

        Route::middleware('hasPermission:broadcast')->name('broadcast.')->prefix('broadcast')->group(function () {
            Route::name('whatsapp.')->prefix('whatsapp')->group(function () {
                Route::get('list', [AdminWhatsappBroadcastController::class, 'list'])->name('list');
                Route::get('create', [AdminWhatsappBroadcastController::class, 'create'])->name('create');
                Route::get('view/{item}', [AdminWhatsappBroadcastController::class, 'view'])->name('view');

                // Used by broadcast create page to auto-select investors who invested in a startup
                Route::get('invested-investors', [AdminWhatsappBroadcastController::class, 'investedInvestors'])->name('investedInvestors');

                Route::get('download', [AdminWhatsappBroadcastController::class, 'downloadGuestTemplate'])->name('downloadGuestTemplate');
                Route::post('upload-guest-excel', [AdminWhatsappBroadcastController::class, 'uploadGuestExcel'])->name('uploadGuestExcel');
                Route::post('store', [AdminWhatsappBroadcastController::class, 'store'])->name('store');
                Route::post('resend/{item}', [AdminWhatsappBroadcastController::class, 'resend'])->name('resend');
                Route::post('cancel/{item}', [AdminWhatsappBroadcastController::class, 'cancel'])->name('cancel');
            });
            Route::get('notification-list', [AdminWhatsappBroadcastController::class, 'notificationlist'])->name('notificationlist');
            Route::name('pushNotification.')->prefix('push-notification')->group(function () {
                Route::get('list', [AdminPushNotificationController::class, 'list'])->name('list');
                Route::get('create', [AdminPushNotificationController::class, 'create'])->name('create');
                Route::get('view/{item}', [AdminPushNotificationController::class, 'view'])->name('view');
                Route::post('store', [AdminPushNotificationController::class, 'store'])->name('store');
            });
        });

        Route::name('news-assignment.')->prefix('news-assignment')->group(function () {
            Route::get('', [\App\Http\Controllers\Web\Admin\NewsAssignmentController::class, 'index'])->name('index');
            Route::get('company/{uuid}', [\App\Http\Controllers\Web\Admin\NewsAssignmentController::class, 'newsList'])->name('newsList');
            Route::post('news/{id}/change-company', [\App\Http\Controllers\Web\Admin\NewsAssignmentController::class, 'updateNewsCompany'])->name('updateNewsCompany');
            Route::post('news/bulk-delete', [\App\Http\Controllers\Web\Admin\NewsAssignmentController::class, 'deleteBulkNews'])->name('deleteBulkNews');
            Route::delete('news/{id}/delete', [\App\Http\Controllers\Web\Admin\NewsAssignmentController::class, 'deleteNews'])->name('deleteNews');
        });



        Route::name('systemConfiguration.')->prefix('system-configuration')->group(function () {
            Route::middleware('hasPermission:system settings')->name('systemSettings.')->prefix('system-settings')->group(function () {
                Route::get('', [SettingController::class, 'index'])->name('get');
                Route::post('', [SettingController::class, 'store'])->name('post');
            });
            Route::resource('apiclient', AdminApiClientsController::class);
            Route::middleware('hasPermission:app version control')->name('appVersionControl.')->prefix('app-version-control')->group(function () {
                Route::get('list', [AppVersionController::class, 'list'])->name('list');
                Route::get('create', [AppVersionController::class, 'create'])->name('create');
                Route::post('store', [AppVersionController::class, 'store'])->name('store');
            });
            Route::prefix('app-build')->name('appbuild.')->group(function () {
                Route::get('list', [AppBuildController::class, 'list'])->name('list');
                Route::get('create', [AppBuildController::class, 'create'])->name('create');
                Route::post('store', [AppBuildController::class, 'store'])->name('store');
                Route::get('download/{uuid}', [AppBuildController::class, 'download'])->name('download');
                Route::delete('delete/{id}', [AppBuildController::class, 'delete'])->name('delete');
            });
        });


        Route::post('logout', [AdminAuthController::class, 'destroy'])->name('logout');

        Route::middleware('hasPermission:master admin')->name('user-management.')->group(function () {
            Route::resource('/user-management/users', AdminDashboardController::class);
            Route::resource('/user-management/roles', AdminDashboardController::class);
            Route::resource('/user-management/permissions', AdminDashboardController::class);
        });

        Route::middleware('hasPermission:master admin')->name('manager.')->prefix('manager')->group(function () {
            Route::get('list', [AdminManagerController::class, 'list'])->name('list');
            Route::get('create', [AdminManagerController::class, 'create'])->name('create');
            Route::get('update/{uuid}', [AdminManagerController::class, 'edit'])->name('edit');
            Route::post('store', [AdminManagerController::class, 'store'])->name('store');
            Route::put('update/{uuid}', [AdminManagerController::class, 'update'])->name('update');
            Route::delete('delete/{id}', [AdminManagerController::class, 'delete'])->name('destroy');
        });

        Route::name('myprofile.')->prefix('profile')->group(function () {
            Route::get('view', [MyProfileController::class, 'view'])->name('view');
            Route::get('update', [MyProfileController::class, 'edit'])->name('edit');
            Route::put('update', [MyProfileController::class, 'update'])->name('update');
        });

        Route::name('manual.')->prefix('manual')->group(function () {
            Route::middleware('hasPermission:manual secondary market')->name('secondary-market.')->prefix('secondary-market')->group(function () {
                Route::get('create', [AdminManualController::class, 'create'])->name('create');
                Route::post('save', [AdminManualController::class, 'save'])->name('save');
            });

            Route::middleware('hasPermission:manual preipo transaction')->name('preipo-transaction.')->prefix('preipo-transaction')->group(function () {
                Route::get('create', [AdminManualController::class, 'preIpoCreate'])->name('create');
                Route::post('save', [AdminManualController::class, 'preIpoSave'])->name('save');
            });

            Route::middleware('hasPermission:manual secondary transaction')->name('secondary-transaction.')->prefix('secondary-transaction')->group(function () {
                Route::get('create', [AdminManualController::class, 'secondaryCreate'])->name('create');
                Route::post('save', [AdminManualController::class, 'secondarySave'])->name('save');
            });

            Route::middleware('hasPermission:manual primary transaction')->name('primary-transaction.')->prefix('primary-transaction')->group(function () {
                Route::get('create', [AdminManualController::class, 'primaryCreate'])->name('create');
                Route::post('save', [AdminManualController::class, 'primarySave'])->name('save');
            });
        });

        // EXTRAS
        Route::middleware('hasPermission:investor notifications')->name('notification.')->prefix('notification')->group(function () {
            Route::name('investor.')->prefix('investor')->group(function () {
                Route::get('create', [AdminNotificationController::class, 'create'])->name('create');
                Route::post('store', [AdminNotificationController::class, 'store'])->name('store');
            });
        });
    });

    // Auth Routes
    Route::group(['middleware' => [App\Http\Middleware\AdminRedirectIfAuthenticatedMiddleware::class]], function () {
        Route::get('login', [AdminAuthController::class, 'create'])->name('login');
        Route::get('register', [AdminAuthController::class, 'create'])->name('register');
        Route::get('forgot-password', [AdminPasswordResetController::class, 'create'])->name('password.request');
        Route::get('reset-password/{token}', [AdminAuthController::class, 'create'])->name('password.reset');

        Route::post('login', [AdminAuthController::class, 'store']);
        Route::post('register', [AdminAuthController::class, 'store']);
        Route::post('forgot-password', [AdminAuthController::class, 'store'])->name('password.email');
        Route::post('reset-password', [AdminAuthController::class, 'store'])->name('password.update');
    });
});

