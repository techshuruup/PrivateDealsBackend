<?php

namespace App\Http\Controllers\Api\V2\Investor;

use App\Enums\GenderEnum;
use App\Enums\InstrumentTypeEnum;
use App\Enums\InvestorCouponStatusEnum;
use App\Enums\CouponCompanyScopeEnum;
use App\Enums\CouponTypeEnum;
use App\Enums\DocumentTypeEnum;
use App\Enums\InvestorProfileVisibilityEnum;
use App\Enums\CompanyTypeEnum;
use App\Enums\PreIpoCategoryEnum;
use App\Enums\PrimaryTransactionPaymentMode;
use App\Enums\PrimaryTransactionTypeEnum;
use App\Enums\Utills\DeviceTypeEnum;
use App\Enums\Utills\StatusEnum;
use App\Helpers\CommonHelper;
use App\Helpers\DateTimeHelper;
use App\Helpers\DigioHelper;
use App\Helpers\FileUpDownHelper;
use App\Helpers\PreIpoTransactionHelper;
use App\Helpers\PrimaryTransactionHelper;
use App\Helpers\TransactionCalculationHelper;
use App\Helpers\UtillsHelper;
use App\Http\Controllers\Controller;
use App\Jobs\preipo\CancelNotificationJob;
use App\Models\AppSettingsModel;
use App\Models\CmsFaqsModel;
use App\Models\CompanyDailySharePriceModel;
use App\Models\CompanyModel;
use App\Models\CompanyNewsModel;
use App\Models\InvestorCouponModel;
use App\Models\MasterCouponModel;
use App\Models\PrimaryTransactionModel;
use App\Models\CompanyShareHolderPercentageModel;
use App\Models\CompanySharePriceModel;
use App\Models\CoreFirebaseDeviceTokenModel;
use App\Models\DematManualModel;
use App\Models\DocumentsModel;
use App\Models\InvestorCompanyViewModel;
use App\Models\InvestorConsultancySlotModel;
use App\Models\InvestorFavouriteCompanyModel;
use App\Models\InvestorKycModel;
use App\Models\InvestorModel;
use App\Models\InvestorRegisterRequestModel;
use App\Models\MasterSectorsModel;
use App\Models\NotificationsModel;
use App\Models\PortfolioImportModel;
use App\Models\PortfolioModel;
use App\Models\PortfolioPreIpoModel;
use App\Models\PreIpoModel;
use App\Models\PreIpoSellRequestModel;
use App\Models\SecondarySellRequestModel;
use App\Models\SecondaryTransactionModel;
use App\Repositories\V2\InvestorRepository;
use App\Services\DematPdfParsingService;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Jobs\notifications\portfolio\upload\CustomJob as PotfolioUploadCutomJob;
use App\Jobs\notifications\portfolio\upload\ExistingJob as PotfolioUploadExistingJob;
use App\Jobs\SendPendingKycAdminNotification;
use App\Models\PreIpoTransactionPaymentsModel;
use App\Models\StartupModel;
use App\Models\CompanyShareLinkModel;
use Illuminate\Support\Facades\Http;
use Throwable;

class CommonController extends Controller
{
    private InvestorRepository $invRepo;

    public function __construct(InvestorRepository $investorRepository)
    {
        $this->invRepo = $investorRepository;
    }
    /**
     * Forgot Password - Send OTP
     */

    function createReferalLink(): JsonResponse
    {
        $request = request();

        $investor = InvestorModel::where('id', $request->user()->id)
            ->with('city', 'country', 'state', 'investor_detail', 'kyc', 'dematAccount', 'relation')
            ->first();

        if (!$investor) {
            return UtillsHelper::json(0, [
                'message' => 'Investor not found'
            ]);
        } else {

            if ($investor->referal_deep_link) {
                return UtillsHelper::investorProfileResponse($investor);
            } else {
                $payload = [
                    "userId" => $investor->uuid,
                    "originalUrl" => "https://www.shuruup.com/",
                    "title" => "PrivateDeals: Pre-IPO&LP Secondary",
                    "description" => "Join PrivateDeals - With " . $investor->name,
                    "iosAppStoreUrl" => "https://apps.apple.com/us/app/shuru-up-pre-ipo-lp-secondary/id6736905561",
                    "androidAppStoreUrl" => "https://play.google.com/store/apps/details?id=com.shuruup.investor",
                    "appScheme" => "shuruup",
                    "targetingRules" => [
                        "devices" => ["ios", "android"],
                        "languages" => ["en"]
                    ],
                    "deepLinkParameters" => [
                        "referral_code" => $investor->referral_code
                    ]
                ];

                $token = '246ef83aabbfce278160f77dd318666dd21cd4e09f6b824134263c506aa91e56';
                try {
                    $response = Http::withToken($token)
                        ->acceptJson()
                        ->contentType('application/json')
                        ->post('https://go.shuruup.com/api/links', $payload);

                    if ($response->successful()) {

                        $responseData = $response->json();

                        // return UtillsHelper::json(1, [
                        //     'message' => 'Referral link created successfully',
                        //     'data' => $responseData
                        // ]);

                        $investor->referal_deep_link = 'https://go.shuruup.com/' . ($responseData['short_code'] ?? '');
                        $investor->save();

                        return UtillsHelper::investorProfileResponse($investor);
                    }


                    return UtillsHelper::json(0, [
                        'message' => 'API request failed',
                        'status' => $response->status(),
                        'error' => $response->json() ?? $response->body()
                    ]);
                } catch (\Exception $e) {

                    return UtillsHelper::json(0, [
                        'message' => 'Something went wrong',
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    public function createCompanyShareLink(): JsonResponse
    {
        $request = request();

        $validation = Validator::make($request->all(), [
            'uuid' => 'required|uuid',
            'type' => 'required|string|in:company,startup',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $investor = InvestorModel::where('id', $request->user()->id)->first();

        if (!$investor) {
            return UtillsHelper::json(0, [
                'message' => 'Investor not found'
            ]);
        }

        // Determine parameters dynamically based on type
        $isCompany = $request->type === 'company';
        $modelClass = $isCompany ? CompanyModel::class : StartupModel::class;
        $slugField = $isCompany ? 'slug' : 'url_slug';
        $pathSegment = $isCompany ? 'company' : 'startup';
        $redirectTo = $isCompany ? 'home/companydetail' : 'home/primary/startupdetail';

        // Verify company/startup exists
        $company = $modelClass::where('uuid', $request->uuid)
            ->where('is_deleted', 0)
            ->first();

        if (!$company) {
            return UtillsHelper::json(0, [
                'message' => 'Company/Startup not found'
            ]);
        }

        // Check if link already exists for this investor and company
        $existingLink = CompanyShareLinkModel::where('investor_id', $investor->id)
            ->where('type', $request->type)
            ->where('company_uuid', $request->uuid)
            ->first();

        if ($existingLink) {
            return UtillsHelper::json(1, [
                'message' => 'Share link retrieved successfully',
                'data' => [
                    'deep_link' => $existingLink->deep_link
                ]
            ]);
        }

        // Generate referral code if not exists
        if (!$investor->referral_code) {
            $investor->referral_code = UtillsHelper::generateUniqueReferralCode();
            $investor->save();
        }

        $originalUrl = "https://www.shuruup.com/{$pathSegment}/" . ($company->{$slugField} ?? $company->uuid);

        $payload = [
            "userId" => $investor->uuid,
            "originalUrl" => $originalUrl,
            "title" => "PrivateDeals: " . ($company->brand_name ?? $company->company_name),
            "description" => "Check out " . ($company->brand_name ?? $company->company_name) . " on PrivateDeals. Shared by " . $investor->name,
            "iosAppStoreUrl" => "https://apps.apple.com/us/app/shuru-up-pre-ipo-lp-secondary/id6736905561",
            "androidAppStoreUrl" => "https://play.google.com/store/apps/details?id=com.shuruup.investor",
            "appScheme" => "shuruup",
            "targetingRules" => [
                "devices" => ["ios", "android"],
                "languages" => ["en"]
            ],
            "deepLinkParameters" => [
                "referral_code"  => $investor->referral_code,
                // "reference_id"   => $company->uuid,
                "reference_type" => $request->type,
                "redirect_to"    => $redirectTo . "?uuid=" . $company->uuid
            ]
        ];

        $token = '246ef83aabbfce278160f77dd318666dd21cd4e09f6b824134263c506aa91e56';

        try {
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->acceptJson()
                ->contentType('application/json')
                ->post('https://go.shuruup.com/api/links', $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                $shortLink = 'https://go.shuruup.com/' . ($responseData['short_code'] ?? '');

                $companyShareLink = CompanyShareLinkModel::create([
                    'investor_id'  => $investor->id,
                    'type'         => $request->type,
                    'company_uuid' => $request->uuid,
                    'deep_link'    => $shortLink,
                ]);

                return UtillsHelper::json(1, [
                    'message' => 'Share link created successfully',
                    'data' => [
                        'deep_link' => $companyShareLink->deep_link
                    ]
                ]);
            }

            return UtillsHelper::json(0, [
                'message' => 'API request failed',
                'status' => $response->status(),
                'error' => $response->json() ?? $response->body()
            ]);
        } catch (\Exception $e) {
            return UtillsHelper::json(0, [
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validation = Validator::make($request->all(), [
            'mobile_no'           => 'required|numeric|digits:10',
            'mobile_country_code' => 'nullable|numeric',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $mobileCountryCode = (int) ($request->mobile_country_code ?? 91);
        $investor          = InvestorModel::where('is_deleted', 0)
            ->where('registration_step', 3)
            ->where('mobile_number', $request->mobile_no)
            ->where('mobile_country_code', $mobileCountryCode)
            ->first();

        if (!$investor) {
            return UtillsHelper::json(0, ['message' => 'Mobile number not registered.']);
        }

        UtillsHelper::sendVerificationCode(
            $investor->id,
            InvestorModel::class,
            $request->mobile_no,
            \App\Enums\Utills\CodeVerificationTypeEnum::forgot_password,
            $mobileCountryCode
        );

        return UtillsHelper::json(1, [
            'message' => 'Verification code sent to ' . $request->mobile_no,
            'data'    => ['investor_id' => $investor->id],
        ]);
    }

    /**
     * Forgot Password - Resend OTP
     */
    public function resendForgotOtp(Request $request): JsonResponse
    {
        $validation = Validator::make($request->all(), [
            'investor_id' => 'required',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $investor = InvestorModel::find($request->investor_id);
        if (!$investor) {
            return UtillsHelper::json(0, ['message' => 'Investor not found']);
        }

        UtillsHelper::sendVerificationCode(
            $investor->id,
            InvestorModel::class,
            $investor->mobile_number,
            \App\Enums\Utills\CodeVerificationTypeEnum::forgot_password,
            (int) $investor->mobile_country_code
        );

        return UtillsHelper::json(1, ['message' => 'Verification code sent to ' . $investor->mobile_number]);
    }

    /**
     * Forgot Password - Verify OTP
     */
    public function verifyForgotOtp(Request $request): JsonResponse
    {
        $validation = Validator::make($request->all(), [
            'investor_id' => 'required',
            'otp'         => 'required|numeric|digits:6',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $investor = InvestorModel::find($request->investor_id);
        if (!$investor) {
            return UtillsHelper::json(0, ['message' => 'Investor not found']);
        }

        $code = UtillsHelper::getVerificationCode(
            $request->otp,
            $investor->id,
            InvestorModel::class,
            \App\Enums\Utills\CodeVerificationTypeEnum::forgot_password
        );

        if (!$code) {
            return UtillsHelper::json(0, ['message' => 'Verification code is not valid']);
        }

        $code->is_used = '1';
        $code->save();

        return UtillsHelper::json(1, ['message' => 'OTP verified, proceed to change your password']);
    }

    /**
     * Forgot Password - Change Password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validation = Validator::make($request->all(), [
            'investor_id' => 'required',
            'password'    => 'required|numeric|digits:4',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $investor = InvestorModel::find($request->investor_id);
        if (!$investor) {
            return UtillsHelper::json(0, ['message' => 'Investor not found']);
        }

        $investor->password            = \Illuminate\Support\Facades\Hash::make($request->password);
        $investor->ask_password_change = 0;
        $investor->save();

        return UtillsHelper::json(1, ['message' => 'Password reset successfully']);
    }

    public function getRecentlyViewedStocks(Request $request): JsonResponse
    {
        $investor = $request->user();

        $recentlyViewed = InvestorCompanyViewModel::where('investor_id', $investor->id)
            ->with([
                'company:id,brand_name,logo,share_price,distributer_price,base_price,category,is_drhp,bg_color_code',
                'company.fundamentals:company_id,lot_size,fifty_two_week_high,fifty_two_week_low'
            ])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(fn($view) => [
                'id' => $view->company->id,
                'brand_name' => $view->company->brand_name,
                'logo' => $view->company->logo ?? null,
                'share_price' => (float) ($view->company->share_price ?? 0),
                'distributer_price' => (float) ($view->company->distributer_price ?? 0),
                'base_price' => (float) ($view->company->base_price ?? 0),
                'category' => $view->company->category ?? null,
                'is_drhp' => $view->company->is_drhp ?? null,
                'bg_color_code' => $view->company->bg_color_code ?? null,
                'fundamentals' => $view->company->fundamentals ? [
                    'lot_size' => $view->company->fundamentals->lot_size ?? null,
                    'fifty_two_week_high' => $view->company->fundamentals->fifty_two_week_high ?? null,
                    'fifty_two_week_low' => $view->company->fundamentals->fifty_two_week_low ?? null,
                ] : null,
            ])
            ->values();


        return UtillsHelper::json(1, [
            'message' => 'Recently Viewed Stocks',
            'data' => $recentlyViewed,
        ]);
    }


    public function getPreipoHome(Request $request): JsonResponse
    {
        $investor = $request->user();
        $data     = [];
        $oneYearAgo = now()->subYear();

        $companies = CompanyModel::approved()->where('is_deleted', 0)
            ->where('type', CompanyTypeEnum::unlisted->value)
            ->where(function ($q) {
                $q->whereNull('category')
                    ->orWhere('category', '!=', PreIpoCategoryEnum::listed->value);
            })
            ->select(
                'id',
                'brand_name',
                'logo',
                'about',
                'category',
                'is_drhp',
                'bg_color_code',
                'share_price',
                'distributer_price',
                'base_price',
                'price_updated_today',
                'last_year_share_price',
                'is_trending',
                'is_drhp',
                'is_grab_opportunity_enabled',
                'min_investment_type',
                'min_investment_amount'
            )
            ->with([
                'fundamentals' => fn($q) => $q->select('company_id', 'lot_size'),
                'sharePrices' => fn($q) => $q
                    ->select('company_id', 'price', 'date')
                    ->whereDate('date', '>=', $oneYearAgo)
                    ->orderBy('date', 'asc'),
                'grabOpportunitySlots'
            ])
            ->orderByRaw('list_order IS NULL')
            ->orderBy('list_order', 'asc')
            ->orderBy('brand_name', 'asc')
            ->get()
            ->each(fn($c) => $c->setAppends([]));

        $data['all'] = $companies
            ->shuffle()
            ->take(6)
            ->map(fn($c) => [
                'id' => $c->id,
                'logo' => $c->logo
            ])
            ->values();

        $nonListedCompanies = $companies
            ->where('category', '!=', PreIpoCategoryEnum::listed->value);

        $data['exclusive_deals'] = $nonListedCompanies
            ->where('category', PreIpoCategoryEnum::exclusive_deals->value)
            ->take(4)
            ->map(function ($company) {
                $formatted = $this->formatCompanyWithPrices($company);
                return $this->appendGrabOpportunity($formatted, $company);
            })
            ->values();

        $data['liquid_stocks'] = $nonListedCompanies
            ->where('category', PreIpoCategoryEnum::liquid_stocks->value)
            ->take(4)
            ->map(function ($company) {
                $formatted = $this->formatCompanyWithPrices($company);
                return $this->appendGrabOpportunity($formatted, $company);
            })
            ->values();

        $data['drhp'] = $nonListedCompanies
            ->where('is_drhp', 1)
            ->where('category', '!=', PreIpoCategoryEnum::coming_soon->value)
            ->where('category', '!=', PreIpoCategoryEnum::listed->value)
            ->take(4)
            ->map(function ($company) {
                $formatted = $this->formatCompanyWithPrices($company);
                return $this->appendGrabOpportunity($formatted, $company);
            })
            ->values();

        $data['trending'] = $nonListedCompanies
            ->where('is_trending', 1)
            ->take(8)
            ->map(function ($company) {
                $formatted = $this->formatCompanyWithPrices($company);
                $formatted = $this->appendGrabOpportunity($formatted, $company);
                $formatted['min_investment_type'] = $company->min_investment_type;
                $formatted['min_investment_amount'] = (float) $company->min_investment_amount;
                return $formatted;
            })
            ->values();

        $priceFluctuation = CompanyDailySharePriceModel::getTopGainersLosers(3);
        $data['top_gainers'] = $priceFluctuation['up'];
        $data['top_losers']  = $priceFluctuation['down'];

        $recentlyViewed = InvestorCompanyViewModel::where('investor_id', $investor->id)
            ->select('company_id', DB::raw('MAX(updated_at) as last_viewed'))
            ->groupBy('company_id')
            ->orderByDesc('last_viewed')
            ->limit(10)
            ->with([
                'company:id,brand_name,logo,share_price,distributer_price,base_price,category,is_drhp,bg_color_code',
                'company.fundamentals:company_id,lot_size,fifty_two_week_high,fifty_two_week_low'
            ])
            ->get()
            ->map(fn($view) => [
                'id' => $view->company->id,
                'brand_name' => $view->company->brand_name,
                'logo' => $view->company->logo ?? null,
                'share_price' => (float) ($view->company->share_price ?? 0),
                'distributer_price' => (float) ($view->company->distributer_price ?? 0),
                'base_price' => (float) ($view->company->base_price ?? 0),
                'category' => $view->company->category ?? null,
                'is_drhp' => $view->company->is_drhp ?? null,
                'bg_color_code' => $view->company->bg_color_code ?? null,
                'fundamentals' => $view->company->fundamentals ? [
                    'lot_size' => $view->company->fundamentals->lot_size ?? null,
                    'fifty_two_week_high' => $view->company->fundamentals->fifty_two_week_high ?? null,
                    'fifty_two_week_low' => $view->company->fundamentals->fifty_two_week_low ?? null,
                ] : null,
            ])
            ->values();

        $data['recently_viewed_stocks'] = $recentlyViewed;
        $supportSettings = AppSettingsModel::whereIn('key', [
            'support_whatsapp_number',
            'support_email',
            'support_phone',
        ])->pluck('value', 'key');

        $data['support'] = [
            'whatsapp_url'  => 'https://wa.me/' . $supportSettings->get('support_whatsapp_number'),
            'email'         => $supportSettings->get('support_email'),
            'call_support'  => $supportSettings->get('support_phone'),
        ];

        return UtillsHelper::json(1, [
            'message' => 'Pre-IPO Home Page',
            'data'    => $data,
        ]);
    }


    private function appendGrabOpportunity($formatted, $company): array
    {
        if ($formatted instanceof \Illuminate\Database\Eloquent\Model) {
            $formatted = $formatted->toArray();
        }

        if (
            $company->is_grab_opportunity_enabled == 1 &&
            $company->grabOpportunitySlots &&
            $company->grabOpportunitySlots->count() > 0
        ) {
            $retailerPrice = (float) $company->share_price;
            $basePrice = (float) $company->base_price;

            $grabOpportunity = $company->grabOpportunitySlots
                ->map(function ($slot) use ($retailerPrice, $basePrice) {

                    $perSharePrice = ($slot->slot_number == 1)
                        ? $retailerPrice
                        : $basePrice * (1 + ($slot->percentage / 100));

                    return [
                        'slot_number' => $slot->slot_number,
                        'slot_range' => $slot->max_amount
                            ? '₹' . number_format($slot->min_amount, 0) . ' - ₹' . number_format($slot->max_amount, 0)
                            : '₹' . number_format($slot->min_amount, 0) . ' and above',
                        'min_amount' => (float) $slot->min_amount,
                        'max_amount' => $slot->max_amount ? (float) $slot->max_amount : null,
                        'per_share_price' => round($perSharePrice, 2),
                        'percentage' => (float) $slot->percentage
                    ];
                })
                ->sortBy('slot_number')
                ->values();

            $formatted['min_share_price_as_slot'] = $grabOpportunity->min('per_share_price');
            $formatted['grab_opportunity'] = $grabOpportunity;
        } else {
            $formatted['min_share_price_as_slot'] = null;
            $formatted['grab_opportunity'] = [];
        }

        return $formatted;
    }



    public function getPreipoAll(Request $request): JsonResponse
    {
        $companies = CompanyModel::approved()->where('is_deleted', 0)
            ->where('type', CompanyTypeEnum::unlisted->value)
            ->where(function ($q) {
                $q->whereNull('category')
                    ->orWhere('category', '!=', PreIpoCategoryEnum::listed->value);
            })
            ->select('id', 'logo')
            ->orderByRaw('list_order IS NULL')
            ->orderBy('list_order', 'asc')
            ->orderBy('brand_name', 'asc')
            ->limit(6)
            ->get();

        $data = $companies->map(fn($c) => [
            'id'   => $c->id,
            'logo' => $c->logo,
        ]);

        return UtillsHelper::json(1, [
            'message' => 'Pre-IPO All Companies',
            'data' => $data
        ]);
    }



    private function formatCompanyWithPrices($company)
    {
        $realPrices = $company->sharePrices
            ->groupBy(fn($price) => Carbon::parse($price->date)->format('Y-m'))
            ->map(fn($pricesInMonth) => $pricesInMonth->sortByDesc('date')->first())
            ->map(fn($price) => [
                'price'     => (float) $price->price,
                'date'      => $price->date,
                'is_padded' => false,
            ])
            ->values()
            ->sortBy('date')
            ->values();

        $targetMonths = 12;
        $realCount    = $realPrices->count();

        if ($realCount < $targetMonths) {
            $neededPoints = $targetMonths - $realCount;

            $firstRealPrice = $realPrices->isNotEmpty()
                ? $realPrices->first()['price']
                : (float) $company->share_price;

            $earliestRealDate = $realPrices->isNotEmpty()
                ? Carbon::parse($realPrices->first()['date'])
                : Carbon::now();

            $lastYearPrice = (float) $company->last_year_share_price;
            if ($lastYearPrice <= 0) {
                $lastYearPrice = round($firstRealPrice * 0.85, 2);
            }

            $s1 = abs(crc32($company->id . 'vol'));
            $s3 = abs(crc32($company->id . 'pts'));

            $volatility   = 0.01 + (($s1 % 100) / 100) * 0.07;

            $range = $neededPoints - 3;
            $actualPoints = $range > 0
                ? max(4, ($s3 % $range) + 4)
                : 4;

            $actualPoints = min($actualPoints, $neededPoints);

            $dummyPoints = collect();

            for ($i = 0; $i < $actualPoints; $i++) {
                $t = $actualPoints > 1 ? $i / ($actualPoints - 1) : 1.0;

                $interpolated = $lastYearPrice + ($firstRealPrice - $lastYearPrice) * $t;

                $h     = abs(crc32($company->id . '_step_' . $i));
                $norm  = ($h % 10000) / 10000;
                $nudge = ($norm * $volatility * 2) - $volatility;

                $price = round($interpolated * (1 + $nudge), 2);
                $price = min($price, round($firstRealPrice * 0.998, 2));
                $floor = round(min($lastYearPrice, $firstRealPrice) * 0.50, 2);
                $price = max($price, $floor);

                $monthsBack = $actualPoints - $i;
                $dummyDate  = $earliestRealDate->copy()->subMonths($monthsBack);

                $dummyPoints->push([
                    'price'     => $price,
                    'date'      => $dummyDate->format('Y-m-d'),
                    'is_padded' => true,
                ]);
            }

            $allPrices = $dummyPoints->concat($realPrices)->values();
        } else {
            $allPrices = $realPrices;
        }

        $company->share_prices = $allPrices;

        $oldestPrice = $allPrices->first()['price'] ?? (float) $company->share_price;

        if ($oldestPrice !== (float) $company->share_price) {
            $company->last_year_share_price = $oldestPrice;
        }

        unset($company->sharePrices);
        return $company->makeHidden('is_favorite');
    }

    public function getPreipoNewsAndSectors(Request $request): JsonResponse
    {
        $news = CompanyNewsModel::whereHas('company', function ($query) {
            $query->where('is_deleted', 0)
                ->where('type', CompanyTypeEnum::unlisted->value);
        })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $sectors = MasterSectorsModel::select('id', 'name', 'icon_image', 'url_slug')
            ->whereHas('companies', function ($query) {
                $query->where('is_deleted', 0)
                    ->where('type', CompanyTypeEnum::unlisted->value);
            })
            ->orderBy('name', 'asc')
            ->get();

        return UtillsHelper::json(1, [
            'message' => 'Pre-IPO News & Sectors',
            'data' => [
                'news' => $news,
                'sectors' => $sectors
            ]
        ]);
    }



    private function getSupportSettings(): array
    {
        return [
            'whatsapp_url' => 'https://wa.me/' . AppSettingsModel::where('key', 'support_whatsapp_number')->first()->value,
            'email' => AppSettingsModel::where('key', 'support_email')->first()->value,
            'call_support' => AppSettingsModel::where('key', 'support_phone')->first()->value,
        ];
    }

    public function getCalendlyBookingUrl(Request $request): JsonResponse
    {
        $investor = $request->user();

        $calendlySchedulingLink = CommonHelper::appSettings('calendly_scheduling_link');
        if (empty($calendlySchedulingLink)) {
            $calendlySchedulingLink = env('CALENDLY_SCHEDULING_LINK', '');
        }

        if (empty($calendlySchedulingLink)) {
            return UtillsHelper::json(0, [
                'message' => 'Calendly booking URL is not configured. Please contact support.',
                'debug_info' => [
                    'env_set' => env('CALENDLY_SCHEDULING_LINK') ? 'yes' : 'no',
                    'app_settings_set' => CommonHelper::appSettings('calendly_scheduling_link') ? 'yes' : 'no',
                ]
            ]);
        }

        // Build booking URL
        $bookingUrl = rtrim($calendlySchedulingLink, '/');

        // Get event path
        $eventPath = CommonHelper::appSettings('calendly_event_path');
        if (empty($eventPath)) {
            $eventPath = env('CALENDLY_EVENT_PATH', '');
        }

        if (!empty($eventPath)) {
            $bookingUrl .= '/' . ltrim($eventPath, '/');
        }

        // Build query parameters
        $params = [
            'utm_campaign' => $investor->id,  // Tracking parameter for investor mapping
            'name' => $investor->name,  // Pre-fill name
            'email' => $investor->email,  // Pre-fill email
        ];

        // Add phone if available
        if (!empty($investor->mobile_number)) {
            $countryCode = $investor->mobile_country_code ?? '91';
            $params['phone'] = '+' . $countryCode . $investor->mobile_number;
        }

        // Build query string
        $queryString = http_build_query($params);
        $bookingUrl .= '?' . $queryString;

        return UtillsHelper::json(1, [
            'message' => 'Calendly booking URL retrieved successfully',
            'data' => [
                'booking_url' => $bookingUrl,
                'investor_name' => $investor->name,
                'investor_email' => $investor->email,
            ]
        ]);
    }


    /**
     * Link Calendly Booking to Investor Account (Optional)
     * This endpoint is optional - webhook automatically links bookings using tracking parameter (a1)
     * Only use this if you need to manually link a booking that wasn't auto-linked
     */
    public function bookSlot(Request $request): JsonResponse
    {
        $validation = Validator::make($request->all(), [
            'calendly_invitee_uri' => 'required|string',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $investor = $request->user();

        // Find booking by invitee URI (created by webhook)
        $booking = InvestorConsultancySlotModel::where('calendly_invitee_uri', $request->calendly_invitee_uri)
            ->first();

        if (!$booking) {
            // If booking doesn't exist yet, webhook might not have arrived
            // Try to find by investor email as fallback
            $booking = InvestorConsultancySlotModel::where('email', $investor->email)
                ->whereNull('investor_id')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$booking) {
                return UtillsHelper::json(0, [
                    'message' => 'Booking not found. Please wait a moment and try again, or ensure you completed the booking on Calendly.'
                ]);
            }
        }

        // Link booking to investor if not already linked
        if (!$booking->investor_id) {
            $booking->investor_id = $investor->id;
            $booking->save();
        } elseif ($booking->investor_id != $investor->id) {
            return UtillsHelper::json(0, [
                'message' => 'This booking belongs to another user.'
            ]);
        }

        return UtillsHelper::json(1, [
            'message' => 'Booking linked successfully',
            'data'    => [
                'booking_id' => $booking->id,
                'status' => $booking->status,
                'scheduled_start_time' => $booking->scheduled_start_time ? $booking->scheduled_start_time->toIso8601String() : null,
                'scheduled_end_time' => $booking->scheduled_end_time ? $booking->scheduled_end_time->toIso8601String() : null,
                'calendly_meeting_url' => $booking->calendly_meeting_url,
                'first_name' => $booking->first_name,
                'last_name' => $booking->last_name,
                'email' => $booking->email,
            ],
        ]);
    }

    /**
     * Get My Booked Slots
     */
    public function getMyBookedSlots(Request $request): JsonResponse
    {
        $investor = $request->user();

        $slots = InvestorConsultancySlotModel::where('investor_id', $investor->id)
            ->orderBy('scheduled_start_time', 'desc')
            ->get()
            ->map(function ($slot) {
                return [
                    'id'                  => $slot->id,
                    'scheduled_start_time' => $slot->scheduled_start_time ? $slot->scheduled_start_time->toIso8601String() : null,
                    'scheduled_end_time'   => $slot->scheduled_end_time ? $slot->scheduled_end_time->toIso8601String() : null,
                    'first_name'          => $slot->first_name,
                    'last_name'           => $slot->last_name,
                    'email'               => $slot->email,
                    'description'         => $slot->description,
                    'mobile_number'       => $slot->mobile_number,
                    'status'              => $slot->status,
                    'calendly_meeting_url' => $slot->calendly_meeting_url,
                    'calendly_event_uri'   => $slot->calendly_event_uri,
                    'created_at'          => $slot->created_at->toIso8601String(),
                ];
            });

        return UtillsHelper::json(1, [
            'message' => 'My booked slots',
            'data'    => $slots,
        ]);
    }

    public function companyDetail(Request $request): JsonResponse
    {
        $validation = Validator::make($request->all(), [
            'company_id' => 'nullable',
            'company_uuid' => 'nullable|uuid',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        // At least one of company_id or company_uuid is required
        if (!$request->filled('company_id') && !$request->filled('company_uuid')) {
            return UtillsHelper::json(0, ['message' => 'Either company_id or company_uuid is required.']);
        }

        $oneYearAgo = now()->subYear();

        $companyQuery = CompanyModel::approved()->with([
            'customData',
            // Load only last 1 year of share prices for graph — same as home API
            // 'sharePrices' => function ($query) use ($oneYearAgo) {
            //     $query->whereDate('date', '>=', $oneYearAgo)
            //         ->orderBy('date', 'asc');
            // },
            'sharePrices',
            'fundamentals',
            'promoters',
            'events' => function ($query) {
                $query->orderBy('date', 'desc');
            },
            'news' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(10);
            },
            'peerratio',
            'sector',
            'grabOpportunitySlots',
        ])->where('is_deleted', 0);

        // If both company_id and company_uuid are provided, ensure they belong to the same company
        if ($request->filled('company_id') && $request->filled('company_uuid')) {
            $companyQuery->where('id', $request->company_id)
                ->where('uuid', $request->company_uuid);
        } elseif ($request->filled('company_id')) {
            $companyQuery->where('id', $request->company_id);
        } else {
            $companyQuery->where('uuid', $request->company_uuid);
        }

        $company = $companyQuery->first();

        if (!$company) {
            return UtillsHelper::json(0, ['message' => 'Company not found']);
        }

        $company->setAppends([]);

        // Load additional news if no recent news
        if ($company->news->count() == 0) {
            $company->load(['news' => function ($query) {
                $query->orderBy('id', 'desc')->limit(3);
            }]);
        }

        // ---------------------------------------------------------------
        // Build share_prices with fake graph — same logic as home API
        // ---------------------------------------------------------------
        // $realPrices = $company->sharePrices
        //     ->groupBy(fn($price) => Carbon::parse($price->date)->format('Y-m'))
        //     ->map(fn($pricesInMonth) => $pricesInMonth->sortByDesc('date')->first())
        //     ->map(fn($price) => [
        //         'price'     => (float) $price->price,
        //         'date'      => $price->date,
        //         'is_padded' => false,
        //     ])
        //     ->values()
        //     ->sortBy('date')
        //     ->values();
        $realPrices = $company->sharePrices;
        $targetMonths = 12;
        $realCount    = $realPrices->count();

        if ($realCount < $targetMonths) {
            $neededPoints = $targetMonths - $realCount;

            $firstRealPrice = $realPrices->isNotEmpty()
                ? $realPrices->first()['price']
                : (float) $company->share_price;

            $earliestRealDate = $realPrices->isNotEmpty()
                ? Carbon::parse($realPrices->first()['date'])
                : Carbon::now();

            $lastYearPrice = (float) $company->last_year_share_price;
            if ($lastYearPrice <= 0) {
                $lastYearPrice = round($firstRealPrice * 0.85, 2);
            }

            $s1 = abs(crc32($company->id . 'vol'));
            $s3 = abs(crc32($company->id . 'pts'));

            $volatility   = 0.01 + (($s1 % 100) / 100) * 0.07;
            //$actualPoints = max(4, ($s3 % ($neededPoints - 3)) + 4);
            $actualPoints = ($neededPoints > 3) ? max(4, ($s3 % ($neededPoints - 3)) + 4) : $neededPoints;
            $actualPoints = min($actualPoints, $neededPoints);

            $dummyPoints = collect();

            for ($i = 0; $i < $actualPoints; $i++) {
                $t = $actualPoints > 1 ? $i / ($actualPoints - 1) : 1.0;

                $interpolated = $lastYearPrice + ($firstRealPrice - $lastYearPrice) * $t;

                $h     = abs(crc32($company->id . '_step_' . $i));
                $norm  = ($h % 10000) / 10000;
                $nudge = ($norm * $volatility * 2) - $volatility;

                $price = round($interpolated * (1 + $nudge), 2);
                $price = min($price, round($firstRealPrice * 0.998, 2));
                $floor = round(min($lastYearPrice, $firstRealPrice) * 0.50, 2);
                $price = max($price, $floor);

                $monthsBack = $actualPoints - $i;
                $dummyDate  = $earliestRealDate->copy()->subMonths($monthsBack);

                $dummyPoints->push([
                    'price'     => $price,
                    'date'      => $dummyDate->format('Y-m-d'),
                    'is_padded' => true,
                ]);
            }

            $allPrices = $dummyPoints->concat($realPrices)->values();
        } else {
            $allPrices = $realPrices;
        }

        $company->share_prices = $allPrices;

        // Override last_year_share_price with oldest point in share_prices
        // so percentage calc on frontend is non-zero for flat-DB companies
        $oldestPrice = $allPrices->first()['price'] ?? (float) $company->share_price;
        if ($oldestPrice !== (float) $company->share_price) {
            $company->last_year_share_price = $oldestPrice;
        }

        // ---------------------------------------------------------------
        // Historical share prices (1m, 6m, 3y) — separate from graph
        // These still query DB directly for accuracy
        // ---------------------------------------------------------------
        $fallbackPrice = $company->sharePrices()->orderBy('date', 'asc')->first();

        $oneMonthAgo = now()->subMonth();
        $lastMonthPrice = $company->sharePrices()
            ->where('date', '<=', $oneMonthAgo)
            ->orderBy('date', 'desc')
            ->first() ?: $fallbackPrice;
        $company->last_month_share_price = (float) ($lastMonthPrice->price ?? 0.00);

        $sixMonthsAgo = now()->subMonths(6);
        $lastSixMonthsPrice = $company->sharePrices()
            ->where('date', '<=', $sixMonthsAgo)
            ->orderBy('date', 'desc')
            ->first() ?: $fallbackPrice;
        $company->last_six_months_share_price = (float) ($lastSixMonthsPrice->price ?? 0.00);

        $threeYearsAgo = now()->subYears(3);
        $lastThreeYearsPrice = $company->sharePrices()
            ->where('date', '<=', $threeYearsAgo)
            ->orderBy('date', 'desc')
            ->first() ?: $fallbackPrice;
        $company->last_three_years_share_price = (float) ($lastThreeYearsPrice->price ?? 0.00);

        // ---------------------------------------------------------------
        // Shareholders
        // ---------------------------------------------------------------
        $shareholdersData = CompanyShareHolderPercentageModel::with('shareHolder:name,id')
            ->where('company_id', $company->id)
            ->select('share_holder_id', 'year', 'percentage')
            ->orderBy('year', 'asc')
            ->get()
            ->groupBy('year')
            ->map(function ($yearGroup, $year) {
                $formattedData = $yearGroup->sortByDesc('percentage')->map(function ($shareHolderPercentage) {
                    return [
                        'name'       => $shareHolderPercentage->shareHolder->name,
                        'percentage' => $shareHolderPercentage->percentage,
                    ];
                })->values();
                return [
                    'year'         => $year,
                    'shareholders' => $formattedData,
                ];
            })
            ->values();
        $company->share_holders = $shareholdersData;

        // ---------------------------------------------------------------
        // Track company view
        // ---------------------------------------------------------------
        if ($request->user()) {
            $investor = $request->user();

            $view = InvestorCompanyViewModel::where('investor_id', $investor->id)
                ->where('company_id', $company->id)
                ->first();

            if ($view) {
                $view->touch();
            } else {
                InvestorCompanyViewModel::create([
                    'investor_id' => $investor->id,
                    'company_id'  => $company->id,
                ]);
            }
        }

        // ---------------------------------------------------------------
        // Similar stocks
        // ---------------------------------------------------------------
        $similarStocks = collect();

        if ($company->sector_id) {
            $similarStocks = CompanyModel::approved()->where('sector_id', $company->sector_id)
                ->where('id', '!=', $company->id)
                ->where('is_deleted', 0)
                ->where(function ($q) {
                    $q->whereNull('category')
                        ->orWhere('category', '!=', PreIpoCategoryEnum::listed->value);
                })
                ->select('id', 'brand_name', 'logo', 'share_price', 'distributer_price', 'base_price', 'category', 'bg_color_code')
                ->with([
                    'fundamentals' => fn($q) => $q->select('company_id', 'lot_size', 'fifty_two_week_high', 'fifty_two_week_low'),
                ])
                ->orderByRaw('list_order IS NULL')
                ->orderBy('list_order', 'asc')
                ->orderBy('brand_name', 'asc')
                ->limit(10)
                ->get()
                ->each(fn($c) => $c->setAppends([]));
        }

        if ($similarStocks->isEmpty()) {
            $similarStocks = CompanyModel::approved()->where('is_trending', 1)
                ->where('id', '!=', $company->id)
                ->where('is_deleted', 0)
                ->where(function ($q) {
                    $q->whereNull('category')
                        ->orWhere('category', '!=', PreIpoCategoryEnum::listed->value);
                })
                ->select('id', 'brand_name', 'logo', 'share_price', 'distributer_price', 'base_price', 'category', 'bg_color_code')
                ->with([
                    'fundamentals' => fn($q) => $q->select('company_id', 'lot_size', 'fifty_two_week_high', 'fifty_two_week_low'),
                ])
                ->orderByRaw('list_order IS NULL')
                ->orderBy('list_order', 'asc')
                ->orderBy('brand_name', 'asc')
                ->limit(10)
                ->get()
                ->each(fn($c) => $c->setAppends([]));
        }

        $company->similar_stocks = $similarStocks;

        // ---------------------------------------------------------------
        // Grab opportunity slots
        // ---------------------------------------------------------------
        if (
            $company->is_grab_opportunity_enabled == 1 &&
            $company->grabOpportunitySlots->count() > 0
        ) {
            $retailerPrice = (float) $company->share_price;
            $basePrice     = (float) $company->base_price;

            $grabOpportunityData = $company->grabOpportunitySlots
                ->map(function ($slot) use ($retailerPrice, $basePrice) {
                    $perSharePrice = ($slot->slot_number == 1)
                        ? $retailerPrice
                        : $basePrice * (1 + ($slot->percentage / 100));

                    return [
                        'slot_number'     => $slot->slot_number,
                        'slot_range'      => $slot->max_amount
                            ? '₹' . number_format($slot->min_amount, 0) . ' - ₹' . number_format($slot->max_amount, 0)
                            : '₹' . number_format($slot->min_amount, 0) . ' and above',
                        'min_amount'      => (float) $slot->min_amount,
                        'max_amount'      => $slot->max_amount ? (float) $slot->max_amount : null,
                        'per_share_price' => round($perSharePrice, 2),
                        'percentage'      => (float) $slot->percentage,
                    ];
                })
                ->sortBy('slot_number')
                ->values();

            $company->min_share_price_as_slot = $grabOpportunityData->min('per_share_price');
            $company->grab_opportunity        = $grabOpportunityData;
        }

        $company->unsetRelation('grabOpportunitySlots');
        $company->unsetRelation('sharePrices');

        // Financial highlights
        $company->financial_highlights = $this->extractFinancialHighlights($company->customData);

        return UtillsHelper::json(1, [
            'message' => 'Company detail retrieved successfully',
            'data'    => $company,
        ]);
    }

    /**
     * Extract specific financial metrics from custom_data
     * Returns data separated by P&L, Balance Sheet, and Cashflow for last 3 years
     */
    private function extractFinancialHighlights($customData)
    {
        // Define which metrics to extract from each statement (+ AI / alternate labels)
        $extractConfig = [
            'pl_statement' => [
                'Revenue' => ['Revenue', 'Interest Earned', 'Total Income', 'Income', 'Net Sales', 'Sales'],
                'EBITDA' => ['EBITDA', 'Operating Profit', 'Ebit'],
                'PAT' => ['PAT', 'Net Profit', 'Profit After Tax', 'Profit After Taxation'],
            ],
            'balance_sheet' => [
                'Fixed Assets' => ['Fixed Assets', 'Total Assets', 'Property Plant And Equipment', 'Net Fixed Assets'],
                'Total Liabilities' => ['Total Liabilities', 'Liabilities'],
                'Reserves' => ['Reserves', 'Reserves And Surplus', 'Other Equity'],
            ],
            'cashflow' => [
                'Cashflow from Operations' => ['Cashflow From Operations', 'Cash Flow From Operations', 'Operating', 'Cash From Operating Activities'],
                'Borrowing' => ['Borrowing', 'Borrowings', 'Proceeds From Borrowings', 'Financing'],
                'Cash at the End' => ['Cash At The End', 'Cash And Cash Equivalents', 'Closing Cash'],
            ],
        ];

        $result = [
            'pl_statement' => [],
            'balance_sheet' => [],
            'cashflow' => [],
        ];

        $normalize = function ($str) {
            return ucwords(strtolower(trim((string) $str)));
        };

        foreach ($customData as $data) {
            $label = $data->label;

            if (!isset($extractConfig[$label])) {
                continue;
            }

            $values = \App\Helpers\CommonHelper::normalizeFinancialValuesToMatrix($data->values);
            if ($values === [] || !isset($values[0]) || !is_array($values[0]) || count($values[0]) < 2) {
                continue;
            }

            $headers = $values[0];
            if (!is_array($headers)) {
                continue;
            }

            $years = array_values(array_slice($headers, 1));
            if ($years === []) {
                continue;
            }

            $lastThreeYears = array_slice($years, -3);
            $yearIndices = [];
            foreach ($lastThreeYears as $year) {
                $idx = array_search($year, $headers, true);
                if ($idx === false) {
                    $idx = array_search($year, $headers);
                }
                $yearIndices[] = $idx;
            }

            $metricsData = [];
            foreach ($extractConfig[$label] as $metricName => $aliases) {
                $aliasNorms = array_map($normalize, $aliases);
                foreach ($values as $rowIndex => $row) {
                    if ($rowIndex === 0 || !is_array($row) || !array_key_exists(0, $row)) {
                        continue;
                    }
                    if (in_array($normalize($row[0]), $aliasNorms, true)) {
                        $metricsData[$metricName] = $row;
                        break;
                    }
                }
            }

            foreach ($lastThreeYears as $index => $year) {
                $yearIndex = $yearIndices[$index] ?? false;
                if ($yearIndex === false) {
                    continue;
                }
                $yearData = [
                    'year' => str_starts_with((string) $year, 'FY') ? (string) $year : ('FY ' . $year),
                ];

                foreach ($extractConfig[$label] as $metricName => $aliases) {
                    $yearData[$metricName] = ($metricsData[$metricName][$yearIndex] ?? null);
                }

                $result[$label][] = $yearData;
            }
        }

        return $result;
    }
    public function companyViewAll(): JsonResponse
    {
        $request = request();
        $oneYearAgo = now()->subYear();

        $withRelations = [
            'fundamentals' => function ($query) {
                $query->select('company_id', 'lot_size', 'fifty_two_week_high', 'fifty_two_week_low', 'depository');
            },
            'sharePrices' => function ($query) use ($oneYearAgo) {
                $query->select('company_id', 'price', 'date')
                    ->whereDate('date', '>=', $oneYearAgo)
                    ->orderBy('date', 'asc');
            },
        ];

        $query = CompanyModel::approved()->where('is_deleted', '0')
            ->where('status', '0')
            ->where('type', CompanyTypeEnum::unlisted->value)
            ->select(
                'id',
                'uuid',
                'brand_name',
                'logo',
                'category',
                'is_drhp',
                'bg_color_code',
                'share_price',
                'distributer_price',
                'base_price',
                'price_updated_today',
                'last_year_share_price'
            )
            ->with($withRelations);

        // Category filter logic
        if ($request->filled('category') && $request->category !== 'All') {
            $categoryEnum = PreIpoCategoryEnum::from($request->category);

            if ($categoryEnum === PreIpoCategoryEnum::drhp) {
                // Include null category; SQL `!=` alone drops NULLs.
                $query->where('is_drhp', 1)
                    ->where(function ($q) {
                        $q->whereNull('category')
                            ->orWhereNotIn('category', [
                                PreIpoCategoryEnum::coming_soon->value,
                                PreIpoCategoryEnum::listed->value,
                            ]);
                    });
            } elseif ($categoryEnum === PreIpoCategoryEnum::trending) {
                $query->where('is_trending', 1);
            } elseif ($categoryEnum === PreIpoCategoryEnum::listed) {
                $query->where('category', PreIpoCategoryEnum::listed->value);
            } else {
                $query->where('category', $categoryEnum->value);
            }
        } else {
            $query->where(function ($q) {
                $q->whereNull('category')
                    ->orWhere('category', '!=', PreIpoCategoryEnum::listed->value);
            });
        }

        if ($request->has('sector_id')) {
            $query->where('sector_id', $request->input('sector_id'));
        }

        if ($request->has('search')) {
            $query->where('brand_name', 'like', '%' . $request->input('search') . '%');
        }

        if ($request->has('skip') && $request->has('take')) {
            $skip = max(0, (int) $request->input('skip'));
            $take = max(1, (int) $request->input('take'));
            $query->skip($skip)->take($take);
        }

        $list = $query
            ->orderByRaw('list_order IS NULL')
            ->orderBy('list_order', 'asc')
            ->orderBy('brand_name', 'asc')
            ->get()
            ->each(fn($c) => $c->setAppends([]))
            ->map(fn($company) => $this->formatCompanyWithPrices($company))
            ->values();

        return UtillsHelper::json(1, [
            'message' => 'Market',
            'data'    => $list,
        ]);
    }

    function getProfile(): JsonResponse
    {
        $request = request();
        $investor = InvestorModel::where('id', $request->user()->id)
            ->with('city', 'country', 'state', 'investor_detail', 'kyc', 'dematAccount', 'relation')
            ->first();

        return UtillsHelper::investorProfileResponse($investor);
    }

    function saveProfile(): JsonResponse
    {
        $request = request();
        $valArray['visibility'] = ['required', Rule::enum(InvestorProfileVisibilityEnum::class)];
        $valArray['country_id'] = ['nullable'];
        $valArray['state_id'] = ['nullable'];
        $valArray['city_id'] = ['nullable'];
        $valArray['address'] = ['nullable'];
        $valArray['pincode'] = ['nullable', 'digits:6'];
        $valArray['gender'] = ['nullable', Rule::enum(GenderEnum::class)];

        $valArray['company_name'] = ['nullable', 'string', 'max:255'];
        $valArray['company_position'] = ['nullable', 'string', 'max:255'];
        $valArray['short_bio'] = ['nullable', 'string', 'max:255'];
        $valArray['facebook_link'] = ['nullable', 'url'];
        $valArray['twitter_link'] = ['nullable', 'url'];
        $valArray['instagram_link'] = ['nullable', 'url'];
        $valArray['linked_in_link'] = ['nullable', 'url'];
        $valArray['website_link'] = ['nullable', 'url'];
        $valArray['date_of_birth'] = ['nullable', 'date'];
        $validation = Validator::make($request->all(), $valArray);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }


        $investor = InvestorModel::where('id', $request->user()->id)->first();
        $investor->profile_visibility = $request->visibility;
        $investor->country_id = $request->country_id;
        $investor->state_id = $request->state_id;
        $investor->city_id = $request->city_id;
        $investor->address = $request->address;
        $investor->pincode = $request->pincode;
        $investor->gender = $request->gender;
        $investor->save();

        $investor->investor_detail()->updateOrCreate(
            [
                'investor_id' => $investor->id
            ],
            [
                'investor_company'  => $request->company_name,
                'investor_company_position'     => $request->company_position,
                'investor_bio' => $request->short_bio,
                'facebook_link' => $request->facebook_link,
                'twitter_link' => $request->twitter_link,
                'instagram_link' => $request->instagram_link,
                'linked_in_link' => $request->linked_in_link,
                'website_link' => $request->website_link,
                'date_of_birth' => $request->date_of_birth ? Carbon::parse($request->date_of_birth)->format('Y-m-d') : NULL
            ]
        );

        return UtillsHelper::json(1, ['message' => 'Profile Updated']);
    }

    function updateProfilePhoto(): JsonResponse
    {
        $request = request();
        $valArray = [
            'profile_photo' => [
                'required_without:profile_path',
                'mimes:' . CommonHelper::appSettings('file_image_extensions_allowed'),
                'max:' . UtillsHelper::maxFileImageSizeInKB(),
            ],
            'profile_path' => [
                'required_without:profile_photo',
                'string',
                'max:255',
            ],
        ];
        $validation = Validator::make($request->all(), $valArray);
        $validation->after(function ($validator) use ($request) {
            if ($request->hasFile('profile_photo') && $request->filled('profile_path')) {
                $validator->errors()->add('profile_path', 'You can only provide one of profile_photo or profile_path.');
            }
        });
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }
        $investor = InvestorModel::where('id', $request->user()->id)->first();
        if ($request->hasFile('profile_photo')) {
            $investor->profile_photo = FileUpDownHelper::investor_profile_photo_upload($request->file('profile_photo'));
        } elseif ($request->filled('profile_path')) {
            $investor->profile_photo = $request->input('profile_path');
        }
        $investor->save();

        return UtillsHelper::json(1, ['message' => 'Profile Photo Uploaded']);
    }

    function removeProfilePhoto(): JsonResponse
    {
        $request = request();
        $investor = InvestorModel::where('id', $request->user()->id)->first();
        $investor->profile_photo = NULL;
        $investor->save();

        return UtillsHelper::json(1, ['message' => 'Profile Photo Removed']);
    }

    function switchProfile(): JsonResponse
    {
        $request = request();
        $valArray['investor_id'] = ['required'];
        $validation = Validator::make($request->all(), $valArray);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }
        $investor = InvestorModel::where('is_deleted', 0)->where('id', $request->investor_id)->first();
        if ($investor) {
            if ($request->user()->id == $investor->parent_investor_id) {
                $investor->token = $investor->createToken('Child Investor login token')->plainTextToken;
                $investorWithDetails = $investor->load(['city', 'state', 'country', 'dematAccount', 'kyc', 'investor_detail']);
                return UtillsHelper::json(1, [
                    'message' => 'Login Success',
                    'data'  => $investorWithDetails
                ]);
            }
        }
        return UtillsHelper::json(0, ['message' => 'Not a valid investor']);
    }

    function checkMpin(): JsonResponse
    {
        $request = request();

        $validation = Validator::make($request->all(), [
            'mpin'     => 'required|numeric|digits:4',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        if (Hash::check($request->mpin, $request->user()->password) || $request->mpin == UtillsHelper::commonMpin()) {
            return UtillsHelper::json(1, ['message' => 'Mpin is valid']);
        } else {
            return UtillsHelper::json(0, ['message' => 'Mpin is invalid']);
        }
    }

    function news()
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'news_id' => 'required'
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $news = CompanyNewsModel::find($request->news_id);

        if (!$news) {
            return UtillsHelper::json(0, ['message' => 'News not found']);
        }

        return UtillsHelper::json(1, [
            'message' => 'News Details',
            'data' => $news
        ]);
    }

    function postFavoriteCompany()
    {
        $request = request();
        if ($request->is('api/*')) {
            $validation = Validator::make($request->all(), [
                'company_id' => 'required'
            ]);
            if ($validation->fails()) {
                return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
            }
            $company_id = $request->company_id;
            $investor_id = $request->user()->id;
        } else {
            $company_id = $request->company;
            $investor_id = Auth::guard('investor')->user()->id;
        }

        $is_data = InvestorFavouriteCompanyModel::where('company_id', $company_id)
            ->where('investor_id', $investor_id)
            ->first();
        if ($is_data) {
            $is_data->delete();
            return UtillsHelper::json(1, ['message' => 'Removed from Favourites!']);
        } else {
            $fav = new InvestorFavouriteCompanyModel();
            $fav->company_id = $company_id;
            $fav->investor_id = $investor_id;
            $fav->save();
            return UtillsHelper::json(1, ['message' => 'Marked as favourite!']);
        }
    }

    function getFavoriteCompany()
    {
        $request = request();
        $companies = InvestorFavouriteCompanyModel::where('investor_id', $request->user()->id)
            ->whereHas('company', function ($query) {
                $query->where('is_deleted', 0);
            })->with([
                'company.sector',
                'company.fundamentals',
                // 'company.sharePrices',
            ]);

        if ($request->skip) {
            $companies->skip($request->skip);
        }

        $companies->take(CommonHelper::appSettings('app_pagination_limit'));

        return UtillsHelper::json(
            1,
            [
                'message' => "Favorite Companies List",
                'data' => $companies->get(),
            ],
            200
        );
    }

    public function getNotifications(): JsonResponse|Builder
    {
        $request = request();

        if ($request->is('api/*')) {
            NotificationsModel::where('user_id', $request->user()->id)
                ->where('user_type', InvestorModel::class)
                ->update(['is_readed' => '1']);

            $notification = NotificationsModel::where('user_id', $request->user()->id)
                ->where('user_type', InvestorModel::class)
                ->orderby('id', 'desc');

            if ($request->has('start_date')) {
                $notification->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $notification->whereDate('created_at', '<=', $request->end_date);
            }

            if ($request->skip) {
                $notification->skip($request->skip);
            }

            $notification->take(CommonHelper::appSettings('app_pagination_limit'));
            $notifications = $notification->get();

            // Bifurcate notifications into Utility and News based on reference_type
            $utilityNotifications = [];
            $newsNotifications = [];

            foreach ($notifications as $item) {
                // Check if it's a news notification (has reference_type = 'preipo_news')
                // if (isset($item->payload['reference_type']) && $item->payload['reference_type'] === 'preipo_news') {
                if ($item->reference_type === 'preipo_news') {
                    // Fetch news details to add link and platform_name
                    $news = CompanyNewsModel::find($item->payload['reference_id']);
                    if ($news) {
                        $item->company_id = $news->company_id;
                        $item->link = $news->link;
                        $item->platform_name = $news->platform_name;
                    }
                    $newsNotifications[] = $item;
                } else {
                    // All other notifications are utility notifications
                    $utilityNotifications[] = $item;
                }
            }

            return UtillsHelper::json(
                1,
                [
                    'message' => "Notification List",
                    'data' => [
                        'utility' => $utilityNotifications,
                        'news' => $newsNotifications,
                    ],
                ],
                200
            );
        } else {
            NotificationsModel::where('user_id', Auth::guard('investor')->user()->id)
                ->where('user_type', InvestorModel::class)
                ->update(['is_readed' => '1']);

            $notification = NotificationsModel::where('user_id', Auth::guard('investor')->user()->id)
                ->where('user_type', InvestorModel::class)
                ->orderBy('id', 'desc');

            if ($request->has('start_date')) {
                $notification->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $notification->whereDate('created_at', '<=', $request->end_date);
            }

            return $notification->limit(200);
        }
    }

    public function utilityNotifications(): JsonResponse
    {
        $user = request()->user();
        $tz = config('app.timezone');

        $perPage = request()->get(
            'per_page',
            CommonHelper::appSettings('app_pagination_limit')
        );

        NotificationsModel::where('user_id', $user->id)
            ->where('user_type', InvestorModel::class)
            ->where(function ($q) {
                $q->whereNull('payload->reference_type')
                    ->orWhere('payload->reference_type', '!=', 'preipo_news');
            })
            ->update(['is_readed' => 1]);

        $query = NotificationsModel::where('user_id', $user->id)
            ->where('user_type', InvestorModel::class)
            ->where(function ($q) {
                $q->whereNull('payload->reference_type')
                    ->orWhere('payload->reference_type', '!=', 'preipo_news');
            })
            ->orderBy('id', 'desc');

        if (request()->filled('date_filter')) {
            switch (request()->date_filter) {
                case 'today':
                    $query->whereBetween('created_at', [
                        Carbon::today($tz)->startOfDay()->utc(),
                        Carbon::today($tz)->endOfDay()->utc(),
                    ]);
                    break;

                case 'week':
                    $query->whereBetween('created_at', [
                        Carbon::now($tz)->startOfWeek()->startOfDay()->utc(),
                        Carbon::now($tz)->endOfWeek()->endOfDay()->utc(),
                    ]);
                    break;

                case 'month':
                    $query->whereBetween('created_at', [
                        Carbon::now($tz)->startOfMonth()->startOfDay()->utc(),
                        Carbon::now($tz)->endOfMonth()->endOfDay()->utc(),
                    ]);
                    break;
            }
        }

        if (request()->filled('from_date') && request()->filled('to_date')) {
            $query->whereBetween('created_at', [
                Carbon::parse(request()->from_date, $tz)->startOfDay()->utc(),
                Carbon::parse(request()->to_date, $tz)->endOfDay()->utc(),
            ]);
        }

        $notifications = $query->paginate($perPage);

        return UtillsHelper::json(1, [
            'message' => 'Utility Notifications',
            'data' => $notifications->items()
        ], 200);
    }


    public function newsNotifications(): JsonResponse
    {
        $tz = config('app.timezone');

        $perPage = request()->get(
            'per_page',
            CommonHelper::appSettings('app_pagination_limit')
        );

        if (request()->filled('company_id')) {
            $news = CompanyNewsModel::where('company_id', request()->company_id)
                ->latest()
                ->paginate($perPage);

            return UtillsHelper::json(1, [
                'message' => 'News Notifications',
                'data' => $news->items()
            ], 200);
        }

        $query = NotificationsModel::where('user_id', request()->user()->id)
            ->where('user_type', InvestorModel::class)
            ->where('payload->reference_type', 'preipo_news')
            ->orderBy('id', 'desc');

        // Date filter (preset)
        if (request()->filled('date_filter')) {
            switch (request()->date_filter) {
                case 'today':
                    $query->whereBetween('created_at', [
                        Carbon::today($tz)->startOfDay()->utc(),
                        Carbon::today($tz)->endOfDay()->utc(),
                    ]);
                    break;

                case 'week':
                    $query->whereBetween('created_at', [
                        Carbon::now($tz)->startOfWeek()->startOfDay()->utc(),
                        Carbon::now($tz)->endOfWeek()->endOfDay()->utc(),
                    ]);
                    break;

                case 'month':
                    $query->whereBetween('created_at', [
                        Carbon::now($tz)->startOfMonth()->startOfDay()->utc(),
                        Carbon::now($tz)->endOfMonth()->endOfDay()->utc(),
                    ]);
                    break;
            }
        }

        // Date range filter
        if (request()->filled('from_date') && request()->filled('to_date')) {
            $query->whereBetween('created_at', [
                Carbon::parse(request()->from_date, $tz)->startOfDay()->utc(),
                Carbon::parse(request()->to_date, $tz)->endOfDay()->utc(),
            ]);
        }

        // Company filter — directly on company_news.company_id
        // if (request()->filled('company_id')) {
        //     // Step 1: Get all company_news IDs belonging to that company
        //     $newsIds = CompanyNewsModel::where('company_id', request()->company_id)
        //         ->latest()
        //         ->limit(15)
        //         ->pluck('id');

        //     // Step 2: Filter notifications whose payload->reference_id is in those IDs
        //     $query->whereIn(
        //         DB::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.reference_id'))"),
        //         $newsIds
        //     );
        // }

        $notifications = $query->paginate($perPage);

        // Get ALL unique news IDs from current page
        $newsIds = collect($notifications->items())
            ->pluck('reference_id')
            ->filter()
            ->unique()
            ->values();

        // Fetch all matching news
        $newsMap = CompanyNewsModel::whereIn('id', $newsIds)
            ->get()
            ->keyBy('id');

        foreach ($notifications->items() as $notification) {
            $news = $newsMap[$notification->reference_id] ?? null;
            $notification->link = $news?->link;
            $notification->platform_name = $news?->platform_name;
        }

        return UtillsHelper::json(1, [
            'message' => 'News Notifications',
            'data' => $notifications->items()
        ], 200);
    }


    public function getApplicableCoupons(Request $request): JsonResponse
    {
        $validation = Validator::make($request->all(), [
            'company_id'        => 'nullable',
            'transaction_type'  => 'nullable',
            'investment_amount' => 'nullable',
            'is_active'         => 'nullable',
            'code'              => 'nullable',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $now              = Carbon::now();
        $investor         = $request->user();
        $companyId        = $request->integer('company_id');
        $transactionType  = $request->input('transaction_type');
        $investmentAmount = $request->input('investment_amount', 0);
        $couponCode       = $request->input('code') ? strtoupper(trim($request->input('code'))) : null;

        $isActiveParam    = $request->has('is_active') ? (int) $request->input('is_active') : null;
        $companyIdPassed  = $request->has('company_id') && $companyId;

        if ($companyId && !CompanyModel::approved()->where('id', $companyId)->exists()) {
            return UtillsHelper::json(0, ['message' => 'Invalid company selected']);
        }

        $validTransactionTypes = ['primary', 'secondary', 'pre_ipo', 'all'];
        if ($transactionType && !in_array($transactionType, $validTransactionTypes)) {
            return UtillsHelper::json(0, ['message' => 'Invalid transaction type. Must be one of: primary, secondary, pre_ipo, all']);
        }

        $applyExpiryFilter = false;
        $expiredOnly       = false;

        if ($isActiveParam === 1 && !$companyIdPassed) {
            $applyExpiryFilter = true;
            $expiredOnly       = false;
        } elseif ($isActiveParam === 1 && $companyIdPassed) {
            $applyExpiryFilter = true;
            $expiredOnly       = false;
        } elseif ($isActiveParam === 0 && $companyIdPassed) {
            $applyExpiryFilter = true;
            $expiredOnly       = true;
        }

        $investorCoupons = InvestorCouponModel::where('investor_id', $investor->id)
            ->whereIn('status', [
                InvestorCouponStatusEnum::active->value,
                InvestorCouponStatusEnum::assigned->value,
            ])
            ->with(['coupon.company', 'coupon.companies'])
            ->get();

        $investorCoupons = $investorCoupons
            ->filter(fn($ic) => $ic->coupon && $ic->coupon->is_active)
            ->filter(function ($ic) use ($applyExpiryFilter, $expiredOnly, $now) {
                $isExpired = $ic->coupon->valid_to && $ic->coupon->valid_to->lt($now);

                if (!$applyExpiryFilter) {
                    return true;
                }

                if ($expiredOnly) {
                    return $isExpired;
                }

                return !$isExpired;
            })->filter(function ($ic) use ($companyId) {
                if (!$companyId) {
                    return true;
                }

                $coupon = $ic->coupon;

                if (is_null($coupon->company_id) && $coupon->companies->isEmpty()) {
                    if ($coupon->type === CouponTypeEnum::grabopportunity->value) {
                        return CompanyModel::approved()->where('id', $companyId)
                            ->where('is_grab_opportunity_enabled', 1)
                            ->where('is_deleted', 0)
                            ->exists();
                    }
                    return true;
                }

                if ($coupon->company_id) {
                    return (int) $coupon->company_id === (int) $companyId;
                }

                if ($coupon->companies->isNotEmpty()) {
                    return $coupon->companies->contains('id', $companyId);
                }

                return false;
            });

        $assignedCouponIds  = $investorCoupons->pluck('coupon_id')->toArray();
        $excludeFromGeneral = $assignedCouponIds;

        $query = MasterCouponModel::query()
            ->where('is_active', 1)
            ->where('is_private', 0)
            ->where('is_referral_coupon', 0)
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            });

        if ($applyExpiryFilter) {
            if ($expiredOnly) {
                $query->where('valid_to', '<', $now);
            } else {
                $query->where(function ($q) use ($now) {
                    $q->whereNull('valid_to')->orWhere('valid_to', '>=', $now);
                });
            }
        }

        if (!empty($excludeFromGeneral)) {
            $query->whereNotIn('id', $excludeFromGeneral);
        }

        if ($couponCode) {
            $query->where('code', 'like', '%' . $couponCode . '%');
        }

        if ($companyId) {
            $query->where(function ($q) use ($companyId) {
                $q->where(function ($subQ) use ($companyId) {
                    $subQ->whereNull('company_id')
                        ->whereDoesntHave('companies')
                        ->where(function ($innerQ) use ($companyId) {
                            $innerQ->where('type', '!=', CouponTypeEnum::grabopportunity->value)
                                ->orWhereExists(function ($existsQ) use ($companyId) {
                                    $existsQ->select(DB::raw(1))
                                        ->from('company')
                                        ->where('company.id', $companyId)
                                        ->where('company.is_grab_opportunity_enabled', 1)
                                        ->where('company.is_deleted', 0);
                                });
                        });
                })
                    ->orWhere('company_id', $companyId)
                    ->orWhereHas('companies', fn($subQ) => $subQ->where('coupon_company.company_id', $companyId));
            });
        } else {
            if ($couponCode) {
                $query->whereNull('company_id')->whereDoesntHave('companies');
            }
        }

        if ($transactionType && $transactionType !== 'all') {
            $query->where(function ($q) use ($transactionType) {
                $q->where('applies_on', 'all')->orWhere('applies_on', $transactionType);
            });
        }

        if ($investmentAmount > 0) {
            $query->where(function ($q) use ($investmentAmount) {
                $q->whereNull('min_investment_amount')
                    ->orWhere('min_investment_amount', '<=', $investmentAmount);
            });
        }

        $masterCoupons = $query->with(['company', 'companies'])
            ->get()
            ->filter(function ($coupon) use ($investor) {
                // if (!is_null($coupon->usage_limit_per_user)) {
                //     $userUsedCount = InvestorCouponModel::where('coupon_id', $coupon->id)
                //         ->where('investor_id', $investor->id)
                //         ->where('status', InvestorCouponStatusEnum::redeemed->value)
                //         ->count();
                //     if ($userUsedCount >= $coupon->usage_limit_per_user) return false;
                // }

                if (!is_null($coupon->usage_limit_global)) {
                    $usedCount = InvestorCouponModel::where('coupon_id', $coupon->id)
                        ->where('status', InvestorCouponStatusEnum::redeemed->value)
                        ->count();
                    if ($usedCount >= $coupon->usage_limit_global) return false;
                }

                return true;
            });

        $formatCoupon = function ($coupon, $investorCouponId = null) use ($investmentAmount, $investor, $couponCode, $now) {
            $discountAmount = 0;
            $badgeText      = '';
            $badgeType      = 'percentage';
            $isExpired      = $coupon->valid_to && $coupon->valid_to->lt($now);

            if ($coupon->type === CouponTypeEnum::flatdiscount->value) {
                $discountAmount = (float) $coupon->discount_value;
                $badgeText      = '₹' . number_format($discountAmount);
            } elseif ($coupon->type === CouponTypeEnum::percentagediscount->value) {
                $badgeText = $coupon->discount_value . '%';
                if ($investmentAmount > 0) {
                    $discountAmount = ($investmentAmount * $coupon->discount_value) / 100;
                    if ($coupon->max_discount_amount && $discountAmount > $coupon->max_discount_amount) {
                        $discountAmount = (float) $coupon->max_discount_amount;
                    }
                } else {
                    $discountAmount = (float) $coupon->discount_value;
                }
            } elseif ($coupon->type === CouponTypeEnum::persharediscount->value) {
                $discountAmount = (float) $coupon->discount_value;
                $badgeText      = '₹' . number_format($discountAmount);
                $badgeType      = 'icon';
            } elseif ($coupon->type === CouponTypeEnum::cashback->value) {
                $discountAmount = (float) $coupon->discount_value;
                $badgeText      = '₹' . number_format($discountAmount);
            } elseif ($coupon->type === CouponTypeEnum::grabopportunity->value) {
                $badgeText      = 'Best Price';
                $badgeType      = 'icon';
                $discountAmount = 0;
            }

            $companyScope = CouponCompanyScopeEnum::all;
            $companyList  = [];

            if ($coupon->company_id && $coupon->company) {
                $companyScope  = CouponCompanyScopeEnum::single;
                $companyList[] = [
                    'id'   => $coupon->company->id,
                    'logo' => $coupon->company->logo,
                    'name' => $coupon->company->brand_name ?? null,
                ];
            } elseif ($coupon->companies && $coupon->companies->count() > 0) {
                $companyScope = CouponCompanyScopeEnum::multiple;
                $companyList  = $coupon->companies
                    ->map(fn($c) => ['id' => $c->id, 'logo' => $c->logo, 'name' => $c->brand_name ?? null])
                    ->toArray();
            } elseif ($coupon->type === CouponTypeEnum::grabopportunity->value) {
                $companyList = CompanyModel::approved()->where('is_deleted', 0)
                    ->where('is_grab_opportunity_enabled', 1)
                    ->where(function ($q) {
                        $q->whereNull('category')
                            ->orWhere('category', '!=', PreIpoCategoryEnum::listed->value);
                    })
                    ->orderBy('brand_name', 'asc')
                    ->get(['id', 'logo', 'brand_name'])
                    ->map(fn($c) => ['id' => $c->id, 'logo' => $c->logo, 'name' => $c->brand_name ?? null])
                    ->toArray();
            }

            $isApplied = false;
            if (!is_null($coupon->usage_limit_per_user)) {
                $redeemedCount = InvestorCouponModel::where('investor_id', $investor->id)
                    ->where('coupon_id', $coupon->id)
                    ->where('status', InvestorCouponStatusEnum::redeemed->value)
                    ->count();
                $isApplied = $redeemedCount >= $coupon->usage_limit_per_user;
            }

            return [
                'id'                    => $coupon->id,
                'uuid'                  => $coupon->uuid,
                'code'                  => $coupon->code,
                'investor_coupon_id'    => $investorCouponId,
                'type'                  => $coupon->type,
                'applies_on'            => $coupon->applies_on,
                'badge'                 => ['type' => $badgeType, 'text' => $badgeText, 'icon' => $badgeType === 'icon' ? 'bulb' : null],
                'description'           => $coupon->description,
                'terms_conditions'      => $coupon->terms_conditions,
                'discount_value'        => (float) $coupon->discount_value,
                'max_discount_amount'   => $coupon->max_discount_amount ? (float) $coupon->max_discount_amount : null,
                'estimated_discount'    => $discountAmount ?: null,
                'min_investment_amount' => $coupon->min_investment_amount ? (float) $coupon->min_investment_amount : null,
                'valid_from'            => optional($coupon->valid_from)->format('Y-m-d H:i:s'),
                'valid_to'              => optional($coupon->valid_to)->format('Y-m-d H:i:s'),
                'valid_to_formatted'    => $coupon->valid_to ? $coupon->valid_to->format('d/m/Y') : null,
                'validity_text'         => $couponCode && $coupon->valid_to ? 'Valid till ' . $coupon->valid_to->format('d/m/Y') : null,
                'is_applied'            => $isApplied,
                'is_expired'            => $isExpired,
                'is_personal'           => $investorCouponId !== null,
                'company_scope'         => $companyScope->value,
                'company_list'          => $companyList,
                'is_referral'           => false,
                '_sort_discount'        => $discountAmount,
            ];
        };

        $personalFormatted = $investorCoupons
            ->map(fn($ic) => $formatCoupon($ic->coupon, $ic->id))
            ->values();

        $generalFormatted = $masterCoupons
            ->map(fn($coupon) => $formatCoupon($coupon, null))
            ->values();

        $allCoupons = collect($personalFormatted->toArray())
            ->concat($generalFormatted->toArray())
            ->sortByDesc('_sort_discount')
            ->map(fn($c) => collect($c)->except('_sort_discount')->toArray())
            ->values();

        return UtillsHelper::json(1, [
            'message' => $couponCode ? 'Search Results for Coupons' : 'Applicable Coupons',
            'data'    => $allCoupons,
        ]);
    }


    function portfolioPreIpo(): JsonResponse
    {
        return $this->invRepo->getPortfolioPreIpo();
    }

    function portfolioPreIpoDetail(): JsonResponse
    {
        return $this->invRepo->getPortfolioPreIpoDetail();
    }

    public function preIpoTransactions(): JsonResponse
    {
        $request = request();

        $query = PreIpoModel::where('investor_id', $request->user()->id);

        if ($request->has('is_processing')) {
            $isProcessing = filter_var($request->is_processing, FILTER_VALIDATE_BOOLEAN);

            if ($isProcessing) {
                $query->whereIn('status', [0, 2, 3, 4]);
            } else {
                $query->where('status', 5);
            }
        }

        if ($request->filled('date_filter')) {
            switch ($request->date_filter) {
                case 'today':
                    $query->whereDate('created_at', Carbon::today());
                    break;

                case 'week':
                    $query->whereBetween('created_at', [
                        Carbon::now()->startOfWeek(),
                        Carbon::now()->endOfWeek(),
                    ]);
                    break;

                case 'month':
                    $query->whereMonth('created_at', Carbon::now()->month)
                        ->whereYear('created_at', Carbon::now()->year);
                    break;
            }
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay(),
            ]);
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }


        $transactions = $query
            ->with('company')
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get()
            ->map(function ($transaction) {
                $transaction->company?->makeHidden(['transaction']);
                $transaction->status_list = PreIpoTransactionHelper::getStatusListForApplicationV2($transaction);

                if (in_array($transaction->status, [1, 5])) {
                    $transaction->makeHidden('transaction_cancel_timer');
                }
                return $transaction;
            });

        return UtillsHelper::json(1, [
            'message' => 'Transaction List',
            'data'    => $transactions
        ], 200);
    }

    function preIpoTransactionsDetails(): JsonResponse
    {
        return $this->invRepo->getPreIpoTransactionDetails();
    }

    public function investedCompanies(): JsonResponse
    {
        $request = request();
        $investorId = $request->user()->id;

        $companyIds = [];

        if ($request->filled('company_ids')) {
            $companyIds = is_array($request->company_ids)
                ? $request->company_ids
                : explode(',', $request->company_ids);
        }

        $data = DB::table('company')
            ->whereIn('id', function ($q) use ($investorId, $companyIds) {
                $q->select('company_id')
                    ->from('pre_ipo_transaction')
                    ->where('investor_id', $investorId)
                    ->where('status', 5);

                if (!empty($companyIds)) {
                    $q->whereIn('company_id', $companyIds);
                }
            })
            ->select('id', 'company_name as name')
            ->get();

        return UtillsHelper::json(1, [
            'message' => 'Invested companies fetched successfully',
            'data'    => $data
        ], 200);
    }


    function transactionList(): JsonResponse
    {
        $request = request();

        $transactions = PrimaryTransactionModel::whereHas('investor', function ($query) use ($request) {
            $query->where('partner_id', $request->user()->id);
        })->with(['startup.details', 'startup.cms', 'investor']);

        if ($request->pending_doc_sign) {
            $transactions->wherein('status', [2, 5, 9]);
        }

        if ($request->pending_payment) {
            $transactions->where('status', 6);
        }

        return UtillsHelper::json(
            1,
            [
                'message' => 'Transaction List',
                'data' => $transactions->get()
            ],
            200
        );
    }

    function transactionDetails(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'transaction_id'  => 'required',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(
                0,
                [
                    'message' => $validation->errors()->first()
                ],
                200
            );
        }

        $transaction = PrimaryTransactionModel::with([
            'startup.cms',
            'investor'
        ])->where('id', $request->transaction_id)
            ->whereHas('investor', function ($query) use ($request) {
                $query->where('partner_id', $request->user()->id);
            })
            ->first();


        if (!$transaction) {
            return UtillsHelper::json(0, ['message' => 'Transaction not found or does not belong to the partner.']);
        }
        $transaction->status_list = PrimaryTransactionHelper::getStatusListForApplication($transaction);

        return UtillsHelper::json(1, ['message' => 'Transaction details fetched successfully', 'data' => $transaction]);
    }

    function secTransaction(): JsonResponse
    {
        $request = request();
        $sellRequests = SecondarySellRequestModel::where('investor_id', $request->user()->id)
            ->with('startup.cms')
            ->with(['transactions' => function ($query) {
                $query->whereNotIn('status', [2, 3])->with('buyer');
            }])
            ->orderby('id', 'desc')
            ->get();

        $list = [];
        foreach ($sellRequests as $sellRequest) {
            $list[] = [
                'type' => 'sell',
                'sell' => $sellRequest,
                'buy' => NULL,
                'created_at' => $sellRequest->created_at,
            ];
        }

        $transactions = SecondaryTransactionModel::where('buyer_id', $request->user()->id)
            ->with('startup.cms', 'escrow')
            ->get();

        foreach ($transactions as $transaction) {
            $list[] = [
                'type' => 'buy',
                'sell' => NULL,
                'buy' => $transaction, // Use $transaction here
                'created_at' => $transaction->created_at,
            ]; // Push the modified transaction to the list
        }

        usort($list, function ($a, $b) {
            return $b['created_at'] <=> $a['created_at']; // Adjusted for array access
        });

        return UtillsHelper::json(1, [
            'message' => 'Transaction List',
            'data' => $list
        ], 200);
    }

    function secTransactionDetails(): JsonResponse
    {
        return $this->invRepo->getSecTransactionDetails();
    }

    function portfolioDetails(): JsonResponse
    {
        return $this->invRepo->getPortfolioDetails();
    }

    public function portfolioStatus(): JsonResponse
    {
        $investorId = request()->user()->id;

        $startup = false;

        foreach (InstrumentTypeEnum::cases() as $instrumentType) {
            $hasInstrument = PortfolioModel::where('investor_id', $investorId)
                ->where('instrument', $instrumentType->value)
                ->where('shares', '>', 0)
                ->exists();

            if ($hasInstrument) {
                $startup = true;
                break;
            }
        }

        $preipo = PortfolioPreIpoModel::where('investor_id', $investorId)
            ->where('shares', '>', 0)
            ->exists();

        return UtillsHelper::json(
            1,
            [
                'message' => 'Portfolio Status',
                'data' => [
                    'startup' => $startup,
                    'preipo'  => $preipo,
                ]
            ],
            200
        );
    }

    function portfolio(): JsonResponse
    {
        return $this->invRepo->getPortfolio();
    }

    function documents(): JsonResponse
    {
        return $this->invRepo->getDocuments();
    }

    function recentTransaction(): JsonResponse
    {
        $request = request();
        $userId = $request->user()->id;
        $allTransactions = [];

        // Get primary transactions
        $primaryTransactions = PrimaryTransactionModel::where('investor_id', $userId)
            ->with('startup.details', 'startup.cms')
            ->get();

        foreach ($primaryTransactions as $transaction) {
            $allTransactions[] = [
                'type' => 'primary',
                'primary' => $transaction,
                'created_at' => $transaction->created_at
            ];
        }

        // Get secondary sell transactions
        $sellRequests = SecondarySellRequestModel::where('investor_id', $userId)
            ->with('startup.cms')
            ->with(['transactions' => function ($query) {
                $query->whereNotIn('status', [2, 3])->with('buyer');
            }])
            ->get();

        foreach ($sellRequests as $sellRequest) {
            $allTransactions[] = [
                'type' => 'secondary',
                'secondary' => [
                    'type' => 'sell',
                    'sell' => $sellRequest,
                    'buy' => null,
                ],
                'created_at' => $sellRequest->created_at
            ];
        }

        // Get secondary buy transactions
        $buyTransactions = SecondaryTransactionModel::where('buyer_id', $userId)
            ->with('startup.cms', 'escrow')
            ->get();

        foreach ($buyTransactions as $transaction) {
            $allTransactions[] = [
                'type' => 'secondary',
                'secondary' => [
                    'type' => 'buy',
                    'sell' => null,
                    'buy' => $transaction,
                ],
                'created_at' => $transaction->created_at
            ];
        }

        $preIpoTransactions = PreIpoModel::where('investor_id', $userId)
            ->with('company')
            ->get()
            ->map(function ($transaction) {
                $transaction->company?->makeHidden(['transaction']);
                $transaction->status_list =
                    PreIpoTransactionHelper::getStatusListForApplicationV2($transaction);

                if (in_array($transaction->status, [1, 5])) {
                    $transaction->makeHidden('transaction_cancel_timer');
                }

                return $transaction;
            });


        foreach ($preIpoTransactions as $transaction) {
            $allTransactions[] = [
                'type' => 'preipo',
                'preipo' => $transaction,
                'created_at' => $transaction->created_at
            ];
        }

        // Sort all transactions by created_at date (newest first)
        usort($allTransactions, function ($a, $b) {
            return $b['created_at'] <=> $a['created_at'];
        });

        return UtillsHelper::json(1, [
            'message' => 'All Transaction List',
            'data' => $allTransactions
        ], 200);
    }


    function logout(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'device'        => ['required', Rule::enum(DeviceTypeEnum::class)],
            'device_id'     => 'required'
        ], [], [
            'device' => 'The selected device is invalid. Valid options are: ' . implode(', ', array_column(DeviceTypeEnum::cases(), 'value'))
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }
        CoreFirebaseDeviceTokenModel::where('user_id', $request->user()->id)->where('user_type', InvestorModel::class)
            ->where('device', $request->device)->where('device_id', $request->device_id)->delete();
        $request->user()->currentAccessToken()->delete();
        return UtillsHelper::json(1, [
            'message' => 'Logout Success'
        ]);
    }

    function deleteAccount(Request $request): JsonResponse
    {
        $validation = Validator::make($request->all(), [
            'device'        => ['required', Rule::enum(DeviceTypeEnum::class)],
            'device_id'     => 'required'
        ], [], [
            'device' => 'The selected device is invalid. Valid options are: ' . implode(', ', array_column(DeviceTypeEnum::cases(), 'value'))
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $investor = InvestorModel::find($request->user()->id);
        if ($investor) {
            CommonHelper::deleteInvestors($investor);
        }
        $request->user()->currentAccessToken()->delete();
        return UtillsHelper::json(1, ['message' => 'Your Account is Deleted']);
    }

    function dashboard(): JsonResponse
    {
        return UtillsHelper::json(1, [
            'data'      => $this->invRepo->dashboard()
        ]);
    }

    function dashboardPreIpo(): JsonResponse
    {
        return UtillsHelper::json(1, [
            'data'      => $this->invRepo->preIpoDashboard()
        ]);
    }

    function preIpoSellTransactions(): JsonResponse
    {
        $request = request();
        $transactions = PreIpoSellRequestModel::where('investor_id', $request->user()->id)
            ->with([
                'company' => function ($query) {
                    $query->select('id', 'brand_name', 'logo');
                }
            ])
            ->orderby('id', 'desc')
            ->get()->map(function ($transaction) {
                $transaction->company->makeHidden(['transaction', 'share_price', 'distributer_price', 'base_price']);
                return $transaction;
            });
        return UtillsHelper::json(1, [
            'message' => 'Transaction List',
            'data' => $transactions
        ], 200);
    }

    public function preIpoBuy(): JsonResponse
    {
        $request = request();

        $validation = Validator::make($request->all(), [
            'investor_id'        => 'required',
            'company_id'         => 'required',
            'shares'             => 'required|integer|min:1',
            'share_price'        => 'required|numeric',
            'distributer_price'  => 'required|numeric',
            'is_distributer'     => 'required|boolean',
            'payment_mode'       => ['required', Rule::enum(PrimaryTransactionPaymentMode::class)],
            'investor_coupon_id' => 'nullable|integer',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, [
                'message' => $validation->errors()->first()
            ]);
        }

        $company = CompanyModel::approved()->find($request->company_id);
        if (!$company) {
            return UtillsHelper::json(0, [
                'message' => 'No company found for company_id: ' . $request->company_id
            ]);
        }

        try {

            $transaction = DB::transaction(function () use ($request, $company) {

                $calculation = TransactionCalculationHelper::calculateTransactionAmount(
                    $request->company_id,
                    $request->shares,
                    null,                            // master_coupon_id = null (not used here)
                    'pre_ipo',                       // transaction type
                    $request->investor_coupon_id
                );

                $transaction = new PreIpoModel();
                $transaction->status = 0;
                $transaction->investor_id = $request->investor_id;
                $transaction->company_id = $request->company_id;
                $transaction->shares = $request->shares;
                $transaction->share_price = $request->share_price;
                $transaction->distributer_price = $request->distributer_price;
                $transaction->investment_amount = $calculation['investment_amount'];
                $transaction->is_distributer = $request->is_distributer;
                $transaction->instrument = InstrumentTypeEnum::equity;
                $transaction->payment_mode = $request->payment_mode;
                $transaction->settlement_date = $calculation['settlement_date'];
                // Add 97hr timer for cancellation
                $transaction->transaction_cancel_timer = now()->addHours(97);
                $transaction->payable_amount = $calculation['payable_amount'];

                if ($request->filled('investor_coupon_id')) {
                    $transaction->investor_coupon_id = $request->investor_coupon_id;
                    $transaction->coupon_code_snapshot = $calculation['coupon_code'];
                    $transaction->coupon_discount_amount = $calculation['coupon_discount'];
                }

                $transaction->save();

                /**
                 * Redeem coupon (LOCKED)
                 */
                // REPLACE the redemption block in preIpoBuy:
                if ($request->filled('investor_coupon_id')) {
                    $investorCoupon = InvestorCouponModel::where('id', $request->investor_coupon_id)
                        ->where('investor_id', $request->investor_id)
                        ->whereIn('status', [
                            InvestorCouponStatusEnum::assigned->value,
                            InvestorCouponStatusEnum::active->value,  // add active too since calculate-transaction sets assigned, but direct buy might pass active
                        ])
                        ->first();

                    if (!$investorCoupon) {
                        throw new Exception('Coupon is invalid, expired or already used.');
                    }

                    $investorCoupon->status                        = InvestorCouponStatusEnum::redeemed->value;
                    $investorCoupon->redeemed_pre_ipo_transaction_id = $transaction->id;
                    $investorCoupon->redeemed_at                   = now();
                    $investorCoupon->save();
                }

                return $transaction;
            });
        } catch (Throwable $e) {
            return UtillsHelper::json(0, [
                'message' => $e->getMessage()
            ]);
        }

        $transaction->load('company');
        $transaction->makeHidden(['investor']);

        $company = $transaction->company;

        $allCompanies = CompanyModel::approved()->where('is_deleted', 0)
            ->where(function ($q) {
                $q->whereNull('category')
                    ->orWhere('category', '!=', PreIpoCategoryEnum::listed->value);
            })
            ->select('id', 'logo')
            ->orderByRaw('list_order IS NULL')
            ->orderBy('list_order', 'asc')
            ->orderBy('brand_name', 'asc')
            ->take(6)
            ->get()
            ->map(fn($c) => [
                'id'   => $c->id,
                'logo' => $c->logo
            ]);
        $similarStocks = [];

        if ($company && $company->sector_id) {
            $similarStocks = CompanyModel::approved()->where('sector_id', $company->sector_id)
                ->where('id', '!=', $company->id)
                ->where('is_deleted', 0)
                ->where(function ($q) {
                    $q->whereNull('category')
                        ->orWhere('category', '!=', PreIpoCategoryEnum::listed->value);
                })
                ->select(
                    'id',
                    'brand_name',
                    'logo',
                    'share_price',
                    'distributer_price',
                    'base_price',
                    'category',
                    'bg_color_code',
                    'min_investment_type'
                )
                ->with([
                    'fundamentals' => function ($query) {
                        $query->select(
                            'company_id',
                            'lot_size',
                            'fifty_two_week_high',
                            'fifty_two_week_low'
                        );
                    }
                ])
                ->orderByRaw('list_order IS NULL')
                ->orderBy('list_order', 'asc')
                ->orderBy('brand_name', 'asc')
                ->limit(10)
                ->get()
                ->makeHidden('is_favorite');
        }

        if ($similarStocks->isEmpty()) {
            $similarStocks = CompanyModel::approved()->where('is_trending', 1)
                ->where('id', '!=', $company->id)
                ->where('is_deleted', 0)
                ->where(function ($q) {
                    $q->whereNull('category')
                        ->orWhere('category', '!=', PreIpoCategoryEnum::listed->value);
                })
                ->select(
                    'id',
                    'brand_name',
                    'logo',
                    'share_price',
                    'distributer_price',
                    'base_price',
                    'category',
                    'bg_color_code',
                    'min_investment_type'
                )
                ->with([
                    'fundamentals' => function ($query) {
                        $query->select(
                            'company_id',
                            'lot_size',
                            'fifty_two_week_high',
                            'fifty_two_week_low'
                        );
                    }
                ])
                ->orderByRaw('list_order IS NULL')
                ->orderBy('list_order', 'asc')
                ->orderBy('brand_name', 'asc')
                ->limit(10)
                ->get()
                ->makeHidden('is_favorite');
        }

        $statusList = PreIpoTransactionHelper::getStatusListForApplicationV2($transaction);

        $currentTime = now();
        $startTime = $currentTime->copy()->setTime(10, 30);
        $endTime = $currentTime->copy()->setTime(17, 0);
        $dayOfWeek = $currentTime->dayOfWeek;

        if ($dayOfWeek === 0 || $dayOfWeek === 6) {
            $responseMessage = 'Your order placed on weekend. It will be processed on the next working day.';
        } elseif ($currentTime->between($startTime, $endTime)) {
            $responseMessage = 'Your order placed successfully. It will be confirmed within 2 hours.';
        } else {
            $responseMessage = 'Your order placed outside transaction hours. It will be processed on the next business day.';
        }

        return UtillsHelper::json(1, [
            'message' => $responseMessage,
            'data' => [
                'transaction' => $transaction,
                'all'             => $allCompanies,
                'similar_stocks'  => $similarStocks,
                'status_list'     => $statusList,
            ]
        ]);
    }

    public function preIpoTransactionMore(): JsonResponse
    {
        $request = request();

        $validation = Validator::make($request->all(), [
            'transaction_id' => 'required',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, [
                'message' => $validation->errors()->first()
            ]);
        }

        $transaction = PreIpoModel::with([
            'company',
            'investor',
            'seller',
            'payment',
            'statusLogs',
        ])->find($request->transaction_id);

        if (!$transaction) {
            return UtillsHelper::json(0, [
                'message' => 'No transaction found for transaction_id: ' . $request->transaction_id
            ]);
        }

        $transaction->makeHidden(['investor']);

        $company = $transaction->company;

        // ── All stocks (top 6 by list_order) ──────────────────────────────────────
        $allCompanies = CompanyModel::approved()->where('is_deleted', 0)
            ->where(function ($q) {
                $q->whereNull('category')
                    ->orWhere('category', '!=', PreIpoCategoryEnum::listed->value);
            })
            ->select('id', 'logo')
            ->orderByRaw('list_order IS NULL')
            ->orderBy('list_order', 'asc')
            ->orderBy('brand_name', 'asc')
            ->take(6)
            ->get()
            ->map(fn($c) => [
                'id'   => $c->id,
                'logo' => $c->logo,
            ]);

        // ── Similar stocks (same sector first, trending fallback) ─────────────────
        $similarStocks = collect();

        if ($company && $company->sector_id) {
            $similarStocks = CompanyModel::approved()->where('sector_id', $company->sector_id)
                ->where('id', '!=', $company->id)
                ->where('is_deleted', 0)
                ->where(function ($q) {
                    $q->whereNull('category')
                        ->orWhere('category', '!=', PreIpoCategoryEnum::listed->value);
                })
                ->select(
                    'id',
                    'brand_name',
                    'logo',
                    'share_price',
                    'distributer_price',
                    'base_price',
                    'category',
                    'bg_color_code',
                    'min_investment_type'
                )
                ->with([
                    'fundamentals' => fn($q) => $q->select(
                        'company_id',
                        'lot_size',
                        'fifty_two_week_high',
                        'fifty_two_week_low'
                    )
                ])
                ->orderByRaw('list_order IS NULL')
                ->orderBy('list_order', 'asc')
                ->orderBy('brand_name', 'asc')
                ->limit(10)
                ->get()
                ->makeHidden('is_favorite');
        }

        if ($similarStocks->isEmpty()) {
            $similarStocks = CompanyModel::approved()->where('is_trending', 1)
                ->where('id', '!=', $company?->id)
                ->where('is_deleted', 0)
                ->where(function ($q) {
                    $q->whereNull('category')
                        ->orWhere('category', '!=', PreIpoCategoryEnum::listed->value);
                })
                ->select(
                    'id',
                    'brand_name',
                    'logo',
                    'share_price',
                    'distributer_price',
                    'base_price',
                    'category',
                    'bg_color_code',
                    'min_investment_type'
                )
                ->with([
                    'fundamentals' => fn($q) => $q->select(
                        'company_id',
                        'lot_size',
                        'fifty_two_week_high',
                        'fifty_two_week_low'
                    )
                ])
                ->orderByRaw('list_order IS NULL')
                ->orderBy('list_order', 'asc')
                ->orderBy('brand_name', 'asc')
                ->limit(10)
                ->get()
                ->makeHidden('is_favorite');
        }

        // ── Status list (reflects live KYC state) ─────────────────────────────────
        $statusList = PreIpoTransactionHelper::getStatusListForApplicationV2($transaction);

        return UtillsHelper::json(1, [
            'message' => 'Transaction detail fetched successfully.',
            'data'    => array_merge(
                $transaction->toArray(),
                [
                    'status_list'    => $statusList,
                    'similar_stocks' => $similarStocks,
                    'all'            => $allCompanies,
                ]
            ),
        ]);
    }

    function preIpoSell(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'portfolio_id'              => 'required',
            'shares'                    => 'required|integer|min:1',
            'price'                     => 'required|numeric|min:0.01',
            'cmr'                       => 'required|file|mimes:png,jpg,jpeg,pdf|max:' . UtillsHelper::maxFileDocumentSizeInKB()
        ]);


        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $portfolio = PortfolioPreIpoModel::where('id', $request->input('portfolio_id'))->first();

        if (!$portfolio) {
            return UtillsHelper::json(0, ['message' => 'No portfolio found']);
        }
        $availableShares = $portfolio->shares - $portfolio->shares_sold;

        if ($availableShares < $request->shares) {
            return UtillsHelper::json(0, ['message' => 'Shares must be less than or equal to portfolio shares']);
        }

        $sellRequest = new PreIpoSellRequestModel;
        $sellRequest->status = 0;
        $sellRequest->company_id = $portfolio->company_id;
        $sellRequest->portfolio_id = $portfolio->id;
        $sellRequest->investor_id = $portfolio->investor_id;
        $sellRequest->shares = $request->shares;
        $sellRequest->price = $request->price;
        $sellRequest->purchase_price = $portfolio->purchase_price;
        $sellRequest->current_price = $portfolio->current_share_price;
        $sellRequest->last_traded_price = $portfolio->last_traded_price;
        $sellRequest->save();

        $file = FileUpDownHelper::upload_preipo_sell_cmr_document($request->file('cmr'));
        if ($file) {
            $sellRequest->file = $file;
            $sellRequest->save();
        }

        return UtillsHelper::json(1, ['message' => 'Sell Request placed']);
    }

    function cancelOrder(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'transaction_id' => 'required',
            'cancellation_reason' => 'required'
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }
        $transaction = PreIpoModel::where('id', $request->transaction_id)
            ->where('investor_id', $request->user()->id)
            ->first();
        if (!$transaction) {
            return UtillsHelper::json(0, ['message' => 'Transaction not found']);
        }
        if ($transaction->usesOrderStep() || $transaction->status != 0) {
            return UtillsHelper::json(0, ['message' => 'Transaction cannot be cancelled']);
        }
        $transaction->status = 1;
        $transaction->is_cancelled_by_investor = 1;
        $transaction->cancellation_reason = $request->cancellation_reason;
        $transaction->save();
        CancelNotificationJob::dispatch($transaction->id);
        // PreIpoTransactionHelper::cancelTransactionNotification($transaction);
        return UtillsHelper::json(1, ['message' => 'Transaction cancelled successfully']);
    }

    public function uploadPreIpoPaymentReceipt(): JsonResponse
    {
        $request = request();

        $validation = Validator::make($request->all(), [
            'transaction_id' => 'required',
            'receipt'               => 'required|file|mimes:png,jpg,jpeg,pdf|max:' . UtillsHelper::maxFileDocumentSizeInKB()
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, [
                'message' => $validation->errors()->first()
            ]);
        }

        $transaction = PreIpoModel::with(['investor', 'company'])
            ->where('id', $request->transaction_id)
            ->first();

        if (!$transaction) {
            return UtillsHelper::json(0, ['message' => 'Transaction not found']);
        }

        if ($transaction->usesOrderStep() || $transaction->status != 3) {
            return UtillsHelper::json(0, [
                'message' => 'Payment receipt can only be uploaded after deal slip is signed.'
            ]);
        }

        DB::beginTransaction();

        try {

            $filePath = FileUpDownHelper::upload_preipo_transaction_payment_receipt_document(
                $request->file('receipt')
            );

            if (!$filePath) {
                throw new Exception('File upload failed');
            }

            $meta = [
                'investor' => [$transaction->investor->id],
                'preipo_transaction' => [$transaction->id],
                'company' => [$transaction->company->id],
                'name' => 'Payment Receipt - ' . $transaction->company->brand_name,
                'sname' => 'Payment Receipt from - ' . $transaction->investor->name,
            ];

            $document = new DocumentsModel();
            $document->api_id = null;
            $document->path = $filePath;
            $document->signed_path = $filePath;
            $document->status = 1;
            $document->type = DocumentTypeEnum::paymentreceipt;
            $document->meta = $meta;
            $document->save();


            $preipoPayment = new PreIpoTransactionPaymentsModel();
            $preipoPayment->transaction_id = $transaction->id;
            $preipoPayment->document_id = $document->id;
            $preipoPayment->save();

            DB::commit();

            /*
        |--------------------------------------------------------------------------
        | Notify admin (we put msg over here later)
        |--------------------------------------------------------------------------
        */
            // UtillsHelper::sendNotification(
            //     null,
            //     null,
            //     'preipo-transaction',
            //     'Payment Receipt Uploaded',
            //     "Payment receipt uploaded for transaction #{$transaction->transaction_invoice_no}"
            // );

            return UtillsHelper::json(1, [
                'message' => 'Payment receipt uploaded successfully. Waiting for verification.'
            ]);
        } catch (Throwable $e) {

            DB::rollBack();

            return UtillsHelper::json(0, [
                'message' => $e->getMessage()
            ]);
        }
    }

    function familyPost(): JsonResponse
    {
        return $this->invRepo->investorSave();
    }

    function familyGet(): JsonResponse
    {
        return $this->invRepo->investorList();
    }

    function dematBank(): JsonResponse
    {
        return $this->invRepo->dematBank();
    }

    // manually verfication of pan

    public function verifyPan(): JsonResponse
    {
        $request = request();

        $panNumber = strtoupper($request->input('pan_number'));

        $validation = Validator::make(
            ['pan_number' => $panNumber],
            [
                'pan_number' => [
                    'required',
                    'size:10',
                    'alpha_num',
                    'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'
                ]
            ],
            [
                'pan_number.regex' => 'Invalid PAN number format'
            ]
        );

        if ($validation->fails()) {
            return UtillsHelper::json(0, [
                'message' => $validation->errors()->first()
            ]);
        }

        $fetchPan = DigioHelper::verifyPan($request->pan_number);

        if ($fetchPan->getStatusCode() == "200") {
            $data = json_decode($fetchPan->getBody()->getContents());
            if ($data->status == "VALID") {
                return UtillsHelper::json(1, [
                    'message' => 'PAN number is valid',
                    'data' => ['name' => $data->full_name, 'category' => $data->category]
                ]);
            } else {
                return UtillsHelper::json(0, [
                    'message' => 'PAN number is not valid'
                ]);
            }
        }

        return UtillsHelper::json(0, [
            'message' => 'PAN number is not valid'
        ]);
    }



    public function addPortfolio(): JsonResponse
    {
        $request = request();

        // Validation Rules
        $validation = Validator::make($request->all(), [
            'investor_id' => 'required',
            'type' => 'required|in:startup,company',
            'company_id' => 'nullable',
            'other_name' => 'required_if:company_id,null|string|max:255',
            'shares' => 'required|integer|min:1',
            'share_price' => 'required|numeric|min:0.01',
            'date' => 'nullable|date'
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validation->errors()->first()
            ]);
        }

        if ($request->company_id == NULL) {
            $importportfolio = new PortfolioImportModel();
            $importportfolio->investor_id = $request->investor_id;
            $importportfolio->type = $request->type;
            // $importportfolio->investor_id = $request->company_id;
            $importportfolio->other_name = $request->other_name;
            $importportfolio->shares = $request->shares;
            $importportfolio->share_price = $request->share_price;
            $importportfolio->date = $request->date;
            $importportfolio->save();

            PotfolioUploadCutomJob::dispatch($importportfolio->id);

            return UtillsHelper::json(1, [
                'message' => 'Portfolio Imported wait for 48 hours to reflect data in portfolio'
            ], 200);
        } else {
            if ($request->type == 'startup') {
                $transaction = new PrimaryTransactionModel;
                $transaction->status = 10;
                $transaction->type          = PrimaryTransactionTypeEnum::captable;
                $transaction->investor_id   = $request->investor_id;
                $transaction->startup_id = $request->company_id;
                $transaction->round_id = UtillsHelper::getRoundIdOfStartup($request->company_id);
                $transaction->instrument = InstrumentTypeEnum::equity;
                $transaction->shares = $request->shares;
                $transaction->share_price = $request->share_price;
                $transaction->investment_amount = $request->shares * $request->share_price;
                $transaction->payment_status = 1;
                $transaction->is_share_transfered = 1;
                $transaction->is_valid = 0;
                $transaction->payment_mode = PrimaryTransactionPaymentMode::rtgs;
                if ($request->date) {
                    $transaction->created_at = Carbon::createFromFormat('d-m-Y', $request->date)->startOfDay();
                }
                $transaction->portfolio_id = UtillsHelper::primaryToPortfolio($transaction);
                $transaction->save();
            } else {
                $transaction = new PreIpoModel();
                $transaction->status = 5;
                $transaction->investor_id = $request->investor_id;
                $transaction->company_id = $request->company_id;
                $transaction->shares = $request->shares;
                $transaction->share_price = $request->share_price;
                $transaction->investment_amount = $request->shares * $request->share_price;
                $transaction->is_valid = 0;
                if ($request->date) {
                    $transaction->created_at = Carbon::createFromFormat('d-m-Y', $request->date)->startOfDay();
                }
                $transaction->instrument = InstrumentTypeEnum::equity;
                $transaction->payment_mode = PrimaryTransactionPaymentMode::rtgs;
                $transaction->portfolio_id = UtillsHelper::preIpoPortfolio($transaction);
                $transaction->save();
            }
            PotfolioUploadExistingJob::dispatch($transaction->id, $request->type);
            return UtillsHelper::json(1, [
                'message' => 'Portfolio Imported now you can check in portfolio'
            ], 200);
        }
    }

    function getStartupCompany(): JsonResponse
    {
        $request = request();
        $startupList = StartupModel::select('id', 'brand_name')->where('registration_step', 6)->where('is_deleted', 0)->where('is_deleted', '0')->get()->makeHidden(['is_favorite', 'investor_count', 'available_shares', 'share_prices_array', 'minimum_shares']);
        $companyList = CompanyModel::approved()->select('id', 'brand_name')->where('is_deleted', '0')->where(function ($q) {
            $q->whereNull('category')
                ->orWhere('category', '!=', PreIpoCategoryEnum::listed->value);
        })->get()->makeHidden(['share_price', 'distributer_price', 'base_price', 'transaction']);

        return UtillsHelper::json(1, [
            'message' => 'Company And Startup List',
            'data' => [
                'startup' => $startupList,
                'company' => $companyList
            ]
        ], 200);
    }

    function dematPdf()
    {
        $request = request();

        $validator = Validator::make(
            $request->all(),
            [
                'cml' => 'required|file|mimes:pdf|max:10000',
            ],
            [
                'cml.required' => 'The CML file is required.',
                'cml.file' => 'The CML must be a valid file.',
                'cml.mimes' => 'The CML must be a PDF file.',
                'cml.max' => 'The CML file may not be greater than 10 MB.',
            ]
        );

        if ($validator->fails()) {
            return UtillsHelper::json(0, ['message' => $validator->errors()->first()]);
        }

        $file = $request->file('cml');
        $userId = $request->user()?->id;

        $pdfParsingService = new DematPdfParsingService();
        $result = $pdfParsingService->processPdf($file, $userId);

        if ($result['success']) {
            return UtillsHelper::json(1, [
                'message' => $result['message'],
                'data' => $result['data']
            ]);
        }

        DB::beginTransaction();

        try {
            $filePath = FileUpDownHelper::uploadInvestorDoc($file);

            if (!$filePath) {
                return UtillsHelper::json(0, ['message' => 'File upload failed']);
            }

            $document = DocumentsModel::create([
                'api_id'      => null,
                'path'        => $filePath,
                'signed_path' => $filePath,
                'status'      => 1,
                'type'        => DocumentTypeEnum::clientmaster->value,
                'meta'        => [
                    'name'     => 'DEMANT-CML',
                    'investor' => [$userId],
                ],
            ]);

            DematManualModel::create([
                'investor_id' => $userId,
                'document_id' => $document->id,
                'status'      => StatusEnum::pending->value,
                'reason'      => null,
            ]);


            DB::commit();
            SendPendingKycAdminNotification::dispatch($userId);

            return UtillsHelper::json(0, [
                'message' => 'PDF could not be auto-read. Sent for manual verification.'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return UtillsHelper::json(0, [
                'message' => 'Manual verification failed',
                'error'   => $e->getMessage()
            ]);
        }
    }



    // Route: GET /api/portfolio/combined?filter=all|preipo|startup

    public function combinedPortfolio(): JsonResponse
    {
        $user   = request()->user();
        $filter = request()->query('filter', 'all');

        $preIpoStats  = in_array($filter, ['all', 'preipo'])  ? $this->getPreIpoStats($user)  : null;
        $startupStats = in_array($filter, ['all', 'startup']) ? $this->getStartupStats($user) : null;

        // Merge or return single
        $totalInvested  = ($preIpoStats['total_investment_amount']  ?? 0) + ($startupStats['total_investment_amount']  ?? 0);
        $totalPortfolio = ($preIpoStats['current_portfolio_value']  ?? 0) + ($startupStats['current_portfolio_value']  ?? 0);
        $pandl          = $totalPortfolio - $totalInvested;
        $pandlPct       = $totalInvested > 0 ? round(($pandl / $totalInvested) * 100, 2) : 0;

        return UtillsHelper::json(1, [
            'message' => 'Portfolio Dashboard',
            'data'    => [
                'statistics' => [
                    'total_company'  => ($preIpoStats['total_startup_invested']  ?? 0)
                        + ($startupStats['total_startup_invested'] ?? 0),
                    'total_investment_amount' => $totalInvested,
                    'current_portfolio_value' => $totalPortfolio,
                    'p_l'                     => $pandl,
                    'p_l_percentage'          => $pandlPct,
                ],
                'list' => collect($preIpoStats['list'] ?? [])
                    ->map(fn($item) => [
                        'type'       => 'preipo',
                        'preipo'     => $item,
                        'created_at' => $item['created_at'],
                    ])
                    ->concat(
                        collect($startupStats['list'] ?? [])
                            ->map(fn($item) => [
                                'type'       => 'startup',
                                'startup'    => $item,
                                'created_at' => $item['created_at'],
                            ])
                    )
                    ->sortByDesc('created_at')
                    ->values()
                    ->toArray(),
                // 'sectors'           => array_values(array_merge($preIpoStats['sectors']  ?? [], $startupStats['sectors']  ?? [])),
                // 'investment_growth' => array_values(array_merge($preIpoStats['growth']   ?? [], $startupStats['growth']   ?? [])),
                // 'investments' => [
                //     'monthly'   => $this->mergePeriods($preIpoStats['monthly']  ?? [], $startupStats['monthly']  ?? [], 'month'),
                //     'quarterly' => $this->mergePeriods($preIpoStats['quarterly'] ?? [], $startupStats['quarterly'] ?? [], 'quater'),
                // ],
                // 'pending_tasks' => array_merge($preIpoStats['pending_tasks'] ?? [], $startupStats['pending_tasks'] ?? []),
            ],
        ], 200);
    }

    // ── Pre-IPO ──────────────────────────────────────────────────────────────────

    private function getPreIpoStats($user): array
    {
        $portfolio = PortfolioPreIpoModel::with([
            'company.sharePrices' => fn($q) => $q->orderByDesc('date')->limit(1),
            'company.sector',
        ])
            ->where('investor_id', $user->id)
            ->whereHas('company', fn($q) => $q->where('category', '!=', \App\Enums\PreIpoCategoryEnum::listed->value))
            ->get()
            ->groupBy('company_id');

        $totalInvested = 0;
        $totalValue    = 0;
        $sectors       = [];
        $growth        = [];
        $list          = [];

        foreach ($portfolio as $investments) {
            $inv          = $investments->first();
            $price        = $inv->company->sharePrices->first()->price ?? $inv->purchase_price;
            $currentValue = $inv->shares * $price;
            $invAmount    = $investments->sum('investment_amount');

            $totalInvested += $invAmount;
            $totalValue    += $currentValue;

            $pandl    = $currentValue - $invAmount;
            $pandlPct = $invAmount > 0 ? round(($pandl / $invAmount) * 100, 2) : 0;

            // List
            $list[] = [
                'id'                 => $inv->id,
                'investor_id'        => $inv->investor_id,
                'company_id'         => $inv->company_id,
                'shares'             => $inv->shares,
                'purchase_price'     => $inv->purchase_price,
                'investment_amount'  => $invAmount,
                'instrument'         => $inv->instrument,
                'is_share_transfered' => $inv->is_share_transfered ?? 0,
                'created_by'         => $inv->created_by,
                'updated_by'         => $inv->updated_by,
                'created_at'         => $inv->created_at,
                'updated_at'         => $inv->updated_at,
                'current_share_price' => $price,
                'current_value'      => $currentValue,
                'p_l'                => $pandl,
                'p_l_percentage'     => $pandlPct,
                'company'            => [
                    'id'          => $inv->company->id,
                    'brand_name'  => $inv->company->brand_name,
                    'logo'        => $inv->company->logo,
                    'is_favorite' => $inv->company->is_favorite ?? false,
                ],
            ];
            // Sectors
            $sid = $inv->company->sector->id;
            $sectors[$sid] ??= ['id' => $sid, 'name' => $inv->company->sector->name, 'total_investment' => 0, 'startups' => []];
            $sectors[$sid]['total_investment'] += $invAmount;
            $sectors[$sid]['startups'][]        = ['startup_id' => $inv->company->id, 'startup_name' => $inv->company->brand_name, 'investment_amount' => $invAmount, 'current_value' => $currentValue, 'type' => 'preipo'];

            // Growth
            $cid = $inv->company->id;
            $growth[$cid] ??= ['startup_id' => $cid, 'startup_name' => $inv->company->brand_name, 'logo' => $inv->company->logo, 'total_invested_amount' => 0, 'current_value' => 0, 'type' => 'preipo'];
            $growth[$cid]['total_invested_amount'] += $invAmount;
            $growth[$cid]['current_value']          = $currentValue;
        }

        // Timely
        [$monthly, $quarterly] = $this->getTimelyInvestments($user, 'preipo');

        return [
            'total_startup_invested'  => $portfolio->count(),
            'total_investment_amount' => $totalInvested,
            'current_portfolio_value' => $totalValue,
            'list'     => $list,
            'sectors'  => array_values($sectors),
            'growth'   => array_values($growth),
            'monthly'  => $monthly,
            'quarterly' => $quarterly,
            'pending_tasks' => ['share_transfer' => 0, 'fund_transfer' => 0],
        ];
    }

    // ── Startup ───────────────────────────────────────────────────────────────────

    private function getStartupStats($user): array
    {
        $portfolio = PortfolioModel::with([
            'startup.sharePrices' => fn($q) => $q->orderByDesc('created_at')->limit(1),
            'startup.details',
            'startup.sector',
        ])
            ->where('investor_id', $user->id)
            ->get()
            ->groupBy('startup_id');

        $totalInvested = 0;
        $totalValue    = 0;
        $sectors       = [];
        $growth        = [];
        $list          = [];

        $list = [];

        foreach ($portfolio as $investments) {

            $inv          = $investments->first();
            $price        = $inv->startup->sharePrices->first()->price ?? $inv->purchase_price;
            $currentValue = $inv->shares * $price;
            $invAmount    = $investments->sum('investment_amount');

            $totalInvested += $invAmount;
            $totalValue    += $currentValue;

            $pandl    = $currentValue - $invAmount;
            $pandlPct = $invAmount > 0 ? round(($pandl / $invAmount) * 100, 2) : 0;

            $list[] = [
                'instrument'          => $inv->instrument,
                'id'                  => $inv->id,
                'investor_id'         => $inv->investor_id,
                'startup_id'          => $inv->startup_id,
                'shares'              => (int) $inv->shares,
                'purchase_price'      => $inv->purchase_price,
                'investment_amount'   => $invAmount,
                'is_share_transfered' => $inv->is_share_transfered ?? 0,
                'created_by'          => $inv->created_by,
                'updated_by'          => $inv->updated_by,
                'created_at'          => $inv->created_at,
                'updated_at'          => $inv->updated_at,
                'current_share_price' => $price,
                'current_value'       => $currentValue,
                'p_l'                 => $pandl,
                'p_l_percentage'      => $pandlPct,
                'startup' => [
                    'id'         => $inv->startup->id,
                    'brand_name' => $inv->startup->brand_name,
                    'logo'       => $inv->startup->details->logo,
                ],
            ];
        }

        [$monthly, $quarterly] = $this->getTimelyInvestments($user, 'startup');

        // $list = [];

        // foreach ($grouped as $instrument => $items) {
        //     $list[] = [
        //         'instrument' => $instrument,
        //         'data'       => $items['data'],
        //         'created_at' => $items['created_at'],
        //     ];
        // }

        return [
            'total_startup_invested'  => $portfolio->count(),
            'total_investment_amount' => $totalInvested,
            'current_portfolio_value' => $totalValue,
            'list'     => $list,
            'sectors'  => array_values($sectors),
            'growth'   => array_values($growth),
            'monthly'  => $monthly,
            'quarterly' => $quarterly,
            'pending_tasks' => [
                'ssa_sign'      => PrimaryTransactionModel::where('investor_id', $user->id)->where('status', 2)->count(),
                'offer_sign'    => PrimaryTransactionModel::where('investor_id', $user->id)->where('status', 5)->count(),
                'sha_sign'      => PrimaryTransactionModel::where('investor_id', $user->id)->where('status', 9)->count(),
                'fund_transfer' => PrimaryTransactionModel::where('investor_id', $user->id)->where('status', 6)->count(),
            ],
        ];
    }

    // ── Shared Helpers ────────────────────────────────────────────────────────────

    private function getTimelyInvestments($user, string $type): array
    {
        $monthly   = [];
        $quarterly = [];

        foreach (DateTimeHelper::getLast6QuartersDates() as $q) {
            if ($type === 'preipo') {
                $total = PreIpoModel::where('investor_id', $user->id)->where('status', '5')->whereBetween('created_at', [$q['start'], $q['end']])->sum('investment_amount');
            } else {
                $p     = PrimaryTransactionModel::where('investor_id', $user->id)->where('status', '>', '6')->whereBetween('created_at', [$q['start'], $q['end']])->sum('investment_amount');
                $s     = SecondaryTransactionModel::where('buyer_id', $user->id)->where('status', '>', '5')->whereBetween('created_at', [$q['start'], $q['end']])->sum('investment_amount');
                $total = $p + $s;
            }
            $quarterly[] = ['quater' => $q['quater'], 'start' => $q['start'], 'end' => $q['end'], 'total_investment' => $total];
        }

        foreach (DateTimeHelper::getLast6Months() as $m) {
            if ($type === 'preipo') {
                $total = PrimaryTransactionModel::where('investor_id', $user->id)->where('status', '5')->whereBetween('created_at', [$m['start'], $m['end']])->sum('investment_amount');
            } else {
                $p     = PrimaryTransactionModel::where('investor_id', $user->id)->where('status', '>', '6')->whereBetween('created_at', [$m['start'], $m['end']])->sum('investment_amount');
                $s     = SecondaryTransactionModel::where('buyer_id', $user->id)->where('status', '>', '5')->whereBetween('created_at', [$m['start'], $m['end']])->sum('investment_amount');
                $total = $p + $s;
            }
            $monthly[] = ['month' => $m['month'], 'start' => $m['start'], 'end' => $m['end'], 'total_investment' => $total];
        }

        return [array_reverse($monthly), $quarterly];
    }

    private function mergePeriods(array $a, array $b, string $key): array
    {
        $merged = [];
        foreach (array_merge($a, $b) as $item) {
            $k = $item[$key];
            $merged[$k] ??= $item;
            $merged[$k]['total_investment'] += isset($merged[$k]) ? $item['total_investment'] : 0;
        }
        return array_values($merged);
    }


    function registerInquiry(): JsonResponse
    {
        return $this->invRepo->registerInquiry();
    }
}
