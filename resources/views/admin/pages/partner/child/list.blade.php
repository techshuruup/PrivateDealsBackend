<div class="d-flex flex-column gap-5 gap-md-7">
    <div class="table-responsive">
        <table id="kt_datatable_dom_positioning" class="table table-row-bordered gy-5 gs-7">
            <thead>
                <tr class="fw-semibold fs-6 text-gray-800">
                    <th class="pe-7">Profile Photo</th>
                    <th class="pe-7">Name</th>
                    <th class="pe-7">Mobile Number</th>
                    <th class="pe-7">Email</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($list as $item)
                    <tr>
                        <td>
                            <div class="symbol symbol-50px me-5">
                                <img class="shimmer lazy"
                                    data-src="{{ FileUpDownHelper::get_partner_profile_photo_url($item) }}" />
                            </div>
                        </td>
                        <td>{{ ucfirst($item->name) }}</td>
                        <td>{{ $item->mobile_number }}</td>
                        <td>{{ $item->email }}</td>
                        <td class="text-center">
                            <div class="card-toolbar">
                                <button type="button"
                                    class="btn btn-sm btn-icon btn-color-primary btn-active-light-primary show menu-dropdown"
                                    data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                    <i class="ki-duotone ki-category fs-6"><span class="path1"></span><span
                                            class="path2"></span><span class="path3"></span><span
                                            class="path4"></span></i>
                                </button>
                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg-light-primary fw-semibold w-200px"
                                    data-kt-menu="true" data-popper-placement="bottom-end">
                                    <div class="menu-item px-3">
                                        <div class="menu-content fs-6 text-gray-900 fw-bold px-3 py-4">
                                            Action</div>
                                    </div>
                                    <div class="separator mb-3 opacity-75"></div>
                                    <div class="menu-item px-3">
                                        @if (request()->routeIs('admin.partner.wealthmanager.*'))
                                            <a href="{{ route('admin.partner.wealthmanager.view', ['uuid' => $item->uuid]) }}"
                                                class="menu-link px-3">
                                                View
                                            </a>
                                        @endif
                                        @if (request()->routeIs('admin.partner.retailers.*'))
                                            <a href="{{ route('admin.partner.retailers.view', ['uuid' => $item->uuid]) }}"
                                                class="menu-link px-3">
                                                View
                                            </a>
                                        @endif
                                        @if (request()->routeIs('admin.partner.distributor.*'))
                                            <a href="{{ route('admin.partner.distributor.view', ['uuid' => $item->uuid]) }}"
                                                class="menu-link px-3">
                                                View
                                            </a>
                                        @endif
                                        @if (request()->routeIs('admin.partner.institution.*'))
                                            <a href="{{ route('admin.partner.institution.view', ['uuid' => $item->uuid]) }}"
                                                class="menu-link px-3">
                                                View
                                            </a>
                                        @endif
                                        @if (request()->routeIs('admin.partner.relationalManager.*'))
                                            <a href="{{ route('admin.partner.relationalManager.view', ['uuid' => $item->uuid]) }}"
                                                class="menu-link px-3">
                                                View
                                            </a>
                                        @endif

                                    </div>

                                    <div class="menu-item px-3">
                                        @if (request()->routeIs('admin.partner.wealthmanager.*'))
                                            <a href="{{ route('admin.partner.wealthmanager.edit', ['uuid' => $item->uuid]) }}"
                                                class="menu-link px-3">
                                                Edit
                                            </a>
                                        @endif
                                        @if (request()->routeIs('admin.partner.retailers.*'))
                                            <a href="{{ route('admin.partner.retailers.edit', ['uuid' => $item->uuid]) }}"
                                                class="menu-link px-3">
                                                Edit
                                            </a>
                                        @endif
                                        @if (request()->routeIs('admin.partner.distributor.*'))
                                            <a href="{{ route('admin.partner.distributor.edit', ['uuid' => $item->uuid]) }}"
                                                class="menu-link px-3">
                                                Edit
                                            </a>
                                        @endif
                                        @if (request()->routeIs('admin.partner.institution.*'))
                                            <a href="{{ route('admin.partner.institution.edit', ['uuid' => $item->uuid]) }}"
                                                class="menu-link px-3">
                                                Edit
                                            </a>
                                        @endif
                                        @if (request()->routeIs('admin.partner.relationalManager.*'))
                                            <a href="{{ route('admin.partner.relationalManager.edit', ['uuid' => $item->uuid]) }}"
                                                class="menu-link px-3">
                                                Edit
                                            </a>
                                        @endif
                                    </div>
                                    @if (Auth::guard('admin')->user()->role == 'admin')
                                        <div class="menu-item px-3">
                                            <a href="#" class="menu-link px-3 edit-manager"
                                                data-partner="{{ $item->id }}"
                                                data-manager="{{ $item->created_by }}">
                                                Manager
                                            </a>
                                        </div>
                                    @endif
                                    <div class="menu-item px-3">
                                        <a href="{{ route('admin.partner.mark-demo', ['uuid' => $item->uuid]) }}"
                                            class="menu-link px-3" onclick="return confirm('Are you sure?')">
                                            {{ $item->is_demo ? 'Mark as Live' : 'Mark as Demo' }}
                                        </a>
                                    </div>
                                    <div class="separator mt-3 opacity-75"></div>
                                    <div class="menu-item px-3">
                                        <div class="menu-content px-3 py-3">
                                            <form
                                                action="{{ route('admin.partner.wealthmanager.destroy', ['id' => $item->id]) }}"
                                                method="POST" style="display:inline;"
                                                id="delete-form-{{ $item->id }}">
                                                @csrf
                                                @method('DELETE')
                                                <a href="#" class="btn btn-danger btn-sm px-4"
                                                    onclick="event.preventDefault(); 
                                                                        if (confirm('Are you sure you want to delete this item?')) {
                                                                            document.getElementById('delete-form-{{ $item->id }}').submit();
                                                                        }">
                                                    <i class="fas fa-trash fs-6"></i> Delete
                                                </a>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@include('admin.pages.partner.child.manager')

@push('scripts')
    <script>
        $(function() {
            $("#kt_datatable_dom_positioning").DataTable({
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
