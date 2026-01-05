<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check all user_management permissions
echo "Available user_management permissions:\n";
$perms = Spatie\Permission\Models\Permission::where('name', 'like', 'user_management%')->get();
foreach ($perms as $perm) {
    echo '  - '.$perm->name."\n";
}

// Check which users have these permissions
echo "\nUsers with user_management.edit:\n";
$users = App\Models\User::permission('user_management.edit')->get();
foreach ($users as $u) {
    echo '  - '.$u->username."\n";
}

// Check the first admin user's direct permissions
echo "\nAdmin user (ID 1) direct permissions:\n";
$adminUser = App\Models\User::find(1);
if ($adminUser) {
    $directPerms = $adminUser->getDirectPermissions()->pluck('name')->toArray();
    echo empty($directPerms) ? "  (none)\n" : implode(', ', $directPerms)."\n";

    $rolePerms = $adminUser->getPermissionsViaRoles()->pluck('name')->toArray();
    echo "\nPermissions via roles:\n";
    echo empty($rolePerms) ? "  (none)\n" : implode(', ', $rolePerms)."\n";
}

// Check if user_management module exists
echo "\nuser_management module:\n";
$module = App\Models\Module::where('name', 'user_management')->first();
if ($module) {
    echo '  Name: '.$module->name."\n";
    echo '  Display: '.$module->display_name."\n";
    echo '  Read Perm: '.$module->read_permission."\n";
} else {
    echo "  NOT FOUND\n";
}
