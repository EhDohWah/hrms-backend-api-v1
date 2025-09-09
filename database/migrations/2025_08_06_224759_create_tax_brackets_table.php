<?php

use Carbon\Carbon;
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
        Schema::create('tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_income', 15, 2);
            $table->decimal('max_income', 15, 2)->nullable(); // NULL for highest bracket
            $table->decimal('tax_rate', 5, 2); // Percentage (e.g., 5.00 for 5%)
            $table->decimal('base_tax', 15, 2)->default(0); // Cumulative tax for optimization
            $table->integer('bracket_order');
            $table->integer('effective_year');
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['effective_year', 'is_active']);
            $table->index('bracket_order');
            $table->unique(['effective_year', 'bracket_order']);
        });

        // Insert Thai 2025 tax bracket data
        $this->insertThai2025TaxBrackets();
    }

    /**
     * Insert Thai 2025 tax bracket data
     */
    private function insertThai2025TaxBrackets(): void
    {
        $now = Carbon::now();
        $year = 2025;

        $brackets = [
            [
                'min_income' => 0,
                'max_income' => 150000,
                'tax_rate' => 0,
                'base_tax' => 0,
                'bracket_order' => 1,
                'effective_year' => $year,
                'is_active' => true,
                'description' => '0% - Income ฿0 to ฿150,000',
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'min_income' => 150001,
                'max_income' => 300000,
                'tax_rate' => 5,
                'base_tax' => 0,
                'bracket_order' => 2,
                'effective_year' => $year,
                'is_active' => true,
                'description' => '5% - Income ฿150,001 to ฿300,000',
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'min_income' => 300001,
                'max_income' => 500000,
                'tax_rate' => 10,
                'base_tax' => 7500,
                'bracket_order' => 3,
                'effective_year' => $year,
                'is_active' => true,
                'description' => '10% - Income ฿300,001 to ฿500,000',
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'min_income' => 500001,
                'max_income' => 750000,
                'tax_rate' => 15,
                'base_tax' => 27500,
                'bracket_order' => 4,
                'effective_year' => $year,
                'is_active' => true,
                'description' => '15% - Income ฿500,001 to ฿750,000',
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'min_income' => 750001,
                'max_income' => 1000000,
                'tax_rate' => 20,
                'base_tax' => 65000,
                'bracket_order' => 5,
                'effective_year' => $year,
                'is_active' => true,
                'description' => '20% - Income ฿750,001 to ฿1,000,000',
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'min_income' => 1000001,
                'max_income' => 2000000,
                'tax_rate' => 25,
                'base_tax' => 115000,
                'bracket_order' => 6,
                'effective_year' => $year,
                'is_active' => true,
                'description' => '25% - Income ฿1,000,001 to ฿2,000,000',
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'min_income' => 2000001,
                'max_income' => 5000000,
                'tax_rate' => 30,
                'base_tax' => 365000,
                'bracket_order' => 7,
                'effective_year' => $year,
                'is_active' => true,
                'description' => '30% - Income ฿2,000,001 to ฿5,000,000',
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'min_income' => 5000001,
                'max_income' => null,
                'tax_rate' => 35,
                'base_tax' => 1265000,
                'bracket_order' => 8,
                'effective_year' => $year,
                'is_active' => true,
                'description' => '35% - Income ฿5,000,001 and above',
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('tax_brackets')->insert($brackets);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_brackets');
    }
};
