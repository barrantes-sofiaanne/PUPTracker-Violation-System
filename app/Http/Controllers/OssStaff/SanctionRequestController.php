<?php

namespace App\Http\Controllers\OssStaff;

use App\Http\Controllers\Controller;
use App\Models\SanctionRequest;
use App\Services\SanctionRequestDecisionService;
use Illuminate\Http\RedirectResponse;

/**
 * OSS Staff sanction requests.
 *
 * The review list and the decision rules are the same as the Security module's
 * (both read `sanction_requests_tbl` and use the SAME shared decision service),
 * but the decision is attributed to the OSS Staff ADMIN account:
 *
 *   audit actor_type = 'admin'
 *   audit actor_id   = the OSS Staff admin id
 *
 * and the student is told the Office of Student Services handled the request.
 */
class SanctionRequestController extends Controller
{
    public function __construct(private readonly SanctionRequestDecisionService $decisions)
    {
    }

    public function index()
    {
        $requests = SanctionRequest::with(['student', 'violationType'])
            ->where('is_active', true)
            ->latest('request_id')
            ->paginate(15);

        return view('oss-staff.sanction-requests.index', compact('requests'));
    }

    public function approve(SanctionRequest $sanctionRequest): RedirectResponse
    {
        $student = $sanctionRequest->student;

        if (! $student || ! $student->isActiveAccount()) {
            return back()->withErrors([
                'sanction_request' => 'This sanction cannot be approved because the student account is inactive.',
            ]);
        }

        $this->decisions->finalize(
            $sanctionRequest,
            SanctionRequestDecisionService::DECISION_APPROVED,
            'admin',
            $this->adminId(),
            'the Office of Student Services',
            'OSS Staff',
        );

        return back()->with('success', 'Sanction request approved and forwarded for follow-up.');
    }

    public function decline(SanctionRequest $sanctionRequest): RedirectResponse
    {
        $this->decisions->finalize(
            $sanctionRequest,
            SanctionRequestDecisionService::DECISION_DECLINED,
            'admin',
            $this->adminId(),
            'the Office of Student Services',
            'OSS Staff',
        );

        return back()->with('success', 'Sanction request declined and the student has been notified.');
    }

    private function adminId(): ?int
    {
        $id = auth('admin')->id();

        return $id !== null ? (int) $id : null;
    }
}
