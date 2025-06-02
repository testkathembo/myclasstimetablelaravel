<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // Add this import
use Illuminate\Support\Facades\Schema; // <-- Add this line
use App\Models\User;
use App\Models\Unit;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\ClassTimetable; // Add this import
use App\Models\ExamTimetable; // Add this import
use Inertia\Inertia;

class LecturerController extends Controller
{
  /**
   * Display the lecturer's dashboard
   */
  public function dashboard(Request $request)
  {
      $user = $request->user();
      
      // Find semesters where the lecturer has assigned units
      $lecturerSemesters = Enrollment::where('lecturer_code', $user->code)
          ->distinct('semester_id')
          ->join('semesters', 'enrollments.semester_id', '=', 'semesters.id')
          ->select('semesters.*')
          ->orderBy('semesters.name')
          ->get();
          
      // Determine the current semester based on lecturer's assignments
      // First, check if there's a semester marked as active among lecturer's semesters
      $currentSemester = $lecturerSemesters->firstWhere('is_active', true);
      
      // If no active semester is found among lecturer's semesters, use the most recent one
      if (!$currentSemester && $lecturerSemesters->isNotEmpty()) {
          $currentSemester = $lecturerSemesters->sortByDesc('id')->first();
      }
      
      // If still no semester is found, try to get any active semester from the system
      if (!$currentSemester) {
          $currentSemester = Semester::where('is_active', true)->first();
          
          // If no active semester in the system, get the most recent one
          if (!$currentSemester) {
              $currentSemester = Semester::latest()->first();
          }
      }
      
      // Get all enrollments for this lecturer across all semesters
      $allEnrollments = Enrollment::where('lecturer_code', $user->code)
          ->with(['unit.school', 'semester']) // Replace faculty with school
          ->get();
          
      // Group units by semester
      $unitsBySemester = [];
      foreach ($allEnrollments as $enrollment) {
          if (!isset($unitsBySemester[$enrollment->semester_id])) {
              $unitsBySemester[$enrollment->semester_id] = [
                  'semester' => $enrollment->semester,
                  'units' => []
              ];
          }
          
          // Check if unit already exists in the array to avoid duplicates
          $unitExists = false;
          foreach ($unitsBySemester[$enrollment->semester_id]['units'] as $unit) {
              if ($unit['id'] === $enrollment->unit->id) {
                  $unitExists = true;
                  break;
              }
          }
          
          if (!$unitExists && $enrollment->unit) {
              $unitsBySemester[$enrollment->semester_id]['units'][] = [
                  'id' => $enrollment->unit->id,
                  'code' => $enrollment->unit->code,
                  'name' => $enrollment->unit->name,
                  'school' => $enrollment->unit->school ? [ // Replace faculty with school
                      'name' => $enrollment->unit->school->name
                  ] : null
              ];
          }
      }
      
      // Count students per unit per semester
      $studentCounts = [];
      foreach ($allEnrollments as $enrollment) {
          $unitId = $enrollment->unit_id;
          $semesterId = $enrollment->semester_id;
          
          if (!isset($studentCounts[$semesterId])) {
              $studentCounts[$semesterId] = [];
          }
          
          if (!isset($studentCounts[$semesterId][$unitId])) {
              $studentCounts[$semesterId][$unitId] = Enrollment::where('unit_id', $unitId)
                  ->where('semester_id', $semesterId)
                  ->where('student_code', '!=', null)
                  ->distinct('student_code')
                  ->count();
          }
      }
      
      // For debugging
      Log::info('Lecturer dashboard', [
          'lecturer_id' => $user->id,
          'lecturer_code' => $user->code ?? 'No code',
          'current_semester_id' => $currentSemester ? $currentSemester->id : null,
          'current_semester_name' => $currentSemester ? $currentSemester->name : null,
          'lecturer_semesters_count' => $lecturerSemesters->count(),
          'units_by_semester_count' => count($unitsBySemester),
          'has_lecturer_role' => $user->hasRole('Lecturer')
      ]);
      
      return Inertia::render('Lecturer/Dashboard', [
          'currentSemester' => $currentSemester,
          'lecturerSemesters' => $lecturerSemesters,
          'unitsBySemester' => $unitsBySemester,
          'studentCounts' => $studentCounts
      ]);
  }
    public function myClasses(Request $request)
    {
        $user = $request->user();
        
        if (!$user || !$user->code) {
            Log::error('My Classes accessed with invalid user', [
                'user_id' => $user ? $user->id : 'null',
                'has_code' => $user && isset($user->code)
            ]);
            
            return Inertia::render('Lecturer/Classes', [
                'error' => 'User profile is incomplete. Please contact an administrator.',
                'units' => [],
                'currentSemester' => null,
                'semesters' => [],
                'selectedSemesterId' => null,
                'lecturerSemesters' => [],
                'studentCounts' => []
            ]);
        }
        
        try {
            // Find semesters where the lecturer has assigned units
            $lecturerSemesters = Enrollment::where('lecturer_code', $user->code)
                ->distinct('semester_id')
                ->join('semesters', 'enrollments.semester_id', '=', 'semesters.id')
                ->select('semesters.*')
                ->orderBy('semesters.name')
                ->get();
                
            // Determine the current semester based on lecturer's assignments
            $currentSemester = $lecturerSemesters->firstWhere('is_active', true);
            
            // If no active semester is found among lecturer's semesters, use the most recent one
            if (!$currentSemester && $lecturerSemesters->isNotEmpty()) {
                $currentSemester = $lecturerSemesters->sortByDesc('id')->first();
            }
            
            // If still no semester is found, try to get any active semester from the system
            if (!$currentSemester) {
                $currentSemester = Semester::where('is_active', true)->first();
                
                // If no active semester in the system, get the most recent one
                if (!$currentSemester) {
                    $currentSemester = Semester::latest()->first();
                }
            }
            
            // Get all semesters for filtering
            $semesters = Semester::orderBy('name')->get();
            
            // Get selected semester (default to current)
            $selectedSemesterId = $request->input('semester_id', $currentSemester ? $currentSemester->id : null);
            
            // Find semesters where the lecturer has assigned units through enrollments
            $lecturerSemesterIds = Enrollment::where('lecturer_code', $user->code)
                ->distinct()
                ->pluck('semester_id')
                ->toArray();
            
            // Debug log to check lecturer code and semester IDs
            Log::info('Lecturer semester IDs', [
                'lecturer_code' => $user->code,
                'semester_ids' => $lecturerSemesterIds,
                'selected_semester_id' => $selectedSemesterId
            ]);
            
            // Get units for the selected semester
            // First, get all enrollments for this lecturer in the selected semester
            $enrollments = Enrollment::where('lecturer_code', $user->code)
                ->where('semester_id', $selectedSemesterId)
                ->with(['unit.school', 'semester']) // Replace faculty with school
                ->get();
            
            Log::info('Enrollments found', [
                'count' => $enrollments->count(),
                'first_few' => $enrollments->take(3)->map(function($e) {
                    return [
                        'id' => $e->id,
                        'unit_id' => $e->unit_id,
                        'unit_code' => $e->unit ? $e->unit->code : null,
                        'semester_id' => $e->semester_id
                    ];
                })
            ]);
            
            // Extract unique unit IDs
            $unitIds = $enrollments->pluck('unit_id')->filter()->unique()->values();
            
            // Get the actual unit objects
            $assignedUnits = [];
            if ($unitIds->isNotEmpty()) {
                $assignedUnits = Unit::whereIn('id', $unitIds)->get();
            }
            
            // Count students per unit
            $studentCounts = [];
            foreach ($unitIds as $unitId) {
                $studentCounts[$unitId] = Enrollment::where('unit_id', $unitId)
                    ->where('semester_id', $selectedSemesterId)
                    ->where('student_code', '!=', null)
                    ->distinct('student_code')
                    ->count();
            }
            
            // For debugging
            Log::info('Lecturer classes', [
                'lecturer_id' => $user->id,
                'lecturer_code' => $user->code,
                'semester_id' => $selectedSemesterId,
                'available_semesters' => $lecturerSemesterIds,
                'unit_ids' => $unitIds->toArray(),
                'units_count' => count($assignedUnits),
                'student_counts' => $studentCounts
            ]);
            
            return Inertia::render('Lecturer/Classes', [
                'units' => $assignedUnits,
                'currentSemester' => $currentSemester,
                'semesters' => $semesters,
                'selectedSemesterId' => (int)$selectedSemesterId,
                'lecturerSemesters' => $lecturerSemesterIds,
                'studentCounts' => $studentCounts
            ]);
        } catch (\Exception $e) {
            Log::error('Error in lecturer classes', [
                'lecturer_code' => $user->code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Inertia::render('Lecturer/Classes', [
                'error' => 'An error occurred while loading your classes. Please try again later.',
                'units' => [],
                'currentSemester' => null,
                'semesters' => [],
                'selectedSemesterId' => null,
                'lecturerSemesters' => [],
                'studentCounts' => []
            ]);
        }
    }
    
    /**
     * Display students enrolled in a specific class
     */
    public function classStudents(Request $request, $unitId)
    {
        $user = $request->user();

        // Get selected semester (required parameter)
        $selectedSemesterId = $request->input('semester_id');
        if (!$selectedSemesterId) {
            return Inertia::render('Lecturer/ClassStudents', [
                'error' => 'Semester ID is required.',
                'unit' => null,
                'students' => [],
                'unitSemester' => null,
                'selectedSemesterId' => null
            ]);
        }

        // Verify that this unit is assigned to the lecturer
        $isAssigned = Enrollment::where('lecturer_code', $user->code)
            ->where('unit_id', $unitId)
            ->where('semester_id', $selectedSemesterId)
            ->exists();

        if (!$isAssigned) {
            return Inertia::render('Lecturer/ClassStudents', [
                'error' => 'You are not assigned to this unit for the selected semester.',
                'unit' => null,
                'students' => [],
                'unitSemester' => null,
                'selectedSemesterId' => $selectedSemesterId
            ]);
        }

        try {
            // Get unit details
            $unit = Unit::findOrFail($unitId);

            // Get the semester that this unit belongs to
            $unitSemester = Semester::findOrFail($selectedSemesterId);

            // Count total students for this unit in this semester
            $studentCount = Enrollment::where('unit_id', $unitId)
                ->where('semester_id', $selectedSemesterId)
                ->where('student_code', '!=', null)
                ->distinct('student_code')
                ->count();

            // Get students enrolled in this unit with their full details
            $enrollments = Enrollment::where('unit_id', $unitId)
                ->where('semester_id', $selectedSemesterId)
                ->whereNotNull('student_code')
                ->get();

            // Fetch the actual student details from the users table
            $studentCodes = $enrollments->pluck('student_code')->filter()->unique()->values();

            // Fetch student details from users table (exclude the `name` column)
            $students = User::whereIn('code', $studentCodes)
                ->select('id', 'code', 'email') // Only include existing columns
                ->get();

            // Map enrollments to include student details
            $enrollmentsWithStudents = $enrollments->map(function ($enrollment) use ($students) {
                $student = $students->firstWhere('code', $enrollment->student_code);

                return [
                    'id' => $enrollment->id,
                    'student_id' => $student ? $student->id : null,
                    'unit_id' => $enrollment->unit_id,
                    'semester_id' => $enrollment->semester_id,
                    'student' => $student ? [
                        'id' => $student->id,
                        'code' => $student->code,
                        'email' => $student->email ?? 'No email'
                    ] : [
                        'id' => null,
                        'code' => $enrollment->student_code,
                        'email' => 'No email available'
                    ]
                ];
            });

            return Inertia::render('Lecturer/ClassStudents', [
                'unit' => $unit,
                'students' => $enrollmentsWithStudents,
                'unitSemester' => $unitSemester,
                'selectedSemesterId' => $selectedSemesterId,
                'studentCount' => $studentCount // Pass the actual count for verification
            ]);
        } catch (\Exception $e) {
            Log::error('Error in class students', [
                'lecturer_code' => $user->code,
                'unit_id' => $unitId,
                'semester_id' => $selectedSemesterId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Inertia::render('Lecturer/ClassStudents', [
                'error' => 'An error occurred while loading student data: ' . $e->getMessage(),
                'unit' => null,
                'students' => [],
                'unitSemester' => null,
                'selectedSemesterId' => $selectedSemesterId
            ]);
        }
    }
    


  /**
   * Display the lecturer's class timetable
   */
  /**
 * Display the lecturer's class timetable
 */
public function viewClassTimetable(Request $request)
{
    $user = $request->user();
    
    if (!$user || !$user->code) {
        Log::error('Class Timetable accessed with invalid user', [
            'user_id' => $user ? $user->id : 'null',
            'has_code' => $user && isset($user->code)
        ]);
        
        return Inertia::render('Lecturer/ClassTimetable', [
            'error' => 'User profile is incomplete. Please contact an administrator.',
            'classTimetables' => [],
            'currentSemester' => null,
            'selectedSemesterId' => null,
            'selectedUnitId' => null,
            'assignedUnits' => []
        ]);
    }
    
    try {
        // Get selected semester and unit from request
        $selectedSemesterId = $request->input('semester_id');
        $selectedUnitId = $request->input('unit_id');
        
        // Find semesters where the lecturer has assigned units
        $lecturerSemesters = Enrollment::where('lecturer_code', $user->code)
            ->distinct('semester_id')
            ->join('semesters', 'enrollments.semester_id', '=', 'semesters.id')
            ->select('semesters.*')
            ->orderBy('semesters.name')
            ->get();
        
        // Get all units assigned to this lecturer across all semesters
        $allAssignedUnits = Enrollment::where('lecturer_code', $user->code)
            ->with('unit.school', 'semester')
            ->get();
            
        // Extract unique units with their semesters
        $unitsBySemester = [];
        $allUnitIds = [];
        
        foreach ($allAssignedUnits as $enrollment) {
            if (!$enrollment->unit_id || !$enrollment->semester_id) continue;
            
            $semesterId = $enrollment->semester_id;
            $unitId = $enrollment->unit_id;
            
            if (!isset($unitsBySemester[$semesterId])) {
                $unitsBySemester[$semesterId] = [];
            }
            
            if (!in_array($unitId, $unitsBySemester[$semesterId])) {
                $unitsBySemester[$semesterId][] = $unitId;
            }
            
            if (!in_array($unitId, $allUnitIds)) {
                $allUnitIds[] = $unitId;
            }
        }
        
        // Get all units for the dropdown
        $assignedUnits = Unit::whereIn('id', $allUnitIds)->get();
        
        // Get the selected semester (for display purposes)
        $selectedSemester = null;
        if ($selectedSemesterId) {
            $selectedSemester = $lecturerSemesters->firstWhere('id', $selectedSemesterId);
        }
        
        // Build the query for class timetable entries
        $query = DB::table('class_timetable')
            ->leftJoin('programs', 'class_timetable.program_id', '=', 'programs.id')
            ->leftJoin('classes', 'class_timetable.class_id', '=', 'classes.id')
            ->leftJoin('groups', 'class_timetable.group_id', '=', 'groups.id')
            ->select(
                'class_timetable.*',
                'programs.name as program_name',
                'classes.name as class_name',
                'groups.name as group_name'
            );

        // Filter by lecturer - this is the key fix
        // Option 1: If the class_timetable has a lecturer column that stores lecturer name
        if (Schema::hasColumn('class_timetable', 'lecturer')) {
            $query->where('class_timetable.lecturer', $user->name);
        }
        // Option 2: If the class_timetable has a lecturer_code column
        elseif (Schema::hasColumn('class_timetable', 'lecturer_code')) {
            $query->where('class_timetable.lecturer_code', $user->code);
        }
        // Option 3: Filter by units assigned to this lecturer
        else {
            $query->whereIn('class_timetable.unit_id', $allUnitIds);
        }

        // Apply additional filters if provided
        if ($selectedUnitId) {
            $query->where('class_timetable.unit_id', $selectedUnitId);
        }
        
        if ($selectedSemesterId) {
            $query->where('class_timetable.semester_id', $selectedSemesterId);
        }
        
        // Get the timetable entries
        $timetableEntries = $query->orderBy('day')
            ->orderBy('start_time')
            ->get();
        
        // Log the query and results for debugging
        Log::info('Class timetable query', [
            'lecturer_code' => $user->code,
            'lecturer_name' => $user->name,
            'semester_id' => $selectedSemesterId,
            'unit_id' => $selectedUnitId,
            'results_count' => $timetableEntries->count(),
            'assigned_unit_ids' => $allUnitIds
        ]);
        
        $classTimetables = [];
        
        // If we have results, join with units and semesters to get their details
        if ($timetableEntries->isNotEmpty()) {
            // Get all unit IDs from the timetable entries
            $unitIds = $timetableEntries->pluck('unit_id')->unique();
            
            // Get the units data
            $units = Unit::whereIn('id', $unitIds)->get()->keyBy('id');
            
            // Get all semester IDs from the timetable entries
            $semesterIds = $timetableEntries->pluck('semester_id')->unique();
            
            // Get the semesters data
            $semesters = Semester::whereIn('id', $semesterIds)->get()->keyBy('id');
            
            // Map the timetable entries to include unit and semester data
            $classTimetables = $timetableEntries->map(function($entry) use ($units, $semesters) {
                $unit = $units->get($entry->unit_id);
                $semester = $semesters->get($entry->semester_id);

                return [
                    'id' => $entry->id,
                    'unit_id' => $entry->unit_id,
                    'semester_id' => $entry->semester_id,
                    'unit' => $unit ? [
                        'id' => $unit->id,
                        'code' => $unit->code,
                        'name' => $unit->name
                    ] : null,
                    'semester' => $semester ? [
                        'id' => $semester->id,
                        'name' => $semester->name
                    ] : null,
                    'day' => $entry->day,
                    'start_time' => $entry->start_time,
                    'end_time' => $entry->end_time,
                    'venue' => $entry->room_name ?? $entry->venue ?? '',
                    'location' => $entry->location ?? '',
                    'no' => $entry->no ?? 0,
                    'program_name' => $entry->program_name ?? '',
                    'class_name' => $entry->class_name ?? '',
                    'group_name' => $entry->group_name ?? '',
                ];
            });
        }
        
        return Inertia::render('Lecturer/ClassTimetable', [
            'classTimetables' => $classTimetables,
            'currentSemester' => $selectedSemester,
            'selectedSemesterId' => $selectedSemesterId,
            'selectedUnitId' => $selectedUnitId,
            'assignedUnits' => $assignedUnits,
            'lecturerSemesters' => $lecturerSemesters,
            'showAllByDefault' => true
        ]);
    } catch (\Exception $e) {
        Log::error('Error in class timetable', [
            'lecturer_code' => $user->code,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return Inertia::render('Lecturer/ClassTimetable', [
            'error' => 'An error occurred while loading the timetable: ' . $e->getMessage(),
            'classTimetables' => [],
            'currentSemester' => null,
            'selectedSemesterId' => $request->input('semester_id'),
            'selectedUnitId' => $request->input('unit_id'),
            'assignedUnits' => [],
            'lecturerSemesters' => [],
            'showAllByDefault' => true
        ]);
    }
}
  
  /**
   * Display the lecturer's exam supervision assignments
   */
  public function examSupervision(Request $request)
{
    $user = $request->user();

    if (!$user) {
        Log::error('Exam Supervision accessed with invalid user');

        return Inertia::render('Lecturer/ExamSupervision', [
            'error' => 'User profile is incomplete. Please contact an administrator.',
            'supervisions' => [],
            'lecturerSemesters' => [],
            'units' => [],
        ]);
    }

    try {
        // Find semesters where the lecturer is assigned units
        $lecturerSemesters = Enrollment::where('lecturer_code', $user->code)
            ->distinct('semester_id')
            ->join('semesters', 'enrollments.semester_id', '=', 'semesters.id')
            ->select('semesters.*')
            ->orderBy('semesters.name')
            ->get();

        // Get all exam timetables where this lecturer is the chief invigilator for the relevant semesters
        $supervisions = ExamTimetable::where('chief_invigilator', $user->name)
            ->whereIn('semester_id', $lecturerSemesters->pluck('id'))
            ->with('unit') // Include unit details
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // Format the supervisions for the view
        $formattedSupervisions = $supervisions->map(function ($exam) {
            return [
                'id' => $exam->id,
                'unit_code' => $exam->unit->code ?? '',
                'unit_name' => $exam->unit->name ?? '',
                'venue' => $exam->venue,
                'location' => $exam->location,
                'day' => $exam->day,
                'date' => $exam->date,
                'start_time' => $exam->start_time,
                'end_time' => $exam->end_time,
                'no' => $exam->no,
            ];
        });

        // Get all units assigned to the lecturer
        $units = Unit::whereIn('id', $supervisions->pluck('unit_id')->unique())
            ->select('id', 'code', 'name')
            ->get();

        return Inertia::render('Lecturer/ExamSupervision', [
            'supervisions' => $formattedSupervisions,
            'lecturerSemesters' => $lecturerSemesters,
            'units' => $units,
        ]);
    } catch (\Exception $e) {
        Log::error('Error in exam supervision', [
            'user_id' => $user ? $user->id : 'null',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return Inertia::render('Lecturer/ExamSupervision', [
            'error' => 'An error occurred while loading supervision assignments. Please try again later.',
            'supervisions' => [],
            'lecturerSemesters' => [],
            'units' => [],
        ]);
    }
}

  /**
   * Display the lecturer's profile
   */
  public function profile(Request $request)
  {
      $user = $request->user();
      
      try {
          // Get lecturer details with related data
          $lecturer = User::where('id', $user->id)
              ->first();
              
          return Inertia::render('Lecturer/Profile', [
              'lecturer' => $lecturer,
          ]);
      } catch (\Exception $e) {
          Log::error('Error in lecturer profile', [
              'lecturer_id' => $user->id,
              'error' => $e->getMessage()
          ]);
          
          return Inertia::render('Lecturer/Profile', [
              'error' => 'An error occurred while loading your profile. Please try again later.',
              'lecturer' => null,
          ]);
      }
  }
}
