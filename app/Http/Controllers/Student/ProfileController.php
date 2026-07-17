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
            'course',
            'year',
            'section',
            'gender',
        ]);

        return view(
            'student.profile',
            compact('user')
        );
    }
}