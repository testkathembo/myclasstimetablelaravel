<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExamTimetable;

class TestExamObserver extends Command
{
    protected $signature = 'exams:test-observer {exam_id=3 : The ID of the exam to test}';
    protected $description = 'Test the ExamTimetableObserver by updating an exam';

    public function handle()
    {
        $examId = $this->argument('exam_id');
        $this->info("Testing observer with exam ID: {$examId}");
        
        // Find the exam
        $exam = ExamTimetable::find($examId);
        
        if (!$exam) {
            $this->error("Exam with ID {$examId} not found.");
            return 3;
        }
        
        // Get current venue
        $currentVenue = $exam->venue;
        $this->info("Current venue: {$currentVenue}");
        
        // Clean the venue first to avoid accumulating (Updated)
        $cleanVenue = preg_replace('/\s*$$Updated$$\s*/', ' ', $currentVenue);
        $cleanVenue = trim($cleanVenue);
        
        // Update with a clean venue + single (Updated)
        $newVenue = $cleanVenue . " (Updated)";
        
        // Update the exam
        $exam->venue = $newVenue;
        $this->info("Updated venue to: {$newVenue}");
        $exam->save();
        
        $this->info("Observer should have been triggered. Check logs and notification_logs table.");
        
        return 0;
    }
}