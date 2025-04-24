<?php

namespace App\Http\Controllers;

use App\Models\TimeSlot;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TimeSlotController extends Controller
{
    /**
     * Display a listing of the time slots.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $timeSlots = TimeSlot::orderBy('start_time')->get();
        
        return Inertia::render('Admin/TimeSlots/Index', [
            'timeSlots' => $timeSlots
        ]);
    }

    /**
     * Show the form for creating a new time slot.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        return Inertia::render('Admin/TimeSlots/Create');
    }

    /**
     * Store a newly created time slot in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'day_of_week' => 'nullable|integer|min:0|max:6',
        ]);

        TimeSlot::create($validated);

        return redirect()->route('timeslots.index')->with('success', 'Time slot created successfully.');
    }

    /**
     * Display the specified time slot.
     *
     * @param  \App\Models\TimeSlot  $timeSlot
     * @return \Inertia\Response
     */
    public function show(TimeSlot $timeSlot)
    {
        return Inertia::render('Admin/TimeSlots/Show', [
            'timeSlot' => $timeSlot
        ]);
    }

    /**
     * Show the form for editing the specified time slot.
     *
     * @param  \App\Models\TimeSlot  $timeSlot
     * @return \Inertia\Response
     */
    public function edit(TimeSlot $timeSlot)
    {
        return Inertia::render('Admin/TimeSlots/Edit', [
            'timeSlot' => $timeSlot
        ]);
    }

    /**
     * Update the specified time slot in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\TimeSlot  $timeSlot
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, TimeSlot $timeSlot)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'day_of_week' => 'nullable|integer|min:0|max:6',
        ]);

        $timeSlot->update($validated);

        return redirect()->route('timeslots.index')->with('success', 'Time slot updated successfully.');
    }

    /**
     * Remove the specified time slot from storage.
     *
     * @param  \App\Models\TimeSlot  $timeSlot
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(TimeSlot $timeSlot)
    {
        // Check if time slot is in use before deleting
        if ($timeSlot->examTimetables()->count() > 0) {
            return redirect()->route('timeslots.index')->with('error', 'Cannot delete time slot that is in use by timetables.');
        }
        
        $timeSlot->delete();

        return redirect()->route('timeslots.index')->with('success', 'Time slot deleted successfully.');
    }
}