<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'gender' => ['sometimes', Rule::in(['male', 'female'])],
            'birth_date' => ['sometimes', 'date', 'before:today'],
            'body_weight_kg' => ['sometimes', 'numeric', 'min:20', 'max:400'],
            'fcm_token' => ['sometimes', 'nullable', 'string', 'max:512'],
            // Up to 3 pinned machines, in display order, for the public profile.
            'featured_machine_ids' => ['sometimes', 'nullable', 'array', 'max:3'],
            'featured_machine_ids.*' => ['integer', 'distinct', 'exists:machines,id'],
        ]);

        if (array_key_exists('body_weight_kg', $data)) {
            $data['body_weight_updated_at'] = now();
        }

        $user = $request->user();
        $user->update($data);

        return response()->json(['user' => AuthController::userPayload($user->fresh())]);
    }

    /**
     * Upload / replace the public profile avatar (MVP 3 #3).
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:5120'], // 5 MB
        ]);

        $user = $request->user();

        if ($user->avatar_path !== null) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        return response()->json(['user' => AuthController::userPayload($user->fresh())]);
    }
}
