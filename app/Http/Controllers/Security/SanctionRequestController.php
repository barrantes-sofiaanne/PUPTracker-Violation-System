<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\SanctionRequest;
use App\Services\SanctionRequestDecisionService;
use Illuminate\Http\RedirectResponse;

/**
 * Security sanction requests.
 *
 * The decision rules themselves live in the SHARED
 * {@see SanctionRequestDecisionService}, which is also used by the OSS Staff
 * module. This controller supplies the Security actor explicitly, so the audit
 * trail and the student-facing wording stay unchanged:
 *
 *   audit actor_type = 'security'
 *   audit actor_id   = the security account id
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

        return view('security.sanction-requests.index', compact('requests'));
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
            'security',
            $this->securityId(),
            'the security office',
            'Security',
        );

        return back()->with('success', 'Sanction request approved and forwarded for follow-up.');
    }

    public function decline(SanctionRequest $sanctionRequest): RedirectResponse
    {
        $this->decisions->finalize(
            $sanctionRequest,
            SanctionRequestDecisionService::DECISION_DECLINED,
            'security',
            $this->securityId(),
            'the security office',
            'Security',
        );

        return back()->with('success', 'Sanction request declined and the student has been notified.');
    }

    private function securityId(): ?int
    {
        $security = \Illuminate\Support\Facades\Auth::guard('security')->user();

        return $security ? (int) $security->id : null;
    }
}
