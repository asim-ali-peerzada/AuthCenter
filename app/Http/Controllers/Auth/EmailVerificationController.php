<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailVerificationController extends Controller
{
    /**
     * Verify signed email link for JobFinder users and redirect accordingly.
     */
    public function verifyJobfinder(Request $request)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired verification link.');
        }

        $email = $request->query('email');

        if (! $email) {
            abort(400, 'Email parameter missing.');
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            abort(404, 'User not found.');
        }

        // Mark email as verified
        $user->email_verified_at = now();
        $user->save();

        Log::info('Jobfinder email verified', ['user_id' => $user->id, 'email' => $user->email]);

        // Redirect to appropriate login route based on environment
        if (app()->environment('local')) {
            return redirect()->to('http://localhost:3000/ccms/auth/sso/login?from=http%3A%2F%2Flocalhost%3A8003%2F');
        }

        return redirect()->to('http://localhost:3000/ccms/auth/sso?from=https%3A%2F%2Fsolucomp.com%2Fjob-portal%2F');
    }
}