<?php

namespace App\Http\Controllers\Api\V2\Business;

use App\Helpers\PreIpoOrderStepHelper;
use App\Helpers\UtillsHelper;
use App\Http\Controllers\Controller;
use App\Models\PreIpoModel;
use App\Services\PreIpoOrderStepService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PreIpoOrderController extends Controller
{
    public function __construct(private readonly PreIpoOrderStepService $orders)
    {
    }

    public function detail(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'transaction_id' => 'required|integer|min:1',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $transaction = $this->orders->findForBuyingPartner($request->user(), (int) $request->transaction_id);
        if (!$transaction) {
            return UtillsHelper::json(0, ['message' => 'Transaction not found']);
        }

        return UtillsHelper::json(1, [
            'message' => 'Transaction detail',
            'data' => $this->present($transaction),
        ]);
    }

    public function cancel(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'transaction_id' => 'required|integer|min:1',
            'reason' => 'required|string|max:1000',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $transaction = $this->orders->findForBuyingPartner($request->user(), (int) $request->transaction_id);
        if (!$transaction) {
            return UtillsHelper::json(0, ['message' => 'Transaction not found']);
        }

        $result = $this->orders->cancelByPartner($transaction, (string) $request->reason);
        if (!$result['ok']) {
            return UtillsHelper::json(0, ['message' => $result['message']]);
        }

        return UtillsHelper::json(1, [
            'message' => $result['message'],
            'data' => $this->present($transaction->fresh()),
        ]);
    }

    public function paymentReceipt(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'transaction_id' => 'required|integer|min:1',
            'file' => 'required|file|mimes:png,jpg,jpeg,pdf|max:'.UtillsHelper::maxFileDocumentSizeInKB(),
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $transaction = $this->orders->findForBuyingPartner($request->user(), (int) $request->transaction_id);
        if (!$transaction) {
            return UtillsHelper::json(0, ['message' => 'Transaction not found']);
        }

        $result = $this->orders->uploadPaymentReceipt($transaction, $request->file('file'));
        if (!$result['ok']) {
            return UtillsHelper::json(0, ['message' => $result['message']]);
        }

        return UtillsHelper::json(1, [
            'message' => $result['message'],
            'data' => $this->present($transaction->fresh()),
        ]);
    }

    public function confirmShareTransfer(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'transaction_id' => 'required|integer|min:1',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $transaction = $this->orders->findForBuyingPartner($request->user(), (int) $request->transaction_id);
        if (!$transaction) {
            return UtillsHelper::json(0, ['message' => 'Transaction not found']);
        }

        $result = $this->orders->confirmShareTransfer($transaction);
        if (!$result['ok']) {
            return UtillsHelper::json(0, ['message' => $result['message']]);
        }

        return UtillsHelper::json(1, [
            'message' => $result['message'],
            'data' => $this->present($transaction->fresh()),
        ]);
    }

    private function present(PreIpoModel $transaction): array
    {
        return PreIpoOrderStepHelper::map($transaction, PreIpoOrderStepHelper::AUDIENCE_PARTNER);
    }
}
