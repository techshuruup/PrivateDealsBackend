<?php

namespace App\Helpers;

use App\Enums\GenderEnum;
use App\Enums\InstrumentTypeEnum;
use App\Enums\InvestorTypeEnum;
use App\Enums\MessagesStatusEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\PrimaryTransactionTypeEnum;
use App\Enums\Utills\CodeVerificationTypeEnum;
use App\Enums\Utills\CommunicationType;
use App\Enums\Utills\DeviceTypeEnum;
use App\Enums\Utills\StatusEnum;
use App\Enums\WpMessageTypeEnum;
use App\Jobs\FirebasePushNotificationSendJob;
use App\Models\BankDetailsModel;
use App\Models\CoreFirebaseDeviceTokenModel;
use App\Models\DynamicUrlModel;
use App\Models\InvestorDematAccountModel;
use App\Models\InvestorKycModel;
use App\Models\InvestorModel;
use App\Models\InvestorConsultancySlotModel;
use App\Models\InvestorPanDetailsModel;
use App\Models\InvestorRegisterRequestModel;
use App\Models\MasterCityModel;
use App\Models\MasterSectorsModel;
use App\Models\MasterSocialmediaLinkModel;
use App\Models\NotificationsModel;
use App\Models\PartnerModel;
use App\Models\PortfolioModel;
use App\Models\PortfolioPreIpoModel;
use App\Models\PreIpoModel;
use App\Models\ReportMessagesWhatsappModel;
use App\Models\ReportsVerificationCodeModel;
use App\Models\SecondaryTransactionModel;
use App\Models\StartupManageCaptableModel;
use App\Models\StartupModel;
use App\Models\StartupRoundModel;
use App\Models\StartupSharePriceModel;
use App\Traits\WhatsAppSendTrait;
use Carbon\Carbon;
use Google\Service\PeopleService\Gender;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class UtillsHelper
{

    use WhatsAppSendTrait;

    static function getProperFunctionForAppLogs($url): string
    {
        $url = str_replace('https://www.shuruup.com/api', '', $url);

        // Extract version before removing it
        $version = '';
        if (str_contains($url, '/v1')) {
            $version = 'v1';
        } elseif (str_contains($url, '/v2')) {
            $version = 'v2';
        }

        $url = str_replace('/v1', '', $url);
        $url = str_replace('/v2', '', $url);
        $url = str_replace('/investor', '', $url);
        $url = str_replace('/business', '', $url);
        $url = ltrim($url, '/');

        // Prepend version if found
        if ($version) {
            $url = $version . '/' . $url;
        }

        return $url;
    }

    static function getSocialMediaType(): Collection
    {
        return MasterSocialmediaLinkModel::where('is_deleted', '0')->get();
    }

    static function sendNotification(
        string $user_id,
        string $user_class_name,
        string $url,
        string $title,
        string $content,
        array $payload = [],
    ): void {
        $notification = new NotificationsModel;
        $notification->user_id = $user_id;
        $notification->user_type = $user_class_name;
        $notification->url = $url;
        $notification->title = $title;
        $notification->body = $content;
        $notification->payload = $payload;
        $notification->save();

        // FirebasePushNotificationSendJob::dispatch($notification->id);    
    }

    static function sendWpMessage(
        NotificationTypeEnum $type,
        string $template_name,
        WpMessageTypeEnum $template_type,
        string $destination_mobile,
        string $destination_name,
        string $media_url           = NULL,
        array $click_url           = [],
        array $params               = [],
        array $message_data         = [],
        $broadcast_id        = NULL,
        bool $send_now       = true,
        $reference_id = NULL,
        string $reference_model = NULL,
        int $mobile_country_code = 91
    ): void {
        self::sendWpMessageTrait($type, $template_name, $template_type, $destination_mobile,  $destination_name, $media_url, $click_url, $params, $message_data, $broadcast_id, $send_now, $reference_id, $reference_model, $mobile_country_code);
    }

    public static function userModelToUserType($model): string
    {
        if ($model == InvestorModel::class) {
            return 'Investor';
        } elseif ($model == PartnerModel::class) {
            return 'Distributor';
        } else {
            return 'NA';
        }
    }

    public static function percentageCalculator($total, $val)
    {
        if ($total == 0 && $val == 0) {
            return 0; // Both are 0, so percentage is 0
        }

        return $total > 0
            ? round(($val * 100) / $total, 2)
            : 0; // Calculate percentage or return 0 if pendingCount is 0
    }
    static function sendVerificationCode(
        string $user_id,
        string $user_class_name,
        string $mobile_no,
        CodeVerificationTypeEnum $codeVerificationTypeEnum,
        int $mobile_country_code = 91
    ): string {
        $otp = mt_rand(100000, 999999);
        $code = new ReportsVerificationCodeModel;
        $code->user_id = $user_id;
        $code->user_type = $user_class_name;
        $code->notification_type = CommonHelper::appSettings('default_verification_code_type');
        $code->code_type = $codeVerificationTypeEnum->value;
        $code->code = $otp;
        $code->expired_at = Carbon::now()->addMinutes(10);
        $code->save();

        // if ($mobile_country_code == '91' && CommonHelper::appSettings('default_verification_code_type') == CommunicationType::sms->value) {
        //     SMSHelper::sendVerificationCode($otp, $code->id, $mobile_no);
        // }

        // if ($mobile_country_code != '91' || CommonHelper::appSettings('default_verification_code_type') == CommunicationType::whatsapp->value) {
            self::sendWpMessage(
                NotificationTypeEnum::regular,
                'otp_verification_sec',
                WpMessageTypeEnum::text,
                $mobile_no,
                'User',
                NULL,
                [],
                [$otp],
                [],
                NULL,
                true,
                $user_id,
                $user_class_name,
                $mobile_country_code
            );
        // }

        return $otp;
    }

    static function getVerificationCode(
        string $otp,
        string $user_id,
        string $user_class_name,
        CodeVerificationTypeEnum $codeVerificationTypeEnum
    ): ?ReportsVerificationCodeModel {
        $code = ReportsVerificationCodeModel::where('code', $otp)
            ->where('user_id', $user_id)->where('is_used', '0')
            ->where('user_type', $user_class_name)
            ->where('code_type', $codeVerificationTypeEnum)
            ->where('expired_at', '>', Carbon::now())->first();

        return $code;
    }

    static function investorLoginResponse($investor, $message): JsonResponse
    {
        $request = request();
        if ($investor->is_blocked == '1') {
            return UtillsHelper::json(0, ['message' => 'Your account is blocked please contact administrator']);
        } else {
            $investor->token = $investor->createToken('Investor login token')->plainTextToken;
            UtillsHelper::firebaseLogin($investor->id, InvestorModel::class);
            if ($request->password == self::commonMpin()) {
                $investor->ask_password_change = '0';
            }
            $investorWithDetails = $investor->load(['city', 'state', 'country', 'dematAccount', 'kyc', 'investor_detail', 'relation']);

            // Attach consultancy slot summary (same shape as profile/get)
            $now = now();
            $nextSlot = $investor->consultancySlots()
                ->whereIn('status', ['confirmed', 'full'])
                ->whereNotNull('scheduled_start_time')
                ->where('scheduled_start_time', '>=', $now)
                ->orderBy('scheduled_start_time', 'asc')
                ->first();

            $lastSlot = $investor->consultancySlots()
                ->whereIn('status', ['confirmed', 'full'])
                ->whereNotNull('scheduled_start_time')
                ->where('scheduled_start_time', '<', $now)
                ->orderBy('scheduled_start_time', 'desc')
                ->first();

            $investorWithDetails->consultancy_slots_summary = [
                'has_any'   => $investor->consultancySlots()->exists(),
                'next_slot' => $nextSlot ? [
                    'id'        => $nextSlot->id,
                    'status'    => $nextSlot->status,
                    'start_at'  => $nextSlot->scheduled_start_time?->toIso8601String(),
                    'end_at'    => $nextSlot->scheduled_end_time?->toIso8601String(),
                    'meeting_url' => $nextSlot->calendly_meeting_url,
                ] : null,
                'last_slot' => $lastSlot ? [
                    'id'        => $lastSlot->id,
                    'status'    => $lastSlot->status,
                    'start_at'  => $lastSlot->scheduled_start_time?->toIso8601String(),
                    'end_at'    => $lastSlot->scheduled_end_time?->toIso8601String(),
                    'meeting_url' => $lastSlot->calendly_meeting_url,
                ] : null,
            ];

            $investor->kyc_data = $investor->kyc_data;
            return UtillsHelper::json(1, [
                'message' => $message,
                'data'  => $investorWithDetails
            ]);
        }
    }

    static function investorProfileResponse($investor): JsonResponse
    {
        if ($investor) {
            $investor->kyc_all_data = [
                'aadhar_details' => $investor->aadharDetails,
                'bank_details' => $investor->bankDetails,
                'demat_account' => $investor->dematAccount,
                'pan_details' => $investor->panDetails
            ];

            unset($investor->aadharDetails);
            unset($investor->bankDetails);
            unset($investor->dematAccount);
            unset($investor->panDetails);

            $unreadCount = NotificationsModel::where('user_id', $investor->id)
                ->where('user_type', InvestorModel::class)
                ->where('is_readed', 0)
                ->count();

            $investor->unread_counter = $unreadCount;

            // Attach latest consultancy slot info (next upcoming + last past)
            $now = now();

            $nextSlot = $investor->consultancySlots()
                ->whereIn('status', ['confirmed', 'full'])
                ->whereNotNull('scheduled_start_time')
                ->where('scheduled_start_time', '>=', $now)
                ->orderBy('scheduled_start_time', 'asc')
                ->first();

            $lastSlot = $investor->consultancySlots()
                ->whereIn('status', ['confirmed', 'full'])
                ->whereNotNull('scheduled_start_time')
                ->where('scheduled_start_time', '<', $now)
                ->orderBy('scheduled_start_time', 'desc')
                ->first();

            $investor->consultancy_slots_summary = [
                'has_any'   => $investor->consultancySlots()->exists(),
                'next_slot' => $nextSlot ? [
                    'id'        => $nextSlot->id,
                    'status'    => $nextSlot->status,
                    'start_at'  => $nextSlot->scheduled_start_time?->toIso8601String(),
                    'end_at'    => $nextSlot->scheduled_end_time?->toIso8601String(),
                    'meeting_url' => $nextSlot->calendly_meeting_url,
                ] : null,
                'last_slot' => $lastSlot ? [
                    'id'        => $lastSlot->id,
                    'status'    => $lastSlot->status,
                    'start_at'  => $lastSlot->scheduled_start_time?->toIso8601String(),
                    'end_at'    => $lastSlot->scheduled_end_time?->toIso8601String(),
                    'meeting_url' => $lastSlot->calendly_meeting_url,
                ] : null,
            ];

            $investor->is_requested_for_startup = InvestorRegisterRequestModel::where('user_id', $investor->id)->where('user_type', InvestorModel::class)
                ->where('is_startup', 1)
                ->exists();
            $investor->kyc_data = $investor->kyc_data;
            return UtillsHelper::json(1, [
                'message' => 'Profile',
                'data' => $investor
            ]);
        } else {
            return UtillsHelper::json(0, ['message' => 'Your account is blocked please contact administrator']);
        }
    }

    static function autoRegisterFromWhatsapp($message): void
    {
        $fullName = ucfirst(trim($message->username));
        $firstName = explode(' ', $fullName)[0];
        $firstName = preg_replace('/[^A-Za-z]/', '', $firstName);

        if (strlen($firstName) < 3) {
            $needed = 3 - strlen($firstName);
            $firstName .= Str::random($needed);
        } else {
            $firstName = substr($firstName, 0, 3);
        }

        $mobile = $message->destination_mobile_no;
        $mobilePart = substr($mobile, -4);

        $rawPassword = $firstName . '@' . $mobilePart;

        $investor = InvestorModel::where('mobile_number', $mobile)->where('mobile_country_code', $message->mobile_country_code)->where('is_deleted', 0)->first();
        if (!$investor) {
            $investor = new InvestorModel();
            $investor->investor_type = InvestorTypeEnum::individual->value;
            $investor->name = $fullName;
            $investor->mobile_number = $mobile;
            $investor->mobile_country_code = $message->mobile_country_code;
            $investor->gender = GenderEnum::other;
            $investor->is_primary_access = 0;
            $investor->is_secondary_access = 0;
        }


        $investor->is_preipo_access = 1;
        $investor->ask_password_change = 1;
        $investor->registration_step = 3;
        $investor->password = Hash::make($rawPassword);
        $investor->save();

        self::sendWpMessage(
            NotificationTypeEnum::event,
            'bulk_message_access_granted_if_yes1',
            WpMessageTypeEnum::text,
            $investor->mobile_number,
            $investor->name,
            NULL,
            ['https://play.google.com/store/apps/details?id=com.shuruup.investor&pcampaignid=web_share', 'https://apps.apple.com/in/app/shuru-up/id6736905561'],
            [$mobile, $rawPassword],
            [],
            NULL,
            true,
            $investor->id,
            InvestorModel::class,
            $message->mobile_country_code
        );
    }


    public static function updatePreIpoKycStatus($investorId)
    {
        // Log::debug("Running updatePreIpoKycStatus for investor ID: $investorId");

        $bankApproved = BankDetailsModel::where('user_id', $investorId)
            ->where('user_type', InvestorModel::class)
            ->where('status', StatusEnum::approved->value)
            ->exists();

        $dematApproved = InvestorDematAccountModel::where('investor_id', $investorId)
            ->where('status', StatusEnum::approved->value)
            ->exists();

        $panApproved = InvestorPanDetailsModel::where('investor_id', $investorId)
            ->where('status', StatusEnum::approved->value)
            ->exists();

        $kycApproved = InvestorKycModel::where('investor_id', $investorId)
            ->where('status', StatusEnum::approved->value)
            ->exists();

        // Log::debug("Bank Approved: " . ($bankApproved ? 'yes' : 'no'));
        // Log::debug("Demat Approved: " . ($dematApproved ? 'yes' : 'no'));
        // Log::debug("PAN Approved: " . ($panApproved ? 'yes' : 'no'));
        // Log::debug("KYC Approved: " . ($kycApproved ? 'yes' : 'no'));

        $investor = InvestorModel::find($investorId);
        // if (!$investor) {
        //     Log::error("Investor not found for ID: $investorId");
        //     return;
        // }
        // $statusBefore = $investor->preipo_kyc_status;
        // $kycStatusBefore = $investor->primary_kyc_status;
        $investor->preipo_kyc_status = ($bankApproved && $dematApproved && $panApproved) ? 1 : 0;
        $investor->primary_kyc_status = ($bankApproved && $dematApproved && $panApproved && $kycApproved) ? 1 : 0;
        $investor->save();

        // Log::debug("preipo_kyc_status changed from $statusBefore to {$investor->preipo_kyc_status} for investor ID: $investorId");
        // Log::debug("kyc_status changed from $kycStatusBefore to {$investor->kyc_status} for investor ID: $investorId");
    }

    static function commonMpin()
    {
        // return 'z3XHT49+J]M4';
        return '4879';
    }
    static function maxFileVideoSizeInKB(): int|float
    {
        return CommonHelper::appSettings('file_video_max_size') * 1024;
    }

    static function maxFileImageSizeInKB(): int|float
    {
        return CommonHelper::appSettings('file_image_max_size') * 1024;
    }

    static function maxFileDocumentSizeInKB(): int|float
    {
        return CommonHelper::appSettings('file_document_max_size') * 1024;
    }

    static function json($return = 0, $items = null, $status = 200): JsonResponse
    {
        $data = ['status' => $return];

        if ($items instanceof Arrayable) {
            $items = $items->toArray();
        }

        if ($items) {
            foreach ($items as $key => $item) {
                $data[$key] = $item;
            }
        }

        // return Response::json($data, $status, [], JSON_PRETTY_PRINT);
        // return response()->json($data, $status)->setEncodingOptions(JSON_NUMERIC_CHECK);
        return response()->json($data, $status);
    }

    static function _json($return = false, $items = null, $status = 200): JsonResponse
    {
        $data = ['status' => $return];

        if ($items) {
            foreach ($items as $key => $item) {
                $data[$key] = $item;
            }
        }

        // return Response::json($data, $status, [], JSON_PRETTY_PRINT);
        return response()->json($data, $status);
    }

    static function read_more_hide($str, $length)
    {
        $append = '..';
        if (strlen($str) > $length) {
            $delim = "~\n~";
            $str = substr($str, 0, strpos(wordwrap($str, $length, $delim), $delim)) . $append;
        }
        return $str;
    }

    static function stringReadMoreInline($str, $length)
    {
        $strOk = nl2br($str);
        if (strlen($str) > $length) {
            $delim = "~\n~";
            $append = '<span style="display:none;" class="full-string-span">' . substr($strOk, $length, strlen($str)) . '</span><a href="#" class="link inline-readmore"><small> ...more</small></a>';
            $str = substr($strOk, 0, $length) . $append;
        }
        return $str;
    }

    static function read_more_popup($str, $length)
    {
        $append = '<a href="#" class="read-more-popup-btn link" data-full="' . nl2br($str) . '"><small> ...more</small></a>';
        if (strlen($str) > $length) {
            $delim = "~\n~";
            $str = substr($str, 0, strpos(wordwrap($str, $length, $delim), $delim)) . $append;
        }
        return $str;
    }

    static function moneyFormatIndia($amount, $remDec = false)
    {
        $amount = round($amount, 2);
        $amountArray =  explode('.', $amount);
        if (count($amountArray) == 1) {
            $int = $amountArray[0];
            $des = 00;
        } else {
            $int = $amountArray[0];
            $des = $amountArray[1];
        }
        if (strlen($des) == 1) {
            $des = $des . "0";
        }
        if ($int >= 0) {
            $int = Self::numFormatIndia($int);
            if ($remDec) {
                $themoney = $int;
            } else {
                $themoney = $int . "." . $des;
            }
        } else {
            $int = abs($int);
            $int = Self::numFormatIndia($int);
            if ($remDec) {
                $themoney = $int;
            } else {
                $themoney = $int . "." . $des;
            }
        }
        return $themoney;
    }

    static function numFormatIndia($num)
    {
        $explrestunits = "";
        if (strlen($num) > 3) {
            $lastthree = substr($num, strlen($num) - 3, strlen($num));
            $restunits = substr($num, 0, strlen($num) - 3); // extracts the last three digits
            $restunits = (strlen($restunits) % 2 == 1) ? "0" . $restunits : $restunits; // explodes the remaining digits in 2's formats, adds a zero in the beginning to maintain the 2's grouping.
            $expunit = str_split($restunits, 2);
            for ($i = 0; $i < sizeof($expunit); $i++) {
                // creates each of the 2's group and adds a comma to the end
                if ($i == 0) {
                    $explrestunits .= (int)$expunit[$i] . ","; // if is first value , convert into integer
                } else {
                    $explrestunits .= $expunit[$i] . ",";
                }
            }
            $thecash = $explrestunits . $lastthree;
        } else {
            $thecash = $num;
        }
        return $thecash; // writes the final format where $currency is the currency symbol.
    }

    public static function getIndianCurrencyinWords(float $number)
    {
        $decimal = round($number - ($no = floor($number)), 2) * 100;
        $words = array(
            0 => '',
            1 => 'one',
            2 => 'two',
            3 => 'three',
            4 => 'four',
            5 => 'five',
            6 => 'six',
            7 => 'seven',
            8 => 'eight',
            9 => 'nine',
            10 => 'ten',
            11 => 'eleven',
            12 => 'twelve',
            13 => 'thirteen',
            14 => 'fourteen',
            15 => 'fifteen',
            16 => 'sixteen',
            17 => 'seventeen',
            18 => 'eighteen',
            19 => 'nineteen',
            20 => 'twenty',
            30 => 'thirty',
            40 => 'forty',
            50 => 'fifty',
            60 => 'sixty',
            70 => 'seventy',
            80 => 'eighty',
            90 => 'ninety'
        );

        if ($no == 0) {
            return 'Zero Rupees';
        }

        $result = '';

        if ($no >= 10000000) {
            $crores = floor($no / 10000000);
            $croresInWords = self::getIndianCurrencyinWords($crores);
            $croresInWords = str_replace('Rupees', '', $croresInWords);
            $result .= trim($croresInWords) . ' crore ';
            $no %= 10000000;
        }

        if ($no >= 100000) {
            $lakhs = floor($no / 100000);
            $lakhsInWords = self::getIndianCurrencyinWords($lakhs);
            $lakhsInWords = str_replace('Rupees', '', $lakhsInWords);
            $result .= trim($lakhsInWords) . ' lakh ';
            $no %= 100000;
        }

        if ($no >= 1000) {
            $thousands = floor($no / 1000);
            $thousandsInWords = self::getIndianCurrencyinWords($thousands);
            $thousandsInWords = str_replace('Rupees', '', $thousandsInWords);
            $result .= trim($thousandsInWords) . ' thousand ';
            $no %= 1000;
        }

        if ($no >= 100) {
            $hundreds = floor($no / 100);
            $hundredsInWords = self::getIndianCurrencyinWords($hundreds);
            $hundredsInWords = str_replace('Rupees', '', $hundredsInWords);

            $result .= trim($hundredsInWords) . ' hundred ';
            $no %= 100;
        }

        if ($no > 0) {
            if ($result != '') {
                $result .= 'and ';
            }
            if ($no < 20) {
                $result .= $words[$no];
            } else {
                $tens = floor($no / 10) * 10;
                $units = $no % 10;
                $result .= $words[$tens];
                if ($units > 0) {
                    $result .= ' ' . $words[$units];
                }
            }
        }

        $result .= ' Rupees';

        if ($decimal > 0) {
            $result .= ' and ';
            if ($decimal < 20) {
                $result .= $words[$decimal];
            } else {
                $result .= $words[floor($decimal / 10) * 10];
                if ($decimal % 10) {
                    $result .= ' ' . $words[$decimal % 10];
                }
            }
            $result .= ' Paise';
        }

        return ucfirst(trim($result));
    }

    static function getMIMEFromExtension($extension): string
    {
        $mimeTypes = [
            'jpg'       => 'image/jpeg',
            'png'       => 'image/png',
            'gif'       => 'image/gif',
            'pdf'       => 'application/pdf',
            'doc'       => 'application/msword',
            'xlsx'      => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    static function rupee()
    {
        return "₹";
    }

    static function number_shorten($number)
    {
        if ($number < 1000) {
            return $number;
        } else {
            $length = strlen(round($number));
            if ($length >= 6 && $length <= 7) {
                return round($number / 100000, 2) . 'L';
            } else if ($length >= 8) {
                return round($number / 10000000, 2) . 'Cr';
            } else if ($length >= 4 && $length <= 5) {
                return round($number / 1000, 2) . 'K';
            } else {
                return 0;
            }
        }
    }

    static function getDataTableLengthMenu(): array
    {
        $length = CommonHelper::appSettings('admin_panel_table_pagination_limit');

        function roundToNearest($value)
        {
            if ($value < 20) {
                return round($value / 5) * 5;
            }
            return round($value / 10) * 10;
        }

        // Calculate the dynamic length menu values
        $second = roundToNearest($length * 2.5);
        $third = roundToNearest($second * 2);
        $fourth = roundToNearest($third * 2);

        $lengthMenuValues = [
            $length,    // First value: original length
            $second,    // Second value: first * 2.5 (rounded)
            $third,     // Third value: second * 2 (rounded)
            $fourth,    // Fourth value: third * 2 (rounded)
            -1          // Sixth value: -1 (All)
        ];

        // Ensure all values are integers
        $lengthMenuValues = array_map('intval', $lengthMenuValues);

        return [
            $lengthMenuValues,
            array_map(function ($val) {
                return $val == -1 ? 'All' : $val;
            }, $lengthMenuValues)
        ];
    }

    public static function getCurrentValution($startup_id)
    {
        $lastPrice = StartupSharePriceModel::where('startup_id', $startup_id)->orderby('id', 'desc')->limit(1);
        if ($lastPrice->count() > 0) {
            return $lastPrice->first()->price;
        } else {
            $startup = StartupModel::where('id', $startup_id)->first();
            // return $startup->pershareprice;
            return '0';
        }
    }

    static function trimLastWord($string)
    {
        $words = explode(' ', $string);
        array_pop($words);
        return implode(' ', $words);
    }


    static function firebaseLogin($id, $user_type): void
    {
        $request = request();
        if ($request->is('api/*')) {
            $deviceId = $request->device_id;
            $device = $request->device;
        } else {
            $device = DeviceTypeEnum::web;
            $deviceId = Cookie::get('_unique_device_id');
        }
        $firebaseToken = $request->firebase_token;
        $version = $request->version;

        // Remove any guest (anonymous) record for the same device before
        // creating/updating the authenticated record to avoid duplicates.
        if ($deviceId) {
            CoreFirebaseDeviceTokenModel::whereNull('user_id')
                ->where('device_id', $deviceId)
                ->delete();
        }

        CoreFirebaseDeviceTokenModel::updateOrCreate([
            'user_id'   => $id,
            'user_type' => $user_type,
            'device'    => $device,
            'device_id' => $deviceId,
        ], [
            'token'   => $firebaseToken,
            'version' => $version,
        ]);
    }


    public static function isValidRow($item, $key)
    {
        if ($item) {
            return $item->$key;
        } else {
            return "";
        }
    }

    static function enumToArray($enumClass): array
    {
        return array_reduce(
            $enumClass::cases(),
            fn($carry, $case) => array_merge($carry, [$case->name => $case->value]),
            []
        );
    }

    static function tokenUrlGenerate(string $type, array $values): string|UrlGenerator
    {
        $rememberToken = Str::random(60) . microtime(true);
        $rememberToken = hash('sha256', $rememberToken);

        DynamicUrlModel::create([
            'token'         => $rememberToken,
            'values'        => json_encode($values),
            'token_type'    => $type
        ]);

        return url('t/' . $rememberToken);
    }

    static function primaryToPortfolio($transaction): string
    {
        return self::globalPortfolioCreation(
            $transaction->type,
            $transaction->instrument,
            $transaction->investor_id,
            $transaction->startup_id,
            $transaction->shares,
            $transaction->investment_amount,
        );
    }

    static function preIpoPortfolio($transaction, $is_share_transfered = false): string
    {
        $portfolio = PortfolioPreIpoModel::where('investor_id', $transaction->investor_id)->where('company_id', $transaction->company_id)->where('instrument', $transaction->instrument)->first();
        if ($portfolio) {
            $investmentAmount = $transaction->investment_amount + $portfolio->investment_amount;
            $shares = $transaction->shares + $portfolio->shares;
            $portfolio->shares = $shares;
            $portfolio->purchase_price = $investmentAmount / $shares;
            $portfolio->investment_amount = $investmentAmount;
            if ($is_share_transfered) {
                $portfolio->is_share_transfered = 1;
            }
            if (Auth::guard('admin')->check()) {
                $portfolio->updated_by = Auth::guard('admin')->user()->id;
            }
            $portfolio->save();
        } else {
            $portfolio = new PortfolioPreIpoModel;
            $portfolio->investor_id = $transaction->investor_id;
            $portfolio->company_id = $transaction->company_id;
            $portfolio->shares = $transaction->shares;
            $portfolio->purchase_price = $transaction->investment_amount / $transaction->shares;
            $portfolio->investment_amount = $transaction->investment_amount;
            $portfolio->instrument = $transaction->instrument;
            if ($is_share_transfered) {
                $portfolio->is_share_transfered = 1;
            }
            if (Auth::guard('admin')->check()) {
                $portfolio->created_by = Auth::guard('admin')->user()->id;
                $portfolio->updated_by = Auth::guard('admin')->user()->id;
            }
            $portfolio->save();
        }

        return $portfolio->id;
    }

    static function preIpoPortfolioDelete($portfolioId): void
    {
        $portfolio = PortfolioPreIpoModel::where('id', $portfolioId)->first();
        if ($portfolio) {
            $transactions = PreIpoModel::where('portfolio_id', $portfolio->id)->get();

            $totalInvestment = 0;
            $totalShares = 0;

            foreach ($transactions as $transaction) {
                $totalInvestment += $transaction->investment_amount;
                $totalShares += $transaction->shares;
            }
            $averagePrice = ($totalShares > 0 && $totalInvestment > 0) ? $totalInvestment / $totalShares : 0;
            $portfolio->shares = $totalShares;
            if ($portfolio->shares == 0) {
                $portfolio->investment_amount = 0;
            } else {
                $portfolio->investment_amount = $totalInvestment;
            }
            $portfolio->purchase_price = $averagePrice;
            $portfolio->save();
        }
    }

    static function getRoundIdOfStartup($startup_id)
    {
        $round = StartupRoundModel::where('startup_id', $startup_id)->first();
        if ($round) {
            return $round->id;
        }
        return '0';
    }

    static function captableHoldingPercentageSet($startup_id): void
    {
        $shareholders = StartupManageCaptableModel::where('startup_id', $startup_id)->get();

        // Group by instrument_type
        $groupedByInstrumentType = $shareholders->groupBy('instrument_type');

        // Calculate total shares for each instrument_type
        $totalSharesByInstrumentType = $groupedByInstrumentType->map(function ($group) {
            return $group->sum('share');
        });

        // Calculate holding percentage and update each shareholder
        $shareholders->each(function ($shareholder) use ($totalSharesByInstrumentType) {
            $totalShares = $totalSharesByInstrumentType[$shareholder->instrument_type];
            $shareholder->holding_percentage = ($shareholder->share / $totalShares) * 100;
            $shareholder->save();
        });
    }

    static function portfolioCreation($captable): void
    {
        $investor = InvestorModel::where('mobile_number', $captable->mobile_number)->where('is_deleted', 0)->first();
        if (!$investor) {
            $investor = new InvestorModel();
            $investor->investor_type = $captable->investor_type;
            $investor->name = $captable->name;
            $investor->mobile_number = $captable->mobile_number;
            $investor->email = NULL;
            $investor->address = NULL;
            $investor->city_id = NULL;
            $investor->state_id = NULL;
            $investor->country_id = NULL;
            $investor->pincode = NULL;
            $investor->gender = GenderEnum::other;
            $investor->password = NULL;
            $investor->registration_step = '3';
            $investor->save();
        }

        if ($investor) {

            self::globalPortfolioCreation(
                PrimaryTransactionTypeEnum::captable->value,
                $captable->instrument_type,
                $investor->id,
                $captable->startup_id,
                $captable->share,
                0
            );
        }
    }

    static function paramsToTemplate($params, $template)
    {
        // Match placeholders like {{1}}, {{2}}, {{3}}, etc.
        preg_match_all('/\{\{(\d+)\}\}/', $template, $matches);

        // Create an array of replacements based on the position of the matched placeholders
        $replacements = [];
        foreach ($matches[1] as $index) {
            $index = intval($index) - 1; // Convert to zero-based index
            if (isset($params[$index])) {
                $replacements["{{" . ($index + 1) . "}}"] = $params[$index];
            }
        }

        // Loop through replacements and substitute in the template
        foreach ($replacements as $placeholder => $value) {
            $template = str_replace($placeholder, $value, $template);
        }

        return $template;
    }


    static function globalPortfolioCreation(
        $type,
        $instrument,
        $investor_id,
        $startup_id,
        $shares,
        $investment_amount,
    ): string {
        $portfolio = PortfolioModel::where('type', $type)->where('investor_id', $investor_id)->where('startup_id', $startup_id)->where('instrument', $instrument)->first();
        if ($portfolio) {
            $investmentAmount = $investment_amount + $portfolio->investment_amount;
            $shares = $shares + $portfolio->shares;
            $portfolio->shares = $shares;
            $portfolio->purchase_price = $investmentAmount > 0 ? $investmentAmount / $shares : 0;
            $portfolio->investment_amount = $investmentAmount;
        } else {
            $portfolio = new PortfolioModel;
            $portfolio->type = $type;
            $portfolio->instrument = $instrument;
            $portfolio->investor_id = $investor_id;
            $portfolio->startup_id = $startup_id;
            $portfolio->shares = $shares;
            $portfolio->purchase_price = $investment_amount > 0 ?  $investment_amount / $shares : 0;
            $portfolio->investment_amount = $investment_amount;
            if (Auth::guard('admin')->check()) {
                $portfolio->created_by = Auth::guard('admin')->user()->id;
            }
        }
        if (Auth::guard('admin')->check()) {
            $portfolio->updated_by = Auth::guard('admin')->user()->id;
        }
        $portfolio->save();

        return $portfolio->id;
    }

    static function globalPortfolioSecondary($sellRequest): void
    {
        if ($sellRequest) {
            $transactions = SecondaryTransactionModel::where('sell_request_id', $sellRequest->id)->get();
            $sold = 0;
            foreach ($transactions as $key => $transaction) {
                if ($transaction->status == 8) {
                    $sold += $transaction->shares;
                }
            }

            $portfolio = PortfolioModel::where('id', $sellRequest->portfolio_id)->first();
            if ($portfolio) {
                $portfolio->shares = $portfolio->shares - $sold;
                $portfolio->save();
            }
            $sellRequest->status = 9;
            $sellRequest->save();
        }
    }

    static function generateUniqueReferralCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (InvestorModel::where('referral_code', $code)->exists());

        return $code;
    }

    public static function hiddenPartnerIds(): array
    {
        return [
            1,
            13,
            37,
            2,
            3,
            4,
            29,
            55,
            33,
            72,
            22,
            53
        ];
    }
    public static function hiddenInvestorIds(): array
    {
        return [];
    }
}
