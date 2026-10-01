<?php

namespace Tests\Feature;

use App\Enums\PartnerTypeEnum;
use App\Http\Controllers\Web\Admin\PreIpoTransactionController;
use App\Http\Middleware\ApiHeaderAuthMiddleware;
use App\Jobs\InvestorActionWindowStartJob;
use App\Jobs\preipo\BuyNotificationJob;
use App\Jobs\preipo\CalcuatePricingAutoJob;
use App\Jobs\preipo\CancelNotificationJob;
use App\Jobs\SendDealSlipJob;
use App\Models\CompanyDealModel;
use App\Models\CompanyModel;
use App\Models\InvestorModel;
use App\Models\MasterSectorsModel;
use App\Models\PartnerModel;
use App\Models\PreIpoModel;
use App\Models\UserAdminModel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PreIpoPartnerBuyTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ApiHeaderAuthMiddleware::class);

        Bus::fake([
            BuyNotificationJob::class,
            CalcuatePricingAutoJob::class,
            SendDealSlipJob::class,
            InvestorActionWindowStartJob::class,
            CancelNotificationJob::class,
        ]);

        Http::fake([
            '*create_sign_request*' => Http::response([
                'id' => 'DID-PARTNER-BUY-TEST',
                'signing_parties' => [[
                    'authentication_url' => 'https://digio.test/sign',
                    'expire_on' => '2030-01-01 00:00:00',
                ]],
            ], 200),
        ]);
    }

    public function test_partner_buy_places_institution_orders_and_rejects_the_batch(): void
    {
        $institution = $this->makePartner(PartnerTypeEnum::institution);
        $selfInvestor = $this->makeInvestor($institution->id, ['is_self' => 1]);
        $company = $this->createApprovedCompany($institution);
        $deal = $this->makeDeal($company, $institution, [
            'base_price' => 99,
            'share_price' => 100,
            'minimum_qty' => 2,
            'available_quantity' => 10,
        ]);

        $buyer = $this->makePartner(PartnerTypeEnum::distributor);
        $manager = $this->makePartner(PartnerTypeEnum::relationmanager, ['parent_id' => $buyer->id]);
        $client = $this->makeInvestor($buyer->id, ['is_self' => 0]);
        $rmClient = $this->makeInvestor($manager->id, ['is_self' => 0]);
        $stranger = $this->makeInvestor($institution->id, ['is_self' => 0]);

        $this->actingAs($buyer, 'partner-api-guard');

        $response = $this->postJson('/api/v2/business/pre-ipo/buy', [
            'orders' => [
                [
                    'deal_uuid' => $deal->uuid,
                    'investor_id' => $client->id,
                    'shares' => 6,
                    'share_price' => 102,
                    'seller_id' => 99,
                    'partner_id' => $buyer->id,
                    'distributer_price' => 1,
                    'payment_mode' => 'Cheque',
                    'is_distributer' => false,
                ],
                [
                    'deal_uuid' => $deal->uuid,
                    'investor_id' => $rmClient->id,
                    'shares' => 4,
                    'share_price' => 110,
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertSame(1, $response->json('status'), json_encode($response->json()));
        $this->assertSame('Orders placed successfully.', $response->json('message'));
        $this->assertCount(2, $response->json('data'));

        $first = PreIpoModel::find($response->json('data.0.id'));
        $second = PreIpoModel::find($response->json('data.1.id'));
        $this->assertNotNull($first);
        $this->assertNotNull($second);

        $this->assertSame(0, (int) $first->status);
        $this->assertSame('mandate_pending', $first->order_step);
        $this->assertSame($client->id, (int) $first->investor_id);
        $this->assertSame($company->id, (int) $first->company_id);
        $this->assertSame($deal->id, (int) $first->deal_id);
        $this->assertSame($institution->id, (int) $first->partner_id);
        $this->assertSame($selfInvestor->id, (int) $first->seller_investor_id);
        $this->assertNull($first->seller_id);
        $this->assertEquals(99, (float) $first->base_price);
        $this->assertEquals(100, (float) $first->distributer_price);
        $this->assertEquals(102, (float) $first->share_price);
        $this->assertEquals(612, (float) $first->investment_amount);
        $this->assertEquals(612, (float) $first->payable_amount);
        $this->assertEquals(1, (int) $first->is_distributer);
        $this->assertSame('RTGS', $first->payment_mode instanceof \BackedEnum ? $first->payment_mode->value : $first->payment_mode);
        $this->assertSame('equity', $first->instrument instanceof \BackedEnum ? $first->instrument->value : $first->instrument);
        $this->assertNotNull($first->settlement_date);
        $this->assertSame(4, (int) $second->shares);
        $this->assertEquals(440, (float) $second->investment_amount);
        $this->assertSame(10, (int) $deal->fresh()->available_quantity);

        $before = PreIpoModel::count();
        $rejected = $this->postJson('/api/v2/business/pre-ipo/buy', [
            'orders' => [
                [
                    'deal_uuid' => $deal->uuid,
                    'investor_id' => $client->id,
                    'shares' => 2,
                    'share_price' => 102,
                ],
                [
                    'deal_uuid' => $deal->uuid,
                    'investor_id' => $stranger->id,
                    'shares' => 2,
                    'share_price' => 102,
                ],
            ],
        ]);
        $rejected->assertOk();
        $this->assertSame(0, $rejected->json('status'), json_encode($rejected->json()));
        $this->assertStringContainsString('Investor is not available', (string) $rejected->json('message'));
        $this->assertSame($before, PreIpoModel::count());

        $belowMin = $this->postJson('/api/v2/business/pre-ipo/buy', [
            'orders' => [
                ['deal_uuid' => $deal->uuid, 'investor_id' => $client->id, 'shares' => 1, 'share_price' => 102],
            ],
        ]);
        $this->assertSame(0, $belowMin->json('status'), json_encode($belowMin->json()));
        $this->assertSame($before, PreIpoModel::count());

        $over = $this->postJson('/api/v2/business/pre-ipo/buy', [
            'orders' => [
                ['deal_uuid' => $deal->uuid, 'investor_id' => $client->id, 'shares' => 6, 'share_price' => 102],
                ['deal_uuid' => $deal->uuid, 'investor_id' => $rmClient->id, 'shares' => 6, 'share_price' => 102],
            ],
        ]);
        $this->assertSame(0, $over->json('status'), json_encode($over->json()));
        $this->assertSame($before, PreIpoModel::count());

        $openDeal = $this->makeDeal($company, $institution, [
            'base_price' => 99,
            'share_price' => 100,
            'minimum_qty' => 1,
            'available_quantity' => 0,
        ]);
        $open = $this->postJson('/api/v2/business/pre-ipo/buy', [
            'orders' => [
                ['deal_uuid' => $openDeal->uuid, 'investor_id' => $client->id, 'shares' => 50, 'share_price' => 102],
            ],
        ]);
        $this->assertSame(1, $open->json('status'), json_encode($open->json()));
        $this->assertSame(0, (int) $openDeal->fresh()->available_quantity);

        $noBase = $this->makeDeal($company, $institution, [
            'base_price' => null,
            'share_price' => 100,
            'minimum_qty' => 1,
            'available_quantity' => 5,
        ]);
        $missingBase = $this->postJson('/api/v2/business/pre-ipo/buy', [
            'orders' => [
                ['deal_uuid' => $noBase->uuid, 'investor_id' => $client->id, 'shares' => 1, 'share_price' => 102],
            ],
        ]);
        $this->assertSame(0, $missingBase->json('status'));

        $sellerDeal = CompanyDealModel::create([
            'company_id' => $company->id,
            'created_by_partner_id' => null,
            'deal_type' => 'sell',
            'share_price' => 100,
            'base_price' => 99,
            'minimum_qty' => 1,
            'available_quantity' => 5,
            'status' => 'available',
            'is_deleted' => false,
            'processing_fee_percentage' => 1,
        ]);
        $noPartner = $this->postJson('/api/v2/business/pre-ipo/buy', [
            'orders' => [
                ['deal_uuid' => $sellerDeal->uuid, 'investor_id' => $client->id, 'shares' => 1, 'share_price' => 102],
            ],
        ]);
        $this->assertSame(0, $noPartner->json('status'));

        $bareInstitution = $this->makePartner(PartnerTypeEnum::institution);
        $bareDeal = $this->makeDeal($company, $bareInstitution, [
            'base_price' => 99,
            'share_price' => 100,
            'minimum_qty' => 1,
            'available_quantity' => 5,
        ]);
        $missingSelf = $this->postJson('/api/v2/business/pre-ipo/buy', [
            'orders' => [
                ['deal_uuid' => $bareDeal->uuid, 'investor_id' => $client->id, 'shares' => 1, 'share_price' => 102],
            ],
        ]);
        $this->assertSame(0, $missingSelf->json('status'));
    }

    public function test_admin_approve_skips_seller_when_partner_is_set(): void
    {
        $institution = $this->makePartner(PartnerTypeEnum::institution);
        $selfInvestor = $this->makeInvestor($institution->id, ['is_self' => 1, 'is_demo' => 1]);
        $company = $this->createApprovedCompany($institution);
        $buyer = $this->makePartner(PartnerTypeEnum::distributor);
        $client = $this->makeInvestor($buyer->id, ['is_self' => 0, 'is_demo' => 1, 'preipo_kyc_status' => 0]);

        $partnerOrder = new PreIpoModel();
        $partnerOrder->status = 0;
        $partnerOrder->investor_id = $client->id;
        $partnerOrder->company_id = $company->id;
        $partnerOrder->partner_id = $institution->id;
        $partnerOrder->seller_investor_id = $selfInvestor->id;
        $partnerOrder->seller_id = null;
        $partnerOrder->shares = 2;
        $partnerOrder->share_price = 102;
        $partnerOrder->distributer_price = 100;
        $partnerOrder->base_price = 99;
        $partnerOrder->investment_amount = 204;
        $partnerOrder->payable_amount = 204;
        $partnerOrder->is_distributer = 1;
        $partnerOrder->instrument = 'equity';
        $partnerOrder->payment_mode = 'RTGS';
        $partnerOrder->save();

        $admin = UserAdminModel::create([
            'role' => 'admin',
            'name' => 'Pre IPO Admin',
            'username' => 'preipo-admin-'.$this->mobile(),
            'email' => $this->email(),
            'password' => Hash::make('secret-pass'),
        ]);
        Auth::guard('admin')->login($admin);

        $this->app->instance('request', Request::create('/admin/pre-ipo-transactions/approve-transaction', 'POST', [
            'status' => 'approve',
            'transaction' => $partnerOrder->id,
        ]));
        $approved = app(PreIpoTransactionController::class)->approveTransaction();
        $this->assertSame(1, $approved->getData()->status);
        $partnerOrder->refresh();
        $this->assertSame(2, (int) $partnerOrder->status);
        $this->assertNull($partnerOrder->seller_id);

        $legacy = new PreIpoModel();
        $legacy->status = 0;
        $legacy->investor_id = $client->id;
        $legacy->company_id = $company->id;
        $legacy->seller_id = null;
        $legacy->shares = 1;
        $legacy->share_price = 10;
        $legacy->investment_amount = 10;
        $legacy->payable_amount = 10;
        $legacy->is_distributer = 0;
        $legacy->instrument = 'equity';
        $legacy->payment_mode = 'RTGS';
        $legacy->save();

        $this->app->instance('request', Request::create('/admin/pre-ipo-transactions/approve-transaction', 'POST', [
            'status' => 'approve',
            'transaction' => $legacy->id,
        ]));
        $blocked = app(PreIpoTransactionController::class)->approveTransaction();
        $this->assertSame(0, $blocked->getData()->status);
        $this->assertSame(0, (int) $legacy->fresh()->status);

        $this->app->instance('request', Request::create('/admin/pre-ipo-transactions/approve-transaction', 'POST', [
            'status' => 'reject',
            'transaction' => $legacy->id,
            'notes' => 'No',
        ]));
        $rejected = app(PreIpoTransactionController::class)->approveTransaction();
        $this->assertSame(1, $rejected->getData()->status);
        $legacy->refresh();
        $this->assertSame(1, (int) $legacy->status);
        $this->assertNull($legacy->seller_id);
    }

    private function createApprovedCompany(PartnerModel $institution): CompanyModel
    {
        $this->actingAs($institution, 'partner-api-guard');
        $response = $this->postJson('/api/v2/business/institution/company', [
            'type' => 'unlisted',
            'cin' => 'U'.substr(str_replace('.', '', uniqid('', true)), 0, 20),
            'brand_name' => 'Partner Buy '.$this->mobile(),
            'company_name' => 'Partner Buy '.$this->mobile().' Pvt Ltd',
            'sector' => $this->sectorId(),
            'about' => 'Partner buy test company',
            'min_investment_amount' => 1,
            'lot_size' => '1',
            'market_cap' => 1,
            'pe_ratio' => 1,
            'pb_ratio' => 1,
            'debt_to_equity' => 1,
            'roe' => 1,
            'book_value' => 1,
            'face_value' => 1,
        ]);
        $response->assertOk();
        $this->assertSame(1, $response->json('status'), json_encode($response->json()));

        return CompanyModel::findOrFail($response->json('data.id'));
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
        ], $overrides));
    }

    private function sectorId(): int
    {
        $id = MasterSectorsModel::where('is_deleted', '0')->value('id');
        $this->assertNotNull($id, 'No master sector available for company create');

        return (int) $id;
    }

    private function makePartner(PartnerTypeEnum $type, array $overrides = []): PartnerModel
    {
        $partner = new PartnerModel();
        $partner->type = $type->value;
        $partner->name = $overrides['name'] ?? ($type->name.' '.$this->mobile());
        $partner->mobile_number = $overrides['mobile_number'] ?? $this->mobile();
        $partner->email = $overrides['email'] ?? $this->email();
        $partner->gender = $overrides['gender'] ?? 'Male';
        $partner->password = $overrides['password'] ?? Hash::make('secret-pass');
        $partner->commission = $overrides['commission'] ?? 0;
        $partner->is_deleted = '0';
        $partner->is_blocked = '0';
        $partner->is_primary_access = 1;
        $partner->is_secondary_access = 0;
        $partner->is_preipo_access = 1;
        if (isset($overrides['parent_id'])) {
            $partner->parent_id = $overrides['parent_id'];
        }
        $partner->save();

        return $partner;
    }

    private function makeInvestor(int $partnerId, array $overrides = []): InvestorModel
    {
        $investor = new InvestorModel();
        $investor->partner_id = $partnerId;
        $investor->investor_type = 'Individual';
        $investor->name = $overrides['name'] ?? ('Investor '.$this->mobile());
        $investor->mobile_country_code = '91';
        $investor->mobile_number = $overrides['mobile_number'] ?? $this->mobile();
        $investor->email = $overrides['email'] ?? $this->email();
        $investor->gender = 'Male';
        $investor->password = Hash::make('secret-pass');
        $investor->is_self = $overrides['is_self'] ?? 0;
        $investor->is_deleted = $overrides['is_deleted'] ?? '0';
        $investor->is_active = $overrides['is_active'] ?? 1;
        $investor->is_blocked = $overrides['is_blocked'] ?? 0;
        $investor->is_demo = $overrides['is_demo'] ?? 0;
        $investor->preipo_kyc_status = $overrides['preipo_kyc_status'] ?? 0;
        $investor->aif_status = $overrides['aif_status'] ?? 0;
        $investor->registration_step = '3';
        $investor->save();

        return $investor;
    }

    private function mobile(): string
    {
        $this->seq++;

        return sprintf('7%09d', (int) (fmod(microtime(true), 100000) * 1000) + $this->seq);
    }

    private function email(): string
    {
        return 'partner-buy-'.bin2hex(random_bytes(6)).'@example.test';
    }
}
