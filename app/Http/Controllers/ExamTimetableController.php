<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ExamTimetable;
use App\Models\Semester;
use App\Models\Enrollment;
use App\Models\TimeSlot;
use App\Models\Classroom;
use Inertia\Inertia;

class ExamTimetableController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');

        // ✅ Join units and fetch unit_name alongside examTimetables
        $examTimetables = ExamTimetable::query()
            ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
            ->select('exam_timetables.*', 'units.name as unit_name') // ✅ Add unit_name to the selection
            ->when($search, function ($query, $search) {
                return $query->where('exam_timetables.day', 'like', "%{$search}%")
                             ->orWhere('exam_timetables.venue', 'like', "%{$search}%");
            })
            ->paginate($perPage);

        $semesters = Semester::all();

        $enrollments = Enrollment::selectRaw("
                MIN(enrollments.id) as id,
                enrollments.unit_id,
                units.name as unit_name,
                enrollments.semester_id,
                COUNT(DISTINCT enrollments.student_id) as student_count,
                MAX(enrollments.lecturer_id) as lecturer_id,
                CONCAT(MAX(users.first_name), ' ', MAX(users.last_name)) as lecturer_name
            ")
            ->join('units', 'units.id', '=', 'enrollments.unit_id')
            ->leftJoin('users', 'users.id', '=', 'enrollments.lecturer_id')
            ->groupBy('enrollments.unit_id', 'units.name', 'enrollments.semester_id')
            ->get();

        $timeSlots = TimeSlot::select('id', 'day', 'date', 'start_time', 'end_time')->get();

        $classrooms = Classroom::all();

        return Inertia::render('ExamTimetable/index', [
            'examTimetables' => $examTimetables,
            'perPage' => $perPage,
            'search' => $search,
            'semesters' => $semesters,
            'enrollments' => $enrollments,
            'timeSlots' => $timeSlots,
            'classrooms' => $classrooms,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
            'semester_id' => 'required|exists:semesters,id',
            'day' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'group' => 'nullable|string',
            'venue' => 'required|string',
            'location' => 'nullable|string',
            'no' => 'required|integer',
            'chief_invigilator' => 'required|string',
        ]);

        // Get the unit_id from the enrollment
        $enrollment = Enrollment::find($validated['enrollment_id']);
        
        // Get the classroom capacity
        $classroom = Classroom::where('name', $validated['venue'])->first();
        
        if (!$classroom) {
            return redirect()->back()->with('error', 'Classroom not found.');
        }
        
        // Check for time conflicts in the same venue
        $conflictingExams = ExamTimetable::where('venue', $validated['venue'])
            ->where('date', $validated['date'])
            ->where(function($query) use ($validated) {
                $query->where(function($q) use ($validated) {
                    $q->where('start_time', '<=', $validated['start_time'])
                      ->where('end_time', '>', $validated['start_time']);
                })->orWhere(function($q) use ($validated) {
                    $q->where('start_time', '<', $validated['end_time'])
                      ->where('end_time', '>=', $validated['end_time']);
                })->orWhere(function($q) use ($validated) {
                    $q->where('start_time', '>=', $validated['start_time'])
                      ->where('end_time', '<=', $validated['end_time']);
                });
            })
            ->get();
        
        // Calculate total students already scheduled
        $totalStudents = $conflictingExams->sum('no');
        
        // Add this right before the capacity check
        \Log::debug("Exam scheduling validation: Venue: {$validated['venue']}, Capacity: {$classroom->capacity}, Current students: {$totalStudents}, New students: {$validated['no']}, Total: " . ($totalStudents + $validated['no']));

        // Check if adding this exam would exceed classroom capacity
        if ($totalStudents + $validated['no'] > $classroom->capacity) {
            return redirect()->back()->with('error', 
                "Cannot schedule this exam. The classroom {$validated['venue']} has a capacity of {$classroom->capacity}, " .
                "but there would be " . ($totalStudents + $validated['no']) . " students scheduled at this time " .
                "(exceeding capacity by " . (($totalStudents + $validated['no']) - $classroom->capacity) . " students)."
            )->withInput();
        }
        
        // Create the data to be stored
        $data = [
            'semester_id' => $validated['semester_id'],
            'unit_id' => $enrollment->unit_id,
            'day' => $validated['day'],
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'group' => $validated['group'] ?? '',
            'venue' => $validated['venue'],
            'no' => $validated['no'],
            'chief_invigilator' => $validated['chief_invigilator'],
        ];

        // Add location if it exists in the request
        if (isset($validated['location'])) {
            $data['location'] = $validated['location'];
        }

        ExamTimetable::create($data);

        return redirect()->back()->with('success', 'Exam timetable created successfully.');
    }
    
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
            'semester_id' => 'required|exists:semesters,id',
            'day' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'group' => 'nullable|string',
            'venue' => 'required|string',
            'location' => 'nullable|string',
            'no' => 'required|integer',
            'chief_invigilator' => 'required|string',
        ]);

        // Get the unit_id from the enrollment
        $enrollment = Enrollment::find($validated['enrollment_id']);
        
        // Get the classroom capacity
        $classroom = Classroom::where('name', $validated['venue'])->first();
        
        if (!$classroom) {
            return redirect()->back()->with('error', 'Classroom not found.');
        }
        
        // Check for time conflicts in the same venue, excluding the current exam
        $conflictingExams = ExamTimetable::where('id', '!=', $id)
            ->where('venue', $validated['venue'])
            ->where('date', $validated['date'])
            ->where(function($query) use ($validated) {
                $query->where(function($q) use ($validated) {
                    $q->where('start_time', '<=', $validated['start_time'])
                      ->where('end_time', '>', $validated['start_time']);
                })->orWhere(function($q) use ($validated) {
                    $q->where('start_time', '<', $validated['end_time'])
                      ->where('end_time', '>=', $validated['end_time']);
                })->orWhere(function($q) use ($validated) {
                    $q->where('start_time', '>=', $validated['start_time'])
                      ->where('end_time', '<=', $validated['end_time']);
                });
            })
            ->get();
        
        // Calculate total students already scheduled
        $totalStudents = $conflictingExams->sum('no');
        
        // Add this right before the capacity check
        \Log::debug("Exam scheduling validation: Venue: {$validated['venue']}, Capacity: {$classroom->capacity}, Current students: {$totalStudents}, New students: {$validated['no']}, Total: " . ($totalStudents + $validated['no']));

        // Check if updating this exam would exceed classroom capacity
        if ($totalStudents + $validated['no'] > $classroom->capacity) {
            return redirect()->back()->with('error', 
                "Cannot schedule this exam. The classroom {$validated['venue']} has a capacity of {$classroom->capacity}, " .
                "but there would be " . ($totalStudents + $validated['no']) . " students scheduled at this time " .
                "(exceeding capacity by " . (($totalStudents + $validated['no']) - $classroom->capacity) . " students)."
            )->withInput();
        }
        
        // Create the data to be updated
        $data = [
            'semester_id' => $validated['semester_id'],
            'unit_id' => $enrollment->unit_id,
            'day' => $validated['day'],
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'group' => $validated['group'] ?? '',
            'venue' => $validated['venue'],
            'no' => $validated['no'],
            'chief_invigilator' => $validated['chief_invigilator'],
        ];

        // Add location if it exists in the request
        if (isset($validated['location'])) {
            $data['location'] = $validated['location'];
        }

        $timetable = ExamTimetable::findOrFail($id);
        $timetable->update($data);

        return redirect()->back()->with('success', 'Exam timetable updated successfully.');
    }

    public function destroy($id)
    {
        $timetable = ExamTimetable::findOrFail($id);
        $timetable->delete();

        return redirect()->route('exam-timetables.index')->with('success', 'Exam timetable deleted successfully.');
    }
    
    // Helper function to check for time overlap
    private function checkTimeOverlap($exam1, $exam2)
    {
        // Convert times to minutes for easier comparison
        $start1 = $this->timeToMinutes($exam1['start_time']);
        $end1 = $this->timeToMinutes($exam1['end_time']);
        $start2 = $this->timeToMinutes($exam2['start_time']);
        $end2 = $this->timeToMinutes($exam2['end_time']);
        
        // Check for overlap
        return ($start1 < $end2 && $start2 < $end1);
    }

    private function timeToMinutes($time)
    {
        list($hours, $minutes) = explode(':', $time);
        return ($hours * 60) + $minutes;
    }
}
