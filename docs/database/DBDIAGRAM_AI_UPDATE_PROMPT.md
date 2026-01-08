# DBDiagram AI Update Prompt

## How to Use This Document

1. Copy the prompt below
2. Go to your dbdiagram.io diagram
3. Use the AI assistant (if available in your plan)
4. Paste the prompt to update your diagram

---

## Prompt for DBDiagram AI Assistant

```
I need you to UPDATE my existing HRMS database schema with the following missing tables and corrections. Please MERGE these changes with my current diagram.

=== CRITICAL CORRECTIONS TO EXISTING TABLES ===

1. RENAME these tables (update all references):
   - "subsidiaries" → "organizations"
   - "subsidiary_hub_funds" → "organization_hub_funds"  
   - "inter_subsidiary_advances" → "inter_organization_advances"

2. UPDATE "employments" table - ADD this foreign key:
   - site_id int [ref: > sites.id]

3. UPDATE "leave_requests" table - REMOVE this column:
   - leave_type_id (this moved to leave_request_items table)

4. REMOVE this table (doesn't exist in actual implementation):
   - employee_funding_allocations_history

=== NEW TABLES TO ADD ===

Add these 26 missing tables with their complete schemas:

--- SYSTEM INFRASTRUCTURE TABLES ---

Table personal_access_tokens {
  id int [pk, increment]
  tokenable_type varchar(255)
  tokenable_id bigint
  name varchar(255)
  token varchar(64) [unique]
  abilities text
  last_used_at timestamp
  expires_at timestamp
  created_at timestamp
  updated_at timestamp
  
  indexes {
    (tokenable_type, tokenable_id)
  }
  Note: "Laravel Sanctum API authentication tokens"
}

Table cache {
  key varchar(255) [pk]
  value mediumtext
  expiration int
  Note: "Application cache storage"
}

Table cache_locks {
  key varchar(255) [pk]
  owner varchar(255)
  expiration int
  Note: "Cache locking mechanism"
}

Table jobs {
  id int [pk, increment]
  queue varchar(255)
  payload longtext
  attempts tinyint
  reserved_at int
  available_at int
  created_at int
  
  indexes {
    (queue)
  }
  Note: "Queue job storage"
}

Table job_batches {
  id varchar(255) [pk]
  name varchar(255)
  total_jobs int
  pending_jobs int
  failed_jobs int
  failed_job_ids longtext
  options mediumtext
  cancelled_at int
  created_at int
  finished_at int
  Note: "Batch job tracking"
}

Table failed_jobs {
  id int [pk, increment]
  uuid varchar(255) [unique]
  connection text
  queue text
  payload longtext
  exception longtext
  failed_at timestamp
  Note: "Failed job records"
}

Table notifications {
  id varchar(36) [pk, note: "UUID"]
  type varchar(255)
  notifiable_type varchar(255)
  notifiable_id bigint
  data text
  read_at timestamp
  created_at timestamp
  updated_at timestamp
  
  indexes {
    (notifiable_type, notifiable_id)
    (read_at)
  }
  Note: "Laravel notification storage"
}

Table activity_logs {
  id int [pk, increment]
  user_id int [ref: > users.id]
  action varchar(50) [note: "created, updated, deleted, processed, imported"]
  subject_type varchar(100) [note: "Model class name"]
  subject_id bigint
  subject_name varchar(255)
  description text
  properties json [note: "Store old/new values"]
  ip_address varchar(45)
  created_at timestamp
  
  indexes {
    (user_id)
    (subject_type, subject_id)
    (created_at)
    (action)
  }
  Note: "System-wide activity logging for audit trail"
}

Table modules {
  id int [pk, increment]
  name varchar(255) [unique, note: "e.g., users, employees"]
  display_name varchar(255)
  description text
  icon varchar(255)
  category varchar(255)
  route varchar(255)
  active_link varchar(255)
  parent_module varchar(255)
  is_parent boolean [default: false]
  read_permission varchar(255)
  edit_permissions json
  order int [default: 0]
  is_active boolean [default: true]
  created_at timestamp
  updated_at timestamp
  deleted_at timestamp
  Note: "Module and menu management system"
}

Table dashboard_widgets {
  id int [pk, increment]
  name varchar(255) [unique]
  display_name varchar(255)
  description varchar(255)
  component varchar(255) [note: "Vue component name"]
  icon varchar(255)
  category varchar(255) [note: "hr, payroll, leave, general"]
  size varchar(20) [default: "medium"]
  required_permission varchar(255)
  is_active boolean [default: true]
  is_default boolean [default: false]
  default_order int [default: 0]
  config json
  created_at timestamp
  updated_at timestamp
  
  indexes {
    (category)
    (is_active)
  }
  Note: "Dashboard widget configuration"
}

Table user_dashboard_widgets {
  id int [pk, increment]
  user_id int [ref: > users.id]
  dashboard_widget_id int [ref: > dashboard_widgets.id]
  display_order int [default: 0]
  is_visible boolean [default: true]
  custom_config json
  created_at timestamp
  updated_at timestamp
  
  indexes {
    (user_id, display_order)
  }
  Note: "User-specific dashboard customization"
}

--- CRITICAL BUSINESS TABLES ---

Table probation_records {
  id int [pk, increment]
  employment_id bigint [not null, ref: > employments.id]
  employee_id bigint [not null, ref: > employees.id]
  event_type varchar(20) [note: "initial, extension, passed, failed"]
  event_date date
  decision_date date
  probation_start_date date
  probation_end_date date
  previous_end_date date
  extension_number int [default: 0, note: "0=initial, 1=first extension"]
  decision_reason varchar(500)
  evaluation_notes text
  approved_by varchar(255)
  is_active boolean [default: true]
  created_by varchar(255)
  updated_by varchar(255)
  created_at timestamp
  updated_at timestamp
  Note: "Probation period tracking with extensions and decisions"
}

Table personnel_actions {
  id int [pk, increment]
  form_number varchar(50) [default: "SMRU-SF038"]
  reference_number varchar(50) [unique]
  employment_id bigint [ref: > employments.id]
  current_employee_no varchar(50)
  current_department_id bigint [ref: > departments.id]
  current_position_id bigint [ref: > positions.id]
  current_salary decimal(12,2)
  current_site_id bigint [ref: > sites.id]
  current_employment_date date
  effective_date date
  action_type varchar(50) [note: "appointment, fiscal_increment, etc."]
  action_subtype varchar(50)
  is_transfer boolean [default: false]
  transfer_type varchar(50)
  new_department_id bigint [ref: > departments.id]
  new_position_id bigint [ref: > positions.id]
  new_site_id bigint [ref: > sites.id]
  new_salary decimal(12,2)
  new_work_schedule varchar(255)
  new_report_to varchar(255)
  new_pay_plan varchar(255)
  new_phone_ext varchar(50)
  new_email varchar(255)
  comments text
  change_details text
  dept_head_approved boolean [default: false]
  coo_approved boolean [default: false]
  hr_approved boolean [default: false]
  accountant_approved boolean [default: false]
  created_by bigint [ref: > users.id]
  updated_by bigint [ref: > users.id]
  created_at timestamp
  updated_at timestamp
  deleted_at timestamp
  Note: "Personnel action forms for promotions, transfers, salary changes"
}

Table allocation_change_logs {
  id int [pk, increment]
  employee_id int [ref: > employees.id]
  employment_id int [ref: > employments.id]
  employee_funding_allocation_id int [ref: > employee_funding_allocations.id]
  change_type varchar(50) [note: "created, updated, deleted, transferred"]
  action_description varchar(255)
  old_values json
  new_values json
  allocation_summary json
  financial_impact decimal(15,2)
  impact_type varchar(20) [note: "increase, decrease, neutral"]
  approval_status varchar(20) [default: "approved"]
  approved_by int [ref: > users.id]
  approved_at timestamp
  approval_notes text
  reason_category varchar(50)
  business_justification text
  effective_date date
  changed_by varchar(100)
  change_source varchar(50) [default: "manual"]
  ip_address varchar(45)
  user_agent text
  metadata json
  created_at timestamp
  updated_at timestamp
  
  indexes {
    (employee_id, created_at)
    (employment_id, change_type)
    (change_type, approval_status)
    (created_at, change_source)
  }
  Note: "Complete audit trail for funding allocation changes"
}

Table leave_request_items {
  id int [pk, increment]
  leave_request_id bigint [ref: > leave_requests.id]
  leave_type_id bigint [ref: > leave_types.id]
  days decimal(8,2)
  created_at timestamp
  updated_at timestamp
  
  indexes {
    (leave_request_id, leave_type_id) [unique]
  }
  Note: "Enables multi-leave-type requests (e.g., sick + annual leave)"
}

Table bulk_payroll_batches {
  id int [pk, increment]
  pay_period varchar(7) [note: "Format: YYYY-MM"]
  filters json [note: "organization, department, grant filters"]
  total_employees int [default: 0]
  total_payrolls int [default: 0]
  processed_payrolls int [default: 0]
  successful_payrolls int [default: 0]
  failed_payrolls int [default: 0]
  advances_created int [default: 0]
  status varchar(20) [default: "pending", note: "pending, processing, completed, failed"]
  errors json
  summary json
  current_employee varchar(255)
  current_allocation varchar(255)
  created_by bigint [ref: > users.id]
  created_at timestamp
  updated_at timestamp
  
  indexes {
    (status, created_by)
    (pay_period)
  }
  Note: "Tracks bulk payroll processing progress and errors"
}

Table benefit_settings {
  id int [pk, increment]
  setting_key varchar(255) [unique, note: "e.g., health_welfare_percentage"]
  setting_value decimal(10,2)
  setting_type varchar(50) [default: "percentage"]
  description text
  effective_date date
  is_active boolean [default: true]
  applies_to json [note: "JSON conditions for applicability"]
  created_by varchar(100)
  updated_by varchar(100)
  created_at timestamp
  updated_at timestamp
  deleted_at timestamp
  
  indexes {
    (setting_key)
    (is_active)
    (effective_date)
  }
  Note: "Configurable benefit settings (PVD, health welfare rates, etc.)"
}

Table payslips {
  id int [pk, increment]
  payroll_id int [ref: > payrolls.id]
  payslip_date date
  payslip_number varchar(20)
  staff_signature varchar(200)
  created_at timestamp
  updated_at timestamp
  created_by varchar(100)
  updated_by varchar(100)
  Note: "Payslip records linked to payroll"
}

Please merge all these changes into my existing diagram, maintaining all my current tables and only adding/updating as specified above. Ensure all foreign key relationships are properly connected.
```

---

## Alternative: Manual Update Instructions

If the AI assistant doesn't work well or you prefer manual updates, you can:

1. **Open the complete diagram file**: `docs/database/COMPLETE_DATABASE_DIAGRAM.md`
2. **Copy the entire DBML code** from that file
3. **Paste directly into dbdiagram.io** to replace your current diagram
4. This gives you a complete, fresh diagram with all 63 tables

---

## Verification Checklist

After updating, verify these key changes:

- [ ] Table count: Should show **63 tables** total
- [ ] "subsidiaries" renamed to "organizations"
- [ ] "personnel_actions" table exists and connects to employments
- [ ] "probation_records" table exists and connects to employments
- [ ] "allocation_change_logs" table exists
- [ ] "leave_request_items" table exists
- [ ] "leave_requests" no longer has leave_type_id column
- [ ] All system tables (cache, jobs, notifications, etc.) are present
- [ ] All relationships are properly connected

---

## Tips for Using DBDiagram AI

### Good Prompts:
- ✅ "Add a table called X with these columns..."
- ✅ "Create a relationship between table A and table B"
- ✅ "Update table X to add column Y"
- ✅ "Remove the column Z from table X"

### Avoid:
- ❌ Asking to add too many tables at once (may get truncated)
- ❌ Complex nested requests
- ❌ Asking it to "figure out" schema designs

### Best Practice:
If the AI has trouble with the large prompt above, break it into smaller chunks:

1. **First prompt**: Add system infrastructure tables (cache, jobs, notifications, etc.)
2. **Second prompt**: Add business tables (probation_records, personnel_actions, etc.)
3. **Third prompt**: Add remaining tables and make corrections
4. **Fourth prompt**: Update relationships and foreign keys

---

## Need Help?

If you encounter issues:
1. Try the manual approach (copy entire DBML from `COMPLETE_DATABASE_DIAGRAM.md`)
2. Use the web interface to manually add tables one by one
3. Export your current diagram first as backup before making changes

---

**Document Created**: January 8, 2026  
**Purpose**: Guide for updating dbdiagram.io with missing HRMS tables

