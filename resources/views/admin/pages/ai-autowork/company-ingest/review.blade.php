<x-default-layout>
    @section('title')
        {{ getPageTitle() }}
    @endsection

    @php
        $isPending = ($item->status->value ?? $item->status) === 'pending';
        $f = old('fundamentals', is_array($item->fundamentals) ? $item->fundamentals : []);
        $promoters = old('promoters', is_array($item->promoters) && count($item->promoters) ? $item->promoters : [['name' => '', 'designation' => '', 'experience' => '', 'url' => '']]);
        $shareholders = old('shareholders', is_array($item->shareholders) && count($item->shareholders) ? $item->shareholders : []);
        $shareholdersByYear = [];
        foreach ($shareholders as $sh) {
            if (!is_array($sh) || empty($sh['name'])) {
                continue;
            }
            $pcts = $sh['percentages'] ?? [];
            if (!is_array($pcts) || $pcts === []) {
                $shareholdersByYear['_'][] = ['name' => $sh['name'], 'percentage' => ''];
                continue;
            }
            foreach ($pcts as $pct) {
                if (!is_array($pct)) {
                    continue;
                }
                $year = (string) ($pct['year'] ?? '');
                if ($year === '') {
                    $year = '_';
                }
                $shareholdersByYear[$year][] = [
                    'name' => $sh['name'],
                    'percentage' => $pct['percentage'] ?? '',
                ];
            }
        }
        uksort($shareholdersByYear, function ($a, $b) {
            if ($a === '_') {
                return 1;
            }
            if ($b === '_') {
                return -1;
            }
            preg_match('/(\d+)/', (string) $a, $ma);
            preg_match('/(\d+)/', (string) $b, $mb);
            $na = isset($ma[1]) ? (int) $ma[1] : 0;
            $nb = isset($mb[1]) ? (int) $mb[1] : 0;

            return $nb <=> $na;
        });
        $events = old('events', is_array($item->events) && count($item->events) ? $item->events : [['title' => '', 'description' => '', 'date' => '', 'file' => '']]);
        $financials = is_array($item->financials) ? $item->financials : [];
        $financialLabels = [
            'pl_statement' => 'Income / P&L',
            'balance_sheet' => 'Balance Sheet',
            'cashflow' => 'Cash Flow',
            'financial_ratios' => 'Financial Ratios',
        ];
        $orderedFinancials = [];
        foreach ($financialLabels as $key => $lab) {
            foreach ($financials as $fin) {
                if (($fin['label'] ?? '') === $key) {
                    $orderedFinancials[] = $fin;
                }
            }
        }
        foreach ($financials as $fin) {
            $lab = $fin['label'] ?? '';
            if ($lab !== '' && !isset($financialLabels[$lab])) {
                $orderedFinancials[] = $fin;
            }
        }

        /**
         * Normalize financials values into a 2D table:
         * [ ['Particulars', 'FY23', 'FY24', ...], ['Revenue', 100, 120, ...], ... ]
         */
        $financialValuesToTable = function (mixed $values): array {
            if (is_string($values)) {
                $decoded = json_decode($values, true);
                $values = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
            }
            if (!is_array($values) || $values === []) {
                return [];
            }

            // Already a 2D row matrix (first row = headers)
            if (array_is_list($values) && is_array($values[0] ?? null)) {
                return $values;
            }

            // Year-keyed objects: { "FY23": { eps: 1, pat: 2 }, "FY24": { ... } }
            $looksLikeYearKeyed = false;
            foreach ($values as $yearKey => $metrics) {
                if (is_string($yearKey) && is_array($metrics) && !array_is_list($metrics)) {
                    $looksLikeYearKeyed = true;
                    break;
                }
            }

            if ($looksLikeYearKeyed) {
                $years = array_keys($values);
                usort($years, function ($a, $b) {
                    preg_match('/(\d+)/', (string) $a, $ma);
                    preg_match('/(\d+)/', (string) $b, $mb);
                    $na = isset($ma[1]) ? (int) $ma[1] : 0;
                    $nb = isset($mb[1]) ? (int) $mb[1] : 0;
                    if ($na === $nb) {
                        return strcmp((string) $a, (string) $b);
                    }

                    return $na <=> $nb;
                });

                $metricKeys = [];
                foreach ($years as $year) {
                    $metrics = is_array($values[$year] ?? null) ? $values[$year] : [];
                    foreach ($metrics as $metricKey => $metricVal) {
                        if (!in_array($metricKey, $metricKeys, true)) {
                            $metricKeys[] = $metricKey;
                        }
                    }
                }

                $table = [array_merge(['Particulars'], $years)];
                foreach ($metricKeys as $metricKey) {
                    $label = ucwords(str_replace(['_', '-'], ' ', (string) $metricKey));
                    $row = [$label];
                    foreach ($years as $year) {
                        $metrics = is_array($values[$year] ?? null) ? $values[$year] : [];
                        $cell = $metrics[$metricKey] ?? '';
                        if (is_bool($cell)) {
                            $cell = $cell ? 'Yes' : 'No';
                        } elseif (is_array($cell)) {
                            $cell = json_encode($cell);
                        }
                        $row[] = $cell;
                    }
                    $table[] = $row;
                }

                return $table;
            }

            // Associative metric => [year => value] or metric => scalar
            if (!array_is_list($values)) {
                $years = [];
                foreach ($values as $metricKey => $metricVal) {
                    if (is_array($metricVal) && !array_is_list($metricVal)) {
                        foreach (array_keys($metricVal) as $year) {
                            if (!in_array($year, $years, true)) {
                                $years[] = $year;
                            }
                        }
                    }
                }
                if ($years !== []) {
                    usort($years, function ($a, $b) {
                        preg_match('/(\d+)/', (string) $a, $ma);
                        preg_match('/(\d+)/', (string) $b, $mb);
                        $na = isset($ma[1]) ? (int) $ma[1] : 0;
                        $nb = isset($mb[1]) ? (int) $mb[1] : 0;

                        return $na <=> $nb;
                    });
                    $table = [array_merge(['Particulars'], $years)];
                    foreach ($values as $metricKey => $metricVal) {
                        $label = ucwords(str_replace(['_', '-'], ' ', (string) $metricKey));
                        $row = [$label];
                        foreach ($years as $year) {
                            $row[] = is_array($metricVal) ? ($metricVal[$year] ?? '') : ($year === $years[0] ? $metricVal : '');
                        }
                        $table[] = $row;
                    }

                    return $table;
                }
            }

            return [];
        };
    @endphp

    <div class="d-flex flex-column gap-5">
        <div class="card card-flush">
            <div class="card-header">
                <div class="card-title">
                    <h2>Review — {{ $item->brand_name ?: $item->uuid }}</h2>
                </div>
                <div class="card-toolbar">
                    <span class="badge badge-light-{{ $isPending ? 'warning' : 'secondary' }} me-3">{{ $item->status->value ?? $item->status }}</span>
                    <span class="badge badge-light-info me-3">{{ $item->intent->value ?? $item->intent }}</span>
                    @if ($item->is_drhp)
                        <span class="badge badge-light-danger me-3">DRHP Filed</span>
                    @endif
                    <a href="{{ route('admin.ai-autowork.company-ingest.inbox') }}" class="btn btn-sm btn-light">Back</a>
                </div>
            </div>
            <div class="card-body pt-0">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.ai-autowork.company-ingest.update', $item->uuid) }}" enctype="multipart/form-data" id="reviewForm">
                    @csrf

                    <ul class="nav nav-tabs nav-line-tabs mb-5 fs-6">
                        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab_core">Core</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab_fundamentals">Fundamentals</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab_promoters">Promoters</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab_shareholders">Shareholders</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab_events">Events</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab_financials">Financials</a></li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tab_core">
                            <div class="row g-5">
                                <div class="col-md-3">
                                    <label class="form-label">Logo</label>
                                    <div class="mb-3">
                                        @if ($item->logo)
                                            <img src="{{ \App\Helpers\FileUpDownHelper::generateUrl($item->logo) }}" alt="logo" style="max-height:80px" class="mb-2 d-block">
                                        @elseif ($item->logo_url)
                                            <img src="{{ $item->logo_url }}" alt="logo" style="max-height:80px" class="mb-2 d-block" referrerpolicy="no-referrer">
                                            <div class="text-muted fs-8 mb-2">Showing remote logo URL (not downloaded yet)</div>
                                        @endif
                                        @if ($isPending)
                                            <input type="file" name="logo" class="form-control mb-2" accept="image/*">
                                        @endif
                                        <label class="form-label">Logo URL</label>
                                        <input type="text" name="logo_url" class="form-control" value="{{ old('logo_url', $item->logo_url) }}" @disabled(!$isPending) placeholder="https://...">
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <div class="row g-5">
                                        <div class="col-md-4">
                                            <label class="form-label required">CIN</label>
                                            <input type="text" name="cin" class="form-control" value="{{ old('cin', $item->cin) }}" @disabled(!$isPending)>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label required">Brand name</label>
                                            <input type="text" name="brand_name" class="form-control" value="{{ old('brand_name', $item->brand_name) }}" @disabled(!$isPending)>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label required">Company name</label>
                                            <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $item->company_name) }}" @disabled(!$isPending)>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label required">Sector</label>
                                            <select name="sector_id" class="form-select" @disabled(!$isPending)>
                                                <option value="">Select</option>
                                                @foreach ($sectors as $sector)
                                                    <option value="{{ $sector->id }}" @selected(old('sector_id', $item->sector_id) == $sector->id)>{{ $sector->name }}</option>
                                                @endforeach
                                            </select>
                                            @if ($item->sector_name)
                                                <div class="text-muted fs-7">AI sector name: {{ $item->sector_name }}</div>
                                            @endif
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Type</label>
                                            <select name="type" class="form-select" @disabled(!$isPending)>
                                                <option value="unlisted" @selected(old('type', $item->type) === 'unlisted')>unlisted</option>
                                                <option value="secondary" @selected(old('type', $item->type) === 'secondary')>secondary</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label d-block">DRHP Filed</label>
                                            <div class="form-check form-switch form-check-custom form-check-solid mt-3">
                                                <input class="form-check-input" type="checkbox" name="is_drhp" value="1"
                                                    @checked(old('is_drhp', $item->is_drhp)) @disabled(!$isPending)>
                                                <label class="form-check-label">Yes — DRHP Filed</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required">Min investment amount</label>
                                    <input type="number" step="0.01" name="min_investment_amount" class="form-control" value="{{ old('min_investment_amount', $item->min_investment_amount) }}" @disabled(!$isPending)>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required">Commission</label>
                                    <input type="number" step="0.01" name="commission" class="form-control" value="{{ old('commission', $item->commission) }}" @disabled(!$isPending)>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required">Processing fee %</label>
                                    <input type="number" step="0.01" name="processing_fee_percentage" class="form-control" value="{{ old('processing_fee_percentage', $item->processing_fee_percentage ?? CommonHelper::processingFeePercentage()) }}" @disabled(!$isPending)>
                                </div>
                                <div class="col-12">
                                    <label class="form-label required">About</label>
                                    <textarea name="about" class="form-control" rows="8" @disabled(!$isPending)>{{ old('about', $item->about) }}</textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Keywords</label>
                                    <input type="text" name="keywords" class="form-control" value="{{ old('keywords', $item->keywords) }}" @disabled(!$isPending)>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Negative keywords</label>
                                    <input type="text" name="negative_keywords" class="form-control" value="{{ old('negative_keywords', $item->negative_keywords) }}" @disabled(!$isPending)>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Alternative names</label>
                                    <input type="text" name="alternative_names" class="form-control" value="{{ old('alternative_names', $item->alternative_names) }}" @disabled(!$isPending)>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Admin notes</label>
                                    <textarea name="admin_notes" class="form-control" rows="2" @disabled(!$isPending)>{{ old('admin_notes', $item->admin_notes) }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab_fundamentals">
                            <div class="row g-5">
                                @foreach ([
                                    'lot_size' => 'Lot size',
                                    'fifty_two_week_high' => '52w high',
                                    'fifty_two_week_low' => '52w low',
                                    'depository' => 'Depository',
                                    'pan_number' => 'PAN',
                                    'isin_number' => 'ISIN',
                                    'cin_number' => 'CIN (fundamentals)',
                                    'rta' => 'RTA',
                                    'market_cap' => 'Market cap',
                                    'pe_ratio' => 'PE ratio',
                                    'pb_ratio' => 'PB ratio',
                                    'debt_to_equity' => 'Debt to equity',
                                    'roe' => 'ROE',
                                    'book_value' => 'Book value',
                                    'face_value' => 'Face value',
                                    'total_shares' => 'Total shares',
                                ] as $key => $label)
                                    <div class="col-md-3">
                                        <label class="form-label">{{ $label }}</label>
                                        <input type="text" name="fundamentals[{{ $key }}]" class="form-control"
                                            value="{{ $f[$key] ?? '' }}" @disabled(!$isPending)>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab_promoters">
                            <div id="promoters_wrap" class="d-flex flex-column gap-4">
                                @foreach ($promoters as $i => $p)
                                    <div class="border rounded p-4 promoter-row">
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label">Name</label>
                                                <input type="text" name="promoters[{{ $i }}][name]" class="form-control" value="{{ $p['name'] ?? '' }}" @disabled(!$isPending)>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Designation</label>
                                                <input type="text" name="promoters[{{ $i }}][designation]" class="form-control" value="{{ $p['designation'] ?? '' }}" @disabled(!$isPending)>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Experience</label>
                                                <input type="text" name="promoters[{{ $i }}][experience]" class="form-control" value="{{ $p['experience'] ?? '' }}" @disabled(!$isPending)>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">LinkedIn / URL</label>
                                                <input type="text" name="promoters[{{ $i }}][url]" class="form-control" value="{{ $p['url'] ?? '' }}" @disabled(!$isPending)>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @if ($isPending)
                                <button type="button" class="btn btn-sm btn-light-primary mt-4" id="btn_add_promoter">+ Add promoter</button>
                            @endif
                        </div>

                        <div class="tab-pane fade" id="tab_shareholders">
                            @if (count($shareholdersByYear) === 0)
                                <p class="text-muted mb-0">No shareholders ingested.</p>
                            @else
                                <ul class="nav nav-tabs nav-line-tabs mb-5 fs-6">
                                    @foreach ($shareholdersByYear as $year => $holders)
                                        <li class="nav-item">
                                            <a class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" href="#sh_year_{{ $loop->index }}">
                                                {{ $year === '_' ? 'Unspecified' : $year }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="tab-content">
                                    @foreach ($shareholdersByYear as $year => $holders)
                                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="sh_year_{{ $loop->index }}">
                                            <div class="table-responsive">
                                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-3">
                                                    <thead>
                                                        <tr class="fw-bold text-muted text-uppercase fs-7">
                                                            <th class="min-w-200px text-start">Shareholder</th>
                                                            <th class="text-end min-w-100px">Percentage</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($holders as $holder)
                                                            <tr>
                                                                <td class="fw-semibold text-gray-800">{{ $holder['name'] }}</td>
                                                                <td class="text-end">
                                                                    @php $pctVal = $holder['percentage']; @endphp
                                                                    {{ $pctVal === '' || $pctVal === null ? '—' : (is_numeric($pctVal) ? rtrim(rtrim(number_format((float) $pctVal, 2), '0'), '.') . '%' : $pctVal) }}
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($isPending)
                                <div class="separator my-8"></div>
                                <div class="text-muted fs-7 mb-3">Edit flat list (saved as name + year percentages). Prefer re-ingest if year tabs were incomplete.</div>
                                <div id="shareholders_wrap" class="d-flex flex-column gap-4">
                                    @php
                                        $editShareholders = count($shareholders) ? $shareholders : [['name' => '', 'percentages' => [['year' => '', 'percentage' => '']]]];
                                    @endphp
                                    @foreach ($editShareholders as $i => $sh)
                                        <div class="border rounded p-4 shareholder-row">
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Shareholder name</label>
                                                    <input type="text" name="shareholders[{{ $i }}][name]" class="form-control" value="{{ $sh['name'] ?? '' }}">
                                                </div>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-row-bordered gy-3">
                                                    <thead><tr><th>Year / tab</th><th>Percentage</th></tr></thead>
                                                    <tbody>
                                                        @php $pcts = $sh['percentages'] ?? [['year' => '', 'percentage' => '']]; @endphp
                                                        @foreach ($pcts as $pi => $pct)
                                                            <tr>
                                                                <td><input type="text" name="shareholders[{{ $i }}][percentages][{{ $pi }}][year]" class="form-control" value="{{ $pct['year'] ?? '' }}" placeholder="FY24"></td>
                                                                <td><input type="text" name="shareholders[{{ $i }}][percentages][{{ $pi }}][percentage]" class="form-control" value="{{ $pct['percentage'] ?? '' }}"></td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" class="btn btn-sm btn-light-primary mt-4" id="btn_add_shareholder">+ Add shareholder</button>
                            @endif
                        </div>

                        <div class="tab-pane fade" id="tab_events">
                            <div class="text-muted fs-7 mb-3">Latest 5 events only (newest first).</div>
                            <div id="events_wrap" class="d-flex flex-column gap-4">
                                @foreach ($events as $i => $ev)
                                    <div class="border rounded p-4 event-row">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Title</label>
                                                <input type="text" name="events[{{ $i }}][title]" class="form-control" value="{{ $ev['title'] ?? '' }}" @disabled(!$isPending)>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Date</label>
                                                <input type="text" name="events[{{ $i }}][date]" class="form-control" value="{{ $ev['date'] ?? '' }}" @disabled(!$isPending)>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label">File / attachment URL</label>
                                                <input type="text" name="events[{{ $i }}][file]" class="form-control" value="{{ $ev['file'] ?? '' }}" @disabled(!$isPending)>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Description</label>
                                                <textarea name="events[{{ $i }}][description]" class="form-control" rows="3" @disabled(!$isPending)>{{ $ev['description'] ?? '' }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @if ($isPending)
                                <button type="button" class="btn btn-sm btn-light-primary mt-4" id="btn_add_event">+ Add event</button>
                            @endif
                        </div>

                        <div class="tab-pane fade" id="tab_financials">
                            <div class="alert alert-light mb-5">
                                Financials are read-only here. Edit them later from the live company page after approve.
                            </div>
                            @if (count($orderedFinancials) === 0)
                                <p class="text-muted mb-0">No financial statements ingested.</p>
                            @else
                                <ul class="nav nav-tabs nav-line-tabs mb-5 fs-6">
                                    @foreach ($orderedFinancials as $fi => $fin)
                                        <li class="nav-item">
                                            <a class="nav-link {{ $fi === 0 ? 'active' : '' }}" data-bs-toggle="tab" href="#fin_pane_{{ $fi }}">
                                                {{ $financialLabels[$fin['label'] ?? ''] ?? ucwords(str_replace('_', ' ', $fin['label'] ?? 'Statement')) }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="tab-content">
                                    @foreach ($orderedFinancials as $fi => $fin)
                                        @php
                                            $table = $financialValuesToTable($fin['values'] ?? []);
                                            $yearCount = count($table[0] ?? []) - 1;
                                        @endphp
                                        <div class="tab-pane fade {{ $fi === 0 ? 'show active' : '' }}" id="fin_pane_{{ $fi }}">
                                            @if (count($table) > 1)
                                                <div class="text-muted text-uppercase fs-8 fw-semibold mb-2">Figures</div>
                                                <div class="table-responsive">
                                                    <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-3">
                                                        <thead>
                                                            <tr class="fw-bold text-muted text-uppercase fs-7">
                                                                @foreach ($table[0] as $colIndex => $headerCell)
                                                                    <th class="{{ $colIndex === 0 ? 'min-w-200px text-start' : 'text-end min-w-100px' }} {{ $colIndex === $yearCount ? 'text-success' : '' }}">
                                                                        {{ $headerCell }}
                                                                    </th>
                                                                @endforeach
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($table as $rowIndex => $rowData)
                                                                @if ($rowIndex > 0 && is_array($rowData))
                                                                    <tr>
                                                                        @foreach ($rowData as $cellIndex => $cellData)
                                                                            @if ($cellIndex === 0)
                                                                                <td class="fw-semibold text-gray-800">{{ $cellData }}</td>
                                                                            @else
                                                                                <td class="text-end {{ $cellIndex === $yearCount ? 'bg-light-success' : '' }}">
                                                                                    {{ is_numeric($cellData) ? (fmod((float) $cellData, 1) == 0 ? number_format((float) $cellData) : number_format((float) $cellData, 2)) : $cellData }}
                                                                                </td>
                                                                            @endif
                                                                        @endforeach
                                                                    </tr>
                                                                @endif
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @else
                                                <p class="text-muted mb-0">No tabular data for this statement.</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($isPending)
                        <div class="mt-8 border-top pt-8">
                            <h3 class="mb-5">Approve / Reject</h3>

                            @if (($item->intent->value ?? $item->intent) === 'update')
                                <p class="text-muted">Update intent — choose which sections to replace on the live company:</p>
                                <div class="d-flex flex-wrap gap-5 mb-5">
                                    <label class="form-check form-check-custom">
                                        <input class="form-check-input" type="checkbox" name="replace_promoters" value="1" checked>
                                        <span class="form-check-label">Replace promoters</span>
                                    </label>
                                    <label class="form-check form-check-custom">
                                        <input class="form-check-input" type="checkbox" name="replace_shareholders" value="1" checked>
                                        <span class="form-check-label">Replace shareholders</span>
                                    </label>
                                    <label class="form-check form-check-custom">
                                        <input class="form-check-input" type="checkbox" name="replace_events" value="1" checked>
                                        <span class="form-check-label">Replace events</span>
                                    </label>
                                    <label class="form-check form-check-custom">
                                        <input class="form-check-input" type="checkbox" name="replace_financials" value="1" checked>
                                        <span class="form-check-label">Replace financials</span>
                                    </label>
                                </div>
                            @endif

                            <div class="d-flex flex-wrap gap-3 align-items-center">
                                <button type="submit" class="btn btn-primary">Save temp changes</button>
                                <button
                                    type="submit"
                                    class="btn btn-success"
                                    formaction="{{ route('admin.ai-autowork.company-ingest.approve', $item->uuid) }}"
                                    onclick="return confirm('Save and promote this into master company data?')"
                                >
                                    Approve &amp; promote
                                </button>
                            </div>
                            <div class="text-muted fs-7 mt-3">Approve saves your filled fields first, then validates required company/fundamentals data.</div>
                        </div>
                    @endif
                </form>

                @if ($isPending)
                    <form method="POST" action="{{ route('admin.ai-autowork.company-ingest.reject', $item->uuid) }}" class="mt-8">
                        @csrf
                        <label class="form-label">Reject notes</label>
                        <textarea name="admin_notes" class="form-control mb-3" rows="2">{{ old('admin_notes', $item->admin_notes) }}</textarea>
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Reject this AI ingest?')">Reject</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @if ($isPending)
        @push('scripts')
            <script>
                (function() {
                    function nextIndex(wrap, rowClass) {
                        return wrap.querySelectorAll('.' + rowClass).length;
                    }
                    document.getElementById('btn_add_promoter')?.addEventListener('click', function() {
                        const wrap = document.getElementById('promoters_wrap');
                        const i = nextIndex(wrap, 'promoter-row');
                        const div = document.createElement('div');
                        div.className = 'border rounded p-4 promoter-row';
                        div.innerHTML = '<div class="row g-3">' +
                            '<div class="col-md-3"><label class="form-label">Name</label><input type="text" name="promoters[' + i + '][name]" class="form-control"></div>' +
                            '<div class="col-md-3"><label class="form-label">Designation</label><input type="text" name="promoters[' + i + '][designation]" class="form-control"></div>' +
                            '<div class="col-md-3"><label class="form-label">Experience</label><input type="text" name="promoters[' + i + '][experience]" class="form-control"></div>' +
                            '<div class="col-md-3"><label class="form-label">LinkedIn / URL</label><input type="text" name="promoters[' + i + '][url]" class="form-control"></div></div>';
                        wrap.appendChild(div);
                    });
                    document.getElementById('btn_add_shareholder')?.addEventListener('click', function() {
                        const wrap = document.getElementById('shareholders_wrap');
                        const i = nextIndex(wrap, 'shareholder-row');
                        const div = document.createElement('div');
                        div.className = 'border rounded p-4 shareholder-row';
                        div.innerHTML = '<div class="row g-3 mb-3"><div class="col-md-6"><label class="form-label">Shareholder name</label><input type="text" name="shareholders[' + i + '][name]" class="form-control"></div></div>' +
                            '<div class="table-responsive"><table class="table table-row-bordered gy-3"><thead><tr><th>Year</th><th>Percentage</th></tr></thead><tbody><tr>' +
                            '<td><input type="text" name="shareholders[' + i + '][percentages][0][year]" class="form-control"></td>' +
                            '<td><input type="text" name="shareholders[' + i + '][percentages][0][percentage]" class="form-control"></td></tr></tbody></table></div>';
                        wrap.appendChild(div);
                    });
                    document.getElementById('btn_add_event')?.addEventListener('click', function() {
                        const wrap = document.getElementById('events_wrap');
                        if (nextIndex(wrap, 'event-row') >= 5) {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({ icon: 'info', title: 'Limit reached', text: 'Only the latest 5 events are kept.' });
                            } else {
                                alert('Only the latest 5 events are kept.');
                            }
                            return;
                        }
                        const i = nextIndex(wrap, 'event-row');
                        const div = document.createElement('div');
                        div.className = 'border rounded p-4 event-row';
                        div.innerHTML = '<div class="row g-3">' +
                            '<div class="col-md-4"><label class="form-label">Title</label><input type="text" name="events[' + i + '][title]" class="form-control"></div>' +
                            '<div class="col-md-3"><label class="form-label">Date</label><input type="text" name="events[' + i + '][date]" class="form-control"></div>' +
                            '<div class="col-md-5"><label class="form-label">File / attachment URL</label><input type="text" name="events[' + i + '][file]" class="form-control"></div>' +
                            '<div class="col-12"><label class="form-label">Description</label><textarea name="events[' + i + '][description]" class="form-control" rows="3"></textarea></div></div>';
                        wrap.appendChild(div);
                    });
                })();
            </script>
        @endpush
    @endif
</x-default-layout>
