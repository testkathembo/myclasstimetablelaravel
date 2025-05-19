<?php
namespace App\Http\Controllers;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LecturerAssignmentController extends Controller
{
    /**
     * Remove the specified lecturer assignment.
     *
     * @param  int  $unitId
     * @param  string  $lecturerCode
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($unitId, $lecturerCode)
    {
        try {
            // Remove the lecturer assignment from the enrollments table
            Enrollment::where('unit_id', $unitId)
                ->where('lecturer_code', $lecturerCode)
                ->update(['lecturer_code' => null]);

            return redirect()->back()->with('success', 'Lecturer assignment deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting lecturer assignment: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to delete lecturer assignment.']);
        }
    }
}
