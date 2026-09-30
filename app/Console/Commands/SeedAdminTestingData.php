<?php

namespace App\Console\Commands;

use App\Enums\CompanyApprovalStatusEnum;
use App\Enums\CompanyDealStatusEnum;
use App\Enums\CompanyDealTypeEnum;
use App\Enums\CompanyTypeEnum;
use App\Enums\GenderEnum;
use App\Enums\InvestorTypeEnum;
use App\Enums\PartnerTypeEnum;
use App\Models\CompanyDealModel;
use App\Models\CompanyModel;
use App\Models\CompanySharePriceModel;
use App\Models\InvestorDematAccountModel;
use App\Models\InvestorKycPanModel;
use App\Models\InvestorModel;
use App\Models\MasterSectorsModel;
use App\Models\PartnerModel;
use App\Models\UserAdminModel;
use App\Models\UserBankAccountModel;
use App\Repositories\PartnerRepository;
use App\Services\CompanyDealPricing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class SeedAdminTestingData extends Command
{
    protected $signature = 'testing:seed-admin';

    protected $description = 'Insert local admin test partners, investors, one institution company, and deals.';

    private const PASSWORD = 'Test@1234';

    public function handle(PartnerRepository $partners, CompanyDealPricing $pricing): int
    {
        if (! app()->environment('local')) {
            $this->error('Refusing to run: APP_ENV must be local.');

            return self::FAILURE;
        }

        if (PartnerModel::where('email', 'test.wm@privatedeals.test')->exists()) {
            $this->error('Test data already exists (test.wm@privatedeals.test).');

            return self::FAILURE;
        }

        $sectorId = MasterSectorsModel::where('is_deleted', '0')->orderBy('id')->value('id');
        if (! $sectorId) {
            $this->error('No active sector found. Add a sector before seeding.');

            return self::FAILURE;
        }

        $adminId = UserAdminModel::query()->orderBy('id')->value('id');
        $password = Hash::make(self::PASSWORD);

        try {
            $summary = DB::transaction(function () use ($partners, $pricing, $sectorId, $adminId, $password) {
                $wealthManager = $this->partner(
                    'Test Wealth Manager',
                    '9000000001',
                    'test.wm@privatedeals.test',
                    PartnerTypeEnum::wealthmanager->value,
                    10,
                    $password,
                    $adminId
                );
                $distributor = $this->partner(
                    'Test Distributor',
                    '9000000002',
                    'test.distributor@privatedeals.test',
                    PartnerTypeEnum::distributor->value,
                    6,
                    $password,
                    $adminId,
                    $wealthManager
                );
                $retailer = $this->partner(
                    'Test Retailer',
                    '9000000003',
                    'test.retailer@privatedeals.test',
                    PartnerTypeEnum::retailer->value,
                    4,
                    $password,
                    $adminId,
                    $distributor
                );
                $relationManager = $this->partner(
                    'Test Relation Manager',
                    '9000000004',
                    'test.rm@privatedeals.test',
                    PartnerTypeEnum::relationmanager->value,
                    null,
                    $password,
                    $adminId,
                    $wealthManager
                );
                $institution = $this->partner(
                    'Test Institution',
                    '9000000005',
                    'test.institution@privatedeals.test',
                    PartnerTypeEnum::institution->value,
                    3,
                    $password,
                    $adminId
                );

                foreach ([
                    [$wealthManager, 'EFGHI5678K', '50100234567001'],
                    [$distributor, 'FGHIJ6789L', '50100234567002'],
                    [$retailer, 'GHIJK7890M', '50100234567003'],
                    [$institution, 'HIJKL8901N', '50100234567004'],
                ] as [$partner, $pan, $accountNumber]) {
                    $partners->createSelfInvestor($partner);
                    $self = InvestorModel::where('partner_id', $partner->id)->where('is_self', 1)->first();
                    if ($self) {
                        $this->attachPanAndBank($self, $pan, $accountNumber);
                    }
                }

                $this->client($wealthManager, 'Test WM Client', '9000000011', 'test.wm.client@privatedeals.test', $password, $adminId, true, 'IN900001', '90000011', 'ABCDE1234F', '50100234567011');
                $this->client($wealthManager, 'Test Inactive Client', '9000000012', 'test.inactive.client@privatedeals.test', $password, $adminId, false, 'IN900002', '90000012', 'BCDEF2345G', '50100234567012');
                $this->pendingClient($wealthManager, 'Test Pending KYC', '9000000013', 'test.pending.kyc@privatedeals.test', $password, $adminId);
                $this->client($relationManager, 'Test RM Client', '9000000014', 'test.rm.client@privatedeals.test', $password, $adminId, true, 'IN900003', '90000013', 'CDEFG3456H', '50100234567013');
                $this->client($institution, 'Test Institution Client', '9000000015', 'test.institution.client@privatedeals.test', $password, $adminId, true, 'IN900004', '90000014', 'DEFGH4567J', '50100234567014');

                $approved = $this->company(
                    $institution,
                    $sectorId,
                    $adminId,
                    'Test Institution Co',
                    'Test Institution Company Private Limited',
                    'U74999MH2026PTC100001',
                    'test-institution-co',
                    CompanyTypeEnum::unlisted->value,
                    CompanyApprovalStatusEnum::approved->value
                );
                $pending = $this->company(
                    $institution,
                    $sectorId,
                    $adminId,
                    'Test Institution Pending',
                    'Test Institution Pending Private Limited',
                    'U74999MH2026PTC100002',
                    'test-institution-pending',
                    CompanyTypeEnum::secondary->value,
                    CompanyApprovalStatusEnum::pending->value
                );

                $deals = [
                    ['type' => CompanyDealTypeEnum::sell->value, 'base' => 100, 'qty' => 500, 'min' => 2, 'hot' => false],
                    ['type' => CompanyDealTypeEnum::sell->value, 'base' => 80, 'qty' => 200, 'min' => 1, 'hot' => false],
                    ['type' => CompanyDealTypeEnum::buy->value, 'base' => 50, 'qty' => 100, 'min' => 4, 'hot' => false],
                    ['type' => CompanyDealTypeEnum::sell->value, 'base' => 3, 'qty' => 5, 'min' => 1, 'hot' => true],
                ];
                foreach ($deals as $row) {
                    $deal = new CompanyDealModel();
                    $deal->company_id = $approved->id;
                    $deal->created_by_partner_id = $institution->id;
                    $deal->available_quantity = $row['qty'];
                    $deal->minimum_qty = $row['min'];
                    $deal->deal_type = $row['type'];
                    $deal->status = CompanyDealStatusEnum::available->value;
                    $deal->is_hot_deal = $row['hot'];
                    $deal->is_deleted = 0;
                    $pricing->stampFromBase($deal, $row['base']);
                    $deal->save();
                }

                $savedDeals = CompanyDealModel::notDeleted()
                    ->notExpired()
                    ->where('company_id', $approved->id)
                    ->where('is_hot_deal', false)
                    ->get(['deal_type', 'share_price', 'base_price']);
                $sells = $savedDeals->where('deal_type', CompanyDealTypeEnum::sell->value);
                $buys = $savedDeals->where('deal_type', CompanyDealTypeEnum::buy->value);
                $sellShare = (float) $sells->min('share_price');
                $buyShare = (float) $buys->min('share_price');
                $sellBase = (float) $sells->min('base_price');

                $approved->share_price = $sellShare;
                $approved->distributer_price = $buyShare;
                $approved->base_price = $sellBase;
                $approved->save();

                CompanySharePriceModel::create([
                    'company_id' => $approved->id,
                    'date' => now()->toDateString(),
                    'price' => $sellShare,
                    'distributer_price' => $buyShare,
                    'base_price' => $sellBase,
                ]);

                return [
                    'partners' => 5,
                    'self_investors' => InvestorModel::where('is_self', 1)->count(),
                    'client_investors' => InvestorModel::where('is_self', 0)->count(),
                    'approved_company' => $approved->brand_name,
                    'pending_company' => $pending->brand_name,
                    'deals' => CompanyDealModel::where('company_id', $approved->id)->count(),
                    'live_price' => $sellShare,
                    'distributer_price' => $buyShare,
                    'base_price' => $sellBase,
                ];
            });
        } catch (Throwable $e) {
            $this->error('Seed rolled back: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Admin test data created. Password for every test partner and client: '.self::PASSWORD);
        $this->table(['Item', 'Value'], collect($summary)->map(
            fn ($value, $name) => [$name, $value]
        )->values()->all());
        $this->line('Admin pages: /admin/partner/wealth-manager/list, /admin/partner/distributor/list, /admin/partner/retailers/list, /admin/partner/relation-manager/list, /admin/partner/institution/list');
        $this->line('Investors: /admin/investor/active, /admin/investor/inactive, /admin/investor/pending-kyc');
        $this->line('Company: search "Test Institution" on /admin/company/list and /admin/company/pending-seller');
        $this->line('Deals: /admin/company-deals');
        $this->line('Self investors are saved but hidden on the admin investor list and on the partner Investors tab.');

        return self::SUCCESS;
    }

    private function partner(
        string $name,
        string $mobile,
        string $email,
        string $type,
        ?float $commission,
        string $password,
        ?int $adminId,
        ?PartnerModel $parent = null
    ): PartnerModel {
        $partner = new PartnerModel();
        $partner->name = $name;
        $partner->mobile_country_code = '91';
        $partner->mobile_number = $mobile;
        $partner->email = $email;
        $partner->type = $type;
        $partner->commission = $commission;
        $partner->password = $password;
        $partner->gender = GenderEnum::male->value;
        $partner->is_verified_mobile = 1;
        $partner->is_verified_email = 1;
        $partner->is_blocked = 0;
        $partner->is_deleted = 0;
        $partner->is_demo = 0;
        $partner->is_primary_access = 1;
        $partner->is_secondary_access = 1;
        $partner->is_preipo_access = 1;
        $partner->created_by = $adminId;
        $partner->updated_by = $adminId;
        if ($parent) {
            $partner->parent_id = $parent->id;
            $partner->parent_type = $parent->type;
        }
        $partner->save();

        return $partner;
    }

    private function client(
        PartnerModel $partner,
        string $name,
        string $mobile,
        string $email,
        string $password,
        ?int $adminId,
        bool $active,
        string $dpId,
        string $clientId,
        string $pan,
        string $accountNumber
    ): void {
        $investor = $this->baseInvestor($partner, $name, $mobile, $email, $password, $adminId);
        $investor->is_active = $active ? 1 : 0;
        $investor->preipo_kyc_status = 1;
        $investor->save();

        InvestorDematAccountModel::create([
            'investor_id' => $investor->id,
            'dp_id' => $dpId,
            'client_id' => $clientId,
            'status' => 1,
        ]);

        $this->attachPanAndBank($investor, $pan, $accountNumber);
    }

    private function attachPanAndBank(InvestorModel $investor, string $pan, string $accountNumber): void
    {
        InvestorKycPanModel::updateOrCreate(
            ['investor_id' => $investor->id],
            [
                'pan_no' => $pan,
                'pan_name' => $investor->name,
                'dob' => '1990-01-15',
            ]
        );

        $bankExists = UserBankAccountModel::where('user_id', $investor->id)
            ->where('user_type', InvestorModel::class)
            ->exists();
        if (! $bankExists) {
            UserBankAccountModel::create([
                'user_type' => InvestorModel::class,
                'user_id' => $investor->id,
                'account_number' => $accountNumber,
                'account_holder_name' => $investor->name,
                'ifsc_code' => 'HDFC0001234',
                'bank_name' => 'HDFC',
            ]);
        }
    }

    private function pendingClient(
        PartnerModel $partner,
        string $name,
        string $mobile,
        string $email,
        string $password,
        ?int $adminId
    ): void {
        $investor = $this->baseInvestor($partner, $name, $mobile, $email, $password, $adminId);
        $investor->is_active = 1;
        $investor->preipo_kyc_status = 0;
        $investor->save();
    }

    private function baseInvestor(
        PartnerModel $partner,
        string $name,
        string $mobile,
        string $email,
        string $password,
        ?int $adminId
    ): InvestorModel {
        $investor = new InvestorModel();
        $investor->investor_type = InvestorTypeEnum::individual->value;
        $investor->name = $name;
        $investor->mobile_country_code = '91';
        $investor->mobile_number = $mobile;
        $investor->email = $email;
        $investor->gender = GenderEnum::male->value;
        $investor->password = $password;
        $investor->partner_id = $partner->id;
        $investor->is_self = 0;
        $investor->registration_step = 3;
        $investor->is_deleted = 0;
        $investor->is_demo = 0;
        $investor->is_blocked = 0;
        $investor->is_verified_mobile = 1;
        $investor->is_verified_email = 1;
        $investor->is_primary_access = 1;
        $investor->is_secondary_access = 1;
        $investor->is_preipo_access = 1;
        $investor->created_by = $adminId;
        $investor->updated_by = $adminId;

        return $investor;
    }

    private function company(
        PartnerModel $institution,
        int $sectorId,
        ?int $adminId,
        string $brand,
        string $legalName,
        string $cin,
        string $slug,
        string $type,
        string $approval
    ): CompanyModel {
        $company = new CompanyModel();
        $company->sector_id = $sectorId;
        $company->brand_name = $brand;
        $company->company_name = $legalName;
        $company->cin = $cin;
        $company->slug = $slug;
        $company->about = 'Local admin test company.';
        $company->type = $type;
        $company->category = 'Trending';
        $company->status = 0;
        $company->approval_status = $approval;
        $company->is_deleted = 0;
        $company->min_investment_type = 'Quantity';
        $company->min_investment_amount = 1;
        $company->submitted_by_partner_id = $institution->id;
        $company->submitted_by_seller_id = null;
        if ($approval === CompanyApprovalStatusEnum::approved->value) {
            $company->approved_by = $adminId;
            $company->approved_at = now();
        }
        $company->save();

        return $company;
    }
}
