<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PortalProfileController extends Controller
{
    /**
     * GET /api/portal/profile - Profile info (client + portal email).
     */
    public function show(Request $request)
    {
        $portalClient = Auth::guard('portal')->user();
        $this->assertPortalActive($portalClient);

        $portalClient->load('client');

        return response()->json([
            'portal_email' => $portalClient->email,
            'client' => [
                'id' => $portalClient->client->id,
                'name' => $portalClient->client->name,
                'email' => $portalClient->client->email,
                'phone' => $portalClient->client->phone,
                'adresse' => $portalClient->client->adresse,
                'country' => $portalClient->client->country ?? '',
                'city' => $portalClient->client->city ?? '',
                'state' => $portalClient->client->state ?? '',
                'zip' => $portalClient->client->zip ?? '',
            ],
        ]);
    }

    /**
     * PUT /api/portal/profile/password - Update portal password.
     */
    public function updatePassword(Request $request)
    {
        $portalClient = Auth::guard('portal')->user();
        $this->assertPortalActive($portalClient);

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (!Hash::check($data['current_password'], $portalClient->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $portalClient->update(['password' => Hash::make($data['password'])]);

        return response()->json(['success' => true]);
    }

    private function assertPortalActive($portalClient): void
    {
        if ((int) $portalClient->status !== 1) {
            abort(403, 'Portal access is disabled');
        }
    }
}
