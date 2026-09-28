@if (!request()->routeIs('front.raise.*'))
    <li>
        <a class="a-small a-big a_hide" href="#">Invest<i class="fa-solid fa-caret-down"></i></a>
        <ul class="submenu_custom">
            <li>
                <a href="#"><img src="{{ asset('front-assets/icons/submenu-1.svg') }}" alt=""
                        class="icon" />
                    <span class="parent">
                        <span class="child_main">Launching soon</span>
                        <span class="child">View the start-ups which will be launched soon on the
                            platform
                            and get a glance of the start-up’s product video.<< /span>
                        </span>
                </a>
            </li>
            <li>
                <a href="#"><img src="{{ asset('front-assets/icons/submenu-2.svg') }}" alt=""
                        class="icon" />
                    <span class="parent">
                        <span class="child_main">Raising Now
                            {{-- <span class="badge">Comming Soon</span> --}}
                        </span>
                        <span class="child">Jump into the details of the start-ups of your interest and
                            start your investment journey with a few clicks.</span>
                    </span>
                </a>
            </li>
            <li>
                <a href="#"><img src="{{ asset('front-assets/icons/submenu-3.svg') }}" alt=""
                        class="icon" />
                    <span class="parent">
                        <span class="child_main">Secondary Marketplace</span>
                        <span class="child">Buy and sell shares in some of the
                            hottest startups</span>
                    </span>
                </a>
            </li>
        </ul>
    </li>
@endif

@if (Auth::guard('investor')->check() || Auth::guard('startup')->check() || Auth::guard('partner')->check())
    @php
        if (Auth::guard('investor')->check()) {
            $notificationPendTotal = \App\Models\NotificationsModel::where(
                'user_type',
                \App\Models\InvestorModel::class,
            )
                ->where('user_id', Auth::guard('investor')->user()->id)
                ->where('is_readed', '0')
                ->count();
        }
        if (Auth::guard('startup')->check()) {
            $notificationPendTotal = \App\Models\NotificationsModel::where('user_type', \App\Models\StartupModel::class)
                ->where('user_id', Auth::guard('startup')->user()->id)
                ->where('is_readed', '0')
                ->count();
        }
        if (Auth::guard('partner')->check()) {
            $notificationPendTotal = \App\Models\NotificationsModel::where('user_type', \App\Models\PartnerModel::class)
                ->where('user_id', Auth::guard('partner')->user()->id)
                ->where('is_readed', '0')
                ->count();
        }
    @endphp
    <div class="notifications">
        <div class="icon">
            <i class="fa-solid fa-bell"></i>
            <div class="notify_counter notiKavaCounter" style="{{ $notificationPendTotal == 0 ? 'display:none' : '' }}">
                <p>{{ $notificationPendTotal > 9 ? '9+' : $notificationPendTotal }}
                </p>
            </div>
        </div>
        <div class="notification_box bg_style">
            <div class="notify_box_header">
                <h3>Notifications</h3>
                <div class="close"><i class="fa-solid fa-xmark"></i></div>
            </div>
            <div class="notify_box_body" id="notificationList">
                <div class="notification">
                    <div class="name_info" style="width:100%;">
                        <div class="ajaxLoad-spinner">
                            <span class="ajaxLoad-spinner-round"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="notify_box_footer" style="display: none;">
                @if (Auth::guard('investor')->check())
                    <a href="{{ route('front.investor.notifications') }}" class="show_all_btn">Show All</a>
                @endif
                @if (Auth::guard('startup')->check())
                    <a href="{{ route('front.raise.notifications') }}" class="show_all_btn">Show All</a>
                @endif
                @if (Auth::guard('partner')->check())
                    <a href="{{ route('front.business.notifications') }}" class="show_all_btn">Show All</a>
                @endif
            </div>
        </div>
    </div>
@endif

<div class="buttons_group a-small a-big">
    @if (request()->routeIs('front.raise.*'))
        @if (Auth::guard('startup')->check())
            <div class="nav_user">
                <div class="user_info">
                    <a class="avatar" href="#">
                        <img class="lazy shimmer"
                            data-src="{{ FileUpDownHelper::get_startup_logo_url(Auth::guard('startup')->user()) }}"
                            alt="">
                    </a>
                    <div class="info">
                        <a class="name" href="javascript:;">{{ Auth::guard('startup')->user()->brand_name }}</a>
                        <a class="email" href="javascript:;">{{ Auth::guard('startup')->user()->mobile_number }}</a>
                    </div>
                </div>
                <div class="user_submenu bg_style">
                    <a href="{{ route('front.raise.dashboard') }}"><i class="fa-solid fa-border-all"></i><span
                            class="text">Dashboard</span></a>
                    <a href="{{ route('front.raise.changepassword.get') }}"><i class="fa-solid fa-lock"></i><span
                            class="text">Change
                            Password</span></a>
                    <a href="{{ route('front.raise.logout') }}" class="logout">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        @else
            <div class="login-reg">
                <a href="{{ route('front.raise.auth.login') }}" class="login"><i
                        class="fas fa-user"></i><span>Login</span></a>
                <a href="{{ route('front.raise.auth.apply') }}" class="register"><i
                        class="fa-solid fa-user-gear"></i><span>Apply</span></a>
            </div>
            <a href="{{ route('home') }}" class="btn_custom">For Investor</a>
        @endif
    @elseif (request()->routeIs('front.business.*'))
        @if (Auth::guard('partner')->check())
            <div class="nav_user">
                <div class="user_info">
                    <a class="avatar" href="#">
                        <img class="lazy shimmer"
                            data-src="{{ FileUpDownHelper::get_partner_profile_photo_url(Auth::guard('partner')->user()) }}"
                            alt="">
                    </a>
                    <div class="info">
                        <a class="name" href="javascript:;">{{ Auth::guard('partner')->user()->name }}</a>
                        <a class="email" href="javascript:;">{{ Auth::guard('partner')->user()->type }}</a>
                    </div>
                </div>
                <div class="user_submenu bg_style">
                    <a href="{{ route('front.business.dashboard') }}"><i class="fa-solid fa-border-all"></i><span
                            class="text">Dashboard</span></a>
                    <a href="{{ route('front.business.changepassword.get') }}"><i class="fa-solid fa-lock"></i><span
                            class="text">Change
                            Password</span></a>
                    <a href="{{ route('front.business.profile.get') }}"><i class="fa-solid fa-user"></i><span
                            class="text">My Profile</span></a>
                    <a href="{{ route('front.business.logout') }}" class="logout">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        @else
            <div class="login-reg">
                <a href="{{ route('front.business.auth.login') }}" class="login"><i
                        class="fas fa-user"></i><span>Login</span></a>
            </div>
        @endif
    @else
        @if (Auth::guard('investor')->check())
            <div class="nav_user">
                <div class="user_info">
                    <a class="avatar" href="#">
                        <img class="lazy shimmer"
                            data-src="{{ FileUpDownHelper::get_investor_profile_photo_url(Auth::guard('investor')->user()) }}"
                            alt="">
                    </a>
                    <div class="info">
                        <a class="name" href="javascript:;">{{ Auth::guard('investor')->user()->name }}</a>
                        <a class="email"
                            href="javascript:;">{{ Auth::guard('investor')->user()->mobile_number }}</a>
                    </div>
                </div>
                <div class="user_submenu bg_style">
                    <a href="{{ route('front.investor.dashboard') }}"><i class="fa-solid fa-border-all"></i><span
                            class="text">Dashboard</span></a>
                    <a href="{{ route('front.investor.profile.profileview') }}"><i
                            class="fa-regular fa-user"></i><span class="text">Profile</span></a>
                    {{-- <a href="#"><i class="fa-solid fa-money-check"></i><span class="text">Manage
                            Bank Accounts</span></a> --}}
                    <a href="{{ route('front.investor.kyc.get') }}"><i class="fa-regular fa-id-card"></i><span
                            class="text">KYC</span></a>
                    <a href="{{ route('front.investor.changepassword.get') }}"><i class="fa-solid fa-lock"></i><span
                            class="text">Change
                            Password</span></a>
                    <a href="{{ route('front.investor.demat.get') }}"><i class="fa-solid fa-user-gear"></i><span
                            class="text">Demat
                            Account</span></a>
                    <a href="{{ route('front.investor.logout') }}" class="logout">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        @else
            <div class="login-reg">
                <a href="" class="login"><i class="fas fa-user"></i><span>Login</span></a>
            </div>
            <a href="{{ route('front.raise.home') }}" class="btn_custom">For Startups</a>
        @endif
    @endif
</div>
