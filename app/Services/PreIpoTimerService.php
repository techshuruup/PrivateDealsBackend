<?php

namespace App\Services;

use App\Helpers\AdminHelper;
use App\Helpers\UtillsHelper;
use App\Enums\NotificationTypeEnum;
use App\Enums\WpMessageTypeEnum;
use App\Helpers\CommonHelper;
use App\Jobs\InvestorActionWindowStartJob;
use App\Models\InvestorModel;
use App\Models\PreIpoModel;
use App\Models\PreIpoStatusLogModel;
use App\Models\UserAdminModel;
use Carbon\Carbon;

/**
 * PreIpoTimerService
 *
 * Central manager for the single transaction_cancel_timer field.
 * This field is OVERWRITTEN at every lifecycle stage — never add new timer columns.
 *
 * Stage map:
 *  Step 1 — Order placed    → timer = now + 2h (admin reminder window, no auto-cancel)
 *  Step 2 — Admin accepts   → timer = now + 24h (investor action window, auto-cancel on expiry)
 *  Step 4 — Payment recv'd  → timer = now + 1 business day (share transfer SLA, no auto-cancel)
 */
class PreIpoTimerService
{
    public function __construct(
        private readonly PreIpoBusinessDayService $businessDayService
    ) {}

    /* ─────────────────────────────────────────────
     |  STEP 1  — Order placed (status = 0)
     |  Timer: now + 2 hours
     |  Purpose: drive admin 15-min reminders only
     ──────────────────────────────────────────── */
    public function setOrderPlacedTimer(PreIpoModel $transaction): void
    {
        if ($transaction->usesOrderStep()) {
            return;
        }
        $transaction->transaction_cancel_timer = $this->businessDayService->getNextActiveHours(CommonHelper::appSettings('peripo_admin_order_accept_hours') ?? 2);
        $transaction->timer_desc = 'Order will be accepted within {value}';
        $transaction->saveQuietly();
    }

    /* ─────────────────────────────────────────────
     |  STEP 2  — Admin sends deal slip (status = 2)
     |  Timer: now + 24 hours
     |  Purpose: investor must complete KYC → deal slip → payment in 24h
     |  Auto-cancel fires if timer expires at status 2 or 3
     ──────────────────────────────────────────── */
    public function setInvestorActionTimer(PreIpoModel $transaction): void
    {
        if ($transaction->usesOrderStep()) {
            return;
        }
        $intHours = (int) (CommonHelper::appSettings('preipo_investor_order_completion_hours') ?? 24);
        // $transaction->transaction_cancel_timer = $this->businessDayService->getNextActiveHours(CommonHelper::appSettings('preipo_investor_order_completion_hours') ?? 24);
        $transaction->transaction_cancel_timer = Carbon::now()->addHours($intHours);
        $transaction->timer_desc = 'Complete transaction within {value}';
        $transaction->saveQuietly();

        // Queue job to notify investor when 24hr window starts
    }



    /* ─────────────────────────────────────────────
     |  STEP 4  — Payment received (status = 4)
     |  Timer: 1 business day from now (holiday-aware)
     |  Purpose: admin share transfer SLA tracker — no auto-cancel, only alerts
     ──────────────────────────────────────────── */
    public function setShareTransferTimer(PreIpoModel $transaction): void
    {
        if ($transaction->usesOrderStep()) {
            return;
        }
        $transaction->transaction_cancel_timer = $this->businessDayService->getNextActiveHours(CommonHelper::appSettings('preipo_share_tranfer_hours') ?? 12);
        $transaction->timer_desc = 'Shares will be transferred within {value}.';
        $transaction->saveQuietly();
    }

    /* ─────────────────────────────────────────────
     |  EXTEND TIMER
     |  Extends the transaction timer based on current status
     |  Status 0: extend by admin acceptance hours
     |  Status 2/3: extend by investor action hours
     |  Status 4: extend by share transfer hours
     ──────────────────────────────────────────── */
    public function extendTimer(PreIpoModel $transaction, ?int $extendByHours = null): void
    {
        if ($transaction->usesOrderStep()) {
            return;
        }
        $hoursToAdd = $extendByHours;

        if ($hoursToAdd === null) {
            // Determine extension hours based on current status
            if ($transaction->status == 0) {
                $hoursToAdd = (int) (CommonHelper::appSettings('peripo_admin_order_accept_hours') ?? 2);
            } elseif (in_array($transaction->status, [2, 3])) {
                $hoursToAdd = (int) (CommonHelper::appSettings('preipo_investor_order_completion_hours') ?? 24);
            } elseif ($transaction->status == 4) {
                $hoursToAdd = (int) (CommonHelper::appSettings('preipo_share_tranfer_hours') ?? 12);
            } else {
                $hoursToAdd = 24; // Default to 24 hours for unknown statuses
            }
        }

        $transaction->transaction_cancel_timer = Carbon::now()->addHours($hoursToAdd);
        $transaction->saveQuietly();
    }

    /* ─────────────────────────────────────────────
     |  STATUS LOG
     |  Insert a row in pre_ipo_transaction_status_logs
     |  whenever any status changes.
     ──────────────────────────────────────────── */
    public function logStatusChange(PreIpoModel $transaction, int $status): void
    {
        PreIpoStatusLogModel::create([
            'transaction_id' => $transaction->id,
            'status'         => $status,
        ]);
    }

    /* ─────────────────────────────────────────────
     |  ADMIN NOTIFICATION HELPER
     |  Send WhatsApp + in-app alert to every admin
     |  that has 'pre ipo transaction' permission.
     ──────────────────────────────────────────── */
    public function notifyAdmins(PreIpoModel $transaction, string $templateName, string $notificationBody, array $params): void
    {
        $admins = UserAdminModel::where('is_deleted', '0')
            ->where('id', '!=', '1')
            ->get();

        foreach ($admins as $admin) {
            if (AdminHelper::hasPermission(['pre ipo transaction'], $admin->id)) {
                UtillsHelper::sendWpMessage(
                    NotificationTypeEnum::event,
                    $templateName,
                    WpMessageTypeEnum::text,
                    $admin->mobile_no,
                    $admin->name,
                    null,
                    [],
                    $params
                );

                UtillsHelper::sendNotification(
                    $admin->id,
                    UserAdminModel::class,
                    'preipo-transaction',
                    'Private Equity Transaction',
                    $notificationBody
                );
            }
        }
    }

    /* ─────────────────────────────────────────────
     |  INVESTOR NOTIFICATION HELPER
     ──────────────────────────────────────────── */
    // public function notifyInvestor(PreIpoModel $transaction, string $templateName, string $notificationBody, array $params): void
    // {
    //     if (!$transaction->investor) {
    //         return;
    //     }

    //     UtillsHelper::sendWpMessage(
    //         NotificationTypeEnum::event,
    //         $templateName,
    //         WpMessageTypeEnum::text,
    //         $transaction->investor->mobile_number,
    //         $transaction->investor->name,
    //         null,
    //         [],
    //         $params
    //     );

    //     UtillsHelper::sendNotification(
    //         $transaction->investor->id,
    //         InvestorModel::class,
    //         'preipo-transaction',
    //         'Private Equity Transaction',
    //         $notificationBody
    //     );
    // }
}
