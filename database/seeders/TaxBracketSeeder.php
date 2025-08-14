<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TaxBracket;
use Carbon\Carbon;

class TaxBracketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currentYear = date('Y');
        
        // Thai Income Tax Brackets for 2025 (Based on Thai Revenue Department)
        $taxBrackets = [
            [
                'min_income' => 0,
                'max_income' => 150000,
                'tax_rate' => 0,
                'bracket_order' => 1,
                'effective_year' => $currentYear,
                'is_active' => true,
                'description' => 'Tax-free bracket - Income up to ฿150,000',
                'created_by' => 'system',
            ],
            [
                'min_income' => 150001,
                'max_income' => 300000,
                'tax_rate' => 5,
                'bracket_order' => 2,
                'effective_year' => $currentYear,
                'is_active' => true,
                'description' => '5% tax bracket - Income ฿150,001 to ฿300,000',
                'created_by' => 'system',
            ],
            [
                'min_income' => 300001,
                'max_income' => 500000,
                'tax_rate' => 10,
                'bracket_order' => 3,
                'effective_year' => $currentYear,
                'is_active' => true,
                'description' => '10% tax bracket - Income ฿300,001 to ฿500,000',
                'created_by' => 'system',
            ],
            [
                'min_income' => 500001,
                'max_income' => 750000,
                'tax_rate' => 15,
                'bracket_order' => 4,
                'effective_year' => $currentYear,
                'is_active' => true,
                'description' => '15% tax bracket - Income ฿500,001 to ฿750,000',
                'created_by' => 'system',
            ],
            [
                'min_income' => 750001,
                'max_income' => 1000000,
                'tax_rate' => 20,
                'bracket_order' => 5,
                'effective_year' => $currentYear,
                'is_active' => true,
                'description' => '20% tax bracket - Income ฿750,001 to ฿1,000,000',
                'created_by' => 'system',
            ],
            [
                'min_income' => 1000001,
                'max_income' => 2000000,
                'tax_rate' => 25,
                'bracket_order' => 6,
                'effective_year' => $currentYear,
                'is_active' => true,
                'description' => '25% tax bracket - Income ฿1,000,001 to ฿2,000,000',
                'created_by' => 'system',
            ],
            [
                'min_income' => 2000001,
                'max_income' => 5000000,
                'tax_rate' => 30,
                'bracket_order' => 7,
                'effective_year' => $currentYear,
                'is_active' => true,
                'description' => '30% tax bracket - Income ฿2,000,001 to ฿5,000,000',
                'created_by' => 'system',
            ],
            [
                'min_income' => 5000001,
                'max_income' => null, // No upper limit
                'tax_rate' => 35,
                'bracket_order' => 8,
                'effective_year' => $currentYear,
                'is_active' => true,
                'description' => '35% tax bracket - Income above ฿5,000,000',
                'created_by' => 'system',
            ],
        ];

        // Delete existing brackets for current year to avoid duplicates
        TaxBracket::where('effective_year', $currentYear)->delete();

        // Insert new tax brackets
        foreach ($taxBrackets as $bracket) {
            TaxBracket::create([
                'min_income' => $bracket['min_income'],
                'max_income' => $bracket['max_income'],
                'tax_rate' => $bracket['tax_rate'],
                'bracket_order' => $bracket['bracket_order'],
                'effective_year' => $bracket['effective_year'],
                'is_active' => $bracket['is_active'],
                'description' => $bracket['description'],
                'created_by' => $bracket['created_by'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        $this->command->info('Tax brackets seeded successfully for year ' . $currentYear);
        
        // Also create brackets for next year (for planning purposes)
        $nextYear = $currentYear + 1;
        foreach ($taxBrackets as $bracket) {
            $bracket['effective_year'] = $nextYear;
            $bracket['description'] = str_replace($currentYear, $nextYear, $bracket['description']);
            
            TaxBracket::create([
                'min_income' => $bracket['min_income'],
                'max_income' => $bracket['max_income'],
                'tax_rate' => $bracket['tax_rate'],
                'bracket_order' => $bracket['bracket_order'],
                'effective_year' => $bracket['effective_year'],
                'is_active' => false, // Inactive until the year starts
                'description' => $bracket['description'],
                'created_by' => $bracket['created_by'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        $this->command->info('Tax brackets seeded successfully for year ' . $nextYear . ' (inactive)');
    }
}