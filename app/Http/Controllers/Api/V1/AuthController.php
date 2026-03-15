<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'timezone' => ['sometimes', 'timezone:all'],
            'distance_unit' => ['sometimes', 'in:mi,km'],
            'temperature_unit' => ['sometimes', 'in:F,C'],
            'currency' => ['sometimes', 'string', 'max:3'],
        ]);

        return DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'timezone' => $validated['timezone'] ?? config('teslog.defaults.timezone'),
                'distance_unit' => $validated['distance_unit'] ?? config('teslog.defaults.distance_unit'),
                'temperature_unit' => $validated['temperature_unit'] ?? config('teslog.defaults.temperature_unit'),
                'currency' => $validated['currency'] ?? config('teslog.defaults.currency'),
            ]);

            $token = $user->createToken('api', ['read', 'write', 'commands', 'admin']);

            return response()->json([
                'user' => $user,
                'token' => $token->plainTextToken,
            ], 201);
        });
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();
        $token = $user->createToken(
            $request->input('device_name', 'api'),
            ['read', 'write', 'commands']
        );

        return response()->json([
            'user' => $user,
            'token' => $token->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function tokens(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->tokens()->select('id', 'name', 'abilities', 'last_used_at', 'created_at')->get()
        );
    }

    public function deleteToken(Request $request, int $tokenId): JsonResponse
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return response()->json(['message' => 'Token revoked']);
    }
}
