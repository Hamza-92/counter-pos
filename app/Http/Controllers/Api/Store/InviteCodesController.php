<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\InviteCode;
use App\Models\StoreSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InviteCodesController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreSetting::class);

        $query = InviteCode::query()->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $query->where('code', 'like', '%'.$request->search.'%');
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->usable();
            } elseif ($request->status === 'inactive') {
                $query->where(function ($q) {
                    $q->where('is_active', false)
                      ->orWhere(function ($q2) {
                          $q2->whereNotNull('expires_at')->where('expires_at', '<=', now());
                      })
                      ->orWhere(function ($q2) {
                          $q2->whereNotNull('max_uses')->whereColumn('times_used', '>=', 'max_uses');
                      });
                });
            }
        }

        $codes = $query->paginate($request->input('per_page', 15));

        return response()->json($codes);
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreSetting::class);

        $data = $request->validate([
            'code' => 'nullable|string|max:64|unique:invite_codes,code',
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:now',
            'is_active' => 'nullable|boolean',
        ]);

        if (empty($data['code'])) {
            $data['code'] = strtoupper(Str::random(8));
        }

        $data['created_by'] = $request->user('api')->id;
        $data['is_active'] = $data['is_active'] ?? true;

        $code = InviteCode::create($data);

        return response()->json($code, 201);
    }

    public function show(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreSetting::class);

        $code = InviteCode::withCount('registrations')->findOrFail($id);

        return response()->json($code);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreSetting::class);

        $code = InviteCode::findOrFail($id);

        $data = $request->validate([
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        $code->fill($data)->save();

        return response()->json($code->fresh());
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreSetting::class);

        $code = InviteCode::findOrFail($id);
        $code->delete();

        return response()->json(['success' => true]);
    }

    public function generateBatch(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreSetting::class);

        $data = $request->validate([
            'count' => 'required|integer|min:1|max:50',
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $codes = [];
        $userId = $request->user('api')->id;

        for ($i = 0; $i < $data['count']; $i++) {
            $codes[] = InviteCode::create([
                'code' => strtoupper(Str::random(8)),
                'created_by' => $userId,
                'max_uses' => $data['max_uses'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'is_active' => true,
            ]);
        }

        return response()->json(['generated' => count($codes), 'codes' => $codes], 201);
    }
}
