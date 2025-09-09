<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Leave Request Summary</title>
    <style>
        /* Page settings: A4 landscape, 1cm margin */
        @page {
            size: A4 landscape;
            margin: 1cm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 0;
        }
        /* Header styling */
        .header-container {
            position: relative;
            margin-bottom: 30px;
        }
        .print-info {
            position: absolute;
            top: 0;
            left: 0;
            font-size: 11px;
        }
        .page-info {
            position: absolute;
            top: 0;
            right: 0;
            font-size: 11px;
        }
        .header-center {
            text-align: center;
            margin-top: 20px;
        }
        .org-name {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .report-title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .report-date {
            font-size: 11px;
            margin-bottom: 2px;
        }
        .site-department {
            font-size: 11px;
        }
        /* Table styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 10px;
        }
        .table-header {
            border-top: 2px solid #000;
            /* border-bottom: 1px solid #000; */
        }
        .section-labels {
            text-align: center;
            font-weight: bold;
            padding: 8px 4px;
            font-size: 11px;
        }
        /* .column-headers {
            border-bottom: 1px solid #000;
        } */
        th {
            padding: 5px 2px;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            font-size: 10px;
            border: none;
        }
        td {
            padding: 3px 2px;
            text-align: center;
            vertical-align: middle;
            border: none;
            font-size: 10px;
        }
        .staff-info {
            text-align: left;
            padding-left: 4px;
        }
        .dashed-separator {
            border-bottom: 1px dashed #000;
            padding: 0;
            height: 1px;
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
        .leave-legend {
            position: fixed;
            bottom: 1cm;
            left: 1cm;
            right: 1cm;
            font-size: 8px;
            text-align: left;
        }
        .leave-code {
            color: red;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Header Container -->
    <div class="header-container">
        <!-- Print Date & Time (Left) -->
        <div class="print-info">
            Print Date &amp; Time: {{ $currentDateTime }}<br>
            Report Period: {{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}
        </div>
        
        <!-- Page Number (Right) -->
        <div class="page-info">
            <script type="text/php">
                if (isset($pdf)) {
                    $font = $fontMetrics->get_font("Arial", "normal");
                    $pdf->page_text(750, 20, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, 11, array(0,0,0));
                }
            </script>
        </div>

        <!-- Center Header -->
        <div class="header-center">
            <div class="org-name">Shoklo Malaria Research Unit</div>
            <div class="report-title">Leave Request Summary</div>
            <div class="report-date">Date: {{ now()->format('d M Y') }}</div>
            <div class="site-department">Site: {{ $work_location ?? 'All Sites' }} | Department: {{ $department ?? 'All Departments' }}</div>
        </div>
    </div>

    <!-- Leave Request Summary Table -->
    <table>
        <thead>
            <!-- Section Labels Row -->
            <tr class="table-header">
                <td></td>
                <td></td>
                <td></td>
                <td colspan="11" class="section-labels">USED LEAVE</td>
                <td colspan="10" class="section-labels">REMAINING LEAVE</td>
            </tr>
            <!-- Column Headers Row -->
            <tr class="column-headers">
                <th>No.</th>
                <th>Staff ID</th>
                <th>Staff name</th>
                <!-- Used Leave Headers -->
                <th>AN ({{ $entitlements['annual'] ?? 26 }})</th>
                <th>S({{ $entitlements['sick'] ?? 30 }})</th>
                <th>U({{ $entitlements['unpaid'] ?? 0 }})</th>
                <th>C({{ $entitlements['compassionate'] ?? 5 }})</th>
                <th>M({{ $entitlements['maternity'] ?? 98 }})</th>
                <th>P({{ $entitlements['paternity'] ?? 14 }})</th>
                <th>T({{ $entitlements['training'] ?? 14 }})</th>
                <th>PH({{ $entitlements['public_holiday'] ?? 14 }})</th>
                <th>PL({{ $entitlements['personal'] ?? 3 }})</th>
                <th>UA({{ $entitlements['unexplained'] ?? 3 }})</th>
                <th></th>
                <!-- Remaining Leave Headers -->
                <th>AN ({{ $entitlements['annual'] ?? 26 }})</th>
                <th>S({{ $entitlements['sick'] ?? 30 }})</th>
                <th>U({{ $entitlements['unpaid'] ?? 0 }})</th>
                <th>C({{ $entitlements['compassionate'] ?? 5 }})</th>
                <th>M({{ $entitlements['maternity'] ?? 98 }})</th>
                <th>P({{ $entitlements['paternity'] ?? 14 }})</th>
                <th>T({{ $entitlements['training'] ?? 14 }})</th>
                <th>PH({{ $entitlements['public_holiday'] ?? 14 }})</th>
                <th>PL({{ $entitlements['personal'] ?? 3 }})</th>
                <th>UA({{ $entitlements['unexplained'] ?? 3 }})</th>
            </tr>
            <!-- Dashed Separator Row -->
            <tr>
                <td colspan="24" class="dashed-separator"></td>
            </tr>
        </thead>
        <tbody>
            @if(count($employees) > 0)
                @foreach($employees as $employee)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="staff-info">{{ $employee->employee_id ?? '' }}</td>
                    <td class="staff-info">{{ $employee->first_name ?? '' }} {{ $employee->last_name ?? '' }}</td>
                    <!-- Used Leave Data -->
                    <td>{{ $employee->used_annual_leave ?? 0 }}</td>
                    <td>{{ $employee->used_sick_leave ?? 0 }}</td>
                    <td>{{ $employee->used_unpaid_leave ?? 0 }}</td>
                    <td>{{ $employee->used_compassionate_leave ?? 0 }}</td>
                    <td>{{ $employee->used_maternity_leave ?? 0 }}</td>
                    <td>{{ $employee->used_paternity_leave ?? 0 }}</td>
                    <td>{{ $employee->used_training_leave ?? 0 }}</td>
                    <td>{{ $employee->used_public_holiday ?? 0 }}</td>
                    <td>{{ $employee->used_personal_leave ?? 0 }}</td>
                    <td>{{ $employee->used_unexplained_absence ?? 0 }}</td>
                    <td></td>
                    <!-- Remaining Leave Data -->
                    <td>{{ $employee->remaining_annual_leave ?? 0 }}</td>
                    <td>{{ $employee->remaining_sick_leave ?? 0 }}</td>
                    <td>{{ $employee->remaining_unpaid_leave ?? 0 }}</td>
                    <td>{{ $employee->remaining_compassionate_leave ?? 0 }}</td>
                    <td>{{ $employee->remaining_maternity_leave ?? 0 }}</td>
                    <td>{{ $employee->remaining_paternity_leave ?? 0 }}</td>
                    <td>{{ $employee->remaining_training_leave ?? 0 }}</td>
                    <td>{{ $employee->remaining_public_holiday ?? 0 }}</td>
                    <td>{{ $employee->remaining_personal_leave ?? 0 }}</td>
                    <td>{{ $employee->remaining_unexplained_absence ?? 0 }}</td>
                </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="23" class="no-data">
                        No employees found for Site: {{ $work_location ?? 'N/A' }}, Department: {{ $department ?? 'N/A' }} in the period {{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}.
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- Leave Type Legend -->
    <div class="leave-legend">
        <strong>REMARK: ANNUAL LEAVE [<span class="leave-code">AN</span>]| SICK LEAVE [<span class="leave-code">S</span>]| UNPAID LEAVES [<span class="leave-code">U</span>]| COMPASSIONATE LEAVE [<span class="leave-code">C</span>]| MATERNITY LEAVE [<span class="leave-code">M</span>]| PATERNITY LEAVE [<span class="leave-code">P</span>]| TRAINING LEAVE [<span class="leave-code">T</span>]| PUBLIC HOLIDAY [<span class="leave-code">PH</span>]| PERSONAL LEAVE [<span class="leave-code">PL</span>]| UNEXPLAINED ABSENCE [<span class="leave-code">UA</span>]</strong>
    </div>

    <!-- Footer with Generation Details -->
    <div class="footer">
        Generated on: {{ now()->format('Y-m-d H:i:s') }} | Confidential HR Document
    </div>
</body>
</html>