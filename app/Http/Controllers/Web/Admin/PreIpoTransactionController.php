<?php

namespace App\Http\Controllers\Web\Admin;

use App\DataTables\PreIpoTransactionDataTable;
use App\Enums\DocumentTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\Utills\StatusEnum;
use App\Enums\WpMessageTypeEnum;
use App\Helpers\AdminHelper;
use App\Helpers\DateTimeHelper;
use App\Helpers\DigioHelper;
use App\Helpers\DocumentHelper;
use App\Helpers\FileUpDownHelper;
use App\Helpers\PreIpoOrderStepHelper;
use App\Helpers\PreIpoTransactionHelper;
use App\Helpers\UtillsHelper;
use App\Helpers\WebhookHelper;
use App\Http\Controllers\Controller;
use App\Jobs\InvestorActionWindowStartJob;
use App\Jobs\preipo\CancelNotificationJob;
use App\Jobs\SendDealSlipJob;
use App\Models\DocumentsModel;
use App\Models\DynamicUrlModel;
use App\Models\InvestorModel;
use App\Models\PortfolioPreIpoModel;
use App\Models\PreIpoModel;
use App\Models\ReportErrorLogModel;
use App\Models\SellerMasterModel;
use App\Services\PreIpoOrderStepService;
use App\Services\PreIpoTimerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

class PreIpoTransactionController extends Controller
{

    function approveTransaction(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'status'   => 'required|in:approve,reject',
            'transaction' => 'required',
            'notes' => 'nullable|string',
            'confirmation_file' => 'nullable|file|max:' . UtillsHelper::maxFileDocumentSizeInKB(),
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        // $transaction = PreIpoModel::where('id', $request->transaction)->where('created_by', NULL)->first();
        $transaction = PreIpoModel::where('id', $request->transaction)
            ->where('status', 0)
            ->whereNull('order_step')
            ->first();
        if ($request->filled('temptoken')) {
            DynamicUrlModel::where('token', $request->temptoken)
                ->update(['expired' => 1]);
        }
        if (!$transaction) {
            return UtillsHelper::json(0, ['message' => 'Transaction Already Assigned', 'remove_transaction' => true]);
        } else {

            if ($request->status == 'approve' && !$transaction->partner_id) {
                $sellerValidation = Validator::make($request->all(), [
                    'seller' => 'required',
                ]);
                if ($sellerValidation->fails()) {
                    return UtillsHelper::json(0, ['message' => 'The seller field is required when status is approve.']);
                }
            }

            $transaction->status = 1;
            $transaction->cancellation_reason = $request->notes;
            if ($request->status == 'approve') {
                if (!$transaction->partner_id) {
                    $transaction->seller_id = $request->seller;
                }
                $transaction->status = 2;
            }

            if ($request->hasFile('confirmation_file')) {
                if ($path = FileUpDownHelper::preipo_document_upload($request->file('confirmation_file'))) {
                    $document = new DocumentsModel();
                    $document->path = $path;
                    $document->signed_path = $path;
                    $document->type = DocumentTypeEnum::preiporejection;
                    if ($request->status == 'approve') {
                        $document->type = DocumentTypeEnum::preipoapproval;
                    }
                    $document->meta = [
                        'name' => $transaction->status == 1 ? 'Rejection Document' : 'Approval Document',
                        'preipo_transactions' => [
                            $transaction->id
                        ]
                    ];
                    $document->status = 1;
                    $document->save();
                }
            }

            if ($transaction->created_by === NULL) {
                $transaction->created_by = Auth::guard('admin')->user()->id;
            }
            $transaction->updated_by = Auth::guard('admin')->user()->id;
            $transaction->save();

            if ($transaction->status == 2) {
                InvestorActionWindowStartJob::dispatch($transaction->id);
                try {
                    $timerService = app(PreIpoTimerService::class);
                    $timerService->setInvestorActionTimer($transaction);
                    $timerService->logStatusChange($transaction, 2);
                } catch (Throwable $e) {
                    Log::error('PreIpoTimerService@setInvestorActionTimer failed: ' . $e->getMessage());
                }
                // PreIpoTransactionHelper::sendDealSlip($transaction);
                if ($transaction->investor && $transaction->investor->preipo_kyc_status == 1) {
                    SendDealSlipJob::dispatch($transaction->id);
                } elseif ($transaction->investor->is_demo == 0) {
                    $params = [
                        $transaction->transaction_invoice_no,
                        $transaction->company->brand_name,
                        $transaction->shares,
                        UtillsHelper::moneyFormatIndia($transaction->share_price),
                    ];
                    UtillsHelper::sendWpMessage(
                        NotificationTypeEnum::event,
                        'preipo_deal_kycpending_sun2',
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
                        'preipo-kyc-incomplete',
                        'Pre-IPO Transaction requires KYC completion',
                        UtillsHelper::paramsToTemplate($params, 'Transaction ID: #{{1}} Your transaction request has been approved. To proceed with the process, KYC completion is required. The deal slip will be generated and shared after successful KYC verification. Company Name: {{2}} Quantity: {{3}} Buying Price: {{4}} Please complete the KYC to continue processing this transaction.')
                    );
                }
            }
            if ($transaction->status == 1) {
                CancelNotificationJob::dispatch($transaction->id);
            }
            return UtillsHelper::json(1, ['message' => 'Transaction Updated']);
        }
    }

    public function resendDealSlip($transaction_id)
    {
        try {
            $transaction = PreIpoModel::findOrFail($transaction_id);
            if ($transaction->status != 2) {
                return redirect()->back()->with('error', 'Deal slip can only be resent for transactions with status "Deal Slip send"');
            }
            if (!$transaction->seller || !$transaction->investor) {
                return redirect()->back()->with('error', 'Cannot resend deal slip: Missing seller or investor information');
            }
            // Find previous deal slips for this investor in this transaction
            $previousDealSlips = DocumentsModel::where('type', DocumentTypeEnum::preipodealslip)
                ->whereJsonContains('meta->preipo_transactions', $transaction->id)
                ->whereJsonContains('meta->investor', $transaction->investor->id)
                ->whereNotNull('api_id')
                ->orderByDesc('id')
                ->get();

            if ($previousDealSlips->isEmpty()) {
                // No previous deal slip, send new
                SendDealSlipJob::dispatch($transaction->id, true);
                return redirect()->back()->with('success', 'Deal slip sent successfully.');
            }

            $latestDealSlip = $previousDealSlips->first();
            $signer = $latestDealSlip->signers()->first();
            $isExpired = false;
            if ($signer && $signer->expire_on) {
                $isExpired = now()->greaterThan($signer->expire_on);
            }

            if ($isExpired) {
                // Cancel all previous deal slips
                foreach ($previousDealSlips as $dealSlip) {
                    DigioHelper::cancelDigioRequest($dealSlip->api_id);
                }
                SendDealSlipJob::dispatch($transaction->id, true);
                return redirect()->back()->with('success', 'Previous deal slip expired. New deal slip sent successfully.');
            } elseif ($signer) {
                // Send WhatsApp notification in the same way as DocumentHelper
                $params = [
                    $transaction->transaction_invoice_no,
                    $transaction->company->brand_name,
                    $transaction->shares,
                    UtillsHelper::moneyFormatIndia($transaction->investment_amount),
                ];
                UtillsHelper::sendWpMessage(
                    NotificationTypeEnum::event,
                    'preipo_on_dealslip_investor_sun_1',
                    WpMessageTypeEnum::text,
                    $transaction->investor->mobile_number,
                    $transaction->investor->name,
                    null,
                    [$signer->link],
                    $params
                );
                UtillsHelper::sendNotification(
                    $transaction->investor->id,
                    InvestorModel::class,
                    'preipo-transaction',
                    'Private Equity transaction',
                    UtillsHelper::paramsToTemplate(
                        $params,
                        'Your transaction #{{1}} has been approved and deal slip for the same has been generated. Kindly review and sign the deal slip via button, given below, at your earliest to continue with the process. Company: {{2}} Quantity: {{3}} Buying Price: {{4}} For any assistance related to this transaction, please contact our support team.'
                    )
                );
                return redirect()->back()->with('info', 'Deal slip already sent and is still valid. Expiry: ' . $signer->expire_on);
            } else {
                SendDealSlipJob::dispatch($transaction->id, true);
                return redirect()->back()->with('success', 'Deal slip sent (status unknown, fallback).');
            }
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while resending the deal slip. Please try again.');
        }
    }

    function market(Request $request): View
    {
        setPageTitle('Pre-IPO Market');
        $data['sellers'] = SellerMasterModel::where('is_deleted', '0')->get();

        $data['transactions'] = PreIpoModel::where('created_by', NULL)->where('status', '0')
            ->whereNull('order_step')
            ->whereHas('investor', function ($query) {
                $query->where('is_demo', 0)->where('is_deleted', 0);
            })
            ->with([
                'investor' => function ($query) {
                    $query->select(['id', 'name', 'mobile_number', 'gender', 'profile_photo', 'uuid', 'partner_id'])
                        ->with(['partner' => function ($partnerQuery) {
                            $partnerQuery->select(['id', 'name']);
                        }]);
                },
                'company' => function ($query) {
                    $query->select(['id', 'logo', 'brand_name', 'uuid', 'share_price']);
                }
            ])
            ->get();

        return view('admin.pages.pre-ipo-transactions.market', $data);
    }


    public function list(PreIpoTransactionDataTable $dataTable, Request $request)
    {
        addVendor('datatables');

        if ($request->routeIs('admin.preipotransaction.pending')) {
            setPageTitle('Pre Ipo Pending Transactions');
        } elseif ($request->routeIs('admin.preipotransaction.completed')) {
            setPageTitle('Pre Ipo Completed Transactions');
        } elseif ($request->routeIs('admin.preipotransaction.rejected')) {
            setPageTitle('Pre Ipo Rejected Transactions');
        }

        setPageTitle('Pre Ipo Transactions');
        return $dataTable->render('admin.pages.pre-ipo-transactions.list');
    }

    function status($transaction_id): RedirectResponse
    {
        $transaction = PreIpoModel::where('id', $transaction_id)->first();
        if ($transaction && $transaction->usesOrderStep()) {
            return redirect()->back()->with('error', 'This order uses the partner order step flow.');
        }
        if ($transaction) {
            if ($transaction->status == 3) {
                $transaction->status = 4;
                $transaction->save();

                // RESTORE TIMER AND LOGGING LOGIC
                app(PreIpoTimerService::class)->setShareTransferTimer($transaction);
                app(PreIpoTimerService::class)->logStatusChange($transaction, 4);

                if ($transaction->investor && $transaction->company && $transaction->investor->is_demo == 0) {

                    $params = [
                        $transaction->investor->name,
                        $transaction->transaction_invoice_no,
                        $transaction->company->brand_name,
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

                    UtillsHelper::sendNotification(
                        $transaction->investor->id,
                        InvestorModel::class,
                        'preipo-transaction',
                        'Private Equity transaction',
                        UtillsHelper::paramsToTemplate(
                            $params,
                            'We have successfully received your payment for transaction #{{2}} related to {{3}} (quantity: {{4}}). Your share transfer is scheduled to be completed on {{5}}. You will be notified once the transfer is successfully executed. If you need any clarification or assistance, our team is available to support you.'
                        )
                    );
                }
                return redirect()->back()->with('success', 'Payment received');
            }
            if ($transaction->status == 4) {
                $transaction->status = 5;
                $transaction->transaction_cancel_timer = null;
                $transaction->timer_desc = null;
                $transaction->portfolio_id = UtillsHelper::preIpoPortfolio($transaction);
                $transaction->save();

                app(PreIpoTimerService::class)->logStatusChange($transaction, 5);
                if ($transaction->investor && $transaction->company && $transaction->investor->is_demo == 0) {

                    $params = [
                        $transaction->investor->name,
                        $transaction->transaction_invoice_no,
                        $transaction->company->brand_name,
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

                    UtillsHelper::sendNotification(
                        $transaction->investor->id,
                        InvestorModel::class,
                        'preipo-transaction',
                        'Private Equity transaction',
                        UtillsHelper::paramsToTemplate(
                            $params,
                            'We are pleased to inform you that the share transfer for transaction #{{2}} related to {{3}} (quantity: {{4}}) has been successfully completed. The shares have been credited to your demat account, and the transaction is now closed. You can view the updated holdings in the portfolio section of your account. If you need any clarification or assistance, our team is available to support you.'
                        )
                    );
                }


                return redirect()->back()->with('success', 'Transaction completed');
            }
        }
        return redirect()->back()->with('error', 'Transaction not found');
    }

    function slipStatus($transaction_id): RedirectResponse
    {
        $item = PreIpoModel::where('id', $transaction_id)->first();
        if ($item) {
            $documentRow = DocumentsModel::where('type', DocumentTypeEnum::preipodealslip->value)->whereJsonContains('meta->preipo_transactions', $item->id)->first();
            if ($documentRow) {
                $documentApi =  DigioHelper::getDocDetails($documentRow->api_id);
                if ($documentApi->getStatusCode() == "200") {
                    $documentApiResponse = json_decode($documentApi->getBody()->getContents());
                    if ($documentApiResponse->agreement_status == 'completed') {
                        $downloadDocApi = DigioHelper::downloadDocment($documentRow->api_id);
                        if ($downloadDocApi->getStatusCode() == "200") {
                            $file = $downloadDocApi->getBody()->getContents();
                            WebhookHelper::dealslipWebhook($documentRow, $file);
                            return redirect()->back()->with('success', 'Document status updated');
                        }
                        return redirect()->back()->with('success', 'Please try again later');
                    }
                    return redirect()->back()->with('error', 'Agreement status is - ' . $documentApiResponse->agreement_status);
                }
                $getDocResponse = json_decode($documentApi->getBody()->getContents());
                return redirect()->back()->with('error', $getDocResponse->message);
            }
            return redirect()->back()->with('error', 'Document not found');
        }
        return redirect()->back()->with('error', 'Item not found');
    }

    public function downloadDealSlip($transactionId): Response|JsonResponse|RedirectResponse
    {
        try {
            $transaction = PreIpoModel::with(['company', 'investor', 'seller', 'admin'])
                ->where('id', $transactionId)
                ->first();

            if (!$transaction) {
                return redirect()->back()->with('error', 'Transaction not found');
            }


            $data = [
                'transaction' => $transaction,
                'company' => $transaction->company,
                'investor' => $transaction->investor,
                'seller' => $transaction->seller,
                'admin' => $transaction->admin,
                'generated_at' => now()->format('d M Y, h:i A'),
                'deal_slip_file' => $transaction->deal_slip,
                'approval_file' => $transaction->approval_file,
            ];

            $pdf = Pdf::loadView('admin.pages.pre-ipo-transactions.deal-slip-pdf', $data);
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                // 'dpi' => 150,
                'defaultFont' => 'Times-Roman',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

            $filename = 'deal-slip-' . $transaction->id . '-' . now()->format('Y-m-d') . '.pdf';

            return $pdf->download($filename);
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Error generating deal slip');
        }
    }

    public function deletePreIpoTransaction($id): RedirectResponse
    {
        $preIpoTransaction = PreIpoModel::find($id);
        if (!$preIpoTransaction) {
            return redirect()->back()->with('error', 'Transaction not found');
        }
        $preIpoTransaction->delete();

        UtillsHelper::preIpoPortfolioDelete($preIpoTransaction->portfolio_id);

        DocumentsModel::whereJsonContains('meta->preipo_transactions', $preIpoTransaction->id)->delete();

        AdminHelper::logPut(
            'Pre-IPO Transaction deleted for ' . $preIpoTransaction->company->name,
            InvestorModel::class,
            $preIpoTransaction->investor_id
        );

        return redirect()->back()->with('success', 'Transaction deleted successfully');
    }

    /**
     * Extend the timer for a pre-IPO transaction
     * Allows admin to extend the timer on orders in market (status 0)
     * or on investor action window (status 2/3)
     */
    public function extendTimer(Request $request, $transaction_id): JsonResponse
    {
        try {
            $transaction = PreIpoModel::where('id', $transaction_id)->first();

            if (!$transaction) {
                return UtillsHelper::json(0, ['message' => 'Transaction not found']);
            }

            if ($transaction->usesOrderStep()) {
                return UtillsHelper::json(0, ['message' => 'This order uses the partner order step flow.']);
            }

            // Only allow extending timer for status 0, 2, and 3
            if (!in_array($transaction->status, [0, 2, 3])) {
                return UtillsHelper::json(0, ['message' => 'Cannot extend timer for this transaction status']);
            }

            // Get optional custom extension hours, defaults based on status
            $extendByHours = $request->input('extend_hours');

            // Extend the timer
            $timerService = app(PreIpoTimerService::class);
            $timerService->extendTimer($transaction, $extendByHours);
            $timerService->logStatusChange($transaction, $transaction->status);

            // Log the action
            AdminHelper::logPut(
                'Pre-IPO Transaction timer extended for ' . $transaction->company->name . ' (TN: ' . $transaction->transaction_invoice_no . ')',
                PreIpoModel::class,
                $transaction->id
            );

            return UtillsHelper::json(1, [
                'message' => 'Timer extended successfully',
                'new_timer' => $transaction->fresh()->transaction_cancel_timer
            ]);
        } catch (Throwable $e) {
            Log::error('PreIpoTransactionController@extendTimer failed: ' . $e->getMessage());
            return UtillsHelper::json(0, ['message' => 'An error occurred while extending timer']);
        }
    }

    /**
     * Retrieve an auto-cancelled transaction
     * Changes status from 1 (cancelled) back to 0 (pending)
     * and extends the timer to give admin time to process
     */
    public function retrieveTransaction(Request $request, string $transaction_id): JsonResponse
    {
        try {
            // Validate status
            $request->validate([
                'last_status' => 'required|in:0,3,4',
            ]);

            $transaction = PreIpoModel::where('id', $transaction_id)
                ->where('status', 1) // Only cancelled transactions
                ->first();

            if (!$transaction) {
                return UtillsHelper::json(0, ['message' => 'Transaction not found or is not cancelled']);
            }

            if ($transaction->usesOrderStep()) {
                return UtillsHelper::json(0, ['message' => 'This order uses the partner order step flow.']);
            }

            if (!$transaction->investor || !$transaction->company) {
                return UtillsHelper::json(0, ['message' => 'Transaction is missing investor or company information']);
            }


            $transaction->status              = $request->last_status;
            $transaction->cancellation_reason = null;
            $transaction->updated_by          = Auth::guard('admin')->user()->id;
            $transaction->save();

            // Set a fresh timer for admin review
            $timerService = app(PreIpoTimerService::class);

            if ($request->last_status == 0) {
                $timerService->setOrderPlacedTimer($transaction);
            } elseif ($request->last_status == 3) {
                $timerService->setInvestorActionTimer($transaction);
            }

            $timerService->logStatusChange($transaction, $request->last_status);

            // Send WhatsApp notification to investor that their transaction has been retrieved
            if ($transaction->investor->is_demo == 0) {
                $params = [
                    $transaction->investor->name,
                    $transaction->company->brand_name,
                    $transaction->shares,
                    UtillsHelper::moneyFormatIndia($transaction->share_price),
                    UtillsHelper::moneyFormatIndia($transaction->investment_amount),
                ];

                UtillsHelper::sendWpMessage(
                    NotificationTypeEnum::event,
                    'retrieve',
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
                    'Pre-IPO Transaction - Action Required',
                    UtillsHelper::paramsToTemplate(
                        $params,
                        'Please note that the below-mentioned transaction, which was auto-cancelled earlier, has now been successfully retrieved: Investor Name: {{1}} Company: {{2}} Quantity: {{3}} Buying Price: {{4}} per share Total Investment: {{5}} Kindly note the same.'
                    )
                );
            }

            // Log the action
            AdminHelper::logPut(
                'Pre-IPO Transaction retrieved from cancellation - ' . $transaction->company->name . ' (TN: ' . $transaction->transaction_invoice_no . ')',
                PreIpoModel::class,
                $transaction->id
            );

            return UtillsHelper::json(1, [
                'message'        => 'Transaction retrieved successfully. Investor has been notified.',
                'transaction_id' => $transaction->id
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return UtillsHelper::json(0, ['message' => 'Please select a valid status']);
        } catch (Throwable $e) {
            Log::error('PreIpoTransactionController@retrieveTransaction failed: ' . $e->getMessage());
            return UtillsHelper::json(0, ['message' => 'An error occurred while retrieving transaction']);
        }
    }

    public function orderSteps(): View
    {
        setPageTitle('Pre-IPO partner order steps');
        $transactions = PreIpoModel::query()
            ->whereNotNull('order_step')
            ->with(['investor', 'company'])
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function (PreIpoModel $transaction) {
                $labels = PreIpoOrderStepHelper::labels($transaction, PreIpoOrderStepHelper::AUDIENCE_ADMIN);

                return [
                    'model' => $transaction,
                    'current' => $labels['current'],
                    'next' => $labels['next'],
                    'action' => $labels['action'] ?? [],
                ];
            });

        return view('admin.pages.pre-ipo-transactions.order-steps', [
            'transactions' => $transactions,
        ]);
    }

    public function orderStepApprove($transaction_id): RedirectResponse
    {
        return $this->orderStepRedirect($transaction_id, function (PreIpoModel $transaction) {
            return app(PreIpoOrderStepService::class)->approve($transaction);
        });
    }

    public function orderStepReject(Request $request, $transaction_id): RedirectResponse
    {
        $validation = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
        ]);
        if ($validation->fails()) {
            return redirect()->back()->with('error', $validation->errors()->first());
        }

        return $this->orderStepRedirect($transaction_id, function (PreIpoModel $transaction) use ($request) {
            return app(PreIpoOrderStepService::class)->reject($transaction, (string) $request->reason);
        });
    }

    public function orderStepConfirmPayment($transaction_id): RedirectResponse
    {
        return $this->orderStepRedirect($transaction_id, function (PreIpoModel $transaction) {
            return app(PreIpoOrderStepService::class)->confirmPayment($transaction);
        });
    }

    public function orderStepShareTransferReceipt(Request $request, $transaction_id): RedirectResponse
    {
        $validation = Validator::make($request->all(), [
            'file' => 'required|file|mimes:png,jpg,jpeg,pdf|max:'.UtillsHelper::maxFileDocumentSizeInKB(),
        ]);
        if ($validation->fails()) {
            return redirect()->back()->with('error', $validation->errors()->first());
        }

        return $this->orderStepRedirect($transaction_id, function (PreIpoModel $transaction) use ($request) {
            return app(PreIpoOrderStepService::class)->uploadShareTransferReceipt($transaction, $request->file('file'));
        });
    }

    private function orderStepRedirect($transaction_id, callable $action): RedirectResponse
    {
        $transaction = PreIpoModel::query()->whereKey($transaction_id)->whereNotNull('order_step')->first();
        if (!$transaction) {
            return redirect()->back()->with('error', 'Transaction not found');
        }

        $result = $action($transaction);
        if (!($result['ok'] ?? false)) {
            return redirect()->back()->with('error', $result['message'] ?? 'This action is not available for the current order step.');
        }

        return redirect()->back()->with('success', $result['message']);
    }
}
