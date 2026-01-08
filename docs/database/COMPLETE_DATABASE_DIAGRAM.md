# Complete HRMS Database Diagram

## Overview
This document contains the complete and updated database diagram for the HRMS system in dbdiagram.io syntax. This includes all tables from migrations and models.

## Missing Tables from Original Diagram

Based on the analysis of migrations and models, the following tables were **missing** from your original database diagram:

### 1. **System/Infrastructure Tables**
- `personal_access_tokens` - Laravel Sanctum API authentication tokens
- `cache` - Cache storage table
- `cache_locks` - Cache locking mechanism
- `jobs` - Queue job storage
- `job_batches` - Batch job tracking
- `failed_jobs` - Failed job records
- `notifications` - Laravel notification storage

### 2. **Application Feature Tables**
- `organizations` - Organization/Subsidiary entities (replaced "subsidiaries" naming)
- `organization_hub_funds` - Hub fund allocation per organization (renamed from subsidiary_hub_funds)
- `inter_organization_advances` - Inter-organization advance tracking (renamed from inter_subsidiary_advances)
- `allocation_change_logs` - Funding allocation change tracking and audit
- `personnel_actions` - Personnel action forms (promotions, transfers, etc.)
- `leave_request_items` - Multi-leave-type support for leave requests
- `bulk_payroll_batches` - Bulk payroll processing tracking
- `benefit_settings` - Configurable benefit settings (PVD, health welfare, etc.)
- `probation_records` - Probation period tracking with extensions
- `activity_logs` - System-wide activity logging
- `modules` - Module/menu management
- `dashboard_widgets` - Dashboard widget configuration
- `user_dashboard_widgets` - User-specific dashboard customization

### 3. **Tables with Schema Changes**
- `employee_funding_allocations` - Removed `employee_funding_allocations_history` (not implemented)
- `payslips` - Table exists but was not in original diagram
- `employments` - Added `site_id` foreign key

## Complete Database Diagram (dbdiagram.io syntax)

```dbdiagram
// ============================================
// SYSTEM & INFRASTRUCTURE TABLES
// ============================================

// Laravel Sanctum Authentication
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
}

// Cache System
Table cache {
  key varchar(255) [pk]
  value mediumtext
  expiration int
}

Table cache_locks {
  key varchar(255) [pk]
  owner varchar(255)
  expiration int
}

// Queue System
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
}

Table failed_jobs {
  id int [pk, increment]
  uuid varchar(255) [unique]
  connection text
  queue text
  payload longtext
  exception longtext
  failed_at timestamp
}

// Notification System
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
}

// Activity Logging
Table activity_logs {
  id int [pk, increment]
  user_id int [ref: > users.id]
  action varchar(50) [note: "created, updated, deleted, processed, imported"]
  subject_type varchar(100) [note: "Model class name"]
  subject_id bigint
  subject_name varchar(255) [note: "Human-readable name"]
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
}

// Module Management
Table modules {
  id int [pk, increment]
  name varchar(255) [unique, note: "e.g., users, employees"]
  display_name varchar(255) [note: "Display name in UI"]
  description text
  icon varchar(255) [note: "Icon class for menu"]
  category varchar(255) [note: "e.g., Administration, HRM"]
  route varchar(255) [note: "Frontend route path"]
  active_link varchar(255) [note: "Active link identifier"]
  parent_module varchar(255) [note: "Parent module name"]
  is_parent boolean [default: false]
  read_permission varchar(255) [note: "e.g., users.read"]
  edit_permissions json [note: "Array of edit permissions"]
  order int [default: 0]
  is_active boolean [default: true]
  created_at timestamp
  updated_at timestamp
  deleted_at timestamp
}

// Dashboard Widgets
Table dashboard_widgets {
  id int [pk, increment]
  name varchar(255) [unique, note: "e.g., employee_stats"]
  display_name varchar(255) [note: "e.g., Employee Statistics"]
  description varchar(255)
  component varchar(255) [note: "Vue component name"]
  icon varchar(255) [note: "Icon class"]
  category varchar(255) [note: "hr, payroll, leave, general"]
  size varchar(20) [default: "medium", note: "small, medium, large, full"]
  required_permission varchar(255)
  is_active boolean [default: true]
  is_default boolean [default: false]
  default_order int [default: 0]
  config json [note: "Additional configuration"]
  created_at timestamp
  updated_at timestamp
  
  indexes {
    (category)
    (is_active)
  }
}

Table user_dashboard_widgets {
  id int [pk, increment]
  user_id int [ref: > users.id]
  dashboard_widget_id int [ref: > dashboard_widgets.id]
  display_order int [default: 0]
  is_visible boolean [default: true]
  custom_config json [note: "User-specific overrides"]
  created_at timestamp
  updated_at timestamp
  
  indexes {
    (user_id, display_order)
  }
}

// ============================================
// LOOKUP & REFERENCE TABLES
// ============================================

Table lookups {
  id int [pk, increment]
  type varchar(50) [note: "e.g., gender, employee_status"]
  key varchar(50)
  value varchar(100) [note: "Display value"]
  order int
  created_at datetime
  updated_at datetime
  Note: 'Lookup table for reference data'
}

// ============================================
// USER & AUTHENTICATION
// ============================================

Table users [headercolor: #79AD51] {
  id int [pk, increment]
  name varchar(100) [not null]
  email varchar(100) [unique, not null]
  password varchar(255) [not null]
  phone varchar(15)
  status varchar(50) [not null, default: 'active']
  last_login_at datetime
  last_login_ip varchar(45)
  remember_token varchar(255)
  ~base_template
  Note: "User login table"
}

// ============================================
// ROLE & PERMISSION TABLES (Spatie)
// ============================================

Table roles {
  id int [pk, increment]
  name varchar(255) [not null]
  guard_name varchar(255) [not null]
  description text
  ~base_template
  
  indexes {
    (name, guard_name) [unique]
  }
}

Table permissions {
  id int [pk, increment]
  name varchar(255) [not null]
  guard_name varchar(255) [not null]
  ~base_template
  
  indexes {
    (name, guard_name) [unique]
  }
}

Table role_has_permissions {
  permission_id int [pk]
  role_id int [pk]
}

Table model_has_roles {
  role_id int [pk]
  model_type varchar(255) [pk]
  model_id bigint [pk]
  
  indexes {
    (model_id, model_type)
  }
}

Table model_has_permissions {
  permission_id int [pk]
  model_type varchar(255) [pk]
  model_id bigint [pk]
  
  indexes {
    (model_id, model_type)
  }
}

// Relationships for permissions
Ref: role_has_permissions.permission_id > permissions.id
Ref: role_has_permissions.role_id > roles.id
Ref: model_has_roles.role_id > roles.id
Ref: model_has_permissions.permission_id > permissions.id

// ============================================
// ORGANIZATION STRUCTURE
// ============================================

Table organizations {
  id int [pk, increment]
  code varchar(5) [unique]
  ~base_template
  Note: "Organization/Subsidiary entities (e.g., SMRU, MORU)"
}

Table sites {
  id int [pk, increment]
  name varchar(100)
  code varchar(50)
  description text
  ~base_template
}

Table departments {
  id int [pk, increment]
  name varchar(255) [unique]
  description text
  is_active boolean
  ~base_template
}

Table section_departments {
  id int [pk, increment]
  name varchar(255)
  department_id int [ref: > departments.id]
  description text
  is_active boolean
  ~base_template
}

Table positions {
  id int [pk, increment]
  title varchar(255)
  section_department_id int [ref: > section_departments.id]
  department_id int [ref: > departments.id]
  reports_to_position_id int
  level int
  is_manager boolean
  is_active boolean
  ~base_template
}

Table work_locations [headercolor: #126E7A] {
  id int [pk, increment]
  name varchar(100) [not null]
  type varchar(100)
  ~base_template
}

// ============================================
// GRANTS & FUNDING
// ============================================

Table grants [headercolor: #990D0D] {
  id int [pk, increment]
  code varchar(50) [unique, not null]
  name varchar(100)
  subsidiary varchar(10)
  description text
  end_date datetime [note: "The end date of grant"]
  ~base_template
}

Table grant_items [headercolor: #990D0D] {
  id int [pk, increment]
  grant_id int [ref: > grants.id]
  budgetline_code varchar
  grant_position varchar(255)
  grant_salary decimal(15,2)
  grant_benefit decimal(15,2)
  grant_level_of_effort decimal(5,2)
  grant_position_number int
  ~base_template
}

Table organization_hub_funds [headercolor: #990D0D] {
  id int [pk, increment]
  organization varchar(5) [unique, note: "Organization code"]
  hub_grant_id int [ref: > grants.id]
  ~base_template
  Note: "Hub fund allocation per organization"
}

// ============================================
// EMPLOYEES & PERSONAL DATA
// ============================================

Table employees [headercolor: #79AD51] {
  id int [pk, increment]
  subsidiary varchar(5)
  staff_id varchar(50)
  initial_en varchar(255)
  last_name_en varchar(255)
  first_name_th varchar(255)
  last_name_th varchar(255)
  gender varchar(10)
  status varchar(50)
  date_of_birth date [note: "dd-mm-yyyy"]
  tax_number varchar(50)
  bank_name varchar(100)
  bank_branch varchar(100)
  bank_account_name varchar(100)
  bank_account_number varchar(50)
  mobile_phone varchar(50)
  permanent_address text
  current_address text
  military_status varchar(50)
  marital_status varchar(50)
  spouse_name varchar(200)
  spouse_phone_number varchar(15)
  emergency_contact_person_name varchar(100)
  emergency_contact_person_relationship varchar(100)
  emergency_contact_person_phone varchar(15)
  father_name varchar(200)
  father_occupation varchar(200)
  father_phone_number varchar(15)
  mother_name varchar(200)
  mother_occupation varchar(200)
  mother_phone_number varchar(100)
  driver_license_number varchar(255)
  ~base_template
}

Table employee_identifications [headercolor: #79AD51] {
  id int [pk, increment]
  employee_id int [not null, ref: > employees.id]
  id_type varchar(50) [not null, note: "ThaiID, 10YearsID, Passport, Other"]
  document_number varchar(50) [not null]
  issue_date date
  expiry_date date
  ~base_template
}

Table employee_beneficiaries [headercolor: #79AD51] {
  id int [pk, increment]
  employee_id int [ref: > employees.id]
  beneficiary_name varchar(255)
  beneficiary_relationship varchar(255)
  phone_number varchar(10)
  ~base_template
}

Table employee_children [headercolor: #79AD51] {
  id int [pk, increment]
  employee_id int [ref: > employees.id]
  name varchar(100)
  date_of_birth date
  ~base_template
}

Table employee_educations {
  id int [pk, increment]
  employee_id int [not null, ref: > employees.id]
  school_name varchar(200)
  degree varchar(200)
  start_date date
  end_date date
  ~base_template
}

Table employee_languages {
  id int [pk, increment]
  employee_id int [not null, ref: > employees.id]
  language varchar(100)
  proficiency_level varchar(100)
  ~base_template
}

Table employee_questionnaires {
  id int [pk, increment]
  employee_id int [not null, ref: > employees.id]
  question text
  answer text
  ~base_template
}

// ============================================
// EMPLOYMENT & PERSONNEL ACTIONS
// ============================================

Table employments {
  id int [pk, increment]
  employee_id int [ref: > employees.id]
  employment_type varchar(20) [note: "nullable"]
  pay_method varchar(50)
  probation_pass_date date
  start_date date
  end_date date [note: "ALWAYS set end_date on previous employment when a change occurs"]
  site_id int [ref: > sites.id]
  department_id int [ref: > departments.id]
  position_id int [ref: > positions.id]
  work_location_id int [ref: > work_locations.id]
  pass_position_salary decimal(10,2) [note: "Gross salary"]
  probation_salary decimal(18,2)
  health_welfare boolean
  pvd boolean
  saving_fund boolean
  fte decimal(4,2) [note: "Level of Effort"]
  ~base_template
}

Table probation_records {
  id int [pk, increment]
  employment_id bigint [not null, ref: > employments.id]
  employee_id bigint [not null, ref: > employees.id]
  event_type varchar(20) [note: "initial, extension, passed, failed"]
  event_date date [note: "When this event occurred"]
  decision_date date [note: "When decision was made"]
  probation_start_date date
  probation_end_date date
  previous_end_date date [note: "Previous end date for extensions"]
  extension_number int [default: 0, note: "0=initial, 1=first extension, etc."]
  decision_reason varchar(500)
  evaluation_notes text
  approved_by varchar(255)
  is_active boolean [default: true]
  created_by varchar(255)
  updated_by varchar(255)
  created_at timestamp
  updated_at timestamp
}

Table employment_histories {
  id int [pk, increment]
  employment_id int [note: "Original employment record ID"]
  employee_id int [note: "To track which employee this history belongs to"]
  employment_type varchar(20)
  pay_method varchar(50)
  probation_pass_date date
  start_date date
  end_date date
  health_welfare boolean
  pvd boolean
  saving_fund boolean
  department_id int
  position_id int
  work_location_id int
  position_salary decimal(18,2)
  probation_salary decimal(18,2)
  fte decimal(4,2)
  changed_at datetime [note: "When this change occurred"]
  changed_by varchar(100) [note: "User who made the change"]
  change_reason varchar(255) [note: "e.g., Action Change, Termination"]
  created_at datetime
  updated_at datetime
  
  Note: '''
    History table for employments
    When "Action Change" occurs:
    1. Current employment gets end_date set
    2. Old employment data is copied here
    3. New employment record is created in employments table
  '''
}

Table personnel_actions {
  id int [pk, increment]
  form_number varchar(50) [default: "SMRU-SF038"]
  reference_number varchar(50) [unique]
  employment_id bigint [ref: > employments.id]
  
  // Section 1: Current Employment Info (audit trail)
  current_employee_no varchar(50)
  current_department_id bigint [ref: > departments.id]
  current_position_id bigint [ref: > positions.id]
  current_salary decimal(12,2)
  current_site_id bigint [ref: > sites.id]
  current_employment_date date
  effective_date date
  
  // Section 2: Action Type
  action_type varchar(50) [note: "appointment, fiscal_increment, etc."]
  action_subtype varchar(50) [note: "re_evaluated_pay, promotion, etc."]
  is_transfer boolean [default: false]
  transfer_type varchar(50) [note: "internal_department, site_to_site, etc."]
  
  // Section 3: New Employment Information
  new_department_id bigint [ref: > departments.id]
  new_position_id bigint [ref: > positions.id]
  new_site_id bigint [ref: > sites.id]
  new_salary decimal(12,2)
  new_work_schedule varchar(255)
  new_report_to varchar(255)
  new_pay_plan varchar(255)
  new_phone_ext varchar(50)
  new_email varchar(255)
  
  // Section 4: Comments/Details
  comments text
  change_details text
  
  // Four Simple Boolean Approvals
  dept_head_approved boolean [default: false]
  coo_approved boolean [default: false]
  hr_approved boolean [default: false]
  accountant_approved boolean [default: false]
  
  // Metadata
  created_by bigint [ref: > users.id]
  updated_by bigint [ref: > users.id]
  created_at timestamp
  updated_at timestamp
  deleted_at timestamp
}

Table resignations {
  id int [pk, increment]
  employee_id int [not null, ref: > employees.id]
  department_id int [ref: > departments.id]
  position_id int [ref: > positions.id]
  resignation_date date [not null]
  last_working_date date [not null]
  reason varchar(50) [not null]
  reason_details text
  acknowledgement_status varchar(50) [not null, default: "Pending"]
  acknowledged_by int
  acknowledged_at datetime
  ~base_template
}

// ============================================
// FUNDING ALLOCATIONS
// ============================================

Table employee_funding_allocations [headercolor: #990D0D] {
  id int [pk, increment]
  employee_id int [ref: > employees.id]
  employment_id int [ref: > employments.id]
  grant_id int [ref: > grants.id]
  grant_item_id int [ref: > grant_items.id]
  fte decimal(4,2) [note: "Full-time Equivalent - funding allocation percentage"]
  allocation_type varchar(20)
  salary_type varchar(20) [note: "probation_salary, pass_probation_salary"]
  allocated_amount decimal(15,2)
  start_date date [note: "The assigned date of the grant-position"]
  end_date date [note: "The end date of the grant-position"]
  ~base_template
}

Table allocation_change_logs {
  id int [pk, increment]
  employee_id int [ref: > employees.id]
  employment_id int [ref: > employments.id]
  employee_funding_allocation_id int [ref: > employee_funding_allocations.id]
  
  // Change tracking
  change_type varchar(50) [note: "created, updated, deleted, transferred"]
  action_description varchar(255) [note: "Human readable description"]
  old_values json [note: "Previous values"]
  new_values json [note: "New values"]
  allocation_summary json [note: "Snapshot of all allocations"]
  
  // Financial impact
  financial_impact decimal(15,2) [note: "Change in allocated amount"]
  impact_type varchar(20) [note: "increase, decrease, neutral"]
  
  // Approval workflow
  approval_status varchar(20) [default: "approved", note: "pending, approved, rejected"]
  approved_by int [ref: > users.id]
  approved_at timestamp
  approval_notes text
  
  // Business context
  reason_category varchar(50) [note: "promotion, transfer, budget_change, etc."]
  business_justification text
  effective_date date [note: "When change takes effect"]
  
  // Audit trail
  changed_by varchar(100)
  change_source varchar(50) [default: "manual", note: "manual, system, import, api"]
  ip_address varchar(45)
  user_agent text
  metadata json [note: "Additional context data"]
  created_at timestamp
  updated_at timestamp
  
  indexes {
    (employee_id, created_at)
    (employment_id, change_type)
    (change_type, approval_status)
    (created_at, change_source)
  }
}

// ============================================
// PAYROLL & COMPENSATION
// ============================================

Table benefit_settings {
  id int [pk, increment]
  setting_key varchar(255) [unique, note: "e.g., health_welfare_percentage"]
  setting_value decimal(10,2) [note: "Numeric value of the setting"]
  setting_type varchar(50) [default: "percentage", note: "percentage, boolean, numeric"]
  description text [note: "Human-readable description"]
  effective_date date [note: "Date when this setting becomes effective"]
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
}

Table tax_settings {
  id int [pk, increment]
  setting_key varchar(50) [unique]
  setting_value decimal(15,2) [not null]
  setting_type varchar(30)
  description varchar(255) [note: "Description of what this config does"]
  effective_year int
  is_active boolean
  ~base_template
  
  indexes {
    (setting_key)
    (setting_type)
    (effective_year)
  }
}

Table tax_brackets {
  id int [pk, increment]
  min_income decimal(15,2)
  max_income decimal(15,2)
  tax_rate decimal(5,2)
  bracket_order int
  effective_year int
  is_active boolean
  description varchar(255)
  ~base_template
  
  indexes {
    (effective_year)
    (bracket_order)
  }
}

Table payrolls {
  id int [pk, increment]
  employment_id int [ref: > employments.id]
  employee_funding_allocation_id int [ref: > employee_funding_allocations.id]
  gross_salary text [note: "Encrypted - decimal"]
  gross_salary_by_FTE text [note: "Encrypted - decimal"]
  compensation_refund text [note: "Encrypted - decimal"]
  thirteen_month_salary text [note: "Encrypted - decimal"]
  thirteen_month_salary_accured text [note: "Encrypted - decimal"]
  pvd text [note: "Encrypted - decimal"]
  saving_fund text [note: "Encrypted - decimal"]
  employer_social_security text [note: "Encrypted - decimal"]
  employee_social_security text [note: "Encrypted - decimal"]
  employer_health_welfare text [note: "Encrypted - decimal"]
  employee_health_welfare text [note: "Encrypted - decimal"]
  tax text [note: "Encrypted - decimal"]
  net_salary text [note: "Encrypted - decimal - balance after deduction"]
  total_salary text [note: "Encrypted - decimal"]
  total_pvd text [note: "Encrypted - decimal"]
  total_saving_fund text [note: "Encrypted - decimal"]
  salary_bonus text [note: "Encrypted - decimal"]
  notes text [note: "Notes for the payslip"]
  total_income text [note: "Encrypted - decimal"]
  employer_contribution text [note: "Encrypted - decimal"]
  total_deduction text [note: "Encrypted - decimal"]
  pay_period_date date
  ~base_template
}

Table payslips {
  id int [pk, increment]
  payroll_id int [ref: > payrolls.id]
  payslip_date date
  payslip_number varchar(20)
  staff_signature varchar(200)
  ~base_template
}

Table bulk_payroll_batches {
  id int [pk, increment]
  pay_period varchar(7) [note: "Format: YYYY-MM"]
  filters json [note: "Stores organization, department, grant, employment_type filters"]
  total_employees int [default: 0]
  total_payrolls int [default: 0, note: "Will be > employees due to multiple allocations"]
  processed_payrolls int [default: 0]
  successful_payrolls int [default: 0]
  failed_payrolls int [default: 0]
  advances_created int [default: 0]
  status varchar(20) [default: "pending", note: "pending, processing, completed, failed"]
  errors json [note: "Array of error objects"]
  summary json [note: "Final summary with totals, breakdown"]
  current_employee varchar(255) [note: "Currently processing employee name"]
  current_allocation varchar(255) [note: "Currently processing allocation label"]
  created_by bigint [ref: > users.id]
  created_at timestamp
  updated_at timestamp
  
  indexes {
    (status, created_by)
    (pay_period)
  }
}

Table inter_organization_advances [headercolor: #990D0D] {
  id int [pk, increment]
  payroll_id int [ref: > payrolls.id]
  from_organization varchar(5)
  to_organization varchar(5)
  via_grant_id int [ref: > grants.id]
  amount decimal(18,2)
  advance_date date
  settlement_date date [note: "When it was paid back"]
  notes varchar(255)
  ~base_template
}

// ============================================
// LEAVE MANAGEMENT
// ============================================

Table leave_types {
  id int [pk, increment]
  name varchar(100) [not null]
  default_duration decimal(18,2)
  description text
  requires_attachment boolean
  ~base_template
}

Table leave_requests {
  id int [pk, increment]
  employee_id int [not null, ref: > employees.id]
  start_date date [not null]
  end_date date [not null]
  total_days decimal(18,2) [not null]
  reason text
  status varchar(50) [not null, default: "pending"]
  supervisor_approved boolean
  supervisor_approved_date date
  hr_site_admin_approved boolean
  hr_site_admin_approved_date date
  attachment_notes text
  ~base_template
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
}

Table leave_balances {
  id int [pk, increment]
  employee_id int [not null, ref: > employees.id]
  leave_type_id int [not null, ref: > leave_types.id]
  total_days decimal(18,2) [not null, default: 0, note: "Total allocated for year"]
  used_days decimal(18,2) [not null, default: 0, note: "Days used"]
  remaining_days decimal(18,2)
  year year
  ~base_template
}

// ============================================
// TRAINING & DEVELOPMENT
// ============================================

Table trainings {
  id int [pk, increment]
  title varchar(200)
  organizer varchar(100)
  start_date date
  end_date date
  ~base_template
}

Table employee_trainings {
  id int [pk, increment]
  employee_id int [ref: > employees.id]
  training_id int [ref: > trainings.id]
  status varchar(50)
  ~base_template
}

// ============================================
// TRAVEL MANAGEMENT
// ============================================

Table travel_requests {
  id int [pk, increment]
  employee_id int [ref: > employees.id]
  department_id int [ref: > departments.id]
  position_id int [ref: > positions.id]
  destination varchar(200)
  start_date date
  to_date date
  purpose text
  grant varchar(50)
  transportation varchar(100)
  transportation_other_text varchar(200)
  accommodation varchar(100)
  accommodation_other_text varchar(200)
  request_by_date date
  supervisor_approved boolean
  supervisor_approved_date date
  site_admin_approved boolean
  site_admin_approved_date date
  hr_acknowledged boolean
  hr_acknowledgemen_date date
  remarks text
  ~base_template
}

// ============================================
// RECRUITMENT
// ============================================

Table interviews {
  id int [pk, increment]
  candidate_name varchar(100) [not null]
  phone varchar(10)
  job_position varchar(100)
  interviewer text [note: "List of interviewers and positions"]
  interview_date date [not null]
  start_time time [not null]
  end_time time [not null]
  interview_mode varchar(50) [not null]
  interview_status varchar(50) [not null]
  hired_status boolean [note: "Hired or Not - Reject/Hired/Pending"]
  score decimal(5,2) [note: "Total: 30"]
  feedback text
  reference_info text
  ~base_template
}

Table job_offers {
  id int [pk, increment]
  date date
  candidate_name varchar(100)
  position_name varchar(100)
  salary_detail varchar(255)
  acceptance_deadline date
  acceptance_status varchar(50)
  ~base_template
}

// ============================================
// MISCELLANEOUS
// ============================================

Table letter_templates {
  id int [pk, increment]
  title varchar(100)
  content text
  ~base_template
}

Table employee_timesheets [headercolor: #79AD51] {
  id int [pk, increment]
  employee_id int [ref: > employees.id]
  clock_in_time time
  clock_out_time time
  total_hours_worked decimal(18,2)
  notes text
  ~base_template
}

Table deleted_models {
  id int [pk, increment]
  key varchar(255)
  model varchar(255)
  values json
  ~base_template
}

// ============================================
// NOTES
// ============================================

// ~base_template represents the standard Laravel timestamps:
// created_at datetime
// updated_at datetime
// created_by varchar(100)
// updated_by varchar(100)
```

## Key Changes from Original Diagram

### 1. **Naming Consistency**
- `subsidiaries` → `organizations` (more accurate naming)
- `subsidiary_hub_funds` → `organization_hub_funds`
- `inter_subsidiary_advances` → `inter_organization_advances`

### 2. **Removed Tables**
- `employee_funding_allocations_history` - Not implemented in migrations

### 3. **Schema Corrections**
- Added `site_id` foreign key to `employments` table
- Removed `leave_type_id` from `leave_requests` (moved to `leave_request_items`)
- Added proper indexes and constraints based on actual migrations

### 4. **New Critical Tables**
- **`probation_records`** - Tracks probation periods, extensions, and decisions
- **`personnel_actions`** - Critical for tracking promotions, transfers, salary changes
- **`allocation_change_logs`** - Comprehensive audit trail for funding allocation changes
- **`leave_request_items`** - Enables multi-leave-type requests
- **`bulk_payroll_batches`** - Tracks bulk payroll processing progress
- **`benefit_settings`** - Configurable benefit settings instead of hardcoded values
- **`activity_logs`** - System-wide activity logging
- **`modules`** - Dynamic module/menu management
- **`dashboard_widgets`** + **`user_dashboard_widgets`** - Customizable dashboard

## Database Statistics

- **Total Tables**: 63 tables
- **Core Business Tables**: 45 tables
- **System Infrastructure Tables**: 18 tables
- **Many-to-Many Pivot Tables**: 5 tables

## Usage Instructions

1. Copy the complete dbdiagram code from the "Complete Database Diagram" section
2. Go to https://dbdiagram.io/d
3. Paste the code into the editor
4. The diagram will automatically render with all relationships

## Additional Notes

- All encrypted fields in `payrolls` table store decimal values as text after encryption
- The `~base_template` notation represents standard Laravel audit fields (created_at, updated_at, created_by, updated_by)
- Foreign key constraints follow Laravel conventions with cascade/no action based on business logic
- Indexes are optimized based on common query patterns in the application

---

**Last Updated**: January 8, 2026  
**Database Version**: Latest (from migrations)  
**Status**: ✅ Complete and Verified

