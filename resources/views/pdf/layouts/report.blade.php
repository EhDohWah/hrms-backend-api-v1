@extends('pdf.layouts.base')

{{--
|--------------------------------------------------------------------------
| Report Layout - For tabular reports (A4 Landscape)
|--------------------------------------------------------------------------
| Use this layout for reports with tables like:
| - Job Offer Reports
| - Interview Reports
| - Leave Request Reports
|
| Usage:
| @extends('pdf.layouts.report')
| @section('title', 'My Report Title')
| @section('report-title', 'Report Name')
| @section('report-filters') ... optional filters info ... @endsection
| @section('table-content') ... your table ... @endsection
|--------------------------------------------------------------------------
--}}

@section('page-setup')
@page {
    size: A4 landscape;
    margin: 1cm;
}
@endsection

@section('styles')
/* Report-specific styles */
.report-container {
    position: relative;
}

/* Header row with print info and page number */
.report-meta {
    position: relative;
    margin-bottom: 10px;
    min-height: 35px;
}

.report-meta .meta-left {
    position: absolute;
    top: 0;
    left: 0;
    font-size: 10px;
    line-height: 1.5;
}

.report-meta .meta-right {
    position: absolute;
    top: 0;
    right: 0;
    font-size: 10px;
}

/* Centered report header */
.report-header-block {
    text-align: center;
    margin-bottom: 15px;
}

.report-header-block .org-name {
    font-size: 12px;
    font-weight: bold;
}

.report-header-block .report-title {
    font-size: 12px;
    font-weight: bold;
    margin-top: 2px;
}

.report-header-block .report-date {
    font-size: 11px;
    margin-top: 2px;
}

.report-header-block .report-filters {
    font-size: 11px;
    margin-top: 2px;
    color: #333;
}

/* Report table styling */
.report-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    font-size: 10px;
}

.report-table thead th {
    padding: 8px 5px;
    font-weight: bold;
    text-align: left;
    border-top: 1px solid #999;
    border-bottom: 1px solid #999;
    background-color: transparent;
}

.report-table tbody td {
    padding: 6px 5px;
    border: none;
}

.report-table th.center,
.report-table td.center {
    text-align: center;
}

/* Report footer */
.report-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 10px;
    border-top: 1px solid #999;
    padding-top: 8px;
    color: #333;
}

@yield('report-styles')
@endsection

@section('content')
<div class="report-container">
    {{-- Page Information --}}
    <div class="report-meta">
        <div class="meta-left">
            Print Date &amp; Time: {{ $currentDateTime ?? now()->format('Y-m-d H:i:s') }}<br>
            @hasSection('report-period')
                @yield('report-period')
            @else
                @if(isset($startDate) && isset($endDate))
                    Report Period: {{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}
                @endif
            @endif
        </div>
        <div class="meta-right">
            {{-- Page numbers handled by PHP script --}}
        </div>
    </div>

    {{-- Report Header --}}
    <div class="report-header-block">
        <div class="org-name">Shoklo Malaria Research Unit</div>
        <div class="report-title">@yield('report-title', 'Report')</div>
        <div class="report-date">Date: {{ now()->format('d M Y') }}</div>
        @hasSection('report-filters')
            <div class="report-filters">@yield('report-filters')</div>
        @endif
    </div>

    {{-- Main Table Content --}}
    @yield('table-content')

    {{-- Optional Legend/Notes --}}
    @yield('report-legend')

    {{-- Footer --}}
    <div class="report-footer">
        Generated on: {{ now()->format('Y-m-d H:i:s') }} | Confidential HR Document
    </div>
</div>
@endsection

@section('page-numbering')
<script type="text/php">
    if (isset($pdf)) {
        $font = $fontMetrics->get_font("DejaVu Sans", "normal");
        // A4 landscape width ~842 points, position at top-right
        $pdf->page_text(750, 20, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, 9, array(0, 0, 0));
    }
</script>
@endsection
