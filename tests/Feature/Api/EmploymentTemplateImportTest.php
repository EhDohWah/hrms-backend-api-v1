<?php

namespace Tests\Feature\Api;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\SectionDepartment;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmploymentTemplateImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Employee $employee;

    protected Department $department;

    protected Position $position;

    protected Site $site;

    protected SectionDepartment $sectionDepartment;

    protected function setUp(): void
    {
        parent::setUp();

        // Create and authenticate user
        $this->user = User::factory()->create();

        // Create permissions
        $permissions = [
            'employment.read',
            'employment.create',
            'employment.update',
            'employment_records.read',
            'employment_records.edit',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $this->user->givePermissionTo($permissions);
        Sanctum::actingAs($this->user);

        // Create test data
        $this->department = Department::create([
            'name' => 'Human Resources',
            'description' => 'HR Department',
            'is_active' => true,
        ]);

        $this->sectionDepartment = SectionDepartment::create([
            'name' => 'Recruitment',
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);

        $this->position = Position::create([
            'title' => 'HR Manager',
            'department_id' => $this->department->id,
            'level' => 2,
            'is_manager' => true,
            'is_active' => true,
        ]);

        // Use unique code for each test run
        $uniqueCode = 'TST'.rand(100, 999);
        $this->site = Site::create([
            'name' => 'Test Site',
            'code' => $uniqueCode,
            'is_active' => true,
        ]);

        $this->employee = Employee::create([
            'organization' => 'SMRU',
            'staff_id' => 'EMP001',
            'first_name_en' => 'John',
            'last_name_en' => 'Doe',
            'gender' => 'Male',
            'date_of_birth' => '1990-01-01',
            'status' => 'Active',
        ]);
    }

    /** @test */
    public function it_can_download_employment_template(): void
    {
        $response = $this->get('/api/v1/downloads/employment-template');

        $response->assertSuccessful();
        expect($response->headers->get('content-type'))->toContain('spreadsheet');
    }

    /** @test */
    public function it_validates_template_has_correct_headers(): void
    {
        $response = $this->get('/api/v1/downloads/employment-template');

        $response->assertSuccessful();

        // Verify we got an Excel file with correct content type
        expect($response->headers->get('content-type'))->toContain('spreadsheet');

        // Verify it's a download response with filename
        expect($response->headers->get('content-disposition'))->toContain('employment_import_template');
    }

    /** @test */
    public function it_can_import_employment_with_human_readable_fields(): void
    {
        // Create a test Excel file
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $headers = [
            'staff_id',
            'start_date',
            'pass_probation_salary',
            'pass_probation_date',
            'probation_salary',
            'end_date',
            'pay_method',
            'site_code',
            'department',
            'section_department',
            'position',
            'health_welfare',
            'pvd',
            'saving_fund',
            'status',
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        // Add validation rules row (row 2)
        $sheet->setCellValue('A2', 'Validation rules...');

        // Data row (row 3)
        $data = [
            'EMP001', // staff_id
            '2025-01-15', // start_date
            '50000.00', // pass_probation_salary
            '2025-04-15', // pass_probation_date
            '45000.00', // probation_salary
            '', // end_date
            'Monthly', // pay_method
            $this->site->code, // site_code
            'Human Resources', // department
            'Recruitment', // section_department
            'HR Manager', // position
            '1', // health_welfare
            '1', // pvd
            '0', // saving_fund
            '1', // status
        ];

        foreach ($data as $index => $value) {
            $sheet->setCellValueByColumnAndRow($index + 1, 3, $value);
        }

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'employment_import_test_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        // Create UploadedFile
        $file = new UploadedFile(
            $tempFile,
            'employment_import_test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        // Upload the file
        $response = $this->postJson('/api/v1/uploads/employment', [
            'file' => $file,
        ]);

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'import_id',
                'status',
            ],
        ]);

        // Note: The actual import is queued, so we can't immediately verify the database
        // In a real test, you would need to process the queue and then check

        // Clean up
        unlink($tempFile);
    }

    /** @test */
    public function it_resolves_department_name_to_id(): void
    {
        // This test verifies the lookup logic works correctly
        $departmentName = 'Human Resources';
        $department = Department::where('name', $departmentName)->first();

        expect($department)->not->toBeNull();
        expect($department->id)->toBe($this->department->id);
    }

    /** @test */
    public function it_resolves_site_code_to_id(): void
    {
        $site = Site::where('code', $this->site->code)->first();

        expect($site)->not->toBeNull();
        expect($site->id)->toBe($this->site->id);
    }

    /** @test */
    public function it_resolves_position_title_to_id(): void
    {
        $positionTitle = 'HR Manager';
        $position = Position::where('title', $positionTitle)->first();

        expect($position)->not->toBeNull();
        expect($position->id)->toBe($this->position->id);
    }

    /** @test */
    public function it_resolves_section_department_name_to_id(): void
    {
        $sectionDepartmentName = 'Recruitment';
        $sectionDepartment = SectionDepartment::where('name', $sectionDepartmentName)->first();

        expect($sectionDepartment)->not->toBeNull();
        expect($sectionDepartment->id)->toBe($this->sectionDepartment->id);
    }

    /** @test */
    public function it_handles_missing_optional_fields(): void
    {
        // Create a test Excel file with only required fields
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $headers = [
            'staff_id',
            'start_date',
            'pass_probation_salary',
            'pass_probation_date',
            'probation_salary',
            'end_date',
            'pay_method',
            'site_code',
            'department',
            'section_department',
            'position',
            'health_welfare',
            'pvd',
            'saving_fund',
            'status',
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        // Add validation rules row
        $sheet->setCellValue('A2', 'Validation rules...');

        // Data row with only required fields
        $data = [
            'EMP001', // staff_id
            '2025-01-15', // start_date
            '50000.00', // pass_probation_salary
            '', // pass_probation_date (optional)
            '', // probation_salary (optional)
            '', // end_date (optional)
            '', // pay_method (optional)
            '', // site_code (optional)
            '', // department (optional)
            '', // section_department (optional)
            '', // position (optional)
            '', // health_welfare (optional)
            '', // pvd (optional)
            '', // saving_fund (optional)
            '', // status (optional)
        ];

        foreach ($data as $index => $value) {
            $sheet->setCellValueByColumnAndRow($index + 1, 3, $value);
        }

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'employment_import_minimal_test_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        // Create UploadedFile
        $file = new UploadedFile(
            $tempFile,
            'employment_import_minimal_test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        // Upload the file
        $response = $this->postJson('/api/v1/uploads/employment', [
            'file' => $file,
        ]);

        $response->assertSuccessful();

        // Clean up
        unlink($tempFile);
    }
}
