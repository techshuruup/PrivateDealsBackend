<div class="card mb-5 mb-xl-10">
    <div class="card-header cursor-pointer">
        <div class="card-title m-0">
            <h3 class="fw-bold m-0">Investors</h3>
        </div>
    </div>
    <div class="card-body p-9">
        <table class="table table-bordered table-mini datatable">
            <thead>
                <tr>
                    <th>Investor</th>
                    <th>Mobile</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($partner->investor->where('is_self', 0) as $invItem)
                    <tr>
                        <td>{{ $invItem->name }}</td>
                        <td>{{ $invItem->mobile_number }}</td>
                        <td>{{ $invItem->email }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
