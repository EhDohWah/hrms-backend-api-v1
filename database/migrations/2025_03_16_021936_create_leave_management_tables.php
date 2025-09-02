<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Leave Types - Enhanced
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->decimal('default_duration', 18, 2)->nullable();
            $table->text('description')->nullable();
            $table->boolean('requires_attachment')->default(false);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        // Leave Requests - Enhanced
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 18, 2);
            $table->text('reason')->nullable();
            $table->string('status', 50)->default('pending');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            // Indexes for performance
            $table->index(['employee_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        // Leave Balances - Enhanced
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->decimal('total_days', 18, 2)->default(0);
            $table->decimal('used_days', 18, 2)->default(0);
            $table->decimal('remaining_days', 18, 2)->default(0);
            $table->year('year')->default(date('Y'));
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            // Unique constraint to prevent duplicate records
            $table->unique(['employee_id', 'leave_type_id', 'year']);

            // Index for performance
            $table->index(['employee_id', 'year']);
        });

        // Leave Request Approvals - Enhanced
        Schema::create('leave_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests');
            $table->string('approver_role', 100)->nullable();
            $table->string('approver_name', 200)->nullable();
            $table->string('approver_signature', 200)->nullable();
            $table->date('approval_date')->nullable();
            $table->string('status', 50)->default('pending');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            // Index for performance
            $table->index(['leave_request_id', 'status']);
        });

        // NEW: Leave Attachments Table
        Schema::create('leave_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->onDelete('cascade');
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->timestamp('uploaded_at')->useCurrent();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            // Index for performance
            $table->index('leave_request_id');
        });

        // Traditional Leaves - Keep as is
        Schema::create('traditional_leaves', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable();
            $table->text('description')->nullable();
            $table->date('date')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        // Insert leave types data immediately after table creation
        $this->seedLeaveTypes();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop in this order to respect foreign key constraints:
        Schema::dropIfExists('leave_attachments');
        Schema::dropIfExists('leave_request_approvals');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('traditional_leaves');
        Schema::dropIfExists('leave_types');
    }

    /**
     * Seed leave types based on the form requirements
     */
    private function seedLeaveTypes()
    {
        $leaveTypes = [
            [
                'name' => 'Annual vacation',
                'default_duration' => 26.00,
                'description' => 'Annual vacation / ลาพักร้อนประจำปี (Remain vacation/จำนวนวันลาพักร้อนคงเหลือ ............days/วัน)',
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
                'description' => 'Career development training (Please attach with Career development training request form/กรุณาแนบแบบฟอร์มขออนุญาตเข้าร่วมการอบรมพัฒนาโดยภายนอกให้แก่ กยุกอนพพุ่อรออำนิยสำนักงำน) 14 days',
                'requires_attachment' => true,
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
        ];

        DB::table('leave_types')->insert($leaveTypes);
    }
};
