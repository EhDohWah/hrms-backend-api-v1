<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 50)->unique()->nullable();
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'deleted_at']);
        });

        // Seed the sites from updated list
        $sites = [
            ['name' => 'Expat',      'code' => 'EXPAT',     'description' => 'Expatriate Staff',         'is_active' => true],
            ['name' => 'KK-MCH',     'code' => 'KK_MCH',    'description' => 'KK-MCH site',               'is_active' => true],
            ['name' => 'TB-KK',      'code' => 'TB_KK',     'description' => 'TB-Koh Kong',               'is_active' => true],
            ['name' => 'MKT',        'code' => 'MKT',       'description' => 'MKT site',                  'is_active' => true],
            ['name' => 'MRM',        'code' => 'MRM',       'description' => 'Mae Ramat',   'is_active' => true],
            ['name' => 'MSL',        'code' => 'MSL',       'description' => 'MSL site',                  'is_active' => true],
            ['name' => 'Mutraw',     'code' => 'MUTRAW',    'description' => 'Mutraw site',               'is_active' => true],
            ['name' => 'TB-MRM',     'code' => 'TB_MRM',    'description' => 'TB-MRM',                    'is_active' => true],
            ['name' => 'WP',         'code' => 'WP',        'description' => 'WP site',                   'is_active' => true],
            ['name' => 'WPA',        'code' => 'WPA',       'description' => 'WPA site',                  'is_active' => true],
            ['name' => 'Yangon',     'code' => 'YANGON',    'description' => 'Yangon site',               'is_active' => true],
        ];

        foreach ($sites as $site) {
            DB::table('sites')->insert(array_merge($site, [
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'System',
                'updated_by' => 'System',
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
