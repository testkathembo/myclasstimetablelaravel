<?php

namespace App\Observers;

use App\Models\ExamTimetable;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\ExamTimetableUpdate;
use Illuminate\Support\Facades\Log;

class ExamTimetableObserver
{
    /**
     * Write debug information directly to a file.
     */
    private function debugToFile($message, $data = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $content = "[{$timestamp}] {$message}\n";
        
        if (!empty($data)) {
            $content .= json_encode($data, JSON_PRETTY_PRINT) . "\n";
        }
        
        $content .= "------------------------------\n";
        
        file_put_contents(
            storage_path('logs/observer_debug.log'),
            $content,
            FILE_APPEND
        );
    }

    /**
     * Handle the ExamTimetable "updated" event.
     */
    public function updated(ExamTimetable $examTimetable): void
    {
        $this->debugToFile('Observer triggered', [
            'exam_id' => $examTimetable->id,
            'dirty' => $examTimetable->getDirty(),
            'original' => $examTimetable->getOriginal()
        ]);
        
        // Get the changed attributes
        $changes = [];
        $dirty = $examTimetable->getDirty();
        
        // Only track specific fields that are relevant to students
        $relevantFields = [
            'date', 'day', 'start_time', 'end_time', 'venue', 'location'
        ];
        
        foreach ($relevantFields as $field) {
            if (array_key_exists($field, $dirty)) {
                $changes[$field] = [
                    'old' => $examTimetable->getOriginal($field),
                    'new' => $dirty[$field]
                ];
            }
        }
        
        $this->debugToFile('Changes detected', $changes);
        
        // Only send notifications if relevant fields were changed
        if (!empty($changes)) {
            $this->debugToFile('Preparing to notify students');
            $this->notifyStudents($examTimetable, $changes);
        } else {
            $this->debugToFile('No relevant changes, skipping notifications');
        }
        
        $this->debugToFile('Observer completed');
    }
    
    /**
     * Notify students about exam timetable changes.
     */
    private function notifyStudents(ExamTimetable $examTimetable, array $changes): void
    {
        try {
            $this->debugToFile('Loading relationships');
            // Load relationships if not already loaded
            $examTimetable->load(['unit', 'semester']);
            
            $this->debugToFile('Finding enrolled students');
            // Find all student codes enrolled in this unit for this semester
            $studentCodes = Enrollment::where('unit_id', $examTimetable->unit_id)
                ->where('semester_id', $examTimetable->semester_id)
                ->pluck('student_code')
                ->toArray();
                
            $this->debugToFile('Student codes found', $studentCodes);
                
            // Get all users with these codes
            $students = User::whereIn('code', $studentCodes)->get();
            
            $this->debugToFile('Students found', [
                'count' => $students->count(),
                'first_few' => $students->take(3)->pluck('email')->toArray()
            ]);
            
            if ($students->isEmpty()) {
                $this->debugToFile('No students found, skipping notifications');
                Log::info("No students found for exam update notification", [
                    'exam_id' => $examTimetable->id,
                    'unit_id' => $examTimetable->unit_id,
                    'semester_id' => $examTimetable->semester_id
                ]);
                return;
            }
            
            $this->debugToFile('Preparing notification data');
            // Prepare notification data
            $data = [
                'subject' => "Important: Exam Schedule Update for {$examTimetable->unit->code}",
                'greeting' => "Hello",
                'message' => "There has been an update to your exam schedule for {$examTimetable->unit->code} - {$examTimetable->unit->name}. Please review the changes below:",
                'exam_details' => [
                    'unit' => $examTimetable->unit->code . ' - ' . $examTimetable->unit->name,
                    'date' => $examTimetable->date,
                    'day' => $examTimetable->day,
                    'time' => $examTimetable->start_time . ' - ' . $examTimetable->end_time,
                    'venue' => $examTimetable->venue . ' (' . $examTimetable->location . ')'
                ],
                'changes' => $changes,
                'closing' => 'Please make note of these changes and adjust your schedule accordingly. If you have any questions, please contact your instructor.'
            ];
            
            $this->debugToFile('Sending notifications to students');
            
            // Send notification to each student
            foreach ($students as $student) {
                try {
                    $this->debugToFile("Sending to student {$student->id} ({$student->email})");
                    $student->notify(new ExamTimetableUpdate($data));
                    
                    $this->debugToFile("Notification sent successfully to {$student->email}");
                    
                    // Log the notification
                    Log::info("Sent exam update notification to student", [
                        'student_code' => $student->code,
                        'student_email' => $student->email,
                        'exam_id' => $examTimetable->id
                    ]);
                    
                    // Log to notification_logs table
                    $this->logNotificationToDatabase($student, $examTimetable, true);
                } catch (\Exception $e) {
                    $this->debugToFile("Error sending to {$student->email}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    // Log the failed notification
                    $this->logNotificationToDatabase($student, $examTimetable, false, $e->getMessage());
                }
            }
            
            $this->debugToFile('All notifications processed');
            
        } catch (\Exception $e) {
            $this->debugToFile("Error in notifyStudents method", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            Log::error("Failed to send exam update notifications", [
                'exam_id' => $examTimetable->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Log notification to database.
     */
    private function logNotificationToDatabase($student, $examTimetable, $success, $errorMessage = null): void
    {
        try {
            $this->debugToFile("Logging notification to database", [
                'student_id' => $student->id,
                'exam_id' => $examTimetable->id,
                'success' => $success
            ]);
            
            \DB::table('notification_logs')->insert([
                'notification_type' => 'App\\Notifications\\ExamTimetableUpdate',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $student->id,
                'channel' => 'mail',
                'success' => $success,
                'error_message' => $errorMessage,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $this->debugToFile("Successfully logged to database");
        } catch (\Exception $e) {
            $this->debugToFile("Error logging to database", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            Log::error("Failed to log notification to database", [
                'error' => $e->getMessage()
            ]);
        }
    }
}