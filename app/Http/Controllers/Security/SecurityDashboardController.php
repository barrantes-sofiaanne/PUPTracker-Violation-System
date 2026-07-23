<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;


class SecurityDashboardController extends Controller
{
    public function index()
    {
        return view('security.dashboard');
    }
}
