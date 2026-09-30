<?php

use App\Helpers\CommonHelper;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\GuestDeviceTokenController;
use App\Http\Controllers\Api\MasterController as ApiMasterController;
use App\Http\Controllers\Api\PrivateDealsController;
use App\Http\Controllers\Api\UploadAndParseController;
use App\Http\Controllers\Api\V1\Business\CommonController as ApiV1PartnerCommonController;
use App\Http\Controllers\Api\V1\Business\CommonKycController;
use App\Http\Controllers\Api\V1\Investor\LoginController as ApiV1InvestorLoginController;
use App\Http\Controllers\Api\V1\Startup\LoginController as ApiV1StartupLoginController;
use App\Http\Controllers\Api\V1\Business\LoginController as ApiV1BusinessLoginController;
use App\Http\Controllers\Api\V1\Investor\CommonController as ApiV1InvestorCommonController;
use App\Http\Controllers\Api\V1\Investor\CommonKycController as ApiV1InvestorCommonKycController;
use App\Http\Controllers\Api\V1\Startup\StartupController as ApiV1StartupController;
use App\Http\Controllers\Api\V2\Business\CommonController as ApiV2BusinessCommonController;
use App\Http\Controllers\Api\V2\Business\CompanyController as ApiV2BusinessCompanyController;
use App\Http\Controllers\Api\V2\Business\EnquiryController as ApiV2BusinessEnquiryController;
use App\Http\Controllers\Api\V2\Business\KycController as ApiV2BusinessKycController;
use App\Http\Controllers\Api\V2\Business\PortfolioController as ApiV2BusinessPortfolioController;
use App\Http\Controllers\Api\V2\Investor\AuthController as ApiV2InvestorAuthController;
use App\Http\Controllers\Api\V2\Investor\CommonController as ApiV2InvestorCommonController;
use App\Http\Controllers\Api\V2\Investor\PriceAlertController as ApiV2InvestorPriceAlertController;
use App\Http\Controllers\Api\V2\Investor\TransactionCalculationController as ApiV2TransactionCalculationController;
use App\Http\Controllers\Api\V2\Seller\LoginController as ApiV2SellerLoginController;
use App\Http\Controllers\Api\V2\Seller\CompanyController as ApiV2SellerCompanyController;
use App\Http\Controllers\Api\V2\Seller\PreIpoTransactionController as ApiV2SellerPreIpoTransactionController;
use App\Http\Controllers\Api\V2\Seller\SellEnquiryController as ApiV2SellerSellEnquiryController;
use App\Http\Controllers\Api\V2\Seller\DashboardController as ApiV2SellerDashboardController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\ThirdParty\StartupController as ThirdPartyStartupController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\ApiBasicAuthMiddleware;
use App\Http\Middleware\ApiHeaderAuthMiddleware;
use App\Http\Middleware\ThirdPartyApiAuthMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::group(['middleware' => [ApiHeaderAuthMiddleware::class]], function () {
    // Route::get('test', [TestController::class, 'test']);

    Route::post('verify-mobile-number', [ApiMasterController::class, 'verifyMobileNumber']);

    // Guest (non-authenticated) device token registration
    Route::post('guest/device-token', [GuestDeviceTokenController::class, 'store']);

    Route::prefix('v1')->name('v1.')->group(function () {
        Route::prefix('investor')->name('investor.')->group(function () {

            Route::prefix('auth')->name('auth.')->group(function () {
                Route::prefix('register')->name('register.')->group(function () {
                    Route::post('validate-send-code', [ApiV1InvestorLoginController::class, 'register'])->name('validate');
                    Route::post('verify-code', [ApiV1InvestorLoginController::class, 'verifyOtp'])->name('verifyOtp');
                    Route::post('set-pin', [ApiV1InvestorLoginController::class, 'setMpin'])->name('setMpin');
                });
            });


            Route::post('login', [ApiV1InvestorLoginController::class, 'login']);

            Route::prefix('forgot')->name('forgot.')->group(function () {
                Route::post('', [ApiV1InvestorLoginController::class, 'forgot']);
                Route::post('resend-otp', [ApiV1InvestorLoginController::class, 'resendOtp']);
                Route::post('verify-otp', [ApiV1InvestorLoginController::class, 'verifyOtp']);
                Route::post('change-password', [ApiV1InvestorLoginController::class, 'changePassword']);
            });

            Route::get('home', [ApiV1InvestorCommonController::class, 'getHome']);
            Route::get('get-preipo-home', [ApiV1InvestorCommonController::class, 'getPreipoHome']);
            Route::get('startup', [ApiV1InvestorCommonController::class, 'getStartup']);
            Route::get('startup-list', [ApiV1InvestorCommonController::class, 'getStartups']);



            Route::middleware('auth:investor-api-guard')->group(function () {

                Route::prefix('auth')->group(function () {
                    Route::get('home', [ApiV1InvestorCommonController::class, 'getHome']);
                    Route::get('get-preipo-home', [ApiV1InvestorCommonController::class, 'getPreipoHome']);
                    Route::get('startup', [ApiV1InvestorCommonController::class, 'getStartup']);
                    Route::get('startup-list', [ApiV1InvestorCommonController::class, 'getStartups']);
                });

                Route::prefix('company')->name('company.')->group(function () {
                    Route::get('market', [ApiV1InvestorCommonController::class, 'companyMarket']);
                    Route::get('detail', [ApiV1InvestorCommonController::class, 'companyDetail']);
                    Route::get('news', [ApiV1InvestorCommonController::class, 'news']);
                    Route::prefix('favorite')->name('favorite.')->group(function () {
                        Route::get('', [ApiV1InvestorCommonController::class, 'getFavoriteCompany']);
                        Route::post('', [ApiV1InvestorCommonController::class, 'postFavoriteCompany']);
                    });
                });

                Route::prefix('pre-ipo')->name('preipo.')->group(function () {
                    Route::get('transaction', [ApiV1InvestorCommonController::class, 'preIpoTransactions']);
                    Route::get('transaction/detail', [ApiV1InvestorCommonController::class, 'preIpoTransactionsDetails']);
                    Route::get('sell-transaction', [ApiV1InvestorCommonController::class, 'preIpoSellTransactions']);
                    Route::post('buy', [ApiV1InvestorCommonController::class, 'preIpoBuy']);
                    Route::post('sell', [ApiV1InvestorCommonController::class, 'preIpoSell']);
                    Route::post('cancel-order', [ApiV1InvestorCommonController::class, 'cancelOrder']);
                });

                Route::get('dashboard', [ApiV1InvestorCommonController::class, 'dashboard']);
                Route::get('dashboard-pre-ipo', [ApiV1InvestorCommonController::class, 'dashboardPreIpo']);
                Route::get('document', [ApiV1InvestorCommonController::class, 'documents']);
                Route::get('mis', [ApiV1InvestorCommonController::class, 'mis']);
                Route::get('portfolio', [ApiV1InvestorCommonController::class, 'portfolio']);
                Route::get('portfolio-status', [ApiV1InvestorCommonController::class, 'portfolioStatus']);
                Route::get('portfolio-details', [ApiV1InvestorCommonController::class, 'portfolioDetails']);
                Route::get('portfolio-pre-ipo', [ApiV1InvestorCommonController::class, 'portfolioPreIpo']);
                Route::get('portfolio-pre-ipo-detail', [ApiV1InvestorCommonController::class, 'portfolioPreIpoDetail']);
                Route::get('get-startup-lite', [ApiV1InvestorCommonController::class, 'getStartupLiteNew']);
                Route::get('bank-mandate', [ApiV1InvestorCommonController::class, 'bankMandates']);
                Route::get('notification', [ApiV1InvestorCommonController::class, 'notifications']);
                Route::post('change-password', [ApiV1InvestorCommonController::class, 'changePassword']);

                //Primary Transaction routes
                Route::get('recent-transaction', [ApiV1PartnerCommonController::class, 'recentTransaction']);
                Route::get('primary-transaction-list', [ApiV1InvestorCommonController::class, 'transactionList']);
                Route::get('primary-transaction-detail', [ApiV1InvestorCommonController::class, 'transactionDetails']);

                // Secondary Investment routes
                Route::prefix('secondary-invest')->name('secondary_invest.')->group(function () {
                    Route::post('sell-now', [ApiV1InvestorCommonController::class, 'secSellNow']);

                    Route::prefix('transactions')->name('transactions.')->group(function () {
                        Route::get('list', [ApiV1InvestorCommonController::class, 'secTransaction']);
                        Route::get('detail', [ApiV1InvestorCommonController::class, 'secTransactionDetails']);

                        Route::post('rofr-status', [ApiV1InvestorCommonController::class, 'secRofrStatus']);
                        Route::post('share-receipt-upload', [ApiV1InvestorCommonController::class, 'secUploadShareReceipt']);
                        Route::post('share-receipt-approve', [ApiV1InvestorCommonController::class, 'secApproveShareReceipt']);

                        Route::post('payment-received', [ApiV1InvestorCommonController::class, 'secPaymentReceived']);
                    });

                    Route::get('market', [ApiV1InvestorCommonController::class, 'secMarket']);
                    Route::post('buy-now', [ApiV1InvestorCommonController::class, 'secBuyNow']);

                    // Route::post('payment-receipt-upload', [ApiV1InvestorCommonController::class, 'secUploadPaymentReceipt']);

                    // Route::get('sell-request', [ApiV1InvestorCommonController::class, 'secSellRequestList']);

                    // Route::get('related-oppotunities', [ApiV1InvestorCommonController::class, 'secReletedOppotunitiesList']);
                });

                Route::prefix('primary-invest')->name('primary_invest.')->group(function () {
                    Route::get('transaction', [ApiV1InvestorCommonController::class, 'transaction']);
                    Route::post('invest-now', [ApiV1InvestorCommonController::class, 'commitNow']);
                    Route::post('upload-payment-receipt', [ApiV1InvestorCommonController::class, 'uploadPaymentReceipt']);
                });

                Route::prefix('demat')->name('demat.')->group(function () {
                    Route::get('', [ApiV1InvestorCommonController::class, 'dematGet']);
                    Route::post('', [ApiV1InvestorCommonController::class, 'dematPost']);
                });

                Route::prefix('favorite')->name('favorite.')->group(function () {
                    Route::get('', [ApiV1InvestorCommonController::class, 'favoriteGet']);
                    Route::post('', [ApiV1InvestorCommonController::class, 'favoritePost']);
                });

                Route::prefix('family')->name('family.')->group(function () {
                    Route::get('', [ApiV1InvestorCommonController::class, 'familyGet']);
                    Route::post('', [ApiV1InvestorCommonController::class, 'familyPost'])->name('create');
                });

                Route::prefix('kyc')->name('kyc.')->group(function () {
                    Route::get('', [ApiV1InvestorCommonController::class, 'kycGet']);
                    Route::post('', [ApiV1InvestorCommonController::class, 'kycPost']);

                    Route::get('get-ekyc-token', [ApiV1InvestorCommonController::class, 'getEkycToken']);
                    Route::post('get-ekyc-data', [ApiV1InvestorCommonController::class, 'getEkycData']);

                    Route::post('upload-bank-account', [ApiV1InvestorCommonController::class, 'uploadBankAccount']);
                    Route::post('upload-demat', [ApiV1InvestorCommonController::class, 'uploadDemat']);
                    Route::post('upload-pan-details', [ApiV1InvestorCommonController::class, 'uploadPanDetails']);
                    Route::post('upload-aadhar-details', [ApiV1InvestorCommonController::class, 'uploadAadharDetails']);

                    Route::prefix('upload')->name('upload.')->group(function () {
                        Route::post('demat-bank', [ApiV1InvestorCommonKycController::class, 'dematBank'])->name('dematBank');
                        Route::post('aadhar-pan-details', [ApiV1InvestorCommonKycController::class, 'aadharPanStore'])->name('aadharPanStore');
                        Route::post('pan', [ApiV1InvestorCommonKycController::class, 'pan'])->name('pan');
                        Route::post('bank-account-details', [ApiV1InvestorCommonKycController::class, 'bankAccountDetails'])->name('bankAccountDetails');
                    });
                });

                Route::prefix('profile')->name('profile.')->group(function () {
                    Route::get('get', [ApiV1InvestorCommonController::class, 'getProfile']);
                    Route::post('save', [ApiV1InvestorCommonController::class, 'saveProfile']);

                    Route::post('photo', [ApiV1InvestorCommonController::class, 'updateProfilePhoto']);
                    Route::post('photo-remove', [ApiV1InvestorCommonController::class, 'removeProfilePhoto']);
                    Route::post('switch-profile', [ApiV1InvestorCommonController::class, 'switchProfile']);
                });

                Route::prefix('aif')->name('aif.')->group(function () {
                    Route::get('get', [ApiV1InvestorCommonController::class, 'getAif']);
                    Route::post('submit', [ApiV1InvestorCommonController::class, 'aifSubmit']);
                });



                Route::post('add-portfolio', [ApiV1InvestorCommonController::class, 'addPortfolio']);
                Route::get('company-startup-list', [ApiV1InvestorCommonController::class, 'getStartupCompany']);


                Route::post('contact-us', [ApiMasterController::class, 'contactFromApp']);
                Route::post('delete-account', [ApiV1InvestorLoginController::class, 'deleteAccount']);

                Route::post('check-mpin', [ApiV1InvestorLoginController::class, 'checkMpin']);
                Route::post('logout', [ApiV1InvestorLoginController::class, 'logout']);
            });


            Route::post('register-inquiry', [ApiV1InvestorLoginController::class, 'registerInquiry']);
        });

        Route::prefix('startup')->name('startup.')->group(function () {
            Route::post('login', [ApiV1StartupLoginController::class, 'login']);
            Route::middleware('auth:startup-api-guard')->group(function () {
                Route::get('dashboard', [ApiV1StartupLoginController::class, 'dashboard']);
                Route::post('logout', [ApiV1StartupLoginController::class, 'logout']);
            });
        });

        Route::prefix('business')->name('business.')->group(function () {

            Route::get('home', [ApiV1InvestorCommonController::class, 'getHome']);
            Route::get('startup', [ApiV1InvestorCommonController::class, 'getStartup']);
            Route::get('startup-list', [ApiV1InvestorCommonController::class, 'getStartups']);

            Route::post('login', [ApiV1BusinessLoginController::class, 'login']);

            Route::name('forgot.')->prefix('forgot-password')->group(function () {
                Route::post('', [ApiV1PartnerCommonController::class, 'forgot']);
                Route::post('resend-otp', [ApiV1PartnerCommonController::class, 'resendOtp']);
                Route::post('verify-otp', [ApiV1PartnerCommonController::class, 'verifyOtp']);
                Route::post('change-password', [ApiV1PartnerCommonController::class, 'changePassword']);
            });

            Route::middleware('auth:partner-api-guard')->group(function () {
                Route::get('dashboard', [ApiV1PartnerCommonController::class, 'dashboard']);
                Route::get('invested-startup-mis', [ApiV1PartnerCommonController::class, 'investedStartupMIS']);
                Route::get('dashboard-pre-ipo', [ApiV1PartnerCommonController::class, 'dashboardPreIpo']);
                Route::get('primary-transactions', [ApiV1PartnerCommonController::class, 'transactions']);

                Route::prefix('primary-invest')->name('primary_invest.')->group(function () {
                    Route::get('transaction', [ApiV1InvestorCommonController::class, 'transaction']);
                    Route::post('invest-now', [ApiV1InvestorCommonController::class, 'commitNow']);
                });

                //Primary Transaction routes
                Route::get('primary-transaction-list', [ApiV1PartnerCommonController::class, 'transactionList']);
                Route::get('primary-transaction-detail', [ApiV1PartnerCommonController::class, 'transactionDetails']);

                // Secondary Investment routes
                Route::prefix('secondary-invest')->name('secondary_invest.')->group(function () {
                    Route::post('sell-now', [ApiV1InvestorCommonController::class, 'secSellNow']);

                    Route::prefix('transactions')->name('transactions.')->group(function () {
                        Route::get('list', [ApiV1PartnerCommonController::class, 'secTransaction']);
                        Route::get('detail', [ApiV1PartnerCommonController::class, 'secTransactionDetails']);

                        Route::post('rofr-status', [ApiV1InvestorCommonController::class, 'secRofrStatus']);
                        Route::post('share-receipt-upload', [ApiV1InvestorCommonController::class, 'secUploadShareReceipt']);
                        Route::post('share-receipt-approve', [ApiV1InvestorCommonController::class, 'secApproveShareReceipt']);

                        Route::post('payment-received', [ApiV1InvestorCommonController::class, 'secPaymentReceived']);
                    });

                    Route::get('market', [ApiV1InvestorCommonController::class, 'secMarket']);
                    Route::post('buy-now', [ApiV1InvestorCommonController::class, 'secBuyNow']);

                    // Route::post('payment-receipt-upload', [ApiV1InvestorCommonController::class, 'secUploadPaymentReceipt']);

                    // Route::get('sell-request', [ApiV1InvestorCommonController::class, 'secSellRequestList']);

                    // Route::get('related-oppotunities', [ApiV1InvestorCommonController::class, 'secReletedOppotunitiesList']);
                });

                Route::prefix('earning')->name('earning.')->group(function () {
                    Route::get('investor', [ApiV1PartnerCommonController::class, 'investorEarning']);
                    Route::get('partner', [ApiV1PartnerCommonController::class, 'partnerEarning']);
                });

                Route::name('profile.')->prefix('profile')->group(function () {
                    Route::get('', [ApiV1PartnerCommonController::class, 'profile']);
                    Route::post('', [ApiV1PartnerCommonController::class, 'profileSave']);
                });

                Route::name('channel_partner.')->prefix('channel-partner')->group(function () {
                    Route::get('', [ApiV1PartnerCommonController::class, 'channelPartnerlist']);
                    Route::post('', [ApiV1PartnerCommonController::class, 'channelPartnerSave']);
                    Route::post('create', [ApiV1PartnerCommonController::class, 'newChannelPartnerSave']);
                });

                Route::prefix('investor')->name('investor.')->group(function () {
                    Route::get('', [ApiV1PartnerCommonController::class, 'investorGet']);
                    Route::post('', [ApiV1PartnerCommonController::class, 'investorPost'])->name('create');
                    Route::post('update', [ApiV1PartnerCommonController::class, 'investorPostUpdate']);
                });

                Route::get('relation-manager', [ApiV1PartnerCommonController::class, 'relationManagerGet']);

                Route::prefix('company')->name('company.')->group(function () {
                    Route::get('market', [ApiV1InvestorCommonController::class, 'companyMarket']);
                    Route::get('detail', [ApiV1InvestorCommonController::class, 'companyDetail']);
                });

                Route::prefix('pre-ipo')->name('preipo.')->group(function () {
                    Route::get('transaction', [ApiV1PartnerCommonController::class, 'preIpoTransaction']);
                    Route::get('transaction/detail', [ApiV1PartnerCommonController::class, 'preIpoTransactionsDetails']);
                    Route::get('sell-transaction', [ApiV1PartnerCommonController::class, 'preIpoSellTransactions']);

                    Route::post('buy', [ApiV1InvestorCommonController::class, 'preIpoBuy']);
                    Route::post('sell', [ApiV1InvestorCommonController::class, 'preIpoSell']);
                    Route::post('cancel-order', [ApiV1InvestorCommonController::class, 'cancelOrder']);
                });

                Route::prefix('aif')->name('aif.')->group(function () {
                    Route::get('get', [ApiV1InvestorCommonController::class, 'getAif']);
                    Route::post('submit', [ApiV1InvestorCommonController::class, 'aifSubmit']);
                });

                Route::post('investor-kyc', [ApiV1InvestorCommonController::class, 'kycPost']);
                Route::post('send-document', [ApiV1PartnerCommonController::class, 'sendDocument']);

                Route::get('pending-tasks', [ApiV1PartnerCommonController::class, 'pendingTasks']);
                Route::post('changepassword', [ApiV1PartnerCommonController::class, 'changePasswordSave']);
                Route::get('document', [ApiV1PartnerCommonController::class, 'documents']);
                Route::get('notifications', [ApiV1PartnerCommonController::class, 'notifications']);



                Route::get('company-startup-list', [ApiV1InvestorCommonController::class, 'getStartupCompany']);
                Route::post('add-portfolio', [ApiV1InvestorCommonController::class, 'addPortfolio']);
                Route::get('get-portfolio', [ApiV1PartnerCommonController::class, 'getPortfolio']);
                Route::get('portfolio-details', [ApiV1PartnerCommonController::class, 'getPortfolioDetails']);

                Route::get('portfolio-pre-ipo', [ApiV1PartnerCommonController::class, 'portfolioPreIpo']);
                Route::get('portfolio-pre-ipo-detail', [ApiV1PartnerCommonController::class, 'getPortfolioPreIpoDetail']);
                Route::get('get-startup-lite', [ApiV1PartnerCommonController::class, 'getStartupLiteNew']);




                // Route::get('portfolio', [ApiV1InvestorCommonController::class, 'portfolio']);
                // Route::get('portfolio-details', [ApiV1InvestorCommonController::class, 'portfolioDetails']);
                // Route::get('portfolio-pre-ipo', [ApiV1InvestorCommonController::class, 'portfolioPreIpo']);
                // Route::get('portfolio-pre-ipo-detail', [ApiV1InvestorCommonController::class, 'portfolioPreIpoDetail']);

                Route::post('contact-us', [ApiMasterController::class, 'contactFromApp']);
                Route::post('delete-account', [ApiV1BusinessLoginController::class, 'deleteAccount']);
                Route::post('logout', [ApiV1BusinessLoginController::class, 'logout']);
            });
        });

        Route::get('get-home', [ApiV1InvestorCommonController::class, 'getHomeNew']);
        Route::get('get-startup', [ApiV1InvestorCommonController::class, 'getStartupNew']);
        Route::get('get-startup-list', [ApiV1InvestorCommonController::class, 'getStartupsNew']);
    });

    // ===================== NEW V2 INVESTOR APIS =====================
    Route::prefix('v2')->name('v2.')->group(function () {
        Route::prefix('investor')->name('investor.')->group(function () {

            Route::prefix('auth')->name('auth.')->group(function () {
                Route::prefix('register')->name('register.')->group(function () {
                    // Step 1: send OTP to mobile for signup
                    Route::post('send-otp', [ApiV2InvestorAuthController::class, 'sendOtp'])->name('sendOtp');

                    // Step 2: verify OTP
                    Route::post('verify-otp', [ApiV2InvestorAuthController::class, 'verifyOtp'])->name('verifyOtp');

                    // Step 3: complete registration
                    // Option A: Manual entry (name, email, referral_code, mpin)
                    // Option B: Google (id_token, referral_code, mpin) - auto-fills name & email from Google
                    Route::post('complete', [ApiV2InvestorAuthController::class, 'completeRegistration'])->name('complete');
                    Route::post('debug-google-token', [ApiV2InvestorAuthController::class, 'debugGoogleToken'])->name('debug.google.token');
                });


                // Login with mobile + MPIN
                Route::post('login', [ApiV2InvestorAuthController::class, 'loginWithMpin'])->name('login');

                // Login using Google (ID token)
                Route::post('login-google', [ApiV2InvestorAuthController::class, 'loginWithGoogle'])->name('loginGoogle');
            });

            // Forgot Password APIs (no auth required)
            Route::prefix('forgot')->name('forgot.')->group(function () {
                Route::post('send-otp', [ApiV2InvestorCommonController::class, 'forgotPassword'])->name('sendOtp');
                Route::post('resend-otp', [ApiV2InvestorCommonController::class, 'resendForgotOtp'])->name('resendOtp');
                Route::post('verify-otp', [ApiV2InvestorCommonController::class, 'verifyForgotOtp'])->name('verifyOtp');
                Route::post('change-password', [ApiV2InvestorCommonController::class, 'changePassword'])->name('changePassword');
            });

            Route::middleware('auth:investor-api-guard')->group(function () {

                // Route::prefix('auth')->group(function () {

                Route::get('referal-link-creation', [ApiV2InvestorCommonController::class, 'createReferalLink']);
                Route::get('company-share-link-creation', [ApiV2InvestorCommonController::class, 'createCompanyShareLink']);

                Route::get('get-preipo-home', [ApiV2InvestorCommonController::class, 'getPreipoHome']);
                Route::get('recently-viewed-stocks', [ApiV2InvestorCommonController::class, 'getRecentlyViewedStocks']);
                Route::get('get-preipo-all', [ApiV2InvestorCommonController::class, 'getPreipoAll']);
                Route::get('get-preipo-news-sectors', [ApiV2InvestorCommonController::class, 'getPreipoNewsAndSectors']);
                Route::get('home', [ApiV1InvestorCommonController::class, 'getHome']);
                Route::get('startup', [ApiV1InvestorCommonController::class, 'getStartup']);
                Route::get('startup-list', [ApiV1InvestorCommonController::class, 'getStartups']);
                // });

                Route::prefix('company')->name('company.')->group(function () {
                    Route::get('detail', [ApiV2InvestorCommonController::class, 'companyDetail'])->name('detail');
                    Route::get('view-all', [ApiV2InvestorCommonController::class, 'companyViewAll']);
                    Route::get('news', [ApiV2InvestorCommonController::class, 'news']);
                    Route::prefix('favorite')->name('favorite.')->group(function () {
                        Route::get('', [ApiV2InvestorCommonController::class, 'getFavoriteCompany']);
                        Route::post('', [ApiV2InvestorCommonController::class, 'postFavoriteCompany']);
                    });
                });

                // Company price alerts (price up/down notifications for a particular company)
                Route::prefix('price-alert')->name('price-alert.')->group(function () {
                    Route::get('', [ApiV2InvestorPriceAlertController::class, 'index'])->name('index');
                    Route::post('', [ApiV2InvestorPriceAlertController::class, 'storeOrUpdate']);
                    Route::post('delete', [ApiV2InvestorPriceAlertController::class, 'delete']);
                });

                Route::prefix('coupon')->name('coupon.')->group(function () {
                    Route::get('applicable', [ApiV2InvestorCommonController::class, 'getApplicableCoupons'])->name('applicable');
                    Route::get('search', [ApiV2InvestorCommonController::class, 'searchCampaignCoupons'])->name('search');
                });

                Route::prefix('profile')->name('profile.')->group(function () {
                    Route::get('get', [ApiV2InvestorCommonController::class, 'getProfile']);
                    Route::post('save', [ApiV2InvestorCommonController::class, 'saveProfile']);

                    Route::post('photo', [ApiV2InvestorCommonController::class, 'updateProfilePhoto']);
                    Route::post('photo-remove', [ApiV2InvestorCommonController::class, 'removeProfilePhoto']);
                    Route::post('switch-profile', [ApiV2InvestorCommonController::class, 'switchProfile']);
                });

                Route::prefix('family')->name('family.')->group(function () {
                    Route::get('', [ApiV2InvestorCommonController::class, 'familyGet']);
                    Route::post('', [ApiV2InvestorCommonController::class, 'familyPost'])->name('create');
                });
                Route::post('check-mpin', [ApiV2InvestorCommonController::class, 'checkMpin']);


                Route::prefix('consultancy')->name('consultancy.')->group(function () {
                    Route::get('calendly-booking-url', [ApiV2InvestorCommonController::class, 'getCalendlyBookingUrl'])->name('calendlyBookingUrl');
                    Route::post('book-slot', [ApiV2InvestorCommonController::class, 'bookSlot'])->name('bookSlot');
                    Route::get('my-slots', [ApiV2InvestorCommonController::class, 'getMyBookedSlots'])->name('mySlots');
                });


                Route::prefix('notification')->name('notification.')->group(function () {
                    Route::get('utility-news', [ApiV2InvestorCommonController::class, 'getNotifications']);
                    Route::get('utility', [ApiV2InvestorCommonController::class, 'utilityNotifications']);
                    Route::get('news', [ApiV2InvestorCommonController::class, 'newsNotifications']);
                });


                Route::prefix('portfolio')->name('portfolio.')->group(function () {
                    Route::get('pre-ipo', [ApiV2InvestorCommonController::class, 'portfolioPreIpo']);
                    Route::get('pre-ipo-detail', [ApiV2InvestorCommonController::class, 'portfolioPreIpoDetail']);

                    Route::get('startup', [ApiV2InvestorCommonController::class, 'portfolio']);
                    Route::get('startup-details', [ApiV2InvestorCommonController::class, 'portfolioDetails']);
                    Route::get('status', [ApiV2InvestorCommonController::class, 'portfolioStatus']);

                    Route::get('combined', [ApiV2InvestorCommonController::class, 'combinedPortfolio']);
                });

                Route::prefix('pre-ipo')->name('preipo.')->group(function () {
                    Route::get('transaction', [ApiV2InvestorCommonController::class, 'preIpoTransactions']);
                    Route::get('transaction/detail', [ApiV2InvestorCommonController::class, 'preIpoTransactionsDetails']);
                    Route::get('transaction-invested-companies', [ApiV2InvestorCommonController::class, 'investedCompanies']);


                    Route::get('sell-transaction', [ApiV2InvestorCommonController::class, 'preIpoSellTransactions']);
                    Route::post('buy', [ApiV2InvestorCommonController::class, 'preIpoBuy']);
                    Route::get('transaction/more',  [ApiV2InvestorCommonController::class, 'preIpoTransactionMore']);
                    Route::post('calculate-transaction', [ApiV2TransactionCalculationController::class, 'calculateTransaction']);
                    Route::post('sell', [ApiV2InvestorCommonController::class, 'preIpoSell']);
                    Route::post('cancel-order', [ApiV2InvestorCommonController::class, 'cancelOrder']);
                    Route::post('upload-payment-receipt', [ApiV2InvestorCommonController::class, 'uploadPreIpoPaymentReceipt']);
                });
                Route::prefix('primary')->name('primary.')->group(function () {
                    Route::get('transaction', [ApiV2InvestorCommonController::class, 'transactionList']);
                    Route::get('transaction/detail', [ApiV2InvestorCommonController::class, 'transactionDetails']);
                });

                Route::prefix('secondary')->name('secondary.')->group(function () {
                    Route::get('transaction', [ApiV2InvestorCommonController::class, 'secTransaction']);
                    Route::get('transaction/detail', [ApiV2InvestorCommonController::class, 'secTransactionDetails']);
                });

                Route::get('document', [ApiV2InvestorCommonController::class, 'documents']);

                Route::get('recent-transaction', [ApiV2InvestorCommonController::class, 'recentTransaction']);

                Route::prefix('dashboard')->name('dashboard.')->group(function () {
                    Route::get('startup', [ApiV2InvestorCommonController::class, 'dashboard']);
                    Route::get('pre-ipo', [ApiV2InvestorCommonController::class, 'dashboardPreIpo']);
                });

                Route::prefix('forge')->name('forge.')->group(function () {
                    Route::prefix('read')->name('read.')->group(function () {
                        Route::post('demat-pdf', [ApiV2InvestorCommonController::class, 'dematPdf'])->name('dematPdf');
                        // Route::post('aadhar-pan', [UploadAndParseController::class, 'aadharPan'])->name('aadharPan');
                    });
                });
                Route::prefix('kyc')->name('kyc.')->group(function () {
                    Route::prefix('upload')->name('upload.')->group(function () {
                        Route::post('demat-bank', [ApiV2InvestorCommonController::class, 'dematBank'])->name('dematBank');
                        // Route::post('aadhar-pan-details', [ApiV1InvestorCommonKycController::class, 'aadharPanStore'])->name('aadharPanStore');
                        // Route::post('pan', [ApiV1InvestorCommonKycController::class, 'pan'])->name('pan');
                        // Route::post('bank-account-details', [ApiV1InvestorCommonKycController::class, 'bankAccountDetails'])->name('bankAccountDetails');
                    });
                    Route::prefix('verification')->name('verification.')->group(function () {
                        Route::post('pan', [ApiV2InvestorCommonController::class, 'verifyPan'])->name('verifyPan');
                    });
                });
                Route::post('add-portfolio', [ApiV2InvestorCommonController::class, 'addPortfolio']);
                Route::get('company-startup-list', [ApiV2InvestorCommonController::class, 'getStartupCompany']);

                Route::post('contact-us', [ApiMasterController::class, 'contactFromApp']);
                Route::post('delete-account', [ApiV2InvestorCommonController::class, 'deleteAccount']);
                Route::post('logout', [ApiV1InvestorLoginController::class, 'logout']);
            });
            Route::post('register-inquiry', [ApiV2InvestorCommonController::class, 'registerInquiry']);
        });

        Route::prefix('business')->name('business.')->group(function () {
            Route::middleware('auth:partner-api-guard')->group(function () {
                Route::prefix('home')->name('home.')->group(function () {
                    Route::prefix('pre-ipo')->name('preipo.')->group(function () {
                        Route::get('', [ApiV2BusinessCommonController::class, 'getPreipoHome']);
                        Route::get('news-sectors', [ApiV2BusinessCommonController::class, 'getPreipoNewsAndSectors']);
                    });
                    Route::prefix('secondary')->name('secondary.')->group(function () {
                        Route::get('', [ApiV2BusinessCommonController::class, 'getSecondaryHome']);
                    });
                });

                Route::prefix('common')->name('common.')->group(function () {
                    Route::get('preipo-news', [ApiV2BusinessCommonController::class, 'getPreipoNews']);
                });

                Route::prefix('company')->name('company.')->group(function () {
                    Route::get('detail', [ApiV2BusinessCommonController::class, 'companyDetail']);
                    Route::get('list', [ApiV2BusinessCommonController::class, 'companyList']);
                });

                Route::prefix('institution')->name('institution.')->group(function () {
                    Route::prefix('company')->name('company.')->group(function () {
                        Route::post('check-duplicate', [ApiV2BusinessCompanyController::class, 'checkDuplicate']);
                        Route::post('', [ApiV2BusinessCompanyController::class, 'create']);
                        Route::get('sectors', [ApiV2BusinessCompanyController::class, 'sectors'])->name('sectors');
                        Route::get('list', [ApiV2BusinessCompanyController::class, 'list'])->name('list');
                        Route::get('list-lite', [ApiV2BusinessCompanyController::class, 'listLite'])->name('list-lite');
                        Route::get('detail', [ApiV2BusinessCompanyController::class, 'detail'])->name('detail');
                        Route::get('my-submissions', [ApiV2BusinessCompanyController::class, 'mySubmissions'])->name('my-submissions');
                        Route::post('promoters', [ApiV2BusinessCompanyController::class, 'savePromoters'])->name('promoters');
                        Route::post('shareholders', [ApiV2BusinessCompanyController::class, 'saveShareholders'])->name('shareholders');
                        Route::prefix('deals')->name('deals.')->group(function () {
                            Route::post('bulk', [ApiV2BusinessCompanyController::class, 'createDealsBulk']);
                            Route::post('', [ApiV2BusinessCompanyController::class, 'createDeal']);
                            Route::get('', [ApiV2BusinessCompanyController::class, 'listDeals']);
                            Route::post('update', [ApiV2BusinessCompanyController::class, 'updateDeal']);
                            Route::post('delete', [ApiV2BusinessCompanyController::class, 'deleteDeal']);
                        });
                    });
                });
                Route::prefix('startup')->name('startup.')->group(function () {
                    Route::get('detail', [ApiV2BusinessCommonController::class, 'startupDetail']);
                    Route::get('list', [ApiV2BusinessCommonController::class, 'startupList']);
                });

                Route::prefix('investor')->name('investor.')->group(function () {
                    Route::get('', [ApiV2BusinessCommonController::class, 'investorList']);
                    Route::post('', [ApiV2BusinessCommonController::class, 'investorCreate'])->name('create');
                    Route::get('detail', [ApiV2BusinessCommonController::class, 'investorDetail']);

                    Route::prefix('kyc')->name('kyc.')->group(function () {
                        Route::post('cml/read', [ApiV2BusinessKycController::class, 'readCml'])->name('cml.read');
                        Route::post('cml/save', [ApiV2BusinessKycController::class, 'saveCml'])->name('cml.save');
                    });
                });

                Route::prefix('primary')->name('primary.')->group(function () {
                });

                Route::prefix('secondary')->name('secondary.')->group(function () {
                });

                Route::prefix('pre-ipo')->name('preipo.')->group(function () {
                    Route::get('transaction-list', [ApiV2BusinessCommonController::class, 'preIpoTransactionList']);
                });

                Route::prefix('enquiries')->name('enquiries.')->group(function () {
                    Route::post('create', [ApiV2BusinessEnquiryController::class, 'create']);
                });

                Route::prefix('portfolio')->name('portfolio.')->group(function () {
                    Route::get('startup', [ApiV2BusinessPortfolioController::class, 'startupPortfolio']);
                    Route::get('pre-ipo', [ApiV2BusinessPortfolioController::class, 'preIpoPortfolio']);
                });
            });
        });

        Route::prefix('seller')->name('seller.')->group(function () {
            Route::post('login', [ApiV2SellerLoginController::class, 'login']);
            Route::name('forgot.')->prefix('forgot-password')->group(function () {
                Route::post('', [ApiV2SellerLoginController::class, 'forgot']);
                Route::post('resend-otp', [ApiV2SellerLoginController::class, 'resendOtp']);
                Route::post('verify-otp', [ApiV2SellerLoginController::class, 'verifyOtp']);
                Route::post('change-password', [ApiV2SellerLoginController::class, 'changePassword']);
            });
            Route::middleware('auth:seller-api-guard')->group(function () {
                Route::get('profile', [ApiV2SellerLoginController::class, 'profile']);
                Route::post('profile/update', [ApiV2SellerLoginController::class, 'updateProfile']);
                Route::post('logout', [ApiV2SellerLoginController::class, 'logout']);
                Route::post('delete-account', [ApiV2SellerLoginController::class, 'deleteAccount']);
                Route::get('dashboard', [ApiV2SellerDashboardController::class, 'index']);

                Route::prefix('company')->name('company.')->group(function () {
                    Route::post('', [ApiV2SellerCompanyController::class, 'create']);
                    Route::post('check-duplicate', [ApiV2SellerCompanyController::class, 'checkDuplicate']);
                    Route::get('sectors', [ApiV2SellerCompanyController::class, 'sectors']);
                    Route::get('list', [ApiV2SellerCompanyController::class, 'list']);
                    Route::get('list-lite', [ApiV2SellerCompanyController::class, 'listLite']);
                    Route::get('detail', [ApiV2SellerCompanyController::class, 'detail']);
                    Route::get('my-submissions', [ApiV2SellerCompanyController::class, 'mySubmissions']);
                    Route::post('promoters', [ApiV2SellerCompanyController::class, 'savePromoters']);
                    Route::post('shareholders', [ApiV2SellerCompanyController::class, 'saveShareholders']);
                    Route::post('update-share-price', [ApiV2SellerCompanyController::class, 'updateSharePrice']);
                    Route::prefix('deals')->name('deals.')->group(function () {
                        Route::get('list', [ApiV2SellerCompanyController::class, 'listDeals']);
                        Route::post('create', [ApiV2SellerCompanyController::class, 'createDeal']);
                        Route::post('update', [ApiV2SellerCompanyController::class, 'updateDeal']);
                        Route::post('delete', [ApiV2SellerCompanyController::class, 'deleteDeal']);
                    });
                });

                Route::prefix('pre-ipo')->name('preipo.')->group(function () {
                    Route::get('transaction', [ApiV2SellerPreIpoTransactionController::class, 'transaction']);
                    Route::get('transaction/detail', [ApiV2SellerPreIpoTransactionController::class, 'transactionDetail']);
                });

                Route::prefix('sell-enquiries')->name('sell-enquiries.')->group(function () {
                    Route::get('list', [ApiV2SellerSellEnquiryController::class, 'list']);
                });
            });
        });
    });

    Route::get('get-config', [ConfigController::class, 'get']);

    Route::prefix('master')->name('master.')->group(function () {
        Route::get('get-supported-countries', [ApiMasterController::class, 'getSupportedCountries']);
        Route::get('get-bse-holidays', [ApiMasterController::class, 'getBseHolidays']);
        Route::get('get-country', [ApiMasterController::class, 'getCountry']);
        Route::get('get-state', [ApiMasterController::class, 'getState']);
        Route::get('get-city', [ApiMasterController::class, 'getCity']);
        Route::get('find-cml-list', [ApiMasterController::class, 'getFindCmlList']);


        Route::get('get-sector', [ApiMasterController::class, 'getSectors']);
        Route::get('get-blog', [ApiMasterController::class, 'getBlog']);
        Route::get('get-avtar', [ApiMasterController::class, 'getAvtar']);
        Route::get('get-bank', [ApiMasterController::class, 'getBank']);
        Route::get('get-faqs', [ApiMasterController::class, 'getFaqs']);

        Route::get('get-familyrelation', [ApiMasterController::class, 'getFamilyRelation']);

        Route::get('city-to-data', [ApiMasterController::class, 'cityToData']);
        Route::prefix('live-pitch')->name('live-pitch.')->group(function () {
            Route::get('upcoming', [ApiMasterController::class, 'upcoming']);
            Route::get('completed', [ApiMasterController::class, 'completed']);
            Route::get('item', [ApiMasterController::class, 'livePitchItem']);
        });
    });
    Route::middleware('auth:investor-api-guard')->group(function () {
        Route::prefix('forge')->name('forge.')->group(function () {
            Route::prefix('read')->name('read.')->group(function () {
                Route::post('demat-pdf', [UploadAndParseController::class, 'dematPdf'])->name('dematPdf');
                Route::post('aadhar-pan', [UploadAndParseController::class, 'aadharPan'])->name('aadharPan');
            });
        });
    });
});

Route::prefix('privatedeals')->name('privatedeals.')->middleware('throttle:20,1')->group(function () {
    Route::match(['post', 'options'], 'submit-data', [PrivateDealsController::class, 'submitData'])->name('submit-data');
});

Route::post('test-request', [WebhookController::class, 'testRequest']);


Route::get('share-price-update', [WebhookController::class, 'sharePrice']);

Route::match(['get', 'post'], 'digio-webhook', [WebhookController::class, 'digio']);

Route::prefix('webhook')->name('webhook.')->group(function () {
    Route::match(['get', 'post'], 'digio', [WebhookController::class, 'digio']);
    // Route::group(['middleware' => [App\Http\Middleware\WhatsappBasicAuth::class]], function () {
    Route::match(['get', 'post'], '11za', [WebhookController::class, 'whatsapp'])->name('11za');
    Route::post('calendly', [WebhookController::class, 'calendly'])->name('calendly');
    // });
});

Route::group(['middleware' => [ApiBasicAuthMiddleware::class]], function () {
    Route::prefix('external')->name('external.')->group(function () {
        Route::get('get-sample-company', function (Request $request) {
            $request->merge(['company_id' => 8]);
            return app(ApiV1InvestorCommonController::class)->companyDetail();
        });
    });
});

Route::get('get-companies-with-id', [TestController::class, 'getCompaniesWithId']);
Route::get('get-companies-names', [TestController::class, 'getCompanyNames']);
Route::post('U9bDaKRxE2NYt5c0gqvFhLiQs74ZmVp1GoXxMHeTjP3CkAWJBLuYnzd86IrSyOb', [TestController::class, 'saveNews']);
Route::get('notification', [TestController::class, 'sendPushNotification']);

Route::match(['get', 'post'], 'login-unauth', [ApiV1InvestorLoginController::class, 'unAuthLogin'])->name('login');




// Route::post('/s3/create-multipart', [UploadController::class, 'createMultipart']);
// Route::post('/s3/get-presigned-url', [UploadController::class, 'getPresignedUrl']);
// Route::post('/s3/complete-multipart', [UploadController::class, 'completeMultipart']);

// Route::post('/s3/create-multipart', [UploadController::class, 'createMultipart']);
// Route::post('/s3/get-presigned-url', [UploadController::class, 'getPresignedUrl']);
// Route::post('/s3/complete-multipart', [UploadController::class, 'completeMultipart']);
// Route::post('/s3/abort-multipart', [UploadController::class, 'abortMultipart']);



Route::group(['middleware' => [ThirdPartyApiAuthMiddleware::class]], function () {
    Route::prefix('sandbox')->name('sandbox.')->group(function () {
        Route::prefix('external')->name('external.')->group(function () {
            Route::get('startups', [ThirdPartyStartupController::class, 'list'])->name('startups')->middleware('tpThrottle:20,1');
            Route::get('startup', [ThirdPartyStartupController::class, 'item'])->name('startup')->middleware('tpThrottle:40,1');
        });

        Route::prefix('ai')->name('ai.')->middleware(['aiClient', 'tpThrottle:30,1'])->group(function () {
            Route::get('schema', [\App\Http\Controllers\ThirdParty\AiCompanyIngestController::class, 'schema'])->name('schema');
            Route::post('companies', [\App\Http\Controllers\ThirdParty\AiCompanyIngestController::class, 'store'])->name('companies.store');
            Route::patch('companies/{uuid}', [\App\Http\Controllers\ThirdParty\AiCompanyIngestController::class, 'update'])->name('companies.update');
            Route::get('companies/{uuid}', [\App\Http\Controllers\ThirdParty\AiCompanyIngestController::class, 'show'])->name('companies.show');
        });
    });
});
