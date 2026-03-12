<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $employee?->staff_id ?? 'N/A' }}</title>
    <style>
        @page {
            margin: 0;
            size: 228.6mm 139.7mm;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 9pt;
            line-height: 1.2;
            color: #000;
            /* Customized margins: Top 0.5cm, Right 1cm, Bottom 0.5cm, Left 2cm */
            padding: 14pt 36pt 14pt 36pt;
        }

        /* ===== Header ===== */
        .header-table { width: 100%; margin-bottom: 4pt; }
        .header-table td { border: none; padding: 0; vertical-align: top; }
        .header-left { width: 70%; }
        .header-right { width: 30%; text-align: right; vertical-align: middle !important; }

        .logo-row img { height: 50px; vertical-align: middle; }
        .logo-row .org-text {
            display: inline-block;
            vertical-align: middle;
            padding-left: 8px;
            font-size: 9pt;
            color: #333;
            line-height: 1.4;
        }

        .payslip-title {
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: 1px;
        }

        /* ===== Date ===== */
        .date-row { text-align: right; margin-bottom: 10pt; padding-right: 10pt; font-size: 9pt; }

        /* ===== Staff Info ===== */
        /* Two separate fixed-layout tables — one per info row — so each row has
           its own independent column widths. white-space: nowrap + overflow: hidden
           guarantee cells never grow beyond their fixed width. */
        .info-row { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 4pt; }
        .info-row td { padding: 1pt 4pt; font-size: 9pt; border: none; white-space: nowrap; overflow: hidden; }

        /* ===== Main Table (auto layout prevents dompdf equal-width distribution) ===== */
        .main-table { width: 100%; border-collapse: collapse; }
        .main-table td { border: 1px solid #000; padding: 2pt 4pt; font-size: 8pt; }

        .section-header td {
            font-weight: bold;
            text-align: center;
            font-size: 10pt;
            padding: 3pt 4pt;
        }

        .spacer-row td { padding: 2pt; }
        /* Column headers: text labels centered, THB right-aligned; explicit 9pt sits
           above the 8pt data rows, matching the original's visual hierarchy */
        .col-header td { font-weight: bold; text-align: center; font-size: 9pt; }
        .col-header td.text-end { text-align: right; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }

        .no-bt { border-top: none !important; }
        .no-bb { border-bottom: none !important; }

        .total-row td { font-weight: bold; }
        .grand-total td { font-weight: bold; }
    </style>
</head>
<body>
    {{-- ===== Header ===== --}}
    <table class="header-table">
        <tr>
            <td class="header-left">
                <div class="logo-row">
                    @if(file_exists(public_path('images/smru-logo.png')))
                        <img src="{{ public_path('images/smru-logo.png') }}" alt="SMRU Logo">
                    @else
                        <strong style="font-size: 24pt; color: #1a3c6e;">SMRU.</strong>
                    @endif
                    <span class="org-text">
                        Shoklo Malaria Research Unit<br>
                        Faculty of Tropical Medicine, Mahidol University<br>
                        78/1 Moo 5, Mae Ramat, Mae Ramat, Tak 63140
                    </span>
                </div>
            </td>
            <td class="header-right">
                <div class="payslip-title">PAY SLIP</div>
            </td>
        </tr>
    </table>

    {{-- ===== Date ===== --}}
    <div class="date-row">Date: {{ \Carbon\Carbon::parse($payroll->pay_period_date)->format('d/m/Y') }}</div>

    {{-- ===== Staff Info Row 1: Staff ID / Name / Position / FTE ===== --}}
    {{-- Fixed 4-column layout: 18% / 38% / 30% / 14% = 100% --}}
    <table class="info-row">
        <tr>
            <td style="width: 18%;">Staff ID: {{ $employee?->staff_id ?? 'N/A' }}</td>
            <td style="width: 38%;">Name: {{ $employee?->first_name_en }} {{ $employee?->last_name_en }}</td>
            <td style="width: 30%;">Position: {{ $position }}</td>
            <td style="width: 14%;">FTE: {{ $ftePercentage }}%</td>
        </tr>
    </table>

    {{-- ===== Staff Info Row 2: Grant / BL / Basic Salary ===== --}}
    {{-- Fixed 4-column layout: 25% / 45% / 12% / 17% = 99%
         Grant code and Grant name are truncated server-side with "..." since
         dompdf does not support CSS text-overflow: ellipsis reliably. --}}
    <table class="info-row">
        <tr>
            <td style="width: 25%;">Grant code: {{ strlen($grantCode) > 15 ? substr($grantCode, 0, 15) . '...' : $grantCode }}</td>
            <td style="width: 45%;">Grant name: {{ strlen($grantName) > 50 ? substr($grantName, 0, 50) . '...' : $grantName }}</td>
            <td style="width: 12%;">BL:{{ $budgetLineCode }}</td>
            <td style="width: 17%;">Basic Salary: {{ number_format((float) $payroll->gross_salary) }}</td>
        </tr>
    </table>

    {{-- ===== Main Table ===== --}}
    {{--
        No table-layout: fixed and no colgroup here.
        Dompdf auto-layout lets each column grow to fit its widest content cell,
        preventing text wrapping that would push the table onto a second page.
    --}}
    <table class="main-table">

        {{-- Section Headers --}}
        <tr class="section-header">
            <td colspan="4">INCOME</td>
            <td colspan="2">DEDUCTIONS</td>
        </tr>

        {{-- Spacer --}}
        <tr class="spacer-row">
            <td colspan="6">&nbsp;</td>
        </tr>

        {{-- Column Headers --}}
        <tr class="col-header">
            <td>Detail</td>
            <td class="text-end">THB</td>
            <td>Details</td>
            <td class="text-end">THB</td>
            <td>Details</td>
            <td class="text-end">THB</td>
        </tr>

        {{-- Row 1 --}}
        <tr>
            <td class="no-bb">Salary</td>
            <td class="text-end no-bb">{{ number_format((float) $payroll->gross_salary_by_FTE, 2) }}</td>
            <td class="fw-bold no-bb">Other:</td>
            <td class="no-bb"></td>
            <td class="no-bb">Provident fund: staff 7.5%</td>
            <td class="text-end no-bb">{{ number_format((float) $payroll->pvd, 2) }}</td>
        </tr>

        {{-- Row 2 --}}
        <tr>
            <td class="no-bt no-bb">13-month salary</td>
            <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->thirteen_month_salary, 2) }}</td>
            <td class="no-bt no-bb">Provident fund SMRU 7.5%</td>
            <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->pvd, 2) }}</td>
            <td class="no-bt no-bb">Social security: staff 5%</td>
            <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->employee_social_security, 2) }}</td>
        </tr>

        {{-- Row 3 --}}
        <tr>
            @if((float) $payroll->retroactive_salary)
                <td class="no-bt no-bb">Retroactive Sal.</td>
                <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->retroactive_salary, 2) }}</td>
            @else
                <td class="no-bt no-bb"></td>
                <td class="no-bt no-bb"></td>
            @endif
            <td class="no-bt no-bb">Social security: SMRU 5%</td>
            <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->employer_social_security, 2) }}</td>
            <td class="no-bt no-bb">Health Welfare: staff</td>
            <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->employee_health_welfare, 2) }}</td>
        </tr>

        {{-- Row 4 --}}
        <tr>
            <td class="no-bt no-bb"></td>
            <td class="no-bt no-bb"></td>
            <td class="no-bt no-bb">Health Welfare: SMRU</td>
            <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->employer_health_welfare, 2) }}</td>
            <td class="no-bt no-bb">Tax</td>
            <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->tax, 2) }}</td>
        </tr>

        {{-- Row 5: Study Loan --}}
        <tr>
            <td class="no-bt no-bb"></td>
            <td class="no-bt no-bb"></td>
            <td class="no-bt no-bb"></td>
            <td class="no-bt no-bb"></td>
            <td class="no-bt no-bb">Study Loan</td>
            <td class="text-end no-bt no-bb">{{ number_format((float) ($payroll->study_loan ?? 0), 2) }}</td>
        </tr>

        {{-- Row 6 --}}
        <tr>
            <td class="no-bt no-bb"></td>
            <td class="no-bt no-bb"></td>
            <td class="no-bt no-bb"></td>
            <td class="no-bt no-bb"></td>
            <td class="no-bt no-bb">Provident fund SMRU 7.5%</td>
            <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->pvd, 2) }}</td>
        </tr>

        {{-- Row 6 --}}
        <tr>
            <td class="no-bt no-bb"></td>
            <td class="no-bt no-bb"></td>
            <td class="no-bt no-bb"></td>
            <td class="no-bt no-bb"></td>
            <td class="no-bt no-bb">Social security SMRU 5%</td>
            <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->employer_social_security, 2) }}</td>
        </tr>

        {{-- Row 7 --}}
        <tr>
            <td class="no-bt no-bb"></td>
            <td class="no-bt no-bb"></td>
            <td class="no-bt no-bb"></td>
            <td class="no-bt no-bb"></td>
            <td class="no-bt">Health Welfare: SMRU</td>
            <td class="text-end no-bt">{{ number_format((float) $payroll->employer_health_welfare, 2) }}</td>
        </tr>

        {{-- Totals --}}
        <tr class="total-row">
            <td>Total</td>
            <td class="text-end">{{ number_format((float) $payroll->total_income, 2) }}</td>
            <td>Total</td>
            <td class="text-end">{{ number_format((float) $payroll->employer_contribution, 2) }}</td>
            <td>Total</td>
            <td class="text-end">{{ number_format((float) $payroll->total_deduction, 2) }}</td>
        </tr>

        {{-- Grand Total --}}
        <tr class="grand-total">
            <td colspan="3" class="text-center">Grand total: Salary &amp; Benefit</td>
            <td class="text-end">{{ number_format((float) $payroll->total_salary, 2) }}</td>
            <td colspan="2" rowspan="2" style="vertical-align: top;"><strong>Staff Signature:</strong></td>
        </tr>

        {{-- Net Paid --}}
        <tr>
            <td colspan="3" class="text-center">Net Paid</td>
            <td class="text-end">{{ number_format((float) $payroll->net_salary, 2) }}</td>
        </tr>

        {{-- Notes --}}
        <tr>
            <td colspan="4">Pay method: {{ $employee?->bank_name ?? 'N/A' }}</td>
            <td colspan="2"></td>
        </tr>

    </table>
</body>
</html>
