<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\DocumentTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\Utills\StatusEnum;
use App\Enums\WpMessageTypeEnum;
use App\Exports\InvestorExport;
use App\Helpers\AdminHelper;
use App\Helpers\CommonHelper;
use App\Helpers\DateTimeHelper;
use App\Helpers\FileUpDownHelper;
use App\Helpers\UtillsHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\InvestorRequest;
use App\Jobs\aif\Onboard;
use App\Models\ApiLogModel;
use App\Models\BankDetailsModel;
use App\Models\CompanyModel;
use App\Models\CoreFirebaseDeviceTokenModel;
use App\Models\DematManualModel;
use App\Models\DocumentsModel;
use App\Models\InvestorAifKycModel;
use App\Models\InvestorDematAccountModel;
use App\Models\InvestorDetailsModel;
use App\Models\InvestorKycAadharModel;
use App\Models\InvestorKycModel;
use App\Models\InvestorKycPanModel;
use App\Models\InvestorModel;
use App\Models\InvestorPanDetailsModel;
use App\Models\MasterCityModel;
use App\Models\MasterFamilyRelationsModel;
use App\Models\MasterInvestorTypeModel;
use App\Models\MasterSupportedCountriesModel;
use App\Models\PartnerModel;
use App\Models\StartupModel;
use App\Models\TempAadharPanDetailsModel;
use App\Models\UserAdminModel;
use App\Models\UserBankAccountModel;
use App\Repositories\InvestorRepository;
use App\Services\DematKycService;
use App\Services\DematPdfParsingService;
use App\Traits\FileUploadTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Yajra\DataTables\Facades\DataTables;

class InvestorController extends Controller
{

    private $invRepo;
    use FileUploadTrait;

    function __construct(InvestorRepository $investorRepository)
    {
        $this->invRepo = $investorRepository;
    }

    function editKYCDetails(string $uuid): JsonResponse
    {
        $investor_details = InvestorModel::where('is_deleted', '0')->where('uuid', $uuid)->with(['kyc', 'dematAccount'])->first();
        // $investor  = InvestorModel::find($id);
        if (!$investor_details) {
            return UtillsHelper::json(0, ['message' => 'Investor not found']);
        }

        return UtillsHelper::json(1, [
            'investor' => $investor_details,
            'kyc' => [
                'aadhaar_front_image' => FileUpDownHelper::get_aadhar_photo_url($investor_details->kyc->aadhaar_front_image ?? null),
                'aadhaar_back_image' => FileUpDownHelper::get_aadhar_photo_url($investor_details->kyc->aadhaar_back_image ?? null),
                'pan_image' => FileUpDownHelper::get_aadhar_photo_url($investor_details->kyc->pan_image ?? null),
                'cml_image' => FileUpDownHelper::get_aadhar_photo_url($investor_details->kyc->cml_image ?? null),
                'cheque_image' => FileUpDownHelper::get_aadhar_photo_url($investor_details->kyc->cheque_image ?? null),
            ]
        ]);
    }

    public function updateKYCDetails(Request $request): JsonResponse
    {
        $rules = [
            'aadhar_no' => 'required|digits:12',
            'name_as_aadhar' => 'required|string|max:255',
            'pan_no' => 'required|string|size:10',
            'name_as_pan' => 'required|string|max:255',
            'country_id' => 'required',
            'state_id' => 'required',
            'city_id' => 'required',
            'pincode' => 'required|digits:6',
            'address_as_aadhar' => 'required|string|max:500',
            'dob_as_aadhar' => 'required|date|before:today',
            'dp_id' => 'required|string|max:50',
            'client_id' => 'required|string|max:50',
            'demat_account' => 'required|string|max:50',
        ];
        $rules['uuid'] = ['required', 'exists:' . InvestorModel::class . ',uuid'];
        $rules['aadhaar_front_image'] = ['nullable', 'mimes:jpg,png,jpeg', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
        $rules['aadhaar_back_image'] = ['nullable', 'mimes:jpg,png,jpeg', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
        $rules['pan_image'] = ['nullable', 'mimes:jpg,png,jpeg', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
        $rules['cheque_image'] = ['nullable', 'mimes:jpg,png,jpeg', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
        $rules['cml_image'] = ['nullable', 'mimes:jpg,png,jpeg', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return UtillsHelper::json(0, ['errors' => $validator->errors()], 422);
        }

        $investor = InvestorModel::where('is_deleted', '0')->where('uuid', $request->uuid)->first();

        if (!$investor) {
            return UtillsHelper::json(0, ['message' => 'Investor not found']);
        }

        $kyc = InvestorKycModel::where('investor_id', $investor->id)->first();
        if (!$kyc) {
            return UtillsHelper::json(0, ['message' => 'KYC details not found']);
        }

        // Update KYC details
        if ($request->hasFile('aadhaar_front_image')) {
            $kyc->aadhaar_front_image = FileUpDownHelper::uploadInvestorDoc($request->file('aadhaar_front_image'));
        }

        if ($request->hasFile('aadhaar_back_image')) {
            $kyc->aadhaar_back_image = FileUpDownHelper::uploadInvestorDoc($request->file('aadhaar_back_image'));
        }

        if ($request->hasFile('pan_image')) {
            $kyc->pan_image = FileUpDownHelper::uploadInvestorDoc($request->file('pan_image'));
        }

        if ($request->hasFile('cheque_image')) {
            $kyc->cheque_image_image = FileUpDownHelper::uploadInvestorDoc($request->file('cheque_image'));
        }

        if ($request->hasFile('cml_image')) {
            $kyc->cml_image_image = FileUpDownHelper::uploadInvestorDoc($request->file('cml_image'));
        }

        $kyc->aadhar_no = $request->aadhar_no;
        $kyc->pan_no = $request->pan_no;
        $kyc->name_as_aadhar = $request->name_as_aadhar;
        $kyc->name_as_pan = $request->name_as_pan;
        $kyc->dob_as_aadhar = DateTimeHelper::formatDateTime($request->dob_as_aadhar, 'Y-m-d');
        $kyc->address_as_aadhar = $request->address_as_aadhar;
        $kyc->updated_by = AdminHelper::getAdmin()->id;
        $kyc->save();

        // Update Investor details
        $investor->update([
            'name' => $request->name_as_aadhar,
            'address' => $request->address,
            'city_id' => $request->city_id,
            'state_id' => $request->state_id,
            'country_id' => $request->country_id,
        ]);

        // Update Demat account details
        $demat = InvestorDematAccountModel::where('investor_id', $investor->id)->first();
        if ($demat) {
            $demat->update([
                'dp_id' => $request->dp_id,
                'client_id' => $request->client_id,
                'demat_account' => $request->demat_account,
            ]);
        }

        $investor = InvestorModel::where('is_deleted', '0')->where('uuid', $request->uuid)->with(['kyc', 'dematAccount'])->first();
        if ($request->has('uuid')) {
            return UtillsHelper::json(1, [
                'message' => 'Investor KYC updated successfully',
                'table' => view('admin.pages.investor.child.kyc-data', ['investor' => $investor])->render()
            ], 200);
        }

        return UtillsHelper::json(0, ['errors' => 'Investor Not Found.']);
    }
    function aifUpload(string $uuid = ''): View|RedirectResponse
    {
        $request = request();
        if ($request->isMethod('post')) {
            $rules['uuid'] = ['required', 'exists:' . InvestorModel::class . ',uuid'];
            $rules['ppm'] = ['required', 'mimes:pdf', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
            $rules['ca'] = ['required', 'mimes:pdf', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors($validator)
                    ->with('error', 'Check form errors');
            }

            $investor = InvestorModel::where('is_deleted', '0')->where('uuid', $request->uuid)->first();
            if ($investor) {
                InvestorAifKycModel::where('investor_id', $investor->id)->update([
                    'status' => 1
                ]);


                $aif = new InvestorAifKycModel();
                $aif->investor_id = $investor->id;
                $aif->status = 3;
                $aif->created_by = AdminHelper::getAdmin()->id;
                $aif->updated_by = AdminHelper::getAdmin()->id;
                $aif->save();

                if ($request->hasFile('ppm')) {
                    $meta = [
                        'aif_kyc' => [
                            $aif->id
                        ]
                    ];
                    $meta['name']   = DocumentTypeEnum::ppm->value;
                    $meta['aname']   = DocumentTypeEnum::ppm->value . ' of ' . $aif->investor->name;
                    $file = FileUpDownHelper::uploadInvestorDoc($request->file('ppm'));
                    if ($file) {
                        $document = new DocumentsModel();
                        $document->api_id = NULL;
                        $document->path = $file;
                        $document->signed_path = $file;
                        $document->status = 1;
                        $document->type = DocumentTypeEnum::ppm;
                        $document->meta = $meta;
                        $document->save();
                    }
                }

                if ($request->hasFile('ca')) {
                    $meta = [
                        'aif_kyc' => [
                            $aif->id
                        ]
                    ];
                    $meta['name']   = DocumentTypeEnum::ca->value;
                    $meta['aname']   = DocumentTypeEnum::ca->value . ' of ' . $aif->investor->name;
                    $file = FileUpDownHelper::uploadInvestorDoc($request->file('ca'));
                    if ($file) {
                        $document = new DocumentsModel();
                        $document->api_id = NULL;
                        $document->path = $file;
                        $document->signed_path = $file;
                        $document->status = 1;
                        $document->type = DocumentTypeEnum::ca;
                        $document->meta = $meta;
                        $document->save();
                    }
                }
                InvestorModel::where('id', $aif->investor_id)->update([
                    'aif_status'            => '1',
                    'updated_by'            => AdminHelper::getAdmin()->id
                ]);
                AdminHelper::logPut('AIF Manual upload', InvestorModel::class, $aif->investor_id);
                return redirect()->route('admin.investor.inactive')->with('success', 'AIF Uploaded.');
            }
            return redirect()->back()
                ->withInput()
                ->withErrors($validator)
                ->with('error', 'Item not found');
        } else {
            $item = InvestorModel::where('is_deleted', '0')->where('uuid', $uuid)->first();
            if ($item) {
                setPageTitle($item->name . "'s AIF Upload");
                $data['item'] = $item;
                return view('admin.pages.investor.aif-onboard.create')->with($data);
            }
            return redirect()->route('admin.investor.active')->with('error', 'Item not found');
        }
    }

    function kycUpload(string $uuid = ''): View|RedirectResponse
    {
        $request = request();
        if ($request->isMethod('post')) {
            $rules = [
                'aadhar_number'     => 'required|digits:12',
                'name_as_aadhar'    => 'required|string|max:255',
                'pan_number'        => 'required|string|size:10',
                'name_as_pan'       => 'required|string|max:255',
                'country_id'        => 'required',
                'state_id'          => 'required',
                'city_id'           => 'required',
                'pincode'           => 'required|digits:6',
                'address'           => 'required|string|max:500',
                'date_of_birth'     => 'required|date|before:today',
                'dp_id'             => 'required|string|max:50',
                'client_id'         => 'required|string|max:50',
                'demat_account'     => 'required|string|max:50',
            ];
            $rules['uuid'] = ['required', 'exists:' . InvestorModel::class . ',uuid'];
            $rules['aadhar_front'] = ['required', 'mimes:jpg,png,jpeg', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
            $rules['aadhar_back'] = ['required', 'mimes:jpg,png,jpeg', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
            $rules['pan_card'] = ['required', 'mimes:jpg,png,jpeg', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
            $rules['cheque'] = ['required', 'mimes:jpg,png,jpeg', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
            $rules['cml'] = ['required', 'mimes:jpg,png,jpeg', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
            $validator = Validator::make($request->all(), $rules, [], [
                'country_id' => 'Country',
                'state_id' => 'State',
                'city_id' => 'City',
            ]);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors($validator)
                    ->with('error', 'Check form errors');
            }

            $investor = InvestorModel::where('is_deleted', '0')->where('uuid', $request->uuid)->first();
            if ($investor) {
                InvestorKycModel::where('investor_id', $investor->id)->update([
                    'status' => StatusEnum::rejected
                ]);


                $kyc = new InvestorKycModel();
                $kyc->investor_id = $investor->id;
                if ($request->hasFile('aadhar_front')) {
                    $image = FileUpDownHelper::uploadInvestorDoc($request->file('aadhar_front'));
                    if ($image) {
                        $kyc->aadhaar_front_image = $image;
                    }
                }

                if ($request->hasFile('aadhar_back')) {
                    $image = FileUpDownHelper::uploadInvestorDoc($request->file('aadhar_back'));
                    if ($image) {
                        $kyc->aadhaar_back_image = $image;
                    }
                }

                if ($request->hasFile('pan_card')) {
                    $image = FileUpDownHelper::uploadInvestorDoc($request->file('pan_card'));
                    if ($image) {
                        $kyc->pan_image = $image;
                    }
                }

                if ($request->hasFile('cheque')) {
                    $image = FileUpDownHelper::uploadInvestorDoc($request->file('cheque'));
                    if ($image) {
                        $kyc->cheque_image = $image;
                    }
                }

                if ($request->hasFile('cml')) {
                    $image = FileUpDownHelper::uploadInvestorDoc($request->file('cml'));
                    if ($image) {
                        $kyc->cml_image = $image;
                    }
                }

                $kyc->status = StatusEnum::approved;
                $kyc->aadhar_no = $request->aadhar_number;
                $kyc->pan_no = strtoupper($request->pan_number);
                $kyc->name_as_aadhar = $request->name_as_aadhar;
                $kyc->name_as_pan = $request->name_as_pan;
                $kyc->dob_as_aadhar = DateTimeHelper::formatDateTime($request->date_of_birth, 'Y-m-d');
                $kyc->address_as_aadhar = $request->address;
                // $kyc->notes = $request->notes;
                $kyc->created_by = AdminHelper::getAdmin()->id;
                $kyc->updated_by = AdminHelper::getAdmin()->id;
                $kyc->save();
                InvestorModel::where('id', $kyc->investor_id)->update([
                    'name'                  => $request->name_as_aadhar,
                    'kyc_status'            => '1',
                    'address'               => $request->address,
                    'city_id'               => $request->city_id,
                    'state_id'              => $request->state_id,
                    'country_id'            => $request->country_id,
                    'aadhar_verified_type'  => 'Manual',
                    'updated_by'            => AdminHelper::getAdmin()->id
                ]);

                $details = InvestorDetailsModel::where('investor_id', $kyc->investor_id)->first();
                if (!$details) {
                    $details = new InvestorDetailsModel;
                    $details->investor_id = $kyc->investor_id;
                }
                $details->date_of_birth = DateTimeHelper::formatDateTime($request->dob, 'Y-m-d');
                $details->save();


                $demat = InvestorDematAccountModel::where('investor_id', $kyc->investor_id)->first();
                if (!$demat) {
                    $demat = new InvestorDematAccountModel;
                    $demat->investor_id = $kyc->investor_id;
                }

                $demat->dp_id = $request->dp_id;
                $demat->client_id = $request->client_id;
                $demat->demat_account = $request->demat_account;
                $demat->save();
                AdminHelper::logPut('Manual KYC Upload, Demat account update', InvestorModel::class, $kyc->investor_id);
                return redirect()->route('admin.investor.pendingkyc')->with('success', 'KYC record approved.');
            }
            return redirect()->back()
                ->withInput()
                ->withErrors($validator)
                ->with('error', 'Item not found');
        } else {
            $item = InvestorModel::where('is_deleted', '0')->where('uuid', $uuid)->first();
            if ($item) {
                setPageTitle($item->name . "'s KYC Upload");
                $data['item'] = $item;
                return view('admin.pages.investor.manual-kyc.create')->with($data);
            }
            return redirect()->route('admin.investor.active')->with('error', 'Item not found');
        }
    }

    function manager(Request $request): RedirectResponse
    {
        $validation = Validator::make($request->all(), [
            'investor_id' => 'required',
            'manager_id' => 'required'
        ]);

        if ($validation->fails()) {
            return redirect()->back()->withInput()->withErrors($validation)
                ->with('error', $validation->errors()->first());
        }

        // dd($request->all());

        $investor = InvestorModel::where('id', $request->investor_id)->first();
        if (!$investor) {
            return redirect()->back()->withInput()
                ->with('error', 'Investor not found');
        }

        $investor->created_by = $request->manager_id;
        $investor->save();

        return redirect()->back()->with('success', 'Manager Updated');
    }

    function create(): View
    {
        setPageTitle('Create Investor');
        $data['partners'] = PartnerModel::where('is_deleted', '0')->orderby('name', 'asc')->select('name', 'id', 'type')->get();
        $data['investors'] = InvestorModel::where('is_deleted', '0')->orderby('name', 'asc')->select('name', 'id')->get();
        $data['relations'] = MasterFamilyRelationsModel::where('is_deleted', '0')->orderby('name', 'asc')->get();
        $data['cities'] = MasterCityModel::where('is_deleted', '0')->orderby('name', 'asc')->get();
        $data['supposted_countries'] = MasterSupportedCountriesModel::where('is_deleted', '0')->orderby('name', 'asc')->get();

        $data['from_markasread'] = request()->has('from_markasread');
        return view('admin.pages.investor.create')->with($data);
    }

    function store(): RedirectResponse
    {
        return $this->invRepo->investorSave();
    }

    public function edit(string $uuid): View|RedirectResponse
    {
        $item = InvestorModel::where('is_deleted', '0')->where('uuid', $uuid)->first();
        $data['cities'] = MasterCityModel::where('is_deleted', '0')->orderby('id', 'desc')->get();
        if ($item) {
            setPageTitle('Edit Investor');
            $data['item'] = $item;
            $data['partners'] = PartnerModel::where('is_deleted', '0')->orderby('name', 'asc')->select('name', 'id', 'type')->get();
            $data['investors'] = InvestorModel::where('is_deleted', '0')->orderby('name', 'asc')->select('name', 'id')->get();
            $data['relations'] = MasterFamilyRelationsModel::where('is_deleted', '0')->orderby('name', 'asc')->get();
            $data['list'] = InvestorModel::where('is_deleted', '0')->orderby('id', 'desc')->get();
            $data['supposted_countries'] = MasterSupportedCountriesModel::where('is_deleted', '0')->orderby('name', 'asc')->get();
            return view('admin.pages.investor.edit')->with($data);
        }
        return redirect()->route('admin.investor.active')->with('error', 'Item not found');
    }

    function update(): RedirectResponse
    {
        return $this->invRepo->investorSave();
    }

    public function delete(string $id): JsonResponse
    {
        $item = InvestorModel::where('is_deleted', '0')->find($id);

        if ($item) {
            // $this->deleteFile($item->profile_photo);
            $item->is_deleted = '1';
            $item->updated_by = AdminHelper::getAdmin()->id;
            $item->update();

            CommonHelper::deleteInvestors($item);

            AdminHelper::logPut('Deleted Investor', InvestorModel::class, $item->id);

            return response()->json(['success' => true, 'message' => 'Investor deleted successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'Investor not found.'], 404);
    }
    function list(Request $request): View
    {
        $query = InvestorModel::where('is_deleted', '0')->where('is_self', 0);
        if ($request->routeIs('admin.investor.active')) {
            setPageTitle('Active Investors');
            $query = $query->where('is_demo', '0')->where('is_blocked', '0')->where('is_deleted', '0')->where('preipo_kyc_status', '1')->where('is_active', '1')->orderby('updated_at', 'desc');
        }
        if ($request->routeIs('admin.investor.inactive')) {
            setPageTitle('In Active Investors');
            $query = $query->where('is_demo', '0')->where('is_blocked', '0')->where('is_deleted', '0')->where('preipo_kyc_status', '1')->where('is_active', '0')->orderby('updated_at', 'desc');
        }
        if ($request->routeIs('admin.investor.rejected')) {
            setPageTitle('Rejected Investors');
            $query = $query->where('is_demo', '0')->where('is_blocked', '0')->where('is_deleted', '0')->where('is_active', '2')->orderby('updated_at', 'desc');
        }
        if ($request->routeIs('admin.investor.pendingkyc')) {
            setPageTitle('KYC Pending Investors');
            $query = $query->where('is_demo', '0')->where('is_blocked', '0')->where('is_deleted', '0')->where('preipo_kyc_status', '0')->orderby('updated_at', 'desc');
        }
        if ($request->routeIs('admin.investor.demo')) {
            setPageTitle('Demo Investors');
            $query = $query->where('is_demo', '1')->orderby('updated_at', 'desc');
        }
        if ($request->routeIs('admin.investor.block')) {
            setPageTitle('Block Investors');
            $query = $query->where('is_blocked', '1')->where('is_deleted', '0')->orderby('updated_at', 'desc');
        }

        // if (AdminHelper::getAdmin()->role != 'admin') {
        //     $query->where('created_by', AdminHelper::getAdmin()->id)->orwhere('created_by', NULL);
        // }
        // if (AdminHelper::getAdmin()->role != 'admin') {
        //     $query->where(function ($q) {
        //         $q->where('created_by', AdminHelper::getAdmin()->id)
        //             ->orWhereNull('created_by');
        //     });
        // }

        if (AdminHelper::getAdmin()->role == 'admin') {
            $data['managers'] = UserAdminModel::where('is_deleted', '0')->get();
        }
        $data['list'] = $query->get();
        addVendor('datatables');
        return view('admin.pages.investor.list')->with($data);
    }

    function filteredList(Request $request)
    {
        setPageTitle('Filtered Investors');
        $startups = StartupModel::where('is_deleted', 0)->get();
        $companies = CompanyModel::where('is_deleted', 0)->get();
        if ($request->ajax()) {
            $query = InvestorModel::with([
                'partner',
                'referredByInvestor'
            ])
                ->where('is_deleted', '0')
                ->where('is_self', 0);
            // ->where('is_demo', '0')
            // ->where('is_blocked', '0')
            // ->where('preipo_kyc_status', '1')
            // ->where('is_active', '1');

            if ($request->filled('demo_filter')) {
                $query->where('is_demo', $request->demo_filter);
            }

            if ($request->filled('status')) {
                $query->where('is_active', $request->status == 'active' ? 1 : 0);
            }

            if ($request->filled('partner')) {
                if ($request->partner === 'with') {
                    $query->whereNotNull('partner_id');
                } elseif ($request->partner === 'without') {
                    $query->whereNull('partner_id');
                }
            }

            if ($request->filled('kyc')) {
                if ($request->kyc == 'without') {
                    $query->where('primary_kyc_status', '0')
                        ->where('preipo_kyc_status', '0');
                } elseif ($request->kyc == 'swith') {
                    $query->where('primary_kyc_status', '1');
                } elseif ($request->kyc == 'pwith') {
                    $query->where('preipo_kyc_status', '1');
                } elseif ($request->kyc == 'awith') {
                    $query->where('primary_kyc_status', '1')
                        ->where('preipo_kyc_status', '1');
                }
            }
            if ($request->filled('access')) {
                if ($request->access == 'primary') {
                    $query->where('is_primary_access', 1);
                } elseif ($request->access == 'secondary') {
                    $query->where('is_secondary_access', 1);
                } elseif ($request->access == 'preipo') {
                    $query->where('is_preipo_access', 1);
                }
            }
            if ($request->filled('date_filter')) {
                if ($request->date_filter == 'after_june_9') {
                    $query->whereDate('created_at', '>=', '2025-06-09')
                        ->where('created_by', NULL);
                } elseif ($request->date_filter == 'before_june_9') {
                    $query->where(function ($q) {
                        $q->whereDate('created_at', '<=', '2025-06-09')
                            ->orWhereNotNull('created_by');
                    });
                }
            }
            if ($request->filled('startup_filter')) {
                $query->whereHas('primaryTransactions', function ($q) use ($request) {
                    $q->where('startup_id', $request->startup_filter)
                        ->where('status', '10');
                });
            }

            if ($request->filled('preipo_filter')) {
                $query->whereHas('perIpoTransactions', function ($q) use ($request) {
                    $q->where('company_id', $request->preipo_filter)
                        ->where('status', '5');
                });
            }
            if ($request->filled('active_filter')) {
                $demoInvestorIds = InvestorModel::where('is_demo', 1)->pluck('id')->toArray();

                $activeUserIdsQuery = ApiLogModel::where('usertype', 'investor')
                    ->whereNotIn('userid', $demoInvestorIds)
                    ->whereNotNull('userid')
                    ->where('userid', '!=', '0');

                if ($request->active_filter == 'today') {
                    $activeUserIds = $activeUserIdsQuery
                        ->whereDate('created_at', Carbon::today())
                        ->distinct()
                        ->pluck('userid')
                        ->toArray();

                    $query->whereIn('id', $activeUserIds);
                } elseif ($request->active_filter == 'overall') {
                    $activeUserIds = $activeUserIdsQuery
                        ->distinct()
                        ->pluck('userid')
                        ->toArray();

                    $query->whereIn('id', $activeUserIds);
                } elseif ($request->active_filter == 'inactive') {
                    $activeUserIds = $activeUserIdsQuery
                        ->distinct()
                        ->pluck('userid')
                        ->toArray();

                    $query->whereNotIn('id', $activeUserIds);
                }
            }

            if ($request->filled('active_filter') && $request->active_filter === 'top150') {
                $demoInvestorIds = InvestorModel::where('is_demo', 1)->pluck('id')->toArray();

                $topIds = ApiLogModel::where('usertype', 'investor')
                    ->whereNotNull('userid')
                    ->where('userid', '!=', '0')
                    ->whereNotIn('userid', $demoInvestorIds)
                    ->where('useragent', 'NOT LIKE', '%PostmanRuntime%')
                    ->select('userid', DB::raw('COUNT(*) as activity_count'))
                    ->groupBy('userid')
                    ->orderByDesc('activity_count')
                    ->limit(150)
                    ->pluck('userid')
                    ->toArray();

                $query->whereIn('id', $topIds);
            }

            try {

                return DataTables::of($query)
                    ->editColumn('created_at', function ($row) {
                        return $row->created_at->format('d M Y, h:i A');
                    })
                    ->addColumn('name', fn($row) => $row->name)
                    ->addColumn('partner_name', function ($row) {

                        $partner = $row->partner?->name;
                        $referral = $row->referredByInvestor?->name;

                        $output = [];

                        if ($partner) {
                            $output[] = '<strong>Partner:</strong> ' . e($partner);
                        }

                        if ($referral) {
                            $output[] = '<strong>Referral:</strong> ' . e($referral);
                        }

                        return !empty($output)
                            ? implode('<br>', $output)
                            : '—';
                    })
                    ->addColumn('action', function ($row) {
                        return view('admin.pages.investor.partials.actions', compact('row'))->render();
                    })
                    ->addColumn('mobile_number', fn($row) => $row->mobile_number)
                    ->addColumn('status', function ($row) {
                        $badges = [];

                        if ($row->is_demo == 1) {
                            $badges[] = '<span class="badge badge-light-warning">Demo</span>';
                        }

                        if ($row->is_blocked == 1) {
                            $badges[] = '<span class="badge badge-light-danger">Blocked</span>';
                        }

                        if ($row->is_deleted == 1) {
                            $badges[] = '<span class="badge badge-light-danger">Deleted</span>';
                        }

                        if ($row->is_demo == 0 && $row->is_blocked == 0) {
                            if ($row->primary_kyc_status == 0 && $row->preipo_kyc_status == 0) {
                                $badges[] = '<span class="badge badge-light-info">Pending KYC</span>';
                            }

                            if ($row->is_active == 1 && $row->preipo_kyc_status == 1) {
                                $badges[] = '<span class="badge badge-light-success">Active</span>';
                            } elseif ($row->is_active == 0 && $row->preipo_kyc_status == 1) {
                                $badges[] = '<span class="badge badge-light-secondary">Inactive</span>';
                            } elseif ($row->is_active == 2) {
                                $badges[] = '<span class="badge badge-light-danger">Rejected</span>';
                            }
                        }

                        return implode(' ', $badges) ?: '<span class="badge badge-light-secondary">—</span>';
                    })
                    ->rawColumns(['action', 'status', 'partner_name'])  // make sure 'status' is added here
                    // ->addColumn('email', fn($row) => $row->email)
                    ->filter(function ($query) use ($request) {
                        if ($request->has('search') && !empty($request->search['value'])) {
                            $search = $request->search['value'];
                            $query->where(function ($q) use ($search) {
                                $q->where('name', 'LIKE', "%{$search}%")
                                    // ->orWhere('email', 'LIKE', "%{$search}%")
                                    ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                                    ->orWhere('investor_type', 'LIKE', "%{$search}%")
                                    ->orWhereHas('partner', function ($partnerQuery) use ($search) {
                                        $partnerQuery->where('name', 'LIKE', "%{$search}%");
                                    })
                                    ->orWhereHas('referredByInvestor', function ($referralQuery) use ($search) {
                                        $referralQuery->where('name', 'LIKE', "%{$search}%");
                                    });
                            });
                        }
                    })
                    ->make(true);
            } catch (Exception $e) {
                return response()->json(['error' => 'Server Error: ' . $e->getMessage()], 500);
            }
        }

        if (AdminHelper::getAdmin()->role == 'admin') {
            $managers = UserAdminModel::where('is_deleted', '0')->get();
        } else {
            $managers = [];
        }

        return view('admin.pages.investor.filtered-list', compact('startups', 'companies', 'managers'));
    }

    public function aadharPanVerification(Request $request)
    {
        setPageTitle('Aadhar-PAN Verification List');
        if ($request->ajax()) {
            try {
                $query = TempAadharPanDetailsModel::with(['investor'])
                    ->whereNotNull('investor_id');

                return DataTables::of($query)
                    ->addColumn('name', function ($row) {
                        return $row->investor?->name ?? '—';
                    })
                    ->addColumn('mobile_number', function ($row) {
                        return $row->investor?->mobile_number ?? '—';
                    })
                    ->addColumn('aadhar_no', fn($row) => $row->aadhar_no ?? '—')
                    ->addColumn('pan_no', fn($row) => $row->pan_no ?? '—')
                    ->addColumn('action', function ($row) {
                        return '<button type="button" class="btn btn-sm btn-primary approve-btn" 
                            data-id="' . $row->id . '" 
                            data-bs-toggle="modal" 
                            data-bs-target="#approveModal">
                            <i class="fas fa-check"></i> Approve
                        </button>';
                    })
                    ->filter(function ($query) use ($request) {
                        if ($request->has('search') && !empty($request->search['value'])) {
                            $search = $request->search['value'];
                            $query->where(function ($q) use ($search) {
                                $q->where('aadhar_no', 'LIKE', "%{$search}%")
                                    ->orWhere('pan_no', 'LIKE', "%{$search}%")
                                    ->orWhereHas('investor', function ($investorQuery) use ($search) {
                                        $investorQuery->where('name', 'LIKE', "%{$search}%")
                                            ->orWhere('mobile_number', 'LIKE', "%{$search}%");
                                    });
                            });
                        }
                    })
                    ->rawColumns(['action'])
                    ->make(true);
            } catch (Exception $e) {
                return UtillsHelper::json(0, ['message' => 'Server Error: ' . $e->getMessage()], 500);
            }
        }

        return view('admin.pages.investor.aadhar_pan_verification');
    }
    public function getTempAadharPanDetails(Request $request)
    {
        try {
            $tempData = TempAadharPanDetailsModel::with(['investor'])->find($request->id);

            if (!$tempData) {
                return UtillsHelper::json(0, ['message' => 'Record not found']);
            }

            return UtillsHelper::json(1, [
                'data' => [
                    'id' => $tempData->id,
                    'investor_name' => $tempData->investor?->name ?? '',
                    'aadhar_front' => route('download.web', ['path' => $tempData->aadhar_front, 'name' => 'Aadhar Front Image of ' . $tempData->investor?->name ?? '']),
                    'aadhar_back' => route('download.web', ['path' => $tempData->aadhar_back, 'name' => 'Aadhar Back Image of ' . $tempData->investor?->name ?? '']),
                    'pan' => route('download.web', ['path' => $tempData->pan, 'name' => 'PAN Image of ' . $tempData->investor?->name ?? ''])
                ]
            ]);
        } catch (Exception $e) {
            return UtillsHelper::json(0, ['message' => $e->getMessage()]);
        }
    }

    public function approveAadharPan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'temp_id' => 'required',
            'aadhar_no' => 'required',
            // 'aadhar_name' => 'required',
            'pan_no' => 'required',
            // 'pan_name' => 'required',
            'dob' => 'required|date|date_format:d-m-Y',
            'address' => 'required',
            'aadhar_front_image' => ['nullable', 'mimes:' . CommonHelper::appSettings('file_image_extensions_allowed'), 'max:' . UtillsHelper::maxFileDocumentSizeInKB()],
            'aadhar_back_image' => ['nullable', 'mimes:' . CommonHelper::appSettings('file_image_extensions_allowed'), 'max:' . UtillsHelper::maxFileDocumentSizeInKB()],
            'pan_image' => ['nullable', 'mimes:' . CommonHelper::appSettings('file_image_extensions_allowed'), 'max:' . UtillsHelper::maxFileDocumentSizeInKB()]
        ]);

        if ($validator->fails()) {
            return UtillsHelper::json(0, ['message' => $validator->errors()->first()]);
        }

        DB::beginTransaction();

        try {
            $tempData = TempAadharPanDetailsModel::find($request->temp_id);

            if (!$tempData) {
                return UtillsHelper::json(0, ['message' => 'Temporary record not found']);
            }

            $investorId = $tempData->investor_id;

            if ($request->hasFile('pan_image')) {
                $filePath = FileUpDownHelper::uploadInvestorDoc($request->file('pan_image'));
                if (!$filePath) {
                    return UtillsHelper::json(1, ['message' => 'PAN file upload failed']);
                }
            } else {
                $filePath = $tempData->pan;
            }


            $docType = DocumentTypeEnum::pancard->value;
            $panDocument = DocumentsModel::create([
                'api_id' => null,
                'path' => $filePath,
                'signed_path' => $filePath,
                'status' => 1,
                'type' => $docType,
                'meta' => [
                    'name' => 'KYC-' . $docType,
                    'investor' => [$investorId],
                ],
            ]);

            if ($request->hasFile('aadhar_front_image')) {
                $filePath = FileUpDownHelper::uploadInvestorDoc($request->file('aadhar_front_image'));
                if (!$filePath) {
                    return UtillsHelper::json(1, ['message' => 'Aadhar front file upload failed']);
                }
            } else {
                $filePath = $tempData->aadhar_front;
            }

            $docType = DocumentTypeEnum::aadharfront->value;
            $aadharFrontDocument = DocumentsModel::create([
                'api_id' => null,
                'path' => $filePath,
                'signed_path' => $filePath,
                'status' => 1,
                'type' => $docType,
                'meta' => [
                    'name' => 'KYC-' . $docType,
                    'investor' => [$investorId],
                ],
            ]);

            // Upload Aadhar back document
            if ($request->hasFile('aadhar_back_image')) {
                $filePath = FileUpDownHelper::uploadInvestorDoc($request->file('aadhar_back_image'));
                if (!$filePath) {
                    return UtillsHelper::json(0, ['message' => 'Aadhar back file upload failed.']);
                }
            } else {
                $filePath = $tempData->aadhar_back;
            }

            $docType = DocumentTypeEnum::aadharback->value;
            $aadharBackDocument = DocumentsModel::create([
                'api_id' => null,
                'path' => $filePath,
                'signed_path' => $filePath,
                'status' => 1,
                'type' => $docType,
                'meta' => [
                    'name' => 'KYC-' . $docType,
                    'investor' => [$investorId],
                ],
            ]);

            // Update or create PAN KYC record
            InvestorKycPanModel::updateOrCreate(
                ['investor_id' => $investorId],
                [
                    'pan_no' => $request->pan_no,
                    'pan_name' => $tempData->investor?->name ?? '',
                    'dob' => DateTimeHelper::formatDateTime($request->input('dob'), 'Y-m-d'),
                    'document_id' => $panDocument->id,
                ]
            );

            // Update or create Aadhar KYC record
            InvestorKycAadharModel::updateOrCreate(
                ['investor_id' => $investorId],
                [
                    'aadhar_no' => $request->aadhar_no,
                    'aadhar_name' => $tempData->investor?->name ?? '',
                    'dob' => DateTimeHelper::formatDateTime($request->input('dob'), 'Y-m-d'),
                    'address' => $request->address,
                    'front_document_id' => $aadharFrontDocument->id,
                    'back_document_id' => $aadharBackDocument->id,
                ]
            );

            $investor = InvestorModel::where('id', $investorId)->first();
            if ($investor) {
                $investor->primary_kyc_status = 1;
                $investor->save();
            }


            DB::commit();

            UtillsHelper::sendWpMessage(
                NotificationTypeEnum::regular,
                'investor_aadhar_pan_kyc_verified_by_admin',
                WpMessageTypeEnum::text,
                $investor->mobile_number,
                $investor->name,
                null,
                [],
                [
                    $investor->name,
                ]
            );

            $tempData->delete();

            return UtillsHelper::json(1, ['message' => 'KYC approved and saved successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            return UtillsHelper::json(0, ['message' => 'Failed to approve KYC data.', 'error' => $e->getMessage()]);
        }
    }

    // public function processDematPdf()
    // {
    //     $request = request();

    //     $validator = Validator::make(
    //         $request->all(),
    //         [
    //             'cml' => 'required|file|mimes:pdf|max:10000',
    //         ],
    //         [
    //             'cml.required' => 'The CML file is required.',
    //             'cml.file' => 'The CML must be a valid file.',
    //             'cml.mimes' => 'The CML must be a PDF file.',
    //             'cml.max' => 'The CML file may not be greater than 10 MB.',
    //         ]
    //     );

    //     if ($validator->fails()) {
    //         return response()->json(['status' => 0, 'message' => $validator->errors()->first()]);
    //     }

    //     try {
    //         $file = $request->file('cml');
    //         $fileContent = file_get_contents($file->getRealPath());

    //         $config = new Config();
    //         $config->setHorizontalOffset("\t");

    //         $parser = new Parser([], $config);
    //         $pdf = $parser->parseContent($fileContent);

    //         $text = trim($pdf->getText());

    //         if (empty($text)) {
    //             return response()->json(['status' => 0, 'message' => 'PDF appears to be image-based or contains no extractable text.']);
    //         }

    //         $lowerText = strtolower($text);

    //         // Extract data using regex (copy your existing extraction logic here)
    //         $dpId = $clientId = $pan = $holderName = $accountNumber = $ifsc = $bankName = null;

    //         // Your existing regex extraction code goes here...
    //         // (Copy all the regex logic from your dematPdf function)

    //         if (strpos($lowerText, 'national securities depository limited') !== false) {
    //             if (preg_match('/\[([^]]+)\]/', $text, $matches)) {
    //                 $dpId = $matches[1];
    //             }
    //         } else {
    //             if (preg_match('/dp\s*id[:\s]*([0-9]{6,})/i', $text, $matches)) {
    //                 $dpId = $matches[1];
    //             }
    //         }

    //         if (preg_match('/client\s*id[^0-9]*([0-9]+)/i', $text, $matches)) {
    //             $clientId = $matches[1];
    //         }

    //         if (preg_match('/([A-Z]{5}[0-9]{4}[A-Z])/i', $text, $matches)) {
    //             $pan = $matches[1];
    //         }

    //         if (preg_match('/HUF\s*Name\s*\([^)]*Sole\s*Holder[^)]*\)\s*(?:MR\.?|MRS\.?|MS\.?|MISS\.?)?\s*([A-Z ]{3,100})\s*HUF/i', $text, $matches)) {
    //             $holderName = trim($matches[1]);
    //         } elseif (preg_match('/Sole\/First\s*Holder\s*Name\s*[:\t ]+(?:MR\.?|MRS\.?|MS\.?|MISS\.?)?\s*([A-Z ]{3,100})(?=\t|$|First|Second|Holder|client|Student|Occupation|Father|Spouse|PAN|Date)/i', $text, $matches)) {
    //             $holderName = trim($matches[1]);
    //         } elseif (preg_match('/First\s*Holder\s*Name\s*[:\t ]+(?:MR\.?|MRS\.?|MS\.?|MISS\.?)?\s*([A-Z ]{3,100}?)(?=\s*(?:First|Second|Third|Holder|client|Student|Occupation|Father|Spouse|PAN|Date|$))/i', $text, $matches)) {
    //             $holderName = trim($matches[1]);
    //         } elseif (preg_match('/First\s*Holder\s*Name\s*[:\t ]+(?:MR\.?|MRS\.?|MS\.?|MISS\.?)?\s*([A-Z ]{3,100})(?=\t|$|First|Second|Holder|client|Student|Occupation|Father|Spouse|PAN|Date)/i', $text, $matches)) {
    //             $holderName = trim($matches[1]);
    //         }
    //         $holderName = $this->fixBrokenName($holderName);

    //         if (preg_match('/bank\s*(a\/c\s*no|account\s*number|a\/c\s*number|account\s*no)[^0-9]*([0-9]{10,})/i', $text, $matches)) {
    //             $accountNumber = $matches[2];
    //         }

    //         if (preg_match('/Bank\s*Details\s*\t*([A-Z&. ]{3,40}?)(?=\t|Bank\s*A\/c|Bank\s*A\/c\s*Type|$)/i', $text, $matches)) {
    //             $bankName = trim($matches[1]);
    //         } elseif (preg_match('/bank\s*name\s*([A-Z&. ]{3,40}?)(?=\s+POA|DDPI|Assigned|$)/i', $text, $matches)) {
    //             $bankName = trim($matches[1]);
    //         }

    //         if (preg_match('/ifsc\s*code[:\s]*([A-Z]{4}[0-9]{1}[0-9A-Z]{6})/i', $text, $matches)) {
    //             $ifsc = $matches[1];
    //         }

    //         if (is_null($dpId) || is_null($clientId) || is_null($pan) || is_null($holderName) || is_null($accountNumber) || is_null($ifsc)) {
    //             return response()->json(['status' => 0, 'message' => 'This PDF is not a valid NSDL or CDSL document.']);
    //         }

    //         return response()->json([
    //             'status' => 1,
    //             'message' => 'PDF parsed successfully.',
    //             'data' => [
    //                 'dp_id' => $dpId,
    //                 'client_id' => $clientId,
    //                 'pan_no' => $pan,
    //                 'account_holder_name' => $holderName,
    //                 'account_number' => $accountNumber,
    //                 'ifsc_code' => $ifsc,
    //                 'bank_name' => $bankName,
    //                 'dob' => ''
    //             ],
    //         ]);
    //     } catch (Exception $e) {
    //         return response()->json(['status' => 0, 'message' => $e->getMessage()]);
    //     }
    // }

    // Don't forget to add the fixBrokenName method if it's not already in your controller
    // function fixBrokenName($name)
    // {
    //     $parts = preg_split('/[\s\t]+/', trim($name));
    //     $fixedParts = [];
    //     $i = 0;

    //     while ($i < count($parts)) {
    //         // If next part exists and both are short (likely broken surname), merge
    //         if (
    //             isset($parts[$i + 1]) &&
    //             strlen($parts[$i]) <= 4 &&
    //             strlen($parts[$i + 1]) <= 4
    //         ) {
    //             $fixedParts[] = $parts[$i] . $parts[$i + 1];
    //             $i += 2; // Skip next part
    //         } else {
    //             $fixedParts[] = $parts[$i];
    //             $i++;
    //         }
    //     }

    //     return implode(' ', $fixedParts);
    // }


    // public function storeDematKyc(string $uuid): JsonResponse
    // {
    //     $request = request();
    //     $investor = InvestorModel::where('uuid', $uuid)->where('is_deleted', '0')->first();

    //     if (!$investor) {
    //         return UtillsHelper::json(0, ['message' => 'Investor not found']);
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'dp_id'             => 'required',
    //         'client_id'         => 'required',
    //         'pan_no'            => 'required',
    //         'name'              => 'required',
    //         'account_number'    => 'required',
    //         'ifsc_code'         => 'required',
    //         'bank_name'         => 'nullable',
    //         'dob'               => 'nullable|date',
    //         'cml_file'          => 'nullable|mimes:pdf|max:10000'
    //     ]);

    //     if ($validator->fails()) {
    //         return UtillsHelper::json(0, ['message' => $validator->errors()->first()]);
    //     }

    //     DB::beginTransaction();

    //     try {
    //         $document = null;

    //         // Handle file upload if provided
    //         if ($request->hasFile('cml_file')) {
    //             $filePath = FileUpDownHelper::uploadInvestorDoc($request->file('cml_file'));

    //             if (!$filePath) {
    //                 return UtillsHelper::json(0, ['message' => 'File upload failed.']);
    //             }

    //             $docType = DocumentTypeEnum::clientmaster->value;

    //             $document = DocumentsModel::create([
    //                 'api_id' => null,
    //                 'path' => $filePath,
    //                 'signed_path' => $filePath,
    //                 'status' => 1,
    //                 'type' => $docType,
    //                 'meta' => [
    //                     'name' => 'KYC-' . $docType,
    //                     'investor' => [$investor->id],
    //                 ],
    //             ]);
    //         }

    //         // Update or create demat account
    //         $demat = InvestorDematAccountModel::updateOrCreate(
    //             ['investor_id' => $investor->id],
    //             [
    //                 'document_id' => $document?->id,
    //                 'dp_id' => $request->input('dp_id'),
    //                 'client_id' => $request->input('client_id'),
    //                 'demat_account' => $request->input('dp_id') . $request->input('client_id'),
    //             ]
    //         );

    //         // Update or create bank details
    //         UserBankAccountModel::updateOrCreate(
    //             ['user_id' => $investor->id, 'user_type' => InvestorModel::class],
    //             [
    //                 'account_number' => $request->input('account_number'),
    //                 'account_holder_name' => $request->input('name'),
    //                 'ifsc_code' => $request->input('ifsc_code'),
    //                 'bank_name' => $request->input('bank_name'),
    //             ]
    //         );

    //         // Update or create PAN details
    //         InvestorKycPanModel::updateOrCreate(
    //             ['investor_id' => $investor->id],
    //             [
    //                 'pan_no' => $request->input('pan_no'),
    //                 'pan_name' => $request->input('name'),
    //                 'dob' => $request->input('dob'),
    //             ]
    //         );

    //         // Update investor
    //         $investor->update([
    //             'name' => $request->input('name'),
    //             'preipo_kyc_status' => 1,
    //         ]);

    //         DB::commit();

    //         return UtillsHelper::json(1, ['message' => 'KYC details saved successfully.']);
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         return UtillsHelper::json(0, [
    //             'message' => 'Failed to save KYC details.',
    //             'error' => $e->getMessage()
    //         ]);
    //     }
    // }

    public function processDematPdf()
    {
        $request = request();

        $validator = Validator::make(
            $request->all(),
            [
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
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()]);
        }

        $file = $request->file('cml');
        $userId = auth()->id();

        $pdfParsingService = new DematPdfParsingService();
        $result = $pdfParsingService->processPdf($file, $userId);

        return response()->json([
            'status' => $result['success'] ? 1 : 0,
            'message' => $result['message'],
            'data' => $result['data'] ?? null
        ]);
    }

    public function storeDematKyc(string $uuid)
    {
        $request = request();
        $investor = InvestorModel::where('uuid', $uuid)->where('is_deleted', '0')->first();

        if (!$investor) {
            return response()->json(['status' => 0, 'message' => 'Investor not found']);
        }

        $validator = Validator::make($request->all(), [
            'dp_id'             => 'required',
            'client_id'         => 'required',
            'pan_no'            => 'required',
            'name'              => 'required',
            'account_number'    => 'required',
            'ifsc_code'         => 'required',
            'bank_name'         => 'nullable',
            'dob'               => 'nullable|date',
            'cml_file'          => 'nullable|mimes:pdf|max:10000'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()]);
        }

        $investorId = $investor->id;
        $cmlFile = $request->hasFile('cml_file') ? $request->file('cml_file') : null;

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
        $result = $kycService->saveDematKyc($investorId, $data, $cmlFile);

        if ($result['success']) {
            return response()->json(['status' => 1, 'message' => $result['message']]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => $result['message'] . ($result['error'] ? ': ' . $result['error'] : '')
            ]);
        }
    }


    public function exportExcel(Request $request)
    {
        return Excel::download(new InvestorExport($request), 'investors.xlsx');
    }

    function activestatus(string $uuid, string $status): RedirectResponse
    {
        $investor = InvestorModel::where('is_deleted', '0')->where('uuid', $uuid)->first();
        if ($investor && in_array($status, [1, 2])) {
            $investor->is_active = $status;
            $investor->save();
            if ($status == '1') {
                return redirect()->back()->with('success', 'Investor Approved');
            }
            return redirect()->back()->with('success', 'Investor Rejected');
        }
        return redirect()->back()->with('error', 'Inputs Are Not Valid');
    }

    function view(string $uuid): View|RedirectResponse
    {
        $investor = InvestorModel::where('is_deleted', '0')->where('uuid', $uuid)
            ->with(['portfolio' => function ($query) {
                $query->where('shares', '>', '0');
            }])->with(['pportfolio' => function ($query) {
                $query->where('shares', '>', '0');
            }])
            ->with(['dematAccount', 'newBankAccount', 'newPan'])
            ->first();
        if ($investor) {
            setPageTitle($investor->name . "'s Profile");
            $documents = DocumentsModel::where('status', 1)
                ->whereJsonContains('meta->investor', $investor->id)
                ->orderBy('id', 'desc')
                ->limit(200)
                ->get();
            $data['investor'] = $investor;
            $data['documents'] = $documents;
            return view('admin.pages.investor.view', $data);
        }

        return redirect()->back()->with('error', 'Investor not found');
    }

    /**
     * Admin: Global investor documents listing with filters.
     *
     * - Shows all documents from documents table (status = 1)
     * - Columns: document name, type, investor names, created_at
     * - Filters: investor name (text), document type (select)
     */
    public function documents(): View
    {
        setPageTitle('Investor Documents');

        $documentTypes = collect(DocumentTypeEnum::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->value]);

        addVendor('datatables');

        return view('admin.pages.investor.documents', compact('documentTypes'));
    }
    public function documentsData(Request $request)
    {
        $query = DocumentsModel::where('status', 1)
            ->orderBy('created_at', 'desc');

        // Document Type filter
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Investor Name filter
        if ($request->filled('investor_name')) {
            $ids = InvestorModel::where('is_deleted', 0)
                ->where('name', 'like', '%' . $request->investor_name . '%')
                ->pluck('id')
                ->toArray();

            $query->where(function ($q) use ($ids) {
                foreach ($ids as $id) {
                    $q->orWhereJsonContains('meta->investor', $id);
                }
            });
        }

        return DataTables::of($query)
            ->addColumn(
                'document_name',
                fn($row) =>
                $row->meta->name ?? $row->display_name ?? 'N/A'
            )
            ->addColumn('investors', function ($row) {
                $ids = $row->meta->investor ?? [];
                $ids = is_array($ids) ? $ids : [$ids];

                return InvestorModel::whereIn('id', $ids)->pluck('name')->implode(', ');
            })
            ->addColumn(
                'created_at',
                fn($row) =>
                $row->created_at->format('d-m-Y H:i')
            )
            ->addColumn('action', function ($row) {
                if (!$row->signed_path) {
                    return '<span class="text-muted">No file</span>';
                }

                return '
                
                <a href="' . route('download.web', [
                    'path' => $row->signed_path,
                    'name' => 'document'
                ]) . '"
                    class="btn btn-icon btn-sm btn-light-success">
                    ' . getIcon('arrow-down', 'fs-4') . '
                </a>
            ';
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function processingKyc(): View
    {
        setPageTitle('Processing KYC');

        addVendor('datatables');

        $list = DematManualModel::with('investor')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.pages.investor.processing-kyc', compact('list'));
    }

    public function processingKycData(Request $request)
    {
        $query = DematManualModel::with(['investor', 'document'])
            ->where('status', StatusEnum::pending->value)
            ->orderBy('created_at', 'desc');

        return DataTables::of($query)

            ->addColumn('investor_name', function ($row) {
                return $row->investor->name ?? 'N/A';
            })

            ->addColumn('mobile', function ($row) {
                return $row->investor->mobile_number ?? 'N/A';
            })

            ->addColumn('email', function ($row) {
                return $row->investor->email ?? 'N/A';
            })

            ->addColumn('document', function ($row) {

                if (!$row->document || !$row->document->signed_path) {
                    return '<span class="text-muted">No file</span>';
                }

                return '
                <a href="' . route('download.web', [
                    'path' => $row->document->signed_path,
                    'name' => 'document'
                ]) . '"
                class="btn btn-icon btn-sm btn-light-success">
                    ' . getIcon('arrow-down', 'fs-4') . '
                </a>';
            })

            ->addColumn('action', function ($row) {

                return '
                <div class="card-toolbar">

                <button type="button"
                class="btn btn-sm btn-icon btn-color-primary btn-active-light-primary"
                data-kt-menu-trigger="click"
                data-kt-menu-placement="bottom-end">

                <i class="ki-duotone ki-category fs-6">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
                <span class="path4"></span>
                </i>

                </button>

                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded
                menu-gray-800 menu-state-bg-light-primary fw-semibold w-200px"
                data-kt-menu="true">

                <div class="menu-item px-3">
                <div class="menu-content fs-6 text-gray-900 fw-bold px-3 py-4">
                Action
                </div>
                </div>

                <div class="separator mb-3 opacity-75"></div>

                <div class="menu-item px-3">
                <a href="#" class="menu-link px-3 manual-kyc-btn"
                data-investor="' . $row->investor_id . '">
                Complete Manual KYC
                </a>
                </div>

                <div class="menu-item px-3">
                <a href="#" class="menu-link px-3 reject-kyc-btn"
                data-investor="' . $row->investor_id . '"
                data-kt-menu-dismiss="false">
                Reject
                </a>
                </div>
                </div>
                </div>
                ';
            })

            ->addColumn('created_at', function ($row) {
                return $row->created_at->format('d-m-Y H:i');
            })

            ->rawColumns(['document', 'action'])
            ->make(true);
    }

    public function manualKycSubmit(Request $request)
    {
        $request->validate([
            'investor_id' => 'required',
            'dp_id' => 'required',
            'client_id' => 'required',
            'pan_number' => 'required',
            'name' => 'required',
            'account_number' => 'nullable',
            'ifsc_code' => 'nullable',
            'bank_name' => 'nullable',
            'dob' => 'nullable'
        ]);

        $investor = InvestorModel::findOrFail($request->investor_id);

        $data = [
            'dp_id' => $request->dp_id,
            'client_id' => $request->client_id,
            'pan_no' => $request->pan_number,
            'name' => $request->name,
            'account_number' => $request->account_number,
            'ifsc_code' => $request->ifsc_code,
            'bank_name' => $request->bank_name,
            'dob' => $request->dob
        ];

        $kycService = new DematKycService();

        $result = $kycService->saveDematKyc($investor->id, $data);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        // Mark manual KYC request as approved
        DematManualModel::where('investor_id', $investor->id)
            ->where('status', StatusEnum::pending->value)
            ->update([
                'status' => StatusEnum::approved->value,
            ]);

        return back()->with('success', 'Manual KYC Completed');
    }

    public function manualKycReject(Request $request)
    {
        $request->validate([
            'investor_id' => 'required',
            'reason' => 'required'
        ]);

        DematManualModel::where('investor_id', $request->investor_id)
            ->where('status', StatusEnum::pending->value)
            ->update([
                'status' => StatusEnum::rejected->value,
                'reason' => $request->reason
            ]);

        return back()->with('success', 'KYC Rejected Successfully');
    }


    function markAsDemo(string $uuid): RedirectResponse
    {
        $investor = InvestorModel::select('id', 'is_demo', 'name')->where('is_deleted', '0')->where('uuid', $uuid)->first();
        if ($investor) {
            $investor->is_demo = $investor->is_demo == '1' ? '0' : '1';
            $investor->save();
            return redirect()->back()->with('success', 'Investor ' . $investor->name . ' Marked as ' . ($investor->is_demo == '1' ? 'Demo' : 'Live'));
        }
        return redirect()->back()->with('error', 'Investor not found');
    }

    function markBlock(string $uuid): RedirectResponse
    {
        $investor = InvestorModel::select('id', 'is_blocked', 'name')->where('is_deleted', '0')->where('uuid', $uuid)->first();
        if ($investor) {
            $investor->is_blocked = $investor->is_blocked == '1' ? '0' : '1';
            $investor->save();
            return redirect()->back()->with('success', 'Investor ' . $investor->name . ' Marked as ' . ($investor->is_blocked == '1' ? 'Block' : 'Unblock'));
        }
        return redirect()->back()->with('error', 'Investor not found');
    }

    function aifOnboardView(string $uuid): View|RedirectResponse
    {
        $investor = InvestorModel::where('is_deleted', '0')->where('uuid', $uuid)->first();
        if ($investor) {
            setPageTitle($investor->name . "'s Profile");
            $data['investor'] = $investor;
            return view('admin.pages.investor.aif-onboard.view', $data);
        }
        return redirect()->back()->with('error', 'Investor not found');
    }

    function aifOnboardPending(): View
    {
        setPageTitle('Pending AIF Onboarding');
        $data['list'] = InvestorAifKycModel::where('status', 0)->get();
        return view('admin.pages.investor.aif-onboard.list', $data);
    }

    function aifOnboardRejected(): View
    {
        setPageTitle('Rejected AIF Onboarding');
        $data['list'] = InvestorAifKycModel::where('status', 1)->get();
        return view('admin.pages.investor.aif-onboard.list', $data);
    }

    function aifOnboardApproved(): View
    {
        setPageTitle('Approved AIF Onboarding');
        $data['list'] = InvestorAifKycModel::where('status', '>', 1)->get();
        return view('admin.pages.investor.aif-onboard.list', $data);
    }

    function aifOnboardapprove(Request $request): RedirectResponse
    {
        $aif = InvestorAifKycModel::where('id', $request->aif_id)->first();
        $aif->notes = $request->notes;
        $aif->status = $request->aif_status;
        $aif->created_by = AdminHelper::getAdmin()->id;
        $aif->updated_by = AdminHelper::getAdmin()->id;
        $aif->save();
        if ($aif->status == '1') {
            AdminHelper::logPut('AIF Onboard Reject', InvestorModel::class, $aif->investor_id);
            return redirect()->route('admin.aifonboard.pending')->with('success', 'Rejected');
        }
        if ($aif->status == '2') {
            if ($request->hasFile('ppmFile')) {
                $meta = [
                    'aif_kyc' => [
                        $aif->id
                    ],
                    'investor' => [
                        $aif->investor->id
                    ],
                ];
                $meta['name']   = DocumentTypeEnum::ppm->value . " AIF Onboarding Agreement";
                $meta['aname']   = DocumentTypeEnum::ppm->value . ' of ' . $aif->investor->name;
                $file = FileUpDownHelper::uploadInvestorDoc($request->file('ppmFile'));
                if ($file) {
                    $document = new DocumentsModel();
                    $document->api_id = NULL;
                    $document->path = $file;
                    $document->signed_path = $file;
                    $document->status = 0;
                    $document->type = DocumentTypeEnum::ppm;
                    $document->meta = $meta;
                    $document->save();
                }
            }

            if ($request->hasFile('caFile')) {
                $meta = [
                    'aif_kyc' => [
                        $aif->id
                    ],
                    'investor' => [
                        $aif->investor->id
                    ],
                ];
                $meta['name']   = DocumentTypeEnum::ca->value . " AIF Onboarding Agreement";
                $meta['aname']   = DocumentTypeEnum::ca->value . ' of ' . $aif->investor->name;
                $file = FileUpDownHelper::uploadInvestorDoc($request->file('caFile'));
                if ($file) {
                    $document = new DocumentsModel();
                    $document->api_id = NULL;
                    $document->path = $file;
                    $document->signed_path = $file;
                    $document->status = 0;
                    $document->type = DocumentTypeEnum::ca;
                    $document->meta = $meta;
                    $document->save();
                }
            }

            Onboard::dispatch($aif->id);
            AdminHelper::logPut('AIF Onboard Approve', InvestorModel::class, $aif->investor_id);
            return redirect()->route('admin.aifonboard.pending')->with('success', 'PPM and CA sent.');
        }

        return redirect()->route('admin.aifonboard.pending');
    }

    function aifDocumentStatus($id, $type): RedirectResponse
    {
        $aif = InvestorAifKycModel::where('id', $id)->first();
        if ($aif) {
            $aif->ppm_signed = 1;
            $aif->ca_signed = 1;
            $aif->status = 3;
            $aif->updated_by = AdminHelper::getAdmin()->id;
            $aif->save();


            InvestorModel::where('id', $aif->investor_id)->update(['aif_status' => '1']);
            return redirect()->back()->with('success', 'AIF Approved');
        }
        return redirect()->back()->with('error', 'Item not found');
    }

    function manualKycPending(): View
    {
        setPageTitle('Pending Manual KYC');
        $data['list'] = InvestorKycModel::where('status', StatusEnum::pending->value)->get();
        return view('admin.pages.investor.manual-kyc.list', $data);
    }

    function manualKycRejected(): View
    {
        setPageTitle('Rejected Manual KYC');
        $data['list'] = InvestorKycModel::where('status', StatusEnum::rejected->value)->get();
        return view('admin.pages.investor.manual-kyc.list', $data);
    }

    function manualKycApproved(): View
    {
        setPageTitle('Approved KYC');
        $data['list'] = InvestorKycModel::where('status', StatusEnum::approved->value)->get();
        return view('admin.pages.investor.manual-kyc.list', $data);
    }

    function manualKycView(string $uuid): View|RedirectResponse
    {
        $investor = InvestorModel::where('is_deleted', '0')->where('uuid', $uuid)->first();
        $bankDetails = BankDetailsModel::where('user_id', $investor->id)->first();
        $dematDetails = InvestorDematAccountModel::where('investor_id', $investor->id)->first();
        $investorKyc = InvestorKycModel::where('investor_id', $investor->id)->first();
        $panDetails = InvestorPanDetailsModel::where('investor_id', $investor->id)->first();
        if ($investor) {
            setPageTitle($investor->name . "'s Profile");
            $data['investor'] = $investor;
            $data['bankDetails'] = $bankDetails;
            $data['dematDetails'] = $dematDetails;
            $data['investorKyc'] = $investorKyc;
            $data['panDetails'] = $panDetails;
            return view('admin.pages.investor.manual-kyc.view', $data);
        }
        return redirect()->back()->with('error', 'Investor not found');
    }

    function approveManualKYC(Request $request): RedirectResponse
    {
        $kycRec = InvestorKycModel::where('id', $request->kyc_id)->first();
        if ($request->status == '0') {
            $kycRec->status = StatusEnum::rejected->value;
            $kycRec->created_by = AdminHelper::getAdmin()->id;
            $kycRec->updated_by = AdminHelper::getAdmin()->id;
            $kycRec->notes = $request->notes;
            $kycRec->save();
            AdminHelper::logPut('Manual KYC Reject', InvestorModel::class, $kycRec->investor_id);
            InvestorModel::where('id', $kycRec->investor_id)->update([
                'kyc_status' => '0',
                'aadhar_verified_type'  => NULL,
                'updated_by'            => AdminHelper::getAdmin()->id
            ]);
            return redirect()->route('admin.manualkyc.pending')->with('success', 'KYC record rejected.');
        } else {
            $kycRec->aadhar_no = $request->aadhar_no;
            $kycRec->pan_no = strtoupper($request->pan_no);
            $kycRec->name_as_aadhar = $request->aadhar_name;
            $kycRec->name_as_pan = $request->pan_name;
            $kycRec->dob_as_aadhar = DateTimeHelper::formatDateTime($request->dob, 'Y-m-d');
            $kycRec->address_as_aadhar = $request->address;
            $kycRec->status = StatusEnum::approved->value;
            $kycRec->notes = $request->notes;
            $kycRec->created_by = AdminHelper::getAdmin()->id;
            $kycRec->updated_by = AdminHelper::getAdmin()->id;
            $kycRec->save();
            InvestorModel::where('id', $kycRec->investor_id)->update([
                'name'          => $request->aadhar_name,
                'kyc_status' => '1',
                'address' => $request->address,
                'aadhar_verified_type'  => 'Manual',
                'updated_by'            => AdminHelper::getAdmin()->id
            ]);

            $details = InvestorDetailsModel::where('investor_id', $kycRec->investor_id)->first();
            if (!$details) {
                $details = new InvestorDetailsModel;
                $details->investor_id = $kycRec->investor_id;
            }
            $details->date_of_birth = DateTimeHelper::formatDateTime($request->dob, 'Y-m-d');
            $details->save();


            $demat = InvestorDematAccountModel::where('investor_id', $kycRec->investor_id)->first();
            if (!$demat) {
                $demat = new InvestorDematAccountModel;
                $demat->investor_id = $kycRec->investor_id;
            }

            $demat->dp_id = $request->dp_id;
            $demat->client_id = $request->client_id;
            $demat->demat_account = $request->demat_account;
            $demat->save();
            AdminHelper::logPut('Manual KYC Approve, Demat account update', InvestorModel::class, $kycRec->investor_id);
            return redirect()->route('admin.manualkyc.pending')->with('success', 'KYC record approved.');
        }
    }

    function rejectManualKYC($id): RedirectResponse
    {
        $kycRec = InvestorKycModel::find($id);
        if ($kycRec) {
            $kycRec->status = StatusEnum::rejected->value;
            $kycRec->save();
            AdminHelper::logPut('Manual KYC Reject', InvestorModel::class, $kycRec->investor_id);
            InvestorModel::where('id', $kycRec->investor_id)->update([
                'kyc_status' => '0',
                'aadhar_verified_type'  => NULL,
                'updated_by'            => AdminHelper::getAdmin()->id
            ]);
        }

        return redirect()->route('admin.manualkyc.pending')->with('success', 'KYC record rejected.');
    }

    function updateBankStatus(Request $request, $id)
    {
        $bank = BankDetailsModel::findOrFail($id);
        $action = $request->input('action');

        if (!in_array($action, [StatusEnum::approved->value, StatusEnum::rejected->value])) {
            return back()->with('error', 'Invalid action.');
        }

        if ($action === StatusEnum::approved->value) {
            $rules = [
                'account_holder_name' => ['required'],
                'account_number' => ['required', 'string', 'size:16'],
                'ifsc_code' => ['required', 'string', 'size:10'],
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors($validator)
                    ->with('error', 'Check form errors');
            }

            $bank->account_holder_name = $request->account_holder_name;
            $bank->account_number = $request->account_number;
            $bank->ifsc_code = $request->ifsc_code;
            $bank->user_type = InvestorModel::class;

            if ($bank->bank) {
                $bank->bank->name = $request->bank_name;
                $bank->bank->save();
            }
        }

        $bank->status = $action;
        $bank->save();

        UtillsHelper::updatePreIpoKycStatus($bank->user_id);

        $message = $action === StatusEnum::approved->value
            ? 'Bank details approved and saved.'
            : 'Bank details rejected.';

        return back()->with($action === StatusEnum::approved->value ? 'success' : 'error', $message);
    }

    function updateDematStatus(Request $request, $id)
    {
        $dematAccount = InvestorDematAccountModel::findOrFail($id);
        $action = $request->input('action');

        if (!in_array($action, [StatusEnum::approved->value, StatusEnum::rejected->value])) {
            return back()->with('error', 'Invalid action.');
        }

        if ($action === StatusEnum::approved->value) {
            $rules = [
                'dp_id' => ['required', 'string', 'size:8'],
                'client_id' => ['required', 'string', 'size:8'],
            ];

            if ($request->hasFile('cml')) {
                $rules['cml'] = ['mimes:pdf', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors($validator)
                    ->with('error', 'Check form errors');
            }

            $dematAccount->dp_id = $request->dp_id;
            $dematAccount->client_id = $request->client_id;
            $dematAccount->demat_account = $request->dp_id . $request->client_id;

            if ($request->hasFile('cml')) {
                $kyc = InvestorKycModel::where('investor_id', $dematAccount->investor_id)->first();

                if ($kyc) {
                    $image = FileUpDownHelper::uploadInvestorDoc($request->file('cml'));
                    if ($image) {
                        $kyc->cml_image = $image;
                        $kyc->save();
                    }
                }
            }
        }
        $dematAccount->status = $action;
        $dematAccount->save();

        UtillsHelper::updatePreIpoKycStatus($dematAccount->investor_id);

        $message = $action === StatusEnum::approved->value
            ? 'Demat account details approved and saved.'
            : 'Demat account details rejected.';

        return back()->with($action === StatusEnum::approved->value ? 'success' : 'error', $message);
    }

    function updatePanStatus(Request $request, $id)
    {
        $panDetails = InvestorPanDetailsModel::findOrFail($id);
        $action = $request->input('action');

        if (!in_array($action, [StatusEnum::approved->value, StatusEnum::rejected->value])) {
            return back()->with('error', 'Invalid action.');
        }

        if ($action === StatusEnum::approved->value) {
            $rules = [
                'name_as_pan' => ['required', 'string'],
                'pan_no' => ['required', 'string', 'size:10'],
            ];

            if ($request->hasFile('pan_image')) {
                $rules['pan_image'] = ['mimes:pdf,jpg,jpeg,png', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors($validator)
                    ->with('error', 'Check form errors');
            }

            $panDetails->name_as_pan = $request->name_as_pan;
            $panDetails->pan_no = $request->pan_no;

            if ($request->hasFile('pan_image')) {
                $image = FileUpDownHelper::uploadInvestorDoc($request->file('pan_image'));
                if ($image) {
                    $panDetails->pan_image = $image;
                }
            }
        }

        $panDetails->status = $action;
        $panDetails->save();

        UtillsHelper::updatePreIpoKycStatus($panDetails->investor_id);

        $message = $action === StatusEnum::approved->value
            ? 'PAN details approved and saved.'
            : 'PAN details rejected.';

        return back()->with($action === StatusEnum::approved->value ? 'success' : 'error', $message);
    }

    function updateAadharStatus(Request $request, $id)
    {
        $kyc = InvestorKycModel::findOrFail($id);
        $action = $request->input('action');

        if (!in_array($action, [StatusEnum::approved->value, StatusEnum::rejected->value])) {
            return back()->with('error', 'Invalid action.');
        }

        if ($action === StatusEnum::approved->value) {
            // $rules = [
            //     'aadhaar_no' => ['required', 'string'],
            // ];

            if ($request->hasFile('aadhaar_front_image')) {
                $rules['aadhaar_front_image'] = ['mimes:jpeg,png,jpg,pdf', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
            }
            if ($request->hasFile('aadhaar_back_image')) {
                $rules['aadhaar_back_image'] = ['mimes:jpeg,png,jpg,pdf', 'max:' . UtillsHelper::maxFileDocumentSizeInKB()];
            }

            // $validator = Validator::make($request->all(), $rules);

            // if ($validator->fails()) {
            //     return redirect()->back()
            //         ->withInput()
            //         ->withErrors($validator)
            //         ->with('error', 'Check form errors');
            // }

            if ($request->hasFile('aadhaar_front_image')) {
                $image = FileUpDownHelper::uploadInvestorDoc($request->file('aadhaar_front_image'));
                if ($image) {
                    $kyc->aadhaar_front_image = $image;
                }
            }

            if ($request->hasFile('aadhaar_back_image')) {
                $image = FileUpDownHelper::uploadInvestorDoc($request->file('aadhaar_back_image'));
                if ($image) {
                    $kyc->aadhaar_back_image = $image;
                }
            }
        }

        $kyc->status = $action;
        $kyc->save();
        UtillsHelper::updatePreIpoKycStatus($kyc->investor_id);

        $message = $action === StatusEnum::approved->value
            ? 'Aadhaar details approved and saved.'
            : 'Aadhaar details rejected.';

        return back()->with($action === StatusEnum::approved->value ? 'success' : 'error', $message);
    }
}
