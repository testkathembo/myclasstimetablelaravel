<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Notifications\Exam_reminder;
use App\Models\User;

class MailController extends Controller
{
    public function index()
    {
        // Retrieve a user instance (replace 1 with the actual user ID or use Auth::user() for the logged-in user)
        $user = User::find(1563); // Example: Fetch user with ID 1

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $data = [
            'Humble reminder' => 'Humble reminder',
            'Hello' => 'Hello ' . $user->name,
            'Wish' => 'We wish to remind you that you will be having an exam tomorrow. Please keep checking your timetable for further details.',
            
        ];

        $user->notify(new Exam_reminder($data));

        return response()->json(['message' => 'Notification sent successfully']);
    }
}
