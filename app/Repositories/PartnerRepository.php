<?php

namespace App\Repositories;

use App\Enums\InvestorTypeEnum;
use App\Enums\PartnerTypeEnum;
use App\Enums\Utills\CodeVerificationTypeEnum;
use App\Enums\Utills\DeviceTypeEnum;
use App\Enums\Utills\StatusEnum;
use App\Helpers\AdminHelper;
use App\Helpers\CommonHelper;
use App\Helpers\FileUpDownHelper;
use App\Helpers\UtillsHelper;
use App\Http\Requests\PartnerRequest;
use App\Models\CoreFirebaseDeviceTokenModel;
use App\Models\DocumentsModel;
use App\Models\InvestorKycModel;
use App\Models\InvestorModel;
use App\Models\NotificationsModel;
use App\Models\PartnerModel;
use App\Models\PortfolioModel;
use App\Models\StartupMisModel;
use App\Models\StartupModel;
use App\Services\DematKycService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Illuminate\Contracts\Database\Eloquent\Builder;

class PartnerRepository
{
    public function getInvestedStartupMIS()
    {
        $partnerId = request()->user()->id;

        // Fetch partner and related investments
        $partner = PartnerModel::with(['investor.portfolio.startup.sector', 'investor.portfolio.startup.details', 'investor.portfolio.startup.sharePrices' => function ($query) {
            $query->orderByDesc('created_at')->limit(1);
        }])->find($partnerId);

        if (!$partner) {
            return response()->json(['error' => 'Partner not found'], 404);
        }

        $investors = $partner->investor;
        $portfolio = $investors->flatMap->portfolio;

        // Build investment_growth
        $investmentGrowth = [];
        $portfolio->each(function ($investment) use (&$investmentGrowth) {
            $startup = $investment->startup;
            $startupId = $startup->id;
            $startupName = $startup->brand_name;
            $investmentAmount = $investment->investment_amount;
            $latestSharePrice = $startup->sharePrices->first()->price ?? $investment->purchase_price;
            $currentValue = $investment->shares * $latestSharePrice;
            $startupLogo = $startup->details->logo;

            if (!isset($investmentGrowth[$startupId])) {
                $investmentGrowth[$startupId] = [
                    'startup_id' => $startupId,
                    'startup_name' => $startupName,
                    'total_invested_amount' => 0,
                    'current_value' => $currentValue,
                    'logo' => $startupLogo,
                ];
            }
            $investmentGrowth[$startupId]['total_invested_amount'] += $investmentAmount;
            $investmentGrowth[$startupId]['current_value'] = $currentValue;
        });

        // Fetch only approved MIS details for startups in investment_growth
        $startupIds = collect($investmentGrowth)->pluck('startup_id');
        $approvedMISDetails = StartupMISModel::whereIn('startup_id', $startupIds)
            ->where('status', \App\Enums\Utills\StatusEnum::approved->value) // Filter by approved status
            ->get();

        // Combine approved MIS details with investment_growth data and filter out startups without approved MIS
        $investmentGrowthWithApprovedMIS = collect($investmentGrowth)->map(function ($startup) use ($approvedMISDetails) {
            $approvedMISData = $approvedMISDetails->where('startup_id', $startup['startup_id']);
            $startup['approved_mis'] = $approvedMISData->isNotEmpty() ? $approvedMISData->values()->toArray() : []; // Include only approved MIS
            return $startup;
        });

        // Filter out startups that have no approved MIS records
        $filteredInvestmentGrowth = $investmentGrowthWithApprovedMIS->filter(function ($startup) {
            return !empty($startup['approved_mis']); // Only keep startups with approved MIS
        });

        // Return the combined data
        return UtillsHelper::json(
            1,
            [
                'message' => 'Invested Startups with Approved MIS Details',
                'data' => $filteredInvestmentGrowth->values()->toArray()
            ],
            200
        );
    }





    function login(): JsonResponse
    {
        $request = request();
        $validateArray = [
            'mobile_no' => 'required|numeric|digits:10',
            'password'  => 'required'
        ];
        $messageArray = [];
        if ($request->is('api/*')) {
            $validateArray['firebase_token'] = 'required';
            $validateArray['device_id'] = 'required|string|max:255';
            $validateArray['device'] = ['required', Rule::enum(DeviceTypeEnum::class)];

            $messageArray['device'] = 'The selected device is invalid. Valid options are: ' . implode(', ', array_column(DeviceTypeEnum::cases(), 'value'));
        }
        $validation = Validator::make($request->all(), $validateArray, [], $messageArray);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $partner = PartnerModel::where('is_deleted', '0')->where('mobile_number', $request->mobile_no)->with(['country', 'city', 'state'])->first();
        if (!$partner) {
            return UtillsHelper::json(0, ['message' => 'Mobile number not registered.']);
        } else {
            if (!Hash::check($request->password, $partner->password)) {
                return UtillsHelper::json(0, ['message' => 'Mobile number and Password do not match.']);
            } else {
                if ($partner->is_blocked == '1') {
                    return UtillsHelper::json(0, ['message' => 'Your account is blocked please contact administrator']);
                } else {
                    if ($request->is('api/*')) {
                        $partner->token = $partner->createToken('Partner login token')->plainTextToken;
                        UtillsHelper::firebaseLogin($partner->id, PartnerModel::class);
                        $this->attachSelfInvestorId($partner);
                        return UtillsHelper::json(1, [
                            'message' => 'Login Success',
                            'data'  => $partner
                        ]);
                    }
                    if ($partner->ask_password_change == '1') {
                        Session::put('partner_login_id', $partner->id);
                        return UtillsHelper::json(1, ['view' => view('front.common.auth.change-password', [
                            'title' => 'Change your password',
                            'action'    => route('front.business.auth.post.login.password'),
                            'redirect'  => route('front.business.auth.login')
                        ])->render()]);
                    } else {
                        Auth::guard('partner')->loginUsingId($partner->id);
                        UtillsHelper::firebaseLogin($partner->id, PartnerModel::class);
                        return UtillsHelper::json(1, ['message' => 'Login Success']);
                    }
                }
            }
        }
    }

    function logout(): RedirectResponse|JsonResponse
    {
        $request = request();
        if ($request->is('api/*')) {
            $validation = Validator::make($request->all(), [
                'device'        => ['required', Rule::enum(DeviceTypeEnum::class)],
                'device_id'     => 'required'
            ], [], [
                'device' => 'The selected device is invalid. Valid options are: ' . implode(', ', array_column(DeviceTypeEnum::cases(), 'value'))
            ]);
            if ($validation->fails()) {
                return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
            }
            CoreFirebaseDeviceTokenModel::where('user_id', $request->user()->id)->where('user_type', PartnerModel::class)
                ->where('device', $request->device)->where('device_id', $request->device_id)->delete();
            $request->user()->currentAccessToken()->delete();
            return UtillsHelper::json(1, [
                'message' => 'Logout Success'
            ]);
        } else {
            if (Auth::guard('partner')->check()) {
                CoreFirebaseDeviceTokenModel::where('user_id', Auth::guard('partner')->user()->id)->where('user_type', PartnerModel::class)
                    ->where('device', DeviceTypeEnum::web)->where('device_id', Cookie::get('_unique_device_id'))->delete();
                Auth::guard('partner')->logout();
            }
            return redirect()->route('front.business.auth.login');
        }
    }

    function channelPartnerlist(): View|RedirectResponse|JsonResponse
    {
        $request = request();
        if ($request->is('api/*')) {
            $user = $request->user();

            $list = PartnerModel::where('parent_id', $user->id)->with(['country', 'city', 'state'])->get();
            $list = $list->map(function ($partner) {
                // Calculate the total investment amount
                $totalInvestmentAmount = $partner->investor->sum(function ($investor) {
                    return $investor->portfolio->sum('investment_amount');
                });
                $totalStartups = $partner->investor->flatMap(function ($investor) {
                    return $investor->portfolio;
                })->unique('startup_id')->count();
                $commissionPercentage = $partner->commission != 0 ? ($partner->commission / 100) : 0;
                $investorCount = $partner->investor->count();
                $commissionEarned = $totalInvestmentAmount * $commissionPercentage;
                // Add the calculated values to the partner model
                $partner->total_invested = $totalInvestmentAmount;
                $partner->investor_count = $investorCount;
                $partner->noOfStartups = $totalStartups;
                $partner->commission_earned = $commissionEarned;

                return $partner;
            });
            return UtillsHelper::json(
                1,
                [
                    'message' => 'List',
                    'data' => $list
                ],
                200
            );
        } else {
            $user = Auth::guard('partner')->user();
            if ($user->type == PartnerTypeEnum::retailer->value) {
                return redirect()->route('front.business.dashboard');
            }
            setPageTitle('Channel Partner');
            $data['list'] = PartnerModel::where('parent_id', $user->id);
            return view('front.partner.channelpartner.list', $data);
        }
    }

    function channelPartnerSave(): RedirectResponse|JsonResponse
    {
        $request = request();
        $user = $request->user();
        $rules = [
            'name' => 'required|string|max:255',
            'mobile_number' => [
                'required',
                'numeric',
                'digits:10',
                Rule::unique((new PartnerModel())->getTable())->where(function ($query) {
                    return $query->where('is_deleted', '0');
                }),
            ],
            'email' => [
                'required',
                'email',
                Rule::unique((new PartnerModel)->getTable())->where(function ($query) {
                    return $query->where('is_deleted', '0');
                }),
            ],
            'commission' => 'required|numeric|between:0,99.99',
            'gender'        => 'required',
        ];
        $rules['partner'] = 'required';
        $rules['password'] = 'required';
        $validation = Validator::make($request->all(), $rules, []);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }
        if ($request->commission > $user->commission) {
            return UtillsHelper::json(0, ['message' => 'Your channel partner’s commission must be less than or equal to your own commission. You have entered ' . $request->commission . ' for your channel partner, while your commission is ' . $user->commission]);
        }
        $partner = new PartnerModel();
        $partner->name = $request->name;
        $partner->mobile_number = $request->mobile_number;
        $partner->email = $request->email;
        $partner->password = Hash::make($request->password);
        $partner->commission = $request->commission;
        $partner->gender = $request->gender;
        $partner->parent_type = $user->type;
        $partner->parent_id = $user->id;
        $partner->ask_password_change = 1;
        $partner->type = $request->partner;
        $partner->is_primary_access = $user->is_primary_access;
        $partner->is_secondary_access = $user->is_secondary_access;
        $partner->is_preipo_access = $user->is_preipo_access;
        $partner->save();
        $this->createSelfInvestor($partner);
        return UtillsHelper::json(1, ['message' => 'Channel Partner Created']);
    }

    function changePasswordSave(): RedirectResponse|JsonResponse
    {
        $request = request();
        if ($request->is('api/*')) {
            $user = $request->user();
        } else {
            $user = Auth::guard('partner')->user();
        }
        $validation = Validator::make($request->all(), [
            'password'  => 'required',
            'cpassword' => 'required'
        ], [], [
            'password'                                  => 'Password',
            'cpassword'                                 => 'Confirm Password'
        ]);
        if ($validation->fails()) {
            if ($request->is('api/*')) {
                return UtillsHelper::json(
                    0,
                    [
                        'message' => $validation->errors()->first()
                    ],
                    200
                );
            } else {
                return redirect()->back()->with('error', $validation->errors()->first());
            }
        }

        $partner = PartnerModel::where('id', $user->id)->first();
        $partner->password = Hash::make($request->password);
        $partner->ask_password_change = 0;
        $partner->save();

        if ($request->is('api/*')) {
            return UtillsHelper::json(
                1,
                [
                    'message' => 'Password Changed'
                ],
                200
            );
        } else {
            return redirect()->back()->with('success', 'Password Changed');
        }
    }

    function investorKYCSubmit(): JsonResponse|RedirectResponse
    {
        $request = request();
        $rules = [
            'investor_id'   => 'required',
            'aadhar_front'  =>  'required|file|mimes:' . CommonHelper::appSettings('file_image_extensions_allowed') . '|max:' . UtillsHelper::maxFileImageSizeInKB(),
            'aadhar_back'   =>  'required|file|mimes:' . CommonHelper::appSettings('file_image_extensions_allowed') . '|max:' . UtillsHelper::maxFileImageSizeInKB(),
            'pan_card'      =>  'required|file|mimes:' . CommonHelper::appSettings('file_image_extensions_allowed') . '|max:' . UtillsHelper::maxFileImageSizeInKB(),
        ];
        $validation = Validator::make($request->all(), $rules);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $kyc = new InvestorKycModel();
        $kyc->investor_id = $request->investor_id;

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

        $kyc->status = StatusEnum::pending->value;
        $kyc->save();
        return UtillsHelper::json(1, ['message' => 'KYC Sent for verification.']);
    }
    public function forgot(): JsonResponse|RedirectResponse
    {
        $request = request();
        $validation = Validator::make($request->all(), [
            'mobile_no' => 'required|numeric|digits:10'
        ], [], [
            'mobile_no'                                  => 'Mobile number is required'
        ]);
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $partner = PartnerModel::where('is_deleted', '0')->where('mobile_number', $request->mobile_no)->first();
        if (!$partner) {
            return UtillsHelper::json(0, ['message' => 'Mobile number not registered. Create a new account']);
        } else {
            UtillsHelper::sendVerificationCode($partner->id, PartnerModel::class, $request->mobile_no, CodeVerificationTypeEnum::forgot_password);
            if ($request->is('api/*')) {
                return UtillsHelper::json(1, ['message' => 'Verification code sent to ' . $request->mobile_no, 'data' => $partner]);
            } else {

                Session::put('partner_forget_id', $partner->id);
                return UtillsHelper::json(1, ['view' => view('front.common.auth.verifyotp', [
                    'mobile_no' => $request->mobile_no,
                    'form_route' => route('front.business.auth.post.forgot.verifyotp'),
                    'resend_route' => route('front.business.auth.post.forgot.resendotp')
                ])->render()]);
            }
        }
    }

    public function verifyOtp(): JsonResponse
    {
        $request = request();
        if ($request->is('api/*')) {
            $validation = Validator::make($request->all(), [
                'partner_id'        => 'required',
                'otp'                => 'required'
            ]);
            if ($validation->fails()) {
                return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
            }
            $partner = PartnerModel::where('id', $request->partner_id)->first();
            if (!$partner) {
                return UtillsHelper::json(0, ['message' => 'Partner not found']);
            } else {
                $code = UtillsHelper::getVerificationCode($request->otp, $partner->id, PartnerModel::class, CodeVerificationTypeEnum::forgot_password);
                if (!$code) {
                    return UtillsHelper::json(0, ['message' => 'Verification code is not valid']);
                } else {
                    $code->is_used = '1';
                    $code->save();
                    return UtillsHelper::json(1, ['message' => 'OTP is Verified , Change Your Password']);
                }
            }
        } else {
            if (!Session::has('partner_forget_id')) {
                return UtillsHelper::json(0, ['reset' => true]);
            }
            $partner = PartnerModel::where('id', Session::get('partner_forget_id'))->first();
            if (!$partner) {
                return UtillsHelper::json(0, ['reset' => true]);
            } else {
                $validation = Validator::make($request->all(), [
                    'otp' => 'required'
                ], [], [
                    'otp'                                  => 'Verification code is required'
                ]);
                if ($validation->fails()) {
                    return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
                }
                $code = UtillsHelper::getVerificationCode($request->otp, $partner->id, PartnerModel::class, CodeVerificationTypeEnum::forgot_password);
                if (!$code) {
                    return UtillsHelper::json(0, ['message' => 'Verification code is not valid']);
                } else {
                    $code->is_used = '1';
                    $code->save();
                    return UtillsHelper::json(1, ['view' => view('front.common.auth.change-password', [
                        'title' => 'Change your password',
                        'action'    => route('front.business.auth.post.forgot.changepassword'),
                        'redirect'  => route('front.business.auth.login')
                    ])->render()]);
                }
            }
        }
    }

    public function resendOtp(): JsonResponse
    {
        $request = request();
        if ($request->is('api/*')) {
            $validation = Validator::make($request->all(), [
                'partner_id'        => 'required'
            ]);

            if ($validation->fails()) {
                return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
            }
            $partner = PartnerModel::where('id', $request->partner_id)->first();
            if (!$partner) {
                return UtillsHelper::json(0, ['message' => 'Partner not found']);
            } else {
                UtillsHelper::sendVerificationCode($partner->id, PartnerModel::class, $partner->mobile_number, CodeVerificationTypeEnum::forgot_password);
                return UtillsHelper::json(1, ['message' => 'Verification code sent to ' . $partner->mobile_number]);
            }
        } else {
            if (!Session::has('partner_forget_id')) {
                return UtillsHelper::json(0, ['reset' => true]);
            }

            $partner = PartnerModel::where('id', Session::get('partner_forget_id'))->first();
            if (!$partner) {
                return UtillsHelper::json(0, ['reset' => true]);
            } else {
                UtillsHelper::sendVerificationCode($partner->id, PartnerModel::class, $partner->mobile_number, CodeVerificationTypeEnum::forgot_password);
                return UtillsHelper::json(1, ['message' => 'Verification code sent to ' . $partner->mobile_number]);
            }
        }
    }

    public function changePassword(): JsonResponse
    {
        $request = request();
        if ($request->is('api/*')) {
            $validation = Validator::make($request->all(), [
                'partner_id'        => 'required',
                'password'          => 'required'
            ]);
            if ($validation->fails()) {
                return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
            }
            $partner = PartnerModel::where('id', $request->partner_id)->first();
            if (!$partner) {
                return UtillsHelper::json(0, ['message' => 'Partner not found']);
            } else {
                $partner->password = Hash::make($request->password);
                $partner->save();
                return UtillsHelper::json(1, ['message' => 'Password reset success.']);
            }
        } else {
            if (!Session::has('partner_forget_id')) {
                return UtillsHelper::json(0, ['reset' => true]);
            }
            $partner = PartnerModel::where('id', Session::get('partner_forget_id'))->first();
            if (!$partner) {
                return UtillsHelper::json(0, ['reset' => true]);
            } else {
                $validation = Validator::make($request->all(), [
                    'password'  => 'required',
                    'cpassword' => 'required'
                ], [], [
                    'password'                                  => 'Password is required',
                    'cpassword'                                 => 'Confirm Password is required'
                ]);
                if ($validation->fails()) {
                    return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
                }

                $partner->password = Hash::make($request->password);
                $partner->ask_password_change = 0;
                $partner->save();

                Session::forget('partner_forget_id');
                Session::flash('success', 'Password changed');
                return UtillsHelper::json(1, ['message' => 'Password reset success.', 'data'  => $partner]);
            }
        }
    }


    function profile(): View|JsonResponse
    {
        $request = request();
        $partner = PartnerModel::where('id', $request->user()->id)->with(['country', 'city', 'state'])->first();
        $this->attachSelfInvestorId($partner);
        return UtillsHelper::json(
            1,
            [
                'message' => 'Profile',
                'data' => $partner
            ],
            200
        );
    }

    function profileSave(): JsonResponse|RedirectResponse
    {
        $request = request();
        if ($request->is('api/*')) {
            $user = $request->user();
        } else {
            $user = Auth::guard('partner')->user();
        }
        $validation = Validator::make($request->all(), [
            'email'     => [
                'required',
                Rule::unique((new PartnerModel())->getTable())->where(function ($query) use ($user) {
                    return $query->where('is_deleted', '0')->where('id', '!=', $user->id);
                })
            ]
        ]);
        if ($validation->fails()) {
            if ($request->is('api/*')) {
                return UtillsHelper::json(
                    0,
                    [
                        'message' => $validation->errors()->first()
                    ],
                    200
                );
            } else {
                return redirect()->back()->withInput()->with('error', $validation->errors()->first());
            }
        }


        $partner = PartnerModel::where('id', $user->id)->first();
        $partner->email = $request->email;
        $partner->save();
        if ($request->is('api/*')) {
            return UtillsHelper::json(
                1,
                [
                    'message' => 'Profile Updated'
                ],
                200
            );
        } else {
            return redirect()->back()->with('success', 'Profile Updated');
        }
    }

    function documents(): View|JsonResponse
    {
        $request = request();
        if ($request->is('api/*')) {
            $user = $request->user();
        } else {
            $user = Auth::guard('partner')->user();
        }
        $investorIds = InvestorModel::where('partner_id', $user->id)->where('is_deleted', '0')->pluck('id')->toArray();
        if (!empty($investorIds)) {
            $documents = DocumentsModel::where('status', '1')
                ->where(function ($query) use ($investorIds) {
                    foreach ($investorIds as $investorId) {
                        $query->orWhereJsonContains('meta->investor', $investorId);
                    }
                });
        } else {
            $documents = collect();
        }

        if ($request->is('api/*')) {
            return UtillsHelper::json(
                1,
                [
                    'message'   => 'Document',
                    'data'      => $documents->count() > 0 ? $documents->get() : [],
                ],
                200
            );
        } else {
            $data['documents'] = $documents;
            return view('front.common.document', $data);
        }
    }

    function notifications(): View|JsonResponse
    {
        $request = request();
        if ($request->is('api/*')) {
            $user = $request->user();
        } else {
            $user = Auth::guard('partner')->user();
        }
        NotificationsModel::where('user_id', $user->id)->where('user_type', PartnerModel::class)->update(['is_readed' => '1']);
        $notificationList = NotificationsModel::where('user_id', $user->id)->where('user_type', PartnerModel::class)->orderby('id', 'desc')->limit(200);
        if ($request->is('api/*')) {
            return UtillsHelper::json(
                1,
                [
                    'message' => 'Notifications',
                    'data' => $notificationList->get()
                ],
                200
            );
        } else {
            setPageTitle('Notifications');
            $data['list'] = $notificationList;
            return view('front.common.notifications', $data);
        }
    }


    function newDistributorSave(): JsonResponse|RedirectResponse
    {
        $request = request();
        $uuid = $request->route('uuid') ?? false;
        if ($request->has('partner_id')) {
            $partner = PartnerModel::find($request->input('partner_id'));
            if (!$partner) {
                if ($request->is('api/*')) {
                    return UtillsHelper::json(0, ['message' => 'Partner Not found']);
                }
                return redirect()->back()->with('error', 'Partner not found');
            }
            $uuid = $partner->uuid;
        }

        $request->merge([
            'is_primary_access' => $request->boolean('is_primary_access'),
            'is_secondary_access' => $request->boolean('is_secondary_access'),
            'is_preipo_access' => $request->boolean('is_preipo_access'),
        ]);

        $rules = [
            'partner_type' => ['required', new Enum(PartnerTypeEnum::class)],
            'name'          => 'required|string|max:255',
            'mobile_number' => [
                'required',
                'numeric',
                'digits:10',
                Rule::unique((new PartnerModel())->getTable())->where(function ($query) use ($uuid) {
                    if ($uuid) {
                        return $query->where('is_deleted', '0')->where('uuid', '!=', $uuid);
                    }
                    return $query->where('is_deleted', '0');
                }),
            ],
            'email' => [
                'required',
                'email',
                Rule::unique((new PartnerModel)->getTable())->where(function ($query) use ($uuid) {
                    if ($uuid) {
                        return $query->where('is_deleted', '0')->where('uuid', '!=', $uuid);
                    }
                    return $query->where('is_deleted', '0');
                }),
            ],
            'gender'                => 'required',
            'is_primary_access'     => 'required|boolean',
            'is_secondary_access'   => 'required|boolean',
            'is_preipo_access'      => 'required|boolean',
        ];

        if ($request->is('api/*') && $request->partner_type == PartnerTypeEnum::institution->value) {
            return UtillsHelper::json(0, ['message' => 'Institution cannot be created from the partner API']);
        }

        if (in_array($request->partner_type, [PartnerTypeEnum::retailer->value, PartnerTypeEnum::distributor->value, PartnerTypeEnum::wealthmanager->value, PartnerTypeEnum::institution->value])) {
            $rules['commission'] = 'required|numeric|between:0,99.99';
        }

        if (!$uuid) {
            $rules['password'] = 'required';
        }

        if ($request->partner_type == PartnerTypeEnum::relationmanager->value || $request->is('api/*')) {
            $rules['parent_partner_id'] = 'required';
        }

        $requiresSelfInvestorCml = $this->adminCreateRequiresSelfInvestorCml($request, $uuid);
        $cmlMessages = [];
        $cmlAttributes = [];
        if ($requiresSelfInvestorCml) {
            $rules['cml_file'] = 'required|file|mimes:pdf|max:10000';
            $rules['dp_id'] = 'required';
            $rules['client_id'] = 'required';
            $rules['pan_no'] = 'required';
            $rules['kyc_name'] = 'required';
            $rules['account_number'] = 'required';
            $rules['ifsc_code'] = 'required';
            $rules['bank_name'] = 'nullable';
            $rules['dob'] = 'nullable|date';
            $cmlMessages = [
                'cml_file.required' => 'The CML file is required.',
                'cml_file.file' => 'The CML must be a valid file.',
                'cml_file.mimes' => 'The CML must be a PDF file.',
                'cml_file.max' => 'The CML file may not be greater than 10 MB.',
            ];
            $cmlAttributes = [
                'kyc_name' => 'name',
                'cml_file' => 'CML file',
            ];
        }

        $validator = Validator::make($request->all(), $rules, $cmlMessages, $cmlAttributes);

        $validator->after(function ($validator) use ($request) {
            $accessFields = [
                $request->input('is_primary_access'),
                $request->input('is_secondary_access'),
                $request->input('is_preipo_access'),
            ];

            if (!in_array(true, $accessFields, true)) {
                $validator->errors()->add(
                    'permission',
                    'At least one of Primary Startup, Secondary Startup, or Pre-IPO Companies permission must be selected.'
                );
            }
        });

        if ($validator->fails()) {
            if ($request->is('api/*')) {
                return UtillsHelper::json(0, ['message' => $validator->errors()->first()]);
            }

            return redirect()->back()
                ->withInput()
                ->withErrors($validator)
                ->with('error', 'Check form errors');
        }

        $parent = false;
        if ($request->has('parent_partner_id')) {
            $parent = PartnerModel::where('id', $request->parent_partner_id)->first();
            if ($request->has('commission') && $parent) {
                if ($request->commission > $parent->commission) {
                    if ($request->is('api/*')) {
                        return UtillsHelper::json(0, ['message' => 'The commission for a child channel partner must be less than that of the parent partner. You have entered ' . $request->commission . ' for child channel partner, while parent commission is ' . $parent->commission]);
                    }
                    return redirect()->back()->withInput()->with('error', 'The commission for a child channel partner must be less than that of the parent partner. You have entered ' . $request->commission . ' for child channel partner, while parent commission is ' . $parent->commission);
                }
            }
        }


        $inCmlTransaction = false;
        if ($requiresSelfInvestorCml) {
            DB::beginTransaction();
            $inCmlTransaction = true;
        }

        try {
        if ($uuid) {
            $partner = PartnerModel::where('uuid', $uuid)->first();
            if (!$partner) {
                if ($inCmlTransaction) {
                    DB::rollBack();
                    $inCmlTransaction = false;
                }
                if ($request->is('api/*')) {
                    return UtillsHelper::json(0, ['message' => 'Partner Not found']);
                }
                return redirect()->back()->with('error', 'Partner not found');
            }
        } else {
            $partner = new PartnerModel();
        }

        $partner->type = $request->partner_type;
        $partner->name = $request->name;
        $partner->mobile_number = $request->mobile_number;
        $partner->email = $request->email;
        $partner->commission = $request->commission ?? 0;
        if ($request->has('password')) {
            $partner->password = Hash::make($request->password);
        }
        if ($request->routeIs('*.admin.*')) {
            if ($request->has('password')) {
                $partner->ask_password_change = 1;
            }
        }



        $partner->gender = $request->gender;

        $partner->is_primary_access = $request->input('is_primary_access', 0);
        $partner->is_secondary_access = $request->input('is_secondary_access', 0);
        $partner->is_preipo_access = $request->input('is_preipo_access', 0);



        $partner->save();

        $partner->investor()->update([
            'is_primary_access' => $partner->is_primary_access,
            'is_secondary_access' => $partner->is_secondary_access,
            'is_preipo_access' => $partner->is_preipo_access,
        ]);


        if ($request->routeIs('*.admin.*')) {
            if ($request->routeIs('*.store')) {
                AdminHelper::logPut('Created partner', PartnerModel::class, $partner->id);
                $partner->created_by = Auth::guard('admin')->user()->id;
            }
            if ($request->routeIs('*.update')) {
                AdminHelper::logPut('Update partner', PartnerModel::class, $partner->id);
            }
            $partner->updated_by = Auth::guard('admin')->user()->id;
        }

        if ($parent) {
            $partner->parent_type = $parent->type;
            $partner->parent_id = $parent->id;
        }
        $partner->save();

        if (!$uuid) {
            $this->createSelfInvestor($partner);
            if ($requiresSelfInvestorCml) {
                $this->saveSelfInvestorDematKyc($partner);
            }
            $message = 'Partner Created';
        } else {
            $message = 'Partner Updated';
        }

        if ($inCmlTransaction) {
            DB::commit();
            $inCmlTransaction = false;
        }
        } catch (Throwable $e) {
            if (!$inCmlTransaction) {
                throw $e;
            }
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Partner create rolled back because CML KYC was not saved.', [
                'error' => $e->getMessage(),
            ]);
            $failureMessage = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Partner could not be created because CML KYC was not saved.';
            return redirect()->back()->withInput()->with('error', $failureMessage);
        }

        if ($request->is('api/*')) {
            return UtillsHelper::json(1, ['message' => $message]);
        }

        $routeMap = [
            PartnerTypeEnum::retailer->value => 'admin.partner.retailers.list',
            PartnerTypeEnum::distributor->value => 'admin.partner.distributor.list',
            PartnerTypeEnum::wealthmanager->value => 'admin.partner.wealthmanager.list',
            PartnerTypeEnum::institution->value => 'admin.partner.institution.list',
        ];

        $route = $routeMap[$request->partner_type] ?? 'admin.partner.relationalManager.list';

        return redirect()->route($route)->with('success', $message);
    }

    private function adminCreateRequiresSelfInvestorCml($request, $uuid): bool
    {
        if ($uuid || $request->is('api/*')) {
            return false;
        }

        if ($request->input('partner_type') === PartnerTypeEnum::relationmanager->value) {
            return false;
        }

        return in_array($request->input('partner_type'), [
            PartnerTypeEnum::wealthmanager->value,
            PartnerTypeEnum::distributor->value,
            PartnerTypeEnum::retailer->value,
            PartnerTypeEnum::institution->value,
        ], true);
    }

    private function saveSelfInvestorDematKyc(PartnerModel $partner): void
    {
        if ($partner->type === PartnerTypeEnum::relationmanager->value) {
            return;
        }

        $request = request();
        $investor = InvestorModel::where('partner_id', $partner->id)->where('is_self', 1)->first();
        if (!$investor) {
            throw new RuntimeException('Self investor was not created, so CML KYC could not be saved.');
        }

        $cmlFile = $request->file('cml_file');
        if (!$cmlFile) {
            throw new RuntimeException('The CML file is required.');
        }

        $result = (new DematKycService())->saveDematKyc($investor->id, [
            'dp_id' => $request->input('dp_id'),
            'client_id' => $request->input('client_id'),
            'pan_no' => $request->input('pan_no'),
            'name' => $request->input('kyc_name'),
            'account_number' => $request->input('account_number'),
            'ifsc_code' => $request->input('ifsc_code'),
            'bank_name' => $request->input('bank_name'),
            'dob' => $request->input('dob'),
        ], $cmlFile);

        if (empty($result['success'])) {
            $message = $result['message'] ?? 'Failed to save KYC details.';
            if (!empty($result['error'])) {
                $message .= ': ' . $result['error'];
            }
            throw new RuntimeException($message);
        }
    }

    public function createSelfInvestor(PartnerModel $partner): void
    {
        if ($partner->type === PartnerTypeEnum::relationmanager->value) {
            return;
        }

        $partner->refresh();

        if (InvestorModel::where('partner_id', $partner->id)->where('is_self', 1)->exists()) {
            return;
        }

        try {
            $investor = new InvestorModel();
            $investor->investor_type = InvestorTypeEnum::individual->value;
            $investor->name = $partner->name;
            $investor->mobile_country_code = $partner->mobile_country_code ?: '91';
            $investor->mobile_number = $partner->mobile_number;
            $investor->email = $partner->email;
            $investor->gender = $partner->gender;
            $investor->partner_id = $partner->id;
            $investor->is_self = 1;
            $investor->registration_step = '3';
            $investor->is_deleted = '0';
            if (!empty($partner->password) && Schema::hasColumn($investor->getTable(), 'password')) {
                $investor->password = $partner->password;
            }
            if (Schema::hasColumn($investor->getTable(), 'is_primary_access')) {
                $investor->is_primary_access = $partner->is_primary_access ? 1 : 0;
            }
            if (Schema::hasColumn($investor->getTable(), 'is_secondary_access')) {
                $investor->is_secondary_access = $partner->is_secondary_access ? 1 : 0;
            }
            if (Schema::hasColumn($investor->getTable(), 'is_preipo_access')) {
                $investor->is_preipo_access = $partner->is_preipo_access ? 1 : 0;
            }
            if ($partner->created_by) {
                $investor->created_by = $partner->created_by;
                $investor->updated_by = $partner->updated_by ?: $partner->created_by;
            }
            $investor->save();
        } catch (UniqueConstraintViolationException $e) {
            $this->logSkippedSelfInvestor($partner);
        } catch (QueryException $e) {
            $sqlState = $e->errorInfo[0] ?? null;
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if ($sqlState === '23000' || $driverCode === 1062) {
                $this->logSkippedSelfInvestor($partner);
                return;
            }
            throw $e;
        }
    }

    private function attachSelfInvestorId(?PartnerModel $partner): void
    {
        if (!$partner) {
            return;
        }

        if ($partner->type === PartnerTypeEnum::relationmanager->value) {
            $partner->setAttribute('self_investor_id', null);
            return;
        }

        $partner->setAttribute(
            'self_investor_id',
            InvestorModel::where('partner_id', $partner->id)->where('is_self', 1)->value('id')
        );
    }

    private function logSkippedSelfInvestor(PartnerModel $partner): void
    {
        Log::warning('Skipped self investor for partner ' . $partner->id . ' because mobile or email already belongs to another investor.', [
            'partner_id' => $partner->id,
            'partner_type' => $partner->type,
            'mobile_number' => $partner->mobile_number,
            'email' => $partner->email,
        ]);
    }
}
