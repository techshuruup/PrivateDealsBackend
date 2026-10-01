<x-default-layout>
    @section('title')
        {{ getPageTitle() }}
    @endsection

    @section('breadcrumbs')
        {{ Breadcrumbs::render('institution.create') }}
    @endsection

    <div class="d-flex flex-column flex-lg-row">
        <div class="w-100 flex-lg-row-auto w-lg-1200px mb-7 me-7 me-lg-10">
            <form class="form" method="POST" action="{{ route('admin.partner.institution.store') }}"
                enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="partner_type" value="{{ App\Enums\PartnerTypeEnum::institution }}">
                <div class="card card-flush py-4 mb-5">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>Basic Details</h2>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex flex-wrap gap-10">
                            @include('admin.pages.partner.partials.logo-field')
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Name</label>
                                <input name="name" class="form-control mb-2" placeholder="Enter Name" tabindex="0"
                                    type="text" value="{{ old('name') }}">
                                @include('admin.partials.form.input-error-message', ['key' => 'name'])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Mobile Number</label>
                                <input name="mobile_number" class="form-control mb-2" placeholder="Enter Mobile Number"
                                    tabindex="0" type="text" value="{{ old('mobile_number') }}">
                                @include('admin.partials.form.input-error-message', [
                                    'key' => 'mobile_number',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Email</label>
                                <input name="email" class="form-control mb-2" placeholder="Enter Email" tabindex="0"
                                    type="text" value="{{ old('email') }}">
                                @include('admin.partials.form.input-error-message', ['key' => 'email'])
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Commission(in %)</label>
                                <input name="commission" class="form-control mb-2 input-decimal-number"
                                    placeholder="Enter Commission" tabindex="0" value="{{ old('commission') }}"
                                    type="text" />
                                @include('admin.partials.form.input-error-message', [
                                    'key' => 'commission',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Password</label>
                                <input name="password" class="form-control mb-2" placeholder="Enter Password"
                                    tabindex="0" type="text" value="{{ old('password') }}">
                                @include('admin.partials.form.input-error-message', [
                                    'key' => 'password',
                                ])
                            </div>
                            <div class="fv-row w-100 flex-md-root">
                                <label class="required form-label">Gender</label>
                                <select class="form-select" name="gender" aria-label="Select example">
                                    <option value="">-- Select Gender --</option>
                                    @foreach (App\Enums\GenderEnum::cases() as $gender)
                                        <option value="{{ $gender->value }}"
                                            {{ old('gender') == $gender->value ? 'selected' : '' }}>
                                            {{ $gender->value }}
                                        </option>
                                    @endforeach
                                </select>
                                @include('admin.partials.form.input-error-message', ['key' => 'gender'])
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-10 mb-5">
                            <div class="fv-row fv-plugins-icon-container col-12 col-md-6 col-lg-4">
                                <label class="form-label">Wealth Manager</label>
                                <select class="form-select" name="parent_partner_id" aria-label="Select Partner Type">
                                    <option value="">-- Select Wealth Manager --</option>
                                    @foreach ($wealthmanagerlist as $wealthmanager)
                                        <option value="{{ $wealthmanager->id }}"
                                            {{ old('parent_partner_id') == $wealthmanager->id ? 'selected' : '' }}>
                                            {{ $wealthmanager->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @include('admin.partials.form.input-error-message', [
                                    'key' => 'parent_partner_id',
                                ])
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card card-flush py-4">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>Permissions</h2>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex flex-wrap column-gap-10">
                            <label class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" value="1" name="is_primary_access"
                                    {{ old('is_primary_access') ? 'checked' : '' }} />
                                <span class="form-check-label">
                                    Primary Startup
                                </span>
                            </label>
                            <label class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" value="1"
                                    name="is_secondary_access" {{ old('is_secondary_access') ? 'checked' : '' }} />
                                <span class="form-check-label">
                                    Secondary Startup
                                </span>
                            </label>
                            <label class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" value="1" name="is_preipo_access"
                                    {{ old('is_preipo_access') ? 'checked' : '' }} />
                                <span class="form-check-label">
                                    Pre-IPO Companies
                                </span>
                            </label>
                            @include('admin.partials.form.input-error-message', ['key' => 'permission'])
                        </div>
                    </div>
                </div>
                @include('admin.pages.partner.child.cml-kyc')
                <div class="d-flex justify-content-end mt-4">
                    <a href="{{ route('admin.partner.institution.list') }}" class="btn btn-light me-3">Cancel</a>
                    <button type="submit" id="kt_ecommerce_edit_order_submit" class="btn btn-primary">
                        <span class="indicator-label">Save</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-default-layout>
