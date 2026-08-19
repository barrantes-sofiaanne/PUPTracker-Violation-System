<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class SecurityProfileController extends Controller
{
    public function index()
    {
        /** @var \App\Models\Security $security */
        $security = Auth::guard('security')->user();

        $security->load([
            'securityInfo',
            'securityProfile',
        ]);

        return view('security.profile', compact('security'));
    }
}
