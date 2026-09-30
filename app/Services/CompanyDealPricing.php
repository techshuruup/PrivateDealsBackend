<?php

namespace App\Services;

use App\Enums\CompanyDealTypeEnum;
use App\Helpers\CommonHelper;
use App\Jobs\preipo\CalcuatePricingAutoJob;
use App\Models\CompanyDealModel;
use App\Models\CompanySharePriceModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompanyDealPricing
{
    /**
     * The typed amount is base_price. share_price includes the admin processing fee.
     */
    public function stampFromBase(CompanyDealModel $deal, float|int|string $baseAmount): void
    {
        $fee = CommonHelper::processingFeePercentage();
        $base = round((float) $baseAmount, 2);
        $deal->base_price = $base;
        $deal->processing_fee_percentage = $fee;
        $deal->share_price = round($base * (1 + ($fee / 100)), 2);
    }

    /**
     * Upsert today's company_share_price from non-hot deals for each company,
     * then dispatch CalcuatePricingAutoJob once after commit.
     * Call only when this request inserted at least one non-hot deal.
     *
     * @param  list<int>  $companyIds
     */
    public function recordNonHotHistory(array $companyIds): void
    {
        $companyIds = array_values(array_unique(array_map('intval', $companyIds)));
        $companyIds = array_values(array_filter($companyIds, fn (int $id) => $id > 0));
        if ($companyIds === []) {
            return;
        }

        $today = now()->toDateString();
        foreach ($companyIds as $companyId) {
            $this->upsertToday($companyId, $today);
        }

        DB::afterCommit(function () {
            CalcuatePricingAutoJob::dispatch();
        });
    }

    private function upsertToday(int $companyId, string $today): void
    {
        $deals = CompanyDealModel::notDeleted()
            ->notExpired()
            ->where('company_id', $companyId)
            ->where('is_hot_deal', false)
            ->get(['deal_type', 'share_price', 'base_price']);

        $sells = $deals->filter(
            fn (CompanyDealModel $deal) => $deal->deal_type === CompanyDealTypeEnum::sell->value
        );
        $buys = $deals->filter(
            fn (CompanyDealModel $deal) => $deal->deal_type === CompanyDealTypeEnum::buy->value
        );

        $sellShare = $sells->isNotEmpty() ? (float) $sells->min('share_price') : null;
        $buyShare = $buys->isNotEmpty() ? (float) $buys->min('share_price') : null;

        if ($sellShare === null && $buyShare === null) {
            return;
        }

        $price = $sellShare ?? $buyShare;
        $distributerPrice = $buyShare ?? $sellShare;
        $basePrice = $this->minimumBase($sells) ?? $this->minimumBase($buys) ?? 0.0;

        $payload = [
            'price' => round((float) $price, 2),
            'distributer_price' => round((float) $distributerPrice, 2),
            'base_price' => round((float) $basePrice, 2),
        ];

        $existing = CompanySharePriceModel::query()
            ->where('company_id', $companyId)
            ->whereDate('date', $today)
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            $existing->fill($payload);
            $existing->save();

            return;
        }

        CompanySharePriceModel::create($payload + [
            'company_id' => $companyId,
            'date' => $today,
        ]);
    }

    /**
     * @param  Collection<int, CompanyDealModel>  $deals
     */
    private function minimumBase(Collection $deals): ?float
    {
        $values = $deals->pluck('base_price')->filter(
            fn ($value) => $value !== null && $value !== ''
        );
        if ($values->isEmpty()) {
            return null;
        }

        return (float) $values->min();
    }
}
