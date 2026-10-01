@extends('front.layouts.dashboard')

@section('child-content')
    <div class="tab-content" id="v-pills-tabContent">
        <div class="tab-pane fade show active" id="ui-kit" role="tabpanel" aria-labelledby="v-pills-home-tab">
            <div class="ui_content">
                <h3 class="main_title">{{ getpageTitle() }}</h3>
                <div class="d_card">
                    <form action="{{ route('front.business.channel_partner.store') }}" method="post"
                        enctype="multipart/form-data">
                        {{ csrf_field() }}
                        <div class="row">
                            <div class="col-md-4">
                                <div class="d_field_group">
                                    <label>Logo</label>
                                    <input type="file" class="d_field" name="logo"
                                        onchange="fileExAllowedWithSize(this,'{{ CommonHelper::appSettings('file_image_extensions_allowed') }}','{{ CommonHelper::appSettings('file_image_max_size') }}')">
                                    @include('front.common.input-error-message', ['key' => 'logo'])
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d_field_group">
                                    <label>Name
                                        <span class="required">*</span>
                                    </label>
                                    <input type="text" class="d_field" name="name"value="{{ old('name') }}"
                                        placeholder="Enter Name">
                                    @include('front.common.input-error-message', ['key' => 'name'])
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d_field_group">
                                    <label>Mobile No
                                        <span class="required">*</span>
                                    </label>
                                    <input type="text" class="d_field numbers" value="{{ old('mobile_number') }}"
                                        name="mobile_number" placeholder="Enter Mobile" maxlength="10" minlength="10">
                                    @include('front.common.input-error-message', [
                                        'key' => 'mobile_number',
                                    ])
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d_field_group">
                                    <label>Email
                                        <span class="required">*</span>
                                    </label>
                                    <input type="email" class="d_field" name="email" value="{{ old('email') }}"
                                        placeholder="Enter Email">
                                    @include('front.common.input-error-message', ['key' => 'email'])
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d_field_group">
                                    <label>Password
                                        <span class="required">*</span>
                                    </label>
                                    <input type="text" class="d_field" name="password" value="{{ old('password') }}"
                                        placeholder="Enter Password">
                                    @include('front.common.input-error-message', ['key' => 'name'])
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d_field_group">
                                    <label>Commission
                                        <span class="required">*</span>
                                    </label>
                                    <input type="text" class="d_field input-decimal-number" name="commission"
                                        value="{{ old('commission') }}" placeholder="Enter Commission">
                                    @include('front.common.input-error-message', ['key' => 'name'])
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d_field_group">
                                    <label>Gender
                                        <span class="required">*</span>
                                    </label>
                                    <select class="d_field" name="gender" aria-label="Select example">
                                        <option value="">-- Select Gender --</option>
                                        @foreach (App\Enums\GenderEnum::cases() as $gender)
                                            <option value="{{ $gender->value }}"
                                                {{ old('gender') == $gender->value ? 'selected' : '' }}>
                                                {{ $gender->value }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @include('front.common.input-error-message', [
                                        'key' => 'gender',
                                    ])
                                </div>
                            </div>
                            @if (Auth::guard('partner')->user()->type == \App\Enums\PartnerTypeEnum::wealthmanager->value)
                                <div class="col-md-4">
                                    <div class="d_field_group">
                                        <label>Type
                                            <span class="required">*</span>
                                        </label>
                                        <select class="d_field" name="partner" aria-label="Select example">
                                            <option value="">-- Select Partner --</option>
                                            @foreach (App\Enums\PartnerTypeEnum::cases() as $partner)
                                                @if ($partner->value === 'Distributor' || $partner->value === 'Retailer')
                                                    <option value="{{ $partner->value }}"
                                                        {{ old('partner') == $partner->value ? 'selected' : '' }}>
                                                        {{ $partner->value }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                        @include('front.common.input-error-message', [
                                            'key' => 'partner',
                                        ])
                                    </div>
                                </div>
                            @else
                                <input type="hidden" name="partner" value="{{ \App\Enums\PartnerTypeEnum::retailer }}" />
                            @endif

                        </div>
                        <div class="row mt-4">
                            <div class="col-md-4">
                                <button class="btn_custom" type="submit">Save</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
