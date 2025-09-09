<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Individual Leave Request Summary</title>
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
            margin-bottom: 15px;
        }

        /* Employee info section */
        .employee-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            padding: 10px 0;
        }

        /* Table styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            font-size: 10px;
        }
        .table-header {
            border-top: 2px solid #000;
        }
        th {
            padding: 8px 4px;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            font-size: 10px;
            border: none;
        }
        td {
            padding: 5px 4px;
            text-align: center;
            vertical-align: middle;
            border: none;
            font-size: 10px;
        }
        .text-left {
            text-align: left;
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

        /* Bottom sections container - Fixed at bottom like job offer footer */
        .bottom-sections {
            position: fixed;
            bottom: 1cm;
            left: 1cm;
            right: 1cm;
            width: calc(100% - 2cm);
        }
        
        /* Remaining leave section - FIXED */
        .remaining-leave-section {
            margin-top: 30px;
            border-top: 2px solid #000;
            padding: 15px 0 5px 0;
        }
        .remaining-leave-title {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 15px;
            text-align: left;
        }
        .leave-balances {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            justify-content: space-around !important;
            align-items: center !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            gap: 5px !important;
            text-align: center;
        }
        .balance-item {
            font-weight: bold;
            text-align: center !important;
            flex: 0 1 auto !important;
            min-width: 70px !important;
            max-width: 90px !important;
            font-size: 9px !important;
            line-height: 1.2 !important;
            margin: 0 !important;
            padding: 2px !important;
            display: inline-block !important;
        }

        /* Footer styling */
        .leave-legend {
            margin-top: 40px;
            font-size: 8px;
            text-align: left;
            border-top: 1px solid #999;
            padding-top: 10px;
        }
        .leave-code {
            color: red;
            font-weight: bold;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }

    </style>
</head>
<body>
    <!-- Header Container -->
    <div class="header-container">
        <!-- Print Date & Time (Left) -->
        <div class="print-info">
            Print date &amp; Time: {{ $currentDateTime ?? now()->format('d M Y H:i:s') }}
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
        </div>
    </div>

    <!-- Employee Information Section -->
    <div class="employee-info">
        <span><strong>Staff ID:</strong> {{ $employee->staff_id ?? '' }}</span>
        <span><strong>Staff name:</strong> {{ $employee->first_name_en ?? '' }} {{ $employee->last_name_en ?? '' }}</span>
        <span><strong>Site:</strong> {{ $employee->work_location ?? '' }}</span>
        <span><strong>Department:</strong> {{ $employee->department ?? '' }}</span>
    </div>

    <!-- Leave Requests Table -->
    <table>
        <thead>
            <tr class="table-header">
                <th style="width: 12%;">Date request</th>
                <th style="width: 12%;">Date from</th>
                <th style="width: 12%;">Date to</th>
                <th style="width: 10%;">Full/Half Day</th>
                <th style="width: 15%;">Leave Type</th>
                <th style="width: 20%;">Leave Specific</th>
                <th style="width: 10%;">Leave Day</th>
            </tr>
            <!-- Dashed Separator Row -->
            <tr>
                <td colspan="7" class="dashed-separator"></td>
            </tr>
        </thead>
        <tbody>
            @if(isset($leaveRequests) && count($leaveRequests) > 0)
                @foreach($leaveRequests as $request)
                <tr>
                    <td>{{ $request->date_requested ? $request->date_requested->format('d/m/Y') : '' }}</td>
                    <td>{{ $request->start_date ? $request->start_date->format('d/m/Y') : '' }}</td>
                    <td>{{ $request->end_date ? $request->end_date->format('d/m/Y') : '' }}</td>
                    <td>{{ $request->duration_type ?? '' }}</td>
                    <td class="text-left">{{ $request->leave_type ?? '' }}</td>
                    <td class="text-left">{{ $request->leave_reason ?? '' }}</td>
                    <td>{{ $request->total_days ?? 0 }}</td>
                </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="7" class="no-data">
                        No leave requests found for this employee in the selected period.
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
        
    <!-- Bottom Sections Container - Fixed at bottom -->
    <div class="bottom-sections">
            <!-- Remaining Leave Section -->
            <div class="remaining-leave-section">
        <div class="remaining-leave-title">REMAINING LEAVE</div>
        <div class="leave-balances">
            <div class="balance-item">AN ({{ $entitlements['annual'] ?? 26 }})<br>{{ $employee->remaining_annual_leave ?? 0 }}</div>
            <div class="balance-item">S({{ $entitlements['sick'] ?? 30 }})<br>{{ $employee->remaining_sick_leave ?? 0 }}</div>
            <div class="balance-item">U({{ $entitlements['unpaid'] ?? 0 }})<br>{{ $employee->remaining_unpaid_leave ?? 0 }}</div>
            <div class="balance-item">C({{ $entitlements['compassionate'] ?? 5 }})<br>{{ $employee->remaining_compassionate_leave ?? 0 }}</div>
            <div class="balance-item">M({{ $entitlements['maternity'] ?? 98 }})<br>{{ $employee->remaining_maternity_leave ?? 0 }}</div>
            <div class="balance-item">P({{ $entitlements['paternity'] ?? 14 }})<br>{{ $employee->remaining_paternity_leave ?? 0 }}</div>
            <div class="balance-item">T({{ $entitlements['training'] ?? 14 }})<br>{{ $employee->remaining_training_leave ?? 0 }}</div>
            <div class="balance-item">PH({{ $entitlements['public_holiday'] ?? 14 }})<br>{{ $employee->remaining_public_holiday ?? 0 }}</div>
            <div class="balance-item">PL({{ $entitlements['personal'] ?? 3 }})<br>{{ $employee->remaining_personal_leave ?? 0 }}</div>
            <div class="balance-item">UA({{ $entitlements['unexplained'] ?? 3 }})<br>{{ $employee->remaining_unexplained_absence ?? 0 }}</div>
            </div>

            <!-- Leave Type Legend -->
            <div class="leave-legend">
                <strong>REMARK: ANNUAL LEAVE [<span class="leave-code">AN</span>]| SICK LEAVE [<span class="leave-code">S</span>]| UNPAID LEAVES [<span class="leave-code">U</span>]| COMPASSIONATE LEAVE [<span class="leave-code">C</span>]| MATERNITY LEAVE [<span class="leave-code">M</span>]| PATERNITY LEAVE [<span class="leave-code">P</span>]| TRAINING LEAVE [<span class="leave-code">T</span>]| PUBLIC HOLIDAY [<span class="leave-code">PH</span>]| PERSONAL LEAVE [<span class="leave-code">PL</span>]| UNEXPLAINED ABSENCE [<span class="leave-code">UA</span>]</strong>
            </div>

        <!-- Footer with Generation Details -->
        <div class="footer">
            Generated on: {{ now()->format('Y-m-d H:i:s') }} | Confidential HR Document
        </div>
    </div>
</body>
</html>