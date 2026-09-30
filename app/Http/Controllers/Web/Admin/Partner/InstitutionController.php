<?php

namespace App\Http\Controllers\Web\Admin\Partner;

use App\Enums\PartnerTypeEnum;
use App\Helpers\AdminHelper;
use App\Http\Controllers\Controller;
use App\Models\PartnerModel;
use App\Repositories\PartnerRepository;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;

class InstitutionController extends Controller
{
    private $partnerRepo;

    function __construct(PartnerRepository $partnerRepo)
    {
        $this->partnerRepo = $partnerRepo;
    }

    function create(): View
    {
        setPageTitle('Create Institution');
        $data['wealthmanagerlist'] = PartnerModel::where('is_deleted', '0')->where('type', PartnerTypeEnum::wealthmanager)->orderby('id', 'desc')->get();
        return view('admin.pages.partner.institution.create')->with($data);
    }

    function list(): View
    {
        setPageTitle('Institutions');
        $data['list'] = PartnerModel::where('is_deleted', '0')->where('is_demo', '0')->where('type', PartnerTypeEnum::institution)->get();
        addVendor('datatables');
        return view('admin.pages.partner.institution.list')->with($data);
    }

    function view(string $uuid): View|RedirectResponse
    {
        $partner = PartnerModel::where('is_deleted', '0')->where('uuid', $uuid)->first();
        if ($partner) {
            setPageTitle(ucfirst($partner->name) . "'s Profile");
            $data['partner'] = $partner;
            $allPartner = PartnerModel::where('parent_id', $partner->id)->where('is_deleted', '0')->where('type', PartnerTypeEnum::relationmanager->value)->get();
            $data['allPartner'] = $allPartner;
            return view('admin.pages.partner.view', $data);
        }

        return redirect()->back()->with('error', 'Partner not found');
    }

    function edit(string $uuid): View|RedirectResponse
    {
        $item = PartnerModel::where('is_deleted', '0')->where('uuid', $uuid)->first();
        if ($item) {
            setPageTitle('Edit Institution');
            $data['wealthmanagerlist'] = PartnerModel::where('is_deleted', '0')->where('type', PartnerTypeEnum::wealthmanager)->orderby('id', 'desc')->get();
            $data['item'] = $item;
            return view('admin.pages.partner.institution.edit')->with($data);
        }

        return redirect()->route('admin.partner.institution.list')->with('error', 'Item not found');
    }

    function store(): RedirectResponse
    {
        return $this->partnerRepo->newDistributorSave();
    }

    function update(): RedirectResponse
    {
        return $this->partnerRepo->newDistributorSave();
    }

    function delete(string $id): RedirectResponse
    {
        $item = PartnerModel::where('is_deleted', '0')->find($id);

        if ($item) {
            $item->is_deleted = '1';
            $item->update();
            $item->tokens()->delete();
            AdminHelper::logPut('Deleted institution', PartnerModel::class, $item->id);
            return redirect()->route('admin.partner.institution.list')
                ->with('success', 'Institution deleted.');
        }

        return redirect()->route('admin.partner.institution.list')->with('error', 'Item not found');
    }
}
