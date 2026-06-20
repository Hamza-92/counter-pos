<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends BaseController
{
    private const AUTH_COOKIE = 'CounterPOS_token';
    private const LEGACY_AUTH_COOKIE = 'Stocky_token';

    // --------------- Function Login ----------------\\

    public function getAccessToken(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required',
        ]);

        $credentials = request(['email', 'password']);

        if (Auth::attempt($credentials)) {
            $userStatus = Auth::User()->statut;
            if ($userStatus === 0) {
                return response()->json([
                    'message' => 'This user not active',
                    'status' => 'NotActive',
                ]);
            }

        } else {
            return response()->json([
                'message' => 'Incorrect Login',
                'status' => false,
            ]);
        }

        $user = auth()->user();
        $tokenResult = $user->createToken('Access Token');
        $token = $tokenResult->token;
        $this->setCookie(self::AUTH_COOKIE, $tokenResult->accessToken);
        $this->setCookie(self::LEGACY_AUTH_COOKIE, $tokenResult->accessToken);

        return response()->json([
            'CounterPOS_token' => $tokenResult->accessToken,
            'Stocky_token' => $tokenResult->accessToken,
            'username' => Auth::User()->username,
            'status' => true,
        ]);
    }

    // --------------- Function Logout ----------------\\

    public function logout()
    {
        if (Auth::check()) {
            $user = Auth::user()->token();
            $user->revoke();
            $this->destroyCookie(self::AUTH_COOKIE);
            $this->destroyCookie(self::LEGACY_AUTH_COOKIE);

            return response()->json('success');
        }

    }
}
