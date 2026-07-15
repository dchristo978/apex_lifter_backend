<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\ResetPasswordCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** How long an emailed password-reset code stays valid. */
    private const RESET_CODE_TTL_MINUTES = 60;

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'birth_date' => ['required', 'date', 'before:today'],
            'body_weight_kg' => ['nullable', 'numeric', 'min:20', 'max:400'],
        ]);

        if (isset($data['body_weight_kg'])) {
            $data['body_weight_updated_at'] = now();
        }

        $user = User::create($data);

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    /**
     * Start a password reset: email a short numeric code to the account, if one
     * exists. The response is deliberately identical whether or not the email is
     * registered, so it can't be used to probe which emails have accounts.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user !== null) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Reuse Laravel's reset-token table, storing a hash of the code so a
            // leaked DB row can't be replayed. One live code per email.
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($code), 'created_at' => now()],
            );

            $user->notify(new ResetPasswordCode($code));
        }

        return response()->json([
            'message' => 'If that email is registered, a reset code has been sent.',
        ]);
    }

    /**
     * Complete a password reset with the emailed code, then sign the lifter in
     * on this device and revoke every other outstanding token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        $valid = $record !== null
            && Carbon::parse($record->created_at)
                ->addMinutes(self::RESET_CODE_TTL_MINUTES)
                ->isFuture()
            && Hash::check($data['code'], $record->token);

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => ['The reset code is invalid or has expired.'],
            ]);
        }

        $user = User::where('email', $data['email'])->firstOrFail();
        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        // Burn the code and sign out every existing session; issue a fresh token
        // for the device that just reset.
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
        $user->tokens()->delete();

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Permanently delete the authenticated lifter's account and their data.
     * Requires the current password so a stolen token can't wipe an account.
     * Row cascades handle sets/checkins/challenges/votes/notifications; avatar
     * and proof-video files aren't covered by the DB so they're removed here.
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Email atau password salah.'],
            ]);
        }

        if ($user->avatar_path !== null) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        foreach ($user->challengesMade->merge($user->challengesReceived) as $challenge) {
            Storage::disk('public')->deleteDirectory("challenges/{$challenge->id}");
        }

        // Sanctum tokens are polymorphic (no FK cascade), so clear them first.
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Account deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    public static function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'gender' => $user->gender,
            'birth_date' => $user->birth_date?->toDateString(),
            'age' => $user->age(),
            'age_bracket' => $user->ageBracket(),
            'body_weight_kg' => $user->body_weight_kg,
            'weight_class' => $user->weightClass(),
            'body_weight_updated_at' => $user->body_weight_updated_at?->toIso8601String(),
            'body_weight_stale' => $user->bodyWeightStale(),
            'avatar_url' => $user->avatarUrl(),
            'featured_machine_ids' => $user->featuredMachineIds(),
            'week_streak' => $user->weekStreak(),
        ];
    }
}
