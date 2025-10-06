# Grant Management Database Schema Documentation

## Overview

This document explains the three-tier structure for managing grant-funded positions: from budget planning to individual position tracking to actual employee assignments.

## Database Schema Structure

### 1. grant_items (Budget Planning Level)
**Purpose**: Defines the budgeted position types and their allocations within grants.

**Key Fields:**
- `grant_id`: Links to the parent grant
- `grant_position`: Position title (e.g., "Research Assistant", "Software Developer")
- `grant_salary`: Budgeted salary per position
- `grant_benefit`: Budgeted benefits per position
- `grant_level_of_effort`: Expected effort percentage
- `grant_position_number`: **Total number of positions available** for this role

**Example Record:**
```
grant_id: 101
grant_position: "Research Assistant"
grant_salary: $60,000
grant_benefit: $12,000
grant_position_number: 4
```
*This means: "Grant 101 has budget for 4 Research Assistant positions at $60K salary + $12K benefits each"*

### 2. position_slots (Individual Position Management Level)
**Purpose**: Creates individual trackable positions from the budgeted position types.

**Key Fields:**
- `grant_item_id`: Links to the parent budget line item
- `slot_number`: Sequential number identifying this specific position (1, 2, 3, 4)
- `position_slots_salary`: Salary for this specific slot (may vary from budget)
- `budget_line_id`: Additional budget line reference if needed

**Example Records:**
```
grant_item_id: 501, slot_number: 1, position_slots_salary: $60,000
grant_item_id: 501, slot_number: 2, position_slots_salary: $60,000
grant_item_id: 501, slot_number: 3, position_slots_salary: $58,000
grant_item_id: 501, slot_number: 4, position_slots_salary: $62,000
```
*This creates 4 individual Research Assistant positions that can be independently managed and filled*

### 3. employee_funding_allocations (Actual Assignment Level)
**Purpose**: Links real employees to specific funded positions with actual allocation amounts.

**Key Fields:**
- `employee_id`: The actual employee assigned
- `employment_id`: Employment record reference
- `org_funded_id`: Organization funding source
- `position_slot_id`: **Links to specific position slot**
- `allocation_type`: Type of allocation (salary, benefits, etc.)
- `allocated_amount`: Actual amount allocated to this employee
- `level_of_effort`: Actual effort percentage
- `start_date` / `end_date`: Assignment period

**Example Records:**
```
employee_id: 1001, position_slot_id: 1, allocated_amount: $60,000, level_of_effort: 100%
employee_id: 1002, position_slot_id: 3, allocated_amount: $58,000, level_of_effort: 100%
```
*This shows Employee 1001 is assigned to slot 1, Employee 1002 to slot 3. Slots 2 and 4 remain unfilled*

## Data Flow and Relationships

### Step 1: Budget Planning (grant_items)
```
Grant Proposal: "We need 4 Research Assistants at $60K each"
↓
Creates grant_items record with grant_position_number: 4
```

### Step 2: Position Creation (position_slots)
```
Budget approved → System creates 4 individual position_slots
Slot 1: Available
Slot 2: Available  
Slot 3: Available
Slot 4: Available
```

### Step 3: Employee Assignment (employee_funding_allocations)
```
Hiring Process:
Employee A hired → Assigned to Slot 1
Employee B hired → Assigned to Slot 3

Status:
Slot 1: Filled (Employee A)
Slot 2: Available
Slot 3: Filled (Employee B)  
Slot 4: Available
```

## Why This Three-Tier Structure?

### Without position_slots (Direct grant_items → employee_funding_allocations):
**Problems:**
- ❌ Can't track which specific position each employee fills
- ❌ No clear way to manage vacant positions
- ❌ Complex queries to determine availability
- ❌ Difficult to track hiring pipeline per position
- ❌ No individual position management (closing/freezing specific slots)

### With position_slots:
**Benefits:**
- ✅ Clear inventory of available positions
- ✅ Individual position tracking and management
- ✅ Easy availability reporting: "2 of 4 positions filled"
- ✅ One-to-one employee assignment clarity
- ✅ Simplified hiring workflow management
- ✅ Historical tracking of position usage

## Common Use Cases

### 1. Position Availability Report
```sql
SELECT 
    gi.grant_position,
    gi.grant_position_number as total_budgeted,
    COUNT(efa.employee_id) as positions_filled,
    (gi.grant_position_number - COUNT(efa.employee_id)) as positions_available
FROM grant_items gi
LEFT JOIN position_slots ps ON gi.id = ps.grant_item_id
LEFT JOIN employee_funding_allocations efa ON ps.id = efa.position_slot_id
GROUP BY gi.id;
```

### 2. Finding Available Position Slots
```sql
SELECT ps.* 
FROM position_slots ps
LEFT JOIN employee_funding_allocations efa ON ps.id = efa.position_slot_id
WHERE efa.position_slot_id IS NULL;
```

### 3. Budget vs Actual Spending
```sql
SELECT 
    gi.grant_position,
    gi.grant_salary * gi.grant_position_number as total_budgeted,
    SUM(efa.allocated_amount) as total_allocated
FROM grant_items gi
LEFT JOIN position_slots ps ON gi.id = ps.grant_item_id
LEFT JOIN employee_funding_allocations efa ON ps.id = efa.position_slot_id
GROUP BY gi.id;
```

## Key Design Principles

1. **Separation of Concerns**:
   - Budget planning (grant_items)
   - Position inventory (position_slots)
   - Employee assignments (employee_funding_allocations)

2. **Individual Position Tracking**: Each funded position is a discrete, manageable entity

3. **Flexibility**: Actual allocations can differ from budgeted amounts

4. **Auditability**: Clear trail from budget to actual spending

5. **Scalability**: Easy to add more positions or modify existing ones

This structure provides comprehensive grant management capabilities while maintaining data integrity and supporting complex reporting requirements.