<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Timetable;

class ExamTimetableController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $perPage = $request->input('per_page', 10);

        $query = Timetable::query()
            ->with(['unit', 'classroom', 'lecturer'])
            ->when($search, function ($q) use ($search) {
                $q->whereHas('unit', function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%");
                });
            });

        $examTimetables = $query->paginate($perPage);

        return inertia('ExamTimetable/index', [
            'examTimetables' => $examTimetables,
            'perPage' => $perPage,
            'search' => $search,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'classroom_id' => 'required|exists:classrooms,id',
            'lecturer_id' => 'required|exists:users,id',
            'semester_id' => 'required|exists:semesters,id',
            'day' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'group' => 'nullable|string',
            'venue' => 'nullable|string',
            'no' => 'nullable|integer',
            'chief_invigilator' => 'nullable|string',
        ]);

        Timetable::create($validated);

        return redirect()->route('exam-timetables.index')->with('success', 'Exam timetable created successfully.');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'classroom_id' => 'required|exists:classrooms,id',
            'lecturer_id' => 'required|exists:users,id',
            'semester_id' => 'required|exists:semesters,id',
            'day' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'group' => 'nullable|string',
            'venue' => 'nullable|string',
            'no' => 'nullable|integer',
            'chief_invigilator' => 'nullable|string',
        ]);

        $timetable = Timetable::findOrFail($id);
        $timetable->update($validated);

        return redirect()->route('exam-timetables.index')->with('success', 'Exam timetable updated successfully.');
    }

    public function destroy($id)
    {
        $timetable = Timetable::findOrFail($id);
        $timetable->delete();

        return redirect()->route('exam-timetables.index')->with('success', 'Exam timetable deleted successfully.');
    }
}
