<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ClassTimetable;

class TestClassTimetableObserver extends Command
{
    protected $signature = 'class:test-observer {class_id=1 : The ID of the class to test}';
    protected $description = 'Test the ClassTimetableObserver by updating a class';

    public function handle()
    {
        $classId = $this->argument('class_id');
        $this->info("Testing observer with class ID: {$classId}");
        
        // Find the class
        $class = ClassTimetable::find($classId);
        
        if (!$class) {
            $this->error("Class with ID {$classId} not found.");
            return 1;
        }
        
        // Get current venue
        $currentVenue = $class->venue;
        $this->info("Current venue: {$currentVenue}");
        
        // Clean the venue first to avoid accumulating (Updated)
        $cleanVenue = preg_replace('/\s*$$Updated$$\s*/', ' ', $currentVenue);
        $cleanVenue = trim($cleanVenue);
        
        // Update with a clean venue + single (Updated)
        $newVenue = $cleanVenue . " (Updated)";
        
        // Update the class
        $class->venue = $newVenue;
        $this->info("Updated venue to: {$newVenue}");
        $class->save();
        
        $this->info("Observer should have been triggered. Check logs and notification_logs table.");
        
        return 0;
    }
}