<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\PortalClient;
use App\utils\helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PortalAdminController extends Controller
{
    /**
     * GET /api/clients/{id}/portal-status - Get portal status for a client (admin only).
     */
    public function getStatus(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Client::class);

        $client = Client::findOrFail($id);
        $portalClient = PortalClient::where('client_id', $client->id)->first();

        return response()->json([
            'portal_enabled' => $portalClient && (int) $portalClient->status === 1,
            'portal_email' => $portalClient ? $portalClient->email : null,
            'has_password' => $portalClient && $portalClient->password !== null,
            'invitation_pending' => $portalClient && $portalClient->invitation_token !== null,
        ]);
    }

    /**
     * POST /api/clients/{id}/portal-enable - Enable portal (admin only).
     */
    public function enable(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Client::class);

        $client = Client::findOrFail($id);
        $existingPortal = PortalClient::where('client_id', $client->id)->first();

        $emailRules = [
            'required',
            'email',
            'max:190',
            $existingPortal && $existingPortal->id
                ? Rule::unique('portal_clients', 'email')->ignore($existingPortal->id)
                : Rule::unique('portal_clients', 'email'),
        ];

        $data = $request->validate([
            'email' => $emailRules,
            'password' => ['nullable', 'string', 'min:8'],
        ], [
            'email.unique' => 'This email is already used by another portal account.',
        ]);

        $portalClient = PortalClient::firstOrNew(['client_id' => $client->id]);
        $portalClient->email = $data['email'];
        $portalClient->invitation_token = null;
        $portalClient->invitation_sent_at = null;
        $portalClient->status = 1;
        if (!empty($data['password'])) {
            $portalClient->password = Hash::make($data['password']);
        } elseif (!$portalClient->password) {
            $portalClient->password = Hash::make(Str::random(32));
        }
        $portalClient->save();

        return response()->json([
            'success' => true,
            'message' => !empty($data['password'])
                ? 'Portal enabled. Client can log in with the password you set.'
                : 'Portal enabled. A random password was set; share credentials with the client.',
        ]);
    }

    /**
     * POST /api/clients/{id}/portal-disable - Disable portal access (admin only).
     */
    public function disable(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Client::class);

        $client = Client::findOrFail($id);
        $portalClient = PortalClient::where('client_id', $client->id)->first();

        if ($portalClient) {
            $portalClient->update(['status' => 0]);
        }

        return response()->json(['success' => true, 'message' => 'Portal access disabled']);
    }
}
