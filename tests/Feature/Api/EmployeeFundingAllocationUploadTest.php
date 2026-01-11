<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\Employment;
use App\Models\Grant;
use App\Models\GrantItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeFundingAllocationUploadTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $employee;

    protected $employment;

    protected $grantItem;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create();

        // Create test employee
        $this->employee = Employee::factory()->create([
            'staff_id' => 'TEST001',
            'first_name_en' => 'Test',
            'last_name_en' => 'Employee',
        ]);

        // Create test employment
        $this->employment = Employment::factory()->create([
            'employee_id' => $this->employee->id,
            'pass_probation_salary' => 50000,
            'status' => true,
        ]);

        // Create test grant and grant item
        $grant = Grant::factory()->create([
            'code' => 'TEST-GRANT',
            'name' => 'Test Grant',
        ]);

        $this->grantItem = GrantItem::create([
            'grant_id' => $grant->id,
            'grant_position' => 'Test Position',
            'budgetline_code' => 'TEST-001',
            'grant_salary' => 50000,
            'grant_benefit' => 10000,
            'grant_position_number' => 5,
        ]);
    }

    /** @test */
    public function it_can_download_funding_allocation_template()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->get('/api/downloads/employee-funding-allocation-template');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function it_can_download_grant_items_reference()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->get('/api/downloads/grant-items-reference');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function it_validates_file_upload_request()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/uploads/employee-funding-allocation', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    /** @test */
    public function it_accepts_valid_excel_file()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('funding-allocation.xlsx', 100);

        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/uploads/employee-funding-allocation', [
                'file' => $file,
            ]);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'import_id',
                'status',
            ],
        ]);
    }

    /** @test */
    public function it_rejects_invalid_file_types()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/uploads/employee-funding-allocation', [
                'file' => $file,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    /** @test */
    public function it_rejects_files_exceeding_size_limit()
    {
        Storage::fake('local');

        // Create file larger than 10MB
        $file = UploadedFile::fake()->create('large-file.xlsx', 11000);

        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/uploads/employee-funding-allocation', [
                'file' => $file,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }
}
