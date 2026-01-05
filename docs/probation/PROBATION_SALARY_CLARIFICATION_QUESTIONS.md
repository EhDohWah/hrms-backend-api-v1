# Probation Salary and Funding Allocation - Business Logic Clarification Questions

**Date**: 2025-10-29
**Status**: Awaiting Client Responses
**Context**: Employment has two salary tiers (probation_salary and pass_probation_salary). Funding allocations may be created during probation period but need to transition to post-probation salary when probation completes.

---

## Overview

Based on comprehensive analysis of the HRMS employment, funding allocation, and payroll systems, this document outlines critical business logic scenarios that require clarification before implementing probation salary transition functionality.

**Key System Components Analyzed**:
- Employment model with probation_salary (e.g., 8,000) and pass_probation_salary (e.g., 18,000)
- EmployeeFundingAllocation model with allocated_amount field
- PayrollService with ProbationTransitionService for pro-rated calculations
- Existing but unused updateFundingAllocationsAfterProbation method in Employment model

---

## Question Category 1: Funding Allocation Creation Timing and Salary Selection

### Scenario Context
An employee starts employment on June 1, 2025 with:
- probation_salary: 8,000 THB
- pass_probation_salary: 18,000 THB
- pass_probation_date: August 1, 2025 (2-month probation)

HR creates funding allocations for this employee on June 5, 2025 (during probation):
- Grant Funded allocation: 20% FTE
- Organization Funded allocation: 80% FTE

### Questions

**Q1.1**: When funding allocations are created during the probation period, which salary should be used to calculate the allocated_amount?
- Option A: Use probation_salary (8,000) because employee is currently on probation
- Option B: Use pass_probation_salary (18,000) because allocations are meant to be permanent
- Option C: Create allocations without salary calculation, calculate dynamically during payroll generation

**Q1.2**: If allocations are created BEFORE employment starts (pre-employment setup), which salary should be used?
- Should the system check if start_date is in the future and the current date falls within probation period?

**Q1.3**: Can funding allocations be created AFTER probation ends? If yes, should the system automatically use pass_probation_salary at that point?

**Q1.4**: Should there be any UI warning or confirmation when HR creates allocations during probation period, alerting them that salary will change after probation completion?

---

## Question Category 2: Probation Completion Trigger and Allocation Updates

### Scenario Context
Continuing previous scenario - employee completes probation on August 1, 2025. The system needs to determine when and how to update funding allocations.

### Questions

**Q2.1**: What should trigger the update of funding allocations when probation ends?
- Option A: Automatic scheduled job (e.g., daily cron) that checks for probation completions
- Option B: Manual HR action (button/form to "Complete Probation")
- Option C: Automatic update when generating first post-probation payroll
- Option D: Real-time update exactly at midnight on pass_probation_date

**Q2.2**: If using automatic scheduled job approach, what time should it run?
- Should it run at midnight, or during business hours?
- What timezone should be used for the check?

**Q2.3**: Should there be a notification system to alert HR when probation completion happens and allocations are updated?
- Email notification to HR manager?
- In-app notification?
- Activity log entry?

**Q2.4**: What happens if the pass_probation_date is changed AFTER allocations have been created?
- Example: Original pass_probation_date was August 1, but HR extends probation to September 1
- Should allocations remain unchanged or recalculate?

**Q2.5**: Is there a concept of "failed probation" where the employee doesn't pass?
- If yes, what happens to funding allocations in this case?

---

## Question Category 3: Payroll Generation During Transition Month

### Scenario Context
Employee completes probation on August 15, 2025 (mid-month). Payroll for August needs to be generated.

**Payroll Period**: August 1-31, 2025
**Probation Period**: August 1-14, 2025 (14 days at probation_salary)
**Post-Probation Period**: August 15-31, 2025 (17 days at pass_probation_salary)

### Questions

**Q3.1**: For the transition month payroll, should the system generate:
- Option A: One payroll record with pro-rated calculation (14 days × probation_salary + 17 days × pass_probation_salary)
- Option B: Two separate payroll records (one for probation period, one for post-probation period)
- Option C: One payroll record using only the salary that applies on the last day of the month

**Q3.2**: If using pro-rated calculation, which method is preferred:
- Option A: 30-day standardized month (as currently implemented in ProbationTransitionService)
  - August probation: (14 days / 30 days) × 8,000 = 3,733.33
  - August post-probation: (16 days / 30 days) × 18,000 = 9,600.00
- Option B: Actual calendar days per month
  - August probation: (14 days / 31 days) × 8,000 = 3,612.90
  - August post-probation: (17 days / 31 days) × 18,000 = 9,870.97

**Q3.3**: Does the pro-rated calculation apply to ALL salary components or only base salary?
- Should thirteen_month_salary also be pro-rated?
- Should compensation_refund be pro-rated?
- Should benefits (PVD, saving fund, health welfare) be pro-rated?

**Q3.4**: If funding allocations still have old allocated_amount (based on probation_salary) during transition month, should:
- Payroll calculation ignore allocated_amount and calculate fresh from employment salary?
- Payroll calculation pro-rate using both old and new allocated_amount?
- System require allocations to be updated before allowing payroll generation?

---

## Question Category 4: Funding Allocation Update Strategy

### Scenario Context
System needs to update existing funding allocations when probation completes.

**Before Probation Completion**:
- Grant Funded (20% FTE): allocated_amount = 1,600 (20% of 8,000)
- Org Funded (80% FTE): allocated_amount = 6,400 (80% of 8,000)
- Total: 8,000

**After Probation Completion**:
- Grant Funded (20% FTE): allocated_amount = 3,600 (20% of 18,000)
- Org Funded (80% FTE): allocated_amount = 14,400 (80% of 18,000)
- Total: 18,000

### Questions

**Q4.1**: Should the system automatically update allocated_amount for all active allocations when probation completes?
- Or should HR manually review and approve each update?

**Q4.2**: What if an allocation has a custom allocated_amount that doesn't match the FTE percentage?
- Example: Grant Funded 20% FTE but allocated_amount is 2,000 instead of 1,600
- Should the system respect the custom amount or recalculate based on FTE?
- How can the system distinguish between "custom amount" vs "calculated amount"?

**Q4.3**: Should there be an audit trail for allocation updates?
- Record old allocated_amount, new allocated_amount, reason for change, who/what triggered it?
- Should this be in a separate history table or in a general activity log?

**Q4.4**: What if an allocation has ended (end_date is before pass_probation_date)?
- Example: Temporary allocation for June-July, but probation ends in August
- Should ended allocations be updated retroactively or left as-is?

**Q4.5**: Can funding allocations be edited manually during probation period?
- If yes, should those manual changes be preserved or overwritten when probation completes?

---

## Question Category 5: Historical Payroll Handling

### Scenario Context
Employee completes probation on August 15, 2025. Payrolls have already been generated for June and July using probation_salary.

**Existing Payrolls**:
- June 2025: gross_salary = 8,000 (probation_salary)
- July 2025: gross_salary = 8,000 (probation_salary)

### Questions

**Q5.1**: Should past payroll records (June, July) remain unchanged?
- Option A: Yes, they were correct at the time of generation
- Option B: No, they should be retroactively corrected if probation ended earlier than expected

**Q5.2**: If pass_probation_date is backdated (e.g., changed from August 15 to July 15 after July payroll was already generated), should:
- July payroll be regenerated/adjusted?
- System show a warning that historical payrolls may be incorrect?
- System prevent backdating if payrolls exist?

**Q5.3**: Should there be a report or dashboard showing:
- Employees currently on probation with their projected pass_probation_date?
- Employees whose probation will complete in the current/next month?
- Employees whose allocations need review due to probation completion?

**Q5.4**: Can payrolls be edited after generation? If yes, should probation status affect edit permissions?

---

## Question Category 6: Multiple Allocation Scenarios

### Scenario Context
Employee has complex funding allocation setup that changes during probation period.

### Questions

**Q6.1**: Can new funding allocations be added AFTER employment starts but DURING probation?
- Example: Employee starts June 1, allocation A created June 1, allocation B added July 1
- Should allocation B use probation_salary (because still on probation) or pass_probation_salary?

**Q6.2**: Can funding allocation FTE percentages be changed during probation?
- Example: June allocation was 50% grant + 50% org, changed to 20% grant + 80% org in July
- How should this interact with probation completion?

**Q6.3**: What if funding allocations have different start_date and end_date ranges?
- Allocation A: June 1 - July 31 (expires before probation ends)
- Allocation B: August 1 - December 31 (starts after probation ends)
- Should Allocation A use probation_salary and Allocation B use pass_probation_salary?

**Q6.4**: Can an employee have allocations from multiple grants simultaneously?
- If yes, should all be updated uniformly when probation completes?

**Q6.5**: What validation should exist to ensure allocation percentages sum to 100% (or employee's actual FTE)?

---

## Question Category 7: Edge Cases and Special Scenarios

### Questions

**Q7.1**: What if probation_salary is HIGHER than pass_probation_salary?
- Is this scenario possible in your business context?
- Example: Trial position at higher pay, then moved to lower permanent role

**Q7.2**: What if probation_salary or pass_probation_salary is NULL or zero?
- Should the system use the other salary?
- Should the system prevent allocation creation?
- Should the system show an error?

**Q7.3**: What if pass_probation_date is NULL (no probation period)?
- Should the system always use pass_probation_salary?
- Is this scenario for contract or executive employees?

**Q7.4**: What if an employee has multiple employment records (rehired scenario)?
- Should each employment's probation be handled independently?
- Can funding allocations span multiple employment records?

**Q7.5**: Can employment start_date be changed after allocations are created?
- If start_date moves forward, what happens to allocations dated before the new start_date?
- If start_date moves backward, do allocations need to be updated?

**Q7.6**: What happens if employment is terminated during probation?
- Should allocations be ended automatically?
- Should final payroll use pro-rated calculation?

**Q7.7**: Are there any government regulations or compliance requirements regarding:
- Minimum probation salary vs permanent salary ratios?
- Probation period length limits?
- Pro-rated salary calculations?

---

## Question Category 8: Frontend UI/UX Requirements

### Questions

**Q8.1**: On the funding allocation creation/edit form, should the system:
- Show both probation_salary and pass_probation_salary with probation status?
- Show a calculated preview of allocated_amount for both scenarios?
- Display a warning if allocations are created during probation?

**Q8.2**: On the payroll generation page, should the system:
- Show probation status for each employee?
- Show which employees are in transition month?
- Show a breakdown of pro-rated calculations for transition month payrolls?

**Q8.3**: Should there be a dedicated "Probation Management" section where HR can:
- View all employees currently on probation?
- View upcoming probation completions?
- Manually trigger probation completion for specific employees?
- View history of probation completions and allocation updates?

**Q8.4**: On employee detail page, should probation information be prominently displayed?
- Days remaining in probation?
- Current applicable salary?
- Visual indicator (badge/color) for probation status?

**Q8.5**: Should bulk operations be supported?
- Bulk update allocations for multiple employees completing probation on same date?
- Bulk payroll generation for transition month employees?

**Q8.6**: What permissions/roles should be required for:
- Creating/editing allocations during probation?
- Manually triggering probation completion?
- Viewing probation salary information?
- Editing pass_probation_date?

---

## Implementation Priority and Dependencies

Once the above questions are answered, the implementation should follow this order:

### Phase 1: Core Business Logic
- Define allocation creation salary selection logic
- Implement probation completion trigger mechanism
- Update Employment model methods

### Phase 2: Payroll Calculation
- Implement/verify pro-rated calculation for transition month
- Update PayrollService to handle probation scenarios
- Test ProbationTransitionService integration

### Phase 3: Allocation Updates
- Implement automatic allocation update on probation completion
- Add audit trail for allocation changes
- Handle edge cases (ended allocations, custom amounts, etc.)

### Phase 4: Frontend Updates
- Add probation status indicators to UI
- Add warnings on allocation creation during probation
- Add transition month breakdown on payroll preview
- Add probation management dashboard (if required)

### Phase 5: Testing and Validation
- Unit tests for probation logic
- Integration tests for payroll generation
- Edge case testing
- UAT with HR staff

---

## Additional Information Needed

Please provide answers to the questions above, organized by category. For each question, please specify:

1. **Your preferred approach** (if options are provided)
2. **Business justification** (why this approach fits your organization)
3. **Any additional context** that might affect the implementation

If any scenario described doesn't match your actual business process, please clarify the correct process.

---

**Prepared By**: Development Team
**Requires Review By**: HR Management, Finance Department, System Administrator
**Next Steps**: Await responses and schedule clarification meeting if needed
