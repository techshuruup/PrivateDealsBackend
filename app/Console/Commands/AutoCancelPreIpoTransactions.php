<?php

namespace App\Console\Commands;

use App\Helpers\PreIpoTransactionHelper;
use App\Models\PreIpoModel;
use App\Services\PreIpoTimerService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * preipo:auto-cancel
 *
 * Runs every minute.
 *
 * Auto-cancel rule (Step 3 — investor window):
 *   If status IN (2, 3) AND transaction_cancel_timer <= now → cancel (status = 1)
 *
 * Step 1 (status = 0) is intentionally excluded — pending orders are NEVER
 * auto-cancelled; only escalated via admin reminders.
 *
 * Step 4 (status = 4, share transfer) is also excluded — SLA breach sends
 * escalation alerts via preipo:share-transfer-escalation, no auto-cancel.
 */
class AutoCancelPreIpoTransactions extends Command
{
    protected $signature   = 'preipo:auto-cancel';
    protected $description = 'Auto-cancel Pre-IPO transactions where the investor action window (24h) has expired';

    public function handle(PreIpoTimerService $timerService): void
    {
        $now = Carbon::now();

        // Only cancel during the investor action window (deal slip sent / signed)
        $expiredTransactions = PreIpoModel::whereIn('status', [2, 3])
            ->whereNull('order_step')
            ->whereNotNull('transaction_cancel_timer')
            ->where('transaction_cancel_timer', '<=', $now)
            ->with(['investor', 'company'])
            ->get();

        foreach ($expiredTransactions as $transaction) {
            $transaction->status              = 1;
            $transaction->cancellation_reason = 'Auto-cancelled: investor did not complete the required steps within 24 hours.';
            $transaction->is_cancelled_by_investor = 0;
            $transaction->save();

            // Log the status change
            $timerService->logStatusChange($transaction, 1);

            // Send cancellation notifications
            try {
                PreIpoTransactionHelper::cancelTransactionNotification($transaction);
            } catch (Throwable $e) {
                Log::error(
                    "PreIpo auto-cancel notification failed for #{$transaction->id}: " . $e->getMessage()
                );
            }

            $this->info("Auto-cancelled transaction #{$transaction->transaction_invoice_no} (ID: {$transaction->id})");
        }

        $this->info('Auto-cancel check complete. Cancelled: ' . $expiredTransactions->count());
    }
}
