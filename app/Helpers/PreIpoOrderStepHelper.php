<?php

namespace App\Helpers;

use App\Enums\DocumentTypeEnum;
use App\Enums\PartnerTypeEnum;
use App\Enums\PreIpoOrderStepEnum;
use App\Models\DocumentsModel;
use App\Models\InvestorModel;
use App\Models\PartnerModel;
use App\Models\PreIpoModel;
use App\Models\UserBankAccountModel;

class PreIpoOrderStepHelper
{
    public const AUDIENCE_PARTNER = 'partner';
    public const AUDIENCE_INSTITUTION = 'institution';
    public const AUDIENCE_ADMIN = 'admin';

    public static function buyingPartnerInvestorIds(PartnerModel $partner): array
    {
        $partnerIds = PartnerModel::query()
            ->where('parent_id', $partner->id)
            ->where('type', PartnerTypeEnum::relationmanager->value)
            ->pluck('id');
        $partnerIds->push($partner->id);

        return InvestorModel::query()
            ->where('is_deleted', 0)
            ->where(function ($query) use ($partner, $partnerIds) {
                $query->where(function ($clients) use ($partnerIds) {
                    $clients->whereIn('partner_id', $partnerIds)->where('is_self', 0);
                })->orWhere(function ($self) use ($partner) {
                    $self->where('partner_id', $partner->id)->where('is_self', 1);
                });
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function partnerOwnsInvestor(PartnerModel $partner, int $investorId): bool
    {
        return in_array($investorId, self::buyingPartnerInvestorIds($partner), true);
    }

    public static function isSelfInvestorOrder(PreIpoModel $transaction): bool
    {
        return (int) ($transaction->investor->is_self ?? 0) === 1;
    }

    public static function paymentAccount(PreIpoModel $transaction): ?array
    {
        $bank = $transaction->sellerInvestor?->newBankAccount;
        if (!$bank instanceof UserBankAccountModel || blank($bank->account_number)) {
            return null;
        }

        return [
            'account_holder_name' => $bank->account_holder_name,
            'bank_name' => $bank->bank_name,
            'account_number' => $bank->account_number,
            'ifsc_code' => $bank->ifsc_code,
        ];
    }

    public static function signLink(PreIpoModel $transaction, string $documentType): ?string
    {
        $document = DocumentsModel::query()
            ->where('type', $documentType)
            ->whereJsonContains('meta->preipo_transactions', $transaction->id)
            ->latest('id')
            ->first();

        if (!$document) {
            return null;
        }

        $link = $document->signers()->latest('id')->value('link');

        return filled($link) ? (string) $link : null;
    }

    public static function shareTransferReceipt(PreIpoModel $transaction): ?DocumentsModel
    {
        return DocumentsModel::query()
            ->where('type', DocumentTypeEnum::preiposharetransferreceipt->value)
            ->whereJsonContains('meta->preipo_transactions', $transaction->id)
            ->latest('id')
            ->first();
    }

    /**
     * @return array{current: string, next: ?string, action: ?array, sign_link: ?string}
     */
    public static function labels(PreIpoModel $transaction, string $audience): array
    {
        $step = (string) $transaction->order_step;
        $self = self::isSelfInvestorOrder($transaction);
        $showSignLink = $self && $audience === self::AUDIENCE_PARTNER;

        $current = '';
        $next = null;
        $action = [];
        $signLink = null;

        switch ($step) {
            case PreIpoOrderStepEnum::mandate_pending->value:
                $current = 'Transaction initiated. Buy mandate generated.';
                $next = 'Ask the investor to sign the mandate sent by SMS and WhatsApp.';
                if ($audience === self::AUDIENCE_PARTNER) {
                    $action[] = 'cancel';
                    if ($showSignLink) {
                        $action[] = 'show_mandate_sign_link';
                        $signLink = self::signLink($transaction, DocumentTypeEnum::buymandate->value);
                    }
                }
                break;

            case PreIpoOrderStepEnum::cancelled->value:
                $reason = trim((string) $transaction->cancellation_reason);
                $current = $reason !== ''
                    ? 'Transaction cancelled. '.$reason
                    : 'Transaction cancelled.';
                $next = null;
                break;

            case PreIpoOrderStepEnum::share_confirmation_pending->value:
                $current = 'Mandate signed.';
                if ($audience === self::AUDIENCE_PARTNER) {
                    $next = 'Share confirmation pending.';
                } else {
                    $next = 'Approve the transaction or cancel with a reason.';
                    $action = ['approve', 'reject'];
                }
                break;

            case PreIpoOrderStepEnum::deal_slip_pending->value:
                $current = 'Deal slip generated.';
                if ($audience === self::AUDIENCE_PARTNER) {
                    $next = 'Ask the investor to sign the deal slip sent by SMS and WhatsApp.';
                    if ($showSignLink) {
                        $action[] = 'show_deal_slip_sign_link';
                        $signLink = self::signLink($transaction, DocumentTypeEnum::preipodealslip->value);
                    }
                } else {
                    $next = 'Waiting for the deal slip signature.';
                }
                break;

            case PreIpoOrderStepEnum::payment_pending->value:
                $current = 'Deal slip signed. Payment details sent to the investor.';
                if ($audience === self::AUDIENCE_PARTNER) {
                    $next = 'Upload the payment receipt.';
                    $action = ['view_payment_details', 'upload_payment_receipt'];
                } else {
                    $next = 'Waiting for payment confirmation.';
                }
                break;

            case PreIpoOrderStepEnum::payment_confirmation_pending->value:
                $current = 'Payment receipt uploaded.';
                if ($audience === self::AUDIENCE_PARTNER) {
                    $next = 'Payment confirmation pending.';
                } else {
                    $next = 'View or download the receipt and confirm the payment.';
                    $action = ['view_payment_receipt', 'confirm_payment'];
                }
                break;

            case PreIpoOrderStepEnum::share_transfer_pending->value:
                $current = 'Payment received.';
                if ($audience === self::AUDIENCE_PARTNER) {
                    $next = 'Waiting for share transfer.';
                } else {
                    $next = 'Upload the share-transfer receipt.';
                    $action = ['upload_share_transfer_receipt'];
                }
                break;

            case PreIpoOrderStepEnum::share_transfer_confirmation_pending->value:
                $current = 'Share transfer receipt uploaded.';
                if ($audience === self::AUDIENCE_PARTNER) {
                    $next = 'Confirm the share transfer.';
                    $action = ['view_share_transfer_receipt', 'confirm_share_transfer'];
                } else {
                    $next = 'Waiting for share transfer confirmation.';
                }
                break;

            case PreIpoOrderStepEnum::completed->value:
                $current = 'Transaction completed.';
                $next = 'N/A';
                break;

            default:
                $current = '';
                $next = null;
        }

        return [
            'current' => $current,
            'next' => $next,
            'action' => $action === [] ? null : array_values(array_unique($action)),
            'sign_link' => $signLink,
        ];
    }

    public static function map(PreIpoModel $transaction, string $audience): array
    {
        if (!$transaction->usesOrderStep()) {
            $transaction->company?->setAppends([]);
            $transaction->status_list = PreIpoTransactionHelper::getStatusListForApplicationV2($transaction);
            if (in_array((int) $transaction->status, [1, 5], true)) {
                $transaction->makeHidden('transaction_cancel_timer');
            }
            $transaction->makeHidden(['order_step']);

            return $transaction->toArray();
        }

        return self::orderStepPayload($transaction, $audience);
    }

    /**
     * List, detail, and action responses for a row that has order_step.
     * Old status-machine fields are not included.
     */
    public static function orderStepPayload(PreIpoModel $transaction, string $audience): array
    {
        $transaction->loadMissing(['company', 'investor', 'sellerInvestor.newBankAccount', 'payment.document']);
        $labels = self::labels($transaction, $audience);
        $company = $transaction->company;
        $investor = $transaction->investor;

        return [
            'id' => $transaction->id,
            'transaction_invoice_no' => $transaction->transaction_invoice_no,
            'order_step' => $transaction->order_step,
            'current_step' => $labels['current'],
            'next_step' => $labels['next'],
            'action' => $labels['action'],
            'sign_link' => $labels['sign_link'],
            'investor' => $investor ? [
                'id' => $investor->id,
                'name' => $investor->name,
            ] : null,
            'company' => $company ? [
                'id' => $company->id,
                'brand_name' => $company->brand_name,
                'logo' => $company->logo,
            ] : null,
            'deal_id' => $transaction->deal_id,
            'shares' => $transaction->shares,
            'base_price' => $transaction->base_price,
            'distributer_price' => $transaction->distributer_price,
            'share_price' => $transaction->share_price,
            'investment_amount' => $transaction->investment_amount,
            'payable_amount' => $transaction->payable_amount,
            'cancellation_reason' => (string) $transaction->order_step === PreIpoOrderStepEnum::cancelled->value
                ? $transaction->cancellation_reason
                : null,
            'payment_details' => self::paymentDetailsPayload($transaction),
            'payment_receipt' => self::filePayload($transaction->payment?->document),
            'share_transfer_receipt' => self::filePayload(self::shareTransferReceipt($transaction)),
            'created_at' => $transaction->created_at?->toJSON(),
        ];
    }

    public static function paymentDetailsPayload(PreIpoModel $transaction): ?array
    {
        $steps = [
            PreIpoOrderStepEnum::payment_pending->value,
            PreIpoOrderStepEnum::payment_confirmation_pending->value,
            PreIpoOrderStepEnum::share_transfer_pending->value,
            PreIpoOrderStepEnum::share_transfer_confirmation_pending->value,
            PreIpoOrderStepEnum::completed->value,
        ];
        if (!in_array((string) $transaction->order_step, $steps, true)) {
            return null;
        }

        return [
            'amount' => $transaction->payable_amount,
            'account' => self::paymentAccount($transaction),
        ];
    }

    public static function filePayload(?DocumentsModel $document): ?array
    {
        if (!$document || blank($document->path)) {
            return null;
        }

        $name = null;
        if (is_object($document->meta) && isset($document->meta->name)) {
            $name = $document->meta->name;
        }

        return [
            'id' => $document->id,
            'name' => $name,
            'path' => $document->path,
            'url' => FileUpDownHelper::generateUrl($document->path),
        ];
    }
}
