<?php

namespace App\Jobs\preipo;

use App\Enums\NotificationTypeEnum;
use App\Enums\WpMessageTypeEnum;
use App\Helpers\AdminHelper;
use App\Helpers\BseCalendarHelper;
use App\Helpers\CommonHelper;
use App\Helpers\UtillsHelper;
use App\Models\BseHolidayModel;
use App\Models\InvestorModel;
use App\Models\PreIpoModel;
use App\Models\UserAdminModel;
use App\Services\PreIpoBusinessDayService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuyNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $transaction_id;
    public function __construct(string $transaction_id)
    {
        $this->transaction_id = $transaction_id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $transaction = PreIpoModel::where('id', $this->transaction_id)->first();
        if ($transaction && $transaction->is_valid == 1) {
            if ($transaction->usesOrderStep()) {
                return;
            }
            if ($transaction->investor && $transaction->investor->is_demo == 0 && $transaction->company) {

                // Build dynamic note for {{6}} in the WhatsApp template
                $note = $this->buildNote();

                $params = [
                    $transaction->investor->name,
                    $transaction->transaction_invoice_no,
                    $transaction->company->brand_name,
                    $transaction->shares,
                    UtillsHelper::moneyFormatIndia($transaction->share_price),
                    $note,
                ];

                $templateName    = 'preipo_commit_investor_2702_sun';
                $templateMessage = 'Your transaction #{{2}} has been created successfully. Company: {{3}} Quantity: {{4}} Buying Price: {{5}} Note : {{6}} For any assistance related to this transaction, please contact our support team.';

                UtillsHelper::sendWpMessage(
                    NotificationTypeEnum::event,
                    $templateName,
                    WpMessageTypeEnum::text,
                    $transaction->investor->mobile_number,
                    $transaction->investor->name,
                    NULL,
                    [],
                    $params
                );
                UtillsHelper::sendNotification(
                    $transaction->investor->id,
                    InvestorModel::class,
                    'preipo-transaction',
                    'Private Equity transaction',
                    UtillsHelper::paramsToTemplate($params, $templateMessage)
                );

                $admins = UserAdminModel::where('is_deleted', '0')->where('id', '!=', '1')->get();
                foreach ($admins as $key => $admin) {
                    if (AdminHelper::hasPermission(['pre ipo transaction'], $admin->id)) {
                        $approveTran = UtillsHelper::tokenUrlGenerate('admin_accept_tran', ['item_id' => $transaction->id, 'admin_id' => $admin->id]);
                        $rejectTran = UtillsHelper::tokenUrlGenerate('admin_reject_tran', ['item_id' => $transaction->id, 'admin_id' => $admin->id]);
                        $params = [
                            $transaction->transaction_invoice_no,
                            $transaction->investor->name,
                            '+' . $transaction->investor->mobile_country_code . '-' . $transaction->investor->mobile_number,
                            $transaction->company->brand_name,
                            $transaction->shares,
                            UtillsHelper::moneyFormatIndia($transaction->share_price),
                            UtillsHelper::moneyFormatIndia($transaction->investment_amount),
                            UtillsHelper::moneyFormatIndia($transaction->payable_amount),
                        ];
                        UtillsHelper::sendWpMessage(
                            NotificationTypeEnum::event,
                            'preipo_commit_admin_3_sun_copy',
                            WpMessageTypeEnum::text,
                            $admin->mobile_no,
                            $admin->name,
                            NULL,
                            [$approveTran, $rejectTran],
                            $params
                        );

                        UtillsHelper::sendNotification(
                            $admin->id,
                            UserAdminModel::class,
                            'preipo-market',
                            'Private Equity transaction',
                            UtillsHelper::paramsToTemplate($params, 'New transaction #{{1}} has been created. Investor Name: {{2}} Ph. No.: {{3}} Company: {{4}} Quantity: {{5}} Buying Price: {{6}} Invested: {{7}} Payable: {{8}} Please review.')
                        );
                    }
                }
            }
        }
    }

    /**
     * Build the dynamic note text for the investor WhatsApp notification ({{6}}).
     *
     * Priority order:
     *  1. BSE Holiday today  → mention holiday name + next process date
     *  2. Weekend            → next process date
     *  3. Outside market window (before open or after cutoff on a trading day) → window times + next process date
     *  4. During working hours → "within N hours"
     */
    private function buildNote(): string
    {
        /** @var PreIpoBusinessDayService $businessDayService */
        $businessDayService = app(PreIpoBusinessDayService::class);

        $now = Carbon::now();

        // ── Condition 1: Today is a BSE holiday ─────────────────────────────
        if (BseCalendarHelper::isBseHoliday($now)) {
            $holiday = BseHolidayModel::active()
                ->whereDate('holiday_date', $now->toDateString())
                ->first();

            $holidayName = $holiday ? $holiday->holiday_name : 'a public holiday';
            $processDate = $businessDayService->nextWorkingDayStart($now)->format('d M Y');

            return "As this transaction was placed during {$holidayName}, it will be processed on {$processDate}.";
        }

        // ── Condition 2: Weekend ─────────────────────────────────────────────
        if ($now->isWeekend()) {
            $processDate = $businessDayService->nextWorkingDayStart($now)->format('d M Y');

            return "Since the transaction was placed over the weekend, our team will process it on {$processDate}.";
        }

        // ── Condition 3: Trading day but outside market window ───────────────
        //    (before market open, or within the last-30-min cutoff, or after close)
        if (!$businessDayService->isWorkingHours($now)) {
            $marketOpen  = CommonHelper::appSettings('peripo_market_open')  ?? '10:00';
            $marketClose = CommonHelper::appSettings('peripo_market_close') ?? '17:30';
            $processDate = $businessDayService->nextWorkingDayStart($now)->format('d M Y');

            // Convert "HH:MM" → "10:30 AM" / "5:30 PM"
            $openFormatted  = Carbon::createFromFormat('H:i', $marketOpen)->format('g:i A');
            $closeFormatted = Carbon::createFromFormat('H:i', $marketClose)->format('g:i A');

            return "As this transaction was placed outside our transaction window ({$openFormatted} – {$closeFormatted}), it will be processed on {$processDate}.";
        }

        // ── Condition 4: During working hours ────────────────────────────────
        $hours = CommonHelper::appSettings('peripo_admin_order_accept_hours') ?? 2;

        return "Our team will confirm the transaction within {$hours} hours.";
    }
}
