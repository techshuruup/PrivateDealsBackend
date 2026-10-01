<?php

namespace App\Http\Controllers;

use App\Enums\DocumentTypeEnum;
use App\Enums\MessagesStatusEnum;
use App\Helpers\CommonHelper;
use App\Helpers\DigioHelper;
use App\Helpers\FileUpDownHelper;
use App\Helpers\PreIpoTransactionHelper;
use App\Helpers\PrimaryTransactionHelper;
use App\Helpers\SecondaryTransactionHelper;
use App\Helpers\UtillsHelper;
use App\Helpers\WebhookHelper;
use App\Models\CompanyModel;
use App\Models\CompanyPriceAlertModel;
use App\Models\CompanySharePriceModel;
use App\Models\InvestorModel;
use App\Models\InvestorConsultancySlotModel;
use App\Models\NotificationsModel;
use App\Models\DocumentsModel;
use App\Models\DocumentsSignersModel;
use App\Models\ReportErrorLogModel;
use App\Models\ReportMessagesWhatsappModel;
use App\Models\ReportMessagesWhatsappRepliesModel;
use App\Models\ReportWebhookLogModel;
use App\Models\WhatsappBroadcastModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

class WebhookController extends Controller
{

    private $digioSecretKey = '62ABDDD65A9684CB7433B4C9CB469';

    function whatsapp(Request $request): JsonResponse
    {

        $body = @file_get_contents("php://input");
        $response = json_decode($body);

        ReportWebhookLogModel::create([
            'type'  => '11ZA',
            'body'  => $body,
            'headers'   => json_encode($request->headers->all())
        ]);
        if (!empty($body)) {
            if (property_exists($response, 'context') && property_exists($response->context, 'messageId')) {
                $message = ReportMessagesWhatsappModel::where('message_id', $response->context->messageId)->first();
                if ($message) {
                    $message->status = MessagesStatusEnum::replied;
                    $message->save();
                    $reply = $response->whatsapp->text ?? NULL;
                    if ($message->broadcast && $message->broadcast->register_guest) {
                        if ($message->broadcast->default_button_response == $reply) {
                            UtillsHelper::autoRegisterFromWhatsapp($message);
                        }
                    }
                    ReportMessagesWhatsappRepliesModel::create([
                        'whatsapp_message_id'       => $message->id,
                        'message'                   => $reply
                    ]);
                }
                return response()->json(['message' => 'Webhook processed successfully'], 200);
            }
            if (property_exists($response, 'messageId') && property_exists($response, 'status')) {
                $message = ReportMessagesWhatsappModel::where('message_id', $response->messageId)->first();
                if ($message) {
                    if ($response->status == 'delivered') {
                        $message->status = MessagesStatusEnum::delivered;
                    }

                    if ($response->status == 'seen') {
                        $message->status = MessagesStatusEnum::seen;
                    }

                    if ($response->status == 'failed') {
                        $message->status = MessagesStatusEnum::failed;
                    }
                    $message->save();
                }

                return response()->json(['message' => 'Webhook processed successfully'], 200);
            }
        }

        ReportErrorLogModel::create([
            'type' => '11Za Webhook',
            'subtype'   => 'Message id not found',
            'description'   => 'Wrong request',
            'notes' => json_encode([
                'header' => $request->headers->all(),
                'body'  => $body
            ])
        ]);
        return response()->json(['error' => 'Not a valid request'], 400);
    }

    function digio(Request $request): JsonResponse
    {

        $body = @file_get_contents("php://input");
        $response = json_decode($body, true);

        ReportWebhookLogModel::create([
            'type'  => 'Digio',
            'body'  => $body,
            'headers'   => json_encode($request->headers->all())
        ]);

        if (!$request->hasHeader('x-digio-checksum')) {
            ReportErrorLogModel::create([
                'type' => 'Digio Webhook',
                'subtype'   => 'x-digio-checksum not found',
                'description'   => 'Wrong request',
                'notes' => json_encode($request->headers->all())
            ]);
            return response()->json(['error' => 'Not a valid request'], 400);
        }

        $headerChecksum = $request->header('x-digio-checksum');
        $payload = $request->getContent();
        $generatedChecksum = hash_hmac('sha256', $payload, $this->digioSecretKey);
        if (hash_equals($generatedChecksum, $headerChecksum)) {
            if (is_array($response)) {

                if (in_array('document', $response['entities'])) {
                    $document = DocumentsModel::where('api_id', $response['payload']['document']['id'])->where('status', '0')->first();
                    if ($document) {
                        foreach ($response['payload']['document']['signing_parties'] as $signer) {
                            $signerRow = DocumentsSignersModel::where('document_id', $document->id)->where('identifier_value', $signer['identifier'])->first();
                            if ($signerRow) {
                                if ($signer['status'] == 'signed') {
                                    $signerRow->is_signed = 1;
                                    $signerRow->save();
                                }
                            }
                        }

                        if ($response['payload']['document']['agreement_status'] == 'completed') {
                            $getDocApi = DigioHelper::downloadDocment($response['payload']['document']['id']);
                            if ($getDocApi->getStatusCode() == "200") {
                                $file = $getDocApi->getBody()->getContents();
                                $name = CommonHelper::generateFileName() . '.pdf';
                                if ($document->type == DocumentTypeEnum::secondarysh->value) {
                                    $path = 'secondary_transaction/' . $name;
                                    if (Storage::disk('s3')->put($path, $file, 'public')) {
                                        $document->signed_path = $path;
                                        $document->status = 1;
                                        $document->save();
                                        SecondaryTransactionHelper::changeTransactionStatus($document);
                                    }
                                } else if (in_array($document->type, [DocumentTypeEnum::ca->value, DocumentTypeEnum::ppm->value])) {
                                    WebhookHelper::aifOnboard($document, $file);
                                } else if ($document->type == DocumentTypeEnum::preipodealslip->value) {
                                    WebhookHelper::dealslipWebhook($document, $file);
                                } else if ($document->type == DocumentTypeEnum::buymandate->value) {
                                    $name = CommonHelper::generateFileName() . '.pdf';
                                    $path = 'preipo/' . $name;
                                    if (Storage::disk('s3')->put($path, $file, 'public')) {
                                        $document->path = $path;
                                        $document->signed_path = $path;
                                        $document->status = 1;
                                        $document->save();
                                        app(\App\Services\PreIpoOrderStepService::class)->onMandateDocumentSigned($document);
                                    }
                                } else {
                                    $path = 'primary_transaction/' . $name;
                                    if (Storage::disk('s3')->put($path, $file, 'public')) {
                                        $document->signed_path = $path;
                                        $document->status = 1;
                                        $document->save();
                                        PrimaryTransactionHelper::changeTransactionStatus($document);
                                    }
                                }
                            } else {
                                $getDocResponse = json_decode($getDocApi->getBody()->getContents());
                                ReportErrorLogModel::create([
                                    'type' => 'Digio webhook',
                                    'subtype'   => 'Download error',
                                    'description'   => '',
                                    'notes'         => 'document id = ' . $document->id
                                ]);
                            }
                        }
                    }
                }
            }
            return response()->json(['message' => 'Webhook processed successfully'], 200);
        } else {
            ReportErrorLogModel::create([
                'type' => 'Digio Webhook',
                'subtype'   => 'Not a valid secret',
                'description'   => 'Wrong request',
                'notes' => json_encode($request->headers->all())
            ]);
            return response()->json(['error' => 'Invalid checksum'], 400);
        }
    }
    function testRequest()
    {
        $request = request();
        dd($request->all());
        // $validation = Validator::make(
        //     $request->all(),
        //     [
        //         'company_id'            => 'required',
        //         'un_company_id'         => 'required',
        //         'year'                  => 'required',
        //     ]
        // );
        // if ($validation->fails()) {
        //     return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        // }
    }

    function sharePrice()
    {
        $request = request();
        $validation = Validator::make(
            $request->all(),
            [
                'company_id'            => 'required',
                'un_company_id'         => 'required',
                'year'                  => 'required',
            ]
        );
        if ($validation->fails()) {
            return UtillsHelper::json(0, ['message' => $validation->errors()->first()]);
        }

        $response = Http::withoutVerifying()->get('https://unlistedzone.com/shares/graph/' . $request->un_company_id . '/' . $request->year);
        if ($response->successful() && isset($response->json()['data'])) {
            CompanySharePriceModel::where('company_id', $request->company_id)->delete();
            $dataArray = $response->json();
            $inserted = 0;
            if (count($dataArray['data']) > 0) {
                foreach ($dataArray['data'] as $key => $value) {

                    $item = CompanySharePriceModel::create([
                        'company_id'        => $request->company_id,
                        'date'              => $value[0],
                        'price'             => $value[1],
                        'distributer_price' => $value[1],
                    ]);

                    if ($item) {
                        $inserted++;

                        // Check & trigger any active price alerts for this company based on latest price
                        $this->checkAndTriggerPriceAlerts(
                            (int) $request->company_id,
                            (float) $item->price
                        );
                    }
                }
            }

            return UtillsHelper::json(1, ['message' => 'Total Fetched - ' . count($dataArray['data']) . ', Inserted - ' . $inserted]);
        } else {
            return UtillsHelper::json(0, ['message' => 'Cant fetch from api']);
        }
    }

    /**
     * Check all active, not-yet-triggered alerts for a company and trigger those
     * whose condition (up/down) has been satisfied by the latest price.
     *
     * This creates an in-app notification for the investor.
     */
    protected function checkAndTriggerPriceAlerts(int $companyId, float $latestPrice): void
    {
        $alerts = CompanyPriceAlertModel::where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        if ($alerts->isEmpty()) {
            return;
        }

        $company = CompanyModel::find($companyId);

        foreach ($alerts as $alert) {
            $shouldTrigger = false;

            if (
                $alert->direction === 'up' &&
                $latestPrice >= (float) $alert->target_price
            ) {
                $shouldTrigger = true;
            }

            if (
                $alert->direction === 'down' &&
                $latestPrice <= (float) $alert->target_price
            ) {
                $shouldTrigger = true;
            }

            if (!$shouldTrigger) {
                continue;
            }

            // If NOT remind always and already triggered → skip
            if (!$alert->remind_always && $alert->is_triggered) {
                continue;
            }

            NotificationsModel::create([
                'user_id'   => $alert->investor_id,
                'user_type' => InvestorModel::class,
                'title'     => 'Price alert triggered',
                'body'      => sprintf(
                    'Price for %s %s %.2f',
                    $company?->brand_name ?? 'company',
                    $alert->direction === 'up' ? 'reached or crossed' : 'fell to',
                    $latestPrice
                ),
                'payload'   => [
                    'reference_type' => 'company',
                    'reference_id'   => $companyId,
                ],
                'is_readed' => 0,
                'is_sent'   => 0,
            ]);

            if (!$alert->remind_always) {

                // ✅ ONE-TIME ALERT → STOP AFTER FIRST TRIGGER
                $alert->update([
                    'is_triggered' => true,
                    'triggered_at' => now(),
                    'is_active'    => false,
                ]);
            } else {

                // ✅ CONTINUOUS ALERT → KEEP RUNNING
                $alert->update([
                    'is_triggered' => true,
                    'triggered_at' => now(),
                ]);
            }
        }
    }

    /**
     * Handle Calendly Webhook
     * This endpoint receives webhooks from Calendly when events are created, updated, or cancelled
     */
    public function calendly(Request $request): JsonResponse
    {
        Log::info('🔥 CALENDLY WEBHOOK HIT', [
            'time' => now(),
            'ip' => request()->ip(),
            'method' => request()->method(),
            'url' => request()->fullUrl(),
            'headers' => request()->headers->all(),
        ]);

        $body = @file_get_contents("php://input");
        Log::info('🔥 RAW WEBHOOK BODY', [
            'body' => $body
        ]);
        $payload = json_decode($body, true);
        Log::info('🔥 JSON DECODE RESULT', [
            'payload' => $payload
        ]);

        // 🔹 1. Raw webhook log
        ReportWebhookLogModel::create([
            'type'    => 'Calendly',
            'body'    => $body,
            'headers' => json_encode($request->headers->all())
        ]);

        if (!$payload) {
            Log::error('Calendly: Invalid JSON payload');
            return response()->json(['error' => 'Invalid webhook payload'], 400);
        }

        // 🔹 2. Event type
        $eventType   = $payload['event'] ?? null;
        Log::info('🔥 JSON DECODE RESULT', [
            'payload' => $payload
        ]);
        $payloadData = $payload['payload'] ?? $payload;

        Log::info('Calendly webhook received', [
            'event_type' => $eventType
        ]);

        // 🔹 3. Invitee + Event
        $invitee   = $payloadData['invitee'] ?? null;
        $eventData = $payloadData['event'] ?? $payloadData['scheduled_event'] ?? null;

        if (!$invitee || !isset($invitee['uri'])) {
            Log::error('Calendly: Invitee missing', ['payload' => $payloadData]);
            return response()->json(['error' => 'Invalid invitee data'], 400);
        }

        $inviteeUri = $invitee['uri'];
        $eventUri   = $eventData['uri'] ?? null;

        Log::info('Calendly invitee identified', [
            'invitee_uri' => $inviteeUri,
            'event_uri'   => $eventUri
        ]);

        // 🔹 4. Meeting URL
        $meetingUrl = $invitee['event_guest_url']
            ?? ($invitee['event_guest_urls'][0] ?? null)
            ?? ($eventData['location']['join_url'] ?? null)
            ?? ($eventData['location']['location'] ?? null);

        Log::info('Calendly meeting URL', [
            'meeting_url' => $meetingUrl
        ]);

        // 🔹 5. Investor tracking
        $investorId = $invitee['tracking']['a1']
            ?? ($eventData['tracking']['a1'] ?? null);

        $investorId = is_numeric($investorId) ? (int) $investorId : null;

        Log::info('Calendly investor tracking', [
            'tracking' => $invitee['tracking'] ?? null,
            'investor_id' => $investorId
        ]);

        if ($investorId) {
            $investor = InvestorModel::where('id', $investorId)
                ->where('is_deleted', 0)
                ->first();

            if (!$investor) {
                Log::warning('Calendly: Invalid investor_id', [
                    'investor_id' => $investorId
                ]);
                $investorId = null;
            }
        }

        // 🔹 6. Time parsing (MOST COMMON ISSUE)
        $scheduledStartTime = null;
        $scheduledEndTime   = null;

        try {
            if ($eventData) {
                $scheduledStartTime = isset($eventData['start_time'])
                    ? Carbon::parse($eventData['start_time'])
                    : null;

                $scheduledEndTime = isset($eventData['end_time'])
                    ? Carbon::parse($eventData['end_time'])
                    : null;
            }
        } catch (Throwable $e) {
            Log::error('Calendly time parse failed', [
                'error' => $e->getMessage(),
                'event_data' => $eventData
            ]);
        }

        Log::info('Calendly schedule parsed', [
            'start' => $scheduledStartTime,
            'end'   => $scheduledEndTime
        ]);

        // 🔹 7. Booking lookup
        $booking = InvestorConsultancySlotModel::where(
            'calendly_invitee_uri',
            $inviteeUri
        )->first();

        Log::info('Calendly booking lookup', [
            'booking_found' => (bool) $booking,
            'booking_id' => $booking->id ?? null
        ]);

        // 🔹 8. Slot count
        $existingSlotCount = 0;
        if ($scheduledStartTime && $scheduledEndTime) {
            $existingSlotCount = InvestorConsultancySlotModel::where(
                'scheduled_start_time',
                $scheduledStartTime
            )
                ->where('scheduled_end_time', $scheduledEndTime)
                ->where('status', '!=', 'cancelled')
                ->count();
        }

        Log::info('Calendly slot count', [
            'existing_count' => $existingSlotCount
        ]);

        // 🔹 9. Basic details
        $email = $invitee['email'] ?? null;

        $nameParts = explode(' ', $invitee['name'] ?? '', 2);
        $firstName = $nameParts[0] ?? '';
        $lastName  = $nameParts[1] ?? '';

        // 🔹 10. Phone
        $phoneNumber = null;
        $phoneCountryCode = '91';

        if (!empty($invitee['questions_and_answers'])) {
            foreach ($invitee['questions_and_answers'] as $qa) {
                if (str_contains(strtolower($qa['question'] ?? ''), 'phone')) {
                    $phoneNumber = preg_replace('/[^0-9]/', '', $qa['answer'] ?? '');
                    if (strlen($phoneNumber) > 10) {
                        $phoneCountryCode = substr($phoneNumber, 0, -10);
                        $phoneNumber = substr($phoneNumber, -10);
                    }
                    break;
                }
            }
        }

        // 🔹 11. SAVE / UPDATE WITH TRY–CATCH (THIS IS THE KEY)
        try {
            if ($eventType === 'invitee.created') {

                $status = $existingSlotCount >= 2 ? 'full' : 'confirmed';

                if ($booking) {
                    $booking->update([
                        'investor_id' => $booking->investor_id ?: $investorId,
                        'calendly_event_uri' => $eventUri,
                        'calendly_meeting_url' => $meetingUrl,
                        'scheduled_start_time' => $scheduledStartTime,
                        'scheduled_end_time' => $scheduledEndTime,
                        'status' => $status,
                        'calendly_event_data' => $payload,
                    ]);

                    Log::info('Calendly booking updated', [
                        'booking_id' => $booking->id
                    ]);
                } else {
                    Log::info('🔥 ABOUT TO INSERT BOOKING', [
                        'investor_id' => $investorId,
                        'invitee_uri' => $inviteeUri,
                        'event_uri' => $eventUri,
                        'start' => $scheduledStartTime,
                        'end' => $scheduledEndTime,
                    ]);
                    InvestorConsultancySlotModel::create([
                        'investor_id' => $investorId,
                        'calendly_event_uri' => $eventUri,
                        'calendly_invitee_uri' => $inviteeUri,
                        'calendly_meeting_url' => $meetingUrl,
                        'scheduled_start_time' => $scheduledStartTime,
                        'scheduled_end_time' => $scheduledEndTime,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email ?? '',
                        'mobile_number' => $phoneNumber,
                        'mobile_country_code' => $phoneCountryCode,
                        'status' => $status,
                        'calendly_event_data' => $payload,
                    ]);

                    Log::info('Calendly booking created', [
                        'invitee_uri' => $inviteeUri
                    ]);
                    Log::info('✅ BOOKING INSERTED SUCCESSFULLY');
                }
            }
        } catch (Throwable $e) {
            Log::error('❌ DB INSERT FAILED', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }


        return response()->json(['message' => 'Webhook processed successfully'], 200);
    }
}
