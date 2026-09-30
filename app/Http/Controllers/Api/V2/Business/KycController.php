<?php

namespace App\Http\Controllers\Api\V2\Business;

use App\Enums\DocumentTypeEnum;
use App\Enums\PartnerTypeEnum;
use App\Enums\Utills\StatusEnum;
use App\Helpers\FileUpDownHelper;
use App\Helpers\UtillsHelper;
use App\Http\Controllers\Controller;
use App\Jobs\SendPendingKycAdminNotification;
use App\Models\DematManualModel;
use App\Models\DocumentsModel;
use App\Models\InvestorModel;
use App\Models\PartnerModel;
use App\Services\DematKycService;
use App\Services\DematPdfParsingService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\JsonResponse;

class KycController extends Controller
{
    public function readCml(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'investor_id' => 'required|integer',
                'cml' => 'required|file|mimes:pdf|max:10000',
            ],
            [
                'cml.required' => 'The CML file is required.',
                'cml.file' => 'The CML must be a valid file.',
                'cml.mimes' => 'The CML must be a PDF file.',
                'cml.max' => 'The CML file may not be greater than 10 MB.',
            ]
        );

        if ($validator->fails()) {
            return UtillsHelper::json(0, ['message' => $validator->errors()->first()]);
        }

        $investor = $this->ownedInvestor($request, (int) $request->input('investor_id'));
        if (!$investor) {
            return UtillsHelper::json(0, ['message' => 'Investor not found']);
        }

        $file = $request->file('cml');
        $pdfParsingService = new DematPdfParsingService();
        $result = $pdfParsingService->processPdf($file, $investor->id);

        if ($result['success']) {
            return UtillsHelper::json(1, [
                'message' => $result['message'],
                'data' => $result['data'],
            ]);
        }

        DB::beginTransaction();

        try {
            $filePath = FileUpDownHelper::uploadInvestorDoc($file);

            if (!$filePath) {
                DB::rollBack();
                return UtillsHelper::json(0, ['message' => 'File upload failed']);
            }

            $document = DocumentsModel::create([
                'api_id'      => null,
                'path'        => $filePath,
                'signed_path' => $filePath,
                'status'      => 1,
                'type'        => DocumentTypeEnum::clientmaster->value,
                'meta'        => [
                    'name'     => 'DEMANT-CML',
                    'investor' => [$investor->id],
                ],
            ]);

            DematManualModel::create([
                'investor_id' => $investor->id,
                'document_id' => $document->id,
                'status'      => StatusEnum::pending->value,
                'reason'      => null,
            ]);

            DB::commit();
            SendPendingKycAdminNotification::dispatch($investor->id);

            return UtillsHelper::json(0, [
                'message' => 'PDF could not be auto-read. Sent for manual verification.',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return UtillsHelper::json(0, [
                'message' => 'Manual verification failed',
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function saveCml(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'investor_id'       => 'required|integer',
            'dp_id'             => 'required',
            'client_id'         => 'required',
            'pan_no'            => 'required',
            'name'              => 'required',
            'account_number'    => 'nullable',
            'ifsc_code'         => 'nullable',
            'bank_name'         => 'nullable',
            'dob'               => 'nullable|date|date_format:d-m-Y',
            'cml_file'          => ['nullable', 'mimes:pdf', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()],
        ]);

        if ($validator->fails()) {
            return UtillsHelper::json(0, ['message' => $validator->errors()->first()]);
        }

        $investor = $this->ownedInvestor($request, (int) $request->input('investor_id'));
        if (!$investor) {
            return UtillsHelper::json(0, ['message' => 'Investor not found']);
        }

        $data = [
            'dp_id' => $request->input('dp_id'),
            'client_id' => $request->input('client_id'),
            'pan_no' => $request->input('pan_no'),
            'name' => $request->input('name'),
            'account_number' => $request->input('account_number'),
            'ifsc_code' => $request->input('ifsc_code'),
            'bank_name' => $request->input('bank_name'),
            'dob' => $request->input('dob'),
        ];

        $kycService = new DematKycService();
        $result = $kycService->saveDematKyc($investor->id, $data, $request->file('cml_file'));

        if ($result['success']) {
            return UtillsHelper::json(1, ['message' => $result['message']]);
        }

        return UtillsHelper::json(0, [
            'message' => $result['message'],
            'error' => $result['error'] ?? null,
        ]);
    }

    /**
     * Same scope as GET /api/v2/business/investor: non-self clients of this partner
     * and their relation managers, plus this partner's self investor.
     */
    private function ownedInvestor(Request $request, int $investorId): ?InvestorModel
    {
        $partner = $request->user();
        if (!$partner instanceof PartnerModel) {
            return null;
        }

        $partnerIds = PartnerModel::select('id')
            ->where('parent_id', $partner->id)
            ->where('type', PartnerTypeEnum::relationmanager->value)
            ->pluck('id');
        $partnerIds->push($partner->id);

        return InvestorModel::query()
            ->where('id', $investorId)
            ->where('is_deleted', 0)
            ->where(function ($query) use ($partner, $partnerIds) {
                $query->where(function ($clients) use ($partnerIds) {
                    $clients->whereIn('partner_id', $partnerIds)->where('is_self', 0);
                })->orWhere(function ($self) use ($partner) {
                    $self->where('partner_id', $partner->id)->where('is_self', 1);
                });
            })
            ->first();
    }
}
