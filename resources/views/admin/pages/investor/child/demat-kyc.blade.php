<div class="card">
    <div class="card-header border-0 pt-5">
        <h3 class="card-title align-items-start flex-column">
            <span class="card-label fw-bold fs-3 mb-1">Demat KYC Details</span>
            <span class="text-muted mt-1 fw-semibold fs-7">Upload CML PDF or enter details manually</span>
        </h3>
    </div>

    <!-- Add data attribute here to pass KYC status -->
    <div class="card-body py-3" data-kyc-saved="{{ $investor->preipo_kyc_status == 1 ? 'true' : 'false' }}">
        <!-- Success/Error Messages -->
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        <!-- Upload Method Toggle -->
        <div class="d-flex flex-wrap gap-5 mb-10">
            <div class="form-check form-check-custom form-check-solid">
                <input class="form-check-input" type="radio" value="pdf" id="upload_method_pdf" name="upload_method"
                    checked>
                <label class="form-check-label" for="upload_method_pdf">
                    Upload PDF & Extract Data
                </label>
            </div>
            <div class="form-check form-check-custom form-check-solid">
                <input class="form-check-input" type="radio" value="manual" id="upload_method_manual"
                    name="upload_method">
                <label class="form-check-label" for="upload_method_manual">
                    Manual Entry
                </label>
            </div>
        </div>

        <form id="dematKycForm" action="{{ route('admin.investor.demat-kyc.store', $investor->uuid) }}" method="POST"
            enctype="multipart/form-data">
            @csrf

            <!-- PDF Upload Section - Always visible -->
            <div id="pdf_upload_section" class="mb-10">
                <div class="fv-row w-100">
                    <label class="required form-label">CML PDF File</label>
                    <input type="file" name="cml_file" id="cml_file" class="form-control" accept=".pdf">
                    <div class="form-text">Upload NSDL or CDSL CML PDF file (Max: 10MB)</div>
                </div>
                <div class="mt-5" id="extract_button_container">
                    <button type="button" id="extract_data_btn" class="btn btn-primary">
                        <span class="indicator-label">Extract Data from PDF</span>
                        <span class="indicator-progress">
                            Please wait... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Form Fields Section -->
            <div id="form_fields_section">
                <!-- Demat Details -->
                <div class="separator separator-dashed my-10"></div>
                <h4 class="fw-bold mb-5">Demat Account Details</h4>

                <div class="d-flex flex-wrap gap-10 mb-5">
                    <div class="fv-row w-100 flex-md-root">
                        <label class="required form-label">DP ID</label>
                        <input type="text" name="dp_id" id="dp_id" class="form-control" placeholder="Enter DP ID"
                            value="{{ $investor->dematAccount?->dp_id }}">
                    </div>
                    <div class="fv-row w-100 flex-md-root">
                        <label class="required form-label">Client ID</label>
                        <input type="text" name="client_id" id="client_id" class="form-control"
                            placeholder="Enter Client ID" value="{{ $investor->dematAccount?->client_id }}">
                    </div>
                </div>

                <!-- Personal Details -->
                <div class="separator separator-dashed my-10"></div>
                <h4 class="fw-bold mb-5">Personal Details</h4>

                <div class="d-flex flex-wrap gap-10 mb-5">
                    <div class="fv-row w-100 flex-md-root">
                        <label class="required form-label">PAN Number</label>
                        <input type="text" name="pan_no" id="pan_no" class="form-control" placeholder="Enter PAN Number"
                            value="{{ $investor->newPan?->pan_no }}">
                    </div>
                    <div class="fv-row w-100 flex-md-root">
                        <label class="required form-label">Full Name</label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="Enter Full Name"
                            value="{{ $investor->newPan?->pan_name ?? $investor->name }}">
                    </div>
                    <div class="fv-row w-100 flex-md-root">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="dob" id="dob" class="form-control"
                            value="{{ $investor->newPan?->dob }}">
                    </div>
                </div>

                <!-- Bank Details -->
                <div class="separator separator-dashed my-10"></div>
                <h4 class="fw-bold mb-5">Bank Account Details</h4>

                <div class="d-flex flex-wrap gap-10 mb-5">
                    <div class="fv-row w-100 flex-md-root">
                        <label class="required form-label">Account Number</label>
                        <input type="text" name="account_number" id="account_number" class="form-control"
                            placeholder="Enter Account Number" value="{{ $investor->newBankAccount?->account_number }}">
                    </div>
                    <div class="fv-row w-100 flex-md-root">
                        <label class="required form-label">IFSC Code</label>
                        <input type="text" name="ifsc_code" id="ifsc_code" class="form-control"
                            placeholder="Enter IFSC Code" value="{{ $investor->newBankAccount?->ifsc_code }}">
                    </div>
                    <div class="fv-row w-100 flex-md-root">
                        <label class="form-label">Bank Name</label>
                        <input type="text" name="bank_name" id="bank_name" class="form-control"
                            placeholder="Enter Bank Name" value="{{ $investor->newBankAccount?->bank_name }}">
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="d-flex justify-content-end mt-10">
                    <button type="submit" id="submit_btn" class="btn btn-primary">
                        <span class="indicator-label">Save KYC Details</span>
                        <span class="indicator-progress">
                            Please wait... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </div>
        </form>

        <!-- Existing KYC Status Display -->
        @if($investor->dematAccount || $investor->newPan || $investor->newBankAccount)
        <div class="separator separator-dashed my-10"></div>
        <div class="alert alert-info">
            <h5 class="alert-heading">Current KYC Status</h5>
            <ul class="mb-0">
                @if($investor->dematAccount)
                <li><strong>Demat Account:</strong> {{ $investor->dematAccount->demat_account }} ✓</li>
                @endif
                @if($investor->newPan)
                <li><strong>PAN:</strong> {{ $investor->newPan->pan_no }} ✓</li>
                @endif
                @if($investor->newBankAccount)
                <li><strong>Bank Account:</strong> {{ $investor->newBankAccount->account_number }} ✓</li>
                @endif
            </ul>
        </div>
        @endif
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
    const pdfMethodRadio = document.getElementById('upload_method_pdf');
    const manualMethodRadio = document.getElementById('upload_method_manual');
    const pdfUploadSection = document.getElementById('pdf_upload_section');
    const extractButtonContainer = document.getElementById('extract_button_container');
    const extractBtn = document.getElementById('extract_data_btn');
    const submitBtn = document.getElementById('submit_btn');
    const form = document.getElementById('dematKycForm');
    
    const formFields = ['dp_id', 'client_id', 'pan_no', 'name', 'account_number', 'ifsc_code', 'bank_name', 'dob'];
    const originalValues = {};
    
    // Check if KYC is already saved from server-side
    const cardBody = document.querySelector('.card-body[data-kyc-saved]');
    let kycSaved = cardBody ? cardBody.getAttribute('data-kyc-saved') === 'true' : false;

    // Store original values on page load
    formFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            originalValues[fieldId] = field.value;
        }
    });

    function clearDobField() {
        const dobField = document.getElementById('dob');
        if (dobField) {
            dobField.value = '';
        }
    }

    function toggleUploadMethod(clearDob) {
        // Always hide PDF section if KYC is saved
        if (kycSaved) {
            pdfUploadSection.style.display = 'none';
        }

        if (pdfMethodRadio.checked) {
            // PDF mode - show extract button (if not saved), make fields readonly.
            // Clear DOB only when switching into PDF mode. A saved date stays visible on load.
            if (!kycSaved) {
                extractButtonContainer.style.display = 'block';
            }
            makeFieldsReadonly(true);
            if (clearDob) {
                clearDobField();
            }
        } else {
            // Manual mode - hide extract button, make fields editable, restore original values
            extractButtonContainer.style.display = 'none';
            makeFieldsReadonly(false);
            restoreOriginalValues();
        }
    }

    function makeFieldsReadonly(readonly) {
        formFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                if (readonly) {
                    field.setAttribute('readonly', 'readonly');
                    field.classList.add('form-control-solid');
                } else {
                    field.removeAttribute('readonly');
                    field.classList.remove('form-control-solid');
                }
            }
        });
    }

    function restoreOriginalValues() {
        formFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field && originalValues[fieldId] !== undefined) {
                field.value = originalValues[fieldId];
            }
        });
    }

    function hidePdfSection() {
        pdfUploadSection.style.display = 'none';
        kycSaved = true;
        
        // After saving, if manual mode is selected, ensure fields remain editable
        if (manualMethodRadio.checked) {
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
        const fileInput = document.getElementById('cml_file');
        const file = fileInput.files[0];

        if (!file) {
            showErrorMessage("Please select a PDF file first.", "error");
            return;
        }

        const formData = new FormData();
        formData.append('cml', file);

        extractBtn.setAttribute('data-kt-indicator', 'on');

        fetch('{{ route("admin.investor.process-demat-pdf") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 1) {
                // Fill form fields with extracted data (fields are already readonly)
                document.getElementById('dp_id').value = data.data.dp_id || '';
                document.getElementById('client_id').value = data.data.client_id || '';
                document.getElementById('pan_no').value = data.data.pan_no || '';
                document.getElementById('name').value = data.data.account_holder_name || '';
                document.getElementById('account_number').value = data.data.account_number || '';
                document.getElementById('ifsc_code').value = data.data.ifsc_code || '';
                document.getElementById('bank_name').value = data.data.bank_name || '';
                // DOB is not extracted from PDF, so keep it empty/null
                document.getElementById('dob').value = '';
                
                showErrorMessage("Data extracted successfully!", "success");
            } else {
                showErrorMessage(data.message || "Failed to extract data from PDF.", "error");
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showErrorMessage("An error occurred while processing the PDF.", "error");
        })
        .finally(() => {
            extractBtn.removeAttribute('data-kt-indicator');
        });
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        submitBtn.setAttribute('data-kt-indicator', 'on');
        
        const formData = new FormData(form);
        
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 1) {
                hidePdfSection();
                showErrorMessage("KYC details saved successfully!", "success");
            } else {
                showErrorMessage(data.message || "Failed to save KYC details.", "error");
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showErrorMessage("An error occurred while saving KYC details.", "error");
        })
        .finally(() => {
            submitBtn.removeAttribute('data-kt-indicator');
        });
    });

    // Initialize without wiping a date already loaded from the server
    toggleUploadMethod(false);
});
</script>