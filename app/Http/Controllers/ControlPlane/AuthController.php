<?php

namespace App\Http\Controllers\ControlPlane;

use App\Http\Controllers\Controller;
use App\Models\ControlPlane\SuperAdmin;
use App\Services\ControlPlane\AuditService;
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

    public function login(Request $request, AuditService $audit): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string'],
        ]);

        $login = strtolower(trim($credentials['username']));
        $admin = SuperAdmin::query()
            ->whereRaw('LOWER(username) = ?', [$login])
            ->orWhereRaw('LOWER(email) = ?', [$login])
            ->first();
        if (! $admin || ! $admin->is_active || ! Hash::check($credentials['password'], $admin->password)) {
            return back()->withErrors(['username' => 'The supplied credentials are invalid.'])->onlyInput('username');
        }

        auth('control')->login($admin, false);
        $request->session()->regenerate();
        $request->session()->put('control_authenticated_at', time());

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
}
