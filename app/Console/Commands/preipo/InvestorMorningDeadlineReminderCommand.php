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
 * preipo:investor-morning-deadline-reminder
 *
 * Runs every day at 10:00 AM (market open).
 * Notifies the investor in the morning if they have a pending transaction expiring today (i.e., before 5:00 PM),
 * using the same message as the 4-hour reminder.
 */
class InvestorMorningDeadlineReminderCommand extends Command
{
    protected $signature   = 'preipo:investor-morning-deadline-reminder';
    protected $description = 'Notify investor in the morning if their 24h action window expires today (before 5.30pm)';

    public function handle(): void
    {
        $now = Carbon::now();
        $marketOpen = $now->copy()->setTime(10, 0, 0); // 10:00 AM
        $marketClose = $now->copy()->setTime(17, 30, 0); // 5:30 PM

        // Transactions in investor action window, timer not expired yet, expiring today after market open
        $transactions = PreIpoModel::whereIn('status', [2, 3])
            ->whereNull('order_step')
            ->whereNotNull('transaction_cancel_timer')
            ->whereDate('transaction_cancel_timer', $now->toDateString())
            ->where('transaction_cancel_timer', '>', $marketOpen)
            ->where('transaction_cancel_timer', '<=', $marketClose)
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
                continue;
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
                ->where('id', '!=', '1')
                ->where('id', $transaction->updated_by)
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

            $this->info("Investor morning reminder sent: transaction #{$transaction->transaction_invoice_no}, {$timeLeft} left.");
        }

        $this->info('Investor morning deadline reminder run complete.');
    }
}
