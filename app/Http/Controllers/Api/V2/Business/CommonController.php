<?php

namespace App\Http\Controllers\Api\V2\Business;

use App\Enums\CompanyDealStatusEnum;
use App\Enums\CompanyDealTypeEnum;
use App\Enums\CompanyTypeEnum;
use App\Enums\GenderEnum;
use App\Enums\InstrumentTypeEnum;
use App\Enums\InvestorTypeEnum;
use App\Enums\PartnerTypeEnum;
use App\Enums\PreIpoCategoryEnum;
use App\Enums\PreIpoOrderStepEnum;
use App\Enums\PrimaryTransactionPaymentMode;
use App\Enums\StartupPrimaryRoundStatusEnum;
use App\Enums\Utills\StatusEnum;
use App\Helpers\CommonHelper;
use App\Helpers\FileUpDownHelper;
use App\Helpers\PreIpoOrderStepHelper;
use App\Helpers\SettlementDateHelper;
use App\Helpers\UtillsHelper;
use App\Http\Controllers\Controller;
use App\Models\CompanyDailySharePriceModel;
use App\Models\CompanyDealModel;
use App\Models\CompanyModel;
use App\Models\CompanyNewsModel;
use App\Models\CompanyShareHolderPercentageModel;
use App\Models\InvestorCompanyViewModel;
use App\Models\InvestorModel;
use App\Models\MasterSectorsModel;
use App\Models\PartnerModel;
use App\Models\PortfolioModel;
use App\Models\PreIpoModel;
use App\Models\SellerCompanySharePriceModel;
use App\Models\StartupModel;
use App\Models\StartupRoundModel;
use App\Services\PreIpoOrderStepService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

class CommonController extends Controller
{
    /** @var array<int, float>|null company_id => min sell_price for today */
    private ?array $sellerLowestSellPriceTodayCache = null;

    function companyDetail(): JsonResponse
    {
        $request = request();

        $validation = Validator::make($request->all(), [
            'slug' => 'required|string',
            'deal_type' => 'nullable|in:hot,normal',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $company = CompanyModel::approved()->with([
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
            'peerratio',
        ])->where('slug', $request->slug)->first();

        if (!$company) {
            return UtillsHelper::json(0, ['message' => 'Company not found']);
        }

        $dealType = $request->input('deal_type', 'normal');
        $deals = CompanyDealModel::query()
            ->where('company_id', $company->id)
            ->notDeleted()
            ->where('status', CompanyDealStatusEnum::available->value)
            ->with([
                'createdBySeller:id,uuid,company_name,logo',
                'createdByPartner:id,uuid,name,profile_photo,gender',
            ]);

        if ($dealType === 'hot') {
            $deals->hot()->notExpired();
        } else {
            $deals->where('is_hot_deal', false)
                ->whereDate('created_at', today());
        }

        $company->setRelation(
            'deals',
            $deals
                ->orderByRaw("FIELD(deal_type, 'sell', 'buy')")
                ->orderByDesc('id')
                ->get()
                ->map(function (CompanyDealModel $deal) {
                $seller = $deal->createdBySeller;
                $partner = $deal->createdByPartner;
                $deal->unsetRelation('createdBySeller');
                $deal->unsetRelation('createdByPartner');
                $deal->setAttribute('seller', $seller ? [
                    'id' => (int) $seller->id,
                    'uuid' => $seller->uuid,
                    'company_name' => $seller->company_name,
                    'logo' => FileUpDownHelper::get_seller_logo_url($seller),
                ] : null);
                $deal->setAttribute('partner', $partner ? [
                    'id' => (int) $partner->id,
                    'uuid' => $partner->uuid,
                    'name' => $partner->name,
                    'profile_photo' => FileUpDownHelper::get_partner_profile_photo_url($partner),
                ] : null);

                return $deal;
            })->values()
        );

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
            ->where('company_id', $company->id)
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

        $company->seller_share_prices = SellerCompanySharePriceModel::query()
            ->with(['seller:id,uuid,company_name,logo'])
            ->where('company_id', $company->id)
            ->whereDate('date', today())
            ->orderBy('sell_price', 'asc')
            ->orderByDesc('id')
            ->get()
            ->map(function (SellerCompanySharePriceModel $row) {
                return [
                    'date' => $row->date,
                    'sell_price' => (float) $row->sell_price,
                    'buy_price' => $row->buy_price !== null ? (float) $row->buy_price : null,
                    'min_qty' => (int) $row->min_qty,
                    'total_qty' => $row->total_qty !== null ? (int) $row->total_qty : null,
                    'seller' => $row->seller ? [
                        'id' => (int) $row->seller->id,
                        'uuid' => $row->seller->uuid,
                        'company_name' => $row->seller->company_name,
                        'logo' => FileUpDownHelper::get_seller_logo_url($row->seller),
                    ] : null,
                ];
            })
            ->values();

        $this->applyBusinessCompanyPricing($company);

        return UtillsHelper::json(1, [
            'message' => 'Detail',
            'data' => $company
        ]);
    }

    function startupDetail(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'slug' => 'required|string'
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $startup = StartupModel::where('url_slug', $request->slug)
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

        if (!$startup) {
            return UtillsHelper::json(0, ['message' => 'Startup not found']);
        }

        $portfolioData = PortfolioModel::select('investor_id', DB::raw('SUM(investment_amount) as total_invested'))
            ->where('startup_id', $startup->id)
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

        $jsonData = json_decode(json_encode($startup), true);

        if (isset($jsonData['bg_color_code'])) {
            $jsonData['bg_color_code'] = sprintf('%06s', $startup->getRawOriginal('bg_color_code'));
        }

        return UtillsHelper::json(1, ['message' => 'Startup item', 'data' => $jsonData]);
    }

    function startupList(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'sector' => 'nullable|string',
            'status' => 'nullable|in:raisingnow,completed,comingsoon',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $with = ['startup.city', 'startup.sector', 'startup.cms', 'startup.raising_round'];

        $startups = StartupRoundModel::with($with)
            ->whereHas('startup', function ($query) use ($request) {
                $query->where('registration_step', 6)->where('is_deleted', 0);
                if ($request->filled('sector')) {
                    $query->whereHas('sector', function ($sectorQuery) use ($request) {
                        $sectorQuery->where('url_slug', $request->sector);
                    });
                }
            })
            ->when(!$request->filled('sector') && $request->status === 'completed', function ($query) {
                $query->where('round_status', StartupPrimaryRoundStatusEnum::completed->value);
            })
            ->when(!$request->filled('sector') && $request->status === 'raisingnow', function ($query) {
                $query->where('round_status', StartupPrimaryRoundStatusEnum::raisingnow->value);
            })
            ->when(!$request->filled('sector') && $request->status === 'comingsoon', function ($query) {
                $query->where('round_status', StartupPrimaryRoundStatusEnum::comingsoon->value);
            })
            ->groupBy('startup_id');

        return UtillsHelper::json(1, ['message' => 'Startup list', 'data' => $startups->get()]);
    }

    function preIpoTransactionList(): JsonResponse
    {
        $request = request();
        $skip = max(0, (int) $request->input('skip', 0));
        $take = (int) $request->input(
            'take',
            CommonHelper::appSettings('app_pagination_limit')
        );
        if ($take < 1) {
            $take = 15;
        }

        $partner = $request->user();
        $investorIds = PreIpoOrderStepHelper::buyingPartnerInvestorIds($partner);
        $transactions = PreIpoModel::whereIn('investor_id', $investorIds === [] ? [0] : $investorIds)
            ->with(['company:id,uuid,brand_name,logo', 'investor:id,name,is_self,partner_id'])
            ->orderBy('id', 'desc')
            ->skip($skip)
            ->take($take)
            ->get()
            ->map(function ($transaction) {
                return PreIpoOrderStepHelper::map($transaction, PreIpoOrderStepHelper::AUDIENCE_PARTNER);
            });

        return UtillsHelper::json(1, [
            'message' => 'Transaction List',
            'data' => $transactions
        ], 200);
    }

    function preIpoBuy(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'orders' => 'required|array|min:1',
            'orders.*.deal_uuid' => 'required|uuid',
            'orders.*.investor_id' => 'required|integer|min:1',
            'orders.*.shares' => 'required|integer|min:1',
            'orders.*.share_price' => 'required|numeric',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $partner = $request->user();
        $prepared = [];
        $sharesByDeal = [];

        foreach ($request->input('orders') as $index => $item) {
            $label = 'Order '.($index + 1);
            $investor = $this->partnerAccessibleInvestor($partner, (int) $item['investor_id']);
            if (!$investor) {
                return UtillsHelper::json(0, ['message' => $label.': Investor is not available for this partner.']);
            }

            $deal = CompanyDealModel::query()
                ->where('uuid', $item['deal_uuid'])
                ->notDeleted()
                ->notExpired()
                ->where('status', CompanyDealStatusEnum::available->value)
                ->where('deal_type', CompanyDealTypeEnum::sell->value)
                ->whereNotNull('created_by_partner_id')
                ->first();

            if (!$deal) {
                return UtillsHelper::json(0, ['message' => $label.': Deal is not available.']);
            }

            if ($deal->base_price === null) {
                return UtillsHelper::json(0, ['message' => $label.': Deal base price is missing.']);
            }

            $shares = (int) $item['shares'];
            if ($shares < (int) $deal->minimum_qty) {
                return UtillsHelper::json(0, ['message' => $label.': Shares are below the deal minimum quantity.']);
            }

            if ((int) $deal->available_quantity > 0 && $shares > (int) $deal->available_quantity) {
                return UtillsHelper::json(0, ['message' => $label.': Shares exceed the deal available quantity.']);
            }

            $sellerInvestor = InvestorModel::query()
                ->where('partner_id', $deal->created_by_partner_id)
                ->where('is_self', 1)
                ->where('is_deleted', 0)
                ->orderBy('id')
                ->first();

            if (!$sellerInvestor) {
                return UtillsHelper::json(0, ['message' => $label.': Institution self investor was not found.']);
            }

            $dealId = (int) $deal->id;
            $sharesByDeal[$dealId] = ($sharesByDeal[$dealId] ?? 0) + $shares;
            $amount = round($shares * (float) $item['share_price'], 2);

            $prepared[] = [
                'investor_id' => $investor->id,
                'company_id' => $deal->company_id,
                'deal_id' => $dealId,
                'partner_id' => $deal->created_by_partner_id,
                'seller_investor_id' => $sellerInvestor->id,
                'shares' => $shares,
                'base_price' => $deal->base_price,
                'distributer_price' => $deal->share_price,
                'share_price' => $item['share_price'],
                'amount' => $amount,
                'available_quantity' => (int) $deal->available_quantity,
            ];
        }

        foreach ($prepared as $index => $row) {
            if ($row['available_quantity'] > 0 && $sharesByDeal[$row['deal_id']] > $row['available_quantity']) {
                return UtillsHelper::json(0, [
                    'message' => 'Order '.($index + 1).': Combined shares exceed the deal available quantity.',
                ]);
            }
        }

        try {
            $transactions = DB::transaction(function () use ($prepared) {
                $created = [];
                $settlementDate = SettlementDateHelper::getT1SettlementDate(now())->format('Y-m-d');
                $cancelTimer = now()->addHours(97);

                foreach ($prepared as $row) {
                    $transaction = new PreIpoModel();
                    $transaction->status = 0;
                    $transaction->investor_id = $row['investor_id'];
                    $transaction->company_id = $row['company_id'];
                    $transaction->deal_id = $row['deal_id'];
                    $transaction->partner_id = $row['partner_id'];
                    $transaction->seller_investor_id = $row['seller_investor_id'];
                    $transaction->seller_id = null;
                    $transaction->shares = $row['shares'];
                    $transaction->base_price = $row['base_price'];
                    $transaction->distributer_price = $row['distributer_price'];
                    $transaction->share_price = $row['share_price'];
                    $transaction->investment_amount = $row['amount'];
                    $transaction->payable_amount = $row['amount'];
                    $transaction->is_distributer = true;
                    $transaction->payment_mode = PrimaryTransactionPaymentMode::rtgs;
                    $transaction->instrument = InstrumentTypeEnum::equity;
                    $transaction->settlement_date = $settlementDate;
                    $transaction->transaction_cancel_timer = $cancelTimer;
                    $transaction->order_step = PreIpoOrderStepEnum::mandate_pending->value;
                    $transaction->save();
                    $created[] = $transaction->fresh();
                }

                return $created;
            });
        } catch (Throwable $e) {
            return UtillsHelper::json(0, ['message' => $e->getMessage()]);
        }

        $mandateFailures = 0;
        $data = [];
        foreach ($transactions as $transaction) {
            $sent = false;
            try {
                $sent = app(PreIpoOrderStepService::class)->sendBuyMandate($transaction);
            } catch (Throwable $e) {
                report($e);
                $sent = false;
            }
            if (!$sent) {
                $mandateFailures++;
            }
            $row = PreIpoOrderStepHelper::map(
                $transaction->fresh(['company', 'investor']),
                PreIpoOrderStepHelper::AUDIENCE_PARTNER
            );
            $row['mandate_sent'] = $sent;
            $data[] = $row;
        }

        return UtillsHelper::json(1, [
            'message' => $mandateFailures > 0
                ? 'Orders placed. The buy mandate could not be sent.'
                : 'Orders placed successfully.',
            'data' => $data,
        ]);
    }

    function investorDetail(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'uuid' => 'required|uuid',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $partner = $request->user();
        $partnerIds = PartnerModel::select('id')
            ->where('parent_id', $partner->id)
            ->where('type', PartnerTypeEnum::relationmanager->value)
            ->pluck('id');
        $partnerIds->push($partner->id);

        $investor = InvestorModel::where('uuid', $request->uuid)
            ->where('is_deleted', 0)
            ->whereIn('partner_id', $partnerIds)
            ->with([
                'city',
                'state',
                'country',
                'kyc',
                'investor_detail',
                'dematAccount',
                'relation',
                'partner:id,name',
            ])
            ->first();

        if (!$investor) {
            return UtillsHelper::json(0, ['message' => 'Investor not found']);
        }

        return UtillsHelper::json(1, [
            'message' => 'Investor detail',
            'data' => $investor
        ]);
    }

    function investorList(): JsonResponse
    {
        $request = request();

        $validation = Validator::make($request->all(), [
            'is_kyc' => ['required', 'in:All,Yes,No'],
            'is_active' => ['required', 'in:All,Yes,No'],
            'is_aif' => ['nullable', 'in:All,Yes,No'],
            'relation_manager_ids' => ['nullable', 'string'],
        ]);

        if (!$request->has('is_aif')) {
            $request->is_aif = 'All';
        }

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $partner = $request->user();
        $commissionRate = $partner->commission != 0 ? ($partner->commission / 100) : 0;

        $partners = PartnerModel::select('id')
            ->where('parent_id', $partner->id)
            ->where('type', PartnerTypeEnum::relationmanager->value)
            ->pluck('id');
        $partners->push($partner->id);

        $investorQuery = InvestorModel::whereIn('partner_id', $partners)->where('is_self', 0);
        if ($request->filled('relation_manager_ids')) {
            $investorQuery = InvestorModel::whereIn('partner_id', explode(',', $request->relation_manager_ids))->where('is_self', 0);
        }

        $this->applyPartnerInvestorListFilters($investorQuery, $request);

        $investordata = $investorQuery->where('is_deleted', '0')
            ->with($this->partnerInvestorListWith())
            ->get()
            ->map(function ($investor) use ($commissionRate) {
                return $this->decoratePartnerInvestorListRow($investor, $commissionRate);
            });

        $selfQuery = InvestorModel::where('partner_id', $partner->id)->where('is_self', 1);
        $this->applyPartnerInvestorListFilters($selfQuery, $request);
        $selfInvestor = $selfQuery->where('is_deleted', '0')
            ->with($this->partnerInvestorListWith())
            ->first();

        if ($selfInvestor) {
            $investordata->prepend($this->decoratePartnerInvestorListRow($selfInvestor, $commissionRate));
        }

        return UtillsHelper::json(1, [
            'message' => 'Investor List',
            'data' => $investordata,
        ]);
    }

    function investorCreate(): JsonResponse
    {
        $request = request();

        if (!$request->filled('email')) {
            $request->merge(['email' => null]);
        }
        if (!$request->filled('gender')) {
            $request->merge(['gender' => null]);
        }

        $emailRules = ['nullable', 'email'];
        if ($request->filled('email')) {
            $emailRules[] = Rule::unique((new InvestorModel)->getTable())->where(function ($query) {
                return $query->where('is_deleted', '0')->where('registration_step', '3');
            });
        }

        $validation = Validator::make($request->all(), [
            'investor_type' => ['required', Rule::enum(InvestorTypeEnum::class)],
            'name' => 'required|string|max:255',
            'mobile_number' => [
                'required',
                'numeric',
                Rule::unique((new InvestorModel)->getTable())->where(function ($query) {
                    return $query->where('is_deleted', '0')->where('registration_step', '3');
                }),
            ],
            'email' => $emailRules,
            'gender' => ['nullable', Rule::enum(GenderEnum::class)],
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $partner = $request->user();

        $investor = InvestorModel::where('mobile_number', $request->mobile_number)
            ->where('mobile_country_code', 91)
            ->where('registration_step', '!=', 3)
            ->where('is_deleted', 0)
            ->first();

        if (!$investor) {
            $investor = new InvestorModel();
        }

        $investor->partner_id = $partner->id;
        $investor->created_by = $partner->created_by;
        $investor->updated_by = $partner->created_by;
        $investor->registration_step = 3;
        $investor->mobile_country_code = 91;
        $investor->mobile_number = $request->mobile_number;
        $investor->referral_code = UtillsHelper::generateUniqueReferralCode();
        $investor->is_self = 0;
        $investor->investor_type = $request->investor_type;
        $investor->name = ucfirst(trim($request->name));
        $investor->email = $request->filled('email') ? strtolower(trim($request->email)) : null;
        $investor->is_primary_access = $partner->is_primary_access ? 1 : 0;
        $investor->is_secondary_access = $partner->is_secondary_access ? 1 : 0;
        $investor->is_preipo_access = $partner->is_preipo_access ? 1 : 0;

        if ($request->filled('gender')) {
            $investor->gender = $request->gender;
        }

        $investor->save();

        return UtillsHelper::json(1, [
            'message' => 'Investor Created',
            'data' => $investor,
        ]);
    }

    public function getPreipoHome(): JsonResponse
    {
        $data = [];
        $oneYearAgo = now()->subYear();


        $companies = CompanyModel::approved()->where('is_deleted', 0)
            ->where('type', CompanyTypeEnum::unlisted->value)
            ->where(function ($q) {
                $q->whereNull('category')
                    ->orWhere('category', '!=', PreIpoCategoryEnum::listed->value);
            })
            ->select(
                'id',
                'slug',
                'sector_id',
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
                'is_price_updated_today',
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
                'grabOpportunitySlots',
                'sector' => fn($q) => $q->select('id', 'name'),
            ])
            ->orderByRaw('list_order IS NULL')
            ->orderBy('list_order', 'asc')
            ->orderBy('brand_name', 'asc')
            ->get()
            ->each(fn($c) => $c->setAppends([]));

        $formatCompany = function ($company) {
            $clone = clone $company;
            if ($company->relationLoaded('sharePrices')) {
                $clone->setRelation('sharePrices', $company->sharePrices);
            }
            if ($company->relationLoaded('grabOpportunitySlots')) {
                $clone->setRelation('grabOpportunitySlots', $company->grabOpportunitySlots);
            }
            if ($company->relationLoaded('fundamentals')) {
                $clone->setRelation('fundamentals', $company->fundamentals);
            }
            if ($company->relationLoaded('sector')) {
                $clone->setRelation('sector', $company->sector);
            }

            $formatted = $this->formatCompanyWithPrices($clone);
            $formatted = $this->appendGrabOpportunity($formatted, $clone);
            $formatted['min_investment_type'] = $clone->min_investment_type;
            $formatted['min_investment_amount'] = (float) $clone->min_investment_amount;
            $formatted['sector'] = $company->sector?->name;
            return $formatted;
        };

        $data['all'] = $companies
            ->take(8)
            ->map($formatCompany)
            ->values();

        $nonListedCompanies = $companies
            ->where('category', '!=', PreIpoCategoryEnum::listed->value);

        $data['exclusive_deals'] = $nonListedCompanies
            ->where('category', PreIpoCategoryEnum::exclusive_deals->value)
            ->take(8)
            ->map($formatCompany)
            ->values();

        $data['liquid_stocks'] = $nonListedCompanies
            ->where('category', PreIpoCategoryEnum::liquid_stocks->value)
            ->take(8)
            ->map($formatCompany)
            ->values();

        $data['drhp'] = $nonListedCompanies
            ->where('is_drhp', 1)
            ->where('category', '!=', PreIpoCategoryEnum::coming_soon->value)
            ->where('category', '!=', PreIpoCategoryEnum::listed->value)
            ->take(8)
            ->map($formatCompany)
            ->values();

        $data['trending'] = $nonListedCompanies
            ->where('is_trending', 1)
            ->take(8)
            ->map($formatCompany)
            ->values();

        $priceFluctuation = CompanyDailySharePriceModel::getTopGainersLosers(8);
        $data['top_gainers'] = $priceFluctuation['up'];
        $data['top_losers']  = $priceFluctuation['down'];
        $data['hot_deals'] = $this->buildHotDealsCompanyList(CompanyTypeEnum::unlisted->value);

        return UtillsHelper::json(1, [
            'message' => 'Pre-IPO Home Page',
            'data'    => $data,
        ]);
    }

    public function getPreipoNewsAndSectors(): JsonResponse
    {
        $news = CompanyNewsModel::whereHas('company', function ($query) {
            $query->where('is_deleted', 0)
                ->where('type', CompanyTypeEnum::unlisted->value);
        })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $sectors = $this->getSectorsWithCompanies(CompanyTypeEnum::unlisted->value);

        return UtillsHelper::json(1, [
            'message' => 'Pre-IPO News & Sectors',
            'data' => [
                'news' => $news,
                'sectors' => $sectors
            ]
        ]);
    }

    public function getSecondaryHome(): JsonResponse
    {
        $oneYearAgo = now()->subYear();

        $companies = CompanyModel::approved()->where('is_deleted', 0)
            ->where('type', CompanyTypeEnum::secondary->value)
            ->where(function ($q) {
                $q->whereNull('category')
                    ->orWhere('category', '!=', PreIpoCategoryEnum::listed->value);
            })
            ->select(
                'id',
                'slug',
                'sector_id',
                'brand_name',
                'logo',
                'about',
                'category',
                'type',
                'is_drhp',
                'bg_color_code',
                'share_price',
                'distributer_price',
                'base_price',
                'price_updated_today',
                'is_price_updated_today',
                'last_year_share_price',
                'is_trending',
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
                'grabOpportunitySlots',
                'sector' => fn($q) => $q->select('id', 'name'),
            ])
            ->orderByRaw('list_order IS NULL')
            ->orderBy('list_order', 'asc')
            ->orderBy('brand_name', 'asc')
            ->limit(8)
            ->get()
            ->each(fn($c) => $c->setAppends([]));

        $all = $companies->map(function ($company) {
            $formatted = $this->formatCompanyWithPrices($company);
            $formatted = $this->appendGrabOpportunity($formatted, $company);
            $formatted['min_investment_type'] = $company->min_investment_type;
            $formatted['min_investment_amount'] = (float) $company->min_investment_amount;
            $formatted['sector'] = $company->sector?->name;
            return $formatted;
        })->values();

        $news = CompanyNewsModel::whereHas('company', function ($query) {
            $query->where('is_deleted', 0)
                ->where('type', CompanyTypeEnum::secondary->value);
        })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $sectors = $this->getSectorsWithCompanies(CompanyTypeEnum::secondary->value);

        return UtillsHelper::json(1, [
            'message' => 'Secondary Home Page',
            'data' => [
                'all' => $all,
                'news' => $news,
                'sectors' => $sectors,
            ],
        ]);
    }

    public function getPreipoNews(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'skip' => 'nullable|integer|min:0',
            'take' => 'nullable|integer|min:1',
            'company_id' => 'nullable|integer',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $skip = max(0, (int) $request->input('skip', 0));
        $take = (int) $request->input(
            'take',
            CommonHelper::appSettings('app_pagination_limit')
        );
        if ($take < 1) {
            $take = 15;
        }

        $query = CompanyNewsModel::whereHas('company', function ($companyQuery) {
            $companyQuery->where('is_deleted', 0)
                ->where('type', CompanyTypeEnum::unlisted->value);
        })->orderBy('created_at', 'desc');

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        $news = $query->skip($skip)->take($take)->get();

        return UtillsHelper::json(1, [
            'message' => 'Pre-IPO News List',
            'data' => $news,
        ]);
    }

    public function companyList(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'category' => 'nullable|in:All,' . implode(',', array_column(PreIpoCategoryEnum::cases(), 'value')),
            'sector' => 'nullable|string',
            'search' => 'nullable|string',
            'type' => 'nullable|in:' . implode(',', array_column(CompanyTypeEnum::cases(), 'value')),
            'skip' => 'nullable|integer|min:0',
            'take' => 'nullable|integer|min:1',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $oneYearAgo = now()->subYear();
        $type = $request->input('type', CompanyTypeEnum::unlisted->value);

        $query = CompanyModel::approved()->where('is_deleted', '0')
            ->where('status', '0')
            ->where('type', $type)
            ->select(
                'id',
                'uuid',
                'slug',
                'brand_name',
                'logo',
                'category',
                'type',
                'is_drhp',
                'bg_color_code',
                'share_price',
                'distributer_price',
                'base_price',
                'price_updated_today',
                'is_price_updated_today',
                'last_year_share_price',
                'is_trending',
                'is_grab_opportunity_enabled',
                'min_investment_type',
                'min_investment_amount'
            )
            ->with([
                'fundamentals' => function ($q) {
                    $q->select('company_id', 'lot_size', 'fifty_two_week_high', 'fifty_two_week_low', 'depository');
                },
                'sharePrices' => function ($q) use ($oneYearAgo) {
                    $q->select('company_id', 'price', 'date')
                        ->whereDate('date', '>=', $oneYearAgo)
                        ->orderBy('date', 'asc');
                },
                'grabOpportunitySlots',
            ]);

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

        if ($request->filled('sector')) {
            $query->whereHas('sector', function ($sectorQuery) use ($request) {
                $sectorQuery->where('url_slug', $request->sector);
            });
        }

        if ($request->filled('search')) {
            $query->where('brand_name', 'like', '%' . $request->input('search') . '%');
        }

        $skip = max(0, (int) $request->input('skip', 0));
        $take = (int) $request->input(
            'take',
            CommonHelper::appSettings('app_pagination_limit')
        );
        if ($take < 1) {
            $take = 15;
        }

        $list = $query
            ->orderByRaw('list_order IS NULL')
            ->orderBy('list_order', 'asc')
            ->orderBy('brand_name', 'asc')
            ->skip($skip)
            ->take($take)
            ->get()
            ->each(fn($c) => $c->setAppends([]))
            ->map(function ($company) {
                $formatted = $this->formatCompanyWithPrices($company);
                $formatted = $this->appendGrabOpportunity($formatted, $company);
                $formatted['min_investment_type'] = $company->min_investment_type;
                $formatted['min_investment_amount'] = (float) $company->min_investment_amount;
                return $formatted;
            })
            ->values();

        return UtillsHelper::json(1, [
            'message' => 'Company List',
            'data' => $list,
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

    private function applyBusinessCompanyPricing(CompanyModel $company): void
    {
        $lowestSellerSellPriceToday = $this->getLowestSellerSellPriceToday((int) $company->id);

        if ($lowestSellerSellPriceToday !== null) {
            $company->base_price = $lowestSellerSellPriceToday;
        } else {
            // No seller price updates today → partner base matches retail share_price
            $company->base_price = (float) $company->share_price;
        }

        $company->distributer_price = $company->base_price;
    }

    private function getLowestSellerSellPriceToday(int $companyId): ?float
    {
        if ($this->sellerLowestSellPriceTodayCache === null) {
            $this->sellerLowestSellPriceTodayCache = SellerCompanySharePriceModel::query()
                ->whereDate('date', today())
                ->selectRaw('company_id, MIN(sell_price) as min_sell_price')
                ->groupBy('company_id')
                ->pluck('min_sell_price', 'company_id')
                ->map(fn ($price) => (float) $price)
                ->all();
        }

        return $this->sellerLowestSellPriceTodayCache[$companyId] ?? null;
    }

    /**
     * Companies that have at least one hot, non-expired deal, each with nested deals[].
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function buildHotDealsCompanyList(?string $type = null)
    {
        $query = CompanyModel::approved()
            ->where('is_deleted', 0)
            ->where('status', '0')
            ->whereIn('type', [
                CompanyTypeEnum::unlisted->value,
                CompanyTypeEnum::secondary->value,
            ])
            ->whereHas('deals', function ($q) {
                $q->notDeleted()
                    ->notExpired()
                    ->hot();
            })
            ->select(
                'id',
                'uuid',
                'slug',
                'brand_name',
                'logo',
                'category',
                'type',
                'share_price',
                'distributer_price',
                'base_price',
                'price_updated_today',
                'is_price_updated_today',
                'last_year_share_price',
                'is_trending',
                'bg_color_code'
            )
            ->with([
                'deals' => function ($q) {
                    $q->notDeleted()
                        ->notExpired()
                        ->hot()
                        ->orderByRaw("FIELD(deal_type, 'sell', 'buy')")
                        ->orderByDesc('id');
                },
            ]);

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query
            ->orderBy('brand_name', 'asc')
            ->get()
            ->each(fn ($c) => $c->setAppends([]))
            ->map(function (CompanyModel $company) {
                $this->applyBusinessCompanyPricing($company);

                return [
                    'id' => $company->id,
                    'uuid' => $company->uuid,
                    'slug' => $company->slug,
                    'brand_name' => $company->brand_name,
                    'logo' => $company->logo,
                    'category' => $company->category,
                    'type' => $company->type,
                    'share_price' => (float) $company->share_price,
                    'distributer_price' => (float) $company->distributer_price,
                    'base_price' => (float) $company->base_price,
                    'price_updated_today' => (int) $company->price_updated_today,
                    'is_price_updated_today' => (int) $company->is_price_updated_today,
                    'last_year_share_price' => (float) $company->last_year_share_price,
                    'is_trending' => (int) $company->is_trending,
                    'bg_color_code' => $company->bg_color_code,
                    'deals' => $company->deals->map(function (CompanyDealModel $deal) {
                        return [
                            'uuid' => $deal->uuid,
                            'deal_type' => $deal->deal_type instanceof \BackedEnum
                                ? $deal->deal_type->value
                                : $deal->deal_type,
                            'available_quantity' => $deal->available_quantity,
                            'share_price' => (float) $deal->share_price,
                            'minimum_qty' => $deal->minimum_qty,
                            'processing_fee_percentage' => (float) $deal->processing_fee_percentage,
                            'status' => $deal->status instanceof \BackedEnum
                                ? $deal->status->value
                                : $deal->status,
                            'is_hot_deal' => (bool) $deal->is_hot_deal,
                            'expired_at' => $deal->expired_at?->format('Y-m-d H:i:s'),
                        ];
                    })->values(),
                ];
            })
            ->values();
    }

    private function formatCompanyWithPrices($company)
    {
        $this->applyBusinessCompanyPricing($company);

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

    private function getSectorsWithCompanies(string $companyType)
    {
        return MasterSectorsModel::select('id', 'name', 'icon_image', 'url_slug')
            ->whereHas('companies', function ($query) use ($companyType) {
                $query->where('is_deleted', 0)
                    ->where('type', $companyType)
                    ->where(function ($q) {
                        $q->whereNull('category')
                            ->orWhere('category', '!=', PreIpoCategoryEnum::listed->value);
                    });
            })
            ->with(['companies' => function ($query) use ($companyType) {
                $query->select('id', 'sector_id', 'brand_name', 'logo')
                    ->where('is_deleted', 0)
                    ->where('type', $companyType)
                    ->where(function ($q) {
                        $q->whereNull('category')
                            ->orWhere('category', '!=', PreIpoCategoryEnum::listed->value);
                    })
                    ->orderByRaw('list_order IS NULL')
                    ->orderBy('list_order', 'asc')
                    ->orderBy('brand_name', 'asc');
            }])
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($sector) {
                $companies = $sector->companies->map(fn ($company) => [
                    'brand_name' => $company->brand_name,
                    'logo' => $company->logo,
                ])->values();

                return [
                    'id' => $sector->id,
                    'name' => $sector->name,
                    'icon_image' => $sector->icon_image,
                    'url_slug' => $sector->url_slug,
                    'company_count' => $companies->count(),
                    'companies' => $companies,
                ];
            })
            ->values();
    }

    private function partnerAccessibleInvestor($partner, int $investorId): ?InvestorModel
    {
        $partnerIds = PartnerModel::select('id')
            ->where('parent_id', $partner->id)
            ->where('type', PartnerTypeEnum::relationmanager->value)
            ->pluck('id');
        $partnerIds->push($partner->id);

        return InvestorModel::query()
            ->where('id', $investorId)
            ->where('is_deleted', 0)
            ->where(function ($query) use ($partner, $partnerIds) {
                $query->where(function ($clients) use ($partnerIds) {
                    $clients->whereIn('partner_id', $partnerIds)->where('is_self', 0);
                })->orWhere(function ($self) use ($partner) {
                    $self->where('partner_id', $partner->id)->where('is_self', 1);
                });
            })
            ->first();
    }

    private function applyPartnerInvestorListFilters($investorQuery, $request): void
    {
        if ($request->is_kyc !== 'All') {
            $investorQuery->where('preipo_kyc_status', $request->is_kyc === 'Yes' ? 1 : 0);
        }
        if ($request->is_aif !== 'All') {
            $investorQuery->where('aif_status', $request->is_aif === 'Yes' ? 1 : 0);
        }
        if ($request->is_active !== 'All') {
            $investorQuery->where('is_active', $request->is_active === 'Yes' ? 1 : 0);
        }
    }

    private function partnerInvestorListWith(): array
    {
        return [
            'partner' => function ($query) {
                $query->select('id', 'name');
            },
            'portfolio' => function ($query) {
                $query->select('id', 'investor_id', 'investment_amount', 'startup_id');
            },
            'portfolio.startup' => function ($query) {
                $query->select('id', 'brand_name');
            },
            'city',
            'state',
            'country',
            'kyc',
        ];
    }

    private function decoratePartnerInvestorListRow($investor, float $commissionRate)
    {
        $totalInvested = $investor->portfolio->sum('investment_amount');
        $noOfStartups = $investor->portfolio->pluck('startup_id')->unique()->count();
        $commissionEarned = $investor->portfolio->sum(function ($portfolio) use ($commissionRate) {
            return $portfolio->investment_amount * $commissionRate;
        });

        $startupList = $investor->portfolio->groupBy('startup_id')->map(function ($portfolioGroup) use ($commissionRate) {
            $portfolio = $portfolioGroup->first();
            return [
                'brand_name' => $portfolio->startup ? $portfolio->startup->brand_name : 'Unknown',
                'amount_invested' => $portfolio->investment_amount,
                'commission_earned' => $portfolio->investment_amount * $commissionRate,
            ];
        })->values();

        $investor->total_invested = $totalInvested;
        $investor->no_of_startups = $noOfStartups;
        $investor->commission_earned = $commissionEarned;
        $investor->startup_list = $startupList;
        $investor->partner_details = [
            'partner_id' => $investor->partner->id ?? null,
            'partner_name' => $investor->partner->name ?? 'Unknown',
        ];

        return $investor;
    }
}
