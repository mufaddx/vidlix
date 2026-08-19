<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Identity\AccountProvisioner;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, AccountProvisioner $onboarding): JsonResponse
    {
        $user = User::query()->create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'mobile' => $request->string('mobile'),
            'password' => $request->string('password'),
            'status' => 'active',
        ]);
        $requested = $request->string('role')->toString();
        if ($requested !== '') {
            $role = Role::query()->where('slug', $requested)->firstOrFail();
            $user->roles()->attach($role);
            $onboarding->provisionRole($user, $role->slug);
        }
        event(new Registered($user));

        return response()->json([
            'success' => true,
            'message' => __('Registered. Verify email before sensitive actions.'),
            'code' => 'REGISTERED',
            'data' => ['id' => $user->id],
            'request_id' => $request->attributes->get('request_id'),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $login = $request->string('login')->toString();
        $user = User::query()->where('email', $login)->orWhere('mobile', $login)->first();
        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages(['login' => __('These credentials do not match our records.')]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => __('Authenticated'),
            'code' => 'OK',
            'data' => ['token' => $token, 'token_type' => 'Bearer'],
            'request_id' => $request->attributes->get('request_id'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => __('Logged out'),
            'code' => 'OK',
            'request_id' => $request->attributes->get('request_id'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'code' => 'OK',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
                'roles' => $user->roleSlugs(),
                'creator' => $user->creatorProfile,
                'editor' => $user->editorProfile,
                'brand' => $user->brandProfile,
            ],
            'request_id' => $request->attributes->get('request_id'),
        ]);
    }
}
