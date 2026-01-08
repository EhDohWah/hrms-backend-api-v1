# Employee Funding Allocation Upload - Permissions Setup

**Date:** January 8, 2026  
**Issue:** 403 Forbidden when trying to download template  
**Status:** ✅ RESOLVED

---

## Problem

When trying to download the employee funding allocation template, users received a 403 Forbidden error:

```
"message": "User does not have the right permissions.",
"exception": "Spatie\\Permission\\Exceptions\\UnauthorizedException"
```

The route was protected with:
```php
->middleware('permission:employee_funding_allocations.read')
```

But the permissions didn't exist in the database.

---

## Solution

### 1. Added Module to ModuleSeeder

Added the new module entry to `database/seeders/ModuleSeeder.php`:

```php
[
    'name' => 'employee_funding_allocations',
    'display_name' => 'Employee Funding Allocations',
    'description' => 'Manage employee funding allocations',
    'icon' => 'chart-pie',
    'category' => 'Employee',
    'route' => '/employee/funding-allocations',
    'active_link' => '/employee/funding-allocations',
    'read_permission' => 'employee_funding_allocations.read',
    'edit_permissions' => ['employee_funding_allocations.edit'],
    'order' => 32,
],
```

**Location:** In the Employee category section, after `employment_records` (line 124-134)

### 2. Created Permissions

Ran the seeder to create the module:

```bash
php artisan db:seed --class=ModuleSeeder
```

This creates the module in the `modules` table.

### 3. Created Permission Records

Created the actual permission records in the `permissions` table:

```php
DB::table('permissions')->insert([
    [
        'name' => 'employee_funding_allocations.read',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now()
    ],
    [
        'name' => 'employee_funding_allocations.edit',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now()
    ]
]);
```

### 4. Assigned Permissions to Roles

Assigned both permissions to the Admin role:

```php
$adminRole = Role::where('name', 'Admin')->first();
$adminRole->givePermissionTo([
    'employee_funding_allocations.read',
    'employee_funding_allocations.edit'
]);
```

### 5. Cleared Permission Cache

```bash
php artisan permission:cache-reset
php artisan cache:clear
```

---

## Permissions Created

### Read Permission
- **Name:** `employee_funding_allocations.read`
- **Purpose:** View funding allocation data, download templates
- **Routes Protected:**
  - `GET /downloads/employee-funding-allocation-template`
  - `GET /employee-funding-allocations`
  - Other read endpoints

### Edit Permission
- **Name:** `employee_funding_allocations.edit`
- **Purpose:** Upload files, create/update/delete allocations
- **Routes Protected:**
  - `POST /uploads/employee-funding-allocation`
  - `POST /employee-funding-allocations`
  - `PUT /employee-funding-allocations/:id`
  - `DELETE /employee-funding-allocations/:id`

---

## Module Configuration

**Category:** Employee  
**Order:** 32 (between Employment Records and Employee Resignation)  
**Icon:** chart-pie  
**Route:** `/employee/funding-allocations`

---

## For New Installations

If setting up a new instance, ensure permissions are created:

### Option 1: Run Module Seeder

```bash
php artisan db:seed --class=ModuleSeeder
```

This will create the module and should trigger permission creation.

### Option 2: Manual Creation (if needed)

```bash
php artisan tinker
```

```php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Create permissions
Permission::create(['name' => 'employee_funding_allocations.read', 'guard_name' => 'web']);
Permission::create(['name' => 'employee_funding_allocations.edit', 'guard_name' => 'web']);

// Assign to Admin role
$admin = Role::where('name', 'Admin')->first();
$admin->givePermissionTo(['employee_funding_allocations.read', 'employee_funding_allocations.edit']);

// Clear cache
\Artisan::call('permission:cache-reset');
```

### Option 3: Use Permission Seeder (if exists)

Check if your project has a `PermissionSeeder` or `RolePermissionSeeder`, and add:

```php
'employee_funding_allocations' => [
    'read',
    'edit'
]
```

---

## Verification

### Check Permissions Exist

```sql
SELECT * FROM permissions WHERE name LIKE 'employee_funding_allocations%';
```

Expected result: 2 rows
- `employee_funding_allocations.read`
- `employee_funding_allocations.edit`

### Check Role Has Permissions

```sql
SELECT p.name 
FROM permissions p
JOIN role_has_permissions rhp ON p.id = rhp.permission_id
JOIN roles r ON rhp.role_id = r.id
WHERE r.name = 'Admin'
AND p.name LIKE 'employee_funding_allocations%';
```

Expected result: 2 rows

### Test Template Download

```bash
curl -X GET http://localhost:8000/api/v1/downloads/employee-funding-allocation-template \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -I
```

Expected: `200 OK` with file download

### Test Upload Endpoint

```bash
curl -X POST http://localhost:8000/api/v1/uploads/employee-funding-allocation \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@test.xlsx" \
  -I
```

Expected: `202 Accepted` (if file is valid)

---

## Troubleshooting

### Still Getting 403 Error

1. **Clear all caches:**
   ```bash
   php artisan permission:cache-reset
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   ```

2. **Verify user has the role:**
   ```sql
   SELECT r.name 
   FROM roles r
   JOIN model_has_roles mhr ON r.id = mhr.role_id
   WHERE mhr.model_id = YOUR_USER_ID;
   ```

3. **Check if role has permissions:**
   ```php
   $user = User::find(YOUR_USER_ID);
   dd($user->getAllPermissions()->pluck('name'));
   ```

4. **Re-login:** Sometimes permissions are cached in the user's session. Log out and log back in.

### Permission Not Found Error

```
There is no permission named `employee_funding_allocations.read` for guard `web`
```

**Solution:**
1. Check guard_name is correct (`web` or `api`)
2. Run permission cache reset
3. Verify permission exists in database
4. Try recreating the permission

### Wrong Guard

If using API authentication (Sanctum), you might need to create permissions with `guard_name = 'api'`:

```php
Permission::create(['name' => 'employee_funding_allocations.read', 'guard_name' => 'api']);
Permission::create(['name' => 'employee_funding_allocations.edit', 'guard_name' => 'api']);
```

---

## Best Practices

1. **Always use the ModuleSeeder** for adding new modules
2. **Run seeders in order** if dependencies exist
3. **Clear caches** after permission changes
4. **Test with different roles** (Admin, Manager, User)
5. **Document** any custom permissions
6. **Version control** seeder changes

---

## Related Files

- `database/seeders/ModuleSeeder.php` - Module definitions
- `routes/api/uploads.php` - Protected routes
- `app/Http/Controllers/Api/EmployeeFundingAllocationController.php` - Controller
- Frontend: `src/views/pages/administration/file-uploads/file-uploads-list.vue`

---

## Summary

✅ Module added to ModuleSeeder  
✅ Permissions created in database (via PermissionRoleSeeder)  
✅ Permissions assigned to Admin role (via UserSeeder)  
✅ Cache cleared  
✅ Template download working  
✅ Upload endpoint accessible  

**Total Modules:** 52 (was 51, now 52)  
**Employee Category:** 4 modules (was 3, now 4)  
**Total Permissions:** 104 (was 102, added 2 for employee_funding_allocations)

## Seeding Order

To properly set up permissions, seeders must be run in this order:

1. **ModuleSeeder** - Creates module definitions with permission names
2. **PermissionRoleSeeder** - Reads modules and creates permission records in database
3. **UserSeeder** - Syncs all permissions to admin and HR manager users
4. **Clear cache** - `php artisan permission:cache-reset`

```bash
php artisan db:seed --class=ModuleSeeder
php artisan db:seed --class=PermissionRoleSeeder
php artisan db:seed --class=UserSeeder
php artisan permission:cache-reset
```

