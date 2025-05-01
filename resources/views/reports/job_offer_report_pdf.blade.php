<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Job Offer Report</title>
    <style>
        /* Page settings: A4 landscape, 1cm margin */
        @page {
            size: A4 landscape;
            margin: 1cm;
        }
        body {
            font-family: Calibri, sans-serif; /* Use a font available to DomPDF */
            font-size: 10px;
            margin: 0;
            padding: 0;
        }
        /* Header styling */
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .org-name {
            font-size: 12px;
        }
        .report-title {
            font-size: 12px;
        }
        .report-date {
            font-size: 12px;
        }
        /* Page info, placed at top-right corner */
        .page-info-left {
            position: relative;
            top: 10px;
            right: 10px;
            font-size: 10px;
        }
        .page-info-right {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 10px;
            text-align: right;
        }
        /* Table design borrowed from the screenshot image */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table {
            border: none;
        }
        th {
            border: none;
            border-top: 1px solid #999;
            border-bottom: 1px solid #999;
        }
        td {
            border: none;
        }
        th {
            padding: 8px;
            font-weight: bold;
            text-align: left;
        }
        td {
            padding: 8px;
        }
        .no-data {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #666;
        }
        /* Footer styling */
        .footer {
            text-align: center;
            border-top: 1px solid #999;
            padding-top: 10px;
            font-size: 10px;
            position: fixed;
            bottom: 0;
            width: 100%;
        }
    </style>
</head>
<body>
    <!-- Page Information -->
    <div class="page-info-left">
        Print Date &amp; Time: {{ $currentDateTime }}<br>
        Report Period: {{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}<br>
    </div>
    <div class="page-info-right">
        <script type="text/php">
            if (isset($pdf)) {
                // Choose a font that is available to DomPDF.
                $font = $fontMetrics->get_font("Calibri", "normal");
                // Set coordinates for the top right corner.
                // For an A4 landscape page (width ~842 points), setting x to 750 and y to 20 should be near the top right.
                $pdf->page_text(750, 20, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, 8, array(0,0,0));
            }
        </script>
    </div>



    <!-- Header with Organization and Report Details -->
    <div class="header">
        <div class="org-name">Shoklo Malaria Research Unit</div>
        <div class="report-title">Job Offer Report</div>
        <div class="report-date">Date: {{ now()->format('d M Y') }}</div>
    </div>

    <!-- Job Offer Data Table -->
    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Candidate Name</th>
                <th>Offer Date</th>
                <th>Position</th>
                <th>Salary Details</th>
                <th>Acceptance Deadline</th>
                <th>Acceptance Status</th>
            </tr>
        </thead>
        <tbody>
            @if(count($jobOffers) > 0)
                @foreach($jobOffers as $jobOffer)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $jobOffer->candidate_name }}</td>
                        <td>{{ \Carbon\Carbon::parse($jobOffer->date)->format('d-m-Y') }}</td>
                        <td>{{ $jobOffer->position_name ?? 'N/A' }}</td>
                        <td>{{ $jobOffer->salary_detail ?? 'N/A' }}</td>
                        <td>{{ \Carbon\Carbon::parse($jobOffer->acceptance_deadline)->format('d-m-Y') }}</td>
                        <td>{{ $jobOffer->acceptance_status ?? 'N/A' }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="7" class="no-data">
                        No job offer records found for the selected date range.
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- Footer with Generation Details -->
    <div class="footer">
        Generated on: {{ now()->format('Y-m-d H:i:s') }} | Confidential HR Document
    </div>
</body>
</html>

