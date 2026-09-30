<div class="app-sidebar-menu overflow-hidden flex-column-fluid">
    <div id="kt_app_sidebar_menu_wrapper" class="app-sidebar-wrapper hover-scroll-overlay-y my-5" data-kt-scroll="true"
        data-kt-scroll-activate="true" data-kt-scroll-height="auto"
        data-kt-scroll-dependencies="#kt_app_sidebar_logo, #kt_app_sidebar_footer"
        data-kt-scroll-wrappers="#kt_app_sidebar_menu" data-kt-scroll-offset="5px" data-kt-scroll-save-state="true">
        <div class="menu menu-column menu-rounded menu-sub-indention px-3 fw-semibold fs-6" id="#kt_app_sidebar_menu"
            data-kt-menu="true" data-kt-menu-expand="false">
            <div class="menu-item">
                <a class="menu-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                    href="{{ route('admin.dashboard') }}">
                    <span class="menu-icon">{!! getIcon('element-11', 'fs-2') !!}</span>
                    <span class="menu-title">Dashboard</span>
                </a>
            </div>
            {{-- @if (AdminHelper::hasPermission(['portfolio']))
            <div class="menu-item">
                <a class="menu-link {{ request()->routeIs('admin.portfolio.list') ? 'active' : '' }}"
                    href="{{ route('admin.portfolio.list') }}">
                    <span class="menu-icon">{!! getIcon('tag', 'fs-2') !!}</span>
                    <span class="menu-title">Portfolio</span>
                </a>
            </div>
            @endif --}}
            @if (AdminHelper::hasPermission(['portfolio']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.portfolioInsights.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('arrow-right-left', 'fs-2') !!}</span>
                    <span class="menu-title">Portfolio Insights</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.portfolioInsights.startupPortfolio.*') ? 'active' : '' }}"
                            href="{{ route('admin.portfolioInsights.startupPortfolio.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Startup Portfolio</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.portfolioInsights.preIpoPortfolio.*') ? 'active' : '' }}"
                            href="{{ route('admin.portfolioInsights.preIpoPortfolio.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Pre-IPO Portfolio</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.portfolioInsights.portfolioUploadRequest') ? 'active' : '' }}"
                            href="{{ route('admin.portfolioInsights.portfolioUploadRequest') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Portfolio Upload Request </span>
                        </a>
                    </div>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['primary transaction']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.primarytransactions.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('arrow-right-left', 'fs-2') !!}</span>
                    <span class="menu-title">Primary Transaction</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.primarytransactions.pending*') ? 'active' : '' }}"
                            href="{{ route('admin.primarytransactions.pending') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Pending</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.primarytransactions.completed') ? 'active' : '' }}"
                            href="{{ route('admin.primarytransactions.completed') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Completed</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.primarytransactions.uploadDocument.*') ? 'active' : '' }}"
                            href="{{ route('admin.primarytransactions.uploadDocument.create') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Upload Document</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['secondary transaction']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.secondarytransactions.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('arrow-right-left', 'fs-2') !!}</span>
                    <span class="menu-title">Secondary Transaction</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.secondarytransactions.pending*') ? 'active' : '' }}"
                            href="{{ route('admin.secondarytransactions.pending') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Pending</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.secondarytransactions.completed') ? 'active' : '' }}"
                            href="{{ route('admin.secondarytransactions.completed') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Completed</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['pre ipo transaction']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.preipotransaction.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('double-check', 'fs-2') !!}</span>
                    <span class="menu-title">Pre IPO Transaction</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.preipotransaction.market*') ? 'active' : '' }}"
                            href="{{ route('admin.preipotransaction.market') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Market</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.preipotransaction.pending*') ? 'active' : '' }}"
                            href="{{ route('admin.preipotransaction.pending') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Processing</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.preipotransaction.completed') ? 'active' : '' }}"
                            href="{{ route('admin.preipotransaction.completed') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Completed</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.preipotransaction.rejected') ? 'active' : '' }}"
                            href="{{ route('admin.preipotransaction.rejected') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Rejected</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif

            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.secondarySellRequest.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('double-check', 'fs-2') !!}</span>
                    <span class="menu-title">Secondary Sell Request</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.secondarySellRequest.pending*') ? 'active' : '' }}"
                            href="{{ route('admin.secondarySellRequest.pending') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Pending</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.secondarySellRequest.inProgress*') ? 'active' : '' }}"
                            href="{{ route('admin.secondarySellRequest.inProgress') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">In Progress</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.secondarySellRequest.completed') ? 'active' : '' }}"
                            href="{{ route('admin.secondarySellRequest.completed') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Completed</span>
                        </a>
                    </div>
                </div>
            </div>

            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.companyEnquiry.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('message-text-2', 'fs-2') !!}</span>
                    <span class="menu-title">Company Enquiry</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.companyEnquiry.pending') ? 'active' : '' }}"
                            href="{{ route('admin.companyEnquiry.pending') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Pending</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.companyEnquiry.completed') ? 'active' : '' }}"
                            href="{{ route('admin.companyEnquiry.completed') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Completed</span>
                        </a>
                    </div>
                </div>
            </div>


            @if (AdminHelper::hasPermission([
            'manual secondary market',
            'manual preipo transaction',
            'manual primary transaction',
            'manual secondary transaction',
            ]))
            <div class="menu-item pt-5">
                <div class="menu-content">
                    <span class="menu-heading fw-bold text-uppercase fs-7">Manual Management</span>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['manual secondary market']))
            <div class="menu-item">
                <a class="menu-link {{ request()->routeIs('admin.manual.secondary-market.*') ? 'active' : '' }}"
                    href="{{ route('admin.manual.secondary-market.create') }}">
                    <span class="menu-icon">{!! getIcon('burger-menu-4', 'fs-2') !!}</span>
                    <span class="menu-title">Secondary Market</span>
                </a>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['investor']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.manual.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('user-square', 'fs-2') !!}</span>
                    <span class="menu-title">Manual Transactions</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    @if (AdminHelper::hasPermission(['manual preipo transaction']))
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.manual.preipo-transaction.*') ? 'active' : '' }}"
                            href="{{ route('admin.manual.preipo-transaction.create') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Pre-IPO Transaction</span>
                        </a>
                    </div>
                    @endif
                    @if (AdminHelper::hasPermission(['manual secondary transaction']))
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.manual.secondary-transaction.*') ? 'active' : '' }}"
                            href="{{ route('admin.manual.secondary-transaction.create') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Secondary Transaction</span>
                        </a>
                    </div>
                    @endif
                    @if (AdminHelper::hasPermission(['manual primary transaction']))
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.manual.primary-transaction.*') ? 'active' : '' }}"
                            href="{{ route('admin.manual.primary-transaction.create') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Primary Transaction</span>
                        </a>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            @if (AdminHelper::hasPermission(['investor', 'partner', 'startup', 'company', 'seller']))
            <div class="menu-item pt-5">
                <div class="menu-content">
                    <span class="menu-heading fw-bold text-uppercase fs-7">Users</span>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['investor']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.investor.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('user-square', 'fs-2') !!}</span>
                    <span class="menu-title">Investor</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.investor.create*') ? 'active' : '' }}"
                            href="{{ route('admin.investor.create') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Create</span>
                        </a>
                    </div>
                    {{-- <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.investor.active') ? 'active' : '' }}"
                            href="{{ route('admin.investor.active') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Active</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.investor.inactive') ? 'active' : '' }}"
                            href="{{ route('admin.investor.inactive') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">In Active</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.investor.pendingkyc') ? 'active' : '' }}"
                            href="{{ route('admin.investor.pendingkyc') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Pending KYC</span>
                        </a>
                    </div> --}}
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.investor.filter') ? 'active' : '' }}"
                            href="{{ route('admin.investor.filter') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Filter</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.investor.demo') ? 'active' : '' }}"
                            href="{{ route('admin.investor.demo') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Demo</span>
                        </a>
                    </div>
                    {{-- <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.investor.block') ? 'active' : '' }}"
                            href="{{ route('admin.investor.block') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Block</span>
                        </a>
                    </div> --}}

                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.investor.documents') ? 'active' : '' }}"
                            href="{{ route('admin.investor.documents') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Documents</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.investor.processing-kyc') ? 'active' : '' }}"
                            href="{{ route('admin.investor.processing-kyc') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Processing KYC</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.investor.aadharPanVerification') ? 'active' : '' }}"
                            href="{{ route('admin.investor.aadharPanVerification') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Aadhar Pan Verification</span>
                        </a>
                    </div>
                    {{-- <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.investor.rejected') ? 'active' : '' }}"
                            href="{{ route('admin.investor.rejected') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Rejected</span>
                        </a>
                    </div> --}}
                </div>
            </div>
            @endif


            @if (AdminHelper::hasPermission(['partner']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.partner.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('badge', 'fs-2') !!}</span>
                    <span class="menu-title">Partner</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.partner.wealthmanager*') ? 'active' : '' }}"
                            href="{{ route('admin.partner.wealthmanager.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Wealth Manager</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.partner.distributor*') ? 'active' : '' }}"
                            href="{{ route('admin.partner.distributor.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Distributor</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.partner.institution*') ? 'active' : '' }}"
                            href="{{ route('admin.partner.institution.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Institution</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.partner.retailers*') ? 'active' : '' }}"
                            href="{{ route('admin.partner.retailers.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Retailers</span>
                        </a>
                    </div>

                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.partner.relationalManager*') ? 'active' : '' }}"
                            href="{{ route('admin.partner.relationalManager.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Relational Manager</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.partner.demo') ? 'active' : '' }}"
                            href="{{ route('admin.partner.demo') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Demo</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif
            @if (Auth::guard('admin')->user()->role == 'admin')
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs(['admin.user-management.*', 'admin.manager.*']) ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('security-user', 'fs-2') !!}</span>
                    <span class="menu-title">Admin</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    {{-- <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('user-management.users.*') ? 'active' : '' }}"
                            href="{{ route('admin.user-management.users.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Users</span>
                        </a>
                    </div> --}}
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.manager.*') ? 'active' : '' }}"
                            href="{{ route('admin.manager.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">List</span>
                        </a>
                    </div>
                    {{-- <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('user-management.roles.*') ? 'active' : '' }}"
                            href="{{ route('admin.user-management.roles.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Roles</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('user-management.permissions.*') ? 'active' : '' }}"
                            href="{{ route('admin.user-management.permissions.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Permissions</span>
                        </a>
                    </div> --}}
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['startup']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.startup.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('emoji-happy', 'fs-2') !!}</span>
                    <span class="menu-title">Start Up</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    {{-- <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.manage.pending') ? 'active' : '' }}"
                            href="{{ route('admin.startup.manage.pending') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Pending</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.manage.coming_soon') ? 'active' : '' }}"
                            href="{{ route('admin.startup.manage.coming_soon') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Coming Soon</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.manage.raising_now') ? 'active' : '' }}"
                            href="{{ route('admin.startup.manage.raising_now') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Raising Now</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.manage.completed') ? 'active' : '' }}"
                            href="{{ route('admin.startup.manage.completed') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Completed</span>
                        </a>
                    </div> --}}

                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.create') ? 'active' : '' }}"
                            href="{{ route('admin.startup.create') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Create</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.list') ? 'active' : '' }}"
                            href="{{ route('admin.startup.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">List</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['company']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.company.*') || request()->routeIs('admin.company-deals.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('medal-star', 'fs-2') !!}</span>
                    <span class="menu-title">Company</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.company.updateSharePrice') ? 'active' : '' }}"
                            href="{{ route('admin.company.updateSharePrice') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Update Share Price</span>
                        </a>
                    </div>
                    {{-- <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.company.import') ? 'active' : '' }}"
                            href="{{ route('admin.company.import') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Import</span>
                        </a>
                    </div> --}}
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.company.create') ? 'active' : '' }}"
                            href="{{ route('admin.company.create') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Create</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.company.list') ? 'active' : '' }}"
                            href="{{ route('admin.company.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">List</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.company.export.view') ? 'active' : '' }}"
                            href="{{ route('admin.company.export.view') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Export</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.company-deals.*') ? 'active' : '' }}"
                            href="{{ route('admin.company-deals.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Company Deals</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['company']))
            <div class="menu-item">
                <a class="menu-link {{ request()->routeIs('admin.bse-holiday.*') ? 'active' : '' }}"
                    href="{{ route('admin.bse-holiday.index') }}">
                    <span class="menu-icon">{!! getIcon('calendar-8', 'fs-2') !!}</span>
                    <span class="menu-title">BSE Holidays</span>
                </a>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['company']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.coupon.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('file-sheet', 'fs-2') !!}</span>
                    <span class="menu-title">Coupon Management</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.coupon.list') ? 'active' : '' }}"
                            href="{{ route('admin.coupon.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">List</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.coupon.create') ? 'active' : '' }}"
                            href="{{ route('admin.coupon.create') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Create</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.coupon.settings') ? 'active' : '' }}"
                            href="{{ route('admin.coupon.settings') }}">
                            <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                            <span class="menu-title">Settings</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.coupon.assign') ? 'active' : '' }}"
                            href="{{ route('admin.coupon.assign') }}">
                            <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                            <span class="menu-title">Assign to Investors</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['seller']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.preiposeller.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('mouse-square', 'fs-2') !!}</span>
                    <span class="menu-title">Seller</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.preiposeller.create') ? 'active' : '' }}"
                            href="{{ route('admin.preiposeller.create') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Create</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.preiposeller.list') ? 'active' : '' }}"
                            href="{{ route('admin.preiposeller.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">List</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif

            @if (AdminHelper::hasPermission(['ai_autowork']))
            <div class="menu-item pt-5">
                <div class="menu-content">
                    <span class="menu-heading fw-bold text-uppercase fs-7">AI AutoWork</span>
                </div>
            </div>
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.ai-autowork.company-ingest.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('medal-star', 'fs-2') !!}</span>
                    <span class="menu-title">Company</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.ai-autowork.company-ingest.inbox') || request()->routeIs('admin.ai-autowork.company-ingest.review') || request()->routeIs('admin.ai-autowork.company-ingest.update') ? 'active' : '' }}"
                            href="{{ route('admin.ai-autowork.company-ingest.inbox') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Inbox</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.ai-autowork.company-ingest.guide') ? 'active' : '' }}"
                            href="{{ route('admin.ai-autowork.company-ingest.guide') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Guide</span>
                        </a>
                    </div>
                </div>
            </div>
            <div class="menu-item">
                <a class="menu-link {{ request()->routeIs('admin.ai-autowork.share-prices.*') ? 'active' : '' }}"
                    href="{{ route('admin.ai-autowork.share-prices.stub') }}">
                    <span class="menu-icon">{!! getIcon('chart-simple', 'fs-2') !!}</span>
                    <span class="menu-title">Share Prices (Soon)</span>
                </a>
            </div>
            @endif

            {{-- @if (AdminHelper::hasPermission(['investor notifications']))
            <div class="menu-item pt-5">
                <div class="menu-content">
                    <span class="menu-heading fw-bold text-uppercase fs-7">Notifications</span>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['investor notifications']))
            <div class="menu-item">
                <a class="menu-link {{ request()->routeIs('admin.notification.investor.*') ? 'active' : '' }}"
                    href="{{ route('admin.notification.investor.create') }}">
                    <span class="menu-icon">{!! getIcon('setting-4', 'fs-2') !!}</span>
                    <span class="menu-title">Investor</span>
                </a>
            </div>
            @endif --}}

            {{-- <div class="menu-item pt-5">
                <div class="menu-content">
                    <span class="menu-heading fw-bold text-uppercase fs-7">Website</span>
                </div>
            </div>
            <div class="menu-item">
                <a class="menu-link {{ request()->routeIs('admin.website.media.*') ? 'active' : '' }}"
                    href="{{ route('admin.website.media.list') }}">
                    <span class="menu-icon">{!! getIcon('setting-4', 'fs-2') !!}</span>
                    <span class="menu-title">Media</span>
                </a>
            </div> --}}



            {{-- <div class="menu-item">
                <a class="menu-link {{ request()->routeIs('admin.reports.requestbetaaccess.list.*') ? 'active' : '' }}"
                    href="{{ route('admin.reports.requestbetaaccess.list') }}">
                    <span class="menu-icon">{!! getIcon('setting-4', 'fs-2') !!}</span>
                    <span class="menu-title">Request Beta Access</span>
                </a>
            </div> --}}


            @if (AdminHelper::hasPermission([
            'sm updates news',
            'sm mis',
            'sm mgt14',
            'sm offer request',
            'sm live pitch',
            'sm pas3',
            ]))
            <div class="menu-item pt-5">
                <div class="menu-content">
                    <span class="menu-heading fw-bold text-uppercase fs-7">Startup Management</span>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['sm updates news']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.startup.update.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('update-file', 'fs-2') !!}</span>
                    <span class="menu-title">Updates/News</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.update.pending') ? 'active' : '' }}"
                            href="{{ route('admin.startup.update.pending') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Pending</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.update.approved') ? 'active' : '' }}"
                            href="{{ route('admin.startup.update.approved') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Approved</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.update.rejected') ? 'active' : '' }}"
                            href="{{ route('admin.startup.update.rejected') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Rejected</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['sm mis']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.startup.mis.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('file-sheet', 'fs-2') !!}</span>
                    <span class="menu-title">MIS</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.mis.pending') ? 'active' : '' }}"
                            href="{{ route('admin.startup.mis.pending') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Pending</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.mis.approved') ? 'active' : '' }}"
                            href="{{ route('admin.startup.mis.approved') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Approved</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.mis.rejected') ? 'active' : '' }}"
                            href="{{ route('admin.startup.mis.rejected') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Rejected</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['sm mgt14']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.startup.mgt14.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('update-file', 'fs-2') !!}</span>
                    <span class="menu-title">MGT14</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.mgt14.pending') ? 'active' : '' }}"
                            href="{{ route('admin.startup.mgt14.pending') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Pending</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.mgt14.approved') ? 'active' : '' }}"
                            href="{{ route('admin.startup.mgt14.approved') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Approved</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.mgt14.rejected') ? 'active' : '' }}"
                            href="{{ route('admin.startup.mgt14.rejected') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Rejected</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['sm offer request']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.startup.offerrequest.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('share', 'fs-2') !!}</span>
                    <span class="menu-title">Offer Request</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.offerrequest.pending') ? 'active' : '' }}"
                            href="{{ route('admin.startup.offerrequest.pending') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Pending</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.offerrequest.approved') ? 'active' : '' }}"
                            href="{{ route('admin.startup.offerrequest.approved') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Approved</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.offerrequest.rejected') ? 'active' : '' }}"
                            href="{{ route('admin.startup.offerrequest.rejected') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Rejected</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['sm live pitch']))
            <div class="menu-item">
                <a class="menu-link {{ request()->routeIs('admin.startup.livepitch.*') ? 'active' : '' }}"
                    href="{{ route('admin.startup.livepitch.index') }}">
                    <span class="menu-icon">{!! getIcon('burger-menu-4', 'fs-2') !!}</span>
                    <span class="menu-title">Manage Live Pitch</span>
                </a>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['sm pas3']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.startup.pas3.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('update-file', 'fs-2') !!}</span>
                    <span class="menu-title">PAS3</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.pas3.pending') ? 'active' : '' }}"
                            href="{{ route('admin.startup.pas3.pending') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Pending</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.pas3.approved') ? 'active' : '' }}"
                            href="{{ route('admin.startup.pas3.approved') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Approved</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.startup.pas3.rejected') ? 'active' : '' }}"
                            href="{{ route('admin.startup.pas3.rejected') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Rejected</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif


            @if (AdminHelper::hasPermission(['manual kyc requests', 'primary payment receipt', 'aif onboard']))
            <div class="menu-item pt-5">
                <div class="menu-content">
                    <span class="menu-heading fw-bold text-uppercase fs-7">Investor Management</span>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['manual kyc requests']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.manualkyc.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('update-file', 'fs-2') !!}</span>
                    <span class="menu-title">Manual KYC Requests</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.manualkyc.pending') ? 'active' : '' }}"
                            href="{{ route('admin.manualkyc.pending') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Pending</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.manualkyc.approved') ? 'active' : '' }}"
                            href="{{ route('admin.manualkyc.approved') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Approved</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.manualkyc.rejected') ? 'active' : '' }}"
                            href="{{ route('admin.manualkyc.rejected') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Rejected</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['aif onboard']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.aifonboard.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('abstract-49', 'fs-2') !!}</span>
                    <span class="menu-title">AIF Onboard Request</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.aifonboard.pending') ? 'active' : '' }}"
                            href="{{ route('admin.aifonboard.pending') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Pending</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.aifonboard.approved') ? 'active' : '' }}"
                            href="{{ route('admin.aifonboard.approved') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Approved</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.aifonboard.rejected') ? 'active' : '' }}"
                            href="{{ route('admin.aifonboard.rejected') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Rejected</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['primary payment receipt']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.paymentReceipt.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('update-file', 'fs-2') !!}</span>
                    <span class="menu-title">Primary Payment Receipt</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.paymentReceipt.pending') ? 'active' : '' }}"
                            href="{{ route('admin.paymentReceipt.pending') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Pending</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.paymentReceipt.approved') ? 'active' : '' }}"
                            href="{{ route('admin.paymentReceipt.approved') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Approved</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.paymentReceipt.rejected') ? 'active' : '' }}"
                            href="{{ route('admin.paymentReceipt.rejected') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Rejected</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif

            @if (AdminHelper::hasPermission(['broadcast']))
            <div class="menu-item pt-5">
                <div class="menu-content">
                    <span class="menu-heading fw-bold text-uppercase fs-7">Others</span>
                </div>
            </div>
            @endif

            @if (AdminHelper::hasPermission(['broadcast']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.broadcast.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('abstract-49', 'fs-2') !!}</span>
                    <span class="menu-title">Broadcast</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.broadcast.whatsapp.*') ? 'active' : '' }}"
                            href="{{ route('admin.broadcast.whatsapp.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Whatsapp</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.broadcast.pushNotification.*') ? 'active' : '' }}"
                            href="{{ route('admin.broadcast.pushNotification.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Push Notification</span>
                        </a>
                    </div>
                    {{-- <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.broadcast.notificationlist.*') ? 'active' : '' }}"
                            href="{{ route('admin.broadcast.notificationlist') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Notification List</span>
                        </a>
                    </div> --}}

                    {{-- <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.broadcast.notification.list') ? 'active' : '' }}"
                            href="{{ route('admin.broadcast.notification.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Push Notification</span>
                        </a>
                    </div> --}}
                </div>
            </div>
            @endif


            <div class="menu-item">
                <a class="menu-link {{ request()->routeIs('admin.news-assignment.*') ? 'active' : '' }}"
                    href="{{ route('admin.news-assignment.index') }}">
                    <span class="menu-icon">{!! getIcon('calendar-8', 'fs-2') !!}</span>
                    <span class="menu-title">News</span>
                </a>
            </div>

            {{-- <div class="menu-item">
                <a class="menu-link {{ request()->routeIs('admin.broadcast.whatsapp.*') ? 'active' : '' }}"
                    href="{{ route('admin.broadcast.whatsapp.list') }}">
                    <span class="menu-icon">{!! getIcon('copy-success', 'fs-2') !!}</span>
                    <span class="menu-title">Broadcast</span>
                </a>
            </div> --}}


            {{-- <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.secpaymentReceipt.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('update-file', 'fs-2') !!}</span>
                    <span class="menu-title">Secondary Payment Receipt</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.secpaymentReceipt.pending') ? 'active' : '' }}"
                            href="{{ route('admin.secpaymentReceipt.pending') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Pending</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.secpaymentReceipt.approved') ? 'active' : '' }}"
                            href="{{ route('admin.secpaymentReceipt.approved') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Approved</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.secpaymentReceipt.rejected') ? 'active' : '' }}"
                            href="{{ route('admin.secpaymentReceipt.rejected') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Rejected</span>
                        </a>
                    </div>
                </div>
            </div> --}}


            @if (AdminHelper::hasPermission(['cms reports', 'application logs', 'resource billing management']))
            <div class="menu-item pt-5">
                <div class="menu-content">
                    <span class="menu-heading fw-bold text-uppercase fs-7">Reports</span>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['cms reports']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.reports.cms*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('update-file', 'fs-2') !!}</span>
                    <span class="menu-title">CMS</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.reports.cms.feedback.list') ? 'active' : '' }}"
                            href="{{ route('admin.reports.cms.feedback.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Feedback</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.reports.cms.contact.list') ? 'active' : '' }}"
                            href="{{ route('admin.reports.cms.contact.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Contact Emails</span>
                        </a>
                    </div>
                    <div data-kt-menu-trigger="click"
                        class="menu-item menu-accordion {{ request()->routeIs('admin.reports.cms.requestaccess.*') ? 'here show' : '' }}">
                        <span class="menu-link">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Request Access</span>
                            <span class="menu-arrow"></span>
                        </span>
                        <div class="menu-sub menu-sub-accordion">
                            <div class="menu-item">
                                <a class="menu-link {{ request()->routeIs('admin.reports.cms.requestaccess.pending') ? 'active' : '' }}"
                                    href="{{ route('admin.reports.cms.requestaccess.pending') }}"><span
                                        class="menu-bullet"><span class="bullet bullet-dot"></span></span><span
                                        class="menu-title">Pending</span></a>
                            </div>
                            <div class="menu-item">
                                <a class="menu-link {{ request()->routeIs('admin.reports.cms.requestaccess.completed') ? 'active' : '' }}"
                                    href="{{ route('admin.reports.cms.requestaccess.completed') }}"><span
                                        class="menu-bullet"><span class="bullet bullet-dot"></span></span><span
                                        class="menu-title">Completed</span></a>
                            </div>
                        </div>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.reports.cms.leads') ? 'active' : '' }}"
                            href="{{ route('admin.reports.cms.leads') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Leads</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['application logs']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.reports.applogs.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('lots-shopping', 'fs-2') !!}</span>
                    <span class="menu-title">Application Logs</span>
                    <span class="menu-arrow"></span>
                </span>
                <div
                    class="menu-sub menu-sub-accordion  {{ request()->routeIs('admin.reports.applogs.*') ? 'here show' : '' }}">
                    <div data-kt-menu-trigger="click"
                        class="menu-item menu-accordion {{ request()->routeIs('admin.reports.applogs.investor.*') ? 'here show' : '' }}">
                        <span class="menu-link">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Investor</span>
                            <span class="menu-arrow"></span>
                        </span>
                        <div
                            class="menu-sub menu-sub-accordion {{ request()->routeIs('admin.reports.applogs.investor.*') ? 'here show' : '' }}">
                            <div class="menu-item">
                                <a class="menu-link {{ request()->routeIs('admin.reports.applogs.investor.android') ? 'active' : '' }}"
                                    href="{{ route('admin.reports.applogs.investor.android') }}">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Android</span>
                                </a>
                            </div>
                            <div class="menu-item">
                                <a class="menu-link {{ request()->routeIs('admin.reports.applogs.investor.ios') ? 'active' : '' }}"
                                    href="{{ route('admin.reports.applogs.investor.ios') }}">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Apple iOS</span>
                                </a>
                            </div>
                            <div class="menu-item">
                                <a class="menu-link {{ request()->routeIs('admin.reports.applogs.investor.windows') ? 'active' : '' }}"
                                    href="{{ route('admin.reports.applogs.investor.windows') }}">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Windows</span>
                                </a>
                            </div>
                            <div class="menu-item">
                                <a class="menu-link {{ request()->routeIs('admin.reports.applogs.investor.active-today.*') ? 'active' : '' }}"
                                    href="{{ route('admin.reports.applogs.investor.active-today.list', ['date' => now()->format('d-m-Y')]) }}">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Active Today</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div data-kt-menu-trigger="click"
                        class="menu-item menu-accordion {{ request()->routeIs('admin.reports.applogs.distributer.*') ? 'here show' : '' }}">
                        <span class="menu-link">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Distributer</span>
                            <span class="menu-arrow"></span>
                        </span>
                        <div
                            class="menu-sub menu-sub-accordion {{ request()->routeIs('admin.reports.applogs.distributer.*') ? 'here show' : '' }}">
                            <div class="menu-item">
                                <a class="menu-link {{ request()->routeIs('admin.reports.applogs.distributer.android') ? 'active' : '' }}"
                                    href="{{ route('admin.reports.applogs.distributer.android') }}">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Android</span>
                                </a>
                            </div>
                            <div class="menu-item">
                                <a class="menu-link {{ request()->routeIs('admin.reports.applogs.distributer.ios') ? 'active' : '' }}"
                                    href="{{ route('admin.reports.applogs.distributer.ios') }}">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Apple iOS</span>
                                </a>
                            </div>
                            <div class="menu-item">
                                <a class="menu-link {{ request()->routeIs('admin.reports.applogs.distributer.windows') ? 'active' : '' }}"
                                    href="{{ route('admin.reports.applogs.distributer.windows') }}">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Windows</span>
                                </a>
                            </div>
                            <div class="menu-item">
                                <a class="menu-link {{ request()->routeIs('admin.reports.applogs.distributer.active-today.*') ? 'active' : '' }}"
                                    href="{{ route('admin.reports.applogs.distributer.active-today.list', ['date' => now()->format('d-m-Y')]) }}">
                                    <span class="menu-bullet">
                                        <span class="bullet bullet-dot"></span>
                                    </span>
                                    <span class="menu-title">Active Today</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['resource billing management']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.reports.resourceBilling.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('book', 'fs-2') !!}</span>
                    <span class="menu-title">Financials</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.reports.resourceBilling.*') ? 'active' : '' }}"
                            href="{{ route('admin.reports.resourceBilling.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Billing Information</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif

            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.reports.notifications.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('book', 'fs-2') !!}</span>
                    <span class="menu-title">Notifications</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.reports.notifications.*') ? 'active' : '' }}"
                            href="{{ route('admin.reports.notifications.whatsappList') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Whatsapp</span>
                        </a>
                    </div>
                </div>
            </div>

            @if (AdminHelper::hasPermission(['masters', 'cms management', 'system settings', 'app version control']))
            <div class="menu-item pt-5">
                <div class="menu-content">
                    <span class="menu-heading fw-bold text-uppercase fs-7">System Setup</span>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['masters']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.master.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('share', 'fs-2') !!}</span>
                    <span class="menu-title">Masters</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.master.supported-countries.*') ? 'active' : '' }}"
                            href="{{ route('admin.master.supported-countries.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Supported Countries</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.master.country.*') ? 'active' : '' }}"
                            href="{{ route('admin.master.country.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Country</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.master.state.*') ? 'active' : '' }}"
                            href="{{ route('admin.master.state.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">State</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.master.city.*') ? 'active' : '' }}"
                            href="{{ route('admin.master.city.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">City</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.master.bank.*') ? 'active' : '' }}"
                            href="{{ route('admin.master.bank.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Bank</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.master.family-relation.*') ? 'active' : '' }}"
                            href="{{ route('admin.master.family-relation.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Family Relation</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.master.industry.*') ? 'active' : '' }}"
                            href="{{ route('admin.master.industry.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Industry Segment</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.master.sector.*') ? 'active' : '' }}"
                            href="{{ route('admin.master.sector.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Sector</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.master.project.*') ? 'active' : '' }}"
                            href="{{ route('admin.master.project.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Projects</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.master.social-media.*') ? 'active' : '' }}"
                            href="{{ route('admin.master.social-media.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Social Media Links</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.master.headertoken.*') ? 'active' : '' }}"
                            href="{{ route('admin.master.headertoken.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Api Header Tokens</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.master.findcml.*') ? 'active' : '' }}"
                            href="{{ route('admin.master.findcml.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Find CML</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['cms management']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.cms.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('book', 'fs-2') !!}</span>
                    <span class="menu-title">CMS</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.cms.blog.*') ? 'active' : '' }}"
                            href="{{ route('admin.cms.blog.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Blog</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.cms.avtar.*') ? 'active' : '' }}"
                            href="{{ route('admin.cms.avtar.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Manage Avtar</span>
                        </a>
                    </div>

                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.cms.website-social-media.*') ? 'active' : '' }}"
                            href="{{ route('admin.cms.website-social-media.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Website Social Media</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.cms.pages.*') ? 'active' : '' }}"
                            href="{{ route('admin.cms.pages.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Pages</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.cms.manage-info-icon.*') ? 'active' : '' }}"
                            href="{{ route('admin.cms.manage-info-icon.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Manage Info Icons</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.cms.media.*') ? 'active' : '' }}"
                            href="{{ route('admin.cms.media.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">Media</span>
                        </a>
                    </div>
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.cms.faqs.*') ? 'active' : '' }}"
                            href="{{ route('admin.cms.faqs.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">FAQs</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif
            @if (AdminHelper::hasPermission(['system settings', 'app version control']))
            <div data-kt-menu-trigger="click"
                class="menu-item menu-accordion {{ request()->routeIs('admin.systemConfiguration.*') ? 'here show' : '' }}">
                <span class="menu-link">
                    <span class="menu-icon">{!! getIcon('setting-2', 'fs-2') !!}</span>
                    <span class="menu-title">System Configuration</span>
                    <span class="menu-arrow"></span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    @if (AdminHelper::hasPermission(['system settings']))
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.systemConfiguration.systemSettings.*') ? 'active' : '' }}"
                            href="{{ route('admin.systemConfiguration.systemSettings.get') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">System Settings</span>
                        </a>
                    </div>

                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.systemConfiguration.apiclient.*') ? 'active' : '' }}"
                            href="{{ route('admin.systemConfiguration.apiclient.index') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">API Clients</span>
                        </a>
                    </div>
                    @endif
                    @if (AdminHelper::hasPermission(['app version control']))
                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.systemConfiguration.appVersionControl.*') ? 'active' : '' }}"
                            href="{{ route('admin.systemConfiguration.appVersionControl.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">App Version Control</span>
                        </a>
                    </div>

                    <div class="menu-item">
                        <a class="menu-link {{ request()->routeIs('admin.systemConfiguration.appbuild.*') ? 'active' : '' }}"
                            href="{{ route('admin.systemConfiguration.appbuild.list') }}">
                            <span class="menu-bullet">
                                <span class="bullet bullet-dot"></span>
                            </span>
                            <span class="menu-title">App Build</span>
                        </a>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</div>