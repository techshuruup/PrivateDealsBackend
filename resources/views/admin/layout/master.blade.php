<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" {!! printHtmlAttributes('html') !!}>
<!--begin::Head-->

<head>
    <base href="" />
    <title>{{ getPageTitle() != '' ? getPageTitle() . ' | ' : '' }}{{ CommonHelper::appSettings('app_name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="utf-8" />
    <meta name="description" content="" />
    <meta name="keywords" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="article" />
    <meta property="og:title" content="" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="canonical" href="" />

    <link rel="icon" type="image/png" href="{{ asset('marketing/images/favicons/favicon-96x96.png') }}" sizes="96x96" />
    <link rel="shortcut icon" href="{{ asset('marketing/images/favicons/favicon.ico') }}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('marketing/images/favicons/apple-touch-icon.png') }}" />
    <link rel="manifest" href="{{ asset('marketing/images/favicons/site.webmanifest') }}">
    <meta name="msapplication-TileColor" content="#da532c">
    <meta name="theme-color" content="#ffffff">

    {{-- {!! includeFavicon() !!} --}}

    <!--begin::Fonts-->
    {!! includeFonts() !!}
    <!--end::Fonts-->

    <!--begin::Global Stylesheets Bundle(used by all pages)-->
    @foreach (getGlobalAssets('css') as $path)
        {!! sprintf('<link rel="stylesheet" href="%s">', asset($path)) !!}
    @endforeach
    <!--end::Global Stylesheets Bundle-->

    <!--begin::Vendor Stylesheets(used by this page)-->
    @foreach (getVendors('css') as $path)
        {!! sprintf('<link rel="stylesheet" href="%s">', asset($path)) !!}
    @endforeach
    <!--end::Vendor Stylesheets-->

    <!--begin::Custom Stylesheets(optional)-->
    @foreach (getCustomCss() as $path)
        {!! sprintf('<link rel="stylesheet" href="%s">', asset($path)) !!}
    @endforeach
    <!--end::Custom Stylesheets-->

    @livewireStyles
</head>
<!--end::Head-->

<!--begin::Body-->

<body {!! printHtmlClasses('body') !!} {!! printHtmlAttributes('body') !!}>

    @include('admin/partials/theme-mode/_init')
    <div id="spinningLoader" style="display: none;">
        <div class="ajaxLoad-spinner">
            <span class="ajaxLoad-spinner-round"></span>
        </div>
    </div>
    @yield('content')

    <!--begin::Javascript-->
    <!--begin::Global Javascript Bundle(mandatory for all pages)-->
    @foreach (getGlobalAssets() as $path)
        {!! sprintf('<script src="%s"></script>', asset($path)) !!}
    @endforeach
    <!--end::Global Javascript Bundle-->

    <!--begin::Vendors Javascript(used by this page)-->
    @if (!request()->routeIs('office.dashboard'))
        @foreach (getVendors('js') as $path)
            {!! sprintf('<script src="%s"></script>', asset($path)) !!}
        @endforeach
    @endif
    <!--end::Vendors Javascript-->

    <!--begin::Custom Javascript(optional)-->
    @foreach (getCustomJs() as $path)
        {!! sprintf('<script src="%s"></script>', asset($path)) !!}
    @endforeach
    <!--end::Custom Javascript-->
    @if (!request()->routeIs('office.dashboard'))
        <script src="https://code.createjs.com/1.0.0/soundjs.min.js"></script>
    @endif
    @stack('scripts')
    <!--end::Javascript-->
    <script type="text/javascript">
        @if (Session::has('error'))
            Swal.fire({
                text: '{{ Session::get('error') }}',
                icon: 'error',
                buttonsStyling: false,
                confirmButtonText: 'Ok, got it!',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
        @endif
        @if (Session::has('success'))
            Swal.fire({
                text: '{{ Session::get('success') }}',
                icon: 'success',
                buttonsStyling: false,
                confirmButtonText: 'Ok, got it!',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
        @endif
    </script>
    <script>
        // document.addEventListener('livewire:init', () => {
        //     Livewire.on('success', (message) => {
        //         toastr.success(message);
        //     });
        //     Livewire.on('error', (message) => {
        //         toastr.error(message);
        //     });

        //     Livewire.on('swal', (message, icon, confirmButtonText) => {
        //         if (typeof icon === 'undefined') {
        //             icon = 'success';
        //         }
        //         if (typeof confirmButtonText === 'undefined') {
        //             confirmButtonText = 'Ok, got it!';
        //         }
        //         Swal.fire({
        //             text: message,
        //             icon: icon,
        //             buttonsStyling: false,
        //             confirmButtonText: confirmButtonText,
        //             customClass: {
        //                 confirmButton: 'btn btn-primary'
        //             }
        //         });
        //     });
        // });
        $(document).ready(function() {
            initClasses();
        });
    </script>
    @if (!request()->routeIs('office.dashboard'))
        @include('admin.layout.partials.firebase')
        @include('admin.layout.partials.session')
        @livewireScripts
    @endif





</body>
<!--end::Body-->

</html>
