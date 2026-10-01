<?php

namespace App\Models;

use App\Enums\DocumentTypeEnum;
use App\Jobs\preipo\BuyNotificationJob;
use App\Services\PreIpoBusinessDayService;
use App\Services\PreIpoTimerService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PreIpoModel extends Model
{
    use HasFactory;



    protected $fillable = [
        'order_step',
        'transaction_invoice_no',
        'status',
        'investor_id',
        'company_id',
        'portfolio_id',
        'seller_id',
        'deal_id',
        'partner_id',
        'seller_investor_id',
        'shares',
        'share_price',
        'base_price',
        'distributer_price',
        'shuru_price',
        'investment_amount',
        'processing_fee',
        'coupon_discount_amount',
        'payable_amount',
        'settlement_date',
        'is_distributer',
        'instrument',
        'payment_mode',
        'is_valid',
        'other_name',
        'created_by',
        'updated_by',
        'notes',
        'is_cancelled_by_investor',
        'cancellation_reason',
        'investor_coupon_id',
        'coupon_code_snapshot',
        'transaction_cancel_timer',
        'timer_desc',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($transaction) {

            if (empty($transaction->transaction_invoice_no)) {

                // Get last generated invoice
                $lastInvoice = self::whereNotNull('transaction_invoice_no')
                    ->orderByDesc('id')
                    ->value('transaction_invoice_no');

                // Extract numeric part from TN-12AB → 12
                if ($lastInvoice && preg_match('/TN-(\d+)/', $lastInvoice, $matches)) {
                    $lastNumber = (int) $matches[1];
                } else {
                    $lastNumber = 0;
                }

                // Increment sequence
                $nextNumber = $lastNumber + 1;

                // Pad only for single digit (01–09)
                $formattedNumber = $nextNumber < 10
                    ? str_pad($nextNumber, 2, '0', STR_PAD_LEFT)
                    : $nextNumber;

                // Random 2-character mask
                $suffix = strtoupper(Str::random(2));

                // Final invoice number
                $transaction->transaction_invoice_no = "TN-{$formattedNumber}-{$suffix}";
            }
        });
        static::created(function ($transaction) {
            if ($transaction->usesOrderStep()) {
                return;
            }

            if ($transaction->created_by == NULL) {
                BuyNotificationJob::dispatch($transaction->id);
            }

            // Step 1: set the admin confirmation timer (now + 2h)
            // and log the initial status.
            try {
                $timerService = app(PreIpoTimerService::class);
                $timerService->setOrderPlacedTimer($transaction);
                $timerService->logStatusChange($transaction, (int) $transaction->status);
            } catch (Throwable $e) {
                // Non-fatal: do not block transaction creation
                Log::error('PreIpoTimerService@setOrderPlacedTimer failed: ' . $e->getMessage());
            }
        });
    }

    protected $table = 'pre_ipo_transaction';

    protected $casts = [
        'transaction_cancel_timer' => 'datetime',
    ];

    public function latestSharePrice()
    {
        return $this->hasOne(CompanySharePriceModel::class, 'company_id', 'company_id')
            ->latest('date');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CompanyModel::class, 'company_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(SellerMasterModel::class, 'seller_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CompanyDealModel::class, 'deal_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(PartnerModel::class, 'partner_id');
    }

    public function sellerInvestor(): BelongsTo
    {
        return $this->belongsTo(InvestorModel::class, 'seller_investor_id');
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(InvestorModel::class, 'investor_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(UserAdminModel::class, 'created_by');
    }

    protected $appends = ['percentage', 'current_status', 'next_step', 'deal_slip', 'approval_file', 'rejection_file', 'is_processing'];

    public function usesOrderStep(): bool
    {
        return filled($this->order_step);
    }

    public function getCurrentStatusAttribute(): string
    {
        if ($this->usesOrderStep()) {
            return '';
        }
        switch ($this->status) {
            case 0:
                return 'Processing';
            case 1:
                return 'Rejected';
            case 2:
                return 'Deal Slip send';
            case 3:
                return 'Deal Slip signed';
            case 4:
                return 'Amount Transfered to seller';
            case 5:
                return 'Completed';
            default:
                return 'Unknown';
        }
    }

    public function getIsProcessingAttribute(): bool
    {
        if ($this->usesOrderStep()) {
            return false;
        }
        // Processing flag is true for statuses 0, 2, 3, 4 (in progress)
        // Processing flag is false for statuses 1, 5 (terminal states - rejected or completed)
        return in_array($this->status, [0, 2, 3, 4]);
    }

    public function getNextStepAttribute(): string
    {
        if ($this->usesOrderStep()) {
            return '';
        }
        switch ($this->status) {
            case 0:
                if ($this->investor?->preipo_kyc_status) {
                    return 'Waiting for order confirmation';
                } else {
                    return 'Please complete your KYC to proceed with the transaction';
                }
            case 1:
                return 'No further action required, transaction is rejected';
            case 2:
                return 'Sign Deal Slip Check your sms for the link from Digiotech Solutions';
            case 3:
                return 'Transfer amount to seller bank account';
            case 4:
                return 'Waiting for share transfer by seller';
            case 5:
                return 'N/A';
            default:
                return 'Unknown';
        }
    }

    public function getDealSlipAttribute(): ?DocumentsModel
    {
        return DocumentsModel::where('type', DocumentTypeEnum::preipodealslip->value)->whereJsonContains('meta->preipo_transactions', $this->id)->first();
    }

    public function getApprovalFileAttribute(): ?DocumentsModel
    {
        return DocumentsModel::where('type', DocumentTypeEnum::preipoapproval->value)->where('status', '1')->whereJsonContains('meta->preipo_transactions', $this->id)->first();
    }
    public function getRejectionFileAttribute(): ?DocumentsModel
    {
        return DocumentsModel::where('type', DocumentTypeEnum::preiporejection->value)->where('status', '1')->whereJsonContains('meta->preipo_transactions', $this->id)->first();
    }

    public function getPercentageAttribute()
    {
        if ($this->usesOrderStep()) {
            return null;
        }
        return $this->status == 1 ? 100 : $this->status * 20;
    }

    public function investorCoupon(): BelongsTo
    {
        return $this->belongsTo(InvestorCouponModel::class, 'investor_coupon_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(PreIpoStatusLogModel::class, 'transaction_id')->orderBy('id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(PreIpoTransactionPaymentsModel::class, 'transaction_id')
            ->latest();
    }
}
