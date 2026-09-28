<x-default-layout>

    @section('title')
    {{ getPageTitle() }}
    @endsection

    @section('breadcrumbs')
    {{ Breadcrumbs::render('dashboard') }} - PrivateDeals
    @endsection


    <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
        <!--begin::Col-->
        <div class="col-md-6 col-lg-6 col-xl-6 col-xxl-3 mb-md-5 mb-xl-10">
            {{-- @include('admin.partials/widgets/cards/_widget-20') --}}
            @include('admin.partials.widgets.cards._widget-20', ['current_month_total' => $current_month_total])

            {{-- @include('admin.partials/widgets/cards/_widget-7') --}}
            @include('admin.partials.widgets.cards._widget-7', ['active_user_today' => $active_user_today,
            'active_investors_today_names' => $active_investors_today_names])
        </div>
        <!--end::Col-->
        <!--begin::Col-->
        <div class="col-md-6 col-lg-6 col-xl-6 col-xxl-3 mb-md-5 mb-xl-10">
            {{-- @include('admin.partials/widgets/cards/_widget-17') --}}
            @include('admin.partials.widgets.lists._widget-26', [
            'request_access' => $request_access,
            'active_user_today' => $active_user_today,
            'active_partner_today' => $active_partner_today,
            'recent_registered_investors' => $recent_registered_investors,
            'total_user' => $total_user,
            'total_partner' => $total_partner,
            ])
            {{-- @include('admin.partials.widgets.cards._widget-17',['primary_total' => $primary_total]) --}}



        </div>
        <!--end::Col-->
        <!--begin::Col-->
        <div class="col-xxl-6">
            {{-- @include('admin.partials/widgets/engage/_widget-10') --}}
            @include('admin.partials.widgets.charts._widget-8', ['chartData' => $chartData])

        </div>
        <!--end::Col-->
    </div>
    <!--end::Row-->


    <div class="row gx-5 gx-xl-10">
        <!--begin::Col-->
        <div class="col-xxl-6 mb-5 mb-xl-10">
            {{-- @include('admin.partials/widgets/charts/_widget-8') --}}
            {{-- @include('admin.partials.widgets.charts._widget-8', ['chartData' => $chartData]) --}}
        </div>
        <!--end::Col-->
        <!--begin::Col-->
        <div class="col-xl-6 mb-5 mb-xl-10">
            {{-- @include('admin.partials/widgets/tables/_widget-16') --}}
            {{-- @include('admin.partials.widgets.tables._widget-16', ['tableData' => $tableData]) --}}
        </div>
        <!--end::Col-->
    </div>
    <!--end::Row-->


    <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
        <!--begin::Col-->
        <div class="col-xxl-6">
            {{-- @include('admin.partials/widgets/cards/_widget-18') --}}
            {{-- @include('admin.partials/widgets/cards/_widget-18') --}}
            <!--end::Col-->
            <!--begin::Col-->
            <div class="col-xl-6">
                {{-- @include('admin.partials/widgets/charts/_widget-36') --}}
                {{-- @include('admin.partials.widgets.charts._widget-36', ['chartPerformance' => $chartPerformance])
                --}}
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->


        <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
            <!--begin::Col-->
            <div class="col-xl-4">
                {{-- @include('admin.partials/widgets/charts/_widget-35') --}}
                {{-- @include('admin.partials.widgets.charts._widget-31', ['warephaseStats' => $warephaseStats]) --}}
            </div>
            <!--end::Col-->
            <!--begin::Col-->
            <div class="col-xl-8">
                {{-- @include('admin.partials/widgets/tables/_widget-14') --}}
                {{-- @include('admin.partials.widgets.tables._widget-14', ['project_stats' => $project_stats]) --}}
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->


        {{-- <div class="row gx-5 gx-xl-10"> --}}
            <!--begin::Col-->
            {{-- <div class="col-xl-4"> --}}
                {{-- @include('admin.partials/widgets/charts/_widget-31') --}}
                {{-- @include('admin.partials.widgets.charts._widget-31', ['anotherStat' => $anotherStat]) --}}
                {{-- </div> --}}
            <!--end::Col-->
            <!--begin::Col-->
            {{-- <div class="col-xl-8"> --}}
                {{-- @include('admin.partials/widgets/charts/_widget-24') --}}
                {{-- @include('admin.partials.widgets.charts._widget-24', ['hrReports' => $hrReports]) --}}
                {{-- </div> --}}
            <!--end::Col-->
            {{--
        </div> --}}
        <!--end::Row-->

        <div class="row g-5 g-xl-10">
            <div class="col-xl-12">
                <div class="card card-flush">
                    <div class="card-header pt-5">
                        <div class="card-title d-flex flex-column">
                            <span class="fs-2hx fw-bold text-dark me-2 lh-1 ls-n2">Share Price Update Status</span>
                            <span class="text-gray-400 pt-1 fw-semibold fs-6">
                                Companies sorted by last update — most overdue first
                            </span>
                        </div>
                    </div>
                    <div class="card-body">

                        {{-- Summary badges --}}
                        @php
                        $critical = $companies_price_status->where('days_since', '>', 7)->count();
                        $warning = $companies_price_status->whereBetween('days_since', [3, 7])->count();
                        $good = $companies_price_status->where('days_since', '<', 3)->count();
                            @endphp

                            <div class="d-flex gap-4 mb-6 flex-wrap">
                                <div class="text-center bg-light-danger rounded px-4 py-2">
                                    <div class="fs-2 fw-bold text-danger">{{ $critical }}</div>
                                    <div class="text-muted fs-8">Critical (&gt;7d)</div>
                                </div>
                                <div class="text-center bg-light-warning rounded px-4 py-2">
                                    <div class="fs-2 fw-bold text-warning">{{ $warning }}</div>
                                    <div class="text-muted fs-8">Warning (3–7d)</div>
                                </div>
                                <div class="text-center bg-light-success rounded px-4 py-2">
                                    <div class="fs-2 fw-bold text-success">{{ $good }}</div>
                                    <div class="text-muted fs-8">Up to date (&lt;3d)</div>
                                </div>
                                <div class="text-center bg-light rounded px-4 py-2">
                                    <div class="fs-2 fw-bold text-dark">{{ $companies_price_status->count() }}</div>
                                    <div class="text-muted fs-8">Total</div>
                                </div>
                            </div>

                            {{-- Company grid --}}
                            <div class="row g-3">
                                @forelse ($companies_price_status as $company)
                                @php
                                $days = $company['days_since'];
                                if ($days > 7) {
                                $bgClass = 'bg-light-danger';
                                $badgeClass = 'badge-light-danger';
                                $textClass = 'text-danger';
                                $label = 'Critical';
                                } elseif ($days >= 3) {
                                $bgClass = 'bg-light-warning';
                                $badgeClass = 'badge-light-warning';
                                $textClass = 'text-warning';
                                $label = 'Warning';
                                } else {
                                $bgClass = 'bg-light-success';
                                $badgeClass = 'badge-light-success';
                                $textClass = 'text-success';
                                $label = 'Good';
                                }
                                $daysLabel = $days === 0 ? 'Updated today' : ($days === 1 ? '1 day ago' : $days . ' days
                                ago');
                                @endphp

                                <div class="col-md-3 col-sm-4 col-6">
                                    <div class="d-flex align-items-center gap-3 rounded p-3 {{ $bgClass }}">
                                        <div class="symbol symbol-35px">
                                            @if (!empty($company['logo']))
                                            <img src="{{ asset('storage/' . $company['logo']) }}"
                                                alt="{{ $company['name'] }}" class="rounded"
                                                onerror="this.style.display='none'">
                                            @else
                                            <div class="symbol-label fs-7 fw-bold">
                                                {{ strtoupper(substr($company['name'], 0, 2)) }}

                                            </div>
                                            @endif
                                        </div>

                                        <div class="flex-grow-1 overflow-hidden">
                                            <div class="fs-7 fw-bold text-dark text-truncate">{{ $company['name'] }}
                                            </div>
                                            <div class="fs-8 {{ $textClass }}">{{ $daysLabel }}</div>
                                        </div>

                                        {{-- <span class="badge {{ $badgeClass }} fs-9">{{ $label }}</span> --}}
                                        <span class="badge {{ $badgeClass }} fs-9">{{
                                            UtillsHelper::rupee().UtillsHelper::moneyFormatIndia($company['share_price'])
                                            }}</span>
                                    </div>
                                </div>
                                @empty
                                <div class="col-12 text-center text-muted py-5">No price data available.</div>
                                @endforelse
                            </div>

                    </div>
                </div>
            </div>
        </div>
</x-default-layout>