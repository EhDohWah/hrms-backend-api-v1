# HRMS Database Relationships Reference

Quick reference guide for all table relationships in the HRMS system.

## Table of Contents
1. [Relationship Summary](#relationship-summary)
2. [All Tables](#all-tables)
3. [Detailed Relationships](#detailed-relationships)

---

## Relationship Summary

| Category | Tables | Total Relationships |
|----------|--------|---------------------|
| Employee & Employment | 3 | 12 |
| Organizational Structure | 4 | 8 |
| Grant & Funding | 5 | 10 |
| Payroll | 3 | 5 |
| Leave Management | 4 | 6 |
| Personnel Actions | 3 | 7 |
| Recruitment | 2 | 1 |
| Auth & Permissions | 5 | 10 |
| **TOTAL** | **29** | **59** |

---

## All Tables

### 1. Core Tables (24)
1. users
2. employees
3. employments
4. employment_histories
5. departments
6. positions
7. work_locations
8. subsidiaries
9. grants
10. grant_items
11. position_slots
12. org_funded_allocations
13. employee_funding_allocations
14. payrolls
15. tax_brackets
16. bulk_payroll_batches
17. leave_types
18. leave_requests
19. leave_request_items
20. leave_balances
21. personnel_actions
22. resignations
23. interviews
24. job_offers

### 2. Permission Tables (5)
25. permissions
26. roles
27. model_has_permissions
28. model_has_roles
29. role_has_permissions

---

## Detailed Relationships

### Table: users
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| has many | employees | 1:M | user_id | User profile for employees |
| has many | personnel_actions | 1:M | created_by | Personnel actions created |
| has many | resignations | 1:M | acknowledged_by | Resignations acknowledged |
| has many | bulk_payroll_batches | 1:M | created_by | Payroll batches created |
| has many | model_has_roles | 1:M (polymorphic) | model_id | Roles assigned to user |
| has many | model_has_permissions | 1:M (polymorphic) | model_id | Permissions assigned to user |

**Total: 6 relationships**

---

### Table: employees
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | users | M:1 | user_id | Associated user account |
| belongs to | subsidiaries | M:1 | subsidiary | Employee's subsidiary |
| has many | employments | 1:M | employee_id | Employment records |
| has many | leave_requests | 1:M | employee_id | Leave requests submitted |
| has many | leave_balances | 1:M | employee_id | Leave balances per type |
| has many | employee_funding_allocations | 1:M | employee_id | Funding allocations |
| has many | resignations | 1:M | employee_id | Resignations filed |

**Total: 7 relationships**

---

### Table: employments
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | employees | M:1 | employee_id | Associated employee |
| belongs to | departments | M:1 | department_id | Assigned department |
| belongs to | positions | M:1 | position_id | Held position |
| belongs to | work_locations | M:1 | work_location_id | Work location |
| has many | employment_histories | 1:M | employment_id | Change history |
| has many | employee_funding_allocations | 1:M | employment_id | Funding allocations |
| has many | payrolls | 1:M | employment_id | Generated payrolls |
| has many | personnel_actions | 1:M | employment_id | Personnel actions |

**Total: 8 relationships**

---

### Table: employment_histories
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | employments | M:1 | employment_id | Related employment |
| belongs to | employees | M:1 | employee_id | Related employee |
| belongs to | departments | M:1 | department_id | Department at time of change |
| belongs to | positions | M:1 | position_id | Position at time of change |
| belongs to | work_locations | M:1 | work_location_id | Work location at time of change |

**Total: 5 relationships**

---

### Table: departments
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| has many | positions | 1:M | department_id | Positions in department |
| has many | employments | 1:M | department_id | Employees assigned |
| has many | org_funded_allocations | 1:M | department_id | Org-funded allocations |
| has many | resignations | 1:M | department_id | Resignations from department |
| has many | personnel_actions (current) | 1:M | current_department_id | Current department in PA |
| has many | personnel_actions (new) | 1:M | new_department_id | New department in PA |

**Total: 6 relationships**

---

### Table: positions
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | departments | M:1 | department_id | Department containing position |
| belongs to | positions (self) | M:1 | reports_to_position_id | Reporting hierarchy |
| has many | positions (self) | 1:M | reports_to_position_id | Subordinate positions |
| has many | employments | 1:M | position_id | Employees in position |
| has many | org_funded_allocations | 1:M | position_id | Org-funded allocations |
| has many | resignations | 1:M | position_id | Resignations from position |
| has many | personnel_actions (current) | 1:M | current_position_id | Current position in PA |
| has many | personnel_actions (new) | 1:M | new_position_id | New position in PA |

**Total: 8 relationships**

---

### Table: work_locations
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| has many | employments | 1:M | work_location_id | Employees at location |
| has many | personnel_actions (current) | 1:M | current_work_location_id | Current location in PA |
| has many | personnel_actions (new) | 1:M | new_work_location_id | New location in PA |

**Total: 3 relationships**

---

### Table: subsidiaries
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| has many | employees | 1:M | subsidiary | Employees in subsidiary |
| has many | grants | 1:M | subsidiary | Grants for subsidiary |

**Total: 2 relationships**

---

### Table: grants
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | subsidiaries | M:1 | subsidiary | Grant's subsidiary |
| has many | grant_items | 1:M | grant_id | Grant items/positions |
| has many | org_funded_allocations | 1:M | grant_id | Org-funded allocations |

**Total: 3 relationships**

---

### Table: grant_items
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | grants | M:1 | grant_id | Parent grant |
| has many | position_slots | 1:M | grant_item_id | Available slots |

**Total: 2 relationships**

---

### Table: position_slots
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | grant_items | M:1 | grant_item_id | Grant item for slot |
| has many | employee_funding_allocations | 1:M | position_slot_id | Allocations filling slot |

**Total: 2 relationships**

---

### Table: org_funded_allocations
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | grants | M:1 | grant_id | Funding grant |
| belongs to | departments | M:1 | department_id | Associated department |
| belongs to | positions | M:1 | position_id | Associated position |
| has many | employee_funding_allocations | 1:M | org_funded_id | Employee allocations |

**Total: 4 relationships**

---

### Table: employee_funding_allocations
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | employees | M:1 | employee_id | Allocated employee |
| belongs to | employments | M:1 | employment_id | Related employment |
| belongs to | position_slots | M:1 | position_slot_id | Grant position slot (nullable) |
| belongs to | org_funded_allocations | M:1 | org_funded_id | Org-funded allocation (nullable) |
| has many | payrolls | 1:M | employee_funding_allocation_id | Generated payrolls |

**Total: 5 relationships**

---

### Table: payrolls
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | employments | M:1 | employment_id | Related employment |
| belongs to | employee_funding_allocations | M:1 | employee_funding_allocation_id | Funding source |
| references | tax_brackets | - | - | Used for tax calculation |

**Total: 3 relationships**

---

### Table: tax_brackets
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| referenced by | payrolls | - | - | Tax calculations reference brackets |

**Total: 1 relationship (indirect)**

---

### Table: bulk_payroll_batches
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | users | M:1 | created_by | Batch creator |
| processes | payrolls | - (indirect) | - | Batch processes multiple payrolls |

**Total: 2 relationships**

---

### Table: leave_types
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| has many | leave_balances | 1:M | leave_type_id | Leave balances per type |
| has many | leave_request_items | 1:M | leave_type_id | Leave request items |

**Total: 2 relationships**

---

### Table: leave_requests
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | employees | M:1 | employee_id | Requesting employee |
| has many | leave_request_items | 1:M | leave_request_id | Multi-type leave breakdown |

**Total: 2 relationships**

---

### Table: leave_request_items
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | leave_requests | M:1 | leave_request_id | Parent leave request |
| belongs to | leave_types | M:1 | leave_type_id | Type of leave |

**Total: 2 relationships**

---

### Table: leave_balances
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | employees | M:1 | employee_id | Employee's balance |
| belongs to | leave_types | M:1 | leave_type_id | Leave type |

**Total: 2 relationships**

---

### Table: personnel_actions
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | employments | M:1 | employment_id | Modified employment |
| belongs to | departments (current) | M:1 | current_department_id | Current department |
| belongs to | positions (current) | M:1 | current_position_id | Current position |
| belongs to | work_locations (current) | M:1 | current_work_location_id | Current location |
| belongs to | departments (new) | M:1 | new_department_id | New department |
| belongs to | positions (new) | M:1 | new_position_id | New position |
| belongs to | work_locations (new) | M:1 | new_work_location_id | New location |
| belongs to | users (creator) | M:1 | created_by | Action creator |
| belongs to | users (updater) | M:1 | updated_by | Last updater |

**Total: 9 relationships**

---

### Table: resignations
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | employees | M:1 | employee_id | Resigning employee |
| belongs to | departments | M:1 | department_id | Department at resignation |
| belongs to | positions | M:1 | position_id | Position at resignation |
| belongs to | users | M:1 | acknowledged_by | Acknowledger |

**Total: 4 relationships**

---

### Table: interviews
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| (standalone) | - | - | - | No direct FK relationships |

**Total: 0 relationships**

---

### Table: job_offers
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| (standalone) | - | - | - | No direct FK relationships |

**Total: 0 relationships**

---

### Table: permissions
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| has many | model_has_permissions | 1:M | permission_id | Direct permission assignments |
| has many | role_has_permissions | 1:M | permission_id | Role permissions |

**Total: 2 relationships**

---

### Table: roles
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| has many | model_has_roles | 1:M | role_id | Role assignments |
| has many | role_has_permissions | 1:M | role_id | Role permissions |

**Total: 2 relationships**

---

### Table: model_has_permissions
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | permissions | M:1 | permission_id | Assigned permission |
| morphs to | users (or other models) | M:1 | model_id + model_type | Polymorphic relation |

**Total: 2 relationships**

---

### Table: model_has_roles
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | roles | M:1 | role_id | Assigned role |
| morphs to | users (or other models) | M:1 | model_id + model_type | Polymorphic relation |

**Total: 2 relationships**

---

### Table: role_has_permissions
| Relationship | Related Table | Type | Foreign Key | Description |
|-------------|---------------|------|-------------|-------------|
| belongs to | roles | M:1 | role_id | Role with permission |
| belongs to | permissions | M:1 | permission_id | Permission for role |

**Total: 2 relationships**

---

## Relationship Type Breakdown

| Type | Count | Description |
|------|-------|-------------|
| One-to-Many (1:M) | 45 | Parent-child relationships |
| Many-to-One (M:1) | 50 | Child-parent references |
| Self-Referencing | 1 | positions → positions (hierarchy) |
| Polymorphic | 4 | Spatie permission system |
| Indirect/Reference | 3 | Calculation references without FK |

**Total Relationships: 103** (bidirectional count)
**Total Unique Relationships: 59** (unidirectional count)

---

## Key Observations

### Most Connected Tables:
1. **employments** - 8 direct relationships (hub table)
2. **personnel_actions** - 9 relationships (captures full state)
3. **positions** - 8 relationships (org structure + hierarchy)
4. **employees** - 7 relationships (core entity)

### Standalone Tables:
- **interviews** - No FK relationships (recruitment tracking)
- **job_offers** - No FK relationships (offer management)

### Complex Relationships:
- **employee_funding_allocations** - Dual source (position_slot OR org_funded)
- **positions** - Self-referencing hierarchy
- **personnel_actions** - Captures both current and new state

---

**Generated**: 2025-11-08
**Database**: SQL Server
**Total Tables**: 29
**Total Relationships**: 59 unique / 103 bidirectional
