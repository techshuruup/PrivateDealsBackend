<x-default-layout>
    @section('title')
    {{ getPageTitle() }}
    @endsection

    @section('breadcrumbs')
    {{ Breadcrumbs::render('company-deals.create') }}
    @endsection

    <div class="d-flex flex-column flex-lg-row">
        <div class="w-100 flex-lg-row-auto w-lg-1200px mb-7 me-7 me-lg-10">
            <form class="form" method="POST" action="{{ route('admin.company-deals.store') }}">
                @csrf
                <div class="card card-flush py-4 mb-5">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>Deal Details</h2>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Added By</label>
                                <select class="form-select" name="created_by_partner_id" data-control="select2" data-placeholder="Select institution" required>
                                    <option value="">-- Select Institution --</option>
                                    @foreach ($institutions as $institution)
                                    @php
                                        $institutionLabel = $institution->name ?: ('Institution #' . $institution->id);
                                        $institutionMobile = trim(($institution->mobile_country_code ?? '') . ' ' . ($institution->mobile_number ?? ''));
                                        if ($institutionMobile !== '') {
                                            $institutionLabel .= ' — ' . $institutionMobile;
                                        }
                                    @endphp
                                    <option value="{{ $institution->id }}" {{ (string) old('created_by_partner_id') === (string) $institution->id ? 'selected' : '' }}>
                                        {{ $institutionLabel }}
                                    </option>
                                    @endforeach
                                </select>
                                <div class="form-text">This deal is added by an Institution.</div>
                                @include('admin.partials.form.input-error-message', ['key' => 'created_by_partner_id'])
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Company</label>
                                <select class="form-select" name="company_id" data-control="select2" data-placeholder="Select company" required>
                                    <option value="">-- Select Company --</option>
                                    @foreach ($companies as $company)
                                    <option value="{{ $company->id }}" {{ (string) old('company_id') === (string) $company->id ? 'selected' : '' }}>
                                        {{ $company->brand_name }} ({{ $company->type }})
                                    </option>
                                    @endforeach
                                </select>
                                @include('admin.partials.form.input-error-message', ['key' => 'company_id'])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Deal Type</label>
                                <select class="form-select" name="deal_type" required>
                                    @foreach (App\Enums\CompanyDealTypeEnum::cases() as $dealType)
                                    <option value="{{ $dealType->value }}" {{ old('deal_type', App\Enums\CompanyDealTypeEnum::sell->value) == $dealType->value ? 'selected' : '' }}>
                                        {{ ucfirst($dealType->value) }}
                                    </option>
                                    @endforeach
                                </select>
                                @include('admin.partials.form.input-error-message', ['key' => 'deal_type'])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Status</label>
                                <select class="form-select" name="status" required>
                                    @foreach (App\Enums\CompanyDealStatusEnum::cases() as $status)
                                    <option value="{{ $status->value }}" {{ old('status', App\Enums\CompanyDealStatusEnum::available->value) == $status->value ? 'selected' : '' }}>
                                        {{ str_replace('_', ' ', ucfirst($status->value)) }}
                                    </option>
                                    @endforeach
                                </select>
                                @include('admin.partials.form.input-error-message', ['key' => 'status'])
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Available Quantity</label>
                                <input placeholder="e.g. 1000" name="available_quantity" value="{{ old('available_quantity') }}"
                                    class="form-control mb-2 input" type="number" min="0" step="1" required>
                                @include('admin.partials.form.input-error-message', ['key' => 'available_quantity'])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Share Price</label>
                                <input placeholder="e.g. 125.50" name="share_price" value="{{ old('share_price') }}"
                                    class="form-control mb-2 input" type="number" min="0" step="0.01" required>
                                <div class="form-text">This amount is the base price. The saved share price adds the processing fee.</div>
                                @include('admin.partials.form.input-error-message', ['key' => 'share_price'])
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Minimum Qty</label>
                                <input placeholder="e.g. 10" name="minimum_qty" value="{{ old('minimum_qty') }}"
                                    class="form-control mb-2 input" type="number" min="1" step="1" required>
                                @include('admin.partials.form.input-error-message', ['key' => 'minimum_qty'])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">Processing Fee (%)</label>
                                <input value="{{ CommonHelper::processingFeePercentage() }}"
                                    class="form-control mb-2" type="text" readonly>
                                <div class="form-text">Set in App Settings. This deal saves the current admin processing fee (1–100, default 1).</div>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">Expires At</label>
                                <input name="expired_at" value="{{ old('expired_at') }}"
                                    class="form-control mb-2 input" type="datetime-local">
                                <div class="form-text">Leave empty for no expiry.</div>
                                @include('admin.partials.form.input-error-message', ['key' => 'expired_at'])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">Hot Deal</label>
                                <div class="form-check form-switch form-check-custom form-check-solid mt-3">
                                    <input class="form-check-input" type="checkbox" name="is_hot_deal" value="1"
                                        {{ old('is_hot_deal') ? 'checked' : '' }} />
                                    <label class="form-check-label">Mark as hot deal</label>
                                </div>
                                @include('admin.partials.form.input-error-message', ['key' => 'is_hot_deal'])
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <a href="{{ route('admin.company-deals.index') }}" class="btn btn-light me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-default-layout>
