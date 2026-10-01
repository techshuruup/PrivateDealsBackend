<?php

namespace App\Repositories\V2;

use App\Enums\CompanyApprovalStatusEnum;
use App\Enums\CompanyDealStatusEnum;
use App\Enums\CompanyDealTypeEnum;
use App\Enums\CompanyTypeEnum;
use App\Enums\MinimumInvestmentTypeEnum;
use App\Enums\PreIpoCategoryEnum;
use App\Helpers\AdminHelper;
use App\Helpers\CommonHelper;
use App\Helpers\FileUpDownHelper;
use App\Helpers\UtillsHelper;
use App\Models\CompanyDealModel;
use App\Models\CompanyFundamentalsModel;
use App\Models\CompanyModel;
use App\Models\CompanyPromotersModel;
use App\Models\CompanyShareHolderModel;
use App\Models\CompanyShareHolderPercentageModel;
use App\Models\MasterSectorsModel;
use App\Models\SellerCompanySharePriceModel;
use App\Services\CompanyDealPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\JsonResponse;

class SellerCompanyRepository
{
    public function sectors(): JsonResponse
    {
        $sectors = MasterSectorsModel::where('is_deleted', '0')
            ->select('id', 'name', 'icon_image', 'url_slug')
            ->orderBy('name', 'asc')
            ->get();

        return UtillsHelper::json(1, [
            'message' => 'Sector list',
            'data' => $sectors,
        ]);
    }

    public function checkDuplicate(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'cin' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'type' => 'nullable|in:' . implode(',', array_column(CompanyTypeEnum::cases(), 'value')),
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        if (!$request->filled('cin') && !$request->filled('company_name')) {
            return UtillsHelper::json(0, [
                'message' => 'Provide cin and/or company_name (legal name) to check',
            ]);
        }

        $matches = [];
        $type = $request->input('type', CompanyTypeEnum::unlisted->value);

        if ($request->filled('cin')) {
            $normalizedCin = $this->normalizeIdentity($request->cin);
            if ($normalizedCin !== '') {
                $byCin = CompanyModel::where('is_deleted', '0')
                    ->whereRaw("UPPER(REPLACE(cin, ' ', '')) = ?", [$normalizedCin])
                    ->first(['id', 'uuid', 'slug', 'brand_name', 'company_name', 'cin', 'type', 'approval_status']);
                if ($byCin) {
                    $matches[] = [
                        'match_on' => 'cin',
                        'company' => $byCin,
                    ];
                }
            }
        }

        if ($request->filled('company_name')) {
            $normalizedName = $this->normalizeIdentity($request->company_name);
            if ($normalizedName !== '') {
                $byName = CompanyModel::where('is_deleted', '0')
                    ->where('type', $type)
                    ->whereRaw("UPPER(REPLACE(company_name, ' ', '')) = ?", [$normalizedName])
                    ->first(['id', 'uuid', 'slug', 'brand_name', 'company_name', 'cin', 'type', 'approval_status']);
                if ($byName) {
                    $already = collect($matches)->contains(fn ($m) => ($m['company']->id ?? null) === $byName->id);
                    if (!$already) {
                        $matches[] = [
                            'match_on' => 'company_name',
                            'company' => $byName,
                        ];
                    } else {
                        foreach ($matches as &$match) {
                            if (($match['company']->id ?? null) === $byName->id) {
                                $match['match_on'] = 'cin,company_name';
                            }
                        }
                        unset($match);
                    }
                }
            }
        }

        $isDuplicate = count($matches) > 0;

        return UtillsHelper::json(1, [
            'message' => $isDuplicate ? 'Duplicate company found' : 'No duplicate company found',
            'data' => [
                'is_duplicate' => $isDuplicate,
                'matches' => $matches,
            ],
        ]);
    }

    public function create(): JsonResponse
    {
        return $this->persistPendingCompany(request()->user()->id, null);
    }

    public function createForInstitution(int $partnerId): JsonResponse
    {
        return $this->persistPendingCompany(null, $partnerId);
    }

    private function persistPendingCompany(?int $sellerId, ?int $partnerId): JsonResponse
    {
        $request = request();

        $validation = Validator::make($request->all(), [
            'type' => ['required', Rule::enum(CompanyTypeEnum::class)],
            'cin' => 'required|string|max:255',
            'brand_name' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'sector' => 'required|integer|exists:master_sectors,id',
            'about' => 'required|string',
            'min_investment_amount' => 'required|numeric|min:0',
            'lot_size' => 'required|string|max:255',
            'market_cap' => 'required|numeric|min:0',
            'pe_ratio' => 'required|numeric',
            'pb_ratio' => 'required|numeric',
            'debt_to_equity' => 'required|numeric|min:0',
            'roe' => 'required|numeric',
            'book_value' => 'required|numeric|min:0',
            'face_value' => 'required|numeric|min:0',
            'logo' => 'nullable|image|mimes:' . CommonHelper::appSettings('file_image_extensions_allowed') . '|max:' . UtillsHelper::maxFileImageSizeInKB(),
            'alternative_names' => 'nullable|string',
            'list_order' => 'nullable|string|max:255',
            'depository' => 'nullable|string|max:255',
            'pan_number' => 'nullable|string|size:10',
            'isin_number' => 'nullable|string|size:12',
            'rta' => 'nullable|string|max:255',
            'total_shares' => 'nullable|numeric|min:0',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $sector = MasterSectorsModel::where('id', $request->sector)->where('is_deleted', '0')->first();
        if (!$sector) {
            return UtillsHelper::json(0, ['message' => 'Sector not found']);
        }

        $duplicateCheck = $this->findDuplicates(
            $request->cin,
            $request->company_name,
            $request->type
        );
        if ($duplicateCheck['is_duplicate']) {
            $reasons = collect($duplicateCheck['matches'])->pluck('match_on')->unique()->implode(', ');
            return UtillsHelper::json(0, [
                'message' => 'Company already exists (' . $reasons . ')',
                'data' => $duplicateCheck,
            ]);
        }

        $processingFee = CommonHelper::processingFeePercentage();

        try {
            DB::beginTransaction();

            $company = new CompanyModel();
            $company->cin = trim($request->cin);
            $company->brand_name = $request->brand_name;
            $company->company_name = $request->company_name;
            $company->sector_id = $request->sector;
            $company->about = $request->about;
            $company->type = $request->type;
            $company->status = 0;
            $company->is_deleted = 0;
            $company->approval_status = CompanyApprovalStatusEnum::approved->value;
            if ($sellerId !== null) {
                $company->submitted_by_seller_id = $sellerId;
            }
            if ($partnerId !== null) {
                $company->submitted_by_partner_id = $partnerId;
            }
            $company->final_min_investment_amount = $request->min_investment_amount;
            $company->min_investment_amount = $request->min_investment_amount;
            $company->min_investment_type = MinimumInvestmentTypeEnum::quantity->value;
            $company->commission = $processingFee;
            $company->processing_fee_percentage = $processingFee;
            $company->is_free_processing_fee = 1;
            $company->alternative_names = $this->cleanCommaList($request->input('alternative_names'));
            $company->list_order = $request->input('list_order');
            $company->category = null;
            if ($request->hasFile('logo')) {
                $company->logo = FileUpDownHelper::company_logo_upload($request->file('logo'));
            }
            $company->slug = AdminHelper::companySlug($request->brand_name);
            $company->save();

            $fundamentals = new CompanyFundamentalsModel();
            $fundamentals->company_id = $company->id;
            $fundamentals->lot_size = $request->lot_size;
            $fundamentals->fifty_two_week_high = 0;
            $fundamentals->fifty_two_week_low = 0;
            $fundamentals->depository = $request->input('depository');
            $fundamentals->pan_number = $request->input('pan_number');
            $fundamentals->isin_number = $request->input('isin_number');
            $fundamentals->cin_number = trim($request->cin);
            $fundamentals->rta = $request->input('rta');
            $fundamentals->market_cap = $request->market_cap;
            $fundamentals->pe_ratio = $request->pe_ratio;
            $fundamentals->pb_ratio = $request->pb_ratio;
            $fundamentals->debt_to_equity = $request->debt_to_equity;
            $fundamentals->roe = $request->roe;
            $fundamentals->book_value = $request->book_value;
            $fundamentals->face_value = $request->face_value;
            $fundamentals->total_shares = $request->input('total_shares');
            $fundamentals->save();

            DB::commit();

            $company->load(['sector:id,name', 'fundamentals']);

            return UtillsHelper::json(1, [
                'message' => 'Company created successfully. It is live.',
                'data' => $company,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return UtillsHelper::json(0, [
                'message' => 'Unable to create company. Please try again.',
            ]);
        }
    }

    public function listLite(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'type' => 'nullable|in:All,' . implode(',', array_column(CompanyTypeEnum::cases(), 'value')),
            'search' => 'nullable|string',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $type = $request->input('type', 'All');

        $query = CompanyModel::approved()
            ->where('is_deleted', '0')
            ->where('status', '0')
            ->select('id', 'uuid', 'slug', 'brand_name', 'company_name', 'logo');

        if ($type !== 'All') {
            $query->where('type', $type);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('brand_name', 'like', '%' . $search . '%')
                    ->orWhere('company_name', 'like', '%' . $search . '%');
            });
        }

        $companies = $query
            ->orderBy('brand_name', 'asc')
            ->get()
            ->makeHidden(['share_price', 'distributer_price', 'base_price']);

        return UtillsHelper::json(1, [
            'message' => 'Company list lite',
            'data' => $companies,
        ]);
    }

    public function list(): JsonResponse
    {
        return $this->catalogList(null);
    }

    public function listForInstitution(int $partnerId): JsonResponse
    {
        return $this->catalogList($partnerId);
    }

    private function catalogList(?int $partnerId): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'category' => 'nullable|in:All,' . implode(',', array_column(PreIpoCategoryEnum::cases(), 'value')),
            'sector' => 'nullable|string',
            'search' => 'nullable|string',
            'type' => 'nullable|in:' . implode(',', array_column(CompanyTypeEnum::cases(), 'value')),
            'skip' => 'nullable|integer|min:0',
            'take' => 'nullable|integer|min:1',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $type = $request->input('type', CompanyTypeEnum::unlisted->value);

        $query = CompanyModel::approved()
            ->where('is_deleted', '0')
            ->where('status', '0')
            ->where('type', $type)
            ->select(
                'id',
                'uuid',
                'slug',
                'brand_name',
                'company_name',
                'logo',
                'category',
                'type',
                'cin',
                'is_drhp',
                'bg_color_code',
                'share_price',
                'distributer_price',
                'base_price',
                'price_updated_today',
                'is_price_updated_today',
                'last_year_share_price',
                'is_trending',
                'is_grab_opportunity_enabled',
                'min_investment_type',
                'min_investment_amount',
                'sector_id',
                'submitted_by_seller_id',
                'submitted_by_partner_id'
            )
            ->with([
                'sector' => fn ($q) => $q->select('id', 'name', 'url_slug'),
                'fundamentals' => fn ($q) => $q->select('company_id', 'lot_size', 'fifty_two_week_high', 'fifty_two_week_low', 'depository'),
            ]);

        if ($request->filled('category') && $request->category !== 'All') {
            $categoryEnum = PreIpoCategoryEnum::from($request->category);

            if ($categoryEnum === PreIpoCategoryEnum::drhp) {
                $query->where('is_drhp', 1)
                    ->where(function ($q) {
                        $q->whereNull('category')
                            ->orWhereNotIn('category', [
                                PreIpoCategoryEnum::coming_soon->value,
                                PreIpoCategoryEnum::listed->value,
                            ]);
                    });
            } elseif ($categoryEnum === PreIpoCategoryEnum::trending) {
                $query->where('is_trending', 1);
            } elseif ($categoryEnum === PreIpoCategoryEnum::listed) {
                $query->where('category', PreIpoCategoryEnum::listed->value);
            } else {
                $query->where('category', $categoryEnum->value);
            }
        } else {
            $query->where(function ($q) {
                $q->whereNull('category')
                    ->orWhere('category', '!=', PreIpoCategoryEnum::listed->value);
            });
        }

        if ($request->filled('sector')) {
            $query->whereHas('sector', function ($sectorQuery) use ($request) {
                $sectorQuery->where('url_slug', $request->sector);
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('brand_name', 'like', '%' . $search . '%')
                    ->orWhere('company_name', 'like', '%' . $search . '%')
                    ->orWhere('keywords', 'like', '%' . $search . '%');
            });
        }

        $skip = max(0, (int) $request->input('skip', 0));
        $take = (int) $request->input('take', CommonHelper::appSettings('app_pagination_limit'));
        if ($take < 1) {
            $take = 15;
        }

        $total = (clone $query)->count();
        $sellerId = $partnerId === null ? (int) $request->user()->id : 0;
        $companies = $query
            ->orderByRaw('list_order IS NULL')
            ->orderBy('list_order', 'asc')
            ->orderBy('brand_name', 'asc')
            ->skip($skip)
            ->take($take)
            ->get()
            ->each(function (CompanyModel $company) use ($sellerId, $partnerId) {
                $editable = $partnerId !== null
                    ? (int) $company->submitted_by_partner_id === $partnerId
                    : (int) $company->submitted_by_seller_id === $sellerId;
                $company->setAttribute('is_editable', $editable);
                $company->makeHidden(['submitted_by_seller_id', 'submitted_by_partner_id']);
            });

        return UtillsHelper::json(1, [
            'message' => 'Company list',
            'data' => $companies,
            'total' => $total,
            'skip' => $skip,
            'take' => $take,
        ]);
    }

    public function detail(): JsonResponse
    {
        return $this->companyDetail(null);
    }

    public function detailForInstitution(int $partnerId): JsonResponse
    {
        return $this->companyDetail($partnerId);
    }

    private function companyDetail(?int $partnerId): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'slug' => 'required_without_all:id,uuid|nullable|string',
            'id' => 'required_without_all:slug,uuid|nullable|integer',
            'uuid' => 'required_without_all:slug,id|nullable|uuid',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $query = CompanyModel::where('is_deleted', '0')
            ->with([
                'sector:id,name,url_slug',
                'fundamentals',
                'promoters',
                'events' => fn ($q) => $q->orderBy('date', 'desc'),
                'news' => fn ($q) => $q->orderBy('created_at', 'desc')->limit(10),
                'deals',
                'sharePrices' => fn ($q) => $q->orderBy('date', 'desc'),
            ]);

        if ($request->filled('slug')) {
            $query->where('slug', $request->slug);
        } elseif ($request->filled('uuid')) {
            $query->where('uuid', $request->uuid);
        } else {
            $query->where('id', $request->id);
        }

        $company = $query->first();
        if (!$company) {
            return UtillsHelper::json(0, ['message' => 'Company not found']);
        }

        $isOwner = $partnerId !== null
            ? (int) $company->submitted_by_partner_id === $partnerId
                && $company->approval_status !== CompanyApprovalStatusEnum::rejected->value
            : (int) $company->submitted_by_seller_id === (int) $request->user()->id
                && $company->approval_status !== CompanyApprovalStatusEnum::rejected->value;
        $isApprovedCatalog = $company->approval_status === CompanyApprovalStatusEnum::approved->value;

        if (!$isApprovedCatalog && !$isOwner) {
            return UtillsHelper::json(0, ['message' => 'Company not found']);
        }

        $company->share_holders = $this->formatShareHolders($company->id);

        $company->seller_share_prices = SellerCompanySharePriceModel::query()
            ->with(['seller:id,uuid,company_name,logo'])
            ->where('company_id', $company->id)
            ->whereDate('date', today())
            ->orderBy('sell_price', 'asc')
            ->orderByDesc('id')
            ->get()
            ->map(function (SellerCompanySharePriceModel $row) {
                return [
                    'date' => $row->date,
                    'sell_price' => (float) $row->sell_price,
                    'buy_price' => $row->buy_price !== null ? (float) $row->buy_price : null,
                    'min_qty' => (int) $row->min_qty,
                    'total_qty' => $row->total_qty !== null ? (int) $row->total_qty : null,
                    'seller' => $row->seller ? [
                        'id' => (int) $row->seller->id,
                        'uuid' => $row->seller->uuid,
                        'company_name' => $row->seller->company_name,
                        'logo' => FileUpDownHelper::get_seller_logo_url($row->seller),
                    ] : null,
                ];
            })
            ->values();

        return UtillsHelper::json(1, [
            'message' => 'Company detail',
            'data' => $company,
        ]);
    }

    public function mySubmissions(): JsonResponse
    {
        return $this->submissionList(null);
    }

    public function mySubmissionsForInstitution(int $partnerId): JsonResponse
    {
        return $this->submissionList($partnerId);
    }

    private function submissionList(?int $partnerId): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'type' => 'nullable|in:' . implode(',', array_column(CompanyTypeEnum::cases(), 'value')),
            'approval_status' => 'nullable|in:' . implode(',', array_column(CompanyApprovalStatusEnum::cases(), 'value')),
            'skip' => 'nullable|integer|min:0',
            'take' => 'nullable|integer|min:1',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $query = CompanyModel::where('is_deleted', '0')
            ->with(['sector:id,name,url_slug', 'fundamentals']);

        if ($partnerId !== null) {
            $query->where('submitted_by_partner_id', $partnerId);
        } else {
            $query->where('submitted_by_seller_id', $request->user()->id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }

        $skip = max(0, (int) $request->input('skip', 0));
        $take = (int) $request->input('take', CommonHelper::appSettings('app_pagination_limit'));
        if ($take < 1) {
            $take = 15;
        }

        $total = (clone $query)->count();
        $companies = $query
            ->orderByDesc('id')
            ->skip($skip)
            ->take($take)
            ->get();

        return UtillsHelper::json(1, [
            'message' => 'My company submissions',
            'data' => $companies,
            'total' => $total,
            'skip' => $skip,
            'take' => $take,
        ]);
    }

    /**
     * @return array{is_duplicate: bool, matches: array<int, array{match_on: string, company: CompanyModel}>}
     */
    private function findDuplicates(?string $cin, ?string $companyName, ?string $type): array
    {
        $matches = [];
        $type = $type ?: CompanyTypeEnum::unlisted->value;

        if ($cin !== null && trim($cin) !== '') {
            $normalizedCin = $this->normalizeIdentity($cin);
            if ($normalizedCin !== '') {
                $byCin = CompanyModel::where('is_deleted', '0')
                    ->whereRaw("UPPER(REPLACE(cin, ' ', '')) = ?", [$normalizedCin])
                    ->first(['id', 'uuid', 'slug', 'brand_name', 'company_name', 'cin', 'type', 'approval_status']);
                if ($byCin) {
                    $matches[] = [
                        'match_on' => 'cin',
                        'company' => $byCin,
                    ];
                }
            }
        }

        if ($companyName !== null && trim($companyName) !== '') {
            $normalizedName = $this->normalizeIdentity($companyName);
            if ($normalizedName !== '') {
                $byName = CompanyModel::where('is_deleted', '0')
                    ->where('type', $type)
                    ->whereRaw("UPPER(REPLACE(company_name, ' ', '')) = ?", [$normalizedName])
                    ->first(['id', 'uuid', 'slug', 'brand_name', 'company_name', 'cin', 'type', 'approval_status']);
                if ($byName) {
                    $existingIndex = null;
                    foreach ($matches as $i => $match) {
                        if (($match['company']->id ?? null) === $byName->id) {
                            $existingIndex = $i;
                            break;
                        }
                    }
                    if ($existingIndex !== null) {
                        $matches[$existingIndex]['match_on'] = 'cin,company_name';
                    } else {
                        $matches[] = [
                            'match_on' => 'company_name',
                            'company' => $byName,
                        ];
                    }
                }
            }
        }

        return [
            'is_duplicate' => count($matches) > 0,
            'matches' => $matches,
        ];
    }

    public function listDeals(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'company_id' => 'nullable|integer',
            'type' => 'nullable|in:All,' . implode(',', array_column(CompanyTypeEnum::cases(), 'value')),
            'skip' => 'nullable|integer|min:0',
            'take' => 'nullable|integer|min:1',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $sellerId = (int) $request->user()->id;
        $type = $request->input('type', 'All');
        $query = CompanyDealModel::notDeleted()
            ->hot()
            ->where('created_by_seller_id', $sellerId)
            ->whereHas('company', function ($q) use ($type) {
                $q->where('is_deleted', '0')
                    ->approved();

                if ($type === 'All') {
                    $q->whereIn('type', [
                        CompanyTypeEnum::unlisted->value,
                        CompanyTypeEnum::secondary->value,
                    ]);
                } else {
                    $q->where('type', $type);
                }
            })
            ->with([
                'company' => fn ($q) => $q->select('id', 'brand_name', 'slug', 'logo', 'type'),
            ])
            ->orderByDesc('id');

        if ($request->filled('company_id')) {
            $company = $this->approvedDealCompany((int) $request->company_id);
            if (!$company) {
                return UtillsHelper::json(0, ['message' => 'Company not found']);
            }
            $query->where('company_id', $company->id);
        }

        $skip = max(0, (int) $request->input('skip', 0));
        $take = (int) $request->input('take', CommonHelper::appSettings('app_pagination_limit'));
        if ($take < 1) {
            $take = 15;
        }

        $total = (clone $query)->count();
        $deals = $query
            ->skip($skip)
            ->take($take)
            ->get()
            ->map(fn (CompanyDealModel $deal) => $this->formatSellerDeal($deal, $sellerId))
            ->values();

        return UtillsHelper::json(1, [
            'message' => 'Deal list',
            'data' => $deals,
            'total' => $total,
            'skip' => $skip,
            'take' => $take,
        ]);
    }

    public function createDeal(): JsonResponse
    {
        $request = request();
        $statusValues = implode(',', array_column(CompanyDealStatusEnum::cases(), 'value'));
        $dealTypeValues = implode(',', array_column(CompanyDealTypeEnum::cases(), 'value'));
        $validation = Validator::make($request->all(), [
            'company_id' => 'required|integer',
            'deal_type' => 'required|in:' . $dealTypeValues,
            'available_quantity' => 'nullable|integer|min:0',
            'share_price' => 'required|numeric|min:0',
            'minimum_qty' => 'required|integer|min:1',
            'status' => 'nullable|in:' . $statusValues,
            'is_hot_deal' => 'nullable|boolean',
            'expired_at' => 'nullable|date_format:Y-m-d H:i:s',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $company = $this->approvedDealCompany((int) $request->company_id);
        if (!$company) {
            return UtillsHelper::json(0, ['message' => 'Company not found']);
        }

        $sellerId = (int) $request->user()->id;
        $deal = new CompanyDealModel();
        $deal->company_id = $company->id;
        $deal->created_by_seller_id = $sellerId;
        $deal->deal_type = $request->deal_type;
        $deal->available_quantity = $request->filled('available_quantity')
            ? (int) $request->available_quantity
            : 0;
        $deal->minimum_qty = $request->minimum_qty;
        app(CompanyDealPricing::class)->stampFromBase($deal, $request->share_price);
        $deal->status = $request->filled('status')
            ? $request->status
            : CompanyDealStatusEnum::available->value;
        $deal->is_hot_deal = $request->boolean('is_hot_deal');
        $deal->expired_at = $request->filled('expired_at') ? $request->input('expired_at') : null;
        DB::transaction(function () use ($deal) {
            $deal->save();
            $this->recordNonHotDealHistory($deal);
        });

        return UtillsHelper::json(1, [
            'message' => 'Deal created',
            'data' => $this->formatSellerDeal($deal->fresh(['company:id,brand_name,slug,logo,type']), $sellerId),
        ]);
    }

    public function updateDeal(): JsonResponse
    {
        $request = request();
        $statusValues = implode(',', array_column(CompanyDealStatusEnum::cases(), 'value'));
        $dealTypeValues = implode(',', array_column(CompanyDealTypeEnum::cases(), 'value'));
        $validation = Validator::make($request->all(), [
            'uuid' => 'required|string',
            'deal_type' => 'required|in:' . $dealTypeValues,
            'available_quantity' => 'nullable|integer|min:0',
            'share_price' => 'required|numeric|min:0',
            'minimum_qty' => 'required|integer|min:1',
            'status' => 'required|in:' . $statusValues,
            'is_hot_deal' => 'nullable|boolean',
            'expired_at' => 'nullable|date_format:Y-m-d H:i:s',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $deal = $this->ownSellerDeal($request->uuid);
        if (!$deal) {
            return UtillsHelper::json(0, ['message' => 'Deal not found']);
        }

        $deal->deal_type = $request->deal_type;
        if ($request->filled('available_quantity')) {
            $deal->available_quantity = (int) $request->available_quantity;
        }
        $deal->share_price = $request->share_price;
        $deal->minimum_qty = $request->minimum_qty;
        $deal->status = $request->status;
        $deal->is_hot_deal = $request->boolean('is_hot_deal');
        $deal->expired_at = $request->filled('expired_at') ? $request->input('expired_at') : null;
        $deal->save();

        $sellerId = (int) $request->user()->id;

        return UtillsHelper::json(1, [
            'message' => 'Deal updated',
            'data' => $this->formatSellerDeal($deal->fresh(['company:id,brand_name,slug,logo,type']), $sellerId),
        ]);
    }

    public function deleteDeal(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'uuid' => 'required|string',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $deal = $this->ownSellerDeal($request->uuid);
        if (!$deal) {
            return UtillsHelper::json(0, ['message' => 'Deal not found']);
        }

        $deal->is_deleted = true;
        $deal->save();

        return UtillsHelper::json(1, [
            'message' => 'Deal deleted',
        ]);
    }

    public function listInstitutionDeals(int $partnerId): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'company_id' => 'nullable|integer',
            'type' => 'nullable|in:All,' . implode(',', array_column(CompanyTypeEnum::cases(), 'value')),
            'skip' => 'nullable|integer|min:0',
            'take' => 'nullable|integer|min:1',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $type = $request->input('type', 'All');
        $query = CompanyDealModel::notDeleted()
            ->hot()
            ->where('created_by_partner_id', $partnerId)
            ->whereHas('company', function ($q) use ($type) {
                $q->where('is_deleted', '0')
                    ->approved();

                if ($type === 'All') {
                    $q->whereIn('type', [
                        CompanyTypeEnum::unlisted->value,
                        CompanyTypeEnum::secondary->value,
                    ]);
                } else {
                    $q->where('type', $type);
                }
            })
            ->with([
                'company' => fn ($q) => $q->select('id', 'brand_name', 'slug', 'logo', 'type'),
            ])
            ->orderByDesc('id');

        if ($request->filled('company_id')) {
            $company = $this->approvedDealCompany((int) $request->company_id);
            if (!$company) {
                return UtillsHelper::json(0, ['message' => 'Company not found']);
            }
            $query->where('company_id', $company->id);
        }

        $skip = max(0, (int) $request->input('skip', 0));
        $take = (int) $request->input('take', CommonHelper::appSettings('app_pagination_limit'));
        if ($take < 1) {
            $take = 15;
        }

        $total = (clone $query)->count();
        $deals = $query
            ->skip($skip)
            ->take($take)
            ->get()
            ->map(fn (CompanyDealModel $deal) => $this->formatInstitutionDeal($deal, $partnerId))
            ->values();

        return UtillsHelper::json(1, [
            'message' => 'Deal list',
            'data' => $deals,
            'total' => $total,
            'skip' => $skip,
            'take' => $take,
        ]);
    }

    public function createInstitutionDeal(int $partnerId): JsonResponse
    {
        $request = request();
        $statusValues = implode(',', array_column(CompanyDealStatusEnum::cases(), 'value'));
        $dealTypeValues = implode(',', array_column(CompanyDealTypeEnum::cases(), 'value'));
        $validation = Validator::make($request->all(), [
            'company_id' => 'required|integer',
            'deal_type' => 'required|in:' . $dealTypeValues,
            'available_quantity' => 'required|integer|min:0',
            'share_price' => 'required|numeric|min:0',
            'minimum_qty' => 'required|integer|min:1',
            'status' => 'nullable|in:' . $statusValues,
            'is_hot_deal' => 'nullable|boolean',
            'expired_at' => 'nullable|date_format:Y-m-d H:i:s',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $company = $this->approvedDealCompany((int) $request->company_id);
        if (!$company) {
            return UtillsHelper::json(0, ['message' => 'Company not found']);
        }

        $deal = new CompanyDealModel();
        $deal->company_id = $company->id;
        $deal->created_by_partner_id = $partnerId;
        $deal->created_by_seller_id = null;
        $deal->deal_type = $request->deal_type;
        $deal->available_quantity = (int) $request->available_quantity;
        $deal->minimum_qty = $request->minimum_qty;
        app(CompanyDealPricing::class)->stampFromBase($deal, $request->share_price);
        $deal->status = $request->filled('status')
            ? $request->status
            : CompanyDealStatusEnum::available->value;
        $deal->is_hot_deal = $request->boolean('is_hot_deal');
        $deal->expired_at = $request->filled('expired_at') ? $request->input('expired_at') : null;
        DB::transaction(function () use ($deal) {
            $deal->save();
            $this->recordNonHotDealHistory($deal);
        });

        return UtillsHelper::json(1, [
            'message' => 'Deal created',
            'data' => $this->formatInstitutionDeal($deal->fresh(['company:id,brand_name,slug,logo,type']), $partnerId),
        ]);
    }

    public function createInstitutionDealsBulk(int $partnerId): JsonResponse
    {
        $request = request();
        $sellRows = $request->exists('sell') ? $request->input('sell') : [];
        $buyRows = $request->exists('buy') ? $request->input('buy') : [];

        if ($sellRows === null) {
            $sellRows = [];
        }
        if ($buyRows === null) {
            $buyRows = [];
        }
        if (!is_array($sellRows)) {
            return UtillsHelper::json(0, ['message' => 'sell must be an array.']);
        }
        if (!is_array($buyRows)) {
            return UtillsHelper::json(0, ['message' => 'buy must be an array.']);
        }

        $prepared = [];
        foreach ($sellRows as $index => $row) {
            $parsed = $this->parseBulkDealRow($row, 'sell', (int) $index);
            if ($parsed === null) {
                continue;
            }
            if (is_string($parsed)) {
                return UtillsHelper::json(0, ['message' => $parsed]);
            }
            $prepared[] = $parsed;
        }
        foreach ($buyRows as $index => $row) {
            $parsed = $this->parseBulkDealRow($row, 'buy', (int) $index);
            if ($parsed === null) {
                continue;
            }
            if (is_string($parsed)) {
                return UtillsHelper::json(0, ['message' => $parsed]);
            }
            $prepared[] = $parsed;
        }

        if ($prepared === []) {
            return UtillsHelper::json(0, ['message' => 'At least one valid sell or buy row is required.']);
        }

        $companyIds = collect($prepared)->pluck('company_id')->unique()->values();
        $companies = CompanyModel::query()
            ->whereIn('id', $companyIds)
            ->where('is_deleted', '0')
            ->whereIn('type', [
                CompanyTypeEnum::unlisted->value,
                CompanyTypeEnum::secondary->value,
            ])
            ->approved()
            ->get(['id'])
            ->keyBy('id');

        foreach ($prepared as $row) {
            if (!$companies->has($row['company_id'])) {
                $side = $row['side'] === 'sell' ? 'Sell' : 'Buy';

                return UtillsHelper::json(0, [
                    'message' => $side . ' row ' . $row['row_number'] . ' company not found (company_id ' . $row['company_id'] . ').',
                ]);
            }
        }

        $created = [];
        try {
            DB::beginTransaction();

            foreach ($prepared as $row) {
                $deal = new CompanyDealModel();
                $deal->company_id = $row['company_id'];
                $deal->created_by_partner_id = $partnerId;
                $deal->created_by_seller_id = null;
                $deal->deal_type = $row['side'];
                $deal->available_quantity = $row['available_quantity'];
                $deal->minimum_qty = $row['min_qty'];
                app(CompanyDealPricing::class)->stampFromBase($deal, $row['base_price']);
                $deal->status = CompanyDealStatusEnum::available->value;
                $deal->is_hot_deal = false;
                $deal->expired_at = null;
                $deal->save();
                $created[] = $deal;
            }

            app(CompanyDealPricing::class)->recordNonHotHistory(
                collect($created)->pluck('company_id')->map(fn ($id) => (int) $id)->all()
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return UtillsHelper::json(0, ['message' => 'Unable to create deals. Please try again.']);
        }

        $data = collect($created)
            ->map(function (CompanyDealModel $deal) use ($partnerId) {
                return $this->formatInstitutionDeal(
                    $deal->fresh(['company:id,brand_name,slug,logo,type']),
                    $partnerId
                );
            })
            ->values();

        return UtillsHelper::json(1, [
            'message' => 'Deals created',
            'data' => $data,
        ]);
    }

    public function updateInstitutionDeal(int $partnerId): JsonResponse
    {
        $request = request();
        $statusValues = implode(',', array_column(CompanyDealStatusEnum::cases(), 'value'));
        $dealTypeValues = implode(',', array_column(CompanyDealTypeEnum::cases(), 'value'));
        $validation = Validator::make($request->all(), [
            'uuid' => 'required|string',
            'deal_type' => 'required|in:' . $dealTypeValues,
            'available_quantity' => 'nullable|integer|min:0',
            'share_price' => 'required|numeric|min:0',
            'minimum_qty' => 'required|integer|min:1',
            'status' => 'required|in:' . $statusValues,
            'is_hot_deal' => 'nullable|boolean',
            'expired_at' => 'nullable|date_format:Y-m-d H:i:s',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $deal = $this->ownInstitutionDeal($request->uuid, $partnerId);
        if (!$deal) {
            return UtillsHelper::json(0, ['message' => 'Deal not found']);
        }

        $deal->deal_type = $request->deal_type;
        if ($request->filled('available_quantity')) {
            $deal->available_quantity = (int) $request->available_quantity;
        }
        $deal->share_price = $request->share_price;
        $deal->minimum_qty = $request->minimum_qty;
        $deal->status = $request->status;
        $deal->is_hot_deal = $request->boolean('is_hot_deal');
        $deal->expired_at = $request->filled('expired_at') ? $request->input('expired_at') : null;
        $deal->save();

        return UtillsHelper::json(1, [
            'message' => 'Deal updated',
            'data' => $this->formatInstitutionDeal($deal->fresh(['company:id,brand_name,slug,logo,type']), $partnerId),
        ]);
    }

    public function deleteInstitutionDeal(int $partnerId): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'uuid' => 'required|string',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $deal = $this->ownInstitutionDeal($request->uuid, $partnerId);
        if (!$deal) {
            return UtillsHelper::json(0, ['message' => 'Deal not found']);
        }

        $deal->is_deleted = true;
        $deal->save();

        return UtillsHelper::json(1, [
            'message' => 'Deal deleted',
        ]);
    }

    public function updateSharePrice(): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'prices' => 'required|array|min:1',
            'prices.*.company_id' => 'required|integer|distinct',
            'prices.*.sell_price' => 'required|numeric|min:0',
            'prices.*.buy_price' => 'nullable|numeric|min:0',
            'prices.*.min_qty' => 'required|integer|min:1',
            'prices.*.total_qty' => 'nullable|integer|min:1',
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $prices = $request->input('prices', []);
        foreach ($prices as $index => $row) {
            if (array_key_exists('total_qty', $row) && $row['total_qty'] !== null && $row['total_qty'] !== '') {
                if ((int) $row['total_qty'] < (int) $row['min_qty']) {
                    return UtillsHelper::json(0, [
                        'message' => 'The prices.' . $index . '.total_qty must be greater than or equal to min_qty.',
                    ]);
                }
            }
        }

        $companyIds = collect($prices)->pluck('company_id')->map(fn ($id) => (int) $id)->unique()->values();
        $companies = CompanyModel::approved()
            ->where('is_deleted', '0')
            ->whereIn('id', $companyIds)
            ->get(['id'])
            ->keyBy('id');

        if ($companies->count() !== $companyIds->count()) {
            return UtillsHelper::json(0, ['message' => 'One or more companies were not found or are not approved.']);
        }

        $sellerId = (int) $request->user()->id;
        $today = now()->toDateString();
        $savedCompanyIds = [];

        try {
            DB::beginTransaction();

            foreach ($prices as $row) {
                $companyId = (int) $row['company_id'];
                $buyPrice = array_key_exists('buy_price', $row) && $row['buy_price'] !== null && $row['buy_price'] !== ''
                    ? $row['buy_price']
                    : null;
                $totalQty = array_key_exists('total_qty', $row) && $row['total_qty'] !== null && $row['total_qty'] !== ''
                    ? (int) $row['total_qty']
                    : null;

                SellerCompanySharePriceModel::create([
                    'company_id' => $companyId,
                    'seller_id' => $sellerId,
                    'date' => $today,
                    'sell_price' => $row['sell_price'],
                    'buy_price' => $buyPrice,
                    'min_qty' => (int) $row['min_qty'],
                    'total_qty' => $totalQty,
                ]);

                $savedCompanyIds[] = $companyId;
            }

            CompanyModel::whereIn('id', $savedCompanyIds)->update([
                'is_price_updated_today' => 1,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return UtillsHelper::json(0, ['message' => 'Unable to update share prices. Please try again.']);
        }

        return UtillsHelper::json(1, [
            'message' => 'Share prices updated',
            'data' => [
                'updated_count' => count($savedCompanyIds),
                'company_ids' => $savedCompanyIds,
            ],
        ]);
    }

    public function savePromoters(?int $partnerId = null): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'company_id' => 'required|integer',
            'promoters' => 'nullable|array',
            'promoters.*.name' => 'required|string|max:255',
            'promoters.*.designation' => 'required|string|max:255',
            'promoters.*.experience' => 'required|string|max:255',
            'promoters.*.url' => 'nullable|string|max:2000',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $company = $this->ownedCompany((int) $request->company_id, $partnerId);
        if (!$company) {
            return UtillsHelper::json(0, ['message' => 'Company not found']);
        }

        try {
            DB::beginTransaction();

            CompanyPromotersModel::where('company_id', $company->id)->delete();

            foreach ($request->input('promoters', []) as $row) {
                $promoter = new CompanyPromotersModel();
                $promoter->company_id = $company->id;
                $promoter->name = $row['name'];
                $promoter->designation = Str::title($row['designation']);
                $promoter->experience = Str::title($row['experience']);
                $promoter->url = isset($row['url']) && trim((string) $row['url']) !== ''
                    ? trim((string) $row['url'])
                    : '';
                $promoter->save();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return UtillsHelper::json(0, ['message' => 'Unable to save promoters. Please try again.']);
        }

        return UtillsHelper::json(1, [
            'message' => 'Promoters updated',
        ]);
    }

    public function saveShareholders(?int $partnerId = null): JsonResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'company_id' => 'required|integer',
            'shareholders' => 'nullable|array',
            'shareholders.*.year' => 'required|string|max:20',
            'shareholders.*.shareholders' => 'nullable|array',
            'shareholders.*.shareholders.*.name' => 'required|string|max:255',
            'shareholders.*.shareholders.*.percentage' => 'required|numeric|min:0|max:100',
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $company = $this->ownedCompany((int) $request->company_id, $partnerId);
        if (!$company) {
            return UtillsHelper::json(0, ['message' => 'Company not found']);
        }

        try {
            DB::beginTransaction();

            CompanyShareHolderPercentageModel::where('company_id', $company->id)->delete();
            CompanyShareHolderModel::where('company_id', $company->id)->delete();

            $namesByKey = [];
            $percentagesByKey = [];

            foreach ($request->input('shareholders', []) as $yearGroup) {
                $year = trim((string) $yearGroup['year']);
                foreach ($yearGroup['shareholders'] ?? [] as $holderRow) {
                    $name = trim((string) $holderRow['name']);
                    $key = mb_strtolower($name);
                    if ($key === '') {
                        continue;
                    }
                    if (!isset($namesByKey[$key])) {
                        $namesByKey[$key] = $name;
                    }
                    $percentagesByKey[$key][$year] = $holderRow['percentage'];
                }
            }

            foreach ($namesByKey as $key => $name) {
                $holder = CompanyShareHolderModel::create([
                    'company_id' => $company->id,
                    'name' => $name,
                ]);

                foreach ($percentagesByKey[$key] ?? [] as $year => $percentage) {
                    CompanyShareHolderPercentageModel::create([
                        'company_id' => $company->id,
                        'share_holder_id' => $holder->id,
                        'year' => $year,
                        'percentage' => $percentage,
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return UtillsHelper::json(0, ['message' => 'Unable to save shareholders. Please try again.']);
        }

        return UtillsHelper::json(1, [
            'message' => 'Shareholders updated',
        ]);
    }

    private function formatShareHolders(int $companyId)
    {
        return CompanyShareHolderPercentageModel::with('shareHolder:name,id')
            ->where('company_id', $companyId)
            ->select('share_holder_id', 'year', 'percentage')
            ->orderBy('year', 'asc')
            ->get()
            ->groupBy('year')
            ->map(function ($yearGroup, $year) {
                $formattedData = $yearGroup->sortByDesc('percentage')->map(function ($shareHolderPercentage) {
                    return [
                        'name' => $shareHolderPercentage->shareHolder->name ?? null,
                        'percentage' => $shareHolderPercentage->percentage,
                    ];
                })->values();

                return [
                    'year' => $year,
                    'shareholders' => $formattedData,
                ];
            })
            ->values();
    }

    private function formatSellerDeal(CompanyDealModel $deal, int $sellerId): array
    {
        if (!$deal->relationLoaded('company')) {
            $deal->load(['company' => fn ($q) => $q->select('id', 'brand_name', 'slug', 'logo', 'type')]);
        }

        $company = $deal->company;

        return [
            'uuid' => $deal->uuid,
            'deal_type' => $deal->deal_type,
            'available_quantity' => $deal->available_quantity,
            'base_price' => $deal->base_price,
            'share_price' => $deal->share_price,
            'minimum_qty' => $deal->minimum_qty,
            'processing_fee_percentage' => $deal->processing_fee_percentage,
            'status' => $deal->status,
            'is_hot_deal' => (bool) $deal->is_hot_deal,
            'expired_at' => $deal->expired_at?->format('Y-m-d H:i:s'),
            'is_expired' => $deal->isExpired(),
            'is_mine' => (int) $deal->created_by_seller_id === $sellerId,
            'company' => $company ? [
                'id' => $company->id,
                'brand_name' => $company->brand_name,
                'slug' => $company->slug,
                'logo' => $company->logo,
                'type' => $company->type,
            ] : null,
        ];
    }

    private function formatInstitutionDeal(CompanyDealModel $deal, int $partnerId): array
    {
        $formatted = $this->formatSellerDeal($deal, (int) ($deal->created_by_seller_id ?? 0));
        $formatted['is_mine'] = (int) $deal->created_by_partner_id === $partnerId;

        return $formatted;
    }

    private function recordNonHotDealHistory(CompanyDealModel $deal): void
    {
        if ($deal->is_hot_deal) {
            return;
        }

        app(CompanyDealPricing::class)->recordNonHotHistory([(int) $deal->company_id]);
    }

    /**
     * @return array{side: string, row_number: int, company_id: int, base_price: float, min_qty: int, available_quantity: int}|string|null
     *         null skips a blank or zero-price row. A string is a row error.
     */
    private function parseBulkDealRow(mixed $row, string $side, int $index): array|string|null
    {
        $priceKey = $side === 'sell' ? 'sell_price' : 'buy_price';
        $label = $side === 'sell' ? 'Sell' : 'Buy';
        $rowNumber = $index + 1;

        if (!is_array($row)) {
            return $label . ' row ' . $rowNumber . ' is invalid.';
        }

        $companyRaw = $row['company_id'] ?? null;
        $priceRaw = $row[$priceKey] ?? null;
        $minRaw = $row['min_qty'] ?? null;

        if (is_string($companyRaw)) {
            $companyRaw = trim($companyRaw);
        }
        if (is_string($priceRaw)) {
            $priceRaw = trim($priceRaw);
        }
        if (is_string($minRaw)) {
            $minRaw = trim($minRaw);
        }

        $priceMissing = $priceRaw === null || $priceRaw === '';
        if ($priceMissing || (is_numeric($priceRaw) && (float) $priceRaw == 0.0)) {
            return null;
        }

        if (!is_numeric($priceRaw) || (float) $priceRaw <= 0) {
            return $label . ' row ' . $rowNumber . ' has an invalid price.';
        }

        if ($companyRaw === null || $companyRaw === '' || !$this->isWholeNumber($companyRaw) || (int) $companyRaw < 1) {
            return $label . ' row ' . $rowNumber . ' requires a valid company_id.';
        }

        if ($minRaw === null || $minRaw === '' || !$this->isWholeNumber($minRaw) || (int) $minRaw < 1) {
            return $label . ' row ' . $rowNumber . ' requires min_qty of at least 1.';
        }

        $minQty = (int) $minRaw;
        $available = 0;
        if (array_key_exists('total_qty', $row) && $row['total_qty'] !== null && $row['total_qty'] !== '') {
            $totalRaw = $row['total_qty'];
            if (is_string($totalRaw)) {
                $totalRaw = trim($totalRaw);
            }
            if ($this->isWholeNumber($totalRaw) && (int) $totalRaw >= $minQty) {
                $available = (int) $totalRaw;
            }
        }

        return [
            'side' => $side,
            'row_number' => $rowNumber,
            'company_id' => (int) $companyRaw,
            'base_price' => round((float) $priceRaw, 2),
            'min_qty' => $minQty,
            'available_quantity' => $available,
        ];
    }

    private function isWholeNumber(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }
        if (is_float($value)) {
            return floor($value) == $value;
        }
        if (!is_string($value) || !is_numeric($value)) {
            return false;
        }

        return preg_match('/^-?\d+$/', $value) === 1;
    }

    private function approvedDealCompany(int $companyId): ?CompanyModel
    {
        return CompanyModel::where('id', $companyId)
            ->where('is_deleted', '0')
            ->whereIn('type', [
                CompanyTypeEnum::unlisted->value,
                CompanyTypeEnum::secondary->value,
            ])
            ->approved()
            ->first();
    }

    private function ownSellerDeal(string $uuid): ?CompanyDealModel
    {
        $deal = CompanyDealModel::notDeleted()
            ->where('uuid', $uuid)
            ->where('created_by_seller_id', request()->user()->id)
            ->first();

        if (!$deal) {
            return null;
        }

        $company = $this->approvedDealCompany((int) $deal->company_id);
        if (!$company) {
            return null;
        }

        return $deal;
    }

    private function ownInstitutionDeal(string $uuid, int $partnerId): ?CompanyDealModel
    {
        $deal = CompanyDealModel::notDeleted()
            ->where('uuid', $uuid)
            ->where('created_by_partner_id', $partnerId)
            ->first();

        if (!$deal) {
            return null;
        }

        $company = $this->approvedDealCompany((int) $deal->company_id);
        if (!$company) {
            return null;
        }

        return $deal;
    }

    private function ownedCompany(int $companyId, ?int $partnerId = null): ?CompanyModel
    {
        $query = CompanyModel::where('id', $companyId)
            ->where('is_deleted', '0')
            ->where('approval_status', '!=', CompanyApprovalStatusEnum::rejected->value);

        if ($partnerId !== null) {
            $query->where('submitted_by_partner_id', $partnerId);
        } else {
            $query->where('submitted_by_seller_id', request()->user()->id);
        }

        return $query->first();
    }

    private function normalizeIdentity(?string $value): string
    {
        return strtoupper(str_replace(' ', '', trim((string) $value)));
    }

    private function cleanCommaList(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return collect(explode(',', strtolower($value)))
            ->map(fn ($k) => trim($k))
            ->filter()
            ->implode(',');
    }
}
