<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $employee?->staff_id ?? 'N/A' }}</title>
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 9px;
            line-height: 1.2;
            color: #000;
            /* 1-inch margins (72pt = 1in) matching standard Word processing */
            padding: 35pt;
        }

        /* ===== Header ===== */
        .header-table { width: 100%; margin-bottom: 8px; }
        .header-table td { border: none; padding: 0; vertical-align: top; }
        .header-left { width: 70%; }
        .header-right { width: 30%; text-align: right; vertical-align: middle !important; }

        .logo-row { margin-bottom: 0; }
        .logo-row img { height: 50px; vertical-align: middle; }
        .logo-row .org-text {
            display: inline-block;
            vertical-align: middle;
            padding-left: 8px;
            font-size: 9px;
            color: #333;
            line-height: 1.4;
        }

        .payslip-title {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        /* ===== Date ===== */
        .date-row { text-align: right; margin-bottom: 5px; font-size: 10px; }

        /* ===== Staff Info ===== */
        .info-table { width: 100%; margin-bottom: 2px; border-collapse: collapse; table-layout: fixed; }
        .info-table td { padding: 3px 5px; font-size: 9px; border: none; }

        /* ===== Main Table ===== */
        .main-table { width: 100%; border-collapse: collapse; margin-top: 0; }
        .main-table td { border: 1px solid #000; padding: 3px 5px; font-size: 9px; }

        .section-header td {
            font-weight: bold;
            text-align: center;
            font-size: 11px;
            padding: 5px 6px;
        }

        .spacer-row td { padding: 3px; }
        .col-header td { font-weight: bold; }
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
                        <strong style="font-size: 22px; color: #1a3c6e;">SMRU.</strong>
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
    <div class="date-row">Date: {{ now()->format('d/m/Y') }}</div>

    {{-- ===== Staff Info ===== --}}
    <table class="info-table">
        <tr>
            <td colspan="2">Staff ID: {{ $employee?->staff_id ?? 'N/A' }}</td>
            <td colspan="3">Name: {{ $employee?->first_name_en }} {{ $employee?->last_name_en }}</td>
            <td colspan="2">Position: {{ $position }}</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">Grant code: {{ $grantCode }}</td>
            <td colspan="3">Grant name: {{ $grantName }}</td>
            <td colspan="1">BL: {{ $budgetLineCode }}</td>
            <td colspan="2">Basic Salary: {{ number_format((float) $payroll->gross_salary, 2) }}</td>
            <td colspan="1">FTE: {{ $ftePercentage }}%</td>
        </tr>
    </table>

    {{-- ===== Main Table ===== --}}
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
            <td class="no-bt no-bb">Retroactive Sal.</td>
            <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->compensation_refund, 2) }}</td>
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

        {{-- Row 5 --}}
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
            <td colspan="3" class="text-center">Grand total: Salary & Benefit</td>
            <td class="text-end">{{ number_format((float) $payroll->total_salary, 2) }}</td>
            <td colspan="2" rowspan="2" style="vertical-align: top;"><strong>Staff Signature:</strong></td>
        </tr>

        {{-- Net Paid --}}
        <tr>
            <td colspan="3" class="text-center">Net Paid</td>
            <td class="text-end">{{ number_format((float) $payroll->net_salary, 2) }}</td>
        </tr>

        {{-- Pay Method --}}
        <tr>
            <td colspan="4">Pay method: {{ $employment?->pay_method ?? 'Bank Transfer' }}</td>
            <td colspan="2"></td>
        </tr>
    </table>
</body>
</html>
