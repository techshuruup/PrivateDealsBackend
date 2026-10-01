<?php

namespace App\Http\Controllers\Api\V2\Business;

use App\Enums\PartnerTypeEnum;
use App\Helpers\PreIpoOrderStepHelper;
use App\Helpers\UtillsHelper;
use App\Http\Controllers\Controller;
use App\Models\PartnerModel;
use App\Models\PreIpoModel;
use App\Services\PreIpoOrderStepService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class InstitutionPreIpoOrderController extends Controller
{
    public function __construct(private readonly PreIpoOrderStepService $orders)
    {
    }

    public function list(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        $partner = request()->user();
        $transactions = $this->orders->institutionQuery((int) $partner->id)
            ->with(['company:id,uuid,brand_name,logo', 'investor:id,name,is_self,partner_id'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (PreIpoModel $transaction) => $this->present($transaction));

        return UtillsHelper::json(1, [
            'message' => 'Transaction list',
            'data' => $transactions,
        ]);
    }

    public function detail(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        $transaction = $this->find(request()->input('transaction_id'));
        if ($transaction instanceof JsonResponse) {
            return $transaction;
        }

        return UtillsHelper::json(1, [
            'message' => 'Transaction detail',
            'data' => $this->present($transaction),
        ]);
    }

    public function approve(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        $transaction = $this->find(request()->input('transaction_id'));
        if ($transaction instanceof JsonResponse) {
            return $transaction;
        }

        $result = $this->orders->approve($transaction);
        if (!$result['ok']) {
            return UtillsHelper::json(0, ['message' => $result['message']]);
        }

        return UtillsHelper::json(1, [
            'message' => $result['message'],
            'data' => $this->present($transaction->fresh()),
        ]);
    }

    public function reject(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        $validation = Validator::make(request()->all(), [
            'transaction_id' => 'required|integer|min:1',
            'reason' => 'required|string|max:1000',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $transaction = $this->find(request()->input('transaction_id'));
        if ($transaction instanceof JsonResponse) {
            return $transaction;
        }

        $result = $this->orders->reject($transaction, (string) request()->input('reason'));
        if (!$result['ok']) {
            return UtillsHelper::json(0, ['message' => $result['message']]);
        }

        return UtillsHelper::json(1, [
            'message' => $result['message'],
            'data' => $this->present($transaction->fresh()),
        ]);
    }

    public function confirmPayment(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        $transaction = $this->find(request()->input('transaction_id'));
        if ($transaction instanceof JsonResponse) {
            return $transaction;
        }

        $result = $this->orders->confirmPayment($transaction);
        if (!$result['ok']) {
            return UtillsHelper::json(0, ['message' => $result['message']]);
        }

        return UtillsHelper::json(1, [
            'message' => $result['message'],
            'data' => $this->present($transaction->fresh()),
        ]);
    }

    public function shareTransferReceipt(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        $validation = Validator::make(request()->all(), [
            'transaction_id' => 'required|integer|min:1',
            'file' => 'required|file|mimes:png,jpg,jpeg,pdf|max:'.UtillsHelper::maxFileDocumentSizeInKB(),
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $transaction = $this->find(request()->input('transaction_id'));
        if ($transaction instanceof JsonResponse) {
            return $transaction;
        }

        $result = $this->orders->uploadShareTransferReceipt($transaction, request()->file('file'));
        if (!$result['ok']) {
            return UtillsHelper::json(0, ['message' => $result['message']]);
        }

        return UtillsHelper::json(1, [
            'message' => $result['message'],
            'data' => $this->present($transaction->fresh()),
        ]);
    }

    private function find(mixed $transactionId): PreIpoModel|JsonResponse
    {
        $validation = Validator::make(['transaction_id' => $transactionId], [
            'transaction_id' => 'required|integer|min:1',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $transaction = $this->orders->findForInstitution(request()->user(), (int) $transactionId);
        if (!$transaction) {
            return UtillsHelper::json(0, ['message' => 'Transaction not found']);
        }

        return $transaction;
    }

    private function present(PreIpoModel $transaction): array
    {
        return PreIpoOrderStepHelper::map($transaction, PreIpoOrderStepHelper::AUDIENCE_INSTITUTION);
    }

    private function institutionOnly(): ?JsonResponse
    {
        $partner = request()->user();
        if (!$partner instanceof PartnerModel || $partner->type !== PartnerTypeEnum::institution->value) {
            return UtillsHelper::json(0, [
                'message' => 'Only Institution partners can manage these orders.',
            ]);
        }

        return null;
    }
}
