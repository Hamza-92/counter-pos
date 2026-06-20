<?php

namespace App\Listeners\Security;

use App\Models\OauthAccessToken;
use App\Models\UserLoginSession;
use Illuminate\Support\Str;
use Laravel\Passport\Events\AccessTokenCreated;

class RecordLoginSession
{
    public function handle(AccessTokenCreated $event): void
    {
        // Best-effort only: do not interrupt login if anything goes wrong
        try {
            $tokenId = (string) $event->tokenId;
            if ($tokenId === '') {
                return;
            }

            $userId = (int) $event->userId;

            $ip = request()->ip();
            $ua = request()->userAgent();
            $ua = $ua !== null ? Str::limit((string) $ua, 2000, '') : null;

            $token = OauthAccessToken::query()->where('id', $tokenId)->first();
            $loggedInAt = $token && $token->created_at ? $token->created_at : now();

            UserLoginSession::query()->updateOrCreate(
                ['access_token_id' => $tokenId],
                [
                    'user_id' => $userId,
                    'ip_address' => $ip,
                    'user_agent' => $ua,
                    'logged_in_at' => $loggedInAt,
                    'last_activity_at' => now(),
                    'revoked_at' => null,
                ]
            );
        } catch (\Throwable $e) {
            // swallow
        }
    }
}



























































