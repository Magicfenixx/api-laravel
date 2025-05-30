<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\RefreshToken;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // dd('register called');
        // Log::info('Register hit:', $request->all());

        $request->validate([
            'name' => 'required|string|max:10',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6|confirmed'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password)
        ]);

        return response()->json(['message' => 'User registered successfully'], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);
        
        $credentials = $request->only('email', 'password');

        if (!$access_token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        return $this->respondWithToken($access_token, auth()->user());
    }

    public function me()
    {
        return response()->json(auth('api')->user());
    }

    protected function respondWithToken($access_token, $user)
    {
        $refresh_token = Str::random(64);
        $hashedrefresh = hash('sha256', $refresh_token);

        RefreshToken::where('user_id', $user->id)->delete();

        RefreshToken::create([
        'user_id' => $user->id,
        'token' => $hashedrefresh,
        'expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ]);
    }

    public function refresh(Request $request)
    {
        $incomingToken = $request->input('refresh_token');

        if (! $incomingToken) {
            return response()->json(['error' => 'Missing refresh token'], 422);
        }

        $hashed = hash('sha256', $incomingToken);

        $stored = RefreshToken::where('token', $hashed)
            ->where('expires_at', '>', now())
            ->first();

        if (! $stored) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        $user = $stored->user;

        $stored->delete();

        $newAccessToken = JWTAuth::fromUser($user);

        return $this->respondWithToken($newAccessToken, $user);
    }
}