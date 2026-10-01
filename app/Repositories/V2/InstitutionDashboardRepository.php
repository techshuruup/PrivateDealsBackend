<?php

namespace App\Repositories\V2;

use App\Enums\CompanyApprovalStatusEnum;
use App\Enums\CompanyDealStatusEnum;
use App\Enums\PartnerTypeEnum;
use App\Enums\PreIpoOrderStepEnum;
use App\Helpers\DateTimeHelper;
use App\Helpers\UtillsHelper;
use App\Models\CompanyDealModel;
use App\Models\CompanyModel;
use App\Models\PartnerModel;
use App\Models\PreIpoModel;
use App\Services\PreIpoOrderStepService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InstitutionDashboardRepository
{
    public function __construct(private PreIpoOrderStepService $orders)
    {
    }

    public function dashboard(): JsonResponse
    {
        $partner = request()->user();
        if (!$partner instanceof PartnerModel) {
            return UtillsHelper::json(0, ['message' => 'Unauthorized']);
        }

        if ($partner->type !== PartnerTypeEnum::institution->value) {
            return UtillsHelper::json(0, [
                'message' => 'Only Institution partners can view this dashboard.',
            ]);
        }

        $partnerId = (int) $partner->id;

        $txBase = $this->orders->institutionQuery($partnerId);

        $pendingCount = (clone $txBase)
            ->where('order_step', PreIpoOrderStepEnum::share_confirmation_pending->value)
            ->count();

        $processingCount = (clone $txBase)
            ->whereIn('order_step', [
                PreIpoOrderStepEnum::deal_slip_pending->value,
                PreIpoOrderStepEnum::payment_pending->value,
                PreIpoOrderStepEnum::payment_confirmation_pending->value,
                PreIpoOrderStepEnum::share_transfer_pending->value,
                PreIpoOrderStepEnum::share_transfer_confirmation_pending->value,
            ])
            ->count();

        $completedCount = (clone $txBase)
            ->where('order_step', PreIpoOrderStepEnum::completed->value)
            ->count();

        $dealBase = CompanyDealModel::query()
            ->where('created_by_partner_id', $partnerId)
            ->where('is_deleted', 0)
            ->hot();

        $availableDeals = (clone $dealBase)
            ->where('status', CompanyDealStatusEnum::available->value)
            ->where(function ($q) {
                $q->whereNull('expired_at')
                    ->orWhere('expired_at', '>=', now());
            })
            ->count();

        $expiredDeals = (clone $dealBase)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<', now())
            ->count();

        $pendingApproval = CompanyModel::query()
            ->where('is_deleted', 0)
            ->where('submitted_by_partner_id', $partnerId)
            ->where('approval_status', CompanyApprovalStatusEnum::pending->value)
            ->count();

        $totalCompanies = CompanyModel::query()
            ->where('is_deleted', 0)
            ->where('approval_status', CompanyApprovalStatusEnum::approved->value)
            ->count();

        // Not seller_company_share_price. An Institution has no share-price upload.
        // A price upload is a non-deleted normal deal (is_hot_deal = false) this
        // Institution created today on an approved company. Hot deals do not count.
        // price_not_uploaded_today is the approved catalog minus that distinct count.
        $priceUploadedToday = (int) CompanyDealModel::query()
            ->where('created_by_partner_id', $partnerId)
            ->where('is_deleted', 0)
            ->where('is_hot_deal', false)
            ->whereDate('created_at', today())
            ->whereIn('company_id', function ($q) {
                $q->select('id')
                    ->from('company')
                    ->where('is_deleted', 0)
                    ->where('approval_status', CompanyApprovalStatusEnum::approved->value);
            })
            ->selectRaw('COUNT(DISTINCT company_id) as cnt')
            ->value('cnt');

        $priceNotUploadedToday = max(0, $totalCompanies - $priceUploadedToday);

        $completedTx = (clone $txBase)->where('order_step', PreIpoOrderStepEnum::completed->value);

        $monthly = [];
        foreach (DateTimeHelper::getLast6Months() as $period) {
            $row = (clone $completedTx)
                ->whereBetween('created_at', [$period['start'] . ' 00:00:00', $period['end'] . ' 23:59:59'])
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(investment_amount), 0) as amount')
                ->first();

            $monthly[] = [
                'month' => $period['month'],
                'start' => $period['start'],
                'end' => $period['end'],
                'count' => (int) ($row->cnt ?? 0),
                'amount' => (float) ($row->amount ?? 0),
            ];
        }

        $quarterly = [];
        foreach (DateTimeHelper::getLast6QuartersDates() as $period) {
            $row = (clone $completedTx)
                ->whereBetween('created_at', [$period['start'] . ' 00:00:00', $period['end'] . ' 23:59:59'])
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(investment_amount), 0) as amount')
                ->first();

            $quarterly[] = [
                'quater' => $period['quater'],
                'start' => $period['start'],
                'end' => $period['end'],
                'count' => (int) ($row->cnt ?? 0),
                'amount' => (float) ($row->amount ?? 0),
            ];
        }

        $topCompanies = (clone $completedTx)
            ->select(
                'company_id',
                DB::raw('COUNT(*) as completed_count'),
                DB::raw('COALESCE(SUM(investment_amount), 0) as completed_amount')
            )
            ->groupBy('company_id')
            ->orderByDesc('completed_amount')
            ->limit(5)
            ->get();

        $companyMap = CompanyModel::query()
            ->whereIn('id', $topCompanies->pluck('company_id')->filter()->all())
            ->get(['id', 'brand_name', 'logo'])
            ->keyBy('id');

        $topCompaniesFormatted = $topCompanies->map(function ($row) use ($companyMap) {
            $company = $companyMap->get($row->company_id);

            return [
                'company_id' => (int) $row->company_id,
                'brand_name' => $company?->brand_name,
                'logo' => $company?->logo,
                'completed_count' => (int) $row->completed_count,
                'completed_amount' => (float) $row->completed_amount,
            ];
        })->values();

        $recentTransactions = (clone $txBase)
            ->with(['company:id,brand_name,logo', 'investor:id,name'])
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'status', 'investment_amount', 'company_id', 'investor_id', 'order_step', 'created_at'])
            ->map(function (PreIpoModel $tx) {
                return [
                    'id' => (int) $tx->id,
                    'status' => (int) $tx->status,
                    'order_step' => $tx->order_step,
                    'investment_amount' => (float) $tx->investment_amount,
                    'created_at' => optional($tx->created_at)?->toDateTimeString(),
                    'company' => $tx->company ? [
                        'id' => (int) $tx->company->id,
                        'brand_name' => $tx->company->brand_name,
                        'logo' => $tx->company->logo,
                    ] : null,
                    'investor' => $tx->investor ? [
                        'id' => (int) $tx->investor->id,
                        'name' => $tx->investor->name,
                    ] : null,
                ];
            })
            ->values();

        $recentDeals = (clone $dealBase)
            ->hot()
            ->with(['company:id,brand_name,slug,logo,type'])
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function (CompanyDealModel $deal) {
                $dealType = $deal->deal_type instanceof \BackedEnum
                    ? $deal->deal_type->value
                    : $deal->deal_type;
                $status = $deal->status instanceof \BackedEnum
                    ? $deal->status->value
                    : $deal->status;

                return [
                    'uuid' => $deal->uuid,
                    'deal_type' => $dealType,
                    'status' => $status,
                    'share_price' => (float) $deal->share_price,
                    'available_quantity' => (int) $deal->available_quantity,
                    'is_hot_deal' => (bool) $deal->is_hot_deal,
                    'expired_at' => optional($deal->expired_at)?->format('Y-m-d H:i:s'),
                    'is_expired' => $deal->isExpired(),
                    'company' => $deal->company ? [
                        'id' => (int) $deal->company->id,
                        'brand_name' => $deal->company->brand_name,
                        'slug' => $deal->company->slug,
                        'logo' => $deal->company->logo,
                        'type' => $deal->company->type instanceof \BackedEnum
                            ? $deal->company->type->value
                            : $deal->company->type,
                    ] : null,
                ];
            })
            ->values();

        $mySubmissions = CompanyModel::query()
            ->where('is_deleted', 0)
            ->where('submitted_by_partner_id', $partnerId)
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'brand_name', 'approval_status', 'type', 'logo'])
            ->map(function (CompanyModel $company) {
                return [
                    'id' => (int) $company->id,
                    'brand_name' => $company->brand_name,
                    'approval_status' => $company->approval_status instanceof \BackedEnum
                        ? $company->approval_status->value
                        : $company->approval_status,
                    'type' => $company->type instanceof \BackedEnum
                        ? $company->type->value
                        : $company->type,
                    'logo' => $company->logo,
                ];
            })
            ->values();

        return UtillsHelper::json(1, [
            'message' => 'Dashboard',
            'data' => [
                'access' => [
                    'is_primary_access' => (bool) $partner->is_primary_access,
                    'is_secondary_access' => (bool) $partner->is_secondary_access,
                    'is_preipo_access' => (bool) $partner->is_preipo_access,
                ],
                'summary' => [
                    'transactions' => [
                        'pending' => $pendingCount,
                        'processing' => $processingCount,
                        'completed' => $completedCount,
                    ],
                    'deals' => [
                        'available' => $availableDeals,
                        'expired' => $expiredDeals,
                    ],
                    'companies' => [
                        'pending_approval' => $pendingApproval,
                        'total_companies' => $totalCompanies,
                        'price_uploaded_today' => $priceUploadedToday,
                        'price_not_uploaded_today' => $priceNotUploadedToday,
                    ],
                ],
                'charts' => [
                    'transaction_volume' => [
                        'monthly' => array_reverse($monthly),
                        'quarterly' => $quarterly,
                    ],
                    'transaction_status' => [
                        ['key' => 'pending', 'label' => 'Pending', 'count' => $pendingCount],
                        ['key' => 'processing', 'label' => 'Processing', 'count' => $processingCount],
                        ['key' => 'completed', 'label' => 'Completed', 'count' => $completedCount],
                    ],
                    'deals_by_status' => [
                        ['key' => 'available', 'count' => $availableDeals],
                        ['key' => 'expired', 'count' => $expiredDeals],
                    ],
                    'top_companies_by_completed_amount' => $topCompaniesFormatted,
                ],
                'recent' => [
                    'transactions' => $recentTransactions,
                    'deals' => $recentDeals,
                    'my_submissions' => $mySubmissions,
                ],
            ],
        ]);
    }
}
