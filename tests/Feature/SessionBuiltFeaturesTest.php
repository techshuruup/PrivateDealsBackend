<?php

namespace Tests\Feature;

use App\Enums\PartnerTypeEnum;
use App\Helpers\CommonHelper;
use App\Http\Controllers\Web\Admin\InvestorController;
use App\Http\Middleware\ApiHeaderAuthMiddleware;
use App\Jobs\notifications\kyc\KycCompletedBroadcastJob;
use App\Jobs\preipo\CalcuatePricingAutoJob;
use App\Jobs\SendDealSlipJob;
use App\Jobs\SendPendingKycAdminNotification;
use App\Models\CompanyDealModel;
use App\Models\CompanyModel;
use App\Models\CompanySharePriceModel;
use App\Models\DocumentsModel;
use App\Models\GlobalSettingModel;
use App\Models\InvestorDematAccountModel;
use App\Models\InvestorModel;
use App\Models\MasterSectorsModel;
use App\Models\PartnerModel;
use App\Models\SellerMasterModel;
use App\Models\UserAdminModel;
use App\Repositories\PartnerRepository;
use App\Services\DematPdfParsingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class SessionBuiltFeaturesTest extends TestCase
{
    use DatabaseTransactions;

    private array $tempFiles = [];

    private array $storagePaths = [];

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ApiHeaderAuthMiddleware::class);

        Bus::fake([
            CalcuatePricingAutoJob::class,
            SendDealSlipJob::class,
            KycCompletedBroadcastJob::class,
            SendPendingKycAdminNotification::class,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
        }
        foreach ($this->storagePaths as $path) {
            Storage::disk('local')->delete($path);
        }

        parent::tearDown();
    }

    public function test_institution_partner_type_and_admin_routes_exist(): void
    {
        $this->assertSame('Institution', PartnerTypeEnum::institution->value);
        $this->assertTrue(collect(PartnerTypeEnum::cases())->contains(fn ($case) => $case === PartnerTypeEnum::institution));

        foreach (['list', 'create', 'view', 'edit', 'store', 'update', 'destroy'] as $action) {
            $this->assertTrue(
                RouteFacade::has('admin.partner.institution.'.$action),
                'Missing route admin.partner.institution.'.$action
            );
        }
    }

    public function test_cml_routes_live_under_investor_and_old_kyc_prefix_is_gone(): void
    {
        $this->assertTrue(RouteFacade::has('v2.business.investor.kyc.cml.read'));
        $this->assertTrue(RouteFacade::has('v2.business.investor.kyc.cml.save'));

        $read = RouteFacade::getRoutes()->getByName('v2.business.investor.kyc.cml.read');
        $save = RouteFacade::getRoutes()->getByName('v2.business.investor.kyc.cml.save');
        $this->assertNotNull($read);
        $this->assertNotNull($save);
        $this->assertSame('api/v2/business/investor/kyc/cml/read', $read->uri());
        $this->assertSame('api/v2/business/investor/kyc/cml/save', $save->uri());
        $this->assertContains('POST', $read->methods());
        $this->assertContains('POST', $save->methods());

        foreach (RouteFacade::getRoutes() as $route) {
            $this->assertStringNotContainsString(
                'v2/business/kyc',
                $route->uri(),
                'Old KYC prefix still registered: '.$route->uri()
            );
        }
    }

    public function test_institution_and_seller_company_create_is_approved_immediately(): void
    {
        $sectorId = $this->sectorId();
        $institution = $this->makePartner(PartnerTypeEnum::institution);
        $seller = $this->makeSeller();

        $this->actingAs($institution, 'partner-api-guard');
        $institutionPayload = $this->companyPayload($sectorId, 'Institution Co');
        $institutionResponse = $this->postJson('/api/v2/business/institution/company', $institutionPayload);
        $institutionResponse->assertOk();
        $institutionJson = $institutionResponse->json();
        $this->assertSame(1, $institutionJson['status'] ?? null, json_encode($institutionJson));

        $institutionCompany = CompanyModel::find($institutionJson['data']['id']);
        $this->assertNotNull($institutionCompany);
        $this->assertSame('approved', $this->enumValue($institutionCompany->approval_status));
        $this->assertEquals(0, $institutionCompany->status);
        $this->assertEquals(0, $institutionCompany->is_deleted);
        $this->assertSame($institution->id, (int) $institutionCompany->submitted_by_partner_id);
        $this->assertNull($institutionCompany->submitted_by_seller_id);

        $wealthManager = $this->makePartner(PartnerTypeEnum::wealthmanager);
        $this->actingAs($wealthManager, 'partner-api-guard');
        $denied = $this->postJson('/api/v2/business/institution/company', $this->companyPayload($sectorId, 'Denied Co'));
        $denied->assertOk();
        $this->assertSame(0, $denied->json('status'), json_encode($denied->json()));

        $this->actingAs($seller, 'seller-api-guard');
        $sellerResponse = $this->postJson('/api/v2/seller/company', $this->companyPayload($sectorId, 'Seller Co'));
        $sellerResponse->assertOk();
        $sellerJson = $sellerResponse->json();
        $this->assertSame(1, $sellerJson['status'] ?? null, json_encode($sellerJson));

        $sellerCompany = CompanyModel::find($sellerJson['data']['id']);
        $this->assertNotNull($sellerCompany);
        $this->assertSame('approved', $this->enumValue($sellerCompany->approval_status));
        $this->assertEquals(0, $sellerCompany->status);
        $this->assertEquals(0, $sellerCompany->is_deleted);
        $this->assertSame($seller->id, (int) $sellerCompany->submitted_by_seller_id);
        $this->assertNull($sellerCompany->submitted_by_partner_id);
    }

    public function test_bulk_deals_insert_price_rows_and_record_non_hot_history(): void
    {
        $this->rebindFee('2.5');
        $this->assertSame(2.5, CommonHelper::processingFeePercentage());

        $institution = $this->makePartner(PartnerTypeEnum::institution);
        $company = $this->createApprovedCompany($institution);

        CompanyDealModel::create([
            'company_id' => $company->id,
            'deal_type' => 'sell',
            'share_price' => 1,
            'base_price' => 1,
            'minimum_qty' => 1,
            'available_quantity' => 1,
            'status' => 'available',
            'is_hot_deal' => false,
            'is_deleted' => true,
            'processing_fee_percentage' => 2.5,
        ]);
        CompanyDealModel::create([
            'company_id' => $company->id,
            'deal_type' => 'sell',
            'share_price' => 2,
            'base_price' => 2,
            'minimum_qty' => 1,
            'available_quantity' => 1,
            'status' => 'available',
            'is_hot_deal' => false,
            'is_deleted' => false,
            'expired_at' => now()->subDay(),
            'processing_fee_percentage' => 2.5,
        ]);

        $this->actingAs($institution, 'partner-api-guard');

        $callbacksBeforeHot = $this->afterCommitCount();
        $hot = $this->postJson('/api/v2/business/institution/company/deals', [
            'company_id' => $company->id,
            'deal_type' => 'sell',
            'available_quantity' => 5,
            'share_price' => 3,
            'minimum_qty' => 1,
            'is_hot_deal' => true,
        ]);
        $hot->assertOk();
        $this->assertSame(1, $hot->json('status'), json_encode($hot->json()));
        $this->assertSame($callbacksBeforeHot, $this->afterCommitCount(), 'Hot-only create scheduled share-price history');
        Bus::assertNotDispatched(CalcuatePricingAutoJob::class);
        $this->assertSame(0, CompanySharePriceModel::where('company_id', $company->id)->count());

        $hotDeal = CompanyDealModel::where('company_id', $company->id)->where('is_hot_deal', true)->first();
        $this->assertNotNull($hotDeal);
        $this->assertEquals(3.0, (float) $hotDeal->base_price);
        $this->assertEqualsWithDelta(3.08, (float) $hotDeal->share_price, 0.001);

        $beforeBulk = CompanyDealModel::where('company_id', $company->id)->count();
        $callbacksBeforeBulk = $this->afterCommitCount();
        $bulk = $this->postJson('/api/v2/business/institution/company/deals/bulk', [
            'sell' => [
                ['company_id' => '', 'sell_price' => '', 'min_qty' => ''],
                ['company_id' => $company->id, 'sell_price' => '0', 'min_qty' => 1],
                ['company_id' => $company->id, 'sell_price' => '   ', 'min_qty' => 1],
                ['company_id' => $company->id, 'sell_price' => 100, 'min_qty' => 2],
                ['company_id' => $company->id, 'sell_price' => 80, 'min_qty' => 1],
            ],
            'buy' => [
                ['company_id' => $company->id, 'buy_price' => 0, 'min_qty' => 1],
                ['company_id' => $company->id, 'buy_price' => 50, 'min_qty' => 4],
            ],
        ]);
        $bulk->assertOk();
        $this->assertSame(1, $bulk->json('status'), json_encode($bulk->json()));

        $created = CompanyDealModel::where('company_id', $company->id)
            ->where('created_by_partner_id', $institution->id)
            ->where('is_hot_deal', false)
            ->orderBy('id')
            ->get();
        $this->assertCount(3, $created);
        $this->assertSame($beforeBulk + 3, CompanyDealModel::where('company_id', $company->id)->count());
        $this->assertEquals(['sell', 'sell', 'buy'], $created->pluck('deal_type')->all());
        $this->assertTrue($created->every(fn ($deal) => $deal->created_by_seller_id === null));

        $sellHigh = $created[0];
        $sellLow = $created[1];
        $buy = $created[2];
        $this->assertEquals(100.0, (float) $sellHigh->base_price);
        $this->assertEqualsWithDelta(102.5, (float) $sellHigh->share_price, 0.001);
        $this->assertEquals(80.0, (float) $sellLow->base_price);
        $this->assertEqualsWithDelta(82.0, (float) $sellLow->share_price, 0.001);
        $this->assertEquals(50.0, (float) $buy->base_price);
        $this->assertEqualsWithDelta(51.25, (float) $buy->share_price, 0.001);
        $this->assertEquals(2.5, (float) $sellLow->processing_fee_percentage);
        $this->assertSame(2, (int) $sellHigh->minimum_qty);

        $history = CompanySharePriceModel::where('company_id', $company->id)->whereDate('date', now()->toDateString())->get();
        $this->assertCount(1, $history);
        $this->assertEqualsWithDelta(82.0, (float) $history[0]->price, 0.001);
        $this->assertEqualsWithDelta(51.25, (float) $history[0]->distributer_price, 0.001);
        $this->assertEqualsWithDelta(80.0, (float) $history[0]->base_price, 0.001);

        if (Bus::dispatched(CalcuatePricingAutoJob::class)->isEmpty()) {
            $this->runNewAfterCommitCallbacks($callbacksBeforeBulk);
        }
        $this->assertNotEmpty(
            Bus::dispatched(CalcuatePricingAutoJob::class),
            'Job was not dispatched. Stored after-commit callbacks: '.$this->afterCommitCount()
        );

        $existingIds = CompanyDealModel::where('company_id', $company->id)->pluck('id')->all();
        $second = $this->postJson('/api/v2/business/institution/company/deals/bulk', [
            'sell' => [
                ['company_id' => $company->id, 'sell_price' => 200, 'min_qty' => 1],
            ],
            'buy' => [],
        ]);
        $second->assertOk();
        $this->assertSame(1, $second->json('status'), json_encode($second->json()));
        $this->assertSame(count($existingIds) + 1, CompanyDealModel::where('company_id', $company->id)->count());
        $this->assertEquals(count($existingIds), CompanyDealModel::whereIn('id', $existingIds)->count());

        $history->first()->refresh();
        $this->assertEqualsWithDelta(82.0, (float) $history->first()->price, 0.001);
        $this->assertCount(1, CompanySharePriceModel::where('company_id', $company->id)->whereDate('date', now()->toDateString())->get());

        $invalid = $this->postJson('/api/v2/business/institution/company/deals/bulk', [
            'sell' => [
                ['company_id' => $company->id, 'sell_price' => 10, 'min_qty' => 0],
            ],
        ]);
        $invalid->assertOk();
        $this->assertSame(0, $invalid->json('status'), json_encode($invalid->json()));
        $this->assertStringContainsString('min_qty', (string) $invalid->json('message'));

        $blank = $this->postJson('/api/v2/business/institution/company/deals/bulk', [
            'sell' => [
                ['company_id' => $company->id, 'sell_price' => '', 'min_qty' => ''],
            ],
            'buy' => [
                ['buy_price' => 0],
            ],
        ]);
        $blank->assertOk();
        $this->assertSame(0, $blank->json('status'), json_encode($blank->json()));
    }

    public function test_buy_only_history_uses_buy_prices(): void
    {
        $this->rebindFee('1');
        $institution = $this->makePartner(PartnerTypeEnum::institution);
        $company = $this->createApprovedCompany($institution);
        $this->actingAs($institution, 'partner-api-guard');

        $response = $this->postJson('/api/v2/business/institution/company/deals/bulk', [
            'buy' => [
                ['company_id' => $company->id, 'buy_price' => 40, 'min_qty' => 1],
                ['company_id' => $company->id, 'buy_price' => 25, 'min_qty' => 1],
            ],
        ]);
        $response->assertOk();
        $this->assertSame(1, $response->json('status'), json_encode($response->json()));

        $history = CompanySharePriceModel::where('company_id', $company->id)->whereDate('date', now()->toDateString())->first();
        $this->assertNotNull($history);
        $this->assertEqualsWithDelta(25.25, (float) $history->price, 0.001);
        $this->assertEqualsWithDelta(25.25, (float) $history->distributer_price, 0.001);
        $this->assertEqualsWithDelta(25.0, (float) $history->base_price, 0.001);
    }

    public function test_self_investor_is_created_once_for_partner_types_except_relation_manager(): void
    {
        $repo = app(PartnerRepository::class);
        $password = Hash::make('secret-pass');

        foreach ([
            PartnerTypeEnum::wealthmanager,
            PartnerTypeEnum::distributor,
            PartnerTypeEnum::retailer,
            PartnerTypeEnum::institution,
        ] as $type) {
            $partner = $this->makePartner($type, ['password' => $password]);
            $repo->createSelfInvestor($partner);
            $repo->createSelfInvestor($partner);

            $rows = InvestorModel::where('partner_id', $partner->id)->where('is_self', 1)->get();
            $this->assertCount(1, $rows, $type->value);
            $investor = $rows->first();
            $this->assertSame($partner->name, $investor->name);
            $this->assertSame($partner->mobile_number, $investor->mobile_number);
            $this->assertSame($partner->email, $investor->email);
            $this->assertSame($partner->password, $investor->password);
            $this->assertEquals(1, $investor->is_self);
        }

        $manager = $this->makePartner(PartnerTypeEnum::relationmanager);
        $repo->createSelfInvestor($manager);
        $this->assertSame(0, InvestorModel::where('partner_id', $manager->id)->count());
    }

    public function test_partner_save_creates_self_investor_on_create_not_update_and_api_skips_cml(): void
    {
        $parent = $this->makePartner(PartnerTypeEnum::wealthmanager, ['commission' => 10]);

        $email = $this->email();
        $mobile = $this->mobile();
        $response = $this->callDistributorSave('/api/v1/business/channel-partner/create', [
            'partner_type' => PartnerTypeEnum::retailer->value,
            'name' => 'Retail Child',
            'mobile_number' => $mobile,
            'email' => $email,
            'gender' => 'Male',
            'password' => 'secret-pass',
            'commission' => 4,
            'parent_partner_id' => $parent->id,
            'is_primary_access' => 1,
            'is_secondary_access' => 0,
            'is_preipo_access' => 0,
        ]);
        $this->assertSame(1, $response->getData(true)['status'] ?? null, json_encode($response->getData(true)));

        $child = PartnerModel::where('email', $email)->first();
        $this->assertNotNull($child);
        $this->assertSame(1, InvestorModel::where('partner_id', $child->id)->where('is_self', 1)->count());
        $this->assertSame(0, InvestorDematAccountModel::whereIn('investor_id', InvestorModel::where('partner_id', $child->id)->pluck('id'))->count());

        $update = $this->callDistributorSave('/admin/partner/retailers/update/'.$child->uuid, [
            'partner_type' => PartnerTypeEnum::retailer->value,
            'name' => 'Retail Child Updated',
            'mobile_number' => $mobile,
            'email' => $email,
            'gender' => 'Male',
            'commission' => 4,
            'is_primary_access' => 1,
            'is_secondary_access' => 0,
            'is_preipo_access' => 0,
        ], $child->uuid);
        $this->assertTrue($update->isRedirection(), json_encode(session('errors') ? session('errors')->all() : $update->getContent()));
        $child->refresh();
        $this->assertSame('Retail Child Updated', $child->name);
        $this->assertSame(1, InvestorModel::where('partner_id', $child->id)->where('is_self', 1)->count());

        $rmEmail = $this->email();
        $rm = $this->callDistributorSave('/api/v1/business/channel-partner/create', [
            'partner_type' => PartnerTypeEnum::relationmanager->value,
            'name' => 'RM Child',
            'mobile_number' => $this->mobile(),
            'email' => $rmEmail,
            'gender' => 'Male',
            'password' => 'secret-pass',
            'parent_partner_id' => $parent->id,
            'is_primary_access' => 1,
            'is_secondary_access' => 0,
            'is_preipo_access' => 0,
        ]);
        $this->assertSame(1, $rm->getData(true)['status'] ?? null, json_encode($rm->getData(true)));
        $rmPartner = PartnerModel::where('email', $rmEmail)->first();
        $this->assertNotNull($rmPartner);
        $this->assertSame(0, InvestorModel::where('partner_id', $rmPartner->id)->count());

        $blockedEmail = $this->email();
        $blocked = $this->callDistributorSave('/api/v1/business/channel-partner/create', [
            'partner_type' => PartnerTypeEnum::institution->value,
            'name' => 'API Institution',
            'mobile_number' => $this->mobile(),
            'email' => $blockedEmail,
            'gender' => 'Male',
            'password' => 'secret-pass',
            'commission' => 1,
            'is_primary_access' => 1,
            'is_secondary_access' => 0,
            'is_preipo_access' => 0,
        ]);
        $this->assertSame(0, $blocked->getData(true)['status'] ?? null, json_encode($blocked->getData(true)));
        $this->assertNull(PartnerModel::where('email', $blockedEmail)->first());
    }

    public function test_admin_create_requires_cml_and_saves_self_investor_kyc_in_one_transaction(): void
    {
        foreach ([
            PartnerTypeEnum::wealthmanager,
            PartnerTypeEnum::distributor,
            PartnerTypeEnum::retailer,
            PartnerTypeEnum::institution,
        ] as $type) {
            $email = $this->email();
            $response = $this->callDistributorSave('/admin/partner/create', $this->adminPartnerPayload($type, $email));
            $this->assertTrue($response->isRedirection());
            $errors = session('errors');
            $this->assertNotNull($errors, $type->value.' create did not validate');
            $this->assertTrue($errors->has('cml_file'), $type->value.' errors: '.json_encode($errors->all()));
            $this->assertTrue($errors->has('kyc_name'), $type->value.' did not require kyc_name');
            $this->assertNull(PartnerModel::where('email', $email)->first());
        }

        $rmEmail = $this->email();
        $parent = $this->makePartner(PartnerTypeEnum::distributor, ['commission' => 10]);
        $rm = $this->callDistributorSave('/admin/partner/relation-manager/save', array_merge(
            $this->adminPartnerPayload(PartnerTypeEnum::relationmanager, $rmEmail),
            ['parent_partner_id' => $parent->id]
        ));
        $this->assertTrue($rm->isRedirection(), json_encode(session('errors') ? session('errors')->all() : []));
        $this->assertFalse(session('errors') && session('errors')->has('cml_file'));
        $rmPartner = PartnerModel::where('email', $rmEmail)->first();
        $this->assertNotNull($rmPartner);
        $this->assertSame(0, InvestorModel::where('partner_id', $rmPartner->id)->count());

        $email = $this->email();
        $levels = [];
        PartnerModel::created(function (PartnerModel $partner) use (&$levels, $email) {
            if ($partner->email === $email) {
                $levels['partner'] = DB::transactionLevel();
            }
        });
        InvestorDematAccountModel::created(function () use (&$levels) {
            $levels['demat'] = DB::transactionLevel();
        });

        $pdf = $this->makeCmlPdf();
        $saved = $this->callDistributorSave('/admin/partner/institution/save', array_merge(
            $this->adminPartnerPayload(PartnerTypeEnum::institution, $email),
            [
                'dp_id' => 'IN123456',
                'client_id' => '99887766',
                'pan_no' => 'ABCDE1234F',
                'kyc_name' => 'KYC Person Name',
                'account_number' => '123456789012',
                'ifsc_code' => 'HDFC0001234',
                'bank_name' => 'HDFC',
                'cml_file' => $pdf,
            ]
        ));

        $this->assertTrue($saved->isRedirection(), json_encode(session('errors') ? session('errors')->all() : $saved->getContent()));
        $this->assertFalse(session('errors') && session('errors')->has('cml_file'), json_encode(session('error')));

        $partner = PartnerModel::where('email', $email)->first();
        $this->assertNotNull($partner, (string) session('error'));
        $this->assertSame('KYC Person Name', $partner->name);
        $investors = InvestorModel::where('partner_id', $partner->id)->where('is_self', 1)->get();
        $this->assertCount(1, $investors);
        $this->assertSame('KYC Person Name', $investors->first()->name);

        $demat = InvestorDematAccountModel::where('investor_id', $investors->first()->id)->first();
        $this->assertNotNull($demat);
        $this->assertSame('IN123456', $demat->dp_id);
        $this->assertGreaterThanOrEqual(2, $levels['partner'] ?? 0);
        $this->assertGreaterThanOrEqual(2, $levels['demat'] ?? 0);

        if ($demat->document_id) {
            $path = DocumentsModel::where('id', $demat->document_id)->value('path');
            if ($path) {
                $this->storagePaths[] = $path;
            }
        }
    }

    public function test_login_and_profile_include_self_investor_id(): void
    {
        $repo = app(PartnerRepository::class);
        $partner = $this->makePartner(PartnerTypeEnum::wealthmanager, [
            'password' => Hash::make('secret-pass'),
        ]);
        $repo->createSelfInvestor($partner);
        $selfId = InvestorModel::where('partner_id', $partner->id)->where('is_self', 1)->value('id');

        $login = $this->postJson('/api/v1/business/login', [
            'mobile_no' => $partner->mobile_number,
            'password' => 'secret-pass',
            'firebase_token' => 'test-token',
            'device_id' => 'device-'.$partner->id,
            'device' => 'android',
        ]);
        $login->assertOk();
        $this->assertSame(1, $login->json('status'), json_encode($login->json()));
        $this->assertSame((int) $selfId, (int) $login->json('data.self_investor_id'));

        $this->actingAs($partner, 'partner-api-guard');
        $profile = $this->getJson('/api/v1/business/profile');
        $profile->assertOk();
        $this->assertSame((int) $selfId, (int) $profile->json('data.self_investor_id'));

        $manager = $this->makePartner(PartnerTypeEnum::relationmanager, [
            'password' => Hash::make('secret-pass'),
        ]);
        $rmLogin = $this->postJson('/api/v1/business/login', [
            'mobile_no' => $manager->mobile_number,
            'password' => 'secret-pass',
            'firebase_token' => 'test-token',
            'device_id' => 'device-rm-'.$manager->id,
            'device' => 'android',
        ]);
        $rmLogin->assertOk();
        $this->assertSame(1, $rmLogin->json('status'), json_encode($rmLogin->json()));
        $this->assertNull($rmLogin->json('data.self_investor_id'));

        $this->actingAs($manager, 'partner-api-guard');
        $rmProfile = $this->getJson('/api/v1/business/profile');
        $rmProfile->assertOk();
        $this->assertNull($rmProfile->json('data.self_investor_id'));
    }

    public function test_v2_investor_list_mirrors_v1_and_prepends_matching_self_investor(): void
    {
        $partner = $this->makePartner(PartnerTypeEnum::wealthmanager, ['commission' => 5]);
        $manager = $this->makePartner(PartnerTypeEnum::relationmanager, ['parent_id' => $partner->id]);
        $other = $this->makePartner(PartnerTypeEnum::distributor);

        $self = $this->makeInvestor($partner->id, ['is_self' => 1, 'preipo_kyc_status' => 0, 'is_active' => 1, 'name' => 'Self Person']);
        $client = $this->makeInvestor($partner->id, ['is_self' => 0, 'preipo_kyc_status' => 1, 'is_active' => 1, 'name' => 'Client Person']);
        $inactive = $this->makeInvestor($partner->id, ['is_self' => 0, 'preipo_kyc_status' => 1, 'is_active' => 0, 'name' => 'Inactive Person']);
        $rmClient = $this->makeInvestor($manager->id, ['is_self' => 0, 'preipo_kyc_status' => 1, 'is_active' => 1, 'name' => 'RM Client']);
        $stranger = $this->makeInvestor($other->id, ['is_self' => 0, 'preipo_kyc_status' => 1, 'is_active' => 1, 'name' => 'Stranger']);

        $this->actingAs($partner, 'partner-api-guard');
        $query = ['is_kyc' => 'All', 'is_active' => 'Yes', 'is_aif' => 'All'];
        $v1 = $this->getJson('/api/v1/business/investor?'.http_build_query($query));
        $v2 = $this->getJson('/api/v2/business/investor?'.http_build_query($query));
        $v1->assertOk();
        $v2->assertOk();
        $this->assertSame(1, $v1->json('status'), json_encode($v1->json()));
        $this->assertSame(1, $v2->json('status'), json_encode($v2->json()));

        $v1ids = collect($v1->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $v2rows = $v2->json('data');
        $v2ids = collect($v2rows)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertSame((int) $self->id, $v2ids[0]);
        $this->assertEqualsCanonicalizing($v1ids, array_slice($v2ids, 1));
        $this->assertNotContains((int) $self->id, $v1ids);
        $this->assertContains((int) $client->id, $v2ids);
        $this->assertContains((int) $rmClient->id, $v2ids);
        $this->assertNotContains((int) $inactive->id, $v2ids);
        $this->assertNotContains((int) $stranger->id, $v2ids);

        foreach (array_slice($v2rows, 1) as $row) {
            $this->assertEquals(0, $row['is_self']);
        }

        $kyc = $this->getJson('/api/v2/business/investor?'.http_build_query([
            'is_kyc' => 'Yes',
            'is_active' => 'All',
        ]));
        $kyc->assertOk();
        $kycIds = collect($kyc->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertNotContains((int) $self->id, $kycIds);
        $this->assertContains((int) $client->id, $kycIds);
    }

    public function test_admin_investor_list_hides_self_investors(): void
    {
        $partner = $this->makePartner(PartnerTypeEnum::institution);
        $self = $this->makeInvestor($partner->id, [
            'is_self' => 1,
            'preipo_kyc_status' => 1,
            'is_active' => 1,
            'is_demo' => 0,
            'is_blocked' => 0,
        ]);
        $client = $this->makeInvestor($partner->id, [
            'is_self' => 0,
            'preipo_kyc_status' => 1,
            'is_active' => 1,
            'is_demo' => 0,
            'is_blocked' => 0,
        ]);

        $admin = UserAdminModel::create([
            'role' => 'admin',
            'name' => 'Test Admin',
            'username' => 'test-admin-'.$this->mobile(),
            'email' => $this->email(),
            'password' => Hash::make('secret-pass'),
        ]);
        Auth::guard('admin')->login($admin);

        $request = Request::create('/admin/investor/active', 'GET');
        $route = RouteFacade::getRoutes()->getByName('admin.investor.active');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);

        $view = app(InvestorController::class)->list($request);
        $ids = collect($view->getData()['list'])->pluck('id')->map(fn ($id) => (int) $id);
        $this->assertFalse($ids->contains((int) $self->id));
        $this->assertTrue($ids->contains((int) $client->id));
    }

    public function test_cml_partner_api_requires_investor_and_checks_ownership(): void
    {
        $partner = $this->makePartner(PartnerTypeEnum::wealthmanager);
        $manager = $this->makePartner(PartnerTypeEnum::relationmanager, ['parent_id' => $partner->id]);
        $other = $this->makePartner(PartnerTypeEnum::distributor);

        $self = $this->makeInvestor($partner->id, ['is_self' => 1, 'preipo_kyc_status' => 0, 'name' => 'Original Self']);
        $client = $this->makeInvestor($partner->id, ['is_self' => 0, 'preipo_kyc_status' => 0, 'name' => 'Original Client']);
        $rmClient = $this->makeInvestor($manager->id, ['is_self' => 0, 'preipo_kyc_status' => 0]);
        $deleted = $this->makeInvestor($partner->id, ['is_self' => 0, 'is_deleted' => 1]);
        $otherClient = $this->makeInvestor($other->id, ['is_self' => 0]);
        $otherSelf = $this->makeInvestor($other->id, ['is_self' => 1]);
        $rmSelf = $this->makeInvestor($manager->id, ['is_self' => 1]);

        $this->actingAs($partner, 'partner-api-guard');

        $missing = $this->postJson('/api/v2/business/investor/kyc/cml/save', []);
        $missing->assertOk();
        $this->assertSame(0, $missing->json('status'));
        $this->assertStringContainsString('investor', strtolower((string) $missing->json('message')));

        $missingRead = $this->postJson('/api/v2/business/investor/kyc/cml/read', []);
        $missingRead->assertOk();
        $this->assertSame(0, $missingRead->json('status'));

        $pdf = $this->makeCmlPdf();
        foreach ([$deleted, $otherClient, $otherSelf, $rmSelf] as $forbidden) {
            $read = $this->postJson('/api/v2/business/investor/kyc/cml/read', [
                'investor_id' => $forbidden->id,
                'cml' => $pdf,
            ]);
            $this->assertSame(0, $read->json('status'), json_encode($read->json()));
            $this->assertSame('Investor not found', $read->json('message'));

            $save = $this->post('/api/v2/business/investor/kyc/cml/save', [
                'investor_id' => $forbidden->id,
                'dp_id' => 'IN000001',
                'client_id' => '11111111',
                'pan_no' => 'ABCDE1234F',
                'name' => 'Should Not Save',
            ]);
            $this->assertSame(0, $save->json('status'), json_encode($save->json()));
            $this->assertSame('Investor not found', $save->json('message'));
            $this->assertNull(InvestorDematAccountModel::where('investor_id', $forbidden->id)->first());
        }

        $saveClient = $this->post('/api/v2/business/investor/kyc/cml/save', [
            'investor_id' => $client->id,
            'dp_id' => 'IN000002',
            'client_id' => '22222222',
            'pan_no' => 'ABCDE1234F',
            'name' => 'Saved Client',
        ]);
        $saveClient->assertOk();
        $this->assertSame(1, $saveClient->json('status'), json_encode($saveClient->json()));
        $clientDemat = InvestorDematAccountModel::where('investor_id', $client->id)->first();
        $this->assertNotNull($clientDemat);
        $this->assertSame($client->id, (int) $clientDemat->investor_id);
        $client->refresh();
        $this->assertSame('Saved Client', $client->name);
        $this->assertEquals(1, $client->preipo_kyc_status);

        $saveRm = $this->post('/api/v2/business/investor/kyc/cml/save', [
            'investor_id' => $rmClient->id,
            'dp_id' => 'IN000003',
            'client_id' => '33333333',
            'pan_no' => 'ABCDE1234F',
            'name' => 'Saved RM Client',
        ]);
        $this->assertSame(1, $saveRm->json('status'), json_encode($saveRm->json()));
        $this->assertNotNull(InvestorDematAccountModel::where('investor_id', $rmClient->id)->first());

        $parsed = (new DematPdfParsingService())->processPdf($pdf, null);
        if (empty($parsed['success'])) {
            $this->markTestIncomplete('Generated CML PDF was not parsed, so read was not called because a failed read uploads the file. '.$parsed['message']);
        }

        $before = (int) $self->preipo_kyc_status;
        $readSelf = $this->post('/api/v2/business/investor/kyc/cml/read', [
            'investor_id' => $self->id,
            'cml' => $pdf,
        ]);
        $readSelf->assertOk();
        $this->assertSame(1, $readSelf->json('status'), json_encode($readSelf->json()));
        $self->refresh();
        $this->assertEquals($before, $self->preipo_kyc_status);
        $this->assertSame('Original Self', $self->name);
    }

    public function test_create_blades_include_cml_partial_and_relation_manager_does_not(): void
    {
        $compiler = app('blade.compiler');
        foreach ([
            'admin.pages.partner.wealthmanger.create',
            'admin.pages.partner.distributor.create',
            'admin.pages.partner.retailers.create',
            'admin.pages.partner.institution.create',
        ] as $view) {
            $compiled = $compiler->compileString(file_get_contents(resource_path('views/'.str_replace('.', '/', $view).'.blade.php')));
            $this->assertStringContainsString('admin.pages.partner.child.cml-kyc', $compiled, $view);
        }

        $rm = $compiler->compileString(file_get_contents(resource_path(
            'views/admin/pages/partner/relationalmanager/create.blade.php'
        )));
        $this->assertStringNotContainsString('admin.pages.partner.child.cml-kyc', $rm);

        view()->share('errors', new ViewErrorBag());
        $html = view('admin.pages.partner.child.cml-kyc')->render();
        $this->assertStringContainsString('name="kyc_name"', $html);
        $this->assertStringContainsString('name="cml_file"', $html);
    }

    private function callDistributorSave(string $path, array $payload, ?string $uuid = null)
    {
        $files = [];
        if (isset($payload['cml_file'])) {
            $files['cml_file'] = $payload['cml_file'];
            unset($payload['cml_file']);
        }

        $request = Request::create($path, 'POST', $payload, [], $files);
        $session = $this->app['session.store'];
        if (!$session->isStarted()) {
            $session->start();
        }
        $session->forget('errors');
        $session->forget('error');
        $request->setLaravelSession($session);

        if ($uuid) {
            $route = new Route(['POST'], 'admin/partner/update/{uuid}', fn () => null);
            $route->name('tests.partner.update');
            $request = Request::create('/admin/partner/update/'.$uuid, 'POST', $payload, [], $files);
            $request->setLaravelSession($session);
            $route->bind($request);
            $request->setRouteResolver(fn () => $route);
        } else {
            $request->setRouteResolver(fn () => null);
        }

        $this->app->instance('request', $request);

        return app(PartnerRepository::class)->newDistributorSave();
    }

    private function adminPartnerPayload(PartnerTypeEnum $type, string $email): array
    {
        $payload = [
            'partner_type' => $type->value,
            'name' => $type->name.' Create',
            'mobile_number' => $this->mobile(),
            'email' => $email,
            'gender' => 'Male',
            'password' => 'secret-pass',
            'is_primary_access' => 1,
            'is_secondary_access' => 0,
            'is_preipo_access' => 0,
        ];
        if ($type !== PartnerTypeEnum::relationmanager) {
            $payload['commission'] = 3;
        }

        return $payload;
    }

    private function createApprovedCompany(PartnerModel $institution): CompanyModel
    {
        $this->actingAs($institution, 'partner-api-guard');
        $response = $this->postJson('/api/v2/business/institution/company', $this->companyPayload($this->sectorId(), 'Deal Co'));
        $response->assertOk();
        $this->assertSame(1, $response->json('status'), json_encode($response->json()));

        return CompanyModel::findOrFail($response->json('data.id'));
    }

    private function companyPayload(int $sectorId, string $label): array
    {
        $token = $label.' '.$this->mobile();

        return [
            'type' => 'unlisted',
            'cin' => 'U'.substr(str_replace('.', '', uniqid('', true)), 0, 20),
            'brand_name' => $token,
            'company_name' => $token.' Pvt Ltd',
            'sector' => $sectorId,
            'about' => 'Session test company',
            'min_investment_amount' => 1,
            'lot_size' => '1',
            'market_cap' => 1,
            'pe_ratio' => 1,
            'pb_ratio' => 1,
            'debt_to_equity' => 1,
            'roe' => 1,
            'book_value' => 1,
            'face_value' => 1,
        ];
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

    private function makeSeller(): SellerMasterModel
    {
        $seller = new SellerMasterModel();
        $seller->pan = 'ABCDE1234F';
        $seller->company_name = 'Seller '.$this->mobile();
        $seller->address = 'Test address';
        $seller->dp_id = 'IN123456';
        $seller->client_id = '12345678';
        $seller->bank_name = 'Test Bank';
        $seller->account_number = '1234567890';
        $seller->ifsc = 'HDFC0001234';
        $seller->branch = 'Main';
        $seller->mobile_number = $this->mobile();
        $seller->email = $this->email();
        $seller->password = Hash::make('secret-pass');
        $seller->is_deleted = '0';
        $seller->save();

        return $seller;
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

    private function makeCmlPdf(): UploadedFile
    {
        $html = '<html><body><p>DP ID: 12345678 Client ID: 99887766 First Holder Name: TEST USER PAN ABCDE1234F Bank A/c No 123456789012 IFSC Code: HDFC0001234</p></body></html>';
        $binary = Pdf::loadHTML($html)->output();
        $path = storage_path('framework/testing-cml-'.uniqid('', true).'.pdf');
        file_put_contents($path, $binary);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, 'cml.pdf', 'application/pdf', null, true);
    }

    private function rebindFee(string $value): void
    {
        $settings = \App\Models\AppSettingsModel::query()->get()->reject(
            fn ($row) => $row->key === 'processing_fee_percentage'
        )->values();
        $row = new \App\Models\AppSettingsModel();
        $row->key = 'processing_fee_percentage';
        $row->value = $value;
        $settings->push($row);
        $this->app->instance(GlobalSettingModel::class, new GlobalSettingModel($settings));
    }

    private function afterCommitCount(): int
    {
        return count($this->afterCommitCallbacks());
    }

    private function runNewAfterCommitCallbacks(int $before): void
    {
        $callbacks = array_slice($this->afterCommitCallbacks(), $before);
        foreach ($callbacks as $callback) {
            $callback();
        }
    }

    private function afterCommitCallbacks(): array
    {
        $connection = DB::connection();
        $manager = (function () {
            return $this->transactionsManager;
        })->call($connection);

        $callbacks = [];
        foreach ($manager->getPendingTransactions() as $transaction) {
            foreach ($transaction->getCallbacks() as $callback) {
                $callbacks[] = $callback;
            }
        }
        foreach ($manager->getCommittedTransactions() as $transaction) {
            foreach ($transaction->getCallbacks() as $callback) {
                $callbacks[] = $callback;
            }
        }

        return $callbacks;
    }

    private function mobile(): string
    {
        $this->seq++;

        return sprintf('8%09d', (int) (fmod(microtime(true), 100000) * 1000) + $this->seq);
    }

    private function email(): string
    {
        return 'session-test-'.bin2hex(random_bytes(6)).'@example.test';
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }
}
