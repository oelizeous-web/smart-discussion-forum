<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Report;
use App\Models\Group; 
use Illuminate\Support\Facades\DB;
use App\Services\ModerationService;

class AdminController extends Controller
{
    // GET /api/admin/users — Members tab
    public function users()
    {
        $users = User::role('student')->get();

        return response()->json([
            'success' => true,
            'data' => $users->map(fn ($user) => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'status'        => $user->status,
                'warning_count' => $user->warning_count,
                'roles'         => $user->getRoleNames(),
            ]),
        ]);
    }

    // GET /api/admin/dashboard
    public function dashboard()
    {
        $groupCount  = Group::count();
        $memberCount = User::role('student')->count(); // adjust if you want lecturers counted too

        $participated = DB::table('marks')->distinct('user_id')->count('user_id');
        $completionRate = $memberCount > 0 ? round(($participated / $memberCount) * 100) : 0;

        $topPoster = User::withCount('posts')->orderByDesc('posts_count')->first();

        $activity = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $activity[] = [
                'date'          => $date,
                'posts_created' => DB::table('posts')->whereDate('created_at', $date)->count(),
                'quiz_attempts' => DB::table('marks')->whereDate('created_at', $date)->distinct('user_id')->count('user_id'),
            ];
        }

        return response()->json([
            'success'         => true,
            'group_count'     => $groupCount,
            'member_count'    => $memberCount,
            'completion_rate' => $completionRate,
            'top_poster'      => ($topPoster && $topPoster->posts_count > 0) ? $topPoster->name : '—',
            'activity'        => $activity,
        ]);
    }

    // GET /api/admin/lecturers — Lecturers tab
    public function lecturers()
    {
        $lecturers = User::role('lecturer')->get();

        return response()->json([
            'success' => true,
            'data' => $lecturers->map(fn ($user) => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ]),
        ]);
    }

    // POST /api/admin/users/{id}/warn
        public function warnUser($id, ModerationService $moderationService)
    {
        $moderationService->warnUser($id);
        return response()->json(['success' => true, 'message' => 'Warning issued.']);
    }

    public function suspendUser($id, ModerationService $moderationService)
    {
        $moderationService->suspendUser($id);
        return response()->json(['success' => true, 'message' => 'User suspended.']);
    }

    public function reinstateUser($id, ModerationService $moderationService)
    {
        $moderationService->reinstateUser($id);
        return response()->json(['success' => true, 'message' => 'User reinstated.']);
    }

    // GET /api/admin/flags — Moderation tab
    public function flags()
    {
        $reports = Report::where('is_resolved', false)
            ->with(['post.topic', 'post.author', 'reporter'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reports->map(fn ($report) => [
                'id'            => $report->id,
                'post_content'  => $report->post->content ?? 'Post was deleted',
                'post_author'   => $report->post->author->name ?? 'Unknown',
                'reporter_name' => $report->reporter->name ?? 'Unknown',
                'reason'        => $report->reason,
                'topic_title'   => $report->post->topic->title ?? '',
            ]),
        ]);
    }

    // POST /api/admin/flags/{id}/resolve
    public function resolveFlag($id)
    {
        $report = Report::findOrFail($id);

        if ($report->post && $report->post->author) {
            $report->post->author->issueWarning();
            $report->post->delete();
        }

        $report->update(['is_resolved' => true]);

        return response()->json(['success' => true, 'message' => 'Flag resolved.']);
    }
}