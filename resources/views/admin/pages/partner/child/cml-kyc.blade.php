<div class="card card-flush py-4 mt-5" id="partner_cml_kyc">
    <div class="card-header">
        <div class="card-title">
            <h2>Demat KYC Details</h2>
        </div>
    </div>
    <div class="card-body pt-0">
        <span class="text-muted fw-semibold fs-7 d-block mb-5">Upload CML PDF or enter details manually</span>

        <div class="d-flex flex-wrap gap-5 mb-10">
            <div class="form-check form-check-custom form-check-solid">
                <input class="form-check-input" type="radio" value="pdf" id="partner_upload_method_pdf"
                    name="upload_method" checked>
                <label class="form-check-label" for="partner_upload_method_pdf">
                    Upload PDF & Extract Data
                </label>
            </div>
            <div class="form-check form-check-custom form-check-solid">
                <input class="form-check-input" type="radio" value="manual" id="partner_upload_method_manual"
                    name="upload_method">
                <label class="form-check-label" for="partner_upload_method_manual">
                    Manual Entry
                </label>
            </div>
        </div>

        <div id="partner_pdf_upload_section" class="mb-10">
            <div class="fv-row w-100">
                <label class="required form-label">CML PDF File</label>
                <input type="file" name="cml_file" id="partner_cml_file" class="form-control" accept=".pdf">
                <div class="form-text">Upload NSDL or CDSL CML PDF file (Max: 10MB)</div>
                @include('admin.partials.form.input-error-message', ['key' => 'cml_file'])
            </div>
            <div class="mt-5" id="partner_extract_button_container">
                <button type="button" id="partner_extract_data_btn" class="btn btn-primary">
                    <span class="indicator-label">Extract Data from PDF</span>
                    <span class="indicator-progress">
                        Please wait... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
            </div>
        </div>

        <div class="separator separator-dashed my-10"></div>
        <h4 class="fw-bold mb-5">Demat Account Details</h4>
        <div class="d-flex flex-wrap gap-10 mb-5">
            <div class="fv-row w-100 flex-md-root">
                <label class="required form-label">DP ID</label>
                <input type="text" name="dp_id" id="partner_dp_id" class="form-control" placeholder="Enter DP ID"
                    value="{{ old('dp_id') }}">
                @include('admin.partials.form.input-error-message', ['key' => 'dp_id'])
            </div>
            <div class="fv-row w-100 flex-md-root">
                <label class="required form-label">Client ID</label>
                <input type="text" name="client_id" id="partner_client_id" class="form-control"
                    placeholder="Enter Client ID" value="{{ old('client_id') }}">
                @include('admin.partials.form.input-error-message', ['key' => 'client_id'])
            </div>
        </div>

        <div class="separator separator-dashed my-10"></div>
        <h4 class="fw-bold mb-5">Personal Details</h4>
        <div class="d-flex flex-wrap gap-10 mb-5">
            <div class="fv-row w-100 flex-md-root">
                <label class="required form-label">PAN Number</label>
                <input type="text" name="pan_no" id="partner_pan_no" class="form-control" placeholder="Enter PAN Number"
                    value="{{ old('pan_no') }}">
                @include('admin.partials.form.input-error-message', ['key' => 'pan_no'])
            </div>
            <div class="fv-row w-100 flex-md-root">
                <label class="required form-label">Full Name</label>
                <input type="text" name="kyc_name" id="partner_kyc_name" class="form-control"
                    placeholder="Enter Full Name" value="{{ old('kyc_name') }}">
                @include('admin.partials.form.input-error-message', ['key' => 'kyc_name'])
            </div>
            <div class="fv-row w-100 flex-md-root">
                <label class="form-label">Date of Birth</label>
                <input type="date" name="dob" id="partner_dob" class="form-control" value="{{ old('dob') }}">
                @include('admin.partials.form.input-error-message', ['key' => 'dob'])
            </div>
        </div>

        <div class="separator separator-dashed my-10"></div>
        <h4 class="fw-bold mb-5">Bank Account Details</h4>
        <div class="d-flex flex-wrap gap-10 mb-5">
            <div class="fv-row w-100 flex-md-root">
                <label class="required form-label">Account Number</label>
                <input type="text" name="account_number" id="partner_account_number" class="form-control"
                    placeholder="Enter Account Number" value="{{ old('account_number') }}">
                @include('admin.partials.form.input-error-message', ['key' => 'account_number'])
            </div>
            <div class="fv-row w-100 flex-md-root">
                <label class="required form-label">IFSC Code</label>
                <input type="text" name="ifsc_code" id="partner_ifsc_code" class="form-control"
                    placeholder="Enter IFSC Code" value="{{ old('ifsc_code') }}">
                @include('admin.partials.form.input-error-message', ['key' => 'ifsc_code'])
            </div>
            <div class="fv-row w-100 flex-md-root">
                <label class="form-label">Bank Name</label>
                <input type="text" name="bank_name" id="partner_bank_name" class="form-control"
                    placeholder="Enter Bank Name" value="{{ old('bank_name') }}">
                @include('admin.partials.form.input-error-message', ['key' => 'bank_name'])
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const root = document.getElementById('partner_cml_kyc');
        if (!root) {
            return;
        }

        const pdfMethodRadio = document.getElementById('partner_upload_method_pdf');
        const manualMethodRadio = document.getElementById('partner_upload_method_manual');
        const extractButtonContainer = document.getElementById('partner_extract_button_container');
        const extractBtn = document.getElementById('partner_extract_data_btn');
        const formFields = [
            'partner_dp_id',
            'partner_client_id',
            'partner_pan_no',
            'partner_kyc_name',
            'partner_account_number',
            'partner_ifsc_code',
            'partner_bank_name',
            'partner_dob'
        ];

        function makeFieldsReadonly(readonly) {
            formFields.forEach(function(fieldId) {
                const field = document.getElementById(fieldId);
                if (!field) {
                    return;
                }
                if (readonly) {
                    field.setAttribute('readonly', 'readonly');
                    field.classList.add('form-control-solid');
                } else {
                    field.removeAttribute('readonly');
                    field.classList.remove('form-control-solid');
                }
            });
        }

        function toggleUploadMethod(clearDob) {
            if (pdfMethodRadio.checked) {
                extractButtonContainer.style.display = 'block';
                makeFieldsReadonly(true);
                if (clearDob) {
                    document.getElementById('partner_dob').value = '';
                }
            } else {
                extractButtonContainer.style.display = 'none';
                makeFieldsReadonly(false);
            }
        }

        pdfMethodRadio.addEventListener('change', function() {
            toggleUploadMethod(true);
        });
        manualMethodRadio.addEventListener('change', function() {
            toggleUploadMethod(false);
        });

        extractBtn.addEventListener('click', function() {
            const fileInput = document.getElementById('partner_cml_file');
            const file = fileInput.files[0];

            if (!file) {
                showErrorMessage('Please select a PDF file first.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('cml', file);
            extractBtn.setAttribute('data-kt-indicator', 'on');

            fetch('{{ route('admin.investor.process-demat-pdf') }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data.status === 1) {
                        document.getElementById('partner_dp_id').value = data.data.dp_id || '';
                        document.getElementById('partner_client_id').value = data.data.client_id || '';
                        document.getElementById('partner_pan_no').value = data.data.pan_no || '';
                        document.getElementById('partner_kyc_name').value = data.data.account_holder_name || '';
                        document.getElementById('partner_account_number').value = data.data.account_number || '';
                        document.getElementById('partner_ifsc_code').value = data.data.ifsc_code || '';
                        document.getElementById('partner_bank_name').value = data.data.bank_name || '';
                        document.getElementById('partner_dob').value = '';
                        showErrorMessage('Data extracted successfully!', 'success');
                    } else {
                        showErrorMessage(data.message || 'Failed to extract data from PDF.', 'error');
                    }
                })
                .catch(function() {
                    showErrorMessage('An error occurred while processing the PDF.', 'error');
                })
                .finally(function() {
                    extractBtn.removeAttribute('data-kt-indicator');
                });
        });

        toggleUploadMethod(false);
    });
</script>
