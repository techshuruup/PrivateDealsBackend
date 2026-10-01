<?php

namespace App\DataTables;

use App\Helpers\AdminHelper;
use App\Models\PreIpoModel;
use App\Models\PreIpoTransaction;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class PreIpoTransactionDataTable extends DataTable
{
    protected $operator = '=';
    protected $status;

    public function withStatus($operator, $status)
    {
        $this->operator = $operator;
        $this->status = $status;
        return $this;
    }
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return datatables()
            ->eloquent($query)
            ->addColumn('investor', function ($transaction) {
                return $transaction->transaction_invoice_no . '<br>' . ($transaction->investor->name ?? 'N/A');
            })
            ->addColumn('company', function ($transaction) {
                return $transaction->company->brand_name ?? 'N/A';
            })
            ->addColumn('investment_amount', function ($transaction) {
                return number_format($transaction->investment_amount, 2);
            })
            ->addColumn('share_price', function ($transaction) {
                return number_format($transaction->share_price, 2);
            })
            // ->addColumn('shares', function ($transaction) {
            //     return number_format($transaction->shares);
            // })
            ->addColumn('created_at', function ($transaction) {
                return $transaction->created_at ? \App\Helpers\DateTimeHelper::formatDateTime($transaction->created_at, 'd M Y h:i A') : 'N/A';
            })
            ->addColumn('current_status', function ($transaction) {
                return $transaction->current_status;
            })
            ->addColumn('action', function ($transaction) {
                return view('admin.pages.pre-ipo-transactions.partials.actions', compact('transaction'))->render();
            })
            ->filterColumn('investor', function ($query, $keyword) {
                $query->whereHas('investor', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('company', function ($query, $keyword) {
                $query->whereHas('company', function ($q) use ($keyword) {
                    $q->where('brand_name', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('investment_amount', function ($query, $keyword) {
                $query->whereRaw("CAST(investment_amount AS DECIMAL(10,2)) LIKE ?", ["%" . str_replace(',', '', $keyword) . "%"]);
            })
            ->filterColumn('share_price', function ($query, $keyword) {
                $query->whereRaw("CAST(share_price AS DECIMAL(10,2)) LIKE ?", ["%" . str_replace(',', '', $keyword) . "%"]);
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->whereRaw("DATE_FORMAT(created_at, '%d %b %Y %h:%i %p') like ?", ["%$keyword%"]);
            })
            // ->filterColumn('shares', function ($query, $keyword) {
            //     $query->where('shares', 'like', "%{$keyword}%");
            // })
            ->rawColumns(['status', 'action', 'investor'])
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(PreIpoModel $model): QueryBuilder
    {

        return $model->newQuery()
            ->with(['investor', 'company'])
            // ->when(isset($this->status), function ($query) {
            //     return $query->where('status', $this->operator, $this->status);
            // })
            ->when(request()->routeIs('admin.preipotransaction.pending'), function ($query) {
                return $query->where('status', '>', '1')->where('status', '!=', '5')->whereNull('order_step');
            })
            ->when(request()->routeIs('admin.preipotransaction.completed'), function ($query) {
                return $query->where('status', '5')->whereNull('order_step');
            })
            ->when(request()->routeIs('admin.preipotransaction.rejected'), function ($query) {
                return $query->where('status', '1')->whereNull('order_step');
            })
            ->whereHas('investor', function ($q) {
                $q->where('is_deleted', 0);
                if (request()->has('investor_key')) {
                    $q->where('uuid', request()->get('investor_key'));
                }
            });
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('preipo_transactions_table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(5, 'desc')
            ->parameters([
                'drawCallback' => 'function() { KTMenu.createInstances(); }',
                'language' => [
                    'lengthMenu' => "Show _MENU_ records per page",
                    'zeroRecords' => "No matching records found",
                    'info' => "Showing _START_ to _END_ of _TOTAL_ entries",
                    'infoEmpty' => "No records available",
                    'infoFiltered' => "(filtered from _MAX_ total records)"
                ],
                'pageLength' => 10,
                'searching' => true,
                'order' => [[4, 'desc']],
                'columnDefs' => [
                    [
                        'targets' => 4,
                        'type' => 'date-eu'
                    ]
                ]
            ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('investor')->title('Investor')->searchable(true),
            Column::make('company')->title('Company')->searchable(true),
            Column::make('investment_amount')->title('Investment Amount')->searchable(true),
            Column::make('share_price')->title('Share Price')->searchable(true),
            // Column::make('shares')->title('Shares')->searchable(true),
            // Column::make('current_status')->title('Current Status')->searchable(true),
            // Column::make('date')->title('Date')
            //     ->searchable(true)
            //     ->orderable(true)
            //     ->data('date_formatted'),
            Column::make('created_at')->title('Date')->searchable(true),
            Column::computed('action')->title('Action')->exportable(false)->printable(false)->width(60)->addClass('text-center'),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'PreIpoTransaction_' . date('YmdHis');
    }
}
