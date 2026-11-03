<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])
            ->where('is_active', true)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang diberikan tidak sesuai.'],
            ]);
        }

        $token = $user->createToken('pos-api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->formatUser($user),
        ]);
    }

    public function pinLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'outlet_id' => ['required', 'integer', 'exists:outlets,id'],
            'pin' => ['required', 'string', 'min:4', 'max:8'],
        ]);

        $users = User::where('outlet_id', $validated['outlet_id'])
            ->where('is_active', true)
            ->whereNotNull('pin')
            ->get();

        $matchedUser = null;
        foreach ($users as $candidate) {
            if (Hash::check($validated['pin'], $candidate->pin)) {
                $matchedUser = $candidate;
                break;
            }
        }

        if (! $matchedUser) {
            throw ValidationException::withMessages([
                'pin' => ['PIN tidak valid atau pengguna tidak aktif.'],
            ]);
        }

        $token = $matchedUser->createToken('pos-pin-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->formatUser($matchedUser),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->formatUser($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Berhasil keluar.',
        ]);
    }

    protected function formatUser(User $user): array
    {
        $user->loadMissing('outlet');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'outlet_id' => $user->outlet_id,
            'outlet' => $user->outlet,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ];
    }
}
