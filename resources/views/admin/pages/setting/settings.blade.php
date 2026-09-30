<x-default-layout>
    @section('title')
    {{ getPageTitle() }}
    @endsection

    @section('breadcrumbs')
    {{ Breadcrumbs::render('systemConfiguration.systemSettings') }}
    @endsection

    <div class="d-flex flex-column">
        <form class="form" method="post" enctype="multipart/form-data"
            action="{{ route('admin.systemConfiguration.systemSettings.post') }}">
            @csrf
            <div class="d-flex flex-column flex-row-fluid gap-7 gap-lg-10">
                <div class="d-flex flex-column gap-7 gap-lg-10">
                    <div class="card card-flush py-4">
                        <div class="card-header">
                            <div class="card-title">
                                <h2>General</h2>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="d-flex flex-wrap gap-5 mb-5">
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">App Name</label>
                                    <input type="text" name="app_name" class="form-control mb-2" placeholder="App name"
                                        value="{{ old('app_name', CommonHelper::appSettings('app_name')) }}">
                                    @if ($errors->has('app_name'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('app_name') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-5 mb-5">
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="form-label">Test Mobile No.</label>
                                    <input type="text" name="test_mobile" class="form-control mb-2"
                                        placeholder="Test Mobile No."
                                        value="{{ old('test_mobile', CommonHelper::appSettings('test_mobile')) }}">
                                    @if ($errors->has('test_mobile'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('test_mobile') }}</div>
                                    @endif
                                </div>
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="form-label">Test Email</label>
                                    <input type="text" name="test_email" class="form-control mb-2"
                                        placeholder="Test Email"
                                        value="{{ old('test_email', CommonHelper::appSettings('test_email')) }}">
                                    @if ($errors->has('test_email'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('test_email') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-5 mb-5">
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">In Maintenance</label>
                                    <select name="in_maintenance" class="form-select mb-2">
                                        <option value="yes" {{ old('in_maintenance',
                                            CommonHelper::appSettings('in_maintenance'))=='yes' ? 'selected' : '' }}>
                                            Yes
                                        </option>
                                        <option value="no" {{ old('in_maintenance',
                                            CommonHelper::appSettings('in_maintenance'))=='no' ? 'selected' : '' }}>
                                            No
                                        </option>
                                    </select>
                                    @if ($errors->has('in_maintenance'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('in_maintenance') }}</div>
                                    @endif
                                </div>
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">OTP Notification Type</label>
                                    <select name="default_verification_code_type" class="form-select mb-2">
                                        @foreach (App\Enums\Utills\CommunicationType::cases() as $communicationType)
                                        <option value="{{ $communicationType }}" {{ old('type',
                                            CommonHelper::appSettings('default_verification_code_type'))==$communicationType->
                                            value ? 'selected' : '' }}>
                                            {{ strtoupper($communicationType->value) }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('default_verification_code_type'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('default_verification_code_type') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card card-flush py-4">
                        <div class="card-header">
                            <div class="card-title">
                                <h2>SMTP</h2>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="d-flex flex-wrap gap-5 mb-5">

                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Mail Send From</label>
                                    <input type="text" name="smtp_mail_send_from" class="form-control mb-2"
                                        placeholder="Mail Send From"
                                        value="{{ old('smtp_mail_send_from', CommonHelper::appSettings('smtp_mail_send_from')) }}">
                                    @if ($errors->has('smtp_mail_send_from'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('smtp_mail_send_from') }}</div>
                                    @endif
                                </div>
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Mail Send From Name</label>
                                    <input type="text" name="smtp_mail_send_from_name" class="form-control mb-2"
                                        placeholder="Mail Send From Name"
                                        value="{{ old('smtp_mail_send_from_name', CommonHelper::appSettings('smtp_mail_send_from_name')) }}">
                                    @if ($errors->has('smtp_mail_send_from_name'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('smtp_mail_send_from_name') }}</div>
                                    @endif
                                </div>
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Host Name</label>
                                    <input type="text" name="smtp_mail_host" class="form-control mb-2"
                                        placeholder="Host Name"
                                        value="{{ old('smtp_mail_host', CommonHelper::appSettings('smtp_mail_host')) }}">
                                    @if ($errors->has('smtp_mail_host'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('smtp_mail_host') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-5 mb-5">
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Mail Username</label>
                                    <input type="text" name="smtp_mail_user" class="form-control mb-2"
                                        placeholder="Mail Username"
                                        value="{{ old('smtp_mail_user', CommonHelper::appSettings('smtp_mail_user')) }}">
                                    @if ($errors->has('smtp_mail_user'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('smtp_mail_user') }}</div>
                                    @endif
                                </div>
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Mail Password</label>
                                    <input type="text" name="smtp_mail_password" class="form-control mb-2"
                                        placeholder="Mail Password"
                                        value="{{ old('smtp_mail_password', CommonHelper::appSettings('smtp_mail_password')) }}">
                                    @if ($errors->has('smtp_mail_password'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('smtp_mail_password') }}</div>
                                    @endif
                                </div>
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Mail PORT</label>
                                    <input type="text" name="smtp_mail_port" class="form-control mb-2"
                                        placeholder="Mail PORT"
                                        value="{{ old('smtp_mail_port', CommonHelper::appSettings('smtp_mail_port')) }}">
                                    <div class="text-muted fs-7">The mail port should be 465 and 567.</div>
                                    @if ($errors->has('smtp_mail_port'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('smtp_mail_port') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card card-flush py-4">
                        <div class="card-header">
                            <div class="card-title">
                                <h2>Branding and Content</h2>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="d-flex flex-wrap gap-5 mb-5">

                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Contact Email</label>
                                    <input type="text" name="branding_content_contact_email" class="form-control mb-2"
                                        placeholder="Contact Email"
                                        value="{{ old('branding_content_contact_email', CommonHelper::appSettings('branding_content_contact_email')) }}">
                                    @if ($errors->has('branding_content_contact_email'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('branding_content_contact_email') }}</div>
                                    @endif
                                </div>
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Contact Mobile</label>
                                    <input type="text" name="branding_content_contact_mobile" class="form-control mb-2"
                                        placeholder="Contact Mobile"
                                        value="{{ old('branding_content_contact_mobile', CommonHelper::appSettings('branding_content_contact_mobile')) }}">
                                    @if ($errors->has('branding_content_contact_mobile'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('branding_content_contact_mobile') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-5 mb-5">
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Contact Address</label>
                                    <textarea type="text" name="branding_content_contact_address"
                                        class="form-control mb-2" placeholder="Contact Address"
                                        value="">{{ old('branding_content_contact_address', CommonHelper::appSettings('branding_content_contact_address')) }}</textarea>
                                    @if ($errors->has('branding_content_contact_address'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('branding_content_contact_address') }}</div>
                                    @endif
                                </div>
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Google Map URL</label>
                                    <input type="text" name="branding_content_google_map_url" class="form-control mb-2"
                                        placeholder="Google Map URL"
                                        value="{{ old('branding_content_google_map_url', CommonHelper::appSettings('branding_content_google_map_url')) }}">
                                    @if ($errors->has('branding_content_google_map_url'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('branding_content_google_map_url') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card card-flush py-4">
                        <div class="card-header">
                            <div class="card-title">
                                <h2>Google Api's</h2>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="d-flex flex-wrap gap-5 mb-5">
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Recaptcha Site Key</label>
                                    <input type="text" name="google_recaptcha_sitekey" class="form-control mb-2"
                                        placeholder="Recaptcha Site Key"
                                        value="{{ old('google_recaptcha_sitekey', CommonHelper::appSettings('google_recaptcha_sitekey')) }}">
                                    @if ($errors->has('google_recaptcha_sitekey'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('google_recaptcha_sitekey') }}</div>
                                    @endif
                                </div>
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Recaptcha Secret</label>
                                    <input type="text" name="google_recaptcha_secret" class="form-control mb-2"
                                        placeholder="Recaptcha Secret"
                                        value="{{ old('google_recaptcha_secret', CommonHelper::appSettings('google_recaptcha_secret')) }}">
                                    @if ($errors->has('google_recaptcha_secret'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('google_recaptcha_secret') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card card-flush py-4">
                        <div class="card-header">
                            <div class="card-title">
                                <h2>11Za WhatsApp</h2>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="d-flex flex-wrap gap-5 mb-5">
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Auth Token</label>
                                    <input type="text" name="third_party_wp_11za_authtoken" class="form-control mb-2"
                                        placeholder="Auth Token"
                                        value="{{ old('third_party_wp_11za_authtoken', CommonHelper::appSettings('third_party_wp_11za_authtoken')) }}">
                                    @if ($errors->has('third_party_wp_11za_authtoken'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('third_party_wp_11za_authtoken') }}</div>
                                    @endif
                                </div>
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Origin Website</label>
                                    <input type="text" name="third_party_wp_11za_origin_website"
                                        class="form-control mb-2" placeholder="Origin Website"
                                        value="{{ old('third_party_wp_11za_origin_website', CommonHelper::appSettings('third_party_wp_11za_origin_website')) }}">
                                    @if ($errors->has('third_party_wp_11za_origin_website'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('third_party_wp_11za_origin_website') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card card-flush py-4">
                        <div class="card-header">
                            <div class="card-title">
                                <h2>Magic SMS</h2>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="d-flex flex-wrap gap-5 mb-5">
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Api Key</label>
                                    <input type="text" name="third_party_magicsms_apikey" class="form-control mb-2"
                                        placeholder="Api Key"
                                        value="{{ old('third_party_magicsms_apikey', CommonHelper::appSettings('third_party_magicsms_apikey')) }}">
                                    @if ($errors->has('third_party_magicsms_apikey'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('third_party_magicsms_apikey') }}</div>
                                    @endif
                                </div>
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Sender ID</label>
                                    <input type="text" name="third_party_magicsms_senderid" class="form-control mb-2"
                                        placeholder="Sender ID"
                                        value="{{ old('third_party_magicsms_senderid', CommonHelper::appSettings('third_party_magicsms_senderid')) }}">
                                    @if ($errors->has('third_party_magicsms_senderid'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('third_party_magicsms_senderid') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card card-flush py-4">
                        <div class="card-header">
                            <div class="card-title">
                                <h2>File Upload</h2>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="d-flex flex-wrap gap-5 mb-5">
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Document Max Upload Size</label>
                                    <input type="text" name="file_document_max_size" class="form-control mb-2"
                                        placeholder="Document Max Upload Size"
                                        value="{{ old('file_document_max_size', CommonHelper::appSettings('file_document_max_size')) }}">
                                    <div class="text-muted fs-7">The file size is considered in MB (Do not write MB)
                                    </div>
                                    @if ($errors->has('file_document_max_size'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('file_document_max_size') }}</div>
                                    @endif
                                </div>
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Document Extensions Allowed</label>
                                    <input type="text" name="file_document_extensions_allowed" class="form-control mb-2"
                                        placeholder="Document Extensions Allowed"
                                        value="{{ old('file_document_extensions_allowed', CommonHelper::appSettings('file_document_extensions_allowed')) }}">
                                    <div class="text-muted fs-7">Enter file extentions here (Without `.`)</div>
                                    @if ($errors->has('file_document_extensions_allowed'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('file_document_extensions_allowed') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-5 mb-5">
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Image Max Upload Size</label>
                                    <input type="text" name="file_image_max_size" class="form-control mb-2"
                                        placeholder="Image Max Upload Size"
                                        value="{{ old('file_image_max_size', CommonHelper::appSettings('file_image_max_size')) }}">
                                    <div class="text-muted fs-7">The file size is considered in MB (Do not write MB)
                                    </div>
                                    @if ($errors->has('file_image_max_size'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('file_image_max_size') }}</div>
                                    @endif
                                </div>
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Image Extensions Allowed</label>
                                    <input type="text" name="file_image_extensions_allowed" class="form-control mb-2"
                                        placeholder="Image Extensions Allowed"
                                        value="{{ old('file_image_extensions_allowed', CommonHelper::appSettings('file_image_extensions_allowed')) }}">
                                    <div class="text-muted fs-7">Enter file extentions here (Without `.`)</div>
                                    @if ($errors->has('file_image_extensions_allowed'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('file_image_extensions_allowed') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-5 mb-5">
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Video Max Upload Size</label>
                                    <input type="text" name="file_video_max_size" class="form-control mb-2"
                                        placeholder="Video Max Upload Size"
                                        value="{{ old('file_video_max_size', CommonHelper::appSettings('file_video_max_size')) }}">
                                    <div class="text-muted fs-7">The file size is considered in MB (Do not write MB)
                                    </div>
                                    @if ($errors->has('file_video_max_size'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('file_video_max_size') }}</div>
                                    @endif
                                </div>
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Video Extensions Allowed</label>
                                    <input type="text" name="file_video_extensions_allowed" class="form-control mb-2"
                                        placeholder="Video Extensions Allowed"
                                        value="{{ old('file_video_extensions_allowed', CommonHelper::appSettings('file_video_extensions_allowed')) }}">
                                    <div class="text-muted fs-7">Enter file extentions here (Without `.`)</div>
                                    @if ($errors->has('file_video_extensions_allowed'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('file_video_extensions_allowed') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card card-flush py-4">
                        <div class="card-header">
                            <div class="card-title">
                                <h2>Digio Configuration</h2>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="row g-4">
                                <!-- Digio URL -->
                                <div class="col-md-6">
                                    <div class="fv-row w-100 fv-plugins-icon-container">
                                        <label class="required form-label">Digio URL</label>
                                        <input type="text" name="digio_url" class="form-control mb-2"
                                            placeholder="Digio URL"
                                            value="{{ old('digio_url', CommonHelper::appSettings('digio_url')) }}">
                                        @if ($errors->has('digio_url'))
                                        <div
                                            class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                            {{ $errors->first('digio_url') }}
                                        </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Digio Client ID -->
                                <div class="col-md-6">
                                    <div class="fv-row w-100 fv-plugins-icon-container">
                                        <label class="required form-label">Digio Client ID</label>
                                        <input type="text" name="digio_client_id" class="form-control mb-2"
                                            placeholder="Digio Client ID"
                                            value="{{ old('digio_client_id', CommonHelper::appSettings('digio_client_id')) }}">
                                        @if ($errors->has('digio_client_id'))
                                        <div
                                            class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                            {{ $errors->first('digio_client_id') }}
                                        </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Digio Client Secret -->
                                <div class="col-md-6">
                                    <div class="fv-row w-100 fv-plugins-icon-container">
                                        <label class="required form-label">Digio Client Secret</label>
                                        <input type="text" name="digio_client_secret" class="form-control mb-2"
                                            placeholder="Digio Client Secret"
                                            value="{{ old('digio_client_secret', CommonHelper::appSettings('digio_client_secret')) }}">
                                        @if ($errors->has('digio_client_secret'))
                                        <div
                                            class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                            {{ $errors->first('digio_client_secret') }}
                                        </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Digio Webhook Hash -->
                                <div class="col-md-6">
                                    <div class="fv-row w-100 fv-plugins-icon-container">
                                        <label class="required form-label">Digio Webhook Hash</label>
                                        <input type="text" name="digio_webhook_hash" class="form-control mb-2"
                                            placeholder="Digio Webhook Hash"
                                            value="{{ old('digio_webhook_hash', CommonHelper::appSettings('digio_webhook_hash')) }}">
                                        @if ($errors->has('digio_webhook_hash'))
                                        <div
                                            class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                            {{ $errors->first('digio_webhook_hash') }}
                                        </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Digio KYC ID -->
                                <div class="col-md-6">
                                    <div class="fv-row w-100 fv-plugins-icon-container">
                                        <label class="required form-label">Digio KYC Workflow</label>
                                        <input type="text" name="digio_kyc_id" class="form-control mb-2"
                                            placeholder="Digio KYC Workflow"
                                            value="{{ old('digio_kyc_id', CommonHelper::appSettings('digio_kyc_id')) }}">
                                        @if ($errors->has('digio_kyc_id'))
                                        <div
                                            class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                            {{ $errors->first('digio_kyc_id') }}
                                        </div>
                                        @endif
                                    </div>
                                </div>


                            </div>
                        </div>
                    </div>
                    <div class="card card-flush py-4">
                        <div class="card-header">
                            <div class="card-title">
                                <h2>Admin Panel Settings</h2>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="d-flex flex-wrap gap-5 mb-5">
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Admin Tables Pagination Limit</label>
                                    <input type="text" name="admin_panel_table_pagination_limit"
                                        class="form-control mb-2" placeholder="Admin Tables Pagination Limit"
                                        value="{{ old('admin_panel_table_pagination_limit', CommonHelper::appSettings('admin_panel_table_pagination_limit')) }}">
                                    @if ($errors->has('admin_panel_table_pagination_limit'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('admin_panel_table_pagination_limit') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card card-flush py-4">
                        <div class="card-header">
                            <div class="card-title">
                                <h2>Api Settings</h2>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="d-flex flex-wrap gap-5 mb-5">
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">App Pagination Limit</label>
                                    <input type="text" name="app_pagination_limit" class="form-control mb-2"
                                        placeholder="App Pagination Limit"
                                        value="{{ old('app_pagination_limit', CommonHelper::appSettings('app_pagination_limit')) }}">
                                    @if ($errors->has('app_pagination_limit'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('app_pagination_limit') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card card-flush py-4">
                        <div class="card-header">
                            <div class="card-title">
                                <h2>Processing Fee</h2>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="d-flex flex-wrap gap-5 mb-5">
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Processing Fee (%)</label>
                                    <input type="number" name="processing_fee_percentage" class="form-control mb-2"
                                        placeholder="Processing Fee (%)" min="1" max="100" step="0.01"
                                        value="{{ old('processing_fee_percentage', CommonHelper::appSettings('processing_fee_percentage') !== '' ? CommonHelper::appSettings('processing_fee_percentage') : 1) }}">
                                    <div class="form-text">Used everywhere a processing fee is applied. Integer or decimal from 1 to 100. Default 1.</div>
                                    @if ($errors->has('processing_fee_percentage'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('processing_fee_percentage') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card card-flush py-4">
                        <div class="card-header">
                            <div class="card-title">
                                <h2>Pre Ipo News Settings</h2>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="d-flex flex-wrap gap-5 mb-5">
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">News Common Keywords</label>
                                    <input type="text" name="pre_ipo_common_keywords" class="form-control mb-2"
                                        placeholder="Pre ipo Common Keywords"
                                        value="{{ old('pre_ipo_common_keywords', CommonHelper::appSettings('pre_ipo_common_keywords')) }}">
                                    @if ($errors->has('pre_ipo_common_keywords'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('pre_ipo_common_keywords') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Support Settings Card --}}
                    <div class="card card-flush py-4">
                        <div class="card-header">
                            <div class="card-title">
                                <h2>Support Settings</h2>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="d-flex flex-wrap gap-5 mb-5">

                                {{-- Support WhatsApp --}}
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Support WhatsApp Number</label>
                                    <input type="text" name="support_whatsapp_number" class="form-control mb-2"
                                        placeholder="Support WhatsApp Number (e.g. 919876543210)"
                                        value="{{ old('support_whatsapp_number', CommonHelper::appSettings('support_whatsapp_number')) }}">
                                    @if ($errors->has('support_whatsapp_number'))
                                    <div class="fv-plugins-message-container fv-plugins-message-container--enabled">
                                        <div class="fv-help-block">
                                            <span role="alert">{{ $errors->first('support_whatsapp_number') }}</span>
                                        </div>
                                    </div>
                                    @endif
                                </div>

                                {{-- Support Email --}}
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Support Email</label>
                                    <input type="email" name="support_email" class="form-control mb-2"
                                        placeholder="Support Email"
                                        value="{{ old('support_email', CommonHelper::appSettings('support_email')) }}">
                                    @if ($errors->has('support_email'))
                                    <div class="fv-plugins-message-container fv-plugins-message-container--enabled">
                                        <div class="fv-help-block">
                                            <span role="alert">{{ $errors->first('support_email') }}</span>
                                        </div>
                                    </div>
                                    @endif
                                </div>

                                {{-- Support Phone --}}
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="required form-label">Support Phone</label>
                                    <input type="text" name="support_phone" class="form-control mb-2"
                                        placeholder="Support Phone (e.g. 919876543210)"
                                        value="{{ old('support_phone', CommonHelper::appSettings('support_phone')) }}">
                                    @if ($errors->has('support_phone'))
                                    <div class="fv-plugins-message-container fv-plugins-message-container--enabled">
                                        <div class="fv-help-block">
                                            <span role="alert">{{ $errors->first('support_phone') }}</span>
                                        </div>
                                    </div>
                                    @endif
                                </div>

                                {{-- Book Slot Image --}}
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="form-label">Book Slot Image</label>
                                    <input name="book_slot_image" class="form-control mb-2 input" type="file"
                                        onchange="fileExAllowedWithSize(this,'{{ CommonHelper::appSettings('file_image_extensions_allowed') }}','{{ CommonHelper::appSettings('file_image_max_size') }}')">
                                    @if ($errors->has('book_slot_image'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('book_slot_image') }}
                                    </div>
                                    @endif
                                </div>

                                {{-- App Tutorial Video --}}
                                <div class="fv-row w-100 flex-md-root fv-plugins-icon-container">
                                    <label class="form-label">App Tutorial Video</label>
                                    <input name="app_tutorial_video" class="form-control mb-2 input" type="file"
                                        onchange="fileExAllowedWithSize(this,'{{ CommonHelper::appSettings('file_video_extensions_allowed') }}','{{ CommonHelper::appSettings('file_video_max_size') }}')">
                                    @if ($errors->has('app_tutorial_video'))
                                    <div
                                        class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
                                        {{ $errors->first('app_tutorial_video') }}
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Pre-IPO Settings Card --}}
                    <div class="card card-flush py-4">
                        <div class="card-header">
                            <div class="card-title">
                                <h2>Pre-IPO Settings</h2>
                            </div>
                        </div>

                        <div class="card-body pt-0">
                            <div class="d-flex flex-wrap gap-5 mb-5">

                                {{-- Market Open Time --}}
                                <div class="fv-row w-100 flex-md-root">
                                    <label class="form-label">Market Open Time</label>
                                    <input type="time" name="peripo_market_open" class="form-control mb-2"
                                        value="{{ old('peripo_market_open', CommonHelper::appSettings('peripo_market_open')) }}">
                                    @error('peripo_market_open')
                                    <div class="fv-help-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Market Close Time --}}
                                <div class="fv-row w-100 flex-md-root">
                                    <label class="form-label">Market Close Time</label>
                                    <input type="time" name="peripo_market_close" class="form-control mb-2"
                                        value="{{ old('peripo_market_close', CommonHelper::appSettings('peripo_market_close')) }}">
                                    @error('peripo_market_close')
                                    <div class="fv-help-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Admin Order Accept Hours --}}
                                <div class="fv-row w-100 flex-md-root">
                                    <label class="form-label">Admin Order Accept Time (in hours)</label>
                                    <input type="number" step="1" min="0" name="peripo_admin_order_accept_hours"
                                        class="form-control mb-2"
                                        value="{{ old('peripo_admin_order_accept_hours', CommonHelper::appSettings('peripo_admin_order_accept_hours')) }}">
                                    @error('peripo_admin_order_accept_hours')
                                    <div class="fv-help-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Investor Order Completion Hours --}}
                                <div class="fv-row w-100 flex-md-root">
                                    <label class="form-label">Investor Order Completion Time (in hours)</label>
                                    <input type="number" step="1" min="0" name="preipo_investor_order_completion_hours"
                                        class="form-control mb-2"
                                        value="{{ old('preipo_investor_order_completion_hours', CommonHelper::appSettings('preipo_investor_order_completion_hours')) }}">
                                    @error('preipo_investor_order_completion_hours')
                                    <div class="fv-help-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Share Transfer Hours --}}
                                <div class="fv-row w-100 flex-md-root">
                                    <label class="form-label">Share Transfer Time (in hours)</label>
                                    <input type="number" step="1" min="0" name="preipo_share_tranfer_hours"
                                        class="form-control mb-2"
                                        value="{{ old('preipo_share_tranfer_hours', CommonHelper::appSettings('preipo_share_tranfer_hours')) }}">
                                    @error('preipo_share_tranfer_hours')
                                    <div class="fv-help-block">{{ $message }}</div>
                                    @enderror
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-light me-5">
                        Cancel
                    </a>
                    <button type="submit" id="kt_ecommerce_add_product_submit" class="btn btn-success">
                        <span class="indicator-label">
                            Save Changes
                        </span>
                        <span class="indicator-progress">
                            Please wait... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </div>
        </form>
    </div>

</x-default-layout>