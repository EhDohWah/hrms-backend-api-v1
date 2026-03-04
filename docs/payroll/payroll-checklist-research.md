# Payroll Test Checklist vs Business Logic — Cross-Reference Analysis

> **Checklist**: `PAYROLL-TESTLIST.docx` (Version 1.1, March 2026)
> **Business Logic**: `PAYROLL-BUSINESS-LOGIC.docx` (Version 2.0, February 2026), documented in `research.md`
> **Analysis date**: 2026-03-03

---

## Summary

| Category | Count |
|----------|-------|
| Test cases in checklist | 100 (across 17 sections) — 97 original + 3 added |
| Fully consistent with business logic | 74 (+8 fixed issues now consistent) |
| Issues found (incorrect/vague/contradicts) | 8 (16 originally, 8 fixed in testlist) |
| Not covered in business logic (test is extra) | 7 |
| Missing from checklist (business logic has it, checklist doesn't) | 5 (8 originally, 3 added to testlist) |

> **Update (2026-03-03):** 8 issues fixed in PAYROLL-TESTLIST.docx (3.2, 4.1, 4.4, 9.2, 10.2, 10.5, 16.3, 17.3). 3 missing tests added (4.7, 11.5, 12.6). See strikethrough entries in tables below.

> **Note:** The testlist header (P3) states: *"Monthly working days is 30 Days for total monthly working days."* This global definition resolves the proration formula wording in tests 1.1 and 1.2 (previously flagged as issues).

---

## Section-by-Section Analysis

### Section 1: Mid-Month Start & Prorated Salary

#### 1.1 — Start Date On or Before 15th
**CONSISTENT (with header note)**

The test says:
> "Proration formula: (remaining working days from start date to end of month) ÷ **(total working days in month)** × monthly salary"

The testlist document header (P3) explicitly states: **"NOTES: Monthly working days is 30 Days for total monthly working days."** This defines "total working days in month" = 30, matching the business logic formula `daily_rate = monthly_salary / 30`.

**Note:** The formula is correct given the header definition. No change needed, though inline clarity (e.g., `÷ 30`) would improve readability.

#### 1.2 — Start Date 16th or After
**CONSISTENT (with header note)**

The test says:
> "Retro portion formula: (working days from Jan 16 to Jan 31) ÷ **(total working days in Jan)** × monthly salary"

Same as 1.1 — the testlist header note defines "total working days" = 30 days. The formula is correct given this definition. January has 31 calendar days, but the calculation always uses 30 per the header note.

**Note:** No change needed, but inline `÷ 30` would improve readability.

#### 1.3 — Mid-month start with multiple grants
**CONSISTENT.** Matches research: proration on full salary first, then multiply by FTE per allocation.

---

### Section 2: Payroll Adjustment (Post-Payroll Data Corrections)

#### 2.1 — Why this field still exists
**CONSISTENT.** Good explanatory test. The checklist adds valuable context about when adjustments are needed (wrong salary, backdated government increases, wrong grant allocation). This enhances the business logic.

#### 2.2 — Positive adjustment (underpaid)
**CONSISTENT.** Includes tax recalculation check with correct tax eligibility conditions ("if employee has tax_number + bank").

#### 2.3 — Negative adjustment (overpaid)
**PARTIALLY ALIGNED.**

The test asks: "If deduction > current month salary, verify system behavior (partial? carry forward?)"

The business logic (Step 13) says: "Net salary must not go below zero. Flag for HR review." But it does NOT address whether excess negative adjustments carry forward to the next month. This remains an open business rule.

#### 2.4 — Adjustment field empty/zero
**CONSISTENT.** Reasonable UX test. Payslip should not show a "0" adjustment row.

---

### Section 3: Normal Monthly Payroll

#### 3.1 — Import existing employees from Excel
**PLACEMENT ISSUE.** This is a data migration test (business logic Step 12), not a "Normal Monthly Payroll" test. Should be in its own section or under a data import section.

#### 3.2 — Full month payroll
**FIXED IN TESTLIST**

~~**ISSUE: Undefined term** — "standard allowances" undefined in business logic.~~

Updated in testlist: replaced "standard allowances" with actual income components (gross salary by FTE, 13th month salary, retroactive adjustment, salary bonus) and standard deductions (SSF, PVD, Health Welfare, Tax).

#### 3.3 — Multiple funding sources
**CONSISTENT.** Matches research Step 7.1.

#### 3.4 — Leave without pay deduction
**CONSISTENT.** Formula matches research: `daily_rate = monthly salary ÷ 30 (fixed 30-day month basis)`.

#### 3.5 — Local non-ID staff cash payment
**PARTIALLY ALIGNED.**

The test says: "These employees are selected as a **'Cash Cheque'**"

The business logic does not use the term "Cash Cheque." It only says employees without `bank_account_number` are not eligible for tax withholding. The "Cash Cheque" payment method is a UI/operational detail not in the business logic document.

---

### Section 4: Thai Tax Calculation

#### 4.1 — Employee HAS tax_number + bank transfer
**FIXED IN TESTLIST**

~~**ISSUE: Incorrect formula description** — "annual tax ÷ 12" was wrong; ACM divides by remaining months.~~

Updated in testlist: replaced with full ACM step-by-step (estimate annual income, apply PND1 brackets, monthly withholding = (est. annual tax − YTD withheld) ÷ remaining months). Also added SSF/PVD pre-tax deduction verification and remaining months definition (Jan=12, Apr=9, Dec=1).

#### 4.2 — Cash payment = no tax
**CONSISTENT.** Matches research: tax requires BOTH `tax_number` AND `bank_account_number`.

#### 4.3 — No tax_number = no tax
**CONSISTENT.**

#### 4.4 — Tax with allowances
**FIXED IN TESTLIST**

~~**ISSUE: Undefined terms** — "taxable allowances" and "non-taxable allowances" not defined in business logic.~~

Updated in testlist: replaced with actual PIT deduction items (personal expenses 50%/max 100K, personal allowance 60K, PVD/Saving Fund, spouse 60K, children 30K each, SSF max 10,500/year). Also added SSF/PVD pre-tax deduction check and retroactive income inclusion.

#### 4.5 — Tax after salary change mid-year
**CONSISTENT.** Correctly describes ACM self-correction behavior.

#### 4.6 — Tax summary validation
**CONSISTENT.** PND1 (monthly filing) reference is correct.

---

### Section 5: Probation & Pass Probation

#### 5.1 — During probation, probation salary
**CONSISTENT.**

#### 5.2 — Pass probation effective 1st
**CONSISTENT.** Matches research Case A.

#### 5.3 — Pass probation mid-month
**CONSISTENT.** The split formula matches research: `probation_days = pass_probation_date − 1`.

#### 5.4 — Payroll record shows both salary tiers
**CONSISTENT.** Reasonable UI verification test.

#### 5.5 — Tax during probation transition
**CONSISTENT.** ACM uses the combined total for the split month.

---

### Section 6: 13th Month Salary Calculation

#### 6.1 — Standard 13th month
**CONSISTENT.**

#### 6.2 — Salary changed mid-year
**CONSISTENT.** Formula `(15,000 × 6 + 18,000 × 6) ÷ 12 = 16,500` is correct.

#### 6.3 — Probation passed in Jan
**CONSISTENT.** Correctly requires actual split amount for January, not just one rate.

#### 6.4 — Probation passed in Feb
**CONSISTENT.**

#### 6.5 — Employee started mid-year
**CONSISTENT.** Correctly identifies Option B (÷ 12) as the correct rule. The "HR to confirm" note in the test is already resolved by the business logic.

#### 6.6 — Employee with retro adjustments
**RESOLVED.** The test includes user commentary: "Yes but this is rare situation where the payroll services won't make arithmetic calculation only the human error." The business logic left this as `[HR to confirm]`, but the test has resolved the question — retro adjustments ARE included.

#### 6.7 — Transfer SMRU → BHF
**CONSISTENT.** Updated with per-allocation calculation reference.

#### 6.8 — Tax on 13th month
**CONSISTENT.** Matches year-end reconciliation in business logic.

#### 6.9–6.11 — Multi-allocation cases
**CONSISTENT.** These were added based on the business logic directly. All formulas and worked examples match.

---

### Section 7: Personnel Action Changes

#### 7.1 — Salary increase mid-month
**NOT IN BUSINESS LOGIC.**

The test assumes mid-month salary splits from personnel actions (similar to probation splits). The business logic **only** describes mid-month splits for probation transitions (Step 2.2). It does NOT define how mid-month salary changes from personnel actions (promotions, salary grade updates) should split the month.

**Status:** The test describes a feature beyond what the business logic specifies. The business logic would need to add a section for personnel-action salary change splits, or the test should note this is NOT currently defined.

#### 7.2 — Position change with salary grade
**ISSUE: Undefined terms**

The test mentions "salary grade" and "allowances change based on new position." The business logic uses raw salary values (`probation_salary`, `pass_probation_salary`), not salary grades. There is no position-linked allowance system defined in the business logic.

#### 7.3 — Multiple actions in same month
**NOT IN BUSINESS LOGIC.** The business logic does not address concurrent personnel actions in a single pay period.

---

### Section 8: Organization Transfer (SMRU ↔ BHF)

#### 8.1 — Clean month boundary
**CONSISTENT.** Matches research Step 7.4.

#### 8.2 — Transfer with salary change
**PARTIALLY ALIGNED.** The business logic mentions transfers but does not explicitly discuss salary changes accompanying transfers. The test is a reasonable extension.

#### 8.3 — Mid-month transfer prorated split
**CONSISTENT.** Business logic says "Days 1–14 charged to old org, Days 15–30 charged to new org."

#### 8.4 — Leave balance on transfer
**CONSISTENT.** Business logic says "Leave balance carries over."

#### 8.5 — Tax continuity across orgs
**CONSISTENT.** Business logic says "YTD tax withholding carries over — annual tax is continuous."

---

### Section 9: Pay Slip Generation

#### 9.1 — Format & layout (A5, landscape)
**NOT IN BUSINESS LOGIC TEXT.** The business logic doesn't specify paper size or orientation. This comes from the payslip template implementation, not the business rules document. The test is valid but references a different source.

#### 9.2 — Content accuracy
**FIXED IN TESTLIST**

~~**ISSUE: Undefined items** — "OT" and "loans" not in payslip contents per business logic.~~

Updated in testlist: replaced with actual payslip fields from business logic. Income: Gross Salary by FTE, 13th Month Salary, 13th Month Accrued (manually added by HR), Retroactive Adjustment, Salary Bonus, Total Income. Deductions: PVD/Saving Fund, SSF, Health Welfare, Tax, Total Deductions. Added net pay verification and pay period dates check.

#### 9.3 — Bulk auto-generation
**ADDITIONAL DETAIL.** The business logic describes bulk payroll calculation (Step 9) but not bulk payslip PDF generation. The test adds the requirement for batch PDF generation per organization, which is a valid feature not explicitly in the business logic.

#### 9.4 — Payslip for special scenarios
**CONSISTENT.**

---

### Section 10: Edge Cases & Data Validation

#### 10.1 — Duplicate payroll prevention
**CONSISTENT.** Matches Step 13.

#### 10.2 — Decimal rounding
**FIXED IN TESTLIST**

~~**ISSUE: Partially correct** — blanket "2 decimal places" was wrong for salary (correct for tax only).~~

Updated in testlist: differentiated rounding by field type. Salary/deductions/13th month → round to nearest whole baht (integer); tax → 2 decimal places. Added worked examples (prorated salary = 5,328; 13th month = 8,583; monthly tax = 52.08) and reconciliation check.

#### 10.3 — Negative net pay prevention
**CONSISTENT.**

#### 10.4 — Future-dated employee excluded
**CONSISTENT.**

#### 10.5 — Termination mid-month
**FIXED IN TESTLIST**

~~**CORRECTED** — "unused leave payout" and "severance" not part of payroll business logic.~~

Updated in testlist: removed leave payout/severance. Now tests only mid-month proration (`salary ÷ 30 × days worked`) and exclusion from subsequent months' payroll.

#### 10.6 — Empty payroll run
**CONSISTENT.**

#### 10.7 — Employee data validation on import
**CONSISTENT** with Step 12 (Data Migration).

---

### Section 11: Social Security Calculation

#### 11.1 — Salary below cap (5% of 10,000 = 500)
**CONSISTENT.**

#### 11.2 — Salary at/above cap (capped at 875)
**CONSISTENT.** Uses correct 17,500 threshold.

#### 11.3 — Multi-grant FTE split (875 × FTE)
**CONSISTENT.** Example: 60%+40%, Grant A = 525, Grant B = 350, total = 875.

#### 11.4 — Starts from start_date (during probation)
**CONSISTENT.** Research: "SSF applies from day one, unlike PVD."

---

### Section 12: PVD / Saving Fund (7.5%)

#### 12.1 — During probation (PVD = 0)
**CONSISTENT.** Research: "Only after probation, during probation NO PVD."

#### 12.2 — After probation passes (PVD = 7.5%)
**CONSISTENT.**

#### 12.3 — Checkbox disabled = no PVD
**CONSISTENT.** Research: "Toggled per employee, HR enables via checkbox."

#### 12.4 — Thai = PVD label, Non-Thai = Saving Fund label
**CONSISTENT.**

#### 12.5 — Multi-grant FTE split
**CONSISTENT.** Example: 20,000 × 0.70 × 7.5% = 1,050 is correct.

---

### Section 13: Health Welfare

#### 13.1–13.3 — Non-Thai tiers
**CONSISTENT.** All three tiers match business logic tables exactly.

#### 13.4 — Thai employee, employer = 0
**CONSISTENT.**

#### 13.5 — HW starts from start_date (during probation)
**CONSISTENT.** Research: "HW is calculated from the start date, including during probation."

#### 13.6 — Local Non-ID staff, no HW
**POTENTIAL INCONSISTENCY.**

The test says: "HW = 0 (no health welfare for Local Non-ID)"

The business logic says: "Expat / Local staff: Health Welfare **varies by individual** (some have it, some don't)."

The business logic says "varies by individual" which implies it's NOT always zero — some local staff might have HW. The test assumes it's always zero for Local Non-ID.

**Recommendation:** Clarify with HR whether Local Non-ID staff ALWAYS have HW = 0 or if it truly varies by individual.

---

### Section 14: Employer Contributions

#### 14.1 — Employer SSF = Employee SSF
**CONSISTENT.**

#### 14.2 — Employer PVD = Employee PVD (7.5%)
**CONSISTENT.**

#### 14.3 — Employer HW tiers (Non-Thai only)
**CONSISTENT.**

#### 14.4 — Total salary cost formula
**CONSISTENT.** Formula `total_salary_cost = net_salary + total_deductions + employer_cost` matches business logic Step 6.5.

---

### Section 15: Year-End Tax Adjustment

#### 15.1 — December reconciliation
**CONSISTENT.** Correctly describes actual-vs-projected reconciliation.

#### 15.2 — Under-withheld, extra December deduction
**CONSISTENT.**

#### 15.3 — Over-withheld, PND.91 refund
**CONSISTENT.** Correctly references PND.91 filing by March 31.

---

### Section 16: Annual Salary Increase

#### 16.1 — 365+ days, 1% increase
**CONSISTENT.** Formula `new_salary = old_salary × 1.01` matches.

#### 16.2 — < 365 days, no increase
**CONSISTENT.**

#### 16.3 — Start date after 15th, does start month count?
**FIXED IN TESTLIST**

~~**ALREADY RESOLVED IN BUSINESS LOGIC** — test had "HR to confirm" but business logic already answered.~~

Updated in testlist: replaced "HR to confirm" with definitive answer from business logic. Start date must be 1st–15th for month to count. Jan 16 start → January does NOT count → first eligible increase = February of following year.

---

### Section 17: Rounding Rules

#### 17.1 — PVD rounding across grants
**CONSISTENT.** Research acknowledges rounding method needs confirmation.

#### 17.2 — SSF rounding across grants
**CONSISTENT.**

#### 17.3 — Prorated salary rounding
**FIXED IN TESTLIST**

~~**ISSUE: Wrong rounding** — "2 decimal places" was wrong for salary (correct for tax only).~~

Updated in testlist: salary → nearest whole baht (integer), with worked example `round(10,000/30) × 16 = 5,328`. Tax → 2 decimal places (cross-reference test 10.2). Added reconciliation check for totals after rounding.

---

## Issues Summary Table

| # | Test | Issue | Severity | Fix Required |
|---|------|-------|----------|-------------|
| ~~1~~ | ~~1.1~~ | ~~Formula says "÷ total working days" instead of "÷ 30"~~ | ~~High~~ | **Resolved:** Testlist header note defines "total working days = 30 days" |
| ~~2~~ | ~~1.2~~ | ~~Same vague formula as 1.1~~ | ~~High~~ | **Resolved:** Same — covered by header note |
| 3 | 3.1 | Placed in wrong section (data import, not monthly payroll) | Low | Move to data import section |
| ~~4~~ | ~~3.2~~ | ~~"Standard allowances" undefined in business logic~~ | ~~Medium~~ | **FIXED IN TESTLIST** — replaced with actual income components |
| 5 | 3.5 | "Cash Cheque" term not in business logic | Low | Acceptable operational detail |
| ~~6~~ | ~~4.1~~ | ~~Formula says "annual tax ÷ 12" — should be ACM formula~~ | ~~**High**~~ | **FIXED IN TESTLIST** — replaced with full ACM formula |
| ~~7~~ | ~~4.4~~ | ~~"Taxable allowances" undefined~~ | ~~Medium~~ | **FIXED IN TESTLIST** — replaced with actual PIT deduction items |
| 8 | 7.1 | Mid-month salary change from PA not defined in business logic | Medium | Add business rule or note as unspecified |
| 9 | 7.2 | "Salary grade" and "position-linked allowances" not in business logic | Medium | Align terminology |
| 10 | 7.3 | Multiple actions in same month not in business logic | Medium | Note as requiring business rule definition |
| ~~11~~ | ~~9.2~~ | ~~"OT" and "loans" not in payslip contents~~ | ~~**High**~~ | **FIXED IN TESTLIST** — replaced with actual payslip fields |
| ~~12~~ | ~~10.2~~ | ~~"2 decimal places" only correct for tax~~ | ~~Medium~~ | **FIXED IN TESTLIST** — differentiated rounding by field type |
| ~~13~~ | ~~10.5~~ | ~~"Leave payout" and "severance" not in payroll business logic~~ | ~~Medium~~ | **FIXED IN TESTLIST** — removed; now tests mid-month proration only |
| 14 | 13.6 | "HW = 0 for Local Non-ID" vs "varies by individual" in business logic | Medium | Clarify with HR |
| ~~15~~ | ~~16.3~~ | ~~"HR to confirm" already answered in business logic~~ | ~~Low~~ | **FIXED IN TESTLIST** — added definitive answer |
| ~~16~~ | ~~17.3~~ | ~~"2 decimal places" wrong for salary proration~~ | ~~Medium~~ | **FIXED IN TESTLIST** — salary → whole baht; tax → 2 decimals |
| 17 | 2.3 | Carry-forward for excess negative adjustments undefined | Medium | Define business rule |
| 18 | 9.1 | A5/landscape not in business logic text | Low | Acceptable (from template, not rules) |

---

## Missing from Checklist (Present in Business Logic)

These are rules explicitly defined in the business logic but have no corresponding test case:

| # | Business Logic Rule | Where in Business Logic | Suggested Test |
|---|-------------------|----------------------|---------------|
| ~~1~~ | ~~**Tax deducted from ONE grant only**~~ | ~~Step 3.4, Step 7.1~~ | **ADDED TO TESTLIST** as test 4.7 — multi-grant employee, verify tax on one record only |
| 2 | **ACM projection uses full-month salary** (not partial/split amount) | Step 3.4, ACM Projection Rule | Add test: mid-month start, verify projected income uses full-month salary |
| 3 | **Funding allocation update on probation pass** (transactional) | Step 2.4 | Add test: pass probation, verify allocation records updated with new salary |
| 4 | **Inter-organization advances** | Step 7.2 | Add test: SMRU employee funded by BHF grant, verify advance created |
| 5 | **Hub grants** (S0031 for SMRU, S22001 for BHF) | Step 7.3 | Add test: verify hub grant codes exist and are used correctly |
| ~~6~~ | ~~**Expat/Local staff — no PVD/Saving Fund**~~ | ~~Step 3.1~~ | **ADDED TO TESTLIST** as test 12.6 — Expat/Local staff, PVD = 0 regardless of checkbox |
| 7 | **Payslip notes replace payment method** | Step 10.2 | Add test: verify payslip shows auto-generated notes, not payment method |
| ~~8~~ | ~~**Social Security min salary threshold (THB 1,650)**~~ | ~~Step 3.2~~ | **ADDED TO TESTLIST** as test 11.5 — salary below THB 1,650, verify SSF handling |

---

## Test Cases Beyond Business Logic (Checklist Has, Business Logic Doesn't)

These tests reference features or behaviors not defined in the business logic document. They may be valid operational requirements, but they need corresponding business rules added to the logic document.

| Test | What It Tests | Status |
|------|-------------|--------|
| 2.1 | Why adjustment field exists (explanatory context) | Good addition — keep |
| 3.1 | Excel employee import validation | Valid but belongs in data import section |
| 3.5 | "Cash Cheque" payment categorization | Operational detail — acceptable |
| 9.1 | A5 landscape payslip format | Template detail — acceptable |
| 9.3 | Bulk payslip PDF generation per org | Feature requirement — should be added to business logic |
| ~~10.5~~ | ~~Leave payout and severance on termination~~ | **FIXED** — removed from testlist; now tests mid-month proration only |
| 10.7 | Import data validation (invalid rows) | Valid extension of Step 12 |

---

## Recommendations

### High Priority Fixes (will cause incorrect testing if not fixed)

1. ~~**Fix the proration formula in 1.1 and 1.2**~~ — **RESOLVED.** The testlist header note (P3) defines "total working days = 30 Days." The formula is correct given this definition. Optional: add inline `÷ 30` for readability.

2. ~~**Fix the tax formula in 4.1**~~ — **DONE.** Replaced with full ACM formula in testlist.

3. ~~**Fix payslip content in 9.2**~~ — **DONE.** Replaced OT/loans with actual payslip fields in testlist.

4. ~~**Fix rounding specification in 10.2 and 17.3**~~ — **DONE.** Differentiated rounding by field type in testlist.

### Medium Priority

5. **Add missing test cases** — **PARTIALLY DONE.** Added: tax on one grant (4.7), expat PVD exclusion (12.6), SSF min salary (11.5). Still missing: ACM projection rule, funding allocation update on probation pass, inter-org advances.

6. ~~**Resolve open terminology**~~ — **DONE.** "Standard allowances" replaced in 3.2, "taxable/non-taxable allowances" replaced in 4.4. "Salary grade" in 7.2 remains (not in testlist scope — refers to a feature beyond business logic).

7. ~~**Update 10.5**~~ — **DONE.** Removed leave payout/severance in testlist.

8. ~~**Update 16.3**~~ — **DONE.** Added definitive answer in testlist.

### Low Priority

9. **Move test 3.1** (Excel employee import) to a data import section.

10. **Clarify 13.6** (Local Non-ID HW) with HR — business logic says "varies by individual" but test assumes always zero.
