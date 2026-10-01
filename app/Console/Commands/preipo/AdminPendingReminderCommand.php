<?php

namespace App\Console\Commands\preipo;

use App\Enums\NotificationTypeEnum;
use App\Enums\WpMessageTypeEnum;
use App\Helpers\AdminHelper;
use App\Helpers\BseCalendarHelper;
use App\Helpers\DateTimeHelper;
use App\Helpers\PreIpoTransactionHelper;
use App\Helpers\UtillsHelper;
use App\Models\PreIpoModel;
use App\Models\UserAdminModel;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * preipo:admin-pending-reminder
 *
 * Runs every 15 minutes.
 * Fires a WhatsApp + in-app reminder to all eligible admins for every
 * Pre-IPO transaction that is still pending admin confirmation (status = 0).
 *
 * Rule: no auto-cancel at this stage — only escalate reminders.
 */
class AdminPendingReminderCommand extends Command
{
    protected $signature   = 'preipo:admin-pending-reminder';
    protected $description = 'Send every-15-min WhatsApp & in-app reminder to admins for pending Pre-IPO orders';

    public function handle(): void
    {
        $pendingTransactions = PreIpoModel::where('status', 0)
            ->whereNull('order_step')
            ->where('is_valid', 1)
            ->whereHas('investor', function ($query) {
                $query->where('is_demo', 0)->where('is_deleted', 0);
            })
            ->with(['investor', 'company'])
            ->get();

        if ($pendingTransactions->isEmpty()) {
            $this->info('No pending transactions found.');
            return;
        }

        $admins = UserAdminModel::where('is_deleted', '0')
            ->where('id', '!=', '1')              // ← exclude super admin
            // ->where('id', $pendingTransactions->updated_by) // ← only the acting manager
            ->get();

        foreach ($pendingTransactions as $transaction) {

            if (!$transaction->investor || !$transaction->company) {
                continue;
            }

            if (!$transaction->transaction_cancel_timer) {
                continue;
            }
            if ($transaction->investor->is_demo == 1) {
                return;
            }

            $timerExpired = Carbon::parse($transaction->transaction_cancel_timer)->isPast();


            if ($timerExpired) {
                $transaction->status              = 1;
                $transaction->cancellation_reason = 'Auto-cancelled: shares not available.';
                $transaction->is_cancelled_by_investor = 0;
                $transaction->save();
                PreIpoTransactionHelper::cancelTransactionNotification($transaction);
                continue;
            }

            $timer = Carbon::parse($transaction->transaction_cancel_timer);

            if ($timer->isToday() && BseCalendarHelper::isMarketTiming() && Carbon::parse($transaction->created_at)->diffInMinutes(now()) > 15) {
                foreach ($admins as $admin) {
                    if ($admin && AdminHelper::hasPermission(['pre ipo transaction'], $admin->id)) {
                        $approveTran = UtillsHelper::tokenUrlGenerate('admin_accept_tran', ['item_id' => $transaction->id, 'admin_id' => $admin->id]);
                        $rejectTran = UtillsHelper::tokenUrlGenerate('admin_reject_tran', ['item_id' => $transaction->id, 'admin_id' => $admin->id]);
                        $params = [
                            $transaction->transaction_invoice_no,
                            '*' . DateTimeHelper::formatElapsedTime($transaction->created_at) . '*',
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
                            'admin_pending_reminder_15min_sun_copy',
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
                            UtillsHelper::paramsToTemplate($params, 'Order #{{1}} has been pending for {{2}} and is awaiting review. Investor: {{3}} Mo. No.: {{4}} Company: {{5}} Quantity: {{6}} Buying Price: {{7}} Invested: {{8}} Payable: {{9}} This order requires your attention. Please review and take the necessary action.')
                        );
                    }
                }
            }
        }
    }
}
