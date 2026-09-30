<x-default-layout>
    @section('title')
    {{ getPageTitle() }}
    @endsection

    @section('breadcrumbs')
    {{ Breadcrumbs::render('company.create') }}
    @endsection

    <div class="d-flex flex-column flex-lg-row">
        <div class="w-100 flex-lg-row-auto w-lg-1200px mb-7 me-7 me-lg-10">
            <form class="form" method="POST" action="{{ route('admin.company.update') }}" enctype="multipart/form-data">
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
                                @if ($item->logo && $item->logo != null && $item->logo != '')
                                <p><a
                                        href="{{ route('download.web', ['path' => $item->logo, 'name' => 'Logo of ' . $item->brand_name]) }}">Download</a>
                                </p>
                                @endif
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">CIN</label>
                                <input placeholder="Enter CIN" name="cin" value="{{ old('cin', $item->cin) }}"
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
                                    <option value="{{ $sector->id }}" {{ old('sector', $item->sector_id) == $sector->id
                                        ? 'selected' : '' }}>
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
                                <input placeholder="Enter Brand Name" name="brand_name"
                                    value="{{ old('brand_name', $item->brand_name) }}" class="form-control mb-2 input"
                                    tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'brand_name',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Legal Name</label>
                                <input placeholder="Enter Legal Name" name="company_name"
                                    value="{{ old('company_name', $item->company_name) }}"
                                    class="form-control mb-2 input" tabindex="0" type="text">
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
                                    <option value="{{ $type->value }}" {{ old('min_investment_type', $item->
                                        min_investment_type) === $type->value ? 'selected' : '' }}>
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
                                    value="{{ old('min_investment_amount', $item->final_min_investment_amount) }}"
                                    class="form-control mb-2 input-decimal-number" tabindex="0" type="text" />
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'min_investment_amount',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">About Company</label>
                                <textarea placeholder="Enter About Company" name="about" class="form-control mb-2 input"
                                    tabindex="0">{{ old('about', $item->about) }}</textarea>
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'about',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">Keywords</label>
                                <textarea placeholder="Enter Company Keywords" name="keywords"
                                    class="form-control mb-2 input"
                                    tabindex="0">{{ old('keywords', $item->keywords) }}</textarea>
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'keywords',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">Negative Keywords</label>
                                <textarea placeholder="Enter Company Negative Keywords" name="negative_keywords"
                                    class="form-control mb-2 input"
                                    tabindex="0">{{ old('negative_keywords', $item->negative_keywords) }}</textarea>
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'negative_keywords',
                                ])
                            </div>


                        </div>

                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">Commission</label>
                                <input value="{{ $item->commission }}"
                                    class="form-control mb-2" type="text" readonly />
                                <small class="text-muted">Stored when the company was created from the admin processing fee. Update does not change it.</small>
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category" aria-label="Select Investment Type">
                                    <option value="" {{ in_array(old('category', $item->category), [null, ''], true) ? 'selected' : '' }}>All</option>
                                    @foreach (App\Enums\PreIpoCategoryEnum::cases() as $type)
                                    @if ($type !== App\Enums\PreIpoCategoryEnum::trending && $type !==
                                    App\Enums\PreIpoCategoryEnum::drhp)
                                    <option value="{{ $type->value }}" {{ old('category', $item->category) ===
                                        $type->value ? 'selected' : '' }}>
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
                                            old('is_trending', $item->is_trending) ? 'checked' : '' }} />
                                        <span class="form-check-label">Mark as Trending</span>
                                    </label>
                                    <label class="form-check form-check-custom form-check-solid">
                                        <input class="form-check-input" type="checkbox" name="is_drhp" value="1" {{
                                            old('is_drhp', $item->is_drhp ?? 0) ? 'checked' : '' }} />
                                        <span class="form-check-label">DRHP Filed</span>
                                    </label>
                                </div>
                            </div>

                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="type" aria-label="Select Company Type">
                                    @foreach (App\Enums\CompanyTypeEnum::cases() as $t)
                                        <option value="{{ $t->value }}" {{ old('type', $item->type) === $t->value ? 'selected' : '' }}>
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
                                    value="{{ old('bg_color_code', $item->bg_color_code) }}">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'bg_color_code',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">List Order</label>
                                <input placeholder="Enter List Order" name="list_order"
                                    value="{{ old('list_order',$item->list_order) }}" class="form-control mb-2"
                                    tabindex="0" type="text" />
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'list_order',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="form-label">Alternative names </label>
                                <textarea placeholder="Enter alternative names" name="alternative_names" value=""
                                    class="form-control mb-2 input" tabindex="0"
                                    type="text">{{ old('alternative_names', $item->alternative_names) }}</textarea>
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
                                <input placeholder="Enter Lot Size" name="lot_size"
                                    value="{{ old('lot_size', $item->fundamentals ? $item->fundamentals->lot_size : '') }}"
                                    class="form-control mb-2 input" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', ['key' => 'lot_size'])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">52 Week High</label>
                                <input placeholder="Enter 52 Week High" name="fifty_two_week_high"
                                    value="{{ old('fifty_two_week_high', $item->fundamentals ? $item->fundamentals->fifty_two_week_high : '') }}"
                                    class="form-control mb-2 input input-decimal-number" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'fifty_two_week_high',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">52 Week Low</label>
                                <input placeholder="Enter 52 Week Low" name="fifty_two_week_low"
                                    value="{{ old('fifty_two_week_low', $item->fundamentals ? $item->fundamentals->fifty_two_week_low : '') }}"
                                    class="form-control mb-2 input input-decimal-number" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'fifty_two_week_low',
                                ])
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Depository</label>
                                <input placeholder="Enter Depository" name="depository"
                                    value="{{ old('depository', $item->fundamentals ? $item->fundamentals->depository : '') }}"
                                    class="form-control mb-2 input" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'depository',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">PAN Number</label>
                                <input placeholder="Enter PAN Number" name="pan_number"
                                    value="{{ old('pan_number', $item->fundamentals ? $item->fundamentals->pan_number : '') }}"
                                    class="form-control mb-2 input" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'pan_number',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">ISIN Number</label>
                                <input placeholder="Enter ISIN Number" name="isin_number"
                                    value="{{ old('isin_number', $item->fundamentals ? $item->fundamentals->isin_number : '') }}"
                                    class="form-control mb-2 input" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'isin_number',
                                ])
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            {{-- <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">CIN Number</label>
                                <input placeholder="Enter CIN Number" name="cin_number"
                                    value="{{ old('cin_number', $item->fundamentals ? $item->fundamentals->cin_number : '') }}"
                                    class="form-control mb-2 input" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'cin_number',
                                ])
                            </div> --}}
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">RTA</label>
                                <input placeholder="Enter RTA" name="rta"
                                    value="{{ old('rta', $item->fundamentals ? $item->fundamentals->rta : '') }}"
                                    class="form-control mb-2 input" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', ['key' => 'rta'])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Market Cap (In Cr.)</label>
                                <input placeholder="Enter Market Cap" name="market_cap"
                                    value="{{ old('market_cap', $item->fundamentals ? $item->fundamentals->market_cap : '') }}"
                                    class="form-control mb-2 input input-decimal-number" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'market_cap',
                                ])
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">PE Ratio</label>
                                <input placeholder="Enter PE Ratio" name="pe_ratio"
                                    value="{{ old('pe_ratio', $item->fundamentals ? $item->fundamentals->pe_ratio : '') }}"
                                    class="form-control mb-2 input input-decimal-number" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', ['key' => 'pe_ratio'])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">PB Ratio</label>
                                <input placeholder="Enter PB Ratio" name="pb_ratio"
                                    value="{{ old('pb_ratio', $item->fundamentals ? $item->fundamentals->pb_ratio : '') }}"
                                    class="form-control mb-2 input input-decimal-number" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', ['key' => 'pb_ratio'])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Debt to Equity</label>
                                <input placeholder="Enter Debt to Equity" name="debt_to_equity"
                                    value="{{ old('debt_to_equity', $item->fundamentals ? $item->fundamentals->debt_to_equity : '') }}"
                                    class="form-control mb-2 input input-decimal-number" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'debt_to_equity',
                                ])
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">ROE</label>
                                <input placeholder="Enter ROE" name="roe"
                                    value="{{ old('roe', $item->fundamentals ? $item->fundamentals->roe : '') }}"
                                    class="form-control mb-2 input input-minus-decimal-number" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', ['key' => 'roe'])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Book Value</label>
                                <input placeholder="Enter Book Value" name="book_value"
                                    value="{{ old('book_value', $item->fundamentals ? $item->fundamentals->book_value : '') }}"
                                    class="form-control mb-2 input input-decimal-number" tabindex="0" type="text">
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'book_value',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Face Value</label>
                                <input placeholder="Enter Face Value" name="face_value"
                                    value="{{ old('face_value', $item->fundamentals ? $item->fundamentals->face_value : '') }}"
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
                                    value="{{ old('total_shares', $item->fundamentals ? $item->fundamentals->total_shares : '') }}"
                                    class="form-control mb-2 input input-number" tabindex="0" type="text">
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
                                    value="1" id="grab_opportunity_toggle" {{ old('is_grab_opportunity_enabled',
                                    $item->is_grab_opportunity_enabled) ? 'checked' : '' }} />
                                <span class="form-check-label">Enable Grab This Opportunity</span>
                            </label>
                        </div>
                        <div id="grab_opportunity_slots"
                            style="display: {{ old('is_grab_opportunity_enabled', $item->is_grab_opportunity_enabled) ? 'block' : 'none' }};">
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
                                        @php
                                        $slot1 = $item->grabOpportunitySlots->where('slot_number', 1)->first();
                                        $slot2 = $item->grabOpportunitySlots->where('slot_number', 2)->first();
                                        $slot3 = $item->grabOpportunitySlots->where('slot_number', 3)->first();
                                        @endphp
                                        <tr>
                                            <td>₹15,000 - ₹50,000</td>
                                            {{-- <td>Retailer Price</td> --}}
                                            <td>
                                                <input type="number" name="grab_slot_1_percentage"
                                                    value="{{ old('grab_slot_1_percentage', $slot1->percentage ?? 0) }}"
                                                    class="form-control" step="0.01" min="0" max="100" placeholder="0">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>₹50,000 - ₹1 Lakh</td>
                                            {{-- <td>Base Price + 1%</td> --}}
                                            <td>
                                                <input type="number" name="grab_slot_2_percentage"
                                                    value="{{ old('grab_slot_2_percentage', $slot2->percentage ?? 1) }}"
                                                    class="form-control" step="0.01" min="0" max="100" placeholder="1">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>₹1 Lakh and Above</td>
                                            {{-- <td>Base Price + 2%</td> --}}
                                            <td>
                                                <input type="number" name="grab_slot_3_percentage"
                                                    value="{{ old('grab_slot_3_percentage', $slot3->percentage ?? 2) }}"
                                                    class="form-control" step="0.01" min="0" max="100" placeholder="2">
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
                                <label class="required form-label">Processing Fee Percentage (%)</label>
                                <input placeholder="Enter Processing Fee %" name="processing_fee_percentage"
                                    value="{{ old('processing_fee_percentage', $item->processing_fee_percentage ?? CommonHelper::processingFeePercentage()) }}"
                                    class="form-control mb-2 input-decimal-number" tabindex="0" type="text" />
                                <small class="text-muted">Empty values use the admin processing fee (1–100, default 1).</small>
                                @include('admin.partials.form.input-error-message', [
                                'key' => 'processing_fee_percentage',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <div class="form-check form-switch mt-8">
                                    <input class="form-check-input" type="checkbox" name="is_free_processing_fee"
                                        value="1" id="is_free_processing_fee" {{ old('is_free_processing_fee',
                                        $item->is_free_processing_fee ?? false) ? 'checked' : '' }}>
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
                    <input type="hidden" value="{{ $item->id }}" name="item">
                    <a href="{{ route('admin.company.list') }}" class="btn btn-light me-3">Cancel</a>
                    <button type="submit" id="kt_ecommerce_edit_order_submit" class="btn btn-primary">
                        <span class="indicator-label">Update</span>
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