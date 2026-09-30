<?php

namespace App\Services\AiAutowork;

use App\Enums\CompanyApprovalStatusEnum;
use App\Enums\CompanyTypeEnum;
use App\Enums\MinimumInvestmentTypeEnum;
use App\Enums\TempCompanyIntentEnum;
use App\Enums\TempCompanyStatusEnum;
use App\Helpers\AdminHelper;
use App\Helpers\CommonHelper;
use App\Models\CompanyCustomDataModel;
use App\Models\CompanyEventsModel;
use App\Models\CompanyFundamentalsModel;
use App\Models\CompanyModel;
use App\Models\CompanyPromotersModel;
use App\Models\CompanyShareHolderModel;
use App\Models\CompanyShareHolderPercentageModel;
use App\Models\TempCompanyModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PromoteTempCompanyService
{
    /**
     * @param  array<string, bool>  $replaceSections  e.g. promoters => true
     */
    public function approve(TempCompanyModel $temp, int $adminId, array $replaceSections = []): CompanyModel
    {
        if ($temp->status !== TempCompanyStatusEnum::pending) {
            throw ValidationException::withMessages(['status' => 'Only pending items can be approved.']);
        }

        $this->validateForApprove($temp);

        return DB::transaction(function () use ($temp, $adminId, $replaceSections) {
            if ($temp->intent === TempCompanyIntentEnum::update && $temp->matched_company_id) {
                $company = CompanyModel::where('id', $temp->matched_company_id)->where('is_deleted', 0)->firstOrFail();
                $this->fillCompany($company, $temp, false);
                $company->save();
                $this->upsertFundamentals($company, $temp);
                $this->syncChildren($company, $temp, $replaceSections, true);
            } else {
                $company = new CompanyModel();
                $this->fillCompany($company, $temp, true);
                $company->slug = AdminHelper::companySlug($temp->brand_name);
                $company->save();
                $this->upsertFundamentals($company, $temp);
                $this->syncChildren($company, $temp, $replaceSections, false);
            }

            $temp->status = TempCompanyStatusEnum::approved;
            $temp->matched_company_id = $company->id;
            $temp->reviewed_by = $adminId;
            $temp->reviewed_at = now();
            $temp->save();

            AdminHelper::logPut('AI AutoWork company approved', TempCompanyModel::class, $temp->id);

            return $company;
        });
    }

    public function reject(TempCompanyModel $temp, int $adminId, ?string $notes = null): void
    {
        if ($temp->status !== TempCompanyStatusEnum::pending) {
            throw ValidationException::withMessages(['status' => 'Only pending items can be rejected.']);
        }

        $temp->status = TempCompanyStatusEnum::rejected;
        $temp->admin_notes = $notes;
        $temp->reviewed_by = $adminId;
        $temp->reviewed_at = now();
        $temp->save();

        AdminHelper::logPut('AI AutoWork company rejected', TempCompanyModel::class, $temp->id);
    }

    public function validateForApprove(TempCompanyModel $temp): void
    {
        $fundamentals = is_array($temp->fundamentals) ? $temp->fundamentals : [];

        $data = [
            'cin' => $temp->cin,
            'brand_name' => $temp->brand_name,
            'company_name' => $temp->company_name,
            'about' => $temp->about,
            'sector_id' => $temp->sector_id,
            'min_investment_amount' => $temp->min_investment_amount,
            'commission' => $temp->commission,
            'processing_fee_percentage' => $temp->processing_fee_percentage ?? CommonHelper::processingFeePercentage(),
            'lot_size' => $fundamentals['lot_size'] ?? null,
            'fifty_two_week_high' => $fundamentals['fifty_two_week_high'] ?? 0,
            'fifty_two_week_low' => $fundamentals['fifty_two_week_low'] ?? 0,
            'depository' => $fundamentals['depository'] ?? null,
            'pan_number' => $fundamentals['pan_number'] ?? null,
            'isin_number' => $fundamentals['isin_number'] ?? null,
            'rta' => $fundamentals['rta'] ?? null,
            'market_cap' => $fundamentals['market_cap'] ?? 0,
            'pe_ratio' => $fundamentals['pe_ratio'] ?? 0,
            'pb_ratio' => $fundamentals['pb_ratio'] ?? 0,
            'debt_to_equity' => $fundamentals['debt_to_equity'] ?? 0,
            'roe' => $fundamentals['roe'] ?? 0,
            'book_value' => $fundamentals['book_value'] ?? 0,
            'face_value' => $fundamentals['face_value'] ?? 0,
            'total_shares' => $fundamentals['total_shares'] ?? 0,
        ];

        $validator = Validator::make($data, [
            'cin' => 'required|string',
            'brand_name' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'about' => 'required|string',
            'sector_id' => 'required',
            'min_investment_amount' => 'required|numeric',
            'commission' => 'required|numeric',
            'processing_fee_percentage' => 'required|numeric|between:0,100',
            'lot_size' => 'required',
            'fifty_two_week_high' => 'required|numeric|min:0',
            'fifty_two_week_low' => 'required|numeric|min:0',
            'depository' => 'required|string|max:255',
            'pan_number' => 'required|string|size:10',
            'isin_number' => 'required|string|size:12',
            'rta' => 'required|string|max:255',
            'market_cap' => 'required|numeric|min:0',
            'pe_ratio' => 'required|numeric',
            'pb_ratio' => 'required|numeric',
            'debt_to_equity' => 'required|numeric|min:0',
            'roe' => 'required|numeric',
            'book_value' => 'required|numeric|min:0',
            'face_value' => 'required|numeric|min:0',
            'total_shares' => 'required|numeric|min:0',
        ], [], [
            'cin' => 'CIN',
            'brand_name' => 'Brand name',
            'company_name' => 'Company name',
            'about' => 'About',
            'sector_id' => 'Sector',
            'min_investment_amount' => 'Min investment amount',
            'commission' => 'Commission',
            'processing_fee_percentage' => 'Processing fee %',
            'lot_size' => 'Lot size',
            'fifty_two_week_high' => '52 week high',
            'fifty_two_week_low' => '52 week low',
            'depository' => 'Depository',
            'pan_number' => 'PAN number',
            'isin_number' => 'ISIN number',
            'rta' => 'RTA',
            'market_cap' => 'Market cap',
            'pe_ratio' => 'PE ratio',
            'pb_ratio' => 'PB ratio',
            'debt_to_equity' => 'Debt to equity',
            'roe' => 'ROE',
            'book_value' => 'Book value',
            'face_value' => 'Face value',
            'total_shares' => 'Total shares',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    protected function fillCompany(CompanyModel $company, TempCompanyModel $temp, bool $isCreate): void
    {
        $company->brand_name = $temp->brand_name;
        $company->company_name = $temp->company_name;
        $company->cin = $temp->cin;
        $company->about = $temp->about;
        $company->keywords = $temp->keywords;
        $company->negative_keywords = $temp->negative_keywords;
        $company->alternative_names = $temp->alternative_names;
        $company->type = $temp->type ?: CompanyTypeEnum::unlisted->value;
        $company->is_drhp = $temp->is_drhp ? 1 : 0;
        $company->sector_id = $temp->sector_id;
        $company->final_min_investment_amount = $temp->min_investment_amount;
        $company->min_investment_type = MinimumInvestmentTypeEnum::quantity->value;
        $company->commission = $temp->commission;
        $company->processing_fee_percentage = $temp->processing_fee_percentage ?? CommonHelper::processingFeePercentage();
        if ($temp->logo) {
            $company->logo = $temp->logo;
        } elseif (filled($temp->logo_url)) {
            $downloaded = app(AiCompanyIngestService::class)->downloadLogoPublic((string) $temp->logo_url);
            if ($downloaded) {
                $company->logo = $downloaded;
            }
        }
        if ($isCreate) {
            $company->is_deleted = 0;
            $company->approval_status = CompanyApprovalStatusEnum::approved->value;
        } else {
            $company->slug = AdminHelper::companySlug($temp->brand_name, $company->id);
        }
    }

    protected function upsertFundamentals(CompanyModel $company, TempCompanyModel $temp): void
    {
        $f = is_array($temp->fundamentals) ? $temp->fundamentals : [];
        $row = CompanyFundamentalsModel::firstOrNew(['company_id' => $company->id]);
        $row->company_id = $company->id;
        $row->lot_size = $f['lot_size'] ?? $row->lot_size;
        $row->fifty_two_week_high = $f['fifty_two_week_high'] ?? $row->fifty_two_week_high ?? 0;
        $row->fifty_two_week_low = $f['fifty_two_week_low'] ?? $row->fifty_two_week_low ?? 0;
        $row->depository = $f['depository'] ?? $row->depository;
        $row->pan_number = $f['pan_number'] ?? $row->pan_number;
        $row->isin_number = $f['isin_number'] ?? $row->isin_number;
        $row->cin_number = $f['cin_number'] ?? $temp->cin ?? $row->cin_number;
        $row->rta = $f['rta'] ?? $row->rta;
        $row->market_cap = $f['market_cap'] ?? $row->market_cap ?? 0;
        $row->pe_ratio = $f['pe_ratio'] ?? $row->pe_ratio ?? 0;
        $row->pb_ratio = $f['pb_ratio'] ?? $row->pb_ratio ?? 0;
        $row->debt_to_equity = $f['debt_to_equity'] ?? $row->debt_to_equity ?? 0;
        $row->roe = $f['roe'] ?? $row->roe ?? 0;
        $row->book_value = $f['book_value'] ?? $row->book_value ?? 0;
        $row->face_value = $f['face_value'] ?? $row->face_value ?? 0;
        $row->total_shares = $f['total_shares'] ?? $row->total_shares ?? 0;
        $row->save();
    }

    /**
     * @param  array<string, bool>  $replaceSections
     */
    protected function syncChildren(CompanyModel $company, TempCompanyModel $temp, array $replaceSections, bool $isUpdate): void
    {
        if (!empty($temp->promoters) && (!$isUpdate || ($replaceSections['promoters'] ?? false))) {
            CompanyPromotersModel::where('company_id', $company->id)->delete();
            foreach ($temp->promoters as $p) {
                if (empty($p['name'])) {
                    continue;
                }
                CompanyPromotersModel::create([
                    'company_id' => $company->id,
                    'name' => $p['name'] ?? null,
                    'designation' => $p['designation'] ?? null,
                    'experience' => $p['experience'] ?? null,
                    'url' => $p['url'] ?? null,
                ]);
            }
        }

        if (!empty($temp->shareholders) && (!$isUpdate || ($replaceSections['shareholders'] ?? false))) {
            CompanyShareHolderModel::where('company_id', $company->id)->delete();
            CompanyShareHolderPercentageModel::where('company_id', $company->id)->delete();
            foreach ($temp->shareholders as $sh) {
                if (empty($sh['name'])) {
                    continue;
                }
                $holder = CompanyShareHolderModel::create([
                    'company_id' => $company->id,
                    'name' => $sh['name'],
                ]);
                foreach ($sh['percentages'] ?? [] as $pct) {
                    CompanyShareHolderPercentageModel::create([
                        'company_id' => $company->id,
                        'share_holder_id' => $holder->id,
                        'year' => $pct['year'] ?? null,
                        'percentage' => $pct['percentage'] ?? null,
                    ]);
                }
            }
        }

        if (!empty($temp->events) && (!$isUpdate || ($replaceSections['events'] ?? false))) {
            CompanyEventsModel::where('company_id', $company->id)->delete();
            foreach ($temp->events as $ev) {
                if (empty($ev['title'])) {
                    continue;
                }
                CompanyEventsModel::create([
                    'company_id' => $company->id,
                    'title' => $ev['title'] ?? null,
                    'description' => $ev['description'] ?? null,
                    'date' => $ev['date'] ?? null,
                    'file' => $ev['file'] ?? null,
                ]);
            }
        }

        if (!empty($temp->financials) && (!$isUpdate || ($replaceSections['financials'] ?? false))) {
            CompanyCustomDataModel::where('company_id', $company->id)->delete();
            foreach ($temp->financials as $fin) {
                if (empty($fin['label'])) {
                    continue;
                }
                $values = $fin['values'] ?? [];
                $matrix = \App\Helpers\CommonHelper::normalizeFinancialValuesToMatrix($values);
                if ($matrix === [] && (is_array($values) || is_string($values))) {
                    // keep original if we couldn't normalize (empty / unknown shape)
                    $encoded = is_string($values) ? $values : json_encode($values);
                } else {
                    $encoded = json_encode($matrix === [] ? new \stdClass() : $matrix);
                }
                CompanyCustomDataModel::create([
                    'company_id' => $company->id,
                    'label' => $fin['label'],
                    'values' => $encoded,
                ]);
            }
        }
    }
}
