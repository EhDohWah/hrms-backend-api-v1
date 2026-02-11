<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the leave_types table with the organization's leave categories.
 *
 * Environment: Production + Development
 * Idempotent: Yes — skips if leave types already exist
 * Dependencies: None
 */
class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('leave_types')->count() > 0) {
            $this->command->info('Leave types already seeded — skipping.');

            return;
        }

        DB::table('leave_types')->insert([
            [
                'name' => 'Annual Leave',
                'default_duration' => 26.00,
                'description' => 'Annual vacation / ลาพักร้อนประจำปี (Remain vacation/จำนวนวันลาพักร้อนคงเหลือ ............days/วัน)',
                'requires_attachment' => false,
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'system',
            ],
            [
                'name' => 'Unpaid Leave',
                'default_duration' => 0.00,
                'description' => 'Unpaid Leave / ลาป่วยไม่จ่ายเงิน (state disease/ระบุโรค) 30 days',
                'requires_attachment' => false,
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'system',
            ],
            [
                'name' => 'Traditional day-off',
                'default_duration' => 13.00,
                'description' => 'Traditional day-off /วันพุฒหาประเพณี (Specify Traditional day off/ระบุวันหยุด) need specific (Remaining traditional day-off/จำนวนวันพุฒหาประเพณี คงเหลือ ............days/วัน)',
                'requires_attachment' => false,
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'system',
            ],
            [
                'name' => 'Sick',
                'default_duration' => 30.00,
                'description' => 'Sick/ลาป่วย (state disease/ระบุโรค) 30 days',
                'requires_attachment' => false,
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'system',
            ],
            [
                'name' => 'Maternity leave',
                'default_duration' => 98.00,
                'description' => 'Maternity leave / Paternity leave วันหยุดมาตรดา 98 days',
                'requires_attachment' => false,
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'system',
            ],
            [
                'name' => 'Compassionate',
                'default_duration' => 5.00,
                'description' => 'Compassionate (spouse, children, mother, father, mother/father in law, siblings and grandparents died or severely sick/ญาติ ความ บุตร บิดา มารดา พ่อ/แม่สามี แม่/แม่ยาย พี่น้องชาย หรือสาว ซึ่งถึงแก่อาจกรรม)',
                'requires_attachment' => false,
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'system',
            ],
            [
                'name' => 'Career development training',
                'default_duration' => 14.00,
                'description' => 'Career development training (Career development training request form is optional/แบบฟอร์มขออนุญาตเข้าร่วมการอบรมพัฒนาโดยภายนอก เป็นทางเลือก) 14 days',
                'requires_attachment' => false,
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'system',
            ],
            [
                'name' => 'Personal leave',
                'default_duration' => 3.00,
                'description' => 'Personal leave/ลากิจธุระส่วนตัวเป็น (please specify/โปรดระบุ) 3 day (Remaining Personal leave/จำนวนวันลากิจธุระส่วนตัวคงเหลือ ............days/วัน)',
                'requires_attachment' => false,
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'system',
            ],
            [
                'name' => 'Military leave',
                'default_duration' => 60.00,
                'description' => 'Military leave / ลาเพื่อรับราชการทหาร 60 days',
                'requires_attachment' => false,
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'system',
            ],
            [
                'name' => 'Sterilization leave',
                'default_duration' => null,
                'description' => 'Sterilization leave/ ลาเพื่อทำหมัน (attach medical certificate/แนบใบรับรองแพทย์มาด้วย) Depends on doctor\'s consideration',
                'requires_attachment' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'system',
            ],
            [
                'name' => 'Other',
                'default_duration' => null,
                'description' => 'Other/ อื่น ๆ ',
                'requires_attachment' => false,
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'system',
            ],
        ]);

        $this->command->info('Leave types seeded: '.DB::table('leave_types')->count().' records.');
    }
}
