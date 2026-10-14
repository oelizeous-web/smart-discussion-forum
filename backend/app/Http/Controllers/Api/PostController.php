<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Services\PostService;
use App\Models\Report;
use Illuminate\Http\Request;
use App\Services\ModerationService;
use App\Models\Topic;

class PostController extends Controller
{
    protected PostService $postService;

    public function __construct(PostService $postService)
    {
        $this->postService = $postService;
    }

// GET /api/topics/{topic}/posts
    public function index(Request $request, $topicId)
    {
        $topic = Topic::findOrFail((int) $topicId);
        $userId = $request->user()->id;

        $canAccess = !$topic->is_private
            || $topic->user_id === $userId
            || $topic->participants()
                ->where('user_id', $userId)
                ->wherePivot('status', 'approved')
                ->exists();

    if (!$canAccess) {
        return response()->json([
            'error'   => 'This topic is private. Request access to view its posts.',
            'message' => 'This topic is private. Request access to view its posts.',
        ], 403);
    }

        $posts = $this->postService->getPostsByTopic((int)$topicId);
        return PostResource::collection($posts);
    }

    // POST /api/topics/{topic}/posts
// POST /api/topics/{topic}/posts
    public function store(Request $request, $topicId, ModerationService $moderationService)
    {
        $validated = $request->validate([
            'content'     => 'required|string',
            'receiver_id' => 'nullable|integer|exists:users,id'
        ]);

        $post = $this->postService->createPost($validated, (int)$topicId);
        $moderationService->clearWarningsIfCompliant(auth()->user());

        return new PostResource($post);
    }

// --- ADD THIS ENTIRE METHOD ---
    // POST /api/posts/{post}/flag
    public function flag(Request $request, $postId)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        Report::create([
            'post_id' => (int)$postId,
            'reported_by' => auth()->id(),
            'reason' => $request->reason,
        ]);

        return response()->json([
            'message' => 'Message has been flagged and sent to the administrator for review.'
        ]);
    }
}