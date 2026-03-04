# Bulk Payslip Generation — Implementation Plan

**Date:** 2026-03-03
**Feature:** Generate a single combined PDF containing all employee payslips for a given organisation (SMRU or BHF) and pay month.

---

## Table of Contents

1. [Feature Overview](#1-feature-overview)
2. [Architecture Decisions](#2-architecture-decisions)
3. [Backend: New Files to Create](#3-backend-new-files-to-create)
4. [Backend: Files to Modify](#4-backend-files-to-modify)
5. [Bulk Blade Template](#5-bulk-blade-template)
6. [Frontend Implementation](#6-frontend-implementation)
7. [Performance Considerations](#7-performance-considerations)
8. [Implementation Sequence](#8-implementation-sequence)

---

## 1. Feature Overview

### User Story

> As an HR admin, I can select an organisation (SMRU or BHF) and a pay month, then click "Generate Payslips" to download a single PDF containing one payslip per page for every employee in that organisation who has a payroll record for that month.

### HTTP Interface

```
POST /api/v1/payrolls/bulk-payslips
Authorization: Bearer {token}
Content-Type: application/json

{
  "organization": "SMRU",
  "pay_period_date": "2025-02"
}
```

**Response:** `Content-Type: application/pdf` stream (inline or attachment). Filename pattern: `payslips-SMRU-2025-02.pdf`.

### What Gets Generated

- One A5 landscape page per payroll record, identical layout to the individual payslip
- Pages separated by CSS page breaks — dompdf renders a true multi-page document
- Header shows the correct organisation (SMRU logo/address or BHF logo/address)
- Ordered by employee staff ID ascending for consistent output

---

## 2. Architecture Decisions

### 2.1 New `PayslipController` (not adding to `PayrollController`)

`PayrollController` already has **16 public methods**, which exceeds the CLAUDE.md guideline of 10–12. Bulk payslip generation is logically a payslip concern, not a payroll CRUD concern.

**Create:** `app/Http/Controllers/Api/V1/PayslipController.php`

Move the existing `generatePayslip()` method there too, and update the route. This produces a clean controller with 2 methods initially and room to grow (e.g., future email delivery, re-print tracking).

### 2.2 Single Bulk Blade Template

Rather than creating `bulk-smru-payslip.blade.php` and `bulk-bhf-payslip.blade.php` separately, use a **single `bulk-payslip.blade.php`** template that receives `$organization` as a variable and renders the correct header for all pages in the loop. Since an entire bulk job is always for one organisation, the header is identical on every page.

### 2.3 Synchronous Generation (MVP)

For MVP, the PDF is generated synchronously on the HTTP request. The PHP time limit is extended for this endpoint (`set_time_limit(300)`). This works well for organisations up to ~150 employees.

For larger batches, a **queue-based upgrade path** is noted in §7 but is not part of the MVP.

### 2.4 Data Architecture

The service method reuses the exact same data-building logic as `generatePayslip()`, extracting it into a private `buildPayslipData(Payroll $payroll)` helper that both the single and bulk methods call. This avoids code duplication.

---

## 3. Backend: New Files to Create

### 3.1 Form Request

**File:** `app/Http/Requests/Payroll/ExportBulkPayslipsPdfRequest.php`

```php
<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportBulkPayslipsPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission:employee_salary.read handled on the route
    }

    public function rules(): array
    {
        return [
            'organization'    => ['required', 'string', Rule::in(['SMRU', 'BHF'])],
            'pay_period_date' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'organization.in'          => 'Organisation must be SMRU or BHF.',
            'pay_period_date.regex'    => 'Pay period date must be in YYYY-MM format (e.g. 2025-02).',
        ];
    }
}
```

### 3.2 PayslipController

**File:** `app/Http/Controllers/Api/V1/PayslipController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\ExportBulkPayslipsPdfRequest;
use App\Models\Payroll;
use App\Services\PayrollService;

class PayslipController extends Controller
{
    public function __construct(private readonly PayrollService $payrollService) {}

    /**
     * Generate a single payslip PDF for one payroll record.
     */
    public function show(Payroll $payroll)
    {
        return $this->payrollService->generatePayslip($payroll);
    }

    /**
     * Generate a combined PDF of all payslips for an organisation and pay month.
     */
    public function exportBulkPdf(ExportBulkPayslipsPdfRequest $request)
    {
        return $this->payrollService->generateBulkPayslips(
            $request->validated('organization'),
            $request->validated('pay_period_date')
        );
    }
}
```

---

## 4. Backend: Files to Modify

### 4.1 Routes

**File:** `routes/api/payroll.php`

Add the bulk payslip route in the **static routes section** (before any `{payroll}` dynamic segments to avoid Laravel trying to bind "bulk-payslips" as a model ID). Also update the single payslip route to point to `PayslipController`.

```php
use App\Http\Controllers\Api\V1\PayslipController;

Route::prefix('payrolls')->group(function () {

    // ── Static read routes (must come before dynamic {payroll} segments) ────
    Route::get('/search',         [PayrollController::class, 'search'])->middleware('permission:employee_salary.read');
    Route::get('/budget-history', [PayrollController::class, 'budgetHistory'])->middleware('permission:employee_salary.read');

    // NEW: bulk payslip PDF export — POST with filter body
    Route::post('/bulk-payslips', [PayslipController::class, 'exportBulkPdf'])
        ->middleware('permission:employee_salary.read');

    // ── Dynamic {payroll} routes ─────────────────────────────────────────────
    Route::get('/{payroll}/payslip', [PayslipController::class, 'show'])    // moved from PayrollController
        ->middleware('permission:employee_salary.read');

    Route::get('/tax-summary/{payroll}', [PayrollController::class, 'taxSummary'])
        ->middleware('permission:employee_salary.read');

    Route::get('/{payroll}', [PayrollController::class, 'show'])
        ->middleware('permission:employee_salary.read');

    // ... rest of existing routes unchanged
});
```

> **Important:** The `POST /bulk-payslips` route must be registered before any `/{payroll}` GET routes to prevent route collision. Verify with `php artisan route:list --name=payroll` after updating.

### 4.2 PayrollService — Add `generateBulkPayslips()` and Extract Helper

**File:** `app/Services/PayrollService.php`

**Step 1:** Extract the data-building logic from `generatePayslip()` into a private helper.

```php
/**
 * Build the view data array for a single payroll record.
 * Shared by generatePayslip() and generateBulkPayslips().
 * Called after relationships are already loaded on the $payroll instance.
 */
private function buildPayslipData(Payroll $payroll): array
{
    $employee   = $payroll->employment?->employee;
    $employment = $payroll->employment;

    // Two-tier fallback: snapshot → live chain → 'N/A'
    $grantAllocation  = $payroll->grantAllocations->first();
    $fundingAllocation = $payroll->employeeFundingAllocation;

    $grantCode      = $grantAllocation?->grant_code        ?? $fundingAllocation?->grantItem?->grant?->code         ?? 'N/A';
    $grantName      = $grantAllocation?->grant_name        ?? $fundingAllocation?->grantItem?->grant?->name         ?? 'N/A';
    $budgetLineCode = $grantAllocation?->budget_line_code  ?? $fundingAllocation?->grantItem?->budgetline_code      ?? 'N/A';
    $grantPosition  = $grantAllocation?->grant_position    ?? $fundingAllocation?->grantItem?->grant_position       ?? 'N/A';
    $fte            = $grantAllocation?->fte               ?? $fundingAllocation?->fte                              ?? 0;

    return [
        'payroll'        => $payroll,
        'employee'       => $employee,
        'employment'     => $employment,
        'department'     => $employment?->department?->name  ?? 'N/A',
        'position'       => $employment?->position?->title   ?? 'N/A',
        'site'           => $employment?->site?->name         ?? 'N/A',
        'grantCode'      => $grantCode,
        'grantName'      => $grantName,
        'budgetLineCode' => $budgetLineCode,
        'grantPosition'  => $grantPosition,
        'fte'            => $fte,
        'ftePercentage'  => round((float) $fte * 100, 2),
        'payPeriod'      => Carbon::parse($payroll->pay_period_date)->format('F Y'),
    ];
}
```

**Step 2:** Refactor `generatePayslip()` to use the helper.

```php
public function generatePayslip(Payroll $payroll)
{
    $payroll->load([
        'employment.employee',
        'employment.department:id,name',
        'employment.position:id,title',
        'employment.site:id,name',
        'employeeFundingAllocation.grantItem.grant',
        'grantAllocations',
    ]);

    $data     = $this->buildPayslipData($payroll);
    $employee = $data['employee'];

    $view = $employee?->organization === 'BHF' ? 'pdf.bhf-payslip' : 'pdf.smru-payslip';
    $pdf  = Pdf::loadView($view, $data);
    $pdf->setPaper('a5', 'landscape');

    $staffId  = $employee?->staff_id ?? 'unknown';
    $grantCode = $data['grantCode'];
    $period   = Carbon::parse($payroll->pay_period_date)->format('Y-m');
    $filename = "payslip-{$staffId}-{$grantCode}-{$period}.pdf";

    return $pdf->stream($filename);
}
```

**Step 3:** Add the new `generateBulkPayslips()` method.

```php
/**
 * Generate a single combined PDF containing all payslips for the given
 * organisation and pay month. One A5-landscape page per payroll record,
 * ordered by employee staff ID.
 *
 * @param  string  $organization   'SMRU' or 'BHF'
 * @param  string  $payPeriodDate  Pay period in 'YYYY-MM' format (e.g. '2025-02')
 */
public function generateBulkPayslips(string $organization, string $payPeriodDate)
{
    // Extend execution time for large batches (dompdf is CPU-intensive per page)
    set_time_limit(300);

    $periodDate = Carbon::createFromFormat('Y-m', $payPeriodDate);

    // ── 1. Fetch all matching payroll records ────────────────────────────────
    $payrolls = Payroll::query()
        ->whereHas('employment.employee', fn ($q) =>
            $q->where('organization', $organization)
        )
        ->whereYear('pay_period_date',  $periodDate->year)
        ->whereMonth('pay_period_date', $periodDate->month)
        ->with([
            'employment.employee',
            'employment.department:id,name',
            'employment.position:id,title',
            'employment.site:id,name',
            'employeeFundingAllocation.grantItem.grant',
            'grantAllocations',
        ])
        // Order by staff ID for a predictable, consistent document
        ->orderBy(
            \App\Models\Employment::select('employee_id')
                ->whereColumn('id', 'payrolls.employment_id')
                ->limit(1)
        )
        ->get();

    if ($payrolls->isEmpty()) {
        abort(404, "No payroll records found for {$organization} in {$periodDate->format('F Y')}.");
    }

    // ── 2. Build the per-payslip data array ──────────────────────────────────
    $payslips = $payrolls->map(fn ($payroll) => $this->buildPayslipData($payroll))->all();

    // ── 3. Render and stream the combined PDF ────────────────────────────────
    $pdf = Pdf::loadView('pdf.bulk-payslip', [
        'payslips'     => $payslips,
        'organization' => $organization,
        'period'       => $periodDate->format('F Y'),  // e.g. "February 2025"
    ]);

    $pdf->setPaper('a5', 'landscape');

    $filename = "payslips-{$organization}-{$periodDate->format('Y-m')}.pdf";

    return $pdf->stream($filename);
}
```

> **Note on ordering:** The `orderBy` subquery above sorts by `employee_id` indirectly. If the Employee model stores `staff_id` as a plain (non-encrypted) string, a more readable alternative is:
> ```php
> ->orderByRaw("(SELECT e.staff_id FROM employees e
>                JOIN employments emp ON emp.employee_id = e.id
>                WHERE emp.id = payrolls.employment_id)")
> ```
> Adjust depending on whether `staff_id` is encrypted in your schema.

---

## 5. Bulk Blade Template

### 5.1 How dompdf Multi-Page Works

dompdf renders a complete HTML document into a PDF. Each CSS `page-break-after: always` on a block element forces a new page. The `@page` rule at the top sets size and margins for all pages uniformly. There are no callbacks or file-merging steps — it's a single HTML string rendered top to bottom.

Structure:
```
<body>
  <div class="payslip-page">  ← Page 1 (Employee A)
  <div class="payslip-page">  ← Page 2 (Employee B)  [page-break-after: always applied on div 1]
  <div class="payslip-page last">  ← Page N (no trailing page break)
</body>
```

### 5.2 New Template File

**File:** `resources/views/pdf/bulk-payslip.blade.php`

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslips – {{ $organization }} – {{ $period }}</title>
    <style>
        @page {
            margin: 0;
            size: A5 landscape;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }

        /*
         * Each payslip is a full-page block.
         * page-break-after is applied via the .break class added to every
         * page except the last, preventing a blank trailing page.
         */
        .payslip-page {
            font-family: 'Times New Roman', Times, serif;
            font-size: 9pt;
            line-height: 1.2;
            color: #000;
            padding: 14pt 28pt 14pt 57pt;
        }
        .payslip-page.break {
            page-break-after: always;
        }

        /* ── Header ── */
        .header-table { width: 100%; margin-bottom: 4pt; }
        .header-table td { border: none; padding: 0; vertical-align: top; }
        .header-left { width: 70%; }
        .header-right { width: 30%; text-align: right; vertical-align: middle !important; }
        .logo-row img { height: 50px; vertical-align: middle; }
        .logo-row .org-text {
            display: inline-block; vertical-align: middle;
            padding-left: 8px; font-size: 9pt; color: #333; line-height: 1.4;
        }
        .payslip-title { font-size: 14pt; font-weight: bold; letter-spacing: 1px; }

        /* ── Date ── */
        .date-row { text-align: right; margin-bottom: 2pt; font-size: 9pt; }

        /* ── Staff Info ── */
        .info-row { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 1pt; }
        .info-row td { padding: 1pt 4pt; font-size: 9pt; border: none; white-space: nowrap; overflow: hidden; }

        /* ── Main Table ── */
        .main-table { width: 100%; border-collapse: collapse; }
        .main-table td { border: 1px solid #000; padding: 2pt 4pt; font-size: 8pt; }
        .section-header td { font-weight: bold; text-align: center; font-size: 10pt; padding: 3pt 4pt; }
        .spacer-row td { padding: 2pt; }
        .col-header td { font-weight: bold; text-align: center; font-size: 9pt; }
        .col-header td.text-end { text-align: right; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        .no-bt { border-top: none !important; }
        .no-bb { border-bottom: none !important; }
        .total-row td { font-weight: bold; }
        .grand-total td { font-weight: bold; background-color: #d9d9d9; }
    </style>
</head>
<body>

@foreach($payslips as $data)
    @php
        $payroll       = $data['payroll'];
        $employee      = $data['employee'];
        $position      = $data['position'];
        $grantCode     = $data['grantCode'];
        $grantName     = $data['grantName'];
        $budgetLineCode = $data['budgetLineCode'];
        $ftePercentage  = $data['ftePercentage'];

        // Determine organisation-specific labels for this page
        $isBHF   = ($organization === 'BHF');
        $orgLabel = $isBHF ? 'BHF' : 'SMRU';
        $logoPath = public_path('images/' . ($isBHF ? 'bhf-logo.png' : 'smru-logo.png'));
    @endphp

    {{-- .break class adds page-break-after on every page except the last --}}
    <div class="payslip-page {{ $loop->last ? '' : 'break' }}">

        {{-- ===== Header ===== --}}
        <table class="header-table">
            <tr>
                <td class="header-left">
                    <div class="logo-row">
                        @if(file_exists($logoPath))
                            <img src="{{ $logoPath }}" alt="{{ $orgLabel }} Logo">
                        @else
                            <strong style="font-size: 24pt; color: #1a3c6e;">{{ $orgLabel }}.</strong>
                        @endif
                        <span class="org-text">
                            @if($isBHF)
                                The Borderland Health Foundation<br>
                                มูลนิธิ เดอะ บอร์เดอร์แลนด์ เฮลท์<br>
                                78/1 Moo 5, Mae Ramat, Tak Province, Thailand 63140
                            @else
                                Shoklo Malaria Research Unit<br>
                                Faculty of Tropical Medicine, Mahidol University<br>
                                78/1 Moo 5, Mae Ramat, Mae Ramat, Tak 63140
                            @endif
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

        {{-- ===== Staff Info Row 1: Staff ID / Name / Position ===== --}}
        <table class="info-row">
            <tr>
                <td style="width: 18%;">Staff ID: {{ $employee?->staff_id ?? 'N/A' }}</td>
                <td style="width: 45%;">Name: {{ $employee?->first_name_en }} {{ $employee?->last_name_en }}</td>
                <td style="width: 37%;">Position: {{ $position }}</td>
            </tr>
        </table>

        {{-- ===== Staff Info Row 2: Grant / BL / Basic Salary / FTE ===== --}}
        <table class="info-row">
            <tr>
                <td style="width: 22%;">Grant code: {{ strlen($grantCode) > 8 ? substr($grantCode, 0, 8) . '...' : $grantCode }}</td>
                <td style="width: 24%;">Grant name: {{ strlen($grantName) > 12 ? substr($grantName, 0, 12) . '...' : $grantName }}</td>
                <td style="width: 12%;">BL:{{ $budgetLineCode }}</td>
                <td style="width: 28%;">Basic Salary: {{ number_format((float) $payroll->gross_salary, 2) }}</td>
                <td style="width: 14%;">FTE: {{ $ftePercentage }}%</td>
            </tr>
        </table>

        {{-- ===== Main Table ===== --}}
        <table class="main-table">

            <tr class="section-header">
                <td colspan="4">INCOME</td>
                <td colspan="2">DEDUCTIONS</td>
            </tr>
            <tr class="spacer-row"><td colspan="6">&nbsp;</td></tr>
            <tr class="col-header">
                <td>Detail</td><td class="text-end">THB</td>
                <td>Details</td><td class="text-end">THB</td>
                <td>Details</td><td class="text-end">THB</td>
            </tr>

            <tr>
                <td class="no-bb">Salary</td>
                <td class="text-end no-bb">{{ number_format((float) $payroll->gross_salary_by_FTE, 2) }}</td>
                <td class="fw-bold no-bb">Other:</td>
                <td class="no-bb"></td>
                <td class="no-bb">Provident fund: staff 7.5%</td>
                <td class="text-end no-bb">{{ number_format((float) $payroll->pvd, 2) }}</td>
            </tr>
            <tr>
                <td class="no-bt no-bb">13-month salary</td>
                <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->thirteen_month_salary, 2) }}</td>
                <td class="no-bt no-bb">Provident fund {{ $orgLabel }} 7.5%</td>
                <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->pvd, 2) }}</td>
                <td class="no-bt no-bb">Social security: staff 5%</td>
                <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->employee_social_security, 2) }}</td>
            </tr>
            <tr>
                <td class="no-bt no-bb">Retroactive Sal.</td>
                <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->retroactive_adjustment, 2) }}</td>
                <td class="no-bt no-bb">Social security: {{ $orgLabel }} 5%</td>
                <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->employer_social_security, 2) }}</td>
                <td class="no-bt no-bb">Health Welfare: staff</td>
                <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->employee_health_welfare, 2) }}</td>
            </tr>
            <tr>
                <td class="no-bt no-bb"></td><td class="no-bt no-bb"></td>
                <td class="no-bt no-bb">Health Welfare: {{ $orgLabel }}</td>
                <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->employer_health_welfare, 2) }}</td>
                <td class="no-bt no-bb">Tax</td>
                <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->tax, 2) }}</td>
            </tr>
            <tr>
                <td class="no-bt no-bb"></td><td class="no-bt no-bb"></td>
                <td class="no-bt no-bb"></td><td class="no-bt no-bb"></td>
                <td class="no-bt no-bb">Provident fund {{ $orgLabel }} 7.5%</td>
                <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->pvd, 2) }}</td>
            </tr>
            <tr>
                <td class="no-bt no-bb"></td><td class="no-bt no-bb"></td>
                <td class="no-bt no-bb"></td><td class="no-bt no-bb"></td>
                <td class="no-bt no-bb">Social security {{ $orgLabel }} 5%</td>
                <td class="text-end no-bt no-bb">{{ number_format((float) $payroll->employer_social_security, 2) }}</td>
            </tr>
            <tr>
                <td class="no-bt no-bb"></td><td class="no-bt no-bb"></td>
                <td class="no-bt no-bb"></td><td class="no-bt no-bb"></td>
                <td class="no-bt">Health Welfare: {{ $orgLabel }}</td>
                <td class="text-end no-bt">{{ number_format((float) $payroll->employer_health_welfare, 2) }}</td>
            </tr>

            <tr class="total-row">
                <td>Total</td>
                <td class="text-end">{{ number_format((float) $payroll->total_income, 2) }}</td>
                <td>Total</td>
                <td class="text-end">{{ number_format((float) $payroll->employer_contribution, 2) }}</td>
                <td>Total</td>
                <td class="text-end">{{ number_format((float) $payroll->total_deduction, 2) }}</td>
            </tr>
            <tr class="grand-total">
                <td colspan="3" class="text-center">Grand total: Salary &amp; Benefit</td>
                <td class="text-end">{{ number_format((float) $payroll->total_salary, 2) }}</td>
                <td colspan="2" rowspan="2" style="vertical-align: top;"><strong>Staff Signature:</strong></td>
            </tr>
            <tr>
                <td colspan="3" class="text-center">Net Paid</td>
                <td class="text-end">{{ number_format((float) $payroll->net_salary, 2) }}</td>
            </tr>
            <tr>
                <td colspan="4">Pay method: {{ $employee?->bank_name ?? 'N/A' }}</td>
                <td colspan="2"></td>
            </tr>

        </table>
    </div>{{-- end .payslip-page --}}

@endforeach

</body>
</html>
```

### 5.3 Key Template Design Notes

| Decision | Reason |
|---|---|
| `page-break-after: always` only on non-last pages (`.break` class) | Prevents a blank trailing page at the end of the PDF |
| `$isBHF` computed once per page in `@php` block | Avoids calling `=== 'BHF'` repeatedly across the template |
| `$logoPath` computed server-side per page | Consistent with how individual templates check `file_exists()` |
| `\Carbon\Carbon::parse($payroll->pay_period_date)->format('d/m/Y')` | Fixes the "Date shows today" bug from the individual templates — bulk shows the actual pay period date |
| `$orgLabel` used inline for employer labels | Single template handles both orgs without conditionals on every label |

---

## 6. Frontend Implementation

### 6.1 API Service Call

**File:** `src/services/payroll.service.js` (or wherever payroll API calls live)

```javascript
/**
 * Download a combined PDF of all payslips for an organisation and pay month.
 * Triggers a file download in the browser.
 *
 * @param {string} organization - 'SMRU' or 'BHF'
 * @param {string} payPeriodDate - 'YYYY-MM' format, e.g. '2025-02'
 */
export async function exportBulkPayslipsPdf(organization, payPeriodDate) {
  const response = await apiClient.post(
    '/payrolls/bulk-payslips',
    { organization, pay_period_date: payPeriodDate },
    { responseType: 'blob' }   // tell axios to return raw binary data
  );

  // Build a download URL from the blob and trigger the browser download
  const url = window.URL.createObjectURL(
    new Blob([response.data], { type: 'application/pdf' })
  );
  const link = document.createElement('a');
  link.href = url;
  link.download = `payslips-${organization}-${payPeriodDate}.pdf`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  window.URL.revokeObjectURL(url);
}
```

### 6.2 Vue Component

**File:** `src/components/payroll/BulkPayslipExport.vue`

This component can be placed on the Payroll list page as a button that opens a small modal.

```vue
<template>
  <!-- Trigger button — placed near existing payroll action buttons -->
  <button
    class="btn btn-outline-secondary"
    @click="modalOpen = true"
  >
    <i class="bi bi-file-earmark-pdf me-1"></i>
    Export Payslips
  </button>

  <!-- Modal -->
  <div v-if="modalOpen" class="modal-overlay" @click.self="close">
    <div class="modal-card">
      <div class="modal-header">
        <h5 class="modal-title">Export Bulk Payslips</h5>
        <button class="btn-close" @click="close" />
      </div>

      <div class="modal-body">
        <!-- Organisation select -->
        <div class="mb-3">
          <label class="form-label fw-semibold">Organisation</label>
          <select v-model="form.organization" class="form-select">
            <option value="">— Select organisation —</option>
            <option value="SMRU">SMRU</option>
            <option value="BHF">BHF</option>
          </select>
          <div v-if="errors.organization" class="text-danger small mt-1">
            {{ errors.organization }}
          </div>
        </div>

        <!-- Month picker -->
        <div class="mb-3">
          <label class="form-label fw-semibold">Pay Month</label>
          <input
            v-model="form.payPeriodDate"
            type="month"
            class="form-control"
            :max="currentMonth"
          />
          <div v-if="errors.payPeriodDate" class="text-danger small mt-1">
            {{ errors.payPeriodDate }}
          </div>
        </div>

        <!-- Info note -->
        <p class="text-muted small mb-0">
          All payslips for <strong>{{ form.organization || '…' }}</strong>
          employees in <strong>{{ formattedPeriod }}</strong> will be
          combined into a single PDF file.
        </p>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" @click="close">Cancel</button>
        <button
          class="btn btn-primary"
          :disabled="loading || !form.organization || !form.payPeriodDate"
          @click="generate"
        >
          <span v-if="loading">
            <span class="spinner-border spinner-border-sm me-1" />
            Generating…
          </span>
          <span v-else>
            <i class="bi bi-download me-1"></i>
            Generate PDF
          </span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { exportBulkPayslipsPdf } from '@/services/payroll.service.js';

const modalOpen = ref(false);
const loading   = ref(false);
const errors    = ref({});

const form = ref({
  organization:  '',
  payPeriodDate: '',
});

// Restrict the month picker to past/current months
const currentMonth = computed(() => {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
});

// Human-readable period label shown in the note
const formattedPeriod = computed(() => {
  if (!form.value.payPeriodDate) return '…';
  const [year, month] = form.value.payPeriodDate.split('-');
  return new Date(year, month - 1).toLocaleString('en-US', { month: 'long', year: 'numeric' });
});

function close() {
  if (loading.value) return; // don't close while downloading
  modalOpen.value = false;
  errors.value    = {};
}

async function generate() {
  errors.value = {};

  // Client-side validation
  if (!form.value.organization) {
    errors.value.organization = 'Please select an organisation.';
    return;
  }
  if (!form.value.payPeriodDate) {
    errors.value.payPeriodDate = 'Please select a pay month.';
    return;
  }

  loading.value = true;
  try {
    await exportBulkPayslipsPdf(form.value.organization, form.value.payPeriodDate);
    close();
  } catch (err) {
    // Handle 404 (no payrolls found) or 422 (validation)
    if (err.response?.status === 404) {
      errors.value.payPeriodDate =
        `No payroll records found for ${form.value.organization} in this month.`;
    } else if (err.response?.status === 422) {
      const serverErrors = err.response.data.errors ?? {};
      errors.value.organization   = serverErrors.organization?.[0];
      errors.value.payPeriodDate  = serverErrors.pay_period_date?.[0];
    } else {
      errors.value.payPeriodDate = 'An unexpected error occurred. Please try again.';
    }
  } finally {
    loading.value = false;
  }
}
</script>
```

### 6.3 Handling the Blob Response on 404/422

When `responseType: 'blob'` is set and the server returns a non-2xx status (e.g., 404 or 422), axios delivers the error body as a Blob. To read the error message, parse it first:

```javascript
// In the catch block of the API service (or in a global axios interceptor):
if (error.response?.data instanceof Blob) {
  const text = await error.response.data.text();
  const json = JSON.parse(text);
  // json.message, json.errors, etc.
}
```

This is already handled in the component via `err.response.data.errors` — make sure your axios interceptor converts Blob errors to JSON for error responses before the component catch block runs, or add the parsing there.

---

## 7. Performance Considerations

### 7.1 Synchronous Limits

dompdf renders one page at roughly 100–300ms (depending on server CPU and page complexity). Expected times:

| Employee Count | Estimated Time | Recommended Approach |
|---|---|---|
| 1–30 | < 10 seconds | Synchronous ✓ |
| 30–100 | 10–30 seconds | Synchronous with `set_time_limit(300)` ✓ |
| 100–300 | 30–90 seconds | Synchronous at risk of Nginx timeout (default 60s) |
| 300+ | > 90 seconds | Queue-based (see §7.2) |

For the Nginx gateway timeout, ensure the upstream timeout is set to at least 300 seconds for this endpoint if large batches are expected.

### 7.2 Queue-Based Upgrade Path (Phase 2)

If generation time becomes a problem, convert the feature to an async pattern:

```
POST /api/v1/payrolls/bulk-payslips
  → Dispatches GenerateBulkPayslipsJob
  → Returns { job_id, status: 'pending' }

GET /api/v1/payrolls/bulk-payslips/{job}/status
  → Returns { status: 'processing' | 'completed' | 'failed', download_url? }

GET /api/v1/payrolls/bulk-payslips/{job}/download
  → Streams the pre-generated PDF from Storage::disk('local')
```

The job:
1. Generates the PDF via `Pdf::loadView()`
2. Saves to `storage/app/private/payslips/{job_id}.pdf`
3. Broadcasts a `BulkPayslipsReady` event via Laravel Reverb
4. Client receives WebSocket notification with download link

The download URL uses a **signed URL** (`URL::temporarySignedRoute()`) with a 1-hour expiry so it cannot be shared or guessed.

This mirrors the existing `ProcessBulkPayroll` queue pattern and would be a natural extension.

### 7.3 Memory Considerations

Loading 200 payroll records with eager-loaded relationships into memory, then building 200 `$data` arrays, then rendering 200 HTML pages through dompdf, can use 256–512MB of PHP memory. Ensure `memory_limit` in `php.ini` is at least `512M` for the worker running this endpoint. For queue-based, set it in the queue worker configuration.

---

## 8. Implementation Sequence

Follow this order to avoid broken states:

**Step 1 — Create the Form Request**
```
app/Http/Requests/Payroll/ExportBulkPayslipsPdfRequest.php
```
No dependencies. Safe to create first.

**Step 2 — Add `buildPayslipData()` helper to `PayrollService`**
Extract from existing `generatePayslip()`. Refactor `generatePayslip()` to use it. Run existing tests to confirm nothing broke.

**Step 3 — Add `generateBulkPayslips()` to `PayrollService`**
Depends on Step 2 (`buildPayslipData()` must exist).

**Step 4 — Create `bulk-payslip.blade.php`**
No PHP dependencies. Can be created in parallel with Steps 2–3.

**Step 5 — Create `PayslipController`**
Move `generatePayslip()` call from `PayrollController`, add `exportBulkPdf()`. Depends on Steps 1–3.

**Step 6 — Update `routes/api/payroll.php`**
- Add `POST /bulk-payslips` route (before `{payroll}` segments)
- Update single payslip route to point to `PayslipController`
- Remove `generatePayslip()` from `PayrollController`

**Step 7 — Frontend: API service function**
Add `exportBulkPayslipsPdf()` to `payroll.service.js`.

**Step 8 — Frontend: Vue component**
Create `BulkPayslipExport.vue`. Wire it into the Payroll list page.

**Step 9 — Manual test**
- Test with an org that has payrolls → PDF downloads correctly with N pages
- Test with an org+month that has no payrolls → 404 response, frontend shows error message
- Test with invalid payload → 422 validation errors shown in the modal

---

## Summary of Files

| Action | File |
|---|---|
| **Create** | `app/Http/Requests/Payroll/ExportBulkPayslipsPdfRequest.php` |
| **Create** | `app/Http/Controllers/Api/V1/PayslipController.php` |
| **Create** | `resources/views/pdf/bulk-payslip.blade.php` |
| **Modify** | `app/Services/PayrollService.php` — extract `buildPayslipData()`, add `generateBulkPayslips()`, refactor `generatePayslip()` |
| **Modify** | `routes/api/payroll.php` — add bulk route, move single payslip to `PayslipController` |
| **Modify** | `app/Http/Controllers/Api/V1/PayrollController.php` — remove `generatePayslip()` |
| **Create** | `src/components/payroll/BulkPayslipExport.vue` (frontend) |
| **Modify** | `src/services/payroll.service.js` (frontend) — add `exportBulkPayslipsPdf()` |
