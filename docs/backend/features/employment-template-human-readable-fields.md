# Employment Template - Human-Readable Fields Update

## Overview
Updated the employment import template and upload logic to use human-readable names, codes, and titles instead of database IDs for better user experience.

## Changes Made

### 1. Template Field Changes

#### Previous Fields (ID-based)
- `department_id` - Required database ID
- `section_department_id` - Required database ID  
- `position_id` - Required database ID
- `site` - Site name

#### New Fields (Human-readable)
- `department` - Department name (e.g., "Human Resources", "Finance")
- `section_department` - Section department name (e.g., "Accounting", "Training")
- `position` - Position title (e.g., "HR Manager", "Software Developer")
- `site_code` - Site code (e.g., "MRM", "BHF", "SMRU")

### 2. Updated Files

#### Backend Controller
**File**: `app/Http/Controllers/Api/EmploymentController.php`
- Method: `downloadEmploymentTemplate()`
- Updated template headers
- Updated validation rules descriptions
- Updated sample data with realistic examples
- Updated column widths
- Updated instructions sheet

#### Import Logic
**File**: `app/Imports/EmploymentsImport.php`
- Added new model imports: `Department`, `Position`, `SectionDepartment`
- Added lookup arrays for name/code/title to ID mapping:
  - `$departmentLookup` - Maps department name → ID
  - `$sectionDepartmentLookup` - Maps section department name → ID
  - `$positionLookup` - Maps position title → ID
  - `$siteLookup` - Changed from name → ID to code → ID
- Updated field parsing in `collection()` method
- Updated validation rules in `rules()` method
- Added `parseBoolean()` helper method
- Updated date field normalization

### 3. Lookup Logic

The import now performs the following lookups during processing:

```php
// Site lookup by code
$siteCode = trim($row['site_code'] ?? '');
$siteId = $this->siteLookup[$siteCode] ?? null;

// Department lookup by name
$departmentName = trim($row['department'] ?? '');
$departmentId = $this->departmentLookup[$departmentName] ?? null;

// Section Department lookup by name
$sectionDepartmentName = trim($row['section_department'] ?? '');
$sectionDepartmentId = $this->sectionDepartmentLookup[$sectionDepartmentName] ?? null;

// Position lookup by title
$positionTitle = trim($row['position'] ?? '');
$positionId = $this->positionLookup[$positionTitle] ?? null;
```

### 4. Template Sample Data

The template now includes realistic sample data:

| staff_id | employment_type | site_code | department | section_department | position |
|----------|----------------|-----------|------------|-------------------|----------|
| EMP001 | Full-time | MRM | Human Resources | | HR Manager |
| EMP002 | Part-time | BHF | Finance | Accounting | Accountant |
| EMP003 | Contract | SMRU | IT | | Software Developer |

### 5. Validation Rules

Updated validation rules to match new field names:

```php
'*.staff_id' => 'required|string',
'*.employment_type' => 'required|string|in:Full-time,Part-time,Contract,Temporary',
'*.start_date' => 'required|date',
'*.pass_probation_salary' => 'required|numeric',
'*.site_code' => 'nullable|string',
'*.department' => 'nullable|string',
'*.section_department' => 'nullable|string',
'*.position' => 'nullable|string',
// ... other fields
```

## Benefits

### 1. User Experience
- Users can fill in the template without looking up database IDs
- More intuitive and less error-prone
- Easier to understand and maintain

### 2. Data Integrity
- System validates that names/codes/titles exist in the database
- Automatic ID resolution during import
- Clear error messages if lookup fails

### 3. Flexibility
- Users can leave optional fields empty
- System handles case-sensitive matching
- Supports partial data imports

## Usage Instructions

### For Users

1. **Download Template**
   - Navigate to Uploads section
   - Click "Download Template" for Employment Records
   - Template includes instructions and sample data

2. **Fill Template**
   - Use exact names/codes/titles as they appear in the system
   - For `site_code`: Use site codes like "MRM", "BHF", "SMRU"
   - For `department`: Use full department name like "Human Resources"
   - For `section_department`: Use section name like "Accounting"
   - For `position`: Use exact position title like "HR Manager"

3. **Upload File**
   - Save completed Excel file
   - Upload through the Employment Upload interface
   - System will validate and import data

### For Developers

#### Adding New Lookup Fields

To add a new lookup field:

1. Add the lookup array property:
```php
protected $newFieldLookup = [];
```

2. Initialize in constructor:
```php
$this->newFieldLookup = Model::pluck('id', 'lookup_field')->toArray();
```

3. Parse in collection method:
```php
$newFieldValue = trim($row['new_field'] ?? '');
$newFieldId = $this->newFieldLookup[$newFieldValue] ?? null;
```

4. Add to employment data:
```php
'new_field_id' => $newFieldId,
```

## Error Handling

The import provides clear error messages when lookups fail:

- "Employee with staff_id 'XXX' not found in database"
- "Invalid employment type 'XXX'"
- "Missing start date"
- "Missing or invalid pass_probation_salary"

If a name/code/title doesn't exist in the database, the field will be set to `null` (for nullable fields) or the row will be skipped with an error message.

## Testing

### Test Cases

1. **Valid Data Import**
   - All fields with valid names/codes/titles
   - Should successfully create employment records

2. **Missing Optional Fields**
   - Only required fields provided
   - Should create records with null optional fields

3. **Invalid Lookups**
   - Non-existent department name
   - Non-existent site code
   - Should handle gracefully (set to null or skip)

4. **Case Sensitivity**
   - Test with different cases
   - Verify exact matching

### Manual Testing Steps

1. Download the template
2. Fill with test data using valid names/codes/titles
3. Upload the file
4. Verify records created with correct IDs
5. Check that lookups resolved correctly

## Migration Notes

### Breaking Changes
- Old templates using IDs will no longer work
- Users must download new template
- Existing import files need to be updated

### Backward Compatibility
- None - this is a breaking change
- All users must use the new template format

## Future Enhancements

1. **Case-Insensitive Matching**
   - Add case-insensitive lookup support
   - Improve user experience

2. **Fuzzy Matching**
   - Suggest similar names if exact match not found
   - Help users identify typos

3. **Validation Preview**
   - Show validation results before import
   - Allow users to fix errors before processing

4. **Bulk Lookup API**
   - Provide API endpoint to validate names/codes/titles
   - Enable frontend validation before upload

## Related Files

- `app/Http/Controllers/Api/EmploymentController.php`
- `app/Imports/EmploymentsImport.php`
- `app/Models/Department.php`
- `app/Models/Position.php`
- `app/Models/SectionDepartment.php`
- `app/Models/Site.php`
- `app/Models/Employment.php`

## Support

For issues or questions:
1. Check the template instructions sheet
2. Verify names/codes/titles exist in the system
3. Review error messages in import notification
4. Contact system administrator if needed
