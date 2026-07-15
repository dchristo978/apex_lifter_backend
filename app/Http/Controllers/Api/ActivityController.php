<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * Give kudos to an activity. Idempotent — a repeat is a no-op that still
     * returns the current count and state.
     */
    public function kudos(Request $request, Activity $activity): JsonResponse
    {
        $activity->kudos()->firstOrCreate(['user_id' => $request->user()->id]);

        return $this->kudosState($request, $activity);
    }

    /**
     * Remove the viewer's kudos. Idempotent.
     */
    public function unkudos(Request $request, Activity $activity): JsonResponse
    {
        $activity->kudos()->where('user_id', $request->user()->id)->delete();

        return $this->kudosState($request, $activity);
    }

    /**
     * Comments on an activity, oldest first (chronological thread).
     */
    public function comments(Request $request, Activity $activity): JsonResponse
    {
        $comments = $activity->comments()
            ->with('user:id,name,avatar_path')
            ->oldest()
            ->get();

        return response()->json([
            'comments' => $comments->map(fn (ActivityComment $c) => $this->presentComment($c, $request))->all(),
        ]);
    }

    /**
     * Post a comment on an activity.
     */
    public function comment(Request $request, Activity $activity): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:500'],
        ]);

        $comment = $activity->comments()->create([
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
        ]);

        $comment->load('user:id,name,avatar_path');

        return response()->json([
            'comment' => $this->presentComment($comment, $request),
        ], 201);
    }

    /**
     * Delete a comment. Allowed for the comment's author or the owner of the
     * activity it sits on.
     */
    public function deleteComment(Request $request, Activity $activity, ActivityComment $comment): JsonResponse
    {
        if ($comment->activity_id !== $activity->id) {
            abort(404);
        }

        $viewerId = $request->user()->id;

        if ($comment->user_id !== $viewerId && $activity->user_id !== $viewerId) {
            abort(403, 'Kamu hanya bisa menghapus komentarmu sendiri.');
        }

        $comment->delete();

        return response()->json(['deleted' => true]);
    }

    private function kudosState(Request $request, Activity $activity): JsonResponse
    {
        return response()->json([
            'kudos_count' => $activity->kudos()->count(),
            'viewer_kudoed' => $activity->kudos()->where('user_id', $request->user()->id)->exists(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentComment(ActivityComment $c, Request $request): array
    {
        return [
            'id' => $c->id,
            'body' => $c->body,
            'created_at' => $c->created_at?->toIso8601String(),
            'is_mine' => $c->user_id === $request->user()->id,
            'author' => $c->user === null ? null : [
                'id' => $c->user->id,
                'name' => $c->user->name,
                'avatar_url' => $c->user->avatarUrl(),
            ],
        ];
    }
}
