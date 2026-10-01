<?php

namespace App\Helpers;

use App\Enums\DocumentTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\Utills\StatusEnum;
use App\Enums\WpMessageTypeEnum;
use App\Models\DocumentsModel;
use App\Models\InvestorModel;
use App\Models\PreIpoModel;
use App\Models\ReportErrorLogModel;
use App\Models\UserAdminModel;
use App\Services\PreIpoTimerService;
use Illuminate\Support\Facades\Log;
use Throwable;

class PreIpoTransactionHelper
{

    static function getStatusListForApplicationV2(PreIpoModel $transaction): array
    {
        if ($transaction->usesOrderStep()) {
            return [];
        }
        $list = [];

        $list[] = [
            'title'         => 'Transaction Initiated',
            'description'   => 'Your transaction request has been successfully submitted',
            'date'          => $transaction->created_at,
            'document'      => null,
            'action'        => null,
            'is_active'     => false,
        ];

        if ($transaction->status == 1) {
            $list[] = [
                'title'         => 'Transaction Rejected',
                'description' => 'Your transaction request was rejected. Reason: ' . ($transaction->cancellation_reason ?? 'Not specified'),
                'date'          => $transaction->updated_at,
                'document'      => null,
                'action'        => null,
                'is_active'     => false,
                'is_completed'  => true,
            ];
        } else {

            $list[] = [
                'title'         => $transaction->status == 0 ? 'Share Confirmation Pending' : 'Share Confirmed',
                'description'   => $transaction->status == 0 ? 'Your request is under review. We will notify you once the shares are confirmed.' : 'The shares for your transaction have been successfully confirmed.',
                'date' => $transaction->status >= 2
                    ? optional(
                        $transaction->statusLogs()
                            ->where('status', 2)
                            ->first()
                    )->created_at
                    : null,
                'document'      => null,
                'action'        => null,
                'is_active'     => in_array($transaction->status, [0]) ? true : false,
            ];
            $isKyc = false;
            if (in_array($transaction->status, [0, 2]) && !($transaction->investor?->preipo_kyc_status ?? false)) {
                $list[] = [
                    'title'         => 'KYC Verification Required',
                    'description'   => 'Please complete your KYC verification to continue with the transaction',
                    'date'          => null,
                    'document'      => null,
                    'action'        => ['type' => 'kyc', 'btn_name' => 'Complete KYC'],
                    'is_active'     => true,
                ];
                $isKyc = true;
            }

            $list[] = [
                'title'         => $transaction->status <= 2 ? 'Deal Slip Signature Pending' : 'Deal Slip Signed',
                'description'   => $transaction->status <= 2 ? 'Please review and sign the deal slip to proceed' : 'The deal slip has been successfully signed',
                'date' => $transaction->status >= 3
                    ? optional(
                        $transaction->statusLogs()
                            ->where('status', 3)
                            ->first()
                    )->created_at
                    : null,
                'document'      => $transaction->status > 2 ? $transaction->deal_slip : null,
                'action' => $transaction->status == 2
                    ? ['type' => 'dealslip', 'btn_name' => 'Click to E-Sign', 'url' => $transaction->deal_slip?->signers?->first()?->link]
                    : null,
                'is_active'     => in_array($transaction->status, [2])  && !$isKyc  ? true : false,
            ];

            //code for payment 

            $payment = $transaction->payment;
            $paymentStatus = $payment?->status;

            $sellerBankDetails = [
                'company_name'   => $transaction->seller?->company_name,
                'bank_name'      => $transaction->seller?->bank_name,
                'account_number' => $transaction->seller?->account_number,
                'ifsc'           => $transaction->seller?->ifsc,
                'branch'         => $transaction->seller?->branch,
            ];

            /*
            |--------------------------------------------------------------------------
            | PAYMENT TITLE & DESCRIPTION
            |--------------------------------------------------------------------------
            */
            if ($transaction->status > 3) {

                $paymentTitle = 'Payment Completed';
                $paymentDescription = 'The payment has been successfully received.';
            } else {

                $paymentTitle = 'Payment Pending';
                $paymentDescription = 'Please transfer the required amount to proceed with the transaction.';

                if ($paymentStatus == StatusEnum::pending->value) {
                    $paymentTitle = 'Payment Processing';
                    $paymentDescription = 'Your payment is being processed. Please upload the payment receipt for confirmation.';
                }

                if ($paymentStatus == StatusEnum::rejected->value) {
                    $paymentTitle = 'Payment Rejected';
                    $paymentDescription = 'Your payment was rejected. Please upload a valid payment receipt to proceed.';
                }
            }

            /*
            |--------------------------------------------------------------------------
            | PAYMENT ACTION
            |--------------------------------------------------------------------------
            */
            $paymentAction = null;

            if ($transaction->status == 3) {

                if ($paymentStatus == StatusEnum::pending->value) {
                    $paymentAction = null;
                } else {

                    $paymentAction = [
                        'type' => 'receipt',
                        'btn_name' => $paymentStatus == StatusEnum::rejected->value
                            ? 'ReUpload receipt'
                            : 'Upload receipt',
                        'details' => $sellerBankDetails
                    ];
                }
            }

            $list[] = [
                'title'         => $paymentTitle,
                'description'   => $paymentDescription,
                'date' => $transaction->status >= 4
                    ? optional(
                        $transaction->statusLogs()
                            ->where('status', 4)
                            ->first()
                    )->created_at
                    : null,
                'document' => $transaction->status >= 4 ? $transaction->payment?->document : null,
                'action'        => $paymentAction,
                'is_active'     => in_array($transaction->status, [3])  ? true : false,
            ];
            //code for payment end
            $list[] = [
                'title'         => $transaction->status <= 4 ? 'Share Transfer Pending' : 'Shares Transferred',
                'description'   => $transaction->status <= 4 ? 'Your shares are being transferred to your account' : 'The shares have been successfully transferred to your account',
                'date' => $transaction->status == 5
                    ? optional(
                        $transaction->statusLogs()
                            ->where('status', 5)
                            ->first()
                    )->created_at
                    : null,
                'document'      => null,
                'action'        => null,
                'is_active'     => in_array($transaction->status, [4])  ? true : false,
            ];
            if ($transaction->status == 5) {
                $list[] = [
                    'title'         => 'Transaction Completed',
                    'description'   => 'Your transaction has been successfully completed',
                    'date'          => null,
                    'document'      => null,
                    'action'        => null,
                    'is_active'     => false,
                    'is_completed'  => true,
                ];
            }
        }

        return $list;
    }

    static function getStatusListForApplication(PreIpoModel $transaction): array
    {
        if ($transaction->usesOrderStep()) {
            return [];
        }
        $list = [];

        $list[] = [
            'title'         => 'Committed',
            'description'   => 'Transaction committed',
            'date'          => $transaction->created_at,
            'document'      => null,
            'action'        => null,
            'is_active'     => in_array($transaction->status, [0]) ? true : false,
        ];

        if ($transaction->status == 1) {
            $list[] = [
                'title'         => 'Request Rejected',
                'description'   => 'Your transaction request has been rejected',
                'date'          => $transaction->updated_at,
                'document'      => null,
                'action'        => null,
                'is_active'     => true
            ];
        } else {
            if ($transaction->status == 0 && !($transaction->investor?->preipo_kyc_status ?? false)) {
                $list[] = [
                    'title'         => 'Complete KYC',
                    'description'   => 'Please complete your KYC to proceed with the transaction',
                    'date'          => null,
                    'document'      => null,
                    'action'        => null,
                    'is_active'     => false,
                ];
            }

            $list[] = [
                'title'         => $transaction->status < 3 ? 'Deal slip sign pending' : 'Deal slip signed',
                'description'   => $transaction->status < 3 ? 'Deal slip document is pending to be signed' : 'Deal slip document has been signed',
                'date'          => $transaction->deal_slip->created_at ?? null,
                'document'      => $transaction->deal_slip,
                'action'        => null,
                'is_active'     => in_array($transaction->status, [3])  ? true : false,
            ];

            $list[] = [
                'title'         => $transaction->status < 4 ? 'Amount Transfer pending' : 'Amount Transfered',
                'description'   => $transaction->status < 4 ? 'Amount transfer to seller is pending' : 'Amount has been transfered to seller',
                'date'          => null,
                'action'        => null,
                'is_active'     => in_array($transaction->status, [4])  ? true : false,
            ];

            $list[] = [
                'title'         => 'Share Transfered',
                'description'   => 'Transaction completed',
                'date'          => null,
                'document'      => null,
                'action'        => null,
                'is_active'     => in_array($transaction->status, [5])  ? true : false,
            ];
        }

        return $list;
    }

    static function changeTransactionStatus($document): void
    {
        if ($document) {
            foreach ($document->meta->preipo_transactions as $key => $value) {
                $transaction = PreIpoModel::find($value);
                if ($transaction) {
                    if ($transaction->usesOrderStep()) {
                        if ($document->type == DocumentTypeEnum::preipodealslip->value) {
                            app(\App\Services\PreIpoOrderStepService::class)->onDealSlipSigned($transaction);
                        }
                        continue;
                    }
                    if ($document->type == DocumentTypeEnum::preipodealslip->value) {
                        if ($transaction->status < 3) {

                            $transaction->status = 3;
                            $transaction->save();

                            // Step 3: deal slip signed — log status change
                            try {
                                app(PreIpoTimerService::class)->logStatusChange($transaction, 3);
                            } catch (Throwable $e) {
                                Log::error('PreIpoTimerService@logStatusChange(3) failed: ' . $e->getMessage());
                            }
                            if ($transaction->investor && $transaction->company && $transaction->seller && $transaction->investor->is_demo == 0) {
                                $params = [
                                    $transaction->investor->name,
                                    $transaction->transaction_invoice_no,
                                    $transaction->company->brand_name,
                                    $transaction->shares,
                                    UtillsHelper::moneyFormatIndia($transaction->payable_amount),
                                    $transaction->seller->company_name,
                                    $transaction->seller->bank_name,
                                    $transaction->seller->ifsc,
                                    $transaction->seller->account_number,
                                    $transaction->seller->branch,
                                ];
                                UtillsHelper::sendWpMessage(
                                    NotificationTypeEnum::event,
                                    'investor_bankdetails_for_transaction',
                                    WpMessageTypeEnum::text,
                                    $transaction->investor->mobile_number,
                                    $transaction->investor->name,
                                    NULL,
                                    [],
                                    $params
                                );

                                UtillsHelper::sendNotification(
                                    $transaction->investor->id,
                                    InvestorModel::class,
                                    'preipo-transaction',
                                    'Private Equity transaction',
                                    UtillsHelper::paramsToTemplate($params, 'Transaction #{{2}} for {{3}} (quantity: {{4}}) has been initiated. The payable amount for this transaction is {{5}}. Bank Account Holder Name: {{6}} Bank Name: {{7}} Bank IFSC Code: {{8}} Bank Account Number: {{9}} Branch Name: {{10}}')
                                );
                            }

                            if ($transaction->admin && $transaction->investor->is_demo == 0) {
                                $params = [
                                    $transaction->admin->name,
                                    $transaction->transaction_invoice_no,
                                    $transaction->company->brand_name,
                                    $transaction->shares,
                                    UtillsHelper::moneyFormatIndia($transaction->payable_amount),
                                    $transaction->seller->company_name,
                                    $transaction->seller->bank_name,
                                    $transaction->seller->account_number,
                                    $transaction->investor->name
                                ];
                                UtillsHelper::sendWpMessage(
                                    NotificationTypeEnum::event,
                                    'pre_ipo_bank_details_admin_sun_copy_copy',
                                    WpMessageTypeEnum::text,
                                    $transaction->admin->mobile_no,
                                    $transaction->admin->name,
                                    NULL,
                                    [],
                                    $params
                                );
                                UtillsHelper::sendNotification(
                                    $transaction->admin->id,
                                    UserAdminModel::class,
                                    'preipo-transaction',
                                    'Private Equity transaction',
                                    UtillsHelper::paramsToTemplate($params, 'Transaction #{{2}} for {{3}} (Quantity: {{4}}, Amount: Rs.{{5}}) has been shared with the investor for fund transfer. Bank Account Holder Name: {{6}}, Bank Name: {{7}}, Bank Account Number: {{8}}, Investor Name: {{9}}. Please check WhatsApp and verify the payment confirmation once received.')
                                );
                            }
                        }
                    }
                }
            }
        }
    }

    static function sendDealSlip($transaction): void
    {
        if ($transaction->usesOrderStep()) {
            return;
        }
        // Only send deal slip if KYC is done
        if (($transaction->seller || $transaction->seller_investor_id) && $transaction->investor) {
            // Check for KYC status (1 = done)
            if (!($transaction->investor->preipo_kyc_status ?? false)) {
                Log::info('Deal slip not sent: Investor KYC not completed for transaction ID ' . $transaction->id);
                return;
            }
            $sendOffer = DigioHelper::sendPreIPODealSleepNow($transaction);
            if ($sendOffer->getStatusCode() == "200") {
                $getDocResponse = json_decode($sendOffer->getBody()->getContents());
                $offerId = $getDocResponse->id;
                $document = new DocumentsModel();
                $document->api_id = $offerId;
                $document->type = DocumentTypeEnum::preipodealslip;
                $document->meta = [
                    'name' => 'Deal slip - ' . $transaction->company->brand_name,
                    'investor' => [
                        $transaction->investor->id
                    ],
                    'preipo_transactions' => [
                        $transaction->id
                    ]
                ];
                $document->save();

                $transaction->status = 2;
                $transaction->save();
            } else {
                $getDocResponse = json_decode($sendOffer->getBody()->getContents());
                ReportErrorLogModel::create([
                    'type' => 'Digio',
                    'subtype'   => 'Pre-IPO Send api error',
                    'description'   => $getDocResponse,
                    'notes'         => 'transaction = ' . $transaction->id
                ]);
            }
        }
    }

    // static function cancelTransactionNotification(PreIpoModel $transaction): void
    // {
    //     $params = [$transaction->investor->name, $transaction->company->brand_name, $transaction->shares, UtillsHelper::moneyFormatIndia($transaction->share_price), UtillsHelper::moneyFormatIndia($transaction->investment_amount), $transaction->cancellation_reason];
    //     UtillsHelper::sendNotification(
    //         $transaction->investor_id,
    //         InvestorModel::class,
    //         'pre-ipo-transactions',
    //         'Transaction Cancelled',
    //         UtillsHelper::paramsToTemplate($params, 'Your order for the following investment has been cancelled: Company: {{2}} Quantity: {{3}} shares Buying Price: INR {{4}} per share Total Investment Amount: INR {{5}} Cancellation Reason: {{6}} If you did not request this cancellation, please contact our support team immediately.')
    //     );
    //     UtillsHelper::sendWpMessage(
    //         NotificationTypeEnum::event,
    //         'notify_investor_transaction_cancelled_1',
    //         WpMessageTypeEnum::text,
    //         $transaction->investor->mobile_number,
    //         $transaction->investor->name,
    //         NULL,
    //         [],
    //         $params,
    //         ['transaction_id' => $transaction->id]
    //     );

    //     $admins = UserAdminModel::where('is_deleted', '0')->where('id', '!=', '1')->get();
    //     foreach ($admins as $key => $admin) {
    //         if (AdminHelper::hasPermission(['pre ipo transaction'], $admin->id)) {
    //             $adminParams = [$transaction->investor->name, $transaction->company->brand_name, $transaction->shares, UtillsHelper::moneyFormatIndia($transaction->share_price), UtillsHelper::moneyFormatIndia($transaction->investment_amount), $transaction->cancellation_reason];
    //             UtillsHelper::sendWpMessage(
    //                 NotificationTypeEnum::event,
    //                 'notify_admin_transaction_cancelled_1',
    //                 WpMessageTypeEnum::text,
    //                 $admin->mobile_no,
    //                 $admin->name,
    //                 NULL,
    //                 [],
    //                 $adminParams
    //             );

    //             UtillsHelper::sendNotification(
    //                 $admin->id,
    //                 UserAdminModel::class,
    //                 'pre-ipo-transactions',
    //                 'Transaction Cancelled',
    //                 UtillsHelper::paramsToTemplate($adminParams, 'The order for the investment of the investor has been cancelled. Details are as below: Investor Name: {{1}} Company: {{2}} Quantity: {{3}} shares Buying Price: INR {{4}} per share Total Investment Amount: INR {{5}} Cancellation Reason: {{6}} Please review and take any necessary action.')
    //             );
    //         }
    //     }
    // }

    static function cancelTransactionNotification(PreIpoModel $transaction): void
    {
        if ($transaction->investor->is_demo == 0) {
            $params = [$transaction->investor->name, $transaction->transaction_invoice_no, $transaction->company->brand_name, $transaction->shares, UtillsHelper::moneyFormatIndia($transaction->share_price), $transaction->cancellation_reason];
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
                NULL,
                [],
                $params,
                ['transaction_id' => $transaction->id]
            );
            $admins = UserAdminModel::where('is_deleted', '0')
                ->where('id', '!=', '1')
                ->when($transaction->updated_by, fn($q) => $q->where('id', $transaction->updated_by))
                ->get();
            foreach ($admins as $akey => $admin) {
                if ($admin && AdminHelper::hasPermission(['pre ipo transaction'], $admin->id)) {
                    $adminParams = [$admin->username ?? $admin->name, $transaction->transaction_invoice_no, $transaction->investor->name, $transaction->company->brand_name, $transaction->shares, UtillsHelper::moneyFormatIndia($transaction->share_price), UtillsHelper::moneyFormatIndia($transaction->investment_amount), $transaction->cancellation_reason ?? 'N/A'];
                    UtillsHelper::sendWpMessage(
                        NotificationTypeEnum::event,
                        'notify_admin_transaction_cancelled_sun',
                        WpMessageTypeEnum::text,
                        $admin->mobile_no,
                        $admin->name,
                        NULL,
                        [],
                        $adminParams
                    );

                    UtillsHelper::sendNotification(
                        $admin->id,
                        UserAdminModel::class,
                        'pre-ipo-transactions',
                        'Transaction Cancelled',
                        UtillsHelper::paramsToTemplate($adminParams, 'The transaction #{{2}} has been cancelled. Investor Name: {{3}} Company: {{4}} Quantity: {{5}} Buying Price: {{6}} Total Investment: {{7}} Cancellation Reason: {{8}} Please review and take any necessary action.')
                    );
                }
            }
        }
    }
}
