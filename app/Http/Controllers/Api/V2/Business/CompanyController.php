<?php

namespace App\Http\Controllers\Api\V2\Business;

use App\Enums\PartnerTypeEnum;
use App\Helpers\UtillsHelper;
use App\Http\Controllers\Controller;
use App\Models\PartnerModel;
use App\Repositories\V2\SellerCompanyRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

class CompanyController extends Controller
{
    public function __construct(private SellerCompanyRepository $companyRepo)
    {
    }

    public function sectors(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        return $this->companyRepo->sectors();
    }

    public function list(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        return $this->companyRepo->listForInstitution((int) request()->user()->id);
    }

    public function listLite(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        return $this->companyRepo->listLite();
    }

    public function detail(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        return $this->companyRepo->detailForInstitution((int) request()->user()->id);
    }

    public function mySubmissions(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        return $this->companyRepo->mySubmissionsForInstitution((int) request()->user()->id);
    }

    public function savePromoters(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        return $this->companyRepo->savePromoters((int) request()->user()->id);
    }

    public function saveShareholders(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        return $this->companyRepo->saveShareholders((int) request()->user()->id);
    }

    public function checkDuplicate(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        return $this->companyRepo->checkDuplicate();
    }

    public function create(): JsonResponse
    {
        $partner = request()->user();
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        return $this->companyRepo->createForInstitution((int) $partner->id);
    }

    public function listDeals(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        return $this->companyRepo->listInstitutionDeals((int) request()->user()->id);
    }

    public function createDeal(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        return $this->companyRepo->createInstitutionDeal((int) request()->user()->id);
    }

    public function createDealsBulk(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        return $this->companyRepo->createInstitutionDealsBulk((int) request()->user()->id);
    }

    public function updateDeal(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        return $this->companyRepo->updateInstitutionDeal((int) request()->user()->id);
    }

    public function deleteDeal(): JsonResponse
    {
        if ($denied = $this->institutionOnly()) {
            return $denied;
        }

        return $this->companyRepo->deleteInstitutionDeal((int) request()->user()->id);
    }

    private function institutionOnly(): ?JsonResponse
    {
        $partner = request()->user();
        if (!$partner instanceof PartnerModel || $partner->type !== PartnerTypeEnum::institution->value) {
            return UtillsHelper::json(0, [
                'message' => 'Only Institution partners can submit a company',
            ]);
        }

        return null;
    }
}
