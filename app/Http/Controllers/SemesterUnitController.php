<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Semester;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class SemesterUnitController extends Controller
{
    /**
     * Display a listing of semester units.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $semesters = Semester::orderBy('name')->get();
        $classes = ClassModel::orderBy('name')->get();
        $units = Unit::with(['program', 'school'])->get();
        
        // Get the active semester
        $activeSemester = Semester::where('is_active', true)->first() ?? $semesters->first();
        
        // Get units assigned to classes for the active semester
        $assignedUnits = $this->getAssignedUnitsForSemester($activeSemester->id);
        
        return Inertia::render('SemesterUnits/Index', [
            'semesters' => $semesters,
            'classes' => $classes,
            'units' => $units,
            'activeSemester' => $activeSemester,
            'assignedUnits' => $assignedUnits
        ]);
    }
    
    /**
     * Store a newly created semester unit assignment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'unit_ids' => 'required|array',
            'unit_ids.*' => 'exists:units,id',
        ]);
        
        try {
            // Begin transaction
            DB::beginTransaction();
            
            // Check if we're using semester_unit_class or class_unit table
            if (Schema::hasTable('semester_unit_class')) {
                // For each unit, create an entry in the semester_unit_class table
                foreach ($validated['unit_ids'] as $unitId) {
                    // Check if the assignment already exists
                    $exists = DB::table('semester_unit_class')
                        ->where('semester_id', $validated['semester_id'])
                        ->where('class_id', $validated['class_id'])
                        ->where('unit_id', $unitId)
                        ->exists();
                        
                    if (!$exists) {
                        DB::table('semester_unit_class')->insert([
                            'semester_id' => $validated['semester_id'],
                            'class_id' => $validated['class_id'],
                            'unit_id' => $unitId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            } else if (Schema::hasTable('class_unit')) {
                // For each unit, create an entry in the class_unit table
                foreach ($validated['unit_ids'] as $unitId) {
                    // Check if the assignment already exists
                    $exists = DB::table('class_unit')
                        ->where('class_id', $validated['class_id'])
                        ->where('unit_id', $unitId)
                        ->exists();
                        
                    if (!$exists) {
                        DB::table('class_unit')->insert([
                            'class_id' => $validated['class_id'],
                            'unit_id' => $unitId,
                            'semester_id' => $validated['semester_id'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            } else {
                // Neither table exists, create class_unit table
                Schema::create('class_unit', function ($table) {
                    $table->id();
                    $table->unsignedBigInteger('class_id');
                    $table->unsignedBigInteger('unit_id');
                    $table->unsignedBigInteger('semester_id')->nullable();
                    $table->timestamps();
                    
                    $table->foreign('class_id')->references('id')->on('classes')->onDelete('cascade');
                    $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');
                    $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('set null');
                    
                    $table->unique(['class_id', 'unit_id', 'semester_id']);
                });
                
                // Now insert the records
                foreach ($validated['unit_ids'] as $unitId) {
                    DB::table('class_unit')->insert([
                        'class_id' => $validated['class_id'],
                        'unit_id' => $unitId,
                        'semester_id' => $validated['semester_id'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            
            // Commit transaction
            DB::commit();
            
            return redirect()->back()->with('success', 'Units assigned successfully!');
        } catch (\Exception $e) {
            // Rollback transaction
            DB::rollBack();
            
            Log::error('Error assigning units: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to assign units: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Remove a unit assignment.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'unit_id' => 'required|exists:units,id',
        ]);
        
        try {
            // Check if we're using semester_unit_class or class_unit table
            if (Schema::hasTable('semester_unit_class')) {
                DB::table('semester_unit_class')
                    ->where('semester_id', $validated['semester_id'])
                    ->where('class_id', $validated['class_id'])
                    ->where('unit_id', $validated['unit_id'])
                    ->delete();
            } else if (Schema::hasTable('class_unit')) {
                DB::table('class_unit')
                    ->where('class_id', $validated['class_id'])
                    ->where('unit_id', $validated['unit_id'])
                    ->where('semester_id', $validated['semester_id'])
                    ->delete();
            }
            
            return redirect()->back()->with('success', 'Unit assignment removed successfully!');
        } catch (\Exception $e) {
            Log::error('Error removing unit assignment: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to remove unit assignment: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Get units assigned to classes for a semester.
     *
     * @param  int  $semesterId
     * @return array
     */
    private function getAssignedUnitsForSemester($semesterId)
    {
        $assignedUnits = [];
        
        // Get all classes
        $classes = ClassModel::orderBy('name')->get();
        
        foreach ($classes as $class) {
            // Get units assigned to this class for the semester
            $units = $this->getUnitsByClass($semesterId, $class->id);
            
            if (count($units) > 0) {
                $assignedUnits[$class->name] = $units;
            }
        }
        
        return $assignedUnits;
    }
    
    /**
     * Get units assigned to a specific class for a semester.
     *
     * @param  int  $semesterId
     * @param  int  $classId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUnitsByClass($semesterId, $classId)
    {
        // First check if we have a semester_unit_class table
        if (Schema::hasTable('semester_unit_class')) {
            $units = Unit::with(['program', 'school'])
                ->join('semester_unit_class', 'units.id', '=', 'semester_unit_class.unit_id')
                ->where('semester_unit_class.semester_id', $semesterId)
                ->where('semester_unit_class.class_id', $classId)
                ->select('units.*')
                ->get();
                
            if (count($units) > 0) {
                return $units;
            }
        }
        
        // If not found or table doesn't exist, check class_unit table
        if (Schema::hasTable('class_unit')) {
            $units = Unit::with(['program', 'school'])
                ->join('class_unit', 'units.id', '=', 'class_unit.unit_id')
                ->where('class_unit.class_id', $classId)
                ->where(function($query) use ($semesterId) {
                    $query->where('class_unit.semester_id', $semesterId)
                          ->orWhereNull('class_unit.semester_id');
                })
                ->select('units.*')
                ->get();
                
            if (count($units) > 0) {
                return $units;
            }
        }
        
        // If still not found, try to find units from enrollments
        $enrolledUnitIds = DB::table('enrollments')
            ->join('groups', 'enrollments.group_id', '=', 'groups.id')
            ->where('groups.class_id', $classId)
            ->where('enrollments.semester_id', $semesterId)
            ->distinct()
            ->pluck('enrollments.unit_id')
            ->toArray();
            
        if (!empty($enrolledUnitIds)) {
            return Unit::with(['program', 'school'])
                ->whereIn('id', $enrolledUnitIds)
                ->get();
        }
        
        // If still not found, try to match by class name pattern
        $class = ClassModel::find($classId);
        if ($class && preg_match('/(\w+)\s+(\d+\.\d+)/', $class->name, $matches)) {
            $program = $matches[1]; // e.g., "BBIT"
            $level = $matches[2];   // e.g., "1.1"
            
            // Extract the major level (e.g., "1" from "1.1")
            $majorLevel = explode('.', $level)[0];
            
            // Look for units with codes that might match this class level
            if (Schema::hasColumn('units', 'code')) {
                $units = Unit::with(['program', 'school'])
                    ->where(function($q) use ($program, $level, $majorLevel) {
                        // Look for units with codes that match this specific class
                        $q->where('code', 'like', $program . $level . '%')
                          // Or units with codes that match this program and level
                          ->orWhere('code', 'like', $program . '%' . str_replace('.', '', $level) . '%')
                          // Or units with codes that match common patterns for this level
                          ->orWhere('code', 'like', $program . $majorLevel . '%');
                    })
                    ->when(Schema::hasColumn('units', 'semester_id'), function($query) use ($semesterId) {
                        return $query->where('semester_id', $semesterId);
                    })
                    ->get();
                    
                if (count($units) > 0) {
                    return $units;
                }
            }
        }
        
        // Return empty collection if no units found
        return collect([]);
    }
}
