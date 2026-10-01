<?php

namespace App\Repositories\V2;

use App\Enums\CompanyApprovalStatusEnum;
use App\Enums\CompanyDealStatusEnum;
use App\Helpers\DateTimeHelper;
use App\Helpers\UtillsHelper;
use App\Models\CompanyDealModel;
use App\Models\CompanyModel;
use App\Models\PreIpoModel;
use App\Models\SellerCompanySharePriceModel;
use App\Models\SellerMasterModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SellerDashboardRepository
{
    public function dashboard(): JsonResponse
    {
        $seller = request()->user();
        if (!$seller instanceof SellerMasterModel) {
            return UtillsHelper::json(0, ['message' => 'Unauthorized']);
        }

        $sellerId = (int) $seller->id;

        $txBase = PreIpoModel::query()
            ->where('seller_id', $sellerId)
            ->whereHas('investor', fn ($q) => $q->where('is_deleted', 0));

        $pendingCount = (clone $txBase)
            ->whereNull('created_by')
            ->where('status', 0)
            ->count();

        $processingCount = (clone $txBase)
            ->where('status', '>', 1)
            ->where('status', '!=', 5)
            ->count();

        $completedCount = (clone $txBase)
            ->where('status', 5)
            ->count();

        $dealBase = CompanyDealModel::query()
            ->where('created_by_seller_id', $sellerId)
            ->where('is_deleted', 0);

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
            ->where('submitted_by_seller_id', $sellerId)
            ->where('approval_status', CompanyApprovalStatusEnum::pending->value)
            ->count();

        $totalCompanies = CompanyModel::query()
            ->where('is_deleted', 0)
            ->where('approval_status', CompanyApprovalStatusEnum::approved->value)
            ->count();

        $priceUploadedToday = (int) SellerCompanySharePriceModel::query()
            ->where('seller_id', $sellerId)
            ->whereDate('date', today())
            ->whereIn('company_id', function ($q) {
                $q->select('id')
                    ->from('company')
                    ->where('is_deleted', 0)
                    ->where('approval_status', CompanyApprovalStatusEnum::approved->value);
            })
            ->selectRaw('COUNT(DISTINCT company_id) as cnt')
            ->value('cnt');

        $priceNotUploadedToday = max(0, $totalCompanies - $priceUploadedToday);

        $monthly = [];
        foreach (DateTimeHelper::getLast6Months() as $period) {
            $row = (clone $txBase)
                ->where('status', 5)
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
            $row = (clone $txBase)
                ->where('status', 5)
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

        $topCompanies = (clone $txBase)
            ->where('status', 5)
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
            ->with(['company:id,brand_name,logo'])
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'status', 'investment_amount', 'company_id', 'created_at'])
            ->map(function (PreIpoModel $tx) {
                return [
                    'id' => (int) $tx->id,
                    'status' => (int) $tx->status,
                    'investment_amount' => (float) $tx->investment_amount,
                    'created_at' => optional($tx->created_at)?->toDateTimeString(),
                    'company' => $tx->company ? [
                        'id' => (int) $tx->company->id,
                        'brand_name' => $tx->company->brand_name,
                        'logo' => $tx->company->logo,
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
                    'is_expired' => $deal->is_expired,
                    'company' => $deal->company ? [
                        'id' => (int) $deal->company->id,
                        'brand_name' => $deal->company->brand_name,
                        'slug' => $deal->company->slug,
                        'logo' => $deal->company->logo,
                        'type' => $deal->company->type,
                    ] : null,
                ];
            })
            ->values();

        $mySubmissions = CompanyModel::query()
            ->where('is_deleted', 0)
            ->where('submitted_by_seller_id', $sellerId)
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
                    'is_primary_access' => (bool) $seller->is_primary_access,
                    'is_secondary_access' => (bool) $seller->is_secondary_access,
                    'is_preipo_access' => (bool) $seller->is_preipo_access,
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
