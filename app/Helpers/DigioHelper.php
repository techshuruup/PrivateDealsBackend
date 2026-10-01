<?php

namespace App\Helpers;

use App\Enums\InvestorTypeEnum;
use App\Models\GlobalSettingModel;
use App\Models\StartupModel;
use App\Models\InvestorModel;
use App\Models\StartupSharePriceModel;
use App\Models\PrimaryTransactionModel;
use App\Models\ReportErrorLogModel;
use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class DigioHelper
{

    static function verifyPan($pan)
    {
        $postJson = [
            'id_no'   => $pan
        ];
        $client = new \GuzzleHttp\Client(['verify' => false, 'http_errors' => false]);
        try {
            $response = $client->post(CommonHelper::appSettings('digio_url') . 'v3/client/kyc/fetch_id_data/PAN', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode(CommonHelper::appSettings('digio_client_id') . ':' . CommonHelper::appSettings('digio_client_secret')),
                ],
                'json' => $postJson
            ]);
        } catch (Exception $e) {
            $response = $e->getMessage();
        }
        return $response;
    }

    public static function checkBankMandate($acno, $ifsc)
    {
        $postJson = [
            'beneficiary_account_no'   => $acno,
            'beneficiary_ifsc'         => $ifsc
        ];
        $client = new \GuzzleHttp\Client(['verify' => false, 'http_errors' => false]);
        try {
            $response = $client->post('https://api.digio.in/client/verify/bank_account', [
                // $client->post(CommonHelper::appSettings('digio_url').'client/verify/bank_account', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode('AI7USISBY4BLO1Z2Y3JKEENEJKBQROMN' . ':' . 'BGS6BB8I6PBT2Z73ZH6XY6H2S5EOPFIW'),
                ],
                'json' => $postJson
            ]);
        } catch (Exception $e) {
            $response = $e->getMessage();
        }
        return $response;
    }
    public static function createMandateForm($mob, $cusName, $acNo, $ifsc, $bankName, $investment)
    {
        $investment = PrimaryTransactionModel::where('id', $investment)->first();
        // $startup = StartUps::where('id',$investment->startup_id)->first();
        $postJson = [
            'customer_identifier'       => $mob,
            'auth_mode'                 => 'api',
            'mandate_type'              => "create",
            'corporate_config_id'       => 'TSE220331154058824QU3DUKMWU4O4AJ',
            'mandate_data'              => [
                'maximum_amount'            => $investment->investment_amount,
                'frequency'                 => 'Adhoc',
                'instrument_type'           => 'debit',
                'first_collection_date'     => date('Y-m-d'),
                'is_recurring'              => true,
                'management_category'       => 'C001',
                'customer_name'             => $cusName,
                'customer_account_number'   => $acNo,
                'destination_bank_id'       => $ifsc,
                'destination_bank_name'     => $bankName,
                'customer_account_type'     => 'savings',
                'final_collection_date'     => CommonHelper::dtplusDays(date('Y-m-d'), 180)
            ]
        ];
        $client = new \GuzzleHttp\Client(['verify' => false, 'http_errors' => false]);
        try {
            $response = $client->post('https://api.digio.in/v3/client/mandate/create_form', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode('AI7USISBY4BLO1Z2Y3JKEENEJKBQROMN' . ':' . 'BGS6BB8I6PBT2Z73ZH6XY6H2S5EOPFIW'),
                ],
                'json' => $postJson
            ]);
        } catch (Exception $e) {
            $response = $e->getMessage();
        }
        return $response;
    }

    public static function generateAuthToken($id)
    {
        $postJson = [
            'entity_id'       => $id
        ];
        $client = new \GuzzleHttp\Client(['verify' => false, 'http_errors' => false]);
        try {
            $response = $client->post('https://api.digio.in/user/auth/generate_token', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode('AI7USISBY4BLO1Z2Y3JKEENEJKBQROMN' . ':' . 'BGS6BB8I6PBT2Z73ZH6XY6H2S5EOPFIW'),
                ],
                'json' => $postJson
            ]);
        } catch (\Exception $e) {
            $response = $e->getMessage();
        }
        return $response;
    }

    public static function getMandate($id)
    {
        $postJson = [];
        $client = new \GuzzleHttp\Client(['verify' => false, 'http_errors' => false]);
        try {
            $response = $client->get('https://api.digio.in/v3/client/mandate/' . $id, [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode('AI7USISBY4BLO1Z2Y3JKEENEJKBQROMN' . ':' . 'BGS6BB8I6PBT2Z73ZH6XY6H2S5EOPFIW'),
                ],
                'json' => $postJson
            ]);
        } catch (\Exception $e) {
            $response = $e->getMessage();
        }
        return $response;
    }

    // final function
    static function getDocDetails($id)
    {
        $postJson = [];
        $client = new \GuzzleHttp\Client(['verify' => false, 'http_errors' => false]);
        try {
            $response = $client->get(CommonHelper::appSettings('digio_url') . 'v2/client/document/' . $id, [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode(CommonHelper::appSettings('digio_client_id') . ':' . CommonHelper::appSettings('digio_client_secret')),
                ],
                'json' => $postJson
            ]);
        } catch (\Exception $e) {
            $response = $e->getMessage();
        }
        return $response;
    }

    static function sendSHNow($transaction)
    {
        $startup = $transaction->startup;
        $buyer = $transaction->buyer;
        $seller = $transaction->seller;

        $postJson = [
            'templates'                 => [
                [
                    'template_key'          => 'TMP220914114921501HNO5I4DOYZBGT7',
                    'template_values'       => [
                        'ex_date'                   => date('d-m-Y'),
                        'cin_no'                    => $transaction->startup->legalInfo ? $transaction->startup->legalInfo->cin : 'NA',
                        'cname'                     => $transaction->startup->company_name,
                        'wt_name'                   => $transaction->startup->representative_name ?? $transaction->startup->brand_name,
                        'sh_type'                   => $transaction->instrument,
                        'face_value'                => 10,
                        'share_price'               => $transaction->share_price,
                        'share_price2'              => $transaction->share_price,
                        'shares'                    => $transaction->shares,
                        'shares_word'               => str_replace(' Rupees', '', UtillsHelper::getIndianCurrencyinWords($transaction->shares)),
                        'sh_amt'                    => ($transaction->shares * $transaction->share_price),
                        'sh_amt_wrd'                => str_replace(' Rupees', '', UtillsHelper::getIndianCurrencyinWords($transaction->investment_amount)),
                        'slr_name'                  => $seller->name,
                        'buy_name'                  => $buyer->name,
                        'sup_name'                  => '',
                        'buy_address'               => $buyer->address,
                        'occup'                     => $buyer->investor_type
                    ]
                ]
            ],
            'signers'                       => [
                [
                    'identifier'            => $seller->mobile_number,
                    'name'                  => $seller->name,
                    'sign_type'             => 'electronic'
                ],
                [
                    'identifier'            => $startup->mobile_number,
                    'name'                  => $startup->brand_name,
                    'sign_type'             => 'electronic'
                ],
                [
                    'identifier'            => $buyer->mobile_number,
                    'name'                  => $buyer->name,
                    'sign_type'             => 'electronic'
                ]
            ],
            'expire_in_days'            => '10',
            'display_on_page'           => "custom",
            'send_sign_link'            => true,
            'notify_signers'            => true,
            'sign_coordinates'          => [
                $seller->mobile_number         =>  [
                    "1"                 =>  [
                        [
                            'llx'       => 380.00021776998807,
                            'lly'       => 151.00415751892345,
                            'urx'       => 519.993609714659,
                            'ury'       => 190.9997150335878
                        ]
                    ]
                ],
                $startup->mobile_number         =>  [
                    "2"                 =>  [
                        [
                            'llx'       => 388.00035023337335,
                            'lly'       => 666.0063431975565,
                            'urx'       => 527.9937421780443,
                            'ury'       => 706.0001206865875
                        ]
                    ]
                ],
                $buyer->mobile_number        =>  [
                    "2"                 =>  [
                        [
                            'llx'       => 477.9998752509004,
                            'lly'       => 491.00529744374546,
                            'urx'       => 532.9985538503223,
                            'ury'       => 516.000292683022
                        ]
                    ]
                ]
            ]
        ];
        $client = new \GuzzleHttp\Client(['verify' => false, 'http_errors' => false]);
        try {
            $response = $client->post(CommonHelper::appSettings('digio_url') . 'v2/client/template/multi_templates/create_sign_request', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode(CommonHelper::appSettings('digio_client_id') . ':' . CommonHelper::appSettings('digio_client_secret')),
                ],
                'json' => $postJson
            ]);
        } catch (\Exception $e) {
            $response = $e->getMessage();
        }
        return $response;
    }

    static function sendPreIPODealSleepNow($transaction)
    {
        $investor = $transaction->investor;
        $investorMobile = $investor->mobile_number;
        $getPremium = 0;
        if ($transaction->company &&  $transaction->company->fundamentals && $transaction->company->fundamentals->face_value) {
            $getPremium = $transaction->share_price - $transaction->company->fundamentals->face_value;
        }
        $postJson = [
            'templates'                 => [
                [
                    'template_key'          => 'TMP241114163701551BIP1FY2J6PL6I6',
                    'template_values'       => [
                        'date'               => DateTimeHelper::viewDate($transaction->created_at),
                        'company_cin'                       => $transaction->company->cin ?? 'NA',
                        'company_legal_name'                => $transaction->company->company_name ?? 'NA',
                        'face_value'                        => $transaction->company->fundamentals->face_value ?? 'NA',
                        'shares'                            => $transaction->shares ?? 'NA',
                        'price'                             => $transaction->share_price ?? 'NA',
                        'invested'                          => $transaction->investment_amount ?? 'NA',
                        'premium'                           => $getPremium,
                        'seller_cin'                        => $transaction->seller?->cin ?? 'NA',
                        'seller_pan'                        => $transaction->seller?->pan ?? 'NA',
                        'seller_name'                       => $transaction->seller?->company_name ?? 'NA',
                        'seller_address'                    => $transaction->seller?->address ?? 'NA',
                        'seller_dpid'                       => $transaction->seller?->dp_id ?? 'NA',
                        'seller_clientid'                   => $transaction->seller?->client_id ?? 'NA',
                        'seller_bank'                       => $transaction->seller?->bank_name ?? 'NA',
                        'seller_ac_no'                      => $transaction->seller?->account_number ?? 'NA',
                        'seller_ifsc'                       => $transaction->seller?->ifsc ?? 'NA',
                        'seller_branch'                     => $transaction->seller?->branch ?? 'NA',
                        'buyer_email'                       => $transaction->investor->email ?? 'NA',
                        'buyer_name'                        => $transaction->investor->name  ?? 'NA',
                        'buyer_cin'                         => 'NA',
                        'buyer_address'                     => $transaction->investor->address ?? 'NA',
                        'buyer_pan'                         => $transaction->investor->newPan->pan_no ?? 'NA',
                        'buyer_demat_account_no'            => $transaction->investor->dematAccount->demat_account ?? 'NA',
                    ]
                ]
            ],
            'signers'                       => [
                [
                    'identifier'            => $investorMobile,
                    'name'                  => $investor->name,
                    'sign_type'             => 'electronic'
                ]
            ],
            'expire_in_days'            => '10',
            'display_on_page'           => "custom",
            'send_sign_link'            => true,
            'notify_signers'            => true,
            'sign_coordinates'          => [
                $investorMobile       =>  [
                    "2"                 =>  [
                        [
                            'llx'       => 78.00031132340936,
                            'lly'       => 103.00664573232837,
                            'urx'       => 217.99893927208885,
                            'ury'       => 143.0001145808905
                        ]
                    ]
                ]
            ]
        ];

        $client = new \GuzzleHttp\Client(['verify' => false, 'http_errors' => false]);
        try {
            $response = $client->post(CommonHelper::appSettings('digio_url') . 'v2/client/template/multi_templates/create_sign_request', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode(CommonHelper::appSettings('digio_client_id') . ':' . CommonHelper::appSettings('digio_client_secret')),
                ],
                'json' => $postJson
            ]);
        } catch (\Exception $e) {
            $response = $e->getMessage();
        }
        return $response;
    }

    static function cancelDigioRequest($docId): bool
    {
        $client = new \GuzzleHttp\Client(['verify' => false, 'http_errors' => false]);

        try {
            $response = $client->post(CommonHelper::appSettings('digio_url') . 'v2/client/document/' . $docId . '/cancel', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode(CommonHelper::appSettings('digio_client_id') . ':' . CommonHelper::appSettings('digio_client_secret')),
                ]
            ]);

            return $response->getStatusCode() == 200;
        } catch (\Exception $e) {
            ReportErrorLogModel::create([
                'type' => 'Digio',
                'subtype' => 'Cancel Request Error',
                'description' => $e->getMessage(),
                'notes' => 'Failed to cancel doc ID: ' . $docId
            ]);
            return false;
        }
    }

    static function sendLOINow($transaction)
    {
        $startup = $transaction->startup;
        $investor = $transaction->investor;

        $investorMobile = $investor->mobile_number;

        $postJson = [
            'templates'                 => [
                [
                    'template_key'          => 'TMP241213113752968PJAXULO9SNLKKE',
                    'template_values'       => [
                        'date'                  => DateTimeHelper::formatDateTime($transaction->created_at, 'd-m-Y'),
                        'investor_name'         => $investor->name,
                        'investor_address'      => $investor->address,
                        'startup_legal_name'    => $startup->legalInfo->company_name . '/' . $startup->brand_name,
                        'startup_address'       => $startup->address,
                        'startup_brand_name'    => $startup->brand_name,
                        'investment_amount'     => $transaction->investment_amount,
                        'investment_amount_in_words'  => CommonHelper::getIndianCurrencyinWords($transaction->investment_amount),
                        'payable_amount'       => $transaction->amount_payable,
                        'payable_amount_in_words'  => CommonHelper::getIndianCurrencyinWords($transaction->amount_payable),
                    ]
                ]
            ],
            'signers'                       => [
                [
                    'identifier'            => $investorMobile,
                    'name'                  => $investor->name,
                    'sign_type'             => 'electronic'
                ]
            ],
            'expire_in_days'            => '10',
            'display_on_page'           => "custom",
            'send_sign_link'            => true,
            'notify_signers'            => true,
            'sign_coordinates'          => [
                $investorMobile       =>  [
                    "5"                 =>  [
                        [
                            'llx'       => 110,
                            'lly'       => 525,
                            'urx'       => 250,
                            'ury'       => 565
                        ]
                    ]
                ]
            ]
        ];

        $client = new \GuzzleHttp\Client(['verify' => false, 'http_errors' => false]);
        try {
            $response = $client->post(CommonHelper::appSettings('digio_url') . 'v2/client/template/multi_templates/create_sign_request', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode(CommonHelper::appSettings('digio_client_id') . ':' . CommonHelper::appSettings('digio_client_secret')),
                ],
                'json' => $postJson
            ]);
        } catch (\Exception $e) {
            $response = $e->getMessage();
        }
        return $response;
    }

    static function sendSSANow($transaction)
    {
        $startup = $transaction->startup;
        $investor = $transaction->investor;
        if ($investor->investor_type == InvestorTypeEnum::individual) {
            $signBehalfdesc = 'Signed and delivered by';
            $invName = $investor->kyc->name_as_aadhar;
        } else {
            $invName = $investor->name;
            $signBehalfdesc = 'Signed and delivered by and on behalf of ' . $invName;
        }


        $authType = 'electronic';
        // if ($investor->kyc_type == '2') {
        //     $authType = 'aadhaar';
        // }

        // if($startup->is_public == '0'){
        //     $startUpMobile = Common::setting('demo_startup_mobile');
        //     $investorMobile = Common::setting('demo_investor_mobile');
        // }else{
        $startUpMobile = $startup->mobile_number;
        $investorMobile = $investor->mobile_number;
        //}

        $postJson = [
            'templates'                 => [
                [
                    'template_key'          => $startup->StartupOtherOne->ssa_id,
                    'template_values'       => [
                        'date'                  => date('d-m-Y'),
                        'investor_name'         => $invName,
                        'amount_number'         => $transaction->investment_amount,
                        'amount_word'           => CommonHelper::getIndianCurrencyinWords($transaction->investment_amount),
                        'investor_address'      => $investor->kyc->address_as_aadhar,
                        'behalf_description'    => $signBehalfdesc
                    ]
                ]
            ],
            'signers'                       => [
                [
                    'identifier'            => $startUpMobile,
                    'name'                  => $startup->company_name,
                    'sign_type'             => 'electronic'
                ],
                [
                    'identifier'            => $investorMobile,
                    'name'                  => $invName,
                    'sign_type'             => 'electronic'
                ]
            ],
            'expire_in_days'            => '10',
            'display_on_page'           => "custom",
            'send_sign_link'            => true,
            'notify_signers'            => true,
            'sign_coordinates'          => [
                $startUpMobile         =>  json_decode($startup->StartupOtherOne->ssa_sign_coordinates, true)[0],
                $investorMobile       =>  json_decode($startup->StartupOtherOne->ssa_sign_coordinates, true)[1]
            ]
        ];

        $client = new \GuzzleHttp\Client(['verify' => false, 'http_errors' => false]);
        try {
            $response = $client->post(CommonHelper::appSettings('digio_url') . 'v2/client/template/multi_templates/create_sign_request', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode(CommonHelper::appSettings('digio_client_id') . ':' . CommonHelper::appSettings('digio_client_secret')),
                ],
                'json' => $postJson
            ]);
        } catch (\Exception $e) {
            $response = $e->getMessage();
        }
        return $response;
    }

    static function sendOfferNow($transaction)
    {
        $startup = $transaction->startup;
        $investor = $transaction->investor;
        if ($investor->investor_type == InvestorTypeEnum::individual) {
            $signBehalfdesc = 'Signed and delivered by';
            $invName = $investor->kyc->name_as_aadhar;
        } else {
            $invName = $investor->name;
            $signBehalfdesc = 'Signed and delivered by and on behalf of ' . $invName;
        }


        $authType = 'electronic';
        // if ($investor->kyc_type == '2') {
        //     $authType = 'aadhaar';
        // }

        // if($startup->is_public == '0'){
        //     $startUpMobile = Common::setting('demo_startup_mobile');
        //     $investorMobile = Common::setting('demo_investor_mobile');
        // }else{
        $startUpMobile = $startup->mobile_number;
        $investorMobile = $investor->mobile_number;
        //}

        $postJson = [
            'templates'                 => [
                [
                    'template_key'          => $startup->StartupOtherOne->offer_id,
                    'template_values'       => [
                        'date'                      => date('d-m-Y'),
                        'serial'                    => $transaction->offerletterno,
                        'investor_name'             => $invName,
                        'amount_number'             => $transaction->investment_amount,
                        'amount_word'               => CommonHelper::getIndianCurrencyinWords($transaction->investment_amount),
                        'investor_address'          => $investor->kyc->address_as_aadhar,
                        'investor_mail'             => $investor->email,
                        'investor_pan'              => $investor->kyc->pan_no
                    ]
                ]
            ],
            'signers'                       => [
                [
                    'identifier'            => $startUpMobile,
                    'name'                  => $startup->company_name,
                    'sign_type'             => 'electronic'
                ],
                [
                    'identifier'            => $investorMobile,
                    'name'                  => $invName,
                    'sign_type'             => 'electronic'
                ]
            ],
            'expire_in_days'            => '10',
            'display_on_page'           => "custom",
            'send_sign_link'            => true,
            'notify_signers'            => true,
            'sign_coordinates'          => [
                $startUpMobile         =>  json_decode($startup->StartupOtherOne->offer_sign_coordinates, true)[0],
                $investorMobile       =>  json_decode($startup->StartupOtherOne->offer_sign_coordinates, true)[1]
            ]
        ];

        $client = new \GuzzleHttp\Client(['verify' => false, 'http_errors' => false]);
        try {
            $response = $client->post(CommonHelper::appSettings('digio_url') . 'v2/client/template/multi_templates/create_sign_request', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode(CommonHelper::appSettings('digio_client_id') . ':' . CommonHelper::appSettings('digio_client_secret')),
                ],
                'json' => $postJson
            ]);
        } catch (\Exception $e) {
            $response = $e->getMessage();
        }
        return $response;
    }

    static function downloadDocment($did)
    {
        $postJson = [];
        $client = new \GuzzleHttp\Client(['verify' => false, 'http_errors' => false]);
        try {
            $response = $client->get(CommonHelper::appSettings('digio_url') . 'v2/client/document/download?document_id=' . $did, [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode(CommonHelper::appSettings('digio_client_id') . ':' . CommonHelper::appSettings('digio_client_secret')),
                ],
                'json' => $postJson
            ]);
        } catch (\Exception $e) {
            $response = $e->getMessage();
        }
        return $response;
    }

    static function getTokensForDigioKYC($mobileNo)
    {
        $postJson = [
            'customer_identifier'   => $mobileNo,
            'template_name'         => CommonHelper::appSettings('digio_kyc_id'),
            'notify_customer'       => false,
            'generate_access_token' => true
        ];
        $client = new \GuzzleHttp\Client(['verify' => false, 'http_errors' => false]);
        try {
            $response = $client->post(CommonHelper::appSettings('digio_url') . 'client/kyc/v2/request/with_template', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode(CommonHelper::appSettings('digio_client_id') . ':' . CommonHelper::appSettings('digio_client_secret')),
                ],
                'json' => $postJson
            ]);
        } catch (\Exception $e) {
            $response = $e->getMessage();
            ReportErrorLogModel::create([
                'type'      => 'Guzzle - Digio',
                'subtype'   => 'e-KYC',
                'description'   => $response
            ]);
        }
        return $response;
    }

    static function getResponseDigioKYC($eid)
    {
        $postJson = [];
        $client = new \GuzzleHttp\Client(['verify' => false, 'http_errors' => false]);
        try {
            $response = $client->post(CommonHelper::appSettings('digio_url') . 'client/kyc/v2/' . $eid . '/response', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic '
                        . base64_encode(CommonHelper::appSettings('digio_client_id') . ':' . CommonHelper::appSettings('digio_client_secret')),
                ],
                'json' => $postJson
            ]);
        } catch (\Exception $e) {
            $response = $e->getMessage();
            ReportErrorLogModel::create([
                'type'      => 'Guzzle - Digio',
                'subtype'   => 'e-KYC',
                'description'   => $response
            ]);
        }
        return $response;
    }

    static function generateCustomDocument($apiPayload)
    {
        $client = new \GuzzleHttp\Client(['verify' => false, 'http_errors' => false]);
        try {
            $response = $client->post(CommonHelper::appSettings('digio_url') . 'v2/client/document/uploadpdf', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode(CommonHelper::appSettings('digio_client_id') . ':' . CommonHelper::appSettings('digio_client_secret')),
                ],
                'json' => $apiPayload
            ]);
        } catch (\Exception $e) {
            $response = $e->getMessage();
        }
        return $response;
    }

    public static function analyzeIdCard($frontFile, $doc_type, $backFile = null)
    {
        try {
            $client = new \GuzzleHttp\Client(['verify' => false, 'http_errors' => false]);

            if (!$frontFile || !$frontFile->isValid()) {
                Log::error('Invalid front file provided to analyzeIdCard');
                return null;
            }

            $multipart = [
                [
                    'name'     => 'front_part',
                    'contents' => $frontFile->get(),
                    'filename' => $frontFile->getClientOriginalName(),
                ],
                [
                    'name'     => 'unique_request_id',
                    'contents' => uniqid('kyc_')
                ],
                [
                    'name'     => 'additional_request',
                    'contents' => json_encode([
                        'features' => [
                            'MASK',
                            'CROP_ALIGN',
                            'VERIFY',
                            'SIGNATURE_EXTRACT',
                            'FACE_EXTRACT',
                            'SECURITY_FEATURE'
                        ],
                        'expected_ids' => [strtoupper($doc_type)],
                        'additional_checks' => ['BLUR_IMAGE', 'BLACK_AND_WHITE_IMAGE']
                    ])
                ]
            ];

            if ($backFile && $backFile->isValid()) {
                $multipart[] = [
                    'name'     => 'back_part',
                    'contents' => $backFile->get(),
                    'filename' => $backFile->getClientOriginalName(),
                ];
            }

            $response = $client->post('https://api.digio.in/v4/client/kyc/analyze/file/idcard', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode(
                        CommonHelper::appSettings('digio_client_id') . ':' . CommonHelper::appSettings('digio_client_secret')
                    ),
                    'Accept' => 'application/json',
                ],
                'multipart' => $multipart,
            ]);

            $responseBody = $response->getBody()->getContents();
            $responseData = json_decode($responseBody, true);

            Log::info('Digio API Response', [
                'status_code' => $response->getStatusCode(),
                'response' => $responseData
            ]);

            return $responseData;
        } catch (\Exception $e) {
            Log::error('Digio ID Card Analysis Failed: ' . $e->getMessage());
            return null;
        }
    }
}
