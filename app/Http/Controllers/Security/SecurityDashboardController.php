<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Violation;
use App\Models\StudentStatus;
use Illuminate\Support\Facades\DB;

class SecurityDashboardController extends Controller
{
    public function index()
    {
        // Get active students count
        $activeStudents = User::count();
        
        // Get total violations
        $totalViolations = Violation::count();
        
        // Get recent violations (last 7 days)
        $recentViolations = Violation::with(['student', 'violationType'])
            ->where('violation_date', '>=', now()->subDays(7))
            ->latest('violation_date')
            ->take(10)
            ->get();
        
        // Get students with active violations (repeat offenders)
        $activeOffenders = Violation::select('student_number', DB::raw('COUNT(*) as violation_count'))
            ->groupBy('student_number')
            ->orderByDesc('violation_count')
            ->take(10)
            ->get();
        
        // Get violations by category for the week
        $violationsByCategory = Violation::select('violation_category_id', DB::raw('COUNT(*) as count'))
            ->with('category')
            ->where('violation_date', '>=', now()->subDays(7))
            ->groupBy('violation_category_id')
            ->orderByDesc('count')
            ->get();
        
        // Get major violations in the system
        $majorViolations = Violation::where('offense_level', 'Major')
            ->count();
        
        // Get minor violations in the system
        $minorViolations = Violation::where('offense_level', 'Minor')
            ->count();

        return view('security.dashboard', compact(
            'activeStudents',
            'totalViolations',
            'recentViolations',
            'activeOffenders',
            'violationsByCategory',
            'majorViolations',
            'minorViolations'
        ));
    }
}
