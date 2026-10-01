<x-default-layout>
    @section('title')
        {{ getPageTitle() }}
    @endsection

    <div class="card card-flush py-4">
        <div class="card-header">
            <div class="card-title">
                <h2>Partner order steps</h2>
            </div>
        </div>
        <div class="card-body pt-0">
            <p class="text-muted">Orders placed by a partner on an Institution deal. These rows stay on <code>order_step</code> and are not moved by the old status 0–5 actions.</p>
            <div class="table-responsive">
                <table class="table table-row-bordered align-middle">
                    <thead>
                        <tr>
                            <th>Transaction</th>
                            <th>Investor</th>
                            <th>Company</th>
                            <th>Step</th>
                            <th>Current</th>
                            <th>Next</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $row)
                            @php
                                $transaction = $row['model'];
                                $actions = $row['action'];
                            @endphp
                            <tr>
                                <td>{{ $transaction->transaction_invoice_no }}</td>
                                <td>{{ $transaction->investor->name ?? '' }}</td>
                                <td>{{ $transaction->company->brand_name ?? '' }}</td>
                                <td>{{ $transaction->order_step }}</td>
                                <td>{{ $row['current'] }}</td>
                                <td>{{ $row['next'] }}</td>
                                <td>
                                    @if (in_array('approve', $actions, true))
                                        <form method="POST" action="{{ route('admin.preipotransaction.orderStepApprove', $transaction->id) }}" class="mb-2">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary">Approve</button>
                                        </form>
                                    @endif
                                    @if (in_array('reject', $actions, true))
                                        <form method="POST" action="{{ route('admin.preipotransaction.orderStepReject', $transaction->id) }}" class="mb-2">
                                            @csrf
                                            <input type="text" name="reason" class="form-control form-control-sm mb-1" placeholder="Cancellation reason" required>
                                            <button type="submit" class="btn btn-sm btn-light-danger">Reject</button>
                                        </form>
                                    @endif
                                    @if (in_array('confirm_payment', $actions, true))
                                        <form method="POST" action="{{ route('admin.preipotransaction.orderStepConfirmPayment', $transaction->id) }}" class="mb-2">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary">Confirm payment</button>
                                        </form>
                                    @endif
                                    @if (in_array('upload_share_transfer_receipt', $actions, true))
                                        <form method="POST" action="{{ route('admin.preipotransaction.orderStepShareTransfer', $transaction->id) }}" enctype="multipart/form-data">
                                            @csrf
                                            <input type="file" name="file" class="form-control form-control-sm mb-1" required>
                                            <button type="submit" class="btn btn-sm btn-primary">Upload share-transfer receipt</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No partner order-step transactions.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-default-layout>
