<x-default-layout>
    @section('title')
        {{ getPageTitle() }}
    @endsection
    <div class="card mb-5 mb-xl-10">
        <div class="card-body pt-9 pb-0">
            <div class="d-flex flex-wrap flex-sm-nowrap">
                <div class="me-7 mb-4">
                    <div class="symbol symbol-100px symbol-lg-160px symbol-fixed position-relative">
                        <img class="shimmer lazy"
                            data-src="{{ FileUpDownHelper::get_partner_profile_photo_url($partner) }}" />
                        <div
                            class="position-absolute translate-middle bottom-0 start-100 mb-6 bg-success rounded-circle border border-4 border-body h-20px w-20px">
                        </div>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start flex-wrap mb-2">
                        <div class="d-flex flex-column">
                            <div class="d-flex align-items-center mb-2">
                                <a class="text-gray-900 text-hover-primary fs-2 fw-bold me-1">{{ $partner->name }}</a>
                                <a href="#"><i class="ki-duotone ki-verify fs-1 text-primary"><span
                                            class="path1"></span><span class="path2"></span></i></a>
                            </div>
                            <div class="d-flex flex-wrap fw-semibold fs-6 mb-4 pe-2">
                                <a class="d-flex align-items-center text-gray-500 text-hover-primary me-5 mb-2">
                                    <i class="ki-duotone ki-profile-circle fs-4 me-1"><span class="path1"></span><span
                                            class="path2"></span><span class="path3"></span></i>{{ $partner->type }}
                                </a>
                                <a class="d-flex align-items-center text-gray-500 text-hover-primary me-5 mb-2">
                                    <i class="ki-duotone ki-sms fs-4"><span class="path1"></span><span
                                            class="path2"></span></i> {{ $partner->email }}
                                </a>
                                <a class="d-flex align-items-center text-gray-500 text-hover-primary mb-2">
                                    <i class="ki-duotone ki-phone fs-4"><span class="path1"></span><span
                                            class="path2"></span></i>
                                    +{{ $partner->mobile_country_code }}-{{ $partner->mobile_number }}
                                </a>
                            </div>
                        </div>
                        <div class="d-flex my-4">
                            @if (request()->routeIs('admin.partner.wealthmanager.*'))
                                            <a href="{{ route('admin.partner.wealthmanager.edit', ['uuid' => $partner->uuid]) }}"
                                                class="btn btn-sm btn-primary me-3">
                                                Edit
                                            </a>
                                        @endif
                            @if (request()->routeIs('admin.partner.retailers.*'))
                                <a href="{{ route('admin.partner.retailers.edit', ['uuid' => $partner->uuid]) }}"
                                    class="btn btn-sm btn-primary me-3">
                                    Edit
                                </a>
                            @endif
                            @if (request()->routeIs('admin.partner.distributor.*'))
                                <a href="{{ route('admin.partner.distributor.edit', ['uuid' => $partner->uuid]) }}"
                                    class="btn btn-sm btn-primary me-3">
                                    Edit
                                </a>
                            @endif
                            @if (request()->routeIs('admin.partner.institution.*'))
                                <a href="{{ route('admin.partner.institution.edit', ['uuid' => $partner->uuid]) }}"
                                    class="btn btn-sm btn-primary me-3">
                                    Edit
                                </a>
                            @endif
                            @if (request()->routeIs('admin.partner.relationalManager.*'))
                                <a href="{{ route('admin.partner.relationalManager.edit', ['uuid' => $partner->uuid]) }}"
                                    class="btn btn-sm btn-primary me-3">
                                    Edit
                                </a>
                            @endif
                            {{-- <a href="{{ route('admin.partner.wealthmanager.edit', ['uuid' => $partner->uuid]) }}"
                                class="btn btn-sm btn-primary me-3">Edit</a> --}}
                        </div>
                    </div>
                </div>
            </div>
            <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold">
                <li class="nav-item mt-2">
                    <a class="nav-link text-active-primary ms-0 me-10 py-5 active" href="#" data-bs-toggle="pill"
                        data-bs-target="#pills-home">
                        Basic Details </a>
                </li>
                <li class="nav-item mt-2">
                    <a class="nav-link text-active-primary ms-0 me-10 py-5 " href="#" data-bs-toggle="pill"
                        data-bs-target="#pills-home2">
                        Investor Holdings</a>
                </li>
                <li class="nav-item mt-2">
                    <a class="nav-link text-active-primary ms-0 me-10 py-5 " href="#" data-bs-toggle="pill"
                        data-bs-target="#pills-home3">
                        Investors</a>
                </li>
                @if ($partner->type === \App\Enums\PartnerTypeEnum::wealthmanager->value || $partner->type === \App\Enums\PartnerTypeEnum::distributor->value)
                    <li class="nav-item mt-2">
                        <a class="nav-link text-active-primary ms-0 me-10 py-5 " href="#" data-bs-toggle="pill"
                            data-bs-target="#pills-home4">
                            Partners</a>
                    </li>
                @endif
                @if (!request()->routeIs('admin.partner.relationalManager.*'))
                    <li class="nav-item mt-2">
                        <a class="nav-link text-active-primary ms-0 me-10 py-5 " href="#" data-bs-toggle="pill"
                            data-bs-target="#pills-home5">
                            Relation Manager</a>
                    </li>
                @endif
            </ul>
        </div>
    </div>
    <div class="tab-content" id="pills-tabContent">
        <div class="tab-pane fade show active" id="pills-home" role="tabpanel" aria-labelledby="pills-home-tab">
            @include('admin.pages.partner.child.basic-details')
        </div>
        <div class="tab-pane fade" id="pills-home2" role="tabpanel" aria-labelledby="pills-profile-tab">
            @include('admin.pages.partner.child.investor-holding')
        </div>
        <div class="tab-pane fade" id="pills-home3" role="tabpanel" aria-labelledby="pills-contact-tab">
            @include('admin.pages.partner.child.investors')
        </div>
        <div class="tab-pane fade" id="pills-home4" role="tabpanel" aria-labelledby="pills-contact-tab">
            @include('admin.pages.partner.child.partners')
        </div>
        @if (!request()->routeIs('admin.partner.relationalManager.*'))
        <div class="tab-pane fade" id="pills-home5" role="tabpanel" aria-labelledby="pills-contact-tab">
            @include('admin.pages.partner.child.relation-manager')
        </div>
        @endif
    </div>
    @push('scripts')
        <script>
            $(function() {
                $(".datatable").DataTable({
                    "language": {
                        "lengthMenu": "Show _MENU_",
                    },
                    "order": [],
                    "dom": "<'row mb-2'" +
                        "<'col-sm-6 d-flex align-items-center justify-conten-start dt-toolbar'l>" +
                        "<'col-sm-6 d-flex align-items-center justify-content-end dt-toolbar'f>" +
                        ">" +

                        "<'table-responsive'tr>" +

                        "<'row'" +
                        "<'col-sm-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-start'i>" +
                        "<'col-sm-12 col-md-7 d-flex align-items-center justify-content-center justify-content-md-end'p>" +
                        ">"
                });
            })
        </script>
    @endpush
</x-default-layout>
