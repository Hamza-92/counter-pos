<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\EcommerceClient;
use App\Models\InviteCode;
use App\Models\StoreSetting;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreAuthController extends Controller
{
    /**
     * Only allow same-origin path redirects.
     */
    protected function safeRedirect(Request $request): string
    {
        $redir = (string) $request->input('redirect', '');
        if ($redir && Str::startsWith($redir, ['/']) && ! Str::startsWith($redir, ['//'])) {
            return $redir;
        }

        if ($intended = (string) session('url.intended')) {
            if (Str::startsWith($intended, ['/']) && ! Str::startsWith($intended, ['//'])) {
                return $intended;
            }
        }

        return route('checkout');
    }

    /** GET /store/login */
    public function showLogin(Request $request)
    {
        $s = StoreSetting::first();
        $redirect = $this->safeRedirect($request);

        return view('store.auth.login', compact('s', 'redirect'));
    }

    /** POST /store/login */
    public function login(Request $request)
    {
        $cred = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
            'redirect' => ['nullable', 'string'],
        ]);

        $ecomClient = EcommerceClient::where('email', $cred['email'])->first();

        if ($ecomClient && Hash::check($cred['password'], $ecomClient->password)) {
            if ((int) $ecomClient->status === 0) {
                return back()
                    ->withErrors(['email' => __('messages.AccountPendingApproval')])
                    ->onlyInput('email');
            }
        }

        $attempt = [
            'email' => $cred['email'],
            'password' => $cred['password'],
            'status' => 1,
        ];

        if (Auth::guard('store')->attempt($attempt, (bool) ($cred['remember'] ?? false))) {
            $request->session()->regenerate();

            return redirect()->to($this->safeRedirect($request));
        }

        return back()
            ->withErrors(['email' => __('Invalid credentials')])
            ->onlyInput('email');
    }

    /** GET /store/register */
    public function showRegister(Request $request)
    {
        $s = StoreSetting::first();
        $redirect = $this->safeRedirect($request);

        $registrationEnabled = $s->registration_enabled ?? true;
        $requireInviteCode = $s->require_invite_code ?? false;

        return view('store.auth.register', compact(
            's', 'redirect', 'registrationEnabled', 'requireInviteCode'
        ));
    }

    /** POST /store/register */
    public function register(Request $request)
    {
        $s = StoreSetting::first();

        if (! ($s->registration_enabled ?? true)) {
            return back()->withErrors(['registration' => __('messages.RegistrationDisabled')]);
        }

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:190',
                Rule::unique('clients', 'email')->whereNull('deleted_at'),
                Rule::unique('ecommerce_clients', 'email')->whereNull('deleted_at'),
            ],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:190'],
            'password' => ['required', 'confirmed', 'min:6'],
        ];

        if ($s->require_invite_code ?? false) {
            $rules['invite_code'] = ['required', 'string', 'max:64'];
        }

        $data = $request->validate($rules);

        $inviteCode = null;
        if ($s->require_invite_code ?? false) {
            $inviteCode = InviteCode::where('code', $data['invite_code'])->first();

            if (! $inviteCode || ! $inviteCode->isValid()) {
                return back()
                    ->withErrors(['invite_code' => __('messages.InvalidInviteCode')])
                    ->withInput($request->except('password', 'password_confirmation'));
            }
        }

        $requireApproval = $s->require_admin_approval ?? false;
        $initialStatus = $requireApproval ? 0 : 1;

        $user = DB::transaction(function () use ($data, $initialStatus, $inviteCode) {
            $client = Client::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'code' => $this->getNumberOrder(),
                    'phone' => $data['phone'],
                    'adresse' => $data['address'],
                ]
            );

            $ecomClient = EcommerceClient::create([
                'client_id' => $client->id,
                'username' => Str::slug(Str::before($data['email'], '@')),
                'email' => $data['email'],
                'status' => $initialStatus,
                'password' => Hash::make($data['password']),
                'invite_code_id' => $inviteCode?->id,
            ]);

            if ($inviteCode) {
                $inviteCode->redeem();
            }

            return $ecomClient;
        });

        if ($requireApproval) {
            return redirect()->route('store.login.show', ['redirect' => $this->safeRedirect($request)])
                ->with('pending_approval', true);
        }

        Auth::guard('store')->login($user);
        $request->session()->regenerate();

        return redirect()->to($this->safeRedirect($request));
    }

    /** POST /store/logout */
    public function logout(Request $request)
    {
        Auth::guard('store')->logout();

        $request->session()->forget('guard_store');
        $request->session()->regenerateToken();

        return redirect()->route('store.login.show');
    }

    public function getNumberOrder()
    {
        $last = DB::table('clients')->latest('id')->first();

        if ($last) {
            $code = $last->code + 1;
        } else {
            $code = 1;
        }

        return $code;
    }
}
