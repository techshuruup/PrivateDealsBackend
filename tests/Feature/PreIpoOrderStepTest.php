<?php

namespace Tests\Feature;

use App\Enums\DocumentTypeEnum;
use App\Enums\PartnerTypeEnum;
use App\Enums\PreIpoOrderStepEnum;
use App\Http\Controllers\Web\Admin\PreIpoTransactionController;
use App\Http\Middleware\ApiHeaderAuthMiddleware;
use App\Models\CompanyDealModel;
use App\Models\CompanyModel;
use App\Models\DocumentsModel;
use App\Models\InvestorModel;
use App\Models\MasterSectorsModel;
use App\Models\PartnerModel;
use App\Models\PortfolioPreIpoModel;
use App\Models\PreIpoModel;
use App\Models\UserAdminModel;
use App\Services\PreIpoOrderStepService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PreIpoOrderStepTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ApiHeaderAuthMiddleware::class);
        Http::fake([
            '*create_sign_request*' => Http::response([
                'id' => 'DID-ORDER-STEP-TEST',
                'signing_parties' => [[
                    'authentication_url' => 'https://digio.test/mandate',
                    'expire_on' => '2030-01-01 00:00:00',
                ]],
            ], 200),
        ]);
    }

    public function test_create_sets_mandate_pending_and_partner_cancel_stays_hidden(): void
    {
        [$buyer, $institution, $client, $deal] = $this->scene();
        $this->actingAs($buyer, 'partner-api-guard');

        $created = $this->postJson('/api/v2/business/pre-ipo/buy', [
            'orders' => [[
                'deal_uuid' => $deal->uuid,
                'investor_id' => $client->id,
                'shares' => 2,
                'share_price' => 102,
            ]],
        ]);
        $created->assertOk();
        $this->assertSame(1, $created->json('status'), json_encode($created->json()));
        $this->assertSame('Orders placed successfully.', $created->json('message'));
        $this->assertTrue($created->json('data.0.mandate_sent'));
        $this->assertSame('mandate_pending', $created->json('data.0.order_step'));
        $this->assertSame('Transaction initiated. Buy mandate generated.', $created->json('data.0.current_step'));
        $this->assertArrayNotHasKey('status_list', $created->json('data.0'));
        $this->assertContains('cancel', $created->json('data.0.action'));

        $order = PreIpoModel::find($created->json('data.0.id'));
        $this->assertSame(0, (int) $order->status);
        $this->assertSame(PreIpoOrderStepEnum::mandate_pending->value, $order->order_step);
        $this->assertNotNull(
            DocumentsModel::where('type', DocumentTypeEnum::buymandate->value)
                ->whereJsonContains('meta->preipo_transactions', $order->id)
                ->first()
        );

        $this->actingAs($institution, 'partner-api-guard');
        $hidden = $this->getJson('/api/v2/business/institution/pre-ipo/transaction');
        $hidden->assertOk();
        $this->assertSame(1, $hidden->json('status'));
        $this->assertNotContains($order->id, collect($hidden->json('data'))->pluck('id')->all());

        $this->actingAs($buyer, 'partner-api-guard');
        $cancel = $this->postJson('/api/v2/business/pre-ipo/transaction/cancel', [
            'transaction_id' => $order->id,
            'reason' => 'Investor changed their mind',
        ]);
        $cancel->assertOk();
        $this->assertSame(1, $cancel->json('status'), json_encode($cancel->json()));
        $order->refresh();
        $this->assertSame('cancelled', $order->order_step);
        $this->assertSame(0, (int) $order->status);
        $this->assertSame('Investor changed their mind', $order->cancellation_reason);

        $again = $this->postJson('/api/v2/business/pre-ipo/transaction/cancel', [
            'transaction_id' => $order->id,
            'reason' => 'Too late',
        ]);
        $this->assertSame(0, $again->json('status'));

        $this->actingAs($institution, 'partner-api-guard');
        $stillHidden = $this->getJson('/api/v2/business/institution/pre-ipo/transaction');
        $this->assertNotContains($order->id, collect($stillHidden->json('data'))->pluck('id')->all());

        $legacy = new PreIpoModel();
        $legacy->status = 0;
        $legacy->investor_id = $client->id;
        $legacy->company_id = $deal->company_id;
        $legacy->shares = 1;
        $legacy->share_price = 10;
        $legacy->investment_amount = 10;
        $legacy->payable_amount = 10;
        $legacy->is_distributer = 0;
        $legacy->instrument = 'equity';
        $legacy->payment_mode = 'RTGS';
        $legacy->save();

        $this->actingAs($buyer, 'partner-api-guard');
        $list = $this->getJson('/api/v2/business/pre-ipo/transaction-list');
        $legacyRow = collect($list->json('data'))->firstWhere('id', $legacy->id);
        $this->assertNotNull($legacyRow);
        $this->assertArrayHasKey('status_list', $legacyRow);
        $this->assertArrayNotHasKey('order_step', $legacyRow);
    }

    public function test_order_step_list_and_detail_omit_the_old_status_payload(): void
    {
        [$buyer, $institution, $client, $deal] = $this->scene();
        $order = $this->place($buyer, $client, $deal);

        $this->actingAs($buyer, 'partner-api-guard');
        $listRow = collect($this->getJson('/api/v2/business/pre-ipo/transaction-list')->json('data'))
            ->firstWhere('id', $order->id);
        $this->assertNotNull($listRow);
        $this->assertOrderStepPayload($listRow);
        $this->assertSame('mandate_pending', $listRow['order_step']);
        $this->assertSame('Transaction initiated. Buy mandate generated.', $listRow['current_step']);
        $this->assertSame(
            'Ask the investor to sign the mandate sent by SMS and WhatsApp.',
            $listRow['next_step']
        );
        $this->assertNull($listRow['payment_details']);
        $this->assertSame(['id', 'name'], array_keys($listRow['investor']));
        $this->assertSame($client->id, $listRow['investor']['id']);

        $detail = $this->getJson('/api/v2/business/pre-ipo/transaction/detail?transaction_id='.$order->id);
        $this->assertSame(1, $detail->json('status'), json_encode($detail->json()));
        $this->assertOrderStepPayload($detail->json('data'));
        $this->assertSame($listRow['current_step'], $detail->json('data.current_step'));
        $this->assertSame($listRow['next_step'], $detail->json('data.next_step'));

        app(PreIpoOrderStepService::class)->onMandateSigned($order->fresh());

        $this->actingAs($institution, 'partner-api-guard');
        $institutionRow = collect($this->getJson('/api/v2/business/institution/pre-ipo/transaction')->json('data'))
            ->firstWhere('id', $order->id);
        $this->assertNotNull($institutionRow);
        $this->assertOrderStepPayload($institutionRow);
        $this->assertSame('Mandate signed.', $institutionRow['current_step']);
        $this->assertSame('Approve the transaction or cancel with a reason.', $institutionRow['next_step']);
        $this->assertSame(['approve', 'reject'], $institutionRow['action']);

        $institutionDetail = $this->getJson('/api/v2/business/institution/pre-ipo/transaction/detail?transaction_id='.$order->id);
        $this->assertSame(1, $institutionDetail->json('status'), json_encode($institutionDetail->json()));
        $this->assertOrderStepPayload($institutionDetail->json('data'));
        $this->assertSame('Mandate signed.', $institutionDetail->json('data.current_step'));
        $this->assertSame('Approve the transaction or cancel with a reason.', $institutionDetail->json('data.next_step'));
    }

    public function test_buy_keeps_the_order_when_the_mandate_send_fails(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([
            '*' => Http::response(['message' => 'digio down'], 500),
        ]);

        [$buyer, $institution, $client, $deal] = $this->scene();
        $this->actingAs($buyer, 'partner-api-guard');
        $created = $this->postJson('/api/v2/business/pre-ipo/buy', [
            'orders' => [[
                'deal_uuid' => $deal->uuid,
                'investor_id' => $client->id,
                'shares' => 2,
                'share_price' => 102,
            ]],
        ]);

        $this->assertSame(1, $created->json('status'), json_encode($created->json()));
        $this->assertSame('Orders placed. The buy mandate could not be sent.', $created->json('message'));
        $this->assertFalse($created->json('data.0.mandate_sent'));
        $this->assertSame('mandate_pending', $created->json('data.0.order_step'));
        $this->assertSame(0, (int) PreIpoModel::find($created->json('data.0.id'))->status);
    }

    public function test_institution_approve_and_reject_only_after_share_confirmation(): void
    {
        [$buyer, $institution, $client, $deal] = $this->scene();
        $outsider = $this->makePartner(PartnerTypeEnum::distributor);
        $approveOrder = $this->place($buyer, $client, $deal);
        $rejectOrder = $this->place($buyer, $client, $deal);

        $this->actingAs($institution, 'partner-api-guard');
        $before = $this->postJson('/api/v2/business/institution/pre-ipo/transaction/approve', [
            'transaction_id' => $approveOrder->id,
        ]);
        $this->assertSame(0, $before->json('status'), json_encode($before->json()));
        $this->assertSame('mandate_pending', $approveOrder->fresh()->order_step);

        $this->actingAs($outsider, 'partner-api-guard');
        $denied = $this->getJson('/api/v2/business/institution/pre-ipo/transaction');
        $this->assertSame(0, $denied->json('status'));

        app(PreIpoOrderStepService::class)->onMandateSigned($approveOrder->fresh());
        app(PreIpoOrderStepService::class)->onMandateSigned($rejectOrder->fresh());
        $this->rememberSignedMandate($rejectOrder->fresh());

        $this->actingAs($institution, 'partner-api-guard');
        $visible = collect($this->getJson('/api/v2/business/institution/pre-ipo/transaction')->json('data'))->pluck('id')->all();
        $this->assertContains($approveOrder->id, $visible);
        $this->assertContains($rejectOrder->id, $visible);

        $approved = $this->postJson('/api/v2/business/institution/pre-ipo/transaction/approve', [
            'transaction_id' => $approveOrder->id,
        ]);
        $this->assertSame(1, $approved->json('status'), json_encode($approved->json()));
        $approveOrder->refresh();
        $this->assertSame('deal_slip_pending', $approveOrder->order_step);
        $this->assertSame(0, (int) $approveOrder->status);
        $this->assertNotNull(
            DocumentsModel::where('type', DocumentTypeEnum::preipodealslip->value)
                ->whereJsonContains('meta->preipo_transactions', $approveOrder->id)
                ->first()
        );

        $rejected = $this->postJson('/api/v2/business/institution/pre-ipo/transaction/reject', [
            'transaction_id' => $rejectOrder->id,
            'reason' => 'Shares are not available',
        ]);
        $this->assertSame(1, $rejected->json('status'), json_encode($rejected->json()));
        $rejectOrder->refresh();
        $this->assertSame('cancelled', $rejectOrder->order_step);
        $this->assertSame('Shares are not available', $rejectOrder->cancellation_reason);
        $this->assertSame(0, (int) $rejectOrder->status);

        $stillListed = collect($this->getJson('/api/v2/business/institution/pre-ipo/transaction')->json('data'))->pluck('id')->all();
        $this->assertContains($rejectOrder->id, $stillListed);

        $secondApprove = $this->postJson('/api/v2/business/institution/pre-ipo/transaction/approve', [
            'transaction_id' => $rejectOrder->id,
        ]);
        $this->assertSame(0, $secondApprove->json('status'));
    }

    public function test_payment_receipt_confirm_and_share_transfer_write_portfolio(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        [$buyer, $institution, $client, $deal] = $this->scene();
        $order = $this->place($buyer, $client, $deal);
        app(PreIpoOrderStepService::class)->onMandateSigned($order->fresh());
        $this->actingAs($institution, 'partner-api-guard');
        $this->postJson('/api/v2/business/institution/pre-ipo/transaction/approve', [
            'transaction_id' => $order->id,
        ])->assertOk();
        app(PreIpoOrderStepService::class)->onDealSlipSigned($order->fresh());
        $this->assertSame('payment_pending', $order->fresh()->order_step);

        $this->actingAs($buyer, 'partner-api-guard');
        $earlyConfirm = $this->postJson('/api/v2/business/pre-ipo/transaction/confirm-share-transfer', [
            'transaction_id' => $order->id,
        ]);
        $this->assertSame(0, $earlyConfirm->json('status'));

        $receipt = $this->post('/api/v2/business/pre-ipo/transaction/payment-receipt', [
            'transaction_id' => $order->id,
            'file' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
        ]);
        $this->assertSame(1, $receipt->json('status'), json_encode($receipt->json()));
        $this->assertSame('payment_confirmation_pending', $order->fresh()->order_step);
        $this->assertSame(0, (int) $order->fresh()->status);

        $this->actingAs($institution, 'partner-api-guard');
        $tooSoon = $this->post('/api/v2/business/institution/pre-ipo/transaction/share-transfer-receipt', [
            'transaction_id' => $order->id,
            'file' => UploadedFile::fake()->create('transfer.pdf', 20, 'application/pdf'),
        ]);
        $this->assertSame(0, $tooSoon->json('status'));

        $confirmed = $this->postJson('/api/v2/business/institution/pre-ipo/transaction/confirm-payment', [
            'transaction_id' => $order->id,
        ]);
        $this->assertSame(1, $confirmed->json('status'), json_encode($confirmed->json()));
        $this->assertSame('share_transfer_pending', $order->fresh()->order_step);

        $wrongReceipt = $this->actingAs($buyer, 'partner-api-guard')->post('/api/v2/business/pre-ipo/transaction/payment-receipt', [
            'transaction_id' => $order->id,
            'file' => UploadedFile::fake()->create('again.pdf', 20, 'application/pdf'),
        ]);
        $this->assertSame(0, $wrongReceipt->json('status'));

        $this->actingAs($institution, 'partner-api-guard');
        $transfer = $this->post('/api/v2/business/institution/pre-ipo/transaction/share-transfer-receipt', [
            'transaction_id' => $order->id,
            'file' => UploadedFile::fake()->create('transfer.pdf', 20, 'application/pdf'),
        ]);
        $this->assertSame(1, $transfer->json('status'), json_encode($transfer->json()));
        $this->assertSame('share_transfer_confirmation_pending', $order->fresh()->order_step);

        $this->actingAs($buyer, 'partner-api-guard');
        $done = $this->postJson('/api/v2/business/pre-ipo/transaction/confirm-share-transfer', [
            'transaction_id' => $order->id,
        ]);
        $this->assertSame(1, $done->json('status'), json_encode($done->json()));
        $order->refresh();
        $this->assertSame('completed', $order->order_step);
        $this->assertSame(0, (int) $order->status);
        $this->assertNotNull($order->portfolio_id);
        $portfolio = PortfolioPreIpoModel::find($order->portfolio_id);
        $this->assertNotNull($portfolio);
        $this->assertSame((int) $order->shares, (int) $portfolio->shares);
        $this->assertEquals((float) $order->investment_amount, (float) $portfolio->investment_amount);

        $admin = UserAdminModel::create([
            'role' => 'admin',
            'name' => 'Order Step Admin',
            'username' => 'order-step-'.$this->mobile(),
            'email' => $this->email(),
            'password' => Hash::make('secret-pass'),
        ]);
        Auth::guard('admin')->login($admin);
        $blocked = new PreIpoModel();
        $blocked->status = 0;
        $blocked->order_step = PreIpoOrderStepEnum::share_confirmation_pending->value;
        $blocked->investor_id = $client->id;
        $blocked->company_id = $deal->company_id;
        $blocked->partner_id = $institution->id;
        $blocked->shares = 1;
        $blocked->share_price = 10;
        $blocked->investment_amount = 10;
        $blocked->payable_amount = 10;
        $blocked->is_distributer = 1;
        $blocked->instrument = 'equity';
        $blocked->payment_mode = 'RTGS';
        $blocked->save();
        $this->app->instance('request', Request::create('/admin/pre-ipo-transactions/approve-transaction', 'POST', [
            'status' => 'approve',
            'transaction' => $blocked->id,
        ]));
        $oldApprove = app(PreIpoTransactionController::class)->approveTransaction();
        $this->assertSame(0, $oldApprove->getData()->status);
        $this->assertSame(0, (int) $blocked->fresh()->status);
        $this->assertSame('share_confirmation_pending', $blocked->fresh()->order_step);
    }

    private function assertOrderStepPayload(array $row): void
    {
        foreach ([
            'status_list',
            'current_status',
            'percentage',
            'is_processing',
            'deal_slip',
            'approval_file',
            'rejection_file',
            'seller',
            'status',
            'current',
        ] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $row);
        }

        $this->assertEqualsCanonicalizing([
            'id',
            'transaction_invoice_no',
            'order_step',
            'current_step',
            'next_step',
            'action',
            'sign_link',
            'investor',
            'company',
            'deal_id',
            'shares',
            'base_price',
            'distributer_price',
            'share_price',
            'investment_amount',
            'payable_amount',
            'cancellation_reason',
            'payment_details',
            'payment_receipt',
            'share_transfer_receipt',
            'created_at',
        ], array_keys($row));
        $this->assertArrayHasKey('order_step', $row);
        $this->assertArrayHasKey('current_step', $row);
        $this->assertArrayHasKey('next_step', $row);
        $this->assertEqualsCanonicalizing(['id', 'brand_name', 'logo'], array_keys($row['company']));
    }

    private function scene(): array
    {
        $institution = $this->makePartner(PartnerTypeEnum::institution);
        $this->makeInvestor($institution->id, ['is_self' => 1, 'is_demo' => 1]);
        $company = $this->createApprovedCompany($institution);
        $deal = CompanyDealModel::create([
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
            'available_quantity' => 50,
        ]);
        $buyer = $this->makePartner(PartnerTypeEnum::distributor);
        $client = $this->makeInvestor($buyer->id, ['is_self' => 0, 'is_demo' => 1]);

        return [$buyer, $institution, $client, $deal];
    }

    private function place(PartnerModel $buyer, InvestorModel $client, CompanyDealModel $deal): PreIpoModel
    {
        $this->actingAs($buyer, 'partner-api-guard');
        $response = $this->postJson('/api/v2/business/pre-ipo/buy', [
            'orders' => [[
                'deal_uuid' => $deal->uuid,
                'investor_id' => $client->id,
                'shares' => 2,
                'share_price' => 110,
            ]],
        ]);
        $this->assertSame(1, $response->json('status'), json_encode($response->json()));

        return PreIpoModel::findOrFail($response->json('data.0.id'));
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

    private function createApprovedCompany(PartnerModel $institution): CompanyModel
    {
        $this->actingAs($institution, 'partner-api-guard');
        $response = $this->postJson('/api/v2/business/institution/company', [
            'type' => 'unlisted',
            'cin' => 'U'.substr(str_replace('.', '', uniqid('', true)), 0, 20),
            'brand_name' => 'Order Step '.$this->mobile(),
            'company_name' => 'Order Step '.$this->mobile().' Pvt Ltd',
            'sector' => $this->sectorId(),
            'about' => 'Order step test company',
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
        $this->assertNotNull($id);

        return (int) $id;
    }

    private function makePartner(PartnerTypeEnum $type, array $overrides = []): PartnerModel
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

    private function makeInvestor(int $partnerId, array $overrides = []): InvestorModel
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
        $investor->is_self = $overrides['is_self'] ?? 0;
        $investor->is_deleted = '0';
        $investor->is_active = 1;
        $investor->is_blocked = 0;
        $investor->is_demo = $overrides['is_demo'] ?? 1;
        $investor->preipo_kyc_status = 0;
        $investor->aif_status = 0;
        $investor->registration_step = '3';
        $investor->save();

        return $investor;
    }

    private function mobile(): string
    {
        $this->seq++;

        return sprintf('8%09d', (int) (fmod(microtime(true), 100000) * 1000) + $this->seq);
    }

    private function email(): string
    {
        return 'order-step-'.bin2hex(random_bytes(6)).'@example.test';
    }
}
