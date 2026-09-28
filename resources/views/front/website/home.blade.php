@extends('front.website.master')
@section('title')
Home
@endsection
@section('content')
<section class="hero-section scroll-animate" style="padding-top: 5rem 0;">
    {{-- <div class="gradient-blob blob-1"></div> --}}
    {{-- <div class="gradient-blob blob-2"></div>
    <div class="gradient-blob blob-3"></div>
    <div class="gradient-blob blob-4"></div> --}}
    <div class="container">
        <div class="row align-items-center hero-row">
            <div class="col-lg-6 col-md-12 loader slide-from-left  hero-left">
                <div class="hero-content">
                    <h1 class="hero-heading">
                        <div class="reveal-container">
                            {{-- <span class="reveal-text">Unlock</span> --}}
                            <span>Unlock</span>
                            <div class="power-text-container">
                                {{-- <span class="reveal-word">the</span>
                                <span class="reveal-word">Power</span>
                                <span class="reveal-word">of</span> --}}
                                <span>the</span>
                                <span>Power</span>
                                <span>of</span>
                            </div>
                            {{-- <span class="final-text">Private Equity</span> --}}
                            <span class="highlight-blue">Private Equity</span>
                        </div>
                    </h1>
                    <p class="subtext hero-context">
                        Invest in Hidden Gems and Transformative Opportunities Beyond the Public Markets. Tap into
                        curated Pre-IPOs, high-growth startups, and institutional-grade private equity—traditionally
                        reserved for the few.
                    </p>
                    <div class="store-buttons hero-context desktop-only">

                        <a href="https://play.google.com/store/apps/developer?id=PrivateDeals"
                            class="store-button play-store hero-context" target="_blank"
                            onclick="addRipple(event, this)">
                            <svg class="store-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.61 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.53,12.92 20.18,13.18L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z" />
                            </svg>
                            <div class="store-text">
                                <div class="store-text-small">Download on</div>
                                <div class="store-text-large">Play Store</div>
                            </div>
                        </a>

                        <a href="https://apps.apple.com/us/developer/shuru-advisory-private-limited/id1773764563"
                            target="_blank" class="store-button app-store hero-context"
                            onclick="addRipple(event, this)">
                            <svg class="store-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M18.71,19.5C17.88,20.74 17,21.95 15.66,21.97C14.32,22 13.89,21.18 12.37,21.18C10.84,21.18 10.37,21.95 9.1,22C7.79,22.05 6.8,20.68 5.96,19.47C4.25,17 2.94,12.45 4.7,9.39C5.57,7.87 7.13,6.91 8.82,6.88C10.1,6.86 11.32,7.75 12.11,7.75C12.89,7.75 14.37,6.68 15.92,6.84C16.57,6.87 18.39,7.1 19.56,8.82C19.47,8.88 17.39,10.1 17.41,12.63C17.44,15.65 20.06,16.66 20.09,16.67C20.06,16.74 19.67,18.11 18.71,19.5M13,3.5C13.73,2.67 14.94,2.04 15.94,2C16.07,3.17 15.6,4.35 14.9,5.19C14.21,6.04 13.07,6.7 11.95,6.61C11.8,5.46 12.36,4.26 13,3.5Z" />
                            </svg>
                            <div class="store-text">
                                <div class="store-text-small">Get it on</div>
                                <div class="store-text-large">App Store</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            {{-- <div class="col-lg-6 col-sm-12 col-md-12">
                <div class="hero-images">
                    <div class="image-row">
                        <div class="image-item">
                            <img src="{{ asset('website-assets/images/mockups/home/1111.png') }}"
                                alt="Investment Dashboard" />
                        </div>
                        <div class="image-item">
                            <img src="{{ asset('website-assets/images/mockups/home/4444.png') }}"
                                alt="Market Analysis" />
                        </div>
                    </div>
                    <div class="image-row">
                        <div class="image-item">
                            <img src="{{ asset('website-assets/images/mockups/home/2222.png') }}"
                                alt="Portfolio Management" />
                        </div>
                        <div class="image-item">
                            <img src="{{ asset('website-assets/images/mockups/home/3333.png') }}"
                                alt="Financial Growth" />
                        </div>
                    </div>
                </div>
            </div> --}}

            <div class="col-lg-6 col-sm-12 col-md-12 hero-right">
                <div class="hero-video-container">
                    <video class="hero-video" autoplay muted playsinline>
                        <source src="{{ asset('website-assets/images/mockups/home_video.mp4') }}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>
            </div>

            <div class="store-buttons hero-context mobile-only">
                <a href="https://play.google.com/store/apps/developer?id=PrivateDeals"
                    class="store-button play-store hero-context" target="_blank" onclick="addRipple(event, this)">
                    <svg class="store-icon" viewBox="0 0 24 24" fill="currentColor">
                        <path
                            d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.61 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.53,12.92 20.18,13.18L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z" />
                    </svg>
                    <div class="store-text">
                        <div class="store-text-small">Download on</div>
                        <div class="store-text-large">Play Store</div>
                    </div>
                </a>

                <a href="https://apps.apple.com/us/developer/shuru-advisory-private-limited/id1773764563"
                    target="_blank" class="store-button app-store hero-context" onclick="addRipple(event, this)">
                    <svg class="store-icon" viewBox="0 0 24 24" fill="currentColor">
                        <path
                            d="M18.71,19.5C17.88,20.74 17,21.95 15.66,21.97C14.32,22 13.89,21.18 12.37,21.18C10.84,21.18 10.37,21.95 9.1,22C7.79,22.05 6.8,20.68 5.96,19.47C4.25,17 2.94,12.45 4.7,9.39C5.57,7.87 7.13,6.91 8.82,6.88C10.1,6.86 11.32,7.75 12.11,7.75C12.89,7.75 14.37,6.68 15.92,6.84C16.57,6.87 18.39,7.1 19.56,8.82C19.47,8.88 17.39,10.1 17.41,12.63C17.44,15.65 20.06,16.66 20.09,16.67C20.06,16.74 19.67,18.11 18.71,19.5M13,3.5C13.73,2.67 14.94,2.04 15.94,2C16.07,3.17 15.6,4.35 14.9,5.19C14.21,6.04 13.07,6.7 11.95,6.61C11.8,5.46 12.36,4.26 13,3.5Z" />
                    </svg>
                    <div class="store-text">
                        <div class="store-text-small">Get it on</div>
                        <div class="store-text-large">App Store</div>
                    </div>
                </a>
            </div>
        </div>
</section>


{{-- <section class="hero-section" style="padding-top: 5rem 0;">
    <div class="gradient-blob blob-2"></div>
    <div class="gradient-blob blob-3"></div>
    <div class="gradient-blob blob-4"></div>
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 col-md-12 loader">
                <div class="hero-content">
                    <h1 class="hero-heading">
                        <div class="reveal-container">
                            <span class="reveal-text">Unlock</span>
                            <div class="power-text-container">
                                <span class="reveal-word">the</span>
                                <span class="reveal-word">Power</span>
                                <span class="reveal-word">of</span>
                            </div>
                            <span class="final-text">Private Equity</span>
                        </div>
                    </h1>
                    <p class="subtext hero-context">
                        Invest in Hidden Gems and Transformative Opportunities Beyond
                        the Public Markets.
                    </p>
                    <div class="store-buttons hero-context">

                        <a href="https://play.google.com/store/apps/developer?id=PrivateDeals"
                            class="store-button play-store hero-context" target="_blank"
                            onclick="addRipple(event, this)">
                            <svg class="store-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.61 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.53,12.92 20.18,13.18L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z" />
                            </svg>
                            <div class="store-text">
                                <div class="store-text-small">Download on</div>
                                <div class="store-text-large">Play Store</div>
                            </div>
                        </a>

                        <a href="https://apps.apple.com/us/developer/shuru-advisory-private-limited/id1773764563"
                            target="_blank" class="store-button app-store hero-context"
                            onclick="addRipple(event, this)">
                            <svg class="store-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M18.71,19.5C17.88,20.74 17,21.95 15.66,21.97C14.32,22 13.89,21.18 12.37,21.18C10.84,21.18 10.37,21.95 9.1,22C7.79,22.05 6.8,20.68 5.96,19.47C4.25,17 2.94,12.45 4.7,9.39C5.57,7.87 7.13,6.91 8.82,6.88C10.1,6.86 11.32,7.75 12.11,7.75C12.89,7.75 14.37,6.68 15.92,6.84C16.57,6.87 18.39,7.1 19.56,8.82C19.47,8.88 17.39,10.1 17.41,12.63C17.44,15.65 20.06,16.66 20.09,16.67C20.06,16.74 19.67,18.11 18.71,19.5M13,3.5C13.73,2.67 14.94,2.04 15.94,2C16.07,3.17 15.6,4.35 14.9,5.19C14.21,6.04 13.07,6.7 11.95,6.61C11.8,5.46 12.36,4.26 13,3.5Z" />
                            </svg>
                            <div class="store-text">
                                <div class="store-text-small">Get it on</div>
                                <div class="store-text-large">App Store</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-sm-12 col-md-12">
                <div class="hero-animation">
                    <div class="img-container">
                        <img src="{{ asset('website-assets/images/mockups/home/phone_in_hand_pre_ipo.svg') }}"
                            alt="Phone Mockup" />
                    </div>
                </div>
            </div>

        </div>
    </div>
</section> --}}

<section class="features-section scroll-animate">
    <div class="container">
        <div class="features-container">
            <!-- Left Features -->
            <div class="left-features">
                <div class="feature-card">
                    <div class="feature-number">01</div>
                    <div class="feature-card-inner">
                        <h3>Investment Tracking</h3>
                        <p>Monitor your portfolio performance with real-time updates</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-number">02</div>
                    <div class="feature-card-inner">
                        <h3>Market Analysis</h3>
                        <p>Get comprehensive market insights and trends analysis to identify</p>
                    </div>
                </div>
            </div>

            <!-- Phone Mockup -->
            {{-- <div class="phone-mockup-container">
                <!-- SVG for connection lines -->
                <!-- Top Left Arrow -->
                <svg class="arrow-svg arrow-left-top" width="123" height="25" viewBox="0 0 123 25" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M90.9998 2.73535V2.23535H91.1562L91.2848 2.32449L90.9998 2.73535ZM6.16666 2.73535C6.16666 4.20811 4.97276 5.40202 3.5 5.40202C2.02724 5.40202 0.833336 4.20811 0.833336 2.73535C0.833336 1.26259 2.02724 0.0686848 3.5 0.0686848C4.97276 0.0686848 6.16666 1.26259 6.16666 2.73535ZM122 24.2354L121.715 24.6462L90.7149 3.14621L90.9998 2.73535L91.2848 2.32449L122.285 23.8245L122 24.2354ZM90.9998 2.73535V3.23535H3.5V2.73535V2.23535H90.9998V2.73535Z"
                        fill="#B7D3FF" />
                </svg>

                <!-- Bottom Left Arrow -->
                <svg class="arrow-svg arrow-left-bottom" width="122" height="25" viewBox="0 0 122 25" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M90.4998 22.2354V22.7354H90.6562L90.7848 22.6462L90.4998 22.2354ZM5.66666 22.2354C5.66666 20.7626 4.47276 19.5687 3 19.5687C1.52724 19.5687 0.333336 20.7626 0.333336 22.2354C0.333336 23.7081 1.52724 24.902 3 24.902C4.47276 24.902 5.66666 23.7081 5.66666 22.2354ZM121.5 0.735352L121.215 0.324493L90.2149 21.8245L90.4998 22.2354L90.7848 22.6462L121.785 1.14621L121.5 0.735352ZM90.4998 22.2354V21.7354H3V22.2354V22.7354H90.4998V22.2354Z"
                        fill="#B7D3FF" />
                </svg>

                <!-- Top Right Arrow -->
                <svg class="arrow-svg arrow-right-top" width="123" height="25" viewBox="0 0 123 25" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M32.0002 2.73535V2.23535H31.8438L31.7152 2.32449L32.0002 2.73535ZM116.833 2.73535C116.833 4.20811 118.027 5.40202 119.5 5.40202C120.973 5.40202 122.167 4.20811 122.167 2.73535C122.167 1.26259 120.973 0.0686848 119.5 0.0686848C118.027 0.0686848 116.833 1.26259 116.833 2.73535ZM1 24.2354L1.28495 24.6462L32.2851 3.14621L32.0002 2.73535L31.7152 2.32449L0.715052 23.8245L1 24.2354ZM32.0002 2.73535V3.23535H119.5V2.73535V2.23535H32.0002V2.73535Z"
                        fill="#B7D3FF" />
                </svg>

                <!-- Bottom Right Arrow -->
                <svg class="arrow-svg arrow-right-bottom" width="123" height="25" viewBox="0 0 123 25" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M32.0002 22.2354V22.7354H31.8438L31.7152 22.6462L32.0002 22.2354ZM116.833 22.2354C116.833 20.7626 118.027 19.5687 119.5 19.5687C120.973 19.5687 122.167 20.7626 122.167 22.2354C122.167 23.7081 120.973 24.902 119.5 24.902C118.027 24.902 116.833 23.7081 116.833 22.2354ZM1 0.735352L1.28495 0.324493L32.2851 21.8245L32.0002 22.2354L31.7152 22.6462L0.715052 1.14621L1 0.735352ZM32.0002 22.2354V21.7354H119.5V22.2354V22.7354H32.0002V22.2354Z"
                        fill="#B7D3FF" />
                </svg>

                <!-- Phone mockup image placeholder -->
                <img src="{{ asset('website-assets/images/mockups/home/app_feature1.png') }}"
                    alt="Phone App Interface" />
            </div> --}}

            <!-- Video Container (Replaces mockup and arrows) -->
            <div class="feature-section-video-container">
                <video autoplay muted playsinline>
                    <source src="{{ asset('website-assets/images/mockups/feature_section_video_fast.mp4') }}"
                        type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>


            <div class="feature-section-video-container-mobile">
                <video autoplay muted playsinline>
                    <source src="{{ asset('website-assets/images/mockups/feature_section_video_mobile.mp4') }}"
                        type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>


            <!-- Right Features -->
            <div class="right-features">
                <div class="feature-card">
                    <div class="feature-number">03</div>
                    <div class="feature-card-inner">
                        <h3>Secure Transactions</h3>
                        <p>Experience bank-level security with encrypted transactions and multi-layer.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-number">04</div>
                    <div class="feature-card-inner">
                        <h3>Live Tracking</h3>
                        <p>Live tracking with real-time updates for complete transparency.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="request-for-startup scroll-animate">
    <div class="container">
        <div class="row d-flex align-items-center">
            <div class="col-lg-6 col-sm-12 col-md-12 order-1 order-lg-1 slide-from-left">
                <div class="financial-content">
                    <h1 class="heading animate-child"><span class="highlight-blue">Startup Investment </span> Access
                    </h1>
                    <p class="paragraph animate-child" style="margin-bottom: 3.5rem;">
                        Discover and request access to high-growth, early-stage startups. PrivateDeals makes it seamless for
                        advisors to facilitate investor interest in private deals — from curated pitch decks to
                        structured allocations. Let your clients invest in the next big thing, before the rest of the
                        world notices.
                    </p>
                    <a href="{{ route('page.opportunities') }}" class="common-cta-btn">Explore More <i
                            class="fa fa-arrow-right"></i></a>
                </div>
            </div>
            <div class="col-lg-6 col-sm-12 col-md-12 order-2 order-lg-2 financial-image-container slide-from-right">
                <div class="img-container">
                    <img src="{{ asset('website-assets/images/mockups/home/request_for_startup.png') }}"
                        alt="Financial & Payments Products" />
                </div>
            </div>

        </div>
    </div>
</section>

{{-- <section class="features-section ">
    <div class="container">
        <div class="features-video-wrapper">
            <video class="features-video" autoplay muted loop playsinline>
                <source src="{{ asset('website-assets/images/mockups/video section 2.mp4') }}" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        </div>
    </div>
</section> --}}

{{-- <section class="intro-section">
    <div class="container">
        <div class="intro-content">
            <h1>
                A fully integrated suite of
                <span class="highlight-blue">financial and
                    payments</span> products
            </h1>
            <p>
                At PrivateDeals, we are redefining how private equity investments are made. Our mission is simple –
                unlocking
                opportunities in private markets by providing investors exclusive access to high-potential private
                companies.
                We aim to bridge the gap between investors and exceptional private companies, empowering you to access
                unique opportunities that have the potential for exponential growth.unique opportunities that have the
                potential for exponential growth.unique opportunities that have the potential for exponential
                growth.unique opportunities that have the potential for exponential growth.unique opportunities that
                have the potential for exponential growth.
            </p>
            <a href="{{ route('page.about') }}" class="common-cta-btn">More About Us <i
                    class="fa fa-arrow-right"></i></a>
        </div>
    </div>
</section> --}}

<section class="track-and-invest scroll-animate">
    <div class="container">
        <div class="row d-flex align-items-center">
            <div class="col-lg-6 col-sm-12 col-md-12 order-2 order-lg-1 track-and-invest-container slide-from-left">
                <div class="img-container animate-child">
                    <img src="{{ asset('website-assets/images/mockups/home/track_and_invest.png') }}"
                        alt="track and invest" />
                    <div class="store-buttons animate-child">
                        <a href="https://play.google.com/store/apps/developer?id=PrivateDeals"
                            class="store-button play-store" target="_blank" onclick="addRipple(event, this)">
                            <svg class="store-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.61 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.53,12.92 20.18,13.18L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z" />
                            </svg>
                            <div class="store-text">
                                <div class="store-text-small">Download on</div>
                                <div class="store-text-large">Play Store</div>
                            </div>
                        </a>

                        <a href="https://apps.apple.com/us/developer/shuru-advisory-private-limited/id1773764563"
                            target="_blank" class="store-button app-store" onclick="addRipple(event, this)">
                            <svg class="store-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M18.71,19.5C17.88,20.74 17,21.95 15.66,21.97C14.32,22 13.89,21.18 12.37,21.18C10.84,21.18 10.37,21.95 9.1,22C7.79,22.05 6.8,20.68 5.96,19.47C4.25,17 2.94,12.45 4.7,9.39C5.57,7.87 7.13,6.91 8.82,6.88C10.1,6.86 11.32,7.75 12.11,7.75C12.89,7.75 14.37,6.68 15.92,6.84C16.57,6.87 18.39,7.1 19.56,8.82C19.47,8.88 17.39,10.1 17.41,12.63C17.44,15.65 20.06,16.66 20.09,16.67C20.06,16.74 19.67,18.11 18.71,19.5M13,3.5C13.73,2.67 14.94,2.04 15.94,2C16.07,3.17 15.6,4.35 14.9,5.19C14.21,6.04 13.07,6.7 11.95,6.61C11.8,5.46 12.36,4.26 13,3.5Z" />
                            </svg>
                            <div class="store-text">
                                <div class="store-text-small">Get it on</div>
                                <div class="store-text-large">App Store</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-1 order-3 order-lg-2">
            </div>
            <div class="col-lg-5 col-sm-12 col-md-12 order-1 order-lg-3 slide-from-right">
                <h1 class="heading animate-child"><span class="highlight-blue">Empower Partners </span> Opportunity with
                    us!</h1>
                <p class="paragraph animate-child">
                    The PrivateDeals Partner Terminal simplifies private market investing — wealth managers and advisors can
                    explore deals, track performance, and invest on behalf of clients, all in one secure platform.
                </p>
            </div>
        </div>
    </div>
</section>





{{-- <section class="statistics" id="statistics-section">
    <div class="container">
        <div class="gradient-blob blob-5"></div>
        <div class="card-container">
            <a href="{{ route('page.primary') }}" class="card gradient-first open">
                <h2 class="heading">Primary Market</h2>
                <p class="short-description paragraph">Raise Capital</p>
                <p class="long-description paragraph">
                    Investing in start-ups is about more than just returns—it's about supporting innovation, disruption,
                    and the next generation of industry leaders. At PrivateDeals, we provide exclusive access to
                    high-potential early-stage companies that are poised to shape the future. By investing in start-ups,
                    you become part of their growth story, driving both economic progress and personal wealth creation.
                </p>
                <span class="arrow"><i class="fas fa-arrow-right"></i></span>
            </a>

            <a href="{{ route('page.secondary') }}" class="card gradient-second">
                <h2>Secondary</h2>
                <p class="short-description paragraph">Exit Flexibility</p>
                <p class="long-description paragraph">
                    The secondary market for start-ups provides investors with unique opportunities to buy and sell
                    interests in promising companies. This market is essential for those looking to enhance liquidity in
                    their portfolios or to take advantage of emerging opportunities.
                </p>
                <span class="arrow"><i class="fas fa-arrow-right"></i></span>
            </a>

            <a href="{{ route('page.unlisted') }}" class="card gradient-third">
                <h2>Private Equity</h2>
                <p class="short-description paragraph">Private Equity Investments</p>
                <p class="long-description paragraph">
                    Private Equity investments offer a unique opportunity to invest in private companies before they
                    transition
                    to the public market. This stage is crucial for those looking to get involved with companies that
                    may have significant growth potential as they prepare for their IPO.
                </p>
                <span class="arrow"><i class="fas fa-arrow-right"></i></span>
            </a>

            <a href="#" class="card gradient-fourth request-access-card">
                <h2>Request Access</h2>
                <p class="short-description paragraph"></p>
                <p class="long-description paragraph">
                    Unlock exclusive investment opportunities across all products. Fill out the form to gain access to
                    curated options, including early-stage startups, mature companies, and more. Join us today!
                </p>
                <span class="arrow"><i class="fas fa-arrow-right"></i></span>
            </a>

        </div>
    </div>
</section> --}}

{{-- <section class="about-shuru">
    <div class="container">
        <div class="row d-flex align-items-center">
            <div class="col-lg-5 col-sm-12 col-md-12">
                <h6 class="mb-2">About PrivateDeals</h6>
                <h1 class="mb-2 heading">Providing a better way to invest</h1>
                <p class="paragraph">At PrivateDeals, we are redefining how private equity investments are made. Our mission
                    is simple -
                    unlocking opportunities in private markets by providing investors exclusive access to high-potential
                    private companies. We aim to bridge the gap between investors and exceptional private companies,
                    empowering you to access unique opportunities that have the potential for exponential growth. </p>
                <a href="{{ route('page.about') }}" class="btn btn-light custom-button">More About Us</a>
            </div>
            <div class="col-lg-1">
            </div>
            <div class="col-lg-6 col-sm-12 col-md-12">
                <div class="img-container">
                    <img src="{{ asset('website-assets/images/about_shuru.svg') }}" alt="About PrivateDeals">
                </div>
            </div>
        </div>
    </div>
</section>

<section class="terminal">
    <div class="container">
        <div class="row d-flex align-items-center">
            <div class="col-lg-5 col-sm-12 col-md-12">
                <h6 class="mb-2">About Terminals</h6>
                <h1 class="mb-2 heading">Unlock New Heights in Your Private Equity.
                </h1>
                <p class="paragraph">The PrivateDeals Terminal is an innovative transactive platform designed to empower
                    investors in the
                    private equity space. By combining robust analytics with seamless transaction capabilities, we
                    provide you with everything you need to navigate the complexities of private investments confidently
                    and efficiently.</p>
                <div class="store-icons">
                    <a href="https://play.google.com/store/apps/developer?id=PrivateDeals" class="" target="_blank">
                        <img src="{{ asset('website-assets/images/store-icon/android.svg') }}" alt="">
                    </a>
                    <a href="https://apps.apple.com/us/developer/shuru-advisory-private-limited/id1773764563" class=""
                        target="_blank">
                        <img src="{{ asset('website-assets/images/store-icon/ios.svg') }}" alt="">
                    </a>
                    <a href="" class="request-access-card">
                        <img src="{{ asset('website-assets/images/store-icon/windows.svg') }}" alt="">
                    </a>
                </div>
                <a href="{{ route('page.how-it-works') }}" class="btn btn-light custom-button mt-4">More About
                    Terminals</a>
            </div>
            <div class="col-lg-1">
            </div>
            <div class="col-lg-6 col-sm-12 col-md-12">
                <div class="img-container">
                    <img src="{{ asset('website-assets/images/laptop-mobile_cube_image.svg') }}" alt="About Terminal">
                </div>
            </div>
        </div>
    </div>
</section> --}}


{{-- <section class="media-slider">
    <div class="container">
        <div class="row d-flex align-items-center">
            <div class="col-lg-5 col-sm-12 col-md-12">
                <h6 class="mb-4">Media</h6>
                <h1 class="mb-4 heading">PrivateDeals in the Spotlight: News & Media Highlights</h1>
                <p class="paragraph">Stay updated with PrivateDeals's journey through our latest press releases, breaking
                    news, and insightful stories that spotlight our pioneering moves in the venture capital space and
                    dedication to empowering emerging startups.</p>
            </div>
            <div class="col-lg-1">
            </div>
            @if ($mediaItems->count() > 0)
            @php
            $mediaChunks = $mediaItems->chunk(ceil($mediaItems->count() / 2));
            @endphp

            <div class="col-lg-6 col-sm-12 col-md-12 slider-container">
                <div class="slider slider-left symbol symbol-50px me-5">
                    @foreach ($mediaChunks[0] as $mediaItem)
                    <a href="{{ $mediaItem->url }}" class="image-card" target="_blank">
                        <img class="shimmer lazy"
                            data-src="{{ FileUpDownHelper::get_website_media_banner_url($mediaItem->banner) }}" alt="">
                        <div class="detail-info">
                            <p class="title">{{ UtillsHelper::read_more_hide($mediaItem->title, 80) }}</p>
                            <p class="description">
                                {{ UtillsHelper::read_more_hide($mediaItem->description, 300) }}
                            </p>
                        </div>
                    </a>
                    @endforeach

                </div>
                <div class="slider slider-right symbol symbol-50px me-5">
                    @foreach ($mediaChunks[1] as $mediaItem)
                    <a href="{{ $mediaItem->url }}" class="image-card" target="_blank">
                        <img class="shimmer lazy"
                            data-src="{{ FileUpDownHelper::get_website_media_banner_url($mediaItem->banner) }}" alt="">
                        <div class="detail-info">
                            <p class="title">{{ UtillsHelper::read_more_hide($mediaItem->title, 80) }}</p>
                            <p class="description">
                                {{ UtillsHelper::read_more_hide($mediaItem->description, 300) }}
                            </p>
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</section> --}}

<section class="news-media-slider scroll-animate">
    <div class="container">
        <div class="row d-flex align-items-center slide-from-left">
            <div class="col-lg-5 col-md-12 col-sm-12">
                <h1 class="heading animate-child">News & <span class="highlight-blue">Media</span></h1>
                <p class="paragraph animate-child">
                    Stay updated with the latest stories, market insights, and platform announcements from the world of
                    private equity, startups, and Pre-IPOs. Explore expert opinions, company highlights, and PrivateDeals’s
                    media coverage — all in one place.
                </p>
            </div>
            <div class="col-lg-7 col-md-12 col-sm-12">
                <div class="news-slider-wrapper">
                    <div class="news-slider">
                        @foreach ($mediaItems as $mediaItem)
                        <a href="{{ $mediaItem->url }}" target="_blank" class="news-card">
                            <div class="image-wrap">
                                <img src="{{ FileUpDownHelper::get_website_media_banner_url($mediaItem->banner) }}"
                                    alt="{{ $mediaItem->title }}" />
                            </div>
                            <div class="info">
                                <div class="category">
                                    {{ UtillsHelper::read_more_hide($mediaItem->title, 40) }}
                                </div>
                                <div class="sub">
                                    {{ UtillsHelper::read_more_hide($mediaItem->description, 50) }}
                                </div>
                            </div>
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>




@endsection