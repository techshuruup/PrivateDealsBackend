<?php

namespace App\Http\Controllers\Web\Front\User\Partner;

use App\Enums\PartnerTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\PartnerRequest;
use App\Models\PartnerModel;
use App\Repositories\PartnerRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ChannelPartnerController extends Controller
{
    private $partnerRepo;

    function __construct(PartnerRepository $partnerRepo)
    {
        $this->partnerRepo = $partnerRepo;
    }

    function list(): View|RedirectResponse
    {
        if (Auth::guard('partner')->user()->type == PartnerTypeEnum::retailer->value) {
            return redirect()->route('front.business.dashboard');
        }
        setPageTitle('Channel Partner');
        $data['list'] = PartnerModel::where('parent_id', Auth::guard('partner')->user()->id);
        return view('front.partner.channelpartner.list', $data);
    }

    function create(): View|RedirectResponse
    {
        if (Auth::guard('partner')->user()->type == PartnerTypeEnum::retailer->value) {
            return redirect()->route('front.business.dashboard');
        }
        setPageTitle('Create Channel Partner');
        return view('front.partner.channelpartner.create');
    }

    function store(PartnerRequest $partnerRequest): RedirectResponse
    {
        if ($partnerRequest->commission > Auth::guard('partner')->user()->commission) {
            return redirect()->back()->withInput()->with('error', 'Your channel partner’s commission must be less than or equal to your own commission. You have entered ' . $partnerRequest->commission . ' for your channel partner, while your commission is ' . Auth::guard('partner')->user()->commission);
        }


        $partner = new PartnerModel();
        $partner->name = $partnerRequest->name;
        $partner->mobile_number = $partnerRequest->mobile_number;
        $partner->email = $partnerRequest->email;
        $partner->password = Hash::make($partnerRequest->password);
        $partner->commission = $partnerRequest->commission;
        $partner->gender = $partnerRequest->gender;
        $partner->parent_type = Auth::guard('partner')->user()->type;
        $partner->parent_id = Auth::guard('partner')->user()->id;
        $partner->ask_password_change = 1;
        $partner->type = $partnerRequest->partner;
        $this->partnerRepo->storePartnerLogo($partner);
        $partner->save();
        $this->partnerRepo->createSelfInvestor($partner);

        return redirect()->route('front.business.channel_partner.list')->with('success', 'Channel Partner Created');
    }
}
