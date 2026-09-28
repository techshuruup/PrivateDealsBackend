@if (CommonHelper::appSettings('in_maintenance') == 'yes')
    <header class="system_page_nav">
        <div class="container_custom">
            <div class="content">
                <a href="{{ route('home') }}" class="brand"><img src="{{ asset('core/images/logo.png') }}"
                        alt="PrivateDeals Logo" /></a>
                <div class="social_link">
                    <p>Connect with us :</p>
                    <div class="links">
                        @foreach (App\Models\MasterWebsiteSocialmediaModel::where('is_deleted', 0)->orderby('display_order', 'asc')->get() as $linkKey => $linkValue)
                            <a href="{{ $linkValue->link }}" target="_blank"><i
                                    class="fab {{ $linkValue->icon }}"></i></a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </header>
@else
    <header>
        <div class="header_bg"></div>
        <div class="container_custom">
            <div id="nav-icon" class="nav_burger">
                <span></span>css
                <span></span>
                <span></span>
                <span></span>
            </div>
            <a href="{{ route('home') }}"
                class="logo_mobile"><img src="{{ asset('core/images/logo.png') }}" alt="shuru-up-logo"
                    class="logo" /></a>
            <nav>
                <ul id="toggle-element">
                    <a href="{{ route('home') }}"
                        class="logo"><img src="{{ asset('core/images/logo.png') }}" alt="shuru-up-logo"
                            class="logo" /></a>

                    @include('front.partials.childs.right-top-menu')
                </ul>
            </nav>
        </div>
    </header>
@endif
