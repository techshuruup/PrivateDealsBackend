<!doctype html>
<html lang="en" class="light" data-force-theme="light">

<head>
    <meta charset="UTF-8" />

    
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="title" content="{{ $page['title'] ?? config('app.name') }}" />
    <meta name="description" content="{{ $page['description'] ?? '' }}" />
    <meta name="author" content="Shuru Advisory Private Limited" />
    <meta name="robots" content="{{ $page['robots'] ?? 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1' }}" />
    @php
    $canonicalPath = $page['path'] ?? url()->current();
    $canonical = ($canonicalPath === '/') ? config('pages.site_url') : rtrim(config('pages.site_url'), '/') . $canonicalPath;
    $ogImage = config('pages.og_image');
    @endphp
    <link rel="canonical" href="{{ $canonical }}" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ $canonical }}" />
    <meta property="og:title" content="{{ $page['title'] ?? config('app.name') }}" />
    <meta property="og:description" content="{{ $page['description'] ?? '' }}" />
    <meta property="og:site_name" content="Private Deals" />
    <meta property="og:locale" content="en_IN" />
    <meta property="og:image" content="{{ $ogImage }}" />
    <meta property="og:image:alt" content="Private Deals" />
    <meta name="twitter:card" content="summary" />
    <meta name="twitter:image" content="{{ $ogImage }}" />
    <meta name="twitter:title" content="{{ $page['title'] ?? config('app.name') }}" />
    <meta name="twitter:description" content="{{ $page['description'] ?? '' }}" />
    <meta name="theme-color" content="#3d5a80" />
    <link rel="icon" type="image/png" href="{{ asset('marketing/images/favicons/favicon-96x96.png') }}" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('marketing/images/favicons/favicon.svg') }}" />
    <link rel="shortcut icon" href="{{ asset('marketing/images/favicons/favicon.ico') }}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('marketing/images/favicons/apple-touch-icon.png') }}" />
    <meta name="apple-mobile-web-app-title" content="Private Deals" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter+Tight:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet" />
    <title>{{ $page['title'] ?? config('app.name') }}</title>
    @stack('head')
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-8FS0TH4TSQ"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());
        gtag('config', 'G-8FS0TH4TSQ');
    </script>
    <script src="{{ asset('marketing/assets/theme-init.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('marketing/assets/main.css') }}">

    <!-- Whatsapp facebook-domain-verification -->
    <meta name="facebook-domain-verification" content="ac37f77dy4ci92goz3wgvgqcwyfec8" />
</head>

<body class="dark:bg-background-7 overflow-x-hidden bg-white">
    @include('marketing.partials.header')
    @yield('content')
    @include('marketing.partials.footer')
    @stack('scripts')
</body>

</html>
