<?php

namespace App\Helpers;

use App\Enums\NotificationTypeEnum;
use App\Enums\PreIpoOrderStepEnum;
use App\Enums\WpMessageTypeEnum;
use App\Models\InvestorModel;
use App\Models\PartnerModel;
use App\Models\PreIpoModel;
use Illuminate\Support\Facades\Log;

class PreIpoOrderNotificationHelper
{
    public static function mandatePending(PreIpoModel $transaction): void
    {
        if (self::skipped($transaction)) {
            return;
        }

        $transaction->loadMissing(['investor', 'company']);
        $params = self::baseParams($transaction);
        self::inAppInvestor(
            $transaction,
            UtillsHelper::paramsToTemplate(
                $params,
                'Your buy mandate for transaction #{{2}} is ready to sign. Company: {{3}} Quantity: {{4}} Buying Price: {{5}}. Sign the mandate sent by SMS and WhatsApp.'
            )
        );
        self::inAppPartner(
            $transaction,
            UtillsHelper::paramsToTemplate(
                $params,
                'A buy mandate for transaction #{{2}} was sent to {{1}} by SMS and WhatsApp. Ask them to sign. Company: {{3}} Quantity: {{4}} Buying Price: {{5}}.'
            )
        );
        self::missingWhatsapp($transaction, PreIpoOrderStepEnum::mandate_pending->value, 'investor');
    }

    public static function cancelled(PreIpoModel $transaction, bool $notifyBuyingPartner): void
    {
        if (self::skipped($transaction)) {
            return;
        }

        $transaction->loadMissing(['investor', 'company']);
        $params = [
            $transaction->investor->name,
            $transaction->transaction_invoice_no,
            $transaction->company->brand_name ?? '',
            $transaction->shares,
            UtillsHelper::moneyFormatIndia($transaction->share_price),
            $transaction->cancellation_reason,
        ];

        UtillsHelper::sendNotification(
            $transaction->investor_id,
            InvestorModel::class,
            'pre-ipo-transactions',
            'Transaction Cancelled',
            UtillsHelper::paramsToTemplate($params, 'Your transaction #{{2}} for the following investment has been cancelled: Company: {{3}} Quantity: {{4}} shares Buying Price: INR {{5}} Cancellation Reason: {{6}} If you did not request this cancellation, please contact our support team immediately.')
        );
        UtillsHelper::sendWpMessage(
            NotificationTypeEnum::event,
            'notify_investor_transaction_cancelled_sun',
            WpMessageTypeEnum::text,
            $transaction->investor->mobile_number,
            $transaction->investor->name,
            null,
            [],
            $params,
            ['transaction_id' => $transaction->id]
        );

        if ($notifyBuyingPartner) {
            self::inAppPartner(
                $transaction,
                UtillsHelper::paramsToTemplate(
                    $params,
                    'Transaction #{{2}} was cancelled. Investor: {{1}} Company: {{3}} Quantity: {{4}} Buying Price: {{5}} Reason: {{6}}'
                )
            );
        }
    }

    public static function shareConfirmationPending(PreIpoModel $transaction): void
    {
        if (self::skipped($transaction)) {
            return;
        }

        $transaction->loadMissing(['investor', 'company']);
        $params = self::baseParams($transaction);
        self::inAppPartner(
            $transaction,
            UtillsHelper::paramsToTemplate(
                $params,
                'The mandate for transaction #{{2}} is signed. Share confirmation is pending with the Institution. Company: {{3}} Quantity: {{4}}.'
            )
        );
        self::inAppInstitution(
            $transaction,
            UtillsHelper::paramsToTemplate(
                $params,
                'A mandate is signed on your deal for transaction #{{2}}. Approve the shares or cancel with a reason. Investor: {{1}} Company: {{3}} Quantity: {{4}} Buying Price: {{5}}.'
            )
        );
        self::missingWhatsapp($transaction, PreIpoOrderStepEnum::share_confirmation_pending->value, 'institution');
    }

    public static function dealSlipPending(PreIpoModel $transaction): void
    {
        if (self::skipped($transaction)) {
            return;
        }

        $transaction->loadMissing(['investor', 'company']);
        $params = self::baseParams($transaction);
        self::inAppPartner(
            $transaction,
            UtillsHelper::paramsToTemplate(
                $params,
                'The deal slip for transaction #{{2}} was sent to the investor by SMS and WhatsApp. Ask them to sign. Company: {{3}} Quantity: {{4}}.'
            )
        );
        self::inAppInstitution(
            $transaction,
            UtillsHelper::paramsToTemplate(
                $params,
                'The deal slip for transaction #{{2}} was generated. Waiting for the investor to sign. Company: {{3}} Quantity: {{4}}.'
            )
        );
    }

    public static function paymentPending(PreIpoModel $transaction): void
    {
        if (self::skipped($transaction)) {
            return;
        }

        $transaction->loadMissing(['investor', 'company', 'sellerInvestor.newBankAccount']);
        $account = PreIpoOrderStepHelper::paymentAccount($transaction);
        $amount = UtillsHelper::moneyFormatIndia($transaction->payable_amount);
        if ($account) {
            $body = 'The deal slip for transaction #'.$transaction->transaction_invoice_no.' is signed. Pay '.$amount.' to the following account. Account holder: '.$account['account_holder_name'].' Bank: '.$account['bank_name'].' Account number: '.$account['account_number'].' IFSC: '.$account['ifsc_code'].'.';
        } else {
            $body = 'The deal slip for transaction #'.$transaction->transaction_invoice_no.' is signed. Payment of '.$amount.' is due.';
        }

        self::inAppInvestor($transaction, $body);
        self::missingWhatsapp($transaction, PreIpoOrderStepEnum::payment_pending->value, 'investor');

        $params = self::baseParams($transaction);
        self::inAppPartner(
            $transaction,
            UtillsHelper::paramsToTemplate(
                $params,
                'Payment details for transaction #{{2}} were sent to the investor. Upload the payment receipt once the investor has paid. Company: {{3}} Quantity: {{4}}.'
            )
        );
        self::inAppInstitution(
            $transaction,
            UtillsHelper::paramsToTemplate(
                $params,
                'The deal slip for transaction #{{2}} is signed and payment details were sent. Waiting for payment confirmation. Company: {{3}} Quantity: {{4}}.'
            )
        );
    }

    public static function paymentConfirmationPending(PreIpoModel $transaction): void
    {
        if (self::skipped($transaction)) {
            return;
        }

        $transaction->loadMissing(['investor', 'company']);
        $params = self::baseParams($transaction);
        self::inAppPartner(
            $transaction,
            UtillsHelper::paramsToTemplate(
                $params,
                'The payment receipt for transaction #{{2}} is uploaded. Payment confirmation is pending. Company: {{3}} Quantity: {{4}}.'
            )
        );
        self::inAppInstitution(
            $transaction,
            UtillsHelper::paramsToTemplate(
                $params,
                'A payment receipt for transaction #{{2}} is ready. View or download it and confirm the payment. Company: {{3}} Quantity: {{4}}.'
            )
        );
        self::missingWhatsapp($transaction, PreIpoOrderStepEnum::payment_confirmation_pending->value, 'institution');
    }

    public static function shareTransferPending(PreIpoModel $transaction): void
    {
        if (self::skipped($transaction)) {
            return;
        }

        $transaction->loadMissing(['investor', 'company']);
        $params = [
            $transaction->investor->name,
            $transaction->transaction_invoice_no,
            $transaction->company->brand_name ?? '',
            $transaction->shares,
            DateTimeHelper::viewDate($transaction->settlement_date),
        ];
        UtillsHelper::sendWpMessage(
            NotificationTypeEnum::event,
            'transaction_payment_received_sun',
            WpMessageTypeEnum::text,
            $transaction->investor->mobile_number,
            $transaction->investor->name,
            null,
            [],
            $params
        );
        self::inAppInvestor(
            $transaction,
            UtillsHelper::paramsToTemplate(
                $params,
                'We have successfully received your payment for transaction #{{2}} related to {{3}} (quantity: {{4}}). Your share transfer is scheduled to be completed on {{5}}.'
            )
        );
        self::inAppPartner(
            $transaction,
            UtillsHelper::paramsToTemplate(
                self::baseParams($transaction),
                'Payment for transaction #{{2}} was received. Waiting for the share transfer. Company: {{3}} Quantity: {{4}}.'
            )
        );
    }

    public static function shareTransferConfirmationPending(PreIpoModel $transaction): void
    {
        if (self::skipped($transaction)) {
            return;
        }

        $transaction->loadMissing(['investor', 'company']);
        $params = self::baseParams($transaction);
        self::inAppPartner(
            $transaction,
            UtillsHelper::paramsToTemplate(
                $params,
                'The share-transfer receipt for transaction #{{2}} is ready. Confirm the share transfer. Company: {{3}} Quantity: {{4}}.'
            )
        );
        self::missingWhatsapp($transaction, PreIpoOrderStepEnum::share_transfer_confirmation_pending->value, 'buying partner');
        self::inAppInstitution(
            $transaction,
            UtillsHelper::paramsToTemplate(
                $params,
                'The share-transfer receipt for transaction #{{2}} is uploaded. Waiting for the partner to confirm. Company: {{3}} Quantity: {{4}}.'
            )
        );
    }

    public static function completed(PreIpoModel $transaction): void
    {
        if (self::skipped($transaction)) {
            return;
        }

        $transaction->loadMissing(['investor', 'company']);
        $params = [
            $transaction->investor->name,
            $transaction->transaction_invoice_no,
            $transaction->company->brand_name ?? '',
            $transaction->shares,
        ];
        UtillsHelper::sendWpMessage(
            NotificationTypeEnum::event,
            'transaction_completed_sun',
            WpMessageTypeEnum::text,
            $transaction->investor->mobile_number,
            $transaction->investor->name,
            null,
            [],
            $params
        );
        self::inAppInvestor(
            $transaction,
            UtillsHelper::paramsToTemplate(
                $params,
                'The share transfer for transaction #{{2}} related to {{3}} (quantity: {{4}}) is complete. Holdings are in the portfolio.'
            )
        );
        $note = UtillsHelper::paramsToTemplate(
            self::baseParams($transaction),
            'Transaction #{{2}} is completed. Company: {{3}} Quantity: {{4}}.'
        );
        self::inAppPartner($transaction, $note);
        self::inAppInstitution($transaction, $note);
    }

    private static function skipped(PreIpoModel $transaction): bool
    {
        $transaction->loadMissing('investor');

        return (int) ($transaction->investor->is_demo ?? 0) === 1;
    }

    private static function baseParams(PreIpoModel $transaction): array
    {
        return [
            $transaction->investor->name ?? '',
            $transaction->transaction_invoice_no,
            $transaction->company->brand_name ?? '',
            $transaction->shares,
            UtillsHelper::moneyFormatIndia($transaction->share_price),
        ];
    }

    private static function inAppInvestor(PreIpoModel $transaction, string $body): void
    {
        UtillsHelper::sendNotification(
            $transaction->investor_id,
            InvestorModel::class,
            'preipo-transaction',
            'Private Equity transaction',
            $body
        );
    }

    private static function inAppPartner(PreIpoModel $transaction, string $body): void
    {
        $partnerId = $transaction->investor->partner_id ?? null;
        if (!$partnerId) {
            return;
        }

        UtillsHelper::sendNotification(
            (string) $partnerId,
            PartnerModel::class,
            'preipo-transaction',
            'Private Equity transaction',
            $body
        );
    }

    private static function inAppInstitution(PreIpoModel $transaction, string $body): void
    {
        if (!$transaction->partner_id) {
            return;
        }

        $buyingPartnerId = (int) ($transaction->investor->partner_id ?? 0);
        if ($buyingPartnerId === (int) $transaction->partner_id) {
            return;
        }

        UtillsHelper::sendNotification(
            (string) $transaction->partner_id,
            PartnerModel::class,
            'preipo-transaction',
            'Private Equity transaction',
            $body
        );
    }

    private static function missingWhatsapp(PreIpoModel $transaction, string $step, string $recipient): void
    {
        Log::info('Pre-IPO WhatsApp template is not configured for order step '.$step.' recipient '.$recipient.'.', [
            'transaction_id' => $transaction->id,
            'order_step' => $step,
            'recipient' => $recipient,
        ]);
    }
}
