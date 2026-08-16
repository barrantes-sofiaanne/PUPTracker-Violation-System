<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;

class SettingsController extends Controller
{
    public function index()
    {
        return view('student.settings');
    }
}