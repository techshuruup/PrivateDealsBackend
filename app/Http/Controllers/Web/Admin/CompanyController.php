<?php

namespace App\Http\Controllers\Web\Admin;

use Illuminate\Support\Arr;
use App\DataTables\CompanyDataTable;
use App\Enums\CompanyApprovalStatusEnum;
use App\Enums\CompanyTypeEnum;
use App\Enums\MinimumInvestmentTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\PreIpoCategoryEnum;
use App\Enums\WpMessageTypeEnum;
use App\Exports\CompanyTemplateDownload;
use App\Helpers\AdminHelper;
use App\Helpers\CommonHelper;
use App\Helpers\DateTimeHelper;
use App\Helpers\FileUpDownHelper;
use App\Helpers\UtillsHelper;
use App\Http\Controllers\Controller;
use App\Jobs\broadcast\Whatsapp;
use App\Jobs\preipo\CalcuatePricingAutoJob;
use App\Jobs\PreIpoSharePriceUpdate;
use App\Jobs\PreIpoSharePriceUpdateJob;
use App\Models\BonusHistoryModel;
use App\Models\CompanyCustomDataModel;
use App\Models\CompanyDailySharePriceModel;
use App\Models\CompanyEventsModel;
use App\Models\CompanyFundamentalsModel;
use App\Models\CompanyModel;
use App\Models\CompanyNewsModel;
use App\Models\CompanyPeerRatioModel;
use App\Models\CompanyPromotersModel;
use App\Models\CompanyShareHolderModel;
use App\Models\CompanyShareHolderPercentage;
use App\Models\CompanyShareHolderPercentageModel;
use App\Models\CompanySharePriceModel;
use App\Models\CompanyGrabOpportunitySlotModel;
use App\Models\MasterSectorsModel;
use App\Models\PartnerModel;
use App\Models\WhatsappBroadcastModel;
use App\Traits\FileUploadTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class CompanyController extends Controller
{
    use FileUploadTrait;

    function download()
    {
        $data = [];
        return Excel::download(new CompanyTemplateDownload($data), 'company_data.xlsx');
    }

    public function uploadCompanyExcel(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'file'               => 'required|file|mimes:xls,xlsx|max:' . UtillsHelper::maxFileDocumentSizeInKB()
        ]);

        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        // Read the Excel file into an array
        $array = Excel::toArray([], $request->file('file'));

        if (isset($array) && is_array($array) && count($array) > 0) {
            $data = $array[0]; // Get the first sheet data

            if (count($data) < 2) {
                return UtillsHelper::json(0, ['message' => 'Invalid Data in excel']);
            }

            // Extract headers and determine seller columns
            $headers = $data[0]; // First row contains column headings
            $sellerColumns = array_slice($headers, 2); // Start from the 3rd column for sellers
            $sellerColumns = array_filter($sellerColumns); // Remove empty columns

            if (empty($sellerColumns)) {
                return UtillsHelper::json(0, ['message' => 'Excel file must contain seller columns starting from the 3rd column.']);
            }

            // Process rows (skip header row)
            foreach (array_slice($data, 1) as $row) {
                $companyName = $row[0] ?? null;
                $retailPrice = $row[1] ?? null;

                if (!$companyName || !$retailPrice || !is_numeric($retailPrice)) {
                    return UtillsHelper::json(0, ['message' => 'Invalid row detected. Ensure company name and retail price are valid.']);
                }

                // Fetch the company ID based on the company name
                $company = CompanyModel::where('brand_name', $companyName)->first();
                if (!$company) {
                    return UtillsHelper::json(0, ['message' => "Unknown company name '{$companyName}' in Excel file."]);
                }

                // Process seller prices
                foreach ($sellerColumns as $key => $sellerName) {
                    $sellerPrice = $row[$key + 2] ?? null; // Seller prices start from the 3rd column

                    if ($sellerPrice && is_numeric($sellerPrice)) {
                        // Calculate distributor price and other fields
                        $distributorPrice = $this->calculateDistributorPrice($sellerPrice);

                        // Store the data in the database
                        CompanyDailySharePriceModel::create([
                            'company_id'        => $company->id,
                            'date'              => date('Y-m-d'),
                            'seller'            => $sellerName,
                            'price'             => $sellerPrice,
                            'distributor_price' => $distributorPrice,
                            'retailer_price'    => $retailPrice,
                        ]);
                    }
                }
            }

            // Dispatch job for post-processing
            PreIpoSharePriceUpdateJob::dispatch(date('Y-m-d'));

            // return redirect()->back()->with('success', 'Prices Updated');
            return UtillsHelper::json(1, ['message' => 'Excel data uploaded successfully']);
        }

        return UtillsHelper::json(0, ['message' => 'Excel has no data']);
    }
    private function calculateDistributorPrice(float $price): float
    {
        $distributorPrice = ($price * (1 / 100)) + $price;
        $grossDisPrice = $distributorPrice * (2.5 / 100);
        $gst = $grossDisPrice * (18 / 100);
        return $distributorPrice + $grossDisPrice + $gst;
    }


    function newsSave(Request $request): RedirectResponse
    {
        $item = CompanyModel::where('id', $request->item)->first();
        if ($item) {
            if ($request->delete) {
                CompanyNewsModel::whereIn('id', explode(',', $request->delete))->delete();
            }
            if ($request->input) {
                foreach ($request->input as $key => $value) {
                    if ($value['old_id'] != "") {
                        $event = CompanyNewsModel::find($value['old_id']);
                    } else {
                        $event = new CompanyNewsModel();
                        $event->company_id                   = $item->id;
                    }
                    $event->title                           = $value['title'];
                    $event->link                           = $value['link'];
                    $event->description                     = $value['description'];
                    if (isset($request->file('input')[$key]['file'])) {
                        $event->image = FileUpDownHelper::company_news_file_upload($request->file('input')[$key]['file']);
                    }
                    $event->save();
                }
            }
            return redirect()->route('admin.company.list')->with('success', 'News Updated');
        }
        return redirect()->route('admin.company.list')->with('error', 'Company not found');
    }

    function news($uuid): RedirectResponse|View
    {
        $item = CompanyModel::where('uuid', $uuid)->first();
        if ($item) {
            setPageTitle('News of ' . $item->brand_name);
            $data['company']   = $item;
            return view('admin.pages.company.news.item', $data);
        }
        return redirect()->back()->with('error', 'Item not found');
    }

    function view($uuid): View|RedirectResponse
    {
        $item = CompanyModel::where('uuid', $uuid)->first();
        if ($item) {
            setPageTitle('View of ' . $item->brand_name);
            $data['item']   = $item;
            $shareholdersData = CompanyShareHolderPercentageModel::with('shareHolder:name,id')
                ->where('company_id', $item->id)
                ->select('share_holder_id', 'year', 'percentage')
                ->orderBy('year', 'asc')
                ->get()
                ->groupBy('year')
                ->map(function ($yearGroup) {
                    $formattedData = [];
                    foreach ($yearGroup as $shareHolderPercentage) {
                        $formattedData[] = [
                            'name' => $shareHolderPercentage->shareHolder->name,
                            'percentage' => $shareHolderPercentage->percentage
                        ];
                    }
                    return $formattedData;
                });
            $data['shareholdersData']   = $shareholdersData;
            return view('admin.pages.company.view', $data);
        }
        return redirect()->back()->with('error', 'Item not found');
    }
    function financialsDownload($uuid): StreamedResponse
    {
        $company = CompanyModel::where('uuid', $uuid)->firstOrFail();

        $customData = CompanyCustomDataModel::where('company_id', $company->id)->get();

        $spreadsheet = new Spreadsheet();
        $sheetIndex = 0;

        foreach ($customData as $data) {
            $sheetTitle = match ($data->label) {
                'pl_statement' => 'Income Statement P&L',
                'financial_ratios' => 'Financial Ratios',
                'balance_sheet' => 'Balance Sheet',
                'cashflow' => 'Cashflow',
                default => ucfirst(str_replace('_', ' ', $data->label))
            };

            $values = json_decode($data->values, true);

            if ($sheetIndex === 0) {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle(substr($sheetTitle, 0, 31));
            } else {
                $sheet = $spreadsheet->createSheet($sheetIndex);
                $sheet->setTitle(substr($sheetTitle, 0, 31));
            }

            foreach ($values as $rowIndex => $row) {
                foreach ($row as $colIndex => $cell) {
                    $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 1, $cell);
                }
            }

            $sheetIndex++;
        }

        $fileName = 'Financials_' . $company->brand_name  . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName);
    }

    function customDataSave(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'custom'               => 'required|file|mimes:xls,xlsx|max:' . UtillsHelper::maxFileDocumentSizeInKB()
        ]);

        if ($validation->fails()) {
            return redirect()->back()->with('error', $validation->errors()->first());
        }

        $array = Excel::toArray([], $request->file('custom'));



        if (!empty($array) && is_array($array)) {
            CompanyCustomDataModel::where('company_id', $request->item)->delete();

            foreach ($array as $key => $sheet) {
                $label = match ($key) {
                    0 => 'pl_statement',
                    1 => 'financial_ratios',
                    2 => 'balance_sheet',
                    default => 'cashflow'
                };

                $header = Arr::get($sheet, 0, []);
                $headerColumnCount = count(array_filter($header, fn($val) => $val !== null && $val !== ''));

                if ($headerColumnCount === 0) {
                    continue;
                }

                $cleanedSheet = array_map(function ($row) use ($headerColumnCount) {
                    $trimmed = array_slice($row, 0, $headerColumnCount);
                    return array_map(fn($val) => $val === null ? "" : $val, $trimmed);
                }, $sheet);

                CompanyCustomDataModel::create([
                    'company_id' => $request->item,
                    'label'      => $label,
                    'values'     => json_encode($cleanedSheet)
                ]);
            }

            return redirect()->back()->with('success', 'Data imported');
        }

        return redirect()->back()->with('error', 'File is not valid');
    }

    function customData($uuid): RedirectResponse|View
    {
        $item = CompanyModel::where('uuid', $uuid)->first();
        if ($item) {
            setPageTitle('Custom data of ' . $item->brand_name);
            $data['company']   = $item;
            return view('admin.pages.company.custom-data.item', $data);
        }
        return redirect()->back()->with('error', 'Item not found');
    }

    public function getFinancialData(Request $request)
    {
        $dataId = $request->input('data_id');
        $data = CompanyCustomDataModel::find($dataId);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data not found'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Update financial data
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateFinancialData(Request $request)
    {
        $dataId = $request->input('data_id');
        $companyId = $request->input('company_id');
        $formData = $request->input('data');

        $customData = CompanyCustomDataModel::find($dataId);

        if (!$customData || $customData->company_id != $companyId) {
            return redirect()->back()->with('error', 'Data not found or does not belong to this company');
        }

        // Get current values
        $values = json_decode($customData->values, true);

        if (is_array($values) && count($values) > 0) {
            // Preserve the header row
            $header = $values[0];

            // Create new values array starting with header
            $newValues = [$header];

            // Process form data and add to newValues
            foreach ($formData as $rowIndex => $rowData) {
                // Skip empty rows (where label is empty)
                if (empty($rowData[0])) {
                    continue;
                }

                $newRow = [];
                foreach ($rowData as $colIndex => $cellValue) {
                    $newRow[] = $cellValue;
                }

                // Add to new values
                $newValues[] = $newRow;
            }

            // Save updated values
            $customData->values = json_encode($newValues);
            $customData->save();

            return redirect()->back()->with('success', 'Financial data updated successfully');
        }

        return redirect()->back()->with('error', 'Invalid data format');
    }

    /**
     * Add new year to financial data
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function addFinancialYear(Request $request)
    {
        $dataId = $request->input('data_id');
        $companyId = $request->input('company_id');
        $newYear = $request->input('new_year');

        // Get the specific custom data for this company
        $customData = CompanyCustomDataModel::find($dataId);

        if (!$customData || $customData->company_id != $companyId) {
            return redirect()->back()->with('error', 'Data not found or does not belong to this company');
        }

        // Get current values for this specific company's data
        $values = json_decode($customData->values, true);

        if (is_array($values) && count($values) > 0) {
            // Check if year already exists in this company's data
            if (in_array($newYear, $values[0])) {
                return redirect()->back()->with('error', 'Year ' . $newYear . ' already exists');
            }

            // Add new year to header for this company only
            $values[0][] = $newYear;

            // Add empty cells for the new year in each row for this company only
            for ($i = 1; $i < count($values); $i++) {
                $values[$i][] = 0; // Default value as 0
            }

            // Save updated values for this company only
            $customData->values = json_encode($values);
            $customData->save();

            return redirect()->back()->with('success', 'New year added successfully');
        }

        return redirect()->back()->with('error', 'Invalid data format');
    }
    function shareHoldersSave(Request $request): RedirectResponse
    {
        CompanyShareHolderModel::where('company_id', $request->input('item'))->delete();
        CompanyShareHolderPercentageModel::where('company_id', $request->input('item'))->delete();
        if ($request->shareholders) {
            foreach ($request->shareholders as $shareholderData) {
                // Create the shareholder
                $shareholder = CompanyShareHolderModel::create([
                    'company_id'    => $request->input('item'),
                    'name'          => $shareholderData['name'],
                ]);

                // Loop through percentages and save them
                foreach ($shareholderData['percentages'] as $percentageData) {
                    CompanyShareHolderPercentageModel::create([
                        'company_id' => $request->input('item'),
                        'share_holder_id' => $shareholder->id,
                        'year' => $percentageData['year'],
                        'percentage' => $percentageData['percentage'],
                    ]);
                }
            }
        }
        return redirect()->route('admin.company.list')->with('success', 'Share holders Updated');
    }

    function shareHolders($uuid): RedirectResponse|View
    {
        $item = CompanyModel::where('uuid', $uuid)->first();
        if ($item) {
            setPageTitle('Share holders of ' . $item->brand_name);
            $data['company']   = $item;
            return view('admin.pages.company.share-holder.item', $data);
        }
        return redirect()->back()->with('error', 'Item not found');
    }

    function sharePriceSave(Request $request): RedirectResponse
    {
        CompanySharePriceModel::where('company_id', $request->input('item'))->delete();
        if ($request->input('date')) {
            $dates = $request->input('date');
            $prices = $request->input('price');
            $distributer_prices = $request->input('distributer_price');

            foreach ($dates as $index => $date) {
                $address = new CompanySharePriceModel();
                $address->company_id = $request->input('item');
                $address->date = DateTimeHelper::getDBDateTime($date);
                $address->price = $prices[$index] ?? 0;
                $address->distributer_price = $distributer_prices[$index] ?? 0;
                $address->save();
            }
        }
        return redirect()->route('admin.company.list')->with('success', 'Share price Updated');
    }

    function sharePrice(Request $request, $uuid): RedirectResponse|View|JsonResponse
    {
        $item = CompanyModel::where('uuid', $uuid)->first();
        if (!$item) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Company not found'], 404);
            }
            return redirect()->back()->with('error', 'Item not found');
        }

        if ($request->ajax() || $request->wantsJson()) {
            $perPage = (int) $request->input('per_page', 15);
            if ($perPage < 1) {
                $perPage = 15;
            } elseif ($perPage > 50) {
                $perPage = 50;
            }

            $query = CompanySharePriceModel::where('company_id', $item->id)->orderBy('date', 'desc');

            $dateFrom = $this->parseSharePriceFilterDate($request->input('date_from'));
            $dateTo = $this->parseSharePriceFilterDate($request->input('date_to'));
            if ($dateFrom) {
                $query->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('date', '<=', $dateTo);
            }

            $paginator = $query->paginate($perPage);

            $rows = $paginator->getCollection()->map(function (CompanySharePriceModel $row) {
                return [
                    'id' => $row->id,
                    'date' => $row->date ? Carbon::parse($row->date)->format('d-m-Y') : '',
                    'price' => UtillsHelper::rupee() . UtillsHelper::moneyFormatIndia($row->price),
                    'distributer_price' => UtillsHelper::rupee() . UtillsHelper::moneyFormatIndia($row->distributer_price),
                    'base_price' => UtillsHelper::rupee() . UtillsHelper::moneyFormatIndia($row->base_price),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'brand_name' => $item->brand_name,
                'data' => $rows,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ]);
        }

        $item->load(['sharePrices' => function ($query) {
            $query->orderBy('date', 'desc');
        }]);
        setPageTitle('Share prices of ' . $item->brand_name);
        $data['company'] = $item;
        return view('admin.pages.company.share-price.item', $data);
    }

    function sharePriceDelete(Request $request, string $uuid, int $id): JsonResponse
    {
        $company = CompanyModel::where('uuid', $uuid)->first();
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Company not found'], 404);
        }

        $row = CompanySharePriceModel::where('id', $id)
            ->where('company_id', $company->id)
            ->first();
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Share price not found'], 404);
        }

        $row->delete();
        $company->syncPricesFromHistory();

        return response()->json(['success' => true, 'message' => 'Share price deleted']);
    }

    private function parseSharePriceFilterDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d-m-Y', $value)->format('Y-m-d');
        } catch (Exception $e) {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (Exception $e2) {
                return null;
            }
        }
    }

    function peerRatioSave(Request $request): RedirectResponse
    {
        CompanyPeerRatioModel::where('company_id', $request->input('item'))->delete();
        if ($request->input('perticular')) {
            $perticulars = $request->input('perticular');
            $revenues = $request->input('revenue');
            $epss = $request->input('eps');
            $market_caps = $request->input('market_cap');
            $pes = $request->input('pe');

            foreach ($perticulars as $index => $perticular) {
                $address = new CompanyPeerRatioModel();
                $address->company_id = $request->input('item');
                $address->perticular = $perticular;
                $address->revenue = $revenues[$index] ?? '';
                $address->eps = $epss[$index];
                $address->market_cap = $market_caps[$index] ?? '';
                $address->pe = $pes[$index] ?? '';
                $address->save();
            }
        }
        return redirect()->route('admin.company.list')->with('success', 'Peer Ratio Updated');
    }

    function peerRatio($uuid): RedirectResponse|View
    {
        $item = CompanyModel::where('uuid', $uuid)->first();
        if ($item) {
            setPageTitle('Peer Ratio of ' . $item->brand_name);
            $data['company']   = $item;
            return view('admin.pages.company.peer-ratio.item', $data);
        }
        return redirect()->back()->with('error', 'Item not found');
    }

    function eventSave(Request $request): RedirectResponse
    {
        $item = CompanyModel::where('id', $request->item)->first();
        if ($item) {
            if ($request->delete) {
                CompanyEventsModel::whereIn('id', explode(',', $request->delete))->delete();
            }
            if ($request->input) {
                foreach ($request->input as $key => $value) {
                    if ($value['old_id'] != "") {
                        $event = CompanyEventsModel::find($value['old_id']);
                    } else {
                        $event = new CompanyEventsModel();
                        $event->company_id                   = $item->id;
                    }
                    $event->date                            = DateTimeHelper::getDBDateTime($value['date']);
                    $event->title                           = $value['title'];
                    $event->description                     = $value['description'];
                    if (isset($request->file('input')[$key]['file'])) {
                        $event->file = FileUpDownHelper::company_event_file_upload($request->file('input')[$key]['file']);
                    }
                    $event->save();
                }
            }
            return redirect()->route('admin.company.list')->with('success', 'Event Updated');
        }
        return redirect()->route('admin.company.list')->with('error', 'Company not found');
    }

    function event($uuid): RedirectResponse|View
    {
        $item = CompanyModel::where('uuid', $uuid)->first();
        if ($item) {
            setPageTitle('Events of ' . $item->brand_name);
            $data['company']   = $item;
            return view('admin.pages.company.event.item', $data);
        }
        return redirect()->back()->with('error', 'Item not found');
    }

    function promoterSave(Request $request): RedirectResponse
    {
        CompanyPromotersModel::where('company_id', $request->input('item'))->delete();
        if ($request->input('name')) {
            $names = $request->input('name');
            $designations = $request->input('designation');
            $experiences = $request->input('experience');
            $urls = $request->input('url');

            foreach ($names as $index => $name) {
                $address = new CompanyPromotersModel();
                $address->company_id = $request->input('item');
                $address->name = $name;
                $address->designation = Str::title($designations[$index] ?? '');
                $address->experience = Str::title($experiences[$index]);
                $address->url = Str::title($urls[$index] ?? '');
                $address->save();
            }
        }
        return redirect()->route('admin.company.list')->with('success', 'Promoter Updated');
    }

    function promoter($uuid): RedirectResponse|View
    {
        $item = CompanyModel::where('uuid', $uuid)->first();
        if ($item) {
            setPageTitle('Promoters of ' . $item->brand_name);
            $data['company']   = $item;
            return view('admin.pages.company.promoter.item', $data);
        }
        return redirect()->back()->with('error', 'Item not found');
    }

    function update(Request $request): RedirectResponse
    {
        $validation = Validator::make($request->all(), [
            'logo' => 'nullable|image|mimes:' . CommonHelper::appSettings('file_image_extensions_allowed') . '|max:' . UtillsHelper::maxFileImageSizeInKB(),
            'cin'  => 'required|string',
            'brand_name' => 'required|string|max:255',
            'sector'    => 'required',
            'company_name' => 'required|string|max:255',
            'keywords' => 'nullable|string',
            'negative_keywords' => 'nullable|string',
            // 'min_investment_type' => 'required|string',
            'min_investment_amount' => 'required|numeric',
            'processing_fee_percentage' => 'required|numeric|between:0,100',
            // 'category'   => 'required',
            'category'   => 'nullable',
            'type'       => 'nullable|in:unlisted,secondary',
            'bg_color_code'   => 'nullable|string',
            'about' => 'required|string',
            'lot_size' => 'required|string|max:255',
            'fifty_two_week_high' => 'required|numeric|min:0',
            'fifty_two_week_low' => 'required|numeric|min:0',
            'depository' => 'required|string|max:255',
            'pan_number' => 'required|string|size:10',
            'isin_number' => 'required|string|size:12',
            // 'cin_number' => 'required|string|size:21',
            'rta' => 'required|string|max:255',
            'market_cap' => 'required|numeric|min:0',
            'pe_ratio' => 'required|numeric',
            'pb_ratio' => 'required|numeric',
            'debt_to_equity' => 'required|numeric|min:0',
            'roe' => 'required|numeric',
            'book_value' => 'required|numeric|min:0',
            'face_value' => 'required|numeric|min:0',
            'total_shares' => 'required|numeric|min:0',
            'list_order' => 'nullable|string|max:255',
        ]);
        if ($validation->fails()) {
            return redirect()->back()->withInput()->withErrors($validation)
                ->with('error', 'Please check form errors. And must select image if you want to change');
        }

        $company = CompanyModel::find($request->item);
        if ($company) {
            $company->brand_name = $request->input('brand_name');
            $company->cin = $request->input('cin');
            $company->company_name = $request->input('company_name');
            $rawKeywords = strtolower($request->input('keywords'));
            $cleanedKeywords = collect(explode(',', $rawKeywords))
                ->map(fn($k) => trim($k))
                ->filter()
                ->implode(',');
            $company->keywords = $cleanedKeywords;
            $rawNegativeKeywords = strtolower($request->input('negative_keywords'));
            $cleanedNegativeKeywords = collect(explode(',', $rawNegativeKeywords))
                ->map(fn($k) => trim($k))
                ->filter()
                ->implode(',');
            $company->negative_keywords = $cleanedNegativeKeywords;
            $rawAlternativeNames = strtolower($request->input('alternative_names'));
            $cleanedAlternativeNames = collect(explode(',', $rawAlternativeNames))
                ->map(fn($k) => trim($k))
                ->filter()
                ->implode(',');
            $company->alternative_names = $cleanedAlternativeNames;
            // $company->min_investment_type = $request->input('min_investment_type');
            $company->final_min_investment_amount = $request->input('min_investment_amount');
            $company->min_investment_type = MinimumInvestmentTypeEnum::quantity->value;
            $company->processing_fee_percentage = $request->input('processing_fee_percentage', CommonHelper::processingFeePercentage());
            $company->category = $request->filled('category') ? $request->input('category') : null;
            $company->type = $request->input('type', CompanyTypeEnum::unlisted->value);
            // Flags
            $company->is_trending = $request->has('is_trending') ? 1 : 0;
            $company->is_drhp     = $request->has('is_drhp') ? 1 : 0;
            $company->bg_color_code = $request->input('bg_color_code');
            $company->about = $request->input('about');
            $company->sector_id = $request->input('sector');
            if ($request->hasFile('logo')) {
                if ($company->logo && $company->logo != NULL) {
                    $this->deleteFile($company->logo);
                }
                $company->logo = FileUpDownHelper::company_logo_upload($request->file('logo'));
            }
            $company->list_order = $request->input('list_order');
            $company->is_grab_opportunity_enabled = $request->has('is_grab_opportunity_enabled') ? 1 : 0;
            $company->is_free_processing_fee = $request->has('is_free_processing_fee') ? 1 : 0;
            $company->slug = AdminHelper::companySlug($request->input('brand_name'), $company->id);
            $company->save();

            // Save/Update Grab Opportunity Slots
            $this->saveGrabOpportunitySlots($company->id, $request);

            $companyFundamentals = $company->fundamentals;
            if ($companyFundamentals) {
                $companyFundamentals->company_id = $company->id;
                // $companyFundamentals->lot_size = $request->input('lot_size');
                $companyFundamentals->lot_size = $request->input('min_investment_amount');
                $companyFundamentals->fifty_two_week_high = $request->input('fifty_two_week_high');
                $companyFundamentals->fifty_two_week_low = $request->input('fifty_two_week_low');
                $companyFundamentals->depository = $request->input('depository');
                $companyFundamentals->pan_number = $request->input('pan_number');
                $companyFundamentals->isin_number = $request->input('isin_number');
                // $companyFundamentals->cin_number = $request->input('cin_number');
                $companyFundamentals->cin_number = $request->input('cin');
                $companyFundamentals->rta = $request->input('rta');
                $companyFundamentals->market_cap = $request->input('market_cap');
                $companyFundamentals->pe_ratio = $request->input('pe_ratio');
                $companyFundamentals->pb_ratio = $request->input('pb_ratio');
                $companyFundamentals->debt_to_equity = $request->input('debt_to_equity');
                $companyFundamentals->roe = $request->input('roe');
                $companyFundamentals->book_value = $request->input('book_value');
                $companyFundamentals->face_value = $request->input('face_value');
                $companyFundamentals->total_shares = $request->input('total_shares');
                $companyFundamentals->save();
            }
            CalcuatePricingAutoJob::dispatch();
            AdminHelper::logPut('Company Update', CompanyModel::class, $company->id);
            return redirect()->route('admin.company.list')->with('success', 'Company Updated');
        }
        return redirect()->back()->with('error', 'Item not found');
    }

    function edit($uuid): RedirectResponse|View
    {
        $item = CompanyModel::where('uuid', $uuid)->with('grabOpportunitySlots')->first();
        if ($item) {
            setPageTitle('Edit ' . $item->brand_name);
            $data['item']   = $item;
            $data['sectors'] = MasterSectorsModel::where('is_deleted', '0')->get();
            return view('admin.pages.company.edit', $data);
        }
        return redirect()->back()->with('error', 'Item not found');
    }

    function save(Request $request): RedirectResponse
    {
        $validation = Validator::make($request->all(), [
            'logo' => 'nullable|image|mimes:' . CommonHelper::appSettings('file_image_extensions_allowed') . '|max:' . UtillsHelper::maxFileImageSizeInKB(),
            'cin'  => 'required|string',
            'brand_name' => 'required|string|max:255',
            'sector'    => 'required',
            'company_name' => 'required|string|max:255',
            'keywords' => 'nullable|string',
            'negative_keywords' => 'nullable|string',
            // 'min_investment_type' => 'required|string',
            'min_investment_amount' => 'required|numeric',
            // 'category'   => 'required',
            'category'   => 'nullable',
            'type'       => 'nullable|in:unlisted,secondary',
            'bg_color_code'   => 'nullable|string',
            'about' => 'required|string',
            'lot_size' => 'required|string|max:255',
            'fifty_two_week_high' => 'required|numeric|min:0',
            'fifty_two_week_low' => 'required|numeric|min:0',
            'depository' => 'required|string|max:255',
            'pan_number' => 'required|string|size:10',
            'isin_number' => 'required|string|size:12',
            // 'cin_number' => 'required|string|size:21',
            'rta' => 'required|string|max:255',
            'market_cap' => 'required|numeric|min:0',
            'pe_ratio' => 'required|numeric',
            'pb_ratio' => 'required|numeric',
            'debt_to_equity' => 'required|numeric|min:0',
            'roe' => 'required|numeric',
            'book_value' => 'required|numeric|min:0',
            'face_value' => 'required|numeric|min:0',
            'total_shares' => 'required|numeric|min:0',
            'list_order' => 'nullable|string|max:255',
        ]);

        if ($validation->fails()) {
            return redirect()->back()->withInput()->withErrors($validation)
                ->with('error', 'Please check form errors. And must select image.');
        }
        DB::beginTransaction();
        try {
            $company = new CompanyModel();
            $company->brand_name = $request->input('brand_name');
            $company->cin = $request->input('cin');
            $company->company_name = $request->input('company_name');
            $rawKeywords = strtolower($request->input('keywords'));
            $cleanedKeywords = collect(explode(',', $rawKeywords))
                ->map(fn($k) => trim($k))
                ->filter()
                ->implode(',');
            $company->keywords = $cleanedKeywords;
            $rawNegativeKeywords = strtolower($request->input('negative_keywords'));
            $cleanedNegativeKeywords = collect(explode(',', $rawNegativeKeywords))
                ->map(fn($k) => trim($k))
                ->filter()
                ->implode(',');
            $company->negative_keywords = $cleanedNegativeKeywords;
            $rawAlternativeNames = strtolower($request->input('alternative_names'));
            $cleanedAlternativeNames = collect(explode(',', $rawAlternativeNames))
                ->map(fn($k) => trim($k))
                ->filter()
                ->implode(',');
            $company->alternative_names = $cleanedAlternativeNames;
            // $company->min_investment_type = $request->input('min_investment_type');
            $company->final_min_investment_amount = $request->input('min_investment_amount');
            $company->min_investment_type = MinimumInvestmentTypeEnum::quantity->value;

            $processingFee = CommonHelper::processingFeePercentage();
            $company->commission = $processingFee;
            $company->processing_fee_percentage = $processingFee;
            $company->category = $request->filled('category') ? $request->input('category') : null;
            $company->type = $request->input('type', CompanyTypeEnum::unlisted->value);
            // Flags
            $company->is_trending = $request->has('is_trending') ? 1 : 0;
            $company->is_drhp     = $request->has('is_drhp') ? 1 : 0;
            $company->bg_color_code = $request->input('bg_color_code');
            $company->about = $request->input('about');
            $company->sector_id = $request->input('sector');
            if ($request->hasFile('logo')) {
                $company->logo = FileUpDownHelper::company_logo_upload($request->file('logo'));
            }
            $company->list_order = $request->input('list_order');
            $company->is_grab_opportunity_enabled = $request->has('is_grab_opportunity_enabled') ? 1 : 0;
            $company->is_free_processing_fee = $request->has('is_free_processing_fee') ? 1 : 0;
            $company->slug = AdminHelper::companySlug($request->input('brand_name'));
            $company->approval_status = CompanyApprovalStatusEnum::approved->value;
            $company->save();

            // Save/Update Grab Opportunity Slots
            $this->saveGrabOpportunitySlots($company->id, $request);

            $companyFundamentals = new CompanyFundamentalsModel();
            $companyFundamentals->company_id = $company->id;
            $companyFundamentals->lot_size = $request->input('lot_size');
            $companyFundamentals->fifty_two_week_high = $request->input('fifty_two_week_high');
            $companyFundamentals->fifty_two_week_low = $request->input('fifty_two_week_low');
            $companyFundamentals->depository = $request->input('depository');
            $companyFundamentals->pan_number = $request->input('pan_number');
            $companyFundamentals->isin_number = $request->input('isin_number');
            // $companyFundamentals->cin_number = $request->input('cin_number');
            $companyFundamentals->cin_number = $request->input('cin');
            $companyFundamentals->rta = $request->input('rta');
            $companyFundamentals->market_cap = $request->input('market_cap');
            $companyFundamentals->pe_ratio = $request->input('pe_ratio');
            $companyFundamentals->pb_ratio = $request->input('pb_ratio');
            $companyFundamentals->debt_to_equity = $request->input('debt_to_equity');
            $companyFundamentals->roe = $request->input('roe');
            $companyFundamentals->book_value = $request->input('book_value');
            $companyFundamentals->face_value = $request->input('face_value');
            $companyFundamentals->total_shares = $request->input('total_shares');
            $companyFundamentals->save();
            DB::commit();
            CalcuatePricingAutoJob::dispatch();
            AdminHelper::logPut('Company Creation', CompanyModel::class, $company->id);
            return redirect()->route('admin.company.list')->with('success', 'Company Created');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->withInput()->withErrors($validation)->with('error', 'An error occurred while saving the data: ' . $e->getMessage());
        }
    }

    function create(): View
    {
        setPageTitle('Create Company');
        $data['sectors'] = MasterSectorsModel::where('is_deleted', '0')->get();
        return view('admin.pages.company.create', $data);
    }

    function updateSharePrice(): View
    {
        setPageTitle('Update Share Price for ' . date('d F Y'));
        $data['list'] = CompanyModel::select('id', 'brand_name', 'company_name', 'commission')->where('is_deleted', '0')->orderBy('brand_name', 'asc')->get();
        return view('admin.pages.company.update-share-prices', $data);
    }

    function updateSharePriceSave(Request $request)
    {
        if ($request->seller_name && count($request->seller_name) > 0) {
            $createdIds = [];
            $companyIds = [];
            $logDetails = [];
            
            $admin = Auth::guard('admin')->user();
            $adminName = $admin ? $admin->name : 'Unknown';

            foreach ($request->company_id as $key => $value) {
                $company = CompanyModel::where('id', $value)->first();
                $companyCommission = $company->commission ?? 0;
                $companyName = $company->brand_name ?? 'Unknown';

                foreach ($request->seller_name as $sKey => $sValue) {
                    // Check if price and retailer_price are provided (not empty)
                    if (
                        isset($request->price[$sKey + 1][$key]) &&
                        isset($request->retailer_price[$sKey + 1][$key]) &&
                        trim($request->price[$sKey + 1][$key]) !== '' &&
                        trim($request->retailer_price[$sKey + 1][$key]) !== ''
                    ) {

                        $price = $request->price[$sKey + 1][$key];
                        $retailerPrice = $request->retailer_price[$sKey + 1][$key];
                        $distributer = 0;

                        // if ($price > 0) {
                        //     $distributerPrice = ($price * (1 / 100)) + $price;
                        //     $grossDisPrice = $distributerPrice * (2.5 / 100);
                        //     $gst = $grossDisPrice * (18 / 100);
                        //     $distributer = $distributerPrice + $grossDisPrice + $gst;
                        // }

                        if ($price > 0  && $companyCommission > 0) {
                            $distributer = ($price * ($companyCommission / 100)) + $price;
                        }

                        $record = CompanyDailySharePriceModel::create(attributes: [
                            'company_id'        => $value,
                            'date'              => date('Y-m-d'),
                            'seller'            => $sValue,
                            'price'             => $price,
                            'distributor_price' => $distributer,
                            'retailer_price'    => $retailerPrice,
                        ]);

                        $createdIds[] = $record->id;
                        $companyIds[] = $record->company_id;

                        $logDetails[] = "{seller: {$sValue}, companies: {name: {$companyName}, shareprice: {$retailerPrice}, baseprice: {$price}}}";
                    }
                }
            }

            if (count($createdIds) > 0) {
                $descriptionString = "{" . implode(', ', $logDetails) . "}";
                $finalDescription = "{$descriptionString} | Changed by admin: {$adminName}";
                
                AdminHelper::logPut($finalDescription, CompanyDailySharePriceModel::class, $companyIds[0] ?? 0);

                PreIpoSharePriceUpdateJob::dispatch($createdIds, $companyIds);
                return redirect()->back()->with('success', 'Prices Updated');

                // $priceData = CompanyDailySharePriceModel::whereIn('id', $createdIds)
                //     ->with('company')
                //     ->get();

                // $newsData = CompanyNewsModel::whereIn('company_id', $companyIds)
                //     ->latest()
                //     ->get()
                //     ->groupBy('company_id');

                // $fluctuationData = CompanyDailySharePriceModel::getPriceFluctuationAlert();

                // $pdf = Pdf::loadView('admin.pages.company.daily-share-report', [
                //     'prices' => $priceData,
                //     'newsData' => $newsData,
                //     'fluctuationData' => $fluctuationData,
                // ]);

                // // return $pdf->download('daily_share_report.pdf');
                // return $pdf->stream();

                // $pdf = Pdf::loadView('admin.pages.company.daily-share-report', [
                //     // 'logoPath' => $logoPath,
                //     'prices' => $priceData,
                //     'newsData' => $newsData,
                //     'fluctuationData' => $fluctuationData,
                // ])->setOption(['fontDir' => public_path('core/fonts/roboto'), 'fontCache' => public_path('core/fonts/Cache'), 'defaultFont' => 'Roboto']);

                // return $pdf->stream();
            }
        }

        return redirect()->back()->with('error', 'Please fill prices and submit');
    }

    // read image to update price start

    public function processOcrFinancialData(Request $request)
    {
        $request->validate([
            'financial_image' => 'required|image|mimes:jpeg,jpg,png|max:10240',
            'seller_name' => 'required|string|max:255'
        ]);

        try {
            // Get uploaded file directly
            $uploadedFile = $request->file('financial_image');

            // Extract text using OCR.Space API directly from uploaded file
            $rawText = $this->extractTextWithOcrSpaceDirect($uploadedFile);

            if (empty(trim($rawText))) {
                return response()->json([
                    'success' => false,
                    'message' => 'No text could be extracted from the image. Please ensure the image is clear and contains readable text.'
                ], 400);
            }

            // Parse the financial table data
            $extractedData = $this->parseFinancialTableForPriceUpdate($rawText);

            // Match with existing companies
            $matchedData = $this->matchExtractedDataWithCompanies($extractedData);

            return response()->json([
                'success' => true,
                'message' => "Successfully extracted " . count($matchedData) . " records from financial table",
                'data' => [
                    'seller_name' => $request->seller_name,
                    'extracted_data' => $matchedData,
                    'raw_text' => $rawText,
                    'total_extracted' => count($extractedData),
                    'total_matched' => count(array_filter($matchedData, fn($item) => $item['match_status'] === 'found'))
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing image: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract text from uploaded file using OCR.Space API
     */
    private function extractTextWithOcrSpaceDirect($uploadedFile)
    {
        try {
            // Configure HTTP client with SSL disabled for development
            $httpOptions = [];
            if (app()->environment('local')) {
                $httpOptions['verify'] = false;
            }

            // Get file info
            $fileExtension = strtolower($uploadedFile->getClientOriginalExtension());
            $fileName = $uploadedFile->getClientOriginalName();

            // Set proper MIME type and file type
            $mimeType = '';
            $fileType = '';

            switch ($fileExtension) {
                case 'jpg':
                case 'jpeg':
                    $mimeType = 'image/jpeg';
                    $fileType = 'JPG';
                    break;
                case 'png':
                    $mimeType = 'image/png';
                    $fileType = 'PNG';
                    break;
                case 'gif':
                    $mimeType = 'image/gif';
                    $fileType = 'GIF';
                    break;
                default:
                    $mimeType = 'image/jpeg';
                    $fileType = 'JPG';
            }

            // Read file content directly
            $fileContent = file_get_contents($uploadedFile->getRealPath());

            $response = Http::withOptions(array_merge([
                'timeout' => 60
            ], $httpOptions))
                ->attach('file', $fileContent, $fileName, [
                    'Content-Type' => $mimeType
                ])
                ->post('https://api.ocr.space/parse/image', [
                    'apikey' => env('OCR_SPACE_API_KEY', 'helloworld'),
                    'language' => 'eng',
                    'scale' => 'true',
                    'OCREngine' => '2',
                    'detectOrientation' => 'false',
                    'isTable' => 'true',
                    'filetype' => $fileType
                ]);

            if (!$response->successful()) {
                throw new \Exception('OCR API request failed with HTTP status: ' . $response->status());
            }

            $result = $response->json();

            if (!is_array($result)) {
                throw new \Exception('Invalid response format from OCR API');
            }

            if (isset($result['IsErroredOnProcessing']) && $result['IsErroredOnProcessing']) {
                $errorMessage = 'OCR processing error';
                if (isset($result['ErrorMessage'])) {
                    $errorMessage .= ': ' . (is_array($result['ErrorMessage']) ? implode(', ', $result['ErrorMessage']) : $result['ErrorMessage']);
                }
                throw new \Exception($errorMessage);
            }

            if (!isset($result['ParsedResults']) || !is_array($result['ParsedResults']) || empty($result['ParsedResults'])) {
                throw new \Exception('No OCR results returned from API');
            }

            return $result['ParsedResults'][0]['ParsedText'] ?? '';
        } catch (\Exception $e) {
            throw new \Exception('OCR processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Parse extracted OCR text to identify financial data
     */
    private function parseFinancialTableForPriceUpdate($text)
    {
        $lines = explode("\n", $text);
        $stocks = [];
        $dataStarted = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Detect start of data after headers
            if (
                stripos($line, 'Script Name') !== false ||
                stripos($line, 'Face Value') !== false ||
                stripos($line, 'Selling Price') !== false ||
                stripos($line, 'Landing Price') !== false
            ) {
                $dataStarted = true;
                continue;
            }

            // Skip footer lines and headers
            if (
                stripos($line, 'Rates') !== false ||
                stripos($line, 'confirm') !== false ||
                stripos($line, 'Valid till') !== false ||
                stripos($line, 'before transferring') !== false ||
                stripos($line, 'CNC: Call and') !== false
            ) {
                continue;
            }

            // Extract valid stock data rows
            if ($dataStarted && $this->isValidStockRow($line)) {
                $stockData = $this->parseStockRowForPricing($line);
                if (!empty($stockData['script_name']) && strlen(trim($stockData['script_name'])) > 1) {
                    $stocks[] = $stockData;
                }
            }
        }

        return $stocks;
    }

    /**
     * Check if line contains valid stock data
     */
    private function isValidStockRow($line)
    {
        return preg_match('/[A-Za-z]/', $line) &&
            preg_match('/\d/', $line) &&
            !preg_match('/^(Rates|Script|Face|Selling|Landing|Please|Valid|Date|CNC)/i', $line);
    }

    /**
     * Parse individual stock row to extract company name and prices
     */
    private function parseStockRowForPricing($line)
    {
        $line = preg_replace('/\s+/', ' ', trim($line));
        if ($line === '') return null;

        // Log::info('[OCR] Parsing table row: ' . $line);

        $parts = explode(' ', $line);

        // 4 columns - standard
        if (count($parts) >= 4) {
            $landing_price = array_pop($parts);
            $selling_price = array_pop($parts);
            $face_value = array_pop($parts);
            $script_name = implode(' ', $parts);

            // Clean up price values
            foreach (['face_value', 'selling_price', 'landing_price'] as $k) {
                if (isset($$k) && strtoupper($$k) === "CNC") {
                    $$k = "CNC";
                } else {
                    $$k = is_null($$k) ? null : str_replace(',', '', $$k);
                }
            }

            return [
                'script_name' => $script_name,
                'face_value' => $face_value,
                'selling_price' => $selling_price,
                'landing_price' => $landing_price
            ];
        }
        // 3 columns: Typically face_value missing (sometimes in mutual funds or simple scripts)
        elseif (count($parts) == 3) {
            // Is the second token numeric? Assume it's selling price, last is landing
            if (is_numeric(str_replace(',', '', $parts[1])) && is_numeric(str_replace(',', '', $parts[2]))) {
                $script_name = $parts[0];
                $face_value = null;
                $selling_price = str_replace(',', '', $parts[1]);
                $landing_price = str_replace(',', '', $parts[2]);
                // Log::warning('[OCR] Face value missing, assumed layout: ScriptName, Selling, Landing', ['line' => $line]);
            } else {
                $script_name = implode(' ', array_slice($parts, 0, -2));
                $face_value = null;
                $selling_price = str_replace(',', '', $parts[count($parts) - 2]);
                $landing_price = str_replace(',', '', $parts[count($parts) - 1]);
                // Log::warning('[OCR] Fallback: non-numeric, treated as ScriptName, Selling, Landing', ['line' => $line]);
            }

            return [
                'script_name' => $script_name,
                'face_value' => null,
                'selling_price' => $selling_price,
                'landing_price' => $landing_price
            ];
        }
        // 2 or less columns - definitely not enough for price
        else {
            // Log::warning('[OCR] Skipping: Not enough tokens for a table row', ['line' => $line, 'tokens' => $parts]);
            return [
                'script_name' => $line,
                'face_value' => null,
                'selling_price' => null,
                'landing_price' => null
            ];
        }
    }




    /**
     * Match extracted company names with database companies
     */
    private function matchExtractedDataWithCompanies($extractedData)
    {
        $matchedData = [];

        foreach ($extractedData as $extracted) {
            $companyName = trim($extracted['script_name']);

            // Try to find matching company using multiple strategies
            $company = $this->findCompanyByName($companyName);

            $matchedData[] = [
                'extracted_name' => $companyName,
                'company_id' => $company->id ?? null,
                'company_name' => $company ? ($company->brand_name . ' - ' . $company->company_name) : null,
                'brand_name' => $company->brand_name ?? null,
                'landing_price' => $extracted['landing_price'], // Your "price" field
                'selling_price' => $extracted['selling_price'],  // Your "retailer_price" field
                'face_value' => $extracted['face_value'],
                'match_status' => $company ? 'found' : 'not_found',
                'commission' => $company->commission ?? 0
            ];
        }

        return $matchedData;
    }

    /**
     * Find company by name using multiple matching strategies
     */
    private function normalizeScriptNameMatch($name)
    {
        return strtolower(
            preg_replace(
                '/\s+/',
                ' ',
                preg_replace('/[^a-zA-Z0-9 ]/', ' ', $name)
            )
        );
    }

    private function findCompanyByName($extractedName)
    {
        $name = $this->normalizeScriptNameMatch($extractedName);
        $companies = CompanyModel::where('is_deleted', '0')->get();

        // 1. Full exact match (after clean)
        foreach ($companies as $company) {
            if ($this->normalizeScriptNameMatch($company->brand_name) === $name) return $company;
            if ($this->normalizeScriptNameMatch($company->company_name) === $name) return $company;
            if (!empty($company->alternative_names)) {
                $altNames = array_map([$this, 'normalizeScriptNameMatch'], explode(',', $company->alternative_names));
                if (in_array($name, $altNames, true)) return $company;
            }
        }

        // 2. Token/word match for short names (e.g., NSE/INSE; allow off-by-1 char for 1-word names)
        foreach ($companies as $company) {
            $candidates = [
                $this->normalizeScriptNameMatch($company->brand_name),
                $this->normalizeScriptNameMatch($company->company_name)
            ];
            if (!empty($company->alternative_names)) {
                $candidates = array_merge($candidates, array_map([$this, 'normalizeScriptNameMatch'], explode(',', $company->alternative_names)));
            }
            foreach ($candidates as $dbName) {
                if (levenshtein($dbName, $name) <= 1 && strlen($dbName) <= 5) {
                    // allow for 1-letter OCR mistake for short names like NSE/INSE
                    return $company;
                }
            }
        }

        // 3. Gentle fallback: substring for longer names only (if no better match)
        if (strlen($name) >= 6) {
            foreach ($companies as $company) {
                foreach (['brand_name', 'company_name', 'alternative_names'] as $col) {
                    if (!empty($company->{$col}) && strpos($this->normalizeScriptNameMatch($company->{$col}), $name) !== false) {
                        return $company;
                    }
                }
            }
        }

        return null;
    }





    // read image to update price end 

    function import(): View
    {
        setPageTitle('Import Company');
        return view('admin.pages.company.import');
    }

    function importSave(Request $request): RedirectResponse
    {
        $validation = Validator::make($request->all(), [
            'excel'               => 'required|file|mimes:xls,xlsx|max:' . UtillsHelper::maxFileDocumentSizeInKB()
        ]);

        if ($validation->fails()) {
            return redirect()->back()->with('error', $validation->errors()->first());
        }

        $array = Excel::toArray([], $request->file('excel'));


        if (isset($array) && is_array($array) && count($array[0]) > 1) {
            foreach ($array[0] as $key => $value) {
                if ($key > 0) {
                    DB::beginTransaction();
                    try {
                        $company = new CompanyModel();
                        $company->brand_name = $value[0];
                        $company->company_name = $value[1];
                        $company->about = $value[3];
                        $company->sector_id = 0;
                        $company->slug = AdminHelper::companySlug($value[0] ?: $value[1]);
                        $company->approval_status = CompanyApprovalStatusEnum::approved->value;
                        // if ($request->hasFile('logo')) {
                        //     $company->logo = FileUpDownHelper::company_logo_upload($request->file('logo'));
                        // }
                        $company->save();
                        $companyFundamentals = new CompanyFundamentalsModel();
                        $companyFundamentals->company_id = $company->id;
                        $companyFundamentals->lot_size = $value[5];
                        $companyFundamentals->fifty_two_week_high = $value[6];
                        $companyFundamentals->fifty_two_week_low = $value[7];
                        $companyFundamentals->depository = $value[8];
                        $companyFundamentals->pan_number = $value[9];
                        $companyFundamentals->isin_number = $value[10];
                        $companyFundamentals->cin_number = $value[11];
                        $companyFundamentals->rta = $value[12];
                        $companyFundamentals->market_cap = $value[13];
                        $companyFundamentals->pe_ratio = $value[14];
                        $companyFundamentals->pb_ratio = $value[15];
                        $companyFundamentals->debt_to_equity = $value[16];
                        $companyFundamentals->roe = $value[17];
                        $companyFundamentals->book_value = $value[18];
                        $companyFundamentals->face_value = $value[19];
                        $companyFundamentals->total_shares = $value[20];
                        $companyFundamentals->save();
                        DB::commit();
                    } catch (\Exception $e) {
                        Log::debug($e->getMessage());
                        DB::rollback();
                    }
                }
            }

            return redirect()->back()->with('success', 'Data imported');
        }

        return redirect()->back()->with('error', 'File is not valid');
    }

    function list(): View
    {
        setPageTitle('Companies');
        // $data['list']   = CompanyModel::where('is_deleted', '0')->get();
        return view('admin.pages.company.list');
    }

    //     public function getCompanies(Request $request)
    // {
    //     if ($request->ajax()) {
    //         $query = CompanyModel::where('is_deleted', '0');

    //         // Create the DataTable instance first
    //         $datatable = DataTables::of($query);

    //         // Handle global search
    //         if ($request->has('search') && !empty($request->search['value'])) {
    //             $searchValue = $request->search['value'];

    //             $datatable->filter(function($query) use ($searchValue) {
    //                 $query->where(function($q) use ($searchValue) {
    //                     $q->where('brand_name', 'LIKE', "%{$searchValue}%")
    //                       ->orWhere('company_name', 'LIKE', "%{$searchValue}%");

    //                     // Handle share price search
    //                     if (is_numeric(str_replace(',', '', $searchValue))) {
    //                         $searchPrice = str_replace(',', '', $searchValue);
    //                         $q->orWhereHas('sharePrices', function($priceQuery) use ($searchPrice) {
    //                             $priceQuery->where('price', 'LIKE', "%{$searchPrice}%")
    //                                      ->orderBy('date', 'desc');
    //                         });
    //                     }
    //                 });
    //             });
    //         }

    //         // Add specific column filter for share_price
    //         $datatable->filterColumn('share_price', function($query, $keyword) {
    //             if (is_numeric(str_replace(',', '', $keyword))) {
    //                 $searchPrice = str_replace(',', '', $keyword);
    //                 $allCompanies = $query->get();
    //                 $filteredIds = $allCompanies->filter(function($company) use ($searchPrice) {
    //                     return str_contains(
    //                         (string) $company->share_price,
    //                         (string) $searchPrice
    //                     );
    //                 })->pluck('id')->toArray();

    //                 $query->whereIn('id', $filteredIds);
    //             }
    //         });

    //         return $datatable
    //             ->addColumn('logo', function ($company) {
    //                 return '<img src="' . FileUpDownHelper::get_company_logo_url($company) . '" width="50">';
    //             })
    //             ->addColumn('share_price', function ($company) {
    //                 return UtillsHelper::rupee() . UtillsHelper::moneyFormatIndia($company->share_price); // Removed number_format to keep original value
    //             })
    //                 ->addColumn('action', function ($company) {
    //                     return '<td class="text-center">
    //                                 <div class="card-toolbar">
    //                                     <button type="button"
    //                                         class="btn btn-sm btn-icon btn-color-primary btn-active-light-primary show menu-dropdown"
    //                                         data-kt-menu-trigger="click"
    //                                         data-kt-menu-placement="bottom-end">
    //                                         <i class="ki-duotone ki-category fs-6">
    //                                             <span class="path1"></span>
    //                                             <span class="path2"></span>
    //                                             <span class="path3"></span>
    //                                             <span class="path4"></span>
    //                                         </i>
    //                                     </button>
    //                                     <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg-light-primary fw-semibold w-200px"
    //                                         data-kt-menu="true" data-popper-placement="bottom-end">
    //                                         <div class="menu-item px-3">
    //                                             <div class="menu-content fs-6 text-gray-900 fw-bold px-3 py-4">Action</div>
    //                                         </div>
    //                                         <div class="separator mb-3 opacity-75"></div>
    //                                         <div class="menu-item px-3">
    //                                             <a href="' . route('admin.company.view', ['uuid' => $company->uuid]) . '" class="menu-link px-3">View</a>
    //                                         </div>
    //                                         <div class="menu-item px-3">
    //                                             <a href="' . route('admin.company.edit', ['uuid' => $company->uuid]) . '" class="menu-link px-3">Edit Details</a>
    //                                         </div>
    //                                         <div class="menu-item px-3">
    //                                             <a href="' . route('admin.company.shareHolders', ['uuid' => $company->uuid]) . '" class="menu-link px-3">Edit Share Holders</a>
    //                                         </div>
    //                                         <div class="menu-item px-3">
    //                                             <a href="' . route('admin.company.promoter', ['uuid' => $company->uuid]) . '" class="menu-link px-3">Edit Promoter</a>
    //                                         </div>
    //                                         <div class="menu-item px-3">
    //                                             <a href="' . route('admin.company.event', ['uuid' => $company->uuid]) . '" class="menu-link px-3">Edit Events</a>
    //                                         </div>
    //                                         <div class="menu-item px-3">
    //                                             <a href="' . route('admin.company.peerRatio', ['uuid' => $company->uuid]) . '" class="menu-link px-3">Edit Peer Ratio</a>
    //                                         </div>
    //                                         <div class="menu-item px-3">
    //                                             <a href="' . route('admin.company.customData', ['uuid' => $company->uuid]) . '" class="menu-link px-3">Edit Custom Data</a>
    //                                         </div>
    //                                         <div class="menu-item px-3">
    //                                             <a href="' . route('admin.company.news', ['uuid' => $company->uuid]) . '" class="menu-link px-3">Edit News</a>
    //                                         </div>
    //                                         <div class="separator mt-3 opacity-75"></div>
    //                                         <div class="menu-item px-3">
    //                                             <div class="menu-content px-3 py-3">
    //                                                 <a href="' . route('admin.company.delete', ['uuid' => $company->uuid]) . '"
    //                                                     class="btn btn-danger btn-sm px-4"
    //                                                     onclick="return confirm(\'Are you sure ?\')">
    //                                                     <i class="fas fa-trash fs-6"></i> Delete
    //                                                 </a>
    //                                             </div>
    //                                         </div>
    //                                     </div>
    //                                 </div>
    //                             </td>';
    //                 })
    //                 ->rawColumns(['logo', 'action'])
    //                 ->make(true);
    //         }

    //         return view('admin.pages.company.list');
    //     }

    public function getCompanies(CompanyDataTable $dataTable)
    {
        return $dataTable->render('admin.pages.company.list');
    }

    function pendingSellerCompanies(): View
    {
        setPageTitle('Pending Seller Companies');
        $data['list'] = CompanyModel::where('is_deleted', '0')
            ->where('approval_status', CompanyApprovalStatusEnum::pending->value)
            ->where(function ($query) {
                $query->whereNotNull('submitted_by_seller_id')
                    ->orWhereNotNull('submitted_by_partner_id');
            })
            ->with([
                'sector:id,name',
                'submittedBySeller:id,company_name,mobile_number',
                'submittedByPartner:id,name,mobile_number',
            ])
            ->orderByDesc('id')
            ->get();

        return view('admin.pages.company.pending-seller', $data);
    }

    function approveSellerCompany(string $uuid): RedirectResponse
    {
        $company = CompanyModel::where('uuid', $uuid)
            ->where('is_deleted', '0')
            ->where('approval_status', CompanyApprovalStatusEnum::pending->value)
            ->first();

        if (!$company) {
            return redirect()->back()->with('error', 'Pending company not found');
        }

        $company->approval_status = CompanyApprovalStatusEnum::approved->value;
        $company->approved_by = Auth::guard('admin')->id();
        $company->approved_at = now();
        $company->rejection_reason = null;
        $company->save();

        AdminHelper::logPut('Seller company approved', CompanyModel::class, $company->id);

        return redirect()->back()->with('success', $company->brand_name . ' approved');
    }

    function rejectSellerCompany(Request $request, string $uuid): RedirectResponse
    {
        $validation = Validator::make($request->all(), [
            'rejection_reason' => 'nullable|string|max:2000',
        ]);
        if ($validation->fails()) {
            return redirect()->back()->with('error', $validation->errors()->first());
        }

        $company = CompanyModel::where('uuid', $uuid)
            ->where('is_deleted', '0')
            ->where('approval_status', CompanyApprovalStatusEnum::pending->value)
            ->first();

        if (!$company) {
            return redirect()->back()->with('error', 'Pending company not found');
        }

        $company->approval_status = CompanyApprovalStatusEnum::rejected->value;
        $company->approved_by = Auth::guard('admin')->id();
        $company->approved_at = now();
        $company->rejection_reason = $request->input('rejection_reason');
        $company->save();

        AdminHelper::logPut('Seller company rejected', CompanyModel::class, $company->id);

        return redirect()->back()->with('success', $company->brand_name . ' rejected');
    }

    function delete($uuid): RedirectResponse
    {
        $item = CompanyModel::where('uuid', $uuid)->first();
        if ($item) {
            $item->is_deleted = '1';
            $item->save();
            return redirect()->back()->with('success',  $item->brand_name . ' Deleted');
        }
        return redirect()->back()->with('error', 'Item not found');
    }

    public function exportView()
    {
        setPageTitle('Companies Report');
        $data['categories'] = PreIpoCategoryEnum::cases();
        return view('admin.pages.company.export.view', $data);
    }

    public function exportPdf(Request $request)
    {
        $request->validate([
            'category' => 'required'
        ]);

        $companies = $this->getCompaniesByCategory($request->category);
        $pdf = $this->generateCompanyPdf($companies, $request->category);

        $date = now()->format('d-M-Y');
        $categoryName = ucfirst(str_replace('_', '-', $request->category));

        return $pdf->download("companies-{$categoryName}-{$date}.pdf");
    }

    public function sendToPartner(Request $request)
    {
        $request->validate([
            'category' => 'required'
        ]);

        return $this->sendPdfViaWhatsApp($request, false); // false = not demo
    }

    public function sendToDemo(Request $request)
    {
        $request->validate([
            'category' => 'required'
        ]);

        return $this->sendPdfViaWhatsApp($request, true); // true = demo
    }

    private function sendPdfViaWhatsApp($request, $isDemo = false)
    {
        try {
            $companies = $this->getCompaniesByCategory($request->category);

            $pdf = $this->generateCompanyPdf($companies, $request->category);

            $filename = "companies-{$request->category}-" . now()->format('Y-m-d') . ".pdf";

            $pdfContent = $pdf->output();
            $tempFile = tmpfile();
            fwrite($tempFile, $pdfContent);
            $tempPath = stream_get_meta_data($tempFile)['uri'];
            $uploadedFile = new UploadedFile(
                $tempPath,
                $filename,
                'application/pdf',
                null,
                true
            );

            $headerFile = FileUpDownHelper::company_exported_category_upload($uploadedFile);

            // Get hidden partner IDs
            $hiddenPartnerIds = UtillsHelper::hiddenPartnerIds();

            if ($isDemo) {
                $partners = PartnerModel::where('is_demo', 1)
                    ->where('is_deleted', 0)
                    ->whereNotIn('id', $hiddenPartnerIds)
                    ->get();

                if ($partners->isEmpty()) {
                    fclose($tempFile);
                    return back()->with('error', 'No demo partners found');
                }
            } else {
                $partners = PartnerModel::where('is_demo', 0)
                    ->where('is_deleted', 0)
                    ->where('is_preipo_access', 1)
                    ->whereNotIn('id', $hiddenPartnerIds)
                    ->get();

                if ($partners->isEmpty()) {
                    fclose($tempFile);
                    return back()->with('error', 'No partners with pre-IPO access found');
                }
            }

            $partnerIds = $partners->pluck('id')->toArray();

            $variables = [
                ['type' => 'name', 'value' => null],
                ['type' => 'custom', 'value' => now()->format('d M Y')]
            ];

            $broadcast = new WhatsappBroadcastModel();
            $broadcast->template_id   = 'company_share_price_report_send_to_partners_and_demo';
            $broadcast->template_name = 'company_share_price_report_send_to_partners_and_demo';
            $broadcast->header_file   = $headerFile;
            $broadcast->investors_ids = json_encode([]);
            $broadcast->partners_ids  = json_encode($partnerIds);
            $broadcast->guest_data    = json_encode([]);
            $broadcast->variables     = json_encode($variables);
            $broadcast->created_by    = AdminHelper::getAdmin()->id;
            $broadcast->updated_by    = AdminHelper::getAdmin()->id;
            $broadcast->save();

            AdminHelper::logPut('Company PDF report broadcast created', WhatsappBroadcastModel::class, $broadcast->id);
            Whatsapp::dispatch($broadcast->id);

            fclose($tempFile);

            $partnerType = $isDemo ? 'demo partners' : 'partners';
            $partnerCount = $partners->count();

            return back()->with('success', "PDF queued for sending to {$partnerCount} {$partnerType} via WhatsApp! Check the WhatsApp Broadcast List for status.");
        } catch (Exception $e) {
            if (isset($tempFile)) {
                fclose($tempFile);
            }
            Log::error('Failed to queue PDF WhatsApp sending: ' . $e->getMessage());
            return back()->with('error', 'Failed to queue PDF for WhatsApp sending: ' . $e->getMessage());
        }
    }

    private function getCompaniesByCategory($category)
    {
        $query = CompanyModel::where('is_deleted', 0);

        if ($category === PreIpoCategoryEnum::trending->value) {
            $query->where('is_trending', 1);
        } elseif ($category === 'exclusive_liquid') {
            $query->whereIn('category', [
                PreIpoCategoryEnum::exclusive_deals->value,
                PreIpoCategoryEnum::liquid_stocks->value
            ]);
        } else {
            $query->where('category', $category);
        }

        return $query->select('id', 'brand_name', 'logo', 'share_price', 'distributer_price', 'category')
            ->with(['sharePrices' => function ($q) {
                $q->orderByDesc('date');
            }])
            ->orderBy('brand_name', 'asc')
            ->get();
    }

    private function generateCompanyPdf($companies, $category)
    {
        return Pdf::setOptions([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isFontSubsettingEnabled' => true,
            'defaultFont' => 'Arial'
        ])->loadView('admin.pages.company.export.pdf', [
            'companies' => $companies,
            'category'  => $category
        ]);
    }

    public function getCompaniesForVerification(Request $request)
    {
        $request->validate([
            'category' => 'required'
        ]);

        $companies = $this->getCompaniesByCategory($request->category);

        $companiesList = [];

        if ($request->category === 'exclusive_liquid') {
            // For exclusive_liquid, show both categories
            $exclusiveCompanies = $companies->where('category', 'Exclusive Deals');
            $liquidCompanies = $companies->where('category', 'Liquid Stocks');

            // Exclusive Deals use latest_price
            foreach ($exclusiveCompanies as $company) {
                $companiesList[] = [
                    'name' => $company->brand_name,
                    'price' => $company->latest_price,
                    'category' => 'Exclusive Deals'
                ];
            }

            // Liquid Stocks use latest_base_price
            foreach ($liquidCompanies as $company) {
                $companiesList[] = [
                    'name' => $company->brand_name,
                    'price' => $company->latest_base_price,
                    'category' => 'Liquid Stocks'
                ];
            }
        } else {
            // For single category
            $isExclusiveDeal = ($request->category === 'exclusive_deals');

            foreach ($companies as $company) {
                $companiesList[] = [
                    'name' => $company->brand_name,
                    'price' => $isExclusiveDeal ? $company->latest_price : $company->latest_base_price,
                    'category' => $request->category
                ];
            }
        }

        return response()->json([
            'companies' => $companiesList
        ]);
    }

    public function applySplit(Request $request, $id)
    {
        $request->validate([
            'split_ratio' => 'required|string|regex:/^\d+:\d+$/',
            'split_date' => 'required|date'
        ]);

        $company = CompanyModel::findOrFail($id);

        // Check if company already has an active split
        if ($company->has_active_split) {
            return response()->json([
                'success' => false,
                'message' => 'Company already has an active split. Please revert the current split before applying a new one.'
            ], 400);
        }

        try {
            $result = $company->applySplit($request->split_ratio, $request->split_date);

            return response()->json([
                'success' => true,
                'message' => "Split applied successfully! {$result['affected_portfolios']} investor portfolios updated.",
                'affected_portfolios' => $result['affected_portfolios']
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error applying split: ' . $e->getMessage()
            ], 500);
        }
    }

    public function revertSplit($id)
    {
        $company = CompanyModel::findOrFail($id);

        try {
            $company->revertLastSplit();

            return response()->json([
                'success' => true,
                'message' => 'Split reverted successfully! All portfolios restored to previous state.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reverting split: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getSplitHistory($id)
    {
        $company = CompanyModel::findOrFail($id);

        $history = BonusHistoryModel::where('company_id', $id)
            ->orderBy('applied_at', 'desc')
            ->get()
            ->map(function ($bonus) {
                return [
                    'id' => $bonus->id,
                    'split_ratio' => $bonus->split_ratio,
                    'split_date' => $bonus->split_date->format('Y-m-d'),
                    'multiplier' => $bonus->multiplier,
                    'before_split' => $bonus->before_split,
                    'after_split' => $bonus->after_split,
                    'affected_portfolios' => $bonus->affected_portfolios,
                    'status' => $bonus->status,
                    'applied_at' => $bonus->applied_at->toDateTimeString(),
                    'reverted_at' => $bonus->reverted_at?->toDateTimeString()
                ];
            });

        return response()->json([
            'history' => $history,
            'current_split' => $company->current_split_ratio,
            'has_active_split' => $company->has_active_split
        ]);
    }

    /**
     * Save or update grab opportunity slots for a company
     */
    private function saveGrabOpportunitySlots($companyId, Request $request): void
    {
        // Delete existing slots
        CompanyGrabOpportunitySlotModel::where('company_id', $companyId)->delete();

        // Only save slots if grab opportunity is enabled
        if ($request->has('is_grab_opportunity_enabled') && $request->input('is_grab_opportunity_enabled')) {
            // Slot 1: 15000 - 50000
            if ($request->has('grab_slot_1_percentage')) {
                CompanyGrabOpportunitySlotModel::create([
                    'company_id' => $companyId,
                    'slot_number' => 1,
                    'min_amount' => 15000,
                    'max_amount' => 50000,
                    'percentage' => $request->input('grab_slot_1_percentage', 0)
                ]);
            }

            // Slot 2: 50000 - 100000
            if ($request->has('grab_slot_2_percentage')) {
                CompanyGrabOpportunitySlotModel::create([
                    'company_id' => $companyId,
                    'slot_number' => 2,
                    'min_amount' => 50000,
                    'max_amount' => 100000,
                    'percentage' => $request->input('grab_slot_2_percentage', 1)
                ]);
            }

            // Slot 3: 100000 and above
            if ($request->has('grab_slot_3_percentage')) {
                CompanyGrabOpportunitySlotModel::create([
                    'company_id' => $companyId,
                    'slot_number' => 3,
                    'min_amount' => 100000,
                    'max_amount' => null,
                    'percentage' => $request->input('grab_slot_3_percentage', 2)
                ]);
            }
        }
    }
}
