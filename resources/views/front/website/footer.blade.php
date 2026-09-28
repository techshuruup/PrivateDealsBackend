<footer class="footer">
    <div class="container">
        <div class="row g-4">
            <!-- Column 1: Our Modules -->
            <div class="col-12 col-sm-12 col-md-6 col-lg-4 order-2 order-lg-1">
                <div class="social-media-section">
                    <div class="logo">
                        <img src="{{ asset('website-assets/images/logo.svg') }}" alt="PrivateDeals Logo">
                    </div>

                    <div class="social-media">
                        @foreach (App\Models\MasterWebsiteSocialmediaModel::where('is_deleted',
                        0)->orderby('display_order', 'asc')->get() as $linkKey => $linkValue)
                        <a href="{{ $linkValue->link }}" target="_blank" title="{{ $linkValue->name }}"
                            class="social {{ strtolower($linkValue->name) }}">
                            <i class="fab {{ $linkValue->icon }}"></i>
                        </a>
                        @endforeach
                    </div>

                    <div class="store-buttons">
                        <a href="https://play.google.com/store/apps/developer?id=PrivateDeals" target="_blank"
                            class="store-button">
                            <svg class="store-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.61 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.53,12.92 20.18,13.18L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z" />
                            </svg>
                            <div class="store-text">
                                <span class="store-text-small">GET IT ON</span>
                                <span class="store-text-large">Google Play</span>
                            </div>
                        </a>

                        <a href="https://apps.apple.com/us/developer/shuru-advisory-private-limited/id1773764563"
                            target="_blank" class="store-button">
                            <svg class="store-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M18.71,19.5C17.88,20.74 17,21.95 15.66,21.97C14.32,22 13.89,21.18 12.37,21.18C10.84,21.18 10.37,21.95 9.1,22C7.79,22.05 6.8,20.68 5.96,19.47C4.25,17 2.94,12.45 4.7,9.39C5.57,7.87 7.13,6.91 8.82,6.88C10.1,6.86 11.32,7.75 12.11,7.75C12.89,7.75 14.37,6.68 15.92,6.84C16.57,6.87 18.39,7.1 19.56,8.82C19.47,8.88 17.39,10.1 17.41,12.63C17.44,15.65 20.06,16.66 20.09,16.67C20.06,16.74 19.67,18.11 18.71,19.5M13,3.5C13.73,2.67 14.94,2.04 15.94,2C16.07,3.17 15.6,4.35 14.9,5.19C14.21,6.04 13.07,6.7 11.95,6.61C11.8,5.46 12.36,4.26 13,3.5Z" />
                            </svg>
                            <div class="store-text">
                                <span class="store-text-small">Download on the</span>
                                <span class="store-text-large">App Store</span>
                            </div>
                        </a>
                    </div>

                </div>
            </div>
            <div class="col-6 col-sm-6 col-md-6 col-lg-2 order-1 order-lg-2">
                <h5>Our Modules</h5>
                <ul class="footer-links">
                    {{-- <li><a href="{{ route('front.cards.primary') }}">Primary</a></li>
                    <li><a href="{{ route('front.cards.secondary') }}">Secondary</a></li> --}}
                    <li><a href="{{ route('page.opportunities') }}">Startup</a></li>
                    <li><a href="{{ route('page.unlisted') }}">Private Equity</a></li>
                </ul>
            </div>

            <!-- Column 2: About PrivateDeals -->
            <div class="col-6 col-sm-6 col-md-6 col-lg-2 order-1 order-lg-3">
                <h5>About Us</h5>
                <ul class="footer-links">
                    <li><a href="{{ route('page.about') }}">About Us</a></li>
                    <li><a href="{{ route('page.how-it-works') }}">Terminal</a></li>
                    <li><a href="{{ route('page.contact') }}">Contact Us</a></li>
                    <li><a href="{{ route('page.disclaimer') }}">Disclaimer</a></li>
                </ul>
            </div>

            <!-- Column 3: Legal Info -->
            <div class="col-6 col-sm-6 col-lg-2 order-1 order-lg-4">
                <h5>Legal Info</h5>
                <ul class="footer-links">
                    <li><a href="{{ route('page.privacy-policy') }}">Privacy Policy</a></li>
                    <li><a href="{{ route('page.terms-conditions') }}">Terms Of Use</a></li>
                    <li><a href="{{ route('page.risk-disclosure') }}">Risk Disclosure</a></li>
                    {{-- <li><a href="#warnings">Risk Warnings</a></li> --}}
                </ul>
            </div>

            <!-- Column 4: Contact Us -->
            <div class="col-6 col-sm-6 col-lg-2 order-1 order-lg-5">
                <h5>Contact Us</h5>
                <div class="contact-box">
                    <div class="contact-info">
                        <div class="icon">
                            <i class="fa-solid fa-phone"></i>
                        </div>
                        <p>{!! CommonHelper::appSettings('branding_content_contact_mobile') !!}</p>
                    </div>
                    <div class="contact-info">
                        <div class="icon">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                        <p>{{ CommonHelper::appSettings('branding_content_contact_email') }}</p>
                    </div>
                    {{-- <div class="contact-info">
                        <div class="icon">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <p>{!! nl2br(CommonHelper::appSettings('branding_content_contact_address')) !!}</p>
                    </div> --}}
                </div>
            </div>

            <!-- Column 5: Social Media & Downloads -->

        </div>
    </div>
</footer>

<!-- Copyright Section -->
<div class="copyrights">
    <div class="container">
        <p>&copy; 2025 Shuru Advisory Private Limited. All Rights Reserved</p>
    </div>
</div>