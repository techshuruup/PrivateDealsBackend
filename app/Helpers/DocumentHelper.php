<?php

namespace App\Helpers;

use App\Enums\DocumentTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\WpMessageTypeEnum;
use App\Models\DocumentsModel;
use App\Models\DocumentsSignersModel;
use App\Models\InvestorAifKycModel;
use App\Models\InvestorModel;
use App\Models\PreIpoModel;
use App\Models\ReportErrorLogModel;
use App\Services\PreIpoTimerService;
use App\Traits\FileUploadTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocumentHelper
{

    use FileUploadTrait;
    static function aifPPMDocumentSend(InvestorAifKycModel $kyc)
    {

        $documentRow = DocumentsModel::where('type', DocumentTypeEnum::ppm->value)->whereJsonContains('meta->aif_kyc', $kyc->id)->first();
        if ($documentRow) {
            $signers = [
                [
                    'identifier' => $kyc->investor->mobile_number,
                    'name' => $kyc->investor->name,
                    'sign_type' => 'electronic',  // Type of signature (aadhaar/electronic)
                    'reason' => 'AIF Onboarding PPM',
                    'user_id' => $kyc->investor->id,
                    'user_type' => InvestorModel::class
                ]
            ];
            $payload = [
                'signers' => $signers,
                'expire_in_days' => 90,
                'display_on_page' => 'all',  // Custom display option
                'notify_signers' => false,  // Notify signers
                'include_authentication_url' => true,
                "file_name" => $kyc->investor->name . "_aifOnboardPPM.pdf",
                'file_data' => base64_encode(file_get_contents(self::fileUrl($documentRow->path))),
                'sign_coordinates' => [
                    $kyc->investor->mobile_number => [
                        1 => [
                            [
                                'llx' => 6.999985653901641,
                                'lly' => 6.00835439433494,
                                'urx' => 146.99546999816394,
                                'ury' => 45.99961941995365
                            ]
                        ]
                    ]
                ],
            ];
            $sanitizedSigners = array_map(function ($signer) {
                return [
                    'identifier' => $signer['identifier'],
                    'name' => $signer['name'],
                    'sign_type' => $signer['sign_type'],
                    'reason' => $signer['reason']
                ];
            }, $payload['signers']);
            $apiPayload = array_merge($payload, ['signers' => $sanitizedSigners]);
            $request = DigioHelper::generateCustomDocument($apiPayload);
            if ($request->getStatusCode() == "200") {
                $getDocResponse = json_decode($request->getBody()->getContents(), true);
                $documentRow->api_id = $getDocResponse['id'];
                $documentRow->save();
                foreach ($payload['signers'] as $key => $signer) {
                    $signers = new DocumentsSignersModel();
                    $signers->document_id = $documentRow->id;
                    $signers->user_id = $signer['user_id'];
                    $signers->user_type = $signer['user_type'];
                    $signers->identifier = 'mobile';
                    $signers->identifier_value = $signer['identifier'];
                    $signers->link = $getDocResponse['signing_parties'][$key]['authentication_url'] ?? null;
                    $signers->expire_on = $getDocResponse['signing_parties'][$key]['expire_on'] ?? null;
                    $signers->save();

                    WhatsAppMessagesHelper::aifOnboardPPMSent($kyc->investor ?? null, $signers->link ?? null);
                }
            } else {
                $getDocResponse = json_decode($request->getBody()->getContents(), true);
                ReportErrorLogModel::create([
                    'type' => 'Digio document create',
                    'subtype'   => 'Aif PPM Document',
                    'description' => $getDocResponse['message'] ?? 'Unknown error',
                    'notes'         => 'kyc = ' . $kyc->id
                ]);
            }
        } else {
            ReportErrorLogModel::create(attributes: [
                'type'      => 'Digio document create',
                'subtype'   => 'Aif PPM Document',
                'description'   => 'Cant find document',
                'notes'         => 'kyc = ' . $kyc->id
            ]);
        }
    }
    static function aifCADocumentSend(InvestorAifKycModel $kyc)
    {
        $documentRow = DocumentsModel::where('type', DocumentTypeEnum::ca->value)->whereJsonContains('meta->aif_kyc', $kyc->id)->first();
        if ($documentRow) {
            $signers = [
                [
                    'identifier' => $kyc->investor->mobile_number,
                    'name' => $kyc->investor->name,
                    'sign_type' => 'electronic',  // Type of signature (aadhaar/electronic)
                    'reason' => 'AIF Onboarding CA',
                    'user_id' => $kyc->investor->id,
                    'user_type' => InvestorModel::class
                ]
            ];
            $payload = [
                'signers' => $signers,
                'expire_in_days' => 90,
                'display_on_page' => 'all',  // Custom display option
                'notify_signers' => false,  // Notify signers
                'include_authentication_url' => true,
                "file_name" => $kyc->investor->name . "_aifOnboardCA.pdf",
                'file_data' => base64_encode(file_get_contents(self::fileUrl($documentRow->path))),
                'sign_coordinates' => [
                    $kyc->investor->mobile_number => [
                        1 => [
                            [
                                'llx' => 6.999985653901641,
                                'lly' => 6.00835439433494,
                                'urx' => 146.99546999816394,
                                'ury' => 45.99961941995365
                            ]
                        ]
                    ]
                ],
            ];
            $sanitizedSigners = array_map(function ($signer) {
                return [
                    'identifier' => $signer['identifier'],
                    'name' => $signer['name'],
                    'sign_type' => $signer['sign_type'],
                    'reason' => $signer['reason']
                ];
            }, $payload['signers']);
            $apiPayload = array_merge($payload, ['signers' => $sanitizedSigners]);
            $request = DigioHelper::generateCustomDocument($apiPayload);
            if ($request->getStatusCode() == "200") {
                $getDocResponse = json_decode($request->getBody()->getContents(), true);
                $documentRow->api_id = $getDocResponse['id'];
                $documentRow->save();
                foreach ($payload['signers'] as $key => $signer) {
                    $signers = new DocumentsSignersModel();
                    $signers->document_id = $documentRow->id;
                    $signers->user_id = $signer['user_id'];
                    $signers->user_type = $signer['user_type'];
                    $signers->identifier = 'mobile';
                    $signers->identifier_value = $signer['identifier'];
                    $signers->link = $getDocResponse['signing_parties'][$key]['authentication_url'] ?? null;
                    $signers->expire_on = $getDocResponse['signing_parties'][$key]['expire_on'] ?? null;
                    $signers->save();

                    WhatsAppMessagesHelper::aifOnboardCASent($kyc->investor ?? null, $signers->link ?? null);
                }
            } else {

                $getDocResponse = json_decode($request->getBody()->getContents(), true);
                ReportErrorLogModel::create([
                    'type' => 'Digio document create',
                    'subtype'   => 'Aif CA Document',
                    'description' => $getDocResponse['message'] ?? 'Unknown error',
                    'notes'         => 'kyc = ' . $kyc->id
                ]);
            }
        } else {
            ReportErrorLogModel::create(attributes: [
                'type'      => 'Digio document create',
                'subtype'   => 'Aif CA Document',
                'description'   => 'Cant find document',
                'notes'         => 'kyc = ' . $kyc->id
            ]);
        }
    }

    static function dealSlipDocumentSend(PreIpoModel $transaction, $ignoreKycCheck = false): bool
    {
        if ($transaction->usesOrderStep()) {
            $ignoreKycCheck = true;
        }
        // Only send deal slip if KYC is done, unless ignoreKycCheck is true
        if (!$ignoreKycCheck && !($transaction->investor && $transaction->investor->preipo_kyc_status)) {
            Log::info('Deal slip not sent: Investor KYC not completed for transaction ID ' . $transaction->id);
            return false;
        }
        if (($transaction->seller || $transaction->seller_investor_id) && $transaction->investor) {
            $investor = $transaction->investor;
            $investorMobile = $investor->mobile_number;
            $getPremium = 0;
            if ($transaction->company && $transaction->company->fundamentals && $transaction->company->fundamentals->face_value) {
                $getPremium = $transaction->share_price - $transaction->company->fundamentals->face_value;
            }
            $sellerLines = self::dealSlipSellerLines($transaction);
            $signers = [
                [
                    'identifier' => $investorMobile,
                    'name' => $investor->name,
                    'sign_type' => 'electronic',  // Type of signature (aadhaar/electronic)
                    'reason' => 'Deal Slip of ' . $transaction->company->brand_name,
                    'user_id' => $investor->id,
                    'user_type' => InvestorModel::class
                ]
            ];
            $payload = [
                'templates'                 => [
                    [
                        'template_key'          => 'TMP241114163701551BIP1FY2J6PL6I6',
                        'template_values'       => [
                            'date'               => DateTimeHelper::viewDate($transaction->created_at),
                            'company_cin'                       => $transaction->company->cin ?? 'NA',
                            'company_legal_name'                => $transaction->company->company_name ?? 'NA',
                            'face_value'                        => $transaction->company?->fundamentals?->face_value ?? 'NA',
                            'shares'                            => $transaction->shares ?? 'NA',
                            'price'                             => $transaction->share_price ?? 'NA',
                            'invested'                          => $transaction->investment_amount ?? 'NA',
                            'premium'                           => $getPremium,
                            'seller_cin'                        => $sellerLines['seller_cin'],
                            'seller_pan'                        => $sellerLines['seller_pan'],
                            'seller_name'                       => $sellerLines['seller_name'],
                            'seller_address'                    => $sellerLines['seller_address'],
                            'seller_dpid'                       => $sellerLines['seller_dpid'],
                            'seller_clientid'                   => $sellerLines['seller_clientid'],
                            'seller_bank'                       => $sellerLines['seller_bank'],
                            'seller_ac_no'                      => $sellerLines['seller_ac_no'],
                            'seller_ifsc'                       => $sellerLines['seller_ifsc'],
                            'seller_branch'                     => $sellerLines['seller_branch'],
                            'buyer_email'                       => $transaction->investor->email ?? 'NA',
                            'buyer_name'                        => $transaction->investor->name  ?? 'NA',
                            'buyer_cin'                         => 'NA',
                            'buyer_address'                     => $transaction->investor->address ?? 'NA',
                            'buyer_pan'                         => $transaction->investor->newPan?->pan_no ?? 'NA',
                            'buyer_demat_account_no'            => $transaction->investor->dematAccount?->demat_account ?? 'NA',
                        ]
                    ]
                ],
                'signers'                       => $signers,
                'expire_in_days'            => '10',
                'display_on_page'           => "custom",
                'send_sign_link'            => true,
                'notify_signers'            => true,
                'include_authentication_url' => true,
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
            $sanitizedSigners = array_map(function ($signer) {
                return [
                    'identifier' => $signer['identifier'],
                    'name' => $signer['name'],
                    'sign_type' => $signer['sign_type'],
                    'reason' => $signer['reason']
                ];
            }, $payload['signers']);
            $postJson = array_merge($payload, ['signers' => $sanitizedSigners]);
            $result = self::digioCreateSignRequest($postJson);
            if ($result['ok']) {
                $getDocResponse = $result['body'];
                $offerId = $getDocResponse->id;
                $document = new DocumentsModel();
                $document->api_id = $offerId;
                $document->type = DocumentTypeEnum::preipodealslip;
                $document->meta = [
                    'name' => 'Deal slip - ' . $transaction->company->brand_name,
                    'investor' => [
                        $transaction->investor->id
                    ],
                    'preipo_transactions' => [
                        $transaction->id
                    ]
                ];
                $document->save();

                foreach ($payload['signers'] as $key => $signer) {
                    $signers = new DocumentsSignersModel();
                    $signers->document_id = $document->id;
                    $signers->user_id = $signer['user_id'];
                    $signers->user_type = $signer['user_type'];
                    $signers->identifier = 'mobile';
                    $signers->identifier_value = $signer['identifier'];
                    $signers->link = $getDocResponse->signing_parties[$key]->authentication_url ?? null;
                    $signers->expire_on = $getDocResponse->signing_parties[$key]->expire_on ?? null;
                    $signers->save();
                    // Log::info('Response Digio: ' . var_dump($getDocResponse));

                    $params = [
                        $transaction->transaction_invoice_no,
                        $transaction->company->brand_name,
                        $transaction->shares,
                        UtillsHelper::moneyFormatIndia($transaction->investment_amount),
                    ];

                    $skipInvestorMessage = $transaction->usesOrderStep() && (int) $transaction->investor->is_demo === 1;
                    if (!$skipInvestorMessage) {
                        UtillsHelper::sendWpMessage(
                            NotificationTypeEnum::event,
                            'preipo_on_dealslip_investor_sun_1',
                            WpMessageTypeEnum::text,
                            $transaction->investor->mobile_number,
                            $transaction->investor->name,
                            null,
                            [$signers->link],
                            $params
                        );

                        UtillsHelper::sendNotification(
                            $transaction->investor->id,
                            InvestorModel::class,
                            'preipo-transaction',
                            'Private Equity transaction',
                            UtillsHelper::paramsToTemplate(
                                $params,
                                'Your transaction #{{1}} has been approved and deal slip for the same has been generated. Kindly review and sign the deal slip via button, given below, at your earliest to continue with the process. Company: {{2}} Quantity: {{3}} Buying Price: {{4}} For any assistance related to this transaction, please contact our support team.'
                            )
                        );
                    }
                }

                if (!$transaction->usesOrderStep()) {
                    $transaction->status = 2;
                    $transaction->save();
                }

                return true;
            }

            ReportErrorLogModel::create([
                'type' => 'Digio',
                'subtype'   => 'Pre-IPO Send api error',
                'description'   => self::digioErrorDescription($result),
                'notes'         => 'transaction = ' . $transaction->id
            ]);
            return false;
        }

        return false;
    }

    static function sendBuyMandate(PreIpoModel $transaction): bool
    {
        $transaction->loadMissing([
            'company.fundamentals',
            'investor.newPan',
            'sellerInvestor.dematAccount',
            'sellerInvestor.newBankAccount',
            'sellerInvestor.newPan',
        ]);

        if (!$transaction->investor || !$transaction->company) {
            self::logDigioError('Buy mandate missing investor or company', 'transaction = '.$transaction->id);
            return false;
        }

        $investor = $transaction->investor;
        $investorMobile = $investor->mobile_number;
        $faceValue = $transaction->company->fundamentals?->face_value ?? null;
        $premium = ($faceValue !== null && $faceValue !== '') ? ($transaction->share_price - $faceValue) : 0;
        $mandateDate = DateTimeHelper::viewDate($transaction->created_at);
        $expireDate = $transaction->created_at
            ? DateTimeHelper::viewDate(Carbon::parse($transaction->created_at)->addDays(7))
            : '';
        $seller = $transaction->sellerInvestor;
        $demat = $seller?->dematAccount;
        $bank = $seller?->newBankAccount;
        $instrument = $transaction->instrument;
        if ($instrument instanceof \BackedEnum) {
            $instrument = $instrument->value;
        }

        $signers = [[
            'identifier' => $investorMobile,
            'name' => $investor->name,
            'sign_type' => 'electronic',
            'reason' => 'Buy mandate of '.$transaction->company->brand_name,
            'user_id' => $investor->id,
            'user_type' => InvestorModel::class,
        ]];

        $payload = [
            'templates' => [[
                'template_key' => 'TMP261001115614894TJQ9LO6JORA2OZ',
                'template_values' => [
                    'company_legal_name' => self::na($transaction->company->company_name),
                    'company_type_of_shares' => self::na($instrument),
                    'face_value' => self::na($faceValue),
                    'qty' => self::na($transaction->shares),
                    'share_price' => self::na($transaction->share_price),
                    'premium_price' => $premium,
                    'mandate_date' => self::na($mandateDate),
                    'mandate_reference_number' => self::na($transaction->transaction_invoice_no),
                    'mandate_expire_date' => self::na($expireDate),
                    'investor_name' => self::na($investor->name),
                    'investor_email' => self::na($investor->email),
                    'investor_mobile' => self::na($investor->mobile_number),
                    'investor_address' => self::na($investor->address),
                    'investor_pan' => self::na($investor->newPan?->pan_no),
                    'investor_dob' => self::na($investor->newPan?->dob),
                    'investor_type' => self::na($investor->investor_type),
                    'seller_name' => self::na($seller?->name),
                    'seller_dpid' => self::na($demat?->dp_id),
                    'seller_clientid' => self::na($demat?->client_id),
                    'seller_demat_no' => self::na($demat?->demat_account),
                    'seller_bank_ifsc' => self::na($bank?->ifsc_code),
                    'seller_bank_ac_no' => self::na($bank?->account_number),
                ],
            ]],
            'signers' => $signers,
            'expire_in_days' => '10',
            'display_on_page' => 'custom',
            'send_sign_link' => true,
            'notify_signers' => true,
            'include_authentication_url' => true,
            'sign_coordinates' => [
                $investorMobile => [
                    '5' => [[
                        'llx' => 173,
                        'lly' => 562,
                        'urx' => 303,
                        'ury' => 607,
                    ]],
                ],
            ],
        ];

        $sanitizedSigners = array_map(function ($signer) {
            return [
                'identifier' => $signer['identifier'],
                'name' => $signer['name'],
                'sign_type' => $signer['sign_type'],
                'reason' => $signer['reason'],
            ];
        }, $payload['signers']);
        $postJson = array_merge($payload, ['signers' => $sanitizedSigners]);
        $result = self::digioCreateSignRequest($postJson);
        if (!$result['ok'] || empty($result['body']->id)) {
            self::logDigioError(self::digioErrorDescription($result), 'transaction = '.$transaction->id);
            return false;
        }

        $getDocResponse = $result['body'];
        $document = new DocumentsModel();
        $document->api_id = $getDocResponse->id;
        $document->type = DocumentTypeEnum::buymandate;
        $document->meta = [
            'name' => 'Buy mandate - '.$transaction->company->brand_name,
            'investor' => [$investor->id],
            'preipo_transactions' => [$transaction->id],
        ];
        $document->save();

        $parties = $getDocResponse->signing_parties ?? [];
        foreach ($payload['signers'] as $key => $signer) {
            $party = $parties[$key] ?? null;
            $row = new DocumentsSignersModel();
            $row->document_id = $document->id;
            $row->user_id = $signer['user_id'];
            $row->user_type = $signer['user_type'];
            $row->identifier = 'mobile';
            $row->identifier_value = $signer['identifier'];
            $row->link = is_object($party) ? ($party->authentication_url ?? null) : null;
            $row->expire_on = is_object($party) ? ($party->expire_on ?? null) : null;
            $row->save();
        }

        return true;
    }

    static function digioCreateSignRequest(array $postJson): array
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic '.base64_encode(CommonHelper::appSettings('digio_client_id').':'.CommonHelper::appSettings('digio_client_secret')),
                ])
                ->post(CommonHelper::appSettings('digio_url').'v2/client/template/multi_templates/create_sign_request', $postJson);
        } catch (Throwable $e) {
            return ['ok' => false, 'body' => null, 'error' => $e->getMessage()];
        }

        $decoded = json_decode($response->body());
        if ($response->status() === 200 && is_object($decoded) && !empty($decoded->id)) {
            return ['ok' => true, 'body' => $decoded, 'error' => null];
        }

        return ['ok' => false, 'body' => $decoded, 'error' => $decoded ?: $response->body()];
    }

    private static function dealSlipSellerLines(PreIpoModel $transaction): array
    {
        if ($transaction->usesOrderStep()) {
            $seller = $transaction->sellerInvestor;
            $demat = $seller?->dematAccount;
            $bank = $seller?->newBankAccount;

            return [
                'seller_cin' => 'NA',
                'seller_pan' => self::na($seller?->newPan?->pan_no),
                'seller_name' => self::na($seller?->name),
                'seller_address' => self::na($seller?->address),
                'seller_dpid' => self::na($demat?->dp_id),
                'seller_clientid' => self::na($demat?->client_id),
                'seller_bank' => self::na($bank?->bank_name),
                'seller_ac_no' => self::na($bank?->account_number),
                'seller_ifsc' => self::na($bank?->ifsc_code),
                'seller_branch' => 'NA',
            ];
        }

        return [
            'seller_cin' => $transaction->seller?->cin ?? 'NA',
            'seller_pan' => $transaction->seller?->pan ?? 'NA',
            'seller_name' => $transaction->seller?->company_name ?? 'NA',
            'seller_address' => $transaction->seller?->address ?? 'NA',
            'seller_dpid' => $transaction->seller?->dp_id ?? 'NA',
            'seller_clientid' => $transaction->seller?->client_id ?? 'NA',
            'seller_bank' => $transaction->seller?->bank_name ?? 'NA',
            'seller_ac_no' => $transaction->seller?->account_number ?? 'NA',
            'seller_ifsc' => $transaction->seller?->ifsc ?? 'NA',
            'seller_branch' => $transaction->seller?->branch ?? 'NA',
        ];
    }

    private static function na(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'NA';
        }

        return (string) $value;
    }

    private static function digioErrorDescription(array $result): string
    {
        $error = $result['error'] ?? $result['body'] ?? 'Digio request failed';
        if (is_string($error)) {
            return $error;
        }

        return json_encode($error) ?: 'Digio request failed';
    }

    private static function logDigioError(string $description, string $notes): void
    {
        ReportErrorLogModel::create([
            'type' => 'Digio',
            'subtype' => 'Pre-IPO Send api error',
            'description' => $description,
            'notes' => $notes,
        ]);
    }
}
