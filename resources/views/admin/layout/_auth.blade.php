@extends('admin.layout.master')

@section('content')
    <!--begin::App-->
    <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
        <!--begin::Wrapper-->
        <div class="d-flex flex-column flex-column-fluid">
            <!--begin::Body-->
            <div class="d-flex flex-column flex-column-fluid p-10">
                <!--begin::Form-->
                <div class="d-flex flex-center flex-column flex-column-fluid">
                    <!--begin::Logo-->
                    <a href="{{ route('admin.dashboard') }}" class="mb-12">
                        <img alt="Logo" src="{{ asset('marketing/images/shared/main-logo.svg') }}" class="h-60px h-lg-75px" />
                    </a>
                    <!--end::Logo-->

                    <!--begin::Wrapper-->
                    <div class="w-lg-500px p-10">
                        <!--begin::Page-->
                        {{ $slot }}
                        <!--end::Page-->
                    </div>
                    <!--end::Wrapper-->
                </div>
                <!--end::Form-->

                <!--begin::Footer-->
                {{-- <div class="d-flex flex-center flex-wrap px-5">
                    <!--begin::Links-->
                    <div class="d-flex fw-semibold text-primary fs-base">
                        <a href="#" class="px-5" target="_blank">Terms</a>

                        <a href="#" class="px-5" target="_blank">Plans</a>

                        <a href="#" class="px-5" target="_blank">Contact Us</a>
                    </div>
                    <!--end::Links-->
                </div> --}}
                <!--end::Footer-->
            </div>
            <!--end::Body-->
        </div>
        <!--end::Wrapper-->
    </div>
    <!--end::App-->
@endsection
