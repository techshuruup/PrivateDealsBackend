<?php

namespace App\Traits;

use App\Enums\MessagesStatusEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\WpMessageTypeEnum;
use App\Helpers\CommonHelper;
use App\Models\ReportErrorLogModel;
use App\Models\ReportMessagesWhatsappModel;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

trait WhatsAppSendTrait
{
    /**
     * Non-OTP WhatsApp is paused. Only these 11za template names are sent.
     * Digio signer SMS is unchanged. In-app inbox writes are paused on NotificationsModel.
     */
    private const WHATSAPP_ALLOWED_TEMPLATES = [
        'otp_verification_sec',
    ];

    private static function whatsAppSendAllowed(string $templateName): bool
    {
        return in_array($templateName, self::WHATSAPP_ALLOWED_TEMPLATES, true);
    }

    static function sendWpMessageTrait(
        NotificationTypeEnum $type,
        string $template_name,
        WpMessageTypeEnum $template_type,
        string $destination_mobile,
        string $destination_name,
        string $media_url           = NULL,
        array $click_url           = [],
        array $params               = [],
        array $message_data         = [],
        $broadcast_id         = NULL,
        bool $send_now       = true,
        $reference_id = NULL,
        string $reference_model = NULL,
        int $mobile_country_code = 91
    ): void {
        if (!self::whatsAppSendAllowed($template_name)) {
            return;
        }

        $wp = new ReportMessagesWhatsappModel;
        $wp->broadcast_id           = $broadcast_id;
        $wp->type                   = $type;
        $wp->template_name          = $template_name;
        $wp->template_type          = $template_type;
        $wp->trycount               = '0';
        $wp->status                 = MessagesStatusEnum::pending;
        $wp->mobile_country_code  = $mobile_country_code;
        $wp->destination_mobile_no  = $destination_mobile;
        $wp->username               = $destination_name;
        $wp->media                  = $media_url;
        $wp->click_url              = json_encode($click_url);
        $wp->message_data           = json_encode($message_data);
        $wp->params                 = json_encode($params);
        $wp->reference_id          = $reference_id;
        $wp->reference_model       = $reference_model;
        $wp->save();
        if ($send_now) {
            self::sendNowWhatsApp($wp);
        }
    }

    private static function sendNowWhatsApp(ReportMessagesWhatsappModel $message): void
    {
        if (!self::whatsAppSendAllowed((string) $message->template_name)) {
            $message->trycount = $message->trycount + 1;
            $message->status = MessagesStatusEnum::failed;
            $message->response = 'Skipped: WhatsApp is paused except OTP (otp_verification_sec).';
            $message->response_code = 0;
            $message->save();
            return;
        }

        $responseCode = 599;
        $status = MessagesStatusEnum::pending;
        $response = 'Pending Response';

        $postJson = [];
        $postJson['authToken']          = CommonHelper::appSettings('third_party_wp_11za_authtoken');
        $postJson['originWebsite']      = CommonHelper::appSettings('third_party_wp_11za_origin_website');
        $postJson['templateName']       = $message->template_name;
        $postJson['language']           = 'en';
        $postJson['name']               = $message->username;
        if (CommonHelper::appSettings('test_mobile') != '') {
            $postJson['sendto']             = '91' . CommonHelper::appSettings('test_mobile');
        } else {
            $postJson['sendto']             = $message->mobile_country_code . $message->destination_mobile_no;
        }
        $postJson['isTinyURL']          = 'no';
        $postJson['buttonValue']        = NULL;
        $click_url = json_decode($message->click_url);
        if (count($click_url) > 0) {
            $postJson['isTinyURL']          = 'yes';
            $postJson['buttonValue']        = $click_url;
        }
        $postJson['myfile']        = $message->media;
        // Log::info('Template Name:', ['template_name' => $message->template_name]);
        $postJson['data'] = [];
        // $params = json_decode($message->params);

        // if (is_string($params)) {
        //     $params = json_decode($params, false); // Decode JSON string to object
        // }
        // if (!empty((array)$params)) { // Cast the object to an array
        //     foreach ($params as $key => $value) {
        //         $postJson['data'][$key] = $value;
        //     }
        // }
        if (count(json_decode($message->params)) > 0) {
            foreach (json_decode($message->params) as $key => $value) {
                $postJson['data'][$key] = $value;
            }
        }

        // Log::alert(json_encode($postJson));

        $client = new Client(['verify' => false, 'http_errors' => false]);
        try {
            $request = $client->post('https://app.11za.in/apis/template/sendTemplate', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json'
                ],
                'json' => $postJson
            ]);

            $responseCode = $request->getStatusCode();
            if ($responseCode != 200) {
                $status         = MessagesStatusEnum::failed;
                $responseCode   = $responseCode;
                $response       = $request->getBody()->getContents();
            } else {
                $status         = MessagesStatusEnum::sent;
                $responseCode   = $responseCode;
                $response       = $request->getBody()->getContents();
            }
        } catch (ConnectException $e) {
            $status         = MessagesStatusEnum::failed;
            $responseCode   = 500;
            $response       = $e->getMessage();
            // Handle timeout or connection error
            // echo "Connection timed out: " . $e->getMessage();
        } catch (RequestException $e) {
            // Handle other Guzzle request exceptions
            // echo "Request failed: " . $e->getMessage();
            $status         = MessagesStatusEnum::failed;
            $responseCode   = 500;
            $response       = $e->getMessage();
        }

        $message->trycount              = $message->trycount + 1;
        $message->status                = $status;
        $message->response              = $response;
        $message->response_code         = $responseCode;
        if ($responseCode == 200) {
            $responseObject = json_decode($response);
            $message->message_id = $responseObject->Data->messageId;

            try {
                // Decode the JSON response
                $responseObject = json_decode($response);

                // Check if decoding was successful
                if (json_last_error() === JSON_ERROR_NONE) {
                    $message->message_id = $responseObject->Data->messageId;
                } else {
                    ReportErrorLogModel::create([
                        'type'          => 'WhatsApp 11ZA',
                        'subtype'       => 'Json decode problem',
                        'description'   => json_last_error_msg()
                    ]);
                }
            } catch (Exception $e) {
                ReportErrorLogModel::create([
                    'type'          => 'WhatsApp 11ZA',
                    'subtype'       => 'Json decode problem',
                    'description'   => $e->getMessage()
                ]);
            }
        }
        $message->save();
        if ($responseCode != 200) {
            ReportErrorLogModel::create([
                'type'          => 'WhatsApp 11ZA',
                'subtype'       => 'Send',
                'description'   => $response
            ]);
        }
    }

    private static function fetchTemplatesFromApi(): array
    {
        $result = [];

        $postJson = [
            'authToken' => CommonHelper::appSettings('third_party_wp_11za_authtoken'),
            'search'    => '',
            'page'      => 1,
            'limit'     => 50,
        ];

        $client = new Client(['verify' => false, 'http_errors' => false]);

        try {
            $request = $client->post('https://app.11za.in/apis/template/getTemplatesAll', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'json' => $postJson,
            ]);

            if ($request->getStatusCode() === 200) {
                $response = json_decode($request->getBody()->getContents(), true);
                if (isset($response['Data']['docs'])) {
                    foreach ($response['Data']['docs'] as $template) {
                        $template['text'] = $template['localizations'][0]['components'][0]['text'] ?? '';
                        $result[] = $template;
                    }
                }
            }
        } catch (RequestException $e) {
            ReportErrorLogModel::create([
                'type'        => 'WhatsApp 11ZA',
                'subtype'     => 'Fetch Templates',
                'description' => $e->getMessage(),
            ]);
        }

        return $result;
    }
}
