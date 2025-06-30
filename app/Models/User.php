<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\School;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'code',
        'phone',
        'schools',
        'programs',
        'school_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the school that this user is assigned to
     */
    public function assignedSchool()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /**
     * Check if user can manage a specific school using Spatie permissions
     */
    public function canManageSchool($schoolCode)
    {
        $schoolCode = strtolower($schoolCode);
        
        // Super admin can manage all schools
        if ($this->hasRole('Admin') || $this->can('manage schools')) {
            return true;
        }
        
        // Check school-specific permission
        return $this->can("manage {$schoolCode} school");
    }

    /**
     * Check if user can view a specific school using Spatie permissions
     */
    public function canViewSchool($schoolCode)
    {
        $schoolCode = strtolower($schoolCode);
        
        // Super admin can view all schools
        if ($this->hasRole('Admin') || $this->can('view schools')) {
            return true;
        }
        
        // Check school-specific permission
        return $this->can("view {$schoolCode} school");
    }

    /**
     * Get schools that this user can manage based on Spatie permissions
     */
    public function getManageableSchools()
    {
        // Super admin can see all schools
        if ($this->hasRole('Admin') || $this->can('manage schools')) {
            return School::all();
        }

        // Get schools based on specific permissions
        $manageableSchools = collect();
        $schools = School::all();
        
        foreach ($schools as $school) {
            if ($this->canViewSchool($school->code)) {
                $manageableSchools->push($school);
            }
        }
        
        return $manageableSchools;
    }

    /**
     * Assign school-specific faculty admin role
     */
    public function assignToSchool($schoolCode)
    {
        $school = School::where('code', $schoolCode)->first();
        if (!$school) {
            throw new \Exception("School with code {$schoolCode} not found");
        }

        // Update school_id for reference
        $this->school_id = $school->id;
        $this->save();

        // Assign school-specific role
        $schoolRoleName = "Faculty Admin - {$schoolCode}";
        $this->assignRole($schoolRoleName);

        return $this;
    }
}
