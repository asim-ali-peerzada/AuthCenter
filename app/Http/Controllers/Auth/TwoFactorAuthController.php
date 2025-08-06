<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemSetting;
use App\Services\TwoFactorAuthService;
use Illuminate\Support\Facades\Auth;

class TwoFactorAuthController extends Controller
{
    public function __construct(protected TwoFactorAuthService $twoFactorService) {}
    /**
     * Update the enforce_2fa_login system setting
     */
    public function updateEnforce2FALogin(Request $request)
    {
        $request->validate([
            'enforce_2fa_login' => 'required|boolean'
        ]);

        try {
            $value = $request->boolean('enforce_2fa_login') ? 'true' : 'false';

            SystemSetting::set('enforce_2fa_login', $value);

            return response()->json([
                'success' => true,
                'message' => 'Enforce 2FA login setting updated successfully',
                'enforce_2fa_login' => $request->boolean('enforce_2fa_login')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update enforce 2FA login setting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the current enforce_2fa_login system setting
     */
    public function getEnforce2FALogin()
    {
        try {
            $enforce2FA = SystemSetting::get('enforce_2fa_login', 'false') === 'true';

            return response()->json([
                'success' => true,
                'enforce_2fa_login' => $enforce2FA
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve enforce 2FA login setting',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
