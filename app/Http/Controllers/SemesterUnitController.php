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
    // Update the index method in SemesterUnitController.php
public function index()
{
    // Get all semesters with their units
    $semesters = Semester::orderBy('name')->get();
    
    // For each semester, load its units with class information
    foreach ($semesters as $semester) {
        // Load units for this semester with their class information
        $semester->units = $this->getSemesterUnits($semester->id);
    }
    
    $classes = ClassModel::orderBy('name')->get();
    $units = Unit::with(['program', 'school'])->get();
    
    // Debug information
    \Log::info('Semesters with units:', [
        'count' => count($semesters),
        'first_semester' => $semesters->first() ? [
            'id' => $semesters->first()->id,
            'name' => $semesters->first()->name,
            'units_count' => $semesters->first()->units ? count($semesters->first()->units) : 0
        ] : null
    ]);
    
    return Inertia::render('SemesterUnits/Index', [
        'semesters' => $semesters,
        'classes' => $classes,
        'units' => $units
    ]);
}

/**
 * Get all units for a specific semester with their class information.
 *
 * @param int $semesterId
 * @return \Illuminate\Support\Collection
 */
private function getSemesterUnits($semesterId)
{
    // Check if we're using semester_unit_class or class_unit table
    if (Schema::hasTable('semester_unit_class')) {
        $units = DB::table('units')
            ->join('semester_unit_class', 'units.id', '=', 'semester_unit_class.unit_id')
            ->where('semester_unit_class.semester_id', $semesterId)
            ->select(
                'units.id',
                'units.name',
                'units.code',
                'semester_unit_class.class_id as pivot_class_id'
            )
            ->get();
            
        if ($units->isNotEmpty()) {
            return $units;
        }
    }
    
    // If not found or table doesn't exist, check semester_unit table
    if (Schema::hasTable('semester_unit')) {
        $units = DB::table('units')
            ->join('semester_unit', 'units.id', '=', 'semester_unit.unit_id')
            ->where('semester_unit.semester_id', $semesterId)
            ->select(
                'units.id',
                'units.name',
                'units.code',
                'semester_unit.class_id as pivot_class_id'
            )
            ->get();
            
        if ($units->isNotEmpty()) {
            return $units;
        }
    }
    
    // If not found, check class_unit table
    if (Schema::hasTable('class_unit')) {
        $units = DB::table('units')
            ->join('class_unit', 'units.id', '=', 'class_unit.unit_id')
            ->where('class_unit.semester_id', $semesterId)
            ->select(
                'units.id',
                'units.name',
                'units.code',
                'class_unit.class_id as pivot_class_id'
            )
            ->get();
            
        if ($units->isNotEmpty()) {
            return $units;
        }
    }
    
    // Return empty collection if no units found
    return collect([]);
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
    
    // Log the validated data
    \Log::info('Assigning units to semester and class', [
        'semester_id' => $validated['semester_id'],
        'class_id' => $validated['class_id'],
        'unit_ids' => $validated['unit_ids'],
    ]);
    
    try {
        // Begin transaction
        \DB::beginTransaction();
        
        // Check if semester_unit table exists, if not create it
        if (!\Schema::hasTable('semester_unit')) {
            \Log::info('Creating semester_unit table');
            
            \Schema::create('semester_unit', function ($table) {
                $table->id();
                $table->unsignedBigInteger('semester_id');
                $table->unsignedBigInteger('unit_id');
                $table->unsignedBigInteger('class_id');
                $table->timestamps();
                
                // Add foreign keys if the referenced tables exist
                if (\Schema::hasTable('semesters')) {
                    $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
                }
                
                if (\Schema::hasTable('units')) {
                    $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');
                }
                
                if (\Schema::hasTable('classes')) {
                    $table->foreign('class_id')->references('id')->on('classes')->onDelete('cascade');
                }
                
                $table->unique(['semester_id', 'unit_id', 'class_id']);
            });
        }
        
        // Get the structure of the semester_unit table
        $semesterUnitColumns = \Schema::getColumnListing('semester_unit');
        \Log::info('semester_unit table columns', ['columns' => $semesterUnitColumns]);
        
        // DIRECT INSERT: Force insert into semester_unit table
        foreach ($validated['unit_ids'] as $unitId) {
            // Check if the assignment already exists
            $exists = \DB::table('semester_unit')
                ->where('semester_id', $validated['semester_id'])
                ->where('unit_id', $unitId)
                ->where('class_id', $validated['class_id'])
                ->exists();
                
            if (!$exists) {
                $insertData = [
                    'semester_id' => $validated['semester_id'],
                    'unit_id' => $unitId,
                    'class_id' => $validated['class_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                
                \Log::info('Inserting record into semester_unit', $insertData);
                
                try {
                    $insertId = \DB::table('semester_unit')->insertGetId($insertData);
                    \Log::info('Insert successful', ['id' => $insertId]);
                } catch (\Exception $e) {
                    \Log::error('Error inserting record', [
                        'error' => $e->getMessage(),
                        'data' => $insertData
                    ]);
                    throw $e;
                }
            } else {
                \Log::info('Record already exists, skipping', [
                    'semester_id' => $validated['semester_id'],
                    'unit_id' => $unitId,
                    'class_id' => $validated['class_id'],
                ]);
            }
        }
        
        // Commit transaction
        \DB::commit();
        
        \Log::info('Units assigned successfully');
        
        return redirect()->back()->with('success', 'Units assigned successfully!');
    } catch (\Exception $e) {
        // Rollback transaction
        \DB::rollBack();
        
        \Log::error('Error assigning units: ' . $e->getMessage(), [
            'exception' => $e,
            'trace' => $e->getTraceAsString(),
        ]);
        
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
    // Log the request
    \Log::info('Getting units for class', [
        'semester_id' => $semesterId,
        'class_id' => $classId
    ]);
    
    // First check if we have a semester_unit_class table
    if (Schema::hasTable('semester_unit_class')) {
        $units = Unit::with(['program', 'school'])
            ->join('semester_unit_class', 'units.id', '=', 'semester_unit_class.unit_id')
            ->where('semester_unit_class.semester_id', $semesterId)
            ->where('semester_unit_class.class_id', $classId)
            ->select('units.*')
            ->get();
            
        if (count($units) > 0) {
            \Log::info('Found units in semester_unit_class table', ['count' => count($units)]);
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
            \Log::info('Found units in class_unit table', ['count' => count($units)]);
            return $units;
        }
    }
    
    // Check semester_unit table
    if (Schema::hasTable('semester_unit')) {
        $units = Unit::with(['program', 'school'])
            ->join('semester_unit', 'units.id', '=', 'semester_unit.unit_id')
            ->where('semester_unit.semester_id', $semesterId)
            ->where('semester_unit.class_id', $classId)
            ->select('units.*')
            ->get();
            
        if (count($units) > 0) {
            \Log::info('Found units in semester_unit table', ['count' => count($units)]);
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
        $units = Unit::with(['program', 'school'])
            ->whereIn('id', $enrolledUnitIds)
            ->get();
            
        \Log::info('Found units from enrollments', ['count' => count($units)]);
        return $units;
    }
    
    // DISABLE THE PATTERN MATCHING FALLBACK
    // Or make it explicit that these are suggested units, not actual assignments
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
                // Mark these units as suggestions, not actual assignments
                foreach ($units as $unit) {
                    $unit->is_suggestion = true;
                }
                
                \Log::info('Found suggested units based on naming pattern', ['count' => count($units)]);
                return $units;
            }
        }
    }
    
    \Log::info('No units found for this class and semester');
    // Return empty collection if no units found
    return collect([]);
}

    /**
     * Delete a unit from a semester.
     *
     * @param  int  $semesterId
     * @param  int  $unitId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteUnit($semesterId, $unitId)
    {
        try {
            // Delete the unit from the semester_unit table
            DB::table('semester_unit')
                ->where('semester_id', $semesterId)
                ->where('unit_id', $unitId)
                ->delete();

            return redirect()->back()->with('success', 'Unit removed successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete unit: ' . $e->getMessage()]);
        }
    }
}
