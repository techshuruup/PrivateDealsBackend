<?php

namespace App\Console\Commands\preipo;

use App\Helpers\UtillsHelper;
use App\Models\InvestorModel;
use App\Models\PreIpoModel;
use App\Enums\NotificationTypeEnum;
use App\Enums\WpMessageTypeEnum;
use App\Helpers\AdminHelper;
use App\Models\UserAdminModel;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * preipo:investor-deadline-reminder
 *
 * Runs every 30 minutes.
 * Notifies the investor when their 24-hour action window (KYC → deal slip → payment)
 * is within 4 hours of expiry (status 2 or 3, timer not yet expired).
 */
class InvestorDeadlineReminderCommand extends Command
{
    protected $signature   = 'preipo:investor-deadline-reminder';
    protected $description = 'Notify investor when their 24h action window is expiring soon (within 4h)';

    /** Warn investor when less than this many hours remain. */
    // private const WARNING_HOURS = 4;

    public function handle(): void
    {
        $now        = Carbon::now();
        $warnFrom = $now->copy()->addHours(4);           // 4h00m from now
        $warnTo   = $now->copy()->addMinutes(235);        // 3h55m from now (4*60 - 5 = 235)

        // Transactions in investor action window, timer not expired yet, expiring within 4h
        $transactions = PreIpoModel::whereIn('status', [2, 3])
            ->whereNull('order_step')
            ->whereNotNull('transaction_cancel_timer')
            ->where('transaction_cancel_timer', '>', $warnTo)
            ->where('transaction_cancel_timer', '<=', $warnFrom)
            ->with(['investor', 'company'])
            ->get();

        if ($transactions->isEmpty()) {
            return;
        }

        foreach ($transactions as $transaction) {
            if (!$transaction->investor || !$transaction->company) {
                continue;
            }

            if ($transaction->investor->is_demo == 1) {
                return;
            }

            $minutesLeft = (int) $now->diffInMinutes($transaction->transaction_cancel_timer);
            $hoursLeft   = floor($minutesLeft / 60);
            $minsLeft    = $minutesLeft % 60;

            $timeLeft = $hoursLeft > 0
                ? "{$hoursLeft}h {$minsLeft}m"
                : "{$minsLeft}m";

            $params = [
                $transaction->investor->name,
                $transaction->transaction_invoice_no,
            ];

            UtillsHelper::sendWpMessage(
                NotificationTypeEnum::event,
                'shuru_up_transaction_reminder_sun',
                WpMessageTypeEnum::text,
                $transaction->investor->mobile_number,
                $transaction->investor->name,
                null,
                [],
                $params
            );

            UtillsHelper::sendNotification(
                $transaction->investor->id,
                InvestorModel::class,
                'preipo-transaction',
                'Action Required — Transaction Expiring Soon',
                UtillsHelper::paramsToTemplate(
                    $params,
                    'This is an urgent reminder from PrivateDeals regarding your ongoing transaction (ID: {{2}}). 🕐 Only 4 hours are remaining out of your 24-hour window to complete your transaction. If not completed in time, your transaction will be automatically cancelled and will cease to exist. Please log in to your account and complete the process immediately to avoid cancellation.'
                )
            );

            $admin = UserAdminModel::where('is_deleted', '0')
                ->where('id', '!=', '1')              // ← exclude super admin
                ->where('id', $transaction->updated_by) // ← only the acting manager
                ->first();
            if ($admin && AdminHelper::hasPermission(['pre ipo transaction'], $admin->id)) {
                $params = [
                    $transaction->transaction_invoice_no,
                    $transaction->investor->name,
                    '+' . $transaction->investor->mobile_country_code . '-' . $transaction->investor->mobile_number,
                    $transaction->company->brand_name,
                    $transaction->shares,
                    UtillsHelper::moneyFormatIndia($transaction->share_price),
                ];
                UtillsHelper::sendWpMessage(
                    NotificationTypeEnum::event,
                    'admin_last4hr_reminder_sun',
                    WpMessageTypeEnum::text,
                    $admin->mobile_no,
                    $admin->name,
                    NULL,
                    [],
                    $params
                );

                UtillsHelper::sendNotification(
                    $admin->id,
                    UserAdminModel::class,
                    'preipo-market',
                    'Private Equity transaction',
                    UtillsHelper::paramsToTemplate($params, 'Transaction ID: #{{1}} Investor Name: {{2}} Mobile Number: {{3}} Company: {{4}} Quantity: {{5}} Buying Price: {{6}} Please follow up to complete the pending process before expiry.')
                );
            }

            $this->info("Investor reminder sent: transaction #{$transaction->transaction_invoice_no}, {$timeLeft} left.");
        }

        $this->info('Investor deadline reminder run complete.');
    }
}
