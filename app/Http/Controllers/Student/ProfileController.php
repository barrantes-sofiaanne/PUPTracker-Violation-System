<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use User;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $user->load([
            'studentInfo.program',
            'studentInfo.year',
            'studentInfo.section',
            'gender',
            'status',
        ]);

        return view(
            'student.profile',
            compact('user')
        );
    }
}