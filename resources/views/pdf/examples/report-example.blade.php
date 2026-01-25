{{--
|--------------------------------------------------------------------------
| Example: How to use the Report Layout
|--------------------------------------------------------------------------
| This is a reference template showing how to create a new report PDF
| using the shared report layout.
|
| Copy this file and customize for your specific report.
|--------------------------------------------------------------------------
--}}

@extends('pdf.layouts.report')

@section('title', 'Sample Report')

@section('report-title', 'Sample Report')

{{-- Optional: Custom report period display --}}
@section('report-period')
    Report Period: {{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}
@endsection

{{-- Optional: Filter information --}}
@section('report-filters')
    Department: {{ $department ?? 'All' }} | Status: {{ $status ?? 'All' }}
@endsection

{{-- Main table content --}}
@section('table-content')
<table class="report-table">
    <thead>
        <tr>
            <th style="width: 5%;">No.</th>
            <th style="width: 25%;">Name</th>
            <th style="width: 20%;">Date</th>
            <th style="width: 25%;">Description</th>
            <th style="width: 15%;" class="center">Status</th>
            <th style="width: 10%;" class="center">Amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse($items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->name }}</td>
                <td>{{ \Carbon\Carbon::parse($item->date)->format('d-m-Y') }}</td>
                <td>{{ $item->description ?? 'N/A' }}</td>
                <td class="center">{{ $item->status }}</td>
                <td class="center">{{ number_format($item->amount, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="no-data">
                    No records found for the selected criteria.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
@endsection

{{-- Optional: Add a legend or notes section --}}
@section('report-legend')
{{--
<div style="position: fixed; bottom: 30px; left: 1cm; font-size: 8px;">
    <strong>Legend:</strong> A = Active | I = Inactive | P = Pending
</div>
--}}
@endsection

{{-- Optional: Add report-specific styles --}}
@section('report-styles')
/* Custom styles for this specific report */
.status-active { color: green; }
.status-inactive { color: red; }
@endsection
