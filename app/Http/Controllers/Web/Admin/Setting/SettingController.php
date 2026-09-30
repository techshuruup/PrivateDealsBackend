<?php

namespace App\Http\Controllers\Web\Admin\Setting;

use App\Helpers\AdminHelper;
use App\Helpers\CommonHelper;
use App\Helpers\FileUpDownHelper;
use App\Helpers\UtillsHelper;
use App\Http\Controllers\Controller;
use App\Models\AppSettingsModel;
use App\Traits\FileUploadTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SettingController extends Controller
{
    use FileUploadTrait;
    function index(): View
    {
        setPageTitle('App Settings');
        return view('admin.pages.setting.settings');
    }

    function store(Request $request): RedirectResponse
    {
        $validation = Validator::make($request->all(), [
            'app_name'                                  => 'required|max:255|string',
            'test_mobile'                               => 'nullable|digits:10',
            'test_email'                                => 'nullable|email|max:255',
            'smtp_mail_send_from'                       => 'required|max:255|email',
            'smtp_mail_send_from_name'                  => 'required|max:255',
            'smtp_mail_host'                            => 'required|max:255',
            'smtp_mail_user'                            => 'required|max:255|email',
            'smtp_mail_password'                        => 'required|max:255',
            'smtp_mail_port'                            => 'required|in:465,587',
            'third_party_wp_11za_authtoken'             => 'required',
            'third_party_wp_11za_origin_website'        => 'required|url',
            'third_party_magicsms_apikey'               => 'required|max:100',
            'third_party_magicsms_senderid'             => 'required|string|size:6',
            'file_document_max_size'                    => 'required|integer|min:10|max:50',
            'file_image_max_size'                       => 'required|integer|min:10|max:50',
            'file_video_max_size'                       => 'required|integer|min:10|max:50',
            'file_document_extensions_allowed'          => 'required|string',
            'file_image_extensions_allowed'             => 'required|string',
            'file_video_extensions_allowed'             => 'required|string',
            'google_recaptcha_sitekey'                  => 'required|string',
            'google_recaptcha_secret'                   => 'required|string',
            'branding_content_contact_email'            => 'required|email|max:255',
            'branding_content_contact_mobile'           => 'required|string|max:255',
            'branding_content_contact_address'          => 'required|string',
            'branding_content_google_map_url'           => 'required|string',
            'digio_url'                                 => 'required|url',
            'digio_client_id'                           => 'required|string|max:255',
            'digio_client_secret'                       => 'required|string|max:255',
            'digio_webhook_hash'                        => 'required|string|max:255',
            'digio_kyc_id'                              => 'required|string|max:255',
            'app_pagination_limit'                      => 'required|integer',
            'admin_panel_table_pagination_limit'        => 'required|integer',
            'processing_fee_percentage'                 => 'required|numeric|min:1|max:100',
            'pre_ipo_common_keywords'                   => 'nullable|string',
            'support_whatsapp_number'                   => 'required|string|max:20',
            'support_email'                             => 'required|email|max:255',
            'support_phone'                             => 'required|string',
            'book_slot_image'                           => 'nullable',
            'app_tutorial_video'                        => 'nullable',
        ], [], [
            'app_name'                                  => 'App Name',
            'test_mobile'                               => 'Test Mobile no.',
            'test_email'                                => 'Test Email',
            'smtp_mail_host'                            => 'Host Name',
            'smtp_mail_user'                            => 'Mail Username',
            'third_party_wp_11za_authtoken'             => 'Auth Token',
            'third_party_wp_11za_origin_website'        => 'Origin Website',
            'third_party_magicsms_apikey'               => 'Api Key',
            'third_party_magicsms_senderid'             => 'Sender ID',
            'file_document_max_size'                    => 'Document Max Upload Size',
            'file_image_max_size'                       => 'Image Max Upload Size',
            'file_document_extensions_allowed'          => 'Document Extensions Allowed',
            'file_image_extensions_allowed'             => 'Image Extensions Allowed',
            'google_recaptcha_sitekey'                  => 'Google recaptcha site key',
            'google_recaptcha_secret'                   => 'Google recaptcha secret',
            'branding_content_contact_email'            => 'Contact Email',
            'branding_content_contact_mobile'           => 'Contact Mobile',
            'branding_content_contact_address'          => 'Contact Address',
            'branding_content_google_map_url'           => 'Google Map URL',
            'pre_ipo_common_keywords'                   => 'Pre IPO Common Keywords',
            'processing_fee_percentage'                 => 'Processing Fee (%)',
            'support_whatsapp_number'                   => 'Support WhatsApp Number',
            'support_email'                             => 'Support Email',
            'support_phone'                             => 'Support Phone',
            'peripo_market_open'                        => 'Pre-IPO Market Open Time',
            'peripo_market_close'                       => 'Pre-IPO Market Close Time',
            'peripo_admin_order_accept_hours'           => 'Pre-IPO Admin Order Accept Time (in hours)',
            'preipo_investor_order_completion_hours'    => 'Pre-IPO Investor Order Completion Time (in hours)',
            'preipo_share_tranfer_hours'                => 'Pre-IPO Share Transfer Time (in hours)',
        ]);

        if ($validation->fails()) {
            return redirect()->back()->withInput()
                ->with('error', 'Please check form errors.')->withErrors($validation);
        }

        foreach ($request->all() as $key => $value) {
            AppSettingsModel::where('key', $key)->update(['value' => $value]);
        }

        if ($request->hasFile('book_slot_image')) {
            $prev = AppSettingsModel::where('key', 'book_slot_image')->value('value');
            if ($prev) {
                $this->deleteFile($prev);
            }
            $file = $request->file('book_slot_image');
            $path = FileUpDownHelper::book_slot_image_upload($file);
            if ($path) {
                AppSettingsModel::updateOrCreate(['key' => 'book_slot_image'], ['value' => $path]);
            }
        }

        if ($request->hasFile('app_tutorial_video')) {
            $prev = AppSettingsModel::where('key', 'app_tutorial_video')->value('value');
            if ($prev) {
                $this->deleteFile($prev);
            }
            $file = $request->file('app_tutorial_video');
            $path = FileUpDownHelper::app_tutorial_video_upload($file);
            if ($path) {
                AppSettingsModel::updateOrCreate(['key' => 'app_tutorial_video'], ['value' => $path]);
            }
        }
        AdminHelper::logPut('Settings changed', SettingController::class, NULL);
        return redirect()->back()->with('success', 'Settings Saved');
    }
}
