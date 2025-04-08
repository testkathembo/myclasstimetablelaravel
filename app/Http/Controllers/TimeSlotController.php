<?php
namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\TimeSlot;
use Illuminate\Http\Request;

class TimeSlotController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $perPage = $request->input('per_page', 10);

        $timeSlots = TimeSlot::query()
            ->when($search, fn($query) => $query->where('day', 'like', "%{$search}%"))
            ->paginate($perPage)
            ->appends(['search' => $search, 'per_page' => $perPage]);

        // Add the day of the week for each time slot
        $timeSlots->getCollection()->transform(function ($timeSlot) {
            $timeSlot->day = date('l', strtotime($timeSlot->date)); // Get the day of the week
            return $timeSlot;
        });

        return Inertia::render('TimeSlots/index', [
            'timeSlots' => $timeSlots,
            'perPage' => $perPage,
            'search' => $search,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'day' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        TimeSlot::create($validated);

        return redirect()->route('timeslots.index')->with('success', 'Time slot created successfully.');
    }

    public function update(Request $request, TimeSlot $timeSlot)
    {
        $validated = $request->validate([
            'day' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        $timeSlot->update($validated);

        return redirect()->route('timeslots.index')->with('success', 'Time slot updated successfully.');
    }

    public function destroy(TimeSlot $timeSlot)
    {
        $timeSlot->delete();

        return redirect()->route('timeslots.index')->with('success', 'Time slot deleted successfully.');
    }
}
