<x-default-layout>
    @section('title')
    {{ getPageTitle() }}
    @endsection

    @section('breadcrumbs')
    {{ Breadcrumbs::render('primarytransactions.pending') }}
    @endsection

    <div class="d-flex flex-column flex-lg-row fv-plugins-bootstrap5 fv-plugins-framework">
        <div class="d-flex flex-column flex-lg-row-fluid gap-6 gap-lg-10">
            <div class="card card-flush py-4">
                <div class="card-header">
                    <div class="card-title">
                        <h2>List</h2>
                    </div>
                    <div class="card-toolbar">
                        <a href="{{ route('admin.preipotransaction.orderSteps') }}" class="btn btn-sm btn-light-primary me-2">Partner order steps</a>
                        {{-- <a href="{{ route('admin.startup.mis.create') }}" class="btn btn-sm btn-primary">
                            Create
                        </a> --}}
                        @if (request()->routeIs('admin.preipotransaction.pending'))
                        <a href="{{ route('admin.preipotransaction.pending') }}" title="Refresh"
                            class="btn btn-success hover-elevate-up btn-icon btn-sm me-1">
                            <i class="fas fa-refresh fs-6"></i>
                        </a>
                        @elseif(request()->routeIs('admin.preipotransaction.rejected'))
                        <a href="{{ route('admin.preipotransaction.rejected') }}" title="Refresh"
                            class="btn btn-success hover-elevate-up btn-icon btn-sm me-1">
                            <i class="fas fa-refresh fs-6"></i>
                        </a>
                        @elseif(request()->routeIs('admin.preipotransaction.completed'))
                        <a href="{{ route('admin.preipotransaction.completed') }}" title="Refresh"
                            class="btn btn-success hover-elevate-up btn-icon btn-sm me-1">
                            <i class="fas fa-refresh fs-6"></i>
                        </a>
                        @endif
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="d-flex flex-column gap-5 gap-md-7">
                        <div class="table-responsive">
                            {!! $dataTable->table(['class' => 'table table-row-bordered gy-5 gs-7']) !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Last Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="updateStatusForm" method="POST">
                        @csrf
                        <input type="hidden" name="transaction_id" id="status_transaction_id">
                        <div class="fv-row mb-5">
                            <label class="required form-label">Last Status</label>
                            <select class="form-select" name="last_status" id="last_status" required>
                                <option value="">Select Status</option>
                                <option value="0">Market Order</option>
                                <option value="3">Dealslip Signed</option>
                                <option value="4">Payment Done</option>
                            </select>
                            <div class="invalid-feedback">Please select a status.</div>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirmStatusBtn">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Documents</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadDocumentForm"
                        action="{{ route('admin.preipotransaction.uploadDocument.storeDealSlip') }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="transaction_id" id="transaction_id">
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Select Type</label>
                                <select class="form-select" name="doc_type" id="doc_type" aria-label="Select example">
                                    <option value="">Select Document Type</option>
                                    @foreach (App\Enums\DocumentTypeEnum::singlePreIPO() as $doc_type)
                                    <option value="{{ $doc_type->value }}" {{ old('doc_type')==$doc_type->value ?
                                        'selected' : '' }}>
                                        {{ $doc_type->value }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Upload File</label>
                                <input type="file" name="doc_file" class="form-control" accept=".pdf">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'doc_file',
                                ])
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Upload</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
    {!! $dataTable->scripts() !!}

    <script>
        $(document).ready(function() {
                let table = $("#preipo_transactions_table").DataTable({
                    processing: true,
                    serverSide: true,
                    destroy: true, // Prevent reinitialization error
                    ajax: {
                        url: "{{ request()->routeIs('admin.preipotransaction.completed') ? route('admin.preipotransaction.completed') : (request()->routeIs('admin.preipotransaction.rejected') ? route('admin.preipotransaction.rejected') : route('admin.preipotransaction.pending')) }}",
                        data: function(d) {
                            let urlParams = new URLSearchParams(window.location.search);
                            if (urlParams.has("investor_key")) {
                                d.investor_key = urlParams.get("investor_key");
                            }
                        },
                        complete: function() {
                            KTMenu.createInstances(); // Reinitialize dropdowns
                        }
                    },
                    columns: [{
                            data: "investor",
                            name: "investor",
                            searchable: true
                        },
                        {
                            data: "company",
                            name: "company",
                            searchable: true
                        },
                        {
                            data: "investment_amount",
                            name: "investment_amount",
                            searchable: true
                        },
                        {
                            data: "share_price",
                            name: "share_price",
                            searchable: true
                        },
                        // {
                        //     data: "current_status",
                        //     name: "current_status",
                        //     searchable: false
                        // },
                        {
                            data: "created_at",
                            name: "created_at",
                            searchable: true
                        },
                        {
                            data: "action",
                            orderable: false,
                            searchable: false
                        }
                    ],
                    order: [
                        [5, 'desc']
                    ], // Sorting by date in descending order
                    pageLength: 10,
                    searching: true,
                    language: {
                        lengthMenu: "Show _MENU_ records per page",
                        zeroRecords: "No matching records found",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        infoEmpty: "No records available",
                        infoFiltered: "(filtered from _MAX_ total records)"
                    },
                    dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                        "<'row'<'col-sm-12'tr>>" +
                        "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
                });

                // Reinitialize dropdown menu after each draw
                table.on('draw.dt', function() {
                    KTMenu.createInstances();
                });

                // Handle upload document modal
                $(document).on('click', '.uploadDocumentBtn', function() {
                    var transactionId = $(this).data('transactionid');
                    $('#transaction_id').val(transactionId);
                    $('#uploadDocumentModal').modal('show');
                });

                // Handle file upload form submission
                $('#uploadDocumentForm').on('submit', function(e) {
                    e.preventDefault();
                    showSpinningLoader(true);

                    var formData = new FormData(this);

                    $.ajax({
                        type: "POST",
                        url: $(this).attr('action'),
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(response) {
                            showSpinningLoader(false);

                            if (response.status === 1) {
                                $('#uploadDocumentModal').modal('hide');
                                $('#uploadDocumentForm')[0].reset();
                                showErrorMessage(response.message, "success");
                                table.ajax.reload(null, false);
                            } else {
                                showErrorMessage(response.message, "error");
                            }
                        },
                        error: function(xhr) {
                            showSpinningLoader(false);
                            let response = xhr.responseJSON;

                            if (xhr.status === 422) {
                                let errorMessage = response.message || "Validation failed.";
                                showErrorMessage(errorMessage, "error");
                            } else {
                                showErrorMessage(response.message ||
                                    "Error uploading document. Please try again.", "error");
                            }
                        }
                    });
                });
                $('#updateStatusModal').on('click', '#confirmStatusBtn', function () {
                    const select = $('#last_status');

                    if (!select.val()) {
                        select.addClass('is-invalid');
                        return;
                    }
                    select.removeClass('is-invalid');

                    const transactionId = $('#status_transaction_id').val();
                    const lastStatus    = select.val();

                    if (!confirm('Are you sure you want to retrieve this cancelled transaction? The investor will be notified.')) {
                        return;
                    }

                    // Show loader INSIDE modal, keep modal open
                    showSpinningLoader(true);

                    axios.post(
                        "{{ route('admin.preipotransaction.retrieve', ['transaction_id' => '__ID__']) }}"
                            .replace('__ID__', transactionId),
                        { last_status: lastStatus }
                    )
                    .then(function (response) {
                        showSpinningLoader(false);
                        if (response.data.status) {
                            // Hide modal only after success
                            $('#updateStatusModal').modal('hide');
                            showErrorMessage(response.data.message, 'success');
                            setTimeout(() => {
                                $('#preipo_transactions_table').DataTable().ajax.reload();
                            }, 1500);
                        } else {
                            // Keep modal open on error so user can retry
                            $('#updateStatusModal').modal('hide');
                            showErrorMessage(response.data.message, 'error');
                        }
                    })
                    .catch(function (error) {
                        showSpinningLoader(false);
                        // Keep modal open on exception so user can retry
                        $('#updateStatusModal').modal('hide');
                        console.error('There was an error!', error);
                        showErrorMessage('An error occurred while retrieving the transaction', 'error');
                    });
                });

                // Clear validation on change
                $('#updateStatusModal').on('change', '#last_status', function () {
                    $(this).removeClass('is-invalid');
                });
            });

            function downloadDealSlipPDF(transactionId) {
                const btn = document.querySelector(`[data-transaction-id="${transactionId}"]`);
                const downloadIcon = btn.querySelector('.download-icon');
                const loadingIcon = btn.querySelector('.loading-icon');
                const downloadText = btn.querySelector('.download-text');
                
                // Show loading state
                downloadIcon.classList.add('d-none');
                loadingIcon.classList.remove('d-none');
                downloadText.textContent = 'Generating PDF...';
                btn.style.pointerEvents = 'none';
                
                // Create a temporary link and trigger download
                const link = document.createElement('a');
                link.href = `{{ route('admin.preipotransaction.downloadDealSlip', '') }}/${transactionId}`;
                link.target = '_blank';
                link.download = '';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Reset button state after a delay
                setTimeout(() => {
                    downloadIcon.classList.remove('d-none');
                    loadingIcon.classList.add('d-none');
                    downloadText.textContent = 'Download Deal Slip PDF';
                    btn.style.pointerEvents = '';
                }, 2000);
            }

            function retrieveTransaction(transactionId) {
                if (!confirm('Are you sure you want to retrieve this cancelled transaction? The investor will be notified.')) {
                    return false;
                }

                showSpinningLoader(true);
                axios.post(
                    "{{ route('admin.preipotransaction.retrieve', ['transaction_id' => '__ID__']) }}".replace('__ID__', transactionId),
                    {}
                )
                .then(function(response) {
                    showSpinningLoader(false);
                    if (response.data.status) {
                        showErrorMessage(response.data.message, 'success');
                        // Reload the table to refresh the list
                        setTimeout(() => {
                            $('#preipo_transactions_table').DataTable().ajax.reload();
                        }, 1500);
                    } else {
                        showErrorMessage(response.data.message, 'error');
                    }
                })
                .catch(function(error) {
                    showSpinningLoader(false);
                    console.error("There was an error!", error);
                    showErrorMessage('An error occurred while retrieving the transaction', 'error');
                });
                return false;
            }
            function retrieveTransaction(transactionId) {
                // Reset modal state
                $('#last_status').val('').removeClass('is-invalid');
                $('#status_transaction_id').val(transactionId);

                // Open the modal
                $('#updateStatusModal').modal('show');
                return false;
            }
    </script>
    @endpush
</x-default-layout>