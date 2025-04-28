<?php
namespace App\Http\Controllers;

use Inertia\Inertia;

class LecturerController extends Controller
{
    public function dashboard()
    {
        return Inertia::render('Lecturer/Dashboard');
    }
}
