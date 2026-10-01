<?php

namespace Tests\Feature;

use App\Enums\CompanyApprovalStatusEnum;
use App\Enums\DocumentTypeEnum;
use App\Enums\PartnerTypeEnum;
use App\Enums\PreIpoOrderStepEnum;
use App\Http\Middleware\ApiHeaderAuthMiddleware;
use App\Models\CompanyDealModel;
use App\Models\CompanyModel;
use App\Models\DocumentsModel;
use App\Models\InvestorModel;
use App\Models\MasterSectorsModel;
use App\Models\PartnerModel;
use App\Models\PreIpoModel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class InstitutionDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ApiHeaderAuthMiddleware::class);
    }

    public function test_non_institution_partner_is_rejected(): void
    {
        $partner = $this->makePartner(PartnerTypeEnum::distributor);
        $this->actingAs($partner, 'partner-api-guard');

        $response = $this->getJson('/api/v2/business/institution/dashboard');

        $response->assertOk();
        $this->assertSame(0, $response->json('status'));
        $this->assertSame('Only Institution partners can view this dashboard.', $response->json('message'));
    }

    public function test_dashboard_scopes_orders_deals_and_charts(): void
    {
        $institution = $this->makePartner(PartnerTypeEnum::institution);
        $other = $this->makePartner(PartnerTypeEnum::institution);
        $investor = $this->makeInvestor($institution->id);
        $company = $this->createApprovedCompany($institution);
        $otherCompany = $this->createApprovedCompany($other);

        $available = $this->makeDeal($company, $institution, [
            'expired_at' => null,
        ]);
        $expired = $this->makeDeal($company, $institution, [
            'expired_at' => now()->subDay(),
        ]);
        $otherDeal = $this->makeDeal($otherCompany, $other, [
            'expired_at' => null,
        ]);

        $pendingCompany = $this->makeSubmission($company, $institution, CompanyApprovalStatusEnum::pending);
        $otherPending = $this->makeSubmission($otherCompany, $other, CompanyApprovalStatusEnum::pending);

        $pending = $this->makeOrder($institution, $investor, $company, PreIpoOrderStepEnum::share_confirmation_pending->value);
        $processing = $this->makeOrder($institution, $investor, $company, PreIpoOrderStepEnum::payment_pending->value);
        $completed = $this->makeOrder($institution, $investor, $company, PreIpoOrderStepEnum::completed->value, [
            'investment_amount' => 250,
        ]);
        $signedCancel = $this->makeOrder($institution, $investor, $company, PreIpoOrderStepEnum::cancelled->value);
        $this->rememberSignedMandate($signedCancel);

        $mandatePending = $this->makeOrder($institution, $investor, $company, PreIpoOrderStepEnum::mandate_pending->value);
        $unsignedCancel = $this->makeOrder($institution, $investor, $company, PreIpoOrderStepEnum::cancelled->value);
        $legacy = $this->makeOrder($institution, $investor, $company, PreIpoOrderStepEnum::share_confirmation_pending->value);
        $legacy->order_step = null;
        $legacy->save();
        $otherOrder = $this->makeOrder($other, $investor, $otherCompany, PreIpoOrderStepEnum::share_confirmation_pending->value);

        $expectedCompanies = CompanyModel::query()
            ->where('is_deleted', 0)
            ->where('approval_status', CompanyApprovalStatusEnum::approved->value)
            ->count();

        $this->actingAs($institution, 'partner-api-guard');
        $response = $this->getJson('/api/v2/business/institution/dashboard');

        $response->assertOk();
        $this->assertSame(1, $response->json('status'), json_encode($response->json()));
        $this->assertSame('Dashboard', $response->json('message'));

        $this->assertSame([
            'is_primary_access' => true,
            'is_secondary_access' => false,
            'is_preipo_access' => true,
        ], $response->json('data.access'));

        $this->assertSame(1, $response->json('data.summary.transactions.pending'));
        $this->assertSame(1, $response->json('data.summary.transactions.processing'));
        $this->assertSame(1, $response->json('data.summary.transactions.completed'));
        $this->assertSame(1, $response->json('data.summary.deals.available'));
        $this->assertSame(1, $response->json('data.summary.deals.expired'));
        $this->assertSame(1, $response->json('data.summary.companies.pending_approval'));
        $this->assertSame($expectedCompanies, $response->json('data.summary.companies.total_companies'));
        $this->assertArrayNotHasKey('price_uploaded_today', $response->json('data.summary.companies'));
        $this->assertArrayNotHasKey('price_not_uploaded_today', $response->json('data.summary.companies'));

        $this->assertSame([
            ['key' => 'pending', 'label' => 'Pending', 'count' => 1],
            ['key' => 'processing', 'label' => 'Processing', 'count' => 1],
            ['key' => 'completed', 'label' => 'Completed', 'count' => 1],
        ], $response->json('data.charts.transaction_status'));
        $this->assertSame([
            ['key' => 'available', 'count' => 1],
            ['key' => 'expired', 'count' => 1],
        ], $response->json('data.charts.deals_by_status'));

        $monthly = $response->json('data.charts.transaction_volume.monthly');
        $this->assertCount(6, $monthly);
        $currentMonth = Carbon::now()->format('F Y');
        $this->assertSame($currentMonth, $monthly[5]['month']);
        $this->assertSame(1, $monthly[5]['count']);
        $this->assertEquals(250, $monthly[5]['amount']);
        $this->assertNotSame($currentMonth, $monthly[0]['month']);

        $quarterly = $response->json('data.charts.transaction_volume.quarterly');
        $this->assertCount(6, $quarterly);
        $this->assertArrayHasKey('quater', $quarterly[5]);
        $this->assertSame(1, $quarterly[5]['count']);
        $this->assertEquals(250, $quarterly[5]['amount']);

        $top = $response->json('data.charts.top_companies_by_completed_amount');
        $this->assertCount(1, $top);
        $this->assertSame($company->id, $top[0]['company_id']);
        $this->assertSame($company->brand_name, $top[0]['brand_name']);
        $this->assertSame(1, $top[0]['completed_count']);
        $this->assertEquals(250, $top[0]['completed_amount']);

        $recentIds = array_column($response->json('data.recent.transactions'), 'id');
        $this->assertSame([
            $signedCancel->id,
            $completed->id,
            $processing->id,
            $pending->id,
        ], $recentIds);
        $this->assertNotContains($mandatePending->id, $recentIds);
        $this->assertNotContains($unsignedCancel->id, $recentIds);
        $this->assertNotContains($legacy->id, $recentIds);
        $this->assertNotContains($otherOrder->id, $recentIds);

        $completedRow = collect($response->json('data.recent.transactions'))->firstWhere('id', $completed->id);
        $this->assertSame(0, $completedRow['status']);
        $this->assertSame(PreIpoOrderStepEnum::completed->value, $completedRow['order_step']);
        $this->assertEquals(250, $completedRow['investment_amount']);
        $this->assertSame($company->id, $completedRow['company']['id']);
        $this->assertSame($company->brand_name, $completedRow['company']['brand_name']);
        $this->assertSame($investor->id, $completedRow['investor']['id']);
        $this->assertSame($investor->name, $completedRow['investor']['name']);

        $recentDealUuids = array_column($response->json('data.recent.deals'), 'uuid');
        $this->assertSame([$expired->uuid, $available->uuid], $recentDealUuids);
        $this->assertNotContains($otherDeal->uuid, $recentDealUuids);
        $this->assertFalse($response->json('data.recent.deals.1.is_expired'));
        $this->assertTrue($response->json('data.recent.deals.0.is_expired'));

        $submissionIds = array_column($response->json('data.recent.my_submissions'), 'id');
        $this->assertSame([$pendingCompany->id, $company->id], $submissionIds);
        $this->assertNotContains($otherPending->id, $submissionIds);
        $this->assertSame(CompanyApprovalStatusEnum::pending->value, $response->json('data.recent.my_submissions.0.approval_status'));
    }

    private function makeOrder(PartnerModel $institution, InvestorModel $investor, CompanyModel $company, string $step, array $overrides = []): PreIpoModel
    {
        $amount = $overrides['investment_amount'] ?? 100;
        $order = new PreIpoModel();
        $order->status = 0;
        $order->order_step = $step;
        $order->investor_id = $investor->id;
        $order->company_id = $company->id;
        $order->partner_id = $institution->id;
        $order->shares = 2;
        $order->share_price = 50;
        $order->investment_amount = $amount;
        $order->payable_amount = $amount;
        $order->is_distributer = 1;
        $order->instrument = 'equity';
        $order->payment_mode = 'RTGS';
        $order->save();

        return $order;
    }

    private function rememberSignedMandate(PreIpoModel $order): void
    {
        DocumentsModel::create([
            'type' => DocumentTypeEnum::buymandate->value,
            'status' => 1,
            'meta' => [
                'name' => 'Buy mandate',
                'investor' => [$order->investor_id],
                'preipo_transactions' => [$order->id],
            ],
        ]);
    }

    private function makeDeal(CompanyModel $company, PartnerModel $institution, array $overrides): CompanyDealModel
    {
        return CompanyDealModel::create(array_merge([
            'company_id' => $company->id,
            'created_by_partner_id' => $institution->id,
            'created_by_seller_id' => null,
            'deal_type' => 'sell',
            'status' => 'available',
            'is_deleted' => false,
            'is_hot_deal' => false,
            'processing_fee_percentage' => 1,
            'base_price' => 99,
            'share_price' => 100,
            'minimum_qty' => 1,
            'available_quantity' => 10,
        ], $overrides));
    }

    private function makeSubmission(CompanyModel $source, PartnerModel $partner, CompanyApprovalStatusEnum $status): CompanyModel
    {
        $company = $source->replicate();
        $company->uuid = (string) Str::uuid();
        $company->slug = 'dash-'.$status->value.'-'.$this->mobile();
        $company->cin = 'U'.substr(str_replace('.', '', uniqid('', true)), 0, 20);
        $company->brand_name = 'Dash '.$status->value.' '.$this->mobile();
        $company->company_name = $company->brand_name.' Pvt Ltd';
        $company->approval_status = $status->value;
        $company->submitted_by_partner_id = $partner->id;
        $company->submitted_by_seller_id = null;
        $company->is_deleted = 0;
        $company->save();

        return $company;
    }

    private function createApprovedCompany(PartnerModel $institution): CompanyModel
    {
        $this->actingAs($institution, 'partner-api-guard');
        $response = $this->postJson('/api/v2/business/institution/company', [
            'type' => 'unlisted',
            'cin' => 'U'.substr(str_replace('.', '', uniqid('', true)), 0, 20),
            'brand_name' => 'Dash Co '.$this->mobile(),
            'company_name' => 'Dash Co '.$this->mobile().' Pvt Ltd',
            'sector' => $this->sectorId(),
            'about' => 'Institution dashboard test company',
            'min_investment_amount' => 1,
            'lot_size' => '1',
            'market_cap' => 1,
            'pe_ratio' => 1,
            'pb_ratio' => 1,
            'debt_to_equity' => 1,
            'roe' => 1,
            'book_value' => 1,
            'face_value' => 10,
        ]);
        $this->assertSame(1, $response->json('status'), json_encode($response->json()));

        return CompanyModel::findOrFail($response->json('data.id'));
    }

    private function sectorId(): int
    {
        $id = MasterSectorsModel::where('is_deleted', '0')->value('id');
        $this->assertNotNull($id, 'No master sector available for company create');

        return (int) $id;
    }

    private function makePartner(PartnerTypeEnum $type): PartnerModel
    {
        $partner = new PartnerModel();
        $partner->type = $type->value;
        $partner->name = $type->name.' '.$this->mobile();
        $partner->mobile_number = $this->mobile();
        $partner->email = $this->email();
        $partner->gender = 'Male';
        $partner->password = Hash::make('secret-pass');
        $partner->commission = 0;
        $partner->is_deleted = '0';
        $partner->is_blocked = '0';
        $partner->is_primary_access = 1;
        $partner->is_secondary_access = 0;
        $partner->is_preipo_access = 1;
        $partner->save();

        return $partner;
    }

    private function makeInvestor(int $partnerId): InvestorModel
    {
        $investor = new InvestorModel();
        $investor->partner_id = $partnerId;
        $investor->investor_type = 'Individual';
        $investor->name = 'Investor '.$this->mobile();
        $investor->mobile_country_code = '91';
        $investor->mobile_number = $this->mobile();
        $investor->email = $this->email();
        $investor->gender = 'Male';
        $investor->password = Hash::make('secret-pass');
        $investor->is_self = 0;
        $investor->is_deleted = '0';
        $investor->is_active = 1;
        $investor->is_blocked = 0;
        $investor->is_demo = 1;
        $investor->preipo_kyc_status = 0;
        $investor->aif_status = 0;
        $investor->registration_step = '3';
        $investor->save();

        return $investor;
    }

    private function mobile(): string
    {
        $this->seq++;

        return sprintf('6%09d', (int) (fmod(microtime(true), 100000) * 1000) + $this->seq);
    }

    private function email(): string
    {
        return 'inst-dash-'.bin2hex(random_bytes(6)).'@example.test';
    }
}
