<?php

namespace App\Services;

use App\Enums\DocumentTypeEnum;
use App\Enums\PreIpoOrderStepEnum;
use App\Enums\Utills\StatusEnum;
use App\Helpers\DocumentHelper;
use App\Helpers\FileUpDownHelper;
use App\Helpers\PreIpoOrderNotificationHelper;
use App\Helpers\PreIpoOrderStepHelper;
use App\Helpers\UtillsHelper;
use App\Models\DocumentsModel;
use App\Models\PartnerModel;
use App\Models\PreIpoModel;
use App\Models\PreIpoTransactionPaymentsModel;
use Illuminate\Http\UploadedFile;

class PreIpoOrderStepService
{
    public function sendBuyMandate(PreIpoModel $transaction): bool
    {
        $sent = DocumentHelper::sendBuyMandate($transaction);
        if ($sent) {
            PreIpoOrderNotificationHelper::mandatePending($transaction);
        }

        return $sent;
    }

    public function onMandateSigned(PreIpoModel $transaction): void
    {
        if ($transaction->order_step !== PreIpoOrderStepEnum::mandate_pending->value) {
            return;
        }

        $transaction->order_step = PreIpoOrderStepEnum::share_confirmation_pending->value;
        $transaction->save();
        PreIpoOrderNotificationHelper::shareConfirmationPending($transaction);
    }

    public function onMandateDocumentSigned(DocumentsModel $document): void
    {
        foreach ($this->transactionIds($document) as $id) {
            $transaction = PreIpoModel::find($id);
            if ($transaction && $transaction->usesOrderStep()) {
                $this->onMandateSigned($transaction);
            }
        }
    }

    public function cancelByPartner(PreIpoModel $transaction, string $reason): array
    {
        if ($transaction->order_step !== PreIpoOrderStepEnum::mandate_pending->value) {
            return $this->wrongStep();
        }

        $this->markCancelled($transaction, $reason);
        PreIpoOrderNotificationHelper::cancelled($transaction, false);

        return $this->ok('Transaction cancelled.');
    }

    public function approve(PreIpoModel $transaction): array
    {
        if ($transaction->order_step !== PreIpoOrderStepEnum::share_confirmation_pending->value) {
            return $this->wrongStep();
        }

        $sent = DocumentHelper::dealSlipDocumentSend($transaction, true);
        if (!$sent) {
            return ['ok' => false, 'message' => 'The deal slip could not be sent.'];
        }

        $transaction->order_step = PreIpoOrderStepEnum::deal_slip_pending->value;
        $transaction->save();
        PreIpoOrderNotificationHelper::dealSlipPending($transaction);

        return $this->ok('Deal slip sent.');
    }

    public function reject(PreIpoModel $transaction, string $reason): array
    {
        if ($transaction->order_step !== PreIpoOrderStepEnum::share_confirmation_pending->value) {
            return $this->wrongStep();
        }

        $this->markCancelled($transaction, $reason);
        PreIpoOrderNotificationHelper::cancelled($transaction, true);

        return $this->ok('Transaction cancelled.');
    }

    public function onDealSlipSigned(PreIpoModel $transaction): void
    {
        if ($transaction->order_step !== PreIpoOrderStepEnum::deal_slip_pending->value) {
            return;
        }

        $transaction->order_step = PreIpoOrderStepEnum::payment_pending->value;
        $transaction->save();
        PreIpoOrderNotificationHelper::paymentPending($transaction);
    }

    public function onDealSlipDocumentSigned(DocumentsModel $document): void
    {
        foreach ($this->transactionIds($document) as $id) {
            $transaction = PreIpoModel::find($id);
            if ($transaction && $transaction->usesOrderStep()) {
                $this->onDealSlipSigned($transaction);
            }
        }
    }

    public function uploadPaymentReceipt(PreIpoModel $transaction, UploadedFile $file): array
    {
        if ($transaction->order_step !== PreIpoOrderStepEnum::payment_pending->value) {
            return $this->wrongStep();
        }

        $filePath = FileUpDownHelper::upload_preipo_transaction_payment_receipt_document($file);
        if (!$filePath) {
            return ['ok' => false, 'message' => 'Payment receipt upload failed.'];
        }

        $transaction->loadMissing(['investor', 'company']);
        $document = new DocumentsModel();
        $document->path = $filePath;
        $document->signed_path = $filePath;
        $document->status = 1;
        $document->type = DocumentTypeEnum::paymentreceipt;
        $document->meta = [
            'investor' => [$transaction->investor_id],
            'preipo_transaction' => [$transaction->id],
            'preipo_transactions' => [$transaction->id],
            'company' => [$transaction->company_id],
            'name' => 'Payment Receipt - '.($transaction->company->brand_name ?? ''),
            'sname' => 'Payment Receipt from - '.($transaction->investor->name ?? ''),
        ];
        $document->save();

        $payment = new PreIpoTransactionPaymentsModel();
        $payment->transaction_id = $transaction->id;
        $payment->document_id = $document->id;
        $payment->status = StatusEnum::pending->value;
        $payment->save();

        $transaction->order_step = PreIpoOrderStepEnum::payment_confirmation_pending->value;
        $transaction->save();
        PreIpoOrderNotificationHelper::paymentConfirmationPending($transaction);

        return $this->ok('Payment receipt uploaded.');
    }

    public function confirmPayment(PreIpoModel $transaction): array
    {
        if ($transaction->order_step !== PreIpoOrderStepEnum::payment_confirmation_pending->value) {
            return $this->wrongStep();
        }

        $payment = $transaction->payment;
        if ($payment) {
            $payment->status = StatusEnum::approved->value;
            $payment->save();
        }

        $transaction->order_step = PreIpoOrderStepEnum::share_transfer_pending->value;
        $transaction->save();
        PreIpoOrderNotificationHelper::shareTransferPending($transaction);

        return $this->ok('Payment confirmed.');
    }

    public function uploadShareTransferReceipt(PreIpoModel $transaction, UploadedFile $file): array
    {
        if ($transaction->order_step !== PreIpoOrderStepEnum::share_transfer_pending->value) {
            return $this->wrongStep();
        }

        $filePath = FileUpDownHelper::preipo_document_upload($file);
        if (!$filePath) {
            return ['ok' => false, 'message' => 'Share-transfer receipt upload failed.'];
        }

        $transaction->loadMissing(['investor', 'company']);
        $document = new DocumentsModel();
        $document->path = $filePath;
        $document->signed_path = $filePath;
        $document->status = 1;
        $document->type = DocumentTypeEnum::preiposharetransferreceipt;
        $document->meta = [
            'investor' => [$transaction->investor_id],
            'preipo_transactions' => [$transaction->id],
            'name' => 'Share transfer receipt - '.($transaction->company->brand_name ?? ''),
        ];
        $document->save();

        $transaction->order_step = PreIpoOrderStepEnum::share_transfer_confirmation_pending->value;
        $transaction->save();
        PreIpoOrderNotificationHelper::shareTransferConfirmationPending($transaction);

        return $this->ok('Share-transfer receipt uploaded.');
    }

    public function confirmShareTransfer(PreIpoModel $transaction): array
    {
        if ($transaction->order_step !== PreIpoOrderStepEnum::share_transfer_confirmation_pending->value) {
            return $this->wrongStep();
        }

        $transaction->order_step = PreIpoOrderStepEnum::completed->value;
        $transaction->portfolio_id = UtillsHelper::preIpoPortfolio($transaction);
        $transaction->save();
        PreIpoOrderNotificationHelper::completed($transaction);

        return $this->ok('Transaction completed.');
    }

    public function findForBuyingPartner(PartnerModel $partner, int $transactionId): ?PreIpoModel
    {
        $investorIds = PreIpoOrderStepHelper::buyingPartnerInvestorIds($partner);
        if ($investorIds === []) {
            return null;
        }

        return PreIpoModel::query()
            ->whereKey($transactionId)
            ->whereIn('investor_id', $investorIds)
            ->whereNotNull('order_step')
            ->first();
    }

    public function institutionQuery(int $partnerId)
    {
        $query = PreIpoModel::query()
            ->where('partner_id', $partnerId)
            ->whereNotNull('order_step')
            ->where('order_step', '!=', PreIpoOrderStepEnum::mandate_pending->value);

        $hidden = [];
        $cancelledIds = (clone $query)->where('order_step', PreIpoOrderStepEnum::cancelled->value)->pluck('id');
        foreach ($cancelledIds as $id) {
            $signed = DocumentsModel::query()
                ->where('type', DocumentTypeEnum::buymandate->value)
                ->where('status', '1')
                ->whereJsonContains('meta->preipo_transactions', (int) $id)
                ->exists();
            if (!$signed) {
                $hidden[] = (int) $id;
            }
        }

        if ($hidden !== []) {
            $query->whereNotIn('id', $hidden);
        }

        return $query;
    }

    public function findForInstitution(PartnerModel $partner, int $transactionId): ?PreIpoModel
    {
        return $this->institutionQuery((int) $partner->id)->whereKey($transactionId)->first();
    }

    private function markCancelled(PreIpoModel $transaction, string $reason): void
    {
        $transaction->order_step = PreIpoOrderStepEnum::cancelled->value;
        $transaction->cancellation_reason = $reason;
        $transaction->save();
    }

    private function transactionIds(DocumentsModel $document): array
    {
        $meta = $document->meta;
        $ids = [];
        if (is_object($meta) && isset($meta->preipo_transactions)) {
            $ids = (array) $meta->preipo_transactions;
        } elseif (is_array($meta) && isset($meta['preipo_transactions'])) {
            $ids = (array) $meta['preipo_transactions'];
        }

        return array_values(array_filter(array_map('intval', $ids)));
    }

    private function ok(string $message): array
    {
        return ['ok' => true, 'message' => $message];
    }

    private function wrongStep(): array
    {
        return ['ok' => false, 'message' => 'This action is not available for the current order step.'];
    }
}
