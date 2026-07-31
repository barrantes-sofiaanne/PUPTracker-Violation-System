<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SecurityContactController extends Controller
{
    public function show()
    {
        return view('security-contact');
    }

    public function submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'category' => 'required|in:vulnerability,data_breach,unauthorized_access,suspicious_activity,other',
            'description' => 'required|string|min:20|max:5000',
            'affected_areas' => 'nullable|string|max:500',
            'confidential' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('security.report')
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        try {
            // Send notification email to security team
            Mail::raw(
                $this->formatSecurityReport($data),
                function ($message) {
                    $message
                        ->to(config('mail.security_contact', 'security@pup.edu.ph'))
                        ->subject('[SECURITY INCIDENT REPORT] ' . now()->format('Y-m-d H:i:s'));
                }
            );

            // Send confirmation email to reporter
            Mail::raw(
                $this->formatConfirmationEmail(),
                function ($message) use ($data) {
                    $message
                        ->to($data['email'])
                        ->subject('Security Incident Report Received - PUPTracker');
                }
            );

            return redirect()
                ->route('security.report')
                ->with('success', 'Your security report has been submitted successfully. Our security team will review it within 24 hours.');
        } catch (\Exception $e) {
            return redirect()
                ->route('security.report')
                ->withErrors(['email' => 'Failed to submit report. Please try again later.'])
                ->withInput();
        }
    }

    private function formatSecurityReport(array $data): string
    {
        $confidential = $this->boolToYesNo(isset($data['confidential']) ? $data['confidential'] : false);
        $affectedAreas = isset($data['affected_areas']) ? $data['affected_areas'] : 'Not specified';
        
        return <<<EOT
SECURITY INCIDENT REPORT - PUPTracker Violation System

Reporter Information:
- Name: {$data['name']}
- Email: {$data['email']}
- Confidential: {$confidential}

Incident Details:
- Category: {$this->formatCategory($data['category'])}
- Affected Areas: {$affectedAreas}
- Timestamp: {now()->format('Y-m-d H:i:s')}

Description:
{$data['description']}

---
Please prioritize reviewing this report within 24 hours.
EOT;
    }

    private function formatConfirmationEmail(): string
    {
        return <<<EOT
Thank you for reporting a security incident to PUPTracker!

We have received your security report and take it seriously. Our security team will review your report within 24 hours and take appropriate action if needed.

If this is a critical security issue, please also contact the PUP IT Security team at:
- Email: security@pup.edu.ph
- Phone: (02) 9999-8888 ext. Security

Security Incident Response Timeline:
1. Report received and logged
2. Initial assessment (24 hours)
3. Investigation and remediation plan
4. Implementation and verification
5. Follow-up communication

Thank you for helping us maintain a secure system!

PUPTracker Security Team
EOT;
    }

    private function formatCategory(string $category): string
    {
        return match($category) {
            'vulnerability' => 'Security Vulnerability',
            'data_breach' => 'Data Breach',
            'unauthorized_access' => 'Unauthorized Access',
            'suspicious_activity' => 'Suspicious Activity',
            'other' => 'Other',
            default => $category,
        };
    }

    private function boolToYesNo(?bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }
}
