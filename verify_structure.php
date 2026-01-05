<?php

require_once __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n=== MODULE STRUCTURE (Matching sidebar-data.json) ===\n\n";

// Group by category
$modules = App\Models\Module::orderBy('order')->get();
$grouped = $modules->groupBy('category');

foreach ($grouped as $category => $categoryModules) {
    echo "📁 {$category}\n";
    echo str_repeat('-', 60)."\n";

    foreach ($categoryModules as $m) {
        $prefix = $m->is_parent ? '  📂' : ($m->parent_module ? '    └─' : '  📄');
        $parentInfo = $m->parent_module ? " (child of: {$m->parent_module})" : '';
        echo sprintf(
            "%s %-30s | route: %s%s\n",
            $prefix,
            $m->display_name,
            $m->route ?? 'null',
            $parentInfo
        );
    }
    echo "\n";
}

echo "\n=== STATISTICS ===\n";
echo 'Total modules: '.$modules->count()."\n";
echo 'Parent modules (is_parent=true): '.$modules->where('is_parent', true)->count()."\n";
echo 'Submenu modules (with parent_module): '.$modules->whereNotNull('parent_module')->count()."\n";
echo 'Standalone modules (no parent, is_parent=false): '.$modules->whereNull('parent_module')->where('is_parent', false)->count()."\n";

echo "\n=== PERMISSIONS ===\n";
$permissions = Spatie\Permission\Models\Permission::orderBy('name')->pluck('name')->toArray();
echo 'Total: '.count($permissions)."\n";

echo "\n=== ADMIN USER CHECK ===\n";
$admin = App\Models\User::where('email', 'admin@hrms.com')->first();
if ($admin) {
    echo "Admin exists: YES\n";
    echo "Has 'users.read': ".($admin->hasPermissionTo('users.read') ? 'YES' : 'NO')."\n";
    echo "Has 'roles.read': ".($admin->hasPermissionTo('roles.read') ? 'YES' : 'NO')."\n";
    echo 'Total permissions: '.$admin->getAllPermissions()->count()."\n";
}

echo "\n";
