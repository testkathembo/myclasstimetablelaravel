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
        if (Schema::hasTable('notification_logs')) { // Singular table name
            $recentNotifications = DB::table('notification_logs') // Singular table name
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
            // Example data for the notification
            $data = [
                'subject' => 'Exam Reminder Notification', // Updated key
                'Hello' => 'Hello ' . auth()->user()->first_name,
                'Wish' => 'We wish to remind you that you have an exam tomorrow. Please check your exam timetable for details.',
            ];

            // Notify the user
            auth()->user()->notify(new Exam_reminder($data));

            // Log the action
            Artisan::call('exams:send-reminders');
            $output = Artisan::output();

            // Insert success log into notification_logs table
            DB::table('notification_logs')->insert([
                'notification_type' => 'App\Notifications\Exam_reminder',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id' => auth()->id(),
                'channel' => 'mail',
                'success' => true,
                'error_message' => null,
                'data' => json_encode(['output' => $output]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()->back()->with('success', 'Exam reminders sent successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to send exam reminders', [
                'error' => $e->getMessage(),
            ]);

            // Insert error log into notification_logs table
            DB::table('notification_logs')->insert([
                'notification_type' => 'App\Notifications\Exam_reminder',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id' => auth()->id(),
                'channel' => 'mail',
                'success' => false,
                'error_message' => $e->getMessage(),
                'data' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
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
