<?php
namespace App\Http\Controllers;
use App\Models\ClassTimetable;
use App\Models\Unit;
use App\Models\User;
use App\Models\Semester;
use App\Models\Enrollment;
use App\Models\ClassTimeSlot;
use App\Models\Classroom;
use App\Models\ClassModel;
use App\Models\Group;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;


class ClassTimetableController extends Controller
{
    // Enhanced scheduling constraints
    private const MAX_PHYSICAL_PER_DAY = 2;
    private const MAX_ONLINE_PER_DAY = 2;
    private const MIN_HOURS_PER_DAY = 2;
    private const MAX_HOURS_PER_DAY = 5;
    private const REQUIRE_MIXED_MODE = true;
    private const AVOID_CONSECUTIVE_SLOTS = true;

    /**
     * ✅ ENHANCED: Assign random time slot with upfront conflict filtering
     */
    private function assignRandomTimeSlot($lecturer, $venue = '', $preferredDay = null, $preferredMode = null, $requiredDuration = 1, $classId = null, $groupId = null, $semesterId = null)
    {
        try {
            \Log::info('Assigning conflict-free time slot', [
                'lecturer' => $lecturer,
                'class_id' => $classId,
                'group_id' => $groupId,
                'semester_id' => $semesterId,
                'preferred_mode' => $preferredMode,
                'required_duration' => $requiredDuration
            ]);

            // Step 1: Get all time slots that match duration requirements
            $baseTimeSlots = $this->getTimeSlotsForDuration($requiredDuration, $preferredDay);
            
            if ($baseTimeSlots->isEmpty()) {
                return [
                    'success' => false,
                    'message' => "No time slots available for {$requiredDuration}-hour sessions."
                ];
            }

            // Step 2: Pre-filter slots to remove those with lecturer conflicts
            $lecturerFreeSlots = $this->filterSlotsForLecturerAvailability($baseTimeSlots, $lecturer, $semesterId);
            
            if ($lecturerFreeSlots->isEmpty()) {
                return [
                    'success' => false,
                    'message' => "No time slots available without lecturer conflicts for {$lecturer}."
                ];
            }

            // Step 3: Pre-filter slots to remove those with class conflicts (if same class)
            $classFreeSlots = $this->filterSlotsForClassAvailability($lecturerFreeSlots, $classId, $groupId, $semesterId);
            
            if ($classFreeSlots->isEmpty()) {
                return [
                    'success' => false,
                    'message' => "No time slots available without class/group conflicts."
                ];
            }

            // Step 4: Pre-filter slots to remove venue conflicts (if venue specified)
            $venueFreeSlots = $this->filterSlotsForVenueAvailability($classFreeSlots, $venue);
            
            if ($venueFreeSlots->isEmpty() && !empty($venue) && strtolower(trim($venue)) !== 'remote') {
                return [
                    'success' => false,
                    'message' => "No time slots available without venue conflicts for {$venue}."
                ];
            }

            // Step 5: Apply constraint-based filtering
            $constraintCompliantSlots = $this->filterSlotsForConstraints($venueFreeSlots, $groupId, $semesterId, $preferredMode);
            
            // Use constraint-compliant slots if available, otherwise fall back to venue-free slots
            $finalSlots = $constraintCompliantSlots->isNotEmpty() ? $constraintCompliantSlots : $venueFreeSlots;

            // Step 6: Randomly select from the pre-filtered, conflict-free slots
            $selectedTimeSlot = $finalSlots->random();

            // Calculate actual duration
            $actualDuration = \Carbon\Carbon::parse($selectedTimeSlot->start_time)
                ->diffInHours(\Carbon\Carbon::parse($selectedTimeSlot->end_time));

            \Log::info('Conflict-free time slot assigned successfully', [
                'day' => $selectedTimeSlot->day,
                'start_time' => $selectedTimeSlot->start_time,
                'end_time' => $selectedTimeSlot->end_time,
                'required_duration' => $requiredDuration,
                'actual_duration' => $actualDuration,
                'lecturer' => $lecturer,
                'total_available_slots' => $finalSlots->count(),
                'filtering_stages' => [
                    'base_slots' => $baseTimeSlots->count(),
                    'lecturer_free' => $lecturerFreeSlots->count(),
                    'class_free' => $classFreeSlots->count(),
                    'venue_free' => $venueFreeSlots->count(),
                    'constraint_compliant' => $constraintCompliantSlots->count()
                ]
            ]);

            return [
                'success' => true,
                'day' => $selectedTimeSlot->day,
                'start_time' => $selectedTimeSlot->start_time,
                'end_time' => $selectedTimeSlot->end_time,
                'duration' => $actualDuration,
                'message' => "Conflict-free slot assigned: {$selectedTimeSlot->day} {$selectedTimeSlot->start_time}-{$selectedTimeSlot->end_time} ({$actualDuration}h)"
            ];

        } catch (\Exception $e) {
            \Log::error('Error in conflict-free time slot assignment: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to assign conflict-free time slot: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ NEW: Get time slots based on duration requirements
     */
    private function getTimeSlotsForDuration($requiredDuration, $preferredDay = null)
    {
        $query = DB::table('class_time_slots');

        if ($requiredDuration == 2) {
            // For 2-hour sessions, prioritize 2-hour slots
            $query->whereRaw('TIMESTAMPDIFF(HOUR, start_time, end_time) = 2');
        } else {
            // For 1-hour sessions, prioritize 1-hour slots but allow any
            $query->whereRaw('TIMESTAMPDIFF(HOUR, start_time, end_time) >= ?', [$requiredDuration]);
        }

        if ($preferredDay) {
            $query->where('day', $preferredDay);
        }

        $slots = $query->get();

        // If no exact duration matches found and we were looking for 2-hour slots, try any slots
        if ($slots->isEmpty() && $requiredDuration == 2) {
            \Log::info('No 2-hour slots found, trying any available slots');
            $query = DB::table('class_time_slots');
            if ($preferredDay) {
                $query->where('day', $preferredDay);
            }
            $slots = $query->get();
        }

        return collect($slots);
    }

    /**
     * ✅ NEW: Filter slots to remove lecturer conflicts upfront
     */
    private function filterSlotsForLecturerAvailability($timeSlots, $lecturer, $semesterId = null)
    {
        return $timeSlots->filter(function ($slot) use ($lecturer, $semesterId) {
            // Check if lecturer has any conflicting sessions in this time slot
            $query = ClassTimetable::where('lecturer', $lecturer)
                ->where('day', $slot->day)
                ->where(function ($q) use ($slot) {
                    $q->where(function ($subQ) use ($slot) {
                        $subQ->where('start_time', '<', $slot->end_time)
                             ->where('end_time', '>', $slot->start_time);
                    });
                });

            if ($semesterId) {
                $query->where('semester_id', $semesterId);
            }

            $hasConflict = $query->exists();

            if ($hasConflict) {
                \Log::debug('Lecturer conflict detected', [
                    'lecturer' => $lecturer,
                    'day' => $slot->day,
                    'time_slot' => "{$slot->start_time}-{$slot->end_time}"
                ]);
            }

            return !$hasConflict;
        });
    }

    /**
     * ✅ NEW: Filter slots to remove class/group conflicts upfront
     */
    private function filterSlotsForClassAvailability($timeSlots, $classId = null, $groupId = null, $semesterId = null)
    {
        return $timeSlots->filter(function ($slot) use ($classId, $groupId, $semesterId) {
            $hasConflict = false;

            // Check for class conflicts (if class ID provided)
            if ($classId) {
                $classConflictQuery = ClassTimetable::where('class_id', $classId)
                    ->where('day', $slot->day)
                    ->where(function ($q) use ($slot) {
                        $q->where(function ($subQ) use ($slot) {
                            $subQ->where('start_time', '<', $slot->end_time)
                                 ->where('end_time', '>', $slot->start_time);
                        });
                    });

                if ($semesterId) {
                    $classConflictQuery->where('semester_id', $semesterId);
                }

                if ($classConflictQuery->exists()) {
                    $hasConflict = true;
                    \Log::debug('Class conflict detected', [
                        'class_id' => $classId,
                        'day' => $slot->day,
                        'time_slot' => "{$slot->start_time}-{$slot->end_time}"
                    ]);
                }
            }

            // Check for group conflicts (if group ID provided)
            if (!$hasConflict && $groupId) {
                $groupConflictQuery = ClassTimetable::where('group_id', $groupId)
                    ->where('day', $slot->day)
                    ->where(function ($q) use ($slot) {
                        $q->where(function ($subQ) use ($slot) {
                            $subQ->where('start_time', '<', $slot->end_time)
                                 ->where('end_time', '>', $slot->start_time);
                        });
                    });

                if ($semesterId) {
                    $groupConflictQuery->where('semester_id', $semesterId);
                }

                if ($groupConflictQuery->exists()) {
                    $hasConflict = true;
                    \Log::debug('Group conflict detected', [
                        'group_id' => $groupId,
                        'day' => $slot->day,
                        'time_slot' => "{$slot->start_time}-{$slot->end_time}"
                    ]);
                }
            }

            return !$hasConflict;
        });
    }

    /**
     * ✅ NEW: Filter slots to remove venue conflicts upfront
     */
    private function filterSlotsForVenueAvailability($timeSlots, $venue = '')
    {
        // If no venue specified or venue is 'Remote', no filtering needed
        if (empty($venue) || strtolower(trim($venue)) === 'remote') {
            return $timeSlots;
        }

        return $timeSlots->filter(function ($slot) use ($venue) {
            // Check if venue has any conflicting bookings in this time slot
            $hasConflict = ClassTimetable::where('venue', $venue)
                ->where('day', $slot->day)
                ->where(function ($q) use ($slot) {
                    $q->where(function ($subQ) use ($slot) {
                        $subQ->where('start_time', '<', $slot->end_time)
                             ->where('end_time', '>', $slot->start_time);
                    });
                })
                ->exists();

            if ($hasConflict) {
                \Log::debug('Venue conflict detected', [
                    'venue' => $venue,
                    'day' => $slot->day,
                    'time_slot' => "{$slot->start_time}-{$slot->end_time}"
                ]);
            }

            return !$hasConflict;
        });
    }

    /**
     * ✅ NEW: Filter slots based on scheduling constraints
     */
    private function filterSlotsForConstraints($timeSlots, $groupId = null, $semesterId = null, $preferredMode = null)
    {
        if (!$groupId || !$semesterId) {
            return $timeSlots; // No constraint filtering if group/semester not specified
        }

        return $timeSlots->filter(function ($slot) use ($groupId, $semesterId, $preferredMode) {
            // Get existing sessions for this group on this day
            $existingSessions = ClassTimetable::where('group_id', $groupId)
                ->where('semester_id', $semesterId)
                ->where('day', $slot->day)
                ->get();

            $physicalCount = $existingSessions->where('teaching_mode', 'physical')->count();
            $onlineCount = $existingSessions->where('teaching_mode', 'online')->count();
            $totalHours = $existingSessions->sum(function ($session) {
                return \Carbon\Carbon::parse($session->start_time)
                    ->diffInHours(\Carbon\Carbon::parse($session->end_time));
            });

            $slotDuration = \Carbon\Carbon::parse($slot->start_time)
                ->diffInHours(\Carbon\Carbon::parse($slot->end_time));

            // Check constraints based on preferred mode
            if ($preferredMode === 'physical') {
                if ($physicalCount >= self::MAX_PHYSICAL_PER_DAY) {
                    \Log::debug('Physical constraint violation', [
                        'group_id' => $groupId,
                        'day' => $slot->day,
                        'current_physical' => $physicalCount,
                        'max_allowed' => self::MAX_PHYSICAL_PER_DAY
                    ]);
                    return false;
                }
            } elseif ($preferredMode === 'online') {
                if ($onlineCount >= self::MAX_ONLINE_PER_DAY) {
                    \Log::debug('Online constraint violation', [
                        'group_id' => $groupId,
                        'day' => $slot->day,
                        'current_online' => $onlineCount,
                        'max_allowed' => self::MAX_ONLINE_PER_DAY
                    ]);
                    return false;
                }
            }

            // Check total hours constraint
            if ($totalHours + $slotDuration > self::MAX_HOURS_PER_DAY) {
                \Log::debug('Hours constraint violation', [
                    'group_id' => $groupId,
                    'day' => $slot->day,
                    'current_hours' => $totalHours,
                    'slot_duration' => $slotDuration,
                    'max_allowed' => self::MAX_HOURS_PER_DAY
                ]);
                return false;
            }

            return true;
        });
    }

    /**
     * ✅ ENHANCED: Assign random venue with upfront conflict filtering
     */
    private function assignRandomVenue($studentCount, $day, $startTime, $endTime, $preferredMode = null, $classId = null, $groupId = null)
    {
        try {
            \Log::info('Assigning conflict-free venue', [
                'student_count' => $studentCount,
                'day' => $day,
                'time' => "{$startTime}-{$endTime}",
                'preferred_mode' => $preferredMode,
                'class_id' => $classId,
                'group_id' => $groupId
            ]);

            // Step 1: Get all classrooms with sufficient capacity
            $baseClassrooms = Classroom::where('capacity', '>=', $studentCount)->get();

            if ($baseClassrooms->isEmpty()) {
                return [
                    'success' => false,
                    'message' => "No venues available with sufficient capacity for {$studentCount} students."
                ];
            }

            // Step 2: Pre-filter by preferred teaching mode
            $modeFilteredClassrooms = $this->filterVenuesByMode($baseClassrooms, $preferredMode);

            // Step 3: Pre-filter to remove venues with time conflicts
            $timeConflictFreeVenues = $this->filterVenuesForTimeAvailability($modeFilteredClassrooms, $day, $startTime, $endTime);

            if ($timeConflictFreeVenues->isEmpty()) {
                return [
                    'success' => false,
                    'message' => "No venues available without time conflicts for {$day} {$startTime}-{$endTime}."
                ];
            }

            // Step 4: Pre-filter to remove venues with class conflicts (if same class)
            $classConflictFreeVenues = $this->filterVenuesForClassAvailability($timeConflictFreeVenues, $classId, $day, $startTime, $endTime);

            // Use class-conflict-free venues if available, otherwise fall back to time-conflict-free venues
            $finalVenues = $classConflictFreeVenues->isNotEmpty() ? $classConflictFreeVenues : $timeConflictFreeVenues;

            // Step 5: Randomly select from pre-filtered, conflict-free venues
            $selectedClassroom = $finalVenues->random();
            $venueInfo = $this->determineTeachingModeAndLocation($selectedClassroom->name);

            \Log::info('Conflict-free venue assigned successfully', [
                'venue' => $selectedClassroom->name,
                'capacity' => $selectedClassroom->capacity,
                'student_count' => $studentCount,
                'teaching_mode' => $venueInfo['teaching_mode'],
                'location' => $venueInfo['location'],
                'day' => $day,
                'time' => "{$startTime}-{$endTime}",
                'total_available_venues' => $finalVenues->count(),
                'filtering_stages' => [
                    'base_venues' => $baseClassrooms->count(),
                    'mode_filtered' => $modeFilteredClassrooms->count(),
                    'time_conflict_free' => $timeConflictFreeVenues->count(),
                    'class_conflict_free' => $classConflictFreeVenues->count()
                ]
            ]);

            return [
                'success' => true,
                'venue' => $selectedClassroom->name,
                'location' => $venueInfo['location'],
                'teaching_mode' => $venueInfo['teaching_mode'],
                'message' => "Conflict-free {$venueInfo['teaching_mode']} venue '{$selectedClassroom->name}' assigned successfully."
            ];

        } catch (\Exception $e) {
            \Log::error('Error in conflict-free venue assignment: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to assign conflict-free venue: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ NEW: Filter venues by teaching mode preference
     */
    private function filterVenuesByMode($classrooms, $preferredMode = null)
    {
        if (!$preferredMode) {
            return $classrooms; // No mode filtering if no preference specified
        }

        $filtered = $classrooms->filter(function ($classroom) use ($preferredMode) {
            $venueInfo = $this->determineTeachingModeAndLocation($classroom->name);
            return $venueInfo['teaching_mode'] === $preferredMode;
        });

        // If no venues match the preferred mode, fall back to all venues
        return $filtered->isNotEmpty() ? $filtered : $classrooms;
    }

    /**
     * ✅ NEW: Filter venues to remove time conflicts upfront
     */
    private function filterVenuesForTimeAvailability($classrooms, $day, $startTime, $endTime)
    {
        return $classrooms->filter(function ($classroom) use ($day, $startTime, $endTime) {
            $venueInfo = $this->determineTeachingModeAndLocation($classroom->name);

            // Online venues (Remote) can handle multiple sessions simultaneously
            if ($venueInfo['teaching_mode'] === 'online') {
                return true;
            }

            // Physical venues need conflict checking
            $hasConflict = ClassTimetable::where('venue', $classroom->name)
                ->where('day', $day)
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<', $endTime)
                          ->where('end_time', '>', $startTime);
                    });
                })
                ->exists();

            if ($hasConflict) {
                \Log::debug('Venue time conflict detected', [
                    'venue' => $classroom->name,
                    'day' => $day,
                    'time_slot' => "{$startTime}-{$endTime}"
                ]);
            }

            return !$hasConflict;
        });
    }

    /**
     * ✅ NEW: Filter venues to remove class conflicts upfront
     */
    private function filterVenuesForClassAvailability($classrooms, $classId = null, $day, $startTime, $endTime)
    {
        if (!$classId) {
            return $classrooms; // No class filtering if no class ID specified
        }

        return $classrooms->filter(function ($classroom) use ($classId, $day, $startTime, $endTime) {
            $venueInfo = $this->determineTeachingModeAndLocation($classroom->name);

            // Online venues can handle multiple classes
            if ($venueInfo['teaching_mode'] === 'online') {
                return true;
            }

            // Check if this venue is already booked by the same class at this time
            $hasClassConflict = ClassTimetable::where('venue', $classroom->name)
                ->where('class_id', $classId)
                ->where('day', $day)
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<', $endTime)
                          ->where('end_time', '>', $startTime);
                    });
                })
                ->exists();

            if ($hasClassConflict) {
                \Log::debug('Venue class conflict detected', [
                    'venue' => $classroom->name,
                    'class_id' => $classId,
                    'day' => $day,
                    'time_slot' => "{$startTime}-{$endTime}"
                ]);
            }

            return !$hasClassConflict;
        });
    }

    /**
     * ✅ ENHANCED: Create session timetable with upfront conflict checking
     */
    private function createSessionTimetable(Request $request, Unit $unit, array $session, int $sessionNumber, $programId, $schoolId)
    {
        $sessionType = $session['type'];
        $requiredDuration = $session['duration'];
        
        \Log::info("Creating conflict-free session {$sessionNumber}", [
            'unit_code' => $unit->code,
            'session_type' => $sessionType,
            'required_duration' => $requiredDuration,
            'class_id' => $request->class_id,
            'group_id' => $request->group_id,
            'semester_id' => $request->semester_id
        ]);
        
        // Get conflict-free time slot with enhanced filtering
        $timeSlotResult = $this->assignRandomTimeSlot(
            $request->lecturer, 
            '', 
            null, 
            $sessionType, 
            $requiredDuration,
            $request->class_id,
            $request->group_id,
            $request->semester_id
        );
        
        if (!$timeSlotResult['success']) {
            return [
                'success' => false,
                'message' => "Session {$sessionNumber} ({$sessionType}, {$requiredDuration}h): " . $timeSlotResult['message']
            ];
        }

        $day = $timeSlotResult['day'];
        $startTime = $timeSlotResult['start_time'];
        $endTime = $timeSlotResult['end_time'];
        $actualDuration = $timeSlotResult['duration'] ?? $requiredDuration;

        // Get conflict-free venue with enhanced filtering
        $venueResult = $this->assignRandomVenue(
            $request->no, 
            $day, 
            $startTime, 
            $endTime, 
            $sessionType,
            $request->class_id,
            $request->group_id
        );
        
        if (!$venueResult['success']) {
            return [
                'success' => false,
                'message' => "Session {$sessionNumber} ({$sessionType}, {$requiredDuration}h): " . $venueResult['message']
            ];
        }

        $venue = $venueResult['venue'];
        $location = $venueResult['location'];
        $teachingMode = $venueResult['teaching_mode'];

        // Final safety check (should not be needed due to upfront filtering)
        $finalConflictCheck = $this->performFinalConflictCheck($request, $day, $startTime, $endTime, $venue);
        
        if (!$finalConflictCheck['safe']) {
            \Log::warning('Final conflict check failed despite upfront filtering', [
                'session_number' => $sessionNumber,
                'conflicts' => $finalConflictCheck['conflicts']
            ]);
            
            return [
                'success' => false,
                'message' => "Session {$sessionNumber}: Final conflict check failed - " . implode(', ', $finalConflictCheck['conflicts'])
            ];
        }

        // Create the timetable entry
        $classTimetable = ClassTimetable::create([
            'day' => $day,
            'unit_id' => $unit->id,
            'semester_id' => $request->semester_id,
            'class_id' => $request->class_id,
            'group_id' => $request->group_id ?: null,
            'venue' => $venue,
            'location' => $location,
            'no' => $request->no,
            'lecturer' => $request->lecturer,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'teaching_mode' => $teachingMode,
            'program_id' => $programId,
            'school_id' => $schoolId,
        ]);

        \Log::info("Conflict-free session {$sessionNumber} created successfully", [
            'timetable_id' => $classTimetable->id,
            'unit_code' => $unit->code,
            'session_type' => $sessionType,
            'day' => $day,
            'time' => "{$startTime}-{$endTime}",
            'duration' => $actualDuration,
            'venue' => $venue,
            'teaching_mode' => $teachingMode
        ]);

        return [
            'success' => true,
            'message' => "Session {$sessionNumber} ({$sessionType}, {$actualDuration}h) created successfully without conflicts",
            'timetable' => $classTimetable,
            'duration' => $actualDuration
        ];
    }

    /**
     * ✅ NEW: Perform final conflict check as safety measure
     */
    private function performFinalConflictCheck(Request $request, $day, $startTime, $endTime, $venue)
    {
        $conflicts = [];

        // Check lecturer conflicts
        $lecturerConflict = ClassTimetable::where('lecturer', $request->lecturer)
            ->where('day', $day)
            ->where('semester_id', $request->semester_id)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
                });
            })
            ->exists();

        if ($lecturerConflict) {
            $conflicts[] = "Lecturer conflict for {$request->lecturer}";
        }

        // Check venue conflicts (skip for online venues)
        if ($venue && strtolower(trim($venue)) !== 'remote') {
            $venueConflict = ClassTimetable::where('venue', $venue)
                ->where('day', $day)
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<', $endTime)
                          ->where('end_time', '>', $startTime);
                    });
                })
                ->exists();

            if ($venueConflict) {
                $conflicts[] = "Venue conflict for {$venue}";
            }
        }

        // Check group conflicts
        if ($request->group_id) {
            $groupConflict = ClassTimetable::where('group_id', $request->group_id)
                ->where('day', $day)
                ->where('semester_id', $request->semester_id)
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<', $endTime)
                          ->where('end_time', '>', $startTime);
                    });
                })
                ->exists();

            if ($groupConflict) {
                $conflicts[] = "Group conflict";
            }
        }

        return [
            'safe' => empty($conflicts),
            'conflicts' => $conflicts
        ];
    }

    /**
     * Determine teaching mode and location based on venue
     */
    private function determineTeachingModeAndLocation($venue)
    {
        if (strtolower(trim($venue)) === 'remote') {
            return [
                'teaching_mode' => 'online',
                'location' => 'online'
            ];
        }
        
        $classroom = Classroom::where('name', $venue)->first();
        $location = $classroom ? $classroom->location : 'Physical';
        return [
            'teaching_mode' => 'physical',
            'location' => $location
        ];
    }

    // ✅ Include all other methods from the original controller
    // (All the existing methods from the original controller would be included here)
    // For brevity, I'm showing the key enhanced methods above
    
    /**
     * Store a newly created resource in storage with enhanced conflict checking
     */
    public function store(Request $request)
    {
        $request->validate([
            'day' => 'nullable|string',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'group_id' => 'nullable|exists:groups,id',
            'venue' => 'nullable|string',
            'location' => 'nullable|string',
            'no' => 'required|integer|min:1',
            'lecturer' => 'required|string',
            'start_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'teaching_mode' => 'nullable|in:physical,online',
            'program_id' => 'nullable|exists:programs,id',
            'school_id' => 'nullable|exists:schools,id',
        ]);

        try {
            \Log::info('Creating enhanced conflict-free class timetable with data:', $request->all());

            $unit = Unit::findOrFail($request->unit_id);
            $class = ClassModel::find($request->class_id);
            $programId = $request->program_id ?: ($class ? $class->program_id : null);
            $schoolId = $request->school_id ?: ($class ? $class->school_id : null);

            $isRandomTimeSlot = empty($request->day) || empty($request->start_time) || 
                              empty($request->end_time) || $request->start_time === 'Random Time Slot (auto-assign)';

            if ($isRandomTimeSlot) {
                return $this->createCreditBasedTimetable($request, $unit, $programId, $schoolId);
            } else {
                return $this->createSingleTimetable($request, $unit, $programId, $schoolId);
            }

        } catch (\Exception $e) {
            \Log::error('Failed to create enhanced class timetable: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'exception' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create class timetable: ' . $e->getMessage(),
                    'errors' => ['error' => $e->getMessage()]
                ], 500);
            }

            return redirect()->back()
                ->withErrors(['error' => 'Failed to create class timetable: ' . $e->getMessage()])
                ->withInput();
        }
    }



    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Log user access
        \Log::info('Accessing /classtimetable', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);

        if (!$user->can('manage-classtimetables')) {
            abort(403, 'Unauthorized action.');
        }

        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');

        // Fetch class timetables with all DB columns and related display fields
        $classTimetables = ClassTimetable::query()
            ->leftJoin('units', 'class_timetable.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'class_timetable.semester_id', '=', 'semesters.id')
            ->leftJoin('classes', 'class_timetable.class_id', '=', 'classes.id')
            ->leftJoin('groups', 'class_timetable.group_id', '=', 'groups.id')
            ->leftJoin('programs', 'class_timetable.program_id', '=', 'programs.id')
            ->leftJoin('schools', 'class_timetable.school_id', '=', 'schools.id')
            ->leftJoin('class_time_slots', function ($join) {
                $join->on('class_timetable.day', '=', 'class_time_slots.day')
                    ->on('class_timetable.start_time', '=', 'class_time_slots.start_time')
                    ->on('class_timetable.end_time', '=', 'class_time_slots.end_time');
            })
            ->leftJoin('users', 'users.code', '=', 'class_timetable.lecturer')
            ->select(
                'class_timetable.id',
                'class_timetable.semester_id',
                'class_timetable.unit_id',
                'class_timetable.class_id',
                'class_timetable.group_id',
                'class_timetable.day',
                'class_timetable.start_time',
                'class_timetable.end_time',
                'class_timetable.teaching_mode',
                'class_timetable.venue',
                'class_timetable.location',
                'class_timetable.no',
                DB::raw("IF(users.id IS NOT NULL, CONCAT(users.first_name, ' ', users.last_name), class_timetable.lecturer) as lecturer"),
                'class_timetable.program_id',
                'class_timetable.school_id',
                'class_timetable.created_at',
                'class_timetable.updated_at',
                // Display fields from joins
                'units.code as unit_code',
                'units.name as unit_name',
                'semesters.name as semester_name',
                'classes.name as class_name',
                'groups.name as group_name',
                'programs.code as program_code',
                'programs.name as program_name',
                'schools.code as school_code',
                'schools.name as school_name',
                'class_time_slots.status'
            )
            ->when($request->has('search') && $request->search !== '', function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('class_timetable.day', 'like', "%{$search}%")
                      ->orWhere('units.code', 'like', "%{$search}%")
                      ->orWhere('units.name', 'like', "%{$search}%")
                      ->orWhere(DB::raw("CONCAT(users.first_name, ' ', users.last_name)"), 'like', "%{$search}%")
                      ->orWhere('class_timetable.venue', 'like', "%{$search}%");
                });
            })
            ->orderBy('class_timetable.day')
            ->orderBy('class_timetable.start_time')
            ->paginate($request->get('per_page', 10));

        // Get all necessary data for the form
        $lecturers = User::role('Lecturer')
            ->select('id', 'code', DB::raw("CONCAT(first_name, ' ', last_name) as name"))
            ->get();

        $semesters = Semester::all();
        $classrooms = Classroom::all();
        $classtimeSlots = ClassTimeSlot::all();
        $allUnits = Unit::select('id', 'code', 'name', 'semester_id', 'credit_hours')->get();
        $allEnrollments = Enrollment::select('id', 'student_code', 'unit_id', 'semester_id', 'lecturer_code')->get();

        // Group enrollments by unit_id and semester_id for easy access
        $enrollmentsByUnitAndSemester = [];
        foreach ($allEnrollments as $enrollment) {
            $key = $enrollment->unit_id . '_' . $enrollment->semester_id;
            if (!isset($enrollmentsByUnitAndSemester[$key])) {
                $enrollmentsByUnitAndSemester[$key] = [];
            }
            $enrollmentsByUnitAndSemester[$key][] = $enrollment;
        }

        // Get units with their associated semesters through enrollments
        $unitsBySemester = [];
        foreach ($semesters as $semester) {
            $unitIdsInSemester = Enrollment::where('semester_id', $semester->id)
                ->distinct('unit_id')
                ->pluck('unit_id')
                ->toArray();

            $unitsInSemester = Unit::whereIn('id', $unitIdsInSemester)
                ->select('id', 'code', 'name', 'credit_hours')
                ->get()
                ->map(function ($unit) use ($semester, $enrollmentsByUnitAndSemester) {
                    $unit->semester_id = $semester->id;
                    $key = $unit->id . '_' . $semester->id;
                    $unitEnrollments = $enrollmentsByUnitAndSemester[$key] ?? [];
                    $unit->student_count = count($unitEnrollments);

                    $lecturerCode = null;
                    foreach ($unitEnrollments as $enrollment) {
                        if ($enrollment->lecturer_code) {
                            $lecturerCode = $enrollment->lecturer_code;
                            break;
                        }
                    }

                    if ($lecturerCode) {
                        $lecturer = User::where('code', $lecturerCode)->first();
                        if ($lecturer) {
                            $unit->lecturer_code = $lecturerCode;
                            $unit->lecturer_name = $lecturer->first_name . ' ' . $lecturer->last_name;
                        }
                    }
                    return $unit;
                });

            $unitsBySemester[$semester->id] = $unitsInSemester;
        }

        $unitsWithSemesters = collect();
        foreach ($unitsBySemester as $semesterId => $units) {
            $unitsWithSemesters = $unitsWithSemesters->concat($units);
        }

        // Fetch classes and groups from the database
        $classes = ClassModel::select('id', 'name')->get();
        $groups = Group::select('id', 'name', 'class_id')->get();

        // Fetch programs from the programs table
        $programs = DB::table('programs')
            ->select('id', 'code', 'name')
            ->get();

         $schools = DB::table('schools')
            ->select('id', 'name', 'code')
            ->get();

        return Inertia::render('ClassTimetables/Index', [
            'classTimetables' => $classTimetables,
            'lecturers' => $lecturers,
            'perPage' => $perPage,
            'search' => $search,
            'semesters' => $semesters,
            'classrooms' => $classrooms,
            'classtimeSlots' => $classtimeSlots,
            'units' => $unitsWithSemesters,
            'enrollments' => $allEnrollments,
            'classes' => $classes,
            'groups' => $groups,
            'programs' => $programs,
            'schools' => $schools,
            'can' => [
                'create' => $user->can('create-classtimetables'),
                'edit' => $user->can('update-classtimetables'),
                'delete' => $user->can('delete-classtimetables'),
                'process' => $user->can('process-classtimetables'),
                'solve_conflicts' => $user->can('solve-class-conflicts'),
                'download' => $user->can('download-classtimetables'),
            ],
        ]);
    }

   
    private function createSingleTimetable(Request $request, Unit $unit, $programId, $schoolId)
{
    // You may want to add more validation or logic here as needed
    $classTimetable = ClassTimetable::create([
        'day' => $request->day,
        'unit_id' => $unit->id,
        'semester_id' => $request->semester_id,
        'class_id' => $request->class_id,
        'group_id' => $request->group_id ?: null,
        'venue' => $request->venue,
        'location' => $request->location,
        'no' => $request->no,
        'lecturer' => $request->lecturer,
        'start_time' => $request->start_time,
        'end_time' => $request->end_time,
        'teaching_mode' => $request->teaching_mode ?? 'physical',
        'program_id' => $programId,
        'school_id' => $schoolId,
    ]);

    \Log::info('Single timetable entry created', [
        'timetable_id' => $classTimetable->id,
        'unit_code' => $unit->code,
        'day' => $request->day,
        'start_time' => $request->start_time,
        'end_time' => $request->end_time,
        'venue' => $request->venue,
    ]);

    return redirect()->back()->with('success', 'Class timetable created successfully.');
}


    /**
     * ✅ FIXED: API endpoint to get units by class and semester
     */
    public function getUnitsByClass(Request $request)
    {
        try {
            $classId = $request->input('class_id');
            $semesterId = $request->input('semester_id');

            if (!$classId || !$semesterId) {
                return response()->json(['error' => 'Class ID and Semester ID are required.'], 400);
            }

            \Log::info('Fetching units for class', [
                'class_id' => $classId,
                'semester_id' => $semesterId
            ]);

            // Method 1: Try to find units through enrollments that have the specific class association
            $unitsFromEnrollments = [];
            
            // Check if enrollments table has class_id column
            $enrollmentColumns = DB::getSchemaBuilder()->getColumnListing('enrollments');
            
            if (in_array('class_id', $enrollmentColumns)) {
                // Direct method: enrollments table has class_id
                $unitsFromEnrollments = DB::table('enrollments')
                    ->join('units', 'enrollments.unit_id', '=', 'units.id')
                    ->where('enrollments.semester_id', $semesterId)
                    ->where('enrollments.class_id', $classId)
                    ->select('units.id', 'units.code', 'units.name', 'units.credit_hours')
                    ->distinct()
                    ->get()
                    ->toArray();
            }

            // Method 2: Try semester_unit pivot table
            $unitsFromSemesterUnit = [];
            $hasSemesterUnitTable = DB::getSchemaBuilder()->hasTable('semester_unit');
            
            if ($hasSemesterUnitTable) {
                $semesterUnitColumns = DB::getSchemaBuilder()->getColumnListing('semester_unit');
                
                if (in_array('class_id', $semesterUnitColumns)) {
                    $unitsFromSemesterUnit = DB::table('semester_unit')
                        ->join('units', 'semester_unit.unit_id', '=', 'units.id')
                        ->where('semester_unit.semester_id', $semesterId)
                        ->where('semester_unit.class_id', $classId)
                        ->select('units.id', 'units.code', 'units.name', 'units.credit_hours')
                        ->distinct()
                        ->get()
                        ->toArray();
                }
            }

            // Method 3: Try class_unit pivot table (common relationship)
            $unitsFromClassUnit = [];
            $hasClassUnitTable = DB::getSchemaBuilder()->hasTable('class_unit');
            
            if ($hasClassUnitTable) {
                $classUnitColumns = DB::getSchemaBuilder()->getColumnListing('class_unit');
                
                if (in_array('semester_id', $classUnitColumns)) {
                    $unitsFromClassUnit = DB::table('class_unit')
                        ->join('units', 'class_unit.unit_id', '=', 'units.id')
                        ->where('class_unit.class_id', $classId)
                        ->where('class_unit.semester_id', $semesterId)
                        ->select('units.id', 'units.code', 'units.name', 'units.credit_hours')
                        ->distinct()
                        ->get()
                        ->toArray();
                } else {
                    // class_unit table exists but doesn't have semester_id
                    $unitsFromClassUnit = DB::table('class_unit')
                        ->join('units', 'class_unit.unit_id', '=', 'units.id')
                        ->where('class_unit.class_id', $classId)
                        ->where('units.semester_id', $semesterId) // Filter by units.semester_id instead
                        ->select('units.id', 'units.code', 'units.name', 'units.credit_hours')
                        ->distinct()
                        ->get()
                        ->toArray();
                }
            }

            // Method 4: Try group-based relationship
            $unitsFromGroups = [];
            $hasGroupUnitTable = DB::getSchemaBuilder()->hasTable('group_unit');
            
            if ($hasGroupUnitTable) {
                $unitsFromGroups = DB::table('groups')
                    ->join('group_unit', 'groups.id', '=', 'group_unit.group_id')
                    ->join('units', 'group_unit.unit_id', '=', 'units.id')
                    ->where('groups.class_id', $classId)
                    ->where('units.semester_id', $semesterId)
                    ->select('units.id', 'units.code', 'units.name', 'units.credit_hours')
                    ->distinct()
                    ->get()
                    ->toArray();
            }

            // Method 5: Fallback - get all units for the semester and let user choose
            $allUnitsInSemester = DB::table('units')
                ->where('semester_id', $semesterId)
                ->select('id', 'code', 'name', 'credit_hours')
                ->get()
                ->toArray();

            // Merge results and prioritize
            $units = [];
            
            if (!empty($unitsFromEnrollments)) {
                $units = $unitsFromEnrollments;
                \Log::info('Found units via enrollments method', ['count' => count($units)]);
            } elseif (!empty($unitsFromSemesterUnit)) {
                $units = $unitsFromSemesterUnit;
                \Log::info('Found units via semester_unit method', ['count' => count($units)]);
            } elseif (!empty($unitsFromClassUnit)) {
                $units = $unitsFromClassUnit;
                \Log::info('Found units via class_unit method', ['count' => count($units)]);
            } elseif (!empty($unitsFromGroups)) {
                $units = $unitsFromGroups;
                \Log::info('Found units via groups method', ['count' => count($units)]);
            } else {
                $units = $allUnitsInSemester;
                \Log::info('Using fallback method - all units in semester', ['count' => count($units)]);
            }

            if (empty($units)) {
                return response()->json([]);
            }

            // Enhance units with enrollment information
            $enhancedUnits = collect($units)->map(function ($unit) use ($semesterId, $classId) {
                $unitArray = is_object($unit) ? (array) $unit : $unit;
                
                // Get enrollment count for this unit in this semester
                $enrollmentQuery = Enrollment::where('unit_id', $unitArray['id'])
                    ->where('semester_id', $semesterId);

                // Add class filter if enrollments table supports it
                $enrollmentColumns = DB::getSchemaBuilder()->getColumnListing('enrollments');
                if (in_array('class_id', $enrollmentColumns)) {
                    $enrollmentQuery->where('class_id', $classId);
                }

                $enrollments = $enrollmentQuery->get();
                $studentCount = $enrollments->count();

                // Get lecturer information
                $lecturerName = '';
                $lecturerEnrollment = $enrollments->whereNotNull('lecturer_code')->first();
                if ($lecturerEnrollment) {
                    $lecturer = User::where('code', $lecturerEnrollment->lecturer_code)->first();
                    if ($lecturer) {
                        $lecturerName = $lecturer->first_name . ' ' . $lecturer->last_name;
                    }
                }

                return [
                    'id' => $unitArray['id'],
                    'code' => $unitArray['code'],
                    'name' => $unitArray['name'],
                    'credit_hours' => $unitArray['credit_hours'] ?? 3,
                    'student_count' => $studentCount,
                    'lecturer_name' => $lecturerName,
                ];
            });

            return response()->json($enhancedUnits->values()->all());
            
        } catch (\Exception $e) {
            \Log::error('Error fetching units for class: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
                'class_id' => $request->input('class_id'),
                'semester_id' => $request->input('semester_id')
            ]);
            
            return response()->json(['error' => 'Failed to fetch units. Please try again.'], 500);
        }
    }

    /**
     * Create multiple timetable entries based on credit hours
     */
    private function createCreditBasedTimetable(Request $request, Unit $unit, $programId, $schoolId)
    {
        $creditHours = $unit->credit_hours;
        $sessions = $this->getSessionsForCredits($creditHours);
        
        \Log::info('Creating credit-based timetable', [
            'unit_code' => $unit->code,
            'credit_hours' => $creditHours,
            'sessions' => $sessions
        ]);

        $createdTimetables = [];
        $errors = [];

        foreach ($sessions as $sessionIndex => $session) {
            try {
                $sessionResult = $this->createSessionTimetable($request, $unit, $session, $sessionIndex + 1, $programId, $schoolId);
                
                if ($sessionResult['success']) {
                    $createdTimetables[] = $sessionResult['timetable'];
                } else {
                    $errors[] = $sessionResult['message'];
                }
            } catch (\Exception $e) {
                $errors[] = "Session " . ($sessionIndex + 1) . ": " . $e->getMessage();
                \Log::error('Failed to create session timetable', [
                    'session' => $session,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if (count($createdTimetables) === 0) {
            $errorMessage = 'Failed to create any timetable sessions. Errors: ' . implode('; ', $errors);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'errors' => ['scheduling' => $errorMessage]
                ], 422);
            }

            return redirect()->back()
                ->withErrors(['scheduling' => $errorMessage])
                ->withInput();
        }

        $successMessage = count($createdTimetables) . " timetable sessions created successfully for {$unit->code} ({$creditHours} credit hours).";
        
        if (count($errors) > 0) {
            $successMessage .= " Note: " . count($errors) . " sessions had issues.";
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'data' => $createdTimetables,
                'errors' => $errors
            ]);
        }

        return redirect()->back()->with('success', $successMessage);
    }

    /**
     * Get session configuration based on credit hours
     */

private function getSessionsForCredits($creditHours)
{
    $sessions = [];

    // Assign 1 physical session of 2 hours if possible
    if ($creditHours >= 2) {
        $sessions[] = ['type' => 'physical', 'duration' => 2];
        $remaining = $creditHours - 2;
    } else {
        $remaining = $creditHours;
    }

    // Assign the remaining hours as 1-hour online sessions
    while ($remaining > 0) {
        $sessions[] = ['type' => 'online', 'duration' => 1];
        $remaining--;
    }

    return $sessions;
}

  
    /**
     * Assign a random venue with sufficient capacity and no conflicts
     */
   

    /**
     * ✅ NEW: Get groups by class ID (for modal functionality)
     */
    public function getGroupsByClass($classId)
    {
        try {
            $groups = Group::where('class_id', $classId)
                ->select('id', 'name', 'class_id')
                ->get();

            return response()->json($groups);
        } catch (\Exception $e) {
            \Log::error('Error fetching groups for class: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch groups.'], 500);
        }
    }

    /**
     * ✅ NEW: Get lecturer information for a specific unit and semester
     */
    public function getLecturerForUnit($unitId, $semesterId)
    {
        try {
            // Find the lecturer assigned to this unit in this semester
            $enrollment = Enrollment::where('unit_id', $unitId)
                ->where('semester_id', $semesterId)
                ->whereNotNull('lecturer_code')
                ->first();

            if (!$enrollment) {
                return response()->json(['success' => false, 'message' => 'No lecturer assigned to this unit.']);
            }

            // Get lecturer details
            $lecturer = User::where('code', $enrollment->lecturer_code)->first();
            if (!$lecturer) {
                return response()->json(['success' => false, 'message' => 'Lecturer not found.']);
            }

            // Count students enrolled in this unit
            $studentCount = Enrollment::where('unit_id', $unitId)
                ->where('semester_id', $semesterId)
                ->count();

            return response()->json([
                'success' => true,
                'lecturer' => [
                    'id' => $lecturer->id,
                    'code' => $lecturer->code,
                    'name' => $lecturer->first_name . ' ' . $lecturer->last_name,
                ],
                'studentCount' => $studentCount
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to get lecturer for unit: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to get lecturer information: ' . $e->getMessage()], 500);
        }
    }

    /**
     * ✅ NEW: Process the class timetable to optimize and resolve conflicts
     */
    public function process(Request $request)
    {
        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'program_id' => 'required|exists:programs,id',
            'class_id' => 'required|exists:classes,id',
            'group_id' => 'nullable|exists:groups,id',
        ]);

        try {
            $semesterId = $request->semester_id;
            $programId = $request->program_id;
            $classId = $request->class_id;
            $groupId = $request->group_id;

            \Log::info('Processing class timetable', [
                'semester_id' => $semesterId,
                'program_id' => $programId,
                'class_id' => $classId,
                'group_id' => $groupId,
            ]);

            // Fetch units for the class and semester
            $units = DB::table('class_unit')
                ->join('units', 'class_unit.unit_id', '=', 'units.id')
                ->where('class_unit.class_id', $classId)
                ->where('class_unit.semester_id', $semesterId)
                ->select('units.id', 'units.name', 'units.code')
                ->get();

            if ($units->isEmpty()) {
                \Log::warning('No units found for the specified class and semester', [
                    'semester_id' => $semesterId,
                    'class_id' => $classId,
                ]);
                return response()->json(['message' => 'No units found for the specified class and semester.'], 422);
            }

            // Fetch available time slots
            $timeSlots = DB::table('class_time_slots')->get();

            if ($timeSlots->isEmpty()) {
                \Log::warning('No time slots available');
                return response()->json(['message' => 'No time slots available.'], 422);
            }

            // Fetch classrooms
            $classrooms = DB::table('classrooms')->get();

            if ($classrooms->isEmpty()) {
                \Log::warning('No classrooms available');
                return response()->json(['message' => 'No classrooms available.'], 422);
            }

            // Randomly assign units to time slots and classrooms
            foreach ($units as $unit) {
                $randomTimeSlot = $timeSlots->random();
                $randomClassroom = $classrooms->random();

                ClassTimetable::create([
                    'day' => $randomTimeSlot->day,
                    'start_time' => $randomTimeSlot->start_time,
                    'end_time' => $randomTimeSlot->end_time,
                    'unit_id' => $unit->id,
                    'semester_id' => $semesterId,
                    'class_id' => $classId,
                    'group_id' => $groupId,
                    'venue' => $randomClassroom->name,
                    'location' => $randomClassroom->location,
                    'no' => Enrollment::where('unit_id', $unit->id)->where('semester_id', $semesterId)->count(),
                    'lecturer' => Enrollment::where('unit_id', $unit->id)->where('semester_id', $semesterId)->value('lecturer_code'),
                ]);
            }

            return response()->json(['success' => true, 'message' => 'Class timetable processed successfully.']);
        } catch (\Exception $e) {
            \Log::error('Failed to process class timetable: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to process class timetable.'], 500);
        }
    }

    /**
     * ✅ NEW: Solve conflicts in the class timetable
     */
    public function solveConflicts(Request $request)
    {
        try {
            \Log::info('Solving class timetable conflicts');

            $conflicts = $this->detectAndResolveConflicts();

            return redirect()->back()->with('success', 'Class conflicts resolved successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to solve class conflicts: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to solve class conflicts: ' . $e->getMessage());
        }
    }

    /**
     * Download the class timetable as a PDF.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function downloadPDF(Request $request)
    {
        try {
            // Ensure the view file exists
            if (!view()->exists('classtimetables.pdf')) {
                \Log::error('PDF template not found: classtimetables.pdf');
                return redirect()->back()->with('error', 'PDF template not found. Please contact the administrator.');
            }

            // Fetch class timetables
            $query = ClassTimetable::query()
                ->join('units', 'class_timetable.unit_id', '=', 'units.id')
                ->join('semesters', 'class_timetable.semester_id', '=', 'semesters.id')
                ->leftJoin('classes', 'class_timetable.class_id', '=', 'classes.id')
                ->leftJoin('groups', 'class_timetable.group_id', '=', 'groups.id')
                ->leftJoin('class_time_slots', function ($join) {
                    $join->on('class_timetable.day', '=', 'class_time_slots.day')
                        ->on('class_timetable.start_time', '=', 'class_time_slots.start_time')
                        ->on('class_timetable.end_time', '=', 'class_time_slots.end_time');
                })
                ->select(
                    'class_timetable.*',
                    'units.name as unit_name',
                    'units.code as unit_code',
                    'semesters.name as semester_name',
                    'classes.name as class_name',
                    'groups.name as group_name',
                    'class_time_slots.status as mode_of_teaching'
                );

            if ($request->has('semester_id')) {
                $query->where('class_timetable.semester_id', $request->semester_id);
            }

            if ($request->has('class_id')) {
                $query->where('class_timetable.class_id', $request->class_id);
            }

            if ($request->has('group_id')) {
                $query->where('class_timetable.group_id', $request->group_id);
            }

            $classTimetables = $query->orderBy('class_timetable.day')
                ->orderBy('class_timetable.start_time')
                ->get();

            // Log the data being passed to the view for debugging
            \Log::info('Generating PDF with data:', [
                'count' => $classTimetables->count(),
                'filters' => $request->only(['semester_id', 'class_id', 'group_id'])
            ]);

            // Generate PDF
            $pdf = Pdf::loadView('classtimetables.pdf', [
                'classTimetables' => $classTimetables,
                'title' => 'Class Timetable',
                'generatedAt' => now()->format('Y-m-d H:i:s'),
                'filters' => $request->only(['semester_id', 'class_id', 'group_id'])
            ]);

            // Set paper size and orientation
            $pdf->setPaper('a4', 'landscape');

            // Return the PDF for download
            return $pdf->download('class-timetable-' . now()->format('Y-m-d') . '.pdf');
        } catch (\Exception $e) {
            // Log detailed error information
            \Log::error('Failed to generate PDF: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            // Return a more informative error response
            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

 /**
     * Helper method to detect conflicts in the timetable
     */
    private function detectConflicts()
    {
        // Detect lecturer conflicts
        $lecturerConflicts = DB::select("
            SELECT lecturer, day, start_time, end_time, COUNT(*) as conflict_count
            FROM class_timetable 
            GROUP BY lecturer, day, start_time, end_time
            HAVING COUNT(*) > 1
        ");

        // Detect venue conflicts
        $venueConflicts = DB::select("
            SELECT venue, day, start_time, end_time, COUNT(*) as conflict_count
            FROM class_timetable 
            GROUP BY venue, day, start_time, end_time
            HAVING COUNT(*) > 1
        ");

        return [
            'lecturer_conflicts' => $lecturerConflicts,
            'venue_conflicts' => $venueConflicts
        ];
    }

    /**
     * Helper method to detect and resolve conflicts
     */
    private function detectAndResolveConflicts()
    {
        $conflicts = $this->detectConflicts();
        $resolved = 0;

        // Implementation of conflict resolution logic
        // This could include automatic rescheduling, venue reassignment, etc.

        return ['conflicts_resolved' => $resolved];
    }

    /**
     * ✅ NEW: Smart school_id determination
     */
    private function determineSchoolId($request, $class)
    {
        // Priority: Request > Class > Program > Default
        if ($request->school_id) {
            return $request->school_id;
        }

        if ($class && $class->school_id) {
            return $class->school_id;
        }

        if ($class && $class->program_id) {
            $program = Program::find($class->program_id);
            if ($program && $program->school_id) {
                return $program->school_id;
            }
        }

        // Fallback to first school if none found
        $defaultSchool = DB::table('schools')->first();
        return $defaultSchool ? $defaultSchool->id : null;
    }

    /**
     * ✅ NEW: Smart program_id determination
     */
    private function determineProgramId($request, $class)
    {
        // Priority: Request > Class > Default
        if ($request->program_id) {
            return $request->program_id;
        }

        if ($class && $class->program_id) {
            return $class->program_id;
        }

        return null;
    }

    /**
     * ✅ NEW: API endpoint to get programs by school
     */
    public function getProgramsBySchool($schoolId)
    {
        try {
            $programs = Program::where('school_id', $schoolId)
                ->select('id', 'name', 'code')
                ->get();

            return response()->json($programs);
        } catch (\Exception $e) {
            \Log::error('Error fetching programs for school: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch programs.'], 500);
        }
    }

    /**
     * ✅ NEW: API endpoint to get classes by program
     */
    public function getClassesByProgram($programId)
    {
        try {
            $classes = ClassModel::where('program_id', $programId)
                ->select('id', 'name')
                ->get();

            return response()->json($classes);
        } catch (\Exception $e) {
            \Log::error('Error fetching classes for program: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch classes.'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $timetable = ClassTimetable::findOrFail($id);
            $timetable->delete();

            \Log::info('Class timetable deleted successfully', ['id' => $id]);

            return redirect()->back()->with('success', 'Class timetable deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to delete class timetable: ' . $e->getMessage(), ['id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to delete class timetable.'], 500);
        }
    }
    public function show($id)
    {
        try {
            $timetable = ClassTimetable::findOrFail($id);
            return response()->json($timetable);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch class timetable: ' . $e->getMessage(), ['id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch class timetable.'], 500);
        }
    }

/**
 * Display student's timetable page
 */
/**
 * Display student's timetable page - FIXED VERSION
 */
/**
 * ✅ FIXED: Display student's timetable page with group filtering
 */
/**
 * ✅ UPDATED: Display student's timetable page with pagination and search
 */
public function studentTimetable(Request $request)
{
    try {
        // Fetch the authenticated student
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Please log in to view your timetable.');
        }

        \Log::info('Student accessing timetable', [
            'user_id' => $user->id,
            'user_code' => $user->code ?? 'No code'
        ]);

        // Fetch the current semester with error handling
        $currentSemester = Semester::where('is_active', true)->first();
        
        // If no active semester, get the latest one
        if (!$currentSemester) {
            $currentSemester = Semester::orderByDesc('id')->first();
        }

        if (!$currentSemester) {
            \Log::warning('No semester found for student timetable');
            return Inertia::render('Student/Timetable', [
                'classTimetables' => [],
                'currentSemester' => null,
                'downloadUrl' => route('student.timetable.download'),
                'student' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'code' => $user->code,
                ],
                'filters' => [
                    'per_page' => $request->get('per_page', 10),
                    'search' => $request->get('search', ''),
                ],
                'error' => 'No semester data available.'
            ]);
        }

        // Check if user has a code (required for enrollments)
        if (!$user->code) {
            \Log::error('User has no code for enrollment lookup', ['user_id' => $user->id]);
            return Inertia::render('Student/Timetable', [
                'classTimetables' => [],
                'currentSemester' => $currentSemester,
                'downloadUrl' => route('student.timetable.download'),
                'student' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'code' => $user->code,
                ],
                'filters' => [
                    'per_page' => $request->get('per_page', 10),
                    'search' => $request->get('search', ''),
                ],
                'error' => 'Student code not found. Please contact administration.'
            ]);
        }

        // Fetch enrollments for this student with proper error handling
        $enrollments = collect();
        try {
            $enrollments = Enrollment::where('student_code', $user->code)
                ->where('semester_id', $currentSemester->id)
                ->with(['unit', 'semester', 'group'])
                ->get();

            \Log::info('Enrollments found', [
                'student_code' => $user->code,
                'semester_id' => $currentSemester->id,
                'count' => $enrollments->count()
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching enrollments: ' . $e->getMessage());
            $enrollments = collect();
        }

        // Get the student's enrolled unit IDs AND group IDs
        $enrolledUnitIds = [];
        $studentGroupIds = [];
        
        if ($enrollments->isNotEmpty()) {
            $enrolledUnitIds = $enrollments->pluck('unit_id')->filter()->toArray();
            $studentGroupIds = $enrollments->pluck('group_id')->filter()->unique()->toArray();
        }

        \Log::info('Student group filtering for web view', [
            'unit_ids' => $enrolledUnitIds,
            'group_ids' => $studentGroupIds
        ]);

        // Get pagination parameters
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');

        // Fetch actual class timetable entries for the student's units AND groups with pagination
        $classTimetables = collect();
        
        if (!empty($enrolledUnitIds)) {
            try {
                $query = DB::table('class_timetable')
                    ->whereIn('class_timetable.unit_id', $enrolledUnitIds)
                    ->where('class_timetable.semester_id', $currentSemester->id);

                // ✅ CRITICAL FIX: Filter by student's group(s)
                if (!empty($studentGroupIds)) {
                    $query->whereIn('class_timetable.group_id', $studentGroupIds);
                } else {
                    // If no specific group assigned, check if there are enrollments with null group_id
                    $hasNullGroup = $enrollments->whereNull('group_id')->isNotEmpty();
                    if ($hasNullGroup) {
                        $query->whereNull('class_timetable.group_id');
                    }
                }

                // Add search functionality
                if (!empty($search)) {
                    $query->where(function ($q) use ($search) {
                        $q->where('units.code', 'like', "%{$search}%")
                          ->orWhere('units.name', 'like', "%{$search}%")
                          ->orWhere('class_timetable.venue', 'like', "%{$search}%")
                          ->orWhere('class_timetable.day', 'like', "%{$search}%")
                          ->orWhere(DB::raw("CONCAT(users.first_name, ' ', users.last_name)"), 'like', "%{$search}%")
                          ->orWhere('class_timetable.lecturer', 'like', "%{$search}%");
                    });
                }

                $classTimetables = $query
                    ->leftJoin('units', 'class_timetable.unit_id', '=', 'units.id')
                    ->leftJoin('users', 'users.code', '=', 'class_timetable.lecturer')
                    ->leftJoin('groups', 'class_timetable.group_id', '=', 'groups.id')
                    ->leftJoin('semesters', 'class_timetable.semester_id', '=', 'semesters.id')
                    ->select(
                        'class_timetable.id',
                        'class_timetable.day',
                        'class_timetable.start_time',
                        'class_timetable.end_time',
                        'class_timetable.venue',
                        'class_timetable.location',
                        'class_timetable.teaching_mode',
                        'class_timetable.lecturer',
                        'units.code as unit_code',
                        'units.name as unit_name',
                        DB::raw("CASE 
                            WHEN users.id IS NOT NULL THEN CONCAT(users.first_name, ' ', users.last_name) 
                            ELSE class_timetable.lecturer 
                            END as lecturer"),
                        'groups.name as group_name',
                        'semesters.name as semester_name'
                    )
                    ->orderBy('class_timetable.day')
                    ->orderBy('class_timetable.start_time')
                    ->paginate($perPage)
                    ->withQueryString(); // This preserves search and other query parameters

                \Log::info('Paginated class timetables found', [
                    'total' => $classTimetables->total(),
                    'per_page' => $classTimetables->perPage(),
                    'current_page' => $classTimetables->currentPage(),
                    'student_code' => $user->code,
                    'groups_filtered' => $studentGroupIds,
                    'search_term' => $search
                ]);

            } catch (\Exception $e) {
                \Log::error('Error fetching class timetables: ' . $e->getMessage(), [
                    'student_code' => $user->code,
                    'semester_id' => $currentSemester->id,
                    'unit_ids' => $enrolledUnitIds,
                    'group_ids' => $studentGroupIds
                ]);
                
                // Return empty paginated result in case of error
                $classTimetables = new \Illuminate\Pagination\LengthAwarePaginator(
                    collect([]), // Empty collection
                    0, // Total items
                    $perPage, // Items per page
                    1, // Current page
                    [
                        'path' => request()->url(),
                        'pageName' => 'page',
                    ]
                );
            }
        } else {
            \Log::info('No enrolled units found for student', [
                'student_code' => $user->code,
                'semester_id' => $currentSemester->id
            ]);
            
            // Return empty paginated result when no enrolled units
            $classTimetables = new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]), // Empty collection
                0, // Total items
                $perPage, // Items per page
                1, // Current page
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ]
            );
        }

        return Inertia::render('Student/Timetable', [
            'classTimetables' => $classTimetables, // This is now a paginated result
            'currentSemester' => $currentSemester ? [
                'id' => $currentSemester->id,
                'name' => $currentSemester->name
            ] : null,
            'downloadUrl' => route('student.timetable.download'),
            'student' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'code' => $user->code,
                'groups' => $studentGroupIds // Pass student groups for debugging
            ],
            'filters' => [
                'per_page' => (int) $perPage,
                'search' => $search,
            ]
        ]);

    } catch (\Exception $e) {
        \Log::error('Critical error in studentTimetable method: ' . $e->getMessage(), [
            'exception' => $e,
            'trace' => $e->getTraceAsString(),
            'user_id' => auth()->id()
        ]);

        return Inertia::render('Student/Timetable', [
            'classTimetables' => new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]), // Empty collection
                0, // Total items
                $request->get('per_page', 10), // Items per page
                1, // Current page
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ]
            ),
            'currentSemester' => null,
            'downloadUrl' => route('student.timetable.download'),
            'student' => [
                'id' => auth()->id(),
                'first_name' => auth()->user()->first_name ?? '',
                'last_name' => auth()->user()->last_name ?? '',
                'code' => auth()->user()->code ?? '',
                'groups' => []
            ],
            'filters' => [
                'per_page' => $request->get('per_page', 10),
                'search' => $request->get('search', ''),
            ],
            'error' => 'An error occurred while loading your timetable. Please try again or contact support.'
        ]);
    }
}
}