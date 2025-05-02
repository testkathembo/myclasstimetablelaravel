<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ExamTimetable;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\Exam_reminder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    /**
     * Display the notification dashboard.
     */
    public function index()
    {
        // Get upcoming exams for the next 7 days
        $upcomingExams = ExamTimetable::where('date', '>=', Carbon::today()->toDateString())
            ->where('date', '<=', Carbon::today()->addDays(7)->toDateString())
            ->with(['unit', 'semester'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
            
        // Get notification logs (you'll need to create this table)
        // Check if the table exists first to avoid errors
        $recentNotifications = [];
        if (Schema::hasTable('notification_logs')) {
            $recentNotifications = DB::table('notification_logs')
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();
        }
            
        return Inertia::render('Notifications/Dashboard', [
            'upcomingExams' => $upcomingExams,
            'recentNotifications' => $recentNotifications,
            'can' => [
                'send_notifications' => auth()->user()->can('send-notifications'),
                'view_logs' => auth()->user()->can('view-notification-logs'),
            ],
        ]);
    }
    
    /**
     * Manually trigger exam reminders.
     */
    public function sendReminders(Request $request)
    {
        // Check permissions
        if (!auth()->user()->can('send-notifications')) {
            return redirect()->back()->with('error', 'You do not have permission to send notifications.');
        }
        
        try {
            // Run the command
            Artisan::call('exams:send-reminders');
            $output = Artisan::output();
            
            // Log the action
            Log::info('Manual notification trigger', [
                'user_id' => auth()->id(),
                'output' => $output
            ]);
            
            return redirect()->back()->with('success', 'Exam reminders sent successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to send exam reminders', [
                'error' => $e->getMessage()
            ]);
            
            return redirect()->back()->with('error', 'Failed to send exam reminders: ' . $e->getMessage());
        }
    }
    
    /**
     * Preview notifications for a specific exam.
     */
    public function previewNotifications(Request $request, $examId)
    {
        $exam = ExamTimetable::with(['unit', 'semester'])->findOrFail($examId);
        
        // Find all student codes enrolled in this unit for this semester
        $studentCodes = Enrollment::where('unit_id', $exam->unit_id)
            ->where('semester_id', $exam->semester_id)
            ->pluck('student_code')
            ->toArray();
            
        // Get all users with these codes
        $students = User::whereIn('code', $studentCodes)
            ->select('id', 'code', 'first_name', 'last_name', 'email')
            ->get();
            
        return Inertia::render('Notifications/Preview', [
            'exam' => $exam,
            'students' => $students,
            'studentCount' => count($students),
        ]);
    }
}
