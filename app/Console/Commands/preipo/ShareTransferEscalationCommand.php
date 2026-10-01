<?php

namespace App\Console\Commands\preipo;

use App\Helpers\AdminHelper;
use App\Helpers\UtillsHelper;
use App\Models\PreIpoModel;
use App\Models\UserAdminModel;
use App\Services\PreIpoTimerService;
use App\Enums\NotificationTypeEnum;
use App\Enums\WpMessageTypeEnum;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * preipo:share-transfer-escalation
 *
 * Runs every 30 minutes.
 * When the share transfer SLA (1 business day) has elapsed and admin has NOT
 * marked the transfer complete (status still = 4), send escalation alerts.
 *
 * Rule: NO auto-cancel at this stage — only send escalation alerts.
 */
class ShareTransferEscalationCommand extends Command
{
    protected $signature   = 'preipo:share-transfer-escalation';
    protected $description = 'Escalate to admins when share transfer SLA is breached (status=4, timer expired)';

    public function handle(): void
    {
        $now = Carbon::now();

        $overdueTransactions = PreIpoModel::where('status', 4)
            ->whereNull('order_step')
            ->whereNotNull('transaction_cancel_timer')
            ->where('transaction_cancel_timer', '<=', $now)
            ->with(['investor', 'company'])
            ->get();

        if ($overdueTransactions->isEmpty()) {
            $this->info('No share transfer SLA breaches found.');
            return;
        }

        $admins = UserAdminModel::where('is_deleted', '0')
            ->where('id', '!=', '1')
            ->get();

        foreach ($overdueTransactions as $transaction) {
            if (!$transaction->investor || !$transaction->company) {
                continue;
            }

            $overdueSince = $transaction->transaction_cancel_timer
                ? $transaction->transaction_cancel_timer->diffForHumans()
                : 'unknown';

            $params = [
                $transaction->transaction_invoice_no,
                $transaction->investor->name,
                $transaction->company->brand_name,
                $transaction->shares,
                UtillsHelper::moneyFormatIndia($transaction->payable_amount),
                $overdueSince,
            ];

            $notificationBody = UtillsHelper::paramsToTemplate(
                $params,
                '🚨 SLA BREACH: Transaction #{{1}} — Share transfer for investor {{2}} ({{3}}, Qty: {{4}}, ₹{{5}}) is overdue by {{6}}. Please complete the transfer immediately.'
            );

            foreach ($admins as $admin) {
                if (AdminHelper::hasPermission(['pre ipo transaction'], $admin->id)) {
                    // UtillsHelper::sendWpMessage(
                    //     NotificationTypeEnum::event,
                    //     'preipo_share_transfer_escalation',
                    //     WpMessageTypeEnum::text,
                    //     $admin->mobile_no,
                    //     $admin->name,
                    //     null,
                    //     [],
                    //     $params
                    // );

                    UtillsHelper::sendNotification(
                        $admin->id,
                        UserAdminModel::class,
                        'preipo-transaction',
                        'Share Transfer SLA Breached',
                        $notificationBody
                    );
                }
            }

            $this->warn("Escalation sent for transaction #{$transaction->transaction_invoice_no} (overdue: {$overdueSince})");
        }

        $this->info('Share transfer escalation run complete.');
    }
}
