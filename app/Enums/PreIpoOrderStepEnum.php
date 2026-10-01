<?php

namespace App\Enums;

enum PreIpoOrderStepEnum: string
{
    case mandate_pending = 'mandate_pending';
    case cancelled = 'cancelled';
    case share_confirmation_pending = 'share_confirmation_pending';
    case deal_slip_pending = 'deal_slip_pending';
    case payment_pending = 'payment_pending';
    case payment_confirmation_pending = 'payment_confirmation_pending';
    case share_transfer_pending = 'share_transfer_pending';
    case share_transfer_confirmation_pending = 'share_transfer_confirmation_pending';
    case completed = 'completed';
}
