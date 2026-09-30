<?php

namespace App\Repositories\V2;

use App\Enums\DocumentTypeEnum;
use App\Enums\GenderEnum;
use App\Enums\InstrumentTypeEnum;
use App\Enums\InvestorTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\PartnerTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\PreIpoCategoryEnum;
use App\Enums\PrimaryTransactionPaymentMode;
use App\Enums\StartupPrimaryRoundStatusEnum;
use App\Enums\Utills\CodeVerificationTypeEnum;
use App\Enums\Utills\DeviceTypeEnum;
use App\Enums\Utills\StatusEnum;
use App\Enums\WpMessageTypeEnum;
use App\Helpers\AdminHelper;
use App\Helpers\CommonHelper;
use App\Helpers\DateTimeHelper;
use App\Helpers\DigioHelper;
use App\Helpers\FileUpDownHelper;
use App\Helpers\PreIpoTransactionHelper;
use App\Helpers\PrimaryTransactionHelper;
use App\Helpers\SecondaryTransactionHelper;
use App\Helpers\UtillsHelper;
use App\Helpers\WhatsAppMessagesHelper;
use App\Http\Controllers\Web\Admin\InvestorController;
use App\Http\Requests\InvestorRequest;
use App\Jobs\broadcast\Whatsapp;
use App\Jobs\common\RequestAccessJob;
use App\Models\BankDetailsModel;
use App\Models\CompanyNewsModel;
use App\Models\CoreFirebaseDeviceTokenModel;
use App\Models\DocumentsModel;
use App\Models\InvestorDematAccountModel;
use App\Models\InvestorFavStartupModel;
use App\Models\InvestorKycAadharModel;
use App\Models\InvestorKycModel;
use App\Models\InvestorKycPanModel;
use App\Models\InvestorMandatesModel;
use App\Models\InvestorModel;
use App\Models\InvestorPanDetailsModel;
use App\Models\InvestorRegisterRequestModel;
use App\Models\MasterCityModel;
use App\Models\NotificationsModel;
use App\Models\PartnerModel;
use App\Models\PortfolioModel;
use App\Models\PortfolioPreIpoModel;
use App\Models\PreIpoModel;
use App\Models\PrimaryTransactionModel;
use App\Models\PrimaryTransactionPaymentModel;
use App\Models\SecondarySellRequestModel;
use App\Models\SecondaryTransactionModel;
use App\Models\StartupMisModel;
use App\Models\StartupModel;
use App\Models\StartupRoundModel;
use App\Models\StartupPitchModel;
use App\Models\UserBankAccountModel;
use App\Services\DematKycService;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;


class InvestorRepository
{
    function getPortfolioPreIpoDetail(): JsonResponse|Builder
    {
        $request = request();
        $investorId = $request->user()->id;
        $validation = Validator::make($request->all(), [
            'portfolio_id'  => 'required',
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
        $portfolio = PortfolioPreIpoModel::where('id', $request->portfolio_id)
            ->where('investor_id', $investorId)
            ->first();

        if (!$portfolio) {
            return UtillsHelper::json(0, ['message' => 'Portfolio not found or does not belong to the user.']);
        }

        $portfolio->load('company:id,brand_name,company_name,logo');

        // $portfolio->company->makeHidden(['transaction', 'share_price', 'distributer_price', 'base_price']);
        $portfolio->transactions = PreIpoModel::with([
            'company:id,brand_name,company_name,logo'
        ])
            ->where('portfolio_id', $portfolio->id)
            ->get();

        $portfolio->news = CompanyNewsModel::where('company_id', $portfolio->company_id)
            ->orderBy('created_at', 'desc')->limit(10)
            ->get();

        return UtillsHelper::json(
            1,
            [
                'message' => 'Portfolio details fetched successfully',
                'data' => $portfolio,
            ],
            200
        );
    }

    function getPortfolioPreIpo(): JsonResponse|Builder
    {
        $request = request();
        $portfolio = PortfolioPreIpoModel::where('investor_id', $request->user()->id)
            ->with([
                'company' => function ($query) {
                    $query->select('id', 'brand_name', 'logo', 'share_price');
                }
            ])
            ->whereHas('company', function ($query) {
                $query->where('category', '!=', PreIpoCategoryEnum::listed->value);
            })
            ->where('shares', '>', '0')
            ->get()
            ->map(function ($transaction) {
                $transaction->company->makeHidden(['transaction', 'share_price', 'distributer_price', 'base_price']);
                return $transaction;
            });
        return UtillsHelper::json(
            1,
            [
                'message'   => "Portfolio List",
                'data'      => $portfolio
            ],
            200
        );
    }

    function getPreIpoTransactionDetails(): JsonResponse|Builder
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'transaction_id'  => 'required',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $transaction = PreIpoModel::with('company')->where('id', $request->transaction_id)
            ->where('investor_id', $request->user()->id)
            ->first();


        if (!$transaction) {
            return UtillsHelper::json(0, ['message' => 'Transaction not found or does not belong to the user.']);
        }
        $transaction->company->makeHidden(['transaction']);
        $transaction->status_list = PreIpoTransactionHelper::getStatusListForApplicationV2($transaction);
        // $transaction->deal_slip_signers = $transaction->deal_slip?->signers ?? [];
        if (in_array($transaction->status, [1, 5])) {
            $transaction->makeHidden('transaction_cancel_timer');
        }
        return UtillsHelper::json(1, ['message' => 'Transaction details fetched successfully', 'data' => $transaction]);
    }

    function getPortfolio(): JsonResponse|Builder
    {
        $request = request();
        if ($request->is('api/*')) {
            $portfolio = PortfolioModel::where('investor_id', $request->user()->id)->with('startup.details', 'startup.cms', 'investor')->where('shares', '>', '0');

            $data = null;

            if ($request->has('type')) {
                switch ($request->type) {
                    case InstrumentTypeEnum::equity->value:
                        $data = (clone $portfolio)->where('instrument', InstrumentTypeEnum::equity);
                        break;
                    case InstrumentTypeEnum::ccps->value:
                        $data = (clone $portfolio)->where('instrument', InstrumentTypeEnum::ccps);
                        break;
                    case InstrumentTypeEnum::ccd->value:
                        $data = (clone $portfolio)->where('instrument', InstrumentTypeEnum::ccd);
                        break;
                    default:
                        break;
                }
            } else {
                return UtillsHelper::json(0, ['message' => 'Type Required']);
            }

            return UtillsHelper::json(
                1,
                [
                    'message'   => "Portfolio List",
                    'data'      => $data->get()
                ],
                200
            );
        } else {
            return PortfolioModel::where('investor_id', Auth::guard('investor')->user()->id);
        }
    }

    function getPortfolioDetails(): JsonResponse|Builder
    {
        $request = request();
        $investorId = $request->user()->id;
        $validation = Validator::make($request->all(), [
            'portfolio_id'  => 'required',
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
        $portfolio = PortfolioModel::with('startup.cms')->where('id', $request->portfolio_id)
            ->where('investor_id', $investorId)
            ->first();

        if (!$portfolio) {
            return UtillsHelper::json(0, ['message' => 'Portfolio not found or does not belong to the user.']);
        }

        $portfolio->primary_transactions = PrimaryTransactionModel::where('portfolio_id', $portfolio->id)->get();
        $portfolio->secondary_transactions = SecondaryTransactionModel::where('c_portfolio_id', $portfolio->id)->get();

        return UtillsHelper::json(
            1,
            [
                'message' => 'Portfolio details fetched successfully',
                'data' => $portfolio,
            ],
            200
        );
    }

    function getDocuments(): JsonResponse|Builder
    {
        $request = request();
        if ($request->is('api/*')) {
            $docuements = DocumentsModel::where('status', 1)->whereJsonContains('meta->investor', $request->user()->id)->orderby('id', 'desc');

            if ($request->has('start_date')) {
                $docuements->whereDate('created_at', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $docuements->whereDate('created_at', '<=', $request->end_date);
            }
            if ($request->has('type')) {
                $types = explode(',', $request->type);
                $docuements->whereIn('type', $types);
                // $validTypes = array_column(DocumentTypeEnum::cases(), 'value');

                // $filteredTypes = array_intersect($types, $validTypes);

                // if (!empty($filteredTypes)) {
                // }
            }
            if ($request->skip) {
                $docuements->skip($request->skip);
            }
            $docuements->take(CommonHelper::appSettings('app_pagination_limit'));

            return UtillsHelper::json(
                1,
                [
                    'message' => "Document List",
                    'data' => $docuements->get()
                ],
                200
            );
        } else {
            return DocumentsModel::where('status', '1')->whereJsonContains('meta->investor', Auth::guard('investor')->user()->id)->orderby('id', 'desc')->limit(200);
        }
    }

    function getSecTransactionDetails(): JsonResponse|Builder
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'transaction_id'  => 'nullable',
            'sell_request_id' => 'nullable',
        ]);

        $validation->after(function ($validator) use ($request) {
            $hasTransactionId = $request->filled('transaction_id');
            $hasSellRequestId = $request->filled('sell_request_id');

            if (!$hasTransactionId && !$hasSellRequestId) {
                $validator->errors()->add('transaction_id', 'One of transaction_id or sell_request_id is required.');
            }

            if ($hasTransactionId && $hasSellRequestId) {
                $validator->errors()->add('transaction_id', 'Only one of transaction_id or sell_request_id should be provided.');
            }
        });
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        if ($request->has('transaction_id')) {
            $transaction = SecondaryTransactionModel::with('startup.cms', 'seller')->where('id', $request->transaction_id)
                ->where('buyer_id', $request->user()->id)
                ->first();
            if (!$transaction) {
                return UtillsHelper::json(0, ['message' => 'Transaction not found or does not belong to the user.']);
            }
            $transaction->status_list = SecondaryTransactionHelper::getTransactionStatusListForApplication($transaction);
            $data = [
                'type' => 'buy',
                'sell' => NULL,
                'buy' => $transaction,
                'created_at' => $transaction->created_at,
            ];
            return UtillsHelper::json(1, ['message' => 'Detail', 'data' => $data]);
        } else {
            $transaction = SecondarySellRequestModel::with('startup.cms', 'transactions.buyer')->where('id', $request->sell_request_id)
                ->where('investor_id', $request->user()->id)
                ->first();
            if (!$transaction) {
                return UtillsHelper::json(0, ['message' => 'Transaction not found or does not belong to the user.']);
            }

            if ($transaction->transactions && $transaction->transactions->count()) {
                foreach ($transaction->transactions as $trans) {
                    $trans->status_list = SecondaryTransactionHelper::getTransactionStatusListForApplication($trans);
                }
            }

            $transaction->status_list = SecondaryTransactionHelper::getSellRequestStatusListForApplication($transaction);
            $data = [
                'type' => 'sell',
                'sell' => $transaction,
                'buy' => NULL,
                'created_at' => $transaction->created_at,
            ];
            return UtillsHelper::json(1, ['message' => 'Detail', 'data' => $data]);
        }
    }

    function dashboard(): array
    {
        $request = request();
        if ($request->is('api/*')) {
            $user = $request->user();
        } else {
            $user = Auth::guard('investor')->user();
        }

        $profitBooked = 0;
        $transactions = SecondaryTransactionModel::where('seller_id', $user->id)->get();

        foreach ($transactions as $transaction) {
            $sellingPrice = $transaction->shares * $transaction->share_price;

            $portfolio = $transaction->portfolio;

            if ($portfolio) {
                $tempProfitBooked = $sellingPrice - ($portfolio->shares * $portfolio->purchase_price);
                $profitBooked += $tempProfitBooked;
            }
        }

        $portfolio = PortfolioModel::with([
            'investor',
            'startup.sharePrices' => function ($query) {
                $query->orderByDesc('created_at')->limit(1);
            },
            'startup.details',
            'startup.cms',
            'startup.sector'
        ])
            ->where('investor_id', $user->id)
            ->get()
            ->groupBy('startup_id');

        $startupIds = $portfolio->keys();
        $latestMisEntries = StartupMisModel::with('startup.details', 'startup.cms')
            ->whereIn('startup_id', $startupIds)
            ->where('status', StatusEnum::approved->value)
            ->latest()
            ->take(3)
            ->get();

        $currentPortfolioValue = $portfolio->sum(function ($investments) {
            $investment = $investments->first();
            $latestSharePrice = $investment->startup->sharePrices->first()->price ?? $investment->purchase_price;
            return $investment->shares * $latestSharePrice;
        });

        $totalStartupsInvested = $portfolio->count();
        // $totalInvestmentAmount = $portfolio->sum('investment_amount');
        $totalInvestmentAmount = $portfolio->sum(function ($investments) {
            return $investments->sum('investment_amount');
        });





        $sectorArray = [];
        $investmentGrowth = [];


        foreach ($portfolio as $investments) {
            $investment = $investments->first();
            $latestSharePrice = $investment->startup->sharePrices->first()->price ?? $investment->purchase_price;
            $currentValue = $investment->shares * $latestSharePrice;

            $sectorId = $investment->startup->sector->id;
            $sectorName = $investment->startup->sector->name;
            $startupId = $investment->startup->id;
            $startupName = $investment->startup->brand_name;
            $investmentAmount = $investment->investment_amount;
            $startupLogo = $investment->startup->details->logo;

            if (!isset($sectorArray[$sectorId])) {
                $sectorArray[$sectorId] = [
                    'id' => $sectorId,
                    'name' => $sectorName,
                    'total_investment' => 0,
                    'startups' => []
                ];
            }

            $sectorArray[$sectorId]['startups'][] = [
                'startup_id' => $startupId,
                'startup_name' => $startupName,
                'investment_amount' => $investmentAmount,
                'current_value' => $currentValue,
                'purchase_price' => $investment->purchase_price,
                'shares' => (int)$investment->shares,
                'created_at' => $investment->created_at
            ];
            $sectorArray[$sectorId]['total_investment'] += $investmentAmount;

            if (!isset($investmentGrowth[$startupId])) {
                $investmentGrowth[$startupId] = [
                    'startup_id' => $startupId,
                    'startup_name' => $startupName,
                    'total_invested_amount' => 0,
                    'current_value' => $currentValue,
                    'logo' => $startupLogo,
                ];
            }

            $investmentGrowth[$startupId]['total_invested_amount'] += $investmentAmount;
            $investmentGrowth[$startupId]['current_value'] = $currentValue;
        }

        // Timely Investment
        $monthlyInvestments = [];
        $quarterlyInvestments = [];
        $quartersList = DateTimeHelper::getLast6QuartersDates();
        foreach ($quartersList as $qkey => $qvalue) {
            $ptotalInvestment = PrimaryTransactionModel::where('investor_id', $user->id)->where('status', '>', '6')->whereBetween('created_at', [$qvalue['start'], $qvalue['end']])
                ->sum('investment_amount');
            $stotalInvestment = SecondaryTransactionModel::where('buyer_id', $user->id)->where('status', '>', '5')->whereBetween('created_at', [$qvalue['start'], $qvalue['end']])
                ->sum('investment_amount');

            $quarterlyInvestments[] = [
                'quater'           => $qvalue['quater'],
                'start'            => $qvalue['start'],
                'end'              => $qvalue['end'],
                'total_investment' => $ptotalInvestment + $stotalInvestment,
            ];
        }

        $monthsList = DateTimeHelper::getLast6Months();
        foreach ($monthsList as $singMonth) {
            $ptotalInvestment = PrimaryTransactionModel::where('investor_id', $user->id)->where('status', '>', '6')->whereBetween('created_at', [$singMonth['start'], $singMonth['end']])
                ->sum('investment_amount');
            $stotalInvestment = SecondaryTransactionModel::where('buyer_id', $user->id)->where('status', '>', '5')->whereBetween('created_at', [$singMonth['start'], $singMonth['end']])
                ->sum('investment_amount');

            $monthlyInvestments[] = [
                'month'            => $singMonth['month'],
                'start'            => $singMonth['start'],
                'end'              => $singMonth['end'],
                'total_investment' => $ptotalInvestment + $stotalInvestment,
            ];
        }

        // $monthlyInvestments = array_reverse($monthlyInvestments);

        $investmentList = $portfolio->map(function ($investments) {
            $investment = $investments->first();
            $latestSharePrice = $investment->startup->sharePrices->first()->price ?? $investment->purchase_price;
            $currentValue = $investment->shares * $latestSharePrice;
            return [
                'logo' => $investment->startup->details->logo,
                'startup_id' => $investment->startup->id,
                'startup_name' => $investment->startup->brand_name,
                'shares' => (int) $investment->shares,
                'purchase_price' => $investment->purchase_price,
                'investment_amount' => $investment->investment_amount,
                'current_value' => $currentValue,
                'created_at' => $investment->created_at
            ];
        })->values()->toArray();

        return [
            'statistics' => [
                'total_startup_invested' => $totalStartupsInvested,
                'total_investment_amount' => $totalInvestmentAmount,
                'profit_booked' => $profitBooked,
                'current_portfolio_value' => $currentPortfolioValue,
                'list' => $investmentList,
            ],
            'pending_tasks' => [
                'ssa_sign' => PrimaryTransactionModel::where('investor_id', $user->id)->where('status', 2)->count(),
                'offer_sign' => PrimaryTransactionModel::where('investor_id', $user->id)->where('status', 5)->count(),
                'sha_sign' => PrimaryTransactionModel::where('investor_id', $user->id)->where('status', 9)->count(),
                'fund_transfer' => PrimaryTransactionModel::where('investor_id', $user->id)->where('status', 6)->count()
            ],
            'mis' => $latestMisEntries,
            'sectors' => array_values($sectorArray),
            'investment_growth' => array_values($investmentGrowth),
            'average_ticket_size' => PortfolioModel::where('investor_id', $user->id)->avg('purchase_price'),
            'investments' => [
                'monthly' => array_reverse($monthlyInvestments),
                'quarterly' => $quarterlyInvestments
            ]
        ];
    }


    function preIpoDashboard(): array
    {
        $request = request();
        $user = $request->user();

        $portfolio = PortfolioPreIpoModel::with([
            'investor',
            'company.fundamentals',
            'company.sharePrices' => function ($query) {
                $query->orderByDesc('date')->limit(1);
            },
            'company.sector'
        ])
            ->where('investor_id', $user->id)
            ->whereHas('company', function ($query) {
                $query->where('category', '!=', \App\Enums\PreIpoCategoryEnum::listed->value);
            })
            ->get()
            ->groupBy('company_id');

        $currentPortfolioValue = $portfolio->sum(function ($investments) {
            $investment = $investments->first();
            $latestSharePrice = $investment->company->sharePrices->first()->price ?? $investment->share_price;
            return $investment->shares * $latestSharePrice;
        });

        $totalStartupsInvested = $portfolio->count();
        // $totalInvestmentAmount = $portfolio->sum('investment_amount');
        $totalInvestmentAmount = $portfolio->sum(function ($investments) {
            return $investments->sum('investment_amount');
        });
        $profitBooked = 0;

        $endDate = now();
        $startDate = $endDate->copy()->subMonths(6);

        $sectorArray = [];
        $investmentGrowth = [];

        foreach ($portfolio as $investments) {
            $investment = $investments->first();
            $latestSharePrice = $investment->company->sharePrices->first()->price ?? $investment->share_price;
            $currentValue = $investment->shares * $latestSharePrice;

            $sectorId = $investment->company->sector->id;
            $sectorName = $investment->company->sector->name;
            $startupId = $investment->company->id;
            $startupName = $investment->company->brand_name;
            $investmentAmount = $investment->investment_amount;
            $startupLogo = $investment->company->logo;

            if (!isset($sectorArray[$sectorId])) {
                $sectorArray[$sectorId] = [
                    'id' => $sectorId,
                    'name' => $sectorName,
                    'total_investment' => 0,
                    'startups' => []
                ];
            }

            $sectorArray[$sectorId]['startups'][] = [
                'startup_id' => $startupId,
                'startup_name' => $startupName,
                'investment_amount' => $investmentAmount,
                'current_value' => $currentValue,
                'purchase_price' => $investment->share_price,
                'shares' => $investment->shares,
                'created_at' => $investment->created_at
            ];
            $sectorArray[$sectorId]['total_investment'] += $investmentAmount;

            if (!isset($investmentGrowth[$startupId])) {
                $investmentGrowth[$startupId] = [
                    'startup_id' => $startupId,
                    'startup_name' => $startupName,
                    'total_invested_amount' => 0,
                    'current_value' => $currentValue,
                    'logo' => $startupLogo,
                ];
            }

            $investmentGrowth[$startupId]['total_invested_amount'] += $investmentAmount;
            $investmentGrowth[$startupId]['current_value'] = $currentValue;
        }

        $monthlyInvestments = [];
        $quarterlyInvestments = [];
        $quartersList = DateTimeHelper::getLast6QuartersDates();
        foreach ($quartersList as $qkey => $qvalue) {
            $ptotalInvestment = PreIpoModel::where('investor_id', $user->id)->where('status', '5')->whereBetween('created_at', [$qvalue['start'], $qvalue['end']])
                ->sum('investment_amount');

            $quarterlyInvestments[] = [
                'quater'           => $qvalue['quater'],
                'start'            => $qvalue['start'],
                'end'              => $qvalue['end'],
                'total_investment' => $ptotalInvestment,
            ];
        }

        $monthsList = DateTimeHelper::getLast6Months();
        foreach ($monthsList as $singMonth) {
            $ptotalInvestment = PrimaryTransactionModel::where('investor_id', $user->id)->where('status', '5')->whereBetween('created_at', [$singMonth['start'], $singMonth['end']])
                ->sum('investment_amount');

            $monthlyInvestments[] = [
                'month'            => $singMonth['month'],
                'start'            => $singMonth['start'],
                'end'              => $singMonth['end'],
                'total_investment' => $ptotalInvestment,
            ];
        }

        $investmentList = $portfolio->map(function ($investments) {
            $investment = $investments->first();
            $latestSharePrice = $investment->company->sharePrices->first()->price ?? $investment->share_price;
            $currentValue = $investment->shares * $latestSharePrice;
            return [
                'logo' => $investment->company->logo,
                'startup_id' => $investment->company->id,
                'startup_name' => $investment->company->brand_name,
                'shares' => $investment->shares,
                'purchase_price' => $investment->share_price,
                'investment_amount' => $investment->investment_amount,
                'current_value' => $currentValue,
                'created_at' => $investment->created_at
            ];
        })->values()->toArray();

        return [
            'statistics' => [
                'total_startup_invested' => $totalStartupsInvested,
                'total_investment_amount' => $totalInvestmentAmount,
                'profit_booked' => $profitBooked,
                'current_portfolio_value' => $currentPortfolioValue,
                'list' => $investmentList,
            ],
            'pending_tasks' => [
                'share_transfer'    => 0,
                'fund_transfer'     => 0
            ],
            'sectors' => array_values($sectorArray),
            'investment_growth' => array_values($investmentGrowth),
            'average_ticket_size' => PreIpoModel::where('investor_id', $user->id)->where('status', '1')->avg('investment_amount'),
            'investments' => [
                'monthly'   => array_reverse($monthlyInvestments),
                'quarterly' => $quarterlyInvestments
            ]
        ];
    }

    public function investorList(): JsonResponse|Builder
    {
        $request = request();
        if ($request->routeIs('*.business.investor.*')) {

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

            $partners = PartnerModel::select('id')->where('parent_id', $partner->id)->where('type', PartnerTypeEnum::relationmanager->value)->pluck('id');
            $partners->push($partner->id);

            $investorQuery = InvestorModel::wherein('partner_id', $partners)->where('is_self', 0);
            if ($request->filled('relation_manager_ids')) {
                $investorQuery = InvestorModel::wherein('partner_id', explode(',', $request->relation_manager_ids))->where('is_self', 0);
            }


            if ($request->is_kyc !== 'All') {
                $investorQuery->where('kyc_status', $request->is_kyc === 'Yes' ? 1 : 0);
            }
            if ($request->is_aif !== 'All') {
                $investorQuery->where('aif_status', $request->is_aif === 'Yes' ? 1 : 0);
            }
            if ($request->is_active !== 'All') {
                $investorQuery->where('is_active', $request->is_active === 'Yes' ? 1 : 0);
            }


            $investordata = $investorQuery->where('is_deleted', '0')
                ->with([
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
                ])
                ->get()->map(function ($investor) use ($commissionRate) {
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

                    $partnerDetails = [
                        'partner_id' => $investor->partner->id ?? null,
                        'partner_name' => $investor->partner->name ?? 'Unknown',
                    ];

                    $investor->total_invested = $totalInvested;
                    $investor->no_of_startups = $noOfStartups;
                    $investor->commission_earned = $commissionEarned;
                    $investor->startup_list = $startupList;
                    $investor->partner_details = $partnerDetails;

                    return $investor;
                });

            return UtillsHelper::json(1, [
                'message' => 'Investor List',
                'data' => $investordata,
            ]);
        }

        $rules['parent_investor_id'] = ['required'];

        $validation = Validator::make($request->all(), $rules);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $familyMembers = InvestorModel::where('parent_investor_id', $request->parent_investor_id)
            ->where('is_deleted', '0')->with('relation', 'city', 'state', 'country')
            ->get();

        return UtillsHelper::json(1, ['message' => 'Investor List for investors', 'data' => $familyMembers]);
    }

    function investorSave(): JsonResponse|RedirectResponse
    {
        $request = request();
        // To get uuid or check this is edit reqquest or create request
        $uuid = $request->route('uuid') ?? false;
        if ($request->has('investor_id')) {
            $investor = InvestorModel::find($request->input('investor_id'));
            if (!$investor) {
                return UtillsHelper::json(0, ['message' => 'Investor Not found']);
            }
            $uuid = $investor->uuid;
        }
        // To get uuid or check this is edit reqquest or create request

        $request->merge([
            'is_primary_access' => $request->boolean('is_primary_access'),
            'is_secondary_access' => $request->boolean('is_secondary_access'),
            'is_preipo_access' => $request->boolean('is_preipo_access'),
        ]);

        if ($request->routeIs('*.investor.auth.register.*')) {
            $request->merge([
                'is_preipo_access' => 1,
                'investor_type' => InvestorTypeEnum::individual->value,
            ]);
        }

        // dd($request->all());


        // Define validation rules
        $rules = [
            'investor_type' => ['required', Rule::enum(InvestorTypeEnum::class)],
            'name' => 'required|string|max:255',
            'mobile_number' => [
                'required',
                'numeric',
                // 'digits:10',
                Rule::unique((new InvestorModel)->getTable())->where(function ($query) use ($uuid) {
                    return $uuid
                        ? $query->where('is_deleted', '0')->where('registration_step', '3')->where('uuid', '!=', $uuid)
                        : $query->where('is_deleted', '0')->where('registration_step', '3');
                }),
            ],
            'email' => [
                'required',
                'email',
                Rule::unique((new InvestorModel)->getTable())->where(function ($query) use ($uuid) {
                    return $uuid
                        ? $query->where('is_deleted', '0')->where('registration_step', '3')->where('uuid', '!=', $uuid)
                        : $query->where('is_deleted', '0')->where('registration_step', '3');
                }),
            ],
            // 'mobile_country_code' => 'required',
            'address' => 'required|string',
            'city_id' => 'required',
            'is_primary_access' => 'required|boolean',
            'is_secondary_access' => 'required|boolean',
            'is_preipo_access' => 'required|boolean',
            'pincode' => 'required|numeric|digits:6',
            'gender' => ['nullable', Rule::enum(GenderEnum::class)],
            'profile_photo' => 'nullable|file|mimes:' . CommonHelper::appSettings('file_image_extensions_allowed') . '|max:' . UtillsHelper::maxFileImageSizeInKB(),
            'profile_path' => 'nullable|string|max:255',
        ];

        if ($request->routeIs('admin.investor.*') || $request->routeIs('*.investor.auth.register.*')) {
            $rules['city_id']               = 'nullable';
            $rules['pincode']               = 'nullable|numeric|digits:6';
            $rules['address']               = 'nullable|string';
            $rules['parent_investor_id']    = 'nullable';
            $rules['parent_investor_type']  = 'required_with:parent_investor_id';
        }

        $isUpdate = $request->has('investor_id') || $request->route('uuid');

        if (
            ($request->routeIs('*.investor.family.create')
                || $request->routeIs('admin.investor.store')
                || $request->routeIs('*.business.investor.create'))
            && !$isUpdate
        ) {
            $rules['password'] = 'required';
        } else {
            $rules['password'] = 'nullable';
        }


        if ($request->routeIs('*.investor.family.create')) {
            $rules['relation_id'] = 'required';
        }

        if (!$request->routeIs('*.business.investor.create')) {
            $rules['mobile_country_code']               = 'required';
        }

        $validator = Validator::make($request->all(), $rules, [
            'relation_id.required' => 'Relation with you is required',
            'parent_investor_type.required_with' => 'The parent investor type is required when a parent investor is selected.',
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($request->routeIs('admin.investor.store') || $request->routeIs('admin.investor.update')) {
                $accessFields = [
                    $request->input('is_primary_access'),
                    $request->input('is_secondary_access'),
                    $request->input('is_preipo_access'),
                ];

                if (!in_array(true, $accessFields, true)) {
                    $validator->errors()->add(
                        'permission',
                        'At least one of Primary Startup, Secondary Startup, or Pre-IPO Companies permission must be selected.'
                    );
                }
            }
        });

        if ($validator->fails()) {
            if ($request->is('api/*')) {
                return UtillsHelper::json(0, ['message' => $validator->errors()->first()]);
            }

            return redirect()->back()
                ->withInput()
                ->withErrors($validator)
                ->with('error', 'Check form errors');
        }

        $city = MasterCityModel::where('id', $request->city_id)->first();

        if ($uuid) {
            $investor = InvestorModel::where('uuid', $uuid)->first();
            if (!$investor) {
                return UtillsHelper::json(0, ['message' => 'Investor Not found']);
            }
        } else {
            $investor = InvestorModel::where('mobile_number', $request->mobile_number)->where('mobile_country_code', $request->mobile_country_code)->where('registration_step', '!=', 3)->where('is_deleted', 0)->first();
            if (!$investor) {
                $investor = new InvestorModel();
            }
        }
        $investor->investor_type = $request->investor_type;
        $investor->name = ucfirst(trim($request->name));
        $investor->mobile_number = $request->mobile_number;
        $investor->mobile_country_code = $request->mobile_country_code ?? 91;

        $investor->email = strtolower(trim($request->email));
        $investor->address = $request->address;
        if ($city) {
            $investor->city_id = $request->city_id;
            $investor->state_id = $city->state_id;
            $investor->country_id = $city->country_id;
        }
        $investor->pincode = $request->pincode;
        $investor->gender = $request->gender;
        if ($request->has('password') && $request->password != NULL) {
            $investor->password = Hash::make($request->password);
            if ($request->routeIs('admin.investor.*')) {
                $investor->ask_password_change = 1;
            }
        }
        if ($request->hasFile('profile_photo')) {
            $investor->profile_photo = FileUpDownHelper::investor_profile_photo_upload($request->file('profile_photo'));
        } else if ($request->has('profile_path')) {
            $investor->profile_photo = $request->profile_path;
        } else {
            // if ($request->gender == GenderEnum::male->value) {
            //     $investor->profile_photo = 'master/pages/avtar/image/1741785351.7638-yFfHHXbfX4l5gjz6khWzRINGvcUOmWo2hkmgqRkKYSECHH8HVAUnMPaG5anv.png';
            // } else {
            //     $investor->profile_photo = 'master/pages/avtar/image/1741785344.5946-xmAO1G6OY8gL0hYB2wSZCZTnWTaLvckBaTPO8qvAeLW944O9tcxdarETFa7h.png';
            // }
        }



        if ($request->routeIs('*.investor.auth.register.*')) {
            $investor->registration_step = '1';
        } else {
            $investor->registration_step = '3';
        }

        $investor->is_primary_access = $request->is_primary_access ? 1 : 0;
        $investor->is_secondary_access = $request->is_secondary_access ? 1 : 0;
        $investor->is_preipo_access = $request->is_preipo_access ? 1 : 0;

        if ($request->routeIs('*.investor.family.create')) {
            $investor->family_relation_id = $request->relation_id;
            $investor->parent_investor_id = $request->user()->id;
            $investor->is_primary_access = $request->user()->is_primary_access ? 1 : 0;
            $investor->is_secondary_access = $request->user()->is_secondary_access ? 1 : 0;
            $investor->is_preipo_access = $request->user()->is_preipo_access ? 1 : 0;
        }

        if ($request->routeIs('*.business.investor.create')) {
            $investor->partner_id = $request->user()->id;
            $investor->created_by = $request->user()->created_by;
            $investor->updated_by = $request->user()->created_by;
            $investor->is_primary_access = $request->user()->is_primary_access ? 1 : 0;
            $investor->is_secondary_access = $request->user()->is_secondary_access ? 1 : 0;
            $investor->is_preipo_access = $request->user()->is_preipo_access ? 1 : 0;
        }

        $investor->save();

        if ($request->routeIs('admin.investor.store') || $request->routeIs('admin.investor.update')) {
            $investor->partner_id = $request->filled('partner_id') ? $request->partner_id : NULL;

            if ($request->has('parent_investor_id')) {
                $investor->parent_investor_id = $request->parent_investor_id;
                $investor->family_relation_id = $request->parent_investor_type;
            }
        }

        if ($request->routeIs('admin.investor.store')) {
            $investor->created_by = Auth::guard('admin')->user()->id;
            $investor->updated_by = Auth::guard('admin')->user()->id;
            UtillsHelper::sendWpMessage(
                NotificationTypeEnum::event,
                'investor_registeration_from_admin',
                WpMessageTypeEnum::text,
                $investor->mobile_number,
                $investor->name,
                NULL,
                ['https://play.google.com/store/apps/details?id=com.shuruup.investor', 'https://apps.apple.com/in/app/shuru-up/id6736905561'],
                [$investor->name,  $investor->mobile_number, $request->password],
                [$investor->id],
                NULL,
                true,
                $investor->id,
                InvestorModel::class,
                $investor->mobile_country_code
            );
            AdminHelper::logPut('Created Investor', InvestorModel::class, $investor->id);
        }
        if ($request->routeIs('admin.investor.update')) {
            $investor->updated_by = Auth::guard('admin')->user()->id;
            if ($request->has('password') && $request->password != NULL) {
                UtillsHelper::sendWpMessage(
                    NotificationTypeEnum::event,
                    'investor_pasword_change_from_admin',
                    WpMessageTypeEnum::text,
                    $investor->mobile_number,
                    $investor->name,
                    NULL,
                    ['https://play.google.com/store/apps/details?id=com.shuruup.investor', 'https://apps.apple.com/in/app/shuru-up/id6736905561'],
                    [$investor->name,  $investor->mobile_number, $request->password],
                    [$investor->id],
                    NULL,
                    true,
                    $investor->id,
                    InvestorModel::class,
                    $investor->mobile_country_code
                );
            }
            AdminHelper::logPut('Update Investor', InvestorModel::class, $investor->id);
        }

        $investor->save();
        if ($request->routeIs('*.investor.auth.register.*')) {
            // dd($investor->mobile_country_code);
            UtillsHelper::sendVerificationCode(
                $investor->id,
                InvestorModel::class,
                $investor->mobile_number,
                CodeVerificationTypeEnum::register,
                $investor->mobile_country_code
            );
        }
        if (!$request->routeIs('*.investor.auth.register.*')) {
            $message = 'Investor Created';
            if ($uuid) {
                $message = 'Investor Updated';
            }
        } else {
            $message = 'Verification code sent to +' . $investor->mobile_country_code . ' ' . $investor->mobile_number;
        }

        if ($request->routeIs('admin.*')) {
            if ($request->has('from_markasread') && $request->from_markasread == '1') {
                return redirect()->route('admin.reports.cms.requestaccess.pending')
                    ->with('success', $message);
            } else {
                return AdminHelper::investorRedirectToReleted($investor, $message);
            }
        } else {
            return UtillsHelper::json(1, ['message' => $message, 'data' => $investor]);
        }
    }

    function dematBank(): JsonResponse
    {
        $request = request();

        $validator = Validator::make($request->all(), [
            'dp_id'             => 'required',
            'client_id'         => 'required',
            'pan_no'            => 'required',
            'name'              => 'required',
            'account_number'    => 'nullable',
            'ifsc_code'         => 'nullable',
            'bank_name'         => 'nullable',
            'dob'               => 'nullable|date|date_format:d-m-Y',
            'cml_file'          => ['nullable', 'mimes:pdf', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()]
        ]);

        if ($validator->fails()) {
            return UtillsHelper::json(0, ['message' => $validator->errors()->first()]);
        }

        $investorId = $request->user()->id;
        $cmlFile = $request->file('cml_file');

        $data = [
            'dp_id' => $request->input('dp_id'),
            'client_id' => $request->input('client_id'),
            'pan_no' => $request->input('pan_no'),
            'name' => $request->input('name'),
            'account_number' => $request->input('account_number'),
            'ifsc_code' => $request->input('ifsc_code'),
            'bank_name' => $request->input('bank_name'),
            'dob' => $request->input('dob'),
        ];

        $kycService = new DematKycService();
        $result = $kycService->saveDematKyc($investorId, $data, $cmlFile);

        if ($result['success']) {
            return UtillsHelper::json(1, ['message' => $result['message']]);
        } else {
            return UtillsHelper::json(0, [
                'message' => $result['message'],
                'error' => $result['error'] ?? null
            ]);
        }
    }

    function registerInquiry(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'name'                  => 'required|max:250',
            'mobile_country_code'   => 'nullable|numeric',
            'mobile_number'         => 'required|max:10',
            'email'                 => 'nullable',
            'device'                => 'required|in:' . implode(',', array_column(DeviceTypeEnum::cases(), 'value')),
            'is_startup'            => 'nullable|boolean',
            'description'          => 'nullable',
        ], [], [
            'device'        => 'Device is required and must be one of the following types: ' . implode(',', array_column(DeviceTypeEnum::cases(), 'value'))
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        // $investor = InvestorModel::where('mobile_number', $request->mobile_number)->where('registration_step', 3)->where('is_deleted', 0)->first();
        // if ($investor) {
        //     return UtillsHelper::json(0, ['message' => 'Mobile no. already registered. Try Login in our application using your mobile no. reset your password if you forgot.']);
        // }

        $userId = CommonHelper::getUserFromSanctum();

        $mobile_country_code = $request->mobile_country_code ?? '91';
        $invReg = new InvestorRegisterRequestModel();
        $invReg->name = $request->name;
        $invReg->mobile_country_code = $mobile_country_code;
        $invReg->mobile_number = $request->mobile_number;
        $invReg->email = $request->email;
        $invReg->firm_name = $request->firm_name;
        $invReg->device = $request->device;
        if ($userId) {
            $invReg->user_id = $userId;
            $invReg->user_type = InvestorModel::class;
        }
        if ($request->is_startup) {
            $invReg->is_startup = 1;
            $invReg->description = $request->description ?? '';
        }
        if ($invReg->save()) {
            // UtillsHelper::sendWpMessage(NotificationTypeEnum::event, 'request_access_email', WpMessageTypeEnum::text, '6354901928', 'Swati', NULL, [], [$request->name]);
            RequestAccessJob::dispatch($invReg->id);
            return UtillsHelper::json(1, ['message' => 'Inquiry Sent Thankyou.']);
        } else {
            return UtillsHelper::json(0, ['message' => 'Failed to save record']);
        }

        //     $request = request();

        // // Validation
        //     $validation = Validator::make($request->all(), [
        //         'name'          => 'required|max:250',
        //         'mobile_number' => 'required|max:10',
        //         'email'         => 'required|max:250',
        //         'device'        => 'required|in:' . implode(',', array_column(DeviceTypeEnum::cases(), 'value')),
        //     ], [], [
        //         'device' => 'Device is required and must be one of the following types: ' . implode(',', array_column(DeviceTypeEnum::cases(), 'value')),
        //     ]);

        //     if ($validation->fails()) {
        //         return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        //     }

        //     // Check if mobile number exists for an already registered investor
        //     $investor = InvestorModel::where('mobile_number', $request->mobile_number)
        //         ->where('email', $request->email)
        //         ->where('is_deleted', 0)
        //         ->first();

        //     if ($investor) {
        //         return UtillsHelper::json(0, ['message' => 'Mobile no. already registered. Try Login in our application using your mobile no. reset your password if you forgot.']);
        //     }

        //     // Save new investor details in InvestorModel
        //     $investor = new InvestorModel();
        //     $investor->investor_type = $request->investor_type;
        //     $investor->name = $request->name;
        //     $investor->mobile_number = $request->mobile_number;
        //     $investor->email = $request->email;
        //     $investor->address = "N/A";
        //     $investor->investor_type = "Individual";
        //     $investor->password = Hash::make('PrivateDeals@123');
        //     $investor->registration_step = 3;
        //     $investor->ask_password_change = 1;

        //     if ($investor->save()) {
        //         // Save request in InvestorRegisterRequestModel
        //         $invReg = new InvestorRegisterRequestModel();
        //         $invReg->name = $request->name;
        //         $invReg->mobile_number = $request->mobile_number;
        //         $invReg->email = $request->email;
        //         $invReg->device = $request->device;

        //         if ($invReg->save()) {
        //             RequestAccessJob::dispatch($invReg->id);
        //             return UtillsHelper::json(1, ['message' => 'Inquiry Sent. Thank you.']);
        //         } else {
        //             return UtillsHelper::json(0, ['message' => 'Failed to save inquiry record']);
        //         }
        //     } else {
        //         return UtillsHelper::json(0, ['message' => 'Failed to save investor record']);
        //     }
    }
}
