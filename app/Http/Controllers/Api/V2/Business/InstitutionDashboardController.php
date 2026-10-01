<?php

namespace App\Http\Controllers\Api\V2\Business;

use App\Http\Controllers\Controller;
use App\Repositories\V2\InstitutionDashboardRepository;
use Illuminate\Http\JsonResponse;

class InstitutionDashboardController extends Controller
{
    public function __construct(private InstitutionDashboardRepository $dashboardRepo)
    {
    }

    public function index(): JsonResponse
    {
        return $this->dashboardRepo->dashboard();
    }
}
