<?php

namespace App\Http\Controllers\ControlPlane;

use App\Http\Controllers\Controller;
use App\Models\ControlPlane\SuperAdmin;
use App\Services\ControlPlane\AuditService;
use App\Services\ControlPlane\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if (auth('control')->check()) {
            return redirect()->route('control.dashboard');
        }

        return view('control.auth.login');
    }

    public function login(Request $request, TotpService $totp, AuditService $audit): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'max:32'],
        ]);

        $admin = SuperAdmin::query()->where('email', strtolower($credentials['email']))->first();
        if (! $admin || ! $admin->is_active || ! Hash::check($credentials['password'], $admin->password)) {
            return back()->withErrors(['email' => 'The supplied credentials are invalid.'])->onlyInput('email');
        }

        $validCode = $admin->totp_secret && $totp->verify($admin->totp_secret, $credentials['code']);
        if (! $validCode) {
            $validCode = $this->consumeRecoveryCode($admin, $credentials['code']);
        }

        if (! $validCode) {
            return back()->withErrors(['code' => 'The verification code is invalid.'])->onlyInput('email');
        }

        auth('control')->login($admin, false);
        $request->session()->regenerate();
        $request->session()->put('control_2fa_verified_at', time());

        $admin->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();
        $audit->record('control.login', $admin);

        return redirect()->intended(route('control.dashboard'));
    }

    public function logout(Request $request, AuditService $audit): RedirectResponse
    {
        $admin = auth('control')->user();
        if ($admin) {
            $audit->record('control.logout', $admin);
        }

        auth('control')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('control.login');
    }

    private function consumeRecoveryCode(SuperAdmin $admin, string $candidate): bool
    {
        $candidate = strtoupper(trim($candidate));
        $hashes = $admin->recovery_code_hashes ?? [];

        foreach ($hashes as $index => $hash) {
            if (Hash::check($candidate, $hash)) {
                unset($hashes[$index]);
                $admin->recovery_code_hashes = array_values($hashes);
                $admin->save();

                return true;
            }
        }

        return false;
    }
}
