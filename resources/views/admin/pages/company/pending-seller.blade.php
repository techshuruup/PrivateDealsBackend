<x-default-layout>
    @section('title')
        {{ getPageTitle() }}
    @endsection

    @section('breadcrumbs')
        {{ Breadcrumbs::render('company.pendingSeller') }}
    @endsection

    <div class="d-flex flex-column flex-lg-row fv-plugins-bootstrap5 fv-plugins-framework">
        <div class="d-flex flex-column flex-lg-row-fluid gap-6 gap-lg-10">
            <div class="card card-flush py-4">
                <div class="card-header">
                    <div class="card-title">
                        <h2>Pending seller companies</h2>
                    </div>
                    <div class="card-toolbar">
                        <a href="{{ route('admin.company.list') }}" class="btn btn-sm btn-light">
                            Back to company list
                        </a>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-row-bordered gy-5 gs-7">
                            <thead>
                                <tr class="fw-semibold fs-6 text-gray-800">
                                    <th>Brand</th>
                                    <th>Company</th>
                                    <th>Type</th>
                                    <th>CIN</th>
                                    <th>Sector</th>
                                    <th>Submitted by</th>
                                    <th>Submitted at</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($list as $item)
                                    <tr>
                                        <td>{{ $item->brand_name }}</td>
                                        <td>{{ $item->company_name }}</td>
                                        <td>
                                            <span class="badge badge-light-primary">{{ ucfirst($item->type) }}</span>
                                        </td>
                                        <td>{{ $item->cin }}</td>
                                        <td>{{ $item->sector->name ?? '-' }}</td>
                                        <td>
                                            @if ($item->submittedByPartner)
                                                {{ $item->submittedByPartner->name ?? '-' }}
                                                <br><span class="text-muted">Institution</span>
                                                @if ($item->submittedByPartner->mobile_number)
                                                    <br><span class="text-muted">{{ $item->submittedByPartner->mobile_number }}</span>
                                                @endif
                                            @else
                                                {{ $item->submittedBySeller->company_name ?? '-' }}
                                                @if ($item->submittedBySeller?->mobile_number)
                                                    <br><span class="text-muted">{{ $item->submittedBySeller->mobile_number }}</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td>{{ $item->created_at }}</td>
                                        <td>
                                            <div class="d-flex flex-column gap-2">
                                                <a href="{{ route('admin.company.edit', ['uuid' => $item->uuid]) }}"
                                                    class="btn btn-sm btn-light-primary">Edit</a>
                                                <form method="POST"
                                                    action="{{ route('admin.company.approveSeller', ['uuid' => $item->uuid]) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success w-100"
                                                        onclick="return confirm('Approve this company?')">
                                                        Approve
                                                    </button>
                                                </form>
                                                <form method="POST"
                                                    action="{{ route('admin.company.rejectSeller', ['uuid' => $item->uuid]) }}">
                                                    @csrf
                                                    <input type="text" name="rejection_reason" class="form-control form-control-sm mb-1"
                                                        placeholder="Rejection reason (optional)">
                                                    <button type="submit" class="btn btn-sm btn-danger w-100"
                                                        onclick="return confirm('Reject this company?')">
                                                        Reject
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-10">No pending seller companies</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-default-layout>
