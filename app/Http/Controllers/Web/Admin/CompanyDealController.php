<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\CompanyDealStatusEnum;
use App\Enums\CompanyDealTypeEnum;
use App\Enums\PartnerTypeEnum;
use App\Helpers\AdminHelper;
use App\Helpers\UtillsHelper;
use App\Http\Controllers\Controller;
use App\Models\CompanyDealModel;
use App\Models\CompanyModel;
use App\Models\PartnerModel;
use App\Services\CompanyDealPricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\JsonResponse;

class CompanyDealController extends Controller
{
    public function index(): View
    {
        setPageTitle('Company Deals');
        addVendor('datatables');
        return view('admin.pages.company-deals.list');
    }

    public function list(): JsonResponse
    {
        $query = CompanyDealModel::with([
            'company:id,brand_name',
            'createdBySeller:id,company_name,mobile_number,mobile_country_code,email',
            'createdByPartner:id,name,mobile_number,mobile_country_code,email',
        ])
            ->where('is_deleted', '0')
            ->hot();

        if (request()->has('search') && request('search')['value']) {
            $search = request('search')['value'];
            $query->where(function ($q) use ($search) {
                $q->where('share_price', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('available_quantity', 'like', "%{$search}%")
                    ->orWhere('minimum_qty', 'like', "%{$search}%")
                    ->orWhereHas('company', function ($cq) use ($search) {
                        $cq->where('brand_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('createdBySeller', function ($sq) use ($search) {
                        $sq->where('company_name', 'like', "%{$search}%")
                            ->orWhere('mobile_number', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('createdByPartner', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")
                            ->orWhere('mobile_number', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $orderColumn = request('order')[0]['column'] ?? 0;
        $orderDir = request('order')[0]['dir'] ?? 'desc';
        $columns = ['company_id', 'created_by_seller_id', 'deal_type', 'available_quantity', 'share_price', 'minimum_qty', 'processing_fee_percentage', 'is_hot_deal', 'expired_at', 'status'];
        if (isset($columns[$orderColumn])) {
            $query->orderBy($columns[$orderColumn], $orderDir);
        } else {
            $query->orderByDesc('id');
        }

        $total = CompanyDealModel::where('is_deleted', '0')->hot()->count();
        $filtered = $query->count();

        $statusBadges = [
            CompanyDealStatusEnum::available->value => 'badge-success',
            CompanyDealStatusEnum::half_sold->value => 'badge-warning',
            CompanyDealStatusEnum::sold->value => 'badge-danger',
        ];

        $dealTypeBadges = [
            CompanyDealTypeEnum::buy->value => 'badge-light-primary',
            CompanyDealTypeEnum::sell->value => 'badge-light-info',
        ];

        $deals = $query->offset(request('start', 0))
            ->limit(request('length', 10))
            ->get()
            ->map(function ($deal) use ($statusBadges, $dealTypeBadges) {
                $badge = $statusBadges[$deal->status] ?? 'badge-light';
                $statusLabel = str_replace('_', ' ', ucfirst($deal->status));
                $dealType = $deal->deal_type ?: CompanyDealTypeEnum::sell->value;
                $dealTypeBadge = $dealTypeBadges[$dealType] ?? 'badge-light';
                $dealTypeLabel = ucfirst($dealType);
                $partner = $deal->createdByPartner;
                $seller = $deal->createdBySeller;
                if ($partner) {
                    $sellerLabel = trim((string) ($partner->name ?: ''));
                    if ($sellerLabel === '') {
                        $sellerLabel = trim(($partner->mobile_country_code ?? '') . ' ' . ($partner->mobile_number ?? ''));
                    }
                    if ($sellerLabel === '') {
                        $sellerLabel = $partner->email ?: ('Institution #' . $partner->id);
                    }
                    $sellerHtml = e($sellerLabel);
                    if (!empty($partner->mobile_number) && $partner->name) {
                        $sellerHtml .= '<div class="text-muted fs-8">' . e(trim(($partner->mobile_country_code ?? '') . ' ' . $partner->mobile_number)) . '</div>';
                    }
                } elseif ($seller) {
                    $sellerLabel = trim((string) ($seller->company_name ?: ''));
                    if ($sellerLabel === '') {
                        $sellerLabel = trim(($seller->mobile_country_code ?? '') . ' ' . ($seller->mobile_number ?? ''));
                    }
                    if ($sellerLabel === '') {
                        $sellerLabel = $seller->email ?: ('Seller #' . $seller->id);
                    }
                    $sellerHtml = e($sellerLabel);
                    if (!empty($seller->mobile_number) && $seller->company_name) {
                        $sellerHtml .= '<div class="text-muted fs-8">' . e(trim(($seller->mobile_country_code ?? '') . ' ' . $seller->mobile_number)) . '</div>';
                    }
                } else {
                    $sellerHtml = '<span class="text-muted">Admin</span>';
                }

                $expiredHtml = '—';
                if ($deal->expired_at) {
                    $expiredHtml = e($deal->expired_at->format('d M Y H:i'));
                    if ($deal->isExpired()) {
                        $expiredHtml .= ' <span class="badge badge-light-danger">Expired</span>';
                    }
                }

                return [
                    'company' => e($deal->company->brand_name ?? '—'),
                    'seller' => $sellerHtml,
                    'deal_type' => '<span class="badge ' . $dealTypeBadge . '">' . e($dealTypeLabel) . '</span>',
                    'available_quantity' => $deal->available_quantity,
                    'share_price' => UtillsHelper::rupee() . UtillsHelper::moneyFormatIndia($deal->share_price),
                    'minimum_qty' => $deal->minimum_qty,
                    'processing_fee_percentage' => number_format((float) $deal->processing_fee_percentage, 2) . '%',
                    'is_hot_deal' => $deal->is_hot_deal
                        ? '<span class="badge badge-light-danger">Hot</span>'
                        : '<span class="text-muted">—</span>',
                    'expired_at' => $expiredHtml,
                    'status' => '<span class="badge ' . $badge . '">' . e($statusLabel) . '</span>',
                    'action' => '
                        <a href="' . route('admin.company-deals.edit', ['uuid' => $deal->uuid]) . '" class="btn btn-primary hover-elevate-up btn-icon btn-sm me-1" title="Edit">
                            <i class="fas fa-pencil fs-6"></i>
                        </a>
                        <form action="' . route('admin.company-deals.delete', ['uuid' => $deal->uuid]) . '" method="POST" style="display:inline;" onsubmit="return confirm(\'Are you sure you want to delete this deal?\');">
                            ' . csrf_field() . '
                            ' . method_field('DELETE') . '
                            <button type="submit" class="btn btn-danger hover-elevate-up btn-icon btn-sm me-1" title="Delete">
                                <i class="fas fa-trash fs-6"></i>
                            </button>
                        </form>
                    ',
                ];
            });

        return response()->json([
            'draw' => intval(request('draw', 1)),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $deals,
        ]);
    }

    public function create(): View
    {
        setPageTitle('Create Company Deal');
        $data['companies'] = CompanyModel::where('is_deleted', 0)
            ->orderBy('brand_name')
            ->get(['id', 'brand_name', 'type']);
        $data['institutions'] = $this->institutionOptions();

        return view('admin.pages.company-deals.create')->with($data);
    }

    public function store(Request $request): RedirectResponse
    {
        $validation = Validator::make($request->all(), [
            'company_id' => 'required|exists:company,id',
            'created_by_partner_id' => 'required|integer',
            'deal_type' => 'required|in:' . implode(',', array_column(CompanyDealTypeEnum::cases(), 'value')),
            'available_quantity' => 'required|integer|min:0',
            'share_price' => 'required|numeric|min:0',
            'minimum_qty' => 'required|integer|min:1',
            'status' => 'required|in:' . implode(',', array_column(CompanyDealStatusEnum::cases(), 'value')),
            'expired_at' => 'nullable|date',
            'is_hot_deal' => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return redirect()->back()->withInput()
                ->with('error', $validation->errors()->first());
        }

        $company = CompanyModel::where('id', $request->company_id)->where('is_deleted', 0)->first();
        if (!$company) {
            return redirect()->back()->withInput()->with('error', 'Company not found');
        }

        $partnerId = $this->resolveInstitutionId($request->input('created_by_partner_id'));
        if ($partnerId === null) {
            return redirect()->back()->withInput()->with('error', 'Institution not found');
        }

        $deal = new CompanyDealModel();
        $deal->company_id = $request->company_id;
        $deal->created_by_seller_id = null;
        $deal->created_by_partner_id = $partnerId;
        $deal->deal_type = $request->deal_type;
        $deal->available_quantity = $request->available_quantity;
        $deal->minimum_qty = $request->minimum_qty;
        app(CompanyDealPricing::class)->stampFromBase($deal, $request->share_price);
        $deal->status = $request->status;
        $deal->expired_at = $request->filled('expired_at') ? $request->expired_at : null;
        $deal->is_hot_deal = $request->boolean('is_hot_deal');
        DB::transaction(function () use ($deal) {
            $deal->save();
            if (!$deal->is_hot_deal) {
                app(CompanyDealPricing::class)->recordNonHotHistory([(int) $deal->company_id]);
            }
        });

        AdminHelper::logPut('Created Company Deal', CompanyDealModel::class, $deal->id);

        return redirect()->route('admin.company-deals.index')
            ->with('success', 'Deal added successfully.');
    }

    public function edit(string $uuid): View|RedirectResponse
    {
        $item = CompanyDealModel::with('createdByPartner:id,name,mobile_number,mobile_country_code,email')
            ->where('uuid', $uuid)
            ->where('is_deleted', '0')
            ->first();

        if ($item) {
            setPageTitle('Edit Company Deal');
            $data['item'] = $item;
            $data['companies'] = CompanyModel::where('is_deleted', 0)
                ->orderBy('brand_name')
                ->get(['id', 'brand_name', 'type']);
            $data['institutions'] = $this->institutionOptions();

            return view('admin.pages.company-deals.edit')->with($data);
        }

        return redirect()->route('admin.company-deals.index')
            ->with('error', 'Deal not found');
    }

    public function update(Request $request, string $uuid): RedirectResponse
    {
        $item = CompanyDealModel::where('uuid', $uuid)->where('is_deleted', '0')->first();

        if (!$item) {
            return redirect()->route('admin.company-deals.index')
                ->with('error', 'Deal not found');
        }

        $validation = Validator::make($request->all(), [
            'company_id' => 'required|exists:company,id',
            'created_by_partner_id' => 'required|integer',
            'deal_type' => 'required|in:' . implode(',', array_column(CompanyDealTypeEnum::cases(), 'value')),
            'available_quantity' => 'required|integer|min:0',
            'share_price' => 'required|numeric|min:0',
            'minimum_qty' => 'required|integer|min:1',
            'status' => 'required|in:' . implode(',', array_column(CompanyDealStatusEnum::cases(), 'value')),
            'expired_at' => 'nullable|date',
            'is_hot_deal' => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return redirect()->back()->withInput()
                ->with('error', $validation->errors()->first());
        }

        $company = CompanyModel::where('id', $request->company_id)->where('is_deleted', 0)->first();
        if (!$company) {
            return redirect()->back()->withInput()->with('error', 'Company not found');
        }

        $partnerId = $this->resolveInstitutionId($request->input('created_by_partner_id'));
        if ($partnerId === null) {
            return redirect()->back()->withInput()->with('error', 'Institution not found');
        }

        $previousCompanyId = (int) $item->company_id;
        $wasNonHot = !$item->is_hot_deal;

        $item->company_id = $request->company_id;
        $item->created_by_seller_id = null;
        $item->created_by_partner_id = $partnerId;
        $item->deal_type = $request->deal_type;
        $item->available_quantity = $request->available_quantity;
        $item->minimum_qty = $request->minimum_qty;
        app(CompanyDealPricing::class)->stampFromBase($item, $request->share_price);
        $item->status = $request->status;
        $item->expired_at = $request->filled('expired_at') ? $request->expired_at : null;
        $item->is_hot_deal = $request->boolean('is_hot_deal');

        $historyCompanyIds = [];
        if (!$item->is_hot_deal || $wasNonHot) {
            $historyCompanyIds[] = (int) $item->company_id;
        }
        if ($wasNonHot && $previousCompanyId !== (int) $item->company_id) {
            $historyCompanyIds[] = $previousCompanyId;
        }

        DB::transaction(function () use ($item, $historyCompanyIds) {
            $item->save();
            if ($historyCompanyIds !== []) {
                app(CompanyDealPricing::class)->recordNonHotHistory($historyCompanyIds);
            }
        });

        AdminHelper::logPut('Updated Company Deal', CompanyDealModel::class, $item->id);

        return redirect()->route('admin.company-deals.index')
            ->with('success', 'Deal updated successfully.');
    }

    public function destroy(string $uuid): RedirectResponse
    {
        $item = CompanyDealModel::where('uuid', $uuid)->where('is_deleted', '0')->first();

        if ($item) {
            $item->is_deleted = '1';
            $item->update();

            AdminHelper::logPut('Deleted Company Deal', CompanyDealModel::class, $item->id);

            return redirect()->route('admin.company-deals.index')
                ->with('success', 'Deal deleted successfully.');
        }

        return redirect()->route('admin.company-deals.index')
            ->with('error', 'Deal not found');
    }

    private function institutionOptions()
    {
        return PartnerModel::where('is_deleted', '0')
            ->where('is_demo', '0')
            ->where('type', PartnerTypeEnum::institution->value)
            ->orderBy('name')
            ->get(['id', 'name', 'mobile_number', 'mobile_country_code', 'email']);
    }

    private function resolveInstitutionId(mixed $partnerId): ?int
    {
        if ($partnerId === null || $partnerId === '') {
            return null;
        }

        $partner = PartnerModel::where('id', (int) $partnerId)
            ->where('is_deleted', '0')
            ->where('is_demo', '0')
            ->where('type', PartnerTypeEnum::institution->value)
            ->first(['id']);

        return $partner?->id;
    }
}
