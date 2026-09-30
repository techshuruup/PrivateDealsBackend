<x-default-layout>
    @section('title')
    {{ getPageTitle() }}
    @endsection

    @section('breadcrumbs')
    {{ Breadcrumbs::render('company.create') }}
    @endsection

    <div class="d-flex flex-column flex-lg-row">
        <div class="w-100 flex-lg-row-auto w-lg-1200px mb-7 me-7 me-lg-10">
            <form class="form" method="POST" action="{{ route('admin.company.save') }}" enctype="multipart/form-data">
                @csrf
                <div class="card card-flush py-4 mb-5">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>About Company</h2>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">Logo</label>
                                <input name="logo" class="form-control mb-2 input" tabindex="0" type="file"
                                    onchange="fileExAllowedWithSize(this,'{{ CommonHelper::appSettings('file_image_extensions_allowed') }}','{{ CommonHelper::appSettings('file_image_max_size') }}')">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'logo',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">CIN</label>
                                <input placeholder="Enter CIN" name="cin" value="{{ old('cin') }}"
                                    class="form-control mb-2 input" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'cin',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Sector</label>
                                <select class="form-select" name="sector" aria-label="Select example">
                                    <option value="">-- Select Sector --</option>
                                    @foreach ($sectors as $sector)
                                    <option value="{{ $sector->id }}" {{ old('sector')==$sector->id ? 'selected' : ''
                                        }}>
                                        {{ $sector->name }}
                                    </option>
                                    @endforeach
                                </select>
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'sector',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Brand Name</label>
                                <input placeholder="Enter Brand Name" name="brand_name" value="{{ old('brand_name') }}"
                                    class="form-control mb-2 input" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'brand_name',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Legal name</label>
                                <input placeholder="Enter Legal name" name="company_name"
                                    value="{{ old('company_name') }}" class="form-control mb-2 input" tabindex="0"
                                    type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'company_name',
                                ])
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            {{-- <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Types Of Investment</label>
                                <select class="form-select" name="min_investment_type"
                                    aria-label="Select Investment Type">
                                    <option value="">-- Select Investment Type --</option>
                                    @foreach (App\Enums\MinimumInvestmentTypeEnum::cases() as $type)
                                    <option value="{{ $type->value }}" {{ old('min_investment_type')===$type->value ?
                                        'selected' : '' }}>
                                        {{ ucfirst($type->value) }}
                                    </option>
                                    @endforeach
                                </select>
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'min_investment_type',
                                ])
                            </div> --}}
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Minimum Investment Amount</label>
                                <input placeholder="Enter Investment Amount" name="min_investment_amount"
                                    value="{{ old('min_investment_amount') }}"
                                    class="form-control mb-2 input-decimal-number" tabindex="0" type="text" />
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'min_investment_amount',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">About Company</label>
                                <textarea placeholder="Enter About Company" name="about" value=""
                                    class="form-control mb-2 input" tabindex="0"
                                    type="text">{{ old('about') }}</textarea>
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'about',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">Keywords</label>
                                <textarea placeholder="Enter Company Keywords" name="keywords" value=""
                                    class="form-control mb-2 input" tabindex="0"
                                    type="text">{{ old('keywords') }}</textarea>
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'keywords',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">Negative Keywords</label>
                                <textarea placeholder="Enter Company Negative Keywords" name="negative_keywords"
                                    value="" class="form-control mb-2 input" tabindex="0"
                                    type="text">{{ old('negative_keywords') }}</textarea>
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'negative_keywords',
                                ])
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">Commission</label>
                                <input value="{{ CommonHelper::processingFeePercentage() }}"
                                    class="form-control mb-2" type="text" readonly />
                                <small class="text-muted">Saved from the admin processing fee when the company is created. This form does not accept a commission.</small>
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category" aria-label="Select Investment Type">
                                    <option value="">All</option>
                                    @foreach (App\Enums\PreIpoCategoryEnum::cases() as $type)
                                    @if ($type !== App\Enums\PreIpoCategoryEnum::trending && $type !==
                                    App\Enums\PreIpoCategoryEnum::drhp)
                                    <option value="{{ $type->value }}" {{ old('category')===$type->value ? 'selected' :
                                        '' }}>
                                        {{ ucfirst($type->value) }}
                                    </option>
                                    @endif
                                    @endforeach
                                </select>
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'category',
                                ])
                                <div class="fv-row mt-4">
                                    <label class="form-check form-check-custom form-check-solid mb-2">
                                        <input class="form-check-input" type="checkbox" name="is_trending" value="1" {{
                                            old('is_trending') ? 'checked' : '' }} />
                                        <span class="form-check-label">Mark as Trending</span>
                                    </label>
                                    <label class="form-check form-check-custom form-check-solid">
                                        <input class="form-check-input" type="checkbox" name="is_drhp" value="1" {{
                                            old('is_drhp') ? 'checked' : '' }} />
                                        <span class="form-check-label">DRHP Filed</span>
                                    </label>
                                </div>
                            </div>

                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="type" aria-label="Select Company Type">
                                    @foreach (App\Enums\CompanyTypeEnum::cases() as $t)
                                        <option value="{{ $t->value }}" {{ old('type', App\Enums\CompanyTypeEnum::unlisted->value) === $t->value ? 'selected' : '' }}>
                                            {{ ucfirst($t->value) }}
                                        </option>
                                    @endforeach
                                </select>
                                @include('admin.partials.form.input-error-message', [
                                    'key' => 'type',
                                ])
                            </div>

                            <div class="fv-row fv-plugins-icon-container col-12 col-md-6 col-lg-4">
                                <label class="form-label">Background Color Code</label>
                                <input name="bg_color_code" class="form-control mb-2 input"
                                    placeholder="Enter Background Color Code" tabindex="0" type="text"
                                    value="{{ old('bg_color_code') }}">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'bg_color_code',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">List Order</label>
                                <input placeholder="Enter List Order" name="list_order" value="{{ old('list_order') }}"
                                    class="form-control mb-2 input-number" tabindex="0" type="text" />
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'list_order',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">Alternative names </label>
                                <textarea placeholder="Enter alternative names" name="alternative_names" value=""
                                    class="form-control mb-2 input" tabindex="0"
                                    type="text">{{ old('alternative_names') }}</textarea>
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'alternative_names',
                                ])
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card card-flush py-4">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>Company Fundamentals</h2>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Lot Size</label>
                                <input placeholder="Enter Lot Size" name="lot_size" value="{{ old('lot_size') }}"
                                    class="form-control mb-2 input" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', ['key' => 'lot_size'])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">52 Week High</label>
                                <input placeholder="Enter 52 Week High" name="fifty_two_week_high"
                                    value="{{ old('fifty_two_week_high') }}"
                                    class="form-control mb-2 input input-decimal-number" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'fifty_two_week_high',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">52 Week Low</label>
                                <input placeholder="Enter 52 Week Low" name="fifty_two_week_low"
                                    value="{{ old('fifty_two_week_low') }}"
                                    class="form-control mb-2 input input-decimal-number" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'fifty_two_week_low',
                                ])
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Depository</label>
                                <input placeholder="Enter Depository" name="depository" value="{{ old('depository') }}"
                                    class="form-control mb-2 input" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'depository',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">PAN Number</label>
                                <input placeholder="Enter PAN Number" name="pan_number" value="{{ old('pan_number') }}"
                                    class="form-control mb-2 input" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'pan_number',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">ISIN Number</label>
                                <input placeholder="Enter ISIN Number" name="isin_number"
                                    value="{{ old('isin_number') }}" class="form-control mb-2 input" tabindex="0"
                                    type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'isin_number',
                                ])
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            {{-- <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">CIN Number</label>
                                <input placeholder="Enter CIN Number" name="cin_number" value="{{ old('cin_number') }}"
                                    class="form-control mb-2 input" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'cin_number',
                                ])
                            </div> --}}
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">RTA</label>
                                <input placeholder="Enter RTA" name="rta" value="{{ old('rta') }}"
                                    class="form-control mb-2 input" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', ['key' => 'rta'])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Market Cap (In Cr.)</label>
                                <input placeholder="Enter Market Cap" name="market_cap" value="{{ old('market_cap') }}"
                                    class="form-control mb-2 input input-decimal-number" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'market_cap',
                                ])
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">PE Ratio</label>
                                <input placeholder="Enter PE Ratio" name="pe_ratio" value="{{ old('pe_ratio') }}"
                                    class="form-control mb-2 input input-decimal-number" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', ['key' => 'pe_ratio'])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">PB Ratio</label>
                                <input placeholder="Enter PB Ratio" name="pb_ratio" value="{{ old('pb_ratio') }}"
                                    class="form-control mb-2 input input-decimal-number" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', ['key' => 'pb_ratio'])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Debt to Equity</label>
                                <input placeholder="Enter Debt to Equity" name="debt_to_equity"
                                    value="{{ old('debt_to_equity') }}" class="form-control mb-2 input" tabindex="0"
                                    type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'debt_to_equity',
                                ])
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">ROE</label>
                                <input placeholder="Enter ROE" name="roe" value="{{ old('roe') }}"
                                    class="form-control mb-2 input input-minus-decimal-number" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', ['key' => 'roe'])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Book Value</label>
                                <input placeholder="Enter Book Value" name="book_value" value="{{ old('book_value') }}"
                                    class="form-control mb-2 input input-decimal-number" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'book_value',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Face Value</label>
                                <input placeholder="Enter Face Value" name="face_value" value="{{ old('face_value') }}"
                                    class="form-control mb-2 input input-decimal-number" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'face_value',
                                ])
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Total Shares</label>
                                <input placeholder="Enter Total Shares" name="total_shares"
                                    value="{{ old('total_shares') }}" class="form-control mb-2 input input-number"
                                    tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'total_shares',
                                ])
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card card-flush py-4 mb-5">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>Grab This Opportunity</h2>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="mb-5">
                            <label class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="is_grab_opportunity_enabled"
                                    value="1" id="grab_opportunity_toggle" {{ old('is_grab_opportunity_enabled')
                                    ? 'checked' : '' }} />
                                <span class="form-check-label">Enable Grab This Opportunity</span>
                            </label>
                        </div>
                        <div id="grab_opportunity_slots" style="display: none;">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Slot Range</th>
                                            {{-- <th>Per Share Price</th> --}}
                                            <th>Percentage (%)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>₹15,000 - ₹50,000</td>
                                            {{-- <td>Retailer Price</td> --}}
                                            <td>
                                                <input type="number" name="grab_slot_1_percentage"
                                                    value="{{ old('grab_slot_1_percentage', 0) }}" class="form-control"
                                                    step="0.01" min="0" max="100" placeholder="0">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>₹50,000 - ₹1 Lakh</td>
                                            {{-- <td>Base Price + 1%</td> --}}
                                            <td>
                                                <input type="number" name="grab_slot_2_percentage"
                                                    value="{{ old('grab_slot_2_percentage', 1) }}" class="form-control"
                                                    step="0.01" min="0" max="100" placeholder="1">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>₹1 Lakh and Above</td>
                                            {{-- <td>Base Price + 2%</td> --}}
                                            <td>
                                                <input type="number" name="grab_slot_3_percentage"
                                                    value="{{ old('grab_slot_3_percentage', 2) }}" class="form-control"
                                                    step="0.01" min="0" max="100" placeholder="2">
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card card-flush py-4 mb-5">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>Processing Fee</h2>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">Processing Fee Percentage (%)</label>
                                <input value="{{ CommonHelper::processingFeePercentage() }}"
                                    class="form-control mb-2" type="text" readonly />
                                <small class="text-muted">Set in App Settings. New companies save this admin processing fee (1–100, default 1).</small>
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'processing_fee_percentage',
                                ])
                            </div>

                            <div class="fv-row w-100 flex-md-root">
                                <div class="form-check form-switch mt-8">
                                    <input class="form-check-input" type="checkbox" name="is_free_processing_fee"
                                        value="1" id="is_free_processing_fee" {{ old('is_free_processing_fee')
                                        ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold" for="is_free_processing_fee">
                                        Free Processing Fee
                                        <span class="text-muted fw-normal ms-2 fs-7">— Show "FREE" instead of charging
                                            processing fee</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <a href="{{ route('admin.company.list') }}" class="btn btn-light me-3">Cancel</a>
                    <button type="submit" id="kt_ecommerce_edit_order_submit" class="btn btn-primary"
                        onclick="this.disabled=true;this.form.submit();">
                        <span class="indicator-label">Save</span>
                        <span class="indicator-progress">
                            Please wait... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.getElementById('grab_opportunity_toggle');
            const slotsDiv = document.getElementById('grab_opportunity_slots');
            
            function toggleSlots() {
                if (toggle.checked) {
                    slotsDiv.style.display = 'block';
                } else {
                    slotsDiv.style.display = 'none';
                }
            }
            
            toggle.addEventListener('change', toggleSlots);
            toggleSlots(); // Initial state
        });
    </script>

</x-default-layout>