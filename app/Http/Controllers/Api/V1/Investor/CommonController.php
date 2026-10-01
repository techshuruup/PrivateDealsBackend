<?php

namespace App\Http\Controllers\Api\V1\Investor;

use App\Enums\DocumentTypeEnum;
use App\Enums\GenderEnum;
use App\Enums\InstrumentTypeEnum;
use App\Enums\InvestorProfileVisibilityEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\PreIpoCategoryEnum;
use App\Enums\PrimaryTransactionPaymentMode;
use App\Enums\PrimaryTransactionTypeEnum;
use App\Enums\StartupPrimaryRoundStatusEnum;
use App\Enums\Utills\StatusEnum;
use App\Enums\WpMessageTypeEnum;
use App\Helpers\AdminHelper;
use App\Helpers\CommonHelper;
use Illuminate\Support\Facades\DB;
use App\Helpers\FileUpDownHelper;
use App\Helpers\PreIpoTransactionHelper;
use App\Helpers\PrimaryTransactionHelper;
use App\Helpers\SecondaryTransactionHelper;
use App\Helpers\UtillsHelper;
use App\Http\Controllers\Controller;
use App\Jobs\notifications\portfolio\upload\CustomJob as PotfolioUploadCutomJob;
use App\Jobs\notifications\portfolio\upload\ExistingJob as PotfolioUploadExistingJob;
use App\Jobs\preipo\CancelNotificationJob;
use App\Models\BankDetailsModel;
use App\Models\CompanyModel;
use App\Models\CompanyNewsModel;
use App\Models\CompanyShareHolderPercentageModel;
use App\Models\CompanySharePriceModel;
use App\Models\DocumentsModel;
use App\Models\InvestorAifKycModel;
use App\Models\InvestorCompanyViewModel;
use App\Models\InvestorFavouriteCompanyModel;
use App\Models\InvestorModel;
use App\Models\InvestorRegisterRequestModel;
use App\Models\LeadsModel;
use App\Models\MasterBankModel;
use App\Models\MasterCityModel;
use App\Models\MasterSectorsModel;
use App\Models\NotificationsModel;
use App\Models\PartnerModel;
use App\Models\PortfolioImportModel;
use App\Models\PortfolioModel;
use App\Models\PortfolioPreIpoModel;
use App\Models\PreIpoModel;
use App\Models\PreIpoSellRequestModel;
use App\Models\PrimaryTransactionModel;
use App\Models\SecondarySellRequestModel;
use App\Models\SecondaryShareTransferModel;
use App\Models\SecondaryTransactionModel;
use App\Models\StartupModel;
use App\Models\StartupRoundModel;
use App\Models\UserAdminModel;
use App\Repositories\InvestorRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

class CommonController extends Controller
{

    private $invRepo;

    function __construct(InvestorRepository $investorRepository)
    {
        $this->invRepo = $investorRepository;
    }


    function getStartupCompany(): JsonResponse
    {
        $request = request();
        $startupList = StartupModel::select('id', 'brand_name')->where('registration_step', 6)->where('is_deleted', 0)->where('is_deleted', '0')->get()->makeHidden(['is_favorite', 'investor_count', 'available_shares', 'share_prices_array', 'minimum_shares']);
        $companyList = CompanyModel::select('id', 'brand_name')->where('is_deleted', '0')->get()->makeHidden(['share_price', 'distributer_price', 'base_price', 'transaction']);

        return UtillsHelper::json(1, [
            'message' => 'Company And Startup List',
            'data' => [
                'startup' => $startupList,
                'company' => $companyList
            ]
        ], 200);
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

    function secTransactionDetails(): JsonResponse
    {
        return $this->invRepo->getSecTransactionDetails();
    }

    function preIpoTransactionsDetails(): JsonResponse
    {
        return $this->invRepo->getPreIpoTransactionDetails();
    }

    function portfolioPreIpoDetail(): JsonResponse
    {
        return $this->invRepo->getPortfolioPreIpoDetail();
    }


    function preIpoTransactions(): JsonResponse
    {
        $request = request();
        $query = PreIpoModel::where('investor_id', $request->user()->id);

        if ($request->has('is_processing')) {
            $isProcessing = filter_var($request->is_processing, FILTER_VALIDATE_BOOLEAN);

            if ($isProcessing) {
                $query->whereIn('status', [0, 2, 3, 4]);
            } else {
                $query->whereIn('status', [1, 5]);
            }
        }

        $transactions = $query->with('company')
            ->orderby('created_at', 'desc')
            ->get()->map(function ($transaction) {
                $transaction->company->makeHidden(['transaction']);
                return $transaction;
            });
        return UtillsHelper::json(1, [
            'message' => 'Transaction List',
            'data' => $transactions
        ], 200);
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

        $investorIds        = $request->input('investor_id');
        $companyIds         = $request->input('company_id');
        $shares             = $request->input('shares');
        $sharePrices        = $request->input('share_price');
        $distributerPrices  = $request->input('distributer_price');
        $isDistributers     = $request->input('is_distributer');
        $paymentModes       = $request->input('payment_mode');
        $sellerIdInput      = $request->input('seller_id');

        $validationRules = [
            'investor_id'            => 'required|array|min:1',
            'company_id'             => 'required|array|min:1',
            'shares'                 => 'required|array|min:1',
            'share_price'            => 'required|array|min:1',
            'distributer_price'      => 'required|array|min:1',
            'is_distributer'         => 'required|array|min:1',
            'payment_mode'           => 'required|array|min:1',

            'investor_id.*'          => 'required|integer',
            'company_id.*'           => 'required|integer',
            'shares.*'               => 'required|integer|min:1',
            'share_price.*'          => 'required|numeric|min:1',
            'distributer_price.*'    => 'required|numeric|min:1',
            'is_distributer.*'       => 'required|boolean',
            'payment_mode.*'         => ['required', Rule::enum(PrimaryTransactionPaymentMode::class)],
        ];

        if ($request->filled('seller_id') && !is_array($sellerIdInput)) {
            $validationRules['seller_id'] = [
                'integer',
                Rule::exists('seller_master', 'id')->where(fn ($q) => $q->where('is_deleted', 0)),
            ];
        } elseif (is_array($sellerIdInput)) {
            $validationRules['seller_id'] = 'array';
            $validationRules['seller_id.*'] = [
                'required',
                'integer',
                Rule::exists('seller_master', 'id')->where(fn ($q) => $q->where('is_deleted', 0)),
            ];
        }

        $validation = Validator::make($request->all(), $validationRules);

        if ($validation->fails()) {
            return UtillsHelper::json(0, [
                'message' => $validation->errors()->first()
            ]);
        }

        $lengths = [
            count($investorIds),
            count($companyIds),
            count($shares),
            count($sharePrices),
            count($distributerPrices),
            count($isDistributers),
            count($paymentModes),
        ];

        if (count(array_unique($lengths)) !== 1) {
            return UtillsHelper::json(0, [
                'message' => 'All input arrays must have the same length.'
            ]);
        }

        $sellerIdsByIndex = null;
        if ($request->filled('seller_id')) {
            if (is_array($sellerIdInput)) {
                if (count($sellerIdInput) !== count($investorIds)) {
                    return UtillsHelper::json(0, [
                        'message' => 'seller_id array must have the same length as other input arrays.',
                    ]);
                }
                $sellerIdsByIndex = $sellerIdInput;
            } else {
                $sellerIdsByIndex = array_fill(0, count($investorIds), (int) $sellerIdInput);
            }
        }

        DB::beginTransaction();

        try {
            foreach ($investorIds as $index => $investorId) {

                $companyId = $companyIds[$index];

                $company = CompanyModel::find($companyId);
                if (!$company) {
                    throw new Exception('No company found for company_id: ' . $companyId);
                }

                $transaction = new PreIpoModel();
                $transaction->status              = 0;
                $transaction->investor_id         = $investorId;
                $transaction->company_id          = $companyId;
                $transaction->shares              = $shares[$index];
                $transaction->share_price         = $sharePrices[$index];
                $transaction->distributer_price   = $distributerPrices[$index];
                $transaction->investment_amount   = $shares[$index] * $sharePrices[$index];
                $transaction->is_distributer      = $isDistributers[$index];
                $transaction->instrument          = InstrumentTypeEnum::equity;
                $transaction->payment_mode        = $paymentModes[$index];
                $transaction->payable_amount      = $transaction->investment_amount;
                if ($sellerIdsByIndex !== null) {
                    $transaction->seller_id = $sellerIdsByIndex[$index];
                }
                $transaction->save();
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            return UtillsHelper::json(0, [
                'message' => $e->getMessage()
            ]);
        }

        $transaction->load('company');
        $company = $transaction->company;

        $allCompanies = CompanyModel::where('is_deleted', 0)
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
            $similarStocks = CompanyModel::where('sector_id', $company->sector_id)
                ->where('id', '!=', $company->id)
                ->where('is_deleted', 0)
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

        $statusList = PreIpoTransactionHelper::getStatusListForApplication($transaction);

        // $currentTime = now();
        // $startTime   = $currentTime->copy()->setTime(10, 30);
        // $endTime     = $currentTime->copy()->setTime(17, 0);
        // $dayOfWeek   = $currentTime->dayOfWeek;

        $responseMessage = 'Your order placed successfully. It will be confirmed within 2 hours.';

        return UtillsHelper::json(1, [
            'message' => $responseMessage,
            'data' => [
                'transaction'     => $transaction,
                'all'             => $allCompanies,
                'similar_stocks'  => $similarStocks,
                'status_list'     => $statusList,
            ]
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

    function companyDetail(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'company_id' => 'required'
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $company = CompanyModel::with([
            'customData',
            'sharePrices' => function ($query) use ($request) {
                if ($request->routeIs('external.*')) {
                    $query->select('company_id', 'price', 'date')->orderBy('date', 'desc');
                } else {
                    $query->orderBy('date', 'desc');
                }
            },
            'fundamentals',
            'promoters',
            'events' => function ($query) {
                $query->orderBy('date', 'desc');
            },
            // 'news' => function ($query) {
            //     $twoDaysAgo = now()->subDays(2)->startOfDay();
            //     $query->where('created_at', '>=', $twoDaysAgo)->orderBy('created_at', 'desc');
            // },
            'news' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(10);
            },
            'peerratio'
        ])->where('id', $request->company_id)->first();

        if ($company && $company->news->count() == 0) {
            $company->load(['news' => function ($query) {
                $query->orderBy('id', 'desc')->limit(3);
            }]);
        }

        if ($company) {
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
        }
        $shareholdersData = CompanyShareHolderPercentageModel::with('shareHolder:name,id')
            ->where('company_id', $request->company_id)
            ->select('share_holder_id', 'year', 'percentage')
            ->orderBy('year', 'asc')
            ->get()
            ->groupBy('year')
            ->map(function ($yearGroup, $year) {
                $formattedData = $yearGroup->sortByDesc('percentage')->map(function ($shareHolderPercentage) {
                    return [
                        'name' => $shareHolderPercentage->shareHolder->name,
                        'percentage' => $shareHolderPercentage->percentage
                    ];
                })->values();
                return [
                    'year' => $year,
                    'shareholders' => $formattedData
                ];
            })
            ->values();
        $company->share_holders = $shareholdersData;

        // Track company view for authenticated investors (automatically tracked when viewing company details)
        if ($request->user()) {
            $investor = $request->user();
            InvestorCompanyViewModel::updateOrCreate(
                [
                    'investor_id' => $investor->id,
                    'company_id'  => $request->company_id,
                ],
                [
                    'investor_id' => $investor->id,
                    'company_id'  => $request->company_id,
                ]
            );
        }

        if ($request->routeIs('external.*')) {
            unset($company->id);
            unset($company->min_investment_type);
            unset($company->min_investment_amount);
        }
        return UtillsHelper::json(1, [
            'message' => 'Detail',
            'data' => $company
        ]);
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


    function companyMarket(): JsonResponse
    {
        $request = request();
        $withRelations = [
            'fundamentals' => function ($query) {
                $query->select('company_id', 'lot_size', 'fifty_two_week_high', 'fifty_two_week_low', 'depository');
            },
        ];

        if ($request->header('app-version-code')) {
            $withRelations['sharePrices'] = function ($query) {
                $query->select('company_id', 'price', 'date')
                    ->where('date', '>=', now()->subDays(90)->toDateString()) // Last 90 days
                    ->orderBy('date', 'asc');
            };
        }

        $query = CompanyModel::where('is_deleted', '0')
            ->where('status', '0')
            ->select('id', 'brand_name', 'slug', 'logo', 'category', 'bg_color_code', 'about', 'share_price', 'distributer_price', 'base_price', 'price_updated_today', 'last_year_share_price')
            ->with($withRelations);

        if ($request->has('category')) {
            $category = $request->input('category');
            $query->where('category', $category);
        }

        if ($request->has('sector_id')) {
            $sector_id = $request->input('sector_id');
            $query->where('sector_id', $sector_id);
        }

        // Search filter
        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where('brand_name', 'like', '%' . $searchTerm . '%');
        }


        // Pagination logic
        if ($request->has('skip') && $request->has('take')) {
            $skip = max(0, (int) $request->input('skip')); // Ensure non-negative
            $take = max(1, (int) $request->input('take')); // Ensure at least 1
            $query->skip($skip)->take($take);
        }

        // $list = $query->orderBy('brand_name', 'asc')->get();
        $list = $query->orderByRaw('list_order IS NULL') // NULL values last
            ->orderBy('list_order', 'asc')
            ->orderBy('brand_name', 'asc')
            ->get()->makeHidden('is_favorite');

        // Fetch earliest prices for all companies in one query
        // $companyIds = $list->pluck('id');
        // $earliestPrices = CompanySharePriceModel::whereIn('company_id', $companyIds)
        //     ->select('company_id', 'price', 'date')
        //     ->orderBy('date', 'asc')
        //     ->groupBy('company_id')
        //     ->get()
        //     ->keyBy('company_id');

        // Manipulate sharePrices collection
        // $list->each(function ($company) use ($earliestPrices) {
        //     if ($earliestPrices->has($company->id)) {
        //         $earliestPrice = $earliestPrices->get($company->id);
        //         $company->sharePrices->push([
        //             'price' => $earliestPrice->price,
        //             'date'  => $earliestPrice->date,
        //         ]);
        //     }
        // });

        return UtillsHelper::json(1, [
            'message' => 'Market',
            'data' => $list
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

    function secPaymentReceived(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'transaction_id'        => 'required'
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $transaction = SecondaryTransactionModel::where('id', $request->transaction_id)->first();
        // $meta = [
        //     'investor' => [
        //         $transaction->buyer->id,
        //         $transaction->seller->id,
        //     ],
        //     'secondary_transaction' => [
        //         $transaction->id
        //     ],
        //     'startup'               => [$transaction->startup_id]
        // ];

        // $meta['name']   = 'Payment Receipt - ' . $transaction->startup->brand_name;
        // $meta['sname']   = 'Payment Receipt from - ' . $transaction->buyer->name;


        // $payment = new SecondaryPaymentsModel();
        // $payment->transaction_id = $request->transaction_id;
        // $file = FileUpDownHelper::upload_secondary_transaction_document($request->file('receipt'));
        // if ($file) {
        //     $document = new DocumentsModel();
        //     $document->api_id = NULL;
        //     $document->path = $file;
        //     $document->signed_path = $file;
        //     $document->status = 0;
        //     $document->type = DocumentTypeEnum::paymentreceipt;

        //     $document->meta = $meta;
        //     $document->save();

        //     if ($document) {
        //         $payment->document_id  = $document->id;
        //     }
        // }
        // $payment->status = StatusEnum::pending;
        // $payment->save();




        $transaction->status = 6;
        $transaction->save();

        UtillsHelper::sendNotification($transaction->seller->id, InvestorModel::class, 'secondary-transactions', 'Payment Received', 'Payment received in escrow account now you can transfer shares to buyer demat acoount.');
        UtillsHelper::sendNotification($transaction->buyer->id, InvestorModel::class, 'secondary-transactions', 'Payment Received', 'Payment request approved by admin next process will be transfer shares in demat.');

        UtillsHelper::sendWpMessage(
            NotificationTypeEnum::event,
            'notify_seller_payment_received_from_buyer_in_escrow',
            WpMessageTypeEnum::text,
            $transaction->seller->mobile_number,
            $transaction->seller->name,
            NULL,
            [],
            [$transaction->seller->name, $transaction->buyer->name, $transaction->shares * $transaction->share_price, $transaction->shares],
            ['transaction_id' => $transaction->id]
        );

        return UtillsHelper::json(1, ['message' => 'Payment request sent.']);
    }

    function secUploadShareReceipt(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'transaction_id'        => 'required',
            'receipt'               => 'required|file|mimes:png,jpg,jpeg,pdf|max:' . UtillsHelper::maxFileDocumentSizeInKB()
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $transaction = SecondaryTransactionModel::where('id', $request->transaction_id)->first();
        $meta = [
            'investor' => [
                $transaction->buyer->id,
                $transaction->seller->id,
            ],
            'secondary_transaction' => [
                $transaction->id
            ],
            'startup'               => [$transaction->startup_id]
        ];

        $meta['name']   = 'Share Receipt - ' . $transaction->startup->brand_name;
        $meta['sname']   = 'Share Receipt from - ' . $transaction->seller->name;


        $payment = new SecondaryShareTransferModel();
        $payment->transaction_id = $request->transaction_id;
        $file = FileUpDownHelper::upload_secondary_transaction_document($request->file('receipt'));
        if ($file) {
            $document = new DocumentsModel();
            $document->api_id = NULL;
            $document->path = $file;
            $document->signed_path = $file;
            $document->status = 1;
            $document->type = DocumentTypeEnum::sharereceipt;

            $document->meta = $meta;
            $document->save();

            if ($document) {
                $payment->document_id  = $document->id;
            }
        }
        $payment->status = StatusEnum::pending;
        $payment->save();
        $transaction->status = 7;
        $transaction->save();

        UtillsHelper::sendNotification($transaction->buyer->id, InvestorModel::class, 'secondary-transactions', 'Share Transfered', 'Share transfered and receipt uploaded please confirm the transfer.');

        UtillsHelper::sendWpMessage(
            NotificationTypeEnum::event,
            'notify_buyer_that_seller_uploaded_receipt',
            WpMessageTypeEnum::text,
            $transaction->buyer->mobile_number,
            $transaction->buyer->name,
            NULL,
            [],
            [$transaction->buyer->name, $transaction->seller->name, $transaction->shares],
            ['transaction_id' => $transaction->id]
        );

        return UtillsHelper::json(1, ['message' => 'Share Recepit Uploaded.']);
    }

    function secApproveShareReceipt(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'transaction_id'        => 'required'
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $transaction = SecondaryTransactionModel::where('id', $request->transaction_id)->first();
        if ($transaction) {
            $transaction->status = 8;
            $transaction->save();

            UtillsHelper::sendNotification($transaction->seller->id, InvestorModel::class, 'secondary-transactions', 'Share transfer confirmed', 'Share transfer confirmed and transaction completed. Amount relesed from Escrow to your bank account.');

            UtillsHelper::sendWpMessage(
                NotificationTypeEnum::event,
                'notify_seller_funds_released_from_escrow_account',
                WpMessageTypeEnum::text,
                $transaction->seller->mobile_number,
                $transaction->seller->name,
                NULL,
                [],
                [$transaction->seller->name, $transaction->shares * $transaction->share_price],
                ['transaction_id' => $transaction->id]
            );

            return UtillsHelper::json(1, ['message' => 'Share Receipt Approved.']);
        }


        return UtillsHelper::json(0, ['message' => 'Transaction not found']);
    }

    function secBuyNow(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'sell_request_id'              => 'required',
            'buyer_id'                      => 'required',
            'shares'                       => 'required|integer|min:1',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $sellReq = SecondarySellRequestModel::where('id', $request->input('sell_request_id'))->first();

        if (!$sellReq) {
            return UtillsHelper::json(0, ['message' => 'No sell request found']);
        }

        $secTransaction = new SecondaryTransactionModel;
        $secTransaction->status = 1;
        $secTransaction->startup_id = $sellReq->startup_id;
        $secTransaction->portfolio_id = $sellReq->portfolio_id;
        $secTransaction->buyer_id = $request->buyer_id;
        $secTransaction->seller_id = $sellReq->investor_id;
        $secTransaction->sell_request_id = $sellReq->id;
        $secTransaction->instrument = $sellReq->instrument;
        $secTransaction->shares = $request->input('shares');
        $secTransaction->share_price = $sellReq->price;
        $secTransaction->investment_amount = $sellReq->price * $request->input('shares');
        $secTransaction->is_promoter = 0;
        $secTransaction->save();

        SecondaryTransactionHelper::sendSH4($secTransaction);
        return UtillsHelper::json(1, ['message' => 'Buy Request placed']);
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

    function secMarket(): JsonResponse
    {
        // $completed = StartupRoundModel::where('round_status', StartupPrimaryRoundStatusEnum::completed->value)
        //     ->with('startup.sector', 'startup.details', 'startup.cms', 'startup.StartupFundRaiseOne', 'startup.lastRounds')
        //     ->whereHas('startup', function ($query) {
        //         $query->where('registration_step', 6)->where('is_deleted', 0);
        //     })
        //     ->groupBy('startup_id')
        //     ->get() // Retrieve the data first
        //     ->sortByDesc(function ($item) {
        //         return $item->startup->available_shares; // Sort by the appended attribute
        //     });

        $completed = StartupRoundModel::with('startup.sector', 'startup.details', 'startup.cms', 'startup.StartupFundRaiseOne', 'startup.lastRounds')
            ->whereHas('startup', function ($query) {
                $query->where('registration_step', 6)->where('is_deleted', 0)->wherein('id',['23','24','25','26']);
            })
            ->groupBy('startup_id')
            ->get() // Retrieve the data first
            ->sortByDesc(function ($item) {
                return $item->startup->available_shares; // Sort by the appended attribute
            });

        return UtillsHelper::json(1, [
            'message' => 'Market',
            'data' => $completed->values() // Reset the keys after sorting
        ]);
    }

    // function secSellRequestList(): JsonResponse
    // {
    //     $request = request();
    //     $transaction = SecondarySellRequestModel::where('investor_id', $request->user()->id)->with('startup.details')->orderby('id', 'desc');
    //     // if ($request->skip) {
    //     //     $transaction->skip($request->skip);
    //     // }
    //     // $transaction->take(CommonHelper::appSettings('app_pagination_limit'));

    //     return UtillsHelper::json(
    //         1,
    //         [
    //             'message' => "Request List",
    //             'data' => $transaction->get(),
    //         ],
    //         200
    //     );
    // }

    // function secReletedOppotunitiesList(): JsonResponse
    // {
    //     $request = request();
    //     $transaction = SecondaryExistingInvestorModel::where('buyer_id', $request->user()->id)->with('startup.details')->orderby('created_at', 'desc');

    //     return UtillsHelper::json(
    //         1,
    //         [
    //             'message' => "Oppotunities List",
    //             'data' => $transaction->get(),
    //         ],
    //         200
    //     );
    // }

    function secRofrStatus(): JsonResponse
    {
        $request = request();

        $validation = Validator::make($request->all(), [
            'item_id'               => ['required'],
            'status'              => ['required', Rule::in(['1', '2'])],
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $item = SecondaryTransactionModel::find($request->item_id);
        if (!$item) {
            return UtillsHelper::json(0, ['message' => 'Transaction not found']);
        }

        $item->status = $request->status;
        $item->save();

        SecondaryTransactionHelper::changeOppotunityStatus($item);

        return UtillsHelper::json(1, ['message' => 'Status Updated']);
    }

    function secSellNow(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'portfolio_id'              => 'required',
            'shares'                    => 'required|integer|min:1',
            'price'                     => 'required|numeric|min:0.01'
        ]);


        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $portfolio = PortfolioModel::where('id', $request->input('portfolio_id'))->first();

        if (!$portfolio) {
            return UtillsHelper::json(0, ['message' => 'No portfolio found']);
        }
        $availableShares = $portfolio->shares - $portfolio->shares_sold;

        if ($availableShares < $request->shares) {
            return UtillsHelper::json(0, ['message' => 'Shares must be less than or equal to portfolio shares']);
        }

        $sellRequest = new SecondarySellRequestModel;
        $sellRequest->status = 0;
        $sellRequest->instrument = $portfolio->instrument;
        $sellRequest->startup_id = $portfolio->startup_id;
        $sellRequest->portfolio_id = $portfolio->id;
        $sellRequest->investor_id = $portfolio->investor_id;
        $sellRequest->shares = $request->shares;
        $sellRequest->price = $request->price;
        $sellRequest->purchase_price = $portfolio->purchase_price;
        $sellRequest->current_price = $portfolio->current_share_price;
        $sellRequest->last_traded_price = $portfolio->last_traded_price;
        $sellRequest->save();

        $body = $portfolio->investor->name . ' is selling ' . $request->shares . ' ' . $portfolio->instrument . ' at ' . UtillsHelper::rupee() . UtillsHelper::moneyFormatIndia($request->price);
        UtillsHelper::sendNotification($portfolio->startup_id, StartupModel::class, 'front.raise.sell_requests.list', 'New Sell request', $body);

        UtillsHelper::sendWpMessage(
            NotificationTypeEnum::event,
            'notify_startup_share_sale_1',
            WpMessageTypeEnum::text,
            $portfolio->startup->mobile_number,
            $portfolio->startup->brand_name,
            NULL,
            [],
            [$portfolio->startup->brand_name, $portfolio->investor->name, $request->shares],
            ['portfolio_id' => $portfolio->id, 'sell_request_id' => $sellRequest->id]
        );

        return UtillsHelper::json(1, ['message' => 'Sell Request placed']);
    }

    function transactionList(): JsonResponse
    {
        $request = request();
        $transactions = PrimaryTransactionModel::where('investor_id', $request->user()->id)->with('startup.details', 'startup.cms')->get();
        return UtillsHelper::json(1, [
            'message' => 'Transaction List',
            'data' => $transactions
        ], 200);
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


    function bankMandates(): JsonResponse
    {
        return $this->invRepo->getBankMandates();
    }

    function notifications(): JsonResponse
    {
        return $this->invRepo->getNotifications();
    }

    function documents(): JsonResponse
    {
        return $this->invRepo->getDocuments();
    }

    function mis(): JsonResponse
    {
        return $this->invRepo->getMIS();
    }

    function portfolio(): JsonResponse
    {
        return $this->invRepo->getPortfolio();
    }
    function portfolioStatus(): JsonResponse
    {
        $investorId = request()->user()->id;

        $status = [];

        foreach (InstrumentTypeEnum::cases() as $instrumentType) {
            $status[$instrumentType->value] = false;
        }

        $status['preipo'] = false;

        foreach (InstrumentTypeEnum::cases() as $instrumentType) {
            $hasInstrument = PortfolioModel::where('investor_id', $investorId)
                ->where('instrument', $instrumentType->value)
                ->where('shares', '>', 0)
                ->exists();

            if ($hasInstrument) {
                $status[$instrumentType->value] = true;
            }
        }

        $hasPreIpo = PortfolioPreIpoModel::where('investor_id', $investorId)
            ->where('shares', '>', 0)
            ->exists();

        if ($hasPreIpo) {
            $status['preipo'] = true;
        }

        return UtillsHelper::json(
            1,
            [
                'message' => 'Portfolio Status',
                'data' => $status
            ],
            200
        );
    }

    function portfolioDetails(): JsonResponse
    {
        return $this->invRepo->getPortfolioDetails();
    }

    function transactionDetails(): JsonResponse
    {
        return $this->invRepo->getTransactionDetails();
    }

    function portfolioPreIpo(): JsonResponse
    {
        return $this->invRepo->getPortfolioPreIpo();
    }

    function getStartupLiteNew(): JsonResponse
    {
        return $this->invRepo->getStartupLiteNew();
    }

    function changePassword(): JsonResponse
    {
        return $this->invRepo->changePassword();
    }

    function dematGet(): JsonResponse
    {
        return $this->invRepo->getDemat();
    }

    function dematPost(): JsonResponse
    {
        return $this->invRepo->saveDemat();
    }

    function favoriteGet(): JsonResponse
    {
        return $this->invRepo->getFavorite();
    }

    function favoritePost(): JsonResponse
    {
        return $this->invRepo->postFavorite();
    }

    function familyPost(): JsonResponse
    {
        return $this->invRepo->investorSave();
    }

    function familyGet(): JsonResponse
    {
        return $this->invRepo->investorList();
    }

    function kycPost(): JsonResponse
    {
        return $this->invRepo->postManualKYC();
    }

    function uploadBankAccount(): JsonResponse
    {
        return $this->invRepo->uploadBankAccount();
    }

    function uploadDemat(): JsonResponse
    {
        return $this->invRepo->uploadDemat();
    }

    function uploadPanDetails(): JsonResponse
    {
        return $this->invRepo->uploadPanDetails();
    }

    function uploadAadharDetails(): JsonResponse
    {
        return $this->invRepo->uploadAadharDetails();
    }

    function kycGet(): JsonResponse
    {
        return $this->invRepo->getKyc();
    }

    function getEkycToken(): JsonResponse
    {
        return $this->invRepo->getEkycToken();
    }

    function getEkycData(): JsonResponse
    {
        return $this->invRepo->getEkycData();
    }

    function aifSubmit(): JsonResponse
    {
        $request = request();
        $valArray['investor_id'] = ['required'];
        // $valArray['aadhar_front'] = ['required', 'mimes:jpg,png', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
        // $valArray['aadhar_back'] = ['required', 'mimes:jpg,png', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
        // $valArray['pan_card'] = ['required', 'mimes:jpg,png', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
        // $valArray['cheque'] = ['required', 'mimes:jpg,png', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
        // $valArray['cml'] = ['required', 'mimes:jpg,png', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
        $validation = Validator::make($request->all(), $valArray);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }
        // $userId = $request->user()->id;
        // $userName = $request->user()->name;
        // if ($request->has('investor_id')) {
        //     $investor = InvestorModel::where('id', $request->investor_id)->first();
        //     $userName = $investor->name;
        // }
        $aif = new InvestorAifKycModel;
        $aif->investor_id = $request->investor_id;
        $aif->status = 0;
        $aif->save();

        return UtillsHelper::json(1, ['message' => 'Data Submited please wait for approval']);

        // if ($request->hasFile('aadhar_front')) {
        //     $meta = [
        //         'investor' => [
        //             $userId,
        //         ],
        //         'aif_kyc' => [
        //             $aif->id
        //         ]
        //     ];

        //     $meta['name']   = DocumentTypeEnum::aadharfront->value;
        //     $meta['aname']   = DocumentTypeEnum::aadharfront->value . ' of ' . $userName;
        //     $file = FileUpDownHelper::uploadInvestorDoc($request->file('aadhar_front'));
        //     if ($file) {
        //         $document = new DocumentsModel();
        //         $document->api_id = NULL;
        //         $document->path = $file;
        //         $document->signed_path = $file;
        //         $document->status = 0;
        //         $document->type = DocumentTypeEnum::aadharfront;
        //         $document->meta = $meta;
        //         $document->save();
        //     }
        // }

        // if ($request->hasFile('aadhar_back')) {
        //     $meta = [
        //         'investor' => [
        //             $userId,
        //         ],
        //         'aif_kyc' => [
        //             $aif->id
        //         ]
        //     ];

        //     $meta['name']   = DocumentTypeEnum::aadharback->value;
        //     $meta['aname']   = DocumentTypeEnum::aadharback->value . ' of ' . $userName;
        //     $file = FileUpDownHelper::uploadInvestorDoc($request->file('aadhar_back'));
        //     if ($file) {
        //         $document = new DocumentsModel();
        //         $document->api_id = NULL;
        //         $document->path = $file;
        //         $document->signed_path = $file;
        //         $document->status = 0;
        //         $document->type = DocumentTypeEnum::aadharback;
        //         $document->meta = $meta;
        //         $document->save();
        //     }
        // }

        // if ($request->hasFile('pan_card')) {
        //     $meta = [
        //         'investor' => [
        //             $userId,
        //         ],
        //         'aif_kyc' => [
        //             $aif->id
        //         ]
        //     ];

        //     $meta['name']   = DocumentTypeEnum::pancard->value;
        //     $meta['aname']   = DocumentTypeEnum::pancard->value . ' of ' . $userName;
        //     $file = FileUpDownHelper::uploadInvestorDoc($request->file('pan_card'));
        //     if ($file) {
        //         $document = new DocumentsModel();
        //         $document->api_id = NULL;
        //         $document->path = $file;
        //         $document->signed_path = $file;
        //         $document->status = 0;
        //         $document->type = DocumentTypeEnum::pancard;
        //         $document->meta = $meta;
        //         $document->save();
        //     }
        // }

        // if ($request->hasFile('cheque')) {
        //     $meta = [
        //         'investor' => [
        //             $userId,
        //         ],
        //         'aif_kyc' => [
        //             $aif->id
        //         ]
        //     ];

        //     $meta['name']   = DocumentTypeEnum::bankcheque->value;
        //     $meta['aname']   = DocumentTypeEnum::bankcheque->value . ' of ' . $userName;
        //     $file = FileUpDownHelper::uploadInvestorDoc($request->file('cheque'));
        //     if ($file) {
        //         $document = new DocumentsModel();
        //         $document->api_id = NULL;
        //         $document->path = $file;
        //         $document->signed_path = $file;
        //         $document->status = 0;
        //         $document->type = DocumentTypeEnum::bankcheque;
        //         $document->meta = $meta;
        //         $document->save();
        //     }
        // }

        // if ($request->hasFile('cml')) {
        //     $meta = [
        //         'investor' => [
        //             $userId,
        //         ],
        //         'aif_kyc' => [
        //             $aif->id
        //         ]
        //     ];

        //     $meta['name']   = DocumentTypeEnum::clientmaster->value;
        //     $meta['aname']   = DocumentTypeEnum::clientmaster->value . ' of ' . $userName;
        //     $file = FileUpDownHelper::uploadInvestorDoc($request->file('cml'));
        //     if ($file) {
        //         $document = new DocumentsModel();
        //         $document->api_id = NULL;
        //         $document->path = $file;
        //         $document->signed_path = $file;
        //         $document->status = 0;
        //         $document->type = DocumentTypeEnum::clientmaster;
        //         $document->meta = $meta;
        //         $document->save();
        //     }
        // }


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
    function getAif(): JsonResponse
    {
        $request = request();
        $userId = $request->user()->id;
        if ($request->has('investor_id')) {
            $userId = $request->input('investor_id');
        }
        $aif = InvestorAifKycModel::where('investor_id', $userId)->latest()->first();
        return UtillsHelper::json(1, [
            'message' => 'AIF Item',
            'data' => $aif
        ]);
    }

    function getProfile(): JsonResponse
    {
        $request = request();
        $investor = InvestorModel::where('id', $request->user()->id)
            ->with('city', 'country', 'state', 'investor_detail', 'kyc', 'dematAccount')
            ->first();

        if ($investor) {
            $investor->kyc_all_data = [
                'aadhar_details' => $investor->aadharDetails,
                'bank_details' => $investor->bankDetails,
                'demat_account' => $investor->dematAccount,
                'pan_details' => $investor->panDetails
            ];

            unset($investor->aadharDetails);
            unset($investor->bankDetails);
            unset($investor->dematAccount);
            unset($investor->panDetails);

            $unreadCount = NotificationsModel::where('user_id', $investor->id)
                ->where('user_type', InvestorModel::class)
                ->where('is_readed', 0)
                ->count();

            $investor->unread_counter = $unreadCount;
        }

        $investor->is_requested_for_startup = InvestorRegisterRequestModel::where('user_id', $investor->id)->where('user_type', InvestorModel::class)
            ->where('is_startup', 1)
            ->exists();
        $investor->kyc_data = $investor->kyc_data;
        return UtillsHelper::json(1, [
            'message' => 'Profile',
            'data' => $investor
        ]);
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

    function getHome(): JsonResponse
    {
        $raisingNow = StartupRoundModel::where('round_status', StartupPrimaryRoundStatusEnum::raisingnow->value)
            ->with('startup.city', 'startup.sector', 'startup.details', 'startup.StartupFundRaiseOne', 'startup.raising_round')->whereHas('startup', function ($query) {
                $query->where('registration_step', 6)->where('is_deleted', 0);
            })->groupBy('startup_id');
        $comingSoon =
            StartupRoundModel::where('round_status', StartupPrimaryRoundStatusEnum::comingsoon->value)
            ->with('startup.city', 'startup.sector', 'startup.details', 'startup.StartupFundRaiseOne', 'startup.raising_round')->whereHas('startup', function ($query) {
                $query->where('registration_step', 6)->where('is_deleted', 0);
            })->groupBy('startup_id');
        $completed =
            StartupRoundModel::where('round_status', StartupPrimaryRoundStatusEnum::completed->value)
            ->with('startup.city', 'startup.sector', 'startup.details', 'startup.StartupFundRaiseOne', 'startup.raising_round')->whereHas('startup', function ($query) {
                $query->where('registration_step', 6)->where('is_deleted', 0);
            })->groupBy('startup_id');
        return UtillsHelper::json(1, [
            'message' => 'Home Page',
            'data' => [
                'statistics' => [
                    'investment'    => PortfolioModel::get()->sum('investment_amount'),
                    'secondary'     => 40,
                    'current'       => $raisingNow->count() + $comingSoon->count(),
                    'funded'        => $completed->count()
                ],
                'raisingNow' => $raisingNow->get(),
                'comingSoon' => $comingSoon->get(),
                'completed' => $completed->get(),
            ]
        ]);
    }

    function getHomeNew(): JsonResponse
    {
        $raisingNow = StartupRoundModel::where('round_status', StartupPrimaryRoundStatusEnum::raisingnow->value)
            ->with('startup.city', 'startup.sector', 'startup.cms', 'startup.raising_round')->whereHas('startup', function ($query) {
                $query->where('registration_step', 6)->where('is_deleted', 0);
            })->groupBy('startup_id');
        $comingSoon =
            StartupRoundModel::where('round_status', StartupPrimaryRoundStatusEnum::comingsoon->value)
            ->with('startup.city', 'startup.sector', 'startup.cms', 'startup.raising_round')->whereHas('startup', function ($query) {
                $query->where('registration_step', 6)->where('is_deleted', 0);
            })->groupBy('startup_id');
        $completed =
            StartupRoundModel::where('round_status', StartupPrimaryRoundStatusEnum::completed->value)
            ->with('startup.city', 'startup.sector', 'startup.cms', 'startup.raising_round')->whereHas('startup', function ($query) {
                $query->where('registration_step', 6)->where('is_deleted', 0);
            })->groupBy('startup_id');
        return UtillsHelper::json(1, [
            'message' => 'Home Page',
            'data' => [
                'statistics' => [
                    'investment'    => PortfolioModel::get()->sum('investment_amount'),
                    'secondary'     => 40,
                    'current'       => $raisingNow->count() + $comingSoon->count(),
                    'funded'        => $completed->count()
                ],
                'raisingNow' => $raisingNow->get(),
                'comingSoon' => $comingSoon->get(),
                'completed' => $completed->get(),
            ]
        ]);
    }

    function getPreipoHome(): JsonResponse
    {
        $data = [];

        // Common query builder for companies
        $baseCompanyQuery = function ($additionalWhere = []) {
            $query = CompanyModel::where('is_deleted', 0)
                ->select('id', 'brand_name', 'logo', 'category', 'bg_color_code', 'about', 'share_price', 'distributer_price', 'base_price', 'price_updated_today', 'last_year_share_price')
                ->with([
                    'fundamentals' => function ($query) {
                        $query->select('company_id', 'lot_size', 'fifty_two_week_high', 'fifty_two_week_low');
                    }
                ]);

            foreach ($additionalWhere as $column => $value) {
                $query->where($column, $value);
            }

            return $query->orderByRaw('list_order IS NULL')
                ->orderBy('list_order', 'asc')
                ->orderBy('brand_name', 'asc');

            // return $query->orderBy('brand_name', 'asc');
        };


        // Fetch all companies, trending companies, and news in parallel using collections
        $queries = [
            'coming_soon' => $baseCompanyQuery(['category' => PreIpoCategoryEnum::coming_soon->value])->limit(4),
            'exclusive_deals' => $baseCompanyQuery(['category' => PreIpoCategoryEnum::exclusive_deals->value])->limit(4),
            'liquid_stocks' => $baseCompanyQuery(['category' => PreIpoCategoryEnum::liquid_stocks->value])->limit(4),
            'listed' => $baseCompanyQuery(['category' => PreIpoCategoryEnum::listed->value])->limit(4),
            'all' => $baseCompanyQuery()->limit(4),
            'trending' => $baseCompanyQuery(['is_trending' => 1])->orderBy('brand_name', 'asc'),
            'news' => CompanyNewsModel::orderBy('created_at', 'desc')->limit(10)
        ];

        // Execute queries and merge results
        foreach ($queries as $key => $query) {
            $data[$key] = $query->get()->makeHidden('is_favorite');
        }

        $data['sectors'] = MasterSectorsModel::select('id', 'name', 'icon_image', 'url_slug')->whereHas('companies', function ($query) {
            $query->where('is_deleted', 0);
        })
            ->orderBy('name', 'asc')
            ->get();

        return UtillsHelper::json(1, [
            'message' => 'Pre-IPO Home Page',
            'data' => $data,
        ]);
    }

    function getStartupNew(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'startup_id' => 'required'
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }
        $startup = StartupModel::where('id', $request->startup_id)
            ->with([
                'faqs',
                'socialMediaLinks.socialMediaType',
                'teamMembers',
                'city',
                'country',
                'state',
                'pitches' => function ($query) {
                    $query->where('status', StatusEnum::approved);
                },
                'market' => function ($query) {
                    $query->where('status', 7);
                },
                'legalInfo',
                'cms',
                'industry',
                'sector',
                'raising_round',
                'lastRounds',
                'updates' => function ($query) {
                    $query->where('status', StatusEnum::approved);
                },
            ])
            ->first();
        $portfolioData = PortfolioModel::select('investor_id', DB::raw('SUM(investment_amount) as total_invested'))
            ->where('startup_id', $request->startup_id)
            ->groupBy('investor_id')
            ->with(['investor:id,name,profile_photo,profile_visibility'])
            ->get();

        $investors = [];
        foreach ($portfolioData as $portfolio) {
            if ($portfolio->investor) {
                $investors[] = [
                    'name' => $portfolio->investor->name,
                    'profile_visibility' => $portfolio->investor->profile_visibility,
                    'profile_photo' => $portfolio->investor->profile_photo,
                    'total_invested' => $portfolio->total_invested
                ];
            }
        }

        $startup->portfolio = $investors;
        if ($startup) {
            $jsonData = json_decode(json_encode($startup), true);

            // Force bg_color_code to be a string with leading zeros preserved
            if (isset($jsonData['bg_color_code'])) {
                // Make sure we preserve the original value format
                $jsonData['bg_color_code'] = sprintf('%06s', $startup->getRawOriginal('bg_color_code'));
            }
            return UtillsHelper::json(1, ['message' => 'Startup item', 'data' => $jsonData]);
        }
        return UtillsHelper::json(0, ['message' => 'Startup not found']);
    }

    function getStartup(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'startup_id' => 'required'
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }
        $startup = StartupModel::where('id', $request->startup_id)
            ->with([
                'faqs',
                'socialMediaLinks.socialMediaType',
                'teamMembers',
                'StartupFundRaiseOne',
                'city',
                'country',
                'state',
                'pitches' => function ($query) {
                    $query->where('status', StatusEnum::approved);
                },
                'market' => function ($query) {
                    $query->where('status', 7);
                },
                'legalInfo',
                'details',
                'industry',
                'sector',
                'StartupOtherOne',
                'lastRounds',
                'raising_round',
                'updates' => function ($query) {
                    $query->where('status', StatusEnum::approved);
                },
                // 'portfolio' => function ($query) {
                //     $query->select('startup_id', 'investor_id', DB::raw('SUM(investment_amount) as total_invested'))
                //         ->groupBy('startup_id', 'investor_id')
                //         ->with(['investor:id,name,profile_photo,profile_visibility']);
                // }
            ])
            ->first();
        $portfolioData = PortfolioModel::select('investor_id', DB::raw('SUM(investment_amount) as total_invested'))
            ->where('startup_id', $request->startup_id)
            ->groupBy('investor_id')
            ->with(['investor:id,name,profile_photo,profile_visibility'])
            ->get();

        $investors = [];
        foreach ($portfolioData as $portfolio) {
            if ($portfolio->investor) {
                $investors[] = [
                    'name' => $portfolio->investor->name,
                    'profile_visibility' => $portfolio->investor->profile_visibility,
                    'profile_photo' => $portfolio->investor->profile_photo,
                    'total_invested' => $portfolio->total_invested
                ];
            }
        }

        $startup->portfolio = $investors;
        if ($startup) {
            return UtillsHelper::json(1, ['message' => 'Startup item', 'data' => $startup]);
        }
        return UtillsHelper::json(0, ['message' => 'Startup not found']);
    }

    function getStartupsNew(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'sector_id' => 'nullable',
            'status' => 'nullable|in:raisingnow,completed,comingsoon',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        // return UtillsHelper::json(1, ['message' => 'Startup list','item' => $request->headers->all()]);

        if ($request->sector_id) {
            $startups =
                StartupRoundModel::with('startup.city', 'startup.sector', 'startup.cms', 'startup.raising_round')->whereHas('startup', function ($query) use ($request) {
                    $query->where('registration_step', 6)->where('is_deleted', 0);
                    $query->where('sector_id', $request->sector_id);
                })->groupBy('startup_id');
        } else if ($request->status) {
            if ($request->status == 'completed') {
                $startups =
                    StartupRoundModel::where('round_status', StartupPrimaryRoundStatusEnum::completed->value)
                    ->with('startup.city', 'startup.sector', 'startup.cms', 'startup.raising_round')->whereHas('startup', function ($query) use ($request) {
                        $query->where('registration_step', 6)->where('is_deleted', 0);
                    })->groupBy('startup_id');
            }
            if ($request->status == 'raisingnow') {
                $startups =
                    StartupRoundModel::where('round_status', StartupPrimaryRoundStatusEnum::raisingnow->value)
                    ->with('startup.city', 'startup.sector', 'startup.cms', 'startup.raising_round')->whereHas('startup', function ($query) use ($request) {
                        $query->where('registration_step', 6)->where('is_deleted', 0);
                    })->groupBy('startup_id');
            }
            if ($request->status == 'comingsoon') {
                $startups =
                    StartupRoundModel::where('round_status', StartupPrimaryRoundStatusEnum::comingsoon->value)
                    ->with('startup.city', 'startup.sector', 'startup.cms', 'startup.raising_round')->whereHas('startup', function ($query) use ($request) {
                        $query->where('registration_step', 6)->where('is_deleted', 0);
                    })->groupBy('startup_id');
            }
        } else {
            $startups =
                StartupRoundModel::with('startup.city', 'startup.sector', 'startup.cms', 'startup.raising_round')->whereHas('startup', function ($query) use ($request) {
                    $query->where('registration_step', 6)->where('is_deleted', 0);
                })->groupBy('startup_id');
        }
        return UtillsHelper::json(1, ['message' => 'Startup list', 'data' => $startups->get()]);
    }

    function getStartups(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'sector_id' => 'nullable',
            'status' => 'nullable|in:raisingnow,completed,comingsoon',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        if ($request->sector_id) {
            $startups =
                StartupRoundModel::with('startup.city', 'startup.sector', 'startup.details', 'startup.raising_round')->whereHas('startup', function ($query) use ($request) {
                    $query->where('registration_step', 6)->where('is_deleted', 0);
                    $query->where('sector_id', $request->sector_id);
                })->groupBy('startup_id');
        } else if ($request->status) {
            if ($request->status == 'completed') {
                $startups =
                    StartupRoundModel::where('round_status', StartupPrimaryRoundStatusEnum::completed->value)
                    ->with('startup.city', 'startup.sector', 'startup.details', 'startup.raising_round')->whereHas('startup', function ($query) use ($request) {
                        $query->where('registration_step', 6)->where('is_deleted', 0);
                    })->groupBy('startup_id');
            }
            if ($request->status == 'raisingnow') {
                $startups =
                    StartupRoundModel::where('round_status', StartupPrimaryRoundStatusEnum::raisingnow->value)
                    ->with('startup.city', 'startup.sector', 'startup.details', 'startup.raising_round')->whereHas('startup', function ($query) use ($request) {
                        $query->where('registration_step', 6)->where('is_deleted', 0);
                    })->groupBy('startup_id');
            }
            if ($request->status == 'comingsoon') {
                $startups =
                    StartupRoundModel::where('round_status', StartupPrimaryRoundStatusEnum::comingsoon->value)
                    ->with('startup.city', 'startup.sector', 'startup.details', 'startup.raising_round')->whereHas('startup', function ($query) use ($request) {
                        $query->where('registration_step', 6)->where('is_deleted', 0);
                    })->groupBy('startup_id');
            }
        } else {
            $startups =
                StartupRoundModel::with('startup.city', 'startup.sector', 'startup.details', 'startup.raising_round')->whereHas('startup', function ($query) use ($request) {
                    $query->where('registration_step', 6)->where('is_deleted', 0);
                })->groupBy('startup_id');
        }
        return UtillsHelper::json(1, ['message' => 'Startup list', 'data' => $startups->get()]);
    }

    function commitNow(): JsonResponse
    {
        $request = request();

        // Validation rules, making investor_id required only if it's a business route.
        $validation = Validator::make($request->all(), [
            'type'                  => ['required', Rule::enum(PrimaryTransactionTypeEnum::class)],
            'startup_id'            => 'required',
            'round_id'              => 'required',
            'instrument'            => ['required', Rule::enum(InstrumentTypeEnum::class)],
            'shares'                => 'required|numeric',
            'share_price'           => 'required|numeric',
            'investment_amount'     => 'required|numeric',
            'fees'                  => 'nullable|numeric',
            'gst'                   => 'nullable|numeric',
            'payment_mode'          => ['required', Rule::enum(PrimaryTransactionPaymentMode::class)],
            'investor_id'           => 'required'
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }
        $investorId = $request->investor_id;
        $investor = InvestorModel::where('id', $investorId)->first();
        if ($investor && $investor->preipo_kyc_status == 0) {
            LeadsModel::create([
                'investor_id'   => $investorId,
                'startup_id'    => $request->startup_id,
                'notes'         => 'Primary Investment'
            ]);
            return UtillsHelper::json(0, ['message' => 'Your kyc is not completed. Please complete your kyc']);
        }
        $transaction = PrimaryTransactionModel::where('investor_id', $investorId)
            ->where('startup_id', $request->startup_id)
            ->where('round_id', $request->round_id)
            ->first();

        if (!$transaction) {
            $transaction = new PrimaryTransactionModel();
            $transaction->round_id      = $request->round_id;
            $transaction->startup_id    = $request->startup_id;
            $transaction->investor_id   = $investorId;
        }
        $fees = $request->fees ?? 0;
        $gst = $request->gst ?? 0;
        $transaction->type                  = $request->type;
        $transaction->instrument            = $request->instrument;
        $transaction->shares                = $request->shares;
        $transaction->share_price           = $request->share_price;
        $transaction->investment_amount     = $request->investment_amount;
        $transaction->fees                  = $fees;
        $transaction->gst                   = $gst;
        $transaction->amount_payable        = $request->investment_amount + $fees + $gst;
        $transaction->payment_mode          = $request->payment_mode;
        $transaction->status                = 2;
        $transaction->save();

        // PrimaryTransactionHelper::sendLOI($transaction);
        // if ($transaction->type == PrimaryTransactionTypeEnum::captable->value) {
        //     PrimaryTransactionHelper::sendSSA($transaction);
        // } else {
        // }

        return UtillsHelper::json(1, [
            'message' => 'Amount Committed and SSA sent',
            'data'    => $transaction
        ]);
    }



    function uploadPaymentReceipt(): JsonResponse
    {
        return $this->invRepo->uploadPaymentReceipt();
    }

    function transaction(): JsonResponse
    {
        $request = request();

        // Validation rules, making investor_id required only if it's a business route.
        $validation = Validator::make($request->all(), [
            'startup_id'            => 'required',
            'round_id'              => 'required',
            'investor_id'           => 'required'
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        // For investor route, use the authenticated user's ID. For business, use the investor_id from the request.
        $investorId = $request->investor_id;

        // Fetch the transaction based on investor_id, startup_id, and round_id.
        $transaction = PrimaryTransactionModel::where('investor_id', $investorId)
            ->where('startup_id', $request->startup_id)
            ->where('round_id', $request->round_id)
            ->first();

        // Return the transaction details.
        return UtillsHelper::json(1, [
            'message' => 'Transaction',
            'data'    => $transaction
        ]);
    }
}
