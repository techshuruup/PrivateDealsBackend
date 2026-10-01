<?php

namespace App\Http\Controllers;

use App\Enums\StartupPrimaryRoundStatusEnum;
use App\Enums\Utills\StatusEnum;
use App\Helpers\AdminHelper;
use App\Helpers\FileUpDownHelper;
use App\Helpers\SecondaryTransactionHelper;
use App\Jobs\backend\auth\RequestAceessApproveJob;
use App\Models\ApiLogModel;
use App\Models\CompanyDailySharePriceModel;
use App\Models\DynamicUrlModel;
use App\Models\InvestorModel;
use App\Models\InvestorRegisterRequestModel;
use App\Models\PartnerModel;
use App\Models\PreIpoModel;
use App\Models\PrimaryTransactionModel;
use App\Models\SecondaryExistingInvestorModel;
use App\Models\SecondarySellRequestModel;
use App\Models\SecondaryTransactionModel;
use App\Models\SellerMasterModel;
use App\Models\StartupModel;
use App\Models\StartupRoundModel;
use App\Models\UserAdminModel;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class DynamicUrlController extends Controller
{
    function index($token)
    {
        $token = DynamicUrlModel::where('token', $token)->where('expired', '0')->first();
        if (!$token) {
            abort(404);
        } else {


            // if ($token->token_type == 'secondary_oppotunity_approve') {
            //     $data = json_decode($token->values);
            //     $item = SecondaryTransactionModel::find($data->item_id);
            //     if ($item) {
            //         if ($item->status == 0) {
            //             $item->status = 1;
            //             $item->save();
            //             SecondaryTransactionHelper::changeOppotunityStatus($item);
            //         }
            //     }
            // }

            // if ($token->token_type == 'secondary_oppotunity_reject') {
            //     $data = json_decode($token->values);
            //     $item = SecondaryTransactionModel::find($data->item_id);
            //     if ($item) {
            //         if ($item->status == 0) {
            //             $item->status = 2;
            //             $item->save();
            //             SecondaryTransactionHelper::changeOppotunityStatus($item);
            //         }
            //     }
            // }

            // if ($token->token_type == 'admin_investor_portfolio_upload') {
            //     $data = json_decode($token->values);
            //     $admin = UserAdminModel::find($data->admin_id);
            //     if ($admin) {
            //         Auth::guard('admin')->loginUsingId($admin->id);
            //         if ($data->is_custom) {
            //             return redirect()->route('admin.portfolioInsights.portfolioUploadRequest', ['investor_key' => $data->investor_uuid]);
            //         } else {
            //             if ($data->type == 'startup') {
            //                 return redirect()->route('admin.primarytransactions.completed', ['investor_key' => $data->investor_uuid]);
            //             } else {
            //                 return redirect()->route('admin.preipotransaction.completed', ['investor_key' => $data->investor_uuid]);
            //             }
            //         }
            //     }
            // }

            // if ($token->token_type == 'admin_request_access_list') {
            //     $data = json_decode($token->values);
            //     $admin = UserAdminModel::where('uuid', $data->admin_uuid)->first();
            //     if ($admin) {
            //         Auth::guard('admin')->loginUsingId($admin->id);
            //         return redirect()->route('admin.reports.cms.requestaccess.pending');
            //     }
            // }

            if ($token->token_type == 'request_access_approve') {
                $data = json_decode($token->values);
                $entry = InvestorRegisterRequestModel::find($data->item_id);
                if ($entry && $entry->is_readed == 0) {

                    $admin = UserAdminModel::where('uuid', $data->admin_uuid)->first();
                    if ($admin) {
                        Auth::guard('admin')->loginUsingId($admin->id);
                        AdminHelper::logPut('Register request apprrove', InvestorRegisterRequestModel::class, $entry->id);
                    }
                    $entry->notes = 'Clicked from whatsapp';
                    // $entry->is_readed = 1;

                    $entry->is_converted = 1;

                    RequestAceessApproveJob::dispatch($entry->id);

                    $entry->save();
                }
                $token->expired = '1';
                $token->save();

                Session::flash('success', 'Request Collected and Processed Successfully');
            }

            if ($token->token_type === 'admin_accept_tran') {
                $data = json_decode($token->values);
                $transaction = PreIpoModel::find($data->item_id);
                Auth::guard('admin')->loginUsingId($data->admin_id);
                if (!$transaction) {
                    Session::flash('success', 'Cannot find the transaction.');
                } elseif ($transaction->usesOrderStep()) {
                    Session::flash('success', 'This order uses the partner order step flow.');
                    return redirect()->route('admin.preipotransaction.orderSteps');
                }

                if ($transaction->status === 0) {
                    return view('admin.pages.pre-ipo-transactions.whatsapp-action', [
                        'transaction' => $transaction,
                        'action'      => 'admin_accept_tran',
                        'token'       => $token->token,
                        'sellers'     => SellerMasterModel::where('is_deleted', '0')->get(),
                    ]);
                }

                // Already processed
                $adminName = $transaction->admin?->name ?? 'another admin';
                $message = match (true) {
                    $transaction->status >= 2 => "Transaction already approved by {$adminName}. No further action is required.",
                    $transaction->status === 1 => "Transaction already rejected by {$adminName}. No further action is required.",
                    default => 'Cannot find the transaction.',
                };

                $token->expired = '1';
                $token->save();
                Session::flash('success', $message);
                return redirect()->route('admin.preipotransaction.market');
            }

            if ($token->token_type === 'admin_reject_tran') {
                $data = json_decode($token->values);
                Auth::guard('admin')->loginUsingId($data->admin_id);
                $transaction = PreIpoModel::find($data->item_id);

                if (!$transaction) {
                    $token->expired = '1';
                    $token->save();
                    Session::flash('success', 'Cannot find the transaction.');
                    return redirect()->route('admin.preipotransaction.market');
                }

                if ($transaction->usesOrderStep()) {
                    Session::flash('success', 'This order uses the partner order step flow.');
                    return redirect()->route('admin.preipotransaction.orderSteps');
                }

                if ($transaction->status !== 0) {
                    $adminName = $transaction->admin?->name ?? 'another admin';
                    $message = match (true) {
                        $transaction->status >= 2 => "Transaction already approved by {$adminName}. No further action is required.",
                        $transaction->status === 1 => "Transaction already rejected by {$adminName}. No further action is required.",
                        default => 'Cannot find the transaction.',
                    };
                    $token->expired = '1';
                    $token->save();
                    Session::flash('success', $message);
                    return redirect()->route('admin.preipotransaction.market');
                }

                return view('admin.pages.pre-ipo-transactions.whatsapp-action', [
                    'transaction' => $transaction,
                    'token'       => $token->token,
                    'action' => 'cancel',
                ]);
            }

            return Redirect('/');
        }
    }

    function dashboard(): View
    {
        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();
        $today = Carbon::today();

        $managers = UserAdminModel::where('role', 'manager')
            ->where('is_deleted', '0')
            ->with([
                'investors' => function ($query) {
                    $query->where('is_demo', '0');
                },
                'investors.primaryTransactions' => function ($query) use ($currentMonthStart, $currentMonthEnd, $today) {
                    $query->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
                        ->orWhereDate('created_at', $today);
                },
                'investors.secondaryTransactions' => function ($query) use ($currentMonthStart, $currentMonthEnd, $today) {
                    $query->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
                        ->orWhereDate('created_at', $today);
                },
                'investors.perIpoTransactions' => function ($query) use ($currentMonthStart, $currentMonthEnd, $today) {
                    $query->where(function ($subQuery) use ($currentMonthStart, $currentMonthEnd, $today) {
                        $subQuery->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
                            ->orWhereDate('created_at', $today);
                    })->where('status', '>=', '2')->where('is_valid', 1);
                }
            ])
            ->get();

        $raisingNow = StartupModel::where('is_deleted', '0')
            ->where('registration_step', '6')
            ->whereHas('raising_round', function ($query) {
                $query->where('round_status', StartupPrimaryRoundStatusEnum::raisingnow);
            })
            ->with([
                'rounds' => function ($query) {
                    $query->where('round_status', StartupPrimaryRoundStatusEnum::raisingnow)
                        ->select('id', 'startup_id', 'round_status', 'fund_requirement');
                },
                'primary_transactions' => function ($query) {
                    $query->whereIn('round_id', function ($subQuery) {
                        $subQuery->select('id')
                            ->from('startup_round')
                            ->where('round_status', StartupPrimaryRoundStatusEnum::raisingnow);
                    })
                        ->whereHas('investor', function ($subQuery) {
                            $subQuery->where('is_demo', '0');
                        })
                        ->select(
                            'startup_id',
                            'round_id',
                            DB::raw('SUM(investment_amount) as total_investment')
                        )
                        ->groupBy('startup_id', 'round_id');
                }
            ])
            ->get()
            ->map(function ($startup) {
                $currentRound = $startup->rounds->first();

                $startup->fund_requirement = $currentRound ? $currentRound->fund_requirement : 0;

                $startup->investment_amount = $startup->primary_transactions
                    ->where('round_id', $currentRound->id)
                    ->first()
                    ?->total_investment ?? 0;

                $startup->percentage_completed = $startup->fund_requirement > 0
                    ? round(($startup->investment_amount / $startup->fund_requirement) * 100, 2)
                    : 0;

                return $startup;
            });

        // Calculate global totals from database (includes ALL investors, even without managers)
        $currentMonthPrimaryTotal = PrimaryTransactionModel::whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->whereHas('investor', function ($query) {
                $query->where('is_demo', '0');
            })
            ->sum('investment_amount');

        $currentMonthSecondaryTotal = SecondaryTransactionModel::whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->whereHas('investor', function ($query) {
                $query->where('is_demo', '0');
            })
            ->sum('investment_amount');

        $currentMonthPreIpoTotal = PreIpoModel::whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->where('status', '>=', '2')
            ->where('is_valid', 1)
            ->whereHas('investor', function ($query) {
                $query->where('is_demo', '0');
            })
            ->sum('investment_amount');

        // Calculate today flags
        $primaryMadeToday = PrimaryTransactionModel::whereDate('created_at', Carbon::today())
            ->whereHas('investor', function ($query) {
                $query->where('is_demo', '0');
            })
            ->exists();

        $secondaryMadeToday = SecondaryTransactionModel::whereDate('created_at', Carbon::today())
            ->whereHas('investor', function ($query) {
                $query->where('is_demo', '0');
            })
            ->exists();

        $preIpoMadeToday = PreIpoModel::whereDate('created_at', Carbon::today())
            ->where('status', '>=', '2')
            ->where('is_valid', 1)
            ->whereHas('investor', function ($query) {
                $query->where('is_demo', '0');
            })
            ->exists();

        // Prepare manager data
        $managerData = $managers->map(function ($manager) use (
            $currentMonthStart,
            $currentMonthEnd,
            $today
        ) {
            $primaryTotal = 0;
            $secondaryTotal = 0;
            $preIpoTotal = 0;

            $investments = [];
            $preIpoInvestments = [];

            $managerMadeToday = false;

            // Process the transactions for each investor under the current manager
            foreach ($manager->investors as $investor) {
                // Primary Transactions
                foreach ($investor->primaryTransactions as $transaction) {
                    $transactionDate = Carbon::parse($transaction->created_at);
                    $investmentAmount = $transaction->investment_amount;

                    if ($transactionDate->isToday()) {
                        $managerMadeToday = true;
                    }

                    if ($transactionDate->between($currentMonthStart, $currentMonthEnd)) {
                        $primaryTotal += $investmentAmount;

                        $startup = $transaction->startup;
                        if ($startup) {
                            $investments[$startup->id] = $investments[$startup->id] ?? [
                                'name' => $startup->brand_name,
                                'logo' => FileUpDownHelper::get_startup_logo_url($startup),
                                'amount' => 0,
                                'made_today' => false
                            ];

                            $investments[$startup->id]['amount'] += $investmentAmount;

                            if ($transactionDate->isToday()) {
                                $investments[$startup->id]['made_today'] = true;
                            }
                        }
                    }
                }

                // Secondary Transactions
                foreach ($investor->secondaryTransactions as $transaction) {
                    $transactionDate = Carbon::parse($transaction->created_at);
                    $investmentAmount = $transaction->investment_amount;

                    if ($transactionDate->isToday()) {
                        $managerMadeToday = true;
                    }

                    if ($transactionDate->between($currentMonthStart, $currentMonthEnd)) {
                        $secondaryTotal += $investmentAmount;

                        $startup = $transaction->startup;
                        if ($startup) {
                            $investments[$startup->id] = $investments[$startup->id] ?? [
                                'name' => $startup->brand_name,
                                'logo' => FileUpDownHelper::get_startup_logo_url($startup),
                                'amount' => 0,
                                'made_today' => false
                            ];

                            $investments[$startup->id]['amount'] += $investmentAmount;

                            if ($transactionDate->isToday()) {
                                $investments[$startup->id]['made_today'] = true;
                            }
                        }
                    }
                }

                // Per-IPO Transactions
                foreach ($investor->perIpoTransactions as $transaction) {
                    $transactionDate = Carbon::parse($transaction->created_at);
                    $investmentAmount = $transaction->investment_amount;

                    if ($transactionDate->isToday()) {
                        $managerMadeToday = true;
                    }

                    if ($transactionDate->between($currentMonthStart, $currentMonthEnd)) {
                        $preIpoTotal += $investmentAmount;

                        $company = $transaction->company;
                        if ($company) {
                            $preIpoInvestments[$company->id] = $preIpoInvestments[$company->id] ?? [
                                'name' => $company->brand_name,
                                'logo' => FileUpDownHelper::get_company_logo_url($company),
                                'amount' => 0,
                                'made_today' => false
                            ];

                            $preIpoInvestments[$company->id]['amount'] += $investmentAmount;

                            if ($transactionDate->isToday()) {
                                $preIpoInvestments[$company->id]['made_today'] = true;
                            }
                        }
                    }
                }
            }

            // Merge investments and pre-IPO investments
            $common = array_merge(array_values($investments), array_values($preIpoInvestments));

            $totalInvestment = $primaryTotal + $secondaryTotal + $preIpoTotal;

            return [
                'name' => $manager->name,
                'profile_photo' => $manager->profile_photo,
                'investor_count' => $manager->investors->count(),
                'total_primary' => $primaryTotal,
                'total_secondary' => $secondaryTotal,
                'total_preipo' => $preIpoTotal,
                'total_investment' => $totalInvestment,
                'investments' => $common,
                'investment_made_today' => $managerMadeToday
            ];
        });

        // Calculate all-time totals in a single query to minimize database hits
        $allTimeTotalPrimary = PrimaryTransactionModel::whereHas('investor', function ($query) {
            $query->where('is_demo', '0');
        })->sum('investment_amount');

        $allTimeTotalSecondary = SecondaryTransactionModel::whereHas('investor', function ($query) {
            $query->where('is_demo', '0');
        })->sum('investment_amount');

        $allTimeTotalPreIpo = PreIpoModel::whereHas('investor', function ($query) {
            $query->where('is_demo', '0');
        })->where('status', '>=', '2')->where('is_valid', 1)
            ->sum('investment_amount');

        // Sort manager data by total investment (high to low)
        $sortedManagerData = $managerData->sortByDesc('total_investment')->values();

        // Calculate current month total across all categories
        $currentMonthTotal = $currentMonthPrimaryTotal + $currentMonthSecondaryTotal + $currentMonthPreIpoTotal;

        // Prepare the final data array
        $demoInvestorIds = InvestorModel::where('is_demo', '1')->pluck('id')->toArray();
        $demoPartnerIds = PartnerModel::where('is_demo', '1')->pluck('id')->toArray();

        $totalActiveInvestors = ApiLogModel::where('usertype', 'investor')->wherenotin('userid', $demoInvestorIds)
            ->distinct('userid')->where('userid', '!=', '0')
            ->count('userid');
        $totalActivePartners = ApiLogModel::wherein('usertype', ['wealthManager', 'distributor'])->wherenotin('userid', $demoPartnerIds)
            ->distinct('userid')->where('userid', '!=', '0')
            ->count('userid');

        $totalActiveInvestorsToday = ApiLogModel::where('usertype', 'investor')
            ->whereNotIn('userid', $demoInvestorIds)
            ->whereDate('created_at', Carbon::today())
            ->whereNotNull('userid')
            ->where('userid', '!=', '')
            ->where('userid', '!=', '0')
            ->distinct('userid')
            ->count('userid');
        $totalActivePartnersToday = ApiLogModel::whereIn('usertype', ['wealthManager', 'distributor'])
            ->whereNotIn('userid', $demoPartnerIds)
            ->whereDate('created_at', Carbon::today())
            ->whereNotNull('userid')
            ->where('userid', '!=', '')
            ->where('userid', '!=', '0')
            ->whereIn('userid', function ($query) {
                $query->select('id')
                    ->from('partner')
                    ->where('name', '!=', 'N/A');
            })
            ->distinct('userid')
            ->count('userid');
        $totalGuestInvestorsToday = ApiLogModel::where('url', 'like', '%investor%')
            ->where('userid', '0')->orwhere('userid', NULL)
            ->whereDate('created_at', Carbon::today())
            ->count();

        $totalInvestor = InvestorModel::where('is_deleted', '0')
            ->where('is_demo', '0')
            ->count();

        $totalPartner = PartnerModel::where('is_deleted', '0')
            ->where('is_demo', '0')
            ->count();

        $activeInvestorsTodayNames = [];
        if ($totalActiveInvestorsToday > 0) {
            $activeInvestorsTodayNames = ApiLogModel::where('usertype', 'investor')
                ->whereNotIn('userid', $demoInvestorIds)
                ->whereDate('created_at', Carbon::today())
                ->get()
                ->map(function ($log) {
                    return [
                        'name' => optional($log->user)->name,
                        'version_code' => $log->version_code,
                    ];
                })
                ->filter(fn($item) => !empty($item['name']))
                ->unique('name')
                ->take(18)
                ->values()
                ->all();
        }

        $activePartnersTodayNames = [];
        if ($totalActivePartnersToday > 0) {
            $partnerIds = ApiLogModel::whereIn('usertype', ['wealthManager', 'distributor'])
                ->whereNotIn('userid', $demoPartnerIds)
                ->whereDate('created_at', Carbon::today())
                ->distinct('userid')
                ->limit(18)
                ->pluck('userid')
                ->toArray();

            $activePartnersTodayNames = PartnerModel::whereIn('id', $partnerIds)
                ->pluck('name')
                ->toArray();
        }

        $recentRegisteredInvestorsCount = InvestorModel::where('is_deleted', 0)
            ->whereDate('created_at', '>', '2025-06-09')->where('created_by', NULL)
            ->count();

        $priceFluctuation = CompanyDailySharePriceModel::getPriceFluctuationAlert();

        $allTimePrimaryCount = PrimaryTransactionModel::whereHas('investor', function ($query) {
            $query->where('is_demo', '0');
        })
            ->count();

        $allTimeSecondaryCount = SecondaryTransactionModel::whereHas('investor', function ($query) {
            $query->where('is_demo', '0');
        })
            ->count();

        $allTimePreIpoCount = PreIpoModel::whereHas('investor', function ($query) {
            $query->where('is_demo', '0');
        })
            ->where('status', '>=', '2')
            ->where('is_valid', 1)
            ->count();

        // ===== Current month valid transaction COUNTS (and amounts already exist above) =====
        $currentMonthPrimaryCount = PrimaryTransactionModel::whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->whereHas('investor', function ($query) {
                $query->where('is_demo', '0');
            })
            ->count();

        $currentMonthSecondaryCount = SecondaryTransactionModel::whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->whereHas('investor', function ($query) {
                $query->where('is_demo', '0');
            })
            ->count();

        $currentMonthPreIpoCount = PreIpoModel::whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->whereHas('investor', function ($query) {
                $query->where('is_demo', '0');
            })
            ->where('status', '>=', '2')
            ->where('is_valid', 1)
            ->count();

        // ===== Overall totals for ALL types =====
        $allTimeTotalCount = $allTimePrimaryCount + $allTimeSecondaryCount + $allTimePreIpoCount;
        $currentMonthTotalCount = $currentMonthPrimaryCount + $currentMonthSecondaryCount + $currentMonthPreIpoCount;

        // Get all investor IDs who have made valid primary transactions
        $primaryInvestorIds = PrimaryTransactionModel::whereHas('investor', function ($query) {
            $query->where('is_demo', '0');
        })
            ->distinct()
            ->pluck('investor_id')->toArray();

        // Get all investor IDs who have made valid secondary transactions (use buyer_id instead of investor_id)
        $secondaryInvestorIds = SecondaryTransactionModel::whereHas('investor', function ($query) {
            $query->where('is_demo', '0');
        })
            ->distinct()
            ->pluck('buyer_id')->toArray();

        // Get all investor IDs who have made valid pre-IPO transactions
        $preipoInvestorIds = PreIpoModel::whereHas('investor', function ($query) {
            $query->where('is_demo', '0');
        })
            ->where('status', '>=', '2')
            ->where('is_valid', 1)
            ->distinct()
            ->pluck('investor_id')->toArray();

        // Merge all to get unique active investor IDs who have done any valid transaction overall
        $allActiveInvestorIds = array_unique(array_merge($primaryInvestorIds, $secondaryInvestorIds, $preipoInvestorIds));

        $overallActiveInvestorCount = count($allActiveInvestorIds);

        // Fetch pending buy orders for the dashboard alert
        $pending_buy_orders = PreIpoModel::with(['investor', 'company'])
            ->where('status', '0')
            ->whereHas('investor', function ($query) {
                $query->where('is_demo', '0');
            })
            ->latest()
            ->get()
            ->map(function ($order) {
                return [
                    'investor_name'  => optional($order->investor)->name,
                    'investor_email' => optional($order->investor)->email,
                    'company_name'   => optional($order->company)->brand_name,
                    'amount'         => $order->investment_amount,
                    'created_at'     => $order->created_at,
                ];
            })->toArray();


        $data = [
            'pending_buy_orders' => $pending_buy_orders,
            'company_Price_updated' => CompanyDailySharePriceModel::where('date', Carbon::today())->exists(),
            'active_user_total' => $totalActiveInvestors,
            'active_user_today' => $totalActiveInvestorsToday,
            'total_user' => $totalInvestor,
            'active_partner_total' => $totalActivePartners,
            'active_partner_today' => $totalActivePartnersToday,
            'total_partner' => $totalPartner,
            'active_guest_total' => $totalGuestInvestorsToday,
            'request_access' => InvestorRegisterRequestModel::where('is_readed', '0')->count(),
            'request_access_all' => InvestorRegisterRequestModel::count(),
            'request_access_converted' => InvestorRegisterRequestModel::where('is_converted', '1')->count(),
            'request_access_today' => InvestorRegisterRequestModel::WhereDate('created_at', Carbon::today())->exists(),
            'pre_request_access' => PreIpoModel::where('status', '0')
                ->whereHas('investor', function ($query) {
                    $query->where('is_demo', '0');
                })->count(),
            'pre_request_access_all' => PreIpoModel::whereHas('investor', function ($query) {
                $query->where('is_demo', '0');
            })->where('is_valid', 1)->count(),
            'pre_request_access_today' => PreIpoModel::whereHas('investor', function ($query) {
                $query->where('is_demo', '0');
            })->whereDate('created_at', Carbon::today())->where('is_valid', 1)->exists(),
            'pre_request_access_older' => PreIpoModel::where('status', '0')
                ->whereHas('investor', function ($query) {
                    $query->where('is_demo', '0');
                })->whereDate('created_at', '<', Carbon::today())->where('is_valid', 1)->count(),
            'manager_list' => $sortedManagerData,
            'current_month_total' => $currentMonthTotal,
            'primary_total' => $allTimeTotalPrimary,
            'secondary_total' => $allTimeTotalSecondary,
            'preipo_total' => $allTimeTotalPreIpo,
            'primary_made_today' => $primaryMadeToday,
            'secondary_made_today' => $secondaryMadeToday,
            'preipo_made_today' => $preIpoMadeToday,
            'total_amount' => $allTimeTotalPrimary + $allTimeTotalSecondary + $allTimeTotalPreIpo,
            'flag_for_today' => ($primaryMadeToday || $secondaryMadeToday || $preIpoMadeToday),
            'raising_now_startups' => $raisingNow,
            'active_investors_today_names' => $activeInvestorsTodayNames,
            'active_partners_today_names' => $activePartnersTodayNames,
            'recent_registered_investors' => $recentRegisteredInvestorsCount,
            'price_fluctuation' => $priceFluctuation,
            'primary_total_count'     => $allTimePrimaryCount,
            'secondary_total_count'   => $allTimeSecondaryCount,
            'preipo_total_count'      => $allTimePreIpoCount,
            'current_month_count'     => $currentMonthTotalCount,
            'current_month_primary_count'   => $currentMonthPrimaryCount,
            'current_month_secondary_count' => $currentMonthSecondaryCount,
            'current_month_preipo_count'    => $currentMonthPreIpoCount,
            'total_amount'            => $allTimeTotalPrimary + $allTimeTotalSecondary + $allTimeTotalPreIpo,
            'total_count'             => $allTimeTotalCount,
            'overall_active_investor_count' => $overallActiveInvestorCount,
        ];

        return view('guest.dashboard', $data);
    }
}
