<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Employment;
use App\Models\GrantPosition;

/**
 * @OA\Schema(
 *     schema="Employee",
 *     required={"staff_id", "first_name", "last_name", "gender", "date_of_birth", "status"},
 *     @OA\Property(property="id", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="staff_id", type="string", maxLength=50),
 *     @OA\Property(property="subsidiary", type="string", enum={"SMRU", "BHF"}, default="SMRU"),
 *     @OA\Property(property="user_id", type="integer", nullable=true),
 *     @OA\Property(property="first_name", type="string", maxLength=255),
 *     @OA\Property(property="middle_name", type="string", maxLength=255, nullable=true),
 *     @OA\Property(property="last_name", type="string", maxLength=255),
 *     @OA\Property(property="gender", type="string", maxLength=10),
 *     @OA\Property(property="date_of_birth", type="string", format="date"),
 *     @OA\Property(property="status", type="string", enum={"Expats", "Local ID", "Local non ID"}, default="Expats"),
 *     @OA\Property(property="religion", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="birth_place", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="identification_number", type="string", maxLength=50, nullable=true),
 *     @OA\Property(property="social_security_number", type="string", maxLength=50, nullable=true),
 *     @OA\Property(property="tax_identification_number", type="string", maxLength=50, nullable=true),
 *     @OA\Property(property="passport_number", type="string", maxLength=50, nullable=true),
 *     @OA\Property(property="bank_name", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="bank_branch", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="bank_account_name", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="bank_account_number", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="office_phone", type="string", maxLength=20, nullable=true),
 *     @OA\Property(property="mobile_phone", type="string", maxLength=20, nullable=true),
 *     @OA\Property(property="permanent_address", type="string", nullable=true),
 *     @OA\Property(property="current_address", type="string", nullable=true),
 *     @OA\Property(property="stay_with", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="military_status", type="boolean", default=false),
 *     @OA\Property(property="marital_status", type="string", maxLength=20, nullable=true),
 *     @OA\Property(property="spouse_name", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="spouse_occupation", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="father_name", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="father_occupation", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="mother_name", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="mother_occupation", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="driver_license_number", type="string", maxLength=50, nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", readOnly=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", readOnly=true)
 * )
 */
class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'subsidiary',
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'date_of_birth',
        'status',
        'religion',
        'birth_place',
        'identification_number',
        'social_security_number',
        'tax_identification_number',
        'passport_number',
        'bank_name',
        'bank_branch',
        'bank_account_name',
        'bank_account_number',
        'office_phone',
        'mobile_phone',
        'permanent_address',
        'current_address',
        'stay_with',
        'military_status',
        'marital_status',
        'spouse_name',
        'spouse_occupation',
        'father_name',
        'father_occupation',
        'mother_name',
        'mother_occupation',
        'driver_license_number',
        'created_by',
        'updated_by'
    ];



    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function employment()
    {
        return $this->hasOne(Employment::class);
    }

    /**
     * Check if employee has a user account
     *
     * @return bool
     */
    public function hasUserAccount()
    {
        return !is_null($this->user_id);
    }

    // grant-position relationship
    public function grantPosition()
    {
        return $this->hasOne(GrantPosition::class);
    }
}
