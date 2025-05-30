<?php

namespace App\Http\Controllers;

use App\Models\ClassTimetable;
use App\Models\Classroom;
use App\Models\ClassTimeSlot;
use App\Models\Unit;
use App\Models\Semester;
use App\Models\Program;
use App\Models\Classes;
use App\Models\Group;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AutoTimetableController extends Controller
{
    /**
     * Show the auto-generation form
     */
    public function showAutoGenerateForm()
    {
        try {
            $semesters = Semester::all();
            $programs = Program::all();
            $classes = Classes::all();
            $groups = Group::all();
            
            return Inertia::render('ClassTimetables/AutoGenerate', [
                'semesters' => $semesters,
                'programs' => $programs,
                'classes' => $classes,
                'groups' => $groups,
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading auto-generate form: ' . $e->getMessage());
            return Inertia::render('ClassTimetables/AutoGenerate', [
                'semesters' => [],
                'programs' => [],
                'classes' => [],
                'groups' => [],
                'error' => 'Failed to load form data.'
            ]);
        }
    }

    /**
     * Auto-generate timetables
     */
    public function autoGenerate(Request $request)
    {
        try {
            Log::info('Auto-Generate Timetable Request', [
                'data' => $request->all()
            ]);
            
            // Validate the request
            $validator = Validator::make($request->all(), [
                'semester_id' => 'required|exists:semesters,id',
                'program_id' => 'required|exists:programs,id',
                'class_id' => 'required|exists:classes,id',
                'group_id' => 'required|exists:groups,id',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Failed to process timetable: ' . $validator->errors()->first(),
                ], 422);
            }

            $validated = $validator->validated();
            $semesterId = $validated['semester_id'];
            $programId = $validated['program_id'];
            $classId = $validated['class_id'];
            $groupId = $validated['group_id'];

            // Fetch available venues and time slots
            $venues = Classroom::all();
            if ($venues->isEmpty()) {
                return response()->json([
                    'error' => 'No classrooms available. Please add classrooms first.',
                ], 422);
            }
            
            $timeSlots = ClassTimeSlot::all();
            if ($timeSlots->isEmpty()) {
                return response()->json([
                    'error' => 'No time slots available. Please add time slots first.',
                ], 422);
            }

            // Fetch units for the selected class and semester
            $units = Unit::where('semester_id', $semesterId)->get();
            
            if ($units->isEmpty()) {
                return response()->json([
                    'error' => 'No units found for the selected semester.',
                ], 404);
            }

            Log::info('Auto-generating timetable', [
                'semester_id' => $semesterId,
                'program_id' => $programId,
                'class_id' => $classId,
                'group_id' => $groupId,
                'units_count' => $units->count(),
                'venues_count' => $venues->count(),
                'time_slots_count' => $timeSlots->count()
            ]);

            $generatedTimetable = [];

            foreach ($units as $unit) {
                $assigned = false;
                
                foreach ($timeSlots as $timeSlot) {
                    // Check for conflicts
                    $conflictExists = ClassTimetable::where('day', $timeSlot->day)
                        ->where(function ($query) use ($timeSlot) {
                            $query->where(function ($q) use ($timeSlot) {
                                $q->where('start_time', '<', $timeSlot->end_time)
                                  ->where('end_time', '>', $timeSlot->start_time);
                            });
                        })
                        ->where(function ($query) use ($unit, $classId, $groupId) {
                            $query->where('unit_id', $unit->id)
                                ->orWhere(function ($q) use ($classId, $groupId) {
                                    $q->where('class_id', $classId)
                                      ->where('group_id', $groupId);
                                });
                        })
                        ->exists();

                    if (!$conflictExists) {
                        // Find an available venue
                        $availableVenue = null;
                        
                        foreach ($venues as $venue) {
                            $venueConflict = ClassTimetable::where('day', $timeSlot->day)
                                ->where('venue', $venue->name)
                                ->where(function ($query) use ($timeSlot) {
                                    $query->where(function ($q) use ($timeSlot) {
                                        $q->where('start_time', '<', $timeSlot->end_time)
                                          ->where('end_time', '>', $timeSlot->start_time);
                                    });
                                })
                                ->exists();
                                
                            if (!$venueConflict) {
                                $availableVenue = $venue;
                                break;
                            }
                        }
                        
                        if ($availableVenue) {
                            // Get student count for this unit
                            $studentCount = Enrollment::where('unit_id', $unit->id)
                                ->where('semester_id', $semesterId)
                                ->count();
                                
                            // Get lecturer for this unit
                            $lecturer = '';
                            $lecturerEnrollment = Enrollment::where('unit_id', $unit->id)
                                ->where('semester_id', $semesterId)
                                ->whereNotNull('lecturer_code')
                                ->first();
                                
                            if ($lecturerEnrollment) {
                                $lecturerUser = User::where('code', $lecturerEnrollment->lecturer_code)->first();
                                if ($lecturerUser) {
                                    $lecturer = $lecturerUser->first_name . ' ' . $lecturerUser->last_name;
                                }
                            }
                            
                            $generatedTimetable[] = [
                                'day' => $timeSlot->day,
                                'start_time' => $timeSlot->start_time,
                                'end_time' => $timeSlot->end_time,
                                'unit_id' => $unit->id,
                                'semester_id' => $semesterId,
                                'class_id' => $classId,
                                'group_id' => $groupId,
                                'venue' => $availableVenue->name,
                                'location' => $availableVenue->location,
                                'no' => $studentCount ?: 0,
                                'lecturer' => $lecturer ?: 'TBD',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                            
                            $assigned = true;
                            break;
                        }
                    }
                }
                
                if (!$assigned) {
                    Log::warning('Could not assign unit to any time slot', [
                        'unit_id' => $unit->id,
                        'unit_code' => $unit->code,
                        'unit_name' => $unit->name
                    ]);
                }
            }

            if (empty($generatedTimetable)) {
                return response()->json([
                    'warning' => true,
                    'message' => 'Could not generate timetable. No suitable time slots or venues available.'
                ], 200);
            }

            // Save generated timetable
            ClassTimetable::insert($generatedTimetable);

            Log::info('Timetable auto-generated successfully', [
                'entries_created' => count($generatedTimetable)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Timetable auto-generated successfully. Created ' . count($generatedTimetable) . ' entries.'
            ]);
                
        } catch (\Exception $e) {
            Log::error('Failed to auto-generate timetable: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Failed to auto-generate timetable: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display auto-generated timetables
     */
    public function showAutoGenerated(Request $request)
    {
        try {
            // Get filter parameters
            $semesterId = $request->get('semester_id');
            $classId = $request->get('class_id');
            $groupId = $request->get('group_id');
            
            // Build query for class timetables with relationships
            $query = ClassTimetable::with(['unit', 'semester', 'class', 'group'])
                ->orderBy('day')
                ->orderBy('start_time');
                
            // Apply filters if provided
            if ($semesterId) {
                $query->where('semester_id', $semesterId);
            }
            if ($classId) {
                $query->where('class_id', $classId);
            }
            if ($groupId) {
                $query->where('group_id', $groupId);
            }
            
            $classTimetables = $query->paginate(20);
            
            // Get data for dropdowns
            $semesters = Semester::all();
            $classes = Classes::all();
            $groups = Group::all();
            
            // Group timetables by day for better display
            $timetablesByDay = [];
            foreach ($classTimetables as $timetable) {
                $timetablesByDay[$timetable->day][] = $timetable;
            }
            
            return Inertia::render('ClassTimetables/AutoGenerated', [
                'classTimetables' => $classTimetables,
                'timetablesByDay' => $timetablesByDay,
                'semesters' => $semesters,
                'classes' => $classes,
                'groups' => $groups,
                'filters' => [
                    'semester_id' => $semesterId,
                    'class_id' => $classId,
                    'group_id' => $groupId,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading auto-generated timetables: ' . $e->getMessage());
            return Inertia::render('ClassTimetables/AutoGenerated', [
                'classTimetables' => [],
                'timetablesByDay' => [],
                'semesters' => [],
                'classes' => [],
                'groups' => [],
                'filters' => [],
                'error' => 'Failed to load auto-generated timetables.'
            ]);
        }
    }

    /**
     * Delete an auto-generated timetable entry
     */
    public function deleteAutoGenerated($id)
    {
        try {
            $timetable = ClassTimetable::findOrFail($id);
            $timetable->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Auto-generated timetable entry deleted successfully!'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting auto-generated timetable: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to delete timetable entry.'
            ], 500);
        }
    }

    /**
     * Download auto-generated timetables as PDF
     */
    public function downloadAutoGenerated(Request $request)
    {
        try {
            // Get filter parameters
            $semesterId = $request->get('semester_id');
            $classId = $request->get('class_id');
            $groupId = $request->get('group_id');
            
            // Build query for class timetables
            $query = ClassTimetable::with(['unit', 'semester', 'class', 'group'])
                ->orderBy('day')
                ->orderBy('start_time');
                
            // Apply filters if provided
            if ($semesterId) {
                $query->where('semester_id', $semesterId);
            }
            if ($classId) {
                $query->where('class_id', $classId);
            }
            if ($groupId) {
                $query->where('group_id', $groupId);
            }
            
            $classTimetables = $query->get();
            
            // Transform data for PDF
            $timetableData = $classTimetables->map(function ($timetable) {
                return [
                    'day' => $timetable->day,
                    'unit_code' => $timetable->unit->code,
                    'unit_name' => $timetable->unit->name,
                    'semester_name' => $timetable->semester->name,
                    'class_name' => $timetable->class->name,
                    'group_name' => $timetable->group->name,
                    'start_time' => $timetable->start_time,
                    'end_time' => $timetable->end_time,
                    'venue' => $timetable->venue,
                    'location' => $timetable->location,
                    'lecturer' => $timetable->lecturer,
                ];
            })->toArray();
            
            $pdf = \PDF::loadView('timetables.auto-generated-pdf', [
                'classTimetables' => $timetableData,
                'title' => 'Auto-Generated Class Timetables',
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ]);
            
            return $pdf->download('auto-generated-class-timetables.pdf');
        } catch (\Exception $e) {
            Log::error('Error downloading auto-generated timetables: ' . $e->getMessage());
            return back()->with('error', 'Failed to download PDF.');
        }
    }
}
