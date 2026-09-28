<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" />

    <!-- start roboto font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap"
        rel="stylesheet">
    <!-- end roboto font -->
    <!-- Hind Vadodara -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Vadodara:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <!-- custom css -->

    {{--
    <link rel="stylesheet" href="{{ asset('website-assets/css/style.css') }}"> --}}
    <link rel="stylesheet"
        href="{{ asset('website-assets/css/style.css') }}?v={{ filemtime(public_path('website-assets/css/style.css')) }}">
    <script script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <title>@yield('title')| PrivateDeals </title>

    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('core/favicon/apple-touch-icon.png') }}" />
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('core/favicon/favicon-32x32.png') }}" />
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('core/favicon/favicon-16x16.png') }}" />
    <link rel="manifest" href="{{ asset('core/favicon/site.webmanifest') }}">
    <link rel="mask-icon" href="{{ asset('core/favicon/safari-pinned-tab.svg') }}" color="#5bbad5">
    <meta name="msapplication-TileColor" content="#da532c">
    <meta name="theme-color" content="#ffffff">
</head>

<body>
    {{-- <div class="gradient-blob blob-1"></div> --}}
    <div id="spinningLoader">
        <div class="color-loader"></div>
        {{-- <div class="ajaxLoad-spinner">
            <span class="ajaxLoad-spinner-round"></span>
        </div> --}}
    </div>
    {{-- <div class="loader"></div> --}}

    @include('front.website.header')
    @yield('content')
    @include('front.website.footer')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="{{ asset('website-assets/js/app.js') }}"></script>
    <script src="{{ asset('assets/plugins/custom/lazy-master/jquery.lazy.min.js') }}"></script>
    @stack('custom-scripts')
    @include('front.website.partials.sweetalert')
    @include('front.website.partials.modals')
    @include('front.website.partials.script')
</body>

</html>