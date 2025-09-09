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
        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 50);
            $table->decimal('setting_value', 15, 2);
            $table->string('setting_type', 30); // 'ALLOWANCE', 'DEDUCTION', 'RATE', 'LIMIT'
            $table->string('description')->nullable();
            $table->integer('effective_year');
            $table->boolean('is_selected')->default(true); // Global toggle control
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();

            // Indexes
            $table->index('setting_key');
            $table->index(['setting_type', 'effective_year']);
            $table->index(['effective_year', 'is_selected']); // Updated index
            $table->index(['is_selected', 'effective_year']); // New index for selected queries
            $table->unique(['setting_key', 'effective_year']);
        });

        // Insert Thai 2025 tax settings data
        $this->insertThai2025TaxSettings();
    }

    /**
     * Insert Thai 2025 tax settings data
     */
    private function insertThai2025TaxSettings(): void
    {
        $now = Carbon::now();
        $year = 2025;

        $settings = [
            // Employment deductions (Personal Expense 50%, max 100k)
            [
                'setting_key' => 'EMPLOYMENT_DEDUCTION_RATE',
                'setting_value' => 50.00,
                'setting_type' => 'DEDUCTION',
                'description' => 'Personal Expense deduction rate (50% of income)',
                'effective_year' => $year,
                'is_selected' => true,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'EMPLOYMENT_DEDUCTION_MAX',
                'setting_value' => 100000.00,
                'setting_type' => 'LIMIT',
                'description' => 'Maximum Personal Expense deduction (฿100,000)',
                'effective_year' => $year,
                'is_selected' => true,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Personal allowances
            [
                'setting_key' => 'PERSONAL_ALLOWANCE',
                'setting_value' => 60000.00,
                'setting_type' => 'ALLOWANCE',
                'description' => 'Personal allowance per taxpayer (฿60,000)',
                'effective_year' => $year,
                'is_selected' => true,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'SPOUSE_ALLOWANCE',
                'setting_value' => 60000.00,
                'setting_type' => 'ALLOWANCE',
                'description' => 'Spouse allowance (if no income) - Available ฿60,000',
                'effective_year' => $year,
                'is_selected' => false, // Set to ฿0 (disabled by default as requested)
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'CHILD_ALLOWANCE',
                'setting_value' => 30000.00,
                'setting_type' => 'ALLOWANCE',
                'description' => 'Child allowance (first child) - Available ฿30,000',
                'effective_year' => $year,
                'is_selected' => false, // Set to ฿0 (disabled by default as requested)
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'CHILD_ALLOWANCE_SUBSEQUENT',
                'setting_value' => 60000.00,
                'setting_type' => 'ALLOWANCE',
                'description' => 'Child allowance (subsequent children born 2018+) - Available ฿60,000',
                'effective_year' => $year,
                'is_selected' => false, // Set to ฿0 (disabled by default as requested)
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Provident Fund - Thai Citizens (PVD Fund)
            [
                'setting_key' => 'PVD_FUND_RATE',
                'setting_value' => 7.5,
                'setting_type' => 'RATE',
                'description' => 'PVD Fund contribution rate for Thai citizens (Local ID Staff) - 7.5% of annual income',
                'effective_year' => $year,
                'is_selected' => true,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'PVD_FUND_MAX',
                'setting_value' => 500000.00,
                'setting_type' => 'LIMIT',
                'description' => 'PVD Fund maximum annual deduction for Thai citizens (฿500,000)',
                'effective_year' => $year,
                'is_selected' => true,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Saving Fund - Non-Thai Citizens
            [
                'setting_key' => 'SAVING_FUND_RATE',
                'setting_value' => 7.5,
                'setting_type' => 'RATE',
                'description' => 'Saving Fund contribution rate for non-Thai citizens (Local non ID Staff) - 7.5% of annual income',
                'effective_year' => $year,
                'is_selected' => true,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'SAVING_FUND_MAX',
                'setting_value' => 500000.00,
                'setting_type' => 'LIMIT',
                'description' => 'Saving Fund maximum annual deduction for non-Thai citizens (฿500,000)',
                'effective_year' => $year,
                'is_selected' => true,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Social Security Fund (฿9,000 as requested)
            [
                'setting_key' => 'SSF_RATE',
                'setting_value' => 5.0,
                'setting_type' => 'DEDUCTION',
                'description' => 'Social Security Fund rate (5%)',
                'effective_year' => $year,
                'is_selected' => true,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'setting_key' => 'SSF_MAX_ANNUAL',
                'setting_value' => 9000.00,
                'setting_type' => 'LIMIT',
                'description' => 'Maximum annual Social Security (฿9,000)',
                'effective_year' => $year,
                'is_selected' => true,
                'created_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('tax_settings')->insert($settings);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_settings');
    }
};
