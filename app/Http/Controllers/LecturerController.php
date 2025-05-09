<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Unit;
use App\Models\Enrollment;
use App\Models\Semester;
use Inertia\Inertia;

class LecturerController extends Controller
{
  /**
   * Display the lecturer's dashboard
   */
  public function dashboard(Request $request)
  {
      $user = $request->user();
      
      // Get current semester for reference
    //   $currentSemester = Semester::where('is_active', true)->first();
    //   if (!$currentSemester) {
    //       $currentSemester = Semester::latest()->first();
    //   }
      
      // Get all semesters where the lecturer has assigned units
      $lecturerSemesters = Enrollment::where('lecturer_code', $user->code)
          ->distinct('semester_id')
          ->join('semesters', 'enrollments.semester_id', '=', 'semesters.id')
          ->select('semesters.*')
          ->orderBy('semesters.name')
          ->get();
          
      // Get all enrollments for this lecturer across all semesters
      $allEnrollments = Enrollment::where('lecturer_code', $user->code)
          ->with(['unit.faculty', 'semester'])
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
                  'faculty' => $enrollment->unit->faculty ? [
                      'name' => $enrollment->unit->faculty->name
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
          'current_semester_id' => $currentSemester->id,
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
  
  /**
   * Display the lecturer's assigned classes
   */
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
          // Get current semester
          $currentSemester = Semester::where('is_active', true)->first();
          if (!$currentSemester) {
              $currentSemester = Semester::latest()->first();
          }
          
          // Get all semesters for filtering
          $semesters = Semester::orderBy('name')->get();
          
          // Get selected semester (default to current)
          $selectedSemesterId = $request->input('semester_id', $currentSemester->id);
          
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
              ->with(['unit.faculty', 'semester'])
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
              $assignedUnits = Unit::whereIn('id', $unitIds)->with('faculty')->get();
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
              'units_count' => count($assignedUnits)
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
          $unit = Unit::with('faculty')->findOrFail($unitId);
          
          // Get the semester that this unit belongs to
          $unitSemester = Semester::findOrFail($selectedSemesterId);
          
          // Get students enrolled in this unit with their full details
          $enrollments = Enrollment::where('unit_id', $unitId)
              ->where('semester_id', $selectedSemesterId)
              ->where('student_code', '!=', null)
              ->get();
              
          // Fetch the actual student details from the users table
          $studentCodes = $enrollments->pluck('student_code')->filter()->unique();
          
          // Log the student codes for debugging
          Log::info('Student codes for unit', [
              'unit_id' => $unitId,
              'semester_id' => $selectedSemesterId,
              'student_codes' => $studentCodes
          ]);
          
          // Get the name field from users table
          $userColumns = \DB::getSchemaBuilder()->getColumnListing('users');
          $nameField = 'name'; // Default
          if (in_array('username', $userColumns)) {
              $nameField = 'username';
          } elseif (in_array('full_name', $userColumns)) {
              $nameField = 'full_name';
          } elseif (in_array('display_name', $userColumns)) {
              $nameField = 'display_name';
          }
          
          // Fetch student details from users table
          $students = User::whereIn('code', $studentCodes)
              ->select('id', 'code', $nameField . ' as name', 'email')
              ->get();
              
          // Log the students found
          Log::info('Students found', [
              'count' => $students->count(),
              'first_few' => $students->take(3)->map(function($s) {
                  return [
                      'id' => $s->id,
                      'code' => $s->code,
                      'name' => $s->name,
                      'email' => $s->email
                  ];
              })
          ]);
          
          // Map enrollments to include student details
          $enrollmentsWithStudents = $enrollments->map(function($enrollment) use ($students) {
              $student = $students->firstWhere('code', $enrollment->student_code);
              
              return [
                  'id' => $enrollment->id,
                  'student_id' => $student ? $student->id : null,
                  'unit_id' => $enrollment->unit_id,
                  'semester_id' => $enrollment->semester_id,
                  'student' => $student ? [
                      'id' => $student->id,
                      'code' => $student->code,
                      'name' => $student->name ?? 'Unknown',
                      'email' => $student->email ?? 'No email'
                  ] : [
                      'id' => null,
                      'code' => $enrollment->student_code,
                      'name' => 'Unknown Student',
                      'email' => 'No email available'
                  ]
              ];
          });
          
          return Inertia::render('Lecturer/ClassStudents', [
              'unit' => $unit,
              'students' => $enrollmentsWithStudents,
              'unitSemester' => $unitSemester,
              'selectedSemesterId' => $selectedSemesterId
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
              'error' => 'An error occurred while loading student data. Please try again later.',
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
  public function viewClassTimetable(Request $request)
  {
      $user = $request->user();
      
      // Get current semester
      $currentSemester = Semester::where('is_active', true)->first();
      if (!$currentSemester) {
          $currentSemester = Semester::latest()->first();
      }
      
      // Get selected semester and unit
      $selectedSemesterId = $request->input('semester_id', $currentSemester->id);
      $selectedUnitId = $request->input('unit_id');
      
      try {
          // Get class timetable for the lecturer
          // Adjust this query based on your actual database structure
          $query = \DB::table('class_timetables')
              ->where('lecturer_code', $user->code)
              ->where('semester_id', $selectedSemesterId)
              ->join('units', 'class_timetables.unit_id', '=', 'units.id')
              ->join('classrooms', 'class_timetables.classroom_id', '=', 'classrooms.id')
              ->join('class_time_slots', 'class_timetables.class_time_slot_id', '=', 'class_time_slots.id')
              ->select('class_timetables.*', 'units.name as unit_name', 'classrooms.name as room_name', 
                      'class_time_slots.start_time', 'class_time_slots.end_time', 'class_time_slots.day');
          
          // Filter by unit if specified
          if ($selectedUnitId) {
              $query->where('class_timetables.unit_id', $selectedUnitId);
          }
          
          $classTimetables = $query->get();
          
          // Get all units assigned to this lecturer for the dropdown
          $assignedUnits = Unit::whereHas('enrollments', function($query) use ($user, $selectedSemesterId) {
              $query->where('lecturer_code', $user->code)
                  ->where('semester_id', $selectedSemesterId);
          })->get();
          
          return Inertia::render('Lecturer/ClassTimetable', [
              'classTimetables' => $classTimetables,
              'currentSemester' => $currentSemester,
              'selectedSemesterId' => $selectedSemesterId,
              'selectedUnitId' => $selectedUnitId,
              'assignedUnits' => $assignedUnits
          ]);
      } catch (\Exception $e) {
          Log::error('Error in class timetable', [
              'lecturer_code' => $user->code,
              'semester_id' => $selectedSemesterId,
              'unit_id' => $selectedUnitId,
              'error' => $e->getMessage()
          ]);
          
          return Inertia::render('Lecturer/ClassTimetable', [
              'error' => 'An error occurred while loading the timetable. Please try again later.',
              'classTimetables' => [],
              'currentSemester' => $currentSemester,
              'selectedSemesterId' => $selectedSemesterId,
              'selectedUnitId' => $selectedUnitId,
              'assignedUnits' => []
          ]);
      }
  }
  
  /**
   * Display the lecturer's exam supervision assignments
   */
  public function examSupervision(Request $request)
  {
      $user = $request->user();
      
      // Get current semester
      $currentSemester = Semester::where('is_active', true)->first();
      if (!$currentSemester) {
          $currentSemester = Semester::latest()->first();
      }
      
      try {
          // Get exam supervision assignments
          // This would need to be adjusted based on your actual model structure
          $supervisions = \DB::table('exam_timetables')
              ->where('chief_invigilator', $user->name)
              ->where('semester_id', $currentSemester->id)
              ->join('units', 'exam_timetables.unit_code', '=', 'units.code')
              ->select('exam_timetables.*', 'units.name as unit_name')
              ->orderBy('date')
              ->orderBy('start_time')
              ->get();
          
          return Inertia::render('Lecturer/ExamSupervision', [
              'supervisions' => $supervisions,
              'currentSemester' => $currentSemester,
          ]);
      } catch (\Exception $e) {
          Log::error('Error in exam supervision', [
              'lecturer_code' => $user->code,
              'semester_id' => $currentSemester->id,
              'error' => $e->getMessage()
          ]);
          
          return Inertia::render('Lecturer/ExamSupervision', [
              'error' => 'An error occurred while loading supervision assignments. Please try again later.',
              'supervisions' => [],
              'currentSemester' => $currentSemester,
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
          $lecturer = User::with(['faculty'])
              ->where('id', $user->id)
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
