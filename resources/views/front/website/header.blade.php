<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="{{ route('home') }}">
            <img src="{{ asset('website-assets/images/logo.svg') }}" alt="PrivateDeals" class="logo">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item"><a class="nav-link {{ Request::routeIs('home') ? 'active' : '' }}"
                        href="{{ route('home') }}">Home</a></li>
                <li class="nav-item"><a class="nav-link {{ Request::routeIs('page.about') ? 'active' : '' }}"
                        href="{{ route('page.about') }}">About Us</a></li>
                {{-- <li class="nav-item"><a class="nav-link" href="#">Blog</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Jobs</a></li> --}}
                <li class="nav-item"><a class="nav-link {{ Request::routeIs('page.how-it-works') ? 'active' : '' }}"
                        href="{{ route('page.how-it-works') }}">Smart Investing</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ Request::routeIs('page.primary', 'page.secondary', 'page.unlisted') ? 'active' : '' }}"
                        href="#" id="navbarDropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true"
                        aria-expanded="false">
                        Investment Modules
                    </a>
                    <div class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                        <a class="dropdown-item {{ Request::routeIs('page.opportunities') ? 'active' : '' }}"
                            href="{{ route('page.opportunities') }}">Startup</a>
                        {{-- <a class="dropdown-item {{ Request::routeIs('front.cards.primary') ? 'active' : '' }}"
                            href="{{ route('front.cards.primary') }}">Primary</a>
                        <a class="dropdown-item {{ Request::routeIs('front.cards.secondary') ? 'active' : '' }}"
                            href="{{ route('front.cards.secondary') }}">Secondary</a> --}}
                        <a class="dropdown-item {{ Request::routeIs('page.unlisted') ? 'active' : '' }}"
                            href="{{ route('page.unlisted') }}">Private Equity</a>
                    </div>
                </li>
                {{-- <li class="nav-item"><a class="nav-link {{ Request::routeIs('front.team') ? 'active' : '' }}"
                        href="{{ route('front.team') }}">Team</a>
                </li> --}}
                <li class="nav-item"><a class="nav-link {{ Request::routeIs('page.contact') ? 'active' : '' }}"
                        href="{{ route('page.contact') }}">Contact us</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
{{-- <div class="top-spacer"></div> --}}
